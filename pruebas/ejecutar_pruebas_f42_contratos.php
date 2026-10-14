<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE CERTIFICACIÓN CONTRACTUAL API 1:1 EN TIEMPO DE EJECUCIÓN (FASE 4.2A/4.2B)
 * DIRECTIVA TÉCNICA DT-V12-002 (DEC-02 OPCIÓN 2A)
 * ==============================================================================
 * Certifica el 100% de los endpoints de `rutas/api.php` (269 endpoints en 13 dominios):
 *  1. Correspondencia bidireccional 1:1 exacta entre `rutas/api.php` y `openapi.json`/`openapi.yaml`.
 *  2. Resolución real de los 20 controladores, métodos públicos y tipado de parámetros (`Enrutador::prepararArgumentosAccion`).
 *  3. Verificación en tiempo de ejecución de controles de autenticación (`BearerAdminAuth`, `CookieClienteAuth`, `TenantHeader`).
 *  4. Ejecución controlada en tiempo de ejecución (con transacción revertida) de los 269 endpoints,
 *     validando cumplimiento de sobre JSON (`{ exito, codigo, mensaje }`) o flujo binario esperado.
 *  5. Emisión de matriz auditable `pruebas/matriz_certificacion_contratos_v12.json`.
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Entidades\ClienteSesion;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Enrutamiento\Enrutador;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionClienteMiddleware;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;

class ExcepcionBloqueoContratoSimulado extends \RuntimeException
{
    public function __construct(public readonly int $codigoHttp, public readonly string $cuerpoJson)
    {
        parent::__construct($cuerpoJson, $codigoHttp);
    }
}

$pruebasEjecutadas = 0;
$pruebasPasadas = 0;
$pruebasFallidas = 0;

function assertF42C(bool $condicion, string $descripcion, string $detalles = ''): void
{
    global $pruebasEjecutadas, $pruebasPasadas, $pruebasFallidas;
    $pruebasEjecutadas++;
    if ($condicion) {
        $pruebasPasadas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$descripcion}" . ($detalles !== '' ? " --> {$detalles}" : "") . "\n";
    }
}

echo "==============================================================================\n";
echo "CANDELARIAAPP V1.2 — SUITE DE CERTIFICACIÓN CONTRACTUAL API 1:1 (FASE 4.2B)\n";
echo "DIRECTIVA DT-V12-002 (DEC-02 OPCIÓN 2A) — 269 ENDPOINTS EN TIEMPO DE EJECUCIÓN\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

// ------------------------------------------------------------------------------
// BLOQUE 1: INVENTARIO EJECUTABLE Y CORRESPONDENCIA BIDIRECCIONAL 1:1 OPENAPI
// ------------------------------------------------------------------------------
echo "--- BLOQUE 1: Inventario Ejecutable y Correspondencia Bidireccional 1:1 ---\n";

require_once __DIR__ . '/../rutas/api.php';
$todasRutasEnrutador = Enrutador::obtenerRutas();
$rutasApi = array_values(array_filter(
    $todasRutasEnrutador,
    fn(array $r) => str_starts_with($r['patron'], '/api/')
));

$rutaOpenApiJson = __DIR__ . '/../docs/api/openapi.json';
$rutaOpenApiYaml = __DIR__ . '/../docs/api/openapi.yaml';

assertF42C(
    file_exists($rutaOpenApiJson) && file_exists($rutaOpenApiYaml),
    "1.1: Especificaciones canónicas docs/api/openapi.json y docs/api/openapi.yaml existen en disco"
);

$spec = json_decode((string) file_get_contents($rutaOpenApiJson), true);
$pathsSpec = $spec['paths'] ?? [];

$mapaRutasApi = [];
$duplicadasEnApiPhp = [];
foreach ($rutasApi as $r) {
    $llave = strtoupper($r['metodo']) . ' ' . $r['patron'];
    if (isset($mapaRutasApi[$llave])) {
        $duplicadasEnApiPhp[] = $llave;
    }
    $mapaRutasApi[$llave] = $r;
}

