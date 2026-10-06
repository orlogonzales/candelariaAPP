<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Catalogo\EstadoCatalogo;
use Aplicacion\Entidades\ItemComercial;
use Aplicacion\Entidades\Paquete;
use Aplicacion\Entidades\PaqueteItem;
use PDO;

class PaqueteRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function guardar(Paquete $paquete): Paquete
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `paquetes` (
                `organizacion_id`, `codigo`, `nombre`, `descripcion`, `estado`
            ) VALUES (
                :organizacion_id, :codigo, :nombre, :descripcion, :estado
            )
        ");

        $stmt->execute([
            'organizacion_id' => $paquete->organizacionId,
            'codigo' => $paquete->codigo,
            'nombre' => $paquete->nombre,
            'descripcion' => $paquete->descripcion,
            'estado' => $paquete->estado->value,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($id, $paquete->organizacionId, false);
    }

    public function actualizar(Paquete $paquete): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `paquetes`
            SET `nombre` = :nombre,
                `descripcion` = :descripcion,
                `estado` = :estado
            WHERE `id` = :id AND `organizacion_id` = :organizacion_id
        ");

        return $stmt->execute([
            'id' => $paquete->id,
            'organizacion_id' => $paquete->organizacionId,
            'nombre' => $paquete->nombre,
            'descripcion' => $paquete->descripcion,
            'estado' => $paquete->estado->value,
        ]);
    }

    public function cambiarEstado(int $id, int $organizacionId, EstadoCatalogo $estado): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `paquetes`
            SET `estado` = :estado
            WHERE `id` = :id AND `organizacion_id` = :organizacion_id
        ");

        return $stmt->execute([
            'id' => $id,
            'organizacion_id' => $organizacionId,
            'estado' => $estado->value,
        ]);
    }

    public function buscarPorId(int $id, int $organizacionId, bool $conItems = true): ?Paquete
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `paquetes`
            WHERE `id` = :id AND `organizacion_id` = :organizacion_id
        ");
        $stmt->execute(['id' => $id, 'organizacion_id' => $organizacionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $items = $conItems ? $this->obtenerItemsDePaquete($id) : [];
        return $this->hidratar($row, $items);
    }

    public function buscarPorCodigo(string $codigo, int $organizacionId, bool $conItems = true): ?Paquete
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `paquetes`
            WHERE `codigo` = :codigo AND `organizacion_id` = :organizacion_id
        ");
        $stmt->execute(['codigo' => $codigo, 'organizacion_id' => $organizacionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $items = $conItems ? $this->obtenerItemsDePaquete((int) $row['id']) : [];
        return $this->hidratar($row, $items);
    }

    /**
     * @return Paquete[]
     */
    public function listar(int $organizacionId, ?EstadoCatalogo $estado = null, ?string $busqueda = null): array
    {
        $sql = "SELECT * FROM `paquetes` WHERE `organizacion_id` = :organizacion_id";
        $params = ['organizacion_id' => $organizacionId];

        if ($estado !== null) {
            $sql .= " AND `estado` = :estado";
            $params['estado'] = $estado->value;
        }

        if ($busqueda !== null && trim($busqueda) !== '') {
            $sql .= " AND (`nombre` LIKE :busqueda_nom OR `codigo` LIKE :busqueda_cod)";
            $params['busqueda_nom'] = '%' . trim($busqueda) . '%';
            $params['busqueda_cod'] = '%' . trim($busqueda) . '%';
        }

        $sql .= " ORDER BY `nombre` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $res = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items = $this->obtenerItemsDePaquete((int) $row['id']);
            $res[] = $this->hidratar($row, $items);
        }
        return $res;
    }

    /**
     * Sincroniza atómicamente la composición de un paquete comercial.
     * @param array<int, array{item_comercial_id: int, cantidad: float, orden: int}> $itemsDef
     */
    public function sincronizarComposicion(int $paqueteId, array $itemsDef): void
    {
        $stmtDelete = $this->pdo->prepare("DELETE FROM `paquete_items` WHERE `paquete_id` = :paquete_id");
        $stmtDelete->execute(['paquete_id' => $paqueteId]);

        $stmtInsert = $this->pdo->prepare("
            INSERT INTO `paquete_items` (`paquete_id`, `item_comercial_id`, `cantidad`, `orden`)
            VALUES (:paquete_id, :item_comercial_id, :cantidad, :orden)
        ");

        foreach ($itemsDef as $def) {
            $stmtInsert->execute([
                'paquete_id' => $paqueteId,
                'item_comercial_id' => $def['item_comercial_id'],
                'cantidad' => $def['cantidad'],
                'orden' => $def['orden'] ?? 0,
            ]);
        }
    }

    /**
     * @return PaqueteItem[]
     */
    public function obtenerItemsDePaquete(int $paqueteId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT pi.*, ic.organizacion_id, ic.categoria_id, ic.codigo AS item_codigo,
                   ic.nombre AS item_nombre, ic.tipo AS item_tipo, ic.unidad_medida AS item_unidad_medida,
                   ic.descripcion AS item_descripcion, ic.estado AS item_estado,
                   ic.creado_en AS item_creado_en, ic.actualizado_en AS item_actualizado_en
            FROM `paquete_items` pi
            JOIN `items_comerciales` ic ON pi.item_comercial_id = ic.id
            WHERE pi.paquete_id = :paquete_id
            ORDER BY pi.orden ASC, pi.id ASC
        ");
        $stmt->execute(['paquete_id' => $paqueteId]);

        $res = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $itemCom = new ItemComercial(
                id: (int) $row['item_comercial_id'],
                organizacionId: (int) $row['organizacion_id'],
                categoriaId: (int) $row['categoria_id'],
                codigo: (string) $row['item_codigo'],
                nombre: (string) $row['item_nombre'],
                tipo: \Aplicacion\Catalogo\TipoItemComercial::from((string) $row['item_tipo']),
                unidadMedida: \Aplicacion\Catalogo\UnidadMedidaItem::from((string) $row['item_unidad_medida']),
                descripcion: $row['item_descripcion'] !== null ? (string) $row['item_descripcion'] : null,
                estado: EstadoCatalogo::from((string) $row['item_estado']),
                creadoEn: (string) $row['item_creado_en'],
                actualizadoEn: (string) $row['item_actualizado_en']
            );

            $res[] = new PaqueteItem(
                id: (int) $row['id'],
                paqueteId: (int) $row['paquete_id'],
                itemComercialId: (int) $row['item_comercial_id'],
                cantidad: (float) $row['cantidad'],
                orden: (int) $row['orden'],
                creadoEn: (string) $row['creado_en'],
                itemComercial: $itemCom
            );
        }
        return $res;
    }

    private function hidratar(array $row, array $items): Paquete
    {
        return new Paquete(
            id: (int) $row['id'],
            organizacionId: (int) $row['organizacion_id'],
            codigo: (string) $row['codigo'],
            nombre: (string) $row['nombre'],
            descripcion: $row['descripcion'] !== null ? (string) $row['descripcion'] : null,
            estado: EstadoCatalogo::from((string) $row['estado']),
            creadoEn: (string) $row['creado_en'],
            actualizadoEn: (string) $row['actualizado_en'],
            items: $items
        );
    }
}
