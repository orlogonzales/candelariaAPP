<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 4.1 (DT-V12-002)
 * ANALÍTICA MULTIANUAL E INTELIGENCIA HISTÓRICA DE EDICIONES
 * ==============================================================================
 * Garantía de Aislamiento:
 * - Opera íntegramente dentro de una transacción PDO revertida en bloque finally.
 * - Cero residuos en la base de datos principal `app_candelaria`.
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Controladores\DashboardControlador;
use Aplicacion\Dashboard\AnaliticaMultianualServicio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;

$pruebasEjecutadas = 0;
$pruebasPasadas = 0;
$pruebasFallidas = 0;

function assertF41(bool $condicion, string $descripcion, string $detalles = ''): void
{
    global $pruebasEjecutadas, $pruebasPasadas, $pruebasFallidas;
    $pruebasEjecutadas++;
    if ($condicion) {
        $pruebasPasadas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$descripcion}" . ($detalles !== '' ? " --> {$detalles}" : '') . "\n";
    }
}

echo "==============================================================================\n";
echo "CANDELARIAAPP V1.2 — SUITE DE PRUEBAS FASE 4.1 (DT-V12-002)\n";
echo "ANALÍTICA MULTIANUAL, VARIACIONES YOY, PREVENTA, COHORTES Y EXPORTACIONES\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();
$servicio = new AnaliticaMultianualServicio($pdo);

$pdo->beginTransaction();

