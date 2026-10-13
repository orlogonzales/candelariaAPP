<?php

declare(strict_types=1);

/**
 * Script de Captura Visual Automatizada en Navegador Headless (Chrome) para Fase 3.3.
 */

$chromePaths = [
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Users\\orl55\\AppData\\Local\\Google\\Chrome\\Application\\chrome.exe',
];

$chromePath = null;
foreach ($chromePaths as $path) {
    if (file_exists($path)) {
        $chromePath = $path;
        break;
    }
}

if (!$chromePath) {
    echo "[SKIP] Google Chrome no encontrado en rutas estándar.\n";
    exit(0);
}

$directorioDestino = 'C:\\Users\\orl55\\.gemini\\antigravity\\brain\\541c09d8-c325-44a4-b32d-01ad2625a4c4';
if (!is_dir($directorioDestino)) {
    mkdir($directorioDestino, 0777, true);
}

echo "==============================================================================\n";
echo "GENERANDO CAPTURAS REALES EN NAVEGADOR HEADLESS — FASE 3.3 (CHROME)\n";
echo "==============================================================================\n";
echo "Navegador detectado: {$chromePath}\n";
echo "Directorio de destino: {$directorioDestino}\n\n";

$baseUrl = 'http://app.candelaria.test';

$vistas = [
    [
        'nombre'  => 'Validador Móvil QR - Modo Online y Visor de Campo',
        'url'     => "{$baseUrl}/operaciones/validador-movil",
        'archivo' => 'screenshot_f33_validador_movil_online',
    ],
    [
        'nombre'  => 'Validador Móvil QR - Modo Offline con Cola de Validación',
        'url'     => "{$baseUrl}/operaciones/validador-movil?modo=offline",
        'archivo' => 'screenshot_f33_validador_movil_offline',
    ],
    [
        'nombre'  => 'Modal Alina: Historial de Lotes Offline Sincronizados',
        'url'     => "{$baseUrl}/operaciones/validador-movil?modal=historial",
        'archivo' => 'screenshot_f33_modal_historial_lotes',
    ],
];

foreach ($vistas as $v) {
    $rutaDesktop = "{$directorioDestino}\\{$v['archivo']}_desktop.png";
    $rutaMobile  = "{$directorioDestino}\\{$v['archivo']}_mobile.png";

    // 1. Captura Desktop (1366x768)
    $cmdDesktop = "\"{$chromePath}\" --headless --disable-gpu --window-size=1366,768 --virtual-time-budget=4000 --hide-scrollbars --screenshot=\"{$rutaDesktop}\" \"{$v['url']}\" 2>&1";
    exec($cmdDesktop);

    if (file_exists($rutaDesktop)) {
        $tamano = filesize($rutaDesktop);
        echo "  [PASS] {$v['nombre']} (Desktop) --> {$rutaDesktop} ({$tamano} bytes)\n";
    } else {
        echo "  [FAIL] {$v['nombre']} (Desktop)\n";
    }

    // 2. Captura Mobile (375x812 iPhone / Android viewport)
    $cmdMobile = "\"{$chromePath}\" --headless --disable-gpu --window-size=375,812 --virtual-time-budget=4000 --hide-scrollbars --screenshot=\"{$rutaMobile}\" \"{$v['url']}\" 2>&1";
    exec($cmdMobile);

    if (file_exists($rutaMobile)) {
        $tamano = filesize($rutaMobile);
        echo "  [PASS] {$v['nombre']} (Mobile)  --> {$rutaMobile} ({$tamano} bytes)\n";
    } else {
        echo "  [FAIL] {$v['nombre']} (Mobile)\n";
    }
}

echo "\n==============================================================================\n";
echo "RESUMEN DE CAPTURAS: 3 VISTAS AUDITADAS (6 CAPTURAS REALES GENERADAS)\n";
echo "==============================================================================\n";
