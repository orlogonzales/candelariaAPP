<?php

declare(strict_types=1);

namespace Nucleo\Seguridad;

/**
 * Acceso centralizado y fuertemente tipado a los parámetros de seguridad y sesiones.
 * Evita la dispersión de números mágicos y cadenas de configuración.
 */
class ConfiguracionSeguridad
{
    private static ?array $config = null;

    /**
     * Carga y almacena en caché la matriz de configuración de seguridad.
     */
    public static function obtenerConfiguracion(): array
    {
        if (self::$config === null) {
            $archivo = dirname(__DIR__, 2) . '/configuracion/seguridad.php';
            if (file_exists($archivo)) {
                self::$config = require $archivo;
            } else {
                self::$config = [
                    'intentos_fallidos_maximos'     => 5,
                    'minutos_bloqueo'               => 15,
                    'timeout_inactividad_segundos'  => 7200,
                    'tiempo_vida_absoluto_segundos' => 86400,
                    'cookie' => [
                        'nombre'   => 'candelaria_sesion',
                        'ruta'     => '/',
                        'dominio'  => null,
                        'httponly' => true,
                        'samesite' => 'Lax',
                        'seguro'   => false,
                    ],
                    'csrf' => [
                        'nombre_campo'         => '_csrf_token',
                        'nombre_encabezado'    => 'X-CSRF-Token',
                        'longitud_bytes'       => 32,
                        'tiempo_vida_segundos' => 7200,
                    ],
                ];
            }
        }

        return self::$config;
    }

    /**
     * Permite sobreescribir la configuración en tiempo de ejecución (útil para pruebas).
     */
    public static function establecerConfiguracion(?array $config): void
    {
        self::$config = $config;
    }

    public static function maxIntentosFallidos(): int
    {
        return (int) (self::obtenerConfiguracion()['intentos_fallidos_maximos'] ?? 5);
    }

    public static function minutosBloqueo(): int
    {
        return (int) (self::obtenerConfiguracion()['minutos_bloqueo'] ?? 15);
    }

    public static function timeoutInactividad(): int
    {
        return (int) (self::obtenerConfiguracion()['timeout_inactividad_segundos'] ?? 7200);
    }

    public static function tiempoVidaAbsoluto(): int
    {
        return (int) (self::obtenerConfiguracion()['tiempo_vida_absoluto_segundos'] ?? 86400);
    }

    public static function nombreCookie(): string
    {
        return (string) (self::obtenerConfiguracion()['cookie']['nombre'] ?? 'candelaria_sesion');
    }

    public static function opcionesCookie(): array
    {
        $c = self::obtenerConfiguracion()['cookie'] ?? [];
        return [
            'expires'  => 0, // sesión de navegador, o timestamp absoluto
            'path'     => $c['ruta'] ?? '/',
            'domain'   => $c['dominio'] ?? '',
            'secure'   => (bool) ($c['seguro'] ?? false),
            'httponly' => (bool) ($c['httponly'] ?? true),
            'samesite' => $c['samesite'] ?? 'Lax',
        ];
    }

    public static function nombreCampoCsrf(): string
    {
        return (string) (self::obtenerConfiguracion()['csrf']['nombre_campo'] ?? '_csrf_token');
    }

    public static function nombreEncabezadoCsrf(): string
    {
        return (string) (self::obtenerConfiguracion()['csrf']['nombre_encabezado'] ?? 'X-CSRF-Token');
    }

    public static function longitudBytesCsrf(): int
    {
        return (int) (self::obtenerConfiguracion()['csrf']['longitud_bytes'] ?? 32);
    }

    public static function timeoutCsrf(): int
    {
        return (int) (self::obtenerConfiguracion()['csrf']['tiempo_vida_segundos'] ?? 7200);
    }
}
