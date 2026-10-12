<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 2.12C
 * DASHBOARD EJECUTIVO Y MÉTRICAS DE RENDIMIENTO INTEGRAL
 * ==============================================================================
 * 1. KPIs Financieros: Ventas devengadas, recaudación neta, comisiones pasarela,
 *    saldos pendientes y % de cobranza sin mezclar devengado con recaudado.
 * 2. Ticket Promedio y distribución porcentual por canal comercial.
 * 3. Operaciones de Flota: Capacidad ofertada vs agendados y % de ocupación.
 * 4. Padrón de Asistencia QR: Check-in presencial, ausencias no-show y tasas.
 * 5. Postproducción y Multimedia: Proyectos, entregas, tasa de aprobación y almacenamiento.
 * 6. Comparativa Histórica Inter-Ediciones sin producto cartesiano en SQL.
 * 7. Empty State Seguro: Edición sin datos no genera división por cero ni errores PHP.
 * 8. Aislamiento Multitenant Estricto (Anti-IDOR entre organizaciones).
 * 9. RBAC en Endpoints: Permiso dashboard.ejecutivo.ver y protección de acceso.
 * 10. Preservación histórica inmutable de Orlando (ID 24) con huella 80e6af84e02e89e3.
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Controladores\DashboardControlador;
use Aplicacion\Dashboard\DashboardEjecutivoServicio;
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
echo "INICIANDO SUITE F2.12C: DASHBOARD EJECUTIVO Y MÉTRICAS DE RENDIMIENTO\n";
echo "==============================================================================\n\n";

$stmtOrg = $pdo->query("SELECT id FROM `organizaciones` ORDER BY id ASC LIMIT 1");
$orgId = (int) $stmtOrg->fetchColumn();

// Limpieza preventiva de ediciones residuales de pruebas previas
$stmtResidual = $pdo->query("SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'TEST-DASH-%'");
$resIds = $stmtResidual->fetchAll(PDO::FETCH_COLUMN);
foreach ($resIds as $rEdId) {
    $pdo->exec("DELETE FROM `postproduccion_archivos` WHERE galeria_id IN (SELECT id FROM `postproduccion_galerias` WHERE proyecto_id IN (SELECT id FROM `postproduccion_proyectos` WHERE edicion_id = {$rEdId}))");
    $pdo->exec("DELETE FROM `postproduccion_galerias` WHERE proyecto_id IN (SELECT id FROM `postproduccion_proyectos` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `postproduccion_entregas` WHERE proyecto_id IN (SELECT id FROM `postproduccion_proyectos` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `postproduccion_proyectos` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `reserva_participantes` WHERE reserva_id IN (SELECT id FROM `reservas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `operacion_salida_prestaciones` WHERE salida_id IN (SELECT id FROM `operacion_salidas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `reserva_prestaciones` WHERE reserva_id IN (SELECT id FROM `reservas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `reservas` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `recibos_caja` WHERE venta_id IN (SELECT id FROM `ventas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `pagos` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `venta_lineas` WHERE venta_id IN (SELECT id FROM `ventas` WHERE edicion_id = {$rEdId})");
    $pdo->exec("DELETE FROM `ventas` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `cotizaciones` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `ofertas_items_edicion` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `operacion_salidas` WHERE edicion_id = {$rEdId}");
    $pdo->exec("DELETE FROM `ediciones_candelaria` WHERE id = {$rEdId}");
}

// ------------------------------------------------------------------------------
// 1. SETUP DE FIXTURES DE PRUEBA
// ------------------------------------------------------------------------------
$codigoEdicion = 'TEST-DASH-' . substr(bin2hex(random_bytes(4)), 0, 8);
$aniosUsados = array_map('intval', $pdo->query("SELECT anio FROM `ediciones_candelaria` WHERE organizacion_id = {$orgId}")->fetchAll(PDO::FETCH_COLUMN));
$anioTest = 2060;
while (in_array($anioTest, $aniosUsados, true) && $anioTest <= 2099) {
    $anioTest++;
}

