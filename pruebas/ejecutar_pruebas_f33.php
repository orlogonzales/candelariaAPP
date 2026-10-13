<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Automatizadas — Fase 3.3: Operación Móvil y Validación QR sin Conexión (DT-V11-010)
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

$pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();

$totalPruebas = 0;
$pruebasPasadas = 0;
$pruebasFallidas = 0;

function assertF33(bool $condicion, string $descripcion): void
{
    global $totalPruebas, $pruebasPasadas, $pruebasFallidas;
    $totalPruebas++;
    if ($condicion) {
        $pruebasPasadas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$descripcion}\n";
    }
}

echo "==============================================================================\n";
echo "CANDELARIAAPP V1.1 — SUITE DE PRUEBAS FASE 3.3 (OPERACIÓN MÓVIL Y QR OFFLINE)\n";
echo "==============================================================================\n\n";

$offlineRepo = new \Aplicacion\Repositorios\OperacionOfflineRepositorio($pdo);
$offlineServicio = new \Aplicacion\Operaciones\OperacionOfflineServicio(offlineRepo: $offlineRepo, pdo: $pdo);

// ------------------------------------------------------------------------------
// PREPARACIÓN DE FIXTURES DE PRUEBA
// ------------------------------------------------------------------------------
$orgId = (int) $pdo->query("SELECT id FROM organizaciones ORDER BY id ASC LIMIT 1")->fetchColumn();
$edicionId = (int) $pdo->query("SELECT id FROM ediciones_candelaria WHERE organizacion_id = {$orgId} ORDER BY id ASC LIMIT 1")->fetchColumn();
$operadorId = (int) $pdo->query("SELECT id FROM usuarios WHERE organizacion_id = {$orgId} ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($operadorId <= 0) {
    $operadorId = (int) $pdo->query("SELECT id FROM usuarios ORDER BY id ASC LIMIT 1")->fetchColumn();
}
$clienteId = (int) $pdo->query("SELECT id FROM clientes WHERE organizacion_id = {$orgId} ORDER BY id ASC LIMIT 1")->fetchColumn();

// Crear venta y reserva de prueba para validar el ciclo de vida
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM `operacion_offline_eventos` WHERE `organizacion_id` = {$orgId}");
$pdo->exec("DELETE FROM `operacion_offline_lotes` WHERE `organizacion_id` = {$orgId}");
$pdo->exec("DELETE FROM `reservas` WHERE `correlativo` LIKE 'TEST-F33-%'");
$pdo->exec("DELETE FROM `ventas` WHERE `correlativo` LIKE 'V-F33-%'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// Insertar venta 1 (PAGADA)
$stmtV1 = $pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `creado_por`,
        `origen_tipo`, `correlativo`, `fecha_venta`, `moneda`, `cliente_nombre_completo`,
        `subtotal`, `total`, `monto_pagado`, `saldo_pendiente`,
        `estado`, `estado_financiero`, `creado_en`
    ) VALUES (
        :org_id, :ed_id, :cli_id, :op_id,
        'DIRECTA', 'V-F33-001', CURRENT_DATE, 'PEN', 'Juan Carlos Pérez Ramos',
        118.00, 118.00, 118.00, 0.00,
        'CONFIRMADA', 'PAGADA_TOTAL', CURRENT_TIMESTAMP
    )
");
$stmtV1->execute(['org_id' => $orgId, 'ed_id' => $edicionId, 'cli_id' => $clienteId, 'op_id' => $operadorId]);
$venta1Id = (int) $pdo->lastInsertId();

// Insertar venta 2
$stmtV2 = $pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `creado_por`,
        `origen_tipo`, `correlativo`, `fecha_venta`, `moneda`, `cliente_nombre_completo`,
        `subtotal`, `total`, `monto_pagado`, `saldo_pendiente`,
        `estado`, `estado_financiero`, `creado_en`
    ) VALUES (
        :org_id, :ed_id, :cli_id, :op_id,
        'DIRECTA', 'V-F33-002', CURRENT_DATE, 'PEN', 'María Elena Flores',
        118.00, 118.00, 118.00, 0.00,
        'CONFIRMADA', 'PAGADA_TOTAL', CURRENT_TIMESTAMP
    )
