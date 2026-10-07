<?php

declare(strict_types=1);

namespace Pruebas;

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Cotizaciones\CotizacionServicio;
use Aplicacion\Cotizaciones\EstadoCotizacion;
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
use Aplicacion\Repositorios\EntregaProductoRepositorio;
use Aplicacion\Repositorios\HistorialEtapaRepositorio;
use Aplicacion\Repositorios\HistorialTarifaRepositorio;
use Aplicacion\Repositorios\InteraccionCrmRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\ItemConfiguracionOperativaRepositorio;
use Aplicacion\Repositorios\OfertaItemEdicionRepositorio;
use Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\OrigenComercialRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\ReservaRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Reservas\EstadoAgendamientoPrestacion;
use Aplicacion\Reservas\EstadoEntregaProducto;
use Aplicacion\Reservas\EstadoReserva;
use Aplicacion\Reservas\MotivoReprogramacion;
use Aplicacion\Reservas\RangoEtarioParticipante;
use Aplicacion\Reservas\RegimenAlimentario;
use Aplicacion\Reservas\ReservaServicio;
use Aplicacion\Reservas\TipoCapacidad;
use Aplicacion\Ventas\EstadoVenta;
use Aplicacion\Ventas\TipoDescuentoVenta;
use Aplicacion\Ventas\TipoLineaVenta;
use Aplicacion\Ventas\TipoOrigenVenta;
use Aplicacion\Ventas\VentaServicio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;
use RuntimeException;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F2.6C\n";
echo "NÚCLEO DE RESERVAS, PRESTACIONES, PARTICIPANTES, REPROGRAMACIÓN Y ENTREGAS\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

$exitos = 0;
$fallos = 0;

function afirmar(bool $condicion, string $mensaje): void
{
    global $exitos, $fallos;
    if ($condicion) {
        $exitos++;
        echo " [PASS] {$mensaje}\n";
    } else {
        $fallos++;
        echo " [FAIL] {$mensaje}\n";
    }
}

// Iniciar transacción de prueba para aislamiento total
$pdo->beginTransaction();

