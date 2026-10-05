<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\ActorSistema;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio PDO nativo para el catálogo controlado de actores de sistema.
 * Permite resolver identificadores y entidades técnicas sin depender de IDs mágicos hardcodeados.
 */
class ActorSistemaRepositorio
{
    private PDO $pdo;
    private static array $cachePorCodigo = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Busca un actor del sistema por su identificador primario.
     */
    public function buscarPorId(int $id): ?ActorSistema
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `actores_sistema` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ActorSistema::desdeArreglo($fila) : null;
    }

    /**
     * Busca un actor del sistema por su código único estable.
     */
    public function buscarPorCodigo(string $codigo): ?ActorSistema
    {
        $codigoNormalizado = strtoupper(trim($codigo));
        if (isset(self::$cachePorCodigo[$codigoNormalizado])) {
            return self::$cachePorCodigo[$codigoNormalizado];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM `actores_sistema` WHERE `codigo` = :codigo LIMIT 1");
        $stmt->execute([':codigo' => $codigoNormalizado]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $actor = ActorSistema::desdeArreglo($fila);
        self::$cachePorCodigo[$codigoNormalizado] = $actor;
        return $actor;
    }

    /**
     * Obtiene el ID numérico de un actor por su código estable.
     * Si no existe en la base de datos, arroja excepción o valor por defecto según modo estricto.
     */
    public function obtenerIdPorCodigo(string $codigo): int
    {
        $actor = $this->buscarPorCodigo($codigo);
        if ($actor === null) {
            throw new \RuntimeException("El actor de sistema con código '{$codigo}' no está registrado en el catálogo de la base de datos.");
        }

        return $actor->id;
    }

    /**
     * Limpia la caché interna en memoria (útil en suites de pruebas y transacciones).
     */
    public static function limpiarCache(): void
    {
        self::$cachePorCodigo = [];
    }
}
