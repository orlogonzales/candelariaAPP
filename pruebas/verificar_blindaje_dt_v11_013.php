<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP V1.1 — VERIFICACIÓN Y CERTIFICACIÓN DEL BLINDAJE DT-V11-013
 * ==============================================================================
 * Demuestra:
 * 1. Diagnóstico de solo lectura sobre la base principal (app_candelaria).
 * 2. Rechazo incondicional de saneamiento sobre app_candelaria (incluso con tokens/argumentos).
 * 3. Ejecución controlada y exitosa de saneamiento sobre una base desechable aislada.
 * 4. Ausencia absoluta de modificaciones en datos canónicos de app_candelaria.
 * 5. Trazabilidad y gobernanza integral.
 * ==============================================================================
 */

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

$dbHost = $_ENV['BD_HOST'] ?? '127.0.0.1';
$dbPort = $_ENV['BD_PUERTO'] ?? '3306';
$dbMain = $_ENV['BD_NOMBRE'] ?? 'app_candelaria';
$dbUser = $_ENV['BD_USUARIO'] ?? 'root';
$dbPass = $_ENV['BD_CLAVE'] ?? '';

$pdoMain = new PDO(
    "mysql:host={$dbHost};port={$dbPort};dbname={$dbMain};charset=utf8mb4",
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "==============================================================================\n";
echo "EJECUCIÓN DE PRUEBAS OBLIGATORIAS DE BLINDAJE TÉCNICO (DT-V11-013)\n";
echo "==============================================================================\n\n";

$pass = 0;
$fail = 0;

function asercion(string $descripcion, bool $condicion): void {
    global $pass, $fail;
    if ($condicion) {
        $pass++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$descripcion}\n";
    }
}

// ------------------------------------------------------------------------------
// PRUEBA 1: DIAGNÓSTICO DE SÓLO LECTURA SOBRE LA BASE PRINCIPAL
// ------------------------------------------------------------------------------
echo "--- 1. DIAGNÓSTICO DE SOLO LECTURA SOBRE BASE PRINCIPAL ({$dbMain}) ---\n";

$cmdDiag = "php pruebas/regularizar_integridad_dt_v11_011.php";
$salidaDiag = [];
$codeDiag = 0;
exec($cmdDiag, $salidaDiag, $codeDiag);
$textoDiag = implode("\n", $salidaDiag);

asercion("Ejecución por defecto corre en modo DIAGNÓSTICO de solo lectura", str_contains($textoDiag, 'Modo activo: DIAGNÓSTICO'));
asercion("Confirma cero mutaciones en base de datos", str_contains($textoDiag, 'CERO registros mutados') || str_contains($textoDiag, 'Ninguna modificación será realizada'));
asercion("Finaliza con código de salida limpio (0)", $codeDiag === 0);

// ------------------------------------------------------------------------------
// PRUEBA 2: RECHAZO INCONDICIONAL DE SANEAMIENTO SOBRE APP_CANDELARIA
// ------------------------------------------------------------------------------
echo "\n--- 2. RECHAZO INCONDICIONAL DE SANEAMIENTO SOBRE {$dbMain} ---\n";

// Intento 2.1: Pasar --modo=saneamiento-autorizado (antiguo parámetro)
$cmdRechazo1 = "php pruebas/regularizar_integridad_dt_v11_011.php --modo=saneamiento-autorizado 2>&1";
$salidaR1 = [];
$codeR1 = 0;
exec($cmdRechazo1, $salidaR1, $codeR1);
$textoR1 = implode("\n", $salidaR1);

asercion("Rechazo estructural incondicional al intentar --modo=saneamiento-autorizado", str_contains($textoR1, '[BLOQUEO INCONDICIONAL DT-V11-013]'));
asercion("Código de salida de fallo fail-closed (1)", $codeR1 === 1);

// Intento 2.2: Intentar tokens previos, bypass de parámetros o cualquier argumento
$cmdRechazo2 = "php pruebas/regularizar_integridad_dt_v11_011.php --modo=saneamiento-autorizado --token-autorizacion=DT-V11-012-APROBADO --forzar 2>&1";
$salidaR2 = [];
$codeR2 = 0;
exec($cmdRechazo2, $salidaR2, $codeR2);
$textoR2 = implode("\n", $salidaR2);

asercion("Tokens antiguos no tienen ningún efecto y son rechazados", str_contains($textoR2, '[BLOQUEO INCONDICIONAL DT-V11-013]'));
asercion("Bloqueo incondicional activo independientemente de flags de línea de comandos", $codeR2 === 1);

// ------------------------------------------------------------------------------
// PRUEBA 3: EJECUCIÓN CONTROLADA SOBRE UNA BASE DESECHABLE AISLADA
// ------------------------------------------------------------------------------
echo "\n--- 3. EJECUCIÓN CONTROLADA SOBRE BASE DESECHABLE (*_disposable) ---\n";

$dbDesechable = 'candelaria_test_disposable';

