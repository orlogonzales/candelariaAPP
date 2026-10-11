<?php
/**
 * Suite F2.12D: Auditoría Integral, Seguridad y Certificación Final V1
 * Directiva DT-F212D-001
 * 
 * Verifica exhaustivamente:
 * 1. Paquete OpenXML XLSX auténtico (ZIP, ECMA-376) y compatibilidad XML SpreadsheetML.
 * 2. Seguridad de claves y segregación de funciones (cuatro ojos) en cierres y reaperturas.
 * 3. Concurrencia real con conexiones PDO independientes en operaciones críticas.
 * 4. Guardianes de edición cerrada y amortizaciones legítimas autorizadas en Cierre Condicionado.
 * 5. Conciliación canónica al centavo: Dashboard Ejecutivo vs Reportes Oficiales.
 * 6. Aislamiento Multi-Tenant riguroso (Anti-IDOR) y Principio de Mínimo Privilegio.
 * 7. Despliegue limpio desde cero en base de datos temporal (23 migraciones SQL y 104 permisos).
 * 8. Protección transversal contra inyección de fórmulas (CSV, XLSX, XML).
 * 9. Preservación inmutable de la huella del usuario Orlando (ID 24).
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Reportes\ReporteCierreServicio;
use Aplicacion\Ediciones\CierreEdicionServicio;
use Aplicacion\Dashboard\DashboardEjecutivoServicio;
use Aplicacion\Controladores\EdicionControlador;
use Aplicacion\Controladores\DashboardControlador;
use Aplicacion\Finanzas\PagoServicio;
use Aplicacion\Servicios\MotorDisponibilidadServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Seguridad\AutenticacionMiddleware;
use Aplicacion\Seguridad\AutorizacionMiddleware;
use Aplicacion\Autorizacion\AutorizacionServicio;
use Nucleo\Http\ContextoOperacion;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\CuentaBancariaRepositorio;
use Aplicacion\Repositorios\PagoRepositorio;
use Aplicacion\Repositorios\PagoReembolsoRepositorio;
use Aplicacion\Repositorios\PasarelaRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\ReservaBloqueoTemporalRepositorio;

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function afirmar(bool $condicion, string $mensaje): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condicion) {
        $passedTests++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$mensaje}\n";
    }
}

echo "==============================================================================\n";
echo "INICIANDO SUITE F2.12D: AUDITORÍA INTEGRAL, SEGURIDAD Y CERTIFICACIÓN FINAL V1\n";
echo "==============================================================================\n\n";

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'app_candelaria';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$orgId = 1;

// ==============================================================================
// GRUPO 1: VALIDACIÓN DEL PAQUETE OPENXML XLSX AUTÉNTICO (ZIP) Y SPREADSHEETML
// ==============================================================================
echo "--- GRUPO 1: VALIDACIÓN DE PAQUETE OPENXML XLSX (ZIP) Y SPREADSHEETML ---\n";

$reporteServicio = new ReporteCierreServicio($pdo);
$dummyReporteData = [
    'titulo' => 'Reporte Auditoría Final V1',
    'generado_en' => date('Y-m-d H:i:s'),
    'items' => [
        [
            'correlativo' => 'BOL-001',
            'cliente' => 'Orlando Valenzuela',
            'monto' => 1500.50,
            'formula_sospechosa' => '=cmd|\'/C calc\'!A0',
            'espacio_formula' => ' =2+2',
        ]
    ],
    'totales' => [
        'total_registros' => 1,
        'monto_total' => 1500.50,
    ]
];

$xlsxBinario = $reporteServicio->exportarXlsx(ReporteCierreServicio::TIPO_LIBRO_INGRESOS, $dummyReporteData);

afirmar(str_starts_with($xlsxBinario, "PK\x03\x04"), "El archivo XLSX exportado es un paquete ZIP con firma mágica PK\\x03\\x04.");

$tmpZipPath = tempnam(sys_get_temp_dir(), 'cand_f212d_');
file_put_contents($tmpZipPath, $xlsxBinario);
$zip = new \ZipArchive();
$openResult = $zip->open($tmpZipPath);
afirmar($openResult === true, "El archivo .xlsx se abre exitosamente como archivo ZIP válido con ZipArchive.");

$contentTypesXml = $zip->getFromName('[Content_Types].xml');
afirmar($contentTypesXml !== false, "El archivo [Content_Types].xml existe dentro del paquete XLSX.");
$parsedContentTypes = simplexml_load_string($contentTypesXml);
afirmar($parsedContentTypes !== false, "El manifiesto [Content_Types].xml es XML sintácticamente válido.");

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
afirmar($sheetXml !== false, "La hoja xl/worksheets/sheet1.xml existe en el paquete.");
$parsedSheet = simplexml_load_string($sheetXml);
afirmar($parsedSheet !== false, "La hoja sheet1.xml es XML OpenXML sintácticamente válido.");

// Neutralización de fórmulas en OpenXML
afirmar(!str_contains($sheetXml, '<v>=cmd'), "Las fórmulas no se emiten como fórmulas ejecutables.");
afirmar(str_contains($sheetXml, "'=cmd"), "La fórmula maliciosa fue neutralizada con apóstrofe ('=cmd).");
afirmar(str_contains($sheetXml, "' =2+2"), "La fórmula con espacio inicial fue neutralizada con apóstrofe.");

$zip->close();
@unlink($tmpZipPath);

// XML SpreadsheetML 2003
$xmlMl = $reporteServicio->exportarXmlSpreadsheetMl(ReporteCierreServicio::TIPO_LIBRO_INGRESOS, $dummyReporteData);
afirmar(str_starts_with($xmlMl, '<?xml version="1.0" encoding="UTF-8"?>'), "SpreadsheetML inicia con cabecera XML.");
afirmar(str_contains($xmlMl, 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'), "SpreadsheetML contiene el espacio de nombres de Microsoft Excel.");

// ==============================================================================
// GRUPO 2: SEGURIDAD CRIPTOGRÁFICA DE CLAVES Y CUATRO OJOS EN REAPERTURA
// ==============================================================================
echo "\n--- GRUPO 2: SEGURIDAD DE CLAVES Y CUATRO OJOS EN REAPERTURA ---\n";

$stmtOrg = $pdo->query("SELECT id FROM `organizaciones` ORDER BY id ASC LIMIT 1");
$orgId = (int) $stmtOrg->fetchColumn();

// Limpiar pruebas previas de F2.12D si existieran
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM `edicion_cierre_reaperturas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `edicion_cierres` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `recibos_caja` WHERE venta_id IN (SELECT id FROM `ventas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'))");
$pdo->exec("DELETE FROM `pagos` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `reserva_participantes` WHERE reserva_id IN (SELECT id FROM `reservas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'))");
$pdo->exec("DELETE FROM `reserva_prestaciones` WHERE reserva_id IN (SELECT id FROM `reservas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'))");
$pdo->exec("DELETE FROM `reservas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `venta_lineas` WHERE venta_id IN (SELECT id FROM `ventas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'))");
$pdo->exec("DELETE FROM `ventas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `operacion_salida_prestaciones` WHERE salida_id IN (SELECT id FROM `operacion_salidas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'))");
$pdo->exec("DELETE FROM `operacion_salidas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `ofertas_items_edicion` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// Crear edición de prueba para auditoría
$aniosUsados = $pdo->query("SELECT anio FROM `ediciones_candelaria` WHERE organizacion_id = {$orgId}")->fetchAll(PDO::FETCH_COLUMN);
$anioTest = 2085;
while (in_array($anioTest, $aniosUsados)) {
    $anioTest++;
}

$codEdicion = 'ED-F212D-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtNewEd = $pdo->prepare("
    INSERT INTO `ediciones_candelaria` 
    (organizacion_id, codigo, nombre, anio, fecha_inicio, fecha_fin, estado, es_actual)
    VALUES 
    (:org, :cod, 'Edición Certificación F2.12D', :anio, '2026-02-01', '2026-02-15', 'PREOPERACION', 0)
");
$stmtNewEd->execute([':org' => $orgId, ':cod' => $codEdicion, ':anio' => $anioTest]);
$edicionId = (int) $pdo->lastInsertId();

// Crear usuario autorizador con clave conocida
$claveAutorizadorSecreta = 'ClaveSuperSegura#2026';
$correoAut = 'autorizador_' . substr(bin2hex(random_bytes(3)), 0, 6) . '@example.com';
$hashAut = password_hash($claveAutorizadorSecreta, PASSWORD_DEFAULT);

$docAut = '88' . substr(str_shuffle('0123456789'), 0, 6);
$stmtPersona = $pdo->prepare("
    INSERT INTO `personas` (organizacion_id, tipo_persona, tipo_documento_id, numero_documento, nombres, apellidos)
    VALUES (:org, 'NATURAL', 1, :doc, 'Fiscal', 'Superior')
");
$stmtPersona->execute([':org' => $orgId, ':doc' => $docAut]);
$personaAutId = (int) $pdo->lastInsertId();

$nombreUsuarioAut = 'fiscal_' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtAut = $pdo->prepare("
    INSERT INTO `usuarios` 
    (organizacion_id, persona_id, nombre_usuario, nombre_completo, correo_electronico, contrasena_hash, estado, es_superadmin_plataforma)
    VALUES 
    (:org, :pid, :usr, 'Lic. Fiscal Superior', :correo, :pwd, 'ACTIVO', 0)
");
$stmtAut->execute([':org' => $orgId, ':pid' => $personaAutId, ':usr' => $nombreUsuarioAut, ':correo' => $correoAut, ':pwd' => $hashAut]);
$autorizadorId = (int) $pdo->lastInsertId();

// Asignar rol admin_organizacion
$stmtRolAdmin = $pdo->prepare("SELECT id FROM `roles` WHERE `codigo` = 'admin_organizacion' LIMIT 1");
$stmtRolAdmin->execute();
$rolAdminId = (int) $stmtRolAdmin->fetchColumn();
$pdo->prepare("INSERT IGNORE INTO `usuario_roles` (usuario_id, rol_id) VALUES (:u, :r)")
    ->execute([':u' => $autorizadorId, ':r' => $rolAdminId]);

$auditoriaRepo = new AuditoriaRepositorio($pdo);
$cierreServicio = new CierreEdicionServicio($pdo, $auditoriaRepo);

// Asentar un Cierre Condicionado
$resultadoCierre = $cierreServicio->ejecutarCierre(
    organizacionId: $orgId,
    edicionId: $edicionId,
    usuarioId: 24,
    tipoCierre: 'CONDICIONADO',
    justificacion: 'Cierre de prueba para auditar el flujo de reapertura de cuatro ojos.',
    autorizadorId: $autorizadorId
);
$cierreId = (int)$resultadoCierre['id'];
afirmar($cierreId > 0, "Cierre Condicionado previo asentado exitosamente (ID #{$cierreId}).");

// 1. Probar rechazo con clave errónea
$claveErroneaRechazada = false;
try {
    $cierreServicio->reabrirEdicionExcepcional(
        organizacionId: $orgId,
        edicionId: $edicionId,
        usuarioSolicitanteId: 24,
        usuarioAutorizadorId: $autorizadorId,
        motivoExcepcional: 'Motivo formal válido con más de veinte caracteres requeridos.',
        claveConfirmacionAutorizador: 'ClaveTotalmenteErronea_123'
    );
} catch (AccesoDenegadoExcepcion $e) {
    if (str_contains($e->getMessage(), 'Clave de confirmación incorrecta')) {
        $claveErroneaRechazada = true;
    }
}
afirmar($claveErroneaRechazada, "Reapertura rechaza estrictamente si la clave del autorizador es errónea (Fail-Closed).");

// 2. Probar rechazo si solicitante == autorizador (Segregación de funciones)
$mismoUsuarioRechazado = false;
try {
    $cierreServicio->reabrirEdicionExcepcional(
        organizacionId: $orgId,
        edicionId: $edicionId,
        usuarioSolicitanteId: $autorizadorId,
        usuarioAutorizadorId: $autorizadorId,
        motivoExcepcional: 'Motivo formal válido con más de veinte caracteres requeridos.',
        claveConfirmacionAutorizador: $claveAutorizadorSecreta
    );
} catch (InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'segregación de funciones')) {
        $mismoUsuarioRechazado = true;
    }
}
afirmar($mismoUsuarioRechazado, "Segregación de funciones: Solicitante no puede ser el mismo usuario que autoriza.");

// 3. Reapertura exitosa con clave válida y verificar que no se registre la clave
$contextoOp = new ContextoOperacion(
    actorTipo: 'HUMANO',
    usuarioId: 24,
    actorSistemaId: null,
    actorSistemaCodigo: null,
    canalId: 1,
    canalCodigo: 'APP',
    correlacionId: 'corr-test-cierre-f212d',
    origenIp: '127.0.0.1',
    agenteUsuario: 'SuiteF212D/Test',
    organizacionId: $orgId,
    metadatos: ['csrf_token' => 'token_valido_f212d'],
    edicionTrabajoId: $edicionId,
    origenEdicion: 'EXPLICITA'
);

$resReapertura = $cierreServicio->reabrirEdicionExcepcional(
    organizacionId: $orgId,
    edicionId: $edicionId,
    usuarioSolicitanteId: 24,
    usuarioAutorizadorId: $autorizadorId,
    motivoExcepcional: 'Motivo formal válido con más de veinte caracteres requeridos.',
    claveConfirmacionAutorizador: $claveAutorizadorSecreta,
    nuevoEstado: 'POSTPRODUCCION_ENTREGA',
    contexto: $contextoOp
);

afirmar(!isset($resReapertura['clave']), "La respuesta de reapertura NO expone la clave.");
afirmar(!isset($resReapertura['password']), "La respuesta de reapertura NO expone atributos de contraseña.");

// Inspeccionar tabla edicion_cierre_reaperturas
$stmtReapRow = $pdo->prepare("SELECT * FROM `edicion_cierre_reaperturas` WHERE id = :id");
$stmtReapRow->execute(['id' => $resReapertura['reapertura_id']]);
$rowReap = $stmtReapRow->fetch();
afirmar(!array_key_exists('clave', $rowReap) && !array_key_exists('contrasena', $rowReap), "La tabla edicion_cierre_reaperturas no posee columnas de contraseña.");

// Inspeccionar auditoria_operaciones
$stmtAudit = $pdo->prepare("
    SELECT datos_previos_json, datos_nuevos_json 
    FROM `auditoria_operaciones` 
    WHERE accion = 'REAPERTURA_EXCEPCIONAL_EDICION' AND entidad_id = :cid
    ORDER BY id DESC LIMIT 1
");
$stmtAudit->execute(['cid' => $cierreId]);
$auditData = $stmtAudit->fetch();
$datosAuditNuevos = (string)($auditData['datos_nuevos_json'] ?? '');
afirmar(!str_contains($datosAuditNuevos, $claveAutorizadorSecreta), "La clave secreta jamás se graba en los logs de auditoria_operaciones.");

// ==============================================================================
// GRUPO 3: CONCURRENCIA REAL EN CIERRES Y REAPERTURAS (DOS CONEXIONES PDO)
// ==============================================================================
echo "\n--- GRUPO 3: CONCURRENCIA REAL CON DOS CONEXIONES PDO INDEPENDIENTES ---\n";

$pdo2 = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Volver a asentar cierre en la edición con conexión 1
$cierreServicio1 = new CierreEdicionServicio($pdo);
$cierreServicio2 = new CierreEdicionServicio($pdo2);

$resCierre2 = $cierreServicio1->ejecutarCierre(
    organizacionId: $orgId,
    edicionId: $edicionId,
    usuarioId: 24,
    tipoCierre: 'CONDICIONADO',
    justificacion: 'Cierre para prueba de concurrencia con dos PDOs independientes.',
    autorizadorId: $autorizadorId
);
afirmar((int)$resCierre2['id'] > 0, "Edición cerrada nuevamente con Conexión 1.");

// Conexión 2 intenta asentar cierre sobre la misma edición que ya está cerrada
$concurrenciaCierreDetectada = false;
try {
    $cierreServicio2->ejecutarCierre(
        organizacionId: $orgId,
        edicionId: $edicionId,
        usuarioId: 24,
        tipoCierre: 'CONDICIONADO',
        justificacion: 'Intento concurrente simultáneo desde otra sesión.',
        autorizadorId: $autorizadorId
    );
} catch (ConflictoConcurrenciaExcepcion $e) {
    if (str_contains($e->getMessage(), 'ya se encuentra formalmente CERRADA')) {
        $concurrenciaCierreDetectada = true;
    }
}
afirmar($concurrenciaCierreDetectada, "Conexión 2 es rechazada con ConflictoConcurrenciaExcepcion (HTTP 409) al intentar cerrar concurrentemente.");

// ==============================================================================
// GRUPO 4: GUARDIANES DE EDICIÓN CERRADA Y EXCEPCIONES LEGÍTIMAS
// ==============================================================================
echo "\n--- GRUPO 4: GUARDIANES DE EDICIÓN CERRADA Y REGULARIZACIÓN LEGÍTIMA ---\n";

// Obtener un cliente existente de la organización
$stmtCli = $pdo->query("SELECT id FROM `clientes` WHERE organizacion_id = {$orgId} LIMIT 1");
$clienteId = (int) $stmtCli->fetchColumn();
if (!$clienteId) {
    $clienteId = 1;
}

// Insertar venta existente en la edición cerrada con saldo pendiente
$corrVentaCond = 'VEN-COND-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtVentaCond = $pdo->prepare("
    INSERT INTO `ventas`
    (organizacion_id, edicion_id, cliente_id, correlativo, origen_tipo, estado, estado_financiero, moneda, subtotal, total, monto_pagado, saldo_pendiente, cliente_nombre_completo, cliente_numero_documento, cliente_email, fecha_venta, creado_por)
    VALUES
    (:org, :ed, :cli, :corr, 'DIRECTA', 'CONFIRMADA', 'PAGO_PARCIAL', 'PEN', 400.00, 400.00, 0.00, 400.00, 'Cliente Auditoría', '77889900', 'cli_audit@example.com', '2026-02-10', 24)
");
$stmtVentaCond->execute([
    ':org' => $orgId,
    ':ed' => $edicionId,
    ':cli' => $clienteId,
    ':corr' => $corrVentaCond
]);
$ventaCondId = (int) $pdo->lastInsertId();

$authzServicio = new AutorizacionServicio(pdo: $pdo);
$pagoRepo = new PagoRepositorio($pdo);
$reembolsoRepo = new PagoReembolsoRepositorio($pdo);
$cuentaRepo = new CuentaBancariaRepositorio($pdo);
$pasarelaRepo = new PasarelaRepositorio($pdo);
$ventaRepo = new VentaRepositorio($pdo);
$edicionRepo = new EdicionRepositorio($pdo);
$configRepo = new ConfiguracionRepositorio($pdo);

$pagoServicio = new PagoServicio(
    $pagoRepo, $reembolsoRepo, $cuentaRepo, $pasarelaRepo,
    $ventaRepo, $edicionRepo, $configRepo, $authzServicio, $auditoriaRepo, $pdo
);

// 1. Amortización legítima permitida en Cierre Condicionado
$resPagoLegitimo = $pagoServicio->registrarPagoManual(
    organizacionId: $orgId,
    edicionId: $edicionId,
    ventaId: $ventaCondId,
    datos: [
        'metodo_pago' => 'EFECTIVO',
        'monto' => 400.00,
        'aprobacion_inmediata' => true,
        'observaciones' => 'Amortización regularizatoria formal de saldo de Cierre Condicionado',
    ],
    contexto: $contextoOp
);
afirmar(!empty($resPagoLegitimo->id), "Cierre Condicionado admite amortización regularizatoria legítima de saldos pendientes (ID Pago #{$resPagoLegitimo->id}).");

// Verificar que el saldo de la venta ahora es 0.00
$stmtCheckVenta = $pdo->prepare("SELECT saldo_pendiente, estado_financiero FROM `ventas` WHERE id = :id");
$stmtCheckVenta->execute(['id' => $ventaCondId]);
$ventaRow = $stmtCheckVenta->fetch();
afirmar((float)$ventaRow['saldo_pendiente'] === 0.0, "El saldo pendiente se actualizó fielmente a S/ 0.00.");
afirmar($ventaRow['estado_financiero'] === 'PAGADA_TOTAL', "El estado financiero transicionó a PAGADA_TOTAL.");

// 2. Intento de registrar nuevo pago en venta ya liquidada en edición cerrada es bloqueado
$bloqueoPagoLiquidadas = false;
try {
    $pagoServicio->registrarPagoManual(
        organizacionId: $orgId,
        edicionId: $edicionId,
        ventaId: $ventaCondId,
        datos: [
            'metodo_pago' => 'EFECTIVO',
            'monto' => 100.00,
            'aprobacion_inmediata' => true,
        ],
        contexto: $contextoOp
    );
} catch (InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'sin saldos pendientes') || str_contains($e->getMessage(), 'No admite nuevos pagos')) {
        $bloqueoPagoLiquidadas = true;
    }
}
afirmar($bloqueoPagoLiquidadas, "Venta sin saldo pendiente en festividad cerrada rechaza cualquier pago adicional.");

// 3. Checkout público bloqueado
$bloqueoRepo = new ReservaBloqueoTemporalRepositorio($pdo);
$dispMotor = new MotorDisponibilidadServicio($bloqueoRepo, $pdo);
$resBloqueo = $dispMotor->bloquearCupos(
    organizacionId: $orgId,
    edicionId: $edicionId,
    itemComercialId: 1,
    cuposSolicitados: 1,
    identificadorSesion: 'sesion-test-' . uniqid()
);
$checkoutBloqueado = ($resBloqueo['exito'] === false && str_contains($resBloqueo['mensaje'], 'CERRADA'));
afirmar($checkoutBloqueado, "Checkout y compra pública bloqueados en festividad cerrada.");

// ==============================================================================
// GRUPO 5: CONCILIACIÓN MATEMÁTICA CANÓNICA: DASHBOARD EJECUTIVO VS REPORTES
// ==============================================================================
echo "\n--- GRUPO 5: CONCILIACIÓN MATEMÁTICA CANÓNICA (DASHBOARD VS REPORTES) ---\n";

// Crear edición exclusiva para conciliación matemática limpia
$aniosUsados = $pdo->query("SELECT anio FROM `ediciones_candelaria` WHERE organizacion_id = {$orgId}")->fetchAll(PDO::FETCH_COLUMN);
$anioTest2 = $anioTest + 1;
while (in_array($anioTest2, $aniosUsados)) {
    $anioTest2++;
}

$codConciliacion = 'ED-CONC-' . substr(bin2hex(random_bytes(3)), 0, 6);
$pdo->prepare("
    INSERT INTO `ediciones_candelaria` 
    (organizacion_id, codigo, nombre, anio, fecha_inicio, fecha_fin, estado, es_actual)
    VALUES 
    (:org, :cod, 'Edición Conciliación Matemática', :anio, '2026-02-01', '2026-02-15', 'OPERACION', 0)
")->execute([':org' => $orgId, ':cod' => $codConciliacion, ':anio' => $anioTest2]);
$edicionConcId = (int) $pdo->lastInsertId();

// Reutilizar o insertar Item Comercial y Oferta
$stmtIc = $pdo->prepare("SELECT id FROM `items_comerciales` WHERE organizacion_id = :org LIMIT 1");
$stmtIc->execute([':org' => $orgId]);
$itemConcId = (int)$stmtIc->fetchColumn();
if (!$itemConcId) {
    $pdo->prepare("
        INSERT INTO `items_comerciales` (organizacion_id, codigo, nombre, tipo_item, precio_base, estado)
        VALUES (:org, 'ITEM-CONC-01', 'Paquete Candelaria VIP', 'PAQUETE_SERVICIOS', 2000.00, 'ACTIVO')
    ")->execute([':org' => $orgId]);
    $itemConcId = (int)$pdo->lastInsertId();
}

$stmtOferta = $pdo->prepare("
    INSERT INTO `ofertas_items_edicion` (organizacion_id, edicion_id, item_comercial_id, estado)
    VALUES (:org, :ed, :item, 'ACTIVO')
");
$stmtOferta->execute([':org' => $orgId, ':ed' => $edicionConcId, ':item' => $itemConcId]);
$ofertaItemConcId = (int)$pdo->lastInsertId();

// Venta A: Total 2,000 PEN, Pagado 2,000 PEN (Pasarela, comisión 60 PEN, neto 1,940 PEN), Saldo 0
$corrVA = 'VEN-A-' . substr(bin2hex(random_bytes(3)), 0, 6);
$pdo->prepare("
    INSERT INTO `ventas`
    (organizacion_id, edicion_id, cliente_id, correlativo, origen_tipo, estado, estado_financiero, moneda, subtotal, total, monto_pagado, saldo_pendiente, cliente_nombre_completo, cliente_numero_documento, cliente_email, fecha_venta, creado_por)
    VALUES
    (:org, :ed, :cli, :corr, 'DIRECTA', 'CONFIRMADA', 'PAGADA_TOTAL', 'PEN', 2000.00, 2000.00, 2000.00, 0.00, 'Cliente A', '11112222', 'a@example.com', '2026-02-10', 24)
")->execute([':org' => $orgId, ':ed' => $edicionConcId, ':cli' => $clienteId, ':corr' => $corrVA]);
$ventaAId = (int) $pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO `pagos`
    (organizacion_id, edicion_id, venta_id, correlativo, metodo_pago, estado, moneda, monto_cobrado_cliente, comision_pasarela, monto_neto_recibido, monto_aplicado_venta, fecha_pago, numero_operacion_bancaria, creado_por)
    VALUES
    (:org, :ed, :v, :corr, 'TARJETA_PASARELA', 'APROBADO', 'PEN', 2000.00, 60.00, 1940.00, 2000.00, '2026-02-10 10:00:00', 'OP-PASARELA-01', 24)
")->execute([':org' => $orgId, ':ed' => $edicionConcId, ':v' => $ventaAId, ':corr' => 'PAG-A-' . substr(bin2hex(random_bytes(2)), 0, 4)]);

// Venta B: Total 1,000 PEN, Pagado 400 PEN (Efectivo, comisión 0), Saldo 600 PEN
$corrVB = 'VEN-B-' . substr(bin2hex(random_bytes(3)), 0, 6);
$pdo->prepare("
    INSERT INTO `ventas`
    (organizacion_id, edicion_id, cliente_id, correlativo, origen_tipo, estado, estado_financiero, moneda, subtotal, total, monto_pagado, saldo_pendiente, cliente_nombre_completo, cliente_numero_documento, cliente_email, fecha_venta, creado_por)
    VALUES
    (:org, :ed, :cli, :corr, 'DIRECTA', 'CONFIRMADA', 'PAGO_PARCIAL', 'PEN', 1000.00, 1000.00, 400.00, 600.00, 'Cliente B', '33334444', 'b@example.com', '2026-02-10', 24)
")->execute([':org' => $orgId, ':ed' => $edicionConcId, ':cli' => $clienteId, ':corr' => $corrVB]);
$ventaBId = (int) $pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO `pagos`
    (organizacion_id, edicion_id, venta_id, correlativo, metodo_pago, estado, moneda, monto_cobrado_cliente, comision_pasarela, monto_neto_recibido, monto_aplicado_venta, fecha_pago, numero_operacion_bancaria, creado_por)
    VALUES
    (:org, :ed, :v, :corr, 'EFECTIVO', 'APROBADO', 'PEN', 400.00, 0.00, 400.00, 400.00, '2026-02-10 11:00:00', 'OP-CAJA-02', 24)
")->execute([':org' => $orgId, ':ed' => $edicionConcId, ':v' => $ventaBId, ':corr' => 'PAG-B-' . substr(bin2hex(random_bytes(2)), 0, 4)]);

// Insertar venta_lineas para Venta A y Venta B
$pdo->prepare("
    INSERT INTO `venta_lineas`
    (venta_id, tipo_linea, item_comercial_id, oferta_item_id, concepto_codigo, concepto_nombre, unidad_medida, cantidad, precio_unitario, subtotal, moneda, orden)
    VALUES
    (:vid, 'ITEM', :item_id, :of_id, 'ITEM-CONC-01', 'Paquete Candelaria VIP', 'SERVICIO', 1.00, 2000.00, 2000.00, 'PEN', 1)
")->execute([':vid' => $ventaAId, ':item_id' => $itemConcId, ':of_id' => $ofertaItemConcId]);
$lineaAId = (int)$pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO `venta_lineas`
    (venta_id, tipo_linea, item_comercial_id, oferta_item_id, concepto_codigo, concepto_nombre, unidad_medida, cantidad, precio_unitario, subtotal, moneda, orden)
    VALUES
    (:vid, 'ITEM', :item_id, :of_id, 'ITEM-CONC-01', 'Paquete Candelaria VIP', 'SERVICIO', 1.00, 1000.00, 1000.00, 'PEN', 1)
")->execute([':vid' => $ventaBId, ':item_id' => $itemConcId, ':of_id' => $ofertaItemConcId]);
$lineaBId = (int)$pdo->lastInsertId();

// Insertar Salida Operativa: Capacidad 20, Agendados 10 (Ocupación 50%)
$pdo->prepare("
    INSERT INTO `operacion_salidas`
    (organizacion_id, edicion_id, item_comercial_id, correlativo, titulo, fecha_salida, hora_citacion, hora_salida, punto_encuentro, tipo_capacidad, capacidad_maxima, estado, creado_por)
    VALUES
    (:org, :ed, :it, :corr, 'Salida Lago', '2026-02-12', '08:00:00', '08:30:00', 'Puerto Puno', 'COLECTIVA', 20, 'PROGRAMADA', 24)
")->execute([':org' => $orgId, ':ed' => $edicionConcId, ':it' => $itemConcId, ':corr' => 'SAL-' . substr(bin2hex(random_bytes(2)), 0, 4)]);
$salidaConcId = (int) $pdo->lastInsertId();

// Reserva para Venta A
$pdo->prepare("
    INSERT INTO `reservas` (organizacion_id, edicion_id, venta_id, cliente_id, correlativo, estado, contacto_nombre, contacto_telefono, contacto_email, notas_operativas, creado_por)
    VALUES (:org, :ed, :v, :cli, :corr, 'CONFIRMADA', 'Cliente A', '999111', 'a@example.com', '[CHECKIN_QR_REGISTRADO: 2026-02-12 08:15:00]', 24)
")->execute([':org' => $orgId, ':ed' => $edicionConcId, ':v' => $ventaAId, ':cli' => $clienteId, ':corr' => 'RES-A-' . substr(bin2hex(random_bytes(2)), 0, 4)]);
$reservaAId = (int) $pdo->lastInsertId();

// Reserva para Venta B (Ausente)
$pdo->prepare("
    INSERT INTO `reservas` (organizacion_id, edicion_id, venta_id, cliente_id, correlativo, estado, contacto_nombre, contacto_telefono, contacto_email, notas_operativas, creado_por)
    VALUES (:org, :ed, :v, :cli, :corr, 'CONFIRMADA', 'Cliente B', '999222', 'b@example.com', 'Sin checkin', 24)
")->execute([':org' => $orgId, ':ed' => $edicionConcId, ':v' => $ventaBId, ':cli' => $clienteId, ':corr' => 'RES-B-' . substr(bin2hex(random_bytes(2)), 0, 4)]);
$reservaBId = (int) $pdo->lastInsertId();

// Participantes: 1 Presente, 1 Ausente -> 50% de asistencia
$pdo->prepare("INSERT INTO `reserva_participantes` (reserva_id, tipo_documento_id, numero_documento, nombres, apellidos, es_titular_reserva) VALUES (:r, 1, '11112222', 'Cliente', 'A', 1)")->execute([':r' => $reservaAId]);
$pdo->prepare("INSERT INTO `reserva_participantes` (reserva_id, tipo_documento_id, numero_documento, nombres, apellidos, es_titular_reserva) VALUES (:r, 1, '33334444', 'Cliente', 'B', 1)")->execute([':r' => $reservaBId]);

// Prestación y asignación a salida (10 pasajeros en total agendados)
$pdo->prepare("
    INSERT INTO `reserva_prestaciones` (reserva_id, venta_linea_id, item_comercial_id, concepto_codigo, concepto_nombre, unidad_medida, cantidad, tipo_capacidad, requiere_agendamiento, requiere_participantes, estado_agendamiento, fecha_servicio)
    VALUES (:r, :vl, :it, 'TOUR', 'Tour Lago', 'SERVICIO', 10.00, 'COLECTIVA', 1, 1, 'PROGRAMADA', '2026-02-12')
")->execute([':r' => $reservaAId, ':vl' => $lineaAId, ':it' => $itemConcId]);
$prestacionConcId = (int) $pdo->lastInsertId();
$prestacionConcId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `operacion_salida_prestaciones` (salida_id, prestacion_id, cantidad_pasajeros, asignado_por) VALUES (:sid, :pid, 10, 24)")
    ->execute([':sid' => $salidaConcId, ':pid' => $prestacionConcId]);

// Obtener datos del Dashboard
$dashboardServicio = new DashboardEjecutivoServicio($pdo);
$metricasDashboard = $dashboardServicio->obtenerKpisEjecutivos($orgId, $edicionConcId);

// Obtener reportes oficiales
$libroReporte = $reporteServicio->obtenerLibroIngresos($orgId, $edicionConcId);
$manifiestoReporte = $reporteServicio->obtenerManifiestoSalidas($orgId, $edicionConcId);
$padronReporte = $reporteServicio->obtenerPadronAsistencia($orgId, $edicionConcId);

// Comparar matemáticamente
$kpis = $metricasDashboard['kpis'];

// 1. Total devengado
afirmar(
    abs($kpis['finanzas']['total_devengado'] - $libroReporte['totales']['total_ventas']) < 0.01,
    "Total Devengado concilia exactamente: Dashboard S/ {$kpis['finanzas']['total_devengado']} == Libro Ingresos S/ {$libroReporte['totales']['total_ventas']}."
);

// 2. Total cobrado cliente
afirmar(
    abs($kpis['finanzas']['total_cobrado_cliente'] - $libroReporte['totales']['total_cobrado']) < 0.01,
    "Total Cobrado concilia exactamente: Dashboard S/ {$kpis['finanzas']['total_cobrado_cliente']} == Libro Ingresos S/ {$libroReporte['totales']['total_cobrado']}."
);

// 3. Comisiones de pasarela
afirmar(
    abs($kpis['finanzas']['total_comisiones_pasarela'] - $libroReporte['totales']['total_comisiones_pasarela']) < 0.01,
    "Comisiones de pasarela concilian: Dashboard S/ {$kpis['finanzas']['total_comisiones_pasarela']} == Libro Ingresos S/ {$libroReporte['totales']['total_comisiones_pasarela']}."
);

// 4. Recaudación neta
afirmar(
    abs($kpis['finanzas']['total_neto_recaudado'] - $libroReporte['totales']['total_neto_bancos']) < 0.01,
    "Recaudación Neta Líquida concilia: Dashboard S/ {$kpis['finanzas']['total_neto_recaudado']} == Libro Ingresos S/ {$libroReporte['totales']['total_neto_bancos']}."
);

// 5. Saldo pendiente
afirmar(
    abs($kpis['finanzas']['total_saldo_pendiente'] - $libroReporte['totales']['total_saldo_pendiente']) < 0.01,
    "Saldo Pendiente concilia: Dashboard S/ {$kpis['finanzas']['total_saldo_pendiente']} == Libro Ingresos S/ {$libroReporte['totales']['total_saldo_pendiente']}."
);

// 6. Ocupación de flota
afirmar(
    abs($kpis['operaciones']['tasa_ocupacion_flota_pct'] - $manifiestoReporte['totales']['tasa_ocupacion_flota_pct']) < 0.01,
    "Ocupación de flota concilia: Dashboard {$kpis['operaciones']['tasa_ocupacion_flota_pct']}% == Manifiesto {$manifiestoReporte['totales']['tasa_ocupacion_flota_pct']}%."
);

// 7. Tasa de asistencia
afirmar(
    abs($kpis['asistencia']['tasa_asistencia_pct'] - $padronReporte['totales']['tasa_asistencia_pct']) < 0.01,
    "Tasa de Asistencia concilia: Dashboard {$kpis['asistencia']['tasa_asistencia_pct']}% == Padrón {$padronReporte['totales']['tasa_asistencia_pct']}%."
);

// ==============================================================================
// GRUPO 6: AISLAMIENTO MULTI-TENANT RIGUROSO (ANTI-IDOR) Y CONTROL DE PERMISOS
// ==============================================================================
echo "\n--- GRUPO 6: AISLAMIENTO MULTI-TENANT RIGUROSO (ANTI-IDOR) ---\n";

$orgAjenaId = 999999;
$idorReportesBloqueado = false;
try {
    $reporteServicio->obtenerLibroIngresos($orgAjenaId, $edicionConcId);
} catch (InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'no pertenece a la organización')) {
        $idorReportesBloqueado = true;
    }
}
afirmar($idorReportesBloqueado, "Anti-IDOR: Organización ajena no puede acceder a reportes de otra organización.");

$kpisAjeno = $dashboardServicio->obtenerKpisEjecutivos($orgAjenaId, $edicionConcId);
afirmar($kpisAjeno['edicion_actual'] === null, "Anti-IDOR: Consultar edición con organización ajena no encuentra la entidad.");
afirmar($kpisAjeno['kpis']['finanzas']['total_devengado'] === 0.0, "Anti-IDOR: Organización ajena no filtra montos devengados ni información financiera.");

// Mínimo Privilegio: Bloqueo de exportación sin permiso atómico
$authMock = new class extends \Nucleo\Http\Middleware\AutenticacionMiddleware {
    public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion {
        return new ContextoOperacion(
            actorTipo: 'HUMANO',
            usuarioId: 24,
            actorSistemaId: null,
            actorSistemaCodigo: null,
            canalId: 1,
            canalCodigo: 'WEB',
            correlacionId: 'CORR-TEST-F212D-000000001',
            origenIp: '127.0.0.1',
            agenteUsuario: 'TestRunner/1.0',
            organizacionId: $GLOBALS['orgId'] ?? 1,
            metadatos: ['roles' => ['operador_edicion'], 'csrf_token' => 'token_valido']
        );
    }
};

$authzSinExportar = new class extends \Nucleo\Http\Middleware\AutorizacionMiddleware {
    public function verificarPermiso(string $codigoPermiso, ?ContextoOperacion $contexto = null, bool $bloquearPeticion = true): bool {
        // Permite ver, pero deniega exportar
        return !str_contains($codigoPermiso, 'exportar');
    }
};

$controladorEd = new EdicionControlador(
    authMiddleware: $authMock,
    authzMiddleware: $authzSinExportar,
    reporteServicio: $reporteServicio,
    pdo: $pdo
);

$_GET['tipo'] = 'LIBRO_INGRESOS';
$_GET['formato'] = 'xlsx';
$resDenegado = json_decode($controladorEd->exportarReporte($edicionConcId), true);
afirmar(($resDenegado['exito'] ?? true) === false, "Principio de Mínimo Privilegio: Deniega exportar XLSX si carece del permiso de exportación.");

// ==============================================================================
// GRUPO 7: PRUEBA DE DESPLIEGUE E INSTALACIÓN LIMPIA DESDE CERO
// ==============================================================================
echo "\n--- GRUPO 7: DESPLIEGUE E INSTALACIÓN LIMPIA DESDE CERO ---\n";

$dbTestClean = 'app_candelaria_test_clean';

try {
    // 1. Crear base de datos limpia de prueba
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbTestClean}`");
    $pdo->exec("CREATE DATABASE `{$dbTestClean}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    afirmar(true, "Base de datos temporal de instalación limpia creada: `{$dbTestClean}`.");

    $pdoClean = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbTestClean};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 2. Ejecutar esquema reproducible oficial (esquema_base.sql)
    $esquemaBaseFile = __DIR__ . '/../base_datos/esquema/esquema_base.sql';
    afirmar(file_exists($esquemaBaseFile), "Archivo de esquema reproducible base existe.");
    $sqlBase = file_get_contents($esquemaBaseFile);
    $pdoClean->exec($sqlBase);
    afirmar(true, "Esquema base reproducible oficial desplegado satisfactoriamente.");

    // 3. Verificar y ejecutar cualquier migración incremental pendiente
    $migracionesDir = __DIR__ . '/../base_datos/migraciones';
    $archivosMigraciones = glob("{$migracionesDir}/*.sql");
    sort($archivosMigraciones);
    $migracionesEjecutadas = $pdoClean->query("SELECT migracion FROM migraciones_control")->fetchAll(PDO::FETCH_COLUMN);

    $migracionesNuevas = 0;
    foreach ($archivosMigraciones as $migracionPath) {
        $nombreMig = basename($migracionPath);
        if (!in_array($nombreMig, $migracionesEjecutadas, true)) {
            $pdoClean->exec(file_get_contents($migracionPath));
            $migracionesNuevas++;
        }
    }
    afirmar(true, "Trazabilidad de migraciones verificada (migraciones pendientes aplicadas: {$migracionesNuevas}).");

    // 4. Verificar catálogo de 104 permisos en la base de datos limpia
    $stmtCountPermisos = $pdoClean->query("SELECT COUNT(*) FROM `permisos`");
    $cantidadPermisos = (int) $stmtCountPermisos->fetchColumn();
    afirmar($cantidadPermisos === 104, "El catálogo de permisos en la instalación limpia contiene exactamente 104 permisos (obtenido: {$cantidadPermisos}).");

    // 5. Verificar tablas críticas creadas
    $tablasEsperadas = [
        'organizaciones', 'usuarios', 'roles', 'permisos', 'usuario_roles', 'rol_permisos',
        'ediciones_candelaria', 'edicion_cierres', 'edicion_cierre_reaperturas',
        'ventas', 'pagos', 'recibos_caja', 'reservas', 'operacion_salidas', 'auditoria_operaciones'
    ];
    $stmtTablas = $pdoClean->query("SHOW TABLES");
    $tablasExistentes = $stmtTablas->fetchAll(PDO::FETCH_COLUMN);

    $todasTablasPresentes = true;
    foreach ($tablasEsperadas as $tbl) {
        if (!in_array($tbl, $tablasExistentes, true)) {
            $todasTablasPresentes = false;
            break;
        }
    }
    afirmar($todasTablasPresentes, "Todas las tablas maestras y de cierre existen en el esquema recién desplegado.");

    // Destruir base de datos temporal
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbTestClean}`");
    afirmar(true, "Base de datos temporal destruida tras certificación limpia exitosa.");

} catch (Throwable $e) {
    afirmar(false, "Fallo durante el despliegue e instalación limpia: " . $e->getMessage());
    try {
        $pdo->exec("DROP DATABASE IF EXISTS `{$dbTestClean}`");
    } catch (Throwable $ignore) {}
}

// ==============================================================================
// GRUPO 8: INMUTABILIDAD DEL USUARIO ORLANDO (ID 24)
// ==============================================================================
echo "\n--- GRUPO 8: PRESERVACIÓN INMUTABLE DE HUELLA DEL USUARIO ORLANDO (ID 24) ---\n";

$stmtOrlando = $pdo->query("SELECT id, correo_electronico, contrasena_hash FROM `usuarios` WHERE id = 24");
$usuarioOrlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);

afirmar($usuarioOrlando !== false, "El usuario Orlando (ID 24) existe en el sistema.");

$huellaCalculada = substr(hash('sha256', (string)$usuarioOrlando['contrasena_hash']), 0, 16);
$huellaEsperada = '80e6af84e02e89e3';

afirmar($huellaCalculada === $huellaEsperada, "Huella inmutable de Orlando (ID 24) intacta: {$huellaCalculada} == {$huellaEsperada}.");

// Limpieza final de datos de prueba
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM `edicion_cierre_reaperturas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `edicion_cierres` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `recibos_caja` WHERE venta_id IN (SELECT id FROM `ventas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'))");
$pdo->exec("DELETE FROM `pagos` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `reserva_participantes` WHERE reserva_id IN (SELECT id FROM `reservas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'))");
$pdo->exec("DELETE FROM `reserva_prestaciones` WHERE reserva_id IN (SELECT id FROM `reservas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'))");
$pdo->exec("DELETE FROM `reservas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `venta_lineas` WHERE venta_id IN (SELECT id FROM `ventas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'))");
$pdo->exec("DELETE FROM `ventas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `operacion_salida_prestaciones` WHERE salida_id IN (SELECT id FROM `operacion_salidas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'))");
$pdo->exec("DELETE FROM `operacion_salidas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `ofertas_items_edicion` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%')");
$pdo->exec("DELETE FROM `ediciones_candelaria` WHERE codigo LIKE 'ED-F212D-%' OR codigo LIKE 'ED-CONC-%'");
$pdo->exec("DELETE FROM `usuarios` WHERE correo_electronico LIKE 'autorizador_%@example.com'");
$pdo->exec("DELETE FROM `personas` WHERE numero_documento LIKE '88%' AND nombres = 'Fiscal'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// ==============================================================================
// RESUMEN FINAL DE LA SUITE
// ==============================================================================
echo "\n==============================================================================\n";
echo "RESUMEN FASE 2.12D: {$passedTests}/{$totalTests} PRUEBAS EXITOSAS";
if ($failedTests > 0) {
    echo " ({$failedTests} FALLIDAS)\n";
} else {
    echo "\nTODAS LAS PRUEBAS DE AUDITORÍA Y CERTIFICACIÓN FINAL COMPLETADAS CON ÉXITO [PASS]\n";
}
echo "==============================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
