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
use Aplicacion\Crm\ClienteServicio;
use Aplicacion\Crm\OportunidadServicio;
use Aplicacion\Crm\OrigenComercialServicio;
use Aplicacion\Entidades\CategoriaItem;
use Aplicacion\Entidades\ItemComercial;
use Aplicacion\Entidades\Paquete;
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
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;
use RuntimeException;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS F2.4B: DOMINIO Y PERSISTENCIA DE COTIZACIONES\n";
echo "SECUENCIAS, SNAPSHOTS, DESCUENTOS, EMISIÓN, REVISIONES, RBAC Y ANTI-IDOR\n";
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

// Repositorios y Servicios
$auditoriaRepo            = new AuditoriaRepositorio($pdo);
$authzServicio            = new AutorizacionServicio(pdo: $pdo);
$configServicio           = new ConfiguracionServicio(auditoriaRepo: $auditoriaRepo, pdo: $pdo);
$edicionRepo              = new EdicionRepositorio($pdo);
$clienteRepo              = new ClienteRepositorio($pdo);
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

// ==============================================================================
// BLOQUE 1: ESQUEMA RELACIONAL, PARIDAD DE TABLAS Y RBAC
// ==============================================================================
echo "\n--- BLOQUE 1: ESQUEMA RELACIONAL Y PARIDAD DE INSTALACIÓN LIMPIA ---\n";

$tablasEsperadas = [
    'cotizaciones_secuencias',
    'cotizaciones',
    'cotizacion_lineas',
    'cotizacion_linea_componentes',
];

$stmtTablas = $pdo->query("SHOW TABLES");
$tablasBd = $stmtTablas->fetchAll(PDO::FETCH_COLUMN);

foreach ($tablasEsperadas as $t) {
    afirmar(in_array($t, $tablasBd, true), "1.1: Tabla obligatoria '{$t}' existe en la base de datos");
}
afirmar(count($tablasBd) >= 39, "1.2: El esquema actual contiene al menos 39 tablas oficiales");

// 1.3 Registro de migraciones 000011 y 000012 en migraciones_control
$stmtMig11 = $pdo->prepare("SELECT COUNT(*) FROM `migraciones_control` WHERE `migracion` = '2026_10_06_000011_crear_modulo_cotizaciones_dominio_y_rbac.sql'");
$stmtMig11->execute();
afirmar((int) $stmtMig11->fetchColumn() === 1, "1.3a: Migración 000011 registrada en 'migraciones_control' (Lote 9)");

$stmtMig12 = $pdo->prepare("SELECT COUNT(*) FROM `migraciones_control` WHERE `migracion` = '2026_10_06_000012_ajustar_contratos_soberanos_cotizaciones.sql'");
$stmtMig12->execute();
afirmar((int) $stmtMig12->fetchColumn() === 1, "1.3b: Migración 000012 registrada en 'migraciones_control' (Lote 10)");

// 1.4 Módulo 20 registrado en 'modulos'
$stmtMod20 = $pdo->prepare("SELECT COUNT(*) FROM `modulos` WHERE `id` = 20 AND `codigo` = 'cotizaciones'");
$stmtMod20->execute();
afirmar((int) $stmtMod20->fetchColumn() === 1, "1.4: Módulo 20 ('cotizaciones') registrado formalmente");

// 1.5 Permisos RBAC de cotizaciones (exactamente 9 permisos soberanos)
$stmtPerms = $pdo->query("SELECT `codigo` FROM `permisos` WHERE `modulo_id` = 20 ORDER BY `id` ASC");
$permsBd = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);
$permsEsperados = [
    'cotizaciones.ver',
    'cotizaciones.crear',
    'cotizaciones.editar',
    'cotizaciones.emitir',
    'cotizaciones.crear_revision',
    'cotizaciones.aceptar',
    'cotizaciones.rechazar',
    'cotizaciones.anular',
    'cotizaciones.aplicar_descuento',
];
$diffPerms = array_diff($permsEsperados, $permsBd);
afirmar(empty($diffPerms) && count($permsBd) === 9, "1.5: Los 9 permisos RBAC soberanos de 'cotizaciones.*' están formalmente registrados");
afirmar(!in_array('cotizaciones.eliminar', $permsBd, true), "1.5b: RBAC Soberano: NO existe permiso 'cotizaciones.eliminar'");
afirmar(in_array('cotizaciones.crear_revision', $permsBd, true), "1.5c: RBAC Soberano: Sí existe permiso 'cotizaciones.crear_revision'");
afirmar(in_array('cotizaciones.aplicar_descuento', $permsBd, true), "1.5d: RBAC Soberano: Sí existe permiso 'cotizaciones.aplicar_descuento'");

// 1.5e Exactamente 6 estados soberanos en el dominio
afirmar(count(EstadoCotizacion::cases()) === 6, "1.5e: El enum EstadoCotizacion contiene exactamente 6 estados soberanos");
afirmar(EstadoCotizacion::tryFrom('SUPERADA_POR_REVISION') === null, "1.5f: SUPERADA_POR_REVISION NO existe como estado en EstadoCotizacion");
afirmar(MotivoAnulacionCotizacion::SUPERADA_POR_REVISION->value === 'SUPERADA_POR_REVISION', "1.5g: SUPERADA_POR_REVISION existe válidamente como motivo en MotivoAnulacionCotizacion");
afirmar(MotivoRechazoCotizacion::tryFrom('SUPERADA_POR_REVISION') === null, "1.5h: SUPERADA_POR_REVISION no es motivo de rechazo (solo de anulación)");

// 1.6 Asignación de roles RBAC
$stmtR1 = $pdo->query("SELECT COUNT(*) FROM `rol_permisos` rp JOIN `permisos` p ON rp.`permiso_id` = p.`id` WHERE rp.`rol_id` = 1 AND p.`modulo_id` = 20");
afirmar((int) $stmtR1->fetchColumn() === 9, "1.6: Superadministrador (Rol 1) tiene asignados los 9 permisos de cotizaciones");

$stmtR2 = $pdo->query("SELECT COUNT(*) FROM `rol_permisos` rp JOIN `permisos` p ON rp.`permiso_id` = p.`id` WHERE rp.`rol_id` = 2 AND p.`modulo_id` = 20");
afirmar((int) $stmtR2->fetchColumn() === 9, "1.7: Administrador (Rol 2) tiene asignados los 9 permisos de cotizaciones");

$stmtR3 = $pdo->query("SELECT p.`codigo` FROM `rol_permisos` rp JOIN `permisos` p ON rp.`permiso_id` = p.`id` WHERE rp.`rol_id` = 3 AND p.`modulo_id` = 20");
$permsR3 = $stmtR3->fetchAll(PDO::FETCH_COLUMN);
afirmar($permsR3 === ['cotizaciones.ver'], "1.8: Operador (Rol 3) tiene ÚNICAMENTE permiso de solo lectura 'cotizaciones.ver'");

