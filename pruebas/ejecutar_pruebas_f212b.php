<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 2.12B
 * REPORTES OFICIALES EXPORTABLES DE CIERRE Y AUDITORÍA
 * ==============================================================================
 * 1. Libro Oficial de Ingresos y Cobranzas (devengado, cobrado, comisiones, saldos).
 * 2. Manifiesto de Salidas Operativas (flota, capacidad, pasajeros, guía y conductor).
 * 3. Padrón Oficial de Asistencia y Check-in (asistieron, no-show, tasa %).
 * 4. Minimización estricta de datos personales según Ley N° 29733 (enmascaramiento).
 * 5. Neutralización rigurosa de Inyección de Fórmulas en CSV (=,+,-,@) y UTF-8 BOM.
 * 6. Exportación XLSX estructurada (SpreadsheetML) con tipos y estilos.
 * 7. Exportación PDF Institucional con membrete, hash SHA-256 y aviso legal no SUNAT.
 * 8. Protección Anti-IDOR y aislamiento multitenant estricto.
 * 9. Controlador EdicionControlador y endpoints HTTP de descarga.
 * 10. Preservación histórica inmutable de Orlando (ID 24) con huella 80e6af84e02e89e3.
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Controladores\EdicionControlador;
use Aplicacion\Reportes\ReporteCierreServicio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;

$pdo = Conexion::obtenerInstancia();

$pruebasEjecutadas = 0;
$pruebasExitosas = 0;
$pruebasFallidas = 0;

function afirmar(bool $condicion, string $mensaje): void
{
    global $pruebasEjecutadas, $pruebasExitosas, $pruebasFallidas;
    $pruebasEjecutadas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$mensaje}\n";
    }
}

echo "==============================================================================\n";
echo "INICIANDO SUITE F2.12B: REPORTES OFICIALES EXPORTABLES DE CIERRE Y AUDITORÍA\n";
echo "==============================================================================\n\n";

$stmtOrg = $pdo->query("SELECT id FROM `organizaciones` ORDER BY id ASC LIMIT 1");
$orgId = (int) $stmtOrg->fetchColumn();

// Limpieza preventiva de ediciones residuales de pruebas previas interrumpidas
$stmtResidual = $pdo->query("SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'TEST-REP-%'");
$resIds = $stmtResidual->fetchAll(PDO::FETCH_COLUMN);
foreach ($resIds as $rEdId) {
    $pdo->exec("DELETE FROM `documentos_emitidos` WHERE entidad_tipo = 'RESERVA' AND entidad_id IN (SELECT id FROM `reservas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `reserva_participantes` WHERE reserva_id IN (SELECT id FROM `reservas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `operacion_salida_prestaciones` WHERE salida_id IN (SELECT id FROM `operacion_salidas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `reserva_prestaciones` WHERE reserva_id IN (SELECT id FROM `reservas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `reservas` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `recibos_caja` WHERE venta_id IN (SELECT id FROM `ventas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `pagos` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `venta_lineas` WHERE venta_id IN (SELECT id FROM `ventas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `ventas` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `operacion_salida_recursos` WHERE salida_id IN (SELECT id FROM `operacion_salidas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `operacion_salidas` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `ofertas_items_edicion` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `ediciones_candelaria` WHERE id = {$rEdId}");
}

// 1. SETUP DE FIXTURES DE PRUEBA
$codigoEdicion = 'TEST-REP-' . substr(bin2hex(random_bytes(4)), 0, 8);
$aniosUsados = array_map('intval', $pdo->query("SELECT anio FROM `ediciones_candelaria` WHERE organizacion_id = {$orgId}")->fetchAll(PDO::FETCH_COLUMN));
$anioTest = 2050;
while (in_array($anioTest, $aniosUsados, true) && $anioTest <= 2099) {
    $anioTest++;
}

