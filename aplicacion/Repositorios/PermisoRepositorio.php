<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Permiso;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio PDO nativo para gestión de permisos RBAC y privilegios de roles.
 */
class PermisoRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    public function buscarPorId(int $id): ?Permiso
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `permisos` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Permiso::desdeArreglo($fila) : null;
    }

    public function buscarPorCodigo(string $codigo): ?Permiso
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `permisos` WHERE `codigo` = :codigo LIMIT 1");
        $stmt->execute([':codigo' => trim($codigo)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Permiso::desdeArreglo($fila) : null;
    }

    /**
     * Retorna todos los permisos registrados en el catálogo oficial.
     * @return Permiso[]
     */
    public function obtenerTodos(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM `permisos` ORDER BY `modulo_id` ASC, `id` ASC");
        return array_map(fn(array $f) => Permiso::desdeArreglo($f), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Retorna los permisos asignados a un rol específico.
     * @return Permiso[]
     */
    public function obtenerPermisosDeRol(int $rolId): array
    {
        $sql = "SELECT p.* FROM `permisos` p
                INNER JOIN `rol_permisos` rp ON rp.permiso_id = p.id
                WHERE rp.rol_id = :rol_id
                ORDER BY p.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':rol_id' => $rolId]);

        return array_map(fn(array $f) => Permiso::desdeArreglo($f), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Retorna los códigos textuales de permisos asignados a un rol.
     * @return string[]
     */
    public function obtenerCodigosPermisosDeRol(int $rolId): array
    {
        $sql = "SELECT p.codigo FROM `permisos` p
                INNER JOIN `rol_permisos` rp ON rp.permiso_id = p.id
                WHERE rp.rol_id = :rol_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':rol_id' => $rolId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Retorna todos los códigos de permisos únicos de un usuario agregados a través de todos sus roles.
     * @return string[]
     */
    public function obtenerCodigosPermisosDeUsuario(int $usuarioId): array
    {
        $sql = "SELECT DISTINCT p.codigo
                FROM `permisos` p
                INNER JOIN `rol_permisos` rp ON rp.permiso_id = p.id
                INNER JOIN `usuario_roles` ur ON ur.rol_id = rp.rol_id
                WHERE ur.usuario_id = :usuario_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':usuario_id' => $usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Comprueba si un usuario tiene un permiso específico a través de cualquiera de sus roles.
     */
    public function usuarioTienePermiso(int $usuarioId, string $codigoPermiso): bool
    {
        $sql = "SELECT 1
                FROM `permisos` p
                INNER JOIN `rol_permisos` rp ON rp.permiso_id = p.id
                INNER JOIN `usuario_roles` ur ON ur.rol_id = rp.rol_id
                WHERE ur.usuario_id = :usuario_id AND p.codigo = :codigo
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':codigo'     => trim($codigoPermiso),
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Asigna un permiso a un rol (idempotente).
     */
    public function asignarPermisoARol(int $rolId, int $permisoId): bool
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES (:rol_id, :permiso_id)");
        return $stmt->execute([':rol_id' => $rolId, ':permiso_id' => $permisoId]);
    }

    /**
     * Remueve un permiso asignado a un rol.
     */
    public function removerPermisoDeRol(int $rolId, int $permisoId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `rol_permisos` WHERE `rol_id` = :rol_id AND `permiso_id` = :permiso_id");
        $stmt->execute([':rol_id' => $rolId, ':permiso_id' => $permisoId]);
        return $stmt->rowCount() > 0;
    }
}
