<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Catalogo\EstadoCatalogo;
use Aplicacion\Entidades\CategoriaItem;
use PDO;

class CategoriaItemRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function guardar(CategoriaItem $c): CategoriaItem
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `categorias_items` (
                `organizacion_id`, `codigo`, `nombre`, `descripcion`, `orden`, `estado`
            ) VALUES (
                :organizacion_id, :codigo, :nombre, :descripcion, :orden, :estado
            )
        ");

        $stmt->execute([
            'organizacion_id' => $c->organizacionId,
            'codigo' => $c->codigo,
            'nombre' => $c->nombre,
            'descripcion' => $c->descripcion,
            'orden' => $c->orden,
            'estado' => $c->estado->value,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($id, $c->organizacionId);
    }

    public function actualizar(CategoriaItem $c): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `categorias_items`
            SET `nombre` = :nombre,
                `descripcion` = :descripcion,
                `orden` = :orden,
                `estado` = :estado
            WHERE `id` = :id AND `organizacion_id` = :organizacion_id
        ");

        return $stmt->execute([
            'id' => $c->id,
            'organizacion_id' => $c->organizacionId,
            'nombre' => $c->nombre,
            'descripcion' => $c->descripcion,
            'orden' => $c->orden,
            'estado' => $c->estado->value,
        ]);
    }

    public function cambiarEstado(int $id, int $organizacionId, EstadoCatalogo $estado): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `categorias_items`
            SET `estado` = :estado
            WHERE `id` = :id AND `organizacion_id` = :organizacion_id
        ");

        return $stmt->execute([
            'id' => $id,
            'organizacion_id' => $organizacionId,
            'estado' => $estado->value,
        ]);
    }

    public function buscarPorId(int $id, int $organizacionId): ?CategoriaItem
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `categorias_items`
            WHERE `id` = :id AND `organizacion_id` = :organizacion_id
        ");
        $stmt->execute(['id' => $id, 'organizacion_id' => $organizacionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    public function buscarPorCodigo(string $codigo, int $organizacionId): ?CategoriaItem
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `categorias_items`
            WHERE `codigo` = :codigo AND `organizacion_id` = :organizacion_id
        ");
        $stmt->execute(['codigo' => $codigo, 'organizacion_id' => $organizacionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    /**
     * @return CategoriaItem[]
     */
    public function listar(int $organizacionId, ?EstadoCatalogo $estado = null): array
    {
        $sql = "SELECT * FROM `categorias_items` WHERE `organizacion_id` = :organizacion_id";
        $params = ['organizacion_id' => $organizacionId];

        if ($estado !== null) {
            $sql .= " AND `estado` = :estado";
            $params['estado'] = $estado->value;
        }

        $sql .= " ORDER BY `orden` ASC, `nombre` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $res = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res[] = $this->hidratar($row);
        }
        return $res;
    }

    private function hidratar(array $row): CategoriaItem
    {
        return new CategoriaItem(
            id: (int) $row['id'],
            organizacionId: (int) $row['organizacion_id'],
            codigo: (string) $row['codigo'],
            nombre: (string) $row['nombre'],
            descripcion: $row['descripcion'] !== null ? (string) $row['descripcion'] : null,
            orden: (int) $row['orden'],
            estado: EstadoCatalogo::from((string) $row['estado']),
            creadoEn: (string) $row['creado_en'],
            actualizadoEn: (string) $row['actualizado_en']
        );
    }
}
