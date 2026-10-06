<?php

declare(strict_types=1);

namespace Pruebas;

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Catalogo\CatalogoServicio;
use Aplicacion\Catalogo\EstadoCatalogo;
use Aplicacion\Catalogo\TipoItemComercial;
use Aplicacion\Catalogo\UnidadMedidaItem;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Entidades\CategoriaItem;
use Aplicacion\Entidades\ItemComercial;
use Aplicacion\Entidades\Paquete;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\CategoriaItemRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\HistorialTarifaRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\OfertaItemEdicionRepositorio;
use Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;
use RuntimeException;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS F2.3B: CATÁLOGO COMERCIAL Y PAQUETES\n";
echo "CATEGORÍAS, ÍTEMS, PAQUETES, OFERTAS POR EDICIÓN, TARIFAS Y CONCURRENCIA\n";
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

// Repositorios
$auditoriaRepo       = new AuditoriaRepositorio($pdo);
$authzServicio       = new AutorizacionServicio(pdo: $pdo);
$configServicio      = new ConfiguracionServicio(auditoriaRepo: $auditoriaRepo, pdo: $pdo);
$edicionRepo         = new EdicionRepositorio($pdo);
$categoriaRepo       = new CategoriaItemRepositorio($pdo);
$itemRepo            = new ItemComercialRepositorio($pdo);
$paqueteRepo         = new PaqueteRepositorio($pdo);
$ofertaItemRepo      = new OfertaItemEdicionRepositorio($pdo);
$ofertaPaqueteRepo   = new OfertaPaqueteEdicionRepositorio($pdo);
$tarifaItemRepo      = new TarifaItemEdicionRepositorio($pdo);
$tarifaPaqueteRepo   = new TarifaPaqueteEdicionRepositorio($pdo);
$historialRepo       = new HistorialTarifaRepositorio($pdo);

// Servicio de dominio
$catalogoServicio = new CatalogoServicio(
    categoriaRepo: $categoriaRepo,
    itemRepo: $itemRepo,
    paqueteRepo: $paqueteRepo,
    ofertaItemRepo: $ofertaItemRepo,
    ofertaPaqueteRepo: $ofertaPaqueteRepo,
    tarifaItemRepo: $tarifaItemRepo,
    tarifaPaqueteRepo: $tarifaPaqueteRepo,
    historialRepo: $historialRepo,
    edicionRepo: $edicionRepo,
    authzServicio: $authzServicio,
    auditoriaRepo: $auditoriaRepo,
    configServicio: $configServicio,
    pdo: $pdo
);

// ==============================================================================
// BLOQUE 1: ESQUEMA RELACIONAL, PARIDAD DE INSTALACIÓN LIMPIA Y MIGRACIONES
// ==============================================================================
echo "\n--- BLOQUE 1: ESQUEMA RELACIONAL Y PARIDAD DE INSTALACIÓN LIMPIA ---\n";

$tablasEsperadas = [
    'categorias_items',
    'items_comerciales',
    'paquetes',
    'paquete_items',
    'ofertas_items_edicion',
    'ofertas_paquetes_edicion',
    'tarifas_items_edicion',
    'tarifas_paquetes_edicion',
    'historial_tarifas_items',
    'historial_tarifas_paquetes',
];

$stmtTablas = $pdo->query("SHOW TABLES");
$tablasBd = $stmtTablas->fetchAll(PDO::FETCH_COLUMN);

foreach ($tablasEsperadas as $t) {
    afirmar(in_array($t, $tablasBd, true), "1.1: Tabla obligatoria '{$t}' existe en la base de datos");
}

// 1.2 Registro de migraciones oficiales del módulo
$stmtMig9 = $pdo->prepare("SELECT COUNT(*) FROM `migraciones_control` WHERE `migracion` = '2026_10_05_000009_crear_catalogo_comercial_paquetes_tarifas.sql'");
$stmtMig9->execute();
afirmar((int) $stmtMig9->fetchColumn() === 1, "1.2: Migración 000009 registrada en 'migraciones_control'");

$stmtMig10 = $pdo->prepare("SELECT COUNT(*) FROM `migraciones_control` WHERE `migracion` = '2026_10_05_000010_agregar_permiso_catalogo_tarifas_ver_historial.sql'");
$stmtMig10->execute();
afirmar((int) $stmtMig10->fetchColumn() === 1, "1.3: Migración incremental 000010 registrada en 'migraciones_control'");

// 1.4 Permisos RBAC módulo 4 (7 permisos soberanos)
$stmtPerms = $pdo->query("SELECT `codigo` FROM `permisos` WHERE `modulo_id` = 4 ORDER BY `id` ASC");
$permsBd = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);
$permsEsperados = [
    'catalogo.ver',
    'catalogo.categorias.gestionar',
    'catalogo.items.gestionar',
    'catalogo.paquetes.gestionar',
    'catalogo.ofertas.gestionar',
    'catalogo.tarifas.gestionar',
    'catalogo.tarifas.ver_historial',
];
$diffPerms = array_diff($permsEsperados, $permsBd);
afirmar(empty($diffPerms) && count($permsBd) === 7, "1.4: Los 7 permisos RBAC soberanos de 'catalogo.*' están formalmente registrados");

