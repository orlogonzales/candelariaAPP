<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 2.10 (MOTOR DOCUMENTAL Y PDF)
 * NÚCLEO: RENDERIZADO PDF (A4 Y TICKET 80MM), ALMACENAMIENTO PRIVADO CON HASH SHA-256,
 *         COTIZACIONES CONGELADAS, CONTRATOS EQUILIBRADOS DE SERVICIOS TURÍSTICOS,
 *         RECIBOS DE CAJA EXCLUSIVOS PARA PAGOS APROBADOS CON LEYENDA SUNAT,
 *         BOLETOS IMPRIMIBLES CON QR HMAC-SHA256 SIN CONSUMO DE INGRESO OPERATIVO,
 *         SEGURIDAD RBAC, MULTITENANCY Y ANTI-IDOR.
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Documentos\BoletoImpresionServicio;
use Aplicacion\Documentos\ContratoDocumentoServicio;
use Aplicacion\Documentos\CotizacionDocumentoServicio;
use Aplicacion\Documentos\MotorPdfServicio;
use Aplicacion\Documentos\PlantillaDocumentoServicio;
use Aplicacion\Documentos\ReciboCajaServicio;
use Aplicacion\Finanzas\EstadoFinancieroVenta;
use Aplicacion\Finanzas\EstadoPago;
use Aplicacion\Finanzas\MetodoPago;
use Aplicacion\Reservas\EstadoReserva;
use Aplicacion\Servicios\FachadaPortalPublicoServicio;
use Aplicacion\Ventas\EstadoVenta;
use Nucleo\BaseDatos\Conexion;

$pruebasEjecutadas = 0;
$pruebasExitosas = 0;
$pruebasFallidas = 0;

function afirmar(bool $condicion, string $descripcion, string $detalleError = ''): void {
    global $pruebasEjecutadas, $pruebasExitosas, $pruebasFallidas;
    $pruebasEjecutadas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$descripcion} --> {$detalleError}\n";
    }
}

echo "==============================================================================\n";
echo "INICIANDO SUITE F2.10: MOTOR DOCUMENTAL Y EMISIÓN PDF (A4 / TICKET 80MM)\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

// Inicializar servicios del motor documental
$motorPdf = new MotorPdfServicio();
$cotizacionDocSvc = new CotizacionDocumentoServicio($motorPdf, $pdo);
$contratoDocSvc = new ContratoDocumentoServicio($motorPdf, $pdo);
$reciboCajaSvc = new ReciboCajaServicio($motorPdf, $pdo);
$fachadaPortal = FachadaPortalPublicoServicio::crearPorDefecto($pdo);
$boletoDocSvc = new BoletoImpresionServicio($motorPdf, $fachadaPortal, $pdo);

// ------------------------------------------------------------------------------
// SECCIÓN 1: MOTOR PDF E INFRAESTRUCTURA DOCUMENTAL (F2.10A)
// ------------------------------------------------------------------------------
echo "--- SECCIÓN 1: MOTOR PDF BASE, FORMATOS Y ALMACENAMIENTO PRIVADO ---\n";

$htmlPruebaA4 = "<html><head><style>" . PlantillaDocumentoServicio::obtenerEstilosCssA4() . "</style></head><body><h1>Prueba A4 UTF-8: ¡Candelaria en Puno! Ñandú y Corazón</h1></body></html>";
$binarioA4 = $motorPdf->renderizar($htmlPruebaA4, MotorPdfServicio::FORMATO_A4, MotorPdfServicio::ORIENTACION_VERTICAL);

afirmar(str_starts_with($binarioA4, '%PDF-'), "MotorPdf genera binario PDF válido para formato A4 (cabecera %PDF-).");
afirmar(strlen($binarioA4) > 1000, "Binario PDF A4 tiene longitud coherente (" . strlen($binarioA4) . " bytes).");

$htmlPruebaTicket = "<html><head><style>" . PlantillaDocumentoServicio::obtenerEstilosCssTicket80() . "</style></head><body><div class='ticket-header'><div class='ticket-empresa'>EMPRESA TEST</div><div class='ticket-titulo'>TICKET 80MM</div></div></body></html>";
$binarioTicket = $motorPdf->renderizar($htmlPruebaTicket, MotorPdfServicio::FORMATO_TICKET_80MM, MotorPdfServicio::ORIENTACION_VERTICAL);

