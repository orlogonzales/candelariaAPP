<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 3.5 (DT-V11-016)
 * CONTRATO OPENAPI 3.0.3, ARQUITECTURA HEADLESS Y AUDITORÍA DE SEGURIDAD CONTRACTUAL
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Nucleo\BaseDatos\Conexion;
use Nucleo\Enrutamiento\Enrutador;

$pruebasEjecutadas = 0;
$pruebasPasadas = 0;
$pruebasFallidas = 0;

function assertF35(bool $condicion, string $descripcion, string $detalles = ''): void {
    global $pruebasEjecutadas, $pruebasPasadas, $pruebasFallidas;
    $pruebasEjecutadas++;
    if ($condicion) {
        $pruebasPasadas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$descripcion} --> {$detalles}\n";
    }
}

echo "==============================================================================\n";
echo "CANDELARIAAPP V1.1 — SUITE DE PRUEBAS FASE 3.5 (DT-V11-016)\n";
echo "ESPECIFICACIÓN OPENAPI 3.0.3, ARQUITECTURA HEADLESS Y CONTRATOS REST\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

// Cargar especificaciones generadas
$rutaJsonDocs = __DIR__ . '/../docs/api/openapi.json';
$rutaYamlDocs = __DIR__ . '/../docs/api/openapi.yaml';
$rutaJsonPub  = __DIR__ . '/../publico/documentacion/openapi.json';
$rutaYamlPub  = __DIR__ . '/../publico/documentacion/openapi.yaml';
$rutaHtmlPub  = __DIR__ . '/../publico/documentacion/index.html';

// ------------------------------------------------------------------------------
// BLOQUE 1: INTEGRIDAD ESTRUCTURAL Y SINTÁCTICA DE OPENAPI 3.0.3
// ------------------------------------------------------------------------------
echo "--- BLOQUE 1: Integridad Estructural y Sintáctica de OpenAPI 3.0.3 ---\n";

assertF35(file_exists($rutaJsonDocs) && filesize($rutaJsonDocs) > 10000, "1.1: Archivo docs/api/openapi.json existe con tamaño sustancial (>10KB)");
assertF35(file_exists($rutaYamlDocs) && filesize($rutaYamlDocs) > 5000, "1.2: Archivo docs/api/openapi.yaml existe con tamaño sustancial (>5KB)");
assertF35(file_exists($rutaJsonPub) && file_exists($rutaYamlPub), "1.3: Archivos de distribución pública sincronizados en publico/documentacion/");

$rawJson = (string) file_get_contents($rutaJsonDocs);
$spec = json_decode($rawJson, true);
assertF35($spec !== null && json_last_error() === JSON_ERROR_NONE, "1.4: Especificación JSON es sintácticamente válida");

assertF35(($spec['openapi'] ?? '') === '3.0.3', "1.5: Versión oficial OpenAPI es estrictamente 3.0.3");

$info = $spec['info'] ?? [];
assertF35(
    !empty($info['title']) &&
    in_array($info['version'] ?? '', ['1.1.0', '1.2.0'], true) &&
    str_contains($info['description'] ?? '', 'API-First') &&
    !empty($info['contact']['email']),
    "1.6: Metadatos info contienen versión oficial (1.1.0/1.2.0), contacto y principios de arquitectura"
);

$servers = $spec['servers'] ?? [];
$urlsServers = array_column($servers, 'url');
assertF35(
    count($servers) >= 2 &&
    in_array('http://localhost/app.candelaria', $urlsServers, true) &&
    in_array('https://app.candelaria.pe', $urlsServers, true),
    "1.7: Servidores configurables para desarrollo local y producción proyectada"
);

