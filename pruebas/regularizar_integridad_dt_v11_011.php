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
echo "EJECUTANDO SANEAMIENTO Y REGULARIZACION DE INTEGRIDAD REFERENCIAL (DT-V11-011)\n";
echo "==============================================================================\n\n";

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
$fks = $pdo->query($sqlFks)->fetchAll(PDO::FETCH_ASSOC);

// Proceder en bucle eliminando registros huérfanos hasta que el número de huérfanos sea 0
$iteracion = 1;
do {
    $eliminadosEnIteracion = 0;
    echo "--- Iteración de saneamiento #{$iteracion} ---\n";

    foreach ($fks as $fk) {
        $tabla = $fk['TABLE_NAME'];
        $columna = $fk['COLUMN_NAME'];
        $tablaRef = $fk['REFERENCED_TABLE_NAME'];
        $columnaRef = $fk['REFERENCED_COLUMN_NAME'];

        // Encontrar filas huérfanas
        $queryDelete = "
            DELETE t FROM `{$tabla}` t
            LEFT JOIN `{$tablaRef}` r ON t.`{$columna}` = r.`{$columnaRef}`
            WHERE t.`{$columna}` IS NOT NULL AND r.`{$columnaRef}` IS NULL
        ";

        try {
            $filas = $pdo->exec($queryDelete);
            if ($filas > 0) {
                echo " [PURGADO] `{$tabla}`.`{$columna}`: {$filas} registros huérfanos eliminados.\n";
                $eliminadosEnIteracion += $filas;
            }
        } catch (\Throwable $e) {
            echo " [ERROR] Purgando `{$tabla}`: " . $e->getMessage() . "\n";
        }
    }

    echo " Total eliminados en iteración {$iteracion}: {$eliminadosEnIteracion}\n\n";
    $iteracion++;
} while ($eliminadosEnIteracion > 0 && $iteracion <= 10);

echo "Saneamiento completado.\n";