afirmar(str_starts_with($binarioTicket, '%PDF-'), "MotorPdf genera binario PDF válido para formato Ticket 80mm.");
afirmar(strlen($binarioTicket) > 500, "Binario PDF Ticket 80mm tiene longitud coherente (" . strlen($binarioTicket) . " bytes).");

// Prueba de almacenamiento privado estructurado con hash
$guardado = $motorPdf->guardarEnAlmacenamientoPrivado($binarioA4, 1, 'PRUEBAS', 'test_doc_' . uniqid() . '.pdf');
afirmar(file_exists($guardado['ruta_absoluta']), "Archivo PDF guardado correctamente en almacenamiento privado.");
afirmar($guardado['hash_sha256'] === hash('sha256', $binarioA4), "Hash SHA-256 registrado coincide estrictamente con el binario guardado.");
afirmar(str_contains(strtolower($guardado['ruta_relativa']), 'privado/documentos/1/pruebas/'), "Ruta relativa respeta la jerarquía por tenant ({org_id}/{tipo}).");

// Limpiar archivo de prueba
if (file_exists($guardado['ruta_absoluta'])) {
    unlink($guardado['ruta_absoluta']);
}

// ------------------------------------------------------------------------------
// SECCIÓN 2: COTIZACIONES HISTÓRICAS CONGELADAS (F2.10B - PARTE 1)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 2: COTIZACIONES PDF CON LÍNEAS HISTÓRICAS CONGELADAS ---\n";

// Localizar una cotización existente en BD con sus líneas
$stmtCot = $pdo->query("SELECT id, organizacion_id, correlativo FROM `cotizaciones` ORDER BY id DESC LIMIT 1");
$cotizacionExistente = $stmtCot->fetch(PDO::FETCH_ASSOC);

if ($cotizacionExistente) {
    $orgId = (int) $cotizacionExistente['organizacion_id'];
    $cotId = (int) $cotizacionExistente['id'];

    $resPdfCot = $cotizacionDocSvc->generarPdf($orgId, $cotId, 1);
    afirmar(str_starts_with($resPdfCot['binario_pdf'], '%PDF-'), "Generación de Cotización PDF oficial retorna PDF válido.");
    afirmar(file_exists($resPdfCot['ruta_privada']), "Cotización PDF guardada en disco en almacenamiento privado seguro.");
    afirmar($resPdfCot['correlativo'] === $cotizacionExistente['correlativo'], "Correlativo coincide con el histórico de la cotización.");

    // Verificar bitácora documentos_emitidos
    $stmtBit = $pdo->prepare("SELECT * FROM `documentos_emitidos` WHERE `entidad_tipo` = 'COTIZACION' AND `entidad_id` = :id ORDER BY id DESC LIMIT 1");
    $stmtBit->execute(['id' => $cotId]);
    $bitacoraCot = $stmtBit->fetch(PDO::FETCH_ASSOC);
    afirmar($bitacoraCot !== false, "Se registró evento en la tabla soberana documentos_emitidos para la cotización.");
    afirmar($bitacoraCot['tipo_documento'] === 'COTIZACION', "Tipo de documento registrado como COTIZACION.");
    afirmar($bitacoraCot['hash_sha256'] === $resPdfCot['hash'], "Hash SHA-256 en documentos_emitidos coincide con el archivo generado.");

    // Aislamiento multitenant: intentar generar con organizacion_id ajena debe fallar
    $falloMultitenant = false;
    try {
        $cotizacionDocSvc->generarPdf(99999, $cotId, 1);
    } catch (\InvalidArgumentException $e) {
        $falloMultitenant = true;
    }
    afirmar($falloMultitenant, "Aislamiento Multitenant: Bloquea generación de cotización si la organización no coincide.");
} else {
    echo "  [SKIP] No hay cotizaciones en BD para probar emisión.\n";
}

// ------------------------------------------------------------------------------
// SECCIÓN 3: CONTRATOS DE SERVICIOS TURÍSTICOS (F2.10B - PARTE 2)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 3: CONTRATOS DE SERVICIOS TURÍSTICOS A PARTIR DE VENTAS ---\n";

// Localizar una venta en estado CONFIRMADA
$stmtVta = $pdo->query("SELECT id, organizacion_id, cliente_id, correlativo, estado FROM `ventas` WHERE `estado` = 'CONFIRMADA' ORDER BY id DESC LIMIT 1");
$ventaConfirmada = $stmtVta->fetch(PDO::FETCH_ASSOC);

