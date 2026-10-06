<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\TarifaPaqueteEdicion;
use PDO;

class TarifaPaqueteEdicionRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function guardar(TarifaPaqueteEdicion $t): TarifaPaqueteEdicion
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `tarifas_paquetes_edicion` (
                `oferta_paquete_id`, `moneda`, `precio`, `version_bloqueo`
            ) VALUES (
                :oferta_paquete_id, :moneda, :precio, :version_bloqueo
            )
        ");

        $stmt->execute([
            'oferta_paquete_id' => $t->ofertaPaqueteId,
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
            UPDATE `tarifas_paquetes_edicion`
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

    public function buscarPorId(int $id): ?TarifaPaqueteEdicion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `tarifas_paquetes_edicion` WHERE `id` = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    public function buscarPorOfertaId(int $ofertaPaqueteId): ?TarifaPaqueteEdicion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `tarifas_paquetes_edicion` WHERE `oferta_paquete_id` = :oferta_id");
        $stmt->execute(['oferta_id' => $ofertaPaqueteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    private function hidratar(array $row): TarifaPaqueteEdicion
    {
        return new TarifaPaqueteEdicion(
            id: (int) $row['id'],
            ofertaPaqueteId: (int) $row['oferta_paquete_id'],
            moneda: (string) $row['moneda'],
            precio: (float) $row['precio'],
            versionBloqueo: (int) $row['version_bloqueo'],
            creadoEn: (string) $row['creado_en'],
            actualizadoEn: (string) $row['actualizado_en']
        );
    }
}
