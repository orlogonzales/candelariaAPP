<?php

declare(strict_types=1);

namespace Pruebas;

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Cotizaciones\CotizacionServicio;
use Aplicacion\Cotizaciones\EstadoCotizacion;
use Aplicacion\Cotizaciones\MotivoAnulacionCotizacion;
use Aplicacion\Cotizaciones\MotivoRechazoCotizacion;
use Aplicacion\Cotizaciones\TipoDescuentoCotizacion;
use Aplicacion\Cotizaciones\TipoLineaCotizacion;
use Aplicacion\Crm\OportunidadServicio;
use Aplicacion\Crm\OrigenComercialServicio;
use Aplicacion\Entidades\CategoriaItem;
use Aplicacion\Entidades\ItemComercial;
use Aplicacion\Entidades\Paquete;
use Aplicacion\Entidades\Venta;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\CategoriaItemRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\CotizacionRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\HistorialEtapaRepositorio;
use Aplicacion\Repositorios\HistorialTarifaRepositorio;
use Aplicacion\Repositorios\InteraccionCrmRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\OfertaItemEdicionRepositorio;
use Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\OrigenComercialRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Ventas\EstadoVenta;
use Aplicacion\Ventas\MotivoAnulacionVenta;
use Aplicacion\Ventas\MotivoCancelacionVenta;
use Aplicacion\Ventas\TipoDescuentoVenta;
use Aplicacion\Ventas\TipoLineaVenta;
use Aplicacion\Ventas\TipoOrigenVenta;
use Aplicacion\Ventas\VentaServicio;
use BadMethodCallException;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;
use RuntimeException;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS F2.5B: DOMINIO Y PERSISTENCIA DE VENTAS\n";
echo "CONVERSIÓN ATÓMICA, CORRELATIVOS, SNAPSHOTS INMUTABLES, FAIL-CLOSED Y CRM GANADA\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

$fallos = 0;
$exitos = 0;

function afirmar(bool $condicion, string $descripcion, string $detalles = ''): void
{
    global $fallos, $exitos;
    if ($condicion) {
        $exitos++;
        echo " [PASS] " . $descripcion . "\n";
    } else {
        $fallos++;
        echo " [FAIL] " . $descripcion . ($detalles ? " -> Detalle: " . $detalles : "") . "\n";
    }
}

// Repositorios e infraestructura
$auditoriaRepo            = new AuditoriaRepositorio($pdo);
$authzServicio            = new AutorizacionServicio(pdo: $pdo);
$configServicio           = new ConfiguracionServicio(auditoriaRepo: $auditoriaRepo, pdo: $pdo);
$edicionRepo              = new EdicionRepositorio($pdo);
$clienteRepo              = new ClienteRepositorio($pdo);
$personaRepo              = new PersonaRepositorio($pdo);
$usuarioRepo              = new UsuarioRepositorio($pdo);
$origenRepo               = new OrigenComercialRepositorio($pdo);
$oportunidadRepo          = new OportunidadRepositorio($pdo);
$historialEtapaRepo       = new HistorialEtapaRepositorio($pdo);
$interaccionRepo          = new InteraccionCrmRepositorio($pdo);
$categoriaRepo            = new CategoriaItemRepositorio($pdo);
$itemRepo                 = new ItemComercialRepositorio($pdo);
$paqueteRepo              = new PaqueteRepositorio($pdo);
$ofertaItemRepo           = new OfertaItemEdicionRepositorio($pdo);
$ofertaPaqueteRepo        = new OfertaPaqueteEdicionRepositorio($pdo);
$tarifaItemRepo           = new TarifaItemEdicionRepositorio($pdo);
$tarifaPaqueteRepo        = new TarifaPaqueteEdicionRepositorio($pdo);
$cotizacionRepo           = new CotizacionRepositorio($pdo);
$ventaRepo                = new VentaRepositorio($pdo);

$oportunidadServicio = new OportunidadServicio(
    oportunidadRepo: $oportunidadRepo,
    historialRepo: $historialEtapaRepo,
    clienteRepo: $clienteRepo,
    edicionRepo: $edicionRepo,
    origenRepo: $origenRepo,
    usuarioRepo: $usuarioRepo,
    authzServicio: $authzServicio,
    auditoriaRepo: $auditoriaRepo,
    configServicio: $configServicio,
    pdo: $pdo
);

$cotizacionServicio = new CotizacionServicio(
    cotizacionRepo: $cotizacionRepo,
    clienteRepo: $clienteRepo,
    edicionRepo: $edicionRepo,
    oportunidadRepo: $oportunidadRepo,
    itemRepo: $itemRepo,
    paqueteRepo: $paqueteRepo,
    ofertaItemRepo: $ofertaItemRepo,
    ofertaPaqueteRepo: $ofertaPaqueteRepo,
    tarifaItemRepo: $tarifaItemRepo,
    tarifaPaqueteRepo: $tarifaPaqueteRepo,
    configServicio: $configServicio,
    authzServicio: $authzServicio,
    auditoriaRepo: $auditoriaRepo,
    oportunidadServicio: $oportunidadServicio,
    pdo: $pdo
);

$ventaServicio = new VentaServicio(
    ventaRepo: $ventaRepo,
    cotizacionRepo: $cotizacionRepo,
    clienteRepo: $clienteRepo,
    personaRepo: $personaRepo,
    edicionRepo: $edicionRepo,
    configServicio: $configServicio,
    authzServicio: $authzServicio,
    auditoriaRepo: $auditoriaRepo,
    oportunidadServicio: $oportunidadServicio,
    pdo: $pdo
);

// Contextos sintéticos de prueba
$orgId = 10000;
$orgIdAjena = 20000;
$usuarioAdminId = 91001;
$usuarioOpId = 91002;