// 1.9 Instalación limpia temporal y paridad de tablas
$dbTemp = 'candelaria_temp_cot_' . substr(bin2hex(random_bytes(4)), 0, 8);
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

    afirmar(in_array('cotizaciones_secuencias', $tablasTemp, true), "1.9: Instalación limpia incluye 'cotizaciones_secuencias'");
    afirmar(in_array('cotizaciones', $tablasTemp, true), "1.10: Instalación limpia incluye 'cotizaciones'");
    afirmar(in_array('cotizacion_lineas', $tablasTemp, true), "1.11: Instalación limpia incluye 'cotizacion_lineas'");
    afirmar(in_array('cotizacion_linea_componentes', $tablasTemp, true), "1.12: Instalación limpia incluye 'cotizacion_linea_componentes'");
    afirmar(count($tablasTemp) >= 39, "1.13: Instalación limpia de 'esquema_base.sql' crea al menos las 39 tablas oficiales del sistema");
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbTemp}`");
}

// ==============================================================================
// PREPARACIÓN DE ENTORNOS DE PRUEBA DETERMINISTAS (TRANSACCIÓN CONTROLADA)
// ==============================================================================
$pdo->beginTransaction();

try {
    $tenantA = 10000;
    $tenantB = 20000;

    // Crear Tenant B si no existe
    $stmtOrgB = $pdo->prepare("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `estado`) VALUES (:id, 'tenant_cot_b', 'Tenant Cotizaciones B', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $stmtOrgB->execute(['id' => $tenantB]);

    // Crear Ediciones para Tenant A y Tenant B
    $stmtEdA = $pdo->prepare("
        INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`)
        VALUES (:org_id, 'edicion-cot-2026-a', 'CANDELARIA 2026 A', 2026, 'OPERACION', '2026-02-01', '2026-02-15', 1)
        ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)
    ");
    $stmtEdA->execute(['org_id' => $tenantA]);
    $edicionAId = (int) $pdo->lastInsertId();

    $stmtEdB = $pdo->prepare("
        INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`)
        VALUES (:org_id, 'edicion-cot-2026-b', 'CANDELARIA 2026 B', 2026, 'OPERACION', '2026-02-01', '2026-02-15', 1)
        ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)
    ");
    $stmtEdB->execute(['org_id' => $tenantB]);
    $edicionBId = (int) $pdo->lastInsertId();

    // Crear Clientes en Tenant A y Tenant B
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (77101, 10000, 'NATURAL', 'CARLOS', 'MENDOZA', '+51951000111', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `clientes` (`id`, `organizacion_id`, `persona_id`, `estado_comercial`) VALUES (55101, 10000, 77101, 'CLIENTE') ON DUPLICATE KEY UPDATE `estado_comercial` = 'CLIENTE'");
    $clienteAId = 55101;

    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (77102, 20000, 'NATURAL', 'ROBERTO', 'VARGAS', '+51951000222', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `clientes` (`id`, `organizacion_id`, `persona_id`, `estado_comercial`) VALUES (55102, 20000, 77102, 'CLIENTE') ON DUPLICATE KEY UPDATE `estado_comercial` = 'CLIENTE'");
    $clienteBId = 55102;

    // Crear Origen Comercial y Oportunidades en Tenant A
    $pdo->exec("INSERT INTO `origenes_comerciales` (`id`, `organizacion_id`, `codigo`, `nombre`, `activo`, `orden`) VALUES (33101, 10000, 'WEB_COT', 'Web Cotizaciones', 1, 1) ON DUPLICATE KEY UPDATE `activo` = 1");
    $pdo->exec("INSERT INTO `crm_oportunidades` (`id`, `organizacion_id`, `edicion_id`, `cliente_id`, `usuario_asignado_id`, `origen_comercial_id`, `titulo`, `etapa`, `valor_estimado`, `moneda`, `version_bloqueo`) VALUES (99101, 10000, {$edicionAId}, {$clienteAId}, 24, 33101, 'Oportunidad Candelaria 2026', 'NUEVA', 1500.00, 'PEN', 1) ON DUPLICATE KEY UPDATE `etapa` = 'NUEVA'");
    $oportunidadAId = 99101;

    // Crear Catálogo en Tenant A: Categoría, Ítems, Ofertas, Tarifas
    $pdo->exec("INSERT INTO `categorias_items` (`id`, `organizacion_id`, `codigo`, `nombre`, `estado`, `orden`) VALUES (11101, 10000, 'GRAN_PARADA_COT', 'Tribunas Gran Parada', 'ACTIVO', 1) ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $categoriaId = 11101;

    // Ítem 1: Entrada Tribuna Central (Servicio, TICKET)
    $pdo->exec("INSERT INTO `items_comerciales` (`id`, `organizacion_id`, `categoria_id`, `codigo`, `nombre`, `descripcion`, `tipo`, `unidad_medida`, `estado`) VALUES (22101, 10000, {$categoriaId}, 'TRIB_CENTRAL', 'Tribuna Central Asiento Numerado', 'Asiento VIP con visibilidad completa', 'SERVICIO', 'TICKET', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $item1Id = 22101;
    $pdo->exec("INSERT INTO `ofertas_items_edicion` (`id`, `organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`) VALUES (44101, 10000, {$edicionAId}, {$item1Id}, 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $ofertaItem1Id = 44101;
    $pdo->exec("INSERT INTO `tarifas_items_edicion` (`id`, `oferta_item_id`, `moneda`, `precio`, `version_bloqueo`) VALUES (66101, {$ofertaItem1Id}, 'PEN', 250.00, 1) ON DUPLICATE KEY UPDATE `precio` = 250.00");

    // Ítem 2: Almuerzo Típico Puneño (Producto, UNIDAD)
    $pdo->exec("INSERT INTO `items_comerciales` (`id`, `organizacion_id`, `categoria_id`, `codigo`, `nombre`, `descripcion`, `tipo`, `unidad_medida`, `estado`) VALUES (22102, 10000, {$categoriaId}, 'ALMUERZO_PUNENO', 'Almuerzo Típico Buffet', 'Almuerzo tradicional en restaurante céntrico', 'PRODUCTO', 'UNIDAD', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $item2Id = 22102;
    $pdo->exec("INSERT INTO `ofertas_items_edicion` (`id`, `organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`) VALUES (44102, 10000, {$edicionAId}, {$item2Id}, 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $ofertaItem2Id = 44102;
    $pdo->exec("INSERT INTO `tarifas_items_edicion` (`id`, `oferta_item_id`, `moneda`, `precio`, `version_bloqueo`) VALUES (66102, {$ofertaItem2Id}, 'PEN', 60.00, 1) ON DUPLICATE KEY UPDATE `precio` = 60.00");

    // Ítem 3: Ítem inactivo o sin oferta
    $pdo->exec("INSERT INTO `items_comerciales` (`id`, `organizacion_id`, `categoria_id`, `codigo`, `nombre`, `descripcion`, `tipo`, `unidad_medida`, `estado`) VALUES (22103, 10000, {$categoriaId}, 'ITEM_SIN_OFERTA', 'Item Sin Oferta Activa', 'No disponible en 2026', 'SERVICIO', 'SERVICIO', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $itemSinOfertaId = 22103;

    // Paquete Comercial: PAQUETE FESTIVIDAD COMPLETO (Ítem 1 + Ítem 2)
    $pdo->exec("INSERT INTO `paquetes` (`id`, `organizacion_id`, `codigo`, `nombre`, `descripcion`, `estado`) VALUES (88101, 10000, 'PAQ_FESTIVIDAD', 'Paquete Festividad Completo', 'Tribuna + Almuerzo Buffet', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $paqueteId = 88101;
    $pdo->exec("DELETE FROM `paquete_items` WHERE `paquete_id` = {$paqueteId}");
    $pdo->exec("INSERT INTO `paquete_items` (`paquete_id`, `item_comercial_id`, `cantidad`, `orden`) VALUES ({$paqueteId}, {$item1Id}, 1.00, 1), ({$paqueteId}, {$item2Id}, 2.00, 2)");
    $pdo->exec("INSERT INTO `ofertas_paquetes_edicion` (`id`, `organizacion_id`, `edicion_id`, `paquete_id`, `estado`) VALUES (55201, 10000, {$edicionAId}, {$paqueteId}, 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $ofertaPaqueteId = 55201;
    $pdo->exec("INSERT INTO `tarifas_paquetes_edicion` (`id`, `oferta_paquete_id`, `moneda`, `precio`, `version_bloqueo`) VALUES (77201, {$ofertaPaqueteId}, 'PEN', 340.00, 1) ON DUPLICATE KEY UPDATE `precio` = 340.00");

    // Configuración institucional
    $pdo->exec("UPDATE `parametros_configuracion` SET `valor` = 'PEN' WHERE `codigo` = 'plataforma.moneda_principal'");
    $pdo->exec("INSERT INTO `parametros_configuracion` (`organizacion_id`, `codigo`, `etiqueta`, `descripcion`, `tipo_dato`, `valor`, `ambito`, `es_editable`) VALUES ({$tenantA}, 'organizacion.dias_validez_cotizacion', 'Días de Validez de Cotizaciones', 'Días de vigencia por defecto', 'INTEGER', '15', 'ORGANIZACION', 1) ON DUPLICATE KEY UPDATE `valor` = '15'");

    // Usuarios y Contextos
    // Usuario 24 (Orlando - Superadmin con todos los permisos)
    $ctxAdminTenantA = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 24,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-cot-admin-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/CotizacionesTest',
        organizacionId: $tenantA
    );

    // Usuario Operador (solo lectura)
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (81099, 10000, 'NATURAL', 'OPERADOR', 'LECTURA', '+51951999777', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`) VALUES (61099, 10000, 81099, 'operador.lectura', 'OPERADOR SOLO LECTURA', 'operador@test.com', '+51951111222', '\$2y\$10\$abcdefghijklmnopqrstuu', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (61099, 3) ON DUPLICATE KEY UPDATE `rol_id` = 3");

    $ctxOperadorTenantA = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 61099,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-cot-op-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/CotizacionesTest',
        organizacionId: $tenantA
    );

    // Ofertas para pruebas de validación cruzada
    $pdo->exec("INSERT INTO `items_comerciales` (`id`, `organizacion_id`, `categoria_id`, `codigo`, `nombre`, `tipo`, `unidad_medida`, `estado`) VALUES (22199, {$tenantB}, {$categoriaId}, 'ITEM_TB', 'Item Tenant B', 'PRODUCTO', 'UNIDAD', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado`='ACTIVO'");
    $pdo->exec("INSERT INTO `ofertas_items_edicion` (`id`, `organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`) VALUES (44199, {$tenantB}, {$edicionBId}, 22199, 'ACTIVO') ON DUPLICATE KEY UPDATE `estado`='ACTIVO'");
    $ofertaItemTenantBId = 44199;

    $pdo->exec("INSERT INTO `ediciones_candelaria` (`id`, `organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`) VALUES (99881, {$tenantA}, 'ed-otra-2025', 'CANDELARIA 2025 OTRA', 2025, 'CERRADA', '2025-02-01', '2025-02-15') ON DUPLICATE KEY UPDATE `estado`='CERRADA'");
    $pdo->exec("INSERT INTO `ofertas_items_edicion` (`id`, `organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`) VALUES (44198, {$tenantA}, 99881, {$item1Id}, 'ACTIVO') ON DUPLICATE KEY UPDATE `estado`='ACTIVO'");
    $ofertaItemOtraEdicionId = 44198;

    $pdo->exec("INSERT INTO `ofertas_items_edicion` (`id`, `organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`) VALUES (44197, {$tenantA}, {$edicionAId}, {$itemSinOfertaId}, 'INACTIVO') ON DUPLICATE KEY UPDATE `estado`='INACTIVO'");
    $ofertaItemInactivaId = 44197;

    $pdo->exec("INSERT INTO `paquetes` (`id`, `organizacion_id`, `codigo`, `nombre`, `estado`) VALUES (88199, {$tenantB}, 'PAQ_TB', 'Paquete Tenant B', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado`='ACTIVO'");
    $pdo->exec("INSERT INTO `ofertas_paquetes_edicion` (`id`, `organizacion_id`, `edicion_id`, `paquete_id`, `estado`) VALUES (55299, {$tenantB}, {$edicionBId}, 88199, 'ACTIVO') ON DUPLICATE KEY UPDATE `estado`='ACTIVO'");
    $ofertaPaqueteTenantBId = 55299;

    $pdo->exec("INSERT INTO `paquetes` (`id`, `organizacion_id`, `codigo`, `nombre`, `estado`) VALUES (88198, {$tenantA}, 'PAQ_INACT', 'Paquete Inactivo', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado`='ACTIVO'");
    $pdo->exec("INSERT INTO `ofertas_paquetes_edicion` (`id`, `organizacion_id`, `edicion_id`, `paquete_id`, `estado`) VALUES (55298, {$tenantA}, {$edicionAId}, 88198, 'INACTIVO') ON DUPLICATE KEY UPDATE `estado`='INACTIVO'");
    $ofertaPaqueteInactivaId = 55298;

    // Rol 81: Solo Editor (sin aplicar_descuento)
    $pdo->exec("INSERT INTO `roles` (`id`, `codigo`, `nombre`, `descripcion`, `es_sistema`) VALUES (81, 'solo_editor', 'Solo Editor Cotizaciones', 'Edición sin descuentos', 0) ON DUPLICATE KEY UPDATE `nombre` = 'Solo Editor Cotizaciones'");
    $pdo->exec("DELETE FROM `rol_permisos` WHERE `rol_id` = 81");
    $pdo->exec("INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES (81, 41), (81, 43)"); // ver, editar
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (81091, 10000, 'NATURAL', 'EDITOR', 'PRUEBA', '+51951999771', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`) VALUES (61091, 10000, 81091, 'solo.editor', 'SOLO EDITOR', 'editor@test.com', '+51951111221', '\$2y\$10\$abcdefghijklmnopqrstuu', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (61091, 81) ON DUPLICATE KEY UPDATE `rol_id` = 81");
    $ctxSoloEditor = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 61091,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-cot-editor-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/CotizacionesTest',
        organizacionId: $tenantA
    );

    // Rol 82: Solo Descuento (sin editar)
    $pdo->exec("INSERT INTO `roles` (`id`, `codigo`, `nombre`, `descripcion`, `es_sistema`) VALUES (82, 'solo_descuento', 'Solo Descuento Cotizaciones', 'Descuentos sin edición general', 0) ON DUPLICATE KEY UPDATE `nombre` = 'Solo Descuento Cotizaciones'");
    $pdo->exec("DELETE FROM `rol_permisos` WHERE `rol_id` = 82");
    $pdo->exec("INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES (82, 41), (82, 49)"); // ver, aplicar_descuento
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (81092, 10000, 'NATURAL', 'DESCUENTO', 'PRUEBA', '+51951999772', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`) VALUES (61092, 10000, 81092, 'solo.descuento', 'SOLO DESCUENTO', 'descuento@test.com', '+51951111222', '\$2y\$10\$abcdefghijklmnopqrstuu', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (61092, 82) ON DUPLICATE KEY UPDATE `rol_id` = 82");
    $ctxSoloDescuento = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 61092,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-cot-desc-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/CotizacionesTest',
        organizacionId: $tenantA
    );

    // Contexto Tenant B (para pruebas Cross-Tenant)
    $ctxTenantB = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 24,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-cot-tb-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/CotizacionesTest',
        organizacionId: $tenantB
    );

    // ==============================================================================
    // BLOQUE 2: ANTI-IDOR Y AISLAMIENTO MULTI-TENANT
    // ==============================================================================
    echo "\n--- BLOQUE 2: ANTI-IDOR Y AISLAMIENTO MULTI-TENANT ---\n";

    // 2.1 Intentar crear cotización con cliente de otro tenant
    $antiIdorCliente = false;
    try {
        $cotizacionServicio->crearBorrador(
            organizacionId: $tenantA,
            edicionId: $edicionAId,
            clienteId: $clienteBId, // Pertenece a Tenant B
            titulo: 'Cotización con Cliente Ajeno',
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $antiIdorCliente = true;
    }
    afirmar($antiIdorCliente, "2.1: Anti-IDOR: Rechazada creación con cliente perteneciente a otra organización");

    // 2.2 Intentar crear cotización con edición de otro tenant
    $antiIdorEdicion = false;
    try {
        $cotizacionServicio->crearBorrador(
            organizacionId: $tenantA,
            edicionId: $edicionBId, // Pertenece a Tenant B
            clienteId: $clienteAId,
            titulo: 'Cotización con Edición Ajena',
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $antiIdorEdicion = true;
    }
    afirmar($antiIdorEdicion, "2.2: Anti-IDOR: Rechazada creación con edición perteneciente a otra organización");

    // 2.3 Intentar crear cotización en Tenant A con contexto de Tenant B
    $crossTenantContexto = false;
    try {
        $cotizacionServicio->crearBorrador(
            organizacionId: $tenantA,
            edicionId: $edicionAId,
            clienteId: $clienteAId,
            titulo: 'Cotización Cross-Tenant',
            contexto: $ctxTenantB // Contexto pertenece a Tenant B
        );
    } catch (AccesoDenegadoExcepcion $e) {
        $crossTenantContexto = true;
    }
    afirmar($crossTenantContexto, "2.3: Tenant Isolation: Rechazada operación con contexto de otro tenant");

    // ==============================================================================
    // BLOQUE 3: CREACIÓN DE BORRADOR E INVARIANTES INICIALES
    // ==============================================================================
    echo "\n--- BLOQUE 3: CREACIÓN DE BORRADOR E INVARIANTES INICIALES ---\n";

    $borrador = $cotizacionServicio->crearBorrador(
        organizacionId: $tenantA,
        edicionId: $edicionAId,
        clienteId: $clienteAId,
        titulo: 'Propuesta Festividad Candelaria 2026',
        oportunidadId: $oportunidadAId,
        terminosCondiciones: 'Precios válidos por 15 días calendario.',
        notasInternas: 'Cliente VIP contactado por feria comercial.',
        contexto: $ctxAdminTenantA
    );

    afirmar($borrador->id !== null && $borrador->id > 0, "3.1: Borrador creado con ID primario autoincremental");
    afirmar($borrador->estado === EstadoCotizacion::BORRADOR, "3.2: Estado inicial es estrictamente BORRADOR");
    afirmar($borrador->correlativo === null, "3.3: Correlativo humano es estrictamente NULL en borrador");
    afirmar($borrador->correlativoBase === null, "3.4: Correlativo base es NULL en borrador");
    afirmar($borrador->versionNumero === 1, "3.5: Versión inicial es exactamente 1");
    afirmar($borrador->fechaEmision === null, "3.6: Fecha de emisión es NULL en borrador");
    afirmar($borrador->validoHasta === null, "3.7: Fecha límite de validez es NULL en borrador");
    afirmar($borrador->moneda === 'PEN', "3.8: Moneda snapshot institucional inmutable es 'PEN'");
    afirmar($borrador->subtotal === 0.00, "3.9: Subtotal inicial es exactamente 0.00");
    afirmar($borrador->total === 0.00, "3.10: Total inicial es exactamente 0.00");
    afirmar($borrador->versionBloqueo === 1, "3.11: Control de concurrencia inicia con version_bloqueo = 1");

    // Actualizar metadatos de borrador
    $borradorActualizado = $cotizacionServicio->actualizarBorrador(
        organizacionId: $tenantA,
        cotizacionId: $borrador->id,
        titulo: 'Propuesta Modificada Candelaria 2026',
        terminosCondiciones: 'Condiciones actualizadas.',
        notasInternas: 'Nota interna actualizada.',
        contexto: $ctxAdminTenantA
    );
    afirmar($borradorActualizado->titulo === 'Propuesta Modificada Candelaria 2026', "3.12: Actualización de título y términos de borrador exitosa");

    // ==============================================================================
    // BLOQUE 4: PROCEDENCIA COMERCIAL Y SNAPSHOTS DE ÍTEMS
    // ==============================================================================
    echo "\n--- BLOQUE 4: PROCEDENCIA COMERCIAL Y SNAPSHOTS DE ÍTEMS ---\n";

    // 4.1 Rechazo de ítem sin oferta activa en la edición
    $itemSinOfertaRechazado = false;
    try {
        $cotizacionServicio->agregarLineaItem(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            itemComercialId: $itemSinOfertaId,
            cantidad: 2.00,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $itemSinOfertaRechazado = true;
    }
    afirmar($itemSinOfertaRechazado, "4.1: Rechazo estricto ante ítem sin oferta comercial activa en la edición");

    // 4.1b Validación de oferta: rechazo si la oferta pertenece a otro tenant
    $ofertaItemOtroTenantRechazada = false;
    try {
        $cotizacionServicio->agregarLineaItem(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            itemComercialId: $item1Id,
            cantidad: 1.00,
            ofertaItemId: $ofertaItemTenantBId,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $ofertaItemOtroTenantRechazada = true;
    }
    afirmar($ofertaItemOtroTenantRechazada, "4.1b: Procedencia: Rechazada oferta_item_id perteneciente a otra organización (cross-tenant)");

    // 4.1c Validación de oferta: rechazo si la oferta pertenece a otra edición comercial
    $ofertaItemOtraEdicionRechazada = false;
    try {
        $cotizacionServicio->agregarLineaItem(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            itemComercialId: $item1Id,
            cantidad: 1.00,
            ofertaItemId: $ofertaItemOtraEdicionId,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $ofertaItemOtraEdicionRechazada = true;
    }
    afirmar($ofertaItemOtraEdicionRechazada, "4.1c: Procedencia: Rechazada oferta_item_id perteneciente a otra edición comercial");

    // 4.1d Validación de oferta: rechazo si la oferta no corresponde al ítem comercial solicitado
    $ofertaItemMismatchedRechazada = false;
    try {
        $cotizacionServicio->agregarLineaItem(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            itemComercialId: $item1Id, // ítem 1
            cantidad: 1.00,
            ofertaItemId: $ofertaItem2Id, // oferta del ítem 2
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $ofertaItemMismatchedRechazada = true;
    }
    afirmar($ofertaItemMismatchedRechazada, "4.1d: Procedencia: Rechazada oferta_item_id inconsistente con el maestro solicitado");

    // 4.1e Validación de oferta: rechazo si la oferta se encuentra inactiva
    $ofertaItemInactivaRechazada = false;
    try {
        $cotizacionServicio->agregarLineaItem(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            itemComercialId: $itemSinOfertaId,
            cantidad: 1.00,
            ofertaItemId: $ofertaItemInactivaId,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $ofertaItemInactivaRechazada = true;
    }
    afirmar($ofertaItemInactivaRechazada, "4.1e: Procedencia: Rechazada oferta_item_id inactiva");

    // 4.1f Invariante de entidad CotizacionLinea: ofertaItemId obligatorio para ITEM
    $entidadItemSinOferta = false;
    try {
        new \Aplicacion\Entidades\CotizacionLinea(
            id: null,
            cotizacionId: $borrador->id,
            tipoLinea: TipoLineaCotizacion::ITEM,
            itemComercialId: $item1Id,
            paqueteId: null,
            ofertaItemId: null, // Prohibido null para ITEM
            ofertaPaqueteId: null,
            conceptoCodigo: 'CODE',
            conceptoNombre: 'NAME',
            conceptoDescripcion: null,
            unidadMedida: 'UND',
            cantidad: 1.0,
            precioUnitario: 100.0,
            moneda: 'PEN'
        );
    } catch (InvalidArgumentException $e) {
        $entidadItemSinOferta = true;
    }
    afirmar($entidadItemSinOferta, "4.1f: Invariante Entidad: CotizacionLinea de tipo ITEM exige obligatoriamente ofertaItemId");

    // 4.2 Agregar línea válida de ítem comercial
    $cotConItem = $cotizacionServicio->agregarLineaItem(
        organizacionId: $tenantA,
        cotizacionId: $borrador->id,
        itemComercialId: $item1Id,
        cantidad: 2.00,
        precioUnitario: null, // Toma la tarifa vigente del catálogo (250.00)
        contexto: $ctxAdminTenantA
    );

    afirmar(count($cotConItem->lineas) === 1, "4.2: Línea de ítem agregada exitosamente a la cotización");
    $lineaItem = $cotConItem->lineas[0];
    afirmar($lineaItem->tipoLinea === TipoLineaCotizacion::ITEM, "4.3: Tipo de línea es estrictamente ITEM");
    afirmar($lineaItem->itemComercialId === $item1Id, "4.4: FK item_comercial_id preservada");
    afirmar($lineaItem->ofertaItemId === $ofertaItem1Id, "4.5: FK oferta_item_id preservada");
    afirmar($lineaItem->conceptoCodigo === 'TRIB_CENTRAL', "4.6: Snapshot del código comercial congelado");
    afirmar($lineaItem->unidadMedida === 'TICKET', "4.7: Snapshot de unidad de medida canónica ('TICKET') congelado");
    afirmar($lineaItem->precioUnitario === 250.00, "4.8: Precio unitario congelado desde tarifa (250.00)");
    afirmar($lineaItem->subtotal === 500.00, "4.9: Subtotal de línea calculado correctamente (2 * 250.00 = 500.00)");

    // 4.9b Integridad BD: CHECK constraint chk_cotizacion_lineas_tipo rechaza ITEM sin oferta_item_id
    $checkDbItemSinOferta = false;
    try {
        $pdo->exec("INSERT INTO `cotizacion_lineas` (`cotizacion_id`, `tipo_linea`, `item_comercial_id`, `paquete_id`, `oferta_item_id`, `oferta_paquete_id`, `concepto_codigo`, `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`) VALUES ({$borrador->id}, 'ITEM', {$item1Id}, NULL, NULL, NULL, 'C1', 'N1', 'UND', 1.0, 10.0, 10.0, 'PEN')");
    } catch (\Throwable $e) {
        $checkDbItemSinOferta = true;
    }
    afirmar($checkDbItemSinOferta, "4.9b: DB CHECK: chk_cotizacion_lineas_tipo rechaza fila ITEM con oferta_item_id = NULL");

    // 4.9c Integridad BD: CHECK constraint rechaza ITEM con paquete_id no nulo
    $checkDbItemConPaquete = false;
    try {
        $pdo->exec("INSERT INTO `cotizacion_lineas` (`cotizacion_id`, `tipo_linea`, `item_comercial_id`, `paquete_id`, `oferta_item_id`, `oferta_paquete_id`, `concepto_codigo`, `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`) VALUES ({$borrador->id}, 'ITEM', {$item1Id}, 88101, {$ofertaItem1Id}, NULL, 'C1', 'N1', 'UND', 1.0, 10.0, 10.0, 'PEN')");
    } catch (\Throwable $e) {
        $checkDbItemConPaquete = true;
    }
    afirmar($checkDbItemConPaquete, "4.9c: DB CHECK: chk_cotizacion_lineas_tipo rechaza fila ITEM con paquete_id no nulo");

    // 4.9d Integridad BD: FK RESTRICT en oferta_item_id impide borrado físico de oferta cotizada
    $fkRestrictOfertaItem = false;
    try {
        $pdo->exec("DELETE FROM `ofertas_items_edicion` WHERE `id` = {$ofertaItem1Id}");
    } catch (\Throwable $e) {
        $fkRestrictOfertaItem = true;
    }
    afirmar($fkRestrictOfertaItem, "4.9d: DB FK RESTRICT: Prohibido eliminar oferta de ítem que esté referenciada en una cotización");

    // 4.10 Verificar inmutabilidad del snapshot: cambiar nombre y precio en catálogo
    $pdo->exec("UPDATE `items_comerciales` SET `nombre` = 'TRIBUNA MODIFICADA EN CATÁLOGO' WHERE `id` = {$item1Id}");
    $pdo->exec("UPDATE `tarifas_items_edicion` SET `precio` = 999.00 WHERE `id` = 66101");

    $lineasReconsultadas = $cotizacionRepo->obtenerLineas($borrador->id);
    afirmar($lineasReconsultadas[0]->conceptoNombre === 'Tribuna Central Asiento Numerado', "4.10: Inmutabilidad de snapshot: Nombre de concepto en cotización no se altera al mutar el catálogo");
    afirmar($lineasReconsultadas[0]->precioUnitario === 250.00, "4.11: Inmutabilidad de snapshot: Precio congelado en cotización no se altera al mutar tarifas de catálogo");

    // ==============================================================================
    // BLOQUE 5: PAQUETES COMERCIALES Y SNAPSHOT RELACIONAL DE COMPONENTES
    // ==============================================================================
    echo "\n--- BLOQUE 5: PAQUETES COMERCIALES Y SNAPSHOT RELACIONAL DE COMPONENTES ---\n";

    // 5.0a Invariante Entidad: CotizacionLinea de tipo PAQUETE exige obligatoriamente ofertaPaqueteId
    $entidadPaqueteSinOferta = false;
    try {
        new \Aplicacion\Entidades\CotizacionLinea(
            id: null,
            cotizacionId: $borrador->id,
            tipoLinea: TipoLineaCotizacion::PAQUETE,
            itemComercialId: null,
            paqueteId: $paqueteId,
            ofertaItemId: null,
            ofertaPaqueteId: null, // Prohibido null para PAQUETE
            conceptoCodigo: 'CODE',
            conceptoNombre: 'NAME',
            conceptoDescripcion: null,
            unidadMedida: 'PAQ',
            cantidad: 1.0,
            precioUnitario: 300.0,
            moneda: 'PEN'
        );
    } catch (InvalidArgumentException $e) {
        $entidadPaqueteSinOferta = true;
    }
    afirmar($entidadPaqueteSinOferta, "5.0a: Invariante Entidad: CotizacionLinea de tipo PAQUETE exige obligatoriamente ofertaPaqueteId");

    // 5.0b Validación de oferta paquete: rechazo si la oferta pertenece a otro tenant
    $ofertaPaqOtroTenantRechazada = false;
    try {
        $cotizacionServicio->agregarLineaPaquete(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            paqueteId: $paqueteId,
            cantidad: 1.00,
            ofertaPaqueteId: $ofertaPaqueteTenantBId,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $ofertaPaqOtroTenantRechazada = true;
    }
    afirmar($ofertaPaqOtroTenantRechazada, "5.0b: Procedencia: Rechazada oferta_paquete_id perteneciente a otra organización (cross-tenant)");

    // 5.0c Validación de oferta paquete: rechazo si la oferta se encuentra inactiva
    $ofertaPaqInactivaRechazada = false;
    try {
        $cotizacionServicio->agregarLineaPaquete(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            paqueteId: 88198,
            cantidad: 1.00,
            ofertaPaqueteId: $ofertaPaqueteInactivaId,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $ofertaPaqInactivaRechazada = true;
    }
    afirmar($ofertaPaqInactivaRechazada, "5.0c: Procedencia: Rechazada oferta_paquete_id inactiva");

    $cotConPaquete = $cotizacionServicio->agregarLineaPaquete(
        organizacionId: $tenantA,
        cotizacionId: $borrador->id,
        paqueteId: $paqueteId,
        cantidad: 1.00,
        precioUnitario: null, // Toma tarifa de paquete: 340.00
        contexto: $ctxAdminTenantA
    );

    afirmar(count($cotConPaquete->lineas) === 2, "5.1: Línea de paquete comercial agregada (total 2 líneas)");
    $lineaPaquete = $cotConPaquete->lineas[1];
    afirmar($lineaPaquete->tipoLinea === TipoLineaCotizacion::PAQUETE, "5.2: Tipo de línea es estrictamente PAQUETE");
    afirmar($lineaPaquete->paqueteId === $paqueteId, "5.3: FK paquete_id preservada");
    afirmar($lineaPaquete->ofertaPaqueteId === $ofertaPaqueteId, "5.4: FK oferta_paquete_id preservada");
    afirmar($lineaPaquete->precioUnitario === 340.00, "5.5: Precio de paquete congelado desde tarifa (340.00)");
    afirmar(count($lineaPaquete->componentes) === 2, "5.6: Snapshot relacional congeló exactamente los 2 componentes del paquete en cotizacion_linea_componentes");

    // 5.6b Integridad BD: CHECK constraint chk_cotizacion_lineas_tipo rechaza PAQUETE sin oferta_paquete_id
    $checkDbPaqSinOferta = false;
    try {
        $pdo->exec("INSERT INTO `cotizacion_lineas` (`cotizacion_id`, `tipo_linea`, `item_comercial_id`, `paquete_id`, `oferta_item_id`, `oferta_paquete_id`, `concepto_codigo`, `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`) VALUES ({$borrador->id}, 'PAQUETE', NULL, {$paqueteId}, NULL, NULL, 'CP', 'NP', 'PAQ', 1.0, 100.0, 100.0, 'PEN')");
    } catch (\Throwable $e) {
        $checkDbPaqSinOferta = true;
    }
    afirmar($checkDbPaqSinOferta, "5.6b: DB CHECK: chk_cotizacion_lineas_tipo rechaza fila PAQUETE con oferta_paquete_id = NULL");

    // 5.6c Integridad BD: CHECK constraint rechaza PAQUETE con item_comercial_id no nulo
    $checkDbPaqConItem = false;
    try {
        $pdo->exec("INSERT INTO `cotizacion_lineas` (`cotizacion_id`, `tipo_linea`, `item_comercial_id`, `paquete_id`, `oferta_item_id`, `oferta_paquete_id`, `concepto_codigo`, `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`) VALUES ({$borrador->id}, 'PAQUETE', {$item1Id}, {$paqueteId}, NULL, {$ofertaPaqueteId}, 'CP', 'NP', 'PAQ', 1.0, 100.0, 100.0, 'PEN')");
    } catch (\Throwable $e) {
        $checkDbPaqConItem = true;
    }
    afirmar($checkDbPaqConItem, "5.6c: DB CHECK: chk_cotizacion_lineas_tipo rechaza fila PAQUETE con item_comercial_id no nulo");

    // 5.6d Integridad BD: FK RESTRICT en oferta_paquete_id impide borrado físico de oferta cotizada
    $fkRestrictOfertaPaq = false;
    try {
        $pdo->exec("DELETE FROM `ofertas_paquetes_edicion` WHERE `id` = {$ofertaPaqueteId}");
    } catch (\Throwable $e) {
        $fkRestrictOfertaPaq = true;
    }
    afirmar($fkRestrictOfertaPaq, "5.6d: DB FK RESTRICT: Prohibido eliminar oferta de paquete referenciada en una cotización");

    // 5.7 Inmutabilidad de componentes ante cambios en paquete_items
    $pdo->exec("DELETE FROM `paquete_items` WHERE `paquete_id` = {$paqueteId} AND `item_comercial_id` = {$item2Id}");
    $lineasTrasAlterarPaquete = $cotizacionRepo->obtenerLineas($borrador->id);
    afirmar(count($lineasTrasAlterarPaquete[1]->componentes) === 2, "5.7: Inmutabilidad de snapshot: Componentes relacionales permanecen intactos tras alterar catálogo de paquetes");

    // ==============================================================================
    // BLOQUE 6: DESCUENTOS ESTRUCTURADOS Y PERMISOS RBAC
    // ==============================================================================
    echo "\n--- BLOQUE 6: DESCUENTOS ESTRUCTURADOS Y PERMISOS RBAC ---\n";

    // 6.0a Descuento cero no exige motivo (PASS)
    $lineaDescCero = $cotizacionServicio->agregarLineaItem(
        organizacionId: $tenantA,
        cotizacionId: $borrador->id,
        itemComercialId: $item2Id,
        cantidad: 1.00,
        precioUnitario: 60.00,
        descuentoTipo: TipoDescuentoCotizacion::NINGUNO,
        descuentoValor: 0.00,
        descuentoMotivo: null,
        contexto: $ctxAdminTenantA
    );
    afirmar(count($lineaDescCero->lineas) === 3, "6.0a: Línea con descuento cero no exige motivo y es aceptada exitosamente");
    // Retirar la línea temporal de prueba para mantener el orden determinista
    $cotizacionServicio->eliminarLinea($tenantA, $borrador->id, $lineaDescCero->lineas[2]->id, $ctxAdminTenantA);

    // 6.1 Descuento sin permiso dedicado (Operador) es denegado
    $descuentoSinPermiso = false;
    try {
        $cotizacionServicio->agregarLineaItem(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            itemComercialId: $item2Id,
            cantidad: 1.00,
            precioUnitario: 60.00,
            descuentoTipo: TipoDescuentoCotizacion::PORCENTAJE,
            descuentoValor: 10.00,
            descuentoMotivo: 'Descuento no autorizado',
            contexto: $ctxOperadorTenantA // Operador sin 'cotizaciones.aplicar_descuento'
        );
    } catch (AccesoDenegadoExcepcion $e) {
        $descuentoSinPermiso = true;
    }
    afirmar($descuentoSinPermiso, "6.1: RBAC: Descuento rechazado a usuario sin permiso 'cotizaciones.aplicar_descuento'");

    // 6.1b Usuario con cotizaciones.editar pero SIN cotizaciones.aplicar_descuento: descuento rechazado
    $editarSinAplicarDescuentoRechazado = false;
    try {
        $cotizacionServicio->agregarLineaItem(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            itemComercialId: $item2Id,
            cantidad: 1.00,
            precioUnitario: 60.00,
            descuentoTipo: TipoDescuentoCotizacion::PORCENTAJE,
            descuentoValor: 10.00,
            descuentoMotivo: 'Descuento intentado por editor simple',
            contexto: $ctxSoloEditor // Tiene editar pero NO aplicar_descuento
        );
    } catch (AccesoDenegadoExcepcion $e) {
        $editarSinAplicarDescuentoRechazado = true;
    }
    afirmar($editarSinAplicarDescuentoRechazado, "6.1b: RBAC Separación: Usuario con 'cotizaciones.editar' pero SIN 'cotizaciones.aplicar_descuento' NO puede aplicar descuentos");

    // 6.1c Usuario con cotizaciones.aplicar_descuento pero SIN cotizaciones.editar: NO concede edición general
    $descuentoSinEditarRechazado = false;
    try {
        $cotizacionServicio->agregarLineaItem(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            itemComercialId: $item2Id,
            cantidad: 1.00,
            precioUnitario: 60.00,
            contexto: $ctxSoloDescuento // Tiene aplicar_descuento pero NO editar
        );
    } catch (AccesoDenegadoExcepcion $e) {
        $descuentoSinEditarRechazado = true;
    }
    afirmar($descuentoSinEditarRechazado, "6.1c: RBAC Separación: Permiso 'cotizaciones.aplicar_descuento' NO concede capacidad de edición general (requiere cotizaciones.editar)");

    // 6.2 Descuento > 100% es rechazado
    $descuentoExcesivo = false;
    try {
        $cotizacionServicio->agregarLineaItem(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            itemComercialId: $item2Id,
            cantidad: 1.00,
            precioUnitario: 60.00,
            descuentoTipo: TipoDescuentoCotizacion::PORCENTAJE,
            descuentoValor: 105.00,
            descuentoMotivo: 'Descuento excesivo',
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $descuentoExcesivo = true;
    }
    afirmar($descuentoExcesivo, "6.2: Rechazo matemático ante porcentaje de descuento superior al 100%");

    // 6.3 Descuento > 0 sin motivo justificado es rechazado
    $descuentoSinMotivo = false;
    try {
        $cotizacionServicio->agregarLineaItem(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            itemComercialId: $item2Id,
            cantidad: 1.00,
            precioUnitario: 60.00,
            descuentoTipo: TipoDescuentoCotizacion::PORCENTAJE,
            descuentoValor: 10.00,
            descuentoMotivo: null, // Sin motivo
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $descuentoSinMotivo = true;
    }
    afirmar($descuentoSinMotivo, "6.3: Rechazo estricto ante descuento sin justificación obligatoria (descuento_motivo)");

    // 6.4 Aplicar descuento válido en línea
    $cotConDescLinea = $cotizacionServicio->agregarLineaItem(
        organizacionId: $tenantA,
        cotizacionId: $borrador->id,
        itemComercialId: $item2Id,
        cantidad: 2.00, // 2 * 60 = 120.00
        precioUnitario: 60.00,
        descuentoTipo: TipoDescuentoCotizacion::MONTO_FIJO,
        descuentoValor: 20.00,
        descuentoMotivo: 'Cortesía de fidelización comercial',
        contexto: $ctxAdminTenantA
    );
    $linea3 = $cotConDescLinea->lineas[2];
    afirmar($linea3->descuentoMonto === 20.00, "6.4: Descuento de línea fijado en 20.00");
    afirmar($linea3->subtotal === 100.00, "6.5: Subtotal de línea neto calculado correctamente (120 - 20 = 100.00)");

    // 6.5b Modificar descuento de línea con aplicarDescuentoLinea
    $cotConDescModificado = $cotizacionServicio->aplicarDescuentoLinea(
        organizacionId: $tenantA,
        cotizacionId: $borrador->id,
        lineaId: $linea3->id,
        tipo: TipoDescuentoCotizacion::MONTO_FIJO,
        valor: 30.00,
        motivo: 'Ajuste adicional de descuento por campaña',
        contexto: $ctxAdminTenantA
    );
    $lineasMod = $cotizacionRepo->obtenerLineas($borrador->id);
    afirmar($lineasMod[2]->descuentoMonto === 30.00, "6.5b: aplicarDescuentoLinea(): Descuento actualizado con motivo obligatorio");
    afirmar($lineasMod[2]->subtotal === 90.00, "6.5c: aplicarDescuentoLinea(): Subtotal de línea recalculado en 90.00");

    // Restaurar a 20.00 para mantener el total determinista de los siguientes bloques
    $cotizacionServicio->aplicarDescuentoLinea(
        organizacionId: $tenantA,
        cotizacionId: $borrador->id,
        lineaId: $linea3->id,
        tipo: TipoDescuentoCotizacion::MONTO_FIJO,
        valor: 20.00,
        motivo: 'Restauración para pruebas siguientes',
        contexto: $ctxAdminTenantA
    );

    // 6.6 Descuento global sobre la cotización
    $cotConDescGlobal = $cotizacionServicio->aplicarDescuentoGlobal(
        organizacionId: $tenantA,
        cotizacionId: $borrador->id,
        tipo: TipoDescuentoCotizacion::MONTO_FIJO,
        valor: 40.00,
        motivo: 'Descuento por cierre oportuno en campaña preventa',
        contexto: $ctxAdminTenantA
    );
    // Subtotal = 500 (linea 1) + 340 (linea 2) + 100 (linea 3) = 940.00
    // Descuento global = 40.00 -> Total = 900.00
    afirmar($cotConDescGlobal->subtotal === 940.00, "6.6: Subtotal global acumulado determinista (940.00)");
    afirmar($cotConDescGlobal->descuentoGlobalMonto === 40.00, "6.7: Monto de descuento global aplicado (40.00)");
    afirmar($cotConDescGlobal->total === 900.00, "6.8: Total final de cotización calculado determinísticamente (900.00)");

    // ==============================================================================
    // BLOQUE 7: EMISIÓN ATÓMICA, SECUENCIA Y MONOTONICIDAD
    // ==============================================================================
    echo "\n--- BLOQUE 7: EMISIÓN ATÓMICA, SECUENCIA Y MONOTONICIDAD ---\n";

    // 7.1 Emisión sin permiso dedicado (Operador) es denegada
    $emisionSinPermiso = false;
    try {
        $cotizacionServicio->emitir(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            contexto: $ctxOperadorTenantA
        );
    } catch (AccesoDenegadoExcepcion $e) {
        $emisionSinPermiso = true;
    }
    afirmar($emisionSinPermiso, "7.1: RBAC: Emisión rechazada a usuario sin permiso 'cotizaciones.emitir'");

    // 7.2 Emisión con fecha de validez menor a la fecha actual es rechazada
    $emisionFechaInvalida = false;
    try {
        $cotizacionServicio->emitir(
            organizacionId: $tenantA,
            cotizacionId: $borrador->id,
            validoHastaManual: '2020-01-01',
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $emisionFechaInvalida = true;
    }
    afirmar($emisionFechaInvalida, "7.2: Rechazo ante fecha límite de validez anterior a la fecha de emisión");

    // 7.3 Emisión exitosa de la primera cotización
    $emitida1 = $cotizacionServicio->emitir(
        organizacionId: $tenantA,
        cotizacionId: $borrador->id,
        contexto: $ctxAdminTenantA
    );

    afirmar($emitida1->estado === EstadoCotizacion::EMITIDA, "7.3: Transición formal a estado EMITIDA exitosa");
    afirmar($emitida1->correlativo === 'COT-2026-000001', "7.4: Correlativo oficial asignado monotónicamente: 'COT-2026-000001'");
    afirmar($emitida1->correlativoBase === 'COT-2026-000001', "7.5: Correlativo base fijado en 'COT-2026-000001'");
    afirmar($emitida1->fechaEmision === date('Y-m-d'), "7.6: Fecha de emisión registrada con fecha actual");
    afirmar($emitida1->validoHasta !== null && $emitida1->validoHasta > date('Y-m-d'), "7.7: Fecha límite de validez calculada con días institucionales (+15 días)");
    afirmar($emitida1->versionBloqueo > $borrador->versionBloqueo, "7.8: version_bloqueo incrementada progresivamente tras las operaciones y emisión atómica");

    // 7.9 Verificar secuencia en base de datos
    $stmtSec = $pdo->prepare("SELECT `ultimo_numero` FROM `cotizaciones_secuencias` WHERE `organizacion_id` = :org_id AND `anio` = 2026");
    $stmtSec->execute(['org_id' => $tenantA]);
    afirmar((int) $stmtSec->fetchColumn() === 1, "7.9: Tabla 'cotizaciones_secuencias' registra exactamente ultimo_numero = 1");

    // 7.10 Siguiente cotización obtiene el siguiente correlativo secuencial
    $borrador2 = $cotizacionServicio->crearBorrador(
        organizacionId: $tenantA,
        edicionId: $edicionAId,
        clienteId: $clienteAId,
        titulo: 'Segunda Propuesta Comercial',
        contexto: $ctxAdminTenantA
    );
    $cotizacionServicio->agregarLineaItem(
        organizacionId: $tenantA,
        cotizacionId: $borrador2->id,
        itemComercialId: $item2Id,
        cantidad: 1.00,
        contexto: $ctxAdminTenantA
    );
    $emitida2 = $cotizacionServicio->emitir(
        organizacionId: $tenantA,
        cotizacionId: $borrador2->id,
        contexto: $ctxAdminTenantA
    );
    afirmar($emitida2->correlativo === 'COT-2026-000002', "7.10: Monotonicidad: Segunda cotización recibe correlativo estricto 'COT-2026-000002'");

    // 7.11 Integración CRM: Oportunidad avanzó a 'COTIZACION'
    $stmtEtapaOp = $pdo->prepare("SELECT `etapa` FROM `crm_oportunidades` WHERE `id` = :id");
    $stmtEtapaOp->execute(['id' => $oportunidadAId]);
    $etapaOpActual = $stmtEtapaOp->fetchColumn();
    afirmar($etapaOpActual === 'COTIZACION', "7.11: Integración CRM: Oportunidad avanzó automáticamente a etapa 'COTIZACION'");

    // ==============================================================================
    // BLOQUE 8: INMUTABILIDAD POST-EMISIÓN
    // ==============================================================================
    echo "\n--- BLOQUE 8: INMUTABILIDAD POST-EMISIÓN ---\n";

    // 8.1 Agregar línea a cotización emitida es denegado
    $agregarLineaEmitida = false;
    try {
        $cotizacionServicio->agregarLineaItem(
            organizacionId: $tenantA,
            cotizacionId: $emitida1->id,
            itemComercialId: $item1Id,
            cantidad: 1.00,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $agregarLineaEmitida = true;
    }
    afirmar($agregarLineaEmitida, "8.1: Inmutabilidad: Rechazado intento de agregar línea a cotización formalmente EMITIDA");

    // 8.2 Eliminar línea de cotización emitida es denegado
    $eliminarLineaEmitida = false;
    try {
        $cotizacionServicio->eliminarLinea(
            organizacionId: $tenantA,
            cotizacionId: $emitida1->id,
            lineaId: $emitida1->lineas[0]->id,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $eliminarLineaEmitida = true;
    }
    afirmar($eliminarLineaEmitida, "8.2: Inmutabilidad: Rechazado intento de eliminar línea de cotización formalmente EMITIDA");

    // 8.3 Modificar metadatos en cotización emitida es denegado
    $modificarBorradorEmitida = false;
    try {
        $cotizacionServicio->actualizarBorrador(
            organizacionId: $tenantA,
            cotizacionId: $emitida1->id,
            titulo: 'Nuevo Título Ilegal',
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $modificarBorradorEmitida = true;
    }
    afirmar($modificarBorradorEmitida, "8.3: Inmutabilidad: Rechazado intento de modificar metadatos de cotización formalmente EMITIDA");

    // 8.4 Re-emisión de cotización ya emitida es denegada
    $reemitirRechazado = false;
    try {
        $cotizacionServicio->emitir(
            organizacionId: $tenantA,
            cotizacionId: $emitida1->id,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $reemitirRechazado = true;
    }
    afirmar($reemitirRechazado, "8.4: Inmutabilidad: Rechazado intento de re-emitir cotización ya formalizada");

    // ==============================================================================
    // BLOQUE 9: REVISIONES ENLAZADAS (R2) Y ANULACIÓN POR SUSTITUCIÓN
    // ==============================================================================
    echo "\n--- BLOQUE 9: REVISIONES ENLAZADAS (R2) ---\n";

    // 9.1 Generar revisión a partir de una cotización en borrador es denegado
    $revisionDeBorrador = false;
    try {
        $borradorSinEmitir = $cotizacionServicio->crearBorrador(
            organizacionId: $tenantA,
            edicionId: $edicionAId,
            clienteId: $clienteAId,
            titulo: 'Borrador Sin Emitir',
            contexto: $ctxAdminTenantA
        );
        $cotizacionServicio->crearRevision(
            organizacionId: $tenantA,
            cotizacionId: $borradorSinEmitir->id,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $revisionDeBorrador = true;
    }
    afirmar($revisionDeBorrador, "9.1: Rechazo de generación de revisión desde cotización que no está EMITIDA");

    // 9.2 Generar revisión válida a partir de la cotización emitida (R1)
    $revision2 = $cotizacionServicio->crearRevision(
        organizacionId: $tenantA,
        cotizacionId: $emitida1->id,
        contexto: $ctxAdminTenantA
    );

    afirmar($revision2->id !== $emitida1->id, "9.2: Revisión generada con nueva identidad física");
    afirmar($revision2->versionNumero === 2, "9.3: Número de versión es exactamente 2");
    afirmar($revision2->estado === EstadoCotizacion::BORRADOR, "9.4: La nueva revisión nace en estado BORRADOR");
    afirmar($revision2->correlativo === null, "9.5: El correlativo de la revisión permanece NULL mientras es borrador");
    afirmar($revision2->correlativoBase === 'COT-2026-000001', "9.6: Preserva el correlativo base de la raíz 'COT-2026-000001'");
    afirmar($revision2->cotizacionOrigenId === $emitida1->id, "9.7: Referencia formal cotizacion_origen_id apunta a la cotización padre");
    afirmar($revision2->cotizacionRaizId === $emitida1->id, "9.8: Referencia formal cotizacion_raiz_id preserva la raíz común");
    afirmar(count($revision2->lineas) === count($emitida1->lineas), "9.9: Clona exactamente las líneas comerciales de la versión anterior");

    // 9.10 Mientras R2 es borrador, R1 permanece intacta en estado EMITIDA
    $r1Consulta = $cotizacionServicio->obtenerCotizacion($tenantA, $emitida1->id, $ctxAdminTenantA);
    afirmar($r1Consulta->estado === EstadoCotizacion::EMITIDA, "9.10: R1 permanece inalterada en estado EMITIDA mientras R2 esté en borrador");

    // 9.11 Emitir la revisión R2
    $r2Emitida = $cotizacionServicio->emitir(
        organizacionId: $tenantA,
        cotizacionId: $revision2->id,
        contexto: $ctxAdminTenantA
    );

    afirmar($r2Emitida->estado === EstadoCotizacion::EMITIDA, "9.11: Revisión R2 transiciona a EMITIDA");
    afirmar($r2Emitida->correlativo === 'COT-2026-000001-R2', "9.12: Asignación de correlativo con sufijo de revisión: 'COT-2026-000001-R2'");

    // 9.13 Al emitirse R2, R1 queda atómicamente ANULADA por sustitución
    $r1TrasEmitirR2 = $cotizacionServicio->obtenerCotizacion($tenantA, $emitida1->id, $ctxAdminTenantA);
    afirmar($r1TrasEmitirR2->estado === EstadoCotizacion::ANULADA, "9.13: R1 pasa atómicamente a ANULADA al emitirse formalmente R2");
    afirmar($r1TrasEmitirR2->motivoAnulacion === MotivoAnulacionCotizacion::SUPERADA_POR_REVISION, "9.14: R1 registra motivo estricto 'SUPERADA_POR_REVISION'");

    // ==============================================================================
    // BLOQUE 10: CONFORMIDAD COMERCIAL (ACEPTADA) Y DESACOPLAMIENTO
    // ==============================================================================
    echo "\n--- BLOQUE 10: CONFORMIDAD COMERCIAL (ACEPTADA) Y DESACOPLAMIENTO ---\n";

    // 10.1 Aceptar un borrador es denegado
    $aceptarBorradorRechazado = false;
    try {
        $cotizacionServicio->aceptar(
            organizacionId: $tenantA,
            cotizacionId: $borradorSinEmitir->id,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $aceptarBorradorRechazado = true;
    }
    afirmar($aceptarBorradorRechazado, "10.1: Rechazado intento de aceptar una cotización en borrador");

    // 10.2 Aceptar la cotización emitida R2
    $r2Aceptada = $cotizacionServicio->aceptar(
        organizacionId: $tenantA,
        cotizacionId: $r2Emitida->id,
        contexto: $ctxAdminTenantA
    );
    afirmar($r2Aceptada->estado === EstadoCotizacion::ACEPTADA, "10.2: Cotización transiciona formalmente a ACEPTADA");

    // 10.3 Desacoplamiento estricto: Cero ventas automáticas, cero reservas, cero deuda
    $stmtVentasCot = $pdo->prepare("SELECT COUNT(*) FROM `ventas` WHERE `cotizacion_id` = :id");
    $stmtVentasCot->execute(['id' => $r2Aceptada->id]);
    afirmar((int) $stmtVentasCot->fetchColumn() === 0, "10.3: Desacoplamiento: Aceptación de cotización no genera venta automáticamente");
    afirmar(!in_array('reservas', $tablasBd, true), "10.4: Desacoplamiento: No existe tabla de reservas en base de datos");
    afirmar(!in_array('caja_movimientos', $tablasBd, true), "10.5: Desacoplamiento: No existe movimiento financiero de caja");

    // 10.6 Oportunidad CRM NO se fuerza a GANADA (permanece en COTIZACION)
    $stmtEtapaOpAceptada = $pdo->prepare("SELECT `etapa` FROM `crm_oportunidades` WHERE `id` = :id");
    $stmtEtapaOpAceptada->execute(['id' => $oportunidadAId]);
    afirmar($stmtEtapaOpAceptada->fetchColumn() === 'COTIZACION', "10.6: Desacoplamiento CRM: Oportunidad NO es forzada a GANADA por aceptación de cotización");

    // ==============================================================================
    // BLOQUE 11: RECHAZO Y ANULACIÓN CON MOTIVOS ESTRUCTURADOS
    // ==============================================================================
    echo "\n--- BLOQUE 11: RECHAZO Y ANULACIÓN CON MOTIVOS ESTRUCTURADOS ---\n";

    // 11.1 Rechazo de cotización emitida con motivo OTRO sin detalle es denegado
    $rechazoSinDetalle = false;
    try {
        $cotizacionServicio->rechazar(
            organizacionId: $tenantA,
            cotizacionId: $emitida2->id,
            motivo: MotivoRechazoCotizacion::OTRO,
            motivoDetalle: '',
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $rechazoSinDetalle = true;
    }
    afirmar($rechazoSinDetalle, "11.1: Rechazo con motivo 'OTRO' exige obligatoriamente detalle explicativo");

    // 11.2 Rechazo válido con motivo estructurado
    $emitida2Rechazada = $cotizacionServicio->rechazar(
        organizacionId: $tenantA,
        cotizacionId: $emitida2->id,
        motivo: MotivoRechazoCotizacion::COMPETENCIA,
        motivoDetalle: 'Cliente contrató con operador alternativo por tarifa menor',
        contexto: $ctxAdminTenantA
    );
    afirmar($emitida2Rechazada->estado === EstadoCotizacion::RECHAZADA, "11.2: Transición formal a estado RECHAZADA exitosa");
    afirmar($emitida2Rechazada->motivoRechazo === MotivoRechazoCotizacion::COMPETENCIA, "11.3: Motivo estructurado COMPETENCIA registrado");

    // 11.4 Anular cotización rechazada (estado terminal) es denegado
    $anularTerminalRechazado = false;
    try {
        $cotizacionServicio->anular(
            organizacionId: $tenantA,
            cotizacionId: $emitida2Rechazada->id,
            motivo: MotivoAnulacionCotizacion::ERROR_DATOS,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $anularTerminalRechazado = true;
    }
    afirmar($anularTerminalRechazado, "11.4: Rechazado intento de anular cotización que ya se encuentra en estado terminal");

    // ==============================================================================
    // BLOQUE 12: POLÍTICA DE VIGENCIA Y EXPIRACIÓN AUDITABLE
    // ==============================================================================
    echo "\n--- BLOQUE 12: POLÍTICA DE VIGENCIA Y EXPIRACIÓN AUDITABLE ---\n";

    // 12.1 Cotización con fecha vencida en el pasado
    $pdo->exec("UPDATE `cotizaciones` SET `estado` = 'EMITIDA', `fecha_emision` = '2025-01-01', `valido_hasta` = '2025-01-15' WHERE `id` = {$r2Emitida->id}");
    $cotExpirada = $cotizacionServicio->obtenerCotizacion($tenantA, $r2Emitida->id, $ctxAdminTenantA);

    afirmar($cotExpirada->estaVencidaEfectiva() === true, "12.1: Cálculo dinámico de vigencia: estaVencidaEfectiva() retorna true");
    // Comprobar que en base de datos sigue con su estado anterior (cero mutación en lectura)
    $stmtEstadoDb = $pdo->prepare("SELECT `estado` FROM `cotizaciones` WHERE `id` = :id");
    $stmtEstadoDb->execute(['id' => $r2Emitida->id]);
    afirmar($stmtEstadoDb->fetchColumn() === 'EMITIDA', "12.2: Cero efectos secundarios en lectura: La base de datos no fue mutada al consultar vigencia");

    // ==============================================================================
    // BLOQUE 13: CONCURRENCIA OPTIMISTA (VERSION_BLOQUEO)
    // ==============================================================================
    echo "\n--- BLOQUE 13: CONCURRENCIA OPTIMISTA (VERSION_BLOQUEO) ---\n";

    // Modificar externamente la cotización para desfasar version_bloqueo
    $pdo->exec("UPDATE `cotizaciones` SET `version_bloqueo` = `version_bloqueo` + 1 WHERE `id` = {$borradorSinEmitir->id}");

    $conflictoConcurrencia = false;
    try {
        $cotizacionServicio->actualizarBorrador(
            organizacionId: $tenantA,
            cotizacionId: $borradorSinEmitir->id,
            titulo: 'Conflicto Forzado',
            versionBloqueoEsperada: 1, // Desfasada respecto a la BD
            contexto: $ctxAdminTenantA
        );
    } catch (ConflictoConcurrenciaExcepcion $e) {
        $conflictoConcurrencia = true;
    }
    afirmar($conflictoConcurrencia, "13.1: Concurrencia Optimista: ConflictoConcurrenciaExcepcion lanzada ante version_bloqueo desfasada");

    // 13.2 Moneda institucional Fail-Closed: Sin configuración de plataforma válida, la creación falla inmediatamente
    $configServicio->limpiarCache();
    $pdo->exec("UPDATE `parametros_configuracion` SET `valor` = '' WHERE `codigo` = 'plataforma.moneda_principal'");
    $monedaFailClosed = false;
    try {
        $cotizacionServicio->crearBorrador(
            organizacionId: $tenantA,
            edicionId: $edicionAId,
            clienteId: $clienteAId,
            titulo: 'Test Moneda Ausente',
            contexto: $ctxAdminTenantA
        );
    } catch (\RuntimeException $e) {
        if (str_contains($e->getMessage(), 'FAIL CLOSED')) {
            $monedaFailClosed = true;
        }
    } finally {
        $pdo->exec("UPDATE `parametros_configuracion` SET `valor` = 'PEN' WHERE `codigo` = 'plataforma.moneda_principal'");
        $configServicio->limpiarCache();
    }
    afirmar($monedaFailClosed, "13.2: Divisa Soberana: plataforma.moneda_principal ausente o inválida produce FAIL CLOSED inmediato sin fallback");

    // 13.3 Desacoplamiento HTTP del dominio: Excepciones y servicios son puros (sin 409 ni Response)
    $esPuraDominio = is_subclass_of(ConflictoConcurrenciaExcepcion::class, \RuntimeException::class);
    $reflectionEx = new \ReflectionClass(ConflictoConcurrenciaExcepcion::class);
    $sinMetodosHttp = !$reflectionEx->hasMethod('getStatusCode') && !$reflectionEx->hasMethod('getResponse');
    afirmar($esPuraDominio && $sinMetodosHttp, "13.3: Dominio Puro: ConflictoConcurrenciaExcepcion es pura sin acoplamiento a HTTP ni status codes");

    // ==============================================================================
    // BLOQUE 14: AUDITORÍA TÉCNICA TRANSVERSAL
    // ==============================================================================
    echo "\n--- BLOQUE 14: AUDITORÍA TÉCNICA TRANSVERSAL ---\n";

    $stmtAud = $pdo->prepare("SELECT DISTINCT `accion` FROM `auditoria_operaciones` WHERE `organizacion_id` = :org_id AND `modulo` = 'cotizaciones'");
    $stmtAud->execute(['org_id' => $tenantA]);
    $accionesAuditadas = $stmtAud->fetchAll(PDO::FETCH_COLUMN);

    $accionesEsperadas = [
        'COTIZACION_CREADA',
        'COTIZACION_LINEA_AGREGADA',
        'COTIZACION_DESCUENTO_GLOBAL_APLICADO',
        'COTIZACION_EMITIDA',
        'COTIZACION_REVISION_GENERADA',
        'COTIZACION_ANULADA',
        'COTIZACION_ACEPTADA',
        'COTIZACION_RECHAZADA',
    ];

    foreach ($accionesEsperadas as $acc) {
        afirmar(in_array($acc, $accionesAuditadas, true), "14.1: Auditoría técnica transversal registró evento '{$acc}'");
    }

} finally {
    // Revertir transacciones de prueba para dejar la base de datos limpia e intacta
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n[INFO] Transacción de pruebas F2.4B revertida con ROLLBACK determinista.\n";
    }
}

// ==============================================================================
// BLOQUE 15: CERTIFICACIÓN DE PRESERVACIÓN DE CREDENCIALES (ORLANDO ID 24)
// ==============================================================================
echo "\n--- BLOQUE 15: CERTIFICACIÓN DE PRESERVACIÓN DE CREDENCIALES (ORLANDO ID 24) ---\n";

$stmtOrlando = $pdo->prepare("SELECT `id`, `estado`, `intentos_fallidos`, `bloqueado_hasta`, SUBSTRING(SHA2(`contrasena_hash`, 256), 1, 16) AS `huella` FROM `usuarios` WHERE `id` = 24");
$stmtOrlando->execute();
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);

afirmar($orlando !== false, "15.1: Usuario Orlando (ID 24) existe en la base de datos");
afirmar($orlando['estado'] === 'ACTIVO', "15.2: Estado de Orlando es ACTIVO");
afirmar((int) $orlando['intentos_fallidos'] === 0, "15.3: Intentos fallidos de login de Orlando es exactamente 0");
afirmar($orlando['bloqueado_hasta'] === null, "15.4: Bloqueo temporal de cuenta de Orlando es NULL");
afirmar($orlando['huella'] === '80e6af84e02e89e3', "15.5: Huella criptográfica SHA-256 (truncada 16) coincide exactamente con '80e6af84e02e89e3'");

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.4B: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
exit(0);
