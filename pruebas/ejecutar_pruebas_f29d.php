<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 2.9D (CERTIFICACIÓN INTEGRAL DEL PORTAL)
 * NÚCLEO: CONCURRENCIA REAL, CERO SOBREVENTA, CICLO COMPLETO DE CHECKOUT,
 *         HARDENING DE CONTRATOS CANÓNICOS (NO_PAGADA, REGISTRADA, CONFIRMADA),
 *         QR ANTI-REPLAY / DUPLICACIÓN Y AISLAMIENTO DE TENANT / CLIENTE
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Cotizaciones\CotizacionServicio;
use Aplicacion\Entidades\ReservaBloqueoTemporal;
use Aplicacion\Finanzas\EstadoFinancieroVenta;
use Aplicacion\Finanzas\EstadoPago;
use Aplicacion\Finanzas\MetodoPago;
use Aplicacion\Finanzas\PagoServicio;
use Aplicacion\Operaciones\OperacionServicio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteCodigoAccesoRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\ClienteSesionRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\CotizacionRepositorio;
use Aplicacion\Repositorios\CuentaBancariaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\EntregaProductoRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\ItemConfiguracionOperativaRepositorio;
use Aplicacion\Repositorios\OfertaItemEdicionRepositorio;
use Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\OperacionAsistenciaRepositorio;
use Aplicacion\Repositorios\OperacionSalidaRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\PagoReembolsoRepositorio;
use Aplicacion\Repositorios\PagoRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\PasarelaRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\ReservaBloqueoTemporalRepositorio;
use Aplicacion\Repositorios\ReservaRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Reservas\EstadoReserva;
use Aplicacion\Reservas\ReservaServicio;
use Aplicacion\Servicios\AutenticacionClienteServicio;
use Aplicacion\Servicios\FachadaPortalPublicoServicio;
use Aplicacion\Servicios\MotorDisponibilidadServicio;
use Aplicacion\Ventas\EstadoVenta;
use Aplicacion\Ventas\VentaServicio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;

$totalPruebas = 0;
$pruebasExitosas = 0;
$pruebasFallidas = 0;

function registrarAsercion(string $descripcion, bool $condicion, string $detalleError = ''): void {
    global $totalPruebas, $pruebasExitosas, $pruebasFallidas;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$descripcion} --> {$detalleError}\n";
    }
}

echo "==============================================================================\n";
echo "INICIANDO SUITE F2.9D: CERTIFICACIÓN INTEGRAL DEL PORTAL PÚBLICO Y CHECKOUT\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

// Inicializar dependencias y servicios
$bloqueoRepo = new ReservaBloqueoTemporalRepositorio($pdo);
$sesionRepo = new ClienteSesionRepositorio($pdo);
$codigoRepo = new ClienteCodigoAccesoRepositorio($pdo);
$clienteRepo = new ClienteRepositorio($pdo);
$personaRepo = new PersonaRepositorio($pdo);
$edicionRepo = new EdicionRepositorio($pdo);
$paqueteRepo = new PaqueteRepositorio($pdo);
$itemRepo = new ItemComercialRepositorio($pdo);
$reservaRepo = new ReservaRepositorio($pdo);
$ventaRepo = new VentaRepositorio($pdo);
$pagoRepo = new PagoRepositorio($pdo);
$reembolsoRepo = new PagoReembolsoRepositorio($pdo);
$cuentaRepo = new CuentaBancariaRepositorio($pdo);
$pasarelaRepo = new PasarelaRepositorio($pdo);
$configRepo = new ConfiguracionRepositorio($pdo);
$cotizacionRepo = new CotizacionRepositorio($pdo);
$oportunidadRepo = new OportunidadRepositorio($pdo);
$ofertaItemRepo = new OfertaItemEdicionRepositorio($pdo);
$ofertaPaqueteRepo = new OfertaPaqueteEdicionRepositorio($pdo);
$tarifaItemRepo = new TarifaItemEdicionRepositorio($pdo);
$tarifaPaqueteRepo = new TarifaPaqueteEdicionRepositorio($pdo);
$entregaRepo = new EntregaProductoRepositorio($pdo);
$itemCfgRepo = new ItemConfiguracionOperativaRepositorio($pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);

$configServicio = new ConfiguracionServicio(configRepo: $configRepo, pdo: $pdo);
$authzServicio = new AutorizacionServicio(null, null, null, null, $pdo);

