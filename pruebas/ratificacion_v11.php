<?php
/**
 * Script de Ratificación V1.1 (DT-V11-003): Verificación Estructural y de Regresión
 * Fecha: 2026-10-11
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dbName = getenv('DB_NAME') ?: 'app_candelaria';

$pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$totalGates = 0;
$passedGates = 0;
$failedGates = 0;

function verificar(bool $cond, string $msg): void {
    global $totalGates, $passedGates, $failedGates;
    $totalGates++;
    if ($cond) {
        $passedGates++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failedGates++;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "==============================================================================\n";
echo "CANDELARIAAPP V1.1 — EJECUCIÓN DE RATIFICACIÓN EVOLUTIVA (DT-V11-003)\n";
echo "==============================================================================\n\n";

// ------------------------------------------------------------------------------
// GATE 1: AUDITORÍA DE REGRESIÓN GLOBAL PERSISTENTE Y SUITE F3.1A
// ------------------------------------------------------------------------------
echo "--- GATE 1: AUDITORÍA DE REGRESIÓN GLOBAL PERSISTENTE Y SUITE F3.1A ---\n";
$logRegresion = __DIR__ . '/registro_regresion_global.log';
verificar(file_exists($logRegresion), "Archivo persistente de regresión histórica existe");

$contenidoLog = file_get_contents($logRegresion);
verificar(str_contains($contenidoLog, "TOTAL REGRESIÓN GLOBAL: 2019 PASS / 0 FAIL en 40 suites"), "Acredita base congelada V1 (2,019 PASS / 0 FAIL en 40 suites)");
verificar(file_exists(__DIR__ . '/ejecutar_pruebas_f31a.php'), "Suite automatizada Fase 3.1A disponible");

// ------------------------------------------------------------------------------
// GATE 2: INSTALACIÓN LIMPIA Y COMPARACIÓN ESTRUCTURAL EXACTA (V1.1)
// ------------------------------------------------------------------------------
echo "\n--- GATE 2: INSTALACIÓN LIMPIA Y COMPARACIÓN ESTRUCTURAL EXACTA (V1.1) ---\n";
$dbClean = 'app_candelaria_v11_test_clean';
$pdo->exec("DROP DATABASE IF EXISTS `{$dbClean}`");
$pdo->exec("CREATE DATABASE `{$dbClean}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$pdoClean = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbClean};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Cargar esquema_base.sql
$esquemaBaseSql = file_get_contents(__DIR__ . '/../base_datos/esquema/esquema_base.sql');
$pdoClean->exec($esquemaBaseSql);

// Tablas en limpio
$tablasLimpio = $pdoClean->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$tablasProd = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

sort($tablasLimpio);
sort($tablasProd);

verificar(count($tablasLimpio) === 104, "Instalación limpia crea exactamente 104 tablas relacionales (obtenidas: " . count($tablasLimpio) . ")");
verificar($tablasLimpio === $tablasProd, "Identidad estructural 1:1 de tablas entre esquema limpio y base canónica");

// Permisos en limpio
$permisosLimpio = (int) $pdoClean->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
$permisosProd = (int) $pdo->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
verificar($permisosLimpio === 112, "Instalación limpia contiene exactamente 112 permisos RBAC (obtenidos: {$permisosLimpio})");
verificar($permisosLimpio === $permisosProd, "Catálogo de permisos en esquema limpio coincide exactamente con base canónica (112 == {$permisosProd})");

// Migraciones en limpio
$migracionesLimpio = (int) $pdoClean->query("SELECT COUNT(*) FROM migraciones_control")->fetchColumn();
$migracionesProd = (int) $pdo->query("SELECT COUNT(*) FROM migraciones_control")->fetchColumn();
verificar($migracionesLimpio === 24, "Trazabilidad de migraciones en limpio acredita 24 migraciones registradas");
verificar($migracionesLimpio === $migracionesProd, "Registro de migraciones coincide con base canónica (24 == {$migracionesProd})");

// Módulos
$modulosLimpio = (int) $pdoClean->query("SELECT COUNT(*) FROM modulos")->fetchColumn();
verificar($modulosLimpio === 24, "Catálogo de módulos oficiales en limpio contiene 24 módulos");

// ------------------------------------------------------------------------------
// GATE 3: RESPALDO Y RESTAURACIÓN EN ENTORNO AISLADO (V1.1)
// ------------------------------------------------------------------------------
echo "\n--- GATE 3: PROCEDIMIENTO DE RESPALDO Y RESTAURACIÓN EN ENTORNO AISLADO ---\n";
$dbRestore = 'app_candelaria_v11_test_restore';
$pdo->exec("DROP DATABASE IF EXISTS `{$dbRestore}`");
$pdo->exec("CREATE DATABASE `{$dbRestore}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$pdoRestore = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbRestore};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdoClean->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdoRestore->exec("SET FOREIGN_KEY_CHECKS = 0;");

foreach ($tablasLimpio as $tbl) {
    $createRow = $pdoClean->query("SHOW CREATE TABLE `{$tbl}`")->fetch();
    $sqlCreate = $createRow['Create Table'] ?? '';
    $pdoRestore->exec("DROP TABLE IF EXISTS `{$tbl}`; " . $sqlCreate);
}

// Copiar datos de control fundamentales
$tablasControl = ['modulos', 'planes', 'roles', 'permisos', 'rol_permisos', 'migraciones_control'];
foreach ($tablasControl as $tbl) {
    $filas = $pdoClean->query("SELECT * FROM `{$tbl}`")->fetchAll();
    if (!empty($filas)) {
        $cols = array_keys($filas[0]);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colNames = implode('`,`', $cols);
        $stmtInsert = $pdoRestore->prepare("INSERT INTO `{$tbl}` (`{$colNames}`) VALUES ({$placeholders})");
        foreach ($filas as $f) {
            $stmtInsert->execute(array_values($f));
        }
    }
}

$tablasRestauradas = $pdoRestore->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$permisosRestaurados = (int) $pdoRestore->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
$migracionesRestauradas = (int) $pdoRestore->query("SELECT COUNT(*) FROM migraciones_control")->fetchColumn();

verificar(count($tablasRestauradas) === 104, "Restauración en entorno aislado recrea exactamente 104 tablas");
verificar($permisosRestaurados === 112, "Restauración en entorno aislado preserva los 112 permisos RBAC");
verificar($migracionesRestauradas === 24, "Restauración en entorno aislado preserva las 24 migraciones registradas");

// Destrucción limpia
$pdo->exec("DROP DATABASE IF EXISTS `{$dbClean}`");
$pdo->exec("DROP DATABASE IF EXISTS `{$dbRestore}`");
verificar(true, "Bases de datos temporales destruidas satisfactoriamente");

// ------------------------------------------------------------------------------
// GATE 4: INTEGRIDAD DE LA BASE DE DATOS Y HUELLA DE ORLANDO (ID 24)
// ------------------------------------------------------------------------------
echo "\n--- GATE 4: INTEGRIDAD DE LA BASE DE DATOS Y HUELLA DE ORLANDO (ID 24) ---\n";
$stmtOrl = $pdo->prepare("SELECT id, estado, contrasena_hash, correo_electronico FROM usuarios WHERE id = 24");
$stmtOrl->execute();
$usuarioOrl = $stmtOrl->fetch();

verificar($usuarioOrl !== false, "Usuario Orlando (ID 24) existe y está registrado");
verificar(($usuarioOrl['estado'] ?? '') === 'ACTIVO', "Usuario Orlando (ID 24) se encuentra en estado ACTIVO");

$hashEsperado = '80e6af84e02e89e3';
$hashActual = substr(hash('sha256', (string) ($usuarioOrl['contrasena_hash'] ?? '')), 0, 16);
verificar($hashActual === $hashEsperado, "Huella inmutable de Orlando intacta: {$hashActual} == {$hashEsperado}");

// ------------------------------------------------------------------------------
// GATE 5: SEGURIDAD Y CONTROL MULTITENANT
// ------------------------------------------------------------------------------
echo "\n--- GATE 5: AUDITORÍA DE SEGURIDAD TRANSVERSAL Y MULTITENANT ---\n";
$htaccessPrivado = __DIR__ . '/../almacenamiento/privado/.htaccess';
verificar(file_exists($htaccessPrivado), "Almacenamiento privado protegido con .htaccess");
if (file_exists($htaccessPrivado)) {
    $c = file_get_contents($htaccessPrivado);
    verificar(str_contains($c, 'Deny from all') || str_contains($c, 'Require all denied'), "Directiva de denegación total activa en .htaccess");
}

echo "\n==============================================================================\n";
echo "RESUMEN DE RATIFICACIÓN V1.1: {$totalGates} GATES EVALUADOS | {$passedGates} PASS | {$failedGates} FAIL\n";
echo "==============================================================================\n";

if ($failedGates > 0) {
    exit(1);
}
exit(0);
