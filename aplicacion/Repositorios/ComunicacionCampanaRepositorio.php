<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use InvalidArgumentException;
use PDO;

/**
 * Repositorio de Campañas Masivas de Comunicación con Control SoD (Segregación de Deberes).
 * El creador de la campaña NO puede ser el aprobador de la misma.
 */
class ComunicacionCampanaRepositorio
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listar(int $orgId, ?string $estado = null, int $limite = 50): array
    {
        $sql = "
            SELECT c.*,
                   p.nombre AS plantilla_nombre,
                   uc.nombre_completo AS creador_nombre,
                   ua.nombre_completo AS aprobador_nombre
            FROM comunicacion_campanas c
            LEFT JOIN comunicacion_plantillas p ON p.id = c.plantilla_id
            LEFT JOIN usuarios uc ON uc.id = c.creado_por
            LEFT JOIN usuarios ua ON ua.id = c.aprobado_por
            WHERE c.organizacion_id = :org_id
        ";
        $params = ['org_id' => $orgId];

        if (!empty($estado)) {
            $sql .= " AND c.estado = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY c.id DESC LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id, int $orgId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   p.nombre AS plantilla_nombre,
                   uc.nombre_completo AS creador_nombre,
                   ua.nombre_completo AS aprobador_nombre
            FROM comunicacion_campanas c
            LEFT JOIN comunicacion_plantillas p ON p.id = c.plantilla_id
            LEFT JOIN usuarios uc ON uc.id = c.creado_por
            LEFT JOIN usuarios ua ON ua.id = c.aprobado_por
            WHERE c.id = :id AND c.organizacion_id = :org_id
        ");
        $stmt->execute(['id' => $id, 'org_id' => $orgId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $criterios
     */
    public function crear(
        int $orgId,
        string $nombre,
        int $plantillaId,
        ?int $edicionId,
        array $criterios,
        float $presupuestoUsd,
        ?string $programadaPara,
        int $creadorId
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO comunicacion_campanas (
                organizacion_id, nombre, plantilla_id, edicion_id,
                criterios_segmentacion_json, estado, presupuesto_asignado_usd,
                programada_para, creado_por, creado_en
            ) VALUES (
                :org_id, :nombre, :plan_id, :edicion_id,
                :criterios, 'BORRADOR', :presupuesto,
                :programada_para, :creador_id, NOW()
            )
        ");

        $stmt->execute([
            'org_id'          => $orgId,
            'nombre'          => $nombre,
            'plan_id'         => $plantillaId,
            'edicion_id'      => $edicionId,
            'criterios'       => json_encode($criterios, JSON_UNESCAPED_UNICODE),
            'presupuesto'     => $presupuestoUsd,
            'programada_para' => $programadaPara,
            'creador_id'      => $creadorId
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Aprobación con control estricto de Segregación de Deberes (SoD).
     * @throws InvalidArgumentException
     */
    public function aprobar(int $id, int $orgId, int $aprobadorId): bool
    {
        $campana = $this->buscarPorId($id, $orgId);
        if (!$campana) {
            throw new InvalidArgumentException("Campaña #{$id} no encontrada.");
        }

        if ($campana['estado'] !== 'BORRADOR') {
            throw new InvalidArgumentException("Solo se pueden aprobar campañas en estado BORRADOR.");
        }

        // Control SoD inquebrantable: El usuario creador no puede aprobar su propia campaña
        if ((int) $campana['creado_por'] === $aprobadorId) {
            throw new InvalidArgumentException("Principio SoD: El usuario creador no puede aprobar su propia campaña.");
        }

        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_campanas SET
                estado = 'APROBADA',
                aprobado_por = :aprobador_id,
                actualizado_en = NOW()
            WHERE id = :id AND organizacion_id = :org_id AND estado = 'BORRADOR'
        ");

        $stmt->execute([
            'aprobador_id' => $aprobadorId,
            'id'           => $id,
            'org_id'       => $orgId
        ]);

        return $stmt->rowCount() > 0;
    }
}
