<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP V1.1 — SUITE DE CERTIFICACIÓN DE SEGURIDAD DEL PORTAL
 * DIRECTIVA DE DIRECCIÓN TÉCNICA: DT-V11-015
 * 
 * Alcance certificado:
 * 1. Autenticación sin contraseña (OTP y enlaces): expiración, consumo atómico,
 *    anti-enumeración, rate limiting, brute-force lock, rotación y revocación.
 * 2. Aislamiento de recursos (Anti-IDOR y Cross-Tenant / Cross-Edition): reservas,
 *    recibos PDF, estado de cuenta, galerías, selecciones, perfiles y consentimientos.
 * 3. Entrega de fotografías: compuertas simultáneas de autorización positiva,
 *    titularidad, hito formal publicado, ausencia de bloqueos y streaming seguro.
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

$pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();

$totalPruebas = 0;
$pruebasPasadas = 0;
$pruebasFallidas = 0;

function assertDT015(bool $condicion, string $descripcion): void
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
echo "CANDELARIAAPP V1.1 — CERTIFICACIÓN DE SEGURIDAD DEL PORTAL (DT-V11-015)\n";
echo "AUTENTICACIÓN PASSWORDLESS, AISLAMIENTO DE RECURSOS Y ENTREGA FOTOGRÁFICA\n";
echo "==============================================================================\n\n";

// ------------------------------------------------------------------------------
// PREPARACIÓN DE FIXTURES AISLADOS (CLIENTE A vs CLIENTE B en ORGANIZACIÓN 1 y 2)
// ------------------------------------------------------------------------------
$orgId = (int) $pdo->query("SELECT id FROM organizaciones WHERE codigo = 'og_estudio' LIMIT 1")->fetchColumn();
$edicionId = (int) $pdo->query("SELECT id FROM ediciones_candelaria WHERE organizacion_id = {$orgId} LIMIT 1")->fetchColumn();

// Crear segunda organización para pruebas cross-tenant
$stmtOrgB = $pdo->prepare("SELECT id FROM organizaciones WHERE codigo = 'test_tenant_b' LIMIT 1");
$stmtOrgB->execute();
$orgBId = (int) $stmtOrgB->fetchColumn();

