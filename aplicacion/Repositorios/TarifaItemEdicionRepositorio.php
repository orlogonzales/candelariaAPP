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
     * Actualiza el precio con verificación estricta de concurrencia optimista.
     * Incrementa la versión si coincide; retorna false si hubo conflicto concurrente.
     */
    public function actualizarPrecio(int $id, float $nuevoPrecio, int $versionActual): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `tarifas_items_edicion`
            SET `precio` = :precio,
                `version_bloqueo` = `version_bloqueo` + 1
            WHERE `id` = :id AND `version_bloqueo` = :version_actual
        ");

        $stmt->execute([
            'id' => $id,
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