// 1.4 Test de instalación limpia de esquema_base.sql y paridad total de tablas
$dbTemp = 'candelaria_temp_cat_' . substr(bin2hex(random_bytes(4)), 0, 8);
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

    afirmar(in_array('categorias_items', $tablasTemp, true), "1.4: Instalación limpia incluye 'categorias_items'");
    afirmar(in_array('items_comerciales', $tablasTemp, true), "1.5: Instalación limpia incluye 'items_comerciales'");
    afirmar(in_array('paquetes', $tablasTemp, true), "1.6: Instalación limpia incluye 'paquetes'");
    afirmar(in_array('paquete_items', $tablasTemp, true), "1.7: Instalación limpia incluye 'paquete_items'");
    afirmar(in_array('ofertas_items_edicion', $tablasTemp, true), "1.8: Instalación limpia incluye 'ofertas_items_edicion'");
    afirmar(in_array('ofertas_paquetes_edicion', $tablasTemp, true), "1.9: Instalación limpia incluye 'ofertas_paquetes_edicion'");
    afirmar(in_array('tarifas_items_edicion', $tablasTemp, true), "1.10: Instalación limpia incluye 'tarifas_items_edicion'");
    afirmar(in_array('tarifas_paquetes_edicion', $tablasTemp, true), "1.11: Instalación limpia incluye 'tarifas_paquetes_edicion'");
    afirmar(in_array('historial_tarifas_items', $tablasTemp, true), "1.12: Instalación limpia incluye 'historial_tarifas_items'");
    afirmar(in_array('historial_tarifas_paquetes', $tablasTemp, true), "1.13: Instalación limpia incluye 'historial_tarifas_paquetes'");
    afirmar(count($tablasTemp) >= 35, "1.14: Instalación limpia de 'esquema_base.sql' crea al menos las 35 tablas oficiales del sistema");
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

    // Crear tenant B si no existe
    $stmtOrgB = $pdo->prepare("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `estado`) VALUES (:id, 'tenant_pruebas_b', 'Tenant Pruebas B', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $stmtOrgB->execute(['id' => $tenantB]);

    // Crear ediciones para tenant A y tenant B
    $stmtEdA = $pdo->prepare("
        INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`)
        VALUES (:org_id, 'edicion-cat-2026-a', 'CANDELARIA 2026 A', 2026, 'OPERACION', '2026-02-01', '2026-02-15', 1)
        ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)
    ");
    $stmtEdA->execute(['org_id' => $tenantA]);
    $edicionAId = (int) $pdo->lastInsertId();

    $stmtEdB = $pdo->prepare("
        INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`)
        VALUES (:org_id, 'edicion-cat-2026-b', 'CANDELARIA 2026 B', 2026, 'OPERACION', '2026-02-01', '2026-02-15', 1)
        ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)
    ");
    $stmtEdB->execute(['org_id' => $tenantB]);
    $edicionBId = (int) $pdo->lastInsertId();

    // Crear usuario operador con rol 3 (solo lectura)
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (81099, 10000, 'NATURAL', 'OPERADOR', 'LECTURA', '+51951999777', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`) VALUES (61099, 10000, 81099, 'operador.lectura', 'OPERADOR SOLO LECTURA', 'operador@test.com', '+51951111222', '\$2y\$10\$abcdefghijklmnopqrstuu', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (61099, 3) ON DUPLICATE KEY UPDATE `rol_id` = 3");

    // Contextos de operación
    $ctxAdminTenantA = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 24, // Superadmin con todos los permisos
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'test-corr-cat-001',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/TestRunner',
        organizacionId: $tenantA
    );

    $ctxOperadorTenantA = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 61099,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'test-corr-cat-002',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/TestRunner',
        organizacionId: $tenantA
    );

    // ==============================================================================
    // BLOQUE 2: CATEGORÍAS DE CATÁLOGO (MISMO TENANT, CROSS-TENANT, CÓDIGO ÚNICO)
    // ==============================================================================
    echo "\n--- BLOQUE 2: CATEGORÍAS DE CATÁLOGO ---\n";

    // 2.1 Creación exitosa en tenant A
    $catA1 = $catalogoServicio->crearCategoria(
        organizacionId: $tenantA,
        codigo: 'TRIBUNAS_GRAN_PARADA',
        nombre: 'Tribunas y Asientos en Parada',
        descripcion: 'Asientos numerados para la Gran Parada y Veneración',
        orden: 1,
        contexto: $ctxAdminTenantA
    );
    afirmar($catA1->id !== null && $catA1->codigo === 'TRIBUNAS_GRAN_PARADA', "2.1: Categoría creada exitosamente en Tenant A");

    // 2.2 Duplicado de código en mismo tenant es rechazado
    $duplicadoCatRechazado = false;
    try {
        $catalogoServicio->crearCategoria(
            organizacionId: $tenantA,
            codigo: 'TRIBUNAS_GRAN_PARADA',
            nombre: 'Intento Duplicado',
            descripcion: null,
            orden: 2,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $duplicadoCatRechazado = true;
    }
    afirmar($duplicadoCatRechazado, "2.2: Creación con código duplicado en mismo tenant es rechazada");

    // 2.3 Mismo código en diferente tenant está permitido (soberanía multi-tenant)
    $catB1 = $catalogoServicio->crearCategoria(
        organizacionId: $tenantB,
        codigo: 'TRIBUNAS_GRAN_PARADA',
        nombre: 'Tribunas Tenant B',
        descripcion: null,
        orden: 1,
        contexto: new ContextoOperacion(
            actorTipo: 'HUMANO',
            usuarioId: 24,
            actorSistemaId: null,
            actorSistemaCodigo: null,
            canalId: 1,
            canalCodigo: 'APP',
            correlacionId: ContextoOperacion::generarCorrelacionId(),
            origenIp: '127.0.0.1',
            agenteUsuario: 'Test',
            organizacionId: $tenantB
        )
    );
    afirmar($catB1->id !== null && $catB1->organizacionId === $tenantB, "2.3: Mismo código en diferente tenant está permitido (soberanía multi-tenant)");

    // 2.4 Actualización de categoría
    $catA1Editada = $catalogoServicio->actualizarCategoria(
        organizacionId: $tenantA,
        categoriaId: (int) $catA1->id,
        nombre: 'Tribunas y Asientos Actualizados',
        descripcion: 'Descripción actualizada',
        orden: 2,
        contexto: $ctxAdminTenantA
    );
    afirmar($catA1Editada->nombre === 'Tribunas y Asientos Actualizados', "2.4: Actualización de categoría exitosa");

    // 2.5 Rechazo Anti-IDOR al intentar actualizar categoría de otro tenant
    $crossCatUpdateRechazado = false;
    try {
        $catalogoServicio->actualizarCategoria(
            organizacionId: $tenantA,
            categoriaId: (int) $catB1->id, // Categoría de tenant B
            nombre: 'Intento Hack Cat',
            descripcion: null,
            orden: 1,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $crossCatUpdateRechazado = true;
    }
    afirmar($crossCatUpdateRechazado, "2.5: Rechazo Anti-IDOR al intentar actualizar categoría de otro tenant");

    // 2.6 Desactivación lógica de categoría
    $okDesactivar = $catalogoServicio->cambiarEstadoCategoria(
        organizacionId: $tenantA,
        categoriaId: (int) $catA1->id,
        nuevoEstado: EstadoCatalogo::INACTIVO,
        contexto: $ctxAdminTenantA
    );
    afirmar($okDesactivar, "2.6: Desactivación lógica de categoría exitosa");

    // Reactivar para siguientes pruebas
    $catalogoServicio->cambiarEstadoCategoria($tenantA, (int) $catA1->id, EstadoCatalogo::ACTIVO, $ctxAdminTenantA);

    // ==============================================================================
    // BLOQUE 3: ÍTEMS COMERCIALES (PRODUCTO, SERVICIO, UNIDAD VÁLIDA/INVÁLIDA, CROSS-TENANT)
    // ==============================================================================
    echo "\n--- BLOQUE 3: ÍTEMS COMERCIALES ---\n";

    // 3.1 Creación de SERVICIO con unidad válida
    $itemServicio = $catalogoServicio->crearItem(
        organizacionId: $tenantA,
        categoriaId: (int) $catA1->id,
        codigo: 'SERV_TRIBUNA_SABADO',
        nombre: 'Asiento en Tribuna Sábado',
        tipo: TipoItemComercial::SERVICIO,
        unidadMedida: 'TICKET',
        descripcion: 'Asiento numerado en sector preferencial',
        contexto: $ctxAdminTenantA
    );
    afirmar($itemServicio->tipo === TipoItemComercial::SERVICIO && $itemServicio->unidadMedida === UnidadMedidaItem::TICKET, "3.1: Ítem tipo SERVICIO con unidad 'TICKET' creado exitosamente");

    // 3.2 Creación de PRODUCTO con unidad válida
    $itemProducto = $catalogoServicio->crearItem(
        organizacionId: $tenantA,
        categoriaId: (int) $catA1->id,
        codigo: 'PROD_POLO_OFICIAL_2026',
        nombre: 'Polo Oficial Candelaria 2026',
        tipo: TipoItemComercial::PRODUCTO,
        unidadMedida: 'UNIDAD',
        descripcion: 'Polo conmemorativo de algodón',
        contexto: $ctxAdminTenantA
    );
    afirmar($itemProducto->tipo === TipoItemComercial::PRODUCTO && $itemProducto->unidadMedida === UnidadMedidaItem::UNIDAD, "3.2: Ítem tipo PRODUCTO con unidad 'UNIDAD' creado exitosamente");

    // 3.3 Rechazo de unidad de medida inválida
    $unidadInvalidaRechazada = false;
    try {
        $catalogoServicio->crearItem(
            organizacionId: $tenantA,
            categoriaId: (int) $catA1->id,
            codigo: 'ITEM_UNIDAD_INVALIDA',
            nombre: 'Ítem Inválido',
            tipo: TipoItemComercial::SERVICIO,
            unidadMedida: 'METRO_CUADRADO',
            descripcion: null,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $unidadInvalidaRechazada = true;
    }
    afirmar($unidadInvalidaRechazada, "3.3: Rechazo estricto ante unidad de medida no contemplada en el catálogo cerrado");

    // 3.4 Rechazo de categoría perteneciente a otro tenant (Anti-IDOR)
    $crossCatRechazado = false;
    try {
        $catalogoServicio->crearItem(
            organizacionId: $tenantA,
            categoriaId: (int) $catB1->id, // Categoría de tenant B
            codigo: 'ITEM_CROSS_TENANT',
            nombre: 'Intento Cross Tenant',
            tipo: TipoItemComercial::SERVICIO,
            unidadMedida: 'SERVICIO',
            descripcion: null,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $crossCatRechazado = true;
    }
    afirmar($crossCatRechazado, "3.4: Rechazo Anti-IDOR al intentar vincular ítem a categoría de otro tenant");

    // ==============================================================================
    // BLOQUE 4: PAQUETES COMERCIALES Y COMPOSICIÓN (SAME-TENANT, CROSS-TENANT, DUPLICADOS)
    // ==============================================================================
    echo "\n--- BLOQUE 4: PAQUETES COMERCIALES Y COMPOSICIÓN ---\n";

    // 4.1 Creación de paquete comercial
    $paqueteA = $catalogoServicio->crearPaquete(
        organizacionId: $tenantA,
        codigo: 'PAQ_CANDELARIA_VIP',
        nombre: 'Paquete Candelaria VIP 3D/2N',
        descripcion: 'Incluye tribuna, hospedaje y merchandising oficial',
        contexto: $ctxAdminTenantA
    );
    afirmar($paqueteA->id !== null && $paqueteA->codigo === 'PAQ_CANDELARIA_VIP', "4.1: Paquete comercial creado exitosamente");

    // 4.2 Sincronización de composición de ítems (misma organización)
    $paqueteCompuesto = $catalogoServicio->sincronizarComposicionPaquete(
        organizacionId: $tenantA,
        paqueteId: (int) $paqueteA->id,
        itemsDef: [
            ['item_comercial_id' => (int) $itemServicio->id, 'cantidad' => 1.0, 'orden' => 1],
            ['item_comercial_id' => (int) $itemProducto->id, 'cantidad' => 2.0, 'orden' => 2],
        ],
        contexto: $ctxAdminTenantA
    );
    afirmar(count($paqueteCompuesto->items) === 2, "4.2: Composición de paquete configurada con 2 ítems incluidos");

    // 4.3 Rechazo de ítem cross-tenant en la composición
    $itemCrossTenantRechazado = false;
    $itemB = $catalogoServicio->crearItem(
        organizacionId: $tenantB,
        categoriaId: (int) $catB1->id,
        codigo: 'ITEM_TENANT_B',
        nombre: 'Ítem de Tenant B',
        tipo: TipoItemComercial::SERVICIO,
        unidadMedida: 'SERVICIO',
        descripcion: null,
        contexto: new ContextoOperacion(
            actorTipo: 'HUMANO',
            usuarioId: 24,
            actorSistemaId: null,
            actorSistemaCodigo: null,
            canalId: 1,
            canalCodigo: 'APP',
            correlacionId: ContextoOperacion::generarCorrelacionId(),
            origenIp: '127.0.0.1',
            agenteUsuario: 'Test',
            organizacionId: $tenantB
        )
    );

    try {
        $catalogoServicio->sincronizarComposicionPaquete(
            organizacionId: $tenantA,
            paqueteId: (int) $paqueteA->id,
            itemsDef: [
                ['item_comercial_id' => (int) $itemB->id, 'cantidad' => 1.0, 'orden' => 1],
            ],
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $itemCrossTenantRechazado = true;
    }
    afirmar($itemCrossTenantRechazado, "4.3: Rechazo Anti-IDOR al intentar incluir ítem de otro tenant en un paquete");

    // 4.4 Rechazo de cantidad <= 0
    $cantidadCeroRechazada = false;
    try {
        $catalogoServicio->sincronizarComposicionPaquete(
            organizacionId: $tenantA,
            paqueteId: (int) $paqueteA->id,
            itemsDef: [
                ['item_comercial_id' => (int) $itemServicio->id, 'cantidad' => 0.0, 'orden' => 1],
            ],
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $cantidadCeroRechazada = true;
    }
    afirmar($cantidadCeroRechazada, "4.4: Rechazo ante cantidad menor o igual a cero en composición de paquete");

    // 4.5 Rechazo de ítem duplicado en la misma composición
    $duplicadoItemEnPaqueteRechazado = false;
    try {
        $catalogoServicio->sincronizarComposicionPaquete(
            organizacionId: $tenantA,
            paqueteId: (int) $paqueteA->id,
            itemsDef: [
                ['item_comercial_id' => (int) $itemServicio->id, 'cantidad' => 1.0, 'orden' => 1],
                ['item_comercial_id' => (int) $itemServicio->id, 'cantidad' => 2.0, 'orden' => 2],
            ],
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $duplicadoItemEnPaqueteRechazado = true;
    }
    afirmar($duplicadoItemEnPaqueteRechazado, "4.5: Rechazo ante ítem duplicado en la composición del paquete");

    // ==============================================================================
    // BLOQUE 5: OFERTAS POR EDICIÓN (ÍTEM, PAQUETE, EDICIÓN CROSS-TENANT, CAPACIDAD)
    // ==============================================================================
    echo "\n--- BLOQUE 5: OFERTAS POR EDICIÓN FOLCLÓRICA ---\n";

    // 5.1 Habilitar oferta de ítem con capacidad referencial NULL
    $ofertaItem1 = $catalogoServicio->habilitarOfertaItem(
        organizacionId: $tenantA,
        edicionId: $edicionAId,
        itemComercialId: (int) $itemServicio->id,
        capacidadReferencial: null,
        contexto: $ctxAdminTenantA
    );
    afirmar($ofertaItem1->id !== null && $ofertaItem1->capacidadReferencial === null, "5.1: Oferta de ítem habilitada con capacidad_referencial NULL (no especificada)");

    // 5.2 Habilitar oferta de paquete con capacidad referencial entera positiva
    $ofertaPaquete1 = $catalogoServicio->habilitarOfertaPaquete(
        organizacionId: $tenantA,
        edicionId: $edicionAId,
        paqueteId: (int) $paqueteA->id,
        capacidadReferencial: 50,
        contexto: $ctxAdminTenantA
    );
    afirmar($ofertaPaquete1->id !== null && $ofertaPaquete1->capacidadReferencial === 50, "5.2: Oferta de paquete habilitada con capacidad_referencial informativa = 50");

    // 5.3 Rechazo de edición perteneciente a otro tenant (Anti-IDOR)
    $edicionCrossTenantRechazada = false;
    try {
        $catalogoServicio->habilitarOfertaItem(
            organizacionId: $tenantA,
            edicionId: $edicionBId, // Edición de Tenant B
            itemComercialId: (int) $itemServicio->id,
            capacidadReferencial: null,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $edicionCrossTenantRechazada = true;
    }
    afirmar($edicionCrossTenantRechazada, "5.3: Rechazo Anti-IDOR al ofertar ítem en edición de otro tenant");

    // 5.4 Rechazo de oferta duplicada para la misma edición
    $ofertaDuplicadaRechazada = false;
    try {
        $catalogoServicio->habilitarOfertaItem(
            organizacionId: $tenantA,
            edicionId: $edicionAId,
            itemComercialId: (int) $itemServicio->id,
            capacidadReferencial: 10,
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $ofertaDuplicadaRechazada = true;
    }
    afirmar($ofertaDuplicadaRechazada, "5.4: Rechazo ante oferta duplicada del mismo ítem en la misma edición");

    // ==============================================================================
    // BLOQUE 6: TARIFAS VIGENTES, FAIL-CLOSED DE MONEDA Y REGLAS DE PRECIO
    // ==============================================================================
    echo "\n--- BLOQUE 6: TARIFAS VIGENTES Y MONEDA SOBERANA ---\n";

    // 6.1 Fijar tarifa inicial de ítem (adopta moneda de plataforma)
    $monedaPlataforma = (string) $configServicio->obtenerPlataforma('plataforma.moneda_principal');
    $tarifaItem1 = $catalogoServicio->fijarTarifaInicialItem(
        organizacionId: $tenantA,
        ofertaItemId: (int) $ofertaItem1->id,
        precio: 150.00,
        contexto: $ctxAdminTenantA
    );
    afirmar($tarifaItem1->precio === 150.00 && $tarifaItem1->moneda === $monedaPlataforma, "6.1: Tarifa inicial de ítem fijada en {$tarifaItem1->precio} con moneda soberana '{$tarifaItem1->moneda}'");

    // 6.2 Tarifa propia de paquete (precio independiente de los componentes)
    $tarifaPaquete1 = $catalogoServicio->fijarTarifaInicialPaquete(
        organizacionId: $tenantA,
        ofertaPaqueteId: (int) $ofertaPaquete1->id,
        precio: 2500.00,
        contexto: $ctxAdminTenantA
    );
    afirmar($tarifaPaquete1->precio === 2500.00 && $tarifaPaquete1->versionBloqueo === 1, "6.2: Tarifa inicial de paquete con precio propio soberano (2500.00) y version_bloqueo = 1");

    // 6.3 Precio cero está permitido (componente de cortesía/promocional)
    $ofertaItem2 = $catalogoServicio->habilitarOfertaItem(
        organizacionId: $tenantA,
        edicionId: $edicionAId,
        itemComercialId: (int) $itemProducto->id,
        capacidadReferencial: 100,
        contexto: $ctxAdminTenantA
    );
    $tarifaCero = $catalogoServicio->fijarTarifaInicialItem(
        organizacionId: $tenantA,
        ofertaItemId: (int) $ofertaItem2->id,
        precio: 0.00,
        contexto: $ctxAdminTenantA
    );
    afirmar($tarifaCero->precio === 0.00, "6.3: Precio cero (0.00) permitido para ítems promocionales/incluidos");

    // 6.4 Precio negativo es rechazado
    $precioNegativoRechazado = false;
    try {
        $catalogoServicio->actualizarTarifaItem(
            organizacionId: $tenantA,
            tarifaId: (int) $tarifaItem1->id,
            nuevoPrecio: -25.00,
            versionEsperada: 1,
            motivo: 'Intento precio negativo',
            contexto: $ctxAdminTenantA
        );
    } catch (InvalidArgumentException $e) {
        $precioNegativoRechazado = true;
    }
    afirmar($precioNegativoRechazado, "6.4: Rechazo estricto ante precio negativo");

    // 6.5 Fail-Closed si moneda de plataforma no está configurada o es inválida
    $configMockSinMoneda = new class extends ConfiguracionServicio {
        public function __construct() {}
        public function obtenerPlataforma(string $codigo, mixed $defecto = null): mixed { return null; }
    };
    $catalogoMockSinMoneda = new CatalogoServicio(
        categoriaRepo: $categoriaRepo,
        itemRepo: $itemRepo,
        paqueteRepo: $paqueteRepo,
        ofertaItemRepo: $ofertaItemRepo,
        ofertaPaqueteRepo: $ofertaPaqueteRepo,
        tarifaItemRepo: $tarifaItemRepo,
        tarifaPaqueteRepo: $tarifaPaqueteRepo,
        historialRepo: $historialRepo,
        edicionRepo: $edicionRepo,
        authzServicio: $authzServicio,
        auditoriaRepo: $auditoriaRepo,
        configServicio: $configMockSinMoneda,
        pdo: $pdo
    );

    $failClosedSinMoneda = false;
    try {
        $catalogoMockSinMoneda->fijarTarifaInicialItem($tenantA, (int) $ofertaItem1->id, 100.0, $ctxAdminTenantA);
    } catch (RuntimeException $e) {
        $failClosedSinMoneda = true;
    }
    afirmar($failClosedSinMoneda, "6.5: Fail-Closed obligatorio (RuntimeException) si 'plataforma.moneda_principal' no está configurada");

    $configMockMonedaInvalida = new class extends ConfiguracionServicio {
        public function __construct() {}
        public function obtenerPlataforma(string $codigo, mixed $defecto = null): mixed { return 'INVALID'; }
    };
    $catalogoMockMonedaInvalida = new CatalogoServicio(
        categoriaRepo: $categoriaRepo,
        itemRepo: $itemRepo,
        paqueteRepo: $paqueteRepo,
        ofertaItemRepo: $ofertaItemRepo,
        ofertaPaqueteRepo: $ofertaPaqueteRepo,
        tarifaItemRepo: $tarifaItemRepo,
        tarifaPaqueteRepo: $tarifaPaqueteRepo,
        historialRepo: $historialRepo,
        edicionRepo: $edicionRepo,
        authzServicio: $authzServicio,
        auditoriaRepo: $auditoriaRepo,
        configServicio: $configMockMonedaInvalida,
        pdo: $pdo
    );

    $failClosedMonedaInvalida = false;
    try {
        $catalogoMockMonedaInvalida->fijarTarifaInicialItem($tenantA, (int) $ofertaItem1->id, 100.0, $ctxAdminTenantA);
    } catch (RuntimeException $e) {
        $failClosedMonedaInvalida = true;
    }
    afirmar($failClosedMonedaInvalida, "6.6: Rechazo estricto (RuntimeException) si la moneda configurada no cumple ISO 4217 de 3 caracteres");

    // ==============================================================================
    // BLOQUE 7: CAMBIO DE TARIFA, OPTIMISTIC LOCKING E HISTORIAL APPEND-ONLY
    // ==============================================================================
    echo "\n--- BLOQUE 7: CAMBIO DE TARIFA, CONCURRENCIA E HISTORIAL ---\n";

    // 7.1 Cambio de tarifa exitoso incrementa version_bloqueo
    $tarifaItem1Mod = $catalogoServicio->actualizarTarifaItem(
        organizacionId: $tenantA,
        tarifaId: (int) $tarifaItem1->id,
        nuevoPrecio: 180.00,
        versionEsperada: 1,
        motivo: 'Ajuste de temporada alta',
        contexto: $ctxAdminTenantA
    );
    afirmar($tarifaItem1Mod->precio === 180.00 && $tarifaItem1Mod->versionBloqueo === 2, "7.1: Tarifa actualizada a 180.00 y version_bloqueo incrementada a 2");

    // 7.2 Registro append-only en historial_tarifas_items
    $historialItem = $historialRepo->listarPorTarifaItem((int) $tarifaItem1->id);
    afirmar(count($historialItem) === 1, "7.2: Historial append-only de tarifa de ítem registra exactamente 1 transición");
    $hUltimo = $historialItem[0];
    afirmar(
        $hUltimo->precioAnterior === 150.00 &&
        $hUltimo->precioNuevo === 180.00 &&
        $hUltimo->actorTipo === 'HUMANO' &&
        $hUltimo->usuarioId === 24 &&
        $hUltimo->correlacionId === 'test-corr-cat-001',
        "7.3: Historial contiene precios exactos, actor HUMANO (usuario 24) y correlación preservada"
    );

    // 7.4 Conflicto de concurrencia optimista (versión esperada obsoleta)
    $conflictoDetectado = false;
    try {
        $catalogoServicio->actualizarTarifaItem(
            organizacionId: $tenantA,
            tarifaId: (int) $tarifaItem1->id,
            nuevoPrecio: 200.00,
            versionEsperada: 1, // La versión en BD ahora es 2
            motivo: 'Intento con versión desactualizada',
            contexto: $ctxAdminTenantA
        );
    } catch (ConflictoConcurrenciaExcepcion $e) {
        $conflictoDetectado = true;
    }
    afirmar($conflictoDetectado, "7.4: Optimistic locking lanza ConflictoConcurrenciaExcepcion ante versión obsoleta");

    // 7.5 Modificación de tarifa de paquete con actor SISTEMA
    $ctxSistema = new ContextoOperacion(
        actorTipo: 'SISTEMA',
        usuarioId: null,
        actorSistemaId: 1, // Cron / Batch
        actorSistemaCodigo: 'CRON',
        canalId: 2,
        canalCodigo: 'API',
        correlacionId: 'cron-actualizacion-tarifas',
        origenIp: '127.0.0.1',
        agenteUsuario: 'Daemon',
        organizacionId: $tenantA
    );

    $tarifaPaqueteMod = $catalogoServicio->actualizarTarifaPaquete(
        organizacionId: $tenantA,
        tarifaId: (int) $tarifaPaquete1->id,
        nuevoPrecio: 2750.00,
        versionEsperada: 1,
        motivo: 'Ajuste automático del sistema',
        contexto: $ctxSistema
    );
    afirmar($tarifaPaqueteMod->precio === 2750.00 && $tarifaPaqueteMod->versionBloqueo === 2, "7.5: Tarifa de paquete actualizada a 2750.00 por actor SISTEMA");

    $historialPaquete = $historialRepo->listarPorTarifaPaquete((int) $tarifaPaquete1->id);
    afirmar(count($historialPaquete) === 1 && $historialPaquete[0]->actorTipo === 'SISTEMA', "7.6: Historial de paquete registra actor SISTEMA con integridad de CHECK");

    // ==============================================================================
    // BLOQUE 8: RBAC Y AUTORIZACIÓN GRANULAR
    // ==============================================================================
    echo "\n--- BLOQUE 8: RBAC Y AUTORIZACIÓN GRANULAR ---\n";

    // 8.1 Operador sin permiso 'catalogo.categorias.gestionar'
    $rbacCatRechazado = false;
    try {
        $catalogoServicio->crearCategoria($tenantA, 'CAT_NO_AUTH', 'No Auth', null, 99, $ctxOperadorTenantA);
    } catch (AccesoDenegadoExcepcion $e) {
        $rbacCatRechazado = true;
    }
    afirmar($rbacCatRechazado, "8.1: RBAC rechaza creación de categoría a usuario sin 'catalogo.categorias.gestionar'");

    // 8.2 Operador sin permiso 'catalogo.items.gestionar'
    $rbacItemRechazado = false;
    try {
        $catalogoServicio->crearItem($tenantA, (int) $catA1->id, 'ITEM_NO_AUTH', 'No Auth', TipoItemComercial::SERVICIO, 'SERVICIO', null, $ctxOperadorTenantA);
    } catch (AccesoDenegadoExcepcion $e) {
        $rbacItemRechazado = true;
    }
    afirmar($rbacItemRechazado, "8.2: RBAC rechaza creación de ítem a usuario sin 'catalogo.items.gestionar'");

    // 8.3 Operador sin permiso 'catalogo.paquetes.gestionar'
    $rbacPaqRechazado = false;
    try {
        $catalogoServicio->crearPaquete($tenantA, 'PAQ_NO_AUTH', 'No Auth', null, $ctxOperadorTenantA);
    } catch (AccesoDenegadoExcepcion $e) {
        $rbacPaqRechazado = true;
    }
    afirmar($rbacPaqRechazado, "8.3: RBAC rechaza creación de paquete a usuario sin 'catalogo.paquetes.gestionar'");

    // 8.4 Operador sin permiso 'catalogo.ofertas.gestionar'
    $rbacOfertaRechazado = false;
    try {
        $catalogoServicio->habilitarOfertaItem($tenantA, $edicionAId, (int) $itemServicio->id, null, $ctxOperadorTenantA);
    } catch (AccesoDenegadoExcepcion $e) {
        $rbacOfertaRechazado = true;
    }
    afirmar($rbacOfertaRechazado, "8.4: RBAC rechaza habilitación de oferta a usuario sin 'catalogo.ofertas.gestionar'");

    // 8.5 Operador sin permiso 'catalogo.tarifas.gestionar'
    $rbacTarifaRechazado = false;
    try {
        $catalogoServicio->actualizarTarifaItem($tenantA, (int) $tarifaItem1->id, 190.0, 2, 'No Auth', $ctxOperadorTenantA);
    } catch (AccesoDenegadoExcepcion $e) {
        $rbacTarifaRechazado = true;
    }
    afirmar($rbacTarifaRechazado, "8.5: RBAC rechaza modificación de tarifa a usuario sin 'catalogo.tarifas.gestionar'");

    // 8.6 Lectura permitida con 'catalogo.ver' (Operador consulta sin permisos de gestión)
    $catConsultada = $catalogoServicio->obtenerCategoria($tenantA, (int) $catA1->id, $ctxOperadorTenantA);
    $itemsConsultados = $catalogoServicio->listarItems($tenantA, null, null, null, $ctxOperadorTenantA);
    $paqConsultado = $catalogoServicio->obtenerPaquete($tenantA, (int) $paqueteA->id, $ctxOperadorTenantA);
    $ofertaItemConsultada = $catalogoServicio->obtenerOfertaItem($tenantA, (int) $ofertaItem1->id, $ctxOperadorTenantA);
    $tarifaVigenteConsultada = $catalogoServicio->obtenerTarifaVigenteItem($tenantA, (int) $ofertaItem1->id, $ctxOperadorTenantA);

    afirmar(
        $catConsultada !== null && count($itemsConsultados) >= 2 && $paqConsultado !== null &&
        $ofertaItemConsultada !== null && $tarifaVigenteConsultada !== null,
        "8.6: Lectura permitida y exitosa para usuario con 'catalogo.ver'"
    );

    // 8.7 Lectura NO requiere permisos de gestión (Operador no tiene permisos 'catalogo.*.gestionar' y aun así lee)
    afirmar(
        !$authzServicio->tienePermiso(61099, 'catalogo.categorias.gestionar') &&
        !$authzServicio->tienePermiso(61099, 'catalogo.items.gestionar') &&
        !$authzServicio->tienePermiso(61099, 'catalogo.tarifas.gestionar') &&
        $catConsultada !== null,
        "8.7: Lectura de catálogo no requiere permisos de gestión (separación VER != GESTIONAR)"
    );

    // 8.8 Usuario sin 'catalogo.ver' es rechazado en todas las operaciones de lectura
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (81098, 10000, 'NATURAL', 'SIN', 'PERMISOS', '+51951999666', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`) VALUES (61098, 10000, 81098, 'sin.permisos', 'USUARIO SIN PERMISOS', 'sinperm@test.com', '+51951111333', '\$2y\$10\$abcdefghijklmnopqrstuu', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $ctxSinPermisos = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 61098,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'test-corr-cat-003',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/TestRunner',
        organizacionId: $tenantA
    );

    $lecturaSinPermisoRechazada = false;
    try {
        $catalogoServicio->obtenerCategoria($tenantA, (int) $catA1->id, $ctxSinPermisos);
    } catch (AccesoDenegadoExcepcion $e) {
        $lecturaSinPermisoRechazada = true;
    }
    afirmar($lecturaSinPermisoRechazada, "8.8: Lectura de catálogo sin permiso 'catalogo.ver' es rechazada estrictamente");

    // 8.9 Operador con 'catalogo.ver' NO puede ver historial de tarifas (requiere 'catalogo.tarifas.ver_historial')
    $verHistorialSinPermisoRechazado = false;
    try {
        $catalogoServicio->listarHistorialTarifasItem($tenantA, (int) $tarifaItem1->id, $ctxOperadorTenantA);
    } catch (AccesoDenegadoExcepcion $e) {
        $verHistorialSinPermisoRechazado = true;
    }
    afirmar($verHistorialSinPermisoRechazado, "8.9: Consulta de historial sin 'catalogo.tarifas.ver_historial' es rechazada (Operador con solo 'catalogo.ver')");

    // 8.10 Consulta de historial permitida para usuario con 'catalogo.tarifas.ver_historial' (Admin)
    $historialConsultado = $catalogoServicio->listarHistorialTarifasItem($tenantA, (int) $tarifaItem1->id, $ctxAdminTenantA);
    afirmar(count($historialConsultado) === 1, "8.10: Consulta de historial permitida exitosamente para usuario con 'catalogo.tarifas.ver_historial'");

    // 8.11 'catalogo.tarifas.ver_historial' NO permite cambiar tarifa (solo lectura de historial)
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (81097, 10000, 'NATURAL', 'SOLO', 'HISTORIAL', '+51951999555', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`) VALUES (61097, 10000, 81097, 'solo.historial', 'AUDITOR HISTORIAL', 'auditor@test.com', '+51951111444', '\$2y\$10\$abcdefghijklmnopqrstuu', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $pdo->exec("INSERT INTO `roles` (`id`, `codigo`, `nombre`, `descripcion`) VALUES (999, 'auditor_tarifas', 'Auditor de Tarifas', 'Solo ver historial') ON DUPLICATE KEY UPDATE `nombre` = 'Auditor de Tarifas'");
    $pdo->exec("INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES (999, 40) ON DUPLICATE KEY UPDATE `permiso_id` = 40");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (61097, 999) ON DUPLICATE KEY UPDATE `rol_id` = 999");

    $ctxSoloHistorial = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 61097,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'test-corr-cat-004',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/TestRunner',
        organizacionId: $tenantA
    );

    $cambiarTarifaConSoloHistorialRechazado = false;
    try {
        $catalogoServicio->actualizarTarifaItem($tenantA, (int) $tarifaItem1->id, 200.0, 2, 'Intento mutar con solo ver_historial', $ctxSoloHistorial);
    } catch (AccesoDenegadoExcepcion $e) {
        $cambiarTarifaConSoloHistorialRechazado = true;
    }
    afirmar($cambiarTarifaConSoloHistorialRechazado, "8.11: Permiso 'catalogo.tarifas.ver_historial' NO permite cambiar tarifa (exclusivo para lectura)");

    // 8.12 Rol con 'catalogo.tarifas.gestionar' no accede a historial si no se le concede 'catalogo.tarifas.ver_historial'
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (81096, 10000, 'NATURAL', 'GESTOR', 'TARIFA', '+51951999444', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`) VALUES (61096, 10000, 81096, 'gestor.tarifa', 'GESTOR TARIFA', 'gestortarifa@test.com', '+51951111555', '\$2y\$10\$abcdefghijklmnopqrstuu', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $pdo->exec("INSERT INTO `roles` (`id`, `codigo`, `nombre`, `descripcion`) VALUES (998, 'gestor_tarifas_sin_historial', 'Gestor Sin Historial', 'Solo gestionar tarifas') ON DUPLICATE KEY UPDATE `nombre` = 'Gestor Sin Historial'");
    $pdo->exec("INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES (998, 39) ON DUPLICATE KEY UPDATE `permiso_id` = 39");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (61096, 998) ON DUPLICATE KEY UPDATE `rol_id` = 998");

    $ctxSoloGestionar = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 61096,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'test-corr-cat-005',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/TestRunner',
        organizacionId: $tenantA
    );

    $verHistorialConSoloGestionarRechazado = false;
    try {
        $catalogoServicio->listarHistorialTarifasItem($tenantA, (int) $tarifaItem1->id, $ctxSoloGestionar);
    } catch (AccesoDenegadoExcepcion $e) {
        $verHistorialConSoloGestionarRechazado = true;
    }
    afirmar($verHistorialConSoloGestionarRechazado, "8.12: Permiso 'catalogo.tarifas.gestionar' no implica acceso al historial (independencia RBAC)");

    // ==============================================================================
    // BLOQUE 9: INTEGRIDAD REFERENCIAL, AUDITORÍA Y NO BORRADO FÍSICO
    // ==============================================================================
    echo "\n--- BLOQUE 9: INTEGRIDAD REFERENCIAL, AUDITORÍA Y NO BORRADO FÍSICO ---\n";

    // 9.1 ON DELETE RESTRICT impide borrar ítem referenciado en una oferta
    $deleteItemRechazado = false;
    try {
        $stmtDel = $pdo->prepare("DELETE FROM `items_comerciales` WHERE `id` = :id");
        $stmtDel->execute(['id' => $itemServicio->id]);
    } catch (\PDOException $e) {
        $deleteItemRechazado = true;
    }
    afirmar($deleteItemRechazado, "9.1: ON DELETE RESTRICT impide eliminación física de ítem referenciado en ofertas/paquetes");

    // 9.2 ON DELETE RESTRICT impide borrar tarifa que posee historial
    $deleteTarifaRechazado = false;
    try {
        $stmtDelT = $pdo->prepare("DELETE FROM `tarifas_items_edicion` WHERE `id` = :id");
        $stmtDelT->execute(['id' => $tarifaItem1->id]);
    } catch (\PDOException $e) {
        $deleteTarifaRechazado = true;
    }
    afirmar($deleteTarifaRechazado, "9.2: ON DELETE RESTRICT protege tarifas con historial append-only");

    // 9.3 Verificación de registros en auditoria_operaciones
    $stmtAudit = $pdo->prepare("SELECT DISTINCT `accion` FROM `auditoria_operaciones` WHERE `modulo` = 'catalogo' AND `organizacion_id` = :org_id");
    $stmtAudit->execute(['org_id' => $tenantA]);
    $accionesAuditadas = $stmtAudit->fetchAll(PDO::FETCH_COLUMN);

    afirmar(in_array('CREAR_CATEGORIA_CATALOGO', $accionesAuditadas, true), "9.3: Auditoría técnica registra 'CREAR_CATEGORIA_CATALOGO'");
    afirmar(in_array('CREAR_ITEM_COMERCIAL', $accionesAuditadas, true), "9.4: Auditoría técnica registra 'CREAR_ITEM_COMERCIAL'");
    afirmar(in_array('CREAR_PAQUETE', $accionesAuditadas, true), "9.5: Auditoría técnica registra 'CREAR_PAQUETE'");
    afirmar(in_array('MODIFICAR_COMPOSICION_PAQUETE', $accionesAuditadas, true), "9.6: Auditoría técnica registra 'MODIFICAR_COMPOSICION_PAQUETE'");
    afirmar(in_array('HABILITAR_OFERTA_ITEM_EDICION', $accionesAuditadas, true), "9.7: Auditoría técnica registra 'HABILITAR_OFERTA_ITEM_EDICION'");
    afirmar(in_array('HABILITAR_OFERTA_PAQUETE_EDICION', $accionesAuditadas, true), "9.8: Auditoría técnica registra 'HABILITAR_OFERTA_PAQUETE_EDICION'");
    afirmar(in_array('CREAR_TARIFA_ITEM', $accionesAuditadas, true), "9.9: Auditoría técnica registra 'CREAR_TARIFA_ITEM'");
    afirmar(in_array('CAMBIAR_TARIFA_ITEM', $accionesAuditadas, true), "9.10: Auditoría técnica registra 'CAMBIAR_TARIFA_ITEM'");
    afirmar(in_array('CREAR_TARIFA_PAQUETE', $accionesAuditadas, true), "9.11: Auditoría técnica registra 'CREAR_TARIFA_PAQUETE'");
    afirmar(in_array('CAMBIAR_TARIFA_PAQUETE', $accionesAuditadas, true), "9.12: Auditoría técnica registra 'CAMBIAR_TARIFA_PAQUETE'");

} finally {
    // Revertir transacciones de prueba para dejar la base de datos intacta
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n[INFO] Transacción de pruebas F2.3B revertida con ROLLBACK determinista.\n";
    }
}

