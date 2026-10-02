<?php

declare(strict_types=1);

namespace Nucleo\Seguridad;

use RuntimeException;

/**
 * Protector CSRF (Cross-Site Request Forgery) criptográfico.
 * Genera tokens de alta entropía y ejecuta comparaciones en tiempo constante (hash_equals).
 * Diseñado exclusivamente para flujos interactivos de navegador y formularios web.
 */
class ProtectorCsrf
{
    /**
     * Genera un nuevo token CSRF criptográficamente seguro.
     */
    public static function generarToken(): string
    {
        $bytes = ConfiguracionSeguridad::longitudBytesCsrf();
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Valida de manera segura un token CSRF comparándolo en tiempo constante.
     * Retorna false ante tokens nulos, vacíos o dispares, previniendo timing attacks.
     */
    public static function validarToken(?string $tokenEsperado, ?string $tokenRecibido): bool
    {
        if ($tokenEsperado === null || $tokenRecibido === null) {
            return false;
        }

        if ($tokenEsperado === '' || $tokenRecibido === '') {
            return false;
        }

        return hash_equals($tokenEsperado, $tokenRecibido);
    }

    /**
     * Extrae el token CSRF recibido desde los campos POST o encabezados HTTP de la solicitud.
     */
    public static function extraerTokenDePeticion(array $post = [], array $servidor = []): ?string
    {
        $nombreCampo = ConfiguracionSeguridad::nombreCampoCsrf();
        if (!empty($post[$nombreCampo]) && is_string($post[$nombreCampo])) {
            return trim($post[$nombreCampo]);
        }

        // Buscar en encabezado HTTP (e.g., HTTP_X_CSRF_TOKEN)
        $nombreEncabezado = 'HTTP_' . strtoupper(str_replace('-', '_', ConfiguracionSeguridad::nombreEncabezadoCsrf()));
        if (!empty($servidor[$nombreEncabezado]) && is_string($servidor[$nombreEncabezado])) {
            return trim($servidor[$nombreEncabezado]);
        }

        return null;
    }

    /**
     * Verifica la solicitud mutativa HTTP (POST, PUT, DELETE, PATCH).
     * Si el método es seguro (GET, HEAD, OPTIONS), no exige validación CSRF.
     */
    public static function verificarPeticion(string $metodo, ?string $tokenEsperado, array $post = [], array $servidor = []): bool
    {
        $metodo = strtoupper(trim($metodo));
        if (in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $tokenRecibido = self::extraerTokenDePeticion($post, $servidor);
        return self::validarToken($tokenEsperado, $tokenRecibido);
    }
}