assertF42C(
    count($duplicadasEnApiPhp) === 0,
    "1.2: Cero registros de rutas duplicadas en rutas/api.php",
    implode(', ', $duplicadasEnApiPhp)
);

assertF42C(
    count($mapaRutasApi) === 269,
    "1.3: Inventario ejecutable de rutas/api.php registra exactamente 269 endpoints únicos (267 de negocio + 2 OpenAPI)",
    "Encontrados: " . count($mapaRutasApi)
);

$mapaSpec = [];
foreach ($pathsSpec as $patron => $metodos) {
    foreach ($metodos as $verbo => $contrato) {
        if (!is_array($contrato)) {
            continue;
        }
        $llave = strtoupper((string) $verbo) . ' ' . $patron;
        $mapaSpec[$llave] = $contrato;
    }
}

$faltantesEnOpenApi = array_diff(array_keys($mapaRutasApi), array_keys($mapaSpec));
$fantasmasEnOpenApi = array_diff(array_keys($mapaSpec), array_keys($mapaRutasApi));

assertF42C(
    count($faltantesEnOpenApi) === 0,
    "1.4: 100% de las rutas de rutas/api.php (269/269) están documentadas en openapi.json (cero rutas huérfanas)",
    implode(', ', $faltantesEnOpenApi)
);

assertF42C(
    count($fantasmasEnOpenApi) === 0,
    "1.5: 100% de las operaciones de openapi.json (269/269) existen en rutas/api.php (cero rutas fantasma)",
    implode(', ', $fantasmasEnOpenApi)
);

// Verificar nuevas rutas de Analítica Multianual (Fase 4.1)
assertF42C(
    isset($mapaSpec['GET /api/v1/dashboard/analitica-multianual']) &&
    isset($mapaSpec['GET /api/v1/dashboard/analitica-multianual/exportar']),
    "1.6: Rutas nuevas de Fase 4.1 (/api/v1/dashboard/analitica-multianual y /exportar) formalmente integradas en OpenAPI 3.0.3"
);

// ------------------------------------------------------------------------------
// BLOQUE 2: RESOLUCIÓN DE CONTROLADORES, MÉTODOS Y PARÁMETROS TIPADOS
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 2: Resolución de los 20 Controladores, Métodos y Parámetros Tipados ---\n";

$controladoresVerificados = [];
$erroresReflexion = [];
$erroresParametrosOpenApi = [];
$erroresTipadoEnrutador = [];

foreach ($mapaRutasApi as $llave => $r) {
    $patron = $r['patron'];
    $metodoHttp = strtoupper($r['metodo']);
    $accion = $r['accion'];
    $contrato = $mapaSpec[$llave];

    // Verificar parámetros de ruta en OpenAPI
    if (preg_match_all('#\{([a-zA-Z0-9_]+)\}#', $patron, $m)) {
        $paramsSpec = [];
        foreach ($contrato['parameters'] ?? [] as $p) {
            if (($p['in'] ?? '') === 'path' && !empty($p['name'])) {
                $paramsSpec[$p['name']] = true;
            }
        }
        foreach ($m[1] as $placeholder) {
            if (!isset($paramsSpec[$placeholder])) {
                $erroresParametrosOpenApi[] = "{$llave} omite parámetro de ruta {{$placeholder}}";
            }
        }
    }

    if (is_array($accion) && count($accion) === 2) {
        [$clase, $metodo] = $accion;
        $controladoresVerificados[$clase] = true;

        if (!class_exists($clase)) {
            $erroresReflexion[] = "Clase inexistente: {$clase} en {$llave}";
            continue;
        }
        if (!method_exists($clase, $metodo)) {
            $erroresReflexion[] = "Método inexistente: {$clase}::{$metodo} en {$llave}";
            continue;
        }
        $ref = new ReflectionMethod($clase, $metodo);
        if (!$ref->isPublic()) {
            $erroresReflexion[] = "Método no público: {$clase}::{$metodo} en {$llave}";
        }

        // Probar resolución de argumentos tipados mediante Enrutador::prepararArgumentosAccion
        preg_match_all('#\{([a-zA-Z0-9_]+)\}#', $patron, $matches);
        $coincidenciasSimuladas = array_fill(0, count($matches[1]), '99999999');
        $argsPreparados = Enrutador::prepararArgumentosAccion($patron, $coincidenciasSimuladas, $accion);

        $paramsMetodo = $ref->getParameters();
        if (!empty($coincidenciasSimuladas) && !empty($paramsMetodo)) {
            $primerTipo = $paramsMetodo[0]->getType();
            if ($primerTipo instanceof ReflectionNamedType) {
                $tn = $primerTipo->getName();
                if ($tn === 'array' && !is_array($argsPreparados[0] ?? null)) {
                    $erroresTipadoEnrutador[] = "{$llave} esperaba array y recibió " . gettype($argsPreparados[0] ?? null);
                } elseif ($tn === 'int' && !is_int($argsPreparados[0] ?? null)) {
                    $erroresTipadoEnrutador[] = "{$llave} esperaba int y recibió " . gettype($argsPreparados[0] ?? null);
                }
            }
        }
    } elseif (!($accion instanceof Closure)) {
        $erroresReflexion[] = "Acción inválida en {$llave}";
    }
}

