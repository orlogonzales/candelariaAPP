<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

require_once __DIR__ . '/../rutas/api.php';
require_once __DIR__ . '/../rutas/web.php';

$rutas = \Nucleo\Enrutamiento\Enrutador::obtenerRutas();

$apiRutas = [];
$webRutas = [];

foreach ($rutas as $r) {
    $patron = $r['patron'];
    $metodo = $r['metodo'];
    $accion = $r['accion'];
    
    $handler = 'Closure';
    if (is_array($accion)) {
        $handler = $accion[0] . '@' . $accion[1];
    } elseif (is_string($accion)) {
        $handler = $accion;
    }

    $item = [
        'metodo'  => $metodo,
        'patron'  => $patron,
        'handler' => $handler
    ];

    if (str_starts_with($patron, '/api/')) {
        $apiRutas[] = $item;
    } else {
        $webRutas[] = $item;
    }
}

function clasificarDominio(string $patron): string {
    if (str_starts_with($patron, '/api/v1/auth')) return 'Identidad y Autenticación';
    if (str_starts_with($patron, '/api/v1/usuarios')) return 'Usuarios y Seguridad';
    if (str_starts_with($patron, '/api/v1/roles') || str_starts_with($patron, '/api/v1/permisos')) return 'RBAC y Roles';
    if (str_starts_with($patron, '/api/v1/organizacion') || str_starts_with($patron, '/api/v1/ediciones') || str_starts_with($patron, '/api/v1/configuracion') || str_starts_with($patron, '/api/v1/contexto') || str_starts_with($patron, '/api/v1/tipos-documento')) return 'Organizaciones y Contexto';
    if (str_starts_with($patron, '/api/v1/personas') || str_starts_with($patron, '/api/v1/clientes')) return 'Personas y Clientes';
    if (str_starts_with($patron, '/api/v1/crm')) return 'CRM y Comercial';
    if (str_starts_with($patron, '/api/v1/ventas') || str_starts_with($patron, '/api/v1/cotizaciones') || str_starts_with($patron, '/api/v1/pagos') || str_starts_with($patron, '/api/v1/catalogo') || str_starts_with($patron, '/api/v1/cuentas-bancarias') || str_starts_with($patron, '/api/v1/pasarelas')) return 'Ventas y Finanzas';
    if (str_starts_with($patron, '/api/v1/reservas')) return 'Reservas';
    if (str_starts_with($patron, '/api/v1/operaciones/offline')) return 'Operación Móvil y QR';
    if (str_starts_with($patron, '/api/v1/operaciones')) return 'Operaciones y Salidas';
    if (str_starts_with($patron, '/api/v1/folclore')) return 'Programación Folclórica';
    if (str_starts_with($patron, '/api/v1/comunicaciones') || str_starts_with($patron, '/api/v1/comunicacion') || str_starts_with($patron, '/api/v1/webhooks/whatsapp')) return 'Comunicaciones y Consentimientos';
    if (str_starts_with($patron, '/api/v1/webhooks')) return 'Webhooks de Pago';
    if (str_starts_with($patron, '/api/v1/postproduccion')) return 'Postproducción y Galerías';
    if (str_starts_with($patron, '/api/v1/publico/portal')) return 'Portal del Cliente';
    if (str_starts_with($patron, '/api/v1/publico')) return 'Portal Público y Checkout';
    if (str_starts_with($patron, '/api/v1/documentos')) return 'Documentos y Emisión PDF';
    if (str_starts_with($patron, '/api/v1/dashboard')) return 'Dashboard Ejecutivo';
    return 'Sistema y Utilidades';
}

$clasificados = [];
foreach ($apiRutas as &$item) {
    $dom = clasificarDominio($item['patron']);
    $item['dominio'] = $dom;
    $item['es_publico_cliente'] = str_starts_with($item['patron'], '/api/v1/publico');
    $clasificados[$dom][] = $item;
}

echo "==============================================================================" . PHP_EOL;
echo "INVENTARIO GENERAL DE RUTAS API (CANDELARIAAPP V1.1)" . PHP_EOL;
echo "==============================================================================" . PHP_EOL;
echo "TOTAL RUTAS API (/api/*): " . count($apiRutas) . PHP_EOL;
$pubCount = count(array_filter($apiRutas, fn($r) => $r['es_publico_cliente']));
echo "  - Rutas Públicas / Espacio del Cliente / App Móvil: {$pubCount}" . PHP_EOL;
echo "  - Rutas Administrativas / Backoffice / Operativas: " . (count($apiRutas) - $pubCount) . PHP_EOL . PHP_EOL;

echo "DISTRIBUCIÓN EXACTA POR DOMINIO:" . PHP_EOL;
foreach ($clasificados as $dom => $lista) {
    echo sprintf("  %-35s : %2d rutas", $dom, count($lista)) . PHP_EOL;
}
echo PHP_EOL;

file_put_contents(__DIR__ . '/inventario_api_rutas.json', json_encode($apiRutas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Inventario guardado en pruebas/inventario_api_rutas.json" . PHP_EOL;
