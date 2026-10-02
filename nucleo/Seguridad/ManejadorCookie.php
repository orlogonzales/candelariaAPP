<?php

declare(strict_types=1);

namespace Nucleo\Seguridad;

/**
 * Gestor seguro de cookies de sesión conforme a estándares OWASP.
 * Implementa HttpOnly, SameSite, Secure adaptativo y rutas restringidas.
 */
class ManejadorCookie
{
    /**
     * Emite la cookie de sesión hacia el cliente.
     */
    public static function emitir(string $tokenPlano, int $expiraEn = 0): bool
    {
        $nombre = ConfiguracionSeguridad::nombreCookie();
        $opciones = ConfiguracionSeguridad::opcionesCookie();
        $opciones['expires'] = $expiraEn;

        if (headers_sent()) {
            return false;
        }

        return setcookie($nombre, $tokenPlano, $opciones);
    }

    /**
     * Invalida y elimina la cookie de sesión del cliente enviando expiración en el pasado.
     */
    public static function eliminar(): bool
    {
        $nombre = ConfiguracionSeguridad::nombreCookie();
        $opciones = ConfiguracionSeguridad::opcionesCookie();
        $opciones['expires'] = time() - 3600;

        if (headers_sent()) {
            return false;
        }

        return setcookie($nombre, '', $opciones);
    }

    /**
     * Extrae el token de sesión desde las cookies de la petición.
     */
    public static function extraerDePeticion(?array $cookies = null): ?string
    {
        $fuente = $cookies ?? $_COOKIE;
        $nombre = ConfiguracionSeguridad::nombreCookie();

        if (!empty($fuente[$nombre]) && is_string($fuente[$nombre])) {
            return trim($fuente[$nombre]);
        }

        return null;
    }
}