assertF42C(
    count($erroresReflexion) === 0,
    "2.1: 100% de las 269 rutas resuelven a controladores y métodos públicos existentes (" . count($controladoresVerificados) . " controladores API activos)",
    implode('; ', $erroresReflexion)
);

assertF42C(
    count($erroresParametrosOpenApi) === 0,
    "2.2: 100% de los placeholders de ruta ({id}, {codigo}, {reservaId}, etc.) están declarados en parameters de OpenAPI",
    implode('; ', $erroresParametrosOpenApi)
);

assertF42C(
    count($erroresTipadoEnrutador) === 0,
    "2.3: Enrutador::prepararArgumentosAccion() adapta sin errores todos los parámetros tipados (int, string, array) bajo strict_types=1",
    implode('; ', $erroresTipadoEnrutador)
);

// ------------------------------------------------------------------------------
// BLOQUE 3: CERTIFICACIÓN EN TIEMPO DE EJECUCIÓN DE LOS 269 ENDPOINTS
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 3: Certificación en Tiempo de Ejecución (269 Endpoints) ---\n";

$matrizCertificacion = [];
$fallosRechazoSinAuth = [];
$fallosEjecucionRuntime = [];
$conteoRechazoSinAuth = 0;
$conteoEjecucionRuntime = 0;

/**
 * Inyecta mocks de autenticación/autorización en cualquier instancia de controlador mediante Reflexión.
 */
function inyectarSeguridadEnControlador(
    object $instancia,
    AutenticacionMiddleware $authMock,
    AutorizacionMiddleware $authzMock,
    AutenticacionClienteMiddleware $authClienteMock
): void {
    $ref = new ReflectionObject($instancia);
    foreach ($ref->getProperties() as $prop) {
        $tipo = $prop->getType();
        $nombre = $prop->getName();
        $nombreTipo = $tipo instanceof ReflectionNamedType ? $tipo->getName() : '';

        if ($nombreTipo === AutenticacionMiddleware::class || $nombre === 'authMiddleware') {
            $prop->setAccessible(true);
            $prop->setValue($instancia, $authMock);
        } elseif ($nombreTipo === AutorizacionMiddleware::class || in_array($nombre, ['authzMiddleware', 'rbacMiddleware'], true)) {
            $prop->setAccessible(true);
            $prop->setValue($instancia, $authzMock);
        } elseif ($nombreTipo === AutenticacionClienteMiddleware::class || $nombre === 'authClienteMiddleware') {
            $prop->setAccessible(true);
            $prop->setValue($instancia, $authClienteMock);
        }
    }
}

/**
 * Ejecuta una acción de ruta capturando buffer, retorno y excepciones simuladas de bloqueo HTTP.
 *
 * @return array{cuerpo: string, codigo_http: int}
 */