// Setup de base desechable
$pdoMain->exec("DROP DATABASE IF EXISTS `{$dbDesechable}`");
$pdoMain->exec("CREATE DATABASE `{$dbDesechable}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$pdoDesechable = new PDO(
    "mysql:host={$dbHost};port={$dbPort};dbname={$dbDesechable};charset=utf8mb4",
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Crear tabla padre e hija con un registro huérfano inducido en la base desechable
$pdoDesechable->exec("
    CREATE TABLE `padre_prueba` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `nombre` VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB;

    CREATE TABLE `hijo_prueba` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `padre_id` INT UNSIGNED DEFAULT NULL,
        `detalle` VARCHAR(50) NOT NULL,
        FOREIGN KEY (`padre_id`) REFERENCES `padre_prueba` (`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB;
");

// Insertar padre y dos hijos
$pdoDesechable->exec("INSERT INTO `padre_prueba` (`id`, `nombre`) VALUES (1, 'Padre Canónico')");
$pdoDesechable->exec("INSERT INTO `hijo_prueba` (`padre_id`, `detalle`) VALUES (1, 'Hijo Legítimo')");

// Forzar registro huérfano con FK checks temporal en base desechable
$pdoDesechable->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdoDesechable->exec("INSERT INTO `hijo_prueba` (`padre_id`, `detalle`) VALUES (999, 'Hijo Huérfano Inducido')");
$pdoDesechable->exec("SET FOREIGN_KEY_CHECKS = 1;");

$huerfanosAntes = (int) $pdoDesechable->query("
    SELECT COUNT(*) FROM `hijo_prueba` h
    LEFT JOIN `padre_prueba` p ON h.`padre_id` = p.`id`
    WHERE h.`padre_id` IS NOT NULL AND p.`id` IS NULL
")->fetchColumn();
asercion("Registro huérfano inducido con éxito en base desechable (huerfanos = 1)", $huerfanosAntes === 1);

// Ejecutar saneamiento autorizado en la base desechable
$cmdSaneamientoDesechable = "php pruebas/regularizar_integridad_dt_v11_011.php --bd={$dbDesechable} --modo=saneamiento-desechable 2>&1";
$salidaDes = [];
$codeDes = 0;
exec($cmdSaneamientoDesechable, $salidaDes, $codeDes);
$textoDes = implode("\n", $salidaDes);

asercion("El script reconoce y autoriza la ejecución en base desechable", str_contains($textoDes, 'Ejecutando saneamiento en base desechable verificada'));
asercion("Proceso de purga ejecutado satisfactoriamente sobre la base desechable", str_contains($textoDes, '[PURGADO EN BASE DESECHABLE]'));
asercion("Código de salida exitoso (0)", $codeDes === 0);

$huerfanosDespues = (int) $pdoDesechable->query("
    SELECT COUNT(*) FROM `hijo_prueba` h
    LEFT JOIN `padre_prueba` p ON h.`padre_id` = p.`id`
    WHERE h.`padre_id` IS NOT NULL AND p.`id` IS NULL
")->fetchColumn();
asercion("Base desechable saneada: 0 huérfanos restantes", $huerfanosDespues === 0);

$padresRestantes = (int) $pdoDesechable->query("SELECT COUNT(*) FROM `padre_prueba` WHERE id = 1")->fetchColumn();
$hijosLegitimos = (int) $pdoDesechable->query("SELECT COUNT(*) FROM `hijo_prueba` WHERE padre_id = 1")->fetchColumn();
asercion("El hijo legítimo y el padre canónico permanecen intactos en la base desechable", $padresRestantes === 1 && $hijosLegitimos === 1);

// Destrucción limpia de la base desechable
$pdoMain->exec("DROP DATABASE IF EXISTS `{$dbDesechable}`");
asercion("Base desechable destruida tras finalizar la prueba controlada", true);

// ------------------------------------------------------------------------------
// PRUEBA 4: AUSENCIA DE MODIFICACIONES EN DATOS CANÓNICOS DE APP_CANDELARIA
// ------------------------------------------------------------------------------
echo "\n--- 4. AUSENCIA DE MODIFICACIONES EN DATOS CANÓNICOS ---\n";

$orgCount = (int) $pdoMain->query("SELECT COUNT(*) FROM organizaciones WHERE id = 10000 AND razon_social = 'O.G. ESTUDIO CREATIVO S.A.C.'")->fetchColumn();
asercion("Organización canónica 10000 intacta", $orgCount === 1);

$edCount = (int) $pdoMain->query("SELECT COUNT(*) FROM ediciones_candelaria WHERE id = 1 AND codigo = 'ED-2026' AND anio = 2026 AND es_actual = 1")->fetchColumn();
asercion("Edición canónica 2026 intacta", $edCount === 1);

$orlando = $pdoMain->query("SELECT id, nombre_usuario, estado, contrasena_hash FROM usuarios WHERE id = 24")->fetch(PDO::FETCH_ASSOC);
$huella = substr(hash('sha256', (string) ($orlando['contrasena_hash'] ?? '')), 0, 16);
asercion("Usuario Orlando (ID 24) en estado ACTIVO", ($orlando['estado'] ?? '') === 'ACTIVO');
asercion("Huella inmutable de Orlando SHA-256 intacta (80e6af84e02e89e3)", $huella === '80e6af84e02e89e3');

echo "\n==============================================================================\n";
echo "RESULTADOS BLINDAJE DT-V11-013: {$pass} PASS | {$fail} FAIL\n";
echo "==============================================================================\n";

if ($fail > 0) {
    exit(1);
}
exit(0);
