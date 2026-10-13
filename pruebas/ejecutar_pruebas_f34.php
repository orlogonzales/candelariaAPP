<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP V1.1 — SUITE DE PRUEBAS FASE 3.4 (PORTAL DEL CLIENTE Y SELECCIÓN)
 * DIRECTIVA TÉCNICA: DT-V11-014
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

$pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();

$totalPruebas = 0;
$pruebasPasadas = 0;
$pruebasFallidas = 0;

function assertF34(bool $condicion, string $descripcion): void
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
echo "CANDELARIAAPP V1.1 — SUITE DE PRUEBAS FASE 3.4 (DT-V11-014)\n";
echo "PORTAL DEL CLIENTE, SALDOS, PAGOS Y SELECCIÓN DE FOTOGRAFÍAS\n";
echo "==============================================================================\n\n";

// ------------------------------------------------------------------------------
// PREPARACIÓN DE ENTORNOS Y DEPENDENCIAS
// ------------------------------------------------------------------------------
$fachada = \Aplicacion\Servicios\FachadaPortalPublicoServicio::crearPorDefecto($pdo);
$postSvc = new \Aplicacion\Postproduccion\PostproduccionServicio($pdo);

$orgId = (int) $pdo->query("SELECT id FROM organizaciones ORDER BY id ASC LIMIT 1")->fetchColumn();
$edicionId = (int) $pdo->query("SELECT id FROM ediciones_candelaria WHERE organizacion_id = {$orgId} ORDER BY id ASC LIMIT 1")->fetchColumn();
$usuarioAdminId = (int) $pdo->query("SELECT id FROM usuarios WHERE organizacion_id = {$orgId} AND estado = 'ACTIVO' ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($usuarioAdminId <= 0) {
    $usuarioAdminId = 24;
}

// ------------------------------------------------------------------------------
// BLOQUE 1: ESQUEMA DE BASE DE DATOS, MIGRACIÓN 29 Y PERMISOS RBAC
// ------------------------------------------------------------------------------
echo "--- BLOQUE 1: Esquema, Migración 29 y Permisos RBAC ---\n";

// 1.1 Existencia de tabla postproduccion_selecciones
$stmtTbl = $pdo->query("SHOW TABLES LIKE 'postproduccion_selecciones'");
assertF34($stmtTbl->rowCount() === 1, "1.1: Tabla 'postproduccion_selecciones' existe en la base de datos");

// 1.2 Conteo exacto de 10 columnas en postproduccion_selecciones
$stmtCols = $pdo->query("SHOW COLUMNS FROM `postproduccion_selecciones`");
$columnas = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
$columnasEsperadas = [
    'id', 'organizacion_id', 'proyecto_id', 'galeria_id', 'archivo_id',
    'cliente_id', 'estado', 'notas_cliente', 'seleccionado_en', 'actualizado_en'
];
$diffCols = array_diff($columnasEsperadas, $columnas);
assertF34(empty($diffCols) && count($columnas) === 10, "1.2: Tabla 'postproduccion_selecciones' contiene exactamente las 10 columnas oficiales");

// 1.3 Verificación de Unique Key uk_post_sel_cliente_archivo
$stmtKeys = $pdo->query("SHOW KEYS FROM `postproduccion_selecciones` WHERE Key_name = 'uk_post_sel_cliente_archivo'");
$keys = $stmtKeys->fetchAll(PDO::FETCH_ASSOC);
assertF34(count($keys) === 2, "1.3: Llave única compuesta 'uk_post_sel_cliente_archivo' (cliente_id, archivo_id) presente");

// 1.4 Verificación de Permiso 115 postproduccion.selecciones.ver
$stmtPerm = $pdo->prepare("SELECT COUNT(*) FROM `permisos` WHERE `codigo` = 'postproduccion.selecciones.ver'");
$stmtPerm->execute();
assertF34((int) $stmtPerm->fetchColumn() === 1, "1.4: Permiso RBAC oficial 'postproduccion.selecciones.ver' registrado");

// 1.5 Verificación de Migración 29 en historial
$stmtMig = $pdo->prepare("SELECT COUNT(*) FROM `migraciones_control` WHERE `migracion` LIKE '%000029_crear_modulo_seleccion_fotografica_portal%'");
$stmtMig->execute();
assertF34((int) $stmtMig->fetchColumn() === 1, "1.5: Migración 29 formalmente registrada en 'migraciones_control'");