$stmtInsEd = $pdo->prepare("
    INSERT INTO `ediciones_candelaria` (
        `organizacion_id`, `codigo`, `nombre`, `anio`, `estado`,
        `fecha_inicio`, `fecha_fin`, `descripcion`, `es_actual`
    ) VALUES (
        :org_id, :codigo, 'Candelaria Test Dashboard', :anio, 'OPERACION',
        '2026-02-01', '2026-02-15', 'Edición para pruebas automatizadas F2.12C', 0
    )
");
$stmtInsEd->execute([
    'org_id' => $orgId,
    'codigo' => $codigoEdicion,
    'anio'   => $anioTest
]);
$edicionId = (int) $pdo->lastInsertId();

// Clientes y usuario existentes
$stmtCli = $pdo->prepare("SELECT id FROM `clientes` WHERE organizacion_id = :org_id ORDER BY id ASC LIMIT 2");
$stmtCli->execute(['org_id' => $orgId]);
$clientes = $stmtCli->fetchAll(PDO::FETCH_COLUMN);
$cli1 = !empty($clientes[0]) ? (int)$clientes[0] : 1;
$cli2 = !empty($clientes[1]) ? (int)$clientes[1] : 1;

// Item Comercial para prestaciones
$stmtIc = $pdo->prepare("SELECT id FROM `items_comerciales` WHERE organizacion_id = :org LIMIT 1");
$stmtIc->execute([':org' => $orgId]);
$itemId = (int) $stmtIc->fetchColumn();
if (!$itemId) {
    $stmtInsIc = $pdo->prepare("
        INSERT INTO `items_comerciales` (organizacion_id, codigo, nombre, tipo_item, precio_base, estado)
        VALUES (:org, 'ITEM-DASH-01', 'Paquete Oficial Candelaria', 'PAQUETE_SERVICIOS', 1000.00, 'ACTIVO')
    ");
    $stmtInsIc->execute([':org' => $orgId]);
    $itemId = (int)$pdo->lastInsertId();
}

// Oferta para la edición de prueba
$stmtOferta = $pdo->prepare("
    INSERT INTO `ofertas_items_edicion` (organizacion_id, edicion_id, item_comercial_id, estado)
    VALUES (:org, :ed, :item, 'ACTIVO')
");
$stmtOferta->execute([':org' => $orgId, ':ed' => $edicionId, ':item' => $itemId]);
$ofertaItemId = (int)$pdo->lastInsertId();

// Insertar Ventas:
// Venta 1: CONFIRMADA, Total 3,000.00, Saldo 1,000.00, Canal DIRECTA, Estado Financiero PAGO_PARCIAL
$stmtV1 = $pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `correlativo`, `origen_tipo`,
        `estado`, `estado_financiero`, `moneda`, `subtotal`, `total`,
        `monto_pagado`, `saldo_pendiente`, `cliente_nombre_completo`, `cliente_numero_documento`,
        `cliente_email`, `fecha_venta`, `creado_por`
    ) VALUES (
        :org, :ed, :cli, 'VEN-DASH-001', 'DIRECTA',
        'CONFIRMADA', 'PAGO_PARCIAL', 'PEN', 3000.00, 3000.00,
        2000.00, 1000.00, 'Juan Carlos Quispe', '72819283',
        'juan.c@example.com', '2026-02-05', 24
    )
");
$stmtV1->execute([':org' => $orgId, ':ed' => $edicionId, ':cli' => $cli1]);
$venta1Id = (int) $pdo->lastInsertId();

// Cotización vinculada para Venta 2 (canal COTIZACION)
$stmtCot = $pdo->prepare("
    INSERT INTO `cotizaciones` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `correlativo`, `correlativo_base`,
        `version_numero`, `titulo`, `estado`, `fecha_emision`, `valido_hasta`,
        `moneda`, `subtotal`, `total`, `creado_por`
    ) VALUES (
        :org, :ed, :cli, 'COT-DASH-001', 'COT-DASH-001',
        1, 'Propuesta Comercial Formal', 'ACEPTADA', '2026-02-01', '2026-02-28',
        'PEN', 2000.00, 2000.00, 24
    )
");
$stmtCot->execute([':org' => $orgId, ':ed' => $edicionId, ':cli' => $cli2]);
$cotizacionId = (int) $pdo->lastInsertId();

// Venta 2: LIQUIDADA, Total 2,000.00, Saldo 0.00, Canal COTIZACION, Estado Financiero PAGADA_TOTAL
$stmtV2 = $pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `correlativo`, `origen_tipo`,
        `estado`, `estado_financiero`, `moneda`, `subtotal`, `total`,
        `monto_pagado`, `saldo_pendiente`, `cliente_nombre_completo`, `cliente_numero_documento`,
        `cliente_email`, `fecha_venta`, `creado_por`
    ) VALUES (
        :org, :ed, :cli, :cot_id, 'VEN-DASH-002', 'COTIZACION',
        'LIQUIDADA', 'PAGADA_TOTAL', 'PEN', 2000.00, 2000.00,
        2000.00, 0.00, 'Lucia Fernandez Mamani', '10293847',
        'lucia.f@example.com', '2026-02-06', 24
    )
");
$stmtV2->execute([':org' => $orgId, ':ed' => $edicionId, ':cli' => $cli2, ':cot_id' => $cotizacionId]);
$venta2Id = (int) $pdo->lastInsertId();

// Venta 3: CANCELADA, Total 1,500.00, DIRECTA (cotizacion_id = NULL)
$stmtV3 = $pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `correlativo`, `origen_tipo`,
        `estado`, `estado_financiero`, `moneda`, `subtotal`, `total`,
        `monto_pagado`, `saldo_pendiente`, `cliente_nombre_completo`, `cliente_numero_documento`,
        `cliente_email`, `fecha_venta`, `motivo_cancelacion`, `creado_por`
    ) VALUES (
        :org, :ed, :cli, 'VEN-DASH-003', 'DIRECTA',
        'CANCELADA', 'NO_PAGADA', 'PEN', 1500.00, 1500.00,
        0.00, 1500.00, 'Cliente Cancelado', '40506070',
        'cancelado@example.com', '2026-02-07', 'DESISTIMIENTO_CLIENTE', 24
    )
