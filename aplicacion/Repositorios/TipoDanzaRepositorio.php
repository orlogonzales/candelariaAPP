<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Folclore\CategoriaDanza;
use Aplicacion\Folclore\TipoDanza;
use PDO;

class TipoDanzaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(TipoDanza $tipo): TipoDanza
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `tipos_danza` (
                `organizacion_id`, `codigo`, `nombre`, `categoria_origen`, `descripcion`, `activo`
            ) VALUES (
                :org_id, :codigo, :nombre, :categoria, :descripcion, :activo
            )
        ");

        $stmt->execute([
            'org_id'      => $tipo->organizacionId,
            'codigo'      => strtoupper(trim($tipo->codigo)),
            'nombre'      => strtoupper(trim($tipo->nombre)),
            'categoria'   => $tipo->categoriaOrigen->value,
            'descripcion' => $tipo->descripcion,
            'activo'      => $tipo->activo ? 1 : 0,
        ]);

        $tipo->id = (int)$this->pdo->lastInsertId();
        return $tipo;
    }

    public function buscarPorId(int $id, int $organizacionId): ?TipoDanza
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `tipos_danza`
            WHERE `id` = :id AND `organizacion_id` = :org_id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? TipoDanza::desdeArreglo($fila) : null;
    }

    public function buscarPorCodigo(string $codigo, int $organizacionId): ?TipoDanza
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `tipos_danza`
            WHERE `codigo` = :codigo AND `organizacion_id` = :org_id
            LIMIT 1
        ");
        $stmt->execute(['codigo' => strtoupper(trim($codigo)), 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? TipoDanza::desdeArreglo($fila) : null;
    }

    /**
     * @return array<TipoDanza>
     */
    public function listarPorOrganizacion(int $organizacionId, bool $soloActivos = true): array
    {
        $sql = "SELECT * FROM `tipos_danza` WHERE `organizacion_id` = :org_id";
        if ($soloActivos) {
            $sql .= " AND `activo` = 1";
        }
        $sql .= " ORDER BY `nombre` ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['org_id' => $organizacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => TipoDanza::desdeArreglo($f), $filas);
    }
}
