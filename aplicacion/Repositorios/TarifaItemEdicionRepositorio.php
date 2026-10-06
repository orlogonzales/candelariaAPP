<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\TarifaItemEdicion;
use PDO;

class TarifaItemEdicionRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function guardar(TarifaItemEdicion $t): TarifaItemEdicion
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `tarifas_items_edicion` (
                `oferta_item_id`, `moneda`, `precio`, `version_bloqueo`
            ) VALUES (
                :oferta_item_id, :moneda, :precio, :version_bloqueo
            )
        ");

        $stmt->execute([
            'oferta_item_id' => $t->ofertaItemId,
            'moneda' => $t->moneda,
            'precio' => $t->precio,
            'version_bloqueo' => $t->versionBloqueo,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($id);
    }

    /**
     * Actualiza el precio con verificación estricta de concurrencia optimista y soberanía tenant.
     * Incrementa la versión atómicamente si coincide; retorna false si la versión o tenant difieren.
     */
    public function actualizarPrecio(int $id, int $organizacionId, float $nuevoPrecio, int $versionActual): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `tarifas_items_edicion` t
            INNER JOIN `ofertas_items_edicion` o ON o.`id` = t.`oferta_item_id`
            SET t.`precio` = :precio,
                t.`version_bloqueo` = t.`version_bloqueo` + 1
            WHERE t.`id` = :id
              AND o.`organizacion_id` = :organizacion_id
              AND t.`version_bloqueo` = :version_actual
        ");

        $stmt->execute([
            'id' => $id,
            'organizacion_id' => $organizacionId,
            'precio' => $nuevoPrecio,
            'version_actual' => $versionActual,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function buscarPorId(int $id): ?TarifaItemEdicion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `tarifas_items_edicion` WHERE `id` = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    public function buscarPorOfertaId(int $ofertaItemId): ?TarifaItemEdicion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `tarifas_items_edicion` WHERE `oferta_item_id` = :oferta_id");
        $stmt->execute(['oferta_id' => $ofertaItemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    private function hidratar(array $row): TarifaItemEdicion
    {
        return new TarifaItemEdicion(
            id: (int) $row['id'],
            ofertaItemId: (int) $row['oferta_item_id'],
            moneda: (string) $row['moneda'],
            precio: (float) $row['precio'],
            versionBloqueo: (int) $row['version_bloqueo'],
            creadoEn: (string) $row['creado_en'],
            actualizadoEn: (string) $row['actualizado_en']
        );
    }
}
