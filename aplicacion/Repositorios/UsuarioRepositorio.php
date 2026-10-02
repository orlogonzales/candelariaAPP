<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Usuario;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio PDO nativo para gestión de cuentas de usuario.
 * Aplica normalización, políticas de bloqueo y verificación criptográfica.
 */
class UsuarioRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Registra un nuevo usuario en la base de datos vinculado a una persona.
     */
    public function crear(Usuario $usuario): int
    {
        $sql = "INSERT INTO `usuarios` (
                    `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`,
                    `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`,
                    `es_superadmin_plataforma`, `avatar_url`, `estado`,
                    `intentos_fallidos`, `bloqueado_hasta`
                ) VALUES (
                    :organizacion_id, :persona_id, :nombre_usuario, :nombre_completo,
                    :correo_electronico, :telefono_whatsapp, :contrasena_hash,
                    :es_superadmin_plataforma, :avatar_url, :estado,
                    :intentos_fallidos, :bloqueado_hasta
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'          => $usuario->organizacionId,
            ':persona_id'               => $usuario->personaId,
            ':nombre_usuario'           => normalizar_minusculas($usuario->nombreUsuario),
            ':nombre_completo'          => normalizar_mayusculas($usuario->nombreCompleto),
            ':correo_electronico'       => normalizar_minusculas($usuario->correoElectronico),
            ':telefono_whatsapp'        => $usuario->avatarUrl ? trim($usuario->avatarUrl) : null,
            ':contrasena_hash'          => $usuario->contrasenaHash,
            ':es_superadmin_plataforma' => $usuario->esSuperadminPlataforma ? 1 : 0,
            ':avatar_url'               => $usuario->avatarUrl,
            ':estado'                   => $usuario->estado,
            ':intentos_fallidos'        => $usuario->intentosFallidos,
            ':bloqueado_hasta'          => $usuario->bloqueadoHasta,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Busca un usuario por su identificador primario.
     */
    public function buscarPorId(int $id): ?Usuario
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `usuarios` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ? Usuario::desdeArreglo($fila) : null;
    }

    /**
     * Busca un usuario por su nombre de usuario de login.
     */
    public function buscarPorNombreUsuario(string $nombreUsuario): ?Usuario
    {
        $sql = "SELECT * FROM `usuarios` WHERE `nombre_usuario` = :nombre_usuario LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':nombre_usuario' => normalizar_minusculas($nombreUsuario)]);
        $fila = $stmt->fetch();

        return $fila ? Usuario::desdeArreglo($fila) : null;
    }

    /**
     * Busca un usuario por su correo electrónico.
     */
    public function buscarPorCorreo(string $correo): ?Usuario
    {
        $sql = "SELECT * FROM `usuarios` WHERE `correo_electronico` = :correo LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':correo' => normalizar_minusculas($correo)]);
        $fila = $stmt->fetch();

        return $fila ? Usuario::desdeArreglo($fila) : null;
    }

    /**
     * Busca la cuenta de usuario vinculada a una persona específica.
     */
    public function buscarPorPersonaId(int $personaId): ?Usuario
    {
        $sql = "SELECT * FROM `usuarios` WHERE `persona_id` = :persona_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':persona_id' => $personaId]);
        $fila = $stmt->fetch();

        return $fila ? Usuario::desdeArreglo($fila) : null;
    }

    /**
     * Retorna lista paginada de usuarios de una organización.
     * @return Usuario[]
     */
    public function buscarPorOrganizacion(int $organizacionId, int $limite = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM `usuarios`
                WHERE `organizacion_id` = :organizacion_id
                ORDER BY `id` DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':organizacion_id', $organizacionId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn(array $f) => Usuario::desdeArreglo($f), $stmt->fetchAll());
    }

    /**
     * Actualiza el estado de una cuenta (ACTIVO, INACTIVO, BLOQUEADO).
     * En lugar de eliminación física, se prefiere la desactivación por gobernanza.
     */
    public function actualizarEstado(int $id, string $nuevoEstado): bool
    {
        $stmt = $this->pdo->prepare("UPDATE `usuarios` SET `estado` = :estado WHERE `id` = :id");
        return $stmt->execute([':estado' => $nuevoEstado, ':id' => $id]);
    }

    /**
     * Registra un intento de login fallido y bloquea temporalmente si excede el umbral.
     */
    public function registrarIntentoFallido(int $id, int $maxIntentos = 5, int $minutosBloqueo = 15): void
    {
        $stmt = $this->pdo->prepare("SELECT `intentos_fallidos` FROM `usuarios` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
        $intentos = (int) $stmt->fetchColumn() + 1;

        if ($intentos >= $maxIntentos) {
            $bloqueadoHasta = date('Y-m-d H:i:s', time() + ($minutosBloqueo * 60));
            $sql = "UPDATE `usuarios`
                    SET `intentos_fallidos` = :intentos,
                        `bloqueado_hasta` = :bloqueado_hasta,
                        `estado` = 'BLOQUEADO'
                    WHERE `id` = :id";
            $this->pdo->prepare($sql)->execute([
                ':intentos'        => $intentos,
                ':bloqueado_hasta' => $bloqueadoHasta,
                ':id'              => $id,
            ]);
        } else {
            $sql = "UPDATE `usuarios` SET `intentos_fallidos` = :intentos WHERE `id` = :id";
            $this->pdo->prepare($sql)->execute([':intentos' => $intentos, ':id' => $id]);
        }
    }

    /**
     * Restablece el contador de intentos fallidos y desbloquea tras autenticación exitosa.
     */
    public function restablecerIntentosFallidos(int $id): void
    {
        $sql = "UPDATE `usuarios`
                SET `intentos_fallidos` = 0,
                    `bloqueado_hasta` = NULL,
                    `estado` = IF(`estado` = 'BLOQUEADO', 'ACTIVO', `estado`)
                WHERE `id` = :id";
        $this->pdo->prepare($sql)->execute([':id' => $id]);
    }

    /**
     * Actualiza la fecha y hora de último acceso.
     */
    public function actualizarUltimoAcceso(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE `usuarios` SET `ultimo_acceso_en` = NOW() WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
    }

    /**
     * Actualiza el hash de contraseña (restablecimiento o rehash automático).
     */
    public function actualizarContrasenaHash(int $id, string $nuevoHash): bool
    {
        $stmt = $this->pdo->prepare("UPDATE `usuarios` SET `contrasena_hash` = :hash WHERE `id` = :id");
        return $stmt->execute([':hash' => $nuevoHash, ':id' => $id]);
    }
}
