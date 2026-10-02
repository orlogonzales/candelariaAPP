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
            ':telefono_whatsapp'        => $usuario->telefonoWhatsapp ? trim($usuario->telefonoWhatsapp) : null,
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

    /**
     * Actualiza atributos mutables del usuario (nombre completo, correo, whatsapp, avatar, estado).
     */
    public function actualizar(int $id, array $datos): bool
    {
        $campos = [];
        $params = [':id' => $id];

        if (array_key_exists('nombre_completo', $datos)) {
            $campos[] = '`nombre_completo` = :nombre_completo';
            $params[':nombre_completo'] = normalizar_mayusculas((string) $datos['nombre_completo']);
        }
        if (array_key_exists('correo_electronico', $datos)) {
            $campos[] = '`correo_electronico` = :correo_electronico';
            $params[':correo_electronico'] = normalizar_minusculas((string) $datos['correo_electronico']);
        }
        if (array_key_exists('telefono_whatsapp', $datos)) {
            $campos[] = '`telefono_whatsapp` = :telefono_whatsapp';
            $params[':telefono_whatsapp'] = !empty($datos['telefono_whatsapp']) ? trim((string) $datos['telefono_whatsapp']) : null;
        }
        if (array_key_exists('avatar_url', $datos)) {
            $campos[] = '`avatar_url` = :avatar_url';
            $params[':avatar_url'] = !empty($datos['avatar_url']) ? trim((string) $datos['avatar_url']) : null;
        }
        if (array_key_exists('estado', $datos)) {
            $campos[] = '`estado` = :estado';
            $params[':estado'] = (string) $datos['estado'];
        }

        if (empty($campos)) {
            return false;
        }

        $sql = "UPDATE `usuarios` SET " . implode(', ', $campos) . " WHERE `id` = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Retorna todos los usuarios con datos de persona, tipo de documento y roles asignados.
     * Estructurado para el consumo de DataTables en el frontend.
     */
    public function obtenerTodosConDetalles(?int $organizacionId = null): array
    {
        $sql = "SELECT
                    u.id,
                    u.organizacion_id,
                    u.persona_id,
                    u.nombre_usuario,
                    u.nombre_completo,
                    u.correo_electronico,
                    u.telefono_whatsapp,
                    u.avatar_url,
                    u.estado,
                    u.ultimo_acceso_en,
                    u.creado_en,
                    p.tipo_persona,
                    p.numero_documento,
                    p.nombres AS persona_nombres,
                    p.apellidos AS persona_apellidos,
                    p.razon_social AS persona_razon_social,
                    p.nombre_comercial AS persona_nombre_comercial,
                    td.codigo AS tipo_documento_codigo,
                    td.nombre AS tipo_documento_nombre,
                    GROUP_CONCAT(DISTINCT r.id ORDER BY r.id ASC SEPARATOR ',') AS roles_ids,
                    GROUP_CONCAT(DISTINCT r.codigo ORDER BY r.id ASC SEPARATOR ',') AS roles_codigos,
                    GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.id ASC SEPARATOR '||') AS roles_nombres
                FROM `usuarios` u
                INNER JOIN `personas` p ON p.id = u.persona_id
                INNER JOIN `tipos_documento` td ON td.id = p.tipo_documento_id
                LEFT JOIN `usuario_roles` ur ON ur.usuario_id = u.id
                LEFT JOIN `roles` r ON r.id = ur.rol_id";

        if ($organizacionId !== null) {
            $sql .= " WHERE u.organizacion_id = :org_id";
        }

        $sql .= " GROUP BY u.id, p.id, td.id ORDER BY u.id DESC";

        $stmt = $this->pdo->prepare($sql);
        if ($organizacionId !== null) {
            $stmt->execute([':org_id' => $organizacionId]);
        } else {
            $stmt->execute();
        }

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $f) {
            $rolesIds = !empty($f['roles_ids']) ? array_map('intval', explode(',', $f['roles_ids'])) : [];
            $rolesCodigos = !empty($f['roles_codigos']) ? explode(',', $f['roles_codigos']) : [];
            $rolesNombres = !empty($f['roles_nombres']) ? explode('||', $f['roles_nombres']) : [];

            $roles = [];
            for ($i = 0; $i < count($rolesIds); $i++) {
                $roles[] = [
                    'id'     => $rolesIds[$i],
                    'codigo' => $rolesCodigos[$i] ?? '',
                    'nombre' => $rolesNombres[$i] ?? '',
                ];
            }

            return [
                'id'                 => (int) $f['id'],
                'organizacion_id'    => (int) $f['organizacion_id'],
                'persona_id'         => (int) $f['persona_id'],
                'nombre_usuario'     => $f['nombre_usuario'],
                'nombre_completo'    => $f['nombre_completo'],
                'correo_electronico' => $f['correo_electronico'],
                'telefono_whatsapp'  => $f['telefono_whatsapp'],
                'avatar_url'         => $f['avatar_url'],
                'estado'             => $f['estado'],
                'ultimo_acceso_en'   => $f['ultimo_acceso_en'],
                'creado_en'          => $f['creado_en'],
                'persona' => [
                    'tipo_persona'          => $f['tipo_persona'],
                    'tipo_documento_codigo' => $f['tipo_documento_codigo'],
                    'tipo_documento_nombre' => $f['tipo_documento_nombre'],
                    'numero_documento'      => $f['numero_documento'],
                    'nombre_representativo' => $f['tipo_persona'] === 'NATURAL'
                        ? trim("{$f['persona_nombres']} {$f['persona_apellidos']}")
                        : ($f['persona_nombre_comercial'] ? "{$f['persona_razon_social']} ({$f['persona_nombre_comercial']})" : $f['persona_razon_social']),
                ],
                'roles'              => $roles,
            ];
        }, $filas);
    }

    /**
     * Retorna el detalle completo de un usuario por su ID, incluyendo persona y roles.
     */
    public function obtenerDetallePorId(int $id): ?array
    {
        $sql = "SELECT
                    u.id,
                    u.organizacion_id,
                    u.persona_id,
                    u.nombre_usuario,
                    u.nombre_completo,
                    u.correo_electronico,
                    u.telefono_whatsapp,
                    u.avatar_url,
                    u.estado,
                    u.ultimo_acceso_en,
                    u.creado_en,
                    p.tipo_persona,
                    p.numero_documento,
                    p.nombres AS persona_nombres,
                    p.apellidos AS persona_apellidos,
                    p.razon_social AS persona_razon_social,
                    p.nombre_comercial AS persona_nombre_comercial,
                    td.codigo AS tipo_documento_codigo,
                    td.nombre AS tipo_documento_nombre,
                    GROUP_CONCAT(DISTINCT r.id ORDER BY r.id ASC SEPARATOR ',') AS roles_ids,
                    GROUP_CONCAT(DISTINCT r.codigo ORDER BY r.id ASC SEPARATOR ',') AS roles_codigos,
                    GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.id ASC SEPARATOR '||') AS roles_nombres
                FROM `usuarios` u
                INNER JOIN `personas` p ON p.id = u.persona_id
                INNER JOIN `tipos_documento` td ON td.id = p.tipo_documento_id
                LEFT JOIN `usuario_roles` ur ON ur.usuario_id = u.id
                LEFT JOIN `roles` r ON r.id = ur.rol_id
                WHERE u.id = :id
                GROUP BY u.id, p.id, td.id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$f) {
            return null;
        }

        $rolesIds = !empty($f['roles_ids']) ? array_map('intval', explode(',', $f['roles_ids'])) : [];
        $rolesCodigos = !empty($f['roles_codigos']) ? explode(',', $f['roles_codigos']) : [];
        $rolesNombres = !empty($f['roles_nombres']) ? explode('||', $f['roles_nombres']) : [];

        $roles = [];
        for ($i = 0; $i < count($rolesIds); $i++) {
            $roles[] = [
                'id'     => $rolesIds[$i],
                'codigo' => $rolesCodigos[$i] ?? '',
                'nombre' => $rolesNombres[$i] ?? '',
            ];
        }

        return [
            'id'                 => (int) $f['id'],
            'organizacion_id'    => (int) $f['organizacion_id'],
            'persona_id'         => (int) $f['persona_id'],
            'nombre_usuario'     => $f['nombre_usuario'],
            'nombre_completo'    => $f['nombre_completo'],
            'correo_electronico' => $f['correo_electronico'],
            'telefono_whatsapp'  => $f['telefono_whatsapp'],
            'avatar_url'         => $f['avatar_url'],
            'estado'             => $f['estado'],
            'ultimo_acceso_en'   => $f['ultimo_acceso_en'],
            'creado_en'          => $f['creado_en'],
            'persona' => [
                'tipo_persona'          => $f['tipo_persona'],
                'tipo_documento_codigo' => $f['tipo_documento_codigo'],
                'tipo_documento_nombre' => $f['tipo_documento_nombre'],
                'numero_documento'      => $f['numero_documento'],
                'nombre_representativo' => $f['tipo_persona'] === 'NATURAL'
                    ? trim("{$f['persona_nombres']} {$f['persona_apellidos']}")
                    : ($f['persona_nombre_comercial'] ? "{$f['persona_razon_social']} ({$f['persona_nombre_comercial']})" : $f['persona_razon_social']),
            ],
            'roles'              => $roles,
        ];
    }
}
