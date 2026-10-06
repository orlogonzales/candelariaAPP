<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Catalogo\EstadoCatalogo;
use Aplicacion\Catalogo\TipoItemComercial;
use Aplicacion\Catalogo\UnidadMedidaItem;
use Aplicacion\Entidades\ItemComercial;
use PDO;

class ItemComercialRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function guardar(ItemComercial $item): ItemComercial
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `items_comerciales` (
                `organizacion_id`, `categoria_id`, `codigo`, `nombre`, `tipo`, `unidad_medida`, `descripcion`, `estado`
            ) VALUES (
                :organizacion_id, :categoria_id, :codigo, :nombre, :tipo, :unidad_medida, :descripcion, :estado
            )
        ");

        $stmt->execute([
            'organizacion_id' => $item->organizacionId,
            'categoria_id' => $item->categoriaId,
            'codigo' => $item->codigo,
            'nombre' => $item->nombre,
            'tipo' => $item->tipo->value,
            'unidad_medida' => $item->unidadMedida->value,
            'descripcion' => $item->descripcion,
            'estado' => $item->estado->value,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($id, $item->organizacionId);
    }

    public function actualizar(ItemComercial $item): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `items_comerciales`
            SET `categoria_id` = :categoria_id,
                `nombre` = :nombre,
                `tipo` = :tipo,
                `unidad_medida` = :unidad_medida,
                `descripcion` = :descripcion,
                `estado` = :estado
            WHERE `id` = :id AND `organizacion_id` = :organizacion_id
        ");

        return $stmt->execute([
            'id' => $item->id,
            'organizacion_id' => $item->organizacionId,
            'categoria_id' => $item->categoriaId,
            'nombre' => $item->nombre,
            'tipo' => $item->tipo->value,
            'unidad_medida' => $item->unidadMedida->value,
            'descripcion' => $item->descripcion,
            'estado' => $item->estado->value,
        ]);
    }

    public function cambiarEstado(int $id, int $organizacionId, EstadoCatalogo $estado): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `items_comerciales`
            SET `estado` = :estado
            WHERE `id` = :id AND `organizacion_id` = :organizacion_id
        ");

        return $stmt->execute([
            'id' => $id,
            'organizacion_id' => $organizacionId,
            'estado' => $estado->value,
        ]);
    }

    public function buscarPorId(int $id, int $organizacionId): ?ItemComercial
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `items_comerciales`
            WHERE `id` = :id AND `organizacion_id` = :organizacion_id
        ");
        $stmt->execute(['id' => $id, 'organizacion_id' => $organizacionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    public function buscarPorCodigo(string $codigo, int $organizacionId): ?ItemComercial
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `items_comerciales`
            WHERE `codigo` = :codigo AND `organizacion_id` = :organizacion_id
        ");
        $stmt->execute(['codigo' => $codigo, 'organizacion_id' => $organizacionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    /**
     * @return ItemComercial[]
     */
    public function listar(
        int $organizacionId,
        ?int $categoriaId = null,
        ?TipoItemComercial $tipo = null,
        ?EstadoCatalogo $estado = null,
        ?string $busqueda = null
    ): array {
        $sql = "SELECT * FROM `items_comerciales` WHERE `organizacion_id` = :organizacion_id";
        $params = ['organizacion_id' => $organizacionId];

        if ($categoriaId !== null) {
            $sql .= " AND `categoria_id` = :categoria_id";
            $params['categoria_id'] = $categoriaId;
        }

        if ($tipo !== null) {
            $sql .= " AND `tipo` = :tipo";
            $params['tipo'] = $tipo->value;
        }

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
            $res[] = $this->hidratar($row);
        }
        return $res;
    }

    private function hidratar(array $row): ItemComercial
    {
        return new ItemComercial(
            id: (int) $row['id'],
            organizacionId: (int) $row['organizacion_id'],
            categoriaId: (int) $row['categoria_id'],
            codigo: (string) $row['codigo'],
            nombre: (string) $row['nombre'],
            tipo: TipoItemComercial::from((string) $row['tipo']),
            unidadMedida: UnidadMedidaItem::from((string) $row['unidad_medida']),
            descripcion: $row['descripcion'] !== null ? (string) $row['descripcion'] : null,
            estado: EstadoCatalogo::from((string) $row['estado']),
            creadoEn: (string) $row['creado_en'],
            actualizadoEn: (string) $row['actualizado_en']
        );
    }
}
