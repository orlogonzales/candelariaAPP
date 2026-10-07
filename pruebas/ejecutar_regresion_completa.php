<?php

declare(strict_types=1);

$dir = __DIR__;
$archivos = glob($dir . '/ejecutar_pruebas_*.php');
sort($archivos);

$totalPass = 0;
$totalFail = 0;
$resumen = [];

echo "==============================================================================\n";
echo "CANDELARIAAPP — REGRESIÓN GLOBAL DE SUITES DE PRUEBAS (HISTÓRICO + F2.6E)\n";
echo "==============================================================================\n\n";

foreach ($archivos as $archivo) {
    $nombre = basename($archivo);
    $cmd = 'php ' . escapeshellarg($archivo) . ' 2>&1';
    $salida = [];
    $codigoRetorno = 0;
    exec($cmd, $salida, $codigoRetorno);
    $textoSalida = implode("\n", $salida);

    $pass = 0;
    $fail = 0;

    // Patrones comunes en las suites históricas
    if (preg_match('/(\d+)\s+(?:PRUEBAS EXITOSAS|pruebas exitosas)/i', $textoSalida, $m)) {
        $pass = (int)$m[1];
    } elseif (preg_match('/(?:ÉXITOS|EXITOS|Exitosos|PASS)\s*[:=]?\s*(\d+)/i', $textoSalida, $m)) {
        $pass = (int)$m[1];
    }

    if (preg_match('/(?:FALLOS|FAIL|Fallidos)\s*[:=]?\s*(\d+)/i', $textoSalida, $m)) {
        $fail = (int)$m[1];
    }

    if ($codigoRetorno !== 0 && $fail === 0) {
        $fail = 1;
    }

    $estado = ($codigoRetorno === 0 && $fail === 0 && $pass > 0) ? 'PASS' : 'FAIL';
    $totalPass += $pass;
    $totalFail += $fail;

    $resumen[] = sprintf("%-30s : %-4s (%3d pass, %2d fail)", $nombre, $estado, $pass, $fail);
}

echo implode("\n", $resumen) . "\n";
echo "==============================================================================\n";
echo sprintf("TOTAL REGRESIÓN GLOBAL: %d PASS / %d FAIL en %d suites\n", $totalPass, $totalFail, count($archivos));
echo "==============================================================================\n";

if ($totalFail > 0) {
    exit(1);
}
