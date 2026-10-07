<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\ItemConfiguracionOperativa;
use Aplicacion\Reservas\TipoCapacidad;
use PDO;

/**
 * Repositorio para la configuración operativa del catálogo.
 */
class ItemConfiguracionOperativaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function buscarPorItemId(int $itemComercialId): ?ItemConfiguracionOperativa
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `item_configuracion_operativa`
            WHERE `item_comercial_id` = :item_id
        ");
        $stmt->execute(['item_id' => $itemComercialId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        return $this->hidratar($fila);
    }

    /**
     * @param int[] $itemIds
     * @return array<int, ItemConfiguracionOperativa>
     */
    public function buscarPorItemIds(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if (empty($itemIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT * FROM `item_configuracion_operativa`
            WHERE `item_comercial_id` IN ({$placeholders})
        ");
        $stmt->execute($itemIds);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $ent = $this->hidratar($f);
            $resultado[$ent->itemComercialId] = $ent;
        }

        return $resultado;
    }

    public function guardar(ItemConfiguracionOperativa $c): ItemConfiguracionOperativa
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `item_configuracion_operativa` (
                `item_comercial_id`, `requiere_reserva`, `requiere_agendamiento`,
                `requiere_participantes`, `tipo_capacidad`, `es_accesorio`,
                `duracion_estimada_minutos`, `punto_partida_predeterminado`
            ) VALUES (
                :item_id, :req_res, :req_agenda,
                :req_part, :tipo_cap, :es_acc,
                :duracion, :punto_partida
            ) ON DUPLICATE KEY UPDATE
                `requiere_reserva` = VALUES(`requiere_reserva`),
                `requiere_agendamiento` = VALUES(`requiere_agendamiento`),
                `requiere_participantes` = VALUES(`requiere_participantes`),
                `tipo_capacidad` = VALUES(`tipo_capacidad`),
                `es_accesorio` = VALUES(`es_accesorio`),
                `duracion_estimada_minutos` = VALUES(`duracion_estimada_minutos`),
                `punto_partida_predeterminado` = VALUES(`punto_partida_predeterminado`)
        ");

        $stmt->execute([
            'item_id'       => $c->itemComercialId,
            'req_res'       => $c->requiereReserva ? 1 : 0,
            'req_agenda'    => $c->requiereAgendamiento ? 1 : 0,
            'req_part'      => $c->requiereParticipantes ? 1 : 0,
            'tipo_cap'      => $c->tipoCapacidad->value,
            'es_acc'        => $c->esAccesorio ? 1 : 0,
            'duracion'      => $c->duracionEstimadaMinutos,
            'punto_partida' => $c->puntoPartidaPredeterminado,
        ]);

        $id = $c->id ?? (int) $this->pdo->lastInsertId();
        if ($id === 0 && $c->id === null) {
            $buscado = $this->buscarPorItemId($c->itemComercialId);
            $id = $buscado?->id;
        }

        return $this->buscarPorItemId($c->itemComercialId);
    }

    private function hidratar(array $f): ItemConfiguracionOperativa
    {
        return new ItemConfiguracionOperativa(
            id: (int) $f['id'],
            itemComercialId: (int) $f['item_comercial_id'],
            requiereReserva: (bool) $f['requiere_reserva'],
            requiereAgendamiento: (bool) $f['requiere_agendamiento'],
            requiereParticipantes: (bool) $f['requiere_participantes'],
            tipoCapacidad: TipoCapacidad::from($f['tipo_capacidad']),
            esAccesorio: (bool) $f['es_accesorio'],
            duracionEstimadaMinutos: $f['duracion_estimada_minutos'] !== null ? (int) $f['duracion_estimada_minutos'] : null,
            puntoPartidaPredeterminado: $f['punto_partida_predeterminado'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en']
        );
    }
}