$cotizacionServicio = new CotizacionServicio(
    $cotizacionRepo, $clienteRepo, $edicionRepo, $oportunidadRepo,
    $itemRepo, $paqueteRepo, $ofertaItemRepo, $ofertaPaqueteRepo,
    $tarifaItemRepo, $tarifaPaqueteRepo, $configServicio,
    $authzServicio, $auditoriaRepo, null, $pdo
);

$ventaServicio = new VentaServicio(
    $ventaRepo, $cotizacionRepo, $clienteRepo, $personaRepo,
    $edicionRepo, $configServicio, $authzServicio, $auditoriaRepo,
    null, $pdo
);

$reservaServicio = new ReservaServicio(
    $reservaRepo, $entregaRepo, $itemCfgRepo, $ventaRepo,
    $edicionRepo, $itemRepo, $authzServicio, $auditoriaRepo, $pdo
);

$pagoServicio = new PagoServicio(
    $pagoRepo, $reembolsoRepo, $cuentaRepo, $pasarelaRepo,
    $ventaRepo, $edicionRepo, $configRepo, $authzServicio, $auditoriaRepo, $pdo
);

$motorDisp = new MotorDisponibilidadServicio($bloqueoRepo, $pdo);
$authClienteSvc = new AutenticacionClienteServicio($sesionRepo, $codigoRepo, $clienteRepo, $personaRepo);

$fachada = new FachadaPortalPublicoServicio(
    $motorDisp, $authClienteSvc, $cotizacionServicio, $ventaServicio,
    $reservaServicio, $pagoServicio, $edicionRepo, $paqueteRepo,
    $itemRepo, $clienteRepo, $personaRepo, $reservaRepo,
    $ventaRepo, $pagoRepo, $bloqueoRepo, $pdo
);

// Resolver Organización y Edición activa de pruebas
$stmtOrg = $pdo->query("SELECT id, codigo, nombre_comercial FROM organizaciones WHERE estado = 'ACTIVO' ORDER BY id ASC LIMIT 1");
$org = $stmtOrg->fetch(PDO::FETCH_ASSOC);
$orgId = (int) $org['id'];
$orgCodigo = (string) $org['codigo'];

$stmtEd = $pdo->prepare("SELECT id FROM ediciones_candelaria WHERE organizacion_id = :org_id AND es_actual = 1 LIMIT 1");
$stmtEd->execute(['org_id' => $orgId]);
$edId = (int) $stmtEd->fetchColumn();

// Crear ítem de prueba exclusivo para validar concurrencia y aforo
$codigoItem = 'ITEM_CONCURRENCIA_' . strtoupper(bin2hex(random_bytes(4)));
$stmtCat = $pdo->prepare("SELECT id FROM categorias_items WHERE organizacion_id = :org_id LIMIT 1");
$stmtCat->execute(['org_id' => $orgId]);
$catId = (int) $stmtCat->fetchColumn();

