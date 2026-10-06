<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\HistorialTarifaItem;
use Aplicacion\Entidades\HistorialTarifaPaquete;
use PDO;

class HistorialTarifaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function registrarItem(HistorialTarifaItem $h): HistorialTarifaItem
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `historial_tarifas_items` (
                `tarifa_item_id`, `precio_anterior`, `precio_nuevo`, `moneda`, `motivo`,
                `actor_tipo`, `usuario_id`, `actor_sistema_id`, `canal_id`, `correlacion_id`
            ) VALUES (
                :tarifa_item_id, :precio_anterior, :precio_nuevo, :moneda, :motivo,
                :actor_tipo, :usuario_id, :actor_sistema_id, :canal_id, :correlacion_id
            )
        ");

        $stmt->execute([
            'tarifa_item_id' => $h->tarifaItemId,
            'precio_anterior' => $h->precioAnterior,
            'precio_nuevo' => $h->precioNuevo,
            'moneda' => $h->moneda,
            'motivo' => $h->motivo,
            'actor_tipo' => $h->actorTipo,
            'usuario_id' => $h->usuarioId,
            'actor_sistema_id' => $h->actorSistemaId,
            'canal_id' => $h->canalId,
            'correlacion_id' => $h->correlacionId,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return new HistorialTarifaItem(
            id: $id,
            tarifaItemId: $h->tarifaItemId,
            precioAnterior: $h->precioAnterior,
            precioNuevo: $h->precioNuevo,
            moneda: $h->moneda,
            motivo: $h->motivo,
            actorTipo: $h->actorTipo,
            usuarioId: $h->usuarioId,
            actorSistemaId: $h->actorSistemaId,
            canalId: $h->canalId,
            correlacionId: $h->correlacionId
        );
    }

    public function registrarPaquete(HistorialTarifaPaquete $h): HistorialTarifaPaquete
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `historial_tarifas_paquetes` (
                `tarifa_paquete_id`, `precio_anterior`, `precio_nuevo`, `moneda`, `motivo`,
                `actor_tipo`, `usuario_id`, `actor_sistema_id`, `canal_id`, `correlacion_id`
            ) VALUES (
                :tarifa_paquete_id, :precio_anterior, :precio_nuevo, :moneda, :motivo,
                :actor_tipo, :usuario_id, :actor_sistema_id, :canal_id, :correlacion_id
            )
        ");

        $stmt->execute([
            'tarifa_paquete_id' => $h->tarifaPaqueteId,
            'precio_anterior' => $h->precioAnterior,
            'precio_nuevo' => $h->precioNuevo,
            'moneda' => $h->moneda,
            'motivo' => $h->motivo,
            'actor_tipo' => $h->actorTipo,
            'usuario_id' => $h->usuarioId,
            'actor_sistema_id' => $h->actorSistemaId,
            'canal_id' => $h->canalId,
            'correlacion_id' => $h->correlacionId,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return new HistorialTarifaPaquete(
            id: $id,
            tarifaPaqueteId: $h->tarifaPaqueteId,
            precioAnterior: $h->precioAnterior,
            precioNuevo: $h->precioNuevo,
            moneda: $h->moneda,
            motivo: $h->motivo,
            actorTipo: $h->actorTipo,
            usuarioId: $h->usuarioId,
            actorSistemaId: $h->actorSistemaId,
            canalId: $h->canalId,
            correlacionId: $h->correlacionId
        );
    }

    /**
     * @return HistorialTarifaItem[]
     */
    public function listarPorTarifaItem(int $tarifaItemId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `historial_tarifas_items`
            WHERE `tarifa_item_id` = :tarifa_item_id
            ORDER BY `id` DESC
        ");
        $stmt->execute(['tarifa_item_id' => $tarifaItemId]);

        $res = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res[] = new HistorialTarifaItem(
                id: (int) $row['id'],
                tarifaItemId: (int) $row['tarifa_item_id'],
                precioAnterior: (float) $row['precio_anterior'],
                precioNuevo: (float) $row['precio_nuevo'],
                moneda: (string) $row['moneda'],
                motivo: $row['motivo'] !== null ? (string) $row['motivo'] : null,
                actorTipo: (string) $row['actor_tipo'],
                usuarioId: $row['usuario_id'] !== null ? (int) $row['usuario_id'] : null,
                actorSistemaId: $row['actor_sistema_id'] !== null ? (int) $row['actor_sistema_id'] : null,
                canalId: $row['canal_id'] !== null ? (int) $row['canal_id'] : null,
                correlacionId: (string) $row['correlacion_id'],
                creadoEn: (string) $row['creado_en']
            );
        }
        return $res;
    }

    /**
     * @return HistorialTarifaPaquete[]
     */
    public function listarPorTarifaPaquete(int $tarifaPaqueteId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `historial_tarifas_paquetes`
            WHERE `tarifa_paquete_id` = :tarifa_paquete_id
            ORDER BY `id` DESC
        ");
        $stmt->execute(['tarifa_paquete_id' => $tarifaPaqueteId]);

        $res = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res[] = new HistorialTarifaPaquete(
                id: (int) $row['id'],
                tarifaPaqueteId: (int) $row['tarifa_paquete_id'],
                precioAnterior: (float) $row['precio_anterior'],
                precioNuevo: (float) $row['precio_nuevo'],
                moneda: (string) $row['moneda'],
                motivo: $row['motivo'] !== null ? (string) $row['motivo'] : null,
                actorTipo: (string) $row['actor_tipo'],
                usuarioId: $row['usuario_id'] !== null ? (int) $row['usuario_id'] : null,
                actorSistemaId: $row['actor_sistema_id'] !== null ? (int) $row['actor_sistema_id'] : null,
                canalId: $row['canal_id'] !== null ? (int) $row['canal_id'] : null,
                correlacionId: (string) $row['correlacion_id'],
                creadoEn: (string) $row['creado_en']
            );
        }
        return $res;
    }
}