// 1.6 Conteo global exacto de tablas y permisos
$totalTablas = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchColumn();
$totalPermisos = (int) $pdo->query("SELECT COUNT(*) FROM `permisos`")->fetchColumn();
assertF34($totalTablas === 108, "1.6: Base de datos contiene exactamente 108 tablas oficiales");
assertF34($totalPermisos === 115, "1.7: Catálogo RBAC contiene exactamente 115 permisos atómicos oficiales");


// ------------------------------------------------------------------------------
// PREPARACIÓN DE CLIENTES DE PRUEBA (CLIENTE A y CLIENTE B PARA ANTI-IDOR)
// ------------------------------------------------------------------------------
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM `postproduccion_selecciones` WHERE `notas_cliente` LIKE '%TEST-F34%'");
$pdo->exec("DELETE FROM `consentimientos_cliente` WHERE `motivo` LIKE '%TEST-F34%'");
$pdo->exec("DELETE FROM `postproduccion_archivos` WHERE `nombre_original` LIKE 'test_f34_%'");
$pdo->exec("DELETE FROM `postproduccion_galerias` WHERE `titulo` LIKE 'Galería Test F34%'");
$pdo->exec("DELETE FROM `postproduccion_proyectos` WHERE `codigo_proyecto` LIKE 'PRY-TEST-F34%'");
$pdo->exec("DELETE FROM `recibos_caja` WHERE `correlativo` LIKE 'REC-TEST-F34%'");
$pdo->exec("DELETE FROM `pagos` WHERE `correlativo` LIKE 'PAG-TEST-F34%'");
$pdo->exec("DELETE FROM `venta_lineas` WHERE `concepto_codigo` LIKE 'CONC-TEST-F34%'");
$pdo->exec("DELETE FROM `ventas` WHERE `correlativo` LIKE 'VTA-TEST-F34%'");
$pdo->exec("DELETE FROM `clientes` WHERE `persona_id` IN (SELECT id FROM `personas` WHERE `numero_documento` IN ('44440001', '44440002'))");
$pdo->exec("DELETE FROM `personas` WHERE `numero_documento` IN ('44440001', '44440002')");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// Persona y Cliente A
$pdo->prepare("
    INSERT INTO `personas` (`organizacion_id`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `telefono_whatsapp`, `correo_electronico`, `direccion`)
    VALUES (:org, 1, '44440001', 'Orlando', 'Cliente Test A', '+51999888001', 'cliente_f34a@test.pe', 'Av. Floral 123 Puno')
")->execute(['org' => $orgId]);
$personaAId = (int) $pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO `clientes` (`organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`, `consentimiento_promocional`)
    VALUES (:org, :per, 'CLIENTE', 1, 0)
")->execute(['org' => $orgId, 'per' => $personaAId]);
$clienteAId = (int) $pdo->lastInsertId();

// Persona y Cliente B (Víctima / Tercero para comprobación Anti-IDOR)
$pdo->prepare("
    INSERT INTO `personas` (`organizacion_id`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `telefono_whatsapp`, `correo_electronico`, `direccion`)
    VALUES (:org, 1, '44440002', 'Beatriz', 'Cliente Test B', '+51999888002', 'cliente_f34b@test.pe', 'Jr. Lima 456 Puno')
")->execute(['org' => $orgId]);
$personaBId = (int) $pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO `clientes` (`organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`, `consentimiento_promocional`)
    VALUES (:org, :per, 'CLIENTE', 1, 1)
")->execute(['org' => $orgId, 'per' => $personaBId]);
$clienteBId = (int) $pdo->lastInsertId();


// ------------------------------------------------------------------------------
// BLOQUE 2: IDENTIDAD, PERFIL Y CONSENTIMIENTOS LEY 29733 (FASE 3.4B)
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 2: Identidad, Perfil y Consentimientos Ley 29733 ---\n";

// 2.1 Consulta de perfil
$perfilA = $fachada->obtenerPerfilCliente($orgId, $clienteAId);
assertF34(isset($perfilA['cliente'], $perfilA['persona']) && array_key_exists('edicion_activa', $perfilA), "2.1: Consulta de perfil retorna cliente, persona y festividad activa");
assertF34($perfilA['persona']['numero_documento'] === '44440001', "2.2: Documento de identidad corresponde a '44440001'");
assertF34($perfilA['cliente']['consentimiento_operativo'] === true, "2.3: Consentimiento operativo inicial es true");
assertF34($perfilA['cliente']['consentimiento_promocional'] === false, "2.4: Consentimiento promocional inicial es false");

// 2.2 Validación de WhatsApp obligatorio
$exWhatsappVacio = false;
try {
    $fachada->actualizarPerfilCliente($orgId, $clienteAId, ['telefono_whatsapp' => '']);
} catch (\InvalidArgumentException $e) {
    $exWhatsappVacio = true;
}
assertF34($exWhatsappVacio, "2.5: Falla controlada si teléfono WhatsApp se envía vacío");

// 2.3 Validación de formato de WhatsApp
$exWhatsappInvalido = false;
try {
    $fachada->actualizarPerfilCliente($orgId, $clienteAId, ['telefono_whatsapp' => 'abc']);
} catch (\InvalidArgumentException $e) {
    $exWhatsappInvalido = true;
}
assertF34($exWhatsappInvalido, "2.6: Falla controlada si formato de WhatsApp es inválido");

// 2.4 Validación de unicidad de WhatsApp en el tenant
$exWhatsappDuplicado = false;
try {
    // Intentar asignar el WhatsApp del Cliente B (+51999888002) al Cliente A
    $fachada->actualizarPerfilCliente($orgId, $clienteAId, ['telefono_whatsapp' => '+51999888002']);
} catch (\InvalidArgumentException $e) {
    $exWhatsappDuplicado = true;
}
assertF34($exWhatsappDuplicado, "2.7: Falla controlada si el número de WhatsApp ya pertenece a otro cliente del tenant");

// 2.5 Actualización legítima de perfil y cambio de consentimientos Ley 29733
$perfilActualizado = $fachada->actualizarPerfilCliente($orgId, $clienteAId, [
    'telefono_whatsapp'          => '+51999888999',
    'correo_electronico'         => 'nuevo_correo_a@test.pe',
    'consentimiento_operativo'   => true,
    'consentimiento_promocional' => true, // Cambia de false a true
]);
assertF34($perfilActualizado['persona']['telefono_whatsapp'] === '+51999888999', "2.8: WhatsApp actualizado exitosamente en perfil");
assertF34($perfilActualizado['persona']['correo_electronico'] === 'nuevo_correo_a@test.pe', "2.9: Correo electrónico actualizado exitosamente");
assertF34($perfilActualizado['cliente']['consentimiento_promocional'] === true, "2.10: Consentimiento promocional actualizado a true en cliente");

// 2.6 Trazabilidad inmutable en consentimientos_cliente
$stmtLogCons = $pdo->prepare("
    SELECT tipo, accion, canal, actor_tipo, actor_sistema_id
    FROM `consentimientos_cliente`
    WHERE `cliente_id` = :cli AND `organizacion_id` = :org
    ORDER BY id DESC LIMIT 1
");
$stmtLogCons->execute(['cli' => $clienteAId, 'org' => $orgId]);
$logCons = $stmtLogCons->fetch(PDO::FETCH_ASSOC);
assertF34($logCons !== false, "2.11: Bitácora 'consentimientos_cliente' registra evento append-only");
assertF34($logCons['tipo'] === 'PROMOCIONAL' && $logCons['accion'] === 'OTORGAR', "2.12: Tipo PROMOCIONAL y acción OTORGAR registrados");
assertF34($logCons['actor_tipo'] === 'SISTEMA' && (int) $logCons['actor_sistema_id'] === 1, "2.13: Actor SISTEMA (id 1) registrado sin usuario humano");


// ------------------------------------------------------------------------------
// BLOQUE 3: ESTADO DE CUENTA, SALDOS Y PAGOS (FASE 3.4C)
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 3: Estado de Cuenta, Saldos y Pagos ---\n";

// Obtener o crear item comercial y oferta para la prueba
$stmtIc = $pdo->prepare("SELECT id FROM `items_comerciales` WHERE organizacion_id = :org LIMIT 1");
$stmtIc->execute([':org' => $orgId]);
$itemId = (int) $stmtIc->fetchColumn();
if (!$itemId) {
    $pdo->prepare("
        INSERT INTO `items_comerciales` (organizacion_id, codigo, nombre, tipo_item, precio_base, estado)
        VALUES (:org, 'ITEM-TEST-F34', 'Paquete Cobertura Fotográfica', 'PAQUETE_SERVICIOS', 1200.00, 'ACTIVO')
    ")->execute([':org' => $orgId]);
    $itemId = (int) $pdo->lastInsertId();
}

$stmtOf = $pdo->prepare("SELECT id FROM `ofertas_items_edicion` WHERE organizacion_id = :org AND edicion_id = :ed AND item_comercial_id = :item LIMIT 1");
$stmtOf->execute([':org' => $orgId, ':ed' => $edicionId, ':item' => $itemId]);
$ofertaItemId = (int) $stmtOf->fetchColumn();
if (!$ofertaItemId) {
    $pdo->prepare("
        INSERT INTO `ofertas_items_edicion` (organizacion_id, edicion_id, item_comercial_id, estado)
        VALUES (:org, :ed, :item, 'ACTIVO')
    ")->execute([':org' => $orgId, ':ed' => $edicionId, ':item' => $itemId]);
    $ofertaItemId = (int) $pdo->lastInsertId();
}

// Crear Venta 1 para Cliente A: Total S/ 1,200.00
$pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `origen_tipo`,
        `correlativo`, `fecha_venta`, `estado`, `cliente_nombre_completo`,
        `moneda`, `subtotal`, `descuento_global_monto`, `total`, `monto_pagado`, `saldo_pendiente`,
        `estado_financiero`, `creado_por`
    ) VALUES (
        :org, :ed, :cli, NULL, 'DIRECTA',
        'VTA-TEST-F34-001', CURDATE(), 'CONFIRMADA', 'Orlando Cliente Test A',
        'PEN', 1200.00, 0.00, 1200.00, 800.00, 400.00,
        'PAGO_PARCIAL', :usr
    )
")->execute([
    'org' => $orgId, 'ed' => $edicionId, 'cli' => $clienteAId, 'usr' => $usuarioAdminId
]);
$ventaAId = (int) $pdo->lastInsertId();

// Línea de venta con item y oferta vinculados (conforme a chk_venta_lineas_tipo)
$pdo->prepare("
    INSERT INTO `venta_lineas` (
        `venta_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `concepto_descripcion`,
        `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`
    ) VALUES (
        :vta, 'ITEM', :item, :of, 'CONC-TEST-F34-01', 'Paquete Cobertura Fotográfica Oficial', 'Fotos y Video de Danza',
        'SERVICIO', 1.00, 1200.00, 1200.00, 'PEN'
    )
")->execute([
    'vta'  => $ventaAId,
    'item' => $itemId,
    'of'   => $ofertaItemId
]);

// Pago 1: S/ 800.00 (APROBADO)
$pdo->prepare("
    INSERT INTO `pagos` (
        `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`,
        `estado`, `moneda`, `monto_cobrado_cliente`, `monto_neto_recibido`, `monto_aplicado_venta`,
        `fecha_pago`, `notas_operativas`, `creado_por`, `verificado_por`, `verificado_en`
    ) VALUES (
        :org, :ed, :vta, 'PAG-TEST-F34-001', 'TRANSFERENCIA_BANCARIA',
        'APROBADO', 'PEN', 800.00, 800.00, 800.00,
        NOW(), 'TEST-F34 Depósito verificado', :usr, :verif, NOW()
    )
")->execute([
    'org'   => $orgId,
    'ed'    => $edicionId,
    'vta'   => $ventaAId,
    'usr'   => $usuarioAdminId,
    'verif' => $usuarioAdminId
]);
$pagoA1Id = (int) $pdo->lastInsertId();

// Pago 2: S/ 400.00 (PENDIENTE_VERIFICACION)
$pdo->prepare("
    INSERT INTO `pagos` (
        `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`,
        `estado`, `moneda`, `monto_cobrado_cliente`, `monto_neto_recibido`, `monto_aplicado_venta`,
        `fecha_pago`, `notas_operativas`, `creado_por`
    ) VALUES (
        :org, :ed, :vta, 'PAG-TEST-F34-002', 'TRANSFERENCIA_BANCARIA',
        'PENDIENTE_VERIFICACION', 'PEN', 400.00, 400.00, 400.00,
        NOW(), 'TEST-F34 Voucher en revisión', :usr
    )
")->execute([
    'org' => $orgId, 'ed' => $edicionId, 'vta' => $ventaAId, 'usr' => $usuarioAdminId
]);
$pagoA2Id = (int) $pdo->lastInsertId();

// 3.1 Consulta de estado de cuenta
$estadoCuentaA = $fachada->obtenerEstadoCuentaCliente($orgId, $clienteAId);
assertF34(isset($estadoCuentaA['resumen'], $estadoCuentaA['ventas']), "3.1: Consulta de estado de cuenta retorna estructura canónica");
assertF34((float) $estadoCuentaA['resumen']['total_contratado'] === 1200.00, "3.2: Total contratado es S/ 1,200.00 calculado en backend");
assertF34((float) $estadoCuentaA['resumen']['total_pagado'] === 800.00, "3.3: Total pagado verificado es S/ 800.00");
assertF34((float) $estadoCuentaA['resumen']['saldo_pendiente_total'] === 400.00, "3.4: Saldo pendiente total consolidado es S/ 400.00");
assertF34($estadoCuentaA['resumen']['estado_financiero'] === 'PAGADA_PARCIAL', "3.5: Estado financiero general es 'PAGADA_PARCIAL'");
assertF34(count($estadoCuentaA['ventas']) === 1, "3.6: Detalla exactamente 1 venta asociada");
assertF34(count($estadoCuentaA['ventas'][0]['pagos']) === 2, "3.7: Detalla los 2 pagos registrados");

// 3.2 Emisión y descarga de Recibo de Caja PDF para Pago 1 (APROBADO)
$motorPdf = new \Aplicacion\Documentos\MotorPdfServicio();
$reciboSvc = new \Aplicacion\Documentos\ReciboCajaServicio($motorPdf, $pdo);
$reciboPdf = $reciboSvc->generarRecibo($orgId, $pagoA1Id, $usuarioAdminId);
assertF34(!empty($reciboPdf['binario_pdf']), "3.8: Generación de binario PDF de Recibo de Caja exitosa");
assertF34(str_starts_with($reciboPdf['correlativo'], 'REC-'), "3.9: Correlativo de recibo formato REC- emitido");
assertF34(str_contains($reciboPdf['binario_pdf'], '%PDF-'), "3.10: Cabecera PDF válida en el recibo de caja");

// 3.3 Rechazo de emisión de recibo para pago en PENDIENTE_VERIFICACION
$exReciboPendiente = false;
try {
    $reciboSvc->generarRecibo($orgId, $pagoA2Id, $usuarioAdminId);
} catch (\InvalidArgumentException $e) {
    $exReciboPendiente = true;
}
assertF34($exReciboPendiente, "3.11: Falla controlada si se solicita recibo de caja para un pago no aprobado");

// 3.4 Anti-IDOR en Estado de Cuenta: Cliente B no ve las ventas del Cliente A
$estadoCuentaB = $fachada->obtenerEstadoCuentaCliente($orgId, $clienteBId);
assertF34($estadoCuentaB['resumen']['total_contratado'] === 0.0, "3.12: Anti-IDOR: Cliente B no ve importes ni contratos del Cliente A");
assertF34(empty($estadoCuentaB['ventas']), "3.13: Anti-IDOR: Lista de ventas del Cliente B está vacía");


// ------------------------------------------------------------------------------
// BLOQUE 4: FOTOGRAFÍAS Y SELECCIÓN PERSISTENTE (FASE 3.4D)
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 4: Fotografías y Selección Persistente ---\n";

// Crear Proyecto para Cliente A (EN_EDICION)
$pdo->prepare("
    INSERT INTO `postproduccion_proyectos` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `codigo_proyecto`, `titulo`, `descripcion`,
        `estado`, `fecha_inicio_edicion`, `total_archivos`, `peso_total_bytes`
    ) VALUES (
        :org, :ed, :cli, 'PRY-TEST-F34-001', 'Cobertura Morenada Central Test A', 'Fotografías oficiales',
        'EN_EDICION', CURDATE(), 2, 2048000
    )
")->execute(['org' => $orgId, 'ed' => $edicionId, 'cli' => $clienteAId]);
$proyectoAId = (int) $pdo->lastInsertId();

// Galería pública
$pdo->prepare("
    INSERT INTO `postproduccion_galerias` (`organizacion_id`, `proyecto_id`, `titulo`, `slug`, `es_publica_cliente`, `orden`)
    VALUES (:org, :pry, 'Galería Test F34 - Pasacalle', 'galeria-test-f34-pasacalle', 1, 1)
")->execute(['org' => $orgId, 'pry' => $proyectoAId]);
$galeriaAId = (int) $pdo->lastInsertId();

// Dos archivos fotográficos
$stmtInsArch = $pdo->prepare("
    INSERT INTO `postproduccion_archivos` (
        `organizacion_id`, `proyecto_id`, `galeria_id`, `nombre_original`, `nombre_almacenamiento`, `ruta_privada`,
        `tipo_archivo`, `mime_type`, `peso_bytes`, `ancho_px`, `alto_px`, `checksum_sha256`, `estado`
    ) VALUES 
    (:org, :pry, :gal, :nombre, :almacen, :ruta, 'FOTO', 'image/jpeg', 1024000, 1920, 1080, :hash, 'ACTIVO')
");
$stmtInsArch->execute([
    'org'     => $orgId,
    'pry'     => $proyectoAId,
    'gal'     => $galeriaAId,
    'nombre'  => 'test_f34_foto1.jpg',
    'almacen' => 'f34_1.jpg',
    'ruta'    => 'privado/f34_1.jpg',
    'hash'    => 'hash1'
]);
$stmtInsArch->execute([
    'org'     => $orgId,
    'pry'     => $proyectoAId,
    'gal'     => $galeriaAId,
    'nombre'  => 'test_f34_foto2.jpg',
    'almacen' => 'f34_2.jpg',
    'ruta'    => 'privado/f34_2.jpg',
    'hash'    => 'hash2'
]);

$archivosGal = $pdo->query("SELECT id FROM `postproduccion_archivos` WHERE galeria_id = {$galeriaAId} ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
$foto1Id = (int) $archivosGal[0];
$foto2Id = (int) $archivosGal[1];

// 4.1 Consulta de fotos de la galería para Cliente A
$fotosGal = $postSvc->obtenerFotosGaleriaCliente($orgId, $galeriaAId, $clienteAId);
assertF34($fotosGal['total_fotos'] === 2, "4.1: Galería retorna las 2 fotos del proyecto");
assertF34($fotosGal['total_seleccionadas'] === 0, "4.2: Inicialmente 0 fotos seleccionadas");
assertF34($fotosGal['fotos'][0]['es_seleccionada'] === false, "4.3: Foto 1 no seleccionada inicialmente");
assertF34($fotosGal['fotos'][0]['descarga_permitida'] === false, "4.4: Descarga de original bloqueada mientras estado sea EN_EDICION");

// 4.2 Marcación de selección para Foto 1
$selRes1 = $postSvc->guardarSeleccionFotoCliente($orgId, $galeriaAId, $foto1Id, $clienteAId, true, 'TEST-F34 Me encanta esta toma');
assertF34($selRes1['es_seleccionada'] === true, "4.5: Foto 1 marcada como seleccionada");
assertF34($selRes1['estado'] === 'SELECCIONADA', "4.6: Estado 'SELECCIONADA' en respuesta");

// 4.3 Persistencia en base de datos de la selección
$stmtSelDb = $pdo->prepare("SELECT estado, notas_cliente FROM `postproduccion_selecciones` WHERE `cliente_id` = :cli AND `archivo_id` = :arch");
$stmtSelDb->execute(['cli' => $clienteAId, 'arch' => $foto1Id]);
$selDb = $stmtSelDb->fetch(PDO::FETCH_ASSOC);
assertF34($selDb !== false && $selDb['estado'] === 'SELECCIONADA', "4.7: Persistencia confirmada en tabla 'postproduccion_selecciones'");
assertF34(str_contains($selDb['notas_cliente'], 'Me encanta esta toma'), "4.8: Notas del cliente persistidas correctamente");

// 4.4 Reconsulta de galería refleja selección actualizada
$fotosGal2 = $postSvc->obtenerFotosGaleriaCliente($orgId, $galeriaAId, $clienteAId);
assertF34($fotosGal2['total_seleccionadas'] === 1, "4.9: Conteo actualizado a 1 foto seleccionada");
assertF34($fotosGal2['fotos'][0]['es_seleccionada'] === true, "4.10: Foto 1 ahora figura con es_seleccionada = true");

// 4.5 Deselección idempotente (toggle off)
$postSvc->guardarSeleccionFotoCliente($orgId, $galeriaAId, $foto1Id, $clienteAId, false);
$stmtSelDbOff = $pdo->prepare("SELECT estado FROM `postproduccion_selecciones` WHERE `cliente_id` = :cli AND `archivo_id` = :arch");
$stmtSelDbOff->execute(['cli' => $clienteAId, 'arch' => $foto1Id]);
assertF34($stmtSelDbOff->fetchColumn() === 'DESCARTADA', "4.11: Deselección transiciona estado a 'DESCARTADA'");

// Volver a marcar para probar listado global
$postSvc->guardarSeleccionFotoCliente($orgId, $galeriaAId, $foto1Id, $clienteAId, true, 'TEST-F34 Seleccionada definitiva');

// 4.6 Listado global de mis selecciones
$misSelecciones = $postSvc->listarMisSeleccionesCliente($orgId, $clienteAId);
assertF34(count($misSelecciones) === 1, "4.12: Listado global retorna exactamente 1 fotografía favorita");
assertF34($misSelecciones[0]['archivo_id'] === $foto1Id, "4.13: Archivo ID coincide con foto 1");
assertF34($misSelecciones[0]['codigo_proyecto'] === 'PRY-TEST-F34-001', "4.14: Contexto de proyecto PRY-TEST-F34-001 incluido");

// 4.7 Anti-IDOR en selección: Cliente B no puede marcar fotos del Cliente A
$exIdorSeleccion = false;
try {
    $postSvc->guardarSeleccionFotoCliente($orgId, $galeriaAId, $foto1Id, $clienteBId, true);
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exIdorSeleccion = true;
}
assertF34($exIdorSeleccion, "4.15: Anti-IDOR: Cliente B es rechazado al intentar seleccionar foto de la galería del Cliente A");

// 4.8 Anti-IDOR en visualización de galería: Cliente B no puede listar fotos de la galería del Cliente A
$exIdorGaleria = false;
try {
    $postSvc->obtenerFotosGaleriaCliente($orgId, $galeriaAId, $clienteBId);
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exIdorGaleria = true;
}
assertF34($exIdorGaleria, "4.16: Anti-IDOR: Cliente B es rechazado al intentar ver fotos de galería ajena");

// 4.9 Habilitación de descarga al pasar proyecto a LISTO_ENTREGA con hito formal publicado
$pdo->prepare("UPDATE `postproduccion_proyectos` SET `estado` = 'LISTO_ENTREGA' WHERE `id` = :id")->execute(['id' => $proyectoAId]);
$stmtEntPrueba = $pdo->prepare("
    INSERT INTO `postproduccion_entregas` (
        `organizacion_id`, `proyecto_id`, `numero_entrega`, `titulo`, `tipo_entrega`, `estado_entrega`, `entregado_en`
    ) VALUES (
        :org_id, :pry_id, 1, 'Entrega Final Oficial', 'FINAL', 'PUBLICADA', NOW()
    )
");
$stmtEntPrueba->execute(['org_id' => $orgId, 'pry_id' => $proyectoAId]);

$fotosGalListo = $postSvc->obtenerFotosGaleriaCliente($orgId, $galeriaAId, $clienteAId);
assertF34($fotosGalListo['fotos'][0]['descarga_permitida'] === true, "4.17: Descarga de original se habilita al transicionar proyecto a LISTO_ENTREGA con entrega publicada");


// ------------------------------------------------------------------------------
// BLOQUE 5: ENRUTAMIENTO Y SEGURIDAD FAIL-CLOSED EN CONTROLADOR
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 5: Rutas y Seguridad Fail-Closed en Controlador ---\n";

require_once __DIR__ . '/../rutas/api.php';

// 5.1 Verificación de rutas registradas en Enrutador
$rutasRegistradas = \Nucleo\Enrutamiento\Enrutador::obtenerRutas();
$rutasGet = [];
$rutasPut = [];
$rutasPost = [];

foreach ($rutasRegistradas as $r) {
    if ($r['metodo'] === 'GET') {
        $rutasGet[$r['patron']] = true;
    } elseif ($r['metodo'] === 'PUT') {
        $rutasPut[$r['patron']] = true;
    } elseif ($r['metodo'] === 'POST') {
        $rutasPost[$r['patron']] = true;
    }
}

assertF34(isset($rutasGet['/api/v1/publico/portal/perfil']), "5.1: Ruta GET /api/v1/publico/portal/perfil registrada");
assertF34(isset($rutasPut['/api/v1/publico/portal/perfil']), "5.2: Ruta PUT /api/v1/publico/portal/perfil registrada");
assertF34(isset($rutasGet['/api/v1/publico/portal/estado-cuenta']), "5.3: Ruta GET /api/v1/publico/portal/estado-cuenta registrada");
assertF34(isset($rutasGet['/api/v1/publico/portal/recibo/{id}']), "5.4: Ruta GET /api/v1/publico/portal/recibo/{id} registrada");
assertF34(isset($rutasGet['/api/v1/publico/portal/galerias/{id}/fotos']), "5.5: Ruta GET /api/v1/publico/portal/galerias/{id}/fotos registrada");
assertF34(isset($rutasPost['/api/v1/publico/portal/galerias/{id}/seleccionar']), "5.6: Ruta POST /api/v1/publico/portal/galerias/{id}/seleccionar registrada");
assertF34(isset($rutasGet['/api/v1/publico/portal/mis-selecciones']), "5.7: Ruta GET /api/v1/publico/portal/mis-selecciones registrada");

// 5.2 Fail-closed: Acceso anónimo a miPerfil es rechazado
$controlador = new \Aplicacion\Controladores\PortalPublicoControlador($fachada);
$exAnonimoPerfil = false;
try {
    $_COOKIE = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    // Se invoca mediante reflexión o directamente esperando la excepción
    $refMethod = new ReflectionMethod($controlador, 'resolverSesionCliente');
    $refMethod->setAccessible(true);
    $refMethod->invoke($controlador);
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exAnonimoPerfil = true;
}
assertF34($exAnonimoPerfil, "5.8: Fail-closed: Solicitud sin cookie de sesión rechaza acceso al portal");


// ------------------------------------------------------------------------------
// LIMPIEZA FINAL DE FIXTURES DE PRUEBA
// ------------------------------------------------------------------------------
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM `postproduccion_selecciones` WHERE `cliente_id` IN ({$clienteAId}, {$clienteBId})");
$pdo->exec("DELETE FROM `consentimientos_cliente` WHERE `cliente_id` IN ({$clienteAId}, {$clienteBId})");
$pdo->exec("DELETE FROM `postproduccion_archivos` WHERE `galeria_id` = {$galeriaAId}");
$pdo->exec("DELETE FROM `postproduccion_galerias` WHERE `id` = {$galeriaAId}");
$pdo->exec("DELETE FROM `postproduccion_entregas` WHERE `proyecto_id` = {$proyectoAId}");
$pdo->exec("DELETE FROM `postproduccion_proyectos` WHERE `id` = {$proyectoAId}");
$pdo->exec("DELETE FROM `recibos_caja` WHERE `id` = {$reciboPdf['recibo_id']}");
$pdo->exec("DELETE FROM `pagos` WHERE `venta_id` = {$ventaAId}");
$pdo->exec("DELETE FROM `venta_lineas` WHERE `venta_id` = {$ventaAId}");
$pdo->exec("DELETE FROM `ventas` WHERE `id` = {$ventaAId}");
$pdo->exec("DELETE FROM `clientes` WHERE `id` IN ({$clienteAId}, {$clienteBId})");
$pdo->exec("DELETE FROM `personas` WHERE `id` IN ({$personaAId}, {$personaBId})");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");


// ------------------------------------------------------------------------------
// BALANCE FINAL DE LA SUITE FASE 3.4
// ------------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo sprintf("TOTAL PRUEBAS FASE 3.4: %d | EXITOSAS: %d | FALLIDAS: %d\n", $totalPruebas, $pruebasPasadas, $pruebasFallidas);
echo "TOTAL: {$pruebasPasadas} PASS / {$pruebasFallidas} FAIL\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
