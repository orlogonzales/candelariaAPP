<?php

declare(strict_types=1);

namespace Nucleo\Enrutamiento;

use Closure;

/**
 * Enrutador HTTP nativo y ligero de CandelariaAPP.
 * Nomenclatura en español estricta.
 */
class Enrutador
{
    private static array $rutas = [];

    public static function get(string $patron, Closure|array|string $accion): void
    {
        self::agregarRuta('GET', $patron, $accion);
    }

    public static function post(string $patron, Closure|array|string $accion): void
    {
        self::agregarRuta('POST', $patron, $accion);
    }

    public static function put(string $patron, Closure|array|string $accion): void
    {
        self::agregarRuta('PUT', $patron, $accion);
    }

    public static function delete(string $patron, Closure|array|string $accion): void
    {
        self::agregarRuta('DELETE', $patron, $accion);
    }

    public static function patch(string $patron, Closure|array|string $accion): void
    {
        self::agregarRuta('PATCH', $patron, $accion);
    }

    private static function agregarRuta(string $metodo, string $patron, Closure|array|string $accion): void
    {
        self::$rutas[] = [
            'metodo' => $metodo,
            'patron' => '/' . trim($patron, '/'),
            'accion' => $accion
        ];
    }

    /**
     * Despacha la petición actual según el método y URI solicitados.
     */
    public static function despachar(): void
    {
        $metodoActual = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($metodoActual === 'HEAD') {
            $metodoActual = 'GET';
        }
        if ($metodoActual === 'POST') {
            $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_POST['_method'] ?? null;
            if ($override && in_array(strtoupper((string) $override), ['PUT', 'PATCH', 'DELETE'], true)) {
                $metodoActual = strtoupper((string) $override);
            }
        }
        $uriCompleta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        // Normalizar URI eliminando el subdirectorio de Laragon si está presente
        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '/' && $scriptName !== '\\' && str_starts_with($uriCompleta, $scriptName)) {
            $uriLimpia = substr($uriCompleta, strlen($scriptName));
        } else {
            // También contemplar prefijo /app.candelaria/
            $prefijo = '/app.candelaria';
            if (str_starts_with($uriCompleta, $prefijo)) {
                $uriLimpia = substr($uriCompleta, strlen($prefijo));
            } else {
                $uriLimpia = $uriCompleta;
            }
        }

        // Si la URI incluye /publico, removerlo para normalizar rutas
        if (str_starts_with($uriLimpia, '/publico')) {
            $uriLimpia = substr($uriLimpia, strlen('/publico'));
        }

        $uriLimpia = '/' . trim($uriLimpia, '/');

        foreach (self::$rutas as $ruta) {
            if ($ruta['metodo'] !== $metodoActual) {
                continue;
            }

            // Coincidencia exacta o por patrón de expresión regular
            $regex = '#^' . preg_replace('#\{([a-zA-Z0-9_]+)\}#', '([^/]+)', $ruta['patron']) . '$#';

            if (preg_match($regex, $uriLimpia, $coincidencias)) {
                array_shift($coincidencias); // Quitar coincidencia global
                $accion = $ruta['accion'];

                try {
                    if ($accion instanceof Closure) {
                        echo call_user_func_array($accion, $coincidencias);
                        return;
                    }

                    if (is_array($accion) && count($accion) === 2) {
                        [$clase, $metodo] = $accion;
                        $instancia = new $clase();
                        echo call_user_func_array([$instancia, $metodo], $coincidencias);
                        return;
                    }
                } catch (\Throwable $e) {
                    if (str_starts_with($uriLimpia, '/api/')) {
                        if (!headers_sent()) {
                            http_response_code(500);
                            header('Content-Type: application/json; charset=utf-8');
                        }
                        echo json_encode([
                            'exito'   => false,
                            'codigo'  => 500,
                            'mensaje' => 'Error interno del servidor.',
                            'datos'   => null,
                        ], JSON_UNESCAPED_UNICODE);
                        return;
                    }
                    throw $e;
                }
            }
        }

        // Si ninguna ruta coincide
        http_response_code(404);
        if (str_starts_with($uriLimpia, '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'exito' => false,
                'codigo' => 404,
                'mensaje' => 'Recurso API no encontrado.',
                'datos' => null
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo "<h1>404 - Página no encontrada</h1><p>La ruta '{$uriLimpia}' no existe en CandelariaAPP.</p>";
        }
    }
}
