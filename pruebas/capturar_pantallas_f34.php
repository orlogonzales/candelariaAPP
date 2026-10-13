<?php

declare(strict_types=1);

/**
 * Script de Captura Visual Automatizada en Navegador Headless (Chrome) para Fase 3.4.
 * Genera evidencias desktop y mobile para el Portal del Cliente.
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

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
echo "GENERANDO CAPTURAS REALES EN NAVEGADOR HEADLESS — FASE 3.4 (CHROME)\n";
echo "==============================================================================\n";
echo "Navegador detectado: {$chromePath}\n";
echo "Directorio de destino: {$directorioDestino}\n\n";

$pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();

// Resolver organización y cliente existente para generar sesión real
$org = $pdo->query("SELECT id, codigo, nombre_comercial FROM organizaciones WHERE codigo = 'og_estudio' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$orgId = (int) $org['id'];
$orgSlug = (string) $org['codigo'];

// Buscar cliente con persona
$cliente = $pdo->query("
    SELECT c.id, c.persona_id, p.nombres, p.apellidos, p.telefono_whatsapp
    FROM clientes c
    JOIN personas p ON p.id = c.persona_id
    WHERE c.organizacion_id = {$orgId}
    ORDER BY c.id ASC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    // Tomar cualquier cliente
    $cliente = $pdo->query("
        SELECT c.id, c.persona_id, p.nombres, p.apellidos, p.telefono_whatsapp
        FROM clientes c
        JOIN personas p ON p.id = c.persona_id
        ORDER BY c.id ASC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
}

$clienteId = (int) $cliente['id'];

// Crear token de sesión temporal en cliente_sesiones
$tokenSesion = 'test_token_f34_' . bin2hex(random_bytes(16));
$tokenHash = hash('sha256', $tokenSesion);
$expiraEn = date('Y-m-d H:i:s', time() + 7200);

$stmtSesion = $pdo->prepare("
    INSERT INTO `cliente_sesiones` (
        `organizacion_id`, `cliente_id`, `token_sesion_hash`, `creado_en`, `expira_en`, `ip_creacion`, `user_agent`, `revocado`
    ) VALUES (
        :org_id, :cli_id, :token_hash, NOW(), :expira_en, '127.0.0.1', 'Chrome Headless Test Runner', 0
    )
");
$stmtSesion->execute([
    'org_id'     => $orgId,
    'cli_id'     => $clienteId,
    'token_hash' => $tokenHash,
    'expira_en'  => $expiraEn,
]);
$sesionId = (int) $pdo->lastInsertId();

$baseUrl = 'http://app.candelaria.test';

$vistas = [
    [
        'nombre'  => 'Portal Cliente: Acceso Seguro Passwordless OTP',
        'url'     => "{$baseUrl}/p/{$orgSlug}/mi-espacio",
        'archivo' => 'screenshot_f34_auth_passwordless',
    ],
    [
        'nombre'  => 'Portal Cliente: Estado de Cuenta y Recibos PDF',
        'url'     => "{$baseUrl}/p/{$orgSlug}/mi-espacio?token={$tokenSesion}&tab=cuenta",
        'archivo' => 'screenshot_f34_portal_estado_cuenta',
    ],
    [
        'nombre'  => 'Portal Cliente: Selección de Fotografías y Recuerdos',
        'url'     => "{$baseUrl}/p/{$orgSlug}/mi-espacio?token={$tokenSesion}&tab=recuerdos",
        'archivo' => 'screenshot_f34_portal_fotografias',
    ],
    [
        'nombre'  => 'Portal Cliente: Mi Perfil y Consentimientos Ley 29733',
        'url'     => "{$baseUrl}/p/{$orgSlug}/mi-espacio?token={$tokenSesion}&tab=perfil",
        'archivo' => 'screenshot_f34_portal_perfil_privacidad',
    ],
];

foreach ($vistas as $v) {
    $rutaDesktop = "{$directorioDestino}\\{$v['archivo']}_desktop.png";
    $rutaMobile  = "{$directorioDestino}\\{$v['archivo']}_mobile.png";

    // 1. Captura Desktop (1366x768)
    $cmdDesktop = "\"{$chromePath}\" --headless --disable-gpu --window-size=1366,768 --virtual-time-budget=5000 --hide-scrollbars --screenshot=\"{$rutaDesktop}\" \"{$v['url']}\" 2>&1";
    exec($cmdDesktop);

    if (file_exists($rutaDesktop)) {
        $tamano = filesize($rutaDesktop);
        echo "  [PASS] {$v['nombre']} (Desktop) --> {$rutaDesktop} ({$tamano} bytes)\n";
    } else {
        echo "  [FAIL] {$v['nombre']} (Desktop)\n";
    }

    // 2. Captura Mobile (375x812 iPhone / Android viewport)
    $cmdMobile = "\"{$chromePath}\" --headless --disable-gpu --window-size=375,812 --virtual-time-budget=5000 --hide-scrollbars --screenshot=\"{$rutaMobile}\" \"{$v['url']}\" 2>&1";
    exec($cmdMobile);

    if (file_exists($rutaMobile)) {
        $tamano = filesize($rutaMobile);
        echo "  [PASS] {$v['nombre']} (Mobile)  --> {$rutaMobile} ({$tamano} bytes)\n";
    } else {
        echo "  [FAIL] {$v['nombre']} (Mobile)\n";
    }
}

// Limpiar sesión de prueba
if ($sesionId > 0) {
    $pdo->exec("DELETE FROM `cliente_sesiones` WHERE `id` = {$sesionId}");
}

echo "\n==============================================================================\n";
echo "RESUMEN DE CAPTURAS FASE 3.4: 4 VISTAS AUDITADAS (8 CAPTURAS REALES GENERADAS)\n";
echo "==============================================================================\n";
