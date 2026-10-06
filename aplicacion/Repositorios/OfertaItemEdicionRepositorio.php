<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Catalogo\EstadoCatalogo;
use Aplicacion\Entidades\ItemComercial;
use Aplicacion\Entidades\OfertaItemEdicion;
use Aplicacion\Entidades\TarifaItemEdicion;
use PDO;

class OfertaItemEdicionRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function guardar(OfertaItemEdicion $oferta): OfertaItemEdicion
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ofertas_items_edicion` (
                `organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`, `capacidad_referencial`
            ) VALUES (
                :organizacion_id, :edicion_id, :item_comercial_id, :estado, :capacidad_referencial
            )
        ");

        $stmt->execute([
            'organizacion_id' => $oferta->organizacionId,
            'edicion_id' => $oferta->edicionId,
            'item_comercial_id' => $oferta->itemComercialId,
            'estado' => $oferta->estado->value,
            'capacidad_referencial' => $oferta->capacidadReferencial,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($id, $oferta->organizacionId);
    }

    public function actualizarCapacidad(int $id, int $organizacionId, ?int $capacidadReferencial): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ofertas_items_edicion`
            SET `capacidad_referencial` = :capacidad_referencial
            WHERE `id` = :id AND `organizacion_id` = :organizacion_id
        ");

        return $stmt->execute([
            'id' => $id,
            'organizacion_id' => $organizacionId,
            'capacidad_referencial' => $capacidadReferencial,
        ]);
    }

    public function cambiarEstado(int $id, int $organizacionId, EstadoCatalogo $estado): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ofertas_items_edicion`
            SET `estado` = :estado
            WHERE `id` = :id AND `organizacion_id` = :organizacion_id
        ");

        return $stmt->execute([
            'id' => $id,
            'organizacion_id' => $organizacionId,
            'estado' => $estado->value,
        ]);
    }

    public function buscarPorId(int $id, int $organizacionId): ?OfertaItemEdicion
    {
        $stmt = $this->pdo->prepare("
            SELECT o.*,
                   t.id AS tarifa_id, t.moneda AS tarifa_moneda, t.precio AS tarifa_precio,
                   t.version_bloqueo AS tarifa_version, t.creado_en AS tarifa_creado_en,
                   t.actualizado_en AS tarifa_actualizado_en
            FROM `ofertas_items_edicion` o
            LEFT JOIN `tarifas_items_edicion` t ON t.oferta_item_id = o.id
            WHERE o.id = :id AND o.organizacion_id = :organizacion_id
        ");
        $stmt->execute(['id' => $id, 'organizacion_id' => $organizacionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    public function buscarPorItemYEdicion(int $itemComercialId, int $edicionId, int $organizacionId): ?OfertaItemEdicion
    {
        $stmt = $this->pdo->prepare("
            SELECT o.*,
                   t.id AS tarifa_id, t.moneda AS tarifa_moneda, t.precio AS tarifa_precio,
                   t.version_bloqueo AS tarifa_version, t.creado_en AS tarifa_creado_en,
                   t.actualizado_en AS tarifa_actualizado_en
            FROM `ofertas_items_edicion` o
            LEFT JOIN `tarifas_items_edicion` t ON t.oferta_item_id = o.id
            WHERE o.item_comercial_id = :item_id
              AND o.edicion_id = :edicion_id
              AND o.organizacion_id = :organizacion_id
        ");
        $stmt->execute([
            'item_id' => $itemComercialId,
            'edicion_id' => $edicionId,
            'organizacion_id' => $organizacionId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    /**
     * @return OfertaItemEdicion[]
     */
    public function listarPorEdicion(int $organizacionId, int $edicionId, ?EstadoCatalogo $estado = null): array
    {
        $sql = "
            SELECT o.*,
                   t.id AS tarifa_id, t.moneda AS tarifa_moneda, t.precio AS tarifa_precio,
                   t.version_bloqueo AS tarifa_version, t.creado_en AS tarifa_creado_en,
                   t.actualizado_en AS tarifa_actualizado_en
            FROM `ofertas_items_edicion` o
            LEFT JOIN `tarifas_items_edicion` t ON t.oferta_item_id = o.id
            WHERE o.organizacion_id = :organizacion_id AND o.edicion_id = :edicion_id
        ";
        $params = [
            'organizacion_id' => $organizacionId,
            'edicion_id' => $edicionId,
        ];

        if ($estado !== null) {
            $sql .= " AND o.estado = :estado";
            $params['estado'] = $estado->value;
        }

        $sql .= " ORDER BY o.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $res = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res[] = $this->hidratar($row);
        }
        return $res;
    }

    private function hidratar(array $row): OfertaItemEdicion
    {
        $tarifa = null;
        if (!empty($row['tarifa_id'])) {
            $tarifa = new TarifaItemEdicion(
                id: (int) $row['tarifa_id'],
                ofertaItemId: (int) $row['id'],
                moneda: (string) $row['tarifa_moneda'],
                precio: (float) $row['tarifa_precio'],
                versionBloqueo: (int) $row['tarifa_version'],
                creadoEn: (string) $row['tarifa_creado_en'],
                actualizadoEn: (string) $row['tarifa_actualizado_en']
            );
        }

        return new OfertaItemEdicion(
            id: (int) $row['id'],
            organizacionId: (int) $row['organizacion_id'],
            edicionId: (int) $row['edicion_id'],
            itemComercialId: (int) $row['item_comercial_id'],
            estado: EstadoCatalogo::from((string) $row['estado']),
            capacidadReferencial: $row['capacidad_referencial'] !== null ? (int) $row['capacidad_referencial'] : null,
            creadoEn: (string) $row['creado_en'],
            actualizadoEn: (string) $row['actualizado_en'],
            tarifaVigente: $tarifa
        );
    }
}
