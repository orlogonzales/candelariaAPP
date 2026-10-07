<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\EntregaItem;
use Aplicacion\Entidades\EntregaProducto;
use Aplicacion\Reservas\EstadoEntregaProducto;
use PDO;

/**
 * Repositorio para la gestión de Órdenes de Entrega de Bienes Tangibles.
 */
class EntregaProductoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function generarSiguienteCorrelativo(int $organizacionId, int $anio): string
    {
        $stmtInit = $this->pdo->prepare("
            INSERT IGNORE INTO `entregas_secuencias` (`organizacion_id`, `anio`, `ultimo_numero`)
            VALUES (:org_id, :anio, 0)
        ");
        $stmtInit->execute(['org_id' => $organizacionId, 'anio' => $anio]);

        $stmtLock = $this->pdo->prepare("
            SELECT `ultimo_numero` FROM `entregas_secuencias`
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
            FOR UPDATE
        ");
        $stmtLock->execute(['org_id' => $organizacionId, 'anio' => $anio]);
        $ultimo = (int) $stmtLock->fetchColumn();

        $siguiente = $ultimo + 1;

        $stmtUp = $this->pdo->prepare("
            UPDATE `entregas_secuencias`
            SET `ultimo_numero` = :siguiente
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
        ");
        $stmtUp->execute([
            'siguiente' => $siguiente,
            'org_id'    => $organizacionId,
            'anio'      => $anio,
        ]);

        return sprintf('ENT-%04d-%06d', $anio, $siguiente);
    }

    public function guardar(EntregaProducto $e): EntregaProducto
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `entregas_productos` (
                `organizacion_id`, `edicion_id`, `venta_id`, `cliente_id`,
                `correlativo`, `estado`, `contacto_nombre`, `contacto_telefono`,
                `direccion_entrega`, `fecha_entrega`, `entregado_por`, `notas_despacho`,
                `version_bloqueo`, `creado_por`
            ) VALUES (
                :org_id, :edicion_id, :venta_id, :cliente_id,
                :correlativo, :estado, :contacto_nom, :contacto_tel,
                :direccion, :fecha_ent, :entregado_por, :notas,
                :version_bloqueo, :creado_por
            )
        ");

        $stmt->execute([
            'org_id'          => $e->organizacionId,
            'edicion_id'      => $e->edicionId,
            'venta_id'        => $e->ventaId,
            'cliente_id'      => $e->clienteId,
            'correlativo'     => $e->correlativo,
            'estado'          => $e->estado->value,
            'contacto_nom'    => $e->contactoNombre,
            'contacto_tel'    => $e->contactoTelefono,
            'direccion'       => $e->direccionEntrega,
            'fecha_ent'       => $e->fechaEntrega,
            'entregado_por'   => $e->entregadoPor,
            'notas'           => $e->notasDespacho,
            'version_bloqueo' => $e->versionBloqueo,
            'creado_por'      => $e->creadoPor,
        ]);

        $entregaId = (int) $this->pdo->lastInsertId();

        $stmtItem = $this->pdo->prepare("
            INSERT INTO `entrega_items` (
                `entrega_id`, `venta_linea_id`, `venta_linea_componente_id`,
                `item_comercial_id`, `concepto_codigo`, `concepto_nombre`,
                `unidad_medida`, `cantidad`
            ) VALUES (
                :entrega_id, :vta_linea_id, :vta_comp_id,
                :item_id, :concepto_cod, :concepto_nom,
                :unidad, :cantidad
            )
        ");

        foreach ($e->items as $it) {
            $stmtItem->execute([
                'entrega_id'   => $entregaId,
                'vta_linea_id' => $it->ventaLineaId,
                'vta_comp_id'  => $it->ventaLineaComponenteId,
                'item_id'      => $it->itemComercialId,
                'concepto_cod' => $it->conceptoCodigo,
                'concepto_nom' => $it->conceptoNombre,
                'unidad'       => $it->unidadMedida,
                'cantidad'     => $it->cantidad,
            ]);
        }

        return $this->buscarPorId($entregaId);
    }

    public function buscarPorId(int $id): ?EntregaProducto
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `entregas_productos` WHERE `id` = :id");
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $items = $this->obtenerItems($id);
        return $this->hidratar($fila, $items);
    }

    public function buscarPorVentaId(int $ventaId): ?EntregaProducto
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `entregas_productos` WHERE `venta_id` = :venta_id");
        $stmt->execute(['venta_id' => $ventaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $items = $this->obtenerItems((int) $fila['id']);
        return $this->hidratar($fila, $items);
    }

    /**
     * @return EntregaItem[]
     */
    private function obtenerItems(int $entregaId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `entrega_items` WHERE `entrega_id` = :entrega_id ORDER BY `id` ASC");
        $stmt->execute(['entrega_id' => $entregaId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($f) {
            return new EntregaItem(
                id: (int) $f['id'],
                entregaId: (int) $f['entrega_id'],
                ventaLineaId: (int) $f['venta_linea_id'],
                ventaLineaComponenteId: $f['venta_linea_componente_id'] !== null ? (int) $f['venta_linea_componente_id'] : null,
                itemComercialId: (int) $f['item_comercial_id'],
                conceptoCodigo: (string) $f['concepto_codigo'],
                conceptoNombre: (string) $f['concepto_nombre'],
                unidadMedida: (string) $f['unidad_medida'],
                cantidad: (float) $f['cantidad'],
                creadoEn: $f['creado_en']
            );
        }, $filas);
    }

    private function hidratar(array $f, array $items = []): EntregaProducto
    {
        return new EntregaProducto(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            edicionId: (int) $f['edicion_id'],
            ventaId: (int) $f['venta_id'],
            clienteId: (int) $f['cliente_id'],
            correlativo: (string) $f['correlativo'],
            estado: EstadoEntregaProducto::from($f['estado']),
            contactoNombre: (string) $f['contacto_nombre'],
            contactoTelefono: $f['contacto_telefono'],
            direccionEntrega: $f['direccion_entrega'],
            fechaEntrega: $f['fecha_entrega'],
            entregadoPor: $f['entregado_por'] !== null ? (int) $f['entregado_por'] : null,
            notasDespacho: $f['notas_despacho'],
            versionBloqueo: (int) $f['version_bloqueo'],
            creadoPor: (int) $f['creado_por'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en'],
            items: $items
        );
    }
}
