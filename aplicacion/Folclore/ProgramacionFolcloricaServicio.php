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

    // --- PROGRAMACIÓN OFICIAL (CON PROCEDENCIA Y VERSIONES) ---

    public function publicarProgramacionOficial(
        int $organizacionId,
        int $edicionId,
        int $actividadId,
        string $fuenteOrigen,
        string $fechaEmisionFuente,
        string $etiquetaVersion = 'Rol Oficial',
        EstadoConfirmacionProgramacion $estado = EstadoConfirmacionProgramacion::OFICIAL_CONFIRMADO,
        ?string $documentoUrl = null,
        ?string $notas = null,
        ?ContextoOperacion $contexto = null
    ): ProgramacionOficialEdicion {
        $this->validarPermiso('folclore.programacion.aprobar', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $actividad = $this->programacionRepo->buscarActividadPorId($actividadId, $organizacionId);
        if ($actividad === null) {
            throw new InvalidArgumentException("La actividad folclórica especificada (#{$actividadId}) no existe.");
        }

        $ultimaVersion = $this->programacionRepo->obtenerUltimaVersionNumero($actividadId);
        $nuevaVersionNumero = $ultimaVersion + 1;

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
            creadoPor: $contexto->usuarioId ?? 1
        );

        $creado = $this->programacionRepo->crearVersionProgramacion($programacion);

        $this->registrarAuditoria($contexto, 'PROGRAMACION_OFICIAL_PUBLICADA', 'folclore_programaciones_oficiales', $creado->id, [
            'actividad_id'   => $actividadId,
            'version_numero' => $nuevaVersionNumero,
            'fuente_origen'  => $fuenteOrigen,
            'estado'         => $estado->value,
        ]);

        return $creado;
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

        $conjuntoEdicion = $this->conjuntoRepo->buscarInscripcionPorId($conjuntoEdicionId, $organizacionId);
        if ($conjuntoEdicion === null) {
            throw new InvalidArgumentException("La inscripción del conjunto (#{$conjuntoEdicionId}) no existe en su organización.");
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

    /**
     * @return array<OrdenPresentacionFolclorico>
     */
    public function obtenerRolPresentacion(int $organizacionId, int $programacionId, ?ContextoOperacion $contexto = null): array
    {
        $this->validarPermiso('folclore.programacion.ver', $contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        return $this->programacionRepo->listarTurnosPorProgramacion($programacionId);
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
