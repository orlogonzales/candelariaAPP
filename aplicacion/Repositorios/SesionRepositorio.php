<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Sesion;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Seguridad\ConfiguracionSeguridad;
use PDO;

/**
 * Repositorio PDO nativo para gestión del ciclo de vida de sesiones persistentes.
 * Almacena exclusivamente representaciones no reversibles (hash SHA-256) de los tokens.
 * Soporta sesiones múltiples por usuario y revocación granular sin tocar columnas de BD.
 */
class SesionRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Calcula la representación no reversible (SHA-256) de un token de sesión.
     * Garantiza que la BD nunca conserve tokens en texto claro.
     */
    public static function hashToken(string $tokenPlano): string
    {
        return hash('sha256', $tokenPlano);
    }

    /**
     * Persiste una nueva sesión en base de datos.
     */
    public function crear(
        string $tokenPlano,
        ?int $usuarioId,
        string $direccionIp,
        ?string $agenteUsuario,
        array $cargaUtil = []
    ): Sesion {
        $idHash = self::hashToken($tokenPlano);
        $ahora = time();

        if (!isset($cargaUtil['creado_en_timestamp'])) {
            $cargaUtil['creado_en_timestamp'] = $ahora;
        }

        $sql = "INSERT INTO `sesiones` (
                    `id`, `usuario_id`, `direccion_ip`, `agente_usuario`, `carga_util`, `ultima_actividad`
                ) VALUES (
                    :id, :usuario_id, :direccion_ip, :agente_usuario, :carga_util, :ultima_actividad
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'               => $idHash,
            ':usuario_id'       => $usuarioId,
            ':direccion_ip'     => $direccionIp,
            ':agente_usuario'   => $agenteUsuario,
            ':carga_util'       => json_encode($cargaUtil, JSON_UNESCAPED_UNICODE),
            ':ultima_actividad' => $ahora,
        ]);

        return new Sesion(
            id: $idHash,
            usuarioId: $usuarioId,
            direccionIp: $direccionIp,
            agenteUsuario: $agenteUsuario,
            cargaUtil: $cargaUtil,
            ultimaActividad: $ahora
        );
    }

    /**
     * Busca una sesión activa por el token en texto plano presentado por el cliente.
     */
    public function buscarPorToken(string $tokenPlano): ?Sesion
    {
        $idHash = self::hashToken($tokenPlano);
        return $this->buscarPorIdHash($idHash);
    }

    /**
     * Busca una sesión por su identificador primario (hash SHA-256).
     */
    public function buscarPorIdHash(string $idHash): ?Sesion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `sesiones` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $idHash]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Sesion::desdeArreglo($fila) : null;
    }

    /**
     * Actualiza la marca de tiempo de última actividad de una sesión.
     */
    public function actualizarActividad(string $tokenPlano): bool
    {
        $idHash = self::hashToken($tokenPlano);
        $ahora = time();

        $stmt = $this->pdo->prepare("UPDATE `sesiones` SET `ultima_actividad` = :ahora WHERE `id` = :id");
        return $stmt->execute([':ahora' => $ahora, ':id' => $idHash]);
    }

    /**
     * Actualiza la carga útil almacenada para una sesión dada.
     */
    public function actualizarCargaUtil(string $tokenPlano, array $cargaUtil): bool
    {
        $idHash = self::hashToken($tokenPlano);
        $ahora = time();

        $stmt = $this->pdo->prepare("UPDATE `sesiones` SET `carga_util` = :carga, `ultima_actividad` = :ahora WHERE `id` = :id");
        return $stmt->execute([
            ':carga' => json_encode($cargaUtil, JSON_UNESCAPED_UNICODE),
            ':ahora' => $ahora,
            ':id'    => $idHash,
        ]);
    }

    /**
     * Revoca y elimina físicamente una sesión individual por su token de cliente.
     */
    public function revocarPorToken(string $tokenPlano): bool
    {
        $idHash = self::hashToken($tokenPlano);
        return $this->revocarPorIdHash($idHash);
    }

    /**
     * Revoca y elimina físicamente una sesión individual por su hash identificador.
     */
    public function revocarPorIdHash(string $idHash): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `sesiones` WHERE `id` = :id");
        $stmt->execute([':id' => $idHash]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Revoca todas las sesiones activas de un usuario (ej. cambio de clave, desconexión forzada).
     * Opcionalmente preserva la sesión actual si se proporciona su token.
     */
    public function revocarTodasDeUsuario(int $usuarioId, ?string $exceptoTokenPlano = null): int
    {
        if ($exceptoTokenPlano !== null) {
            $exceptoHash = self::hashToken($exceptoTokenPlano);
            $stmt = $this->pdo->prepare("DELETE FROM `sesiones` WHERE `usuario_id` = :usuario_id AND `id` != :excepto_id");
            $stmt->execute([':usuario_id' => $usuarioId, ':excepto_id' => $exceptoHash]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM `sesiones` WHERE `usuario_id` = :usuario_id");
            $stmt->execute([':usuario_id' => $usuarioId]);
        }

        return $stmt->rowCount();
    }

    /**
     * Consulta todas las sesiones activas de un usuario determinado.
     * @return Sesion[]
     */
    public function buscarActivasPorUsuario(int $usuarioId, ?int $timeoutSegundos = null): array
    {
        $timeout = $timeoutSegundos ?? ConfiguracionSeguridad::timeoutInactividad();
        $limiteActividad = time() - $timeout;

        $sql = "SELECT * FROM `sesiones`
                WHERE `usuario_id` = :usuario_id
                  AND `ultima_actividad` >= :limite
                ORDER BY `ultima_actividad` DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':limite'     => $limiteActividad,
        ]);

        return array_map(fn(array $f) => Sesion::desdeArreglo($f), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Purga sesiones inactivas o expiradas de la base de datos.
     */
    public function limpiarExpiradas(?int $timeoutSegundos = null): int
    {
        $timeout = $timeoutSegundos ?? ConfiguracionSeguridad::timeoutInactividad();
        $limiteActividad = time() - $timeout;

        $stmt = $this->pdo->prepare("DELETE FROM `sesiones` WHERE `ultima_actividad` < :limite");
        $stmt->execute([':limite' => $limiteActividad]);

        return $stmt->rowCount();
    }
}
