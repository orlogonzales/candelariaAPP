<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Folclore\ActividadFolcloricaEdicion;
use Aplicacion\Folclore\EstadoConfirmacionProgramacion;
use Aplicacion\Folclore\OrdenPresentacionFolclorico;
use Aplicacion\Folclore\ProgramacionOficialEdicion;
use PDO;

class ProgramacionFolcloricaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    // --- ACTIVIDADES ---

    public function crearActividad(ActividadFolcloricaEdicion $actividad): ActividadFolcloricaEdicion
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `folclore_actividades_edicion` (
                `organizacion_id`, `edicion_id`, `codigo`, `titulo`,
                `fecha_inicio`, `fecha_fin`, `lugar_principal`, `estado`
            ) VALUES (
                :org_id, :ed_id, :codigo, :titulo,
                :inicio, :fin, :lugar, :estado
            )
        ");

        $stmt->execute([
            'org_id'  => $actividad->organizacionId,
            'ed_id'   => $actividad->edicionId,
            'codigo'  => strtoupper(trim($actividad->codigo)),
            'titulo'  => strtoupper(trim($actividad->titulo)),
            'inicio'  => $actividad->fechaInicio,
            'fin'     => $actividad->fechaFin,
            'lugar'   => $actividad->lugarPrincipal,
            'estado'  => $actividad->estado->value,
        ]);

        $actividad->id = (int)$this->pdo->lastInsertId();
        return $actividad;
    }

    public function buscarActividadPorId(int $id, int $organizacionId): ?ActividadFolcloricaEdicion
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `folclore_actividades_edicion`
            WHERE `id` = :id AND `organizacion_id` = :org_id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ActividadFolcloricaEdicion::desdeArreglo($fila) : null;
    }

    /**
     * @return array<ActividadFolcloricaEdicion>
     */
    public function listarActividadesPorEdicion(int $edicionId, int $organizacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `folclore_actividades_edicion`
            WHERE `edicion_id` = :ed_id AND `organizacion_id` = :org_id
            ORDER BY `fecha_inicio` ASC, `id` ASC
        ");
        $stmt->execute(['ed_id' => $edicionId, 'org_id' => $organizacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => ActividadFolcloricaEdicion::desdeArreglo($f), $filas);
    }

    // --- PROGRAMACIÓN OFICIAL (VERSIONES) ---

    public function crearVersionProgramacion(ProgramacionOficialEdicion $prog): ProgramacionOficialEdicion
    {
        // Si se marca como activa, inactivar versiones anteriores de la misma actividad
        if ($prog->esVersionActiva) {
            $stmtDesact = $this->pdo->prepare("
                UPDATE `folclore_programaciones_oficiales`
                SET `es_version_activa` = 0
                WHERE `actividad_id` = :act_id
            ");
            $stmtDesact->execute(['act_id' => $prog->actividadId]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO `folclore_programaciones_oficiales` (
                `organizacion_id`, `edicion_id`, `actividad_id`, `version_numero`,
                `etiqueta_version`, `fuente_origen`, `fecha_emision_fuente`,
                `documento_referencia_url`, `estado_confirmacion`, `es_version_activa`,
                `notas_version`, `creado_por`
            ) VALUES (
                :org_id, :ed_id, :act_id, :version,
                :etiqueta, :fuente, :fecha_fuente,
                :doc_url, :estado, :activa,
                :notas, :creado_por
            )
        ");

        $stmt->execute([
            'org_id'       => $prog->organizacionId,
            'ed_id'        => $prog->edicionId,
            'act_id'       => $prog->actividadId,
            'version'      => $prog->versionNumero,
            'etiqueta'     => $prog->etiquetaVersion,
            'fuente'       => $prog->fuenteOrigen,
            'fecha_fuente' => $prog->fechaEmisionFuente,
            'doc_url'      => $prog->documentoReferenciaUrl,
            'estado'       => $prog->estadoConfirmacion->value,
            'activa'       => $prog->esVersionActiva ? 1 : 0,
            'notas'        => $prog->notasVersion,
            'creado_por'   => $prog->creadoPor,
        ]);

        $prog->id = (int)$this->pdo->lastInsertId();
        return $prog;
    }

    public function buscarProgramacionPorId(int $id, int $organizacionId): ?ProgramacionOficialEdicion
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `folclore_programaciones_oficiales`
            WHERE `id` = :id AND `organizacion_id` = :org_id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ProgramacionOficialEdicion::desdeArreglo($fila) : null;
    }

    public function obtenerUltimaVersionNumero(int $actividadId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(`version_numero`), 0)
            FROM `folclore_programaciones_oficiales`
            WHERE `actividad_id` = :act_id
        ");
        $stmt->execute(['act_id' => $actividadId]);
        return (int)$stmt->fetchColumn();
    }

    public function buscarProgramacionActiva(int $actividadId, int $organizacionId): ?ProgramacionOficialEdicion
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `folclore_programaciones_oficiales`
            WHERE `actividad_id` = :act_id AND `organizacion_id` = :org_id AND `es_version_activa` = 1
            LIMIT 1
        ");
        $stmt->execute(['act_id' => $actividadId, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ProgramacionOficialEdicion::desdeArreglo($fila) : null;
    }

    // --- TURNOS DE PRESENTACIÓN ---

    public function registrarTurnoPresentacion(OrdenPresentacionFolclorico $turno): OrdenPresentacionFolclorico
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `folclore_orden_presentacion` (
                `programacion_oficial_id`, `conjunto_edicion_id`, `orden_sorteo`,
                `hora_estimada_partida`, `hora_estimada_llegada`, `punto_partida_especifico`,
                `estado_turno`, `hora_real_partida`, `hora_real_fin`, `observaciones`
            ) VALUES (
                :prog_id, :conj_ed_id, :sorteo,
                :partida, :llegada, :punto,
                :estado, :real_partida, :real_fin, :obs
            )
        ");

        $stmt->execute([
            'prog_id'      => $turno->programacionOficialId,
            'conj_ed_id'   => $turno->conjuntoEdicionId,
            'sorteo'       => $turno->ordenSorteo,
            'partida'      => $turno->horaEstimadaPartida,
            'llegada'      => $turno->horaEstimadaLlegada,
            'punto'        => $turno->puntoPartidaEspecifico,
            'estado'       => $turno->estadoTurno->value,
            'real_partida' => $turno->horaRealPartida,
            'real_fin'     => $turno->horaRealFin,
            'obs'          => $turno->observaciones,
        ]);

        $turno->id = (int)$this->pdo->lastInsertId();
        return $turno;
    }

    public function buscarTurnoPorId(int $id): ?OrdenPresentacionFolclorico
    {
        $stmt = $this->pdo->prepare("
            SELECT o.*, c.nombre as conjunto_nombre, t.nombre as tipo_danza_nombre
            FROM `folclore_orden_presentacion` o
            JOIN `edicion_conjunto_inscripciones` i ON o.conjunto_edicion_id = i.id
            JOIN `conjuntos_folcloricos` c ON i.conjunto_id = c.id
            JOIN `tipos_danza` t ON c.tipo_danza_id = t.id
            WHERE o.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? OrdenPresentacionFolclorico::desdeArreglo($fila) : null;
    }

    /**
     * @return array<OrdenPresentacionFolclorico>
     */
    public function listarTurnosPorProgramacion(int $programacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT o.*, c.nombre as conjunto_nombre, t.nombre as tipo_danza_nombre
            FROM `folclore_orden_presentacion` o
            JOIN `edicion_conjunto_inscripciones` i ON o.conjunto_edicion_id = i.id
            JOIN `conjuntos_folcloricos` c ON i.conjunto_id = c.id
            JOIN `tipos_danza` t ON c.tipo_danza_id = t.id
            WHERE o.programacion_oficial_id = :prog_id
            ORDER BY o.orden_sorteo ASC
        ");
        $stmt->execute(['prog_id' => $programacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => OrdenPresentacionFolclorico::desdeArreglo($f), $filas);
    }

    /**
     * @return array<ProgramacionOficialEdicion>
     */
    public function listarVersionesPorActividad(int $actividadId, int $organizacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `folclore_programaciones_oficiales`
            WHERE `actividad_id` = :act_id AND `organizacion_id` = :org_id
            ORDER BY `version_numero` DESC
        ");
        $stmt->execute(['act_id' => $actividadId, 'org_id' => $organizacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => ProgramacionOficialEdicion::desdeArreglo($f), $filas);
    }

    public function oficializarVersion(int $programacionId, int $organizacionId, ?int $aprobadorId = null, ?string $aprobadoEn = null): bool
    {
        $prog = $this->buscarProgramacionPorId($programacionId, $organizacionId);
        if ($prog === null) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Inactivar todas las versiones de esta actividad
            $stmtDesact = $this->pdo->prepare("
                UPDATE `folclore_programaciones_oficiales`
                SET `es_version_activa` = 0
                WHERE `actividad_id` = :act_id AND `organizacion_id` = :org_id
            ");
            $stmtDesact->execute(['act_id' => $prog->actividadId, 'org_id' => $organizacionId]);

            // 2. Activar y marcar como oficial confirmada la versión seleccionada con trazabilidad de aprobador
            $stmtAct = $this->pdo->prepare("
                UPDATE `folclore_programaciones_oficiales`
                SET `es_version_activa` = 1,
                    `estado_confirmacion` = 'OFICIAL_CONFIRMADO',
                    `aprobado_por` = :aprobador_id,
                    `aprobado_en` = :aprobado_en
                WHERE `id` = :id AND `organizacion_id` = :org_id
            ");
            $stmtAct->execute([
                'id'           => $programacionId,
                'org_id'       => $organizacionId,
                'aprobador_id' => $aprobadorId,
                'aprobado_en'  => $aprobadoEn ?? date('Y-m-d H:i:s'),
            ]);

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function actualizarActividad(ActividadFolcloricaEdicion $actividad): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `folclore_actividades_edicion`
            SET `titulo` = :titulo,
                `fecha_inicio` = :inicio,
                `fecha_fin` = :fin,
                `lugar_principal` = :lugar,
                `estado` = :estado
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");

        return $stmt->execute([
            'id'     => $actividad->id,
            'org_id' => $actividad->organizacionId,
            'titulo' => strtoupper(trim($actividad->titulo)),
            'inicio' => $actividad->fechaInicio,
            'fin'    => $actividad->fechaFin,
            'lugar'  => $actividad->lugarPrincipal,
            'estado' => $actividad->estado->value,
        ]);
    }

    public function actualizarTurno(OrdenPresentacionFolclorico $turno): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `folclore_orden_presentacion`
            SET `orden_sorteo` = :sorteo,
                `hora_estimada_partida` = :partida,
                `hora_estimada_llegada` = :llegada,
                `punto_partida_especifico` = :punto,
                `estado_turno` = :estado,
                `hora_real_partida` = :real_partida,
                `hora_real_fin` = :real_fin,
                `observaciones` = :obs
            WHERE `id` = :id
        ");

        return $stmt->execute([
            'id'           => $turno->id,
            'sorteo'       => $turno->ordenSorteo,
            'partida'      => $turno->horaEstimadaPartida,
            'llegada'      => $turno->horaEstimadaLlegada,
            'punto'        => $turno->puntoPartidaEspecifico,
            'estado'       => $turno->estadoTurno->value,
            'real_partida' => $turno->horaRealPartida,
            'real_fin'     => $turno->horaRealFin,
            'obs'          => $turno->observaciones,
        ]);
    }

    public function eliminarTurno(int $turnoId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `folclore_orden_presentacion` WHERE `id` = :id");
        return $stmt->execute(['id' => $turnoId]);
    }
}