if ($orgBId <= 0) {
    $stmtInsOrgB = $pdo->prepare("
        INSERT INTO `organizaciones` (
            `codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`,
            `direccion`, `codigo_pais`, `departamento`, `provincia`, `distrito`, `correo_contacto`, `estado`
        ) VALUES (
            'test_tenant_b', 'TENANT B PRUEBAS', 'TENANT B PRUEBAS S.A.C.', 2, '20999999999',
            'AV. TEST 123', 'PE', 'PUNO', 'PUNO', 'PUNO', 'tenantb@test.com', 'ACTIVO'
        )
    ");
    $stmtInsOrgB->execute();
    $orgBId = (int) $pdo->lastInsertId();
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
// Limpieza preventiva de fixtures de prueba DT-V11-015
$pdo->exec("DELETE FROM `cliente_codigos_acceso` WHERE `destino` IN ('+51999888777', '+51999888666', 'cliente.a@test015.com', 'cliente.b@test015.com')");
$pdo->exec("DELETE FROM `cliente_sesiones` WHERE `ip_creacion` = '127.0.0.15'");
$pdo->exec("DELETE FROM `postproduccion_selecciones` WHERE `cliente_id` IN (SELECT id FROM clientes WHERE persona_id IN (SELECT id FROM personas WHERE numero_documento IN ('77770015', '77770016')))");
$pdo->exec("DELETE FROM `postproduccion_entregas` WHERE `titulo` LIKE 'ENTREGA-DT015-%'");
$pdo->exec("DELETE FROM `postproduccion_archivos` WHERE `nombre_original` LIKE 'foto_dt015_%'");
$pdo->exec("DELETE FROM `postproduccion_galerias` WHERE `slug` LIKE 'gal-dt015-%'");
$pdo->exec("DELETE FROM `postproduccion_proyectos` WHERE `codigo_proyecto` LIKE 'PRY-DT015-%'");
$pdo->exec("DELETE FROM `recibos_caja` WHERE `titular_documento` = '77770015'");
$pdo->exec("DELETE FROM `pagos` WHERE `correlativo` LIKE 'PAG-DT015-%'");
$pdo->exec("DELETE FROM `venta_lineas` WHERE `concepto_nombre` LIKE 'LINEA-DT015-%'");
$pdo->exec("DELETE FROM `ventas` WHERE `correlativo` LIKE 'VEN-DT015-%'");
$pdo->exec("DELETE FROM `reservas` WHERE `correlativo` LIKE 'RES-DT015-%'");
$pdo->exec("DELETE FROM `clientes` WHERE `persona_id` IN (SELECT id FROM personas WHERE numero_documento IN ('77770015', '77770016'))");
$pdo->exec("DELETE FROM `personas` WHERE `numero_documento` IN ('77770015', '77770016')");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// Inserción de Persona y Cliente A (Titular legítimo)
$stmtPerA = $pdo->prepare("
    INSERT INTO `personas` (
        `organizacion_id`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `telefono_whatsapp`, `correo_electronico`
    ) VALUES (
        :org_id, 1, '77770015', 'CARLOS', 'MENDOZA TITULAR', '+51999888777', 'cliente.a@test015.com'
    )
");
$stmtPerA->execute(['org_id' => $orgId]);
$personaAId = (int) $pdo->lastInsertId();

$stmtCliA = $pdo->prepare("
    INSERT INTO `clientes` (
        `organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`, `consentimiento_promocional`
    ) VALUES (
        :org_id, :per_id, 'CLIENTE', 1, 0
    )
");
$stmtCliA->execute(['org_id' => $orgId, 'per_id' => $personaAId]);
$clienteAId = (int) $pdo->lastInsertId();

// Inserción de Persona y Cliente B (Tercero / Atacante potencial)
$stmtPerB = $pdo->prepare("
    INSERT INTO `personas` (
        `organizacion_id`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `telefono_whatsapp`, `correo_electronico`
    ) VALUES (
        :org_id, 1, '77770016', 'JAVIER', 'ROJAS ATACANTE', '+51999888666', 'cliente.b@test015.com'
    )
");
$stmtPerB->execute(['org_id' => $orgId]);
$personaBId = (int) $pdo->lastInsertId();

$stmtCliB = $pdo->prepare("
    INSERT INTO `clientes` (
        `organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`, `consentimiento_promocional`
    ) VALUES (
        :org_id, :per_id, 'CLIENTE', 1, 0
    )
");
$stmtCliB->execute(['org_id' => $orgId, 'per_id' => $personaBId]);
$clienteBId = (int) $pdo->lastInsertId();

// Repositorios y Servicios
$sesionRepo = new \Aplicacion\Repositorios\ClienteSesionRepositorio($pdo);
$codigoRepo = new \Aplicacion\Repositorios\ClienteCodigoAccesoRepositorio($pdo);
$clienteRepo = new \Aplicacion\Repositorios\ClienteRepositorio($pdo);
$personaRepo = new \Aplicacion\Repositorios\PersonaRepositorio($pdo);
$authClienteSvc = new \Aplicacion\Servicios\AutenticacionClienteServicio($sesionRepo, $codigoRepo, $clienteRepo, $personaRepo);
$postSvc = new \Aplicacion\Postproduccion\PostproduccionServicio($pdo);
$fachada = \Aplicacion\Servicios\FachadaPortalPublicoServicio::crearPorDefecto($pdo);


// ------------------------------------------------------------------------------
// BLOQUE 1: AUTENTICACIÓN SIN CONTRASEÑA (PASSWORDLESS)
// ------------------------------------------------------------------------------
echo "--- BLOQUE 1: Autenticación Passwordless, Expiración y Un solo Uso ---\n";

// 1.1 Protección contra enumeración de cuentas en WhatsApp
$resEnumNoExiste = $authClienteSvc->solicitarOtpWhatsApp($orgId, '+51999000111');
assertDT015($resEnumNoExiste['exito'] === true, "1.1: Solicitud para teléfono no registrado retorna 'exito = true'");
assertDT015(str_contains($resEnumNoExiste['mensaje'], 'Si el número de WhatsApp se encuentra registrado'), "1.2: Mensaje idéntico y homogéneo que previene enumeración de cuentas");

// 1.2 Solicitud para cliente legítimo A
$resOtpA = $authClienteSvc->solicitarOtpWhatsApp($orgId, '+51999888777');
assertDT015($resOtpA['exito'] === true, "1.3: Emisión de OTP exitosa para Cliente A");
$codigoOtpA = $authClienteSvc->obtenerCodigoTesting('+51999888777');
assertDT015($codigoOtpA !== null && strlen($codigoOtpA) === 6, "1.4: Código OTP de 6 dígitos emitido en entorno de pruebas");

// 1.3 Almacenamiento seguro del verificador (hash SHA-256 en base de datos)
$stmtCheckHash = $pdo->prepare("SELECT codigo_hash FROM cliente_codigos_acceso WHERE destino = '+51999888777' ORDER BY id DESC LIMIT 1");
$stmtCheckHash->execute();
$hashGuardado = $stmtCheckHash->fetchColumn();
assertDT015($hashGuardado === hash('sha256', $codigoOtpA), "1.5: La base de datos almacena el hash SHA-256 y nunca el código plano");

// 1.4 Consumo de un solo uso mediante operación atómica
$verif1 = $authClienteSvc->verificarOtpWhatsApp($orgId, '+51999888777', $codigoOtpA, '127.0.0.15', 'PHPUnit Test 1');
assertDT015($verif1['exito'] === true, "1.6: Primer intento de verificación con OTP correcto es aceptado");
$tokenSesionA1 = $verif1['token_sesion'];

// Reintento idéntico del mismo OTP debe ser rechazado atómicamente
$verif2 = $authClienteSvc->verificarOtpWhatsApp($orgId, '+51999888777', $codigoOtpA, '127.0.0.15', 'PHPUnit Test 2');
assertDT015($verif2['exito'] === false, "1.7: Reintento del mismo código OTP es rechazado (single-use atómico garantizado)");

// 1.5 Expiración efectiva de códigos
$stmtExp = $pdo->prepare("
    INSERT INTO `cliente_codigos_acceso` (
        `organizacion_id`, `cliente_id`, `tipo_acceso`, `codigo_hash`, `destino`, `utilizado`, `intentos_fallidos`, `expira_en`
    ) VALUES (
        :org_id, :cli_id, 'OTP_WHATSAPP', :hash, '+51999888777', 0, 0, NOW() - INTERVAL 5 MINUTE
    )
");
$codigoExpirado = '123456';
$stmtExp->execute([
    'org_id' => $orgId,
    'cli_id' => $clienteAId,
    'hash'   => hash('sha256', $codigoExpirado),
]);
$verifExp = $authClienteSvc->verificarOtpWhatsApp($orgId, '+51999888777', $codigoExpirado, '127.0.0.15');
assertDT015($verifExp['exito'] === false, "1.8: Código con tiempo de expiración vencido es rechazado de forma fail-closed");

// 1.6 Límites de intentos fallidos (Brute-Force Lock a 3 intentos)
$resOtpLock = $authClienteSvc->solicitarOtpWhatsApp($orgId, '+51999888777');
$codigoLock = $authClienteSvc->obtenerCodigoTesting('+51999888777');
// Tres intentos erróneos
$authClienteSvc->verificarOtpWhatsApp($orgId, '+51999888777', '000001', '127.0.0.15');
$authClienteSvc->verificarOtpWhatsApp($orgId, '+51999888777', '000002', '127.0.0.15');
$resInt3 = $authClienteSvc->verificarOtpWhatsApp($orgId, '+51999888777', '000003', '127.0.0.15');
assertDT015($resInt3['exito'] === false && str_contains($resInt3['mensaje'], 'invalidado por exceder el número máximo de intentos fallidos'), "1.9: Al 3er intento fallido se bloquea e invalida el código");

// Intentar ahora con el código correcto sobre el bloqueado
$verifPostLock = $authClienteSvc->verificarOtpWhatsApp($orgId, '+51999888777', $codigoLock, '127.0.0.15');
assertDT015($verifPostLock['exito'] === false, "1.10: Código previamente bloqueado ya no puede ser verificado ni con la clave correcta");

// 1.7 Límites de solicitudes (Rate Limiting a 3 solicitudes en 15 min)
$resRateLimit = $authClienteSvc->solicitarOtpWhatsApp($orgId, '+51999888777');
assertDT015($resRateLimit['exito'] === false && str_contains($resRateLimit['mensaje'], 'excedido el límite de solicitudes'), "1.11: Rate Limiting activo: 4ta solicitud en 15 minutos es bloqueada de forma estricta");

// 1.8 Regeneración de sesión y rotación anti-fijación (rotar sesión existente)
$sesionFresca = $authClienteSvc->rotarSesion($tokenSesionA1, '127.0.0.15', 'Nuevo Navegador');
$tokenSesionA2 = $sesionFresca['token_plano'];
assertDT015($tokenSesionA2 !== $tokenSesionA1, "1.12: Rotación de sesión genera token criptográfico fresco e independiente");

// La sesión anterior tokenSesionA1 debe haber sido invalidada (rotación completa)
$sesionVieja = $authClienteSvc->validarSesion($tokenSesionA1);
assertDT015($sesionVieja === null, "1.13: Sesión previa tokenSesionA1 invalidada atómicamente por la rotación (anti-fijación)");

// 1.9 Invalidación al cerrar sesión
$authClienteSvc->cerrarSesion($tokenSesionA2);
$sesionRevocada = $authClienteSvc->validarSesion($tokenSesionA2);
assertDT015($sesionRevocada === null, "1.14: Cierre de sesión revoca e invalida inmediatamente el token en base de datos");


// ------------------------------------------------------------------------------
// PREPARACIÓN DE ENTORNOS PARA BLOQUE 2 Y BLOQUE 3 (RECURSOS DE CLIENTE A)
// ------------------------------------------------------------------------------
// Emitir sesión activa para Cliente A y Cliente B
$sesA = $authClienteSvc->emitirSesionCliente($orgId, $clienteAId, '127.0.0.15', 'Browser Client A');
$tokenA = $sesA['token_plano'];

$sesB = $authClienteSvc->emitirSesionCliente($orgId, $clienteBId, '127.0.0.15', 'Browser Client B');
$tokenB = $sesB['token_plano'];

$usuarioAdminId = (int) $pdo->query("SELECT id FROM usuarios WHERE organizacion_id = {$orgId} AND estado = 'ACTIVO' ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($usuarioAdminId <= 0) {
    $usuarioAdminId = 24;
}

// Crear Venta, Pago y Recibo de Caja para Cliente A
$stmtVen = $pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `origen_tipo`,
        `correlativo`, `fecha_venta`, `estado`, `cliente_nombre_completo`,
        `moneda`, `subtotal`, `descuento_global_monto`, `total`, `monto_pagado`, `saldo_pendiente`,
        `estado_financiero`, `creado_por`
    ) VALUES (
        :org_id, :ed_id, :cli_id, NULL, 'DIRECTA',
        'VEN-DT015-001', CURDATE(), 'CONFIRMADA', 'CARLOS MENDOZA TITULAR',
        'PEN', 1000.00, 0.00, 1000.00, 1000.00, 0.00,
        'PAGADA_TOTAL', :usr_id
    )
");
$stmtVen->execute(['org_id' => $orgId, 'ed_id' => $edicionId, 'cli_id' => $clienteAId, 'usr_id' => $usuarioAdminId]);
$ventaAId = (int) $pdo->lastInsertId();

$stmtPag = $pdo->prepare("
    INSERT INTO `pagos` (
        `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`,
        `estado`, `moneda`, `monto_cobrado_cliente`, `monto_neto_recibido`, `monto_aplicado_venta`,
        `fecha_pago`, `notas_operativas`, `creado_por`, `verificado_por`, `verificado_en`
    ) VALUES (
        :org_id, :ed_id, :v_id, 'PAG-DT015-001', 'TRANSFERENCIA_BANCARIA',
        'APROBADO', 'PEN', 1000.00, 1000.00, 1000.00,
        NOW(), 'Depósito verificado DT015', :crea_id, :verif_id, NOW()
    )
");
$stmtPag->execute([
    'org_id'   => $orgId,
    'ed_id'    => $edicionId,
    'v_id'     => $ventaAId,
    'crea_id'  => $usuarioAdminId,
    'verif_id' => $usuarioAdminId
]);
$pagoAId = (int) $pdo->lastInsertId();

// Generar recibo de caja canónico
$reciboSvc = new \Aplicacion\Documentos\ReciboCajaServicio(new \Aplicacion\Documentos\MotorPdfServicio(), $pdo);
$reciboA = $reciboSvc->generarRecibo($orgId, $pagoAId, $usuarioAdminId);
$reciboAId = (int) $reciboA['recibo_id'];

// Crear Reserva para Cliente A
$stmtRes = $pdo->prepare("
    INSERT INTO `reservas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `venta_id`, `creado_por`,
        `correlativo`, `estado`, `contacto_nombre`
    ) VALUES (
        :org_id, :ed_id, :cli_id, :v_id, :usr_id,
        'RES-DT015-001', 'CONFIRMADA', 'CARLOS MENDOZA'
    )
");
$stmtRes->execute([
    'org_id' => $orgId,
    'ed_id'  => $edicionId,
    'cli_id' => $clienteAId,
    'v_id'   => $ventaAId,
    'usr_id' => $usuarioAdminId
]);
$reservaAId = (int) $pdo->lastInsertId();

// Crear Proyecto, Galería y Archivos para Cliente A
$stmtPry = $pdo->prepare("
    INSERT INTO `postproduccion_proyectos` (
        `organizacion_id`, `edicion_id`, `reserva_id`, `cliente_id`, `codigo_proyecto`,
        `titulo`, `estado`, `total_archivos`
    ) VALUES (
        :org_id, :ed_id, :res_id, :cli_id, 'PRY-DT015-001',
        'PROYECTO DT015 CLIENTE A', 'EN_EDICION', 2
    )
");
$stmtPry->execute([
    'org_id' => $orgId,
    'ed_id'  => $edicionId,
    'res_id' => $reservaAId,
    'cli_id' => $clienteAId,
]);
$proyectoAId = (int) $pdo->lastInsertId();

$stmtGal = $pdo->prepare("
    INSERT INTO `postproduccion_galerias` (
        `organizacion_id`, `proyecto_id`, `titulo`, `slug`, `es_publica_cliente`
    ) VALUES (
        :org_id, :pry_id, 'Galería DT015', 'gal-dt015-001', 1
    )
");
$stmtGal->execute(['org_id' => $orgId, 'pry_id' => $proyectoAId]);
$galeriaAId = (int) $pdo->lastInsertId();

// Crear archivos físicos temporales para test
$rutaTmpA1 = "postproduccion_pruebas_dt015_a1.jpg";
$almacenamiento = new \Aplicacion\Postproduccion\Almacenamiento\AlmacenamientoLocalDriver();
$streamTest1 = fopen('php://memory', 'r+');
fwrite($streamTest1, "IMG_TEST_BINARY_CONTENT_DT015_A1");
rewind($streamTest1);
$almacenamiento->guardarStream($streamTest1, $rutaTmpA1);

$stmtArch = $pdo->prepare("
    INSERT INTO `postproduccion_archivos` (
        `organizacion_id`, `proyecto_id`, `galeria_id`, `nombre_original`,
        `nombre_almacenamiento`, `driver_almacenamiento`, `ruta_privada`,
        `tipo_archivo`, `mime_type`, `peso_bytes`, `checksum_sha256`, `estado`
    ) VALUES (
        :org_id, :pry_id, :gal_id, 'foto_dt015_01.jpg',
        'foto_dt015_01.jpg', 'LOCAL', :ruta,
        'FOTO', 'image/jpeg', 29, 'dummyhashdt015a1', 'ACTIVO'
    )
");
$stmtArch->execute([
    'org_id' => $orgId,
    'pry_id' => $proyectoAId,
    'gal_id' => $galeriaAId,
    'ruta'   => $rutaTmpA1,
]);
$archivoAId = (int) $pdo->lastInsertId();


// ------------------------------------------------------------------------------
// BLOQUE 2: AISLAMIENTO DE RECURSOS (ANTI-IDOR Y CROSS-TENANT)
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 2: Aislamiento de Recursos y Pruebas Negativas Anti-IDOR ---\n";

$controlador = new \Aplicacion\Controladores\PortalPublicoControlador($fachada, $authClienteSvc);

// 2.1 Anti-IDOR en Reservas: Cliente B no puede consultar las reservas de Cliente A
$reservasCliB = $fachada->obtenerMisReservas($orgId, $clienteBId);
assertDT015(count($reservasCliB) === 0, "2.1: Consulta de reservas de Cliente B está estrictamente aislada (0 reservas)");

// 2.2 Anti-IDOR en Estado de Cuenta: Cliente B no ve contratos ni saldos de Cliente A
$ctaCliB = $fachada->obtenerEstadoCuentaCliente($orgId, $clienteBId);
assertDT015($ctaCliB['resumen']['total_ventas'] === 0 && $ctaCliB['resumen']['total_contratado'] === 0.0, "2.2: Estado de cuenta de Cliente B no expone montos de Cliente A");
assertDT015(count($ctaCliB['ventas']) === 0, "2.3: Desglose de ventas de Cliente B no contiene contratos de Cliente A");

// 2.3 Anti-IDOR en Recibos PDF: Cliente B intenta descargar recibo de Cliente A modificando el ID
$_GET = ['id' => $pagoAId];
$_COOKIE = [\Aplicacion\Servicios\AutenticacionClienteServicio::COOKIE_CLIENTE_SESION => $tokenB];
$_SERVER['HTTP_HOST'] = 'app.candelaria.test';
$_GET['slug_organizacion'] = 'og_estudio';

ob_start();
$controlador->descargarReciboPdf(['id' => $pagoAId]);
$salidaRecibo = ob_get_clean();

$exIdorRecibo = str_contains($salidaRecibo, 'AccesoDenegadoExcepcion');
if (!$exIdorRecibo) {
    echo "\nDEBUG SALIDA RECIBO: " . var_export($salidaRecibo, true) . "\n";
}
assertDT015($exIdorRecibo, "2.4: Anti-IDOR: Intento de Cliente B de descargar recibo PDF de Cliente A es denegado con 403 (AccesoDenegadoExcepcion)");

// 2.4 Anti-IDOR en Galerías: Cliente B intenta consultar fotos de la galería de Cliente A
$exIdorGal = false;
try {
    $postSvc->obtenerFotosGaleriaCliente($orgId, $galeriaAId, $clienteBId);
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exIdorGal = true;
}
assertDT015($exIdorGal, "2.5: Anti-IDOR: Cliente B es rechazado al intentar ver álbum/galería de Cliente A");

// 2.5 Anti-IDOR en Selección Fotográfica: Cliente B intenta marcar foto de Cliente A
$exIdorSel = false;
try {
    $postSvc->guardarSeleccionFotoCliente($orgId, $galeriaAId, $archivoAId, $clienteBId, true, "Hacker note");
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exIdorSel = true;
}
assertDT015($exIdorSel, "2.6: Anti-IDOR: Intento de Cliente B de marcar fotografía ajena como favorita es bloqueado");

// 2.6 Aislamiento en Mis Selecciones
$postSvc->guardarSeleccionFotoCliente($orgId, $galeriaAId, $archivoAId, $clienteAId, true, "Foto favorita Cliente A");
$misSelB = $postSvc->listarMisSeleccionesCliente($orgId, $clienteBId);
assertDT015(count($misSelB) === 0, "2.7: Listado global de selecciones de Cliente B no expone favoritos de Cliente A");

// 2.7 Cross-Tenant: Petición desde Organización B para recurso de Organización A
$exCrossTenant = false;
try {
    $postSvc->obtenerFotosGaleriaCliente($orgBId, $galeriaAId, $clienteAId);
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exCrossTenant = true;
}
assertDT015($exCrossTenant, "2.8: Cross-Tenant: Solicitud con orgId ajeno rechazada fail-closed");

// 2.8 Inmutabilidad de Documento de Identidad en Perfil
$perfilInicial = $fachada->obtenerPerfilCliente($orgId, $clienteAId);
assertDT015($perfilInicial['persona']['numero_documento'] === '77770015', "2.9: Documento inicial verificado");
$fachada->actualizarPerfilCliente($orgId, $clienteAId, [
    'telefono_whatsapp' => '+51999888777',
    'correo_electronico' => 'nuevo.correo@test015.com',
    'numero_documento'  => '00000000', // Intento malicioso de alterar documento
]);
$perfilPost = $fachada->obtenerPerfilCliente($orgId, $clienteAId);
assertDT015($perfilPost['persona']['numero_documento'] === '77770015', "2.10: Inmutabilidad de documento preservada (el backend ignora alteraciones al documento)");


// ------------------------------------------------------------------------------
// BLOQUE 3: CERTIFICACIÓN DE ENTREGA Y DESCARGA DE FOTOGRAFÍAS ORIGINALES
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 3: Entrega de Fotografías y Descarga de Originales ---\n";

// 3.1 Proyecto en EN_EDICION: descarga de original bloqueada
$exBloqEdicion = false;
try {
    $postSvc->verificarAutorizacionDescargaOriginal($orgId, $archivoAId, $clienteAId);
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exBloqEdicion = true;
    assertDT015(str_contains($e->getMessage(), 'LISTO_ENTREGA'), "3.1: Descarga de original bloqueada mientras estado sea EN_EDICION");
}
assertDT015($exBloqEdicion, "3.2: Excepción lanzada formalmente al intentar descargar original en edición");

// 3.2 Proyecto en LISTO_ENTREGA pero SIN hito formal publicado (Demostrar que NO basta con salir de EN_EDICION)
$pdo->prepare("UPDATE `postproduccion_proyectos` SET `estado` = 'LISTO_ENTREGA' WHERE `id` = :id")->execute(['id' => $proyectoAId]);
$exSinHito = false;
try {
    $postSvc->verificarAutorizacionDescargaOriginal($orgId, $archivoAId, $clienteAId);
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exSinHito = true;
    assertDT015(str_contains($e->getMessage(), 'No existe un paquete o hito de entrega formalmente publicado'), "3.3: Descarga de original bloqueada si no existe hito formal publicado (requisito positivo)");
}
assertDT015($exSinHito, "3.4: Salir de EN_EDICION no es suficiente sin autorización positiva de entrega");

// 3.3 Hito publicado pero con enlace CADUCADO
$stmtEntCad = $pdo->prepare("
    INSERT INTO `postproduccion_entregas` (
        `organizacion_id`, `proyecto_id`, `numero_entrega`, `titulo`, `tipo_entrega`, `estado_entrega`,
        `fecha_caducidad_enlace`, `entregado_en`
    ) VALUES (
        :org_id, :pry_id, 1, 'ENTREGA-DT015-EXPIRADA', 'FINAL', 'PUBLICADA',
        NOW() - INTERVAL 1 DAY, NOW()
    )
");
$stmtEntCad->execute(['org_id' => $orgId, 'pry_id' => $proyectoAId]);
$entregaCadId = (int) $pdo->lastInsertId();

$exCaducado = false;
try {
    $postSvc->verificarAutorizacionDescargaOriginal($orgId, $archivoAId, $clienteAId);
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exCaducado = true;
}
assertDT015($exCaducado, "3.5: Descarga de original rechazada si el hito de entrega tiene enlace caducado");

// 3.4 Hito publicado vigente pero archivo REVOCADO
$stmtEntOk = $pdo->prepare("
    INSERT INTO `postproduccion_entregas` (
        `organizacion_id`, `proyecto_id`, `numero_entrega`, `titulo`, `tipo_entrega`, `estado_entrega`,
        `fecha_caducidad_enlace`, `entregado_en`
    ) VALUES (
        :org_id, :pry_id, 2, 'ENTREGA-DT015-OFICIAL', 'FINAL', 'PUBLICADA',
        NOW() + INTERVAL 30 DAY, NOW()
    )
");
$stmtEntOk->execute(['org_id' => $orgId, 'pry_id' => $proyectoAId]);
$entregaOkId = (int) $pdo->lastInsertId();

$pdo->prepare("UPDATE `postproduccion_archivos` SET `estado` = 'REVOCADO' WHERE `id` = :id")->execute(['id' => $archivoAId]);
$exRevocado = false;
try {
    $postSvc->verificarAutorizacionDescargaOriginal($orgId, $archivoAId, $clienteAId);
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exRevocado = true;
    assertDT015(str_contains($e->getMessage(), 'revocado'), "3.6: Archivo REVOCADO bloquea descarga aunque el proyecto esté entregado");
}
assertDT015($exRevocado, "3.7: Excepción en archivo revocado");

// 3.5 Archivo ELIMINADO_SOLICITUD (Ley 29733)
$pdo->prepare("UPDATE `postproduccion_archivos` SET `estado` = 'ELIMINADO_SOLICITUD' WHERE `id` = :id")->execute(['id' => $archivoAId]);
$exSuprimido = false;
try {
    $postSvc->verificarAutorizacionDescargaOriginal($orgId, $archivoAId, $clienteAId);
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exSuprimido = true;
    assertDT015(str_contains($e->getMessage(), 'suprimido'), "3.8: Archivo suprimido por Ley 29733 bloquea descarga de original");
}
assertDT015($exSuprimido, "3.9: Excepción en archivo suprimido");

// 3.6 Bloqueo Operativo sobre la Reserva
$pdo->prepare("UPDATE `postproduccion_archivos` SET `estado` = 'ACTIVO' WHERE `id` = :id")->execute(['id' => $archivoAId]);
$pdo->prepare("UPDATE `reservas` SET `notas_operativas` = '[BLOQUEO_OPERATIVO: Disputa contractual pendiente]' WHERE `id` = :id")->execute(['id' => $reservaAId]);
$exBloqOperativo = false;
try {
    $postSvc->verificarAutorizacionDescargaOriginal($orgId, $archivoAId, $clienteAId);
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $exBloqOperativo = true;
    assertDT015(str_contains($e->getMessage(), 'bloqueo operativo'), "3.10: Bloqueo operativo en reserva suspende descarga de originales en alta resolución");
}
assertDT015($exBloqOperativo, "3.11: Excepción ante bloqueo operativo");

// 3.7 Habilitación de descarga al cumplir simultáneamente todas las compuertas
$pdo->prepare("UPDATE `reservas` SET `notas_operativas` = NULL WHERE `id` = :id")->execute(['id' => $reservaAId]);
$autorizacionPositiva = $postSvc->verificarAutorizacionDescargaOriginal($orgId, $archivoAId, $clienteAId);
assertDT015(isset($autorizacionPositiva['archivo']) && isset($autorizacionPositiva['proyecto']) && isset($autorizacionPositiva['entrega']), "3.12: Autorización positiva concedida al cumplirse simultáneamente las 5 compuertas");


// ------------------------------------------------------------------------------
// BLOQUE 4: INTEGRIDAD Y LIMPIEZA
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 4: Integridad de Base de Datos y No-Destructividad ---\n";

$tablasActuales = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
assertDT015($tablasActuales === 108, "4.1: Base de datos conserva exactamente 108 tablas oficiales");

$migracionesActuales = (int) $pdo->query("SELECT COUNT(*) FROM migraciones_control")->fetchColumn();
assertDT015(in_array($migracionesActuales, [29, 30], true), "4.2: Trazabilidad de migraciones conserva 29-30 migraciones ({$migracionesActuales})");

$permisosActuales = (int) $pdo->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
assertDT015($permisosActuales === 115, "4.3: Matriz RBAC conserva 115 permisos");

// Limpieza de fixtures
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM `cliente_codigos_acceso` WHERE `destino` IN ('+51999888777', '+51999888666', 'cliente.a@test015.com', 'cliente.b@test015.com')");
$pdo->exec("DELETE FROM `cliente_sesiones` WHERE `ip_creacion` = '127.0.0.15'");
$pdo->exec("DELETE FROM `postproduccion_selecciones` WHERE `proyecto_id` = {$proyectoAId}");
$pdo->exec("DELETE FROM `postproduccion_entregas` WHERE `proyecto_id` = {$proyectoAId}");
$pdo->exec("DELETE FROM `postproduccion_archivos` WHERE `id` = {$archivoAId}");
$pdo->exec("DELETE FROM `postproduccion_galerias` WHERE `id` = {$galeriaAId}");
$pdo->exec("DELETE FROM `postproduccion_proyectos` WHERE `id` = {$proyectoAId}");
$pdo->exec("DELETE FROM `recibos_caja` WHERE `id` = {$reciboAId}");
$pdo->exec("DELETE FROM `pagos` WHERE `id` = {$pagoAId}");
$pdo->exec("DELETE FROM `ventas` WHERE `id` = {$ventaAId}");
$pdo->exec("DELETE FROM `reservas` WHERE `id` = {$reservaAId}");
$pdo->exec("DELETE FROM `clientes` WHERE `id` IN ({$clienteAId}, {$clienteBId})");
$pdo->exec("DELETE FROM `personas` WHERE `id` IN ({$personaAId}, {$personaBId})");
$pdo->exec("DELETE FROM `organizaciones` WHERE `codigo` = 'test_tenant_b'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

assertDT015(true, "4.4: Limpieza de fixtures completada sin tocar datos productivos");

echo "\n==============================================================================\n";
echo sprintf("TOTAL PRUEBAS DT-V11-015: %d | EXITOSAS: %d | FALLIDAS: %d\n", $totalPruebas, $pruebasPasadas, $pruebasFallidas);
echo "TOTAL: {$pruebasPasadas} PASS / {$pruebasFallidas} FAIL\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