try {
    // ==============================================================================
    // BLOQUE 1: MIGRACIÓN, ESQUEMA Y METADATOS DDL
    // ==============================================================================
    echo "--- BLOQUE 1: MIGRACIÓN, ESTRUCTURA DDL Y PARIDAD DE ESQUEMA ---\n";

    $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    afirmar(in_array('reservas_secuencias', $tablas, true), "1.1: Tabla 'reservas_secuencias' existe");
    afirmar(in_array('entregas_secuencias', $tablas, true), "1.2: Tabla 'entregas_secuencias' existe");
    afirmar(in_array('item_configuracion_operativa', $tablas, true), "1.3: Tabla 'item_configuracion_operativa' existe");
    afirmar(in_array('reservas', $tablas, true), "1.4: Tabla 'reservas' existe");
    afirmar(in_array('reserva_prestaciones', $tablas, true), "1.5: Tabla 'reserva_prestaciones' existe");
    afirmar(in_array('reserva_participantes', $tablas, true), "1.6: Tabla 'reserva_participantes' existe");
    afirmar(in_array('prestacion_participantes', $tablas, true), "1.7: Tabla 'prestacion_participantes' existe");
    afirmar(in_array('reserva_reprogramaciones', $tablas, true), "1.8: Tabla 'reserva_reprogramaciones' existe");
    afirmar(in_array('entregas_productos', $tablas, true), "1.9: Tabla 'entregas_productos' existe");
    afirmar(in_array('entrega_items', $tablas, true), "1.10: Tabla 'entrega_items' existe");

    // Verificar Módulo 22 y Permisos 54-59
    $stmtMod = $pdo->prepare("SELECT `nombre`, `codigo` FROM `modulos` WHERE `id` = 22");
    $stmtMod->execute();
    $mod22 = $stmtMod->fetch(PDO::FETCH_ASSOC);
    afirmar($mod22 !== false && $mod22['codigo'] === 'reservas', "1.11: Módulo 22 'reservas' registrado formalmente");

    $stmtPerms = $pdo->query("SELECT `id`, `codigo` FROM `permisos` WHERE `modulo_id` = 22 ORDER BY `id` ASC");
    $permsReservas = $stmtPerms->fetchAll(PDO::FETCH_KEY_PAIR);
    afirmar(count($permsReservas) === 6, "1.12: Exactamente 6 permisos canónicos en módulo 22");
    afirmar(isset($permsReservas[54]) && $permsReservas[54] === 'reservas.ver', "1.13: Permiso 54 'reservas.ver' registrado");
    afirmar(isset($permsReservas[55]) && $permsReservas[55] === 'reservas.crear_desde_venta', "1.14: Permiso 55 'reservas.crear_desde_venta' registrado");
    afirmar(isset($permsReservas[56]) && $permsReservas[56] === 'reservas.programar', "1.15: Permiso 56 'reservas.programar' registrado");
    afirmar(isset($permsReservas[57]) && $permsReservas[57] === 'reservas.reprogramar', "1.16: Permiso 57 'reservas.reprogramar' registrado");
    afirmar(isset($permsReservas[58]) && $permsReservas[58] === 'reservas.gestionar_participantes', "1.17: Permiso 58 'reservas.gestionar_participantes' registrado");
    afirmar(isset($permsReservas[59]) && $permsReservas[59] === 'reservas.cancelar', "1.18: Permiso 59 'reservas.cancelar' registrado");

    $stmtMig = $pdo->query("SELECT COUNT(*) FROM `migraciones_control` WHERE `migracion` LIKE '%000014_crear_nucleo_reservas%'");
    afirmar((int) $stmtMig->fetchColumn() === 1, "1.19: Migración 000014 registrada en migraciones_control");

    // ==============================================================================
    // SETUP DE FIXTURES SINTÉTICOS (ZERO-PII)
    // ==============================================================================
    $orgId = 10000;
    $edicionId = 2026;

    // Asegurar edición 2026 para org 10000
    $pdo->exec("
        INSERT INTO `ediciones_candelaria` (`id`, `organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`)
        VALUES (2026, 10000, 'candelaria-2026-f26c', 'FESTIVIDAD CANDELARIA 2026 F26C', 2026, 'OPERACION', '2026-02-01', '2026-02-15')
        ON DUPLICATE KEY UPDATE `estado` = 'OPERACION'
    ");

    // Actores sintéticos
    $adminSinteticoId = 8801;
    $operadorSinteticoId = 8802;

    $pdo->exec("
        INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`)
        VALUES
        (8801, 10000, 'NATURAL', 1, '88880001', 'ADMIN', 'SINTETICO F26C', 'admin.f26c@test.local'),
        (8802, 10000, 'NATURAL', 1, '88880002', 'OPERADOR', 'SINTETICO F26C', 'operador.f26c@test.local'),
        (8803, 10000, 'NATURAL', 1, '88880003', 'CLIENTE', 'COMPRADOR F26C', 'cliente.f26c@test.local')
        ON DUPLICATE KEY UPDATE `nombres` = VALUES(`nombres`)
    ");

    $pdo->exec("
        INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `es_superadmin_plataforma`, `estado`)
        VALUES
        (8801, 10000, 8801, 'admin_sintetico_f26c', 'Admin Sintetico F26C', 'admin.f26c@test.local', '\$2y\$10\$abcdefghijklmnopqrstuv', 1, 'ACTIVO'),
        (8802, 10000, 8802, 'operador_sintetico_f26c', 'Operador Sintetico F26C', 'operador.f26c@test.local', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO')
        ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'
    ");

    $pdo->exec("INSERT IGNORE INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (8801, 1), (8802, 3)");

    $pdo->exec("
        INSERT INTO `clientes` (`id`, `organizacion_id`, `persona_id`, `estado_comercial`)
        VALUES (8803, 10000, 8803, 'CLIENTE')
        ON DUPLICATE KEY UPDATE `estado_comercial` = 'CLIENTE'
    ");

    // Instanciar servicios y repositorios
    $auditoriaRepo   = new AuditoriaRepositorio($pdo);
    $authzServicio   = new AutorizacionServicio(pdo: $pdo);
    $edicionRepo     = new EdicionRepositorio($pdo);
    $itemRepo        = new ItemComercialRepositorio($pdo);
    $ventaRepo       = new VentaRepositorio($pdo);
    $clienteRepo     = new ClienteRepositorio($pdo);
    $personaRepo     = new PersonaRepositorio($pdo);
    $itemCfgRepo     = new ItemConfiguracionOperativaRepositorio($pdo);
    $reservaRepo     = new ReservaRepositorio($pdo);
    $entregaRepo     = new EntregaProductoRepositorio($pdo);

    $reservaServicio = new ReservaServicio(
        reservaRepo: $reservaRepo,
        entregaRepo: $entregaRepo,
        itemCfgRepo: $itemCfgRepo,
        ventaRepo: $ventaRepo,
        edicionRepo: $edicionRepo,
        itemRepo: $itemRepo,
        authzServicio: $authzServicio,
        auditoriaRepo: $auditoriaRepo,
        pdo: $pdo
    );

    $ctxAdmin = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: $adminSinteticoId,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-rsv-admin-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF26C/Admin',
        organizacionId: $orgId
    );

    $ctxOperador = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: $operadorSinteticoId,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-rsv-op-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF26C/Operador',
        organizacionId: $orgId
    );

    // ==============================================================================
    // BLOQUE 2: RBAC Y AUTORIZACIÓN SOBERANA
    // ==============================================================================
    echo "\n--- BLOQUE 2: RBAC Y AUTORIZACIÓN SOBERANA ---\n";

    afirmar($authzServicio->tienePermiso($adminSinteticoId, 'reservas.ver'), "2.1: Admin tiene 'reservas.ver'");
    afirmar($authzServicio->tienePermiso($adminSinteticoId, 'reservas.crear_desde_venta'), "2.2: Admin tiene 'reservas.crear_desde_venta'");
    afirmar($authzServicio->tienePermiso($operadorSinteticoId, 'reservas.ver'), "2.3: Operador tiene 'reservas.ver'");
    afirmar($authzServicio->tienePermiso($operadorSinteticoId, 'reservas.programar'), "2.4: Operador tiene 'reservas.programar'");
    afirmar(!$authzServicio->tienePermiso($operadorSinteticoId, 'reservas.crear_desde_venta'), "2.5: Operador NO tiene 'reservas.crear_desde_venta'");
    afirmar(!$authzServicio->tienePermiso($operadorSinteticoId, 'reservas.cancelar'), "2.6: Operador NO tiene 'reservas.cancelar'");

    // Intentar crear reserva con operador sin permiso
    $bloqueoRbac = false;
    try {
        $reservaServicio->formalizarDesdeVenta($orgId, 999999, $ctxOperador);
    } catch (AccesoDenegadoExcepcion $e) {
        $bloqueoRbac = true;
    }
    afirmar($bloqueoRbac, "2.7: Operador sin permiso es bloqueado con AccesoDenegadoExcepcion");

    // ==============================================================================
    // BLOQUE 3: CONFIGURACIÓN OPERATIVA EXPLÍCITA Y FAIL-CLOSED
    // ==============================================================================
    echo "\n--- BLOQUE 3: CONFIGURACIÓN OPERATIVA EXPLÍCITA Y FAIL-CLOSED ---\n";

    // Crear categoría comercial e ítems sintéticos
    $catRepo = new CategoriaItemRepositorio($pdo);
    $catRepo->guardar(new CategoriaItem(
        id: null,
        organizacionId: $orgId,
        codigo: 'CAT_F26C',
        nombre: 'CATEGORIA F26C',
        descripcion: 'Categoria de prueba'
    ));
    $cat = $catRepo->buscarPorCodigo('CAT_F26C', $orgId);

    // Ítem 1: Tour Lacustre (Servicio agendable colectivo)
    $itemRepo->guardar(new ItemComercial(
        id: null,
        organizacionId: $orgId,
        categoriaId: (int) $cat->id,
        codigo: 'SRV_TOUR_UROS_F26C',
        nombre: 'TOUR ISLAS UROS F26C',
        tipo: \Aplicacion\Catalogo\TipoItemComercial::SERVICIO,
        unidadMedida: \Aplicacion\Catalogo\UnidadMedidaItem::PERSONA
    ));
    $itemTour = $itemRepo->buscarPorCodigo('SRV_TOUR_UROS_F26C', $orgId);

    // Ítem 2: Polo Oficial Candelaria (Producto físico entregable)
    $itemRepo->guardar(new ItemComercial(
        id: null,
        organizacionId: $orgId,
        categoriaId: (int) $cat->id,
        codigo: 'PRD_POLO_OFICIAL_F26C',
        nombre: 'POLO OFICIAL CANDELARIA F26C',
        tipo: \Aplicacion\Catalogo\TipoItemComercial::PRODUCTO,
        unidadMedida: \Aplicacion\Catalogo\UnidadMedidaItem::UNIDAD
    ));
    $itemPolo = $itemRepo->buscarPorCodigo('PRD_POLO_OFICIAL_F26C', $orgId);

    // Configurar explícitamente ítem Tour
    $cfgTour = $reservaServicio->configurarOperativamenteItem($orgId, (int) $itemTour->id, [
        'requiere_reserva'            => true,
        'requiere_agendamiento'       => true,
        'requiere_participantes'      => true,
        'tipo_capacidad'              => 'COLECTIVA',
        'es_accesorio'                => false,
        'duracion_estimada_minutos'   => 180,
        'punto_partida_predeterminado'=> 'Muelle Lacustre Puno',
    ], $ctxAdmin);

    afirmar($cfgTour->requiereReserva === true, "3.1: Tour configurado explícitamente con requiereReserva = true");
    afirmar($cfgTour->tipoCapacidad === TipoCapacidad::COLECTIVA, "3.2: Tour configurado con capacidad COLECTIVA");
    afirmar($cfgTour->puntoPartidaPredeterminado === 'Muelle Lacustre Puno', "3.3: Punto de partida guardado");

    // Configurar explícitamente ítem Polo (Producto entregable sin reserva)
    $cfgPolo = $reservaServicio->configurarOperativamenteItem($orgId, (int) $itemPolo->id, [
        'requiere_reserva'            => false,
        'requiere_agendamiento'       => false,
        'requiere_participantes'      => false,
        'tipo_capacidad'              => 'SIN_CONTROL',
        'es_accesorio'                => false,
    ], $ctxAdmin);

    afirmar($cfgPolo->requiereReserva === false, "3.4: Polo configurado explícitamente con requiereReserva = false");

    // Invariante de coherencia: agendamiento=1 requiere reserva=1
    $coherenciaRechazada = false;
    try {
        $reservaServicio->configurarOperativamenteItem($orgId, (int) $itemTour->id, [
            'requiere_reserva'       => false,
            'requiere_agendamiento'  => true,
            'requiere_participantes' => false,
            'tipo_capacidad'         => 'SIN_CONTROL',
        ], $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $coherenciaRechazada = true;
    }
    afirmar($coherenciaRechazada, "3.5: Regla de coherencia: requiere_agendamiento=1 con requiere_reserva=0 es rechazada");

    // 3.6: Rechazo ante omisión de tipo_capacidad (cero default COLECTIVA)
    $omisionTipoCapRechazada = false;
    try {
        $reservaServicio->configurarOperativamenteItem($orgId, (int) $itemTour->id, [
            'requiere_reserva'       => true,
            'requiere_agendamiento'  => true,
            'requiere_participantes' => true,
        ], $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $omisionTipoCapRechazada = true;
    }
    afirmar($omisionTipoCapRechazada, "3.6: Omisión de tipo_capacidad es rechazada (sin default COLECTIVA)");

    // 3.7: Rechazo ante omisión de requiere_reserva (cero default true)
    $omisionReqResRechazada = false;
    try {
        $reservaServicio->configurarOperativamenteItem($orgId, (int) $itemTour->id, [
            'requiere_agendamiento'  => true,
            'requiere_participantes' => true,
            'tipo_capacidad'         => 'COLECTIVA',
        ], $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $omisionReqResRechazada = true;
    }
    afirmar($omisionReqResRechazada, "3.7: Omisión de requiere_reserva es rechazada (sin default true)");

    // 3.8: Fail-Closed: Venta de ítem sin configuración operativa es rechazada con RuntimeException
    $itemSinCfg = new ItemComercial(
        id: null,
        organizacionId: $orgId,
        categoriaId: (int) $cat->id,
        codigo: 'SRV_SIN_CFG_F26C',
        nombre: 'SERVICIO SIN CONFIGURACION OPERATIVA',
        descripcion: 'Item sin registro en item_configuracion_operativa',
        tipo: \Aplicacion\Catalogo\TipoItemComercial::SERVICIO,
        unidadMedida: \Aplicacion\Catalogo\UnidadMedidaItem::PERSONA
    );
    $itemRepo->guardar($itemSinCfg);
    $itemSinCfg = $itemRepo->buscarPorCodigo('SRV_SIN_CFG_F26C', $orgId);

    $stmtOfSinCfg = $pdo->prepare("
        INSERT INTO `ofertas_items_edicion` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`)
        VALUES (:org_id, :edicion_id, :item_id, 'ACTIVO')
    ");
    $stmtOfSinCfg->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'item_id'    => (int) $itemSinCfg->id,
    ]);
    $ofertaSinCfgId = (int) $pdo->lastInsertId();

    $stmtVSinCfg = $pdo->prepare("
        INSERT INTO `ventas` (
            `organizacion_id`, `edicion_id`, `cliente_id`, `origen_tipo`,
            `correlativo`, `fecha_venta`, `estado`,
            `cliente_nombre_completo`, `moneda`, `subtotal`, `total`, `creado_por`
        ) VALUES (
            :org_id, :edicion_id, :cliente_id, 'DIRECTA',
            'VTA-2026-880099', '2026-02-02', 'CONFIRMADA',
            'CLIENTE SIN CONFIGURACION', 'PEN', 150.00, 150.00, :creado_por
        )
    ");
    $stmtVSinCfg->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'cliente_id' => 8803,
        'creado_por' => $adminSinteticoId,
    ]);
    $ventaSinCfgId = (int) $pdo->lastInsertId();

    $stmtLSinCfg = $pdo->prepare("
        INSERT INTO `venta_lineas` (
            `venta_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`,
            `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`,
            `subtotal`, `moneda`
        ) VALUES (
            :venta_id, 'ITEM', :item_id, :of_id, 'SRV_SIN_CFG_F26C',
            'SERVICIO SIN CONFIGURACION OPERATIVA', 'PERSONA', 1.00, 150.00,
            150.00, 'PEN'
        )
    ");
    $stmtLSinCfg->execute([
        'venta_id' => $ventaSinCfgId,
        'item_id'  => (int) $itemSinCfg->id,
        'of_id'    => $ofertaSinCfgId,
    ]);

    $failClosedRechazado = false;
    try {
        $reservaServicio->formalizarDesdeVenta($orgId, $ventaSinCfgId, $ctxAdmin);
    } catch (RuntimeException $e) {
        $failClosedRechazado = str_contains($e->getMessage(), 'carece de configuración operativa explícita');
    }
    afirmar($failClosedRechazado, "3.8: Fail-Closed: Ítem sin configuración operativa aborta formalización sin producir reserva");

    // ==============================================================================
    // BLOQUE 4: GENERACIÓN ATÓMICA VENTA -> RESERVA (1:1 E IDEMPOTENCIA)
    // ==============================================================================
    echo "\n--- BLOQUE 4: GENERACIÓN VENTA -> RESERVA (1:1 E IDEMPOTENCIA) ---\n";

    // Crear oferta de ítem para Tour en la edición
    $stmtOfTour = $pdo->prepare("
        INSERT INTO `ofertas_items_edicion` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`)
        VALUES (:org_id, :edicion_id, :item_id, 'ACTIVO')
    ");
    $stmtOfTour->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'item_id'    => (int) $itemTour->id,
    ]);
    $ofertaTourId = (int) $pdo->lastInsertId();

    // Crear oferta de ítem para Polo en la edición
    $stmtOfPolo = $pdo->prepare("
        INSERT INTO `ofertas_items_edicion` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`)
        VALUES (:org_id, :edicion_id, :item_id, 'ACTIVO')
    ");
    $stmtOfPolo->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'item_id'    => (int) $itemPolo->id,
    ]);
    $ofertaPoloId = (int) $pdo->lastInsertId();

    // Crear una venta sintética CONFIRMADA de Tour Lacustre (2 cupos)
    $stmtV1 = $pdo->prepare("
        INSERT INTO `ventas` (
            `organizacion_id`, `edicion_id`, `cliente_id`, `origen_tipo`,
            `correlativo`, `fecha_venta`, `estado`,
            `cliente_nombre_completo`, `cliente_tipo_documento`, `cliente_numero_documento`,
            `cliente_telefono`, `cliente_email`, `moneda`, `subtotal`, `total`, `creado_por`
        ) VALUES (
            :org_id, :edicion_id, :cliente_id, 'DIRECTA',
            'VTA-2026-880001', '2026-02-02', 'CONFIRMADA',
            'CLIENTE COMPRADOR F26C', 'DNI', '88880003',
            '951000000', 'cliente.f26c@test.local', 'PEN', 200.00, 200.00, :creado_por
        )
    ");
    $stmtV1->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'cliente_id' => 8803,
        'creado_por' => $adminSinteticoId,
    ]);
    $venta1Id = (int) $pdo->lastInsertId();

    $stmtL1 = $pdo->prepare("
        INSERT INTO `venta_lineas` (
            `venta_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`,
            `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`,
            `subtotal`, `moneda`
        ) VALUES (
            :venta_id, 'ITEM', :item_id, :of_id, 'SRV_TOUR_UROS_F26C',
            'TOUR ISLAS UROS F26C', 'PERSONA', 2.00, 100.00,
            200.00, 'PEN'
        )
    ");
    $stmtL1->execute([
        'venta_id' => $venta1Id,
        'item_id'  => (int) $itemTour->id,
        'of_id'    => $ofertaTourId,
    ]);

    // Formalizar Venta 1
    $resultadoV1 = $reservaServicio->formalizarDesdeVenta($orgId, $venta1Id, $ctxAdmin);
    $reserva1 = $resultadoV1['reserva'];
    $entrega1 = $resultadoV1['entrega'];

    afirmar($reserva1 !== null, "4.1: Reserva generada formalmente desde Venta CONFIRMADA");
    afirmar($entrega1 === null, "4.2: Cero orden de entrega generada (venta contenía solo servicios)");
    afirmar(str_starts_with($reserva1->correlativo, 'RSV-2026-'), "4.3: Correlativo institucional RSV-YYYY-NNNNNN asignado ({$reserva1->correlativo})");
    afirmar($reserva1->estado === EstadoReserva::PENDIENTE_DATOS, "4.4: Reserva nace en PENDIENTE_DATOS (el tour requiere participantes)");
    afirmar(count($reserva1->prestaciones) === 1, "4.5: Reserva contiene exactamente 1 prestación desglosada");
    afirmar($reserva1->prestaciones[0]->cantidad === 2.0, "4.6: Prestación preserva cantidad contratada (2.00 personas)");

    // Prueba de idempotencia (Regla 1:1)
    $reintentoBloqueado = false;
    try {
        $reservaServicio->formalizarDesdeVenta($orgId, $venta1Id, $ctxAdmin);
    } catch (RuntimeException $e) {
        $reintentoBloqueado = true;
    }
    afirmar($reintentoBloqueado, "4.7: Reintento de formalizar la misma venta es rechazado (Idempotencia / Regla 1:1)");

    // Venta en estado no CONFIRMADA es rechazada
    $stmtV2 = $pdo->prepare("
        INSERT INTO `ventas` (
            `organizacion_id`, `edicion_id`, `cliente_id`, `origen_tipo`,
            `correlativo`, `fecha_venta`, `estado`, `motivo_cancelacion`,
            `cliente_nombre_completo`, `moneda`, `subtotal`, `total`, `creado_por`
        ) VALUES (
            :org_id, :edicion_id, :cliente_id, 'DIRECTA',
            'VTA-2026-880002', '2026-02-02', 'CANCELADA', 'DESISTIMIENTO_CLIENTE',
            'CLIENTE CANCELADO', 'PEN', 100.00, 100.00, :creado_por
        )
    ");
    $stmtV2->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'cliente_id' => 8803,
        'creado_por' => $adminSinteticoId,
    ]);
    $venta2Id = (int) $pdo->lastInsertId();

    $noConfirmadaRechazada = false;
    try {
        $reservaServicio->formalizarDesdeVenta($orgId, $venta2Id, $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $noConfirmadaRechazada = true;
    }
    afirmar($noConfirmadaRechazada, "4.8: Venta no CONFIRMADA es rechazada inmediatamente");

    // ==============================================================================
    // BLOQUE 5: BIFURCACIÓN DE PAQUETES MIXTOS (SERVICIOS vs PRODUCTOS)
    // ==============================================================================
    echo "\n--- BLOQUE 5: BIFURCACIÓN DE PAQUETES MIXTOS (SERVICIOS vs PRODUCTOS) ---\n";

    // Crear un paquete comercial mixto en el catálogo
    $paqRepo = new PaqueteRepositorio($pdo);
    $paqRepo->guardar(new Paquete(
        id: null,
        organizacionId: $orgId,
        codigo: 'PAQ_MIXTO_F26C',
        nombre: 'PAQUETE MIXTO F26C'
    ));
    $paquete = $paqRepo->buscarPorCodigo('PAQ_MIXTO_F26C', $orgId);

    // Asociar componentes: 1 Tour Lacustre (Servicio) + 1 Polo Oficial (Producto)
    $stmtPI = $pdo->prepare("
        INSERT INTO `paquete_items` (`paquete_id`, `item_comercial_id`, `cantidad`, `orden`)
        VALUES (:paq_id1, :tour_id, 1.00, 1), (:paq_id2, :polo_id, 1.00, 2)
    ");
    $stmtPI->execute([
        'paq_id1' => (int) $paquete->id,
        'paq_id2' => (int) $paquete->id,
        'tour_id' => (int) $itemTour->id,
        'polo_id' => (int) $itemPolo->id,
    ]);

    // Crear Venta 3 que contiene 1 Paquete Mixto
    $stmtV3 = $pdo->prepare("
        INSERT INTO `ventas` (
            `organizacion_id`, `edicion_id`, `cliente_id`, `origen_tipo`,
            `correlativo`, `fecha_venta`, `estado`,
            `cliente_nombre_completo`, `moneda`, `subtotal`, `total`, `creado_por`
        ) VALUES (
            :org_id, :edicion_id, :cliente_id, 'DIRECTA',
            'VTA-2026-880003', '2026-02-03', 'CONFIRMADA',
            'COMPRADOR PAQUETE MIXTO', 'PEN', 350.00, 350.00, :creado_por
        )
    ");
    $stmtV3->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'cliente_id' => 8803,
        'creado_por' => $adminSinteticoId,
    ]);
    $venta3Id = (int) $pdo->lastInsertId();

    // Crear oferta de paquete en la edición
    $stmtOfPaq = $pdo->prepare("
        INSERT INTO `ofertas_paquetes_edicion` (`organizacion_id`, `edicion_id`, `paquete_id`, `estado`)
        VALUES (:org_id, :edicion_id, :paq_id, 'ACTIVO')
    ");
    $stmtOfPaq->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'paq_id'     => (int) $paquete->id,
    ]);
    $ofertaPaqId = (int) $pdo->lastInsertId();

    $stmtL3 = $pdo->prepare("
        INSERT INTO `venta_lineas` (
            `venta_id`, `tipo_linea`, `paquete_id`, `oferta_paquete_id`, `concepto_codigo`,
            `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`,
            `subtotal`, `moneda`
        ) VALUES (
            :venta_id, 'PAQUETE', :paq_id, :of_paq_id, 'PAQ_MIXTO_F26C',
            'PAQUETE MIXTO F26C', 'UNIDAD', 2.00, 175.00,
            350.00, 'PEN'
        )
    ");
    $stmtL3->execute([
        'venta_id'   => $venta3Id,
        'paq_id'     => (int) $paquete->id,
        'of_paq_id'  => $ofertaPaqId,
    ]);
    $linea3Id = (int) $pdo->lastInsertId();

    // Componentes congelados en venta_linea_componentes
    $stmtComp = $pdo->prepare("
        INSERT INTO `venta_linea_componentes` (
            `venta_linea_id`, `item_comercial_id`, `item_codigo`, `item_nombre`,
            `item_tipo`, `unidad_medida`, `cantidad`, `orden`
        ) VALUES
        (:linea_id1, :tour_id, 'SRV_TOUR_UROS_F26C', 'TOUR ISLAS UROS F26C', 'SERVICIO', 'PERSONA', 1.00, 1),
        (:linea_id2, :polo_id, 'PRD_POLO_OFICIAL_F26C', 'POLO OFICIAL CANDELARIA F26C', 'PRODUCTO', 'UNIDAD', 1.00, 2)
    ");
    $stmtComp->execute([
        'linea_id1' => $linea3Id,
        'linea_id2' => $linea3Id,
        'tour_id'   => (int) $itemTour->id,
        'polo_id'   => (int) $itemPolo->id,
    ]);

    // Formalizar Venta 3 (Paquete Mixto)
    $resultadoV3 = $reservaServicio->formalizarDesdeVenta($orgId, $venta3Id, $ctxAdmin);
    $reserva3 = $resultadoV3['reserva'];
    $entrega3 = $resultadoV3['entrega'];

    afirmar($reserva3 !== null, "5.1: Reserva generada para los componentes SERVICIO del paquete mixto");
    afirmar($entrega3 !== null, "5.2: Orden de entrega generada para los componentes PRODUCTO del paquete mixto");
    afirmar(count($reserva3->prestaciones) === 1, "5.3: Reserva contiene exactamente 1 prestación de servicio");
    afirmar($reserva3->prestaciones[0]->conceptoCodigo === 'SRV_TOUR_UROS_F26C', "5.4: Prestación corresponde al Tour Uros");
    afirmar($reserva3->prestaciones[0]->cantidad === 2.0, "5.5: Cantidad efectiva de servicio = 2 paquetes * 1 pax = 2.00");
    afirmar(count($entrega3->items) === 1, "5.6: Orden de entrega contiene exactamente 1 ítem de producto");
    afirmar($entrega3->items[0]->conceptoCodigo === 'PRD_POLO_OFICIAL_F26C', "5.7: Ítem de entrega corresponde al Polo Oficial");
    afirmar($entrega3->items[0]->cantidad === 2.0, "5.8: Cantidad efectiva de entrega = 2 paquetes * 1 unidad = 2.00");

    // ==============================================================================
    // BLOQUE 6: PARTICIPANTES, ASIGNACIÓN M:N Y PII OPERACIONAL MINIMIZADA
    // ==============================================================================
    echo "\n--- BLOQUE 6: PARTICIPANTES, ASIGNACIÓN M:N Y PII OPERACIONAL MINIMIZADA ---\n";

    $prestacion1Id = (int) $reserva1->prestaciones[0]->id;

    // Registrar participante 1 (Pasajero adulto)
    $part1 = $reservaServicio->registrarParticipante($orgId, (int) $reserva1->id, [
        'nombres'            => 'JUAN CARLOS',
        'apellidos'          => 'QUISPE MAMANI',
        'tipo_documento_id'  => 1,
        'numero_documento'   => '70809011',
        'nacionalidad'       => 'PE',
        'rango_etario'       => 'ADULTO',
        'telefono_contacto'  => '951234567',
        'es_titular_reserva' => true,
    ], [$prestacion1Id], $ctxAdmin);

    afirmar($part1->id > 0, "6.1: Participante titular registrado exitosamente");
    afirmar($part1->nombres === 'JUAN CARLOS', "6.2: Snapshot inmutable de nombres");
    afirmar($part1->rangoEtario === RangoEtarioParticipante::ADULTO, "6.3: Rango etario registrado");

    // Registrar participante 2 (Menor con requerimiento de menú vegetariano)
    $part2 = $reservaServicio->registrarParticipante($orgId, (int) $reserva1->id, [
        'nombres'             => 'SOFIA',
        'apellidos'           => 'QUISPE FLORES',
        'tipo_documento_id'   => 1,
        'numero_documento'    => '70809012',
        'nacionalidad'        => 'PE',
        'rango_etario'        => 'MENOR',
        'regimen_alimentario' => 'VEGETARIANO',
    ], [$prestacion1Id], $ctxAdmin);

    afirmar($part2->regimenAlimentario === RegimenAlimentario::VEGETARIANO, "6.4: Tag cerrado de régimen alimentario guardado");

    // Verificar asignación M:N
    $prestacionActualizada = $reservaRepo->buscarPrestacionPorId($prestacion1Id);
    afirmar(count($prestacionActualizada->participanteIds) === 2, "6.5: Prestación tiene asignados exactamente los 2 participantes");
    afirmar(in_array((int) $part1->id, $prestacionActualizada->participanteIds, true), "6.6: Participante 1 presente en asignación M:N");
    afirmar(in_array((int) $part2->id, $prestacionActualizada->participanteIds, true), "6.7: Participante 2 presente en asignación M:N");

    // Verificar que la reserva avanzó a CONFIRMADA
    $reserva1Recargada = $reservaRepo->buscarPorId((int) $reserva1->id);
    afirmar($reserva1Recargada->estado === EstadoReserva::CONFIRMADA, "6.8: Reserva avanzó formalmente de PENDIENTE_DATOS a CONFIRMADA al recibir participantes");

    // Comprobar que PARTICIPANTE != CLIENTE (no se crearon clientes en el CRM)
    $stmtCrmCheck = $pdo->prepare("SELECT COUNT(*) FROM `clientes` WHERE `persona_id` IN (:p1, :p2)");
    $stmtCrmCheck->execute(['p1' => $part1->personaId ?? 0, 'p2' => $part2->personaId ?? 0]);
    afirmar((int) $stmtCrmCheck->fetchColumn() === 0, "6.9: PARTICIPANTE != CLIENTE: Ningún participante contaminó la tabla clientes del CRM");

    // Unicidad de documento por reserva rechaza duplicados
    $duplicadoDocRechazado = false;
    try {
        $reservaServicio->registrarParticipante($orgId, (int) $reserva1->id, [
            'nombres'           => 'JUAN CLON',
            'apellidos'         => 'QUISPE',
            'tipo_documento_id' => 1,
            'numero_documento'  => '70809011',
        ], [], $ctxAdmin);
    } catch (\Throwable $e) {
        $duplicadoDocRechazado = true;
    }
    afirmar($duplicadoDocRechazado, "6.10: Documento duplicado en la misma reserva es rechazado");

    // 6.11 y 6.12: Registro sin nacionalidad ni rango etario almacena strictly NULL (sin defaults 'PE' ni 'ADULTO')
    $partSinDefaults = $reservaServicio->registrarParticipante($orgId, (int) $reserva1->id, [
        'nombres'           => 'MARIA SIN DEFAULTS',
        'apellidos'         => 'CONDORI FLORES',
        'tipo_documento_id' => 1,
        'numero_documento'  => '70809099',
    ], [], $ctxAdmin);
    afirmar($partSinDefaults->nacionalidad === null, "6.11: Participante sin nacionalidad explícita almacena strictly NULL (cero default 'PE')");
    afirmar($partSinDefaults->rangoEtario === null, "6.12: Participante sin rango etario explícito almacena strictly NULL (cero default 'ADULTO')");

    // ==============================================================================
    // BLOQUE 7: PROGRAMACIÓN Y REPROGRAMACIÓN APPEND-ONLY
    // ==============================================================================
    echo "\n--- BLOQUE 7: PROGRAMACIÓN Y REPROGRAMACIÓN APPEND-ONLY ---\n";

    afirmar($reserva1->prestaciones[0]->estadoAgendamiento === EstadoAgendamientoPrestacion::PENDIENTE_PROGRAMAR, "7.1: Prestación nace en PENDIENTE_PROGRAMAR");

    // Programar fecha de servicio por primera vez
    $prestProgramada = $reservaServicio->programarPrestacion(
        organizacionId: $orgId,
        prestacionId: $prestacion1Id,
        fechaServicio: '2026-02-05',
        horaServicio: '07:30:00',
        puntoEncuentro: 'Muelle Principal Puno',
        contexto: $ctxOperador
    );

    afirmar($prestProgramada->estadoAgendamiento === EstadoAgendamientoPrestacion::PROGRAMADA, "7.2: Prestación transiciona a PROGRAMADA");
    afirmar($prestProgramada->fechaServicio === '2026-02-05', "7.3: Fecha de servicio asignada correctamente");
    afirmar($prestProgramada->horaServicio === '07:30:00', "7.4: Horario asignado correctamente");
    afirmar($prestProgramada->versionBloqueo === 2, "7.5: Versión de bloqueo incrementada a 2 tras programar");

    // Reprogramar prestación (por oleaje o solicitud de cliente)
    $prestReprogramada = $reservaServicio->reprogramarPrestacion(
        organizacionId: $orgId,
        prestacionId: $prestacion1Id,
        nuevaFecha: '2026-02-07',
        nuevaHora: '08:00:00',
        motivoCategoria: MotivoReprogramacion::CLIMA_FUERZA_MAYOR,
        motivoDetalle: 'Oleaje anómalo en el lago Titicaca; capitanía cerró muelle.',
        contexto: $ctxAdmin
    );

    afirmar($prestReprogramada->estadoAgendamiento === EstadoAgendamientoPrestacion::PROGRAMADA, "7.6: Prestación permanece en estado PROGRAMADA con la nueva fecha");
    afirmar($prestReprogramada->fechaServicio === '2026-02-07', "7.7: Nueva fecha de servicio actualizada a 2026-02-07");
    afirmar($prestReprogramada->versionBloqueo === 3, "7.8: Versión de bloqueo incrementada a 3 tras reprogramación");

    // Verificar historial append-only
    $stmtReprog = $pdo->prepare("SELECT * FROM `reserva_reprogramaciones` WHERE `prestacion_id` = :p_id");
    $stmtReprog->execute(['p_id' => $prestacion1Id]);
    $filaReprog = $stmtReprog->fetch(PDO::FETCH_ASSOC);

    afirmar($filaReprog !== false, "7.9: Registro append-only creado en reserva_reprogramaciones");
    afirmar($filaReprog['fecha_anterior'] === '2026-02-05', "7.10: Historial preserva fecha_anterior = 2026-02-05");
    afirmar($filaReprog['fecha_nueva'] === '2026-02-07', "7.11: Historial preserva fecha_nueva = 2026-02-07");
    afirmar($filaReprog['motivo_categoria'] === 'CLIMA_FUERZA_MAYOR', "7.12: Historial preserva motivo_categoria");
    afirmar((int) $filaReprog['creado_por'] === $adminSinteticoId, "7.13: Actor responsable registrado en historial");

    // Verificar inmutabilidad de la venta
    $ventaOriginal = $ventaRepo->buscarPorId($venta1Id, $orgId);
    afirmar($ventaOriginal->fechaVenta === '2026-02-02', "7.14: Inmutabilidad de Venta: ventas.fecha_venta permanece intocable");

    // ==============================================================================
    // BLOQUE 8: CONCURRENCIA OPTIMISTA (HTTP 409)
    // ==============================================================================
    echo "\n--- BLOQUE 8: CONCURRENCIA OPTIMISTA (HTTP 409 / CONFLICTO) ---\n";

    $conflictoDetectado = false;
    try {
        // Enviar versión obsoleta 1 (actual es 3)
        $reservaRepo->actualizarAgendamientoPrestacion(
            prestacionId: $prestacion1Id,
            fechaServicio: '2026-02-10',
            horaServicio: '09:00:00',
            puntoEncuentro: null,
            versionEsperada: 1
        );
    } catch (ConflictoConcurrenciaExcepcion $e) {
        $conflictoDetectado = true;
    }
    afirmar($conflictoDetectado, "8.1: ConflictoConcurrenciaExcepcion lanzada ante versión desfasada en prestación");

    // ==============================================================================
    // BLOQUE 9: CANCELACIÓN Y DESACOPLAMIENTO ESTRICTO
    // ==============================================================================
    echo "\n--- BLOQUE 9: CANCELACIÓN Y DESACOPLAMIENTO ESTRICTO ---\n";

    $reservaCancelada = $reservaServicio->cancelarReserva(
        organizacionId: $orgId,
        reservaId: (int) $reserva1->id,
        motivo: 'Cancelación administrativa solicitada por el cliente',
        contexto: $ctxAdmin
    );

    afirmar($reservaCancelada->estado === EstadoReserva::CANCELADA, "9.1: Cabecera de reserva transiciona formalmente a CANCELADA");

    $prestacionCancelada = $reservaRepo->buscarPrestacionPorId($prestacion1Id);
    afirmar($prestacionCancelada->estadoAgendamiento === EstadoAgendamientoPrestacion::CANCELADA, "9.2: Prestaciones activas pasan a CANCELADA");

    // Desacoplamiento estricto
    afirmar(!in_array('pagos', $tablas, true), "9.3: Desacoplamiento Pagos: CERO tabla 'pagos'");
    afirmar(!in_array('caja_sesiones', $tablas, true), "9.4: Desacoplamiento Caja: CERO tabla 'caja_sesiones'");
    afirmar(!in_array('comprobantes_pago', $tablas, true), "9.5: Desacoplamiento SUNAT: CERO tabla 'comprobantes_pago'");
    afirmar(!in_array('comprobante_lineas', $tablas, true), "9.6: Desacoplamiento SUNAT: CERO tabla 'comprobante_lineas'");

    // ==============================================================================
    // BLOQUE 10: TRAZABILIDAD DE AUDITORÍA Y ZERO-PII
    // ==============================================================================
    echo "\n--- BLOQUE 10: TRAZABILIDAD DE AUDITORÍA Y ZERO-PII ---\n";

    $stmtAud = $pdo->prepare("
        SELECT `accion`, `entidad_tipo`, `entidad_id`
        FROM `auditoria_operaciones`
        WHERE `modulo` = 'reservas'
        ORDER BY `id` ASC
    ");
    $stmtAud->execute();
    $eventosAuditados = $stmtAud->fetchAll(PDO::FETCH_ASSOC);

    $acciones = array_column($eventosAuditados, 'accion');
    afirmar(in_array('RESERVA_GENERADA', $acciones, true), "10.1: Evento 'RESERVA_GENERADA' registrado en auditoria_operaciones");
    afirmar(in_array('ENTREGA_GENERADA', $acciones, true), "10.2: Evento 'ENTREGA_GENERADA' registrado en auditoria_operaciones");
    afirmar(in_array('PRESTACION_PROGRAMADA', $acciones, true), "10.3: Evento 'PRESTACION_PROGRAMADA' registrado en auditoria_operaciones");
    afirmar(in_array('PRESTACION_REPROGRAMADA', $acciones, true), "10.4: Evento 'PRESTACION_REPROGRAMADA' registrado en auditoria_operaciones");
    afirmar(in_array('PARTICIPANTE_REGISTRADO', $acciones, true), "10.5: Evento 'PARTICIPANTE_REGISTRADO' registrado en auditoria_operaciones");
    afirmar(in_array('RESERVA_CANCELADA', $acciones, true), "10.6: Evento 'RESERVA_CANCELADA' registrado en auditoria_operaciones");

} finally {
    // Revertir deterministamente la transacción para dejar la base de datos libre de fixtures
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n[INFO] Transacción de pruebas F2.6C revertida con ROLLBACK determinista.\n";
    }
}

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.6C: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
exit(0);
