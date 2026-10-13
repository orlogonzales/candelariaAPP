<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

$pdo = new PDO(
    "mysql:host=" . ($_ENV['BD_HOST'] ?? '127.0.0.1') . ";port=" . ($_ENV['BD_PUERTO'] ?? '3306') . ";dbname=" . ($_ENV['BD_NOMBRE'] ?? 'app_candelaria') . ";charset=utf8mb4",
    $_ENV['BD_USUARIO'] ?? 'root',
    $_ENV['BD_CLAVE'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "==============================================================================\n";
echo "AUDITORIA DE INTEGRIDAD REFERENCIAL EXHAUSTIVA - CANDELARIAAPP (DT-V11-011)\n";
echo "==============================================================================\n\n";

// 1. Obtener todas las restricciones de Foreign Key en la base de datos
$sqlFks = "
    SELECT 
        TABLE_NAME, 
        COLUMN_NAME, 
        CONSTRAINT_NAME, 
        REFERENCED_TABLE_NAME, 
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME, COLUMN_NAME
";
$stmt = $pdo->query($sqlFks);
$fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de restricciones Foreign Key activas en el esquema: " . count($fks) . "\n\n";

$huerfanosTotales = 0;
$detallesHuerfanos = [];

foreach ($fks as $fk) {
    $tabla = $fk['TABLE_NAME'];
    $columna = $fk['COLUMN_NAME'];
    $tablaRef = $fk['REFERENCED_TABLE_NAME'];
    $columnaRef = $fk['REFERENCED_COLUMN_NAME'];
    $constraint = $fk['CONSTRAINT_NAME'];

    $query = "
        SELECT COUNT(*) 
        FROM `{$tabla}` t
        LEFT JOIN `{$tablaRef}` r ON t.`{$columna}` = r.`{$columnaRef}`
        WHERE t.`{$columna}` IS NOT NULL AND r.`{$columnaRef}` IS NULL
    ";

    try {
        $count = (int) $pdo->query($query)->fetchColumn();
        if ($count > 0) {
            $huerfanosTotales += $count;
            $detallesHuerfanos[] = [
                'tabla' => $tabla,
                'columna' => $columna,
                'tabla_ref' => $tablaRef,
                'columna_ref' => $columnaRef,
                'huerfanos' => $count,
                'constraint' => $constraint
            ];
            echo " [FAIL] Tabla `{$tabla}`.`{$columna}` tiene {$count} registros huerfanos respecto a `{$tablaRef}`.`{$columnaRef}` (FK: {$constraint})\n";
        }
    } catch (\Throwable $e) {
        echo " [ERROR] Evaluando FK {$constraint}: " . $e->getMessage() . "\n";
    }
}

if ($huerfanosTotales === 0) {
    echo " [PASS] INTEGRIDAD REFERENCIAL 100% PURA: 0 registros huerfanos detectados en las " . count($fks) . " FKs.\n";
} else {
    echo "\nTotal de violaciones de integridad referencial: {$huerfanosTotales}\n";
}

// 2. Comprobar registros canónicos de ediciones_candelaria
echo "\n--- ESTADO DE EDICIONES_CANDELARIA ---\n";
$ediciones = $pdo->query("SELECT id, organizacion_id, codigo, nombre, anio, estado, es_actual FROM ediciones_candelaria")->fetchAll(PDO::FETCH_ASSOC);
foreach ($ediciones as $ed) {
    echo " Edicion ID: {$ed['id']} | Org: {$ed['organizacion_id']} | Codigo: {$ed['codigo']} | Anio: {$ed['anio']} | Estado: {$ed['estado']} | Actual: {$ed['es_actual']}\n";
}

// 3. Comprobar huella de Orlando ID 24
echo "\n--- ESTADO DE SEGURIDAD ORLANDO (ID 24) ---\n";
$orlando = $pdo->query("SELECT id, nombre_usuario, estado, contrasena_hash FROM usuarios WHERE id = 24")->fetch(PDO::FETCH_ASSOC);
if ($orlando) {
    $huella = substr(hash('sha256', (string) $orlando['contrasena_hash']), 0, 16);
    echo " Orlando ID: {$orlando['id']} | Usuario: {$orlando['nombre_usuario']} | Estado: {$orlando['estado']} | Huella SHA-256: {$huella}\n";
} else {
    echo " [FAIL] Usuario Orlando ID 24 no encontrado.\n";
}

// 4. Tablas 106 y 107 de Operaciones Offline
echo "\n--- ESTADO TABLAS OPERACION OFFLINE ---\n";
$totalLotes = (int) $pdo->query("SELECT COUNT(*) FROM operacion_offline_lotes")->fetchColumn();
$totalEventos = (int) $pdo->query("SELECT COUNT(*) FROM operacion_offline_eventos")->fetchColumn();
echo " operacion_offline_lotes: {$totalLotes} registros\n";
echo " operacion_offline_eventos: {$totalEventos} registros\n";

echo "\n==============================================================================\n";
