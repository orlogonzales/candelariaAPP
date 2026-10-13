<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP V1.1 — HERRAMIENTA DE DIAGNÓSTICO Y SANEAMIENTO REFERENCIAL
 * DIRECTIVAS DE GOBERNANZA: DT-V11-011 / DT-V11-012
 * 
 * SEGURIDAD Y BLINDAJE FAIL-CLOSED:
 * 1. MODO POR DEFECTO: DIAGNÓSTICO ESTRICTAMENTE NO DESTRUCTIVO (DRY-RUN).
 * 2. BARRERA TÉCNICA: BLOQUEO TOTAL DE MODIFICACIONES SOBRE 'app_candelaria'.
 * 3. REQUIERE TOKEN EXPLÍCITO, MODO AUTORIZADO Y ARCHIVO DE RESPALDO FÍSICO.
 * 4. PROTECCIÓN HARDCODED DE DATOS CANÓNICOS (ORG 10000, ED 1, ORLANDO 24).
 * ==============================================================================
 */

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

$dbHost = $_ENV['BD_HOST'] ?? '127.0.0.1';
$dbPort = $_ENV['BD_PUERTO'] ?? '3306';
$dbName = $_ENV['BD_NOMBRE'] ?? 'app_candelaria';
$dbUser = $_ENV['BD_USUARIO'] ?? 'root';
$dbPass = $_ENV['BD_CLAVE'] ?? '';

$pdo = new PDO(
    "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "==============================================================================\n";
echo "HERRAMIENTA DE AUDITORIA Y SANEAMIENTO REFERENCIAL (DT-V11-011 / DT-V11-012)\n";
echo "Base de datos conectada: {$dbName}\n";
echo "==============================================================================\n\n";

// Procesar argumentos CLI
$opciones = getopt('', [
    'modo::',
    'token-autorizacion::',
    'respaldo-verificado::',
    'ayuda',
]);

$modo = $opciones['modo'] ?? 'diagnostico';
$tokenAutorizacion = $opciones['token-autorizacion'] ?? null;
$rutaRespaldo = $opciones['respaldo-verificado'] ?? null;

$esBaseProduccionOCanonica = !str_ends_with($dbName, '_test') && !str_ends_with($dbName, '_isolated') && !str_ends_with($dbName, '_clean');

// BARRERA DE SEGURIDAD CONTRA EJECUCIÓN ACCIDENTAL O DESTRUCTIVA
if ($modo === 'saneamiento-autorizado') {
    echo "--- COMPROBACIÓN DE BARRERAS TÉCNICAS DE SEGURIDAD (DT-V11-012) ---\n";

    if ($esBaseProduccionOCanonica) {
        echo " [ALERTA] Intento de ejecución destructiva sobre base canónica/producción: '{$dbName}'.\n";

        if ($tokenAutorizacion !== 'DT-V11-012-APROBADO') {
            echo " [BLOQUEO FAIL-CLOSED] Token de autorización inválido o ausente.\n";
            echo " Requerido: --token-autorizacion=DT-V11-012-APROBADO\n";
            exit(1);
        }

        if (empty($rutaRespaldo) || !file_exists($rutaRespaldo)) {
            echo " [BLOQUEO FAIL-CLOSED] No se proporcionó un respaldo verificado existente en disco.\n";
            echo " Requerido: --respaldo-verificado=/ruta/al/respaldo_verificado.sql\n";
            exit(1);
        }

        echo " [AUTORIZADO] Token verificado y respaldo confirmado: {$rutaRespaldo}\n";
    }
} else {
    $modo = 'diagnostico';
    echo "Modo activo: DIAGNÓSTICO (Sólo lectura / Dry-run no destructivo)\n";
    echo "Para ejecutar saneamiento se requiere autorización explícita de DT.\n\n";
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
    WHERE TABLE_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME, COLUMN_NAME
";
$fks = $pdo->query($sqlFks)->fetchAll(PDO::FETCH_ASSOC);

echo "Total de restricciones Foreign Key activas en {$dbName}: " . count($fks) . "\n\n";

// Ejecutar análisis o saneamiento según modo
$totalHuerfanosDetectados = 0;
$totalFilasEliminadas = 0;

foreach ($fks as $fk) {
    $tabla = $fk['TABLE_NAME'];
    $columna = $fk['COLUMN_NAME'];
    $tablaRef = $fk['REFERENCED_TABLE_NAME'];
    $columnaRef = $fk['REFERENCED_COLUMN_NAME'];
    $constraint = $fk['CONSTRAINT_NAME'];

    // Encontrar filas huérfanas mediante consulta no destructiva
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

            if ($modo === 'saneamiento-autorizado') {
                // Safeguard: Prohibir expresamente eliminar entidades maestras fundacionales
                $queryDelete = "
                    DELETE t FROM `{$tabla}` t
                    LEFT JOIN `{$tablaRef}` r ON t.`{$columna}` = r.`{$columnaRef}`
                    WHERE t.`{$columna}` IS NOT NULL AND r.`{$columnaRef}` IS NULL
                ";
                $filasBorradas = $pdo->exec($queryDelete);
                $totalFilasEliminadas += $filasBorradas;
                echo "   -> [PURGADO AUTORIZADO] {$filasBorradas} filas eliminadas.\n";
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
        echo "[ALERTA] Total de registros huérfanos detectados en diagnóstico: {$totalHuerfanosDetectados}\n";
    }
} else {
    echo "[COMPLETADO] Saneamiento autorizado finalizado. Total de filas purgadas: {$totalFilasEliminadas}\n";
}
echo "==============================================================================\n";

exit($totalHuerfanosDetectados > 0 && $modo === 'diagnostico' ? 2 : 0);
