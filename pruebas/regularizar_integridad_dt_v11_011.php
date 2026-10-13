<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP V1.1 — HERRAMIENTA DE DIAGNÓSTICO REFERENCIAL
 * DIRECTIVAS DE GOBERNANZA: DT-V11-011 / DT-V11-012 / DT-V11-013
 * 
 * POLÍTICA DE SEGURIDAD INTEGRAL Y SEPARACIÓN DE RESPONSABILIDADES:
 * 1. PROHIBICIÓN INCONDICIONAL: En 'app_candelaria' (base principal), TODA
 *    operación destructiva o de saneamiento está TERMINANTEMENTE PROHIBIDA.
 *    No existen tokens, banderas ni argumentos que puedan anular este bloqueo.
 * 2. MODO EXCLUSIVO EN BASE PRINCIPAL: Diagnóstico estricto de solo lectura.
 * 3. BASES DESECHABLES: Operaciones de saneamiento automatizado únicamente
 *    están permitidas en bases temporales aisladas (*_test, *_isolated, *_disposable).
 * 4. MANTENIMIENTO EN PRODUCCIÓN: Las operaciones sobre datos legítimos deben
 *    seguir un procedimiento administrativo independiente con revisión de plan SQL.
 * ==============================================================================
 */

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

// Procesar argumentos CLI
$opciones = getopt('', [
    'modo::',
    'bd::',
    'ayuda',
]);

$dbHost = $_ENV['BD_HOST'] ?? '127.0.0.1';
$dbPort = $_ENV['BD_PUERTO'] ?? '3306';
$dbName = !empty($opciones['bd']) ? (string)$opciones['bd'] : ($_ENV['BD_NOMBRE'] ?? 'app_candelaria');
$dbUser = $_ENV['BD_USUARIO'] ?? 'root';
$dbPass = $_ENV['BD_CLAVE'] ?? '';

$pdo = new PDO(
    "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "==============================================================================\n";
echo "HERRAMIENTA DE AUDITORIA Y DIAGNOSTICO REFERENCIAL (DT-V11-013)\n";
echo "Base de datos conectada: {$dbName}\n";
echo "==============================================================================\n\n";

$modo = $opciones['modo'] ?? 'diagnostico';

// Verificación estricta de base desechable
$esBaseDesechable = (bool) preg_match('/_(?:test|isolated|disposable)$/i', $dbName);
$esBasePrincipal = ($dbName === 'app_candelaria' || !$esBaseDesechable);

// BARRERA INCONDICIONAL DT-V11-013 CONTRA OPERACIONES DESTRUCTIVAS
if ($modo !== 'diagnostico') {
    if ($esBasePrincipal) {
        echo " [BLOQUEO INCONDICIONAL DT-V11-013]\n";
        echo " Se prohíbe terminantemente cualquier operación destructiva o de saneamiento\n";
        echo " sobre la base de datos principal '{$dbName}'.\n";
        echo " Esta restricción es estructural: no existen tokens, contraseñas ni parámetros\n";
        echo " de línea de comandos que puedan anularla.\n";
        echo " En '{$dbName}', este script opera única y exclusivamente en modo de sólo lectura.\n\n";
        exit(1);
    }

    if ($modo !== 'saneamiento-desechable') {
        echo " [ERROR] Modo no reconocido para base desechable: '{$modo}'.\n";
        echo " Modos válidos en bases desechables: 'diagnostico', 'saneamiento-desechable'.\n";
        exit(1);
    }

    echo " [AVISO] Ejecutando saneamiento en base desechable verificada: '{$dbName}'.\n\n";
} else {
    echo "Modo activo: DIAGNÓSTICO (Solo lectura / Inspección no destructiva)\n";
    echo "Ninguna modificación será realizada en la base de datos.\n\n";
}

// 1. Obtener restricciones FK
$sqlFks = "
    SELECT 
        TABLE_NAME, 
        COLUMN_NAME, 
        CONSTRAINT_NAME, 
        REFERENCED_TABLE_NAME, 
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = :schema
      AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME, COLUMN_NAME
";
$stmt = $pdo->prepare($sqlFks);
$stmt->execute(['schema' => $dbName]);
$fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de restricciones Foreign Key activas en {$dbName}: " . count($fks) . "\n\n";

// Ejecutar análisis o saneamiento en base desechable
$totalHuerfanosDetectados = 0;
$totalFilasEliminadas = 0;

foreach ($fks as $fk) {
    $tabla = $fk['TABLE_NAME'];
    $columna = $fk['COLUMN_NAME'];
    $tablaRef = $fk['REFERENCED_TABLE_NAME'];
    $columnaRef = $fk['REFERENCED_COLUMN_NAME'];
    $constraint = $fk['CONSTRAINT_NAME'];

    // Consulta de inspección no destructiva
    $queryCount = "
        SELECT COUNT(*)
        FROM `{$tabla}` t
        LEFT JOIN `{$tablaRef}` r ON t.`{$columna}` = r.`{$columnaRef}`
        WHERE t.`{$columna}` IS NOT NULL AND r.`{$columnaRef}` IS NULL
    ";

    try {
        $count = (int) $pdo->query($queryCount)->fetchColumn();
        if ($count > 0) {
            $totalHuerfanosDetectados += $count;
            echo " [DETECTADO] `{$tabla}`.`{$columna}`: {$count} registros huérfanos (FK: {$constraint} -> `{$tablaRef}`).\n";

            // Saneamiento ÚNICAMENTE en base desechable verificada
            if ($modo === 'saneamiento-desechable' && $esBaseDesechable) {
                $queryDelete = "
                    DELETE t FROM `{$tabla}` t
                    LEFT JOIN `{$tablaRef}` r ON t.`{$columna}` = r.`{$columnaRef}`
                    WHERE t.`{$columna}` IS NOT NULL AND r.`{$columnaRef}` IS NULL
                ";
                $filasBorradas = $pdo->exec($queryDelete);
                $totalFilasEliminadas += $filasBorradas;
                echo "   -> [PURGADO EN BASE DESECHABLE] {$filasBorradas} filas eliminadas.\n";
            }
        }
    } catch (\Throwable $e) {
        echo " [ERROR] Evaluando `{$tabla}`.`{$columna}`: " . $e->getMessage() . "\n";
    }
}

echo "\n------------------------------------------------------------------------------\n";
if ($modo === 'diagnostico') {
    if ($totalHuerfanosDetectados === 0) {
        echo "[PASS] INTEGRIDAD REFERENCIAL 100% PURA: 0 huérfanos detectados en {$dbName}.\n";
    } else {
        echo "[DIAGNÓSTICO] Total de registros huérfanos detectados: {$totalHuerfanosDetectados}\n";
        echo "[INFO] Operación completada en modo solo lectura. CERO registros mutados.\n";
    }
} else {
    echo "[COMPLETADO] Saneamiento en base desechable finalizado. Total de filas purgadas: {$totalFilasEliminadas}\n";
}
echo "==============================================================================\n";

exit(0);
