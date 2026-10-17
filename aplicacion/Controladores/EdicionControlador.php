<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Ediciones\CierreEdicionServicio;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Ediciones\EdicionServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Reportes\ReporteCierreServicio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;
use Throwable;

/**
 * Controlador oficial para la gestión de Ediciones Candelaria,
 * selector global y resolución de contexto de edición (F2.1B).
 */
class EdicionControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private EdicionServicio $edicionServicio;
    private EdicionRepositorio $edicionRepo;
    private ContextoEdicionResolver $contextoResolver;
    private CierreEdicionServicio $cierreServicio;
    private ReporteCierreServicio $reporteServicio;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?EdicionServicio $edicionServicio = null,
        ?EdicionRepositorio $edicionRepo = null,
        ?ContextoEdicionResolver $contextoResolver = null,
        ?CierreEdicionServicio $cierreServicio = null,
        ?ReporteCierreServicio $reporteServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->edicionRepo = $edicionRepo ?? new EdicionRepositorio($this->pdo);

        $orgRepo = new OrganizacionRepositorio($this->pdo);
        $rolRepo = new RolRepositorio($this->pdo);
        $permRepo = new PermisoRepositorio($this->pdo);
        $usrRepo = new UsuarioRepositorio($this->pdo);
        $auditoriaRepo = new AuditoriaRepositorio($this->pdo);

        if ($edicionServicio !== null) {
            $this->edicionServicio = $edicionServicio;
        } else {
            $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $usrRepo, $auditoriaRepo, $this->pdo);
            $this->edicionServicio = new EdicionServicio($this->edicionRepo, $orgRepo, $authzServicio, $auditoriaRepo, $this->pdo);
        }

        $this->cierreServicio = $cierreServicio ?? new CierreEdicionServicio($this->pdo, $auditoriaRepo);
        $this->reporteServicio = $reporteServicio ?? new ReporteCierreServicio($this->pdo);
        $this->contextoResolver = $contextoResolver ?? new ContextoEdicionResolver($this->edicionRepo);
    }

    /**
     * GET /ediciones
     * Renderiza la vista principal de gestión de ediciones con DataTable asíncrona.
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('ediciones.ver', $contexto, false)) {
            http_response_code(403);
            return \Nucleo\Http\Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Ediciones Candelaria',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (ediciones.ver) para consultar las ediciones.'
            ], 'principal');
        }

        if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
            http_response_code(403);
            return \Nucleo\Http\Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Ediciones Candelaria',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'Contexto organizacional ausente o inválido.'
            ], 'principal');
        }

        return \Nucleo\Http\Vista::renderizar('ediciones', [
            'titulo'          => 'Ediciones Candelaria | CandelariaAPP',
            'subtitulo'       => 'Gestión de Ediciones y Ciclo de Vida',
            'tituloSeccion'   => 'Ediciones Candelaria',
            'seccionActiva'   => 'ediciones',
            'scriptAdicional' => url_base('publico/js/ediciones.js')
        ], 'principal');
    }

    /**
     * GET /api/v1/ediciones
     * Retorna el listado de ediciones del tenant.
     */
    /**
     * GET /api/v1/ediciones
     * Retorna el listado de ediciones del tenant.
     */
    public function listar(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.ver');

            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $ediciones = $this->edicionServicio->listar($contexto->organizacionId, $contexto);
            $datos = array_map(fn($e) => $e->aArreglo(), $ediciones);

            return $this->responderExito($datos);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al obtener catálogo de ediciones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/ediciones/{id}
     * Retorna el detalle de una edición por su ID asegurando Anti-IDOR.
     */
    public function detalle(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.ver');

            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $edicion = $this->edicionServicio->obtener($id, $contexto->organizacionId, $contexto);
            if ($edicion === null) {
                return $this->responderError('La edición solicitada no existe o no pertenece a la organización.', 404);
            }

            return $this->responderExito($edicion->aArreglo());
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al consultar detalle de edición.', 500);
        }
    }

    /**
     * POST /api/v1/ediciones
     * Da de alta una nueva edición Candelaria.
     */
    public function crear(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.crear');

            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $nuevaEdicion = $this->edicionServicio->crear($contexto->organizacionId, [
                'codigo'       => (string) ($datos['codigo'] ?? ''),
                'nombre'       => (string) ($datos['nombre'] ?? ''),
                'anio'         => (int) ($datos['anio'] ?? 0),
                'fecha_inicio' => (string) ($datos['fecha_inicio'] ?? ''),
                'fecha_fin'    => (string) ($datos['fecha_fin'] ?? ''),
                'descripcion'  => isset($datos['descripcion']) ? (string) $datos['descripcion'] : null,
                'es_actual'    => false, // Nueva edición nace siempre como no actual institucional
            ], $contexto);

            return $this->responderExito(
                $nuevaEdicion->aArreglo(),
                'Edición Candelaria registrada exitosamente.',
                201
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al dar de alta la edición.', 500);
        }
    }

    /**
     * PUT /api/v1/ediciones/{id}
     * Modifica atributos mutables de una edición con concurrencia optimista.
     */
    public function actualizar(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.editar');

            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $actualizadoEnEsperado = isset($datos['actualizado_en_esperado'])
                ? (string) $datos['actualizado_en_esperado']
                : null;

            $edicionActualizada = $this->edicionServicio->actualizar(
                $id,
                $contexto->organizacionId,
                $datos,
                $contexto,
                $actualizadoEnEsperado
            );

            return $this->responderExito(
                $edicionActualizada->aArreglo(),
                'Edición actualizada exitosamente.'
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderError($e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al actualizar la edición.', 500);
        }
    }

    /**
     * POST /api/v1/ediciones/{id}/estado
     * Transiciona el estado del ciclo de vida de una edición.
     */
    public function cambiarEstado(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.cambiar_estado');

            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $nuevoEstado = (string) ($datos['nuevo_estado'] ?? '');
            $motivo = isset($datos['motivo']) 
                ? (string) $datos['motivo'] 
                : (isset($datos['motivo_retroceso']) ? (string) $datos['motivo_retroceso'] : null);
            $actualizadoEnEsperado = isset($datos['actualizado_en_esperado'])
                ? (string) $datos['actualizado_en_esperado']
                : null;

            $edicion = $this->edicionServicio->cambiarEstado(
                $id,
                $contexto->organizacionId,
                $nuevoEstado,
                $contexto,
                $motivo,
                $actualizadoEnEsperado
            );

            return $this->responderExito(
                $edicion->aArreglo(),
                "Fase de la edición actualizada exitosamente a '{$edicion->estado->etiqueta()}'."
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderError($e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al cambiar estado de la edición.', 500);
        }
    }

    /**
     * POST /api/v1/ediciones/{id}/seleccionar-actual
     * Designa formalmente una edición como la oficial activa de la organización.
     */
    public function seleccionarActual(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.seleccionar_actual');

            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $this->validarCsrf($contexto);

            $edicion = $this->edicionServicio->establecerActual($id, $contexto->organizacionId, $contexto);

            return $this->responderExito(
                $edicion->aArreglo(),
                "La edición '{$edicion->nombre}' fue establecida como la edición oficial de la organización."
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al designar edición actual.', 500);
        }
    }

    /**
     * GET /api/v1/contexto/edicion
     * Resuelve el contexto de edición para el frontend (edición activa y catálogo disponible).
     */
    public function contextoActual(): string
    {
        try {
            $contexto = $this->resolverContextoAutenticado();

            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            // Resuelve contexto aplicando: Header explícito -> Fallback es_actual -> Ausente
            $contextoResuelto = $this->contextoResolver->resolverDesdeServidor($contexto, $_SERVER);

            $edicionTrabajo = null;
            if ($contextoResuelto->edicionTrabajoId !== null) {
                $edicionTrabajo = $this->edicionRepo->buscarPorId($contextoResuelto->edicionTrabajoId);
            }

            $ediciones = $this->edicionRepo->listarPorOrganizacion($contexto->organizacionId);
            $disponibles = array_map(fn($e) => [
                'id'        => $e->id,
                'codigo'    => $e->codigo,
                'nombre'    => $e->nombre,
                'anio'      => $e->anio,
                'estado'    => $e->estado->value,
                'estado_etiqueta' => $e->estado->etiqueta(),
                'es_actual' => $e->esActual,
            ], $ediciones);

            return $this->responderExito([
                'edicion_trabajo_id' => $contextoResuelto->edicionTrabajoId,
                'origen_edicion'     => $contextoResuelto->origenEdicion,
                'origen'             => $contextoResuelto->origenEdicion,
                'edicion_trabajo'    => $edicionTrabajo ? $edicionTrabajo->aArreglo() : null,
                'ediciones'          => $disponibles,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 400);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al resolver contexto de edición.', 500);
        }
    }

    /**
     * GET /api/v1/ediciones/{id}/diagnostico-cierre
     * Inspecciona ventas, pagos, vouchers, operaciones y entregas antes de asentar el cierre.
     */
    public function diagnosticoCierre(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.ver');
            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $diagnostico = $this->cierreServicio->generarDiagnostico($contexto->organizacionId, $id);

            return $this->responderExito(
                $diagnostico,
                'Diagnóstico de cierre de festividad generado exitosamente.'
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al generar diagnóstico de cierre: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/ediciones/{id}/cerrar
     * Asienta formalmente el acta de cierre administrativo y financiero.
     */
    public function ejecutarCierre(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.cerrar');
            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $tipoCierre = (string) ($datos['tipo_cierre'] ?? 'DEFINITIVO');
            $justificacion = isset($datos['justificacion']) ? (string) $datos['justificacion'] : null;
            $autorizadorId = isset($datos['autorizador_id']) ? (int) $datos['autorizador_id'] : null;
            $versionBloqueo = isset($datos['version_bloqueo']) ? (int) $datos['version_bloqueo'] : 1;

            $resultado = $this->cierreServicio->ejecutarCierre(
                organizacionId: $contexto->organizacionId,
                edicionId: $id,
                usuarioId: (int) $contexto->usuarioId,
                tipoCierre: $tipoCierre,
                justificacion: $justificacion,
                autorizadorId: $autorizadorId,
                versionBloqueoEsperada: $versionBloqueo,
                contexto: $contexto
            );

            return $this->responderExito(
                $resultado,
                "La festividad ha sido cerrada formalmente bajo modalidad {$tipoCierre}.",
                201
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderError($e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al ejecutar el cierre de la edición: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/ediciones/{id}/reabrir
     * Procedimiento excepcional de reversibilidad / reapertura auditada de una edición cerrada.
     */
    public function reabrirExcepcional(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.reabrir_excepcional');
            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $this->validarCsrf($contexto);
            $datos = $this->obtenerPayloadJson();

            $motivo = (string) ($datos['motivo_excepcional'] ?? '');
            $autorizadorId = (int) ($datos['autorizado_por_usuario_id'] ?? 0);
            $claveConfirmacion = !empty($datos['clave_confirmacion_autorizador']) ? (string)$datos['clave_confirmacion_autorizador'] : null;
            $nuevoEstado = (string) ($datos['nuevo_estado'] ?? 'POSTPRODUCCION_ENTREGA');

            $resultado = $this->cierreServicio->reabrirEdicionExcepcional(
                organizacionId: $contexto->organizacionId,
                edicionId: $id,
                usuarioSolicitanteId: (int) $contexto->usuarioId,
                usuarioAutorizadorId: $autorizadorId,
                motivoExcepcional: $motivo,
                claveConfirmacionAutorizador: $claveConfirmacion,
                nuevoEstado: $nuevoEstado,
                contexto: $contexto
            );

            return $this->responderExito(
                $resultado,
                'Reapertura excepcional de la festividad autorizada y registrada con éxito.'
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error al procesar la reapertura excepcional: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/ediciones/{id}/cierre
     * Retorna el acta oficial de cierre vigente con su hash criptográfico.
     */
    public function consultarCierre(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.ver');
            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $cierre = $this->cierreServicio->obtenerCierreVigente($contexto->organizacionId, $id);
            if (!$cierre) {
                return $this->responderError('La edición no cuenta con un cierre vigente asentado.', 404);
            }

            return $this->responderExito(
                $cierre,
                'Acta oficial de cierre obtenida exitosamente.'
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error al consultar el cierre de la edición.', 500);
        }
    }

    /**
     * GET /api/v1/ediciones/{id}/cierre/verificar-integridad
     * Audita el sellado HMAC del acta y snapshot de cierre contrastándolo con la clave del sistema.
     */
    public function verificarIntegridad(int $id): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.ver');
            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $cierre = $this->cierreServicio->obtenerCierreVigente($contexto->organizacionId, $id);
            if (!$cierre) {
                return $this->responderError('La edición no cuenta con un cierre vigente para verificar.', 404);
            }

            $resultado = $this->cierreServicio->verificarIntegridadCierre($contexto->organizacionId, (int)$cierre['id']);

            return $this->responderExito(
                $resultado,
                $resultado['es_integro'] ? 'Integridad histórica y sellado criptográfico 100% íntegro.' : 'Alerta: El acta de cierre presenta inconsistencias de hash.'
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error al auditar la integridad del cierre: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/ediciones/{id}/reportes/libro-ingresos
     * Retorna los datos conciliados del Libro Oficial de Ingresos y Cobranzas.
     */
    public function reporteLibroIngresos(int $id): string
    {
        try {
            $contexto = $this->verificarAlMenosUnPermiso(['ediciones.reportes.ingresos.ver', 'pagos.ver', 'ediciones.ver']);
            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $datos = $this->reporteServicio->obtenerLibroIngresos($contexto->organizacionId, $id, $_GET);
            return $this->responderExito($datos, 'Libro de ingresos y cobranzas obtenido exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 404);
        } catch (Throwable $e) {
            return $this->responderError('Error al generar el libro de ingresos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/ediciones/{id}/reportes/manifiesto-salidas
     * Retorna los datos operativos de las salidas de campo y vehículos.
     */
    public function reporteManifiestoSalidas(int $id): string
    {
        try {
            $contexto = $this->verificarAlMenosUnPermiso(['ediciones.reportes.salidas.ver', 'operacion.ver', 'ediciones.ver']);
            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $datos = $this->reporteServicio->obtenerManifiestoSalidas($contexto->organizacionId, $id, $_GET);
            return $this->responderExito($datos, 'Manifiesto de salidas operativas obtenido exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 404);
        } catch (Throwable $e) {
            return $this->responderError('Error al generar el manifiesto de salidas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/ediciones/{id}/reportes/padron-asistencia
     * Retorna el padrón de asistencia con minimización de datos personales (Ley 29733).
     */
    public function reportePadronAsistencia(int $id): string
    {
        try {
            $contexto = $this->verificarAlMenosUnPermiso(['ediciones.reportes.asistencia.ver', 'reservas.ver', 'ediciones.ver']);
            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $datos = $this->reporteServicio->obtenerPadronAsistencia($contexto->organizacionId, $id, $_GET);
            return $this->responderExito($datos, 'Padrón oficial de asistencia obtenido exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 404);
        } catch (Throwable $e) {
            return $this->responderError('Error al generar el padrón de asistencia: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/ediciones/{id}/reportes/exportar
     * Exporta cualquiera de los 3 reportes oficiales a formato CSV (anti-formula injection), XLSX o PDF institucional.
     * Exige permiso específico de exportación por tipo de reporte (Principio de Mínimo Privilegio).
     */
    public function exportarReporte(int $id): string
    {
        try {
            $tipo = strtoupper(trim((string)($_GET['tipo'] ?? '')));
            $formato = strtolower(trim((string)($_GET['formato'] ?? 'csv')));

            $permisoExportacion = match ($tipo) {
                ReporteCierreServicio::TIPO_LIBRO_INGRESOS => 'ediciones.reportes.ingresos.exportar',
                ReporteCierreServicio::TIPO_MANIFIESTO_SALIDAS => 'ediciones.reportes.salidas.exportar',
                ReporteCierreServicio::TIPO_PADRON_ASISTENCIA => 'ediciones.reportes.asistencia.exportar',
                default => throw new InvalidArgumentException("Tipo de reporte no reconocido: '{$tipo}'. Use LIBRO_INGRESOS, MANIFIESTO_SALIDAS o PADRON_ASISTENCIA."),
            };

            $contexto = $this->verificarSesionYPermiso($permisoExportacion);
            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $reporteData = match ($tipo) {
                ReporteCierreServicio::TIPO_LIBRO_INGRESOS => $this->reporteServicio->obtenerLibroIngresos($contexto->organizacionId, $id, $_GET),
                ReporteCierreServicio::TIPO_MANIFIESTO_SALIDAS => $this->reporteServicio->obtenerManifiestoSalidas($contexto->organizacionId, $id, $_GET),
                ReporteCierreServicio::TIPO_PADRON_ASISTENCIA => $this->reporteServicio->obtenerPadronAsistencia($contexto->organizacionId, $id, $_GET),
                default => throw new InvalidArgumentException("Tipo de reporte no reconocido: '{$tipo}'. Use LIBRO_INGRESOS, MANIFIESTO_SALIDAS o PADRON_ASISTENCIA."),
            };

            $slugBase = strtolower(str_replace('_', '-', $tipo)) . '-' . $id;

            if ($formato === 'csv') {
                $contenido = $this->reporteServicio->exportarCsv($tipo, $reporteData);
                $GLOBALS['__ultimo_codigo_http_api'] = 200;
                http_response_code(200);
                if (php_sapi_name() === 'cli') {
                    return $contenido;
                }
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: text/csv; charset=utf-8');
                    header(sprintf('Content-Disposition: attachment; filename="%s_%s.csv"', $slugBase, date('Ymd_His')));
                    header('Content-Length: ' . strlen($contenido));
                }
                echo $contenido;
                exit;
            }

            if ($formato === 'xlsx') {
                $contenido = $this->reporteServicio->exportarXlsx($tipo, $reporteData);
                $GLOBALS['__ultimo_codigo_http_api'] = 200;
                http_response_code(200);
                if (php_sapi_name() === 'cli') {
                    return $contenido;
                }
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header(sprintf('Content-Disposition: attachment; filename="%s_%s.xlsx"', $slugBase, date('Ymd_His')));
                    header('Content-Length: ' . strlen($contenido));
                }
                echo $contenido;
                exit;
            }

            if ($formato === 'xml') {
                $contenido = $this->reporteServicio->exportarXmlSpreadsheetMl($tipo, $reporteData);
                $GLOBALS['__ultimo_codigo_http_api'] = 200;
                http_response_code(200);
                if (php_sapi_name() === 'cli') {
                    return $contenido;
                }
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/xml; charset=utf-8');
                    header(sprintf('Content-Disposition: attachment; filename="%s_%s.xml"', $slugBase, date('Ymd_His')));
                    header('Content-Length: ' . strlen($contenido));
                }
                echo $contenido;
                exit;
            }

            if ($formato === 'pdf') {
                $stmtEd = $this->pdo->prepare("SELECT * FROM `ediciones_candelaria` WHERE `id` = :id AND `organizacion_id` = :org_id LIMIT 1");
                $stmtEd->execute([':id' => $id, ':org_id' => $contexto->organizacionId]);
                $edicionInfo = $stmtEd->fetch(PDO::FETCH_ASSOC) ?: [];

                $stmtOrg = $this->pdo->prepare("SELECT * FROM `organizaciones` WHERE `id` = :org_id LIMIT 1");
                $stmtOrg->execute([':org_id' => $contexto->organizacionId]);
                $tenantInfo = $stmtOrg->fetch(PDO::FETCH_ASSOC) ?: [];

                $contenido = $this->reporteServicio->exportarPdf($tipo, $reporteData, $tenantInfo, $edicionInfo);
                $GLOBALS['__ultimo_codigo_http_api'] = 200;
                http_response_code(200);
                if (php_sapi_name() === 'cli') {
                    return $contenido;
                }
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                $descargar = !empty($_GET['descargar']);
                $disposition = $descargar ? 'attachment' : 'inline';
                if (!headers_sent()) {
                    header('Content-Type: application/pdf');
                    header(sprintf('Content-Disposition: %s; filename="%s_%s.pdf"', $disposition, $slugBase, date('Ymd_His')));
                    header('Content-Length: ' . strlen($contenido));
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $contenido;
                exit;
            }

            return $this->responderError("Formato de exportación no soportado: '{$formato}'. Formatos admitidos: csv, xlsx, xml, pdf.", 400);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 400);
        } catch (Throwable $e) {
            return $this->responderError('Error al exportar el reporte oficial: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verifica la sesión activa del operador y valida el permiso RBAC en backend.
     */
    private function verificarSesionYPermiso(string $permiso): ContextoOperacion
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            if (!headers_sent()) {
                http_response_code(401);
            }
            throw new AccesoDenegadoExcepcion('No autenticado: requiere una sesión activa.', null, 401);
        }

        if (!$this->authzMiddleware->verificarPermiso($permiso, $contexto, false)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            throw new AccesoDenegadoExcepcion("Acceso denegado: requiere el permiso '{$permiso}'.", $permiso, 403);
        }

        return $contexto;
    }

    /**
     * Verifica que el operador cuente con al menos uno de los permisos provistos.
     *
     * @param string[] $permisos
     */
    private function verificarAlMenosUnPermiso(array $permisos): ContextoOperacion
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            if (!headers_sent()) {
                http_response_code(401);
            }
            throw new AccesoDenegadoExcepcion('No autenticado: requiere una sesión activa.', null, 401);
        }

        foreach ($permisos as $p) {
            if ($this->authzMiddleware->verificarPermiso($p, $contexto, false)) {
                return $contexto;
            }
        }

        if (!headers_sent()) {
            http_response_code(403);
        }
        $lista = implode("', '", $permisos);
        throw new AccesoDenegadoExcepcion("Acceso denegado: requiere al menos uno de los permisos ['{$lista}'].", $permisos[0], 403);
    }

    /**
     * Resuelve el contexto de sesión autenticado sin requerir un permiso específico.
     */
    private function resolverContextoAutenticado(): ContextoOperacion
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            if (!headers_sent()) {
                http_response_code(401);
            }
            throw new AccesoDenegadoExcepcion('No autenticado: requiere una sesión activa.', null, 401);
        }

        return $contexto;
    }

    /**
     * Extrae el payload de la petición (JSON body o matriz $_POST).
     */
    private function obtenerPayloadJson(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }

        return $_POST;
    }

    /**
     * Valida el token CSRF para peticiones mutantes.
     */
    private function validarCsrf(ContextoOperacion $contexto): void
    {
        $tokenEsperado = $contexto->metadatos['csrf_token'] ?? null;
        $cuerpo = $this->obtenerPayloadJson();
        $post = !empty($cuerpo) ? array_merge($_POST, $cuerpo) : $_POST;

        $tokenRecibido = ProtectorCsrf::extraerTokenDePeticion($post, $_SERVER);

        if ($tokenEsperado === null || $tokenRecibido === null || !ProtectorCsrf::validarToken($tokenEsperado, $tokenRecibido)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            throw new AccesoDenegadoExcepcion('Token de seguridad CSRF ausente o inválido.', null, 403);
        }
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

    private function responderJson(array $payload): string
    {
        $codigo = (int) ($payload['codigo'] ?? 200);
        $GLOBALS['__ultimo_codigo_http_api'] = $codigo;
        if (!headers_sent()) {
            http_response_code($codigo);
        }
        header('Content-Type: application/json; charset=utf-8');
        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
