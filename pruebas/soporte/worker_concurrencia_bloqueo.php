<?php

declare(strict_types=1);

/**
 * Worker auxiliar para pruebas de concurrencia simultánea en procesos paralelos.
 * Ejecuta un intento de bloqueo de cupo sobre el Motor de Disponibilidad.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../../.env');

use Aplicacion\Repositorios\ReservaBloqueoTemporalRepositorio;
use Aplicacion\Servicios\MotorDisponibilidadServicio;
use Nucleo\BaseDatos\Conexion;

$orgId = (int) ($argv[1] ?? 0);
$edId = (int) ($argv[2] ?? 0);
$itemId = (int) ($argv[3] ?? 0);
$sesionId = (string) ($argv[4] ?? 'ses_' . bin2hex(random_bytes(6)));

// Conexión independiente para este proceso
$pdo = Conexion::crearNuevaConexion();
$bloqueoRepo = new ReservaBloqueoTemporalRepositorio($pdo);
$motor = new MotorDisponibilidadServicio($bloqueoRepo, $pdo);

try {
    $res = $motor->bloquearCupos($orgId, $edId, $itemId, 1, $sesionId, ttlMinutos: 15);
    echo json_encode([
        'exito'         => (bool) ($res['exito'] ?? false),
        'mensaje'       => (string) ($res['mensaje'] ?? ''),
        'token_bloqueo' => $res['token_bloqueo'] ?? null,
    ]);
} catch (\Throwable $e) {
    echo json_encode([
        'exito'   => false,
        'mensaje' => $e->getMessage(),
        'error'   => get_class($e),
    ]);
}