$stmtEd = $pdo->prepare("
    INSERT INTO `ediciones_candelaria` 
    (organizacion_id, codigo, nombre, anio, estado, fecha_inicio, fecha_fin, descripcion, es_actual)
    VALUES (:org, :cod, :nom, :anio, 'OPERACION', '2026-02-01', '2026-02-15', 'Edicion para pruebas de reportes oficiales F2.12B', 0)
");
$stmtEd->execute([
    ':org'  => $orgId,
    ':cod'  => $codigoEdicion,
    ':nom'  => "Festividad Candelaria {$codigoEdicion}",
    ':anio' => $anioTest,
]);
$edicionId = (int)$pdo->lastInsertId();

// Item Comercial
$stmtIc = $pdo->prepare("SELECT id FROM `items_comerciales` WHERE organizacion_id = :org LIMIT 1");
$stmtIc->execute([':org' => $orgId]);
$itemId = $stmtIc->fetchColumn();
if (!$itemId) {
    $stmtInsIc = $pdo->prepare("
        INSERT INTO `items_comerciales` (organizacion_id, codigo, nombre, tipo_item, precio_base, estado)
        VALUES (:org, 'ITEM-REP-01', 'Tour Oficial Candelaria', 'PAQUETE_SERVICIOS', 500.00, 'ACTIVO')
    ");
    $stmtInsIc->execute([':org' => $orgId]);
    $itemId = (int)$pdo->lastInsertId();
} else {
    $itemId = (int)$itemId;
}

// Oferta para la edición de prueba
$stmtOferta = $pdo->prepare("
    INSERT INTO `ofertas_items_edicion` (organizacion_id, edicion_id, item_comercial_id, estado)
    VALUES (:org, :ed, :item, 'ACTIVO')
");
$stmtOferta->execute([':org' => $orgId, ':ed' => $edicionId, ':item' => $itemId]);
$ofertaItemId = (int)$pdo->lastInsertId();

// Recurso Físico: Vehículo
$codRecurso = 'BUS-TEST-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtRecFis = $pdo->prepare("
    INSERT INTO `operacion_recursos`
    (organizacion_id, tipo_recurso, codigo_interno, nombre, capacidad_maxima, identificacion_oficial, estado)
    VALUES
    (:org, 'VEHICULO_TERRESTRE', :cod, 'Mercedes Sprinter', 20, 'X1Y-890', 'DISPONIBLE')
");
$stmtRecFis->execute([':org' => $orgId, ':cod' => $codRecurso]);
$recursoFisicoId = (int)$pdo->lastInsertId();

// Personas: Conductor y Guía Oficial
$dniCond = '70' . rand(100000, 999999);
$stmtPerCond = $pdo->prepare("
    INSERT INTO `personas` (organizacion_id, tipo_persona, tipo_documento_id, numero_documento, nombres, apellidos)
    VALUES (:org, 'NATURAL', 1, :dni, 'Marcos', 'Condori')
");
$stmtPerCond->execute([':org' => $orgId, ':dni' => $dniCond]);
$personaCondId = (int)$pdo->lastInsertId();

$dniGuia = '70' . rand(100000, 999999);
$stmtPerGuia = $pdo->prepare("
    INSERT INTO `personas` (organizacion_id, tipo_persona, tipo_documento_id, numero_documento, nombres, apellidos)
    VALUES (:org, 'NATURAL', 1, :dni, 'Diana', 'Quispe')
");
$stmtPerGuia->execute([':org' => $orgId, ':dni' => $dniGuia]);
$personaGuiaId = (int)$pdo->lastInsertId();

// Salida Operativa de prueba
$stmtSalida = $pdo->prepare("
    INSERT INTO `operacion_salidas` 
    (organizacion_id, edicion_id, item_comercial_id, correlativo, titulo, fecha_salida, hora_citacion, hora_salida, punto_encuentro, tipo_capacidad, capacidad_maxima, estado, creado_por)
    VALUES 
    (:org, :ed, :ic, :corr, 'Salida Lago Sagrado', '2026-02-10', '07:30:00', '08:00:00', 'Plaza Mayor Puno', 'COLECTIVA', 20, 'PROGRAMADA', 24)
");
$corrSalida = 'SAL-REP-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtSalida->execute([
    ':org'  => $orgId,
    ':ed'   => $edicionId,
    ':ic'   => $itemId,
    ':corr' => $corrSalida
]);
$salidaId = (int)$pdo->lastInsertId();

// Asignar recursos a la salida
$pdo->prepare("
    INSERT INTO `operacion_salida_recursos` (salida_id, persona_id, rol_operativo, asignado_por)
    VALUES (:sid, :pid, 'CONDUCTOR', 24)
")->execute([':sid' => $salidaId, ':pid' => $personaCondId]);

$pdo->prepare("
    INSERT INTO `operacion_salida_recursos` (salida_id, persona_id, rol_operativo, asignado_por)
    VALUES (:sid, :pid, 'GUIA_PRINCIPAL', 24)
")->execute([':sid' => $salidaId, ':pid' => $personaGuiaId]);

$pdo->prepare("
    INSERT INTO `operacion_salida_recursos` (salida_id, recurso_fisico_id, rol_operativo, asignado_por)
    VALUES (:sid, :rid, 'EQUIPO_LOGISTICO', 24)
")->execute([':sid' => $salidaId, ':rid' => $recursoFisicoId]);

// Clientes para las ventas
$stmtCli = $pdo->query("SELECT id FROM `clientes` WHERE organizacion_id = {$orgId} LIMIT 2");
$clientes = $stmtCli->fetchAll(PDO::FETCH_COLUMN);
$cli1 = !empty($clientes[0]) ? (int)$clientes[0] : 1;
$cli2 = !empty($clientes[1]) ? (int)$clientes[1] : 1;

// Venta 1: Devengado 500, Pagado 300 (Efectivo manual), Saldo 200, PAGO_PARCIAL
$stmtV1 = $pdo->prepare("
    INSERT INTO `ventas`
    (organizacion_id, edicion_id, cliente_id, correlativo, origen_tipo, estado, estado_financiero, moneda, subtotal, total, monto_pagado, saldo_pendiente, cliente_nombre_completo, cliente_numero_documento, cliente_email, fecha_venta, creado_por)
    VALUES
    (:org, :ed, :cli, :corr, 'DIRECTA', 'CONFIRMADA', 'PAGO_PARCIAL', 'PEN', 500.00, 500.00, 300.00, 200.00, 'Juan Carlos Quispe', '72819283', 'juan.c@example.com', '2026-02-05', 24)
");
$corrV1 = 'VEN-REP-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtV1->execute([':org' => $orgId, ':ed' => $edicionId, ':cli' => $cli1, ':corr' => $corrV1]);
$ventaId1 = (int)$pdo->lastInsertId();

$corrP1 = 'PAG-REP-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtP1 = $pdo->prepare("
    INSERT INTO `pagos`
    (organizacion_id, edicion_id, venta_id, correlativo, metodo_pago, estado, moneda, monto_cobrado_cliente, comision_pasarela, monto_neto_recibido, monto_aplicado_venta, fecha_pago, numero_operacion_bancaria, creado_por)
    VALUES
    (:org, :ed, :v, :corr, 'EFECTIVO', 'APROBADO', 'PEN', 300.00, 0.00, 300.00, 300.00, '2026-02-05 10:15:00', 'CAJA-MANUAL-01', 24)
");
$stmtP1->execute([':org' => $orgId, ':ed' => $edicionId, ':v' => $ventaId1, ':corr' => $corrP1]);
$pagoId1 = (int)$pdo->lastInsertId();

$corrR1 = 'REC-REP-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtR1 = $pdo->prepare("
    INSERT INTO `recibos_caja`
    (organizacion_id, venta_id, pago_id, correlativo, monto_recibido, moneda, metodo_pago, cajero_usuario_id, titular_nombre, concepto, emitido_en)
    VALUES
    (:org, :v, :p, :corr, 300.00, 'PEN', 'EFECTIVO', 24, 'Juan Carlos Quispe', 'Pago a cuenta de festividad', NOW())
");
$stmtR1->execute([':org' => $orgId, ':v' => $ventaId1, ':p' => $pagoId1, ':corr' => $corrR1]);

// Venta 2: Devengado 1000, Pagado 1000 (Pasarela Culqi, comisión 45, neto 955), Saldo 0, PAGADA_TOTAL
$stmtV2 = $pdo->prepare("
    INSERT INTO `ventas`
    (organizacion_id, edicion_id, cliente_id, correlativo, origen_tipo, estado, estado_financiero, moneda, subtotal, total, monto_pagado, saldo_pendiente, cliente_nombre_completo, cliente_numero_documento, cliente_email, fecha_venta, creado_por)
    VALUES
    (:org, :ed, :cli, :corr, 'DIRECTA', 'CONFIRMADA', 'PAGADA_TOTAL', 'PEN', 1000.00, 1000.00, 1000.00, 0.00, 'Lucia Fernandez Mamani', '10293847', 'lucia.f@example.com', '2026-02-06', 24)
");
$corrV2 = 'VEN-REP-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtV2->execute([':org' => $orgId, ':ed' => $edicionId, ':cli' => $cli2, ':corr' => $corrV2]);
$ventaId2 = (int)$pdo->lastInsertId();

$corrP2 = 'PAG-REP-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtP2 = $pdo->prepare("
    INSERT INTO `pagos`
    (organizacion_id, edicion_id, venta_id, correlativo, metodo_pago, estado, moneda, monto_cobrado_cliente, comision_pasarela, monto_neto_recibido, monto_aplicado_venta, fecha_pago, numero_operacion_bancaria, creado_por)
    VALUES
    (:org, :ed, :v, :corr, 'TARJETA_PASARELA', 'APROBADO', 'PEN', 1000.00, 45.00, 955.00, 1000.00, '2026-02-06 14:35:00', 'CULQI-CHG-987654', 24)
");
$stmtP2->execute([':org' => $orgId, ':ed' => $edicionId, ':v' => $ventaId2, ':corr' => $corrP2]);
$pagoId2 = (int)$pdo->lastInsertId();

// Reservas para Padrón de Asistencia
// Reserva 1: Asistió (Check-in con timestamp en notas_operativas)
$corrRes1 = 'RES-REP-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtRes1 = $pdo->prepare("
    INSERT INTO `reservas`
    (organizacion_id, edicion_id, venta_id, cliente_id, correlativo, estado, contacto_nombre, contacto_tipo_documento, contacto_numero_documento, contacto_telefono, contacto_email, notas_operativas, creado_por)
    VALUES
    (:org, :ed, :v, :cli, :corr, 'CONFIRMADA', 'Juan Carlos Quispe', 'DNI', '72819283', '987654321', 'juan.c@example.com', '[CHECKIN_QR_REGISTRADO: 2026-02-10 08:05:00]', 24)
");
$stmtRes1->execute([':org' => $orgId, ':ed' => $edicionId, ':v' => $ventaId1, ':cli' => $cli1, ':corr' => $corrRes1]);
$reservaId1 = (int)$pdo->lastInsertId();

// Reserva 2: Ausente / No-Show
$corrRes2 = 'RES-REP-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtRes2 = $pdo->prepare("
    INSERT INTO `reservas`
    (organizacion_id, edicion_id, venta_id, cliente_id, correlativo, estado, contacto_nombre, contacto_tipo_documento, contacto_numero_documento, contacto_telefono, contacto_email, notas_operativas, creado_por)
    VALUES
    (:org, :ed, :v, :cli, :corr, 'CONFIRMADA', 'Lucia Fernandez Mamani', 'DNI', '10293847', '987654322', 'lucia.f@example.com', '@NotaConArroba', 24)
");
$stmtRes2->execute([':org' => $orgId, ':ed' => $edicionId, ':v' => $ventaId2, ':cli' => $cli2, ':corr' => $corrRes2]);
$reservaId2 = (int)$pdo->lastInsertId();

// Venta Líneas para Venta 1 y Venta 2
$stmtVL1 = $pdo->prepare("
    INSERT INTO `venta_lineas`
    (venta_id, tipo_linea, item_comercial_id, oferta_item_id, concepto_codigo, concepto_nombre, unidad_medida, cantidad, precio_unitario, subtotal, moneda, orden)
    VALUES
    (:vid, 'ITEM', :item_id, :of_id, 'ITEM-REP-01', 'Tour Oficial Candelaria', 'SERVICIO', 1.00, 500.00, 500.00, 'PEN', 1)
");
$stmtVL1->execute([':vid' => $ventaId1, ':item_id' => $itemId, ':of_id' => $ofertaItemId]);
$lineaId1 = (int)$pdo->lastInsertId();

$stmtVL2 = $pdo->prepare("
    INSERT INTO `venta_lineas`
    (venta_id, tipo_linea, item_comercial_id, oferta_item_id, concepto_codigo, concepto_nombre, unidad_medida, cantidad, precio_unitario, subtotal, moneda, orden)
    VALUES
    (:vid, 'ITEM', :item_id, :of_id, 'ITEM-REP-01', 'Tour Oficial Candelaria', 'SERVICIO', 1.00, 1000.00, 1000.00, 'PEN', 1)
");
$stmtVL2->execute([':vid' => $ventaId2, ':item_id' => $itemId, ':of_id' => $ofertaItemId]);
$lineaId2 = (int)$pdo->lastInsertId();

// Prestaciones para vincular a la salida
$stmtPrest1 = $pdo->prepare("
    INSERT INTO `reserva_prestaciones` 
    (reserva_id, venta_linea_id, item_comercial_id, concepto_codigo, concepto_nombre, unidad_medida, cantidad, tipo_capacidad, requiere_agendamiento, requiere_participantes, estado_agendamiento, fecha_servicio)
    VALUES 
    (:r, :vl, :ic, 'TOUR-PUNO', 'Tour Lago Titicaca', 'SERVICIO', 1.00, 'COLECTIVA', 1, 1, 'PROGRAMADA', '2026-02-10')
");
$stmtPrest1->execute([':r' => $reservaId1, ':vl' => $lineaId1, ':ic' => $itemId]);
$prestacionId1 = (int)$pdo->lastInsertId();

$stmtPrest2 = $pdo->prepare("
    INSERT INTO `reserva_prestaciones` 
    (reserva_id, venta_linea_id, item_comercial_id, concepto_codigo, concepto_nombre, unidad_medida, cantidad, tipo_capacidad, requiere_agendamiento, requiere_participantes, estado_agendamiento, fecha_servicio)
    VALUES 
    (:r, :vl, :ic, 'TOUR-PUNO', 'Tour Lago Titicaca', 'SERVICIO', 1.00, 'COLECTIVA', 1, 1, 'PROGRAMADA', '2026-02-10')
");
$stmtPrest2->execute([':r' => $reservaId2, ':vl' => $lineaId2, ':ic' => $itemId]);
$prestacionId2 = (int)$pdo->lastInsertId();

// Vincular prestaciones a la salida operativa (2 pasajeros agendados en la salida)
$pdo->prepare("
    INSERT INTO `operacion_salida_prestaciones` (salida_id, prestacion_id, cantidad_pasajeros, asignado_por)
    VALUES (:sid, :pid, 1, 24)
")->execute([':sid' => $salidaId, ':pid' => $prestacionId1]);

$pdo->prepare("
    INSERT INTO `operacion_salida_prestaciones` (salida_id, prestacion_id, cantidad_pasajeros, asignado_por)
    VALUES (:sid, :pid, 1, 24)
")->execute([':sid' => $salidaId, ':pid' => $prestacionId2]);

// Participantes: Participante 1 (DNI 72819283) y Participante 2 con fórmula peligrosa
$pdo->prepare("
    INSERT INTO `reserva_participantes` (reserva_id, tipo_documento_id, numero_documento, nombres, apellidos, es_titular_reserva)
    VALUES (:r, 1, '72819283', 'Juan Carlos', 'Quispe', 1)
")->execute([':r' => $reservaId1]);

$nombreConFormula = '=cmd|\'/C calc\'!A0';
$pdo->prepare("
    INSERT INTO `reserva_participantes` (reserva_id, tipo_documento_id, numero_documento, nombres, apellidos, es_titular_reserva)
    VALUES (:r, 1, '10293847', :pnom, 'Mamani', 0)
")->execute([':r' => $reservaId2, ':pnom' => $nombreConFormula]);

// Documentos emitidos ficticios para simular código de seguridad y token QR
$tokenQr1 = hash('sha256', "TOKEN_QR_RES_1_{$reservaId1}");
$pdo->prepare("
    INSERT INTO `documentos_emitidos`
    (organizacion_id, tipo_documento, entidad_tipo, entidad_id, correlativo_documento, formato, version, es_vigente, estado_documento, ruta_archivo_privado, peso_bytes, hash_sha256, generado_por_usuario_id, cliente_titular_id, generado_en)
    VALUES
    (:org, 'BOLETO_A4', 'RESERVA', :r, 'BOL-A4-0001', 'A4', 1, 1, 'VIGENTE', 'privado/boletos/1.pdf', 1024, :h, 24, :cli, '2026-02-05 12:00:00')
")->execute([':org' => $orgId, ':r' => $reservaId1, ':h' => $tokenQr1, ':cli' => $cli1]);

$reporteServicio = new ReporteCierreServicio($pdo);

// ------------------------------------------------------------------------------
// SECCIÓN 1: LIBRO DE INGRESOS Y COBRANZAS (FINANZAS Y CONCILIACIÓN)
// ------------------------------------------------------------------------------
echo "--- SECCIÓN 1: LIBRO DE INGRESOS Y COBRANZAS ---\n";

$libro = $reporteServicio->obtenerLibroIngresos($orgId, $edicionId);

afirmar($libro['tipo_reporte'] === ReporteCierreServicio::TIPO_LIBRO_INGRESOS, "Tipo de reporte identificado como LIBRO_INGRESOS.");
afirmar(isset($libro['totales']), "Estructura incluye bloque de totales financieros.");
afirmar($libro['totales']['total_ventas'] === 1500.0, "Total de ventas devengadas conciliado: S/ 1,500.00 (esperado: 1500.00, obtenido: {$libro['totales']['total_ventas']}).");
afirmar($libro['totales']['total_cobrado'] === 1300.0, "Total de cobros aprobados conciliado: S/ 1,300.00 (esperado: 1300.00, obtenido: {$libro['totales']['total_cobrado']}).");
afirmar($libro['totales']['total_comisiones_pasarela'] === 45.0, "Total de comisiones de pasarela conciliado: S/ 45.00 (esperado: 45.00, obtenido: {$libro['totales']['total_comisiones_pasarela']}).");
afirmar($libro['totales']['total_neto_bancos'] === 1255.0, "Total neto en bancos conciliado: S/ 1,255.00 (esperado: 1255.00, obtenido: {$libro['totales']['total_neto_bancos']}).");
afirmar($libro['totales']['total_saldo_pendiente'] === 200.0, "Total saldo por cobrar conciliado: S/ 200.00 (esperado: 200.00, obtenido: {$libro['totales']['total_saldo_pendiente']}).");
afirmar(count($libro['items']) === 2, "Contiene 2 registros individuales de venta/pago.");

// Prueba de filtros en Libro de Ingresos
$libroFiltrado = $reporteServicio->obtenerLibroIngresos($orgId, $edicionId, ['estado_financiero' => 'PAGADA_TOTAL']);
afirmar(count($libroFiltrado['items']) === 1, "Filtro por estado_financiero = PAGADA_TOTAL retorna exactamente 1 venta.");
afirmar($libroFiltrado['items'][0]['correlativo_venta'] === $corrV2, "Venta filtrada corresponde a {$corrV2}.");

$libroFiltradoMetodo = $reporteServicio->obtenerLibroIngresos($orgId, $edicionId, ['metodo_pago' => 'EFECTIVO']);
afirmar(count($libroFiltradoMetodo['items']) === 1, "Filtro por metodo_pago = EFECTIVO retorna exactamente 1 registro.");
afirmar($libroFiltradoMetodo['items'][0]['correlativo_recibo'] === $corrR1, "Registro efectivo incluye correlativo del recibo de caja ({$corrR1}).");

// ------------------------------------------------------------------------------
// SECCIÓN 2: MANIFIESTO DE SALIDAS OPERATIVAS (FLOTA Y OPERACIONES)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 2: MANIFIESTO DE SALIDAS OPERATIVAS ---\n";

$manifiesto = $reporteServicio->obtenerManifiestoSalidas($orgId, $edicionId);

afirmar($manifiesto['tipo_reporte'] === ReporteCierreServicio::TIPO_MANIFIESTO_SALIDAS, "Tipo de reporte identificado como MANIFIESTO_SALIDAS.");
afirmar($manifiesto['totales']['total_salidas'] === 1, "Registra 1 salida operativa programada.");
afirmar($manifiesto['totales']['total_capacidad_flota'] === 20, "Capacidad total de flota calculada: 20 pasajeros.");
afirmar($manifiesto['totales']['total_pasajeros_asignados'] === 2, "Pasajeros agendados en la salida: 2.");
afirmar($manifiesto['totales']['tasa_ocupacion_flota_pct'] === 10.0, "Tasa de ocupación calculada exactamente: 10.0% (2 de 20).");

$salidaDetalle = $manifiesto['items'][0];
afirmar(str_contains($salidaDetalle['vehiculo_asignado'], 'X1Y-890'), "Placa de vehículo correcta: X1Y-890.");
afirmar($salidaDetalle['conductor_asignado'] === 'Marcos Condori', "Nombre de conductor verificado: Marcos Condori.");
afirmar($salidaDetalle['guia_asignado'] === 'Diana Quispe', "Guía turístico verificado: Diana Quispe.");

// ------------------------------------------------------------------------------
// SECCIÓN 3 Y 4: PADRÓN OFICIAL DE ASISTENCIA Y LEY 29733 (DATOS PERSONALES)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 3 Y 4: PADRÓN DE ASISTENCIA Y PRIVACIDAD LEY 29733 ---\n";

$padron = $reporteServicio->obtenerPadronAsistencia($orgId, $edicionId);

afirmar($padron['tipo_reporte'] === ReporteCierreServicio::TIPO_PADRON_ASISTENCIA, "Tipo de reporte identificado como PADRON_ASISTENCIA.");
afirmar($padron['totales']['total_participantes'] === 2, "Total de participantes en el padrón: 2.");
afirmar($padron['totales']['total_asistieron'] === 1, "Total que realizaron check-in: 1.");
afirmar($padron['totales']['total_ausentes'] === 1, "Total ausentes (no-show): 1.");
afirmar($padron['totales']['tasa_asistencia_pct'] === 50.0, "Tasa de asistencia calculada: 50.0%.");

$participante1 = $padron['items'][0];
afirmar($participante1['estado_checkin'] === 'INGRESADO', "Participante 1 tiene estado INGRESADO.");
afirmar($participante1['fecha_checkin'] === '2026-02-10 08:05:00', "Timestamp exacto de check-in preservado.");

// Verificación de Minimización según Ley 29733
afirmar(!str_contains($participante1['documento_minimizado'], '72819283'), "Documento original '72819283' NO se expone en texto plano.");
afirmar(str_contains($participante1['documento_minimizado'], '****'), "Documento de identidad debidamente enmascarado (p. ej. '72****83').");
afirmar(!str_contains($participante1['token_qr_resumido'], $tokenQr1), "Token QR secreto no se expone íntegro en el reporte (token resumido: {$participante1['token_qr_resumido']}).");

// ------------------------------------------------------------------------------
// SECCIÓN 5: NEUTRALIZACIÓN ANTI-FORMULA INJECTION EN CSV Y BOM UTF-8
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 5: PROTECCIÓN ANTI-INYECCIÓN EN CSV Y BOM UTF-8 ---\n";

$csv = $reporteServicio->exportarCsv(ReporteCierreServicio::TIPO_PADRON_ASISTENCIA, $padron);

afirmar(str_starts_with($csv, "\xEF\xBB\xBF"), "El CSV inicia con UTF-8 BOM (\\xEF\\xBB\\xBF) para compatibilidad Excel en Windows.");
afirmar(str_contains($csv, "'=cmd"), "La inyección con '=' fue neutralizada anteponiendo apóstrofe ('=cmd).");
afirmar(!preg_match('/^=cmd/m', $csv), "Ninguna celda comienza con '=' sin sanitizar.");

// Probar sanitización directa de otros prefijos de riesgo
$refMetodoSanitizar = new ReflectionMethod(ReporteCierreServicio::class, 'sanitizarParaCsv');
$refMetodoSanitizar->setAccessible(true);

$resSuma = $refMetodoSanitizar->invoke($reporteServicio, '+500');
afirmar($resSuma === "'+500", "Prefijo '+' sanitizado con apóstrofe.");

$resResta = $refMetodoSanitizar->invoke($reporteServicio, '-100');
afirmar($resResta === "'-100", "Prefijo '-' en texto sanitizado con apóstrofe.");

$resArroba = $refMetodoSanitizar->invoke($reporteServicio, '@SUM(A1:A10)');
afirmar($resArroba === "'@SUM(A1:A10)", "Prefijo '@' sanitizado con apóstrofe.");

$resTab = $refMetodoSanitizar->invoke($reporteServicio, "\tcmd");
afirmar($resTab === "'\tcmd", "Prefijo tabulación (\\t) sanitizado con apóstrofe.");

$resNumeroPuro = $refMetodoSanitizar->invoke($reporteServicio, 250.50);
afirmar($resNumeroPuro === 250.50, "Valores numéricos puros no se alteran innecesariamente.");

// ------------------------------------------------------------------------------
// SECCIÓN 6: EXPORTACIÓN XLSX (SPREADSHEETML DE ALTA COMPATIBILIDAD)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 6: EXPORTACIÓN XLSX ESTRUCTURADA ---\n";

$xlsx = $reporteServicio->exportarXlsx(ReporteCierreServicio::TIPO_LIBRO_INGRESOS, $libro);

afirmar(str_starts_with($xlsx, '<?xml version="1.0" encoding="UTF-8"?>'), "XLSX inicia con declaración XML válida.");
afirmar(str_contains($xlsx, 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'), "Contiene namespace SpreadsheetML estándar.");
afirmar(str_contains($xlsx, '<Style ss:ID="Header">'), "Define estilos corporativos de encabezado.");
afirmar(str_contains($xlsx, '<Style ss:ID="DatoNumero">'), "Define formato numérico para cifras financieras.");
afirmar(str_contains($xlsx, 'RESUMEN OFICIAL'), "Incluye bloque de resumen y totales al pie.");

// ------------------------------------------------------------------------------
// SECCIÓN 7: EXPORTACIÓN PDF INSTITUCIONAL CON HASH PROBATORIO
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 7: EXPORTACIÓN PDF INSTITUCIONAL ---\n";

$tenantInfo = ['nombre_comercial' => 'Empresa Festividades Puno S.A.C.', 'identificador_fiscal' => '20123456789'];
$edicionInfo = ['nombre' => 'Candelaria 2026', 'codigo' => $codigoEdicion];

$pdfLibro = $reporteServicio->exportarPdf(ReporteCierreServicio::TIPO_LIBRO_INGRESOS, $libro, $tenantInfo, $edicionInfo);

afirmar(str_starts_with($pdfLibro, '%PDF-'), "El documento generado es un binario PDF válido (%PDF-).");
afirmar(strlen($pdfLibro) > 1000, "El tamaño del PDF supera 1 KB (tamaño real: " . strlen($pdfLibro) . " bytes).");

$pdfPadron = $reporteServicio->exportarPdf(ReporteCierreServicio::TIPO_PADRON_ASISTENCIA, $padron, $tenantInfo, $edicionInfo);
afirmar(str_starts_with($pdfPadron, '%PDF-'), "El PDF del Padrón de Asistencia se genera válidamente.");

// ------------------------------------------------------------------------------
// SECCIÓN 8: PROTECCIÓN ANTI-IDOR Y CONTROLADOR EDICIONCONTROLADOR
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 8: PROTECCIÓN ANTI-IDOR Y ACCESO API ---\n";

$idorBloqueado = false;
try {
    // Organización ajena (ID 999) intenta consultar reportes de la edición de la organización 1
    $reporteServicio->obtenerLibroIngresos(999, $edicionId);
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'no pertenece a la organización')) {
        $idorBloqueado = true;
    }
}
afirmar($idorBloqueado, "Protección Anti-IDOR: Se bloquea el acceso a reportes cuando el tenant no coincide.");

// Probar métodos del Controlador con sesión simulada
$authMock = new class extends AutenticacionMiddleware {
    public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion {
        return new ContextoOperacion(
            actorTipo: 'HUMANO',
            usuarioId: 24,
            actorSistemaId: null,
            actorSistemaCodigo: null,
            canalId: 1,
            canalCodigo: 'WEB',
            correlacionId: 'CORR-TEST-F212B-000000001',
            origenIp: '127.0.0.1',
            agenteUsuario: 'TestRunner/1.0',
            organizacionId: $GLOBALS['orgId'],
            metadatos: ['roles' => ['admin_organizacion'], 'permisos' => ['ediciones.ver', 'ediciones.cerrar']]
        );
    }
};

$authzMock = new class extends AutorizacionMiddleware {
    public function verificarPermiso(string $codigoPermiso, ?ContextoOperacion $contexto = null, bool $bloquearPeticion = true): bool {
        return true;
    }
};

$controlador = new EdicionControlador(
    authMiddleware: $authMock,
    authzMiddleware: $authzMock,
    reporteServicio: $reporteServicio,
    pdo: $pdo
);

// Probar respuesta JSON de reporteLibroIngresos
$jsonLibro = $controlador->reporteLibroIngresos($edicionId);
$resLibro = json_decode($jsonLibro, true);
afirmar(($resLibro['exito'] ?? false) === true, "EdicionControlador::reporteLibroIngresos retorna éxito JSON.");
afirmar((float)($resLibro['datos']['totales']['total_ventas'] ?? 0) === 1500.0, "Totales devueltos por el endpoint concilian con los datos reales.");

// Probar exportarReporte vía controlador
$_GET['tipo'] = 'LIBRO_INGRESOS';
$_GET['formato'] = 'csv';
$csvExportado = $controlador->exportarReporte($edicionId);
afirmar(str_starts_with($csvExportado, "\xEF\xBB\xBF"), "EdicionControlador::exportarReporte emite CSV con BOM UTF-8.");

$_GET['formato'] = 'xlsx';
$xlsxExportado = $controlador->exportarReporte($edicionId);
afirmar(str_contains($xlsxExportado, 'SpreadsheetML'), "EdicionControlador::exportarReporte emite XML SpreadsheetML.");

$_GET['formato'] = 'pdf';
$pdfExportado = $controlador->exportarReporte($edicionId);
afirmar(str_starts_with($pdfExportado, '%PDF-'), "EdicionControlador::exportarReporte emite documento PDF oficial.");

// Probar validación de formato no soportado
$_GET['formato'] = 'txt_invalido';
$jsonError = $controlador->exportarReporte($edicionId);
$resError = json_decode($jsonError, true);
afirmar(($resError['exito'] ?? true) === false, "Formato no soportado es rechazado con error controlado (código 400).");

// Validación de Integridad XML del archivo XLSX con parser independiente
$xmlParsed = simplexml_load_string($xlsxExportado);
afirmar($xmlParsed !== false, "El archivo XLSX generado es un XML estructurado 100% válido y parseable por parser independiente.");
afirmar(isset($xmlParsed->Worksheet), "El documento XML contiene elemento Worksheet estructurado.");

// Verificación de Principio de Mínimo Privilegio: Usuario con ediciones.ver NO puede exportar sin permiso específico
$authzRestringido = new class extends AutorizacionMiddleware {
    public function verificarPermiso(string $codigoPermiso, ?ContextoOperacion $contexto = null, bool $bloquearPeticion = true): bool {
        return !str_contains($codigoPermiso, 'exportar');
    }
};
$controladorRestringido = new EdicionControlador(
    authMiddleware: $authMock,
    authzMiddleware: $authzRestringido,
    reporteServicio: $reporteServicio,
    pdo: $pdo
);
$_GET['tipo'] = 'LIBRO_INGRESOS';
$_GET['formato'] = 'csv';
$jsonDenegado = $controladorRestringido->exportarReporte($edicionId);
$resDenegado = json_decode($jsonDenegado, true);
afirmar(($resDenegado['exito'] ?? true) === false, "Exportación financiera es bloqueada a usuarios sin permiso específico de exportación (Principio de Mínimo Privilegio).");

$_GET['tipo'] = 'PADRON_ASISTENCIA';
$jsonDenegadoPadron = $controladorRestringido->exportarReporte($edicionId);
$resDenegadoPadron = json_decode($jsonDenegadoPadron, true);
afirmar(($resDenegadoPadron['exito'] ?? true) === false, "Exportación de Padrón Ley 29733 es bloqueada a usuarios sin permiso ediciones.reportes.asistencia.exportar.");

// Pruebas ampliadas de neutralización de inyección de fórmulas con espacios y comandos
$resEspacioFormula = $refMetodoSanitizar->invoke($reporteServicio, " =1+1");
afirmar($resEspacioFormula === "' =1+1", "Fórmula con espacio en blanco inicial neutralizada con apóstrofe.");

$resPuntoComa = $refMetodoSanitizar->invoke($reporteServicio, ";calc");
afirmar($resPuntoComa === "';calc", "Prefijo ';' neutralizado con apóstrofe.");

$resPipe = $refMetodoSanitizar->invoke($reporteServicio, "|cmd");
afirmar($resPipe === "'|cmd", "Prefijo '|' neutralizado con apóstrofe.");

// ------------------------------------------------------------------------------
// SECCIÓN 9: INTEGRIDAD HISTÓRICA INMUTABLE DEL USUARIO ORLANDO (ID 24)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 9: INTEGRIDAD HISTÓRICA DEL USUARIO ORLANDO (ID 24) ---\n";

$stmtOrlando = $pdo->query("SELECT id, correo_electronico, contrasena_hash FROM `usuarios` WHERE id = 24");
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);
afirmar($orlando !== false, "Usuario Orlando (ID 24) existe en el sistema.");
$fingerprintOrlando = substr(hash('sha256', (string) $orlando['contrasena_hash']), 0, 16);
afirmar($fingerprintOrlando === '80e6af84e02e89e3', "Huella inmutable de Orlando (ID 24) preservada intacta (80e6af84e02e89e3).");

// ------------------------------------------------------------------------------
// LIMPIEZA DE FIXTURES DE PRUEBA
// ------------------------------------------------------------------------------
$pdo->exec("DELETE FROM `documentos_emitidos` WHERE entidad_tipo = 'RESERVA' AND entidad_id IN ({$reservaId1}, {$reservaId2})");
$pdo->exec("DELETE FROM `reserva_participantes` WHERE reserva_id IN ({$reservaId1}, {$reservaId2})");
$pdo->exec("DELETE FROM `operacion_salida_prestaciones` WHERE salida_id = {$salidaId}");
$pdo->exec("DELETE FROM `reserva_prestaciones` WHERE reserva_id IN ({$reservaId1}, {$reservaId2})");
$pdo->exec("DELETE FROM `reservas` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `recibos_caja` WHERE venta_id IN ({$ventaId1}, {$ventaId2})");
$pdo->exec("DELETE FROM `pagos` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `venta_lineas` WHERE venta_id IN ({$ventaId1}, {$ventaId2})");
$pdo->exec("DELETE FROM `ventas` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `operacion_salida_recursos` WHERE salida_id = {$salidaId}");
$pdo->exec("DELETE FROM `operacion_salidas` WHERE id = {$salidaId}");
$pdo->exec("DELETE FROM `operacion_recursos` WHERE id = {$recursoFisicoId}");
$pdo->exec("DELETE FROM `personas` WHERE id IN ({$personaCondId}, {$personaGuiaId})");
$pdo->exec("DELETE FROM `ofertas_items_edicion` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `ediciones_candelaria` WHERE id = {$edicionId}");

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.12B: {$pruebasExitosas} PASS / {$pruebasFallidas} FAIL (Total: {$pruebasEjecutadas})\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