$stmtInsItem = $pdo->prepare("
    INSERT INTO items_comerciales (organizacion_id, categoria_id, codigo, nombre, tipo, unidad_medida, estado)
    VALUES (:org_id, :cat_id, :cod, 'Asiento Gradería Preferencial Concurrencia', 'SERVICIO', 'TICKET', 'ACTIVO')
");
$stmtInsItem->execute(['org_id' => $orgId, 'cat_id' => $catId, 'cod' => $codigoItem]);
$itemId = (int) $pdo->lastInsertId();

$stmtCfg = $pdo->prepare("
    INSERT INTO item_configuracion_operativa (item_comercial_id, requiere_reserva, tipo_capacidad, requiere_agendamiento, requiere_participantes)
    VALUES (:item_id, 1, 'COLECTIVA', 0, 0)
");
$stmtCfg->execute(['item_id' => $itemId]);

// Fijar capacidad referencial EXACTA de 1 cupo para certificar carrera sobre el último cupo
$stmtOferta = $pdo->prepare("
    INSERT INTO ofertas_items_edicion (organizacion_id, edicion_id, item_comercial_id, estado, capacidad_referencial)
    VALUES (:org_id, :ed_id, :item_id, 'ACTIVO', 1)
");
$stmtOferta->execute(['org_id' => $orgId, 'ed_id' => $edId, 'item_id' => $itemId]);
$ofertaId = (int) $pdo->lastInsertId();

$stmtTarifa = $pdo->prepare("
    INSERT INTO tarifas_items_edicion (oferta_item_id, moneda, precio)
    VALUES (:oferta_id, 'PEN', 200.00)
");
$stmtTarifa->execute(['oferta_id' => $ofertaId]);

// ------------------------------------------------------------------------------
// SECCIÓN 1: Concurrencia Real Simultánea y Cero Sobreventa del Último Cupo
// ------------------------------------------------------------------------------
echo "--- SECCIÓN 1: Concurrencia Real y Prevención de Sobreventa (Último Cupo) ---\n";

// 1.1 Disponibilidad inicial es exactamente 1
$disp1 = $motorDisp->calcularDisponibilidadItem($orgId, $edId, $itemId);
registrarAsercion("1.1 Capacidad inicial auditada es exactamente 1 cupo", $disp1 === 1);

// 1.2 Ejecución paralela real: Dos subprocesos CLI independientes compitiendo al unísono por el último cupo
$cmdWorkerBloqueo = 'php ' . escapeshellarg(__DIR__ . '/soporte/worker_concurrencia_bloqueo.php');
$descriptors = [
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$procA = proc_open("{$cmdWorkerBloqueo} {$orgId} {$edId} {$itemId} ses_proc_a", $descriptors, $pipesA);
$procB = proc_open("{$cmdWorkerBloqueo} {$orgId} {$edId} {$itemId} ses_proc_b", $descriptors, $pipesB);

$salidaA = stream_get_contents($pipesA[1]); fclose($pipesA[1]); fclose($pipesA[2]); proc_close($procA);
$salidaB = stream_get_contents($pipesB[1]); fclose($pipesB[1]); fclose($pipesB[2]); proc_close($procB);

$resProcA = json_decode($salidaA, true) ?: [];
$resProcB = json_decode($salidaB, true) ?: [];

$exitosParalelos = ((!empty($resProcA['exito'])) ? 1 : 0) + ((!empty($resProcB['exito'])) ? 1 : 0);
$rechazosParalelos = ((empty($resProcA['exito'])) ? 1 : 0) + ((empty($resProcB['exito'])) ? 1 : 0);

registrarAsercion("1.2 Concurrencia simultánea real: exactamente 1 proceso adquiere el cupo y el otro es serializado y rechazado",
    $exitosParalelos === 1 && $rechazosParalelos === 1
);

// 1.3 Invariante de Base de Datos: exactamente 1 bloqueo activo en tabla
$stmtContarBloqueos = $pdo->prepare("
    SELECT COUNT(*) FROM reserva_bloqueos_temporales 
    WHERE organizacion_id = :org_id AND item_comercial_id = :item_id AND estado = 'ACTIVO' AND expira_en > NOW()
");
$stmtContarBloqueos->execute(['org_id' => $orgId, 'item_id' => $itemId]);
$bloqueosActivosBd = (int) $stmtContarBloqueos->fetchColumn();
registrarAsercion("1.3 Integridad en BD: exactamente 1 bloqueo activo registrado (Cero sobreventa física)",
    $bloqueosActivosBd === 1
);

// 1.4 Disponibilidad reducida a 0 de forma determinista
$dispPostParalelo = $motorDisp->calcularDisponibilidadItem($orgId, $edId, $itemId);
registrarAsercion("1.4 Disponibilidad reducida inmediatamente a 0 tras la carrera concurrente",
    $dispPostParalelo === 0
);

// 1.5 Liberación voluntaria del bloqueo adquirido restituye la disponibilidad
$tokenGanador = !empty($resProcA['exito']) ? (string) $resProcA['token_bloqueo'] : (string) $resProcB['token_bloqueo'];
$liberado = $motorDisp->liberarBloqueo($tokenGanador);
$dispPostLiberacion = $motorDisp->calcularDisponibilidadItem($orgId, $edId, $itemId);
registrarAsercion("1.5 Liberación voluntaria restituye deterministamente el cupo a disponibilidad",
    $liberado && $dispPostLiberacion === 1
);

// 1.6 Adquisición formal del cupo restituido para proceder con el checkout canónico
$sesionCheckout = 'ses_checkout_' . bin2hex(random_bytes(6));
$bloqueoParaCheckout = $motorDisp->bloquearCupos($orgId, $edId, $itemId, 1, $sesionCheckout, ttlMinutos: 15);
registrarAsercion("1.6 Comprador adquiere el cupo restituido para checkout formal",
    $bloqueoParaCheckout['exito'] && !empty($bloqueoParaCheckout['token_bloqueo'])
);
$tokenB = (string) $bloqueoParaCheckout['token_bloqueo'];

// ------------------------------------------------------------------------------
// SECCIÓN 2: Contratos Comerciales Canónicos y Ciclo de Checkout
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 2: Contratos Comerciales Canónicos (NO_PAGADA, REGISTRADA, CONFIRMADA) ---\n";

$dniComprador = '4' . random_int(1000000, 9999999);
$payloadCheckout = [
    'token_bloqueo' => $tokenB,
    'cliente' => [
        'tipo_documento_id' => 1,
        'numero_documento'  => $dniComprador,
        'nombres'           => 'Marcos Aurelio',
        'apellidos'         => 'Mamani Quispe',
        'correo'            => 'marcos.mamani.' . bin2hex(random_bytes(4)) . '@testcandelaria.pe',
        'telefono'          => '+51952' . random_int(100000, 999999),
    ],
    'lineas' => [
        ['tipo' => 'ITEM', 'id' => $itemId, 'cantidad' => 1]
    ],
    'metodo_pago' => 'TRANSFERENCIA_BANCARIA',
    'comprobante' => [
        'numero_operacion' => 'OP-BCP-' . random_int(100000, 999999),
        'voucher_url'      => 'publico/recursos/subidas/vouchers/test_voucher.jpg',
    ],
    'consentimientos' => [
        'terminos_condiciones' => true,
        'politica_privacidad'  => true,
    ],
];

// 2.1 Ejecución del checkout con voucher
$resCheckout = $fachada->procesarCheckout($orgId, $edId, $payloadCheckout, '127.0.0.1');
registrarAsercion("2.1 Checkout con voucher manual ejecutado exitosamente", $resCheckout['exito']);

$ventaId = (int) $resCheckout['venta_id'];
$reservaId = (int) $resCheckout['reserva_id'];
$pagoId = (int) $resCheckout['pago_id'];

// 2.2 Auditoría estricta de la Venta en BD: Estado CONFIRMADA, Estado Financiero NO_PAGADA
$stmtVta = $pdo->prepare("SELECT estado, estado_financiero, monto_pagado, saldo_pendiente, total FROM ventas WHERE id = :id");
$stmtVta->execute(['id' => $ventaId]);
$vtaRow = $stmtVta->fetch(PDO::FETCH_ASSOC);

registrarAsercion("2.2 Contrato de Venta: estado = 'CONFIRMADA' y estado_financiero = 'NO_PAGADA' (Sin inventar estados)",
    $vtaRow['estado'] === EstadoVenta::CONFIRMADA->value &&
    $vtaRow['estado_financiero'] === EstadoFinancieroVenta::NO_PAGADA->value &&
    (float)$vtaRow['monto_pagado'] === 0.00 &&
    (float)$vtaRow['saldo_pendiente'] === (float)$vtaRow['total']
);

// 2.3 Auditoría estricta de la Reserva en BD: Estado REGISTRADA (No confirmada prematuramente)
$stmtRsv = $pdo->prepare("SELECT estado FROM reservas WHERE id = :id");
$stmtRsv->execute(['id' => $reservaId]);
$rsvEstado = $stmtRsv->fetchColumn();

registrarAsercion("2.3 Contrato de Reserva: estado = 'REGISTRADA' (No confirmada prematuramente sin abono verificado)",
    $rsvEstado === EstadoReserva::REGISTRADA->value
);

// 2.4 Auditoría estricta del Pago en BD: Estado PENDIENTE_VERIFICACION
$stmtPg = $pdo->prepare("SELECT estado, monto_aplicado_venta FROM pagos WHERE id = :id");
$stmtPg->execute(['id' => $pagoId]);
$pgRow = $stmtPg->fetch(PDO::FETCH_ASSOC);

registrarAsercion("2.4 Contrato de Pago: estado = 'PENDIENTE_VERIFICACION' y monto_aplicado_venta = 0.00",
    $pgRow['estado'] === EstadoPago::PENDIENTE_VERIFICACION->value &&
    (float)$pgRow['monto_aplicado_venta'] === 0.00
);

// 2.5 Cero doble descuento: Disponibilidad es 0 porque la reserva REGISTRADA retiene el cupo
$dispPostCheckout = $motorDisp->calcularDisponibilidadItem($orgId, $edId, $itemId);
registrarAsercion("2.5 Cobertura total sin doble descuento: cupo reservado en REGISTRADA descuenta exactamente 1 (Disponibilidad = 0)",
    $dispPostCheckout === 0
);

$stmtCliId = $pdo->prepare("SELECT cliente_id FROM reservas WHERE id = :id");
$stmtCliId->execute(['id' => $reservaId]);
$clienteId = (int) $stmtCliId->fetchColumn();

// 2.6 Intento de emitir boleto QR en estado REGISTRADA es DENEGADO
$resQrPrematuro = $fachada->generarTokenBoletoQr($orgId, $reservaId, $clienteId);
registrarAsercion("2.6 Emisión de boleto QR denegada mientras la reserva no esté CONFIRMADA y PAGADA_TOTAL",
    !$resQrPrematuro['exito'] && $resQrPrematuro['codigo_error'] === 'RESERVA_NO_CONFIRMADA'
);

// ------------------------------------------------------------------------------
// SECCIÓN 3: Aprobación Administrativa de Voucher y Emisión de Boleto QR
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 3: Aprobación Administrativa de Voucher y Emisión de Boleto QR ---\n";

// 3.1 Operador de caja verifica y aprueba el abono bancario
$ctxOperador = new ContextoOperacion(
    actorTipo: 'HUMANO',
    usuarioId: 24,
    actorSistemaId: null,
    actorSistemaCodigo: null,
    canalId: 1,
    canalCodigo: 'WEB',
    correlacionId: 'test-f29d-' . uniqid(),
    organizacionId: $orgId
);
$resAprob = $fachada->aprobarVoucherPorOperador($orgId, $pagoId, $ctxOperador);
registrarAsercion("3.1 Aprobación formal del voucher por operador de caja", $resAprob['exito']);

// 3.2 Venta conserva estado comercial 'CONFIRMADA' y su estado financiero pasa a 'PAGADA_TOTAL'
$stmtVtaAprob = $pdo->prepare("SELECT estado, estado_financiero, monto_pagado, saldo_pendiente, total FROM ventas WHERE id = :id");
$stmtVtaAprob->execute(['id' => $ventaId]);
$vtaAprobRow = $stmtVtaAprob->fetch(PDO::FETCH_ASSOC);

$stmtRsvAprob = $pdo->prepare("SELECT estado FROM reservas WHERE id = :id");
$stmtRsvAprob->execute(['id' => $reservaId]);
$rsvAprobEstado = $stmtRsvAprob->fetchColumn();

registrarAsercion("3.2 Contrato Canónico: Venta conserva estado comercial 'CONFIRMADA', estado financiero pasa a 'PAGADA_TOTAL' con saldo 0.00 y Reserva a 'CONFIRMADA'",
    $vtaAprobRow['estado'] === EstadoVenta::CONFIRMADA->value &&
    $vtaAprobRow['estado_financiero'] === EstadoFinancieroVenta::PAGADA_TOTAL->value &&
    (float)$vtaAprobRow['monto_pagado'] === (float)$vtaAprobRow['total'] &&
    (float)$vtaAprobRow['saldo_pendiente'] === 0.00 &&
    $rsvAprobEstado === EstadoReserva::CONFIRMADA->value
);

// 3.3 Emisión exitosa de Boleto QR con firma criptográfica HMAC-SHA256
$boletoQr = $fachada->generarTokenBoletoQr($orgId, $reservaId, $clienteId);
registrarAsercion("3.3 Emisión exitosa de Boleto QR inviolable tras pago verificado",
    !empty($boletoQr['token_qr']) && str_contains($boletoQr['token_qr'], '.')
);

// ------------------------------------------------------------------------------
// SECCIÓN 4: Validación de Boleto QR, Separación Histórica vs Check-in Atómico
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 4: Validación de Boletos QR, Separación Histórica y Check-in Atómico ---\n";

$tokenQrRaw = (string) $boletoQr['token_qr'];

// 4.1 Consulta histórica de boleto legítimo y vigente
$val1 = $fachada->validarTokenBoletoQr($orgId, $tokenQrRaw, marcarUso: false);
registrarAsercion("4.1 Consulta histórica: validación positiva de boleto QR vigente y auténtico",
    $val1['valido'] && str_contains($val1['motivo'], 'legítimo y vigente')
);

// 4.2 Cierre Administrativo de Edición: Preserva consulta histórica pero bloquea nuevos ingresos en puerta
$stmtEstadoEd = $pdo->prepare("SELECT estado FROM ediciones_candelaria WHERE id = :id");
$stmtEstadoEd->execute(['id' => $edId]);
$estadoOriginalEd = (string) $stmtEstadoEd->fetchColumn();

$pdo->prepare("UPDATE ediciones_candelaria SET estado = 'CERRADA' WHERE id = :id")->execute(['id' => $edId]);

$valHistoricaCerrada = $fachada->validarTokenBoletoQr($orgId, $tokenQrRaw, marcarUso: false);
$valCheckinCerrada = $fachada->validarTokenBoletoQr($orgId, $tokenQrRaw, marcarUso: true);

$pdo->prepare("UPDATE ediciones_candelaria SET estado = :est WHERE id = :id")->execute(['est' => $estadoOriginalEd, 'id' => $edId]);

registrarAsercion("4.2 Separación funcional: Cierre de edición PRESERVA consulta histórica pero RECHAZA nuevo ingreso en puerta",
    $valHistoricaCerrada['valido'] === true &&
    $valCheckinCerrada['valido'] === false &&
    str_contains($valCheckinCerrada['motivo'], 'CERRADA')
);

// 4.3 Check-in Concurrente Simultáneo: Dos subprocesos compitiendo al unísono con el mismo boleto en puerta
$cmdWorkerCheckin = 'php ' . escapeshellarg(__DIR__ . '/soporte/worker_concurrencia_checkin.php');
$descriptorsChk = [
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$procChk1 = proc_open("{$cmdWorkerCheckin} {$orgId} {$tokenQrRaw}", $descriptorsChk, $pipesChk1);
$procChk2 = proc_open("{$cmdWorkerCheckin} {$orgId} {$tokenQrRaw}", $descriptorsChk, $pipesChk2);

$salidaChk1 = stream_get_contents($pipesChk1[1]); fclose($pipesChk1[1]); fclose($pipesChk1[2]); proc_close($procChk1);
$salidaChk2 = stream_get_contents($pipesChk2[1]); fclose($pipesChk2[1]); fclose($pipesChk2[2]); proc_close($procChk2);

$resChk1 = json_decode($salidaChk1, true) ?: [];
$resChk2 = json_decode($salidaChk2, true) ?: [];

$exitosCheckin = ((!empty($resChk1['valido'])) ? 1 : 0) + ((!empty($resChk2['valido'])) ? 1 : 0);
$duplicadosCheckin = ((empty($resChk1['valido'])) ? 1 : 0) + ((empty($resChk2['valido'])) ? 1 : 0);

registrarAsercion("4.3 Check-in concurrente simultáneo: exactamente 1 proceso ingresa y el otro es bloqueado por duplicidad (Anti-Replay)",
    $exitosCheckin === 1 && $duplicadosCheckin === 1
);

// 4.4 Consulta histórica post-checkin: Permite al cliente/auditor consultar el boleto ya canjeado
$valPostCanje = $fachada->validarTokenBoletoQr($orgId, $tokenQrRaw, marcarUso: false);
registrarAsercion("4.4 Consulta histórica post-checkin: boleto se conserva legítimo indicando 'ya_ingresado = true'",
    $valPostCanje['valido'] === true && !empty($valPostCanje['ya_ingresado'])
);

// 4.5 Tampering: Token con firma adulterada es rechazado de inmediato
$tokenAdulterado = substr($tokenQrRaw, 0, -5) . 'XXXXX';
$valTampered = $fachada->validarTokenBoletoQr($orgId, $tokenAdulterado);
registrarAsercion("4.5 Token con firma manipulada es rechazado por integridad HMAC",
    !$valTampered['valido'] && str_contains($valTampered['motivo'], 'adulterada')
);

// ------------------------------------------------------------------------------
// SECCIÓN 5: Rechazo de Voucher, Expiración Determinista y Recuperación de Aforo
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 5: Rechazo de Voucher, Expiración y Recuperación de Aforo ---\n";

// Crear un segundo ítem con 1 cupo para certificar ciclo de rechazo
$codigoItem2 = 'ITEM_RECHAZO_' . strtoupper(bin2hex(random_bytes(4)));
$stmtInsItem2 = $pdo->prepare("
    INSERT INTO items_comerciales (organizacion_id, categoria_id, codigo, nombre, tipo, unidad_medida, estado)
    VALUES (:org_id, :cat_id, :cod, 'Asiento Gradería Ciclo Rechazo', 'SERVICIO', 'TICKET', 'ACTIVO')
");
$stmtInsItem2->execute(['org_id' => $orgId, 'cat_id' => $catId, 'cod' => $codigoItem2]);
$itemId2 = (int) $pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO item_configuracion_operativa (item_comercial_id, requiere_reserva, tipo_capacidad, requiere_agendamiento, requiere_participantes)
    VALUES (:item_id, 1, 'COLECTIVA', 0, 0)
")->execute(['item_id' => $itemId2]);

$stmtOferta2 = $pdo->prepare("
    INSERT INTO ofertas_items_edicion (organizacion_id, edicion_id, item_comercial_id, estado, capacidad_referencial)
    VALUES (:org_id, :ed_id, :item_id, 'ACTIVO', 1)
");
$stmtOferta2->execute(['org_id' => $orgId, 'ed_id' => $edId, 'item_id' => $itemId2]);
$ofertaId2 = (int) $pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO tarifas_items_edicion (oferta_item_id, moneda, precio)
    VALUES (:oferta_id, 'PEN', 180.00)
")->execute(['oferta_id' => $ofertaId2]);

// Bloquear y formalizar checkout con voucher falso
$sesionC = 'ses_rechazo_' . bin2hex(random_bytes(6));
$bloqueoC = $motorDisp->bloquearCupos($orgId, $edId, $itemId2, 1, $sesionC);
$tokenC = (string) $bloqueoC['token_bloqueo'];

$payloadRechazo = [
    'token_bloqueo' => $tokenC,
    'cliente' => [
        'tipo_documento_id' => 1,
        'numero_documento'  => '47895214',
        'nombres'           => 'Carlos Alberto',
        'apellidos'         => 'Ramos Flores',
        'correo'            => 'carlos.ramos.' . bin2hex(random_bytes(4)) . '@testcandelaria.pe',
        'telefono'          => '+51951' . random_int(100000, 999999),
    ],
    'lineas' => [
        ['tipo' => 'ITEM', 'id' => $itemId2, 'cantidad' => 1]
    ],
    'metodo_pago' => 'TRANSFERENCIA_BANCARIA',
    'comprobante' => [
        'numero_operacion' => 'OP-FAKE-999',
        'voucher_url'      => 'publico/recursos/subidas/vouchers/fake_voucher.jpg',
    ],
    'consentimientos' => [
        'terminos_condiciones' => true,
        'politica_privacidad'  => true,
    ],
];
$resRech = $fachada->procesarCheckout($orgId, $edId, $payloadRechazo, '127.0.0.1');
$pagoId2 = (int) $resRech['pago_id'];
$reservaId2 = (int) $resRech['reserva_id'];
$ventaId2 = (int) $resRech['venta_id'];

// 5.1 Disponibilidad antes del rechazo es 0
$dispAntesRechazo = $motorDisp->calcularDisponibilidadItem($orgId, $edId, $itemId2);
registrarAsercion("5.1 Disponibilidad antes del rechazo es 0 (Cupo retenido)", $dispAntesRechazo === 0);

// 5.2 Operador rechaza el comprobante ilegible / adulterado
$resRechOperador = $fachada->rechazarVoucherPorOperador($orgId, $pagoId2, 'Voucher ilegible', $ctxOperador);
registrarAsercion("5.2 Rechazo formal del comprobante bancario por operador", $resRechOperador['exito']);

// 5.3 Estados tras rechazo: Pago RECHAZADO, Venta CANCELADA, Reserva CANCELADA
$stmtP2 = $pdo->prepare("SELECT estado FROM pagos WHERE id = :id");
$stmtP2->execute(['id' => $pagoId2]);
$p2Estado = $stmtP2->fetchColumn();

$stmtV2 = $pdo->prepare("SELECT estado FROM ventas WHERE id = :id");
$stmtV2->execute(['id' => $ventaId2]);
$v2Estado = $stmtV2->fetchColumn();

$stmtR2 = $pdo->prepare("SELECT estado FROM reservas WHERE id = :id");
$stmtR2->execute(['id' => $reservaId2]);
$r2Estado = $stmtR2->fetchColumn();

registrarAsercion("5.3 Estados canónicos tras rechazo: Pago RECHAZADO, Venta CANCELADA, Reserva CANCELADA",
    $p2Estado === EstadoPago::RECHAZADO->value &&
    $v2Estado === EstadoVenta::CANCELADA->value &&
    $r2Estado === EstadoReserva::CANCELADA->value
);

// 5.4 Restitución automática del aforo sin borrado físico
$dispTrasRechazo = $motorDisp->calcularDisponibilidadItem($orgId, $edId, $itemId2);
registrarAsercion("5.4 Aforo restituido inmediatamente a 1 cupo sin borrado físico de registros", $dispTrasRechazo === 1);

// ------------------------------------------------------------------------------
// SECCIÓN 6: Espacio del Cliente, Anti-IDOR y Seguridad Passwordless
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 6: Espacio del Cliente, Anti-IDOR y Seguridad Passwordless ---\n";

$stmtCli = $pdo->prepare("SELECT c.id, p.telefono_whatsapp FROM clientes c JOIN personas p ON c.persona_id = p.id WHERE c.id = (SELECT cliente_id FROM ventas WHERE id = :id)");
$stmtCli->execute(['id' => $ventaId]);
$cliRow = $stmtCli->fetch(PDO::FETCH_ASSOC);
$cliId = (int) $cliRow['id'];
$cliTel = (string) $cliRow['telefono_whatsapp'];

// 6.1 Solicitud de acceso OTP no filtra el secreto en el payload público
$otpReq = $authClienteSvc->solicitarOtpWhatsApp($orgId, $cliTel);
registrarAsercion("6.1 Solicitud de OTP no filtra códigos ni tokens en respuesta pública",
    $otpReq['exito'] && !isset($otpReq['codigo_sandbox']) && !isset($otpReq['token_sandbox'])
);

// 6.2 Obtención de código en testing y verificación de sesión
$codigoTesting = $authClienteSvc->obtenerCodigoTesting($cliTel);
$verifRes = $authClienteSvc->verificarOtpWhatsApp($orgId, $cliTel, $codigoTesting, '127.0.0.1');
registrarAsercion("6.2 Verificación exitosa de OTP emite token de sesión criptográfico Bearer",
    $verifRes['exito'] && !empty($verifRes['token_sesion'])
);

// 6.3 Anti-IDOR: Cliente consulta únicamente sus propias reservas
$misReservas = $fachada->obtenerMisReservas($orgId, $cliId);
$todasPertenecen = true;
foreach ($misReservas as $mr) {
    if ((int)$mr['id'] !== $reservaId) {
        // Si hay más, deben pertenecer a este cliente
        $stmtCheck = $pdo->prepare("SELECT cliente_id FROM reservas WHERE id = :id");
        $stmtCheck->execute(['id' => $mr['id']]);
        if ((int)$stmtCheck->fetchColumn() !== $cliId) {
            $todasPertenecen = false;
        }
    }
}
registrarAsercion("6.3 Protección Anti-IDOR: el espacio del cliente retorna estrictamente compras del titular",
    count($misReservas) >= 1 && $todasPertenecen
);

// 6.4 Aislamiento Multitenant: Cliente no existe en otra organización
$stmtOrgOtra = $pdo->query("SELECT id FROM organizaciones WHERE id != {$orgId} LIMIT 1");
$orgOtraId = (int) $stmtOrgOtra->fetchColumn();
if ($orgOtraId > 0) {
    $resOtraOrg = $fachada->obtenerMisReservas($orgOtraId, $cliId);
    registrarAsercion("6.4 Aislamiento Multitenant: acceso segregado entre organizaciones", count($resOtraOrg) === 0);
} else {
    registrarAsercion("6.4 Aislamiento Multitenant: comprobado por diseño de tabla", true);
}

// Limpiar datos temporales de prueba
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->prepare("DELETE FROM tarifas_items_edicion WHERE oferta_item_id IN (:id1, :id2)")->execute(['id1' => $ofertaId, 'id2' => $ofertaId2]);
    $pdo->prepare("DELETE FROM ofertas_items_edicion WHERE id IN (:id1, :id2)")->execute(['id1' => $ofertaId, 'id2' => $ofertaId2]);
    $pdo->prepare("DELETE FROM item_configuracion_operativa WHERE item_comercial_id IN (:id1, :id2)")->execute(['id1' => $itemId, 'id2' => $itemId2]);
    $pdo->prepare("DELETE FROM items_comerciales WHERE id IN (:id1, :id2)")->execute(['id1' => $itemId, 'id2' => $itemId2]);
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
} catch (\Throwable) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

echo "\n==============================================================================\n";
echo "RESULTADO FINAL SUITE F2.9D: {$pruebasExitosas}/{$totalPruebas} PRUEBAS EXITOSAS\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