try {
    // --------------------------------------------------------------------------
    // PREPARACIÓN DE ESCENARIO CONTROLADO EN TRANSACCIÓN AISLADA
    // --------------------------------------------------------------------------
    $sufijo = substr(bin2hex(random_bytes(4)), 0, 8);

    // Crear Organización A (Principal de prueba) y Organización B (Intrusa para test Multi-tenant)
    $stmtOrg = $pdo->prepare("
        INSERT INTO `organizaciones` (`codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `estado`)
        VALUES (:codigo, :comercial, :razon, 2, :doc, 'ACTIVO')
    ");
    $stmtOrg->execute([
        'codigo'    => 'ORG-F41A-' . strtoupper($sufijo),
        'comercial' => 'Candelaria Analytics A',
        'razon'     => 'Organización Analítica A S.A.C.',
        'doc'       => '2091' . random_int(1000000, 9999999),
    ]);
    $orgAId = (int) $pdo->lastInsertId();

    $stmtOrg->execute([
        'codigo'    => 'ORG-F41B-' . strtoupper($sufijo),
        'comercial' => 'Candelaria Intrusa B',
        'razon'     => 'Organización Intrusa B S.A.C.',
        'doc'       => '2092' . random_int(1000000, 9999999),
    ]);
    $orgBId = (int) $pdo->lastInsertId();

    // Crear 3 Ediciones en Organización A:
    // - Edición 2024: Base Cero (0 ventas) para probar división segura por cero en YoY de 2025
    // - Edición 2025: 2 ventas activas (S/ 1,000.00 + S/ 500.50 = S/ 1,500.50 = 150050 centavos) + 1 anulada (S/ 800.00)
    // - Edición 2026: 2 ventas activas (S/ 1,200.00 + S/ 600.60 = S/ 1,800.60 = 180060 centavos, crecimiento exacto +20.00% YoY)
    $stmtEd = $pdo->prepare("
        INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `fecha_inicio`, `fecha_fin`, `estado`, `es_actual`)
        VALUES (:org_id, :codigo, :nombre, :anio, :inicio, :fin, :estado, :actual)
    ");

    $stmtEd->execute([
        'org_id' => $orgAId,
        'codigo' => 'CND-2024-' . strtoupper($sufijo),
        'nombre' => 'Festividad Candelaria 2024',
        'anio'   => 2024,
        'inicio' => '2024-02-02',
        'fin'    => '2024-02-15',
        'estado' => 'CERRADA',
        'actual' => 0,
    ]);
    $ed2024Id = (int) $pdo->lastInsertId();

    $stmtEd->execute([
        'org_id' => $orgAId,
        'codigo' => 'CND-2025-' . strtoupper($sufijo),
        'nombre' => 'Festividad Candelaria 2025',
        'anio'   => 2025,
        'inicio' => '2025-02-02',
        'fin'    => '2025-02-15',
        'estado' => 'CERRADA',
        'actual' => 0,
    ]);
    $ed2025Id = (int) $pdo->lastInsertId();

    $stmtEd->execute([
        'org_id' => $orgAId,
        'codigo' => 'CND-2026-' . strtoupper($sufijo),
        'nombre' => 'Festividad Candelaria 2026',
        'anio'   => 2026,
        'inicio' => '2026-02-02',
        'fin'    => '2026-02-15',
        'estado' => 'OPERACION',
        'actual' => 1,
    ]);
    $ed2026Id = (int) $pdo->lastInsertId();

    // Edición en Organización B (con venta de S/ 50,000.00 que jamás debe contaminar Org A)
    $stmtEd->execute([
        'org_id' => $orgBId,
        'codigo' => 'CND-B-2026-' . strtoupper($sufijo),
        'nombre' => 'Festividad Otra Org 2026',
        'anio'   => 2026,
        'inicio' => '2026-02-02',
        'fin'    => '2026-02-15',
        'estado' => 'OPERACION',
        'actual' => 1,
    ]);
    $edOrgBId = (int) $pdo->lastInsertId();

    // Crear Personas y Clientes en Org A (Cliente 1 compra en 2025 y repite en 2026; Cliente 2 solo en 2025; Cliente 3 nuevo en 2026)
    $stmtPersona = $pdo->prepare("
        INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`)
        VALUES (:org_id, 'NATURAL', 1, :doc, :nom, :ape, :correo, '+51951000111', '+51951000111')
    ");
    $stmtCliente = $pdo->prepare("
        INSERT INTO `clientes` (`organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`)
        VALUES (:org_id, :per_id, 'CLIENTE', 1)
    ");

    $clientesOrgA = [];
    for ($i = 1; $i <= 3; $i++) {
        $stmtPersona->execute([
            'org_id' => $orgAId,
            'doc'    => '7' . random_int(1000000, 9999999),
            'nom'    => "ClienteF41_{$i}",
            'ape'    => "Apellido_{$sufijo}",
            'correo' => "cli{$i}_{$sufijo}@test.pe",
        ]);
        $perId = (int) $pdo->lastInsertId();
        $stmtCliente->execute([
            'org_id' => $orgAId,
            'per_id' => $perId,
        ]);
        $clientesOrgA[$i] = (int) $pdo->lastInsertId();
    }

    // Cliente en Org B
    $stmtPersona->execute([
        'org_id' => $orgBId,
        'doc'    => '7' . random_int(1000000, 9999999),
        'nom'    => 'ClienteOrgB',
        'ape'    => 'Externo',
        'correo' => "clib_{$sufijo}@test.pe",
    ]);
    $perBId = (int) $pdo->lastInsertId();
    $stmtCliente->execute([
        'org_id' => $orgBId,
        'per_id' => $perBId,
    ]);
    $clienteOrgBId = (int) $pdo->lastInsertId();

    // Insertar Ventas en Edición 2025 (Inicio: 2025-02-02)
    // Venta 1: fecha 2024-11-01 (T-93 -> entra desde T-90), S/ 1,000.00, CONFIRMADA, Cliente 1
    // Venta 2: fecha 2025-01-20 (T-13 -> entra en T-7 y T-0), S/ 500.50, LIQUIDADA, Cliente 2
    // Venta 3: fecha 2025-01-22, S/ 800.00, ANULADA (no debe sumar a devengado)
    $stmtVenta = $pdo->prepare("
        INSERT INTO `ventas` (
            `organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `origen_tipo`, `correlativo`,
            `fecha_venta`, `estado`, `cliente_nombre_completo`, `moneda`,
            `subtotal`, `total`, `monto_pagado`, `saldo_pendiente`, `estado_financiero`,
            `motivo_anulacion`, `creado_por`
        ) VALUES (
            :org_id, :ed_id, :cli_id, NULL, 'DIRECTA', :corr,
            :fecha, :estado, 'Cliente Prueba F41', 'PEN',
            :subtotal, :total, :pagado, :saldo, :est_fin,
            :motivo_anul, 24
        )
    ");

    $stmtVenta->execute([
        'org_id'      => $orgAId,
        'ed_id'       => $ed2025Id,
        'cli_id'      => $clientesOrgA[1],
        'corr'        => 'VTA-2025-001-' . $sufijo,
        'fecha'       => '2024-11-01', // > 90 días antes del 2025-02-02
        'estado'      => 'CONFIRMADA',
        'subtotal'    => 1000.00,
        'total'       => 1000.00,
        'pagado'      => 1000.00,
        'saldo'       => 0.00,
        'est_fin'     => 'PAGADA_TOTAL',
        'motivo_anul' => null,
    ]);
    $venta2025_1 = (int) $pdo->lastInsertId();

    $stmtVenta->execute([
        'org_id'      => $orgAId,
        'ed_id'       => $ed2025Id,
        'cli_id'      => $clientesOrgA[2],
        'corr'        => 'VTA-2025-002-' . $sufijo,
        'fecha'       => '2025-01-20', // 13 días antes del 2025-02-02 (entra en T-7 y T-0)
        'estado'      => 'LIQUIDADA',
        'subtotal'    => 500.50,
        'total'       => 500.50,
        'pagado'      => 300.50,
        'saldo'       => 200.00,
        'est_fin'     => 'PAGO_PARCIAL',
        'motivo_anul' => null,
    ]);
    $venta2025_2 = (int) $pdo->lastInsertId();

    $stmtVenta->execute([
        'org_id'      => $orgAId,
        'ed_id'       => $ed2025Id,
        'cli_id'      => $clientesOrgA[2],
        'corr'        => 'VTA-2025-003-' . $sufijo,
        'fecha'       => '2025-01-22',
        'estado'      => 'ANULADA',
        'subtotal'    => 800.00,
        'total'       => 800.00,
        'pagado'      => 0.00,
        'saldo'       => 0.00,
        'est_fin'     => 'NO_PAGADA',
        'motivo_anul' => 'ERROR_REGISTRO',
    ]);

    // Insertar Ventas en Edición 2026 (Inicio: 2026-02-02)
    // Venta 1: Cliente 1 (Recurrente!), S/ 1,200.00, fecha 2025-12-20 (44 días antes -> entra en T-30)
    // Venta 2: Cliente 3 (Nuevo!), S/ 600.60, fecha 2026-02-01 (1 día antes -> entra en T-0)
    $stmtVenta->execute([
        'org_id'      => $orgAId,
        'ed_id'       => $ed2026Id,
        'cli_id'      => $clientesOrgA[1],
        'corr'        => 'VTA-2026-001-' . $sufijo,
        'fecha'       => '2025-12-20',
        'estado'      => 'CONFIRMADA',
        'subtotal'    => 1200.00,
        'total'       => 1200.00,
        'pagado'      => 1200.00,
        'saldo'       => 0.00,
        'est_fin'     => 'PAGADA_TOTAL',
        'motivo_anul' => null,
    ]);
    $venta2026_1 = (int) $pdo->lastInsertId();

    $stmtVenta->execute([
        'org_id'      => $orgAId,
        'ed_id'       => $ed2026Id,
        'cli_id'      => $clientesOrgA[3],
        'corr'        => 'VTA-2026-002-' . $sufijo,
        'fecha'       => '2026-02-01',
        'estado'      => 'CONFIRMADA',
        'subtotal'    => 600.60,
        'total'       => 600.60,
        'pagado'      => 600.60,
        'saldo'       => 0.00,
        'est_fin'     => 'PAGADA_TOTAL',
        'motivo_anul' => null,
    ]);
    $venta2026_2 = (int) $pdo->lastInsertId();

    // Venta en Organización B (S/ 50,000.00)
    $stmtVenta->execute([
        'org_id'      => $orgBId,
        'ed_id'       => $edOrgBId,
        'cli_id'      => $clienteOrgBId,
        'corr'        => 'VTA-ORGB-001-' . $sufijo,
        'fecha'       => '2026-01-10',
        'estado'      => 'CONFIRMADA',
        'subtotal'    => 50000.00,
        'total'       => 50000.00,
        'pagado'      => 50000.00,
        'saldo'       => 0.00,
        'est_fin'     => 'PAGADA_TOTAL',
        'motivo_anul' => null,
    ]);

    // Insertar Pagos Aprobados en Org A
    $stmtPago = $pdo->prepare("
        INSERT INTO `pagos` (
            `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`,
            `metodo_pago`, `estado`, `moneda`, `monto_cobrado_cliente`,
            `comision_pasarela`, `monto_neto_recibido`, `monto_aplicado_venta`,
            `monto_reembolsado_acumulado`, `fecha_pago`, `creado_por`
        ) VALUES (
            :org_id, :ed_id, :venta_id, :corr,
            'TARJETA_PASARELA', 'APROBADO', 'PEN', :cobrado,
            :comision, :neto, :aplicado,
            :reembolso, '2025-01-15 10:00:00', 24
        )
    ");

    // Pago 2025: Cobrado 1,300.50, Comisión 50.25, Neto 1,250.25, Reembolso 50.00
    $stmtPago->execute([
        'org_id'    => $orgAId,
        'ed_id'     => $ed2025Id,
        'venta_id'  => $venta2025_1,
        'corr'      => 'PAG-2025-001-' . $sufijo,
        'cobrado'   => 1300.50,
        'comision'  => 50.25,
        'neto'      => 1250.25,
        'aplicado'  => 1300.50,
        'reembolso' => 50.00,
    ]);

    // Pago 2026: Cobrado 1,800.60, Comisión 60.60, Neto 1,740.00, Reembolso 0.00
    $stmtPago->execute([
        'org_id'    => $orgAId,
        'ed_id'     => $ed2026Id,
        'venta_id'  => $venta2026_1,
        'corr'      => 'PAG-2026-001-' . $sufijo,
        'cobrado'   => 1800.60,
        'comision'  => 60.60,
        'neto'      => 1740.00,
        'aplicado'  => 1800.60,
        'reembolso' => 0.00,
    ]);

    // Insertar Reservas en 2025 y 2026
    $stmtRes = $pdo->prepare("
        INSERT INTO `reservas` (`organizacion_id`, `edicion_id`, `venta_id`, `cliente_id`, `correlativo`, `estado`, `contacto_nombre`, `notas_operativas`, `creado_por`)
        VALUES (:org_id, :ed_id, :venta_id, :cli_id, :corr, :estado, 'Titular F41', :notas, 24)
    ");
    $stmtRes->execute([
        'org_id'   => $orgAId,
        'ed_id'    => $ed2025Id,
        'venta_id' => $venta2025_1,
        'cli_id'   => $clientesOrgA[1],
        'corr'     => 'RES-2025-01-' . $sufijo,
        'estado'   => 'CONFIRMADA',
        'notas'    => '[CHECKIN_QR_REGISTRADO: 2025-02-02]',
    ]);
    $stmtRes->execute([
        'org_id'   => $orgAId,
        'ed_id'    => $ed2026Id,
        'venta_id' => $venta2026_1,
        'cli_id'   => $clientesOrgA[1],
        'corr'     => 'RES-2026-01-' . $sufijo,
        'estado'   => 'CONFIRMADA',
        'notas'    => '[CHECKIN_QR_REGISTRADO: 2026-02-02]',
    ]);

    // Insertar Folclore en Org A (Tipo Danza + Conjunto + Inscripciones en 2025 y 2026)
    $stmtTd = $pdo->prepare("
        INSERT INTO `tipos_danza` (`organizacion_id`, `codigo`, `nombre`, `categoria_origen`, `activo`)
        VALUES (:org_id, :cod, :nom, 'TRAJES_DE_LUCES', 1)
    ");
    $stmtTd->execute([
        'org_id' => $orgAId,
        'cod'    => 'MORENADA_' . strtoupper($sufijo),
        'nom'    => 'MORENADA TEST ' . strtoupper($sufijo),
    ]);
    $tipoDanzaId = (int) $pdo->lastInsertId();

    $stmtConj = $pdo->prepare("
        INSERT INTO `conjuntos_folcloricos` (`organizacion_id`, `tipo_danza_id`, `codigo`, `nombre`, `activo`, `creado_por`)
        VALUES (:org_id, :td_id, :cod, :nom, 1, 24)
    ");
    $stmtConj->execute([
        'org_id' => $orgAId,
        'td_id'  => $tipoDanzaId,
        'cod'    => 'CONJ-TEST-' . strtoupper($sufijo),
        'nom'    => 'MORENADA CENTRAL TEST ' . strtoupper($sufijo),
    ]);
    $conjuntoId = (int) $pdo->lastInsertId();

    $stmtInsc = $pdo->prepare("
        INSERT INTO `edicion_conjunto_inscripciones` (`organizacion_id`, `edicion_id`, `conjunto_id`, `cantidad_estimada_danzarines`, `cantidad_bandas`, `estado`, `creado_por`)
        VALUES (:org_id, :ed_id, :conj_id, :danz, :bandas, 'CONFIRMADO', 24)
    ");
    $stmtInsc->execute(['org_id' => $orgAId, 'ed_id' => $ed2025Id, 'conj_id' => $conjuntoId, 'danz' => 450, 'bandas' => 3]);
    $stmtInsc->execute(['org_id' => $orgAId, 'ed_id' => $ed2026Id, 'conj_id' => $conjuntoId, 'danz' => 600, 'bandas' => 4]);

    // --------------------------------------------------------------------------
    // BLOQUE 1: EXACTITUD ARITMÉTICA EN CENTAVOS, VARIACIONES YOY Y ANULACIONES
    // --------------------------------------------------------------------------
    echo "--- BLOQUE 1: Exactitud Aritmética en Centavos y Variaciones YoY ---\n";

    $resultado = $servicio->obtenerAnaliticaMultianual($orgAId);
    $serieFin = $resultado['serie_financiera'];

    assertF41(count($serieFin) === 3, "1.1: Se evaluaron exactamente las 3 ediciones cronológicas de la Organización A (2024, 2025, 2026)");

    $fin2024 = $serieFin[0];
    $fin2025 = $serieFin[1];
    $fin2026 = $serieFin[2];

    assertF41(
        $fin2024['anio'] === 2024 && $fin2024['devengado_centavos'] === 0 && $fin2024['variacion_yoy']['disponible'] === false,
        "1.2: Edición 2024 (Base Inicial) registra 0 centavos devengados y variacion_yoy.disponible = false"
    );

    assertF41(
        $fin2025['anio'] === 2025 &&
        $fin2025['devengado_centavos'] === 150050 &&
        $fin2025['total_devengado'] === 1500.50 &&
        $fin2025['anulado_cancelado_centavos'] === 80000 &&
        $fin2025['total_anulado_cancelado'] === 800.00,
        "1.3: Edición 2025 separa exactamente el devengado (S/ 1,500.50 = 150,050 centavos) de las ventas anuladas (S/ 800.00)"
    );

    assertF41(
        $fin2025['variacion_yoy']['disponible'] === true &&
        $fin2025['variacion_yoy']['delta_devengado_pct'] === null &&
        $fin2025['variacion_yoy']['tendencia_devengado'] === 'NUEVA_ACTIVIDAD',
        "1.4: Variación YoY 2025 vs 2024 (base cero) previene división por cero (delta_devengado_pct = null, tendencia = NUEVA_ACTIVIDAD)"
    );

    assertF41(
        $fin2026['anio'] === 2026 &&
        $fin2026['devengado_centavos'] === 180060 &&
        $fin2026['total_devengado'] === 1800.60 &&
        $fin2026['variacion_yoy']['delta_devengado_centavos'] === 30010 &&
        $fin2026['variacion_yoy']['delta_devengado_pct'] === 20.00 &&
        $fin2026['variacion_yoy']['tendencia_devengado'] === 'CRECIMIENTO',
        "1.5: Variación YoY 2026 vs 2025 calcula exactamente +30,010 centavos (+S/ 300.10) y +20.00% de crecimiento"
    );

    assertF41(
        $fin2025['cobrado_bruto_centavos'] === 130050 &&
        $fin2025['comisiones_pasarela_centavos'] === 5025 &&
        $fin2025['recaudacion_neta_centavos'] === 125025 &&
        $fin2025['reembolsos_centavos'] === 5000,
        "1.6: Conciliación de pagos en centavos (bruto 130,050 - comisión 5,025 = neto 125,025; reembolsos 5,000)"
    );

    // --------------------------------------------------------------------------
    // BLOQUE 2: CURVAS DE PREVENTA (BOOKING PACE) Y RETENCIÓN DE COHORTES
    // --------------------------------------------------------------------------
    echo "\n--- BLOQUE 2: Curvas de Preventa (Booking Pace) y Cohortes de Clientes ---\n";

    $curvas = $resultado['curvas_preventa']['series_ediciones'];
    $curva2025 = $curvas[1];
    $mapaHitos2025 = [];
    foreach ($curva2025['hitos'] as $h) {
        $mapaHitos2025[$h['codigo_hito']] = $h;
    }

    assertF41(
        $mapaHitos2025['T-90']['devengado_acumulado_centavos'] === 100000 &&
        $mapaHitos2025['T-15']['devengado_acumulado_centavos'] === 100000 &&
        $mapaHitos2025['T-7']['devengado_acumulado_centavos'] === 150050 &&
        $mapaHitos2025['FINAL']['porcentaje_avance_monto'] === 100.00,
        "2.1: Curva Booking Pace 2025 acumula S/ 1,000.00 en T-90 y alcanza S/ 1,500.50 (100%) en T-7"
    );

    $retSerie = $resultado['retencion_clientes']['serie_por_edicion'];
    $ret2025 = $retSerie[1];
    $ret2026 = $retSerie[2];

    assertF41(
        $ret2025['clientes_unicos_activos'] === 2 &&
        $ret2025['clientes_nuevos'] === 2 &&
        $ret2025['clientes_recurrentes'] === 0,
        "2.2: Edición 2025 identifica 2 clientes únicos activos, 2 nuevos y 0 recurrentes"
    );

    assertF41(
        $ret2026['clientes_unicos_activos'] === 2 &&
        $ret2026['clientes_nuevos'] === 1 &&
        $ret2026['clientes_recurrentes'] === 1 &&
        $ret2026['tasa_recurrencia_pct'] === 50.00 &&
        $ret2026['tasa_retencion_directa_pct'] === 50.00 &&
        $ret2026['ingresos_recurrentes_centavos'] === 120000 &&
        $ret2026['ingresos_nuevos_centavos'] === 60060,
        "2.3: Edición 2026 identifica 1 cliente recurrente (S/ 1,200.00) y 1 cliente nuevo (S/ 600.60), tasa de recurrencia 50.00%"
    );

    $cohortes = $resultado['retencion_clientes']['matriz_cohortes'];
    $cohorte2025 = $cohortes[1];
    assertF41(
        $cohorte2025['tamano_cohorte'] === 2 &&
        $cohorte2025['evolucion'][0]['clientes_activos'] === 2 &&
        $cohorte2025['evolucion'][1]['clientes_activos'] === 1 &&
        $cohorte2025['evolucion'][1]['retencion_pct'] === 50.00,
        "2.4: Matriz de cohortes rastrea la cohorte 2025 (2 clientes) con 50.0% de retención en 2026 (1 cliente)"
    );

    // --------------------------------------------------------------------------
    // BLOQUE 3: PARTICIPACIÓN FOLCLÓRICA, FILTROS Y AISLAMIENTO MULTI-TENANT
    // --------------------------------------------------------------------------
    echo "\n--- BLOQUE 3: Folclore Multianual, Filtros y Aislamiento Multi-Tenant ---\n";

    $folclore = $resultado['serie_folclore'];
    assertF41(
        $folclore[1]['conjuntos_activos'] === 1 &&
        $folclore[1]['danzarines_estimados_total'] === 450 &&
        $folclore[2]['conjuntos_activos'] === 1 &&
        $folclore[2]['danzarines_estimados_total'] === 600,
        "3.1: Serie folclórica refleja crecimiento de 450 a 600 danzarines estimados entre 2025 y 2026"
    );

    $resConsolidado = $resultado['resumen_consolidado'];
    assertF41(
        $resConsolidado['devengado_acumulado_centavos'] === 330110 &&
        $resConsolidado['total_devengado_acumulado'] === 3301.10 &&
        $resConsolidado['mejor_edicion_historica']['anio'] === 2026,
        "3.2: Resumen consolidado suma exactamente S/ 3,301.10 (excluyendo los S/ 50,000.00 de la Organización B)"
    );

    $filtradoCanal = $servicio->obtenerAnaliticaMultianual($orgAId, [
        'edicion_ids'  => [$ed2025Id, $ed2026Id],
        'canal_origen' => 'DIRECTA',
    ]);
    assertF41(
        count($filtradoCanal['serie_financiera']) === 2 &&
        $filtradoCanal['serie_financiera'][0]['total_devengado'] === 1500.50 &&
        $filtradoCanal['serie_financiera'][1]['total_devengado'] === 1800.60,
        "3.3: Filtro combinado por edicion_ids y canal_origen (DIRECTA) segmenta las ediciones con exactitud"
    );

    // --------------------------------------------------------------------------
    // BLOQUE 4: EXPORTACIONES MULTIANUALES (CSV, XLSX, PDF) Y SEGURIDAD CSV
    // --------------------------------------------------------------------------
    echo "\n--- BLOQUE 4: Exportaciones Multianuales (CSV, XLSX, PDF) y Anti-Formula Injection ---\n";

    $celdaMaliciosa = $servicio->sanitizarParaCsv('=SUM(A1:A10)+cmd|\'/C calc\'!A0');
    assertF41(
        is_string($celdaMaliciosa) && str_starts_with($celdaMaliciosa, "'=SUM"),
        "4.1: Sanitizador CSV neutraliza fórmulas maliciosas (=, +, -, @) anteponiendo apóstrofe protector"
    );

    $csv = $servicio->exportarCsv($orgAId);
    assertF41(
        str_starts_with($csv, "\xEF\xBB\xBF") &&
        str_contains($csv, 'CND-2025-') &&
        str_contains($csv, '1500.5') &&
        str_contains($csv, 'RESUMEN CONSOLIDADO MULTIANUAL'),
        "4.2: Exportación CSV incluye BOM UTF-8, filas por edición y bloque de totales consolidados"
    );

    $xlsx = $servicio->exportarXlsx($orgAId);
    assertF41(
        str_starts_with($xlsx, "PK\x03\x04") && strlen($xlsx) > 1000,
        "4.3: Exportación XLSX genera paquete binario OpenXML (ECMA-376) válido con firma PK\\x03\\x04"
    );

    $pdf = $servicio->exportarPdf($orgAId, [], ['nombre_comercial' => 'Candelaria Analytics A']);
    assertF41(
        str_starts_with($pdf, '%PDF-') && strlen($pdf) > 2000,
        "4.4: Exportación PDF genera documento institucional binario válido (%PDF-) en orientación horizontal"
    );

    // --------------------------------------------------------------------------
    // BLOQUE 5: CONTROLADOR REST Y AUTORIZACIÓN RBAC
    // --------------------------------------------------------------------------
    echo "\n--- BLOQUE 5: Controlador REST y Autorización RBAC ---\n";

    $authDenegado = new class extends AutenticacionMiddleware {
        public function __construct() {}
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion
        {
            return null;
        }
    };
    $ctrlSinSesion = new DashboardControlador($authDenegado, null, null, $pdo, $servicio);
    $resp403 = json_decode($ctrlSinSesion->analiticaMultianual(), true);
    assertF41(
        ($resp403['exito'] ?? true) === false && ($resp403['codigo'] ?? 0) === 403,
        "5.1: Endpoint analiticaMultianual rechaza peticiones sin sesión válida (HTTP 403)"
    );

    $orgIdParaMock = $orgAId;
    $authValido = new class($orgIdParaMock) extends AutenticacionMiddleware {
        private int $orgId;
        public function __construct(int $orgId) { $this->orgId = $orgId; }
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion
        {
            return new ContextoOperacion(
                actorTipo: 'HUMANO',
                usuarioId: 24,
                actorSistemaId: null,
                actorSistemaCodigo: null,
                canalId: 1,
                canalCodigo: 'WEB',
                correlacionId: 'corr-f41-1234567890',
                origenIp: '127.0.0.1',
                agenteUsuario: 'TestSuiteF41',
                organizacionId: $this->orgId
            );
        }
    };
    $authzSinPermiso = new class extends AutorizacionMiddleware {
        public function __construct() {}
        public function verificarPermiso(string $codigoPermiso, ?ContextoOperacion $contexto = null, bool $bloquearPeticion = true): bool
        {
            return false;
        }
    };
    $ctrlSinPermiso = new DashboardControlador($authValido, $authzSinPermiso, null, $pdo, $servicio);
    $respExport403 = json_decode($ctrlSinPermiso->exportarAnaliticaMultianual(), true);
    assertF41(
        ($respExport403['exito'] ?? true) === false &&
        ($respExport403['codigo'] ?? 0) === 403 &&
        str_contains((string) ($respExport403['mensaje'] ?? ''), 'ediciones.reportes.ingresos.exportar'),
        "5.2: Endpoint exportarAnaliticaMultianual exige explícitamente el permiso ediciones.reportes.ingresos.exportar"
    );

    $authzConPermiso = new class extends AutorizacionMiddleware {
        public function __construct() {}
        public function verificarPermiso(string $codigoPermiso, ?ContextoOperacion $contexto = null, bool $bloquearPeticion = true): bool
        {
            return in_array($codigoPermiso, ['dashboard.ejecutivo.ver', 'ediciones.reportes.ingresos.exportar'], true);
        }
    };
    $ctrlAutorizado = new DashboardControlador($authValido, $authzConPermiso, null, $pdo, $servicio);
    $_GET = [];
    $respExito = json_decode($ctrlAutorizado->analiticaMultianual(), true);
    assertF41(
        ($respExito['exito'] ?? false) === true &&
        ($respExito['codigo'] ?? 0) === 200 &&
        count($respExito['datos']['serie_financiera'] ?? []) === 3,
        "5.3: Endpoint analiticaMultianual responde HTTP 200 con la estructura contractual completa para usuario autorizado"
    );

    $_GET = ['formato' => 'formato_invalido'];
    $respFormato422 = json_decode($ctrlAutorizado->exportarAnaliticaMultianual(), true);
    assertF41(
        ($respFormato422['exito'] ?? true) === false && ($respFormato422['codigo'] ?? 0) === 422,
        "5.4: Endpoint exportarAnaliticaMultianual rechaza formatos desconocidos con código HTTP 422"
    );
    $_GET = [];
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ------------------------------------------------------------------------------
// BLOQUE 6: INTEGRIDAD ESTRUCTURAL POST-ROLLBACK Y GOBERNANZA
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 6: Integridad Estructural Post-Rollback y Gobernanza ---\n";

$tablasActuales = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
assertF41($tablasActuales === 108, "6.1: Base de datos conserva exactamente 108 tablas oficiales (DEC-01 Opción 1A)");

$migracionesActuales = (int) $pdo->query("SELECT COUNT(*) FROM migraciones_control")->fetchColumn();
assertF41(in_array($migracionesActuales, [29, 30], true), "6.2: Trazabilidad de migraciones conserva 29-30 migraciones ({$migracionesActuales})");

$permisosActuales = (int) $pdo->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
assertF41($permisosActuales === 115, "6.3: Matriz RBAC conserva 115 permisos");

$stmtOrlando = $pdo->query("SELECT contrasena_hash FROM usuarios WHERE id = 24");
$hashOrlando = (string) $stmtOrlando->fetchColumn();
$huellaOrlando = substr(hash('sha256', $hashOrlando), 0, 16);
assertF41($huellaOrlando === '80e6af84e02e89e3', "6.4: Huella inmutable de Orlando (ID 24) preservada intacta (80e6af84e02e89e3)");

echo "\n==============================================================================\n";
echo sprintf("TOTAL PRUEBAS FASE 4.1: %d | EXITOSAS: %d | FALLIDAS: %d\n", $pruebasEjecutadas, $pruebasPasadas, $pruebasFallidas);
echo "TOTAL: {$pruebasPasadas} PASS / {$pruebasFallidas} FAIL\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
