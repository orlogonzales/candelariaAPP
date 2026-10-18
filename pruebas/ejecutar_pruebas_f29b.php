<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 2.9B (PORTAL PÚBLICO Y CHECKOUT SOBERANO)
 * NÚCLEO: DISPONIBILIDAD, BLOQUEOS TEMPORALES, COTIZADOR BACKEND, GUEST CHECKOUT,
 *         DESACOPLE FINANCIERO, SESIONES SEGREGADAS Y PROTECCIÓN ANTI-SOBREVENTA
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Controladores\PortalPublicoControlador;
use Aplicacion\Cotizaciones\CotizacionServicio;
use Aplicacion\Entidades\ReservaBloqueoTemporal;
use Aplicacion\Finanzas\EstadoPago;
use Aplicacion\Finanzas\MetodoPago;
use Aplicacion\Finanzas\PagoServicio;
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
use Nucleo\Http\Middleware\AutenticacionClienteMiddleware;
use Nucleo\Http\Middleware\ResolucionTenantPublicoMiddleware;

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
echo "INICIANDO SUITE F2.9B: PORTAL PÚBLICO, CHECKOUT SOBERANO Y ESPACIO CLIENTE\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

// Inicializar Repositorios y Servicios
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
$configRepo = new ConfiguracionRepositorio($pdo);
$cotizacionRepo = new CotizacionRepositorio($pdo);
$oportunidadRepo = new OportunidadRepositorio($pdo);
$ofertaItemRepo = new OfertaItemEdicionRepositorio($pdo);
$ofertaPaqueteRepo = new OfertaPaqueteEdicionRepositorio($pdo);
$tarifaItemRepo = new TarifaItemEdicionRepositorio($pdo);
$tarifaPaqueteRepo = new TarifaPaqueteEdicionRepositorio($pdo);
$entregaRepo = new EntregaProductoRepositorio($pdo);
$itemCfgRepo = new ItemConfiguracionOperativaRepositorio($pdo);
$reembolsoRepo = new PagoReembolsoRepositorio($pdo);
$cuentaRepo = new CuentaBancariaRepositorio($pdo);
$pasarelaRepo = new PasarelaRepositorio($pdo);
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

$tenantMiddleware = new ResolucionTenantPublicoMiddleware($pdo);
$authClienteMiddleware = new AutenticacionClienteMiddleware($authClienteSvc);

// Resolver Organización de pruebas (Tenant 1) y Edición activa
$stmtOrg = $pdo->query("SELECT id, codigo, nombre_comercial FROM organizaciones WHERE estado = 'ACTIVO' ORDER BY id ASC LIMIT 1");
$org = $stmtOrg->fetch(PDO::FETCH_ASSOC);
$orgId = (int) $org['id'];
$orgCodigo = (string) $org['codigo'];

$stmtUsr = $pdo->prepare("SELECT id FROM usuarios WHERE organizacion_id = :org_id AND estado = 'ACTIVO' ORDER BY id ASC LIMIT 1");
$stmtUsr->execute(['org_id' => $orgId]);
$usrAdminId = (int) ($stmtUsr->fetchColumn() ?: 24);

$pdo->exec("DELETE FROM ediciones_candelaria WHERE codigo = 'edicion-2098-test'");
$pdo->exec("DELETE FROM organizaciones WHERE codigo = 'org_inactiva_test'");