");
$stmtV2->execute(['org_id' => $orgId, 'ed_id' => $edicionId, 'cli_id' => $clienteId, 'op_id' => $operadorId]);
$venta2Id = (int) $pdo->lastInsertId();

// Insertar venta 3
$stmtV3 = $pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `creado_por`,
        `origen_tipo`, `correlativo`, `fecha_venta`, `moneda`, `cliente_nombre_completo`,
        `subtotal`, `total`, `monto_pagado`, `saldo_pendiente`,
        `estado`, `estado_financiero`, `creado_en`
    ) VALUES (
        :org_id, :ed_id, :cli_id, :op_id,
        'DIRECTA', 'V-F33-003', CURRENT_DATE, 'PEN', 'Pedro Quispe Mamani',
        118.00, 118.00, 118.00, 0.00,
        'CONFIRMADA', 'PAGADA_TOTAL', CURRENT_TIMESTAMP
    )
");
$stmtV3->execute(['org_id' => $orgId, 'ed_id' => $edicionId, 'cli_id' => $clienteId, 'op_id' => $operadorId]);
$venta3Id = (int) $pdo->lastInsertId();

// Insertar reserva 1 válida
$stmtR1 = $pdo->prepare("
    INSERT INTO `reservas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `venta_id`, `creado_por`,
        `correlativo`, `contacto_nombre`, `contacto_telefono`,
        `estado`, `creado_en`
    ) VALUES (
        :org_id, :ed_id, :cli_id, :v_id, :op_id,
        'TEST-F33-001', 'Juan Carlos Pérez Ramos', '+51951234567',
        'CONFIRMADA', CURRENT_TIMESTAMP
    )
");
$stmtR1->execute(['org_id' => $orgId, 'ed_id' => $edicionId, 'cli_id' => $clienteId, 'v_id' => $venta1Id, 'op_id' => $operadorId]);
$reserva1Id = (int) $pdo->lastInsertId();

// Insertar reserva 2 (CANCELADA)
$stmtR2 = $pdo->prepare("
    INSERT INTO `reservas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `venta_id`, `creado_por`,
        `correlativo`, `contacto_nombre`, `contacto_telefono`,
        `estado`, `creado_en`
    ) VALUES (
        :org_id, :ed_id, :cli_id, :v_id, :op_id,
        'TEST-F33-002', 'María Elena Flores', '+51951987654',
        'CANCELADA', CURRENT_TIMESTAMP
    )
");
$stmtR2->execute(['org_id' => $orgId, 'ed_id' => $edicionId, 'cli_id' => $clienteId, 'v_id' => $venta2Id, 'op_id' => $operadorId]);
$reserva2Id = (int) $pdo->lastInsertId();

