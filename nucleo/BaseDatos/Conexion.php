<?php

declare(strict_types=1);

namespace Nucleo\BaseDatos;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Gestor de conexión PDO nativo para MySQL/MariaDB.
 * Proporciona instancia única configurada bajo estándares de seguridad y rendimiento.
 */
class Conexion
{
    private static ?PDO $instancia = null;

    private function __construct()
    {
    }

    /**
     * Obtiene la instancia activa de PDO o crea una nueva basada en las variables de entorno.
     */
    public static function obtenerInstancia(): PDO
    {
        if (self::$instancia === null) {
            $driver = (string) entorno('BD_DRIVER', 'mysql');
            $host = (string) entorno('BD_HOST', '127.0.0.1');
            $puerto = (int) entorno('BD_PUERTO', 3306);
            $nombre = (string) entorno('BD_NOMBRE', 'app_candelaria');
            $usuario = (string) entorno('BD_USUARIO', 'root');
            $contrasena = (string) entorno('BD_CONTRASENA', '');
            $charset = (string) entorno('BD_CHARSET', 'utf8mb4');
            $colate = (string) entorno('BD_COLATE', 'utf8mb4_unicode_ci');

            $dsn = "{$driver}:host={$host};port={$puerto};dbname={$nombre};charset={$charset}";

            $opciones = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$charset}' COLLATE '{$colate}'",
            ];

            try {
                self::$instancia = new PDO($dsn, $usuario, $contrasena, $opciones);
            } catch (PDOException $e) {
                throw new RuntimeException('Error de conexión a base de datos: ' . $e->getMessage(), (int) $e->getCode(), $e);
            }
        }

        return self::$instancia;
    }

    /**
     * Inyecta una instancia personalizada (útil para pruebas en bases de datos aisladas o transacciones).
     */
    public static function establecerInstancia(?PDO $pdo): void
    {
        self::$instancia = $pdo;
    }
}