$ctxAdmin = new ContextoOperacion(
    actorTipo: 'HUMANO',
    usuarioId: $usuarioAdminId,
    actorSistemaId: null,
    actorSistemaCodigo: null,
    canalId: 1,
    canalCodigo: 'APP',
    correlacionId: 'corr-test-vta-admin-01',
    origenIp: '127.0.0.1',
    agenteUsuario: 'PHPUnit/F2.5B',
    organizacionId: $orgId
);

$ctxOp = new ContextoOperacion(
    actorTipo: 'HUMANO',
    usuarioId: $usuarioOpId,
    actorSistemaId: null,
    actorSistemaCodigo: null,
    canalId: 1,
    canalCodigo: 'APP',
    correlacionId: 'corr-test-vta-op-01',
    origenIp: '127.0.0.1',
    agenteUsuario: 'PHPUnit/F2.5B',
    organizacionId: $orgId
);

$ctxAjeno = new ContextoOperacion(
    actorTipo: 'HUMANO',
    usuarioId: $usuarioAdminId,
    actorSistemaId: null,
    actorSistemaCodigo: null,
    canalId: 1,
    canalCodigo: 'APP',
    correlacionId: 'corr-test-vta-ajeno-01',
    origenIp: '127.0.0.1',
    agenteUsuario: 'PHPUnit/F2.5B',
    organizacionId: $orgIdAjena
);

// ==============================================================================
// BLOQUE 0: INSTALACIÓN LIMPIA TEMPORAL Y PARIDAD ESTRUCTURAL DEL ESQUEMA
// ==============================================================================
echo "--- BLOQUE 0: PARIDAD ESTRUCTURAL E INSTALACIÓN LIMPIA (ESQUEMA BASE) ---\n";

$tablasVentas = ['ventas_secuencias', 'ventas', 'venta_lineas', 'venta_linea_componentes'];
$tablasActuales = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tablasVentas as $t) {
    afirmar(in_array($t, $tablasActuales, true), "0.1: Tabla de ventas obligatoria '{$t}' existe en la base de datos principal");
}
afirmar(count($tablasActuales) >= 43, "0.2: Base de datos principal contiene al menos 43 tablas oficiales");

// Migración 13 registrada en migraciones_control
$stmtMig13 = $pdo->prepare("SELECT COUNT(*) FROM `migraciones_control` WHERE `migracion` = '2026_10_06_000013_crear_modulo_ventas_dominio_y_rbac.sql'");
$stmtMig13->execute();
afirmar((int) $stmtMig13->fetchColumn() === 1, "0.3: Migración 000013 registrada en 'migraciones_control' (Lote 11)");

