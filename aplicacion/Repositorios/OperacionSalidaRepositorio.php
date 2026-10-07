<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\OperacionAsistencia;
use Aplicacion\Entidades\OperacionIncidencia;
use Aplicacion\Entidades\OperacionSalida;
use Aplicacion\Entidades\OperacionSalidaPrestacion;
use Aplicacion\Entidades\OperacionSalidaRecurso;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Operaciones\EstadoAsistencia;
use Aplicacion\Operaciones\EstadoSalida;
use Aplicacion\Operaciones\RolOperativoRecurso;
use Aplicacion\Operaciones\TipoIncidenciaOperativa;
use Aplicacion\Reservas\TipoCapacidad;
use PDO;

/**
 * Repositorio de Persistencia Soberana para el Núcleo de Operaciones y Salidas de Campo.
 */
class OperacionSalidaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function generarSiguienteCorrelativo(int $organizacionId, int $anio): string
    {
        $stmtInit = $this->pdo->prepare("
            INSERT IGNORE INTO `operacion_salidas_secuencias` (`organizacion_id`, `anio`, `ultimo_numero`)
            VALUES (:org_id, :anio, 0)
        ");
        $stmtInit->execute(['org_id' => $organizacionId, 'anio' => $anio]);

        $stmtLock = $this->pdo->prepare("
            SELECT `ultimo_numero` FROM `operacion_salidas_secuencias`
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
            FOR UPDATE
        ");
        $stmtLock->execute(['org_id' => $organizacionId, 'anio' => $anio]);
        $ultimo = (int) $stmtLock->fetchColumn();

        $siguiente = $ultimo + 1;

        $stmtUp = $this->pdo->prepare("
            UPDATE `operacion_salidas_secuencias`
            SET `ultimo_numero` = :siguiente
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
        ");
        $stmtUp->execute([
            'siguiente' => $siguiente,
            'org_id'    => $organizacionId,
            'anio'      => $anio,
        ]);

        return sprintf("SAL-%04d-%06d", $anio, $siguiente);
    }

    public function guardar(OperacionSalida $s): OperacionSalida
    {
        if ($s->id === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO `operacion_salidas` (
                    `organizacion_id`, `edicion_id`, `item_comercial_id`,
                    `correlativo`, `titulo`, `fecha_salida`,
                    `hora_citacion`, `hora_salida`, `hora_inicio_real`, `hora_fin_real`,
                    `punto_encuentro`, `tipo_capacidad`, `capacidad_maxima`,
                    `estado`, `motivo_cancelacion_interrupcion`, `version_bloqueo`, `creado_por`
                ) VALUES (
                    :org_id, :edicion_id, :item_id,
                    :correlativo, :titulo, :fecha,
                    :hora_citacion, :hora_salida, :hora_inicio_real, :hora_fin_real,
                    :punto_encuentro, :tipo_capacidad, :capacidad_max,
                    :estado, :motivo, :version_bloqueo, :creado_por
                )
            ");
            $stmt->execute([
                'org_id'          => $s->organizacionId,
                'edicion_id'      => $s->edicionId,
                'item_id'         => $s->itemComercialId,
                'correlativo'     => $s->correlativo,
                'titulo'          => $s->titulo,
                'fecha'           => $s->fechaSalida,
                'hora_citacion'   => $s->horaCitacion,
                'hora_salida'     => $s->horaSalida,
                'hora_inicio_real'=> $s->horaInicioReal,
                'hora_fin_real'   => $s->horaFinReal,
                'punto_encuentro' => $s->puntoEncuentro,
                'tipo_capacidad'  => $s->tipoCapacidad->value,
                'capacidad_max'   => $s->capacidadMaxima,
                'estado'          => $s->estado->value,
                'motivo'          => $s->motivoCancelacionInterrupcion,
                'version_bloqueo' => $s->versionBloqueo,
                'creado_por'      => $s->creadoPor,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            return $this->buscarPorId($id, $s->organizacionId) ?? $s;
        }

        $stmt = $this->pdo->prepare("
            UPDATE `operacion_salidas`
            SET `titulo` = :titulo,
                `fecha_salida` = :fecha,
                `hora_citacion` = :hora_citacion,
                `hora_salida` = :hora_salida,
                `punto_encuentro` = :punto_encuentro,
                `capacidad_maxima` = :capacidad_max,
                `version_bloqueo` = `version_bloqueo` + 1
            WHERE `id` = :id AND `organizacion_id` = :org_id AND `version_bloqueo` = :version_esperada
        ");
        $stmt->execute([
            'id'               => $s->id,
            'org_id'           => $s->organizacionId,
            'titulo'           => $s->titulo,
            'fecha'            => $s->fechaSalida,
            'hora_citacion'    => $s->horaCitacion,
            'hora_salida'      => $s->horaSalida,
            'punto_encuentro'  => $s->puntoEncuentro,
            'capacidad_max'    => $s->capacidadMaxima,
            'version_esperada' => $s->versionBloqueo,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new ConflictoConcurrenciaExcepcion("La salida #{$s->id} fue modificada por otro usuario concurrente.");
        }

        return $this->buscarPorId($s->id, $s->organizacionId) ?? $s;
    }

    public function buscarPorId(int $id, int $organizacionId): ?OperacionSalida
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `operacion_salidas`
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $recursos = $this->obtenerRecursosPorSalida($id);
        $prestaciones = $this->obtenerPrestacionesPorSalida($id);
        $asistencias = $this->obtenerAsistenciasPorSalida($id);

        return $this->hidratar($fila, $recursos, $prestaciones, $asistencias);
    }

    public function buscarPorCorrelativo(string $correlativo, int $organizacionId): ?OperacionSalida
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `operacion_salidas`
            WHERE `correlativo` = :correlativo AND `organizacion_id` = :org_id
        ");
        $stmt->execute(['correlativo' => $correlativo, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $id = (int) $fila['id'];
        $recursos = $this->obtenerRecursosPorSalida($id);
        $prestaciones = $this->obtenerPrestacionesPorSalida($id);
        $asistencias = $this->obtenerAsistenciasPorSalida($id);

        return $this->hidratar($fila, $recursos, $prestaciones, $asistencias);
    }

    public function asignarPrestacion(int $salidaId, int $prestacionId, float $cantidadPasajeros, int $asignadoPor): OperacionSalidaPrestacion
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `operacion_salida_prestaciones` (
                `salida_id`, `prestacion_id`, `cantidad_pasajeros`, `asignado_por`
            ) VALUES (
                :salida_id, :prestacion_id, :cantidad, :asignado_por
            )
        ");
        $stmt->execute([
            'salida_id'     => $salidaId,
            'prestacion_id' => $prestacionId,
            'cantidad'      => $cantidadPasajeros,
            'asignado_por'  => $asignadoPor,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return new OperacionSalidaPrestacion(
            id: $id,
            salidaId: $salidaId,
            prestacionId: $prestacionId,
            cantidadPasajeros: $cantidadPasajeros,
            asignadoPor: $asignadoPor
        );
    }

    public function desasignarPrestacion(int $salidaId, int $prestacionId): void
    {
        // 1. Eliminar asistencias asociadas de esta prestación en la salida
        $stmtAsist = $this->pdo->prepare("
            DELETE FROM `operacion_asistencias`
            WHERE `salida_id` = :salida_id AND `prestacion_id` = :prestacion_id
        ");
        $stmtAsist->execute(['salida_id' => $salidaId, 'prestacion_id' => $prestacionId]);

        // 2. Eliminar la asignación de la prestación
        $stmt = $this->pdo->prepare("
            DELETE FROM `operacion_salida_prestaciones`
            WHERE `salida_id` = :salida_id AND `prestacion_id` = :prestacion_id
        ");
        $stmt->execute(['salida_id' => $salidaId, 'prestacion_id' => $prestacionId]);
    }

    /**
     * @return OperacionSalidaPrestacion[]
     */
    public function obtenerPrestacionesPorSalida(int $salidaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `operacion_salida_prestaciones`
            WHERE `salida_id` = :salida_id
            ORDER BY `id` ASC
        ");
        $stmt->execute(['salida_id' => $salidaId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => new OperacionSalidaPrestacion(
            id: (int) $f['id'],
            salidaId: (int) $f['salida_id'],
            prestacionId: (int) $f['prestacion_id'],
            cantidadPasajeros: (float) $f['cantidad_pasajeros'],
            asignadoPor: (int) $f['asignado_por'],
            asignadoEn: $f['asignado_en']
        ), $filas);
    }

    public function buscarSalidaActivaPorPrestacion(int $prestacionId): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT osp.salida_id
            FROM `operacion_salida_prestaciones` osp
            INNER JOIN `operacion_salidas` os ON os.id = osp.salida_id
            WHERE osp.prestacion_id = :prestacion_id
              AND os.estado NOT IN ('CANCELADA')
            LIMIT 1
        ");
        $stmt->execute(['prestacion_id' => $prestacionId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int) $val : null;
    }

    public function asignarRecurso(OperacionSalidaRecurso $r): OperacionSalidaRecurso
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `operacion_salida_recursos` (
                `salida_id`, `recurso_fisico_id`, `persona_id`,
                `rol_operativo`, `notas`, `asignado_por`
            ) VALUES (
                :salida_id, :recurso_id, :persona_id,
                :rol_operativo, :notas, :asignado_por
            )
        ");
        $stmt->execute([
            'salida_id'     => $r->salidaId,
            'recurso_id'    => $r->recursoFisicoId,
            'persona_id'    => $r->personaId,
            'rol_operativo' => $r->rolOperativo->value,
            'notas'         => $r->notas,
            'asignado_por'  => $r->asignadoPor,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return new OperacionSalidaRecurso(
            id: $id,
            salidaId: $r->salidaId,
            recursoFisicoId: $r->recursoFisicoId,
            personaId: $r->personaId,
            rolOperativo: $r->rolOperativo,
            notas: $r->notas,
            asignadoPor: $r->asignadoPor
        );
    }

    public function desasignarRecurso(int $salidaRecursoId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM `operacion_salida_recursos` WHERE `id` = :id");
        $stmt->execute(['id' => $salidaRecursoId]);
    }

    /**
     * @return OperacionSalidaRecurso[]
     */
    public function obtenerRecursosPorSalida(int $salidaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `operacion_salida_recursos`
            WHERE `salida_id` = :salida_id
            ORDER BY `id` ASC
        ");
        $stmt->execute(['salida_id' => $salidaId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => new OperacionSalidaRecurso(
            id: (int) $f['id'],
            salidaId: (int) $f['salida_id'],
            recursoFisicoId: $f['recurso_fisico_id'] !== null ? (int) $f['recurso_fisico_id'] : null,
            personaId: $f['persona_id'] !== null ? (int) $f['persona_id'] : null,
            rolOperativo: RolOperativoRecurso::from($f['rol_operativo']),
            notas: $f['notas'],
            asignadoPor: (int) $f['asignado_por'],
            asignadoEn: $f['asignado_en']
        ), $filas);
    }

    public function registrarAsistencia(OperacionAsistencia $a): OperacionAsistencia
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `operacion_asistencias` (
                `salida_id`, `prestacion_id`, `participante_id`,
                `ubicacion_asiento`, `estado_asistencia`, `marcado_en`, `marcado_por`, `observacion`
            ) VALUES (
                :salida_id, :prestacion_id, :participante_id,
                :asiento, :estado, :marcado_en, :marcado_por, :obs
            )
            ON DUPLICATE KEY UPDATE
                `ubicacion_asiento` = VALUES(`ubicacion_asiento`),
                `estado_asistencia` = VALUES(`estado_asistencia`),
                `marcado_en` = VALUES(`marcado_en`),
                `marcado_por` = VALUES(`marcado_por`),
                `observacion` = VALUES(`observacion`)
        ");
        $stmt->execute([
            'salida_id'       => $a->salidaId,
            'prestacion_id'   => $a->prestacionId,
            'participante_id' => $a->participanteId,
            'asiento'         => $a->ubicacionAsiento,
            'estado'          => $a->estadoAsistencia->value,
            'marcado_en'      => $a->marcadoEn,
            'marcado_por'     => $a->marcadoPor,
            'obs'             => $a->observacion,
        ]);

        $id = $a->id ?? (int) $this->pdo->lastInsertId();
        return new OperacionAsistencia(
            id: $id,
            salidaId: $a->salidaId,
            prestacionId: $a->prestacionId,
            participanteId: $a->participanteId,
            ubicacionAsiento: $a->ubicacionAsiento,
            estadoAsistencia: $a->estadoAsistencia,
            marcadoEn: $a->marcadoEn,
            marcadoPor: $a->marcadoPor,
            observacion: $a->observacion
        );
    }

    public function actualizarCheckin(
        int $salidaId,
        int $participanteId,
        EstadoAsistencia $nuevoEstado,
        ?string $asiento,
        int $marcadoPor,
        ?string $observacion = null
    ): OperacionAsistencia {
        $ahora = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("
            UPDATE `operacion_asistencias`
            SET `estado_asistencia` = :estado,
                `ubicacion_asiento` = :asiento,
                `marcado_en` = :marcado_en,
                `marcado_por` = :marcado_por,
                `observacion` = :obs
            WHERE `salida_id` = :salida_id AND `participante_id` = :participante_id
        ");
        $stmt->execute([
            'salida_id'       => $salidaId,
            'participante_id' => $participanteId,
            'estado'          => $nuevoEstado->value,
            'asiento'         => $asiento,
            'marcado_en'      => $ahora,
            'marcado_por'     => $marcadoPor,
            'obs'             => $observacion,
        ]);

        $stmtSel = $this->pdo->prepare("
            SELECT * FROM `operacion_asistencias`
            WHERE `salida_id` = :salida_id AND `participante_id` = :participante_id
        ");
        $stmtSel->execute(['salida_id' => $salidaId, 'participante_id' => $participanteId]);
        $f = $stmtSel->fetch(PDO::FETCH_ASSOC);

        return new OperacionAsistencia(
            id: (int) $f['id'],
            salidaId: (int) $f['salida_id'],
            prestacionId: (int) $f['prestacion_id'],
            participanteId: (int) $f['participante_id'],
            ubicacionAsiento: $f['ubicacion_asiento'],
            estadoAsistencia: EstadoAsistencia::from($f['estado_asistencia']),
            marcadoEn: $f['marcado_en'],
            marcadoPor: $f['marcado_por'] !== null ? (int) $f['marcado_por'] : null,
            observacion: $f['observacion'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en']
        );
    }

    /**
     * @return OperacionAsistencia[]
     */
    public function obtenerAsistenciasPorSalida(int $salidaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `operacion_asistencias`
            WHERE `salida_id` = :salida_id
            ORDER BY `id` ASC
        ");
        $stmt->execute(['salida_id' => $salidaId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => new OperacionAsistencia(
            id: (int) $f['id'],
            salidaId: (int) $f['salida_id'],
            prestacionId: (int) $f['prestacion_id'],
            participanteId: (int) $f['participante_id'],
            ubicacionAsiento: $f['ubicacion_asiento'],
            estadoAsistencia: EstadoAsistencia::from($f['estado_asistencia']),
            marcadoEn: $f['marcado_en'],
            marcadoPor: $f['marcado_por'] !== null ? (int) $f['marcado_por'] : null,
            observacion: $f['observacion'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en']
        ), $filas);
    }

    public function registrarIncidencia(OperacionIncidencia $i): OperacionIncidencia
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `operacion_incidencias` (
                `salida_id`, `tipo_incidencia`, `descripcion`,
                `acciones_tomadas`, `afecto_continuidad`, `registrado_por`
            ) VALUES (
                :salida_id, :tipo, :desc,
                :acciones, :afecto, :por
            )
        ");
        $stmt->execute([
            'salida_id' => $i->salidaId,
            'tipo'      => $i->tipoIncidencia->value,
            'desc'      => $i->descripcion,
            'acciones'  => $i->accionesTomadas,
            'afecto'    => $i->afectoContinuidad ? 1 : 0,
            'por'       => $i->registradoPor,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return new OperacionIncidencia(
            id: $id,
            salidaId: $i->salidaId,
            tipoIncidencia: $i->tipoIncidencia,
            descripcion: $i->descripcion,
            accionesTomadas: $i->accionesTomadas,
            afectoContinuidad: $i->afectoContinuidad,
            registradoPor: $i->registradoPor
        );
    }

    /**
     * @return OperacionIncidencia[]
     */
    public function obtenerIncidenciasPorSalida(int $salidaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `operacion_incidencias`
            WHERE `salida_id` = :salida_id
            ORDER BY `id` ASC
        ");
        $stmt->execute(['salida_id' => $salidaId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => new OperacionIncidencia(
            id: (int) $f['id'],
            salidaId: (int) $f['salida_id'],
            tipoIncidencia: TipoIncidenciaOperativa::from($f['tipo_incidencia']),
            descripcion: $f['descripcion'],
            accionesTomadas: $f['acciones_tomadas'],
            afectoContinuidad: (bool) $f['afecto_continuidad'],
            registradoPor: (int) $f['registrado_por'],
            registradoEn: $f['registrado_en']
        ), $filas);
    }

    public function actualizarEstado(
        int $salidaId,
        EstadoSalida $nuevoEstado,
        int $versionEsperada,
        ?string $motivo = null,
        ?string $horaInicioReal = null,
        ?string $horaFinReal = null
    ): OperacionSalida {
        $sql = "
            UPDATE `operacion_salidas`
            SET `estado` = :nuevo_estado,
                `motivo_cancelacion_interrupcion` = :motivo,
                `version_bloqueo` = `version_bloqueo` + 1
        ";
        $params = [
            'id'               => $salidaId,
            'nuevo_estado'     => $nuevoEstado->value,
            'motivo'           => $motivo,
            'version_esperada' => $versionEsperada,
        ];

        if ($horaInicioReal !== null) {
            $sql .= ", `hora_inicio_real` = :hora_inicio";
            $params['hora_inicio'] = $horaInicioReal;
        }
        if ($horaFinReal !== null) {
            $sql .= ", `hora_fin_real` = :hora_fin";
            $params['hora_fin'] = $horaFinReal;
        }

        $sql .= " WHERE `id` = :id AND `version_bloqueo` = :version_esperada";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            throw new ConflictoConcurrenciaExcepcion("La salida #{$salidaId} fue modificada por otro usuario concurrente.");
        }

        $stmtOrg = $this->pdo->prepare("SELECT `organizacion_id` FROM `operacion_salidas` WHERE `id` = :id");
        $stmtOrg->execute(['id' => $salidaId]);
        $orgId = (int) $stmtOrg->fetchColumn();

        return $this->buscarPorId($salidaId, $orgId)
            ?? throw new \RuntimeException("No se pudo recargar la salida #{$salidaId}.");
    }

    public function calcularOcupacionActiva(int $salidaId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(`cantidad_pasajeros`), 0.0)
            FROM `operacion_salida_prestaciones`
            WHERE `salida_id` = :salida_id
        ");
        $stmt->execute(['salida_id' => $salidaId]);
        return (float) $stmt->fetchColumn();
    }

    private function hidratar(
        array $f,
        array $recursos = [],
        array $prestaciones = [],
        array $asistencias = []
    ): OperacionSalida {
        return new OperacionSalida(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            edicionId: (int) $f['edicion_id'],
            itemComercialId: (int) $f['item_comercial_id'],
            correlativo: $f['correlativo'],
            titulo: $f['titulo'],
            fechaSalida: $f['fecha_salida'],
            horaCitacion: $f['hora_citacion'],
            horaSalida: $f['hora_salida'],
            horaInicioReal: $f['hora_inicio_real'],
            horaFinReal: $f['hora_fin_real'],
            puntoEncuentro: $f['punto_encuentro'],
            tipoCapacidad: TipoCapacidad::from($f['tipo_capacidad']),
            capacidadMaxima: $f['capacidad_maxima'] !== null ? (int) $f['capacidad_maxima'] : null,
            estado: EstadoSalida::from($f['estado']),
            motivoCancelacionInterrupcion: $f['motivo_cancelacion_interrupcion'],
            versionBloqueo: (int) $f['version_bloqueo'],
            creadoPor: (int) $f['creado_por'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en'],
            recursos: $recursos,
            prestaciones: $prestaciones,
            asistencias: $asistencias
        );
    }
}
