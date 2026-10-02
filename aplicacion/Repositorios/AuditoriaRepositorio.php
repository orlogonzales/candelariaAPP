<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;

/**
 * Repositorio PDO nativo para registro de auditoría de operaciones (Append-Only).
 * Inmutable por diseño: no expone métodos para actualización ni eliminación física.
 * Sanitiza automáticamente secretos y credenciales antes de persistir en base de datos.
 */
class AuditoriaRepositorio
{
    private PDO $pdo;

    /**
     * Lista de claves sensibles que deben ser redactadas en los payloads de auditoría.
     */
    private const CLAVES_SENSIBLES = [
        'contrasena',
        'password',
        'contrasena_hash',
        'password_hash',
        'clave',
        'token',
        'api_key',
        'secret',
        'secreto',
        'cvv',
        'tarjeta',
        'authorization',
    ];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Registra un nuevo evento inmutable de auditoría dual (humano o sistema).
     */
    public function registrar(
        ContextoOperacion $contexto,
        string $modulo,
        string $accion,
        string $entidadTipo,
        string $entidadId,
        ?array $datosPrevios = null,
        ?array $datosNuevos = null
    ): int {
        $previosSanitizados = $datosPrevios !== null ? $this->sanitizarDatos($datosPrevios) : null;
        $nuevosSanitizados = $datosNuevos !== null ? $this->sanitizarDatos($datosNuevos) : null;

        $sql = "INSERT INTO `auditoria_operaciones` (
                    `organizacion_id`, `actor_tipo`, `usuario_id`, `actor_sistema_id`,
                    `canal_id`, `correlacion_id`, `modulo`, `accion`,
                    `entidad_tipo`, `entidad_id`, `datos_previos_json`, `datos_nuevos_json`,
                    `origen_ip`, `agente_usuario`
                ) VALUES (
                    :organizacion_id, :actor_tipo, :usuario_id, :actor_sistema_id,
                    :canal_id, :correlacion_id, :modulo, :accion,
                    :entidad_tipo, :entidad_id, :datos_previos_json, :datos_nuevos_json,
                    :origen_ip, :agente_usuario
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'    => $contexto->organizacionId,
            ':actor_tipo'         => $contexto->actorTipo,
            ':usuario_id'         => $contexto->usuarioId,
            ':actor_sistema_id'   => $contexto->actorSistemaId,
            ':canal_id'           => $contexto->canalId,
            ':correlacion_id'     => $contexto->correlacionId,
            ':modulo'             => normalizar_minusculas($modulo),
            ':accion'             => normalizar_mayusculas($accion),
            ':entidad_tipo'       => normalizar_minusculas($entidadTipo),
            ':entidad_id'         => trim($entidadId),
            ':datos_previos_json' => $previosSanitizados ? json_encode($previosSanitizados, JSON_UNESCAPED_UNICODE) : null,
            ':datos_nuevos_json'  => $nuevosSanitizados ? json_encode($nuevosSanitizados, JSON_UNESCAPED_UNICODE) : null,
            ':origen_ip'          => $contexto->origenIp,
            ':agente_usuario'     => $contexto->agenteUsuario,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Consulta el histórico de auditoría por identificador de correlación transversal.
     */
    public function buscarPorCorrelacion(string $correlacionId): array
    {
        $sql = "SELECT a.*, c.codigo AS canal_codigo, s.codigo AS actor_sistema_codigo
                FROM `auditoria_operaciones` a
                INNER JOIN `canales` c ON c.id = a.canal_id
                LEFT JOIN `actores_sistema` s ON s.id = a.actor_sistema_id
                WHERE a.correlacion_id = :correlacion_id
                ORDER BY a.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':correlacion_id' => $correlacionId]);

        return $stmt->fetchAll();
    }

    /**
     * Consulta el histórico de auditoría de una entidad específica.
     */
    public function buscarPorEntidad(string $entidadTipo, string $entidadId, int $limite = 50): array
    {
        $sql = "SELECT a.*, c.codigo AS canal_codigo, s.codigo AS actor_sistema_codigo
                FROM `auditoria_operaciones` a
                INNER JOIN `canales` c ON c.id = a.canal_id
                LEFT JOIN `actores_sistema` s ON s.id = a.actor_sistema_id
                WHERE a.entidad_tipo = :entidad_tipo
                  AND a.entidad_id = :entidad_id
                ORDER BY a.id DESC
                LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':entidad_tipo', normalizar_minusculas($entidadTipo));
        $stmt->bindValue(':entidad_id', trim($entidadId));
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Sanitiza recursivamente un arreglo de datos censurando claves sensibles.
     */
    private function sanitizarDatos(array $datos): array
    {
        $resultado = [];

        foreach ($datos as $clave => $valor) {
            $claveMinuscula = strtolower((string) $clave);

            $esSensible = false;
            foreach (self::CLAVES_SENSIBLES as $sensible) {
                if (str_contains($claveMinuscula, $sensible)) {
                    $esSensible = true;
                    break;
                }
            }

            if ($esSensible) {
                $resultado[$clave] = '[REDACTADO]';
            } elseif (is_array($valor)) {
                $resultado[$clave] = $this->sanitizarDatos($valor);
            } else {
                $resultado[$clave] = $valor;
            }
        }

        return $resultado;
    }
}