");
$stmtV3->execute([':org' => $orgId, ':ed' => $edicionId, ':cli' => $cli1]);
$venta3Id = (int) $pdo->lastInsertId();

// Insertar Pagos:
// Pago 1 (para Venta 1): APROBADO, Cobrado: 2,000.00, Comision: 80.00, Neto: 1,920.00
$stmtP1 = $pdo->prepare("
    INSERT INTO `pagos` (
        `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`,
        `estado`, `moneda`, `monto_cobrado_cliente`, `comision_pasarela`,
        `monto_neto_recibido`, `monto_aplicado_venta`, `fecha_pago`, `numero_operacion_bancaria`, `creado_por`
    ) VALUES (
        :org, :ed, :v, 'PAG-DASH-001', 'TARJETA_PASARELA',
        'APROBADO', 'PEN', 2000.00, 80.00,
        1920.00, 2000.00, '2026-02-05 10:00:00', 'CULQI-CHG-111', 24
    )
");
$stmtP1->execute([':org' => $orgId, ':ed' => $edicionId, ':v' => $venta1Id]);

// Pago 2 (para Venta 2): APROBADO, Cobrado: 2,000.00, Comision: 0.00, Neto: 2,000.00
$stmtP2 = $pdo->prepare("
    INSERT INTO `pagos` (
        `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`,
        `estado`, `moneda`, `monto_cobrado_cliente`, `comision_pasarela`,
        `monto_neto_recibido`, `monto_aplicado_venta`, `fecha_pago`, `numero_operacion_bancaria`, `creado_por`
    ) VALUES (
        :org, :ed, :v, 'PAG-DASH-002', 'TRANSFERENCIA_BANCARIA',
        'APROBADO', 'PEN', 2000.00, 0.00,
        2000.00, 2000.00, '2026-02-06 11:00:00', 'BCP-TRANS-222', 24
    )
");
$stmtP2->execute([':org' => $orgId, ':ed' => $edicionId, ':v' => $venta2Id]);

// Pago 3: PENDIENTE_VERIFICACION, Cobrado 500.00 (NO debe sumar en recaudación neta)
$stmtP3 = $pdo->prepare("
    INSERT INTO `pagos` (
        `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`,
        `estado`, `moneda`, `monto_cobrado_cliente`, `comision_pasarela`,
        `monto_neto_recibido`, `monto_aplicado_venta`, `fecha_pago`, `numero_operacion_bancaria`, `creado_por`
    ) VALUES (
        :org, :ed, :v, 'PAG-DASH-003', 'TRANSFERENCIA_BANCARIA',
        'PENDIENTE_VERIFICACION', 'PEN', 500.00, 0.00,
        500.00, 500.00, '2026-02-07 12:00:00', 'PENDIENTE-333', 24
    )
");
$stmtP3->execute([':org' => $orgId, ':ed' => $edicionId, ':v' => $venta1Id]);

// Insertar Salida de Campo: Capacidad 20
$stmtSal = $pdo->prepare("
    INSERT INTO `operacion_salidas` (
        `organizacion_id`, `edicion_id`, `item_comercial_id`, `correlativo`, `titulo`,
        `fecha_salida`, `hora_citacion`, `hora_salida`, `punto_encuentro`, `tipo_capacidad`,
        `capacidad_maxima`, `estado`, `creado_por`
    ) VALUES (
        :org, :ed, :ic, 'SAL-DASH-01', 'Salida Virgen de la Candelaria',
        '2026-02-10', '07:30:00', '08:00:00', 'Plaza Mayor Puno', 'COLECTIVA',
        20, 'PROGRAMADA', 24
    )
");
$stmtSal->execute([':org' => $orgId, ':ed' => $edicionId, ':ic' => $itemId]);
$salidaId = (int) $pdo->lastInsertId();

// Insertar Líneas de Venta y Prestaciones de Reserva para asociar a la salida
$stmtVL = $pdo->prepare("
    INSERT INTO `venta_lineas` (
        `venta_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`,
        `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`
    ) VALUES (
        :vid, 'ITEM', :ic, :of_id, 'ITEM-DASH-01', 'Paquete Candelaria',
        'SERVICIO', 15.00, 200.00, 3000.00, 'PEN', 1
    )
");
$stmtVL->execute([':vid' => $venta1Id, ':ic' => $itemId, ':of_id' => $ofertaItemId]);
$lineaId = (int) $pdo->lastInsertId();

// Reservas
// Reserva 1: Asistió (Check-in con timestamp en notas_operativas)
$stmtRes1 = $pdo->prepare("
    INSERT INTO `reservas` (
        `organizacion_id`, `edicion_id`, `venta_id`, `cliente_id`, `correlativo`,
        `estado`, `contacto_nombre`, `contacto_tipo_documento`, `contacto_numero_documento`,
        `contacto_telefono`, `contacto_email`, `notas_operativas`, `creado_por`
    ) VALUES (
        :org, :ed, :v, :cli, 'RES-DASH-001',
        'CONFIRMADA', 'Juan Carlos Quispe', 'DNI', '72819283',
        '987654321', 'juan.c@example.com', '[CHECKIN_QR_REGISTRADO: 2026-02-10 08:05:00]', 24
    )
");
$stmtRes1->execute([':org' => $orgId, ':ed' => $edicionId, ':v' => $venta1Id, ':cli' => $cli1]);
$reservaId1 = (int) $pdo->lastInsertId();

// Reserva 2: Ausente / No-Show
$stmtRes2 = $pdo->prepare("
    INSERT INTO `reservas` (
        `organizacion_id`, `edicion_id`, `venta_id`, `cliente_id`, `correlativo`,
        `estado`, `contacto_nombre`, `contacto_tipo_documento`, `contacto_numero_documento`,
        `contacto_telefono`, `contacto_email`, `notas_operativas`, `creado_por`
    ) VALUES (
        :org, :ed, :v, :cli, 'RES-DASH-002',
        'CONFIRMADA', 'Lucia Fernandez Mamani', 'DNI', '10293847',
        '987654322', 'lucia.f@example.com', 'Ausente sin aviso previo', 24
    )
");
$stmtRes2->execute([':org' => $orgId, ':ed' => $edicionId, ':v' => $venta2Id, ':cli' => $cli2]);
$reservaId2 = (int) $pdo->lastInsertId();

// Prestación asignada a la salida operativa con 15 pasajeros
$stmtPrest = $pdo->prepare("
    INSERT INTO `reserva_prestaciones` (
        `reserva_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`, `concepto_nombre`,
        `unidad_medida`, `cantidad`, `tipo_capacidad`, `requiere_agendamiento`, `requiere_participantes`,
        `estado_agendamiento`, `fecha_servicio`
    ) VALUES (
        :r, :vl, :ic, 'TOUR-DASH', 'Tour Candelaria',
        'SERVICIO', 15.00, 'COLECTIVA', 1, 1,
        'PROGRAMADA', '2026-02-10'
    )
");
$stmtPrest->execute([':r' => $reservaId1, ':vl' => $lineaId, ':ic' => $itemId]);
$prestId = (int) $pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO `operacion_salida_prestaciones` (`salida_id`, `prestacion_id`, `cantidad_pasajeros`, `asignado_por`)
    VALUES (:sid, :pid, 15, 24)
")->execute([':sid' => $salidaId, ':pid' => $prestId]);

// Participantes: 10 en Reserva 1 (asistieron check-in), 2 en Reserva 2 (ausentes)
for ($i = 1; $i <= 10; $i++) {
    $stmtPart = $pdo->prepare("
        INSERT INTO `reserva_participantes` (
            `reserva_id`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`
        ) VALUES (
            :res_id, 1, '4000000{$i}', 'Asistente {$i}', 'Puno'
        )
    ");
    $stmtPart->execute([':res_id' => $reservaId1]);
}
for ($j = 1; $j <= 2; $j++) {
    $stmtPartNo = $pdo->prepare("
        INSERT INTO `reserva_participantes` (
            `reserva_id`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`
        ) VALUES (
            :res_id, 1, '4000001{$j}', 'Ausente {$j}', 'Puno'
        )
    ");
    $stmtPartNo->execute([':res_id' => $reservaId2]);
}

// Postproducción: 1 proyecto, 2 entregas (1 aprobada, 1 pendiente), 1 galería con 2 archivos (10 MB total)
$stmtProj = $pdo->prepare("
    INSERT INTO `postproduccion_proyectos` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `reserva_id`,
        `codigo_proyecto`, `titulo`, `estado`
    ) VALUES (
        :org, :ed, :cli, :r,
        'PRY-POST-2026-9999', 'Proyecto Fotográfico Candelaria', 'EN_EDICION'
    )
");
$stmtProj->execute([':org' => $orgId, ':ed' => $edicionId, ':cli' => $cli1, ':r' => $reservaId1]);
$proyectoId = (int) $pdo->lastInsertId();

$stmtEnt1 = $pdo->prepare("
    INSERT INTO `postproduccion_entregas` (
        `organizacion_id`, `proyecto_id`, `numero_entrega`, `titulo`, `tipo_entrega`, `estado_entrega`
    ) VALUES (
        :org, :prj, 1, 'Entrega Fotos Seleccionadas', 'PARCIAL', 'APROBADA_CLIENTE'
    )
");
$stmtEnt1->execute([':org' => $orgId, ':prj' => $proyectoId]);

$stmtEnt2 = $pdo->prepare("
    INSERT INTO `postproduccion_entregas` (
        `organizacion_id`, `proyecto_id`, `numero_entrega`, `titulo`, `tipo_entrega`, `estado_entrega`
    ) VALUES (
        :org, :prj, 2, 'Entrega Video Resumen', 'PARCIAL', 'PUBLICADA'
    )
");
$stmtEnt2->execute([':org' => $orgId, ':prj' => $proyectoId]);

$stmtGal = $pdo->prepare("
    INSERT INTO `postproduccion_galerias` (
        `organizacion_id`, `proyecto_id`, `titulo`, `slug`, `tipo_galeria`
    ) VALUES (
        :org, :prj, 'Galería Principal', 'galeria-principal-dash', 'FOTOGRAFIA'
    )
");
$stmtGal->execute([':org' => $orgId, ':prj' => $proyectoId]);
$galeriaId = (int) $pdo->lastInsertId();

$stmtArc = $pdo->prepare("
    INSERT INTO `postproduccion_archivos` (
        `organizacion_id`, `proyecto_id`, `galeria_id`, `nombre_original`, `nombre_almacenamiento`,
        `ruta_privada`, `tipo_archivo`, `mime_type`, `peso_bytes`, `checksum_sha256`, `version`, `estado`
    ) VALUES (
        :org, :prj, :gal, :nom, :almac, :ruta, 'FOTO', 'image/jpeg', 5242880, 'hash123', 1, 'ACTIVO'
    )
");
$stmtArc->execute([':org' => $orgId, ':prj' => $proyectoId, ':gal' => $galeriaId, ':nom' => 'foto1.jpg', ':almac' => 'f1.jpg', ':ruta' => 'galerias/foto1.jpg']);
$stmtArc->execute([':org' => $orgId, ':prj' => $proyectoId, ':gal' => $galeriaId, ':nom' => 'foto2.jpg', ':almac' => 'f2.jpg', ':ruta' => 'galerias/foto2.jpg']);

echo "--- GRUPO 1: CÁLCULOS Y FÓRMULAS CANÓNICAS DE KPIS FINANCIEROS ---\n";
$dashboardServicio = new DashboardEjecutivoServicio($pdo);
$resKpis = $dashboardServicio->obtenerKpisEjecutivos($orgId, $edicionId);

$fin = $resKpis['kpis']['finanzas'];
afirmar(abs($fin['total_devengado'] - 5000.00) < 0.01, "Total Devengado suma exactamente 5,000.00 PEN (3,000 + 2,000, excluye cancelada)");
afirmar(abs($fin['total_cobrado_cliente'] - 4000.00) < 0.01, "Total Cobrado al Cliente suma exactamente 4,000.00 PEN (excluye pendiente)");
afirmar(abs($fin['total_comisiones_pasarela'] - 80.00) < 0.01, "Comisiones de Pasarela suma 80.00 PEN de la transacción pasarela");
afirmar(abs($fin['total_neto_recaudado'] - 3920.00) < 0.01, "Recaudación Neta Líquida suma 3,920.00 PEN (4,000.00 - 80.00)");
afirmar(abs($fin['total_saldo_pendiente'] - 1000.00) < 0.01, "Saldo Pendiente suma 1,000.00 PEN");
afirmar(abs($fin['porcentaje_cobranza'] - 80.00) < 0.01, "% de Cobranza es exactamente 80.00% (4,000 / 5,000 * 100)");
afirmar(abs($fin['ticket_promedio'] - 2500.00) < 0.01, "Ticket Promedio es 2,500.00 PEN (5,000 devengado / 2 ventas activas)");
afirmar($fin['ventas_activas_qty'] === 2, "Cantidad de ventas activas es 2");
afirmar($fin['ventas_pagadas_total_qty'] === 1, "Ventas pagadas total es 1");
afirmar($fin['ventas_pago_parcial_qty'] === 1, "Ventas pago parcial es 1");

echo "\n--- GRUPO 2: DISTRIBUCIÓN POR CANAL COMERCIAL ---\n";
$canales = $resKpis['kpis']['canales'];
afirmar(count($canales) === 2, "Se registraron exactamente 2 canales con ventas activas (DIRECTA y COTIZACION)");
$canalDirecta = null;
$canalCotizacion = null;
foreach ($canales as $c) {
    if ($c['canal'] === 'DIRECTA') $canalDirecta = $c;
    if ($c['canal'] === 'COTIZACION') $canalCotizacion = $c;
}
afirmar($canalDirecta !== null && abs($canalDirecta['volumen_devengado'] - 3000.00) < 0.01, "Canal DIRECTA suma 3,000.00 PEN");
afirmar($canalDirecta !== null && abs($canalDirecta['porcentaje_volumen'] - 60.00) < 0.01, "Canal DIRECTA representa el 60.00%");
afirmar($canalCotizacion !== null && abs($canalCotizacion['volumen_devengado'] - 2000.00) < 0.01, "Canal COTIZACION suma 2,000.00 PEN");
afirmar($canalCotizacion !== null && abs($canalCotizacion['porcentaje_volumen'] - 40.00) < 0.01, "Canal COTIZACION representa el 40.00%");

echo "\n--- GRUPO 3: OPERACIONES DE FLOTA Y CAPACIDAD ---\n";
$ops = $resKpis['kpis']['operaciones'];
afirmar($ops['total_salidas_programadas'] === 1, "Registra 1 salida de campo programada");
afirmar($ops['capacidad_total_flota'] === 20, "Capacidad total de la flota es 20 asientos");
afirmar($ops['pasajeros_agendados_total'] === 15, "Pasajeros agendados es 15");
afirmar(abs($ops['tasa_ocupacion_flota_pct'] - 75.00) < 0.01, "Porcentaje de ocupación de flota es 75.00% (15 / 20 * 100)");

echo "\n--- GRUPO 4: PADRÓN DE ASISTENCIA QR Y NO-SHOW (LEY 29733) ---\n";
$asis = $resKpis['kpis']['asistencia'];
afirmar($asis['total_participantes'] === 12, "Total participantes en nómina es 12");
afirmar($asis['total_asistieron'] === 10, "Asistentes con check-in QR efectivo es 10");
afirmar($asis['total_ausentes'] === 2, "Ausentes (no-show) es 2");
afirmar(abs($asis['tasa_asistencia_pct'] - 83.33) < 0.1, "Tasa de asistencia es aproximadamente 83.33%");
afirmar(abs($asis['tasa_no_show_pct'] - 16.67) < 0.1, "Tasa de no-show es aproximadamente 16.67%");

echo "\n--- GRUPO 5: POSTPRODUCCIÓN Y ENTREGAS AUDIOVISUALES ---\n";
$post = $resKpis['kpis']['postproduccion'];
afirmar($post['total_proyectos'] === 1, "Total de proyectos de postproducción es 1");
afirmar($post['entregas_totales'] === 2, "Total de entregas despachadas es 2");
afirmar($post['entregas_aprobadas'] === 1, "Entregas aprobadas por cliente es 1");
afirmar($post['entregas_pendientes'] === 1, "Entregas pendientes de respuesta es 1");
afirmar(abs($post['tasa_aprobacion_entregas_pct'] - 50.00) < 0.01, "Tasa de aprobación de entregas es 50.00% (1 / 2 * 100)");
afirmar($post['galerias_creadas'] === 1, "Galerías creadas es 1");
afirmar(abs($post['almacenamiento_total_mb'] - 10.00) < 0.1, "Almacenamiento total consumido es 10.00 MB (2 archivos de 5MB)");

echo "\n--- GRUPO 6: EMPTY STATE SEGURO (CERO DIVISIONES POR CERO) ---\n";
$codigoEmpty = 'TEST-DASH-EMPTY-' . substr(bin2hex(random_bytes(4)), 0, 8);
$aniosUsadosEmpty = array_map('intval', $pdo->query("SELECT anio FROM `ediciones_candelaria` WHERE organizacion_id = {$orgId}")->fetchAll(PDO::FETCH_COLUMN));
$anioEmpty = $anioTest + 1;
while (in_array($anioEmpty, $aniosUsadosEmpty, true) && $anioEmpty <= 2099) {
    $anioEmpty++;
}
$stmtInsEmpty = $pdo->prepare("
    INSERT INTO `ediciones_candelaria` (
        `organizacion_id`, `codigo`, `nombre`, `anio`, `estado`,
        `fecha_inicio`, `fecha_fin`, `descripcion`, `es_actual`
    ) VALUES (
        :org_id, :codigo, 'Edición Virgen Sin Datos', :anio, 'PREOPERACION',
        '2027-02-01', '2027-02-15', 'Empty state test', 0
    )
");
$stmtInsEmpty->execute(['org_id' => $orgId, 'codigo' => $codigoEmpty, 'anio' => $anioEmpty]);
$emptyEdicionId = (int) $pdo->lastInsertId();

$kpisEmpty = $dashboardServicio->obtenerKpisEjecutivos($orgId, $emptyEdicionId);
afirmar($kpisEmpty['kpis']['finanzas']['total_devengado'] === 0.0, "Empty state: Devengado es 0.0");
afirmar($kpisEmpty['kpis']['finanzas']['porcentaje_cobranza'] === 0.0, "Empty state: % Cobranza es 0.0 (sin división por cero)");
afirmar($kpisEmpty['kpis']['finanzas']['ticket_promedio'] === 0.0, "Empty state: Ticket promedio es 0.0 (sin división por cero)");
afirmar($kpisEmpty['kpis']['operaciones']['tasa_ocupacion_flota_pct'] === 0.0, "Empty state: Ocupación flota es 0.0 (sin división por cero)");
afirmar($kpisEmpty['kpis']['asistencia']['tasa_asistencia_pct'] === 0.0, "Empty state: Tasa asistencia es 0.0 (sin división por cero)");
afirmar($kpisEmpty['kpis']['postproduccion']['tasa_aprobacion_entregas_pct'] === 0.0, "Empty state: Tasa aprobación es 0.0 (sin división por cero)");
afirmar(is_array($kpisEmpty['kpis']['canales']) && count($kpisEmpty['kpis']['canales']) === 0, "Empty state: Canales es array vacío sin errores");

// Limpiar edición empty
$pdo->exec("DELETE FROM `ediciones_candelaria` WHERE id = {$emptyEdicionId}");

echo "\n--- GRUPO 7: COMPARATIVA HISTÓRICA INTER-EDICIONES SIN PRODUCTO CARTESIANO ---\n";
$compHistorica = $dashboardServicio->obtenerComparativaHistorica($orgId);
afirmar(is_array($compHistorica) && count($compHistorica) >= 1, "Comparativa histórica retorna array con al menos 1 edición");
$filaTest = null;
foreach ($compHistorica as $ch) {
    if ($ch['edicion_id'] === $edicionId) {
        $filaTest = $ch;
        break;
    }
}
afirmar($filaTest !== null, "La edición de prueba aparece en la comparativa histórica");
afirmar(abs($filaTest['ventas_devengadas'] - 5000.00) < 0.01, "Comparativa: Devengado no sufre producto cartesiano (exactamente 5,000.00)");
afirmar(abs($filaTest['recaudacion_neta'] - 3920.00) < 0.01, "Comparativa: Recaudación no sufre producto cartesiano (exactamente 3,920.00)");
afirmar($filaTest['cantidad_ventas'] === 2, "Comparativa: Cantidad de ventas activas es exactamente 2");
afirmar(abs($filaTest['ticket_promedio'] - 2500.00) < 0.01, "Comparativa: Ticket promedio es exactamente 2,500.00");

echo "\n--- GRUPO 8: AISLAMIENTO MULTITENANT RIGUROSO (ANTI-IDOR) ---\n";
$orgAjenaId = 999999;
$kpisAjeno = $dashboardServicio->obtenerKpisEjecutivos($orgAjenaId, $edicionId);
afirmar($kpisAjeno['edicion_actual'] === null, "Consultar edición con organización ajena no encuentra la entidad (Anti-IDOR)");
afirmar($kpisAjeno['kpis']['finanzas']['total_devengado'] === 0.0, "Organización ajena no filtra montos devengados");

echo "\n--- GRUPO 9: ENDPOINTS HTTP Y CONTROLADOR CON RBAC (DASHBOARD.EJECUTIVO.VER) ---\n";
$contextoConPermiso = new ContextoOperacion(
    actorTipo: 'HUMANO',
    usuarioId: 1,
    actorSistemaId: null,
    actorSistemaCodigo: null,
    canalId: 1,
    canalCodigo: 'WEB',
    correlacionId: 'CORR-TEST-F212C-001',
    organizacionId: $orgId,
    metadatos: ['permisos' => ['dashboard.ejecutivo.ver'], 'roles' => ['admin_organizacion']]
);

$contextoSinPermiso = new ContextoOperacion(
    actorTipo: 'HUMANO',
    usuarioId: 2,
    actorSistemaId: null,
    actorSistemaCodigo: null,
    canalId: 1,
    canalCodigo: 'WEB',
    correlacionId: 'CORR-TEST-F212C-002',
    organizacionId: $orgId,
    metadatos: ['permisos' => ['ventas.ver'], 'roles' => ['operador']]
);

$mockAuthPermiso = new class($contextoConPermiso) extends AutenticacionMiddleware {
    public function __construct(private ContextoOperacion $ctx) {}
    public function procesar(array $servidor = [], array $cookies = [], bool $redirigirSiFalla = true): ?ContextoOperacion {
        return $this->ctx;
    }
};

$mockAuthSinPermiso = new class($contextoSinPermiso) extends AutenticacionMiddleware {
    public function __construct(private ContextoOperacion $ctx) {}
    public function procesar(array $servidor = [], array $cookies = [], bool $redirigirSiFalla = true): ?ContextoOperacion {
        return $this->ctx;
    }
};

$mockAuthz = new class extends AutorizacionMiddleware {
    public function __construct() {}
    public function verificarPermiso(string $permiso, ?ContextoOperacion $contexto = null, bool $lanzarExcepcion = true): bool {
        if ($contexto === null) return false;
        $permisos = $contexto->metadatos['permisos'] ?? [];
        return in_array($permiso, $permisos, true);
    }
};

$controladorAutorizado = new DashboardControlador(
    authMiddleware: $mockAuthPermiso,
    authzMiddleware: $mockAuthz,
    dashboardServicio: $dashboardServicio,
    pdo: $pdo
);

$controladorDenegado = new DashboardControlador(
    authMiddleware: $mockAuthSinPermiso,
    authzMiddleware: $mockAuthz,
    dashboardServicio: $dashboardServicio,
    pdo: $pdo
);

// Probar Endpoint kpis con autorización
$_GET['edicion_id'] = (string) $edicionId;
$jsonKpis = $controladorAutorizado->kpis();
$dataKpis = json_decode($jsonKpis, true);
afirmar(is_array($dataKpis) && $dataKpis['exito'] === true, "Endpoint /api/v1/dashboard/kpis responde exito:true para usuario autorizado");
afirmar(isset($dataKpis['datos']['kpis']['finanzas']), "Payload JSON contiene estructura completa de KPIs");

// Probar Endpoint comparativa con autorización
$jsonComp = $controladorAutorizado->comparativa();
$dataComp = json_decode($jsonComp, true);
afirmar(is_array($dataComp) && $dataComp['exito'] === true, "Endpoint /api/v1/dashboard/comparativa responde exito:true");

// Probar Endpoint kpis con denegación (sin permiso dashboard.ejecutivo.ver)
$jsonDenegado = $controladorDenegado->kpis();
$dataDenegada = json_decode($jsonDenegado, true);
afirmar(is_array($dataDenegada) && $dataDenegada['exito'] === false && $dataDenegada['codigo'] === 403, "Endpoint /api/v1/dashboard/kpis rechaza con HTTP 403 a usuario sin permiso");

echo "\n--- GRUPO 10: PRESERVACIÓN INMUTABLE DE HUELLA ORLANDO (ID 24) ---\n";
$stmtOrl = $pdo->prepare("SELECT id, correo_electronico, contrasena_hash FROM `usuarios` WHERE id = 24");
$stmtOrl->execute();
$orlando = $stmtOrl->fetch(PDO::FETCH_ASSOC);
afirmar($orlando !== false, "Usuario Orlando (ID 24) existe inmutable");
if ($orlando) {
    $huellaHash = substr(hash('sha256', (string) $orlando['contrasena_hash']), 0, 16);
    afirmar($huellaHash === '80e6af84e02e89e3', "Huella SHA-256 de Orlando es inmutable: 80e6af84e02e89e3 (actual: {$huellaHash})");
}

// Limpieza de fixtures
$pdo->exec("DELETE FROM `postproduccion_archivos` WHERE galeria_id = {$galeriaId}");
$pdo->exec("DELETE FROM `postproduccion_galerias` WHERE id = {$galeriaId}");
$pdo->exec("DELETE FROM `postproduccion_entregas` WHERE proyecto_id = {$proyectoId}");
$pdo->exec("DELETE FROM `postproduccion_proyectos` WHERE id = {$proyectoId}");
$pdo->exec("DELETE FROM `reserva_participantes` WHERE reserva_id IN ({$reservaId1}, {$reservaId2})");
$pdo->exec("DELETE FROM `operacion_salida_prestaciones` WHERE salida_id = {$salidaId}");
$pdo->exec("DELETE FROM `reserva_prestaciones` WHERE reserva_id IN ({$reservaId1}, {$reservaId2})");
$pdo->exec("DELETE FROM `reservas` WHERE id IN ({$reservaId1}, {$reservaId2})");
$pdo->exec("DELETE FROM `operacion_salidas` WHERE id = {$salidaId}");
$pdo->exec("DELETE FROM `pagos` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `venta_lineas` WHERE venta_id IN ({$venta1Id}, {$venta2Id}, {$venta3Id})");
$pdo->exec("DELETE FROM `ventas` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `cotizaciones` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `ofertas_items_edicion` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `ediciones_candelaria` WHERE id = {$edicionId}");

echo "\n==============================================================================\n";
echo "RESUMEN FASE 2.12C: {$pruebasExitosas}/{$pruebasEjecutadas} PRUEBAS EXITOSAS\n";
if ($pruebasFallidas > 0) {
    echo "¡ALERTA! {$pruebasFallidas} PRUEBAS FALLIDAS\n";
} else {
    echo "TODAS LAS PRUEBAS DE FASE 2.12C COMPLETADAS CON ÉXITO [PASS]\n";
}
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
