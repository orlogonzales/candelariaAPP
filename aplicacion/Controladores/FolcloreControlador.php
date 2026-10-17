<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Folclore\CategoriaDanza;
use Aplicacion\Folclore\EstadoActividadFolclorica;
use Aplicacion\Folclore\EstadoConfirmacionProgramacion;
use Aplicacion\Folclore\EstadoInscripcionConjunto;
use Aplicacion\Folclore\EstadoTurnoPresentacion;
use Aplicacion\Folclore\ProgramacionFolcloricaServicio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConjuntoFolcloricoRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\ProgramacionFolcloricaRepositorio;
use Aplicacion\Repositorios\TipoDanzaRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;
use Throwable;

/**
 * Controlador oficial para la administración de Programación Folclórica (Fase 3.1B).
 * Gestiona tipos de danza, agrupaciones, bloques, padrón de inscripciones por edición,
 * actividades, procedencia/versiones del rol y turnos de presentación.
 */
class FolcloreControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private ProgramacionFolcloricaServicio $folcloreServicio;
    private EdicionRepositorio $edicionRepo;
    private ContextoEdicionResolver $contextoResolver;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?ProgramacionFolcloricaServicio $folcloreServicio = null,
        ?EdicionRepositorio $edicionRepo = null,
        ?ContextoEdicionResolver $contextoResolver = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->edicionRepo = $edicionRepo ?? new EdicionRepositorio($this->pdo);
        $this->contextoResolver = $contextoResolver ?? new ContextoEdicionResolver($this->edicionRepo);

        if ($folcloreServicio !== null) {
            $this->folcloreServicio = $folcloreServicio;
        } else {
            $tipoDanzaRepo = new TipoDanzaRepositorio($this->pdo);
            $conjuntoRepo = new ConjuntoFolcloricoRepositorio($this->pdo);
            $progRepo = new ProgramacionFolcloricaRepositorio($this->pdo);
            $auditoriaRepo = new AuditoriaRepositorio($this->pdo);
            $authzServicio = new AutorizacionServicio(null, null, null, $auditoriaRepo, $this->pdo);

            $this->folcloreServicio = new ProgramacionFolcloricaServicio(
                tipoDanzaRepo: $tipoDanzaRepo,
                conjuntoRepo: $conjuntoRepo,
                programacionRepo: $progRepo,
                edicionRepo: $this->edicionRepo,
                authzServicio: $authzServicio,
                auditoriaRepo: $auditoriaRepo,
                pdo: $this->pdo
            );
        }
    }

    /**
     * GET /folclore
     * Renderiza la vista principal de Programación Folclórica con plantilla Alina.
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('folclore.programacion.ver', $contexto, false)
            && !$this->authzMiddleware->verificarPermiso('folclore.conjuntos.ver', $contexto, false)) {
            http_response_code(403);
            return \Nucleo\Http\Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Programación Folclórica',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con privilegios para consultar la programación folclórica.'
            ], 'principal');
        }

        if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
            http_response_code(403);
            return \Nucleo\Http\Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Programación Folclórica',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'Contexto organizacional ausente o inválido.'
            ], 'principal');
        }

        $contextoEdicion = $this->contextoResolver->resolverDesdeServidor($contexto, $_SERVER);
        $edicionActiva = $contextoEdicion->edicionIdTrabajo !== null
            ? $this->edicionRepo->buscarPorId($contextoEdicion->edicionIdTrabajo)
            : $this->edicionRepo->obtenerActual($contexto->organizacionId);

        return \Nucleo\Http\Vista::renderizar('folclore', [
            'titulo'          => 'Programación Folclórica | CandelariaAPP',
            'subtitulo'       => 'Gestión de Danzas, Agrupaciones, Edición y Rol Oficial',
            'tituloSeccion'   => 'Programación Folclórica',
            'seccionActiva'   => 'folclore',
            'edicionActiva'   => $edicionActiva,
            'scriptAdicional' => url_base('publico/js/folclore.js')
        ], 'principal');
    }

    // ==============================================================================
    // API: TIPOS DE DANZA
    // ==============================================================================

    public function listarTiposDanza(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.tipos_danza.ver');
            $tipos = $this->folcloreServicio->listarTiposDanza($contexto->organizacionId, $contexto);
            $datos = array_map(fn($t) => $t->aArreglo(), $tipos);

            return $this->responderExito($datos);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error al listar tipos de danza: ' . $e->getMessage(), 500);
        }
    }

    public function crearTipoDanza(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.tipos_danza.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $categoria = CategoriaDanza::tryFrom((string)($datos['categoria_origen'] ?? 'TRAJES_DE_LUCES'))
                ?? CategoriaDanza::TRAJES_DE_LUCES;

            $creado = $this->folcloreServicio->registrarTipoDanza(
                organizacionId: $contexto->organizacionId,
                codigo: (string)($datos['codigo'] ?? ''),
                nombre: (string)($datos['nombre'] ?? ''),
                categoria: $categoria,
                descripcion: isset($datos['descripcion']) ? (string)$datos['descripcion'] : null,
                contexto: $contexto
            );

            return $this->responderExito($creado->aArreglo(), 'Tipo de danza registrado exitosamente.', 201);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al registrar tipo de danza: ' . $e->getMessage(), 500);
        }
    }

    public function actualizarTipoDanza(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.tipos_danza.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $categoria = CategoriaDanza::tryFrom((string)($datos['categoria_origen'] ?? 'TRAJES_DE_LUCES'))
                ?? CategoriaDanza::TRAJES_DE_LUCES;

            $actualizado = $this->folcloreServicio->actualizarTipoDanza(
                organizacionId: $contexto->organizacionId,
                id: $id,
                nombre: (string)($datos['nombre'] ?? ''),
                categoria: $categoria,
                descripcion: isset($datos['descripcion']) ? (string)$datos['descripcion'] : null,
                activo: (bool)($datos['activo'] ?? true),
                contexto: $contexto
            );

            return $this->responderExito($actualizado->aArreglo(), 'Tipo de danza actualizado exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al actualizar tipo de danza: ' . $e->getMessage(), 500);
        }
    }

    // ==============================================================================
    // API: CONJUNTOS FOLCLÓRICOS
    // ==============================================================================

    public function listarConjuntos(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.conjuntos.ver');
            $tipoId = isset($_GET['tipo_danza_id']) && is_numeric($_GET['tipo_danza_id'])
                ? (int)$_GET['tipo_danza_id'] : null;

            $conjuntos = $this->folcloreServicio->listarConjuntos($contexto->organizacionId, $tipoId, $contexto);
            $datos = array_map(fn($c) => $c->aArreglo(), $conjuntos);

            return $this->responderExito($datos);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error al listar conjuntos: ' . $e->getMessage(), 500);
        }
    }

    public function obtenerConjunto(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.conjuntos.ver');
            $conjunto = $this->folcloreServicio->obtenerConjuntoPorId($id, $contexto->organizacionId, $contexto);
            if ($conjunto === null) {
                return $this->responderError('El conjunto folclórico no existe o no pertenece a su organización.', 404);
            }

            return $this->responderExito($conjunto->aArreglo());
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error al consultar conjunto: ' . $e->getMessage(), 500);
        }
    }

    public function crearConjunto(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.conjuntos.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $creado = $this->folcloreServicio->registrarConjunto(
                organizacionId: $contexto->organizacionId,
                tipoDanzaId: (int)($datos['tipo_danza_id'] ?? 0),
                codigo: (string)($datos['codigo'] ?? ''),
                nombre: (string)($datos['nombre'] ?? ''),
                sigla: isset($datos['sigla']) ? (string)$datos['sigla'] : null,
                fechaFundacion: isset($datos['fecha_fundacion']) ? (string)$datos['fecha_fundacion'] : null,
                nombrePresidente: isset($datos['nombre_presidente']) ? (string)$datos['nombre_presidente'] : null,
                telefonoContacto: isset($datos['telefono_contacto']) ? (string)$datos['telefono_contacto'] : null,
                colorEmblematicoHex: isset($datos['color_emblematico_hex']) ? (string)$datos['color_emblematico_hex'] : null,
                resenaHistorica: isset($datos['resena_historica']) ? (string)$datos['resena_historica'] : null,
                contexto: $contexto
            );

            return $this->responderExito($creado->aArreglo(), 'Conjunto folclórico registrado exitosamente.', 201);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al crear conjunto: ' . $e->getMessage(), 500);
        }
    }

    public function actualizarConjunto(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.conjuntos.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $actualizado = $this->folcloreServicio->actualizarConjunto(
                organizacionId: $contexto->organizacionId,
                id: $id,
                tipoDanzaId: (int)($datos['tipo_danza_id'] ?? 0),
                nombre: (string)($datos['nombre'] ?? ''),
                descripcion: isset($datos['resena_historica']) ? (string)$datos['resena_historica'] : null,
                anioFundacion: isset($datos['anio_fundacion']) && is_numeric($datos['anio_fundacion']) ? (int)$datos['anio_fundacion'] : null,
                ciudadSede: isset($datos['ciudad_sede']) ? (string)$datos['ciudad_sede'] : null,
                activo: (bool)($datos['activo'] ?? true),
                contexto: $contexto
            );

            return $this->responderExito($actualizado->aArreglo(), 'Conjunto folclórico actualizado exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al actualizar conjunto: ' . $e->getMessage(), 500);
        }
    }

    // ==============================================================================
    // API: BLOQUES Y FILIALES
    // ==============================================================================

    public function listarBloques(int $conjuntoId): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.conjuntos.ver');
            $bloques = $this->folcloreServicio->listarBloques($contexto->organizacionId, $conjuntoId, $contexto);
            $datos = array_map(fn($b) => $b->aArreglo(), $bloques);

            return $this->responderExito($datos);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error al listar bloques: ' . $e->getMessage(), 500);
        }
    }

    public function crearBloque(int $conjuntoId): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.conjuntos.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $creado = $this->folcloreServicio->registrarBloque(
                organizacionId: $contexto->organizacionId,
                conjuntoId: $conjuntoId,
                codigo: (string)($datos['codigo'] ?? ''),
                nombre: (string)($datos['nombre'] ?? ''),
                responsableNombre: isset($datos['responsable_nombre']) ? (string)$datos['responsable_nombre'] : null,
                telefonoContacto: isset($datos['telefono_contacto']) ? (string)$datos['telefono_contacto'] : null,
                contexto: $contexto
            );

            return $this->responderExito($creado->aArreglo(), 'Bloque registrado exitosamente.', 201);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al crear bloque: ' . $e->getMessage(), 500);
        }
    }

    public function actualizarBloque(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.conjuntos.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $actualizado = $this->folcloreServicio->actualizarBloque(
                organizacionId: $contexto->organizacionId,
                id: $id,
                nombre: (string)($datos['nombre'] ?? ''),
                responsableNombre: isset($datos['responsable_nombre']) ? (string)$datos['responsable_nombre'] : null,
                telefonoContacto: isset($datos['telefono_contacto']) ? (string)$datos['telefono_contacto'] : null,
                activo: (bool)($datos['activo'] ?? true),
                contexto: $contexto
            );

            return $this->responderExito($actualizado->aArreglo(), 'Bloque actualizado exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al actualizar bloque: ' . $e->getMessage(), 500);
        }
    }

    // ==============================================================================
    // API: INSCRIPCIONES POR EDICIÓN
    // ==============================================================================

    public function listarInscripciones(int $edicionId): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.conjuntos.ver');
            $inscritos = $this->folcloreServicio->listarInscritosEnEdicion($contexto->organizacionId, $edicionId, $contexto);
            $datos = array_map(fn($i) => $i->aArreglo(), $inscritos);

            return $this->responderExito($datos);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error al listar inscripciones: ' . $e->getMessage(), 500);
        }
    }

    public function inscribirConjunto(int $edicionId): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.inscripciones.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $creado = $this->folcloreServicio->inscribirConjuntoEnEdicion(
                organizacionId: $contexto->organizacionId,
                edicionId: $edicionId,
                conjuntoId: (int)($datos['conjunto_id'] ?? 0),
                numeroRegistroOficial: isset($datos['numero_registro_oficial']) ? (string)$datos['numero_registro_oficial'] : null,
                delegado: isset($datos['nombre_delegado_edicion']) ? (string)$datos['nombre_delegado_edicion'] : null,
                telefonoDelegado: isset($datos['telefono_delegado_edicion']) ? (string)$datos['telefono_delegado_edicion'] : null,
                danzarinesEstimados: (int)($datos['cantidad_estimada_danzarines'] ?? 0),
                cantidadBandas: (int)($datos['cantidad_bandas'] ?? 1),
                observaciones: isset($datos['observaciones']) ? (string)$datos['observaciones'] : null,
                contexto: $contexto
            );

            return $this->responderExito($creado->aArreglo(), 'Conjunto inscrito formalmente en la edición.', 201);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al inscribir conjunto: ' . $e->getMessage(), 500);
        }
    }

    public function actualizarInscripcion(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.inscripciones.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $estado = EstadoInscripcionConjunto::tryFrom((string)($datos['estado'] ?? 'INSCRITO'))
                ?? EstadoInscripcionConjunto::INSCRITO;

            $actualizado = $this->folcloreServicio->actualizarInscripcion(
                organizacionId: $contexto->organizacionId,
                id: $id,
                numeroRegistro: isset($datos['numero_registro_oficial']) ? (string)$datos['numero_registro_oficial'] : null,
                delegado: isset($datos['nombre_delegado_edicion']) ? (string)$datos['nombre_delegado_edicion'] : null,
                telefono: isset($datos['telefono_delegado_edicion']) ? (string)$datos['telefono_delegado_edicion'] : null,
                danzarines: (int)($datos['cantidad_estimada_danzarines'] ?? 0),
                bandas: (int)($datos['cantidad_bandas'] ?? 1),
                estado: $estado,
                observaciones: isset($datos['observaciones']) ? (string)$datos['observaciones'] : null,
                contexto: $contexto
            );

            return $this->responderExito($actualizado->aArreglo(), 'Inscripción actualizada exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al actualizar inscripción: ' . $e->getMessage(), 500);
        }
    }

    // ==============================================================================
    // API: ACTIVIDADES FOLCLÓRICAS
    // ==============================================================================

    public function listarActividades(int $edicionId): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.ver');
            $repo = new ProgramacionFolcloricaRepositorio($this->pdo);
            $actividades = $repo->listarActividadesPorEdicion($edicionId, $contexto->organizacionId);
            $datos = array_map(fn($a) => $a->aArreglo(), $actividades);

            return $this->responderExito($datos);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error al listar actividades: ' . $e->getMessage(), 500);
        }
    }

    public function crearActividad(int $edicionId): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $creado = $this->folcloreServicio->registrarActividad(
                organizacionId: $contexto->organizacionId,
                edicionId: $edicionId,
                codigo: (string)($datos['codigo'] ?? ''),
                titulo: (string)($datos['titulo'] ?? ''),
                fechaInicio: (string)($datos['fecha_inicio'] ?? ''),
                fechaFin: (string)($datos['fecha_fin'] ?? ''),
                lugarPrincipal: (string)($datos['lugar_principal'] ?? ''),
                contexto: $contexto
            );

            return $this->responderExito($creado->aArreglo(), 'Actividad folclórica registrada exitosamente.', 201);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al crear actividad: ' . $e->getMessage(), 500);
        }
    }

    public function actualizarActividad(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $estado = EstadoActividadFolclorica::tryFrom((string)($datos['estado'] ?? 'PROGRAMADA'))
                ?? EstadoActividadFolclorica::PROGRAMADA;

            $actualizado = $this->folcloreServicio->actualizarActividad(
                organizacionId: $contexto->organizacionId,
                id: $id,
                titulo: (string)($datos['titulo'] ?? ''),
                fechaInicio: (string)($datos['fecha_inicio'] ?? ''),
                fechaFin: (string)($datos['fecha_fin'] ?? ''),
                lugarPrincipal: (string)($datos['lugar_principal'] ?? ''),
                estado: $estado,
                contexto: $contexto
            );

            return $this->responderExito($actualizado->aArreglo(), 'Actividad folclórica actualizada exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al actualizar actividad: ' . $e->getMessage(), 500);
        }
    }

    // ==============================================================================
    // API: PROGRAMACIONES OFICIALES Y PROCEDENCIA
    // ==============================================================================

    public function listarVersiones(int $actividadId): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.ver');
            $versiones = $this->folcloreServicio->listarVersionesProgramacion($contexto->organizacionId, $actividadId, $contexto);
            $datos = array_map(fn($v) => $v->aArreglo(), $versiones);

            return $this->responderExito($datos);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error al listar versiones: ' . $e->getMessage(), 500);
        }
    }

    public function publicarVersion(int $actividadId): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $estadoInput = (string)($datos['estado_confirmacion'] ?? 'PRELIMINAR');
            $estado = EstadoConfirmacionProgramacion::tryFrom($estadoInput)
                ?? EstadoConfirmacionProgramacion::PRELIMINAR;

            // Si intenta marcar como OFICIAL_CONFIRMADO directamente en la creación, requiere permiso de aprobación
            if ($estado === EstadoConfirmacionProgramacion::OFICIAL_CONFIRMADO) {
                if (!$this->authzMiddleware->verificarPermiso('folclore.programacion.aprobar', $contexto, false)) {
                    throw new AccesoDenegadoExcepcion('Se requiere el permiso de aprobación formal (folclore.programacion.aprobar) para publicar como OFICIAL_CONFIRMADO.');
                }
            }

            $edicionId = (int)($datos['edicion_id'] ?? 0);

            $creado = $this->folcloreServicio->publicarProgramacionOficial(
                organizacionId: $contexto->organizacionId,
                edicionId: $edicionId,
                actividadId: $actividadId,
                fuenteOrigen: (string)($datos['fuente_origen'] ?? 'FRFCP Puno'),
                fechaEmisionFuente: (string)($datos['fecha_emision_fuente'] ?? date('Y-m-d')),
                etiquetaVersion: (string)($datos['etiqueta_version'] ?? 'Rol de Presentación'),
                estado: $estado,
                documentoUrl: isset($datos['documento_referencia_url']) ? (string)$datos['documento_referencia_url'] : null,
                notas: isset($datos['notas_version']) ? (string)$datos['notas_version'] : null,
                contexto: $contexto
            );

            return $this->responderExito($creado->aArreglo(), 'Versión de programación oficial registrada exitosamente.', 201);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al publicar versión: ' . $e->getMessage(), 500);
        }
    }

    public function oficializarVersion(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.aprobar');
            $this->validarCsrf($contexto);

            $exito = $this->folcloreServicio->oficializarVersionExistente($contexto->organizacionId, $id, $contexto);
            if (!$exito) {
                return $this->responderError('No fue posible oficializar la versión solicitada.', 404);
            }

            return $this->responderExito(['id' => $id], 'La versión ha sido oficializada, aprobada y activada como rol oficial vigente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al oficializar versión: ' . $e->getMessage(), 500);
        }
    }

    // ==============================================================================
    // API: TURNOS Y ORDEN DE PRESENTACIÓN
    // ==============================================================================

    public function listarTurnos(int $programacionId): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.ver');
            $turnos = $this->folcloreServicio->obtenerRolPresentacion($contexto->organizacionId, $programacionId, $contexto);
            $datos = array_map(fn($t) => $t->aArreglo(), $turnos);

            return $this->responderExito($datos);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error al listar turnos: ' . $e->getMessage(), 500);
        }
    }

    public function asignarTurno(int $programacionId): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $creado = $this->folcloreServicio->asignarTurnoPresentacion(
                organizacionId: $contexto->organizacionId,
                programacionOficialId: $programacionId,
                conjuntoEdicionId: (int)($datos['conjunto_edicion_id'] ?? 0),
                ordenSorteo: (int)($datos['orden_sorteo'] ?? 1),
                horaEstimadaPartida: (string)($datos['hora_estimada_partida'] ?? '08:00:00'),
                horaEstimadaLlegada: isset($datos['hora_estimada_llegada']) ? (string)$datos['hora_estimada_llegada'] : null,
                puntoPartida: isset($datos['punto_partida_especifico']) ? (string)$datos['punto_partida_especifico'] : null,
                observaciones: isset($datos['observaciones']) ? (string)$datos['observaciones'] : null,
                contexto: $contexto
            );

            return $this->responderExito($creado->aArreglo(), 'Turno de presentación asignado exitosamente.', 201);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al asignar turno: ' . $e->getMessage(), 500);
        }
    }

    public function actualizarTurno(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $estadoTurno = EstadoTurnoPresentacion::tryFrom((string)($datos['estado_turno'] ?? 'PROGRAMADO'))
                ?? EstadoTurnoPresentacion::PROGRAMADO;

            $actualizado = $this->folcloreServicio->actualizarTurno(
                organizacionId: $contexto->organizacionId,
                turnoId: $id,
                ordenSorteo: (int)($datos['orden_sorteo'] ?? 1),
                horaEstimadaPartida: (string)($datos['hora_estimada_partida'] ?? '08:00:00'),
                horaEstimadaLlegada: isset($datos['hora_estimada_llegada']) ? (string)$datos['hora_estimada_llegada'] : null,
                puntoPartida: isset($datos['punto_partida_especifico']) ? (string)$datos['punto_partida_especifico'] : null,
                estadoTurno: $estadoTurno,
                horaRealPartida: isset($datos['hora_real_partida']) ? (string)$datos['hora_real_partida'] : null,
                horaRealFin: isset($datos['hora_real_fin']) ? (string)$datos['hora_real_fin'] : null,
                observaciones: isset($datos['observaciones']) ? (string)$datos['observaciones'] : null,
                contexto: $contexto
            );

            return $this->responderExito($actualizado->aArreglo(), 'Turno de presentación actualizado exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al actualizar turno: ' . $e->getMessage(), 500);
        }
    }

    public function eliminarTurno(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.gestionar');
            $this->validarCsrf($contexto);

            $exito = $this->folcloreServicio->eliminarTurno($contexto->organizacionId, $id, $contexto);
            if (!$exito) {
                return $this->responderError('No fue posible eliminar el turno especificado.', 404);
            }

            return $this->responderExito(['id' => $id], 'Turno de presentación eliminado exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al eliminar turno: ' . $e->getMessage(), 500);
        }
    }

    // ==============================================================================
    // API: VINCULACIÓN CON SALIDAS DE OPERACIÓN
    // ==============================================================================

    public function vincularSalidaOperativa(int $turnoId): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $salidaId = (int)($datos['salida_id'] ?? 0);
            $desvincular = (bool)($datos['desvincular'] ?? false);

            $exito = $this->folcloreServicio->vincularSalidaAOrdenPresentacion(
                organizacionId: $contexto->organizacionId,
                salidaId: $salidaId,
                ordenPresentacionId: $desvincular ? null : $turnoId,
                contexto: $contexto
            );

            if (!$exito) {
                return $this->responderError('No fue posible actualizar el vínculo de la salida operativa.', 404);
            }

            return $this->responderExito(
                ['salida_id' => $salidaId, 'orden_presentacion_id' => $desvincular ? null : $turnoId],
                $desvincular ? 'Salida desvinculada del turno folclórico.' : 'Salida vinculada exitosamente al turno de presentación.'
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al vincular salida operativa: ' . $e->getMessage(), 500);
        }
    }

    // ==============================================================================
    // API: SEGUIMIENTO OPERATIVO Y COBERTURA AUDIOVISUAL (FASE 3.1C - DT-V11-008)
    // ==============================================================================

    public function obtenerTableroOperativo(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.ver');

            $edicionId = isset($_GET['edicion_id']) && is_numeric($_GET['edicion_id'])
                ? (int)$_GET['edicion_id']
                : null;

            if ($edicionId === null || $edicionId <= 0) {
                $edicionActiva = $this->edicionRepo->obtenerActual($contexto->organizacionId);
                $edicionId = $edicionActiva?->id ?? 0;
            }

            if ($edicionId <= 0) {
                return $this->responderError('No se especificó una edición válida ni existe una edición activa configurada.', 400);
            }

            $actividadId = isset($_GET['actividad_id']) && is_numeric($_GET['actividad_id'])
                ? (int)$_GET['actividad_id'] : null;
            $conjuntoId = isset($_GET['conjunto_id']) && is_numeric($_GET['conjunto_id'])
                ? (int)$_GET['conjunto_id'] : null;
            $filtroEstado = isset($_GET['estado']) && !empty($_GET['estado'])
                ? (string)$_GET['estado'] : null;
            $personaId = isset($_GET['persona_id']) && is_numeric($_GET['persona_id'])
                ? (int)$_GET['persona_id'] : null;

            $datos = $this->folcloreServicio->obtenerTableroOperativo(
                organizacionId: $contexto->organizacionId,
                edicionId: $edicionId,
                actividadId: $actividadId,
                conjuntoId: $conjuntoId,
                filtroEstado: $filtroEstado,
                personaAsignadaId: $personaId,
                contexto: $contexto
            );

            return $this->responderExito($datos);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al obtener datos del tablero operativo: ' . $e->getMessage(), 500);
        }
    }

    public function actualizarEstadoOperativoTurno(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('folclore.programacion.gestionar');
            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $nuevoEstado = (string)($datos['estado_turno'] ?? $datos['estado'] ?? '');
            if (empty($nuevoEstado)) {
                return $this->responderError('Debe especificar un estado operativo válido.', 422);
            }

            $horaRealPartida = isset($datos['hora_real_partida']) && !empty($datos['hora_real_partida'])
                ? (string)$datos['hora_real_partida'] : null;
            $horaRealFin = isset($datos['hora_real_fin']) && !empty($datos['hora_real_fin'])
                ? (string)$datos['hora_real_fin'] : null;
            $observaciones = isset($datos['observaciones'])
                ? (string)$datos['observaciones'] : null;
            $retrasoMinutos = isset($datos['retraso_minutos']) && is_numeric($datos['retraso_minutos'])
                ? (int)$datos['retraso_minutos'] : null;
            $versionBloqueo = isset($datos['version_bloqueo']) && is_numeric($datos['version_bloqueo'])
                ? (int)$datos['version_bloqueo'] : 1;

            $actualizado = $this->folcloreServicio->actualizarEstadoOperativoTurno(
                organizacionId: $contexto->organizacionId,
                turnoId: $id,
                nuevoEstadoEntrada: $nuevoEstado,
                horaRealPartida: $horaRealPartida,
                horaRealFin: $horaRealFin,
                observaciones: $observaciones,
                retrasoMinutos: $retrasoMinutos,
                versionBloqueo: $versionBloqueo,
                contexto: $contexto
            );

            return $this->responderExito($actualizado->aArreglo(), 'Estado operativo del turno actualizado exitosamente.');
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderError($e->getMessage(), 409);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al actualizar estado operativo del turno: ' . $e->getMessage(), 500);
        }
    }

    // ==============================================================================
    // MÉTODOS AUXILIARES Y DE SEGURIDAD
    // ==============================================================================

    private function verificarSesionYPermiso(string $permiso): ContextoOperacion
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null) {
            throw new AccesoDenegadoExcepcion('Sesión no autenticada o token inválido.', null, 401);
        }

        if (!$this->authzMiddleware->verificarPermiso($permiso, $contexto, true)) {
            throw new AccesoDenegadoExcepcion("Permiso insuficiente: Se requiere '{$permiso}' para esta operación.", null, 403);
        }

        if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
            throw new AccesoDenegadoExcepcion('Contexto organizacional ausente o inválido.', null, 403);
        }

        return $contexto;
    }

    private function validarCsrf(ContextoOperacion $contexto): void
    {
        $post = $_POST;
        $tokenEsperado = $_SESSION['_token_csrf'] ?? null;
        $tokenRecibido = ProtectorCsrf::extraerTokenDePeticion($post, $_SERVER);

        if ($tokenEsperado === null || $tokenRecibido === null || !ProtectorCsrf::validarToken($tokenEsperado, $tokenRecibido)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            throw new AccesoDenegadoExcepcion('Token de seguridad CSRF ausente o inválido.', null, 403);
        }
    }

    private function obtenerPayloadJson(): array
    {
        $cuerpo = file_get_contents('php://input');
        if (empty($cuerpo)) {
            return $_POST;
        }

        $decodificado = json_decode($cuerpo, true);
        if (!is_array($decodificado)) {
            return $_POST;
        }

        return array_merge($_POST, $decodificado);
    }

    private function responderExito(mixed $datos = null, string $mensaje = 'Operación exitosa', int $codigo = 200): string
    {
        $GLOBALS['__ultimo_codigo_http_api'] = $codigo;
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: application/json; charset=utf-8');
        }
        return json_encode([
            'exito'   => true,
            'mensaje' => $mensaje,
            'datos'   => $datos,
            'codigo'  => $codigo,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function responderError(string $mensaje, int $codigo = 400, array $detalles = []): string
    {
        $GLOBALS['__ultimo_codigo_http_api'] = $codigo;
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: application/json; charset=utf-8');
        }
        return json_encode([
            'exito'    => false,
            'mensaje'  => $mensaje,
            'codigo'   => $codigo,
            'detalles' => $detalles,
        ], JSON_UNESCAPED_UNICODE);
    }
}