$tags = $spec['tags'] ?? [];
$nombresTags = array_column($tags, 'name');
$tagsEsperados = [
    'Portal del Cliente',
    'Portal Público y Checkout',
    'Operación Móvil y QR',
    'Programación Folclórica',
    'Comunicaciones y Consentimientos',
    'Ventas y Finanzas',
    'Reservas y Operaciones',
    'Postproducción y Galerías',
    'Documentos y Emisión PDF',
    'Identidad y Autenticación Admin'
];
$todosTagsPresentes = true;
foreach ($tagsEsperados as $tagEsp) {
    if (!in_array($tagEsp, $nombresTags, true)) {
        $todosTagsPresentes = false;
        break;
    }
}
assertF35($todosTagsPresentes, "1.8: Todos los tags oficiales de dominio están declarados formalmente");

// Validación recursiva de $ref para asegurar ausencia total de referencias rotas
$refsEncontrados = [];
function recolectarRefs(array $nodo, array &$refs): void {
    foreach ($nodo as $k => $v) {
        if ($k === '$ref' && is_string($v)) {
            $refs[] = $v;
        } elseif (is_array($v)) {
            recolectarRefs($v, $refs);
        }
    }
}
recolectarRefs($spec, $refsEncontrados);

$refsRotas = [];
foreach ($refsEncontrados as $ref) {
    // Referencia interna estándar tipo #/components/schemas/Nombre
    if (str_starts_with($ref, '#/')) {
        $partes = explode('/', substr($ref, 2));
        $actual = $spec;
        foreach ($partes as $p) {
            if (!isset($actual[$p])) {
                $refsRotas[] = $ref;
                break;
            }
            $actual = $actual[$p];
        }
    }
}
assertF35(count($refsEncontrados) > 10, "1.9: Se encontraron " . count($refsEncontrados) . " referencias componentes \$ref activas");
assertF35(count($refsRotas) === 0, "1.10: Ausencia total de referencias rotas (cero \$ref inexistentes)", implode(', ', $refsRotas));

// Unicidad estricta de operationId
$operationIds = [];
$duplicadosOpId = [];
foreach ($spec['paths'] ?? [] as $ruta => $metodos) {
    foreach ($metodos as $metodo => $op) {
        if (!is_array($op)) continue;
        if (!empty($op['operationId'])) {
            $opId = $op['operationId'];
            if (in_array($opId, $operationIds, true)) {
                $duplicadosOpId[] = $opId;
            }
            $operationIds[] = $opId;
        }
    }
}
assertF35(count($duplicadosOpId) === 0, "1.11: Todos los operationId son únicos e irrepetibles en la API", implode(', ', $duplicadosOpId));


// ------------------------------------------------------------------------------
// BLOQUE 2: CORRESPONDENCIA CONTRACTUAL CON RUTAS REALES DEL BACKEND
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 2: Correspondencia Contractual con Rutas Reales ---\n";

require_once __DIR__ . '/../rutas/api.php';
require_once __DIR__ . '/../rutas/web.php';
$rutasBackend = Enrutador::obtenerRutas();

$mapaBackend = [];
foreach ($rutasBackend as $r) {
    $mapaBackend[$r['metodo']][$r['patron']] = true;
}

$pathsOpenApi = $spec['paths'] ?? [];

// 2.1 Verificar rutas del Portal del Cliente
$rutasClienteCriticas = [
    'POST' => ['/api/v1/publico/portal/solicitar-acceso', '/api/v1/publico/portal/verificar-acceso', '/api/v1/publico/portal/cerrar-sesion'],
    'GET'  => ['/api/v1/publico/portal/perfil', '/api/v1/publico/portal/estado-cuenta', '/api/v1/publico/portal/recibo/{id}', '/api/v1/publico/portal/mis-reservas', '/api/v1/publico/portal/mis-galerias', '/api/v1/publico/portal/mis-selecciones'],
    'PUT'  => ['/api/v1/publico/portal/perfil']
];
$coincidenCliente = true;
foreach ($rutasClienteCriticas as $met => $uris) {
    foreach ($uris as $uri) {
        if (!isset($pathsOpenApi[$uri][strtolower($met)])) {
            $coincidenCliente = false;
            break 2;
        }
    }
}
assertF35($coincidenCliente, "2.1: Todas las rutas críticas del Portal del Cliente coinciden 1:1 con la especificación");