// ==============================================================================
// BLOQUE 10: CERTIFICACIÓN DE PRESERVACIÓN DE CREDENCIALES (ORLANDO ID 24)
// ==============================================================================
echo "\n--- BLOQUE 10: CERTIFICACIÓN DE PRESERVACIÓN DE CREDENCIALES (ORLANDO ID 24) ---\n";

$stmtOrlando = $pdo->prepare("SELECT `id`, `estado`, `intentos_fallidos`, `bloqueado_hasta`, SUBSTRING(SHA2(`contrasena_hash`, 256), 1, 16) AS `huella` FROM `usuarios` WHERE `id` = 24");
$stmtOrlando->execute();
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);

afirmar($orlando !== false, "10.1: Usuario Orlando (ID 24) existe en la base de datos");
afirmar($orlando['estado'] === 'ACTIVO', "10.2: Estado de Orlando es ACTIVO");
afirmar((int) $orlando['intentos_fallidos'] === 0, "10.3: Intentos fallidos de login de Orlando es exactamente 0");
afirmar($orlando['bloqueado_hasta'] === null, "10.4: Bloqueo temporal de cuenta de Orlando es NULL");
afirmar($orlando['huella'] === '80e6af84e02e89e3', "10.5: Huella criptográfica SHA-256 (truncada 16) coincide exactamente con '80e6af84e02e89e3'");

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.3B: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
exit(0);