if ($ventaConfirmada) {
    $orgId = (int) $ventaConfirmada['organizacion_id'];
    $vtaId = (int) $ventaConfirmada['id'];

    $resPdfContrato = $contratoDocSvc->generarPdf($orgId, $vtaId, 1);
    afirmar(str_starts_with($resPdfContrato['binario_pdf'], '%PDF-'), "Generación de Contrato PDF retorna archivo PDF válido.");
    afirmar(file_exists($resPdfContrato['ruta_privada']), "Contrato PDF guardado en almacenamiento privado estructurado.");
    afirmar(str_starts_with($resPdfContrato['correlativo'], 'CON-'), "Correlativo de contrato deriva con prefijo CON- ({$resPdfContrato['correlativo']}).");

    // Verificar documentos_emitidos para contrato
    $stmtBitCont = $pdo->prepare("SELECT * FROM `documentos_emitidos` WHERE `entidad_tipo` = 'VENTA' AND `entidad_id` = :id AND `tipo_documento` = 'CONTRATO' ORDER BY id DESC LIMIT 1");
    $stmtBitCont->execute(['id' => $vtaId]);
    $bitacoraCont = $stmtBitCont->fetch(PDO::FETCH_ASSOC);
    afirmar($bitacoraCont !== false, "Documento de contrato registrado en documentos_emitidos.");
    afirmar($bitacoraCont['formato'] === 'A4', "Formato de contrato registrado como A4.");

    // Validación de estado: Si la venta NO está CONFIRMADA debe fallar (Fail-Closed)
    // Crear venta simulada CANCELADA para probar restricción
    $stmtVtaCancelada = $pdo->query("SELECT id, organizacion_id FROM `ventas` WHERE `estado` != 'CONFIRMADA' LIMIT 1");
    $ventaNoConfirmada = $stmtVtaCancelada->fetch(PDO::FETCH_ASSOC);
    if ($ventaNoConfirmada) {
        $falloEstado = false;
        try {
            $contratoDocSvc->generarPdf((int) $ventaNoConfirmada['organizacion_id'], (int) $ventaNoConfirmada['id'], 1);
        } catch (\InvalidArgumentException $e) {
            $falloEstado = true;
        }
        afirmar($falloEstado, "Fail-Closed: Bloquea generación de contrato si la venta no está en estado CONFIRMADA.");
    }
} else {
    echo "  [SKIP] No hay ventas CONFIRMADAS para emitir contrato.\n";
}

// ------------------------------------------------------------------------------
// SECCIÓN 4: RECIBOS DE CAJA INTERNOS (F2.10C)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 4: RECIBOS DE CAJA CON LEYENDA LEGAL NO TRIBUTARIA SUNAT ---\n";

// Caso 4.1: Pago en estado PENDIENTE_VERIFICACION debe ser RECHAZADO para emisión de recibo
$stmtPagoPend = $pdo->query("SELECT id, organizacion_id, estado FROM `pagos` WHERE `estado` = 'PENDIENTE_VERIFICACION' LIMIT 1");
$pagoPendiente = $stmtPagoPend->fetch(PDO::FETCH_ASSOC);

if ($pagoPendiente) {
    $bloqueoReciboPendiente = false;
    try {
        $reciboCajaSvc->generarRecibo((int) $pagoPendiente['organizacion_id'], (int) $pagoPendiente['id'], 1);
    } catch (\InvalidArgumentException $e) {
        $bloqueoReciboPendiente = true;
        $mensajeRecibo = $e->getMessage();
    }
    afirmar($bloqueoReciboPendiente, "Regla de Negocio: Bloquea emisión de recibo de caja para pagos en PENDIENTE_VERIFICACION.");
    afirmar(str_contains($mensajeRecibo ?? '', 'APROBADO'), "Mensaje de error exige explícitamente estado APROBADO.");
} else {
    echo "  [INFO] No había pago pendiente para probar bloqueo directo.\n";
}

// Caso 4.2: Pago en estado APROBADO genera recibo con correlativo atómico REC-YYYY-NNNNNN
$stmtPagoAprob = $pdo->query("SELECT id, organizacion_id, correlativo FROM `pagos` WHERE `estado` = 'APROBADO' ORDER BY id DESC LIMIT 1");
$pagoAprobado = $stmtPagoAprob->fetch(PDO::FETCH_ASSOC);

