<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConjuntoFolcloricoRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\ProgramacionFolcloricaRepositorio;
use Aplicacion\Repositorios\TipoDanzaRepositorio;
use InvalidArgumentException;
use Nucleo\Http\ContextoOperacion;
use PDO;

class ProgramacionFolcloricaServicio
{
    public function __construct(
        private TipoDanzaRepositorio $tipoDanzaRepo,
        private ConjuntoFolcloricoRepositorio $conjuntoRepo,
        private ProgramacionFolcloricaRepositorio $programacionRepo,
        private EdicionRepositorio $edicionRepo,
        private ?AutorizacionServicio $authzServicio = null,
        private ?AuditoriaRepositorio $auditoriaRepo = null,
        private ?PDO $pdo = null
    ) {
    }

    // --- TIPOS DE DANZA ---

    public function registrarTipoDanza(
        int $organizacionId,
        string $codigo,
        string $nombre,
        CategoriaDanza $categoria = CategoriaDanza::TRAJES_DE_LUCES,
        ?string $descripcion = null,
        ?ContextoOperacion $contexto = null
    ): TipoDanza {
        $this->validarPermiso('folclore.tipos_danza.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $existente = $this->tipoDanzaRepo->buscarPorCodigo($codigo, $organizacionId);
        if ($existente !== null) {
            throw new InvalidArgumentException("El tipo de danza con código '{$codigo}' ya existe en esta organización.");
        }

        $tipo = new TipoDanza(
            id: null,
            organizacionId: $organizacionId,
            codigo: strtoupper(trim($codigo)),
            nombre: strtoupper(trim($nombre)),
            categoriaOrigen: $categoria,
            descripcion: $descripcion,
            activo: true
        );

        $creado = $this->tipoDanzaRepo->crear($tipo);

        $this->registrarAuditoria($contexto, 'TIPO_DANZA_CREADO', 'tipos_danza', $creado->id, [
            'codigo' => $creado->codigo,
            'nombre' => $creado->nombre,
            'categoria' => $creado->categoriaOrigen->value,
        ]);

        return $creado;
    }

    /**
     * @return array<TipoDanza>
     */
    public function listarTiposDanza(int $organizacionId, ?ContextoOperacion $contexto = null): array
    {
        $this->validarPermiso('folclore.tipos_danza.ver', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        return $this->tipoDanzaRepo->listarPorOrganizacion($organizacionId);
    }

    public function actualizarTipoDanza(
        int $organizacionId,
        int $id,
        string $nombre,
        CategoriaDanza $categoria,
        ?string $descripcion = null,
        bool $activo = true,
        ?ContextoOperacion $contexto = null
    ): TipoDanza {
        $this->validarPermiso('folclore.tipos_danza.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $existente = $this->tipoDanzaRepo->buscarPorId($id, $organizacionId);
        if ($existente === null) {
            throw new InvalidArgumentException("El tipo de danza (#{$id}) no existe en esta organización.");
        }

        $tipoActualizado = new TipoDanza(
            id: $id,
            organizacionId: $organizacionId,
            codigo: $existente->codigo,
            nombre: strtoupper(trim($nombre)),
            categoriaOrigen: $categoria,
            descripcion: $descripcion,
            activo: $activo
        );

        $this->tipoDanzaRepo->actualizar($tipoActualizado);

        $this->registrarAuditoria($contexto, 'TIPO_DANZA_ACTUALIZADO', 'tipos_danza', $id, [
            'nombre' => $tipoActualizado->nombre,
            'categoria' => $tipoActualizado->categoriaOrigen->value,
            'activo' => $activo
        ]);

        return $tipoActualizado;
    }

    // --- CONJUNTOS FOLCLÓRICOS (HISTÓRICOS) ---

    public function registrarConjunto(
        int $organizacionId,
        int $tipoDanzaId,
        string $codigo,
        string $nombre,
        ?string $sigla = null,
        ?string $fechaFundacion = null,
        ?string $nombrePresidente = null,
        ?string $telefonoContacto = null,
        ?string $colorEmblematicoHex = null,
        ?string $resenaHistorica = null,
        ?ContextoOperacion $contexto = null
    ): ConjuntoFolclorico {
        $this->validarPermiso('folclore.conjuntos.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $tipoDanza = $this->tipoDanzaRepo->buscarPorId($tipoDanzaId, $organizacionId);
        if ($tipoDanza === null) {
            throw new InvalidArgumentException("El tipo de danza especificado (#{$tipoDanzaId}) no existe.");
        }

        $existente = $this->conjuntoRepo->buscarPorCodigo($codigo, $organizacionId);
        if ($existente !== null) {
            throw new InvalidArgumentException("Ya existe un conjunto con el código '{$codigo}' en esta organización.");
        }

        $conjunto = new ConjuntoFolclorico(
            id: null,
            organizacionId: $organizacionId,
            tipoDanzaId: $tipoDanzaId,
            codigo: strtoupper(trim($codigo)),
            nombre: strtoupper(trim($nombre)),
            sigla: $sigla ? strtoupper(trim($sigla)) : null,
            fechaFundacion: $fechaFundacion,
            nombrePresidente: $nombrePresidente,
            telefonoContacto: $telefonoContacto,
            colorEmblematicoHex: $colorEmblematicoHex,
            resenaHistorica: $resenaHistorica,
            activo: true,
            creadoPor: $contexto->usuarioId ?? 1
        );

        $creado = $this->conjuntoRepo->crear($conjunto);

        $this->registrarAuditoria($contexto, 'CONJUNTO_CREADO', 'conjuntos_folcloricos', $creado->id, [
            'codigo' => $creado->codigo,
            'nombre' => $creado->nombre,
            'tipo_danza_id' => $tipoDanzaId,
        ]);

        return $creado;
    }

    /**
     * @return array<ConjuntoFolclorico>
     */
    public function listarConjuntos(int $organizacionId, ?int $tipoDanzaId = null, ?ContextoOperacion $contexto = null): array
    {
        $this->validarPermiso('folclore.conjuntos.ver', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        return $this->conjuntoRepo->listarPorOrganizacion($organizacionId, $tipoDanzaId);
    }

    public function obtenerConjuntoPorId(int $id, int $organizacionId, ?ContextoOperacion $contexto = null): ?ConjuntoFolclorico
    {
        $this->validarPermiso('folclore.conjuntos.ver', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        return $this->conjuntoRepo->buscarPorId($id, $organizacionId);
    }

    public function actualizarConjunto(
        int $organizacionId,
        int $id,
        int $tipoDanzaId,
        string $nombre,
        ?string $descripcion = null,
        ?int $anioFundacion = null,
        ?string $ciudadSede = null,
        bool $activo = true,
        ?ContextoOperacion $contexto = null
    ): ConjuntoFolclorico {
        $this->validarPermiso('folclore.conjuntos.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $existente = $this->conjuntoRepo->buscarPorId($id, $organizacionId);
        if ($existente === null) {
            throw new InvalidArgumentException("El conjunto folclórico (#{$id}) no existe en esta organización.");
        }

        $tipo = $this->tipoDanzaRepo->buscarPorId($tipoDanzaId, $organizacionId);
        if ($tipo === null) {
            throw new InvalidArgumentException("El tipo de danza especificado (#{$tipoDanzaId}) no existe.");
        }

        $conjuntoActualizado = new ConjuntoFolclorico(
            id: $id,
            organizacionId: $organizacionId,
            tipoDanzaId: $tipoDanzaId,
            codigo: $existente->codigo,
            nombre: strtoupper(trim($nombre)),
            sigla: $existente->sigla,
            fechaFundacion: $existente->fechaFundacion,
            nombrePresidente: $existente->nombrePresidente,
            telefonoContacto: $existente->telefonoContacto,
            colorEmblematicoHex: $existente->colorEmblematicoHex,
            resenaHistorica: $descripcion ?? $existente->resenaHistorica,
            activo: $activo,
            creadoPor: $existente->creadoPor
        );

        $this->conjuntoRepo->actualizar($conjuntoActualizado);

        $this->registrarAuditoria($contexto, 'CONJUNTO_ACTUALIZADO', 'conjuntos_folcloricos', $id, [
            'nombre' => $conjuntoActualizado->nombre,
            'tipo_danza_id' => $tipoDanzaId,
            'activo' => $activo
        ]);

        return $conjuntoActualizado;
    }

    // --- BLOQUES DE DANZARINES ---

    public function registrarBloque(
        int $organizacionId,
        int $conjuntoId,
        string $codigo,
        string $nombre,
        ?string $responsableNombre = null,
        ?string $telefonoContacto = null,
        ?ContextoOperacion $contexto = null
    ): BloqueConjunto {
        $this->validarPermiso('folclore.conjuntos.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $conjunto = $this->conjuntoRepo->buscarPorId($conjuntoId, $organizacionId);
        if ($conjunto === null) {
            throw new InvalidArgumentException("El conjunto folclórico (#{$conjuntoId}) no existe en esta organización.");
        }

        $bloque = new BloqueConjunto(
            id: null,
            organizacionId: $organizacionId,
            conjuntoId: $conjuntoId,
            codigo: strtoupper(trim($codigo)),
            nombre: strtoupper(trim($nombre)),
            responsableNombre: $responsableNombre,
            telefonoContacto: $telefonoContacto,
            activo: true
        );

        $creado = $this->conjuntoRepo->crearBloque($bloque);

        $this->registrarAuditoria($contexto, 'BLOQUE_CREADO', 'bloques_conjunto', $creado->id, [
            'conjunto_id' => $conjuntoId,
            'codigo'      => $creado->codigo,
            'nombre'      => $creado->nombre,
        ]);

        return $creado;
    }

    /**
     * @return array<BloqueConjunto>
     */
    public function listarBloques(int $organizacionId, int $conjuntoId, ?ContextoOperacion $contexto = null): array
    {
        $this->validarPermiso('folclore.conjuntos.ver', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        return $this->conjuntoRepo->listarBloquesPorConjunto($conjuntoId, $organizacionId);
    }

    public function actualizarBloque(
        int $organizacionId,
        int $id,
        string $nombre,
        ?string $responsableNombre = null,
        ?string $telefonoContacto = null,
        bool $activo = true,
        ?ContextoOperacion $contexto = null
    ): BloqueConjunto {
        $this->validarPermiso('folclore.conjuntos.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $existente = $this->conjuntoRepo->buscarBloquePorId($id, $organizacionId);
        if ($existente === null) {
            throw new InvalidArgumentException("El bloque folclórico (#{$id}) no existe en esta organización.");
        }

        $bloqueActualizado = new BloqueConjunto(
            id: $id,
            organizacionId: $organizacionId,
            conjuntoId: $existente->conjuntoId,
            codigo: $existente->codigo,
            nombre: strtoupper(trim($nombre)),
            responsableNombre: $responsableNombre,
            telefonoContacto: $telefonoContacto,
            activo: $activo
        );

        $this->conjuntoRepo->actualizarBloque($bloqueActualizado);

        $this->registrarAuditoria($contexto, 'BLOQUE_ACTUALIZADO', 'bloques_conjunto', $id, [
            'nombre' => $bloqueActualizado->nombre,
            'activo' => $activo
        ]);

        return $bloqueActualizado;
    }

    // --- INSCRIPCIÓN / PARTICIPACIÓN POR EDICIÓN ---

    public function inscribirConjuntoEnEdicion(
        int $organizacionId,
        int $edicionId,
        int $conjuntoId,
        ?string $numeroRegistroOficial = null,
        ?string $delegado = null,
        ?string $telefonoDelegado = null,
        int $danzarinesEstimados = 0,
        int $cantidadBandas = 1,
        ?string $observaciones = null,
        ?ContextoOperacion $contexto = null
    ): InscripcionConjuntoEdicion {
        $this->validarPermiso('folclore.inscripciones.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $edicion = $this->edicionRepo->buscarPorId($edicionId, $organizacionId);
        if ($edicion === null) {
            throw new InvalidArgumentException("La edición especificada (#{$edicionId}) no existe.");
        }

        $conjunto = $this->conjuntoRepo->buscarPorId($conjuntoId, $organizacionId);
        if ($conjunto === null) {
            throw new InvalidArgumentException("El conjunto especificado (#{$conjuntoId}) no existe.");
        }

        $yaInscrito = $this->conjuntoRepo->buscarInscripcion($edicionId, $conjuntoId, $organizacionId);
        if ($yaInscrito !== null) {
            throw new InvalidArgumentException("El conjunto '{$conjunto->nombre}' ya se encuentra inscrito en la edición #{$edicionId}.");
        }

        $inscripcion = new InscripcionConjuntoEdicion(
            id: null,
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            conjuntoId: $conjuntoId,
            numeroRegistroOficial: $numeroRegistroOficial,
            nombreDelegadoEdicion: $delegado,
            telefonoDelegadoEdicion: $telefonoDelegado,
            cantidadEstimadaDanzarines: max(0, $danzarinesEstimados),
            cantidadBandas: max(1, $cantidadBandas),
            estado: EstadoInscripcionConjunto::INSCRITO,
            observaciones: $observaciones,
            creadoPor: $contexto->usuarioId ?? 1
        );

        $creado = $this->conjuntoRepo->inscribirEnEdicion($inscripcion);

        $this->registrarAuditoria($contexto, 'CONJUNTO_INSCRITO_EDICION', 'edicion_conjunto_inscripciones', $creado->id, [
            'edicion_id'  => $edicionId,
            'conjunto_id' => $conjuntoId,
            'registro'    => $numeroRegistroOficial,
        ]);

        return $creado;
    }

    /**
     * @return array<InscripcionConjuntoEdicion>
     */
    public function listarInscritosEnEdicion(int $organizacionId, int $edicionId, ?ContextoOperacion $contexto = null): array
    {
        $this->validarPermiso('folclore.conjuntos.ver', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        return $this->conjuntoRepo->listarInscritosPorEdicion($edicionId, $organizacionId);
    }

    public function actualizarInscripcion(
        int $organizacionId,
        int $id,
        ?string $numeroRegistro = null,
        ?string $delegado = null,
        ?string $telefono = null,
        int $danzarines = 0,
        int $bandas = 1,
        EstadoInscripcionConjunto $estado = EstadoInscripcionConjunto::INSCRITO,
        ?string $observaciones = null,
        ?ContextoOperacion $contexto = null
    ): InscripcionConjuntoEdicion {
        $this->validarPermiso('folclore.inscripciones.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $existente = $this->conjuntoRepo->buscarInscripcionPorId($id, $organizacionId);
        if ($existente === null) {
            throw new InvalidArgumentException("La inscripción (#{$id}) no existe en esta organización.");
        }

        $inscripcionActualizada = new InscripcionConjuntoEdicion(
            id: $id,
            organizacionId: $organizacionId,
            edicionId: $existente->edicionId,
            conjuntoId: $existente->conjuntoId,
            numeroRegistroOficial: $numeroRegistro,
            nombreDelegadoEdicion: $delegado,
            telefonoDelegadoEdicion: $telefono,
            cantidadEstimadaDanzarines: max(0, $danzarines),
            cantidadBandas: max(1, $bandas),
            estado: $estado,
            observaciones: $observaciones,
            creadoPor: $existente->creadoPor
        );

        $this->conjuntoRepo->actualizarInscripcion($inscripcionActualizada);

        $this->registrarAuditoria($contexto, 'INSCRIPCION_ACTUALIZADA', 'edicion_conjunto_inscripciones', $id, [
            'estado' => $estado->value,
            'registro' => $numeroRegistro,
        ]);

        return $inscripcionActualizada;
    }

    // --- ACTIVIDADES FOLCLÓRICAS DE LA EDICIÓN ---

    public function registrarActividad(
        int $organizacionId,
        int $edicionId,
        string $codigo,
        string $titulo,
        string $fechaInicio,
        string $fechaFin,
        string $lugarPrincipal,
        ?ContextoOperacion $contexto = null
    ): ActividadFolcloricaEdicion {
        $this->validarPermiso('folclore.programacion.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $actividad = new ActividadFolcloricaEdicion(
            id: null,
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            codigo: strtoupper(trim($codigo)),
            titulo: strtoupper(trim($titulo)),
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            lugarPrincipal: $lugarPrincipal,
            estado: EstadoActividadFolclorica::PROGRAMADA
        );

        $creado = $this->programacionRepo->crearActividad($actividad);

        $this->registrarAuditoria($contexto, 'ACTIVIDAD_FOLCLORICA_CREADA', 'folclore_actividades_edicion', $creado->id, [
            'edicion_id' => $edicionId,
            'codigo'     => $creado->codigo,
            'titulo'     => $creado->titulo,
        ]);

        return $creado;
    }

    public function actualizarActividad(
        int $organizacionId,
        int $id,
        string $titulo,
        string $fechaInicio,
        string $fechaFin,
        string $lugarPrincipal,
        EstadoActividadFolclorica $estado = EstadoActividadFolclorica::PROGRAMADA,
        ?ContextoOperacion $contexto = null
    ): ActividadFolcloricaEdicion {
        $this->validarPermiso('folclore.programacion.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $existente = $this->programacionRepo->buscarActividadPorId($id, $organizacionId);
        if ($existente === null) {
            throw new InvalidArgumentException("La actividad folclórica (#{$id}) no existe en esta organización.");
        }

        $actividadActualizada = new ActividadFolcloricaEdicion(
            id: $id,
            organizacionId: $organizacionId,
            edicionId: $existente->edicionId,
            codigo: $existente->codigo,
            titulo: strtoupper(trim($titulo)),
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            lugarPrincipal: $lugarPrincipal,
            estado: $estado
        );

        $this->programacionRepo->actualizarActividad($actividadActualizada);

        $this->registrarAuditoria($contexto, 'ACTIVIDAD_FOLCLORICA_ACTUALIZADA', 'folclore_actividades_edicion', $id, [
            'titulo' => $actividadActualizada->titulo,
            'estado' => $estado->value,
        ]);

        return $actividadActualizada;
    }

    // --- PROGRAMACIÓN OFICIAL (CON PROCEDENCIA Y VERSIONES) ---

    public function publicarProgramacionOficial(
        int $organizacionId,
        int $edicionId,
        int $actividadId,
        string $fuenteOrigen,
        string $fechaEmisionFuente,
        string $etiquetaVersion = 'Rol Oficial',
        EstadoConfirmacionProgramacion $estado = EstadoConfirmacionProgramacion::PRELIMINAR,
        ?string $documentoUrl = null,
        ?string $notas = null,
        ?ContextoOperacion $contexto = null
    ): ProgramacionOficialEdicion {
        $this->validarPermiso('folclore.programacion.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $actividad = $this->programacionRepo->buscarActividadPorId($actividadId, $organizacionId);
        if ($actividad === null) {
            throw new InvalidArgumentException("La actividad folclórica especificada (#{$actividadId}) no existe.");
        }

        if ($actividad->edicionId !== $edicionId) {
            throw new InvalidArgumentException("Incompatibilidad de ámbito: La actividad folclórica (#{$actividadId}) no pertenece a la edición #{$edicionId}.");
        }

        // Principio de Segregación de Funciones: Una versión oficial confirmada no puede nacer autoaprobada
        if ($estado === EstadoConfirmacionProgramacion::OFICIAL_CONFIRMADO) {
            throw new AccesoDenegadoExcepcion("Segregación de funciones: La aprobación oficial requiere el flujo formal de cuatro ojos (oficialización por un usuario distinto del creador).");
        }

        $ultimaVersion = $this->programacionRepo->obtenerUltimaVersionNumero($actividadId);
        $nuevaVersionNumero = $ultimaVersion + 1;
        $creadorId = (int) ($contexto?->usuarioId ?? 1);

        $programacion = new ProgramacionOficialEdicion(
            id: null,
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            actividadId: $actividadId,
            versionNumero: $nuevaVersionNumero,
            etiquetaVersion: "{$etiquetaVersion} v{$nuevaVersionNumero}.0",
            fuenteOrigen: trim($fuenteOrigen),
            fechaEmisionFuente: $fechaEmisionFuente,
            documentoReferenciaUrl: $documentoUrl,
            estadoConfirmacion: $estado,
            esVersionActiva: true,
            notasVersion: $notas,
            creadoPor: $creadorId,
            aprobadoPor: null,
            aprobadoEn: null
        );

        $creado = $this->programacionRepo->crearVersionProgramacion($programacion);

        $this->registrarAuditoria($contexto, 'PROGRAMACION_OFICIAL_PUBLICADA', 'folclore_programaciones_oficiales', $creado->id, [
            'actividad_id'   => $actividadId,
            'version_numero' => $nuevaVersionNumero,
            'fuente_origen'  => $fuenteOrigen,
            'creador_id'     => $creadorId,
            'estado'         => $estado->value,
        ]);

        return $creado;
    }

    /**
     * @return array<ProgramacionOficialEdicion>
     */
    public function listarVersionesProgramacion(int $organizacionId, int $actividadId, ?ContextoOperacion $contexto = null): array
    {
        $this->validarPermiso('folclore.programacion.ver', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        return $this->programacionRepo->listarVersionesPorActividad($actividadId, $organizacionId);
    }

    /**
     * Oficializa y aprueba una versión preliminar de programación oficial.
     * Impone el Principio de Cuatro Ojos: El usuario aprobador debe ser obligatoriamente
     * distinto del usuario creador/solicitante de la versión.
     */
    public function oficializarVersionExistente(
        int $organizacionId,
        int $programacionId,
        ?ContextoOperacion $contexto = null
    ): bool {
        $this->validarPermiso('folclore.programacion.aprobar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $prog = $this->programacionRepo->buscarProgramacionPorId($programacionId, $organizacionId);
        if ($prog === null) {
            throw new InvalidArgumentException("La programación oficial (#{$programacionId}) no existe.");
        }

        // 1. Inmutabilidad histórica: No modificar ni re-aprobar versiones ya confirmadas
        if ($prog->estadoConfirmacion === EstadoConfirmacionProgramacion::OFICIAL_CONFIRMADO) {
            throw new InvalidArgumentException("La programación oficial (#{$programacionId}) ya fue formalmente aprobada y confirmada con anterioridad. No se permiten reaprobaciones ni modificaciones.");
        }

        // 2. Rechazo de estados incompatibles
        if ($prog->estadoConfirmacion === EstadoConfirmacionProgramacion::OBSOLETO) {
            throw new InvalidArgumentException("Transición inválida: No se puede aprobar una versión catalogada como OBSOLETA.");
        }

        // 3. Identificación del aprobador
        $aprobadorId = (int) ($contexto?->usuarioId ?? 0);
        if ($aprobadorId <= 0) {
            throw new AccesoDenegadoExcepcion("Identidad no autenticada: Se requiere un usuario autenticado válido para ejercer la aprobación de la programación oficial.");
        }

        // 4. Principio de Cuatro Ojos (Segregación de Funciones): El aprobador debe ser distinto del creador
        if ($prog->creadoPor === $aprobadorId) {
            throw new AccesoDenegadoExcepcion("Violación de Segregación de Funciones (Principio de Cuatro Ojos): El usuario aprobador (#{$aprobadorId}) no puede ser el mismo usuario creador/solicitante (#{$prog->creadoPor}) de la versión preliminar.");
        }

        $fechaAprobacion = date('Y-m-d H:i:s');
        $exito = $this->programacionRepo->oficializarVersion($programacionId, $organizacionId, $aprobadorId, $fechaAprobacion);

        if ($exito) {
            $this->registrarAuditoria($contexto, 'PROGRAMACION_OFICIAL_CONFIRMADA', 'folclore_programaciones_oficiales', $programacionId, [
                'actividad_id'      => $prog->actividadId,
                'version_numero'    => $prog->versionNumero,
                'solicitante_id'    => $prog->creadoPor,
                'aprobador_id'      => $aprobadorId,
                'fecha_aprobacion'  => $fechaAprobacion,
                'nuevo_estado'      => EstadoConfirmacionProgramacion::OFICIAL_CONFIRMADO->value,
            ]);
        }

        return $exito;
    }

    public function asignarTurnoPresentacion(
        int $organizacionId,
        int $programacionOficialId,
        int $conjuntoEdicionId,
        int $ordenSorteo,
        string $horaEstimadaPartida,
        ?string $horaEstimadaLlegada = null,
        ?string $puntoPartida = null,
        ?string $observaciones = null,
        ?ContextoOperacion $contexto = null
    ): OrdenPresentacionFolclorico {
        $this->validarPermiso('folclore.programacion.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $prog = $this->programacionRepo->buscarProgramacionPorId($programacionOficialId, $organizacionId);
        if ($prog === null) {
            throw new InvalidArgumentException("La programación oficial (#{$programacionOficialId}) no existe.");
        }

        $actividad = $this->programacionRepo->buscarActividadPorId($prog->actividadId, $organizacionId);
        if ($actividad === null) {
            throw new InvalidArgumentException("La actividad asociada a la programación oficial no existe.");
        }

        $conjuntoEdicion = $this->conjuntoRepo->buscarInscripcionPorId($conjuntoEdicionId, $organizacionId);
        if ($conjuntoEdicion === null) {
            throw new InvalidArgumentException("La inscripción del conjunto (#{$conjuntoEdicionId}) no existe en su organización.");
        }

        // Blindaje estricto de ámbito: la inscripción debe pertenecer a la misma edición de la actividad
        if ($conjuntoEdicion->edicionId !== $actividad->edicionId) {
            throw new InvalidArgumentException("Incompatibilidad de edición: La inscripción (#{$conjuntoEdicionId}) pertenece a la edición #{$conjuntoEdicion->edicionId}, distinta a la edición #{$actividad->edicionId} de la actividad folclórica.");
        }

        $turno = new OrdenPresentacionFolclorico(
            id: null,
            programacionOficialId: $programacionOficialId,
            conjuntoEdicionId: $conjuntoEdicionId,
            ordenSorteo: $ordenSorteo,
            horaEstimadaPartida: $horaEstimadaPartida,
            horaEstimadaLlegada: $horaEstimadaLlegada,
            puntoPartidaEspecifico: $puntoPartida,
            estadoTurno: EstadoTurnoPresentacion::PROGRAMADO,
            observaciones: $observaciones
        );

        $creado = $this->programacionRepo->registrarTurnoPresentacion($turno);

        $this->registrarAuditoria($contexto, 'TURNO_PRESENTACION_ASIGNADO', 'folclore_orden_presentacion', $creado->id, [
            'programacion_id' => $programacionOficialId,
            'orden_sorteo'    => $ordenSorteo,
            'hora_partida'    => $horaEstimadaPartida,
        ]);

        return $creado;
    }

    public function actualizarTurno(
        int $organizacionId,
        int $turnoId,
        int $ordenSorteo,
        string $horaEstimadaPartida,
        ?string $horaEstimadaLlegada = null,
        ?string $puntoPartida = null,
        EstadoTurnoPresentacion $estadoTurno = EstadoTurnoPresentacion::PROGRAMADO,
        ?string $horaRealPartida = null,
        ?string $horaRealFin = null,
        ?string $observaciones = null,
        ?ContextoOperacion $contexto = null
    ): OrdenPresentacionFolclorico {
        $this->validarPermiso('folclore.programacion.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $existente = $this->programacionRepo->buscarTurnoPorId($turnoId);
        if ($existente === null) {
            throw new InvalidArgumentException("El turno de presentación (#{$turnoId}) no existe.");
        }

        $prog = $this->programacionRepo->buscarProgramacionPorId($existente->programacionOficialId, $organizacionId);
        if ($prog === null) {
            throw new InvalidArgumentException("Violación de alcance: El turno no pertenece a esta organización.");
        }

        $turnoActualizado = new OrdenPresentacionFolclorico(
            id: $turnoId,
            programacionOficialId: $existente->programacionOficialId,
            conjuntoEdicionId: $existente->conjuntoEdicionId,
            ordenSorteo: $ordenSorteo,
            horaEstimadaPartida: $horaEstimadaPartida,
            horaEstimadaLlegada: $horaEstimadaLlegada,
            puntoPartidaEspecifico: $puntoPartida,
            estadoTurno: $estadoTurno,
            horaRealPartida: $horaRealPartida,
            horaRealFin: $horaRealFin,
            observaciones: $observaciones
        );

        $this->programacionRepo->actualizarTurno($turnoActualizado);

        $this->registrarAuditoria($contexto, 'TURNO_PRESENTACION_ACTUALIZADO', 'folclore_orden_presentacion', $turnoId, [
            'orden_sorteo' => $ordenSorteo,
            'estado'       => $estadoTurno->value,
        ]);

        return $turnoActualizado;
    }

    public function eliminarTurno(int $organizacionId, int $turnoId, ?ContextoOperacion $contexto = null): bool
    {
        $this->validarPermiso('folclore.programacion.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $existente = $this->programacionRepo->buscarTurnoPorId($turnoId);
        if ($existente === null) {
            return false;
        }

        $prog = $this->programacionRepo->buscarProgramacionPorId($existente->programacionOficialId, $organizacionId);
        if ($prog === null) {
            throw new InvalidArgumentException("Violación de alcance: El turno no pertenece a esta organización.");
        }

        $exito = $this->programacionRepo->eliminarTurno($turnoId);

        if ($exito) {
            $this->registrarAuditoria($contexto, 'TURNO_PRESENTACION_ELIMINADO', 'folclore_orden_presentacion', $turnoId, [
                'programacion_id' => $existente->programacionOficialId,
                'orden_sorteo'    => $existente->ordenSorteo,
            ]);
        }

        return $exito;
    }

    /**
     * @return array<OrdenPresentacionFolclorico>
     */
    public function obtenerRolPresentacion(int $organizacionId, int $programacionId, ?ContextoOperacion $contexto = null): array
    {
        $this->validarPermiso('folclore.programacion.ver', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        return $this->programacionRepo->listarTurnosPorProgramacion($programacionId);
    }

    /**
     * Actualiza el estado operativo y tiempos reales de un turno en el Tablero de Seguimiento (Fase 3.1C).
     * Aplica la máquina de estados, control de concurrencia optimista (HTTP 409) y auditoría inmutable.
     */
    public function actualizarEstadoOperativoTurno(
        int $organizacionId,
        int $turnoId,
        string|EstadoTurnoPresentacion $nuevoEstadoEntrada,
        ?string $horaRealPartida = null,
        ?string $horaRealFin = null,
        ?string $observaciones = null,
        ?int $retrasoMinutos = null,
        int $versionBloqueo = 1,
        ?ContextoOperacion $contexto = null
    ): OrdenPresentacionFolclorico {
        $this->validarPermiso('folclore.programacion.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $existente = $this->programacionRepo->buscarTurnoPorId($turnoId);
        if ($existente === null) {
            throw new InvalidArgumentException("El turno folclórico especificado (#{$turnoId}) no existe.");
        }

        $prog = $this->programacionRepo->buscarProgramacionPorId($existente->programacionOficialId, $organizacionId);
        if ($prog === null) {
            throw new InvalidArgumentException("Violación de alcance tenant: El turno no pertenece a esta organización.");
        }

        // 1. Normalización de estado y validación de máquina de estados
        $estadoActual = $existente->estadoTurno;
        $nuevoEstado = EstadoTurnoPresentacion::normalizar($nuevoEstadoEntrada);

        if (!$estadoActual->puedeTransicionarA($nuevoEstado)) {
            throw new InvalidArgumentException(
                "Transición de estado operativa no permitida: No se puede cambiar de '{$estadoActual->etiquetaOperativa()}' ({$estadoActual->value}) a '{$nuevoEstado->etiquetaOperativa()}' ({$nuevoEstado->value})."
            );
        }

        // 2. Coherencia cronológica en zona horaria America/Lima
        $ahoraLima = (new \DateTimeImmutable('now', new \DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');

        // Si pasa a estado activo y no se definió hora de partida, asignar automáticamente si estaba en null
        if (in_array($nuevoEstado, [EstadoTurnoPresentacion::EN_PREPARACION, EstadoTurnoPresentacion::EN_RUTA, EstadoTurnoPresentacion::EN_ESCENARIO], true)) {
            if ($horaRealPartida === null && $existente->horaRealPartida === null) {
                $horaRealPartida = $ahoraLima;
            } elseif ($horaRealPartida === null) {
                $horaRealPartida = $existente->horaRealPartida;
            }
        }

        // Si pasa a FINALIZADO y no se definió hora fin, asignar automáticamente si estaba en null
        if ($nuevoEstado === EstadoTurnoPresentacion::FINALIZADO) {
            if ($horaRealFin === null && $existente->horaRealFin === null) {
                $horaRealFin = $ahoraLima;
            } elseif ($horaRealFin === null) {
                $horaRealFin = $existente->horaRealFin;
            }
        }

        if ($horaRealPartida !== null && $horaRealFin !== null) {
            if (strtotime($horaRealFin) < strtotime($horaRealPartida)) {
                throw new InvalidArgumentException("Incoherencia cronológica: La hora de finalización ({$horaRealFin}) no puede ser anterior a la hora de partida ({$horaRealPartida}).");
            }
        }

        // 3. Ejecutar actualización con control de concurrencia optimista
        $this->programacionRepo->actualizarEstadoOperativoTurno(
            turnoId: $turnoId,
            nuevoEstado: $nuevoEstado,
            horaRealPartida: $horaRealPartida,
            horaRealFin: $horaRealFin,
            observaciones: $observaciones ?? $existente->observaciones,
            retrasoMinutos: $retrasoMinutos ?? $existente->retrasoMinutos,
            versionBloqueoEsperada: $versionBloqueo
        );

        $actualizado = $this->programacionRepo->buscarTurnoPorId($turnoId);

        // 4. Registro de auditoría
        $this->registrarAuditoria($contexto, 'TURNO_ESTADO_OPERATIVO_ACTUALIZADO', 'folclore_orden_presentacion', $turnoId, [
            'estado_anterior'   => $estadoActual->value,
            'estado_nuevo'      => $nuevoEstado->value,
            'hora_real_partida' => $horaRealPartida,
            'hora_real_fin'     => $horaRealFin,
            'retraso_minutos'   => $retrasoMinutos,
            'version_bloqueo'   => $actualizado?->versionBloqueo,
        ]);

        return $actualizado;
    }

    /**
     * Obtiene los datos consolidados y métricas para el Tablero Operativo de Seguimiento (Fase 3.1C).
     *
     * @return array{turnos: array, kpis: array}
     */
    public function obtenerTableroOperativo(
        int $organizacionId,
        int $edicionId,
        ?int $actividadId = null,
        ?int $conjuntoId = null,
        string|EstadoTurnoPresentacion|null $filtroEstado = null,
        ?int $personaAsignadaId = null,
        ?ContextoOperacion $contexto = null
    ): array {
        $this->validarPermiso('folclore.programacion.ver', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $estadoEnum = null;
        if ($filtroEstado !== null && $filtroEstado !== '') {
            $estadoEnum = EstadoTurnoPresentacion::normalizar($filtroEstado);
        }

        return $this->programacionRepo->obtenerDatosTableroSeguimiento(
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            actividadId: $actividadId,
            conjuntoId: $conjuntoId,
            filtroEstado: $estadoEnum,
            personaAsignadaId: $personaAsignadaId
        );
    }

    // --- INTEGRACIÓN OPCIONAL CON RESERVAS Y OPERACIONES ---

    /**
     * Vincula opcionalmente un participante de reserva a un conjunto y bloque folclórico.
     * (No convierte al danzarín en titular ni modifica los compromisos de venta).
     */
    public function vincularParticipanteAConjunto(
        int $organizacionId,
        int $participanteId,
        ?int $conjuntoId,
        ?int $bloqueId = null,
        ?ContextoOperacion $contexto = null
    ): bool {
        $this->validarPermiso('folclore.conjuntos.ver', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        if ($conjuntoId !== null) {
            $conjunto = $this->conjuntoRepo->buscarPorId($conjuntoId, $organizacionId);
            if ($conjunto === null) {
                throw new InvalidArgumentException("El conjunto especificado (#{$conjuntoId}) no existe.");
            }
        }

        // Blindaje de pertenencia de bloque al conjunto
        if ($bloqueId !== null) {
            if ($conjuntoId === null) {
                throw new InvalidArgumentException("No se puede especificar un bloque sin especificar el conjunto correspondiente.");
            }
            $bloque = $this->conjuntoRepo->buscarBloquePorId($bloqueId, $organizacionId);
            if ($bloque === null || $bloque->conjuntoId !== $conjuntoId) {
                throw new InvalidArgumentException("Incompatibilidad de bloque: El bloque (#{$bloqueId}) no pertenece al conjunto folclórico (#{$conjuntoId}).");
            }
        }

        if ($this->pdo === null) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            UPDATE `reserva_participantes` rp
            JOIN `reservas` r ON rp.reserva_id = r.id
            SET rp.conjunto_id = :conj_id, rp.bloque_id = :blq_id
            WHERE rp.id = :part_id AND r.organizacion_id = :org_id
        ");

        return $stmt->execute([
            'conj_id'  => $conjuntoId,
            'blq_id'   => $bloqueId,
            'part_id'  => $participanteId,
            'org_id'   => $organizacionId,
        ]);
    }

    /**
     * Vincula opcionalmente una salida operativa a un turno oficial de presentación.
     */
    public function vincularSalidaAOrdenPresentacion(
        int $organizacionId,
        int $salidaId,
        ?int $ordenPresentacionId,
        ?ContextoOperacion $contexto = null
    ): bool {
        $this->validarPermiso('folclore.programacion.gestionar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        if ($this->pdo === null) {
            return false;
        }

        if ($ordenPresentacionId !== null) {
            // Verificar existencia y consistencia de tenant y edición
            $stmtVerif = $this->pdo->prepare("
                SELECT fpo.organizacion_id, fae.edicion_id
                FROM `folclore_orden_presentacion` fop
                JOIN `folclore_programaciones_oficiales` fpo ON fop.programacion_oficial_id = fpo.id
                JOIN `folclore_actividades_edicion` fae ON fpo.actividad_id = fae.id
                WHERE fop.id = :orden_id
            ");
            $stmtVerif->execute(['orden_id' => $ordenPresentacionId]);
            $filaTurno = $stmtVerif->fetch(\PDO::FETCH_ASSOC);

            if (!$filaTurno || (int)$filaTurno['organizacion_id'] !== $organizacionId) {
                throw new InvalidArgumentException("El turno de presentación especificado (#{$ordenPresentacionId}) no existe o no pertenece a esta organización.");
            }

            // Verificar que la salida operativa pertenezca a la misma edición
            $stmtSalida = $this->pdo->prepare("SELECT edicion_id FROM `operacion_salidas` WHERE id = :id AND organizacion_id = :org_id");
            $stmtSalida->execute(['id' => $salidaId, 'org_id' => $organizacionId]);
            $salida = $stmtSalida->fetch(\PDO::FETCH_ASSOC);

            if (!$salida) {
                throw new InvalidArgumentException("La salida operativa especificada (#{$salidaId}) no existe en esta organización.");
            }

            if ((int)$salida['edicion_id'] !== (int)$filaTurno['edicion_id']) {
                throw new InvalidArgumentException("Incompatibilidad de edición: La salida operativa (#{$salidaId}, edición {$salida['edicion_id']}) y el turno folclórico (edición {$filaTurno['edicion_id']}) pertenecen a ediciones distintas.");
            }
        }

        $stmt = $this->pdo->prepare("
            UPDATE `operacion_salidas`
            SET `orden_presentacion_id` = :orden_id
            WHERE `id` = :salida_id AND `organizacion_id` = :org_id
        ");

        return $stmt->execute([
            'orden_id'  => $ordenPresentacionId,
            'salida_id' => $salidaId,
            'org_id'    => $organizacionId,
        ]);
    }

    // --- MÉTODOS DE SEGURIDAD Y GOBERNANZA ---

    private function validarPermiso(string $permiso, ?ContextoOperacion $contexto): void
    {
        if ($this->authzServicio !== null && $contexto !== null) {
            if (!$this->authzServicio->tienePermiso($contexto->usuarioId, $permiso, $contexto->organizacionId)) {
                throw new AccesoDenegadoExcepcion("Permiso insuficiente: Se requiere '{$permiso}' para ejecutar esta operación.");
            }
        }
    }

    private function validarAlcanceTenant(int $organizacionId, ?ContextoOperacion $contexto): void
    {
        if ($contexto !== null && $contexto->organizacionId !== null) {
            if ($contexto->organizacionId !== $organizacionId) {
                throw new AccesoDenegadoExcepcion("Violación de aislamiento multitenant: No puede operar sobre la organización {$organizacionId}.");
            }
        }
    }

    private function registrarAuditoria(?ContextoOperacion $contexto, string $accion, string $tabla, ?int $registroId, array $detalles): void
    {
        if ($this->auditoriaRepo !== null && $contexto !== null) {
            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'PROGRAMACION_FOLCLORICA',
                accion: $accion,
                entidadTipo: $tabla,
                entidadId: (string)($registroId ?? '0'),
                datosPrevios: null,
                datosNuevos: $detalles
            );
        }
    }
}
