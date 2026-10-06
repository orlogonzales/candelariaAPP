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
     * Actualiza el precio con verificación estricta de concurrencia optimista y soberanía tenant.
     * Incrementa la versión atómicamente si coincide; retorna false si la versión o tenant difieren.
     */
    public function actualizarPrecio(int $id, int $organizacionId, float $nuevoPrecio, int $versionActual): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `tarifas_paquetes_edicion` t
            INNER JOIN `ofertas_paquetes_edicion` o ON o.`id` = t.`oferta_paquete_id`
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
