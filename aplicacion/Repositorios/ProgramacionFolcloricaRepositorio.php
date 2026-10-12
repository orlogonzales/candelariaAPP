<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Folclore\ActividadFolcloricaEdicion;
use Aplicacion\Folclore\EstadoConfirmacionProgramacion;
use Aplicacion\Folclore\EstadoTurnoPresentacion;
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
                `observaciones` = :obs,
                `version_bloqueo` = `version_bloqueo` + 1,
                `retraso_minutos` = :retraso
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
            'retraso'      => $turno->retrasoMinutos ?? 0,
        ]);
    }

    /**
     * Actualiza el estado operativo y horarios reales del turno implementando control de concurrencia optimista (HTTP 409).
     */
    public function actualizarEstadoOperativoTurno(
        int $turnoId,
        EstadoTurnoPresentacion $nuevoEstado,
        ?string $horaRealPartida,
        ?string $horaRealFin,
        ?string $observaciones,
        ?int $retrasoMinutos,
        int $versionBloqueoEsperada
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE `folclore_orden_presentacion`
            SET `estado_turno` = :estado,
                `hora_real_partida` = :real_partida,
                `hora_real_fin` = :real_fin,
                `observaciones` = :observaciones,
                `retraso_minutos` = :retraso,
                `version_bloqueo` = `version_bloqueo` + 1
            WHERE `id` = :id AND `version_bloqueo` = :version_esperada
        ");

        $stmt->execute([
            'id'               => $turnoId,
            'estado'           => $nuevoEstado->value,
            'real_partida'     => $horaRealPartida,
            'real_fin'         => $horaRealFin,
            'observaciones'    => $observaciones,
            'retraso'          => $retrasoMinutos,
            'version_esperada' => $versionBloqueoEsperada,
        ]);

        if ($stmt->rowCount() === 0) {
            // Verificar si el registro existe para detectar colisión de concurrencia optimista
            $stmtCheck = $this->pdo->prepare("SELECT version_bloqueo FROM `folclore_orden_presentacion` WHERE id = :id");
            $stmtCheck->execute(['id' => $turnoId]);
            $fila = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($fila !== false) {
                throw new ConflictoConcurrenciaExcepcion(
                    "Conflicto de concurrencia (HTTP 409): El turno folclórico (#{$turnoId}) fue modificado por otro operador (versión en base de datos: {$fila['version_bloqueo']}, versión enviada: {$versionBloqueoEsperada}). Recargue el tablero para visualizar los datos actualizados."
                );
            }

            return false;
        }

        return true;
    }

    /**
     * Obtiene los turnos enriquecidos con información del conjunto, danza, salidas de cobertura
     * asignadas y métricas para el Tablero Operativo de Seguimiento Folclórico (Fase 3.1C).
     *
     * @return array{turnos: array, kpis: array}
     */
    public function obtenerDatosTableroSeguimiento(
        int $organizacionId,
        int $edicionId,
        ?int $actividadId = null,
        ?int $conjuntoId = null,
        ?EstadoTurnoPresentacion $filtroEstado = null,
        ?int $personaAsignadaId = null
    ): array {
        $sql = "
            SELECT o.*,
                   c.id as conjunto_id,
                   c.nombre as conjunto_nombre,
                   c.color_emblematico_hex as conjunto_color,
                   c.sigla as conjunto_sigla,
                   t.nombre as tipo_danza_nombre,
                   t.categoria_origen as categoria_danza,
                   a.id as actividad_id,
                   a.titulo as actividad_titulo,
                   a.fecha_inicio as actividad_fecha,
                   p.id as programacion_oficial_id,
                   p.version_numero as programacion_version,
                   p.etiqueta_version as programacion_etiqueta,
                    (SELECT GROUP_CONCAT(b.nombre SEPARATOR ', ') FROM `bloques_conjunto` b WHERE b.conjunto_id = c.id AND b.activo = 1) as bloque_nombre
            FROM `folclore_orden_presentacion` o
            JOIN `folclore_programaciones_oficiales` p ON o.programacion_oficial_id = p.id
            JOIN `folclore_actividades_edicion` a ON p.actividad_id = a.id
            JOIN `edicion_conjunto_inscripciones` i ON o.conjunto_edicion_id = i.id
            JOIN `conjuntos_folcloricos` c ON i.conjunto_id = c.id
            JOIN `tipos_danza` t ON c.tipo_danza_id = t.id
            WHERE a.organizacion_id = :org_id
              AND a.edicion_id = :edicion_id
              AND p.es_version_activa = 1
        ";

        $params = [
            'org_id'     => $organizacionId,
            'edicion_id' => $edicionId,
        ];

        if ($actividadId !== null && $actividadId > 0) {
            $sql .= " AND a.id = :actividad_id";
            $params['actividad_id'] = $actividadId;
        }

        if ($conjuntoId !== null && $conjuntoId > 0) {
            $sql .= " AND c.id = :conjunto_id";
            $params['conjunto_id'] = $conjuntoId;
        }

        if ($filtroEstado !== null) {
            $sql .= " AND o.estado_turno = :estado_turno";
            $params['estado_turno'] = $filtroEstado->value;
        }

        $sql .= " ORDER BY o.orden_sorteo ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $turnosFilas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($turnosFilas)) {
            return [
                'turnos' => [],
                'kpis'   => [
                    'total_turnos'      => 0,
                    'pendientes'        => 0,
                    'en_preparacion'    => 0,
                    'en_desplazamiento' => 0,
                    'en_presentacion'   => 0,
                    'en_curso'          => 0,
                    'finalizados'       => 0,
                    'no_presentados'    => 0,
                    'con_retraso'       => 0,
                    'sin_cobertura'     => 0,
                    'cobertura_activa'  => 0,
                ],
            ];
        }

        $turnoIds = array_column($turnosFilas, 'id');
        $placeholders = implode(',', array_fill(0, count($turnoIds), '?'));

        // Obtener salidas de cobertura vinculadas y su personal
        $sqlSalidas = "
            SELECT s.id as salida_id,
                   s.correlativo,
                   s.titulo,
                   s.estado as estado_salida,
                   s.fecha_salida,
                   s.hora_salida,
                   s.orden_presentacion_id,
                   r.id as recurso_asignacion_id,
                   r.rol_operativo,
                   p.id as persona_id,
                   p.nombres as persona_nombres,
                   p.apellidos as persona_apellidos
            FROM `operacion_salidas` s
            LEFT JOIN `operacion_salida_recursos` r ON s.id = r.salida_id
            LEFT JOIN `personas` p ON r.persona_id = p.id
            WHERE s.organizacion_id = ?
              AND s.orden_presentacion_id IN ({$placeholders})
            ORDER BY s.id ASC
        ";

        $stmtSalidas = $this->pdo->prepare($sqlSalidas);
        $stmtSalidas->execute(array_merge([$organizacionId], $turnoIds));
        $salidasFilas = $stmtSalidas->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar salidas y recursos por orden_presentacion_id
        $coberturaPorTurno = [];
        foreach ($salidasFilas as $sf) {
            $tId = (int)$sf['orden_presentacion_id'];
            $sId = (int)$sf['salida_id'];

            if (!isset($coberturaPorTurno[$tId][$sId])) {
                $coberturaPorTurno[$tId][$sId] = [
                    'salida_id'     => $sId,
                    'correlativo'   => $sf['correlativo'],
                    'titulo'        => $sf['titulo'],
                    'estado_salida' => $sf['estado_salida'],
                    'hora_salida'   => $sf['hora_salida'],
                    'personal'      => [],
                ];
            }

            if (!empty($sf['persona_id'])) {
                $coberturaPorTurno[$tId][$sId]['personal'][] = [
                    'persona_id'      => (int)$sf['persona_id'],
                    'nombre_completo' => trim(($sf['persona_nombres'] ?? '') . ' ' . ($sf['persona_apellidos'] ?? '')),
                    'rol_operativo'   => $sf['rol_operativo'],
                ];
            }
        }

        // Zona horaria de la festividad (America/Lima)
        $ahora = new \DateTimeImmutable('now', new \DateTimeZone('America/Lima'));

        $turnosResultado = [];
        $kpis = [
            'total_turnos'      => 0,
            'pendientes'        => 0,
            'en_preparacion'    => 0,
            'en_desplazamiento' => 0,
            'en_presentacion'   => 0,
            'en_curso'          => 0,
            'finalizados'       => 0,
            'no_presentados'    => 0,
            'con_retraso'       => 0,
            'sin_cobertura'     => 0,
            'cobertura_activa'  => 0,
        ];

        foreach ($turnosFilas as $f) {
            $tId = (int)$f['id'];
            $salidas = isset($coberturaPorTurno[$tId]) ? array_values($coberturaPorTurno[$tId]) : [];

            // Si hay filtro por persona asignada, verificar si está presente en alguna salida
            if ($personaAsignadaId !== null && $personaAsignadaId > 0) {
                $personaEncontrada = false;
                foreach ($salidas as $sal) {
                    foreach ($sal['personal'] as $pers) {
                        if ($pers['persona_id'] === $personaAsignadaId) {
                            $personaEncontrada = true;
                            break 2;
                        }
                    }
                }
                if (!$personaEncontrada) {
                    continue;
                }
            }

            // Cálculo determinista de retraso en minutos
            $retrasoCalculado = 0;
            $fechaActividad = $f['actividad_fecha'] ?? date('Y-m-d');
            $horaEstimada = $f['hora_estimada_partida'];

            try {
                $dtEstimada = new \DateTimeImmutable("{$fechaActividad} {$horaEstimada}", new \DateTimeZone('America/Lima'));

                if (!empty($f['hora_real_partida'])) {
                    $dtReal = new \DateTimeImmutable($f['hora_real_partida'], new \DateTimeZone('America/Lima'));
                    $difSegundos = $dtReal->getTimestamp() - $dtEstimada->getTimestamp();
                    $retrasoCalculado = max(0, (int)floor($difSegundos / 60));
                } elseif ($f['estado_turno'] === 'PROGRAMADO') {
                    // Retraso en curso si ya pasó la hora estimada en la misma fecha
                    if ($ahora > $dtEstimada && $ahora->format('Y-m-d') === $fechaActividad) {
                        $difSegundos = $ahora->getTimestamp() - $dtEstimada->getTimestamp();
                        $retrasoCalculado = max(0, (int)floor($difSegundos / 60));
                    }
                }
            } catch (\Throwable) {
                $retrasoCalculado = 0;
            }

            // Si en BD ya se grabó un retraso manual superior, prevalece
            $retrasoFinal = max((int)($f['retraso_minutos'] ?? 0), $retrasoCalculado);

            $f['salidas_cobertura'] = $salidas;
            $f['retraso_minutos']   = $retrasoFinal;

            $turnoObj = OrdenPresentacionFolclorico::desdeArreglo($f);
            $datosTurno = $turnoObj->aArreglo();
            $datosTurno['actividad_id']         = (int)$f['actividad_id'];
            $datosTurno['actividad_titulo']     = $f['actividad_titulo'];
            $datosTurno['actividad_fecha']      = $f['actividad_fecha'];
            $datosTurno['conjunto_emblema_url'] = $f['conjunto_emblema_url'] ?? null;
            $datosTurno['categoria_danza']      = $f['categoria_danza'] ?? null;

            $turnosResultado[] = $datosTurno;

            // Acumular KPIs
            $kpis['total_turnos']++;
            match ($f['estado_turno']) {
                'PROGRAMADO'     => $kpis['pendientes']++,
                'EN_PREPARACION' => $kpis['en_preparacion']++,
                'EN_RUTA'        => $kpis['en_desplazamiento']++,
                'EN_ESCENARIO'   => $kpis['en_presentacion']++,
                'FINALIZADO'     => $kpis['finalizados']++,
                'NO_PRESENTO'    => $kpis['no_presentados']++,
                default          => null,
            };

            if (in_array($f['estado_turno'], ['EN_PREPARACION', 'EN_RUTA', 'EN_ESCENARIO'], true)) {
                $kpis['en_curso']++;
            }

            if ($retrasoFinal > 0) {
                $kpis['con_retraso']++;
            }

            if (empty($salidas)) {
                $kpis['sin_cobertura']++;
            } else {
                $kpis['cobertura_activa']++;
            }
        }

        return [
            'turnos' => $turnosResultado,
            'kpis'   => $kpis,
        ];
    }

    public function eliminarTurno(int $turnoId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `folclore_orden_presentacion` WHERE `id` = :id");
        return $stmt->execute(['id' => $turnoId]);
    }
}