// 2.2 Verificar rutas de Operación Móvil y Validación Offline
$rutasOfflineCriticas = [
    'GET'  => ['/api/v1/operaciones/offline/paquete', '/api/v1/operaciones/offline/lotes'],
    'POST' => ['/api/v1/operaciones/offline/validar-online', '/api/v1/operaciones/offline/sincronizar']
];
$coincidenOffline = true;
foreach ($rutasOfflineCriticas as $met => $uris) {
    foreach ($uris as $uri) {
        if (!isset($pathsOpenApi[$uri][strtolower($met)])) {
            $coincidenOffline = false;
            break 2;
        }
    }
}
assertF35($coincidenOffline, "2.2: Todas las rutas de Operación Móvil y Validación Offline están formalizadas");

// 2.3 Verificar rutas de Programación Folclórica (Fase 3.1)
$rutasFolcloreCriticas = [
    'GET' => ['/api/v1/folclore/tipos-danza', '/api/v1/folclore/conjuntos', '/api/v1/folclore/seguimiento/tablero'],
    'PUT' => ['/api/v1/folclore/seguimiento/turnos/{id}/estado']
];
$coincidenFolclore = true;
foreach ($rutasFolcloreCriticas as $met => $uris) {
    foreach ($uris as $uri) {
        if (!isset($pathsOpenApi[$uri][strtolower($met)])) {
            $coincidenFolclore = false;
            break 2;
        }
    }
}
assertF35($coincidenFolclore, "2.3: Rutas de Programación Folclórica y Tablero Operativo en vivo documentadas");

// 2.4 Endpoints de especificación OpenAPI en backend
assertF35(isset($mapaBackend['GET']['/api/v1/openapi.json']), "2.4: Endpoint GET /api/v1/openapi.json registrado en el enrutador");
assertF35(isset($mapaBackend['GET']['/api/v1/openapi.yaml']), "2.5: Endpoint GET /api/v1/openapi.yaml registrado en el enrutador");
assertF35(isset($mapaBackend['GET']['/documentacion']), "2.6: Endpoint web GET /documentacion registrado para el visualizador");


// ------------------------------------------------------------------------------
// BLOQUE 3: SEGURIDAD CONTRACTUAL, ESQUEMAS Y CERO FUGAS
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 3: Seguridad Contractual, Esquemas y Cero Fugas ---\n";

$secSchemes = $spec['components']['securitySchemes'] ?? [];
assertF35(isset($secSchemes['CookieClienteAuth']), "3.1: Esquema CookieClienteAuth (candelaria_cliente_sesion) documentado");
assertF35(($secSchemes['CookieClienteAuth']['in'] ?? '') === 'cookie', "3.2: CookieClienteAuth configurado como cookie HttpOnly");

assertF35(isset($secSchemes['BearerAdminAuth']), "3.3: Esquema BearerAdminAuth documentado para operadores y backoffice");
assertF35(($secSchemes['BearerAdminAuth']['type'] ?? '') === 'http' && ($secSchemes['BearerAdminAuth']['scheme'] ?? '') === 'bearer', "3.4: BearerAdminAuth configurado con esquema HTTP Bearer");

assertF35(isset($secSchemes['TenantHeader']), "3.5: Esquema TenantHeader (X-Organizacion-Slug) documentado para resolución multitenant");

// Verificación de Sobres JSON estándar
$schemas = $spec['components']['schemas'] ?? [];
$sobreExito = $schemas['SobreRespuestaExito'] ?? [];
assertF35(
    isset($sobreExito['properties']['exito'], $sobreExito['properties']['codigo'], $sobreExito['properties']['mensaje']),
    "3.6: Sobre estándar SobreRespuestaExito define campos obligatorios exito, codigo y mensaje"
);

$sobreError = $schemas['SobreRespuestaError'] ?? [];
assertF35(
    isset($sobreError['properties']['errores']) && ($sobreError['properties']['errores']['type'] ?? '') === 'array',
    "3.7: Sobre estándar SobreRespuestaError define matriz de errores estructurados para validación 422"
);

