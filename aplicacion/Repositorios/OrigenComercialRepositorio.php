<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\OrigenComercial;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio oficial para el catálogo relacional de Orígenes Comerciales por Tenant.
 */
class OrigenComercialRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    public function crear(OrigenComercial $origen): int
    {
        $sql = "INSERT INTO `origenes_comerciales` (
                    `organizacion_id`, `codigo`, `nombre`, `descripcion`, `activo`, `orden`
                ) VALUES (
                    :organizacion_id, :codigo, :nombre, :descripcion, :activo, :orden
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id' => $origen->organizacionId,
            ':codigo'          => strtoupper(trim($origen->codigo)),
            ':nombre'          => trim($origen->nombre),
            ':descripcion'     => $origen->descripcion !== null ? trim($origen->descripcion) : null,
            ':activo'          => $origen->activo ? 1 : 0,
            ':orden'           => $origen->orden,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarPorId(int $id): ?OrigenComercial
    {
        $sql = "SELECT * FROM `origenes_comerciales` WHERE `id` = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? OrigenComercial::desdeArreglo($fila) : null;
    }

    public function buscarPorCodigo(int $organizacionId, string $codigo): ?OrigenComercial
    {
        $sql = "SELECT * FROM `origenes_comerciales`
                WHERE `organizacion_id` = :organizacion_id AND `codigo` = :codigo
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id' => $organizacionId,
            ':codigo'          => strtoupper(trim($codigo)),
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? OrigenComercial::desdeArreglo($fila) : null;
    }

    /**
     * @return OrigenComercial[]
     */
    public function listarPorOrganizacion(int $organizacionId, bool $soloActivos = true): array
    {
        $sql = "SELECT * FROM `origenes_comerciales`
                WHERE `organizacion_id` = :organizacion_id"
                . ($soloActivos ? " AND `activo` = 1" : "")
                . " ORDER BY `orden` ASC, `id` ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':organizacion_id' => $organizacionId]);

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = OrigenComercial::desdeArreglo($fila);
        }

        return $resultados;
    }

    public function desactivar(int $id, int $organizacionId): bool
    {
        $sql = "UPDATE `origenes_comerciales`
                SET `activo` = 0
                WHERE `id` = :id AND `organizacion_id` = :organizacion_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'              => $id,
            ':organizacion_id' => $organizacionId,
        ]);
    }

    public function activar(int $id, int $organizacionId): bool
    {
        $sql = "UPDATE `origenes_comerciales`
                SET `activo` = 1
                WHERE `id` = :id AND `organizacion_id` = :organizacion_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'              => $id,
            ':organizacion_id' => $organizacionId,
        ]);
    }

    public function actualizar(OrigenComercial $origen): bool
    {
        $sql = "UPDATE `origenes_comerciales`
                SET `nombre`      = :nombre,
                    `descripcion` = :descripcion,
                    `orden`       = :orden,
                    `activo`      = :activo
                WHERE `id` = :id AND `organizacion_id` = :organizacion_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'              => $origen->id,
            ':organizacion_id' => $origen->organizacionId,
            ':nombre'          => trim($origen->nombre),
            ':descripcion'     => $origen->descripcion !== null ? trim($origen->descripcion) : null,
            ':orden'           => $origen->orden,
            ':activo'          => $origen->activo ? 1 : 0,
        ]);
    }

    public function tieneOportunidadesVinculadas(int $id, int $organizacionId): bool
    {
        $sql = "SELECT COUNT(*) FROM `crm_oportunidades`
                WHERE `origen_comercial_id` = :id AND `organizacion_id` = :organizacion_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'              => $id,
            ':organizacion_id' => $organizacionId,
        ]);

        return ((int) $stmt->fetchColumn()) > 0;
    }
}