// Test de instalación limpia en BD temporal
$dbTemp = 'candelaria_temp_vta_' . substr(bin2hex(random_bytes(4)), 0, 8);
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbTemp}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $host = entorno('DB_HOST', '127.0.0.1');
    $user = entorno('DB_USERNAME', 'root');
    $pass = entorno('DB_PASSWORD', '');
    $pdoTemp = new PDO("mysql:host={$host};dbname={$dbTemp};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $sqlEsquema = (string) file_get_contents(__DIR__ . '/../base_datos/esquema/esquema_base.sql');
    $pdoTemp->exec($sqlEsquema);
    $tablasTemp = $pdoTemp->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tablasVentas as $t) {
        afirmar(in_array($t, $tablasTemp, true), "0.4: Instalación limpia incluye '{$t}'");
    }
    afirmar(count($tablasTemp) >= 43, "0.5: Instalación limpia de 'esquema_base.sql' crea al menos 43 tablas oficiales");

    // Paridad 1:1 de tablas entre clean install y BD principal
    $dif1 = array_diff($tablasActuales, $tablasTemp);
    $dif2 = array_diff($tablasTemp, $tablasActuales);
    afirmar(empty($dif1) && empty($dif2), "0.6: Paridad estructural de tablas al 100% entre BD principal e instalación limpia");
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbTemp}`");
}

try {
    $pdo->beginTransaction();

    // 0. Provisión de usuarios sintéticos deterministas (Zero-PII)
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `correo_electronico`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES ({$usuarioAdminId}, {$orgId}, 'NATURAL', 'Admin', 'Sintetico F25B', 'admin.f25b@sintetico.local', '999111222', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `es_superadmin_plataforma`, `estado`) VALUES ({$usuarioAdminId}, {$orgId}, {$usuarioAdminId}, 'admin_sintetico_f25b', 'Admin Sintetico F25B', 'admin.f25b@sintetico.local', '\$2y\$10\$abcdefghijklmnopqrstuu', 1, 'ACTIVO') ON DUPLICATE KEY UPDATE `es_superadmin_plataforma` = 1, `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES ({$usuarioAdminId}, 1) ON DUPLICATE KEY UPDATE `rol_id` = 1");

    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `correo_electronico`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES ({$usuarioOpId}, {$orgId}, 'NATURAL', 'Operador', 'Sintetico F25B', 'operador.f25b@sintetico.local', '999111333', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `es_superadmin_plataforma`, `estado`) VALUES ({$usuarioOpId}, {$orgId}, {$usuarioOpId}, 'operador_sintetico_f25b', 'Operador Sintetico F25B', 'operador.f25b@sintetico.local', '\$2y\$10\$abcdefghijklmnopqrstuu', 0, 'ACTIVO') ON DUPLICATE KEY UPDATE `es_superadmin_plataforma` = 0, `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES ({$usuarioOpId}, 3) ON DUPLICATE KEY UPDATE `rol_id` = 3");

    // ==============================================================================
    // BLOQUE 1: ESTRUCTURA RELACIONAL, RBAC SOBERANO (4 PERMISOS) Y PROHIBICIONES
    // ==============================================================================
    echo "--- BLOQUE 1: ESTRUCTURA RELACIONAL, RBAC (4 PERMISOS) Y PROHIBICIONES ---\n";

    // 1.1 Verificar existencia del Módulo 21 'ventas'
    $stmtMod = $pdo->prepare("SELECT * FROM `modulos` WHERE `id` = 21 AND `codigo` = 'ventas'");
    $stmtMod->execute();
    $modVentas = $stmtMod->fetch(PDO::FETCH_ASSOC);
    afirmar($modVentas !== false, "1.1: Módulo 21 'ventas' existe y está registrado formalmente");

    // 1.2 Verificar exactamente los 4 permisos RBAC de Ventas
    $stmtPerm = $pdo->prepare("SELECT `codigo` FROM `permisos` WHERE `modulo_id` = 21 ORDER BY `codigo` ASC");
    $stmtPerm->execute();
    $permisosVentas = $stmtPerm->fetchAll(PDO::FETCH_COLUMN);

    $esperados = ['ventas.anular', 'ventas.cancelar', 'ventas.crear_desde_cotizacion', 'ventas.ver'];
    afirmar($permisosVentas === $esperados, "1.2: Módulo ventas contiene exactamente los 4 permisos soberanos autorizados", json_encode($permisosVentas));

    // 1.3 Verificar que 'ventas.crear_directa' NO existe en RBAC
    afirmar(!in_array('ventas.crear_directa', $permisosVentas, true), "1.3: Permiso 'ventas.crear_directa' NO existe en el catálogo RBAC (bloqueo preventivo)");

    // 1.4 Invocar crearVentaDirecta() arroja BadMethodCallException
    $bloqueoDirecta = false;
    try {
        $ventaServicio->crearVentaDirecta();
    } catch (BadMethodCallException $e) {
        $bloqueoDirecta = true;
    }
    afirmar($bloqueoDirecta, "1.4: Método crearVentaDirecta() está funcionalmente bloqueado con BadMethodCallException");

    // 1.5 Invocar liquidar() arroja BadMethodCallException
    $bloqueoLiquidar = false;
    try {
        $ventaServicio->liquidar($orgId, 999, $ctxAdmin);
    } catch (BadMethodCallException $e) {
        $bloqueoLiquidar = true;
    }
    afirmar($bloqueoLiquidar, "1.5: Transición a LIQUIDADA está funcionalmente bloqueada con BadMethodCallException");

    // 1.6 Desacoplamiento de tablas prohibidas en F2.5
    $stmtTablas = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE()");
    $tablas = $stmtTablas->fetchAll(PDO::FETCH_COLUMN);
    afirmar(!in_array('operacion_salidas', $tablas, true), "1.6.1: Tabla 'operacion_salidas' no existe (desacoplamiento estricto)");
    afirmar(!in_array('pagos', $tablas, true), "1.6.2: Tabla 'pagos' no existe (desacoplamiento estricto)");
    afirmar(!in_array('caja_sesiones', $tablas, true), "1.6.3: Tablas de caja no existen (desacoplamiento estricto)");
    afirmar(!in_array('comprobantes_pago', $tablas, true), "1.6.4: Tablas de facturación/SUNAT no existen (desacoplamiento estricto)");

    // ==============================================================================
    // BLOQUE 2: PROVISIÓN DE FIXTURES SINTÉTICOS Y COTIZACIÓN APROBADA
    // ==============================================================================
    echo "\n--- BLOQUE 2: PROVISIÓN DE FIXTURES SINTÉTICOS Y COTIZACIÓN APROBADA ---\n";

    $pdo->exec("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `estado`) VALUES (20000, 'tenant_vta_b', 'Tenant Ventas B', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    // 2.1 Obtener o crear Edición activa
    $stmtEd = $pdo->prepare("SELECT `id` FROM `ediciones_candelaria` WHERE `organizacion_id` = :org_id AND `estado` IN ('OPERACION', 'PREOPERACION') LIMIT 1");
    $stmtEd->execute(['org_id' => $orgId]);
    $edicionId = (int) $stmtEd->fetchColumn();
    if ($edicionId === 0) {
        $stmtInsEd = $pdo->prepare("INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`) VALUES (:org_id, 'edicion-f25b-2026', 'CANDELARIA 2026 F25B', 2026, 'OPERACION', '2026-02-01', '2026-02-15', 1)");
        $stmtInsEd->execute(['org_id' => $orgId]);
        $edicionId = (int) $pdo->lastInsertId();
    }
    afirmar($edicionId > 0, "2.1: Edición activa identificada (#{$edicionId})");

    // 2.2 Crear Persona y Cliente sintético
    $dniSintetico = '99' . str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $stmtPer = $pdo->prepare("INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (:org_id, 'NATURAL', 1, :dni, 'Cliente', 'Sintetico Venta', 'cliente.venta@sintetico.local', '951000111', 'PE', 'ACTIVO')");
    $stmtPer->execute(['org_id' => $orgId, 'dni' => $dniSintetico]);
    $personaId = (int) $pdo->lastInsertId();

    $stmtCli = $pdo->prepare("INSERT INTO `clientes` (`organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`) VALUES (:org_id, :persona_id, 'CLIENTE', 1)");
    $stmtCli->execute(['org_id' => $orgId, 'persona_id' => $personaId]);
    $clienteId = (int) $pdo->lastInsertId();
    afirmar($clienteId > 0, "2.2: Cliente sintético creado (#{$clienteId}) con Persona (#{$personaId})");

    // 2.3 Crear Oportunidad CRM vinculada
    $stmtOp = $pdo->prepare("INSERT INTO `crm_oportunidades` (`organizacion_id`, `edicion_id`, `cliente_id`, `titulo`, `etapa`, `moneda`, `valor_estimado`) VALUES (:org_id, :edicion_id, :cliente_id, 'Oportunidad Paquete Festivo', 'COTIZACION', 'PEN', 1200.00)");
    $stmtOp->execute(['org_id' => $orgId, 'edicion_id' => $edicionId, 'cliente_id' => $clienteId]);
    $oportunidadId = (int) $pdo->lastInsertId();
    afirmar($oportunidadId > 0, "2.3: Oportunidad CRM creada (#{$oportunidadId}) en etapa COTIZACION");

    // 2.4 Obtener o crear Ítem Comercial y Oferta
    $stmtCat = $pdo->prepare("SELECT `id` FROM `categorias_items` WHERE `organizacion_id` = :org_id LIMIT 1");
    $stmtCat->execute(['org_id' => $orgId]);
    $catId = (int) $stmtCat->fetchColumn();
    if ($catId === 0) {
        $stmtInsCat = $pdo->prepare("INSERT INTO `categorias_items` (`organizacion_id`, `codigo`, `nombre`, `estado`) VALUES (:org_id, 'TOUR', 'Tours', 'ACTIVO')");
        $stmtInsCat->execute(['org_id' => $orgId]);
        $catId = (int) $pdo->lastInsertId();
    }

    $codItem = 'ITM_SINT_' . rand(1000, 9999);
    $stmtItm = $pdo->prepare("INSERT INTO `items_comerciales` (`organizacion_id`, `categoria_id`, `codigo`, `nombre`, `tipo`, `unidad_medida`, `estado`) VALUES (:org_id, :cat_id, :cod, 'City Tour Puno', 'SERVICIO', 'SERVICIO', 'ACTIVO')");
    $stmtItm->execute(['org_id' => $orgId, 'cat_id' => $catId, 'cod' => $codItem]);
    $itemId = (int) $pdo->lastInsertId();

    $stmtOfItm = $pdo->prepare("INSERT INTO `ofertas_items_edicion` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`) VALUES (:org_id, :edicion_id, :item_id, 'ACTIVO')");
    $stmtOfItm->execute(['org_id' => $orgId, 'edicion_id' => $edicionId, 'item_id' => $itemId]);
    $ofertaItemId = (int) $pdo->lastInsertId();

    $stmtTarItm = $pdo->prepare("INSERT INTO `tarifas_items_edicion` (`oferta_item_id`, `moneda`, `precio`, `version_bloqueo`) VALUES (:of_id, 'PEN', 250.00, 1)");
    $stmtTarItm->execute(['of_id' => $ofertaItemId]);

    // 2.5 Obtener o crear Paquete Comercial y Oferta
    $codPaq = 'PAQ_SINT_' . rand(1000, 9999);
    $stmtPaq = $pdo->prepare("INSERT INTO `paquetes` (`organizacion_id`, `codigo`, `nombre`, `estado`) VALUES (:org_id, :cod, 'Paquete Candelaria VIP', 'ACTIVO')");
    $stmtPaq->execute(['org_id' => $orgId, 'cod' => $codPaq]);
    $paqueteId = (int) $pdo->lastInsertId();

    $stmtPaqItm = $pdo->prepare("INSERT INTO `paquete_items` (`paquete_id`, `item_comercial_id`, `cantidad`) VALUES (:paq_id, :itm_id, 2.00)");
    $stmtPaqItm->execute(['paq_id' => $paqueteId, 'itm_id' => $itemId]);

    $stmtOfPaq = $pdo->prepare("INSERT INTO `ofertas_paquetes_edicion` (`organizacion_id`, `edicion_id`, `paquete_id`, `estado`) VALUES (:org_id, :edicion_id, :paq_id, 'ACTIVO')");
    $stmtOfPaq->execute(['org_id' => $orgId, 'edicion_id' => $edicionId, 'paq_id' => $paqueteId]);
    $ofertaPaqueteId = (int) $pdo->lastInsertId();

    $stmtTarPaq = $pdo->prepare("INSERT INTO `tarifas_paquetes_edicion` (`oferta_paquete_id`, `moneda`, `precio`, `version_bloqueo`) VALUES (:of_id, 'PEN', 800.00, 1)");
    $stmtTarPaq->execute(['of_id' => $ofertaPaqueteId]);

    afirmar($ofertaItemId > 0 && $ofertaPaqueteId > 0, "2.4: Ofertas de Ítem (#{$ofertaItemId}) y Paquete (#{$ofertaPaqueteId}) listas");

    // 2.6 Crear Cotización en BORRADOR y armar líneas
    $cot = $cotizacionServicio->crearBorrador(
        organizacionId: $orgId,
        edicionId: $edicionId,
        clienteId: $clienteId,
        titulo: 'Presupuesto Integral Candelaria',
        oportunidadId: $oportunidadId,
        terminosCondiciones: 'Condiciones de venta pactadas al 100%. Validez garantizada.',
        notasInternas: 'Cliente corporativo vip.',
        contexto: $ctxAdmin
    );
    $cotId = (int) $cot->id;

    // Agregar línea de ítem
    $cot = $cotizacionServicio->agregarLineaItem(
        organizacionId: $orgId,
        cotizacionId: $cotId,
        itemComercialId: $itemId,
        cantidad: 2.00,
        contexto: $ctxAdmin
    );

    // Agregar línea de paquete
    $cot = $cotizacionServicio->agregarLineaPaquete(
        organizacionId: $orgId,
        cotizacionId: $cotId,
        paqueteId: $paqueteId,
        cantidad: 1.00,
        contexto: $ctxAdmin
    );

    // Aplicar descuento global
    $cot = $cotizacionServicio->aplicarDescuentoGlobal(
        organizacionId: $orgId,
        cotizacionId: $cotId,
        tipo: TipoDescuentoCotizacion::MONTO_FIJO,
        valor: 100.00,
        motivo: 'Descuento comercial fidelización',
        contexto: $ctxAdmin
    );

    // Emitir cotización
    $cot = $cotizacionServicio->emitir(
        organizacionId: $orgId,
        cotizacionId: $cotId,
        validoHastaManual: date('Y-m-d', strtotime('+15 days')),
        contexto: $ctxAdmin
    );
    afirmar($cot->estado === EstadoCotizacion::EMITIDA, "2.5: Cotización #{$cotId} emitida exitosamente con correlativo {$cot->correlativo}");

    // ==============================================================================
    // BLOQUE 3: CONVERSIÓN EXCLUSIVA DESDE ACEPTADA Y RECHAZO DE OTROS ESTADOS
    // ==============================================================================
    echo "\n--- BLOQUE 3: REGLAS DE ESTADO Y CONVERSIÓN EXCLUSIVA DESDE ACEPTADA ---\n";

    // 3.1 Rechazar conversión si cotización está en EMITIDA (no aceptada)
    $errorEmitida = false;
    try {
        $ventaServicio->crearDesdeCotizacion($orgId, $cotId, $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $errorEmitida = str_contains($e->getMessage(), 'Solo se pueden convertir en venta cotizaciones en estado ACEPTADA');
    }
    afirmar($errorEmitida, "3.1: Rechazada conversión desde cotización en estado EMITIDA");

    // 3.2 Aceptar formalmente la cotización
    $cot = $cotizacionServicio->aceptar(
        organizacionId: $orgId,
        cotizacionId: $cotId,
        contexto: $ctxAdmin
    );
    afirmar($cot->estado === EstadoCotizacion::ACEPTADA, "3.2: Cotización formalmente ACEPTADA");

    // 3.3 Aceptación de cotización continúa SIN marcar GANADA la oportunidad
    $stmtCheckOp = $pdo->prepare("SELECT `etapa` FROM `crm_oportunidades` WHERE `id` = :id");
    $stmtCheckOp->execute(['id' => $oportunidadId]);
    $etapaOp = $stmtCheckOp->fetchColumn();
    afirmar($etapaOp === 'COTIZACION', "3.3: Aceptación de cotización NO altera la oportunidad a GANADA (se mantiene en '{$etapaOp}')");

    // ==============================================================================
    // BLOQUE 4: SEGURIDAD, RBAC Y VALIDACIÓN FAIL-CLOSED DE MONEDA
    // ==============================================================================
    echo "\n--- BLOQUE 4: SEGURIDAD, RBAC Y MONEDA INSTITUCIONAL FAIL-CLOSED ---\n";

    // 4.1 Operador sin permiso 'ventas.crear_desde_cotizacion' es bloqueado con 403
    $bloqueoRbac = false;
    try {
        $ventaServicio->crearDesdeCotizacion($orgId, $cotId, $ctxOp);
    } catch (AccesoDenegadoExcepcion $e) {
        $bloqueoRbac = str_contains($e->getMessage(), 'ventas.crear_desde_cotizacion');
    }
    afirmar($bloqueoRbac, "4.1: Operador sin permiso 'ventas.crear_desde_cotizacion' es rechazado con AccesoDenegadoExcepcion");

    // 4.2 Tenant isolation: Operador de otra organización no puede convertir la cotización
    $bloqueoTenant = false;
    try {
        $ventaServicio->crearDesdeCotizacion($orgIdAjena, $cotId, $ctxAjeno);
    } catch (InvalidArgumentException|AccesoDenegadoExcepcion $e) {
        $bloqueoTenant = true;
    }
    afirmar($bloqueoTenant, "4.2: Anti-IDOR: Rechazada conversión de cotización ajena al tenant");

    // 4.3 Moneda institucional FAIL-CLOSED: Detección si difiere de cotización
    $stmtUpMon = $pdo->prepare("UPDATE `cotizaciones` SET `moneda` = 'USD' WHERE `id` = :id");
    $stmtUpMon->execute(['id' => $cotId]);

    $bloqueoMonedaDif = false;
    try {
        $ventaServicio->crearDesdeCotizacion($orgId, $cotId, $ctxAdmin);
    } catch (RuntimeException $e) {
        $bloqueoMonedaDif = str_contains($e->getMessage(), 'no coincide con la moneda institucional');
    }
    afirmar($bloqueoMonedaDif, "4.3: Moneda cotización ('USD') != institucional ('PEN') produce Fail-Closed inmediato");

    // Restaurar moneda original de la cotización
    $stmtUpMonRestore = $pdo->prepare("UPDATE `cotizaciones` SET `moneda` = 'PEN' WHERE `id` = :id");
    $stmtUpMonRestore->execute(['id' => $cotId]);

    // 4.4 Moneda institucional ausente o inválida produce Fail-Closed
    // Probamos con una llamada simulada a validarFailClosed directamente en un mock/servicio
    $failClosedMissing = false;
    try {
        // Simulamos temporalmente parámetro inexistente
        $pdo->exec("UPDATE `parametros_configuracion` SET `valor` = '' WHERE `codigo` = 'plataforma.moneda_principal'");
        $configServicioRefreshed = new ConfiguracionServicio(auditoriaRepo: $auditoriaRepo, pdo: $pdo);
        $ventaServicioMon = new VentaServicio(
            ventaRepo: $ventaRepo,
            cotizacionRepo: $cotizacionRepo,
            clienteRepo: $clienteRepo,
            personaRepo: $personaRepo,
            edicionRepo: $edicionRepo,
            configServicio: $configServicioRefreshed,
            authzServicio: $authzServicio,
            auditoriaRepo: $auditoriaRepo,
            oportunidadServicio: $oportunidadServicio,
            pdo: $pdo
        );
        $ventaServicioMon->crearDesdeCotizacion($orgId, $cotId, $ctxAdmin);
    } catch (RuntimeException $e) {
        $failClosedMissing = str_contains($e->getMessage(), 'Fail-Closed');
    } finally {
        $pdo->exec("UPDATE `parametros_configuracion` SET `valor` = 'PEN' WHERE `codigo` = 'plataforma.moneda_principal'");
    }
    afirmar($failClosedMissing, "4.4: Moneda institucional vacía o ausente produce Fail-Closed estricto (cero fallback 'PEN')");

    // ==============================================================================
    // BLOQUE 5: CONVERSIÓN ATÓMICA DE VENTA, CORRELATIVO Y SNAPSHOT INMUTABLE
    // ==============================================================================
    echo "\n--- BLOQUE 5: CONVERSIÓN ATÓMICA, CORRELATIVO Y SNAPSHOT INMUTABLE ---\n";

    // 5.1 Conversión formal exitosa
    $venta = $ventaServicio->crearDesdeCotizacion($orgId, $cotId, $ctxAdmin);
    $ventaId = (int) $venta->id;

    afirmar($ventaId > 0, "5.1: Venta formal creada exitosamente (#{$ventaId})");
    afirmar($venta->estado === EstadoVenta::CONFIRMADA, "5.2: Estado inicial de la venta es estrictamente CONFIRMADA");
    afirmar(str_starts_with($venta->correlativo, 'VTA-2026-'), "5.3: Correlativo asignado con formato soberano: {$venta->correlativo}");
    afirmar($venta->cotizacionId === $cotId, "5.4: Procedencia de cotización #{$cotId} preservada");
    afirmar($venta->origenTipo === TipoOrigenVenta::COTIZACION, "5.5: Tipo de origen es COTIZACION");

    // 5.6 Snapshot de Cliente
    afirmar($venta->clienteNombreCompleto === 'Cliente Sintetico Venta', "5.6: Snapshot cliente: nombre completo congelado ('{$venta->clienteNombreCompleto}')");
    afirmar($venta->clienteNumeroDocumento === $dniSintetico, "5.7: Snapshot cliente: documento congelado ('{$venta->clienteNumeroDocumento}')");
    afirmar($venta->clienteTelefono === '951000111', "5.8: Snapshot cliente: teléfono congelado ('{$venta->clienteTelefono}')");

    // 5.9 Snapshot Económico
    afirmar($venta->subtotal === 1300.00, "5.9: Subtotal oficial coincide exactamente (2*250 + 1*800 = 1300.00)");
    afirmar($venta->descuentoGlobalMonto === 100.00, "5.10: Descuento global congelado (100.00)");
    afirmar($venta->total === 1200.00, "5.11: Total neto formal coincide exactamente (1300 - 100 = 1200.00)");
    afirmar($venta->moneda === 'PEN', "5.12: Moneda formal congelada ('PEN')");
    afirmar(!empty($venta->terminosCondiciones), "5.13: Términos y condiciones congelados de forma inmutable");

    // 5.14 Snapshot de Líneas y Componentes
    afirmar(count($venta->lineas) === 2, "5.14: La venta contiene exactamente las 2 líneas cotizadas");
    $lineaItem = null;
    $lineaPaq = null;
    foreach ($venta->lineas as $l) {
        if ($l->tipoLinea === TipoLineaVenta::ITEM) $lineaItem = $l;
        if ($l->tipoLinea === TipoLineaVenta::PAQUETE) $lineaPaq = $l;
    }

    afirmar($lineaItem !== null && $lineaItem->itemComercialId === $itemId && $lineaItem->ofertaItemId === $ofertaItemId, "5.15: Procedencia de ítem y oferta_item_id preservada");
    afirmar($lineaPaq !== null && $lineaPaq->paqueteId === $paqueteId && $lineaPaq->ofertaPaqueteId === $ofertaPaqueteId, "5.16: Procedencia de paquete y oferta_paquete_id preservada");
    afirmar(count($lineaPaq->componentes) === 1, "5.17: Línea de paquete contiene su snapshot relacional inmutable de componentes");
    afirmar($lineaPaq->componentes[0]->itemCodigo === $codItem, "5.18: Componente congeló el código de ítem comercial '{$codItem}'");

    // ==============================================================================
    // BLOQUE 6: CARDINALIDAD 1:1, PREVENCIÓN DE DOBLE CONVERSIÓN Y CONCURRENCIA
    // ==============================================================================
    echo "\n--- BLOQUE 6: CARDINALIDAD 1:1 Y PREVENCIÓN DE DOBLE CONVERSIÓN ---\n";

    // 6.1 Intento de convertir nuevamente la misma cotización aceptada arroja excepción
    $dobleConversionBloqueada = false;
    try {
        $ventaServicio->crearDesdeCotizacion($orgId, $cotId, $ctxAdmin);
    } catch (RuntimeException $e) {
        $dobleConversionBloqueada = str_contains($e->getMessage(), 'ya fue convertida previamente');
    }
    afirmar($dobleConversionBloqueada, "6.1: Regla 1:1: Doble conversión bloqueada en dominio");

    // 6.2 Verificación de constraint UNIQUE uk_ventas_cotizacion en base de datos
    $violacionUniqueBd = false;
    try {
        $stmtDup = $pdo->prepare("INSERT INTO `ventas` (`organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `correlativo`, `fecha_venta`, `estado`, `cliente_nombre_completo`, `moneda`, `subtotal`, `total`, `creado_por`) VALUES (:org_id, :ed_id, :cli_id, :cot_id, 'VTA-2026-999999', '2026-02-05', 'CONFIRMADA', 'Cliente Dup', 'PEN', 100, 100, 1)");
        $stmtDup->execute(['org_id' => $orgId, 'ed_id' => $edicionId, 'cli_id' => $clienteId, 'cot_id' => $cotId]);
    } catch (\PDOException $e) {
        $violacionUniqueBd = str_contains($e->getMessage(), 'uk_ventas_cotizacion') || $e->getCode() === '23000';
    }
    afirmar($violacionUniqueBd, "6.2: Restricción física UNIQUE uk_ventas_cotizacion impide a nivel de base de datos doble venta");

    // ==============================================================================
    // BLOQUE 7: INTEGRACIÓN CRM: TRANSICIÓN ATÓMICA A 'GANADA' Y ROLLBACK EN CASO DE FALLO
    // ==============================================================================
    echo "\n--- BLOQUE 7: INTEGRACIÓN CRM ATÓMICA A 'GANADA' Y ROLLBACK DE TRANSACCIÓN ---\n";

    // 7.1 Verificar que la oportunidad pasó a GANADA al crearse la venta
    $stmtCheckOpGanada = $pdo->prepare("SELECT `etapa` FROM `crm_oportunidades` WHERE `id` = :id");
    $stmtCheckOpGanada->execute(['id' => $oportunidadId]);
    $etapaGanada = $stmtCheckOpGanada->fetchColumn();
    afirmar($etapaGanada === 'GANADA', "7.1: Transición atómica exitosa: Oportunidad CRM avanzó legítimamente a 'GANADA'");

    // 7.2 Verificar que si la transición de CRM falla, la creación de la venta se revierte con ROLLBACK
    // Preparamos una cotización aceptada vinculada a una oportunidad ya terminal (PERDIDA)
    $stmtOpPerdida = $pdo->prepare("INSERT INTO `crm_oportunidades` (`organizacion_id`, `edicion_id`, `cliente_id`, `titulo`, `etapa`, `moneda`, `motivo_perdida`) VALUES (:org_id, :edicion_id, :cliente_id, 'Op Ya Terminal', 'PERDIDA', 'PEN', 'PRECIO')");
    $stmtOpPerdida->execute(['org_id' => $orgId, 'edicion_id' => $edicionId, 'cliente_id' => $clienteId]);
    $opTerminalId = (int) $pdo->lastInsertId();

    $cotFallida = $cotizacionServicio->crearBorrador(
        organizacionId: $orgId,
        edicionId: $edicionId,
        clienteId: $clienteId,
        titulo: 'Presupuesto Fallo CRM',
        oportunidadId: $opTerminalId,
        contexto: $ctxAdmin
    );
    $cotizacionServicio->agregarLineaItem($orgId, (int) $cotFallida->id, $itemId, 1.00, contexto: $ctxAdmin);
    $cotFallida = $cotizacionServicio->emitir($orgId, (int) $cotFallida->id, date('Y-m-d', strtotime('+5 days')), contexto: $ctxAdmin);
    $cotFallida = $cotizacionServicio->aceptar($orgId, (int) $cotFallida->id, $ctxAdmin);

    $rollbackTotalExitoso = false;
    try {
        $ventaServicio->crearDesdeCotizacion($orgId, (int) $cotFallida->id, $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        // Falló porque no se puede transicionar de PERDIDA a GANADA
        $rollbackTotalExitoso = true;
    }
    afirmar($rollbackTotalExitoso, "7.2: Excepción capturada cuando CRM rechaza la transición a GANADA");

    // Verificar que NO existe venta creada para esa cotización
    $existeVentaFallida = $ventaRepo->existePorCotizacionId((int) $cotFallida->id, $orgId);
    afirmar(!$existeVentaFallida, "7.3: ROLLBACK TOTAL: No existe venta creada tras el fallo en CRM");

    // ==============================================================================
    // BLOQUE 8: PROCEDENCIA Y POLÍTICA DE FOREIGN KEYS (ON DELETE RESTRICT)
    // ==============================================================================
    echo "\n--- BLOQUE 8: PROCEDENCIA Y POLÍTICA DE FOREIGN KEYS (RESTRICT) ---\n";

    // 8.1 Intentar eliminar cotización origen referenciada por venta
    $bloqueoDeleteCot = false;
    try {
        $stmtDel = $pdo->prepare("DELETE FROM `cotizaciones` WHERE `id` = :id");
        $stmtDel->execute(['id' => $cotId]);
    } catch (\PDOException $e) {
        $bloqueoDeleteCot = $e->getCode() === '23000';
    }
    afirmar($bloqueoDeleteCot, "8.1: FK cotizacion_id ON DELETE RESTRICT: Prohibido eliminar cotización vendida");

    // 8.2 Intentar eliminar línea de venta referenciada
    $bloqueoDeleteLinea = false;
    try {
        $stmtDelLinea = $pdo->prepare("DELETE FROM `cotizacion_lineas` WHERE `id` = :id");
        $stmtDelLinea->execute(['id' => $lineaItem->cotizacionLineaId]);
    } catch (\PDOException $e) {
        $bloqueoDeleteLinea = $e->getCode() === '23000';
    }
    afirmar($bloqueoDeleteLinea, "8.2: FK cotizacion_linea_id ON DELETE RESTRICT: Prohibido eliminar línea cotizada vinculada");

    // 8.3 Intentar eliminar venta con líneas
    $bloqueoDeleteVenta = false;
    try {
        $stmtDelVenta = $pdo->prepare("DELETE FROM `ventas` WHERE `id` = :id");
        $stmtDelVenta->execute(['id' => $ventaId]);
    } catch (\PDOException $e) {
        $bloqueoDeleteVenta = $e->getCode() === '23000';
    }
    afirmar($bloqueoDeleteVenta, "8.3: FK venta_id ON DELETE RESTRICT: Prohibido borrado físico en cascada de venta");

    // ==============================================================================
    // BLOQUE 9: CONCURRENCIA OPTIMISTA, CANCELACIÓN Y ANULACIÓN CON REGLAS DE DETALLE
    // ==============================================================================
    echo "\n--- BLOQUE 9: CONCURRENCIA OPTIMISTA, CANCELACIÓN Y ANULACIÓN ---\n";

    // 9.1 Cancelación comercial: motivo OTRO exige detalle
    $errorDetalleCancel = false;
    try {
        $ventaServicio->cancelar(
            organizacionId: $orgId,
            ventaId: $ventaId,
            motivo: MotivoCancelacionVenta::OTRO,
            motivoDetalle: '',
            versionBloqueoEsperada: $venta->versionBloqueo,
            contexto: $ctxAdmin
        );
    } catch (InvalidArgumentException $e) {
        $errorDetalleCancel = str_contains($e->getMessage(), 'detalle explicativo');
    }
    afirmar($errorDetalleCancel, "9.1: Cancelación con motivo OTRO exige obligatoriamente detalle");

    // 9.2 Concurrencia optimista en cancelación: versión desfasada arroja ConflictoConcurrenciaExcepcion
    $errorVersionCancel = false;
    try {
        $ventaServicio->cancelar(
            organizacionId: $orgId,
            ventaId: $ventaId,
            motivo: MotivoCancelacionVenta::DESISTIMIENTO_CLIENTE,
            motivoDetalle: null,
            versionBloqueoEsperada: 999, // versión desfasada
            contexto: $ctxAdmin
        );
    } catch (ConflictoConcurrenciaExcepcion $e) {
        $errorVersionCancel = true;
    }
    afirmar($errorVersionCancel, "9.2: Optimistic Locking: ConflictoConcurrenciaExcepcion ante versión desfasada");

    // 9.3 Cancelación exitosa
    $ventaCancelada = $ventaServicio->cancelar(
        organizacionId: $orgId,
        ventaId: $ventaId,
        motivo: MotivoCancelacionVenta::DESISTIMIENTO_CLIENTE,
        motivoDetalle: 'Cliente no podrá viajar a Puno por motivos laborales',
        versionBloqueoEsperada: $venta->versionBloqueo,
        contexto: $ctxAdmin
    );
    afirmar($ventaCancelada->estado === EstadoVenta::CANCELADA, "9.3: Venta transicionó formalmente a CANCELADA");
    afirmar($ventaCancelada->versionBloqueo === 2, "9.4: Version de bloqueo incrementada a 2 tras cancelación");

    // 9.4 Intentar cancelar o anular una venta ya terminal
    $errorCancelTerminal = false;
    try {
        $ventaServicio->anular(
            organizacionId: $orgId,
            ventaId: $ventaId,
            motivo: MotivoAnulacionVenta::ERROR_REGISTRO,
            motivoDetalle: null,
            versionBloqueoEsperada: $ventaCancelada->versionBloqueo,
            contexto: $ctxAdmin
        );
    } catch (InvalidArgumentException $e) {
        $errorCancelTerminal = str_contains($e->getMessage(), 'Solo se pueden anular ventas en estado CONFIRMADA');
    }
    afirmar($errorCancelTerminal, "9.5: Venta CANCELADA no puede volver a ser anulada (estado terminal respetado)");

    // 9.6 Probar Anulación en una nueva venta sintética
    $cotParaAnular = $cotizacionServicio->crearBorrador($orgId, $edicionId, $clienteId, 'Venta para Anular', contexto: $ctxAdmin);
    $cotizacionServicio->agregarLineaItem($orgId, (int) $cotParaAnular->id, $itemId, 1.00, contexto: $ctxAdmin);
    $cotParaAnular = $cotizacionServicio->emitir($orgId, (int) $cotParaAnular->id, date('Y-m-d', strtotime('+3 days')), contexto: $ctxAdmin);
    $cotParaAnular = $cotizacionServicio->aceptar($orgId, (int) $cotParaAnular->id, $ctxAdmin);

    $ventaParaAnular = $ventaServicio->crearDesdeCotizacion($orgId, (int) $cotParaAnular->id, $ctxAdmin);
    afirmar($ventaParaAnular->estado === EstadoVenta::CONFIRMADA, "9.6: Segunda venta de prueba creada en estado CONFIRMADA");

    // Anular con motivo FRAUDE_SUPLANTACION
    $ventaAnulada = $ventaServicio->anular(
        organizacionId: $orgId,
        ventaId: (int) $ventaParaAnular->id,
        motivo: MotivoAnulacionVenta::FRAUDE_SUPLANTACION,
        motivoDetalle: 'Tarjeta sospechosa no validada',
        versionBloqueoEsperada: $ventaParaAnular->versionBloqueo,
        contexto: $ctxAdmin
    );
    afirmar($ventaAnulada->estado === EstadoVenta::ANULADA, "9.7: Venta transicionó formalmente a ANULADA");

    // ==============================================================================
    // BLOQUE 10: TRAZABILIDAD DE AUDITORÍA Y ZERO-PII
    // ==============================================================================
    echo "\n--- BLOQUE 10: AUDITORÍA TÉCNICA TRANSVERSAL Y ZERO-PII ---\n";

    $stmtAud = $pdo->prepare("SELECT `accion`, `entidad_tipo`, `entidad_id` FROM `auditoria_operaciones` WHERE `modulo` = 'ventas' AND `entidad_id` = :id ORDER BY `id` ASC");
    $stmtAud->execute(['id' => (string) $ventaId]);
    $eventosAuditados = $stmtAud->fetchAll(PDO::FETCH_ASSOC);

    $acciones = array_column($eventosAuditados, 'accion');
    afirmar(in_array('VENTA_CREADA_DESDE_COTIZACION', $acciones, true), "10.1: Evento 'VENTA_CREADA_DESDE_COTIZACION' registrado en auditoria_operaciones");
    afirmar(in_array('VENTA_CANCELADA', $acciones, true), "10.2: Evento 'VENTA_CANCELADA' registrado en auditoria_operaciones");

} finally {
    // Revertir transacciones de prueba deterministamente para dejar la base de datos limpia
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n[INFO] Transacción de pruebas F2.5B revertida con ROLLBACK determinista.\n";
    }
}

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.5B: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
exit(0);
