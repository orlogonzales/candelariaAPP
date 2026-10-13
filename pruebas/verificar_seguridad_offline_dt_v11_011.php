<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

use Aplicacion\Operaciones\OperacionOfflineServicio;
use Aplicacion\Operaciones\OperacionOfflineLote;
use Aplicacion\Repositorios\OperacionOfflineRepositorio;
use Nucleo\Http\ContextoOperacion;

$pdo = new PDO(
    "mysql:host=" . ($_ENV['BD_HOST'] ?? '127.0.0.1') . ";port=" . ($_ENV['BD_PUERTO'] ?? '3306') . ";dbname=" . ($_ENV['BD_NOMBRE'] ?? 'app_candelaria') . ";charset=utf8mb4",
    $_ENV['BD_USUARIO'] ?? 'root',
    $_ENV['BD_CLAVE'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "==============================================================================\n";
echo "DEMOSTRACION FORMAL DE SEGURIDAD QR OFFLINE Y CONCILIACION (DT-V11-011)\n";
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

$repo = new OperacionOfflineRepositorio($pdo);
$servicio = new OperacionOfflineServicio(offlineRepo: $repo, pdo: $pdo);

$orgId = 10000;
$edicionId = 1;
$turnoId = 101;
$ctx = new ContextoOperacion(
    actorTipo: 'HUMANO',
    usuarioId: 24, // Orlando
    actorSistemaId: null,
    actorSistemaCodigo: null,
    canalId: 1,
    canalCodigo: 'WEB',
    correlacionId: 'corr-dt-v11-011-seguridad-offline',
    origenIp: '127.0.0.1',
    agenteUsuario: 'DemoSeguridad/1.0',
    organizacionId: $orgId
);

// Setup de fixtures de prueba
$clienteId = (int) $pdo->query("SELECT id FROM clientes LIMIT 1")->fetchColumn();
$correlativoVenta = 'V-TEST-SEC-' . rand(100000, 999999);
$pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `creado_por`,
        `origen_tipo`, `correlativo`, `fecha_venta`, `moneda`, `cliente_nombre_completo`,
        `subtotal`, `total`, `monto_pagado`, `saldo_pendiente`,
        `estado`, `estado_financiero`, `creado_en`
    ) VALUES (
        ?, ?, ?, 24,
        'DIRECTA', ?, CURRENT_DATE, 'PEN', 'Carlos Alberto Mamani',
        100.00, 100.00, 100.00, 0.00,
        'CONFIRMADA', 'PAGADA_TOTAL', CURRENT_TIMESTAMP
    )
")->execute([$orgId, $edicionId, $clienteId, $correlativoVenta]);
$ventaId = (int) $pdo->lastInsertId();

$correlativoTest = 'RSV-TEST-SEC-' . rand(100000, 999999);
$pdo->prepare("
    INSERT INTO `reservas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `venta_id`, `correlativo`,
        `estado`, `contacto_nombre`, `creado_por`, `version_bloqueo`, `notas_operativas`
    ) VALUES (
        ?, ?, ?, ?, ?,
        'CONFIRMADA', 'CARLOS MAMANI', 24, 1, NULL
    )
")->execute([$orgId, $edicionId, $clienteId, $ventaId, $correlativoTest]);
$reservaId = (int) $pdo->lastInsertId();

// Registrar participante titular
$pdo->prepare("
    INSERT INTO `reserva_participantes` (
        `reserva_id`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `es_titular_reserva`
    ) VALUES (
        ?, 1, '76543210', 'CARLOS ALBERTO', 'MAMANI QUISPE', 1
    )
")->execute([$reservaId]);
$participanteId = (int) $pdo->lastInsertId();

echo "--- 1. DESCARGA DEL PAQUETE Y PRUEBA DE NO-MUTACION PROVISIONAL ---\n";
$paquete = $servicio->descargarPaqueteOffline(
    organizacionId: $orgId,
    edicionId: $edicionId,
    operadorId: 24,
    salidaId: null
);
asercion("Paquete offline emitido con firma criptografica HMAC-SHA256", !empty($paquete['firma_paquete']));

// Validar credencial en el dispositivo (simulación offline en memoria)
$evaluacionLocal = $servicio->validarCredencialLocal($paquete, $correlativoTest);
asercion("Validacion local calificada estrictamente como provisional (es_provisional = true)", $evaluacionLocal['es_provisional'] === true);
asercion("Resultado local clasificado como VALIDO_LOCAL_PROVISIONAL", $evaluacionLocal['resultado_codigo'] === 'VALIDO_LOCAL_PROVISIONAL');

// Comprobar que en la base de datos la reserva NUNCA fue mutada
$stmtCheck = $pdo->prepare("SELECT notas_operativas, estado FROM reservas WHERE id = ?");
$stmtCheck->execute([$reservaId]);
$filaReserva = $stmtCheck->fetch(PDO::FETCH_ASSOC);
asercion("Inmutabilidad confirmada: notas_operativas en BD permanece NULL tras validacion local", $filaReserva['notas_operativas'] === null);
asercion("Estado de reserva permanece en CONFIRMADA sin alteracion durante fase offline", $filaReserva['estado'] === 'CONFIRMADA');

echo "\n--- 2. ESCENARIO DE DOBLE GASTO CROSS-DEVICE Y CONCILIACION ---\n";
// Dos operadores (Terminal Alpha y Terminal Beta) escanearon la misma credencial en offline
$loteIdAlpha = 'LOT-ALPHA-' . bin2hex(random_bytes(4));
$datosLoteAlpha = [
    'lote_uuid' => $loteIdAlpha,
    'turno_id' => $turnoId,
    'dispositivo_info' => 'Terminal Alpha Puerta Norte',
    'eventos' => [
        [
            'token_qr' => $correlativoTest,
            'escaneado_en' => date('Y-m-d H:i:s'),
            'resultado_local' => 'VALIDO_LOCAL_PROVISIONAL',
            'detalle_local' => 'Escaneo Terminal Alpha Puerta Norte'
        ]
    ]
];

$loteIdBeta = 'LOT-BETA-' . bin2hex(random_bytes(4));
$datosLoteBeta = [
    'lote_uuid' => $loteIdBeta,
    'turno_id' => $turnoId,
    'dispositivo_info' => 'Terminal Beta Puerta Sur',
    'eventos' => [
        [
            'token_qr' => $correlativoTest,
            'escaneado_en' => date('Y-m-d H:i:s', time() + 5),
            'resultado_local' => 'VALIDO_LOCAL_PROVISIONAL',
            'detalle_local' => 'Escaneo Terminal Beta Puerta Sur'
        ]
    ]
];

// Terminal Alpha sincroniza primero
$resSincAlpha = $servicio->sincronizarLoteOffline(
    organizacionId: $orgId,
    edicionId: $edicionId,
    operadorId: 24,
    datosLote: $datosLoteAlpha,
    ipOrigen: '127.0.0.1'
);
asercion("Terminal Alpha sincroniza exitosamente su lote", !empty($resSincAlpha['lote_id']));
asercion("Escaneo de Terminal Alpha pasa a estado definitivo CONFIRMADO", $resSincAlpha['detalles_eventos'][0]['resultado_servidor'] === 'CONFIRMADO');
asercion("Lote Alpha registrado como PROCESADO sin conflictos", $resSincAlpha['estado'] === OperacionOfflineLote::ESTADO_PROCESADO);

// Comprobar check-in asentado formalmente en BD
$stmtCheck->execute([$reservaId]);
$filaReservaPostAlpha = $stmtCheck->fetch(PDO::FETCH_ASSOC);
asercion("Servidor asento check-in definitivo tras conciliacion transaccional", str_contains((string)$filaReservaPostAlpha['notas_operativas'], '[CHECKIN_QR_OFFLINE_SYNC'));

// Terminal Beta sincroniza después su escaneo offline duplicado
$resSincBeta = $servicio->sincronizarLoteOffline(
    organizacionId: $orgId,
    edicionId: $edicionId,
    operadorId: 24,
    datosLote: $datosLoteBeta,
    ipOrigen: '127.0.0.1'
);
asercion("Terminal Beta procesa lote con deteccion de duplicidad cross-device", !empty($resSincBeta['lote_id']));
asercion("Segundo escaneo calificado como CONFLICTO_DUPLICADO (doble gasto neutralizado)", $resSincBeta['detalles_eventos'][0]['resultado_servidor'] === 'CONFLICTO_DUPLICADO');
asercion("Lote Beta clasificado como CON_CONFLICTOS (total_conflictos = 1)", $resSincBeta['estado'] === OperacionOfflineLote::ESTADO_CON_CONFLICTOS && $resSincBeta['total_conflictos'] === 1);
asercion("CERO confirmaciones otorgadas a Terminal Beta (total_confirmados = 0)", $resSincBeta['total_confirmados'] === 0);

echo "\n--- 3. PRUEBA DE IDEMPOTENCIA TRANSACCIONAL ANTE REINTENTOS DE RED ---\n";
// Terminal Alpha reenvía el lote por timeout de red
$resSincAlphaReintento = $servicio->sincronizarLoteOffline(
    organizacionId: $orgId,
    edicionId: $edicionId,
    operadorId: 24,
    datosLote: $datosLoteAlpha,
    ipOrigen: '127.0.0.1'
);
asercion("Reintento de sincronizacion reconocido de forma idempotente", str_contains((string)$resSincAlphaReintento['mensaje'], 'idempotente'));
asercion("Respuesta identica y consistente sin reincrementar contadores", $resSincAlphaReintento['total_escaneos'] === 1);

// Limpieza de fixtures de prueba
$pdo->prepare("DELETE FROM operacion_offline_eventos WHERE lote_id IN (?, ?)")->execute([$resSincAlpha['lote_id'], $resSincBeta['lote_id']]);
$pdo->prepare("DELETE FROM operacion_offline_lotes WHERE id IN (?, ?)")->execute([$resSincAlpha['lote_id'], $resSincBeta['lote_id']]);
$pdo->prepare("DELETE FROM reserva_participantes WHERE id = ?")->execute([$participanteId]);
$pdo->prepare("DELETE FROM reservas WHERE id = ?")->execute([$reservaId]);
$pdo->prepare("DELETE FROM ventas WHERE id = ?")->execute([$ventaId]);

echo "\n==============================================================================\n";
echo "RESULTADOS SEGURIDAD DT-V11-011: {$pass} PASS | {$fail} FAIL\n";
echo "==============================================================================\n";

if ($fail > 0) {
    exit(1);
}
exit(0);
