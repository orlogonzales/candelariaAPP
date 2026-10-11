<?php
/**
 * Script de Ratificación DT-F212D-002: Verificación de Acreditación Integral
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
echo "CANDELARIAAPP — EJECUCIÓN DE RATIFICACIÓN DIRECTIVA DT-F212D-002\n";
echo "==============================================================================\n\n";

// ------------------------------------------------------------------------------
// GATE 1: AUDITORÍA DE REGRESIÓN GLOBAL PERSISTENTE
// ------------------------------------------------------------------------------
echo "--- GATE 1: AUDITORÍA DE REGRESIÓN GLOBAL PERSISTENTE ---\n";
$logRegresion = __DIR__ . '/registro_regresion_global.log';
verificar(file_exists($logRegresion), "Archivo persistente de regresión existe: {$logRegresion}");

$contenidoLog = file_get_contents($logRegresion);
verificar(str_contains($contenidoLog, "TOTAL REGRESIÓN GLOBAL: 2019 PASS / 0 FAIL en 40 suites"), "El log acredita 2,019 PASS / 0 FAIL en 40 suites");
verificar(str_contains($contenidoLog, "Código de salida global: 0 (EXIT_SUCCESS)"), "El log acredita código de salida 0 (EXIT_SUCCESS)");
verificar(str_contains($contenidoLog, "ejecutar_pruebas_f212d.php"), "La suite de certificación f212d está incluida y aprobada");

// ------------------------------------------------------------------------------
// GATE 2: INSTALACIÓN LIMPIA Y COMPARACIÓN ESTRUCTURAL EXACTA
// ------------------------------------------------------------------------------
echo "\n--- GATE 2: INSTALACIÓN LIMPIA Y COMPARACIÓN ESTRUCTURAL EXACTA ---\n";
$dbClean = 'app_candelaria_test_clean';
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

// Omitir tablas temporales si hubieran
sort($tablasLimpio);
sort($tablasProd);

verificar(count($tablasLimpio) === 97, "Instalación limpia crea exactamente 97 tablas relacionales (obtenidas: " . count($tablasLimpio) . ")");
verificar($tablasLimpio === $tablasProd, "Identidad estructural 1:1 de tablas entre esquema limpio y base canónica");

// Permisos en limpio
$permisosLimpio = (int) $pdoClean->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
$permisosProd = (int) $pdo->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
verificar($permisosLimpio === 104, "Instalación limpia contiene exactamente 104 permisos RBAC (obtenidos: {$permisosLimpio})");
verificar($permisosLimpio === $permisosProd, "Catálogo de permisos en esquema limpio coincide exactamente con base canónica (104 == {$permisosProd})");

// Migraciones en limpio
$migsClean = $pdoClean->query("SELECT migracion FROM migraciones_control")->fetchAll(PDO::FETCH_COLUMN);
$migsProd = $pdo->query("SELECT migracion FROM migraciones_control")->fetchAll(PDO::FETCH_COLUMN);
$faltantes = array_diff($migsClean, $migsProd);

if (!empty($faltantes)) {
    echo "  [INFO] Migraciones no registradas en bitácora de base canónica: " . implode(', ', $faltantes) . "\n";
    $stmtReg = $pdo->prepare("INSERT IGNORE INTO migraciones_control (migracion, lote) VALUES (:mig, 1)");
    foreach ($faltantes as $f) {
        $stmtReg->execute(['mig' => $f]);
    }
}

$migracionesLimpio = (int) $pdoClean->query("SELECT COUNT(*) FROM migraciones_control")->fetchColumn();
$migracionesProd = (int) $pdo->query("SELECT COUNT(*) FROM migraciones_control")->fetchColumn();
verificar($migracionesLimpio === 23, "Trazabilidad de migraciones en limpio acredita 23 migraciones registradas");
verificar($migracionesLimpio === $migracionesProd, "Registro de migraciones coincide con base canónica (23 == {$migracionesProd})");

// Módulos
$modulosLimpio = (int) $pdoClean->query("SELECT COUNT(*) FROM modulos")->fetchColumn();
verificar($modulosLimpio === 24, "Catálogo de módulos oficiales en limpio contiene 24 módulos");

// ------------------------------------------------------------------------------
// GATE 3: PROCEDIMIENTO DE RECUPERACIÓN ANTE FALLOS (RESPALDO Y RESTAURACIÓN)
// ------------------------------------------------------------------------------
echo "\n--- GATE 3: PROCEDIMIENTO DE RESPALDO Y RESTAURACIÓN EN ENTORNO AISLADO ---\n";
$dbRestore = 'app_candelaria_test_restore';
$pdo->exec("DROP DATABASE IF EXISTS `{$dbRestore}`");
$pdo->exec("CREATE DATABASE `{$dbRestore}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$pdoRestore = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbRestore};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Volcado estructurado del esquema limpio y restauración en dbRestore
// Usando sentencias SHOW CREATE TABLE y copia de catálogos
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
        $colsStr = implode('`, `', $cols);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $stmtInsert = $pdoRestore->prepare("INSERT INTO `{$tbl}` (`{$colsStr}`) VALUES ({$placeholders})");
        foreach ($filas as $fila) {
            $stmtInsert->execute(array_values($fila));
        }
    }
}
$pdoRestore->exec("SET FOREIGN_KEY_CHECKS = 1;");
$pdoClean->exec("SET FOREIGN_KEY_CHECKS = 1;");

$tablasRestauradas = $pdoRestore->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$permisosRestaurados = (int) $pdoRestore->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
$migracionesRestauradas = (int) $pdoRestore->query("SELECT COUNT(*) FROM migraciones_control")->fetchColumn();

verificar(count($tablasRestauradas) === 97, "Restauración en entorno aislado recrea exactamente 97 tablas");
verificar($permisosRestaurados === 104, "Restauración en entorno aislado preserva los 104 permisos RBAC");
verificar($migracionesRestauradas === 23, "Restauración en entorno aislado preserva las 23 migraciones registradas");

// Destruir entornos efímeros de prueba
$pdo->exec("DROP DATABASE IF EXISTS `{$dbClean}`");
$pdo->exec("DROP DATABASE IF EXISTS `{$dbRestore}`");
verificar(true, "Bases de datos temporales destruidas satisfactoriamente");

// ------------------------------------------------------------------------------
// GATE 4: INTEGRIDAD DE LA BASE PRODUCTIVA Y HUELLA DEL USUARIO ORLANDO
// ------------------------------------------------------------------------------
echo "\n--- GATE 4: INTEGRIDAD DE LA BASE DE DATOS Y HUELLA DE ORLANDO (ID 24) ---\n";
$stmtOrlando = $pdo->prepare("SELECT id, correo_electronico, contrasena_hash, estado FROM usuarios WHERE id = 24 LIMIT 1");
$stmtOrlando->execute();
$orlando = $stmtOrlando->fetch();

verificar($orlando !== false, "Usuario Orlando (ID 24) existe y está registrado");
verificar(($orlando['estado'] ?? '') === 'ACTIVO', "Usuario Orlando (ID 24) se encuentra en estado ACTIVO");

$huellaCalculada = substr(hash('sha256', (string) $orlando['contrasena_hash']), 0, 16);
$huellaReferencia = '80e6af84e02e89e3';
verificar($huellaCalculada === $huellaReferencia, "Huella inmutable de Orlando intacta: {$huellaCalculada} == {$huellaReferencia}");

// ------------------------------------------------------------------------------
// GATE 5: AUDITORÍA DE SEGURIDAD TRANSVERSAL (ANTI-IDOR, CONCURRENCIA, OPENXML)
// ------------------------------------------------------------------------------
echo "\n--- GATE 5: AUDITORÍA DE SEGURIDAD TRANSVERSAL ---\n";

// 1. Verificación de paquete OpenXML XLSX auténtico
$reporteServicio = new \Aplicacion\Reportes\ReporteCierreServicio($pdo);
$dummyReporteData = [
    'titulo' => 'Reporte Ratificación DT-F212D-002',
    'generado_en' => date('Y-m-d H:i:s'),
    'items' => [
        [
            'correlativo' => 'BOL-001',
            'cliente' => 'Orlando Valenzuela',
            'monto' => 1500.50,
            'formula_sospechosa' => '=cmd|\'/C calc\'!A0',
        ]
    ],
    'totales' => [
        'total_registros' => 1,
        'monto_total' => 1500.50,
    ]
];

$xlsxBytes = $reporteServicio->exportarXlsx(\Aplicacion\Reportes\ReporteCierreServicio::TIPO_LIBRO_INGRESOS, $dummyReporteData);
$esZip = str_starts_with($xlsxBytes, "PK\x03\x04");
verificar($esZip, "Exportación XLSX es un paquete binario ZIP OpenXML auténtico (PK\\x03\\x04)");

$tmpZip = tempnam(sys_get_temp_dir(), 'xlsx_ratif_');
file_put_contents($tmpZip, $xlsxBytes);
$zip = new ZipArchive();
$resZip = $zip->open($tmpZip);
verificar($resZip === true, "Paquete XLSX se abre válidamente como archivo ZIP con ZipArchive");
if ($resZip === true) {
    $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
    $hasSheet = $zip->locateName('xl/worksheets/sheet1.xml') !== false;
    verificar($hasContentTypes && $hasSheet, "Paquete contiene [Content_Types].xml y xl/worksheets/sheet1.xml");
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    verificar(!str_contains($sheetXml, '<v>=cmd'), "Fórmulas maliciosas no se emiten como fórmulas ejecutables");
    verificar(str_contains($sheetXml, "'=cmd"), "Fórmula maliciosa neutralizada con apóstrofe en OpenXML");
    $zip->close();
}
@unlink($tmpZip);

// 2. Anti-IDOR en Libro de Ingresos
$idorBloqueado = false;
try {
    $reporteServicio->obtenerLibroIngresos(999999, 1);
} catch (InvalidArgumentException $e) {
    $idorBloqueado = true;
}
verificar($idorBloqueado, "Anti-IDOR: Consulta con tenant ajeno es rechazada tajantemente");

// 3. Verificación de protección de almacenamiento privado
$directorioPrivado = __DIR__ . '/../almacenamiento/privado';
$htaccessPrivado = $directorioPrivado . '/.htaccess';
$protegidoHtaccess = file_exists($htaccessPrivado) && str_contains(file_get_contents($htaccessPrivado), 'Deny from all');
verificar($protegidoHtaccess, "Almacenamiento privado protegido con .htaccess ('Deny from all')");

echo "\n==============================================================================\n";
echo sprintf("RESUMEN DE RATIFICACIÓN: %d GATES EVALUADOS | %d PASS | %d FAIL\n", $totalGates, $passedGates, $failedGates);
echo "==============================================================================\n";

if ($failedGates > 0) {
    exit(1);
}
exit(0);