// Insertar reserva 3 (YA CANJEADA PREVIAMENTE)
$stmtR3 = $pdo->prepare("
    INSERT INTO `reservas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `venta_id`, `creado_por`,
        `correlativo`, `contacto_nombre`, `contacto_telefono`,
        `estado`, `notas_operativas`, `creado_en`
    ) VALUES (
        :org_id, :ed_id, :cli_id, :v_id, :op_id,
        'TEST-F33-003', 'Pedro Quispe Mamani', '+51951112233',
        'CONFIRMADA', '[CHECKIN_QR_REGISTRADO: 2026-10-12 10:00:00]', CURRENT_TIMESTAMP
    )
");
$stmtR3->execute(['org_id' => $orgId, 'ed_id' => $edicionId, 'cli_id' => $clienteId, 'v_id' => $venta3Id, 'op_id' => $operadorId]);
$reserva3Id = (int) $pdo->lastInsertId();

// ------------------------------------------------------------------------------
// BLOQUE 1: DESCARGA DEL PAQUETE DE VALIDACIÓN OFFLINE (MICROFASE 3.3A / 3.3C)
// ------------------------------------------------------------------------------
echo "--- BLOQUE 1: GENERACIÓN Y DESCARGA DEL PAQUETE OFFLINE ---\n";

$paquete = $offlineServicio->descargarPaqueteOffline(
    organizacionId: $orgId,
    edicionId: $edicionId,
    operadorId: $operadorId
);

assertF33(is_array($paquete), "Generación exitosa del paquete de validación offline");
assertF33(!empty($paquete['lote_uuid']), "Paquete incluye UUID único de lote de sesión de campo");
assertF33(!empty($paquete['firma_paquete']), "Paquete incluye firma criptográfica HMAC-SHA256 para integridad");
assertF33(isset($paquete['resumen_credenciales']), "Paquete incluye catálogo de credenciales en caché");
assertF33(isset($paquete['lista_revocacion']), "Paquete incluye lista de revocaciones activas");

// Enmascaramiento estricto de PII
$hashR1 = hash('sha256', "CORR:{$orgId}:TEST-F33-001");
assertF33(isset($paquete['resumen_credenciales'][$hashR1]), "Reserva TEST-F33-001 presente en el catálogo de credenciales válidas");
$titularMascarado = $paquete['resumen_credenciales'][$hashR1]['titular_mascarado'] ?? '';
assertF33(!empty($titularMascarado) && !str_contains($titularMascarado, 'Carlos') && str_contains($titularMascarado, '*'), "Nombre del titular enmascarado conforme al principio de privacidad: {$titularMascarado}");

// Presencia en lista de revocación
$hashR2 = hash('sha256', "CORR:{$orgId}:TEST-F33-002"); // Cancelada
$hashR3 = hash('sha256', "CORR:{$orgId}:TEST-F33-003"); // Ya Canjeada
$revocadasHashes = array_column($paquete['lista_revocacion'], 'qr_hash');
assertF33(in_array($hashR2, $revocadasHashes, true), "Reserva CANCELADA (TEST-F33-002) incorporada a la lista de revocación offline");
assertF33(in_array($hashR3, $revocadasHashes, true), "Reserva YA CANJEADA (TEST-F33-003) incorporada a la lista de revocación offline");

// ------------------------------------------------------------------------------
// BLOQUE 2: MOTOR DE VALIDACIÓN LOCAL OFFLINE EN DISPOSITIVO (MICROFASE 3.3C)
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 2: MOTOR DE VALIDACIÓN LOCAL EN DISPOSITIVO ---\n";

// 1. Escaneo válido provisional
$resValido = $offlineServicio->validarCredencialLocal($paquete, 'TEST-F33-001');
assertF33($resValido['valido'] === true, "Credencial activa validada como VÁLIDA");
assertF33($resValido['resultado_codigo'] === 'VALIDO_LOCAL_PROVISIONAL', "Resultado calificado correctamente como VALIDO_LOCAL_PROVISIONAL");
assertF33($resValido['es_provisional'] === true, "Marcado explícitamente como provisional (requiere confirmación en servidor)");

// 2. Control Anti-Replay Local (Doble escaneo en el mismo dispositivo)
$escaneosPrevios = [$hashR1];
$resDuplicadoLocal = $offlineServicio->validarCredencialLocal($paquete, 'TEST-F33-001', $escaneosPrevios);
assertF33($resDuplicadoLocal['valido'] === false, "Doble escaneo local rechazado de inmediato");
assertF33($resDuplicadoLocal['resultado_codigo'] === \Aplicacion\Operaciones\OperacionOfflineEvento::RESULTADO_CONFLICTO_DUPLICADO, "Calificado como CONFLICTO_DUPLICADO local");

// 3. Credencial Revocada
$resRevocada = $offlineServicio->validarCredencialLocal($paquete, 'TEST-F33-002');
assertF33($resRevocada['valido'] === false, "Credencial cancelada rechazada localmente");
assertF33($resRevocada['resultado_codigo'] === \Aplicacion\Operaciones\OperacionOfflineEvento::RESULTADO_RECHAZADO_REVOCADO, "Calificado como RECHAZADO_REVOCADO");

// 4. Credencial Inexistente / Desconocida
$resInexistente = $offlineServicio->validarCredencialLocal($paquete, 'INVENTADO-999');
assertF33($resInexistente['valido'] === false, "Credencial desconocida rechazada localmente");
assertF33($resInexistente['resultado_codigo'] === \Aplicacion\Operaciones\OperacionOfflineEvento::RESULTADO_RECHAZADO_INEXISTENTE, "Calificado como RECHAZADO_INEXISTENTE");

// 5. Paquete vencido
$paqueteExpirado = $paquete;
$paqueteExpirado['expira_en'] = '2020-01-01 00:00:00';
$resExpirado = $offlineServicio->validarCredencialLocal($paqueteExpirado, 'TEST-F33-001');
assertF33($resExpirado['valido'] === false, "Paquete fuera de vigencia de turno rechazado");
assertF33($resExpirado['resultado_codigo'] === \Aplicacion\Operaciones\OperacionOfflineEvento::RESULTADO_RECHAZADO_EXPIRADO, "Calificado como RECHAZADO_EXPIRADO");

// ------------------------------------------------------------------------------
// BLOQUE 3: VALIDACIÓN DIRECTA ONLINE (FALLBACK ONLINE)
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 3: VALIDACIÓN DIRECTA ONLINE ---\n";

// Crear token QR estructurado auténtico para TEST-F33-001
$secretoQr = hash('sha256', 'candelaria_qr_secret_org_' . $orgId);
$payloadToken = [
    'organizacion_id' => $orgId,
    'reserva_id'      => $reserva1Id,
    'correlativo'     => 'TEST-F33-001',
    'titular'         => 'Juan Carlos Pérez Ramos',
];
$payloadJson = json_encode($payloadToken, JSON_UNESCAPED_UNICODE);
$firmaToken = hash_hmac('sha256', $payloadJson, $secretoQr);
$tokenQrAutentico = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=') . '.' . $firmaToken;

// Token alterado / apócrifo
$tokenQrAdulterado = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=') . '.firma_falsa_1234567890abcdef';

$resFirmaInvalida = $offlineServicio->validarCredencialOnline(
    organizacionId: $orgId,
    edicionId: $edicionId,
    operadorId: $operadorId,
    tokenQr: $tokenQrAdulterado,
    marcarIngreso: false
);
assertF33($resFirmaInvalida['valido'] === false, "Token QR con firma adulterada rechazado");
assertF33(str_contains($resFirmaInvalida['motivo'], 'adulterada') || str_contains($resFirmaInvalida['motivo'], 'inválida'), "Mensaje explícito de firma digital adulterada");

// Validación online por correlativo manual
$resManualOnline = $offlineServicio->validarCredencialOnline(
    organizacionId: $orgId,
    edicionId: $edicionId,
    operadorId: $operadorId,
    tokenQr: 'TEST-F33-001',
    marcarIngreso: false
);
assertF33($resManualOnline['valido'] === true, "Validación online directa de correlativo manual aprobada");
assertF33($resManualOnline['resultado_codigo'] === 'CONFIRMADO', "Calificado como CONFIRMADO");

// ------------------------------------------------------------------------------
// BLOQUE 4: SINCRONIZACIÓN Y CONCILIACIÓN POR LOTES (MICROFASE 3.3D)
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 4: SINCRONIZACIÓN POR LOTES Y CONCILIACIÓN TRANSACCIONAL ---\n";

$loteUuidTest = 'lote-test-' . bin2hex(random_bytes(6));
$idemp1 = 'idemp_test_' . bin2hex(random_bytes(8));
$idemp2 = 'idemp_test_' . bin2hex(random_bytes(8));

$datosLote = [
    'lote_uuid'        => $loteUuidTest,
    'dispositivo_info' => 'Dispositivo Móvil Test Chrome Headless',
    'eventos'          => [
        [
            'idempotencia_hash' => $idemp1,
            'token_qr'          => 'TEST-F33-001',
            'resultado_local'   => 'VALIDO_PROVISIONAL',
            'escaneado_en'      => '2026-10-12 12:30:00',
        ],
        [
            'idempotencia_hash' => $idemp2,
            'token_qr'          => 'TEST-F33-002', // Cancelada
            'resultado_local'   => 'VALIDO_PROVISIONAL',
            'escaneado_en'      => '2026-10-12 12:31:00',
        ],
    ]
];

$resultadoSync = $offlineServicio->sincronizarLoteOffline(
    organizacionId: $orgId,
    edicionId: $edicionId,
    operadorId: $operadorId,
    datosLote: $datosLote,
    ipOrigen: '192.168.1.50'
);

assertF33($resultadoSync['exito'] === true, "Sincronización de lote ejecutada con éxito");
assertF33($resultadoSync['total_escaneos'] === 2, "Procesamiento de 2 escaneos en el lote");
assertF33($resultadoSync['total_confirmados'] === 1, "Exactamente 1 escaneo confirmado (TEST-F33-001)");
assertF33($resultadoSync['total_rechazados'] === 1, "Exactamente 1 escaneo rechazado por cancelación (TEST-F33-002)");

// Verificar actualización atómica en reservas
$stmtCheck = $pdo->prepare("SELECT notas_operativas FROM `reservas` WHERE `id` = :id");
$stmtCheck->execute(['id' => $reserva1Id]);
$notasActualizadas = (string) $stmtCheck->fetchColumn();
assertF33(str_contains($notasActualizadas, '[CHECKIN_QR_OFFLINE_SYNC:'), "Check-in oficial asentado en notas_operativas de la reserva");

// ------------------------------------------------------------------------------
// BLOQUE 5: CONCURRENCIA, DOBLE GASTO CROSS-DEVICE E IDEMPOTENCIA
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 5: CONTROL DE CONCURRENCIA, DOBLE GASTO E IDEMPOTENCIA ---\n";

// 1. Idempotencia de Lote completo
$resultadoReSync = $offlineServicio->sincronizarLoteOffline(
    organizacionId: $orgId,
    edicionId: $edicionId,
    operadorId: $operadorId,
    datosLote: $datosLote
);
assertF33($resultadoReSync['exito'] === true, "Re-sincronización del mismo lote procesada sin fallos");
assertF33(str_contains($resultadoReSync['mensaje'], 'idempotente'), "Re-sincronización identificada como operación idempotente");

// 2. Conflicto de Doble Gasto Cross-Device (Otro operador intenta sincronizar el mismo TEST-F33-001 en otro lote)
$loteUuidOperador2 = 'lote-operador2-' . bin2hex(random_bytes(6));
$datosLote2 = [
    'lote_uuid'        => $loteUuidOperador2,
    'dispositivo_info' => 'Dispositivo 2 Operador Campo',
    'eventos'          => [
        [
            'idempotencia_hash' => 'idemp_op2_' . bin2hex(random_bytes(8)),
            'token_qr'          => 'TEST-F33-001', // Ya canjeada en el lote anterior
            'resultado_local'   => 'VALIDO_PROVISIONAL',
            'escaneado_en'      => '2026-10-12 12:35:00',
        ],
    ]
];

$resultadoSync2 = $offlineServicio->sincronizarLoteOffline(
    organizacionId: $orgId,
    edicionId: $edicionId,
    operadorId: $operadorId,
    datosLote: $datosLote2
);

assertF33($resultadoSync2['total_conflictos'] === 1, "Doble gasto cross-device detectado como conflicto (total_conflictos = 1)");
assertF33($resultadoSync2['total_confirmados'] === 0, "No se permite segundo ingreso para la misma credencial");
assertF33($resultadoSync2['estado'] === \Aplicacion\Operaciones\OperacionOfflineLote::ESTADO_CON_CONFLICTOS, "Lote catalogado como ESTADO_CON_CONFLICTOS");

// ------------------------------------------------------------------------------
// BLOQUE 6: CONSULTA DE HISTORIAL Y TRAZABILIDAD (MIGRACIÓN 28 / RBAC 114)
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 6: HISTORIAL DE LOTES, AUDITORÍA Y PERMISOS ---\n";

$historial = $offlineRepo->listarLotesPorOrganizacion($orgId, $edicionId);
assertF33(count($historial) >= 2, "Historial de lotes contiene los lotes sincronizados de la sesión");

// Validar existencia de permiso 114
$stmtPerm = $pdo->prepare("SELECT id, codigo, nombre FROM permisos WHERE codigo = 'operacion.offline.validar'");
$stmtPerm->execute();
$permiso114 = $stmtPerm->fetch(PDO::FETCH_ASSOC);
assertF33($permiso114 !== false, "Permiso 114 'operacion.offline.validar' registrado formalmente en base de datos");

// Limpieza final de fixtures
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM `operacion_offline_eventos` WHERE `organizacion_id` = {$orgId}");
$pdo->exec("DELETE FROM `operacion_offline_lotes` WHERE `organizacion_id` = {$orgId}");
$pdo->exec("DELETE FROM `reservas` WHERE `correlativo` LIKE 'TEST-F33-%'");
$pdo->exec("DELETE FROM `ventas` WHERE `correlativo` LIKE 'V-F33-%'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\n==============================================================================\n";
echo "RESULTADO SUITE FASE 3.3: {$totalPruebas} PRUEBAS EVALUADAS | {$pruebasPasadas} PASS | {$pruebasFallidas} FAIL\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
