<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Rol;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio PDO nativo para gestión de roles RBAC y asignación a usuarios.
 */
class RolRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    public function buscarPorId(int $id): ?Rol
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `roles` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Rol::desdeArreglo($fila) : null;
    }

    public function buscarPorCodigo(string $codigo, ?int $organizacionId = null): ?Rol
    {
        if ($organizacionId !== null) {
            $stmt = $this->pdo->prepare("SELECT * FROM `roles` WHERE `codigo` = :codigo AND (`organizacion_id` = :org_id OR `organizacion_id` IS NULL) ORDER BY `organizacion_id` DESC LIMIT 1");
            $stmt->execute([':codigo' => trim($codigo), ':org_id' => $organizacionId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM `roles` WHERE `codigo` = :codigo AND `organizacion_id` IS NULL LIMIT 1");
            $stmt->execute([':codigo' => trim($codigo)]);
        }

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? Rol::desdeArreglo($fila) : null;
    }

    /**
     * Retorna todos los roles disponibles (roles de sistema y roles del tenant si aplica).
     * @return Rol[]
     */
    public function obtenerTodos(?int $organizacionId = null): array
    {
        if ($organizacionId !== null) {
            $sql = "SELECT * FROM `roles` WHERE `organizacion_id` = :org_id OR `organizacion_id` IS NULL ORDER BY `id` ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':org_id' => $organizacionId]);
        } else {
            $sql = "SELECT * FROM `roles` WHERE `organizacion_id` IS NULL ORDER BY `id` ASC";
            $stmt = $this->pdo->query($sql);
        }

        return array_map(fn(array $f) => Rol::desdeArreglo($f), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Retorna la lista de roles asignados a un usuario.
     * @return Rol[]
     */
    public function obtenerRolesDeUsuario(int $usuarioId): array
    {
        $sql = "SELECT r.* FROM `roles` r
                INNER JOIN `usuario_roles` ur ON ur.rol_id = r.id
                WHERE ur.usuario_id = :usuario_id
                ORDER BY r.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':usuario_id' => $usuarioId]);

        return array_map(fn(array $f) => Rol::desdeArreglo($f), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Asigna un rol a un usuario (idempotente mediante INSERT IGNORE).
     */
    public function asignarRolAUsuario(int $usuarioId, int $rolId): bool
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (:usuario_id, :rol_id)");
        return $stmt->execute([':usuario_id' => $usuarioId, ':rol_id' => $rolId]);
    }

    /**
     * Remueve un rol asignado a un usuario.
     */
    public function removerRolDeUsuario(int $usuarioId, int $rolId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `usuario_roles` WHERE `usuario_id` = :usuario_id AND `rol_id` = :rol_id");
        $stmt->execute([':usuario_id' => $usuarioId, ':rol_id' => $rolId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Sincroniza la lista de roles asignados a un usuario.
     */
    public function sincronizarRolesUsuario(int $usuarioId, array $rolesIds): void
    {
        $this->pdo->prepare("DELETE FROM `usuario_roles` WHERE `usuario_id` = :usuario_id")
            ->execute([':usuario_id' => $usuarioId]);

        if (!empty($rolesIds)) {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (:usuario_id, :rol_id)");
            foreach ($rolesIds as $rId) {
                $stmt->execute([':usuario_id' => $usuarioId, ':rol_id' => (int) $rId]);
            }
        }
    }

    /**
     * Comprueba si un usuario tiene asignado un rol específico por su código.
     */
    public function usuarioTieneRol(int $usuarioId, string $codigoRol): bool
    {
        $sql = "SELECT 1 FROM `usuario_roles` ur
                INNER JOIN `roles` r ON r.id = ur.rol_id
                WHERE ur.usuario_id = :usuario_id AND r.codigo = :codigo
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':usuario_id' => $usuarioId, ':codigo' => trim($codigoRol)]);

        return (bool) $stmt->fetchColumn();
    }
}
