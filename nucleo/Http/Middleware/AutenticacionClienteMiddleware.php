<?php

declare(strict_types=1);

namespace Nucleo\Http\Middleware;

use Aplicacion\Entidades\ClienteSesion;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Servicios\AutenticacionClienteServicio;
use Nucleo\BaseDatos\Conexion;
use PDO;

class AutenticacionClienteMiddleware
{
    private AutenticacionClienteServicio $authClienteSvc;

    public function __construct(?AutenticacionClienteServicio $authClienteSvc = null)
    {
        if ($authClienteSvc !== null) {
            $this->authClienteSvc = $authClienteSvc;
        } else {
            $pdo = Conexion::obtenerInstancia();
            $sesionRepo = new \Aplicacion\Repositorios\ClienteSesionRepositorio($pdo);
            $codigoRepo = new \Aplicacion\Repositorios\ClienteCodigoAccesoRepositorio($pdo);
            $clienteRepo = new \Aplicacion\Repositorios\ClienteRepositorio($pdo);
            $personaRepo = new \Aplicacion\Repositorios\PersonaRepositorio($pdo);
            $this->authClienteSvc = new AutenticacionClienteServicio(
                $sesionRepo,
                $codigoRepo,
                $clienteRepo,
                $personaRepo
            );
        }
    }

    /**
     * Procesa y valida la sesión del cliente en el portal público.
     */
    public function procesar(
        array $servidor = [],
        array $cookies = [],
        bool $bloquearSiInvalido = true
    ): ?ClienteSesion {
        $servidor = !empty($servidor) ? $servidor : $_SERVER;
        $cookies = !empty($cookies) ? $cookies : $_COOKIE;

        // 1. Extraer token de cookie candelaria_cliente_sesion
        $token = $cookies[AutenticacionClienteServicio::COOKIE_CLIENTE_SESION] ?? null;

        // 2. Extraer de encabezado X-Cliente-Token o Authorization Bearer
        if ($token === null) {
            $token = $servidor['HTTP_X_CLIENTE_TOKEN'] ?? null;
        }

        if ($token === null) {
            $authHeader = $servidor['HTTP_AUTHORIZATION'] ?? $servidor['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
            if ($authHeader !== null && preg_match('/^Bearer\s+(cli_ses_[a-f0-9]+)$/i', trim((string) $authHeader), $matches)) {
                $token = $matches[1];
            }
        }

        if ($token === null) {
            if ($bloquearSiInvalido) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'exito'        => false,
                    'codigo_error' => 'NO_AUTENTICADO',
                    'mensaje'      => 'Se requiere iniciar sesión en el Espacio del Cliente.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            return null;
        }

        $sesion = $this->authClienteSvc->validarSesion((string) $token);
        if ($sesion === null) {
            if ($bloquearSiInvalido) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'exito'        => false,
                    'codigo_error' => 'SESION_EXPIRADA_O_INVALIDA',
                    'mensaje'      => 'Su sesión de cliente ha expirado o es inválida. Por favor solicite un nuevo código de acceso.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            return null;
        }

        return $sesion;
    }
}
