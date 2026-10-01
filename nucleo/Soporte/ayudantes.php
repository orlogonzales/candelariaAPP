<?php

declare(strict_types=1);

/**
 * Funciones auxiliares globales del sistema CandelariaAPP.
 * Nomenclatura en español estricta según directrices de arquitectura.
 */

if (!function_exists('entorno')) {
    /**
     * Obtiene el valor de una variable de entorno con fallback predeterminado.
     */
    function entorno(string $clave, mixed $predeterminado = null): mixed
    {
        $valor = $_ENV[$clave] ?? getenv($clave);
        if ($valor === false || $valor === null) {
            return $predeterminado;
        }

        return match (strtolower((string) $valor)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $valor,
        };
    }
}

if (!function_exists('escapar_html')) {
    /**
     * Escapa caracteres especiales para prevención estricta de XSS contextual.
     */
    function escapar_html(?string $cadena): string
    {
        return htmlspecialchars((string) $cadena, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('normalizar_mayusculas')) {
    /**
     * Normaliza cadenas textuales de negocio a MAYÚSCULAS respetando caracteres multibyte.
     * Regla obligatoria: Sección 10 de la Instrucción Maestra.
     */
    function normalizar_mayusculas(?string $cadena): string
    {
        if ($cadena === null) {
            return '';
        }
        return mb_strtoupper(trim($cadena), 'UTF-8');
    }
}

if (!function_exists('normalizar_minusculas')) {
    /**
     * Normaliza correos electrónicos y URLs a minúsculas respetando caracteres multibyte.
     */
    function normalizar_minusculas(?string $cadena): string
    {
        if ($cadena === null) {
            return '';
        }
        return mb_strtolower(trim($cadena), 'UTF-8');
    }
}

if (!function_exists('url_base')) {
    /**
     * Genera una URL absoluta adaptativa a partir de la raíz del host actual.
     */
    function url_base(string $ruta = ''): string
    {
        if (!empty($_SERVER['HTTP_HOST'])) {
            $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $directorio = trim(dirname($scriptName), '/');
            if (str_ends_with($directorio, 'publico')) {
                $directorio = trim(substr($directorio, 0, -7), '/');
            }
            $prefijo = ($directorio !== '' && $directorio !== '.') ? '/' . $directorio : '';
            $base = rtrim($protocolo . $host . $prefijo, '/');
        } else {
            $base = rtrim((string) entorno('APP_URL', '/'), '/');
        }

        $ruta = ltrim($ruta, '/');
        return empty($ruta) ? $base : $base . '/' . $ruta;
    }
}

if (!function_exists('url_activo')) {
    /**
     * Genera la URL pública hacia un activo estático de la aplicación.
     */
    function url_activo(string $ruta = ''): string
    {
        $rutaLimpia = ltrim($ruta, '/');
        return url_base('publico/activos/' . $rutaLimpia);
    }
}