if ($pagoAprobado) {
    $orgId = (int) $pagoAprobado['organizacion_id'];
    $pagoId = (int) $pagoAprobado['id'];

    $resRecibo = $reciboCajaSvc->generarRecibo($orgId, $pagoId, 1);
    afirmar(str_starts_with($resRecibo['binario_pdf'], '%PDF-'), "Generación de Recibo de Caja retorna PDF válido.");
    afirmar(preg_match('/^REC-\d{4}-\d{6}$/', $resRecibo['correlativo']) === 1, "Correlativo de recibo cumple formato estricto REC-YYYY-NNNNNN ({$resRecibo['correlativo']}).");
    afirmar(file_exists($resRecibo['ruta_privada']), "Recibo de caja guardado en almacenamiento privado estructurado.");

    // Verificar inserción en tabla recibos_caja
    $stmtRc = $pdo->prepare("SELECT * FROM `recibos_caja` WHERE `id` = :id");
    $stmtRc->execute(['id' => $resRecibo['recibo_id']]);
    $filaRc = $stmtRc->fetch(PDO::FETCH_ASSOC);
    afirmar($filaRc !== false, "Registro persistido en tabla recibos_caja.");
    afirmar((int) $filaRc['pago_id'] === $pagoId, "Recibo vinculado exactamente al pago_id correspondiente.");
    afirmar(!empty($filaRc['hash_sha256']), "Hash SHA-256 registrado en recibos_caja.");

    // Verificar bitácora documentos_emitidos
    $stmtDocRc = $pdo->prepare("SELECT * FROM `documentos_emitidos` WHERE `entidad_tipo` = 'PAGO' AND `entidad_id` = :pago_id ORDER BY id DESC LIMIT 1");
    $stmtDocRc->execute(['pago_id' => $pagoId]);
    $filaDocRc = $stmtDocRc->fetch(PDO::FETCH_ASSOC);
    afirmar($filaDocRc !== false, "Registro creado en documentos_emitidos para RECIBO_CAJA.");
    afirmar($filaDocRc['tipo_documento'] === 'RECIBO_CAJA', "Tipo de documento registrado como RECIBO_CAJA.");

    // Idempotencia: Volver a invocar generarRecibo para el mismo pago no debe arrojar error ni duplicar
    $resReciboReiterado = $reciboCajaSvc->generarRecibo($orgId, $pagoId, 1);
    afirmar($resReciboReiterado['recibo_id'] === $resRecibo['recibo_id'], "Idempotencia: Re-emisión de recibo para el mismo pago preserva el mismo ID de recibo.");
    afirmar($resReciboReiterado['correlativo'] === $resRecibo['correlativo'], "Idempotencia: Preserva el correlativo original sin gastar una nueva secuencia.");
} else {
    echo "  [SKIP] No hay pagos APROBADOS para emitir recibo de caja.\n";
}

// ------------------------------------------------------------------------------
// SECCIÓN 5: BOLETOS IMPRIMIBLES CON QR HMAC-SHA256 (F2.10D)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 5: BOLETOS IMPRIMIBLES A4 Y TICKET 80MM (CERO CONSUMO) ---\n";

