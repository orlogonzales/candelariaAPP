<?php

declare(strict_types=1);

$inicioTimestamp = microtime(true);
$fechaInicio = date('Y-m-d H:i:s T');

$dir = __DIR__;
$archivos = glob($dir . '/ejecutar_pruebas_*.php');
sort($archivos);

$totalPass = 0;
$totalFail = 0;
$resumen = [];

$cabecera = "==============================================================================\n";
$cabecera .= "CANDELARIAAPP — REGRESIÓN GLOBAL DE SUITES DE PRUEBAS (DT-F212D-002)\n";
$cabecera .= "Fecha y hora de inicio: {$fechaInicio}\n";
$cabecera .= "PHP Version: " . PHP_VERSION . " | OS: " . PHP_OS . "\n";
$cabecera .= "Suites detectadas: " . count($archivos) . "\n";
$cabecera .= "==============================================================================\n\n";

echo $cabecera;

foreach ($archivos as $index => $archivo) {
    $nombre = basename($archivo);
    $cmd = 'php ' . escapeshellarg($archivo) . ' 2>&1';
    $salida = [];
    $codigoRetorno = 0;
    
    $inicioSuite = microtime(true);
    exec($cmd, $salida, $codigoRetorno);
    $duracionSuite = round(microtime(true) - $inicioSuite, 2);
    $textoSalida = implode("\n", $salida);

    $pass = 0;
    $fail = 0;

    // Patrones comunes en las suites históricas
    if (preg_match('/(?:TOTAL PRUEBAS EXITOSAS|PRUEBAS EXITOSAS|EXITOSAS|Exitosos)\s*:\s*(\d+)/i', $textoSalida, $m)) {
        $pass = (int)$m[1];
    } elseif (preg_match('/(\d+)\s*(?:\/|\s+de\s+)\s*(\d+)\s+PRUEBAS EXITOSAS/i', $textoSalida, $m)) {
        $pass = (int)$m[1];
    } elseif (preg_match('/(\d+)\s+PRUEBAS EXITOSAS/i', $textoSalida, $m)) {
        $pass = (int)$m[1];
    } elseif (preg_match('/(?:ÉXITOS|EXITOS|PASS)\s*[:=]?\s*(\d+)/i', $textoSalida, $m)) {
        $pass = (int)$m[1];
    } elseif (preg_match('/:\s*(\d+)\s+PASS/i', $textoSalida, $m)) {
        $pass = (int)$m[1];
    } elseif (preg_match('/(\d+)\s+PASS\b/i', $textoSalida, $m)) {
        $pass = (int)$m[1];
    }

    if (preg_match('/(?:TOTAL PRUEBAS FALLIDAS|FALLIDAS|FALLOS|FALLIDOS|Fallidos)\s*:\s*(\d+)/i', $textoSalida, $m)) {
        $fail = (int)$m[1];
    } elseif (preg_match('/\/\s*(\d+)\s+FALLOS/i', $textoSalida, $m)) {
        $fail = (int)$m[1];
    } elseif (preg_match('/\/\s*(\d+)\s+FAIL\b/i', $textoSalida, $m)) {
        $fail = (int)$m[1];
    } elseif (preg_match('/\|\s*(?:FALLOS|Fallidos)\s*:\s*(\d+)/i', $textoSalida, $m)) {
        $fail = (int)$m[1];
    }

    if ($codigoRetorno === 0) {
        $fail = 0;
    } elseif ($fail === 0) {
        $fail = 1;
    }

    $estado = ($codigoRetorno === 0 && $fail === 0 && $pass > 0) ? 'PASS' : 'FAIL';
    $totalPass += $pass;
    $totalFail += $fail;

    $lineaResumen = sprintf("[%02d/%02d] %-30s : %-4s (%3d pass, %2d fail) [%5.2fs]", $index + 1, count($archivos), $nombre, $estado, $pass, $fail, $duracionSuite);
    $resumen[] = $lineaResumen;
    echo $lineaResumen . "\n";

    if ($estado === 'FAIL') {
        $resumen[] = "  --> DETALLE: " . substr(trim($textoSalida), 0, 300);
        echo "  --> DETALLE: " . substr(trim($textoSalida), 0, 300) . "\n";
    }
}

$duracionTotal = round(microtime(true) - $inicioTimestamp, 2);
$fechaFin = date('Y-m-d H:i:s T');

$pie = "\n==============================================================================\n";
$pie .= sprintf("TOTAL REGRESIÓN GLOBAL: %d PASS / %d FAIL en %d suites\n", $totalPass, $totalFail, count($archivos));
$pie .= "Fecha y hora de finalización: {$fechaFin}\n";
$pie .= "Duración total de ejecución: {$duracionTotal} segundos\n";
$pie .= "Código de salida global: " . ($totalFail === 0 ? "0 (EXIT_SUCCESS)" : "1 (EXIT_FAILURE)") . "\n";
$pie .= "==============================================================================\n";

echo $pie;

$logFile = $dir . '/registro_regresion_global.log';
file_put_contents($logFile, $cabecera . implode("\n", $resumen) . "\n" . $pie);
echo "Evidencia persistida en: {$logFile}\n";

if ($totalFail > 0) {
    exit(1);
}
exit(0);