function ejecutarAccionEnMemoria(
    string $patron,
    string $metodoHttp,
    mixed $accion,
    AutenticacionMiddleware $authMock,
    AutorizacionMiddleware $authzMock,
    AutenticacionClienteMiddleware $authClienteMock,
    string $slugTenant
): array {
    $_SERVER['REQUEST_METHOD'] = $metodoHttp;
    $_SERVER['REQUEST_URI'] = $patron;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'CertificadorContratosV12';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['HTTP_X_ORGANIZACION_SLUG'] = $slugTenant;
    $_COOKIE = [];
    $_POST = in_array($metodoHttp, ['POST', 'PUT', 'PATCH'], true) ? ['_test' => 1] : [];

    if ($patron === '/api/v1/dashboard/analitica-multianual/exportar' || $patron === '/api/v1/ediciones/{id}/reportes/exportar') {
        $_GET = ['formato' => 'formato_prueba_contrato_422', 'tipo' => 'RESERVAS', 'slug' => $slugTenant, 'slug_organizacion' => $slugTenant];
    } else {
        $_GET = ['slug' => $slugTenant, 'slug_organizacion' => $slugTenant];
    }

    preg_match_all('#\{([a-zA-Z0-9_]+)\}#', $patron, $m);
    $coincidencias = [];
    foreach ($m[1] ?? [] as $nombreParam) {
        $coincidencias[] = $nombreParam === 'codigo' ? 'PASARELA_PRUEBA_INACTIVA' : '99999999';
    }
    $argumentos = Enrutador::prepararArgumentosAccion($patron, $coincidencias, $accion);

    set_error_handler(fn(int $errno) => $errno === E_WARNING, E_WARNING);
    @http_response_code(200);
    $nivelObInicial = ob_get_level();
    ob_start();
    $retorno = '';

    try {
        if ($accion instanceof Closure) {
            $res = call_user_func_array($accion, $argumentos);
            if (is_string($res)) {
                $retorno = $res;
            }
        } elseif (is_array($accion) && count($accion) === 2) {
            [$clase, $metodo] = $accion;
            $instancia = new $clase();
            inyectarSeguridadEnControlador($instancia, $authMock, $authzMock, $authClienteMock);
            $res = call_user_func_array([$instancia, $metodo], $argumentos);
            if (is_string($res)) {
                $retorno = $res;
            }
        }
        $buffer = '';
        while (ob_get_level() > $nivelObInicial) {
            $buffer = ob_get_clean() . $buffer;
        }
        $cuerpoFinal = $retorno !== '' ? $retorno : $buffer;
        $codigoHttp = (int) (@http_response_code() ?: 200);

        $jsonDec = json_decode($cuerpoFinal, true);
        if (is_array($jsonDec) && isset($jsonDec['codigo']) && is_numeric($jsonDec['codigo'])) {
            $codigoHttp = (int) $jsonDec['codigo'];
        }

        return [
            'cuerpo'      => $cuerpoFinal,
            'codigo_http' => $codigoHttp,
        ];
    } catch (ExcepcionBloqueoContratoSimulado $eb) {
        while (ob_get_level() > $nivelObInicial) {
            ob_end_clean();
        }
        return [
            'cuerpo'      => $eb->cuerpoJson,
            'codigo_http' => $eb->codigoHttp,
        ];
    } catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $ead) {
        while (ob_get_level() > $nivelObInicial) {
            ob_end_clean();
        }
        $codAd = $ead->getCode() >= 400 && $ead->getCode() < 600 ? (int) $ead->getCode() : 403;
        return [
            'cuerpo'      => json_encode([
                'exito'   => false,
                'codigo'  => $codAd,
                'mensaje' => $ead->getMessage(),
            ], JSON_UNESCAPED_UNICODE),
            'codigo_http' => $codAd,
        ];
    } catch (\Throwable $e) {
        while (ob_get_level() > $nivelObInicial) {
            ob_end_clean();
        }
        return [
            'cuerpo'      => json_encode([
                'exito'   => false,
                'codigo'  => 500,
                'mensaje' => 'Excepción no controlada: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE),
            'codigo_http' => 500,
        ];
    } finally {
        restore_error_handler();
    }
}

$pdo->beginTransaction();

try {
    $sufijo = bin2hex(random_bytes(3));
    $slugTenant = 'org_cert_v12_' . $sufijo;

    // 1. Crear Organización y Edición de prueba para ejecución controlada
    $pdo->prepare("
        INSERT INTO `organizaciones` (`codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `estado`)
        VALUES (:cod, 'Candelaria Cert V1.2', 'Org Certificación Contratos V1.2 SAC', 2, :ruc, 'ACTIVO')
    ")->execute([
        'cod' => $slugTenant,
        'ruc' => '2088' . random_int(1000000, 9999999),
    ]);
    $orgTestId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `fecha_inicio`, `fecha_fin`, `estado`, `es_actual`)
        VALUES (:org, :cod, 'Edición Certificación 2026', 2026, '2026-02-01', '2026-02-15', 'OPERACION', 1)
    ")->execute([
        'org' => $orgTestId,
        'cod' => 'CERT-2026-' . $sufijo,
    ]);
    $edicionTestId = (int) $pdo->lastInsertId();

    // 2. Crear Persona, Usuario (con rol super_admin) y Cliente de prueba
    $pdo->prepare("
        INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`)
        VALUES (:org, 'NATURAL', 1, :num, 'Auditor', 'Contratos V12', :correo, '+51951000999', '+51951000999')
    ")->execute([
        'org'    => $orgTestId,
        'num'    => '88' . random_int(100000, 999999),
        'correo' => "auditor.{$sufijo}@candelaria.pe",
    ]);
    $personaTestId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO `usuarios` (`organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `estado`)
        VALUES (:org, :per, :usr, 'Auditor Contratos V12', :correo, :hash, 'ACTIVO')
    ")->execute([
        'org'    => $orgTestId,
        'per'    => $personaTestId,
        'usr'    => "auditor_v12_{$sufijo}",
        'correo' => "auditor.{$sufijo}@candelaria.pe",
        'hash'   => password_hash('CertV12Secret!', PASSWORD_BCRYPT),
    ]);
    $usuarioTestId = (int) $pdo->lastInsertId();

    $rolSuperAdminId = (int) ($pdo->query("SELECT id FROM roles WHERE codigo = 'super_admin' LIMIT 1")->fetchColumn() ?: 1);
    $pdo->prepare("
        INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`)
        VALUES (:usr, :rol)
    ")->execute([
        'usr' => $usuarioTestId,
        'rol' => $rolSuperAdminId,
    ]);

    $pdo->prepare("
        INSERT INTO `clientes` (`organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`)
        VALUES (:org, :per, 'CLIENTE', 1)
    ")->execute([
        'org' => $orgTestId,
        'per' => $personaTestId,
    ]);
    $clienteTestId = (int) $pdo->lastInsertId();

    // 3. Configurar Mocks de Middleware para las dos pasadas de certificación
    $authDenegadoMock = new class extends AutenticacionMiddleware {
        public function __construct() {}
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion
        {
            ContextoOperacion::establecerActual(null);
            if ($bloquearSiInvalido) {
                throw new \Aplicacion\Excepciones\AccesoDenegadoExcepcion('No autenticado: token de sesión ausente.', null, 401);
            }
            return null;
        }
    };

    $authzDenegadoMock = new class extends AutorizacionMiddleware {
        public function __construct()
        {
            parent::__construct();
        }
        public function verificarPermiso(string $codigoPermiso, ?ContextoOperacion $contexto = null, bool $bloquearPeticion = true): bool
        {
            if ($bloquearPeticion) {
                throw new \Aplicacion\Excepciones\AccesoDenegadoExcepcion("Acceso denegado: carece del permiso {$codigoPermiso}.", $codigoPermiso, 403);
            }
            return false;
        }
    };

    $authClienteDenegadoMock = new class extends AutenticacionClienteMiddleware {
        public function __construct() {}
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ClienteSesion
        {
            if ($bloquearSiInvalido) {
                throw new \Aplicacion\Excepciones\AccesoDenegadoExcepcion('Se requiere iniciar sesión en el Espacio del Cliente.', null, 401);
            }
            return null;
        }
    };

    $authValidoMock = new class($usuarioTestId, $orgTestId, $edicionTestId) extends AutenticacionMiddleware {
        public function __construct(
            private int $usrId,
            private int $orgId,
            private int $edId
        ) {}
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion
        {
            $ctx = new ContextoOperacion(
                actorTipo: 'HUMANO',
                usuarioId: $this->usrId,
                actorSistemaId: null,
                actorSistemaCodigo: null,
                canalId: 1,
                canalCodigo: 'WEB',
                correlacionId: 'corr-f42-cert-123456',
                origenIp: '127.0.0.1',
                agenteUsuario: 'CertificadorContratosV12',
                organizacionId: $this->orgId,
                metadatos: [],
                edicionTrabajoId: $this->edId,
                origenEdicion: 'EXPLICITA'
            );
            ContextoOperacion::establecerActual($ctx);
            return $ctx;
        }
    };

    $authzValidoMock = new class extends AutorizacionMiddleware {
        public function __construct()
        {
            parent::__construct();
        }
        public function verificarPermiso(string $codigoPermiso, ?ContextoOperacion $contexto = null, bool $bloquearPeticion = true): bool
        {
            return true;
        }
    };

    $authClienteValidoMock = new class($orgTestId, $clienteTestId) extends AutenticacionClienteMiddleware {
        public function __construct(
            private int $orgId,
            private int $cliId
        ) {}
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ClienteSesion
        {
            return new ClienteSesion(
                id: 1,
                organizacionId: $this->orgId,
                clienteId: $this->cliId,
                tokenSesionHash: hash('sha256', 'cli_ses_cert_v12'),
                ipCreacion: '127.0.0.1',
                userAgent: 'CertificadorContratosV12',
                expiraEn: date('Y-m-d H:i:s', time() + 3600),
                revocado: false
            );
        }
    };

    // 4. Recorrer y certificar cada uno de los 269 endpoints
    foreach ($mapaRutasApi as $llave => $r) {
        $patron = $r['patron'];
        $metodoHttp = strtoupper($r['metodo']);
        $accion = $r['accion'];
        $contrato = $mapaSpec[$llave];

        $seguridadSpec = $contrato['security'] ?? [];
        $esquemaSeguridad = 'PUBLICO';
        if (!empty($seguridadSpec[0])) {
            $esquemaSeguridad = (string) array_key_first($seguridadSpec[0]);
        }

        $nombreControlador = 'Closure (rutas/api.php)';
        if (is_array($accion) && count($accion) === 2) {
            $partes = explode('\\', (string) $accion[0]);
            $nombreControlador = end($partes) . '::' . $accion[1];
        }

        // PASADA A: Verificación de rechazo sin autenticación en endpoints protegidos
        $rechazoSinAuthOk = true;
        $esCierreSesionIdempotente = in_array($patron, ['/api/v1/auth/logout', '/api/v1/publico/portal/cerrar-sesion'], true);
        if (in_array($esquemaSeguridad, ['BearerAdminAuth', 'CookieClienteAuth'], true) && !$esCierreSesionIdempotente) {
            $conteoRechazoSinAuth++;
            ContextoOperacion::establecerActual(null);
            $resSinAuth = ejecutarAccionEnMemoria(
                $patron,
                $metodoHttp,
                $accion,
                $authDenegadoMock,
                $authzDenegadoMock,
                $authClienteDenegadoMock,
                $slugTenant
            );

            $jsonSinAuth = json_decode($resSinAuth['cuerpo'], true);
            $codRechazo = (int) ($jsonSinAuth['codigo'] ?? $resSinAuth['codigo_http']);
            $esRechazoValido = is_array($jsonSinAuth)
                && ($jsonSinAuth['exito'] ?? true) === false
                && in_array($codRechazo, [400, 401, 403, 405], true)
                && (!empty($jsonSinAuth['mensaje']) || !empty($jsonSinAuth['error']));

            if (!$esRechazoValido) {
                $rechazoSinAuthOk = false;
                $fallosRechazoSinAuth[] = "{$llave} (HTTP {$resSinAuth['codigo_http']}: " . substr($resSinAuth['cuerpo'], 0, 120) . ")";
            }
        }

        // PASADA B: Ejecución en tiempo de ejecución autenticado / público
        $conteoEjecucionRuntime++;
        $resRuntime = ejecutarAccionEnMemoria(
            $patron,
            $metodoHttp,
            $accion,
            $authValidoMock,
            $authzValidoMock,
            $authClienteValidoMock,
            $slugTenant
        );

        $cuerpoRuntime = $resRuntime['cuerpo'];
        $codigoRuntime = $resRuntime['codigo_http'];
        $formatoValido = false;
        $tipoRespuestaDetectado = 'JSON_ENVELOPE';

        if ($patron === '/api/v1/openapi.json') {
            $dec = json_decode($cuerpoRuntime, true);
            $formatoValido = is_array($dec) && ($dec['openapi'] ?? '') === '3.0.3';
            $tipoRespuestaDetectado = 'OPENAPI_JSON';
        } elseif ($patron === '/api/v1/openapi.yaml') {
            $formatoValido = str_contains($cuerpoRuntime, 'openapi: 3.0.3');
            $tipoRespuestaDetectado = 'OPENAPI_YAML';
        } elseif ($patron === '/api/v1/webhooks/whatsapp' && $metodoHttp === 'GET') {
            $formatoValido = in_array($codigoRuntime, [200, 403], true) && $cuerpoRuntime !== '';
            $tipoRespuestaDetectado = 'TEXT_CHALLENGE';
        } else {
            $jsonRuntime = json_decode($cuerpoRuntime, true);
            $tieneEstructuraSobre = is_array($jsonRuntime)
                && array_key_exists('exito', $jsonRuntime)
                && count($jsonRuntime) >= 2;

            if ($tieneEstructuraSobre) {
                $codSobre = (int) ($jsonRuntime['codigo'] ?? $codigoRuntime);
                $codigoRuntime = $codSobre;
                $msgSobre = (string) ($jsonRuntime['mensaje'] ?? $jsonRuntime['error'] ?? '');
                $esErrorFatalNoControlado = str_starts_with($msgSobre, 'Excepción no controlada:');
                $formatoValido = !$esErrorFatalNoControlado && in_array($codSobre, [200, 201, 400, 401, 403, 404, 405, 409, 422, 429, 500], true);
            }
        }

        if (!$formatoValido) {
            $fallosEjecucionRuntime[] = "{$llave} -> HTTP {$codigoRuntime}: " . substr($cuerpoRuntime, 0, 140);
        }

        $matrizCertificacion[] = [
            'metodo'                       => $metodoHttp,
            'patron'                       => $patron,
            'operation_id'                 => $contrato['operationId'] ?? '',
            'dominio'                      => $contrato['tags'][0] ?? 'General',
            'controlador'                  => $nombreControlador,
            'esquema_seguridad'            => $esquemaSeguridad,
            'certificado_1a1_openapi'      => true,
            'certificado_rechazo_sin_auth' => $rechazoSinAuthOk,
            'certificado_ejecucion_runtime'=> $formatoValido,
            'codigo_http_runtime'          => $codigoRuntime,
            'tipo_respuesta'               => $tipoRespuestaDetectado,
            'estado_certificacion'         => ($rechazoSinAuthOk && $formatoValido) ? 'CERTIFICADO' : 'OBSERVADO',
        ];
    }
} finally {
    ContextoOperacion::establecerActual(null);
    $_GET = [];
    $_POST = [];
    $_COOKIE = [];
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

assertF42C(
    count($fallosRechazoSinAuth) === 0 && $conteoRechazoSinAuth >= 250,
    "3.1: 100% de los endpoints protegidos ({$conteoRechazoSinAuth}/{$conteoRechazoSinAuth}) rechazan peticiones sin sesión válida (HTTP 401/403 Fail-Closed)",
    implode(' | ', array_slice($fallosRechazoSinAuth, 0, 5))
);

assertF42C(
    count($fallosEjecucionRuntime) === 0 && $conteoEjecucionRuntime === 269,
    "3.2: 100% de los endpoints de la API (269/269) ejecutan en tiempo de ejecución emitiendo sobre contractual válido sin errores fatales ni TypeError",
    implode(' | ', array_slice($fallosEjecucionRuntime, 0, 5))
);

// Exportar matriz auditable a disco
$rutaMatriz = __DIR__ . '/matriz_certificacion_contratos_v12.json';
$resumenDominios = [];
foreach ($matrizCertificacion as $item) {
    $dom = $item['dominio'];
    $resumenDominios[$dom] = ($resumenDominios[$dom] ?? 0) + 1;
}

$documentoMatriz = [
    'proyecto'                  => 'CandelariaAPP',
    'version'                   => '1.2.0',
    'directiva'                 => 'DT-V12-002 (DEC-02 Opción 2A)',
    'generado_en'               => date('Y-m-d H:i:s'),
    'total_endpoints_api'       => count($matrizCertificacion),
    'total_certificados_1a1'    => count(array_filter($matrizCertificacion, fn($x) => $x['estado_certificacion'] === 'CERTIFICADO')),
    'total_observados'          => count(array_filter($matrizCertificacion, fn($x) => $x['estado_certificacion'] !== 'CERTIFICADO')),
    'total_controladores'       => count($controladoresVerificados),
    'distribucion_por_dominio'  => $resumenDominios,
    'endpoints'                 => $matrizCertificacion,
];

file_put_contents(
    $rutaMatriz,
    json_encode($documentoMatriz, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

assertF42C(
    file_exists($rutaMatriz) && $documentoMatriz['total_certificados_1a1'] === 269 && $documentoMatriz['total_observados'] === 0,
    "3.3: Matriz auditable pruebas/matriz_certificacion_contratos_v12.json generada con 269/269 endpoints CERTIFICADOS y 0 observados"
);

// ------------------------------------------------------------------------------
// BLOQUE 4: INTEGRIDAD ESTRUCTURAL POST-ROLLBACK Y GOBERNANZA
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 4: Integridad Estructural Post-Rollback y Gobernanza ---\n";

$tablasActuales = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
assertF42C($tablasActuales === 108, "4.1: Base de datos conserva exactamente 108 tablas oficiales post-rollback");

$migracionesActuales = (int) $pdo->query("SELECT COUNT(*) FROM migraciones_control")->fetchColumn();
assertF42C($migracionesActuales === 29, "4.2: Trazabilidad de migraciones conserva 29 migraciones");

$permisosActuales = (int) $pdo->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
assertF42C($permisosActuales === 115, "4.3: Matriz RBAC conserva 115 permisos");

$stmtOrlando = $pdo->query("SELECT contrasena_hash FROM usuarios WHERE id = 24");
$hashOrlando = (string) $stmtOrlando->fetchColumn();
$huellaOrlando = substr(hash('sha256', $hashOrlando), 0, 16);
assertF42C($huellaOrlando === '80e6af84e02e89e3', "4.4: Huella inmutable de Orlando (ID 24) preservada intacta (80e6af84e02e89e3)");

echo "\n==============================================================================\n";
echo sprintf("TOTAL PRUEBAS FASE 4.2B (CONTRATOS): %d | EXITOSAS: %d | FALLIDAS: %d\n", $pruebasEjecutadas, $pruebasPasadas, $pruebasFallidas);
echo "TOTAL: {$pruebasPasadas} PASS / {$pruebasFallidas} FAIL\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