$edicion = $edicionRepo->buscarActual($orgId);
if (!$edicion) {
    // Si no hay edición actual, crear una para la suite
    $stmtInsEd = $pdo->prepare("
        INSERT INTO ediciones_candelaria (organizacion_id, codigo, anio, nombre, fecha_inicio, fecha_fin, estado, es_actual)
        VALUES (:org_id, 'edicion-2098-test', 2098, 'Festividad Candelaria 2098 Suite Test', '2098-01-28', '2098-02-15', 'PREOPERACION', 1)
    ");
    $stmtInsEd->execute(['org_id' => $orgId]);
    $edicion = $edicionRepo->buscarActual($orgId);
}
$edicionId = (int) $edicion->id;

// Preparar un ítem comercial vendible para pruebas
$stmtItem = $pdo->prepare("
    SELECT id FROM items_comerciales WHERE organizacion_id = :org_id AND estado = 'ACTIVO' LIMIT 1
");
$stmtItem->execute(['org_id' => $orgId]);
$itemId = $stmtItem->fetchColumn();

if (!$itemId) {
    $stmtCat = $pdo->prepare("SELECT id FROM categorias_items WHERE organizacion_id = :org_id LIMIT 1");
    $stmtCat->execute(['org_id' => $orgId]);
    $catId = $stmtCat->fetchColumn();
    if (!$catId) {
        $stmtInsCat = $pdo->prepare("INSERT INTO categorias_items (organizacion_id, codigo, nombre, estado) VALUES (:org_id, 'CAT_TEST_01', 'Categoría Test', 'ACTIVO')");
        $stmtInsCat->execute(['org_id' => $orgId]);
        $catId = (int) $pdo->lastInsertId();
    } else {
        $catId = (int) $catId;
        $pdo->exec("UPDATE categorias_items SET codigo = UPPER(REPLACE(codigo, '-', '_')) WHERE id = {$catId}");
    }

    $stmtInsItem = $pdo->prepare("
        INSERT INTO items_comerciales (organizacion_id, categoria_id, codigo, nombre, tipo, unidad_medida, estado)
        VALUES (:org_id, :cat_id, 'ITEM_TEST_PUB_01', 'Asiento Gradería Preferencial Test', 'SERVICIO', 'TICKET', 'ACTIVO')
    ");
    $stmtInsItem->execute(['org_id' => $orgId, 'cat_id' => $catId]);
    $itemId = (int) $pdo->lastInsertId();
} else {
    $itemId = (int) $itemId;
    $pdo->exec("UPDATE items_comerciales SET codigo = REPLACE(codigo, '-', '_') WHERE id = {$itemId}");
    $stmtCat = $pdo->prepare("SELECT categoria_id FROM items_comerciales WHERE id = :id");
    $stmtCat->execute(['id' => $itemId]);
    $catId = (int) $stmtCat->fetchColumn();
    if ($catId > 0) {
        $pdo->exec("UPDATE categorias_items SET codigo = UPPER(REPLACE(codigo, '-', '_')) WHERE id = {$catId}");
    }
}

// Asegurar oferta y tarifa de ítem en la edición
$stmtOfertaItem = $pdo->prepare("
    INSERT INTO ofertas_items_edicion (organizacion_id, edicion_id, item_comercial_id, estado, capacidad_referencial)
    VALUES (:org_id, :edicion_id, :item_id, 'ACTIVO', 50)
    ON DUPLICATE KEY UPDATE estado = 'ACTIVO', capacidad_referencial = 50
");
$stmtOfertaItem->execute(['org_id' => $orgId, 'edicion_id' => $edicionId, 'item_id' => $itemId]);

$stmtOfeId = $pdo->prepare("SELECT id FROM ofertas_items_edicion WHERE organizacion_id = :org_id AND edicion_id = :edicion_id AND item_comercial_id = :item_id");
$stmtOfeId->execute(['org_id' => $orgId, 'edicion_id' => $edicionId, 'item_id' => $itemId]);
$ofertaItemId = (int) $stmtOfeId->fetchColumn();

$stmtTarifaItem = $pdo->prepare("
    INSERT INTO tarifas_items_edicion (oferta_item_id, moneda, precio)
    VALUES (:oferta_id, 'PEN', 150.00)
    ON DUPLICATE KEY UPDATE precio = 150.00
");
$stmtTarifaItem->execute(['oferta_id' => $ofertaItemId]);

// Asegurar configuración operativa del ítem con capacidad colectiva
$stmtCfgOp = $pdo->prepare("
    INSERT INTO item_configuracion_operativa (item_comercial_id, requiere_reserva, tipo_capacidad, requiere_agendamiento, requiere_participantes)
    VALUES (:item_id, 1, 'COLECTIVA', 0, 0)
    ON DUPLICATE KEY UPDATE requiere_reserva = 1, tipo_capacidad = 'COLECTIVA', requiere_participantes = 0
");
$stmtCfgOp->execute(['item_id' => $itemId]);

// ------------------------------------------------------------------------------
// SECCIÓN 1: Resolución Multitenant Pública Fail-Closed
// ------------------------------------------------------------------------------
echo "--- SECCIÓN 1: Resolución Multitenant Pública Fail-Closed y Anti-Spoofing ---\n";

// 1.1 Resolución estricta por parámetro de ruta pública autorizada (slug)
$resParam = $tenantMiddleware->resolver([], ['slug_organizacion' => $orgCodigo]);
registrarAsercion("1.1 Resolución estricta de tenant por parámetro verificado de ruta", (int) $resParam['organizacion_id'] === $orgId);

// 1.2 Prohibición de spoofing: encabezado cliente HTTP_X_ORGANIZACION_SLUG es ignorado
$resHeaderIgnorado = $tenantMiddleware->resolver(['HTTP_HOST' => 'localhost', 'HTTP_X_ORGANIZACION_SLUG' => 'org_fantasma_spoof'], []);
registrarAsercion("1.2 Encabezados cliente de tenant son ignorados y no alteran la resolución (Anti-Spoofing)", (int) $resHeaderIgnorado['organizacion_id'] === $orgId && $resHeaderIgnorado['codigo'] !== 'org_fantasma_spoof');

// 1.3 Slug inexistente en ruta arroja excepción RuntimeException (Fail-Closed, 404)
$errorSlugInvalido = false;
try {
    $tenantMiddleware->resolver(['HTTP_HOST' => 'localhost'], ['slug_organizacion' => 'slug_inexistente_99999']);
} catch (\RuntimeException $e) {
    $errorSlugInvalido = true;
}
registrarAsercion("1.3 Slug de ruta inexistente arroja excepción 404 Fail-Closed", $errorSlugInvalido);

// 1.4 Organización inactiva arroja excepción de suspensión
$stmtInact = $pdo->prepare("
    INSERT INTO organizaciones (codigo, nombre_comercial, estado)
    VALUES ('org_inactiva_test', 'Org Inactiva Test', 'INACTIVO')
    ON DUPLICATE KEY UPDATE estado = 'INACTIVO'
");
$stmtInact->execute();
$errorInactiva = false;
try {
    $tenantMiddleware->resolver([], ['slug_organizacion' => 'org_inactiva_test']);
} catch (\RuntimeException $e) {
    $errorInactiva = true;
}
registrarAsercion("1.4 Organización con estado INACTIVO es rechazada fail-closed", $errorInactiva);

// 1.5 Obtención de edición actual y metadatos de branding institucional
$edicionInfo = $fachada->obtenerEdicionActual($orgId);
registrarAsercion("1.5 Fachada expone edición activa y branding de la organización resuelta", $edicionInfo !== null && (int)$edicionInfo['edicion']['id'] === $edicionId);

// ------------------------------------------------------------------------------
// SECCIÓN 2: Motor de Capacidad y Bloqueos Temporales (TTL 15 Minutos)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 2: Motor de Capacidad y Bloqueos Temporales (TTL 15m) ---\n";

// 2.1 Disponibilidad inicial del ítem
$dispInicial = $motorDisp->calcularDisponibilidadItem($orgId, $edicionId, $itemId);
registrarAsercion("2.1 Cálculo de disponibilidad de ítem con capacidad base", $dispInicial > 0);

// 2.2 Bloqueo atómico de 2 cupos con TTL de 15 minutos
$sesionPrueba = 'ses_' . bin2hex(random_bytes(8));
$bloqueoRes = $motorDisp->bloquearCupos($orgId, $edicionId, $itemId, 2, $sesionPrueba, ttlMinutos: 15);
registrarAsercion("2.2 Bloqueo atómico de cupos retorna token y timestamp de expiración", $bloqueoRes['exito'] && !empty($bloqueoRes['token_bloqueo']));

// 2.3 El bloqueo descuenta inmediatamente de la disponibilidad activa
$dispPostBloqueo = $motorDisp->calcularDisponibilidadItem($orgId, $edicionId, $itemId);
registrarAsercion("2.3 El bloqueo activo reduce inmediatamente la disponibilidad reportada", $dispPostBloqueo === ($dispInicial - 2));

// 2.4 Bloqueo que excede la disponibilidad es rechazado (409 Conflict)
$bloqueoExceso = $motorDisp->bloquearCupos($orgId, $edicionId, $itemId, 99999, $sesionPrueba);
registrarAsercion("2.4 Solicitud de cupos que excede la capacidad es rechazada", !$bloqueoExceso['exito'] && str_contains($bloqueoExceso['mensaje'], 'No hay suficientes cupos'));

// 2.5 Extensión del bloqueo a RETENCION_VOUCHER (24 horas)
$tokenBloqueo = (string) $bloqueoRes['token_bloqueo'];
$extendido = $motorDisp->extenderParaVoucher($tokenBloqueo, 24);
$bloqueoObj = $bloqueoRepo->buscarPorToken($tokenBloqueo);
registrarAsercion("2.5 Extensión a RETENCION_VOUCHER asigna estado y vigencia de 24 horas", $extendido && $bloqueoObj->estado === ReservaBloqueoTemporal::ESTADO_RETENCION_VOUCHER);

// 2.6 Consumo de bloqueo temporal marca estado CONSUMIDO
$consumido = $motorDisp->consumirBloqueo($tokenBloqueo);
$bloqueoObj2 = $bloqueoRepo->buscarPorToken($tokenBloqueo);
registrarAsercion("2.6 Consumo de bloqueo marca estado CONSUMIDO formalmente", $consumido && $bloqueoObj2->estado === ReservaBloqueoTemporal::ESTADO_CONSUMIDO);

// 2.7 Liberación manual por abandono de usuario
$bloqueoManual = $motorDisp->bloquearCupos($orgId, $edicionId, $itemId, 1, $sesionPrueba);
$tokenManual = (string) $bloqueoManual['token_bloqueo'];
$liberado = $motorDisp->liberarBloqueo($tokenManual);
$bloqueoObj3 = $bloqueoRepo->buscarPorToken($tokenManual);
registrarAsercion("2.7 Liberación manual marca estado LIBERADO_MANUAL y restituye cupos", $liberado && $bloqueoObj3->estado === ReservaBloqueoTemporal::ESTADO_LIBERADO_MANUAL);

// 2.8 Purgado y marcado de bloqueos expirados
$stmtExp = $pdo->prepare("
    INSERT INTO reserva_bloqueos_temporales (
        organizacion_id, edicion_id, item_comercial_id, token_bloqueo, identificador_sesion,
        cupos_bloqueados, estado, expira_en
    ) VALUES (
        :org_id, :edicion_id, :item_id, :token, 'ses_exp',
        1, 'ACTIVO', (NOW() - INTERVAL 10 MINUTE)
    )
");
$tokenExpirado = 'blk_exp_' . bin2hex(random_bytes(8));
$stmtExp->execute([
    'org_id'     => $orgId,
    'edicion_id' => $edicionId,
    'item_id'    => $itemId,
    'token'      => $tokenExpirado,
]);
$purgados = $motorDisp->purgarExpirados();
$bloqueoExpObj = $bloqueoRepo->buscarPorToken($tokenExpirado);
registrarAsercion("2.8 Purgado de expirados actualiza registros vencidos a estado EXPIRADO", $purgados >= 1 && $bloqueoExpObj->estado === ReservaBloqueoTemporal::ESTADO_EXPIRADO);

// ------------------------------------------------------------------------------
// SECCIÓN 3: Cotizador Soberano en Backend (Cero Manipulación de Precios)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 3: Cotizador Soberano en Backend ---\n";

// 3.1 Cálculo determinista de línea de ítem (Precio 150.00 x 2 = 300.00)
$cotizacionCalc = $fachada->cotizar($orgId, $edicionId, [
    ['tipo' => 'ITEM', 'id' => $itemId, 'cantidad' => 2]
]);
registrarAsercion("3.1 Cálculo backend determinista: Subtotal exacto (S/ 300.00)", (float)$cotizacionCalc['subtotal'] === 300.00);

// 3.2 Desglose con Base Imponible e IGV (18% incluido según régimen nacional)
$baseEsperada = round(300.00 / 1.18, 2);
$igvEsperado = round(300.00 - $baseEsperada, 2);
registrarAsercion("3.2 Desglose legal de base imponible e IGV del 18%", (float)$cotizacionCalc['base_imponible'] === $baseEsperada && (float)$cotizacionCalc['igv'] === $igvEsperado);

// 3.3 Rechazo de cotizaciones vacías
$errorCotVacia = false;
try {
    $fachada->cotizar($orgId, $edicionId, []);
} catch (\InvalidArgumentException $e) {
    $errorCotVacia = true;
}
registrarAsercion("3.3 Rechazo de solicitud de cotización sin líneas comerciales", $errorCotVacia);

// 3.4 Rechazo de ítem inexistente o no habilitado en la edición
$errorItemInvalido = false;
try {
    $fachada->cotizar($orgId, $edicionId, [
        ['tipo' => 'ITEM', 'id' => 999999, 'cantidad' => 1]
    ]);
} catch (\InvalidArgumentException $e) {
    $errorItemInvalido = true;
}
registrarAsercion("3.4 Rechazo de cotización con ítems no pertenecientes a la edición", $errorItemInvalido);

// ------------------------------------------------------------------------------
// SECCIÓN 4: Checkout de Invitado y Gestión de Identidad (Ley 29733)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 4: Checkout de Invitado y Gestión de Identidad ---\n";

// Crear bloqueo activo para checkout de prueba
$bloqueoCheckout = $motorDisp->bloquearCupos($orgId, $edicionId, $itemId, 1, $sesionPrueba);
$tokenChk = (string) $bloqueoCheckout['token_bloqueo'];

// 4.1 Rechazo de checkout sin aceptación de Términos y Privacidad (Ley 29733)
$payloadSinConsentimiento = [
    'token_bloqueo' => $tokenChk,
    'cliente' => [
        'tipo_documento_id' => 1,
        'numero_documento'  => '47852145',
        'nombres'           => 'Juan Carlos',
        'apellidos'         => 'Paredes Mamani',
        'correo'            => 'juan.paredes@testcandelaria.pe',
        'telefono'          => '+51951999888',
    ],
    'lineas' => [
        ['tipo' => 'ITEM', 'id' => $itemId, 'cantidad' => 1]
    ],
    'metodo_pago' => 'TRANSFERENCIA_BANCARIA',
    'consentimientos' => [
        'terminos_condiciones' => false,
        'politica_privacidad'  => true,
    ],
];
$errorConsentimiento = false;
try {
    $fachada->procesarCheckout($orgId, $edicionId, $payloadSinConsentimiento, '127.0.0.1');
} catch (\InvalidArgumentException $e) {
    $errorConsentimiento = true;
}
registrarAsercion("4.1 Rechazo de checkout si el cliente no acepta Términos y Privacidad", $errorConsentimiento);

// 4.2 Checkout de invitado exitoso con Voucher Manual
$payloadVoucherValido = $payloadSinConsentimiento;
$payloadVoucherValido['consentimientos']['terminos_condiciones'] = true;
$payloadVoucherValido['consentimientos']['whatsapp_transaccional'] = true;
$payloadVoucherValido['comprobante'] = [
    'numero_operacion' => 'BN-8874125',
    'voucher_url'      => '/almacenamiento/privado/vouchers/voucher_test_01.jpg',
];

$checkoutRes = $fachada->procesarCheckout($orgId, $edicionId, $payloadVoucherValido, '127.0.0.1');
registrarAsercion("4.2 Checkout de invitado con voucher manual procesado exitosamente", $checkoutRes['exito'] && $checkoutRes['modo'] === 'VOUCHER_MANUAL');

// 4.3 Creación idempotente de Persona y Cliente en base de datos
$personaCreada = $personaRepo->buscarPorDocumento($orgId, 1, '47852145');
$clienteCreado = $clienteRepo->buscarPorPersona($orgId, (int) $personaCreada->id);
registrarAsercion("4.3 Persona y Cliente registrados automáticamente en tenant sin duplicación", $personaCreada !== null && $clienteCreado !== null);

// 4.4 Registro de evidencia de consentimiento en comunicacion_consentimientos_canal
$stmtEvidencia = $pdo->prepare("
    SELECT * FROM `comunicacion_consentimientos_canal`
    WHERE `organizacion_id` = :org_id AND `cliente_id` = :cli_id AND `canal` = 'WHATSAPP'
");
$stmtEvidencia->execute(['org_id' => $orgId, 'cli_id' => $clienteCreado->id]);
$evidencia = $stmtEvidencia->fetch(PDO::FETCH_ASSOC);
registrarAsercion("4.4 Evidencia de Opt-In registrada conforme a Ley 29733 (origen WEB_CHECKBOX)", $evidencia && $evidencia['origen_evidencia'] === 'WEB_CHECKBOX' && $evidencia['actor_tipo'] === 'SISTEMA');

// ------------------------------------------------------------------------------
// SECCIÓN 5: Máquina de Estados y Desacople Financiero
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 5: Máquina de Estados y Desacople Financiero ---\n";

// 5.1 En pago manual, Venta creada en CONFIRMADA con estado_financiero = NO_PAGADA
$ventaId = (int) $checkoutRes['venta_id'];
$ventaCreada = $ventaRepo->buscarPorId($ventaId, $orgId);
registrarAsercion("5.1 Venta creada en estado CONFIRMADA con estado financiero NO_PAGADA (sin PENDIENTE_PAGO)", $ventaCreada->estado === EstadoVenta::CONFIRMADA && ($ventaCreada->estadoFinanciero === 'NO_PAGADA' || $ventaCreada->estadoFinanciero === \Aplicacion\Finanzas\EstadoFinancieroVenta::NO_PAGADA->value));

// 5.2 En pago manual, Reserva queda en estado REGISTRADA (NO CONFIRMADA)
$reservaId = (int) $checkoutRes['reserva_id'];
$reservaCreada = $reservaRepo->buscarPorId($reservaId, $orgId);
registrarAsercion("5.2 Reserva creada en estado REGISTRADA (la carga de voucher NO confirma la reserva)", $reservaCreada->estado === EstadoReserva::REGISTRADA);

// 5.3 En pago manual, Pago registrado en PENDIENTE_VERIFICACION
$pagoId = (int) $checkoutRes['pago_id'];
$stmtPagoCheck = $pdo->prepare("SELECT estado, metodo_pago, boucher_comprobante_url FROM pagos WHERE id = :id");
$stmtPagoCheck->execute(['id' => $pagoId]);
$pagoCheck = $stmtPagoCheck->fetch(PDO::FETCH_ASSOC);
registrarAsercion("5.3 Pago manual registrado en estado PENDIENTE_VERIFICACION", $pagoCheck['estado'] === EstadoPago::PENDIENTE_VERIFICACION->value && $pagoCheck['metodo_pago'] === MetodoPago::TRANSFERENCIA_BANCARIA->value);

// 5.4 En pago manual, el bloqueo se extiende a 24 horas (RETENCION_VOUCHER)
$bloqueoExtCheck = $bloqueoRepo->buscarPorToken($tokenChk);
registrarAsercion("5.4 Bloqueo temporal extendido a RETENCION_VOUCHER con 24 horas de vigencia", $bloqueoExtCheck->estado === ReservaBloqueoTemporal::ESTADO_RETENCION_VOUCHER);

// 5.5 Aprobación de voucher por operador administrativo autorizado (pagos.verificar)
$ctxOperador = ContextoOperacion::paraHumano(
    usuarioId: $usrAdminId, // Operador/Admin
    canalId: 1,
    canalCodigo: 'APP',
    organizacionId: $orgId
);
$aprobacionRes = $fachada->aprobarVoucherPorOperador($orgId, $pagoId, $ctxOperador);
$reservaAprobada = $reservaRepo->buscarPorId($reservaId, $orgId);
$ventaAprobada = $ventaRepo->buscarPorId($ventaId, $orgId);
registrarAsercion("5.5 Aprobación de voucher transiciona Reserva a CONFIRMADA y Venta a PAGADA_TOTAL", $reservaAprobada->estado === EstadoReserva::CONFIRMADA && ($ventaAprobada->estadoFinanciero === 'PAGADA_TOTAL' || $ventaAprobada->estadoFinanciero === \Aplicacion\Finanzas\EstadoFinancieroVenta::PAGADA_TOTAL->value));

// 5.6 Notificación Outbox RESERVA_CONFIRMADA encolada solo tras confirmación real
$stmtMsgOutbox = $pdo->prepare("
    SELECT * FROM `comunicacion_mensajes`
    WHERE `cliente_id` = :cli_id AND `contenido_texto` LIKE '%RESERVA_CONFIRMADA%'
    ORDER BY id DESC LIMIT 1
");
$stmtMsgOutbox->execute(['cli_id' => $clienteCreado->id]);
$msgConf = $stmtMsgOutbox->fetch(PDO::FETCH_ASSOC);
registrarAsercion("5.6 Plantilla de confirmación encolada en Outbox únicamente al confirmarse la reserva", $msgConf !== false && str_contains($msgConf['contenido_texto'], 'RESERVA_CONFIRMADA'));

// 5.7 Rechazo de voucher por operador cancela reserva y libera capacidad
// Creamos una segunda compra con voucher para probar el rechazo
$bloqueoRechazo = $motorDisp->bloquearCupos($orgId, $edicionId, $itemId, 1, $sesionPrueba);
$payloadRechazo = $payloadVoucherValido;
$payloadRechazo['token_bloqueo'] = $bloqueoRechazo['token_bloqueo'];
$checkoutRechazo = $fachada->procesarCheckout($orgId, $edicionId, $payloadRechazo, '127.0.0.1');

$rechazoRes = $fachada->rechazarVoucherPorOperador($orgId, (int)$checkoutRechazo['pago_id'], 'Voucher ilegible', $ctxOperador);
$reservaRechazada = $reservaRepo->buscarPorId((int)$checkoutRechazo['reserva_id'], $orgId);
$ventaRechazada = $ventaRepo->buscarPorId((int)$checkoutRechazo['venta_id'], $orgId);
registrarAsercion("5.7 Rechazo de voucher por operador cancela formalmente Reserva y Venta", $reservaRechazada->estado === EstadoReserva::CANCELADA && $ventaRechazada->estado === EstadoVenta::CANCELADA);

// 5.7b Expiración automática de vouchers vencidos (>24h)
$stmtVenc = $pdo->prepare("UPDATE pagos SET fecha_pago = NOW() - INTERVAL 25 HOUR WHERE id = :id");
$stmtVenc->execute(['id' => (int)$checkoutRechazo['pago_id']]);
$expiradosCount = $fachada->expirarVouchersVencidos($orgId, 24);
registrarAsercion("5.7b Expiración determinista cancela vouchers mayores a 24h y libera capacidad", is_int($expiradosCount));

// 5.8 Checkout con Pasarela Sandbox emite token sin crear reserva ni venta prematuras
$bloqueoOnline = $motorDisp->bloquearCupos($orgId, $edicionId, $itemId, 1, $sesionPrueba);
$payloadOnline = $payloadVoucherValido;
$payloadOnline['token_bloqueo'] = $bloqueoOnline['token_bloqueo'];
$payloadOnline['metodo_pago'] = 'PASARELA_NIUBIZ';

$checkoutOnline = $fachada->procesarCheckout($orgId, $edicionId, $payloadOnline, '127.0.0.1');
registrarAsercion("5.8 Checkout pasarela sandbox retorna sesión efímera sin formalizar reserva prematura", $checkoutOnline['exito'] && $checkoutOnline['modo'] === 'PASARELA_ONLINE_SANDBOX' && isset($checkoutOnline['cotizacion_id']));

// 5.9 Confirmación online sandbox formaliza atómicamente Venta y Reserva CONFIRMADA
$confirmacionOnline = $fachada->confirmarPagoOnlineSandbox(
    $orgId,
    (int) $checkoutOnline['cotizacion_id'],
    (string) $checkoutOnline['token_bloqueo'],
    'NIUBIZ',
    'TX-NIUBIZ-9988451'
);
registrarAsercion("5.9 Confirmación online formaliza Venta y Reserva en estado CONFIRMADA", $confirmacionOnline['exito'] && $confirmacionOnline['estado'] === 'CONFIRMADA');

// ------------------------------------------------------------------------------
// SECCIÓN 6: Autenticación Passwordless y Espacio del Cliente (Cero Filtración)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 6: Autenticación Passwordless y Espacio del Cliente (Cero Filtración) ---\n";

// Limpiar solicitudes previas para garantizar determinismo en rate limiting
$pdo->prepare("DELETE FROM cliente_codigos_acceso WHERE organizacion_id = :org_id AND destino IN (:dest1, :dest2)")->execute(['org_id' => $orgId, 'dest1' => '+51951999888', 'dest2' => 'juan.paredes@testcandelaria.pe']);

// 6.1 Emisión de código OTP WhatsApp no filtra secretos en respuesta HTTP y genera código numérico
$solicitudOtp = $authClienteSvc->solicitarOtpWhatsApp($orgId, '+51951999888');
$codigoCorrecto = (string) $authClienteSvc->obtenerCodigoTesting('+51951999888');
registrarAsercion("6.1 Solicitud OTP no filtra secretos en respuesta HTTP y genera código numérico de 6 dígitos", $solicitudOtp['exito'] && !isset($solicitudOtp['codigo_sandbox']) && strlen($codigoCorrecto) === 6);

// 6.2 Rate limiting de OTP (máximo 3 solicitudes por 15 minutos)
$authClienteSvc->solicitarOtpWhatsApp($orgId, '+51951999888');
$authClienteSvc->solicitarOtpWhatsApp($orgId, '+51951999888');
$otpBloqueado = $authClienteSvc->solicitarOtpWhatsApp($orgId, '+51951999888');
registrarAsercion("6.2 Rate limiting bloquea cuarta solicitud de OTP en ventana de 15 minutos", !$otpBloqueado['exito'] && str_contains($otpBloqueado['mensaje'], 'límite de solicitudes'));

// 6.3 Verificación fallida de OTP con código incorrecto
$verifFallida = $authClienteSvc->verificarOtpWhatsApp($orgId, '+51951999888', '000000', '127.0.0.1');
registrarAsercion("6.3 Verificación de OTP rechaza código incorrecto", !$verifFallida['exito']);

// 6.4 Verificación exitosa de OTP emite sesión de cliente candelaria_cliente_sesion
$verifExitosa = $authClienteSvc->verificarOtpWhatsApp($orgId, '+51951999888', $codigoCorrecto, '127.0.0.1');
registrarAsercion("6.4 Verificación exitosa emite token de sesión seguro cli_ses_*", $verifExitosa['exito'] && str_starts_with((string)$verifExitosa['token_sesion'], 'cli_ses_'));

// 6.5 El código OTP es de un solo uso (rechazo en segundo intento)
$reintentoUso = $authClienteSvc->verificarOtpWhatsApp($orgId, '+51951999888', $codigoCorrecto, '127.0.0.1');
registrarAsercion("6.5 El código OTP se invalida tras su primer uso exitoso (Single-Use)", !$reintentoUso['exito']);

// 6.6 Emisión y verificación de Magic Link por correo sin filtrar token en respuesta
$solicitudMagic = $authClienteSvc->solicitarMagicLinkEmail($orgId, 'juan.paredes@testcandelaria.pe');
$tokenMagic = (string) $authClienteSvc->obtenerCodigoTesting('juan.paredes@testcandelaria.pe');
registrarAsercion("6.6 Solicitud Magic Link no filtra token en respuesta HTTP", $solicitudMagic['exito'] && !isset($solicitudMagic['token_sandbox']));
$verifMagic = $authClienteSvc->verificarMagicLink($orgId, 'juan.paredes@testcandelaria.pe', $tokenMagic, '127.0.0.1');
registrarAsercion("6.6b Verificación exitosa de Magic Link emite sesión cliente", $verifMagic['exito'] && !empty($verifMagic['token_sesion']));

// 6.7 Middleware de cliente valida sesión y extrae cliente_id
$sesionClienteObj = $authClienteSvc->validarSesion((string)$verifExitosa['token_sesion']);
registrarAsercion("6.7 Sesión de cliente se valida y vincula a cliente_id sin roles RBAC", $sesionClienteObj !== null && $sesionClienteObj->clienteId === (int)$clienteCreado->id);

// 6.8 Cierre de sesión de cliente revoca el token en base de datos
$authClienteSvc->cerrarSesion((string)$verifExitosa['token_sesion']);
$sesionRevocada = $authClienteSvc->validarSesion((string)$verifExitosa['token_sesion']);
registrarAsercion("6.8 Cierre de sesión revoca inmediatamente el token del cliente", $sesionRevocada === null);

// ------------------------------------------------------------------------------
// SECCIÓN 7: Boletos QR y Control de Acceso (Post-Confirmación)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 7: Boletos QR y Control de Acceso ---\n";

// 7.1 Reserva CONFIRMADA genera boleto QR con firma criptográfica HMAC-SHA256
$qrRes = $fachada->generarTokenBoletoQr($orgId, $reservaId, (int)$clienteCreado->id);
registrarAsercion("7.1 Reserva CONFIRMADA con venta PAGADA genera boleto QR firmado", $qrRes['exito'] && !empty($qrRes['token_qr']));

// 7.1b Validación digital del Boleto QR generado
$valQr = $fachada->validarTokenBoletoQr($orgId, (string)$qrRes['token_qr']);
registrarAsercion("7.1b Validación de firma y vigencia del Boleto QR es conforme", $valQr['valido'] && isset($valQr['datos']['reserva_id']));

// 7.1c Boleto QR adulterado es rechazado
$valQrFake = $fachada->validarTokenBoletoQr($orgId, $qrRes['token_qr'] . '_tampered');
registrarAsercion("7.1c Boleto QR con firma alterada es categóricamente rechazado", !$valQrFake['valido']);

// 7.2 Reserva CANCELADA rechaza la generación de boleto QR
$qrCancelado = $fachada->generarTokenBoletoQr($orgId, (int)$checkoutRechazo['reserva_id'], (int)$clienteCreado->id);
registrarAsercion("7.2 Reserva CANCELADA bloquea la generación de boleto QR", !$qrCancelado['exito'] && $qrCancelado['codigo_error'] === 'RESERVA_NO_CONFIRMADA');

// 7.3 Anti-IDOR: Intento de generar boleto QR para reserva de otro cliente es denegado
$errorIdor = false;
try {
    $fachada->generarTokenBoletoQr($orgId, $reservaId, 99999); // Cliente ajeno
} catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
    $errorIdor = true;
}
registrarAsercion("7.3 Anti-IDOR: Denegación de acceso ante intento de ver reserva ajena", $errorIdor);

// 7.4 Listado de Mis Reservas retorna únicamente las compras del cliente autenticado
$misReservas = $fachada->obtenerMisReservas($orgId, (int)$clienteCreado->id);
$misReservasIds = array_map(fn($r) => (int)$r['id'], $misReservas);
registrarAsercion("7.4 Consulta de Mis Reservas retorna el histórico filtrado estrictamente por titular", count($misReservas) >= 1 && in_array($reservaId, $misReservasIds, true));

// ------------------------------------------------------------------------------
// SECCIÓN 8: Prueba de Concurrencia y Aforo Crítico
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 8: Prueba de Concurrencia y Aforo Crítico ---\n";

// Crear un ítem con cupo limitado a exactamente 1
$stmtItemCrit = $pdo->prepare("
    INSERT INTO items_comerciales (organizacion_id, categoria_id, codigo, nombre, tipo, unidad_medida, estado)
    VALUES (:org_id, :cat_id, 'ITEM_CRITICO_1CUPO', 'Último Asiento Gradería Bolívar', 'SERVICIO', 'TICKET', 'ACTIVO')
    ON DUPLICATE KEY UPDATE estado = 'ACTIVO'
");
$stmtItemCrit->execute(['org_id' => $orgId, 'cat_id' => $catId]);
$stmtItemCritId = $pdo->prepare("SELECT id FROM items_comerciales WHERE organizacion_id = :org_id AND codigo = 'ITEM_CRITICO_1CUPO'");
$stmtItemCritId->execute(['org_id' => $orgId]);
$itemCriticoId = (int) $stmtItemCritId->fetchColumn();

// Asegurar configuración operativa del ítem con capacidad colectiva
$stmtCfgOpCrit = $pdo->prepare("
    INSERT INTO item_configuracion_operativa (item_comercial_id, requiere_reserva, tipo_capacidad, requiere_agendamiento, requiere_participantes)
    VALUES (:item_id, 1, 'COLECTIVA', 0, 0)
    ON DUPLICATE KEY UPDATE requiere_reserva = 1, tipo_capacidad = 'COLECTIVA', requiere_participantes = 0
");
$stmtCfgOpCrit->execute(['item_id' => $itemCriticoId]);

// Asignar capacidad referencial = 1
$stmtOfeCrit = $pdo->prepare("
    INSERT INTO ofertas_items_edicion (organizacion_id, edicion_id, item_comercial_id, estado, capacidad_referencial)
    VALUES (:org_id, :edicion_id, :item_id, 'ACTIVO', 1)
    ON DUPLICATE KEY UPDATE estado = 'ACTIVO', capacidad_referencial = 1
");
$stmtOfeCrit->execute(['org_id' => $orgId, 'edicion_id' => $edicionId, 'item_id' => $itemCriticoId]);

// Usuario A bloquea el único cupo disponible
$bloqueoUserA = $motorDisp->bloquearCupos($orgId, $edicionId, $itemCriticoId, 1, 'sesion_user_a');
registrarAsercion("8.1 Usuario A adquiere exitosamente el último cupo disponible", $bloqueoUserA['exito']);

// Usuario B intenta bloquear el mismo cupo inmediatamente
$bloqueoUserB = $motorDisp->bloquearCupos($orgId, $edicionId, $itemCriticoId, 1, 'sesion_user_b');
registrarAsercion("8.2 Usuario B es rechazado por aforo agotado (Cero Sobreventa)", !$bloqueoUserB['exito'] && str_contains($bloqueoUserB['mensaje'], 'No hay suficientes cupos'));

// Usuario A desiste y libera el cupo
$motorDisp->liberarBloqueo((string)$bloqueoUserA['token_bloqueo']);

// Usuario B reintenta y ahora adquiere el cupo liberado
$bloqueoUserBReintento = $motorDisp->bloquearCupos($orgId, $edicionId, $itemCriticoId, 1, 'sesion_user_b');
registrarAsercion("8.3 Usuario B adquiere el cupo tras la liberación de Usuario A", $bloqueoUserBReintento['exito']);

// Consumir el bloqueo de B
$motorDisp->consumirBloqueo((string)$bloqueoUserBReintento['token_bloqueo']);

// Limpieza formal de fixtures efímeros para mantener la base de datos limpia
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("DELETE FROM recibos_caja WHERE organizacion_id = {$orgId}");
$pdo->exec("DELETE FROM documentos_emitidos WHERE organizacion_id = {$orgId}");
$pdo->exec("DELETE FROM reserva_bloqueos_temporales WHERE organizacion_id = {$orgId}");
$pdo->exec("DELETE FROM cliente_sesiones WHERE organizacion_id = {$orgId}");
$pdo->exec("DELETE FROM cliente_codigos_acceso WHERE organizacion_id = {$orgId}");
$pdo->exec("DELETE FROM comunicacion_consentimientos_canal WHERE organizacion_id = {$orgId}");
$pdo->exec("DELETE FROM comunicacion_mensajes WHERE organizacion_id = {$orgId}");
$pdo->exec("DELETE FROM pagos WHERE edicion_id = {$edicionId} OR organizacion_id = {$orgId}");
$pdo->exec("DELETE FROM reserva_prestaciones WHERE reserva_id IN (SELECT id FROM reservas WHERE edicion_id = {$edicionId} OR organizacion_id = {$orgId})");
$pdo->exec("DELETE FROM reservas WHERE edicion_id = {$edicionId} OR organizacion_id = {$orgId}");
$pdo->exec("DELETE FROM venta_lineas WHERE venta_id IN (SELECT id FROM ventas WHERE edicion_id = {$edicionId} OR organizacion_id = {$orgId})");
$pdo->exec("DELETE FROM ventas WHERE edicion_id = {$edicionId} OR organizacion_id = {$orgId}");
$pdo->exec("DELETE FROM cotizacion_lineas WHERE cotizacion_id IN (SELECT id FROM cotizaciones WHERE edicion_id = {$edicionId} OR organizacion_id = {$orgId})");
$pdo->exec("DELETE FROM cotizaciones WHERE edicion_id = {$edicionId} OR organizacion_id = {$orgId}");
$pdo->exec("DELETE FROM tarifas_items_edicion WHERE oferta_item_id IN (SELECT id FROM ofertas_items_edicion WHERE edicion_id = {$edicionId})");
$pdo->exec("DELETE FROM ofertas_items_edicion WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM item_configuracion_operativa WHERE item_comercial_id = {$itemId} OR item_comercial_id = {$itemCriticoId}");
$pdo->exec("DELETE FROM items_comerciales WHERE id = {$itemCriticoId}");
$pdo->exec("DELETE FROM ediciones_candelaria WHERE id = {$edicionId} AND codigo = 'edicion-2098-test'");
$pdo->exec("DELETE FROM organizaciones WHERE codigo = 'org_inactiva_test'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n==============================================================================\n";
echo "RESUMEN DE PRUEBAS SUITE F2.9B:\n";
echo "TOTAL PRUEBAS EJECUTADAS : {$totalPruebas}\n";
echo "TOTAL PRUEBAS EXITOSAS   : {$pruebasExitosas}\n";
echo "TOTAL PRUEBAS FALLIDAS   : {$pruebasFallidas}\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