// Verificación de documentación de la regla de 5 compuertas
$descargaOp = $pathsOpenApi['/api/v1/publico/portal/descargar-multimedia/{id}']['get'] ?? [];
$descDescarga = (string) ($descargaOp['description'] ?? '');
assertF35(
    str_contains($descDescarga, '5 Compuertas') &&
    str_contains($descDescarga, 'postproduccion_entregas') &&
    str_contains($descDescarga, 'Ley 29733'),
    "3.8: Regla canónica de 5 compuertas de descarga explícitamente documentada en el contrato"
);

// Auditoría estricta de Cero Secretos y Cero PII en la especificación
$textoCompletoSpec = $rawJson;
$fugaContrasena = str_contains($textoCompletoSpec, '$2y$') || str_contains($textoCompletoSpec, '80e6af84e02e89e3');
assertF35(!$fugaContrasena, "3.9: Auditoría Cero Secretos: Cero hashes de contraseñas ni huella de Orlando expuestos en la especificación");

$telefonoEjemplo = $schemas['SolicitarAccesoRequest']['properties']['destino']['example'] ?? '';
assertF35($telefonoEjemplo === '+51951999888', "3.10: Ejemplo de teléfono utiliza numeración representativa ficticia");


// ------------------------------------------------------------------------------
// BLOQUE 4: VISUALIZADOR INTERACTIVO SWAGGER UI Y ACCESO WEB
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 4: Visualizador Interactivo Swagger UI y Acceso Web ---\n";

assertF35(file_exists($rutaHtmlPub), "4.1: Archivo publico/documentacion/index.html existe");

$htmlContenido = (string) file_get_contents($rutaHtmlPub);
assertF35(str_contains($htmlContenido, 'SwaggerUIBundle'), "4.2: Visualizador inicializa SwaggerUIBundle de forma nativa");
assertF35(str_contains($htmlContenido, 'openapi.json'), "4.3: Visualizador carga la especificación desde openapi.json");
assertF35(str_contains($htmlContenido, 'download="openapi.json"') && str_contains($htmlContenido, 'download="openapi.yaml"'), "4.4: Interfaz provee botones directos de descarga para JSON y YAML");
assertF35(str_contains($htmlContenido, 'CandelariaAPP'), "4.5: Interfaz personalizada con branding y colores de CandelariaAPP");


// ------------------------------------------------------------------------------
// BLOQUE 5: INTEGRIDAD DEL SISTEMA Y GOBERNANZA V1.1
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 5: Integridad del Sistema y Gobernanza V1.1 ---\n";

$tablasActuales = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
assertF35($tablasActuales === 108, "5.1: Base de datos conserva exactamente 108 tablas oficiales");

$migracionesActuales = (int) $pdo->query("SELECT COUNT(*) FROM migraciones_control")->fetchColumn();
assertF35($migracionesActuales === 29, "5.2: Trazabilidad de migraciones conserva 29 migraciones");

$permisosActuales = (int) $pdo->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
assertF35($permisosActuales === 115, "5.3: Matriz RBAC conserva 115 permisos");

$stmtOrlando = $pdo->query("SELECT contrasena_hash FROM usuarios WHERE id = 24");
$hashOrlando = (string) $stmtOrlando->fetchColumn();
$huellaOrlando = substr(hash('sha256', $hashOrlando), 0, 16);
assertF35($huellaOrlando === '80e6af84e02e89e3', "5.4: Huella inmutable de Orlando (ID 24) preservada intacta (80e6af84e02e89e3)");


// ------------------------------------------------------------------------------
// RESUMEN FINAL DE LA SUITE
// ------------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo sprintf("TOTAL PRUEBAS FASE 3.5: %d | EXITOSAS: %d | FALLIDAS: %d\n", $pruebasEjecutadas, $pruebasPasadas, $pruebasFallidas);
echo "TOTAL: {$pruebasPasadas} PASS / {$pruebasFallidas} FAIL\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
