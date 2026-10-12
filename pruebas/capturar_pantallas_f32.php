<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Nucleo\BaseDatos\Conexion;
use Aplicacion\Repositorios\SesionRepositorio;
use Nucleo\Seguridad\ProtectorCsrf;

$pdo = Conexion::obtenerInstancia();
$sesionRepo = new SesionRepositorio($pdo);

// 1. Crear sesión activa para Orlando Superadmin (ID 24)
$tokenPlano = bin2hex(random_bytes(32));
$csrfToken = ProtectorCsrf::generarToken();
$cargaUtil = [
    'iniciado_en'         => date('Y-m-d H:i:s'),
    'creado_en_timestamp' => time(),
    'nombre_usuario'      => 'orlando_superadmin',
    'csrf_token'          => $csrfToken,
];

$sesion = $sesionRepo->crear($tokenPlano, 24, '127.0.0.1', 'Mozilla/5.0 ChromeAuditor/1.0', $cargaUtil);

$chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
if (!file_exists($chromePath)) {
    $chromePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
}

$destDir = 'C:\\Users\\orl55\\.gemini\\antigravity\\brain\\541c09d8-c325-44a4-b32d-01ad2625a4c4';
if (!is_dir($destDir)) {
    mkdir($destDir, 0777, true);
}

$baseUrl = "http://app.candelaria.test/comunicaciones?token_sesion={$tokenPlano}";

$capturas = [
    [
        'nombre'      => 'screenshot_f32_tablero_automatizaciones',
        'url'         => $baseUrl . '&tab=automatizaciones',
        'descripcion' => 'Pestaña 7: Tablero de Reglas de Automatización y Disparadores WhatsApp'
    ],
    [
        'nombre'      => 'screenshot_f32_modal_nueva_regla',
        'url'         => $baseUrl . '&tab=automatizaciones&modal=automatizacion',
        'descripcion' => 'Modal Alina: Configuración de Nueva Regla de Automatización y Mapeo'
    ],
    [
        'nombre'      => 'screenshot_f32_modal_simulador_preflight',
        'url'         => $baseUrl . '&tab=automatizaciones&modal=probar',
        'descripcion' => 'Modal Alina: Simulación Pre-flight de Automatización y Verificación'
    ],
];

echo "==============================================================================\n";
echo "GENERANDO CAPTURAS REALES EN NAVEGADOR HEADLESS — FASE 3.2 (CHROME)\n";
echo "==============================================================================\n";
echo "Navegador detectado: {$chromePath}\n";
echo "Directorio de destino: {$destDir}\n\n";

$resultados = [];

foreach ($capturas as $item) {
    // 1. Versión Desktop (1366x850)
    $archivoDesktop = $destDir . DIRECTORY_SEPARATOR . $item['nombre'] . '_desktop.png';
    $cmdDesktop = "\"{$chromePath}\" --headless --disable-gpu --run-all-compositor-stages-before-draw --virtual-time-budget=4000 --window-size=1366,850 --screenshot=\"{$archivoDesktop}\" \"{$item['url']}\" 2>&1";
    exec($cmdDesktop, $outDesktop, $retDesktop);

    $sizeDesktop = file_exists($archivoDesktop) ? filesize($archivoDesktop) : 0;
    $okDesktop = $sizeDesktop > 5000;
    echo "  [" . ($okDesktop ? "PASS" : "FAIL") . "] {$item['descripcion']} (Desktop) --> {$archivoDesktop} ({$sizeDesktop} bytes)\n";

    // 2. Versión Mobile (375x812 iPhone / Android)
    $archivoMobile = $destDir . DIRECTORY_SEPARATOR . $item['nombre'] . '_mobile.png';
    $uaMobile = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1';
    $cmdMobile = "\"{$chromePath}\" --headless --disable-gpu --run-all-compositor-stages-before-draw --virtual-time-budget=4000 --window-size=375,812 --user-agent=\"{$uaMobile}\" --screenshot=\"{$archivoMobile}\" \"{$item['url']}\" 2>&1";
    exec($cmdMobile, $outMobile, $retMobile);

    $sizeMobile = file_exists($archivoMobile) ? filesize($archivoMobile) : 0;
    $okMobile = $sizeMobile > 5000;
    echo "  [" . ($okMobile ? "PASS" : "FAIL") . "] {$item['descripcion']} (Mobile)  --> {$archivoMobile} ({$sizeMobile} bytes)\n";

    $resultados[] = [
        'nombre'     => $item['nombre'],
        'desktop'    => $okDesktop,
        'mobile'     => $okMobile,
        'size_desk'  => $sizeDesktop,
        'size_mob'   => $sizeMobile
    ];
}

echo "\n==============================================================================\n";
echo "RESUMEN DE CAPTURAS: " . count($resultados) . " VISTAS AUDITADAS\n";
echo "==============================================================================\n";
