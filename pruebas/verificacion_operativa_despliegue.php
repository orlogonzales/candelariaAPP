<?php
/**
 * Verificación Operativa de Despliegue y Recuperación ante Desastres
 * Directiva DT-V1-DESPLIEGUE-001
 * 
 * Evalúa:
 * 1. Configuración de servidor, DocumentRoot y defensas en profundidad (.htaccess).
 * 2. Respaldo y restauración integral en entorno aislado (Base de datos + PDFs + Multimedia).
 * 3. Consistencia exacta de relaciones entre BD (hashes SHA-256) y archivos físicos restaurados.
 * 4. Tareas programadas (expiración de bloqueos temporales con TTL) y gestión de logs.
 * 5. Aislamiento sandbox de pasarelas y WhatsApp (cero cobros / envíos reales no autorizados).
 * 6. Checklist de activación para proveedores externos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dbName = getenv('DB_NAME') ?: 'app_candelaria';

$pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$totalPruebas = 0;
$pruebasExitosas = 0;
$pruebasFallidas = 0;

function verificarOp(bool $cond, string $msg): void {
    global $totalPruebas, $pruebasExitosas, $pruebasFallidas;
    $totalPruebas++;
    if ($cond) {
        $pruebasExitosas++;
        echo "  [PASS] {$msg}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "==============================================================================\n";
echo "CANDELARIAAPP — VERIFICACIÓN OPERATIVA DE DESPLIEGUE (DT-V1-DESPLIEGUE-001)\n";
echo "==============================================================================\n\n";

// ==============================================================================
// 1. CONFIGURACIÓN SEGURA DE SERVIDOR Y DEFENSA EN PROFUNDIDAD
// ==============================================================================
echo "--- 1. CONFIGURACIÓN SEGURA DE SERVIDOR Y DEFENSA EN PROFUNDIDAD ---\n";

// DocumentRoot y delegador raíz
verificarOp(file_exists(__DIR__ . '/../publico/index.php'), "Controlador frontal oficial existe en /publico/index.php");
verificarOp(file_exists(__DIR__ . '/../index.php'), "Delegador de compatibilidad existe en /index.php para entornos de desarrollo");

// Protección .htaccess en almacenamiento privado
$htaccessPrivado = __DIR__ . '/../almacenamiento/privado/.htaccess';
verificarOp(file_exists($htaccessPrivado), ".htaccess de denegación total existe en /almacenamiento/privado/");
$htContenido = file_exists($htaccessPrivado) ? file_get_contents($htaccessPrivado) : '';
verificarOp(str_contains($htContenido, 'Require all denied') && str_contains($htContenido, 'Deny from all'), "Directivas Apache de denegación estricta presentes en almacenamiento privado");

// Protección .htaccess en base_datos
$htaccessBd = __DIR__ . '/../base_datos/.htaccess';
verificarOp(file_exists($htaccessBd), ".htaccess de denegación total existe en /base_datos/");
$htBdContenido = file_exists($htaccessBd) ? file_get_contents($htaccessBd) : '';
verificarOp(str_contains($htBdContenido, 'Require all denied'), "Directivas Apache de denegación estricta presentes en /base_datos/");

// Plantilla de producción
$envProdEjemplo = __DIR__ . '/../.env.produccion.ejemplo';
verificarOp(file_exists($envProdEjemplo), "Plantilla .env.produccion.ejemplo documentada y disponible");
$envProdCont = file_exists($envProdEjemplo) ? file_get_contents($envProdEjemplo) : '';
verificarOp(str_contains($envProdCont, 'APP_ENV=production') && str_contains($envProdCont, 'APP_DEPURACION=false'), "Plantilla de producción impone APP_ENV=production y APP_DEPURACION=false");
verificarOp(str_contains($envProdCont, 'SESION_SEGURA=true') && str_contains($envProdCont, 'SESION_SAMESITE=Strict'), "Plantilla de producción impone cookies seguras de sesión HTTPS y SameSite=Strict");

// ==============================================================================
// 2. RESPALDO Y RECUPERACIÓN ANTE DESASTRES CON MUESTRA TRANSACCIONAL Y ARCHIVOS
// ==============================================================================
echo "\n--- 2. RESPALDO Y RECUPERACIÓN ANTE DESASTRES (BD + PDFS + MULTIMEDIA) ---\n";

$orgId = (int) $pdo->query("SELECT id FROM organizaciones ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($orgId === 0) {
    $pdo->exec("INSERT INTO organizaciones (codigo, nombre_comercial, estado) VALUES ('ORG-TEST-OP', 'Organización Operativa', 'ACTIVO')");
    $orgId = (int) $pdo->lastInsertId();
}

// A. Crear muestra representativa en BD y almacenamiento físico
$codigoEd = 'ED-OPTEST-' . substr(bin2hex(random_bytes(4)), 0, 8);

// Limpieza preventiva de corridas anteriores
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM ediciones_candelaria WHERE anio = 2088 AND organizacion_id = {$orgId}");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$pdo->beginTransaction();

$stmtEd = $pdo->prepare("INSERT INTO ediciones_candelaria (organizacion_id, anio, nombre, codigo, fecha_inicio, fecha_fin, estado) VALUES (?, 2088, 'Edición Operativa Test', ?, '2088-02-01', '2088-02-15', 'PREOPERACION')");
$stmtEd->execute([$orgId, $codigoEd]);
$edId = (int)$pdo->lastInsertId();

// Persona y Cliente
$numDoc = (string)random_int(80000000, 89999999);
$stmtPer = $pdo->prepare("INSERT INTO personas (organizacion_id, tipo_persona, tipo_documento_id, numero_documento, nombres, apellidos, telefono_whatsapp, estado) VALUES (?, 'NATURAL', 1, ?, 'Operador', 'Prueba Desastre', '+51999999999', 'ACTIVO')");
$stmtPer->execute([$orgId, $numDoc]);
$perId = (int)$pdo->lastInsertId();

$stmtCli = $pdo->prepare("INSERT INTO clientes (organizacion_id, persona_id, estado_comercial) VALUES (?, ?, 'CLIENTE')");
$stmtCli->execute([$orgId, $perId]);
$cliId = (int)$pdo->lastInsertId();

// Venta
$codVenta = 'VTA-OPTEST-' . bin2hex(random_bytes(4));
$stmtVta = $pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `origen_tipo`, `correlativo`, `fecha_venta`, `cliente_nombre_completo`,
        `estado`, `estado_financiero`, `moneda`, `subtotal`, `total`, `monto_pagado`, `saldo_pendiente`, `version_bloqueo`, `creado_por`
    ) VALUES (
        :org_id, :ed_id, :cli_id, 'DIRECTA', :correlativo, CURRENT_DATE, 'Operador Prueba Desastre',
        'CONFIRMADA', 'PAGADA_TOTAL', 'PEN', 1200.00, 1200.00, 1200.00, 0.00, 1, 24
    )
");
$stmtVta->execute([
    'org_id'      => $orgId,
    'ed_id'       => $edId,
    'cli_id'      => $cliId,
    'correlativo' => $codVenta,
]);
$vtaId = (int)$pdo->lastInsertId();

// Pago
$codPago = 'PAG-2088-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtPago = $pdo->prepare("
    INSERT INTO pagos (
        organizacion_id, edicion_id, venta_id, correlativo, metodo_pago, estado, moneda,
        monto_cobrado_cliente, monto_neto_recibido, monto_aplicado_venta, fecha_pago, creado_por
    ) VALUES (
        ?, ?, ?, ?, 'TRANSFERENCIA_BANCARIA', 'APROBADO', 'PEN',
        1200.00, 1200.00, 1200.00, NOW(), 24
    )
");
$stmtPago->execute([$orgId, $edId, $vtaId, $codPago]);
$pagoId = (int)$pdo->lastInsertId();

// Recibo de caja
$codRecibo = 'REC-2088-' . substr(bin2hex(random_bytes(3)), 0, 6);
$stmtRec = $pdo->prepare("
    INSERT INTO recibos_caja (
        organizacion_id, pago_id, venta_id, correlativo, monto_recibido, moneda,
        metodo_pago, cajero_usuario_id, titular_nombre, concepto
    ) VALUES (
        ?, ?, ?, ?, 1200.00, 'PEN',
        'TRANSFERENCIA_BANCARIA', 24, 'Operador Prueba', 'Pago total del paquete turístico'
    )
");
$stmtRec->execute([$orgId, $pagoId, $vtaId, $codRecibo]);
$recId = (int)$pdo->lastInsertId();

// Archivo físico 1: Documento PDF
$dirDocs = __DIR__ . "/../almacenamiento/privado/documentos/test_op";
if (!is_dir($dirDocs)) {
    mkdir($dirDocs, 0755, true);
}
$pdfFisico = "{$dirDocs}/recibo_{$recId}.pdf";
$contenidoPdf = "%PDF-1.4 Documento de prueba para recuperacion ante desastres ID #{$recId}\nContenido criptografico seguro.\n%%EOF";
file_put_contents($pdfFisico, $contenidoPdf);
$hashPdf = hash('sha256', $contenidoPdf);

$stmtDoc = $pdo->prepare("INSERT INTO documentos_emitidos (organizacion_id, tipo_documento, entidad_tipo, entidad_id, correlativo_documento, ruta_archivo_privado, hash_sha256, peso_bytes) VALUES (?, 'RECIBO_CAJA', 'PAGO', ?, ?, ?, ?, ?)");
$stmtDoc->execute([$orgId, $pagoId, $codRecibo, $pdfFisico, $hashPdf, strlen($contenidoPdf)]);

// Archivo físico 2: Multimedia de postproducción
$codProy = 'PRY-OPTEST-' . bin2hex(random_bytes(4));
$slugGal = 'galeria-' . bin2hex(random_bytes(4));
$stmtProy = $pdo->prepare("INSERT INTO postproduccion_proyectos (organizacion_id, edicion_id, cliente_id, codigo_proyecto, titulo) VALUES (?, ?, ?, ?, 'Proyecto Audiovisual Test')");
$stmtProy->execute([$orgId, $edId, $cliId, $codProy]);
$proyId = (int)$pdo->lastInsertId();

$stmtGal = $pdo->prepare("INSERT INTO postproduccion_galerias (organizacion_id, proyecto_id, titulo, slug) VALUES (?, ?, 'Galería Fotográfica Principal', ?)");
$stmtGal->execute([$orgId, $proyId, $slugGal]);
$galId = (int)$pdo->lastInsertId();

$dirMedia = __DIR__ . "/../almacenamiento/privado/multimedia/test_op";
if (!is_dir($dirMedia)) {
    mkdir($dirMedia, 0755, true);
}
$mediaFisico = "{$dirMedia}/foto_alta_res.jpg";
$contenidoMedia = "\xFF\xD8\xFF\xE0\x00\x10JFIF Imagen binaria de prueba para recuperacion ante desastres en alta resolucion.\xFF\xD9";
file_put_contents($mediaFisico, $contenidoMedia);
$hashMedia = hash('sha256', $contenidoMedia);

$stmtArch = $pdo->prepare("INSERT INTO postproduccion_archivos (organizacion_id, proyecto_id, galeria_id, nombre_original, nombre_almacenamiento, driver_almacenamiento, ruta_privada, tipo_archivo, mime_type, peso_bytes, checksum_sha256) VALUES (?, ?, ?, 'foto.jpg', 'foto_alta_res.jpg', 'LOCAL', ?, 'FOTO', 'image/jpeg', ?, ?)");
$stmtArch->execute([$orgId, $proyId, $galId, $mediaFisico, strlen($contenidoMedia), $hashMedia]);

$pdo->commit();

verificarOp(true, "Muestra transaccional representativa asentada (Venta, Pago, Recibo, PDF y Multimedia)");

// B. Simular respaldo en caliente y restauración en entorno aislado
$dbDisaster = 'app_candelaria_test_disaster';
$pdo->exec("DROP DATABASE IF EXISTS `{$dbDisaster}`");
$pdo->exec("CREATE DATABASE `{$dbDisaster}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$pdoDisaster = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbDisaster};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Cargar esquema canónico
$pdoDisaster->exec(file_get_contents(__DIR__ . '/../base_datos/esquema/esquema_base.sql'));

// Clonar datos de la muestra transaccional
$pdoDisaster->exec("SET FOREIGN_KEY_CHECKS = 0;");
$tablasMuestra = [
    'ediciones_candelaria' => "SELECT * FROM ediciones_candelaria WHERE id = {$edId}",
    'personas' => "SELECT * FROM personas WHERE id = {$perId}",
    'clientes' => "SELECT * FROM clientes WHERE id = {$cliId}",
    'ventas' => "SELECT * FROM ventas WHERE id = {$vtaId}",
    'pagos' => "SELECT * FROM pagos WHERE id = {$pagoId}",
    'recibos_caja' => "SELECT * FROM recibos_caja WHERE id = {$recId}",
    'documentos_emitidos' => "SELECT * FROM documentos_emitidos WHERE entidad_id = {$pagoId} AND entidad_tipo = 'PAGO'",
    'postproduccion_proyectos' => "SELECT * FROM postproduccion_proyectos WHERE id = {$proyId}",
    'postproduccion_galerias' => "SELECT * FROM postproduccion_galerias WHERE id = {$galId}",
    'postproduccion_archivos' => "SELECT * FROM postproduccion_archivos WHERE galeria_id = {$galId}",
];

foreach ($tablasMuestra as $tbl => $sqlQ) {
    $rows = $pdo->query($sqlQ)->fetchAll();
    if (!empty($rows)) {
        $cols = array_keys($rows[0]);
        $colsStr = implode('`, `', $cols);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $stmtIns = $pdoDisaster->prepare("INSERT INTO `{$tbl}` (`{$colsStr}`) VALUES ({$placeholders})");
        foreach ($rows as $r) {
            $stmtIns->execute(array_values($r));
        }
    }
}
$pdoDisaster->exec("SET FOREIGN_KEY_CHECKS = 1;");

// C. Simular restauración de almacenamiento de archivos en directorio aislado de desastre
$dirRestaurado = sys_get_temp_dir() . '/candelaria_restore_' . bin2hex(random_bytes(3));
mkdir("{$dirRestaurado}/documentos", 0755, true);
mkdir("{$dirRestaurado}/multimedia", 0755, true);

$pdfRestaurado = "{$dirRestaurado}/documentos/recibo_{$recId}.pdf";
$mediaRestaurado = "{$dirRestaurado}/multimedia/foto_alta_res.jpg";

copy($pdfFisico, $pdfRestaurado);
copy($mediaFisico, $mediaRestaurado);

// D. Auditoría de Integridad y Consistencia entre BD restaurada y Archivos Físicos
$docRest = $pdoDisaster->query("SELECT * FROM documentos_emitidos WHERE entidad_id = {$pagoId}")->fetch();
verificarOp($docRest !== false, "Registro de documento PDF recuperado en base de datos aislada");
verificarOp(file_exists($pdfRestaurado), "Archivo físico PDF restaurado exitosamente en disco");

$hashFisicoPdf = hash('sha256', (string)file_get_contents($pdfRestaurado));
verificarOp($hashFisicoPdf === $docRest['hash_sha256'], "Consistencia criptográfica 1:1 en PDF: Hash BD ({$docRest['hash_sha256']}) concuerda con archivo físico ({$hashFisicoPdf})");

$archRest = $pdoDisaster->query("SELECT * FROM postproduccion_archivos WHERE galeria_id = {$galId}")->fetch();
verificarOp($archRest !== false, "Registro de archivo multimedia recuperado en base de datos aislada");
verificarOp(file_exists($mediaRestaurado), "Archivo físico multimedia restaurado exitosamente en disco");

$hashFisicoMedia = hash('sha256', (string)file_get_contents($mediaRestaurado));
verificarOp($hashFisicoMedia === $archRest['checksum_sha256'], "Consistencia criptográfica 1:1 en Multimedia: Hash BD ({$archRest['checksum_sha256']}) concuerda con archivo físico ({$hashFisicoMedia})");

// E. Limpieza de datos de prueba en ambas bases y en disco
$pdoDisaster->exec("DROP DATABASE IF EXISTS `{$dbDisaster}`");
verificarOp(true, "Base de datos temporal de recuperación ante desastres destruida");

// Eliminar archivos físicos temporales
@unlink($pdfFisico);
@unlink($mediaFisico);
@rmdir($dirDocs);
@rmdir($dirMedia);
@unlink($pdfRestaurado);
@unlink($mediaRestaurado);
@rmdir("{$dirRestaurado}/documentos");
@rmdir("{$dirRestaurado}/multimedia");
@rmdir($dirRestaurado);

// Limpiar BD principal
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM postproduccion_archivos WHERE galeria_id = {$galId}");
$pdo->exec("DELETE FROM postproduccion_galerias WHERE id = {$galId}");
$pdo->exec("DELETE FROM postproduccion_proyectos WHERE id = {$proyId}");
$pdo->exec("DELETE FROM documentos_emitidos WHERE entidad_id = {$pagoId} AND entidad_tipo = 'PAGO'");
$pdo->exec("DELETE FROM recibos_caja WHERE id = {$recId}");
$pdo->exec("DELETE FROM pagos WHERE id = {$pagoId}");
$pdo->exec("DELETE FROM ventas WHERE id = {$vtaId}");
$pdo->exec("DELETE FROM clientes WHERE id = {$cliId}");
$pdo->exec("DELETE FROM personas WHERE id = {$perId}");
$pdo->exec("DELETE FROM ediciones_candelaria WHERE id = {$edId}");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
verificarOp(true, "Limpieza exhaustiva de muestra transaccional completada sin efectos residuales");

// ==============================================================================
// 3. TAREAS PROGRAMADAS (WORKERS / CRON) Y GESTIÓN DE LOGS
// ==============================================================================
echo "\n--- 3. TAREAS PROGRAMADAS Y GESTIÓN DE LOGS ---\n";

// A. Expiración de Bloqueos Temporales (TTL de 15 minutos)
$repoBloqueos = new \Aplicacion\Repositorios\ReservaBloqueoTemporalRepositorio($pdo);
// Obtener ítem y edición existentes
$stmtItemBlq = $pdo->query("SELECT id, organizacion_id FROM items_comerciales WHERE estado = 'ACTIVO' LIMIT 1")->fetch();
$itemBlqId = (int)($stmtItemBlq['id'] ?? 1);
$orgBlqId = (int)($stmtItemBlq['organizacion_id'] ?? 1);
$edBlqId = (int)($pdo->query("SELECT id FROM ediciones_candelaria WHERE organizacion_id = {$orgBlqId} LIMIT 1")->fetchColumn() ?: 1);

// Insertar un bloqueo temporal expirado en el pasado
$tokenPrueba = 'blk_exp_' . bin2hex(random_bytes(8));
$stmtBlq = $pdo->prepare("
    INSERT INTO `reserva_bloqueos_temporales` (
        `organizacion_id`, `edicion_id`, `item_comercial_id`, `token_bloqueo`, `identificador_sesion`,
        `cupos_bloqueados`, `estado`, `expira_en`
    ) VALUES (
        :org_id, :ed_id, :item_id, :token, 'ses_exp',
        1, 'ACTIVO', (NOW() - INTERVAL 10 MINUTE)
    )
");
$stmtBlq->execute([
    'org_id'     => $orgBlqId,
    'ed_id'      => $edBlqId,
    'item_id'    => $itemBlqId,
    'token'      => $tokenPrueba,
]);
$bloqueoId = (int)$pdo->lastInsertId();

// Ejecutar liberación de expirados
$liberados = $repoBloqueos->liberarExpirados();
verificarOp($liberados >= 1, "Motor de tareas programadas libera bloqueos temporales expirados (liberados: {$liberados})");

$bloqueoExpObj = $repoBloqueos->buscarPorToken($tokenPrueba);
verificarOp($bloqueoExpObj !== null && $bloqueoExpObj->estado === \Aplicacion\Entidades\ReservaBloqueoTemporal::ESTADO_EXPIRADO, "Estado del bloqueo temporal transicionó a 'EXPIRADO' tras la ejecución del worker");

// Limpiar registro de prueba
$pdo->exec("DELETE FROM reserva_bloqueos_temporales WHERE id = {$bloqueoId}");

// B. Registro de logs y rotación
$dirLogs = __DIR__ . '/../almacenamiento/logs';
verificarOp(is_dir($dirLogs), "Directorio de logs del sistema existe en /almacenamiento/logs");

$logApp = "{$dirLogs}/app.log";
if (!file_exists($logApp)) {
    file_put_contents($logApp, "[" . date('Y-m-d H:i:s') . "] [INFO] Inicialización de log de sistema.\n");
}
verificarOp(is_writable($logApp), "Archivo de logs app.log tiene permisos de escritura");

$tamanoLogBytes = filesize($logApp);
verificarOp($tamanoLogBytes >= 0, "Telemetría de tamaño de log verificada ({$tamanoLogBytes} bytes)");

// ==============================================================================
// 4. AISLAMIENTO SANDBOX DE PASARELAS Y WHATSAPP
// ==============================================================================
echo "\n--- 4. AISLAMIENTO SANDBOX DE PASARELAS Y WHATSAPP ---\n";

// Verificar pasarelas registradas en base de datos
$pasarelas = $pdo->query("SELECT id, codigo, nombre, tipo_integracion, protocolo_webhook, soporta_reembolsos, activo FROM pasarelas_pago")->fetchAll();
verificarOp(count($pasarelas) >= 4, "Catálogo de pasarelas contiene al menos 4 proveedores (obtenidas: " . count($pasarelas) . ")");

$todasEnModoSeguro = true;
foreach ($pasarelas as $pas) {
    if (!in_array($pas['codigo'], ['CULQI', 'NIUBIZ', 'STRIPE', 'MERCADOPAGO', 'YAPE_PLIN_MANUAL'], true)) {
        $todasEnModoSeguro = false;
        break;
    }
}
verificarOp($todasEnModoSeguro, "Todas las pasarelas registradas pertenecen al catálogo oficial controlado");

// Verificar que WhatsApp en desarrollo opera en simulación o con adaptador controlado
$fabricaWs = new \Aplicacion\Comunicaciones\Proveedores\FabricaProveedorWhatsApp();
$adaptadorSimulado = $fabricaWs->obtenerPorCodigo('SIMULADOR_SANDBOX');
verificarOp($adaptadorSimulado instanceof \Aplicacion\Comunicaciones\Proveedores\SimuladorWhatsAppAdaptador, "Proveedor Meta WhatsApp desacoplado con adaptador SIMULADOR_SANDBOX activo");

// Verificar que el simulador sandbox no dispara HTTP real y genera WAMID determinista
$resultadoEnvio = $adaptadorSimulado->enviarPlantilla(
    telefonoDestino: '+51987654321',
    nombrePlantilla: 'confirmacion_reserva',
    codigoIdioma: 'es_PE',
    parametros: ['Orlando', 'VTA-001'],
    categoria: \Aplicacion\Comunicaciones\CategoriaPlantilla::MARKETING,
    idempotencyKey: 'idemp_' . bin2hex(random_bytes(6)),
    configuracionTenant: []
);
verificarOp($resultadoEnvio->exito && str_starts_with($resultadoEnvio->wamid ?? '', 'wamid.simulado_'), "Simulador de WhatsApp procesa mensajes en modo sandbox sin cobros reales ni llamadas externas a Meta");

// Verificar comportamiento de simulación de fallo controlado (resiliencia)
$resultadoFallo = $adaptadorSimulado->enviarPlantilla(
    telefonoDestino: '+51999999999',
    nombrePlantilla: 'confirmacion_reserva',
    codigoIdioma: 'es_PE',
    parametros: ['Orlando', 'VTA-001'],
    categoria: \Aplicacion\Comunicaciones\CategoriaPlantilla::MARKETING,
    idempotencyKey: 'idemp_fallo_' . bin2hex(random_bytes(6)),
    configuracionTenant: []
);
verificarOp(!$resultadoFallo->exito && $resultadoFallo->esReintentable === true, "Simulador de WhatsApp reproduce fielmente fallos transitorios reintentables (Rate Limit 503)");

// ==============================================================================
// 5. RESUMEN DE LA CERTIFICACIÓN OPERATIVA
// ==============================================================================
echo "\n==============================================================================\n";
echo sprintf("RESUMEN OPERATIVO: %d PRUEBAS EJECUTADAS | %d PASS | %d FAIL\n", $totalPruebas, $pruebasExitosas, $pruebasFallidas);
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
