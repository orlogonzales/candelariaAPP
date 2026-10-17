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
     * Retorna todas las rutas registradas en el enrutador.
     */
    public static function obtenerRutas(): array
    {
        return self::$rutas;
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
                    $argumentos = self::prepararArgumentosAccion($ruta['patron'], $coincidencias, $accion);

                    if ($accion instanceof Closure) {
                        echo call_user_func_array($accion, $argumentos);
                        return;
                    }

                    if (is_array($accion) && count($accion) === 2) {
                        [$clase, $metodo] = $accion;
                        $instancia = new $clase();
                        echo call_user_func_array([$instancia, $metodo], $argumentos);
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

    /**
     * Adapta los parámetros capturados en la URI a las firmas tipadas bajo strict_types=1.
     */
    public static function prepararArgumentosAccion(string $patron, array $coincidencias, Closure|array $accion): array
    {
        if (empty($coincidencias)) {
            return [];
        }

        try {
            $ref = $accion instanceof Closure
                ? new \ReflectionFunction($accion)
                : new \ReflectionMethod($accion[0], $accion[1]);

            $parametros = $ref->getParameters();
            if (empty($parametros)) {
                return $coincidencias;
            }

            $primerTipo = $parametros[0]->getType();
            if ($primerTipo instanceof \ReflectionNamedType && $primerTipo->getName() === 'array') {
                preg_match_all('#\{([a-zA-Z0-9_]+)\}#', $patron, $m);
                $nombres = $m[1] ?? [];
                $mapa = [];
                foreach ($coincidencias as $idx => $valor) {
                    $llave = $nombres[$idx] ?? (string) $idx;
                    $mapa[$llave] = is_numeric($valor) ? (int) $valor : $valor;
                }
                if (!isset($mapa['id']) && isset($coincidencias[0])) {
                    $mapa['id'] = is_numeric($coincidencias[0]) ? (int) $coincidencias[0] : $coincidencias[0];
                }
                return [$mapa];
            }

            $argumentos = [];
            foreach ($coincidencias as $idx => $valor) {
                if (isset($parametros[$idx])) {
                    $tipo = $parametros[$idx]->getType();
                    if ($tipo instanceof \ReflectionNamedType) {
                        $nombreTipo = $tipo->getName();
                        if ($nombreTipo === 'int' && is_numeric($valor)) {
                            $argumentos[] = (int) $valor;
                            continue;
                        }
                        if ($nombreTipo === 'float' && is_numeric($valor)) {
                            $argumentos[] = (float) $valor;
                            continue;
                        }
                        if ($nombreTipo === 'string') {
                            $argumentos[] = (string) $valor;
                            continue;
                        }
                    }
                }
                $argumentos[] = $valor;
            }

            return $argumentos;
        } catch (\Throwable) {
            return $coincidencias;
        }
    }
}
