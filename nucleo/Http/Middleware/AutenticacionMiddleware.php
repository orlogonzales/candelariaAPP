<?php

declare(strict_types=1);

namespace Nucleo\Http\Middleware;

use Aplicacion\Seguridad\AutenticacionServicio;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Seguridad\ManejadorCookie;

/**
 * Middleware de Autenticación de CandelariaAPP.
 * Verifica la existencia y validez de la sesión activa del usuario humano.
 * Hidrata el Contexto de Operación para el resto del ciclo de vida de la petición.
 */
class AutenticacionMiddleware
{
    private AutenticacionServicio $autenticacionServicio;

    public function __construct(?AutenticacionServicio $autenticacionServicio = null)
    {
        $this->autenticacionServicio = $autenticacionServicio ?? new AutenticacionServicio();
    }

    /**
     * Ejecuta la verificación de autenticación de la petición actual.
     */
    public function procesar(
        array $servidor = [],
        array $cookies = [],
        bool $bloquearSiInvalido = true
    ): ?ContextoOperacion {
        $servidor = !empty($servidor) ? $servidor : $_SERVER;
        $cookies = !empty($cookies) ? $cookies : $_COOKIE;

        // 1. Extraer token desde Cookie o Encabezado Authorization
        $token = ManejadorCookie::extraerDePeticion($cookies);
        if ($token === null) {
            $authHeader = $servidor['HTTP_AUTHORIZATION']
                ?? $servidor['REDIRECT_HTTP_AUTHORIZATION']
                ?? null;

            if ($authHeader === null && function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
            }

            if (!empty($authHeader)) {
                $auth = trim((string) $authHeader);
                if (preg_match('/^Bearer\s+(.+)$/i', $auth, $coincidencias)) {
                    $token = trim($coincidencias[1]);
                }
            }
        }

        $ip = $servidor['REMOTE_ADDR'] ?? '127.0.0.1';
        $agenteUsuario = $servidor['HTTP_USER_AGENT'] ?? null;
        $correlacionId = ContextoOperacion::extraerDeEncabezados($servidor);

        // 2. Si no hay token proporcionado
        if ($token === null) {
            ContextoOperacion::establecerActual(null);
            if ($bloquearSiInvalido) {
                $this->denegarAcceso($servidor, 'No autenticado: token de sesión ausente.');
            }
            return null;
        }

        // 3. Validar sesión mediante el servicio central
        $contexto = $this->autenticacionServicio->validarSesion($token, $ip, $agenteUsuario, $correlacionId);

        if ($contexto === null) {
            ContextoOperacion::establecerActual(null);
            if ($bloquearSiInvalido) {
                $this->denegarAcceso($servidor, 'Sesión expirada o no autorizada.');
            }
            return null;
        }

        // 4. Hidratar contexto transversal
        ContextoOperacion::establecerActual($contexto);

        // 5. Propagar encabezado de correlación si los headers no han sido emitidos
        if (!headers_sent()) {
            header("X-Correlation-ID: {$contexto->correlacionId}");
        }

        return $contexto;
    }

    /**
     * Responde con error 401 no autorizado según el tipo de cliente solicitante.
     */
    private function denegarAcceso(array $servidor, string $mensaje): void
    {
        $uri = $servidor['REQUEST_URI'] ?? '/';
        $accept = $servidor['HTTP_ACCEPT'] ?? '';
        $esApi = str_contains($uri, '/api') || str_contains($accept, 'application/json');

        if (!headers_sent()) {
            http_response_code(401);
            if ($esApi) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'exito'   => false,
                    'codigo'  => 401,
                    'mensaje' => $mensaje,
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
            exit;
        }
    }
}