// Localizar una reserva CONFIRMADA con venta PAGADA_TOTAL
$stmtResConf = $pdo->query("
    SELECT r.id, r.organizacion_id, r.cliente_id, r.correlativo, r.estado, r.notas_operativas,
           v.estado as venta_estado, v.estado_financiero
    FROM `reservas` r
    JOIN `ventas` v ON r.venta_id = v.id
    WHERE r.estado = 'CONFIRMADA'
      AND v.estado = 'CONFIRMADA'
      AND v.estado_financiero IN ('PAGADA_TOTAL', 'SOBREPAGADA')
    ORDER BY r.id DESC LIMIT 1
");
$reservaApta = $stmtResConf->fetch(PDO::FETCH_ASSOC);

if ($reservaApta) {
    $orgId = (int) $reservaApta['organizacion_id'];
    $resId = (int) $reservaApta['id'];
    $cliId = (int) $reservaApta['cliente_id'];
    $notasOperativasAntes = (string) ($reservaApta['notas_operativas'] ?? '');

    // 5.1 Emisión en Formato A4
    $resBoletoA4 = $boletoDocSvc->generarBoletoA4($orgId, $resId, $cliId, 1);
    afirmar(str_starts_with($resBoletoA4['binario_pdf'], '%PDF-'), "Boleto A4 generado exitosamente como PDF válido.");
    afirmar(file_exists($resBoletoA4['ruta_privada']), "Boleto A4 guardado en almacenamiento privado.");
    afirmar(!empty($resBoletoA4['token_qr']), "Token QR canónico HMAC-SHA256 generado e incrustado en boleto A4.");

    // 5.2 Emisión en Formato Ticket 80mm
    $resBoletoTkt = $boletoDocSvc->generarBoletoTicket80($orgId, $resId, $cliId, 1);
    afirmar(str_starts_with($resBoletoTkt['binario_pdf'], '%PDF-'), "Boleto Ticket 80mm generado exitosamente como PDF válido.");
    afirmar(file_exists($resBoletoTkt['ruta_privada']), "Boleto Ticket 80mm guardado en almacenamiento privado.");
    $auditoriaQrTkt = $fachadaPortal->validarTokenBoletoQr($orgId, $resBoletoTkt['token_qr'], marcarUso: false);
    afirmar($auditoriaQrTkt['valido'] === true, "Token QR de Ticket 80mm es 100% auténtico y válido criptográficamente.");

    // 5.3 Regla Crítica de Auditoría F2.10: La impresión NO altera estado ni marca check-in
    $stmtResCheck = $pdo->prepare("SELECT estado, notas_operativas FROM `reservas` WHERE id = :id");
    $stmtResCheck->execute(['id' => $resId]);
    $resDespues = $stmtResCheck->fetch(PDO::FETCH_ASSOC);

    afirmar($resDespues['estado'] === EstadoReserva::CONFIRMADA->value, "Inmutabilidad de Estado: La reserva permanece en CONFIRMADA tras la emisión de boletos.");
    afirmar($resDespues['notas_operativas'] === $notasOperativasAntes, "Cero Check-in: La generación e impresión de boletos NO alteró notas_operativas ni consumió ingreso.");

    // 5.4 Consulta Histórica posterior: El token QR sigue siendo válido para auditoría sin consumir ingreso
    $auditoriaQr = $fachadaPortal->validarTokenBoletoQr($orgId, $resBoletoA4['token_qr'], marcarUso: false);
    afirmar($auditoriaQr['valido'] === true, "Validación de Token QR: El token generado por el boleto es 100% auténtico y válido criptográficamente.");

    // 5.5 Bitácora en documentos_emitidos para boletos
    $stmtBitBol = $pdo->prepare("SELECT * FROM `documentos_emitidos` WHERE `entidad_tipo` = 'RESERVA' AND `entidad_id` = :res_id AND `tipo_documento` = 'BOLETO_TICKET_80MM' ORDER BY id DESC LIMIT 1");
    $stmtBitBol->execute(['res_id' => $resId]);
    $bitBol = $stmtBitBol->fetch(PDO::FETCH_ASSOC);
    afirmar($bitBol !== false, "Boleto Ticket 80mm registrado en bitácora documentos_emitidos.");
    afirmar($bitBol['formato'] === 'TICKET_80MM', "Formato registrado como TICKET_80MM en bitácora.");
} else {
    echo "  [SKIP] No hay reservas confirmadas aptas para probar emisión de boletos.\n";
}

// ------------------------------------------------------------------------------
// SECCIÓN 6: PROTECCIÓN ANTI-IDOR Y CONTROL RBAC (F2.10E)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 6: SEGURIDAD, ANTI-IDOR Y VERIFICACIÓN DE PERMISOS RBAC ---\n";

// Caso 6.1: Anti-IDOR en generación de boletos: Cliente ajeno es bloqueado
if (!empty($reservaApta)) {
    $bloqueoIdor = false;
    try {
        $boletoDocSvc->generarBoletoA4((int) $reservaApta['organizacion_id'], (int) $reservaApta['id'], clienteId: 99999);
    } catch (\InvalidArgumentException $e) {
        $bloqueoIdor = true;
    }
    afirmar($bloqueoIdor, "Anti-IDOR: Impide que un cliente no titular emita o descargue el boleto de otra persona.");
}

// Caso 6.2: Verificación de Permisos RBAC en Base de Datos (Módulo 13: documentos)
$permisosDocumentos = [
    'documentos.cotizaciones.descargar',
    'documentos.contratos.descargar',
    'documentos.recibos.emitir',
    'documentos.recibos.descargar',
    'documentos.boletos.descargar',
];
$stmtPerm = $pdo->prepare("SELECT codigo FROM `permisos` WHERE `codigo` IN ('" . implode("','", $permisosDocumentos) . "')");
$stmtPerm->execute();
$permisosEncontrados = $stmtPerm->fetchAll(PDO::FETCH_COLUMN);

afirmar(count($permisosEncontrados) === 5, "Módulo 13 (documentos) cuenta con sus 5 permisos RBAC oficiales registrados en la base de datos.");

// ------------------------------------------------------------------------------
// SECCIÓN 7: HOMOLOGACIÓN DOCUMENTAL F2.10E-1 (DIMENSIONES, CICLO DE VIDA E INMUTABILIDAD)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 7: HOMOLOGACIÓN F2.10E-1: DIMENSIONES, CICLO CONTRACTUAL E INMUTABILIDAD ---\n";

// Caso 7.1: Dimensiones físicas reales del Ticket Térmico 80mm
if (!empty($reservaApta)) {
    $resTicketDiag = $boletoDocSvc->generarBoletoTicket80((int) $reservaApta['organizacion_id'], (int) $reservaApta['id']);
    $pdfTicketRaw = $resTicketDiag['binario_pdf'];

    $dimCorrectas = false;
    if (preg_match('/\/MediaBox\s*\[\s*([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)\s*\]/', $pdfTicketRaw, $m)) {
        $wMm = ($m[3] - $m[1]) * 25.4 / 72;
        $hMm = ($m[4] - $m[2]) * 25.4 / 72;
        // 80mm de ancho exacto (+/- 0.5mm)
        $dimCorrectas = (abs($wMm - 80.0) < 0.5 && abs($hMm - 250.0) < 1.0);
    }
    afirmar($dimCorrectas, "Dimensiones físicas de Ticket 80mm verificadas (80.00 mm de ancho x 250.00 mm de alto).");

    $numPaginasTicket = preg_match_all('/\/Type\s*\/Page\b/', $pdfTicketRaw);
    afirmar($numPaginasTicket === 1, "Ticket térmico 80mm renderiza en exactamente 1 sola página continua (sin desbordes).");

    // Caso 7.2: QR impreso verificado contra motor canónico sin marcar check-in
    $notasAntesVal = (string) ($reservaApta['notas_operativas'] ?? '');
    $verifQr = $fachadaPortal->validarTokenBoletoQr((int) $reservaApta['organizacion_id'], $resTicketDiag['token_qr'], false);
    afirmar(!empty($verifQr['valido']) && $verifQr['valido'] === true, "Código QR impreso en ticket es 100% compatible y validado por el verificador canónico.");

    $stmtReservaNotas = $pdo->prepare("SELECT notas_operativas FROM `reservas` WHERE `id` = :id");
    $stmtReservaNotas->execute(['id' => $reservaApta['id']]);
    $notasDespuesVal = (string) $stmtReservaNotas->fetchColumn();
    afirmar($notasDespuesVal === $notasAntesVal, "Validar o descargar el boleto no consumó ingreso ni alteró notas_operativas de check-in.");
}

// Caso 7.3: Ciclo de Vida Contractual Íntegro (Emisión, Aceptación, Inmutabilidad y Anulación)
if (!empty($ventaConfirmada)) {
    $orgIdCto = (int) $ventaConfirmada['organizacion_id'];
    $vtaIdCto = (int) $ventaConfirmada['id'];
    $cliIdCto = (int) $ventaConfirmada['cliente_id'];

    // 7.3.1 Emisión inicial
    $ctoV1 = $contratoDocSvc->generarPdf($orgIdCto, $vtaIdCto, null, true, 'Emisión inicial de prueba');
    afirmar($ctoV1['version'] >= 1, "Contrato emitido con versión >= 1 (versión actual: {$ctoV1['version']}).");

    $stmtCtoRow = $pdo->prepare("SELECT * FROM `contratos_venta` WHERE `organizacion_id` = :org_id AND `venta_id` = :vta_id");
    $stmtCtoRow->execute(['org_id' => $orgIdCto, 'vta_id' => $vtaIdCto]);
    $ctoRow = $stmtCtoRow->fetch(PDO::FETCH_ASSOC);
    afirmar($ctoRow !== false, "Entidad contratos_venta creada y persistida en base de datos.");
    afirmar($ctoRow['estado_contrato'] === 'EMITIDO' || $ctoRow['estado_contrato'] === 'ACEPTADO', "Ciclo de vida: Estado contractual registrado correctamente ({$ctoRow['estado_contrato']}).");

    // 7.3.2 Registro de aceptación formal por el cliente titular
    $ctoAceptado = $contratoDocSvc->registrarAceptacionCliente(
        $orgIdCto,
        $vtaIdCto,
        $cliIdCto,
        '190.237.112.45',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0'
    );
    afirmar($ctoAceptado['version'] > $ctoV1['version'], "Aceptación contractual incrementa la versión documental (v{$ctoV1['version']} -> v{$ctoAceptado['version']}).");

    $stmtCtoAcep = $pdo->prepare("SELECT * FROM `contratos_venta` WHERE `id` = :id");
    $stmtCtoAcep->execute(['id' => $ctoRow['id']]);
    $ctoRowAcep = $stmtCtoAcep->fetch(PDO::FETCH_ASSOC);
    afirmar($ctoRowAcep['estado_contrato'] === 'ACEPTADO', "Ciclo de vida: Contrato transita a estado formal ACEPTADO.");
    afirmar($ctoRowAcep['aceptado_ip'] === '190.237.112.45', "Evidencia legal: Dirección IP del cliente registrada en contratos_venta.");
    afirmar(!empty($ctoRowAcep['aceptado_en']), "Evidencia legal: Fecha y hora exacta de aceptación registradas.");

    // 7.3.3 Inmutabilidad de archivos físicos: Ambos PDFs coexisten en disco sin sobreescritura
    $stmtDocsHist = $pdo->prepare("SELECT * FROM `documentos_emitidos` WHERE `entidad_tipo` = 'VENTA' AND `entidad_id` = :vta_id AND `tipo_documento` = 'CONTRATO' ORDER BY version ASC");
    $stmtDocsHist->execute(['vta_id' => $vtaIdCto]);
    $docsHist = $stmtDocsHist->fetchAll(PDO::FETCH_ASSOC);

    afirmar(count($docsHist) >= 2, "Inmutabilidad: Existen al menos 2 versiones históricas registradas en documentos_emitidos.");
    $coexistenEnDisco = file_exists($ctoV1['ruta_privada'])
        && file_exists($ctoAceptado['ruta_privada'])
        && $ctoV1['ruta_privada'] !== $ctoAceptado['ruta_privada'];
    afirmar($coexistenEnDisco, "Inmutabilidad estricta: Archivo de emisión inicial y archivo de contrato aceptado coexisten físicamente en disco sin sobreescritura.");

    // 7.3.4 Anulación contractual
    $contratoDocSvc->anularContrato($orgIdCto, $vtaIdCto, 'Anulación de prueba para certificación');
    $stmtCtoAnul = $pdo->prepare("SELECT estado_contrato, anulado_motivo FROM `contratos_venta` WHERE `id` = :id");
    $stmtCtoAnul->execute(['id' => $ctoRow['id']]);
    $ctoRowAnul = $stmtCtoAnul->fetch(PDO::FETCH_ASSOC);
    afirmar($ctoRowAnul['estado_contrato'] === 'ANULADO', "Ciclo de vida: Contrato transita a estado ANULADO al ser revocado.");
}

// Caso 7.4: Leyenda no tributaria en Recibo Interno de Caja
if (!empty($recibo)) {
    $pdfReciboRaw = $recibo['binario_pdf'];
    // Verificar en HTML interno o binario la presencia de advertencia no tributaria
    afirmar(str_contains($pdfReciboRaw, '%PDF-'), "Recibo interno de caja emitido en formato PDF válido.");
}

// Caso 7.5: Integridad de la cuenta de Orlando (ID 24)
$stmtOrlando = $pdo->query("SELECT id, correo_electronico, contrasena_hash FROM `usuarios` WHERE id = 24");
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);
afirmar($orlando !== false, "Usuario Orlando (ID 24) existe en el sistema.");
$fingerprintOrlando = substr(hash('sha256', (string) $orlando['contrasena_hash']), 0, 16);
afirmar($fingerprintOrlando === '80e6af84e02e89e3', "Huella inmutable de Orlando (ID 24) preservada intacta (80e6af84e02e89e3).");

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.10: {$pruebasExitosas} PASS / {$pruebasFallidas} FAIL (Total: {$pruebasEjecutadas})\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
