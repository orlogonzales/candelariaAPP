<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F2.6D
 * OPERACIÓN DE CAMPO, SALIDAS, RECURSOS, MANIFIESTOS, CHECK-IN Y EJECUCIÓN
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Catalogo\TipoItemComercial;
use Aplicacion\Catalogo\UnidadMedidaItem;
use Nucleo\Http\ContextoOperacion;
use Aplicacion\Entidades\CategoriaItem;
use Aplicacion\Entidades\Edicion;
use Aplicacion\Entidades\ItemComercial;
use Aplicacion\Entidades\ItemConfiguracionOperativa;
use Aplicacion\Entidades\OperacionAsistencia;
use Aplicacion\Entidades\OperacionIncidencia;
use Aplicacion\Entidades\OperacionRecurso;
use Aplicacion\Entidades\OperacionSalida;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Proveedor;
use Aplicacion\Entidades\Reserva;
use Aplicacion\Entidades\ReservaParticipante;
use Aplicacion\Entidades\ReservaPrestacion;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Operaciones\EstadoAsistencia;
use Aplicacion\Operaciones\EstadoProveedor;
use Aplicacion\Operaciones\EstadoRecursoFisico;
use Aplicacion\Operaciones\EstadoSalida;
use Aplicacion\Operaciones\OperacionServicio;
use Aplicacion\Operaciones\PropiedadRecurso;
use Aplicacion\Operaciones\ProveedorRecursoServicio;
use Aplicacion\Operaciones\ResultadoProyeccionReserva;
use Aplicacion\Operaciones\RolOperativoRecurso;
use Aplicacion\Operaciones\TipoIncidenciaOperativa;
use Aplicacion\Operaciones\TipoRecursoFisico;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\CategoriaItemRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\EntregaProductoRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\ItemConfiguracionOperativaRepositorio;
use Aplicacion\Repositorios\OperacionRecursoRepositorio;
use Aplicacion\Repositorios\OperacionSalidaRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\ProveedorRepositorio;
use Aplicacion\Repositorios\ReservaRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Reservas\EstadoAgendamientoPrestacion;
use Aplicacion\Reservas\EstadoEntregaProducto;
use Aplicacion\Reservas\EstadoReserva;
use Aplicacion\Reservas\RangoEtarioParticipante;
use Aplicacion\Reservas\RegimenAlimentario;
use Aplicacion\Reservas\ReservaServicio;
use Aplicacion\Reservas\TipoCapacidad;
use Nucleo\BaseDatos\Conexion;

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

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F2.6D\n";
echo "OPERACIÓN DE CAMPO, SALIDAS, RECURSOS, MANIFIESTOS, CHECK-IN Y EJECUCIÓN\n";
// Limpieza preventiva de fixtures sintéticos de ejecuciones anteriores
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("DELETE FROM `operacion_incidencias` WHERE `salida_id` IN (SELECT `id` FROM `operacion_salidas` WHERE `correlativo` LIKE 'SAL-2026-%')");
$pdo->exec("DELETE FROM `operacion_asistencias` WHERE `salida_id` IN (SELECT `id` FROM `operacion_salidas` WHERE `correlativo` LIKE 'SAL-2026-%')");
$pdo->exec("DELETE FROM `operacion_salida_recursos` WHERE `salida_id` IN (SELECT `id` FROM `operacion_salidas` WHERE `correlativo` LIKE 'SAL-2026-%')");
$pdo->exec("DELETE FROM `operacion_salida_prestaciones` WHERE `salida_id` IN (SELECT `id` FROM `operacion_salidas` WHERE `correlativo` LIKE 'SAL-2026-%')");
$pdo->exec("DELETE FROM `operacion_salidas` WHERE `correlativo` LIKE 'SAL-2026-%'");
$pdo->exec("DELETE FROM `operacion_recursos` WHERE `codigo_interno` IN ('LANCHA_TITICACA_01', 'VAN_PROPIA_01')");
$pdo->exec("DELETE FROM `proveedores` WHERE `notas_contacto` LIKE '%Muelle Banchero Rossi%'");
$pdo->exec("DELETE FROM `entregas_productos` WHERE `correlativo` = 'ENT-2026-000001'");
$pdo->exec("DELETE FROM `prestacion_participantes` WHERE `prestacion_id` IN (SELECT `id` FROM `reserva_prestaciones` WHERE `concepto_codigo` = 'SRV_TOUR_TITICACA_F26D')");
$pdo->exec("DELETE FROM `reserva_participantes` WHERE `numero_documento` IN ('70809001', '70809002', '70809003', '70809004')");
$pdo->exec("DELETE FROM `reserva_prestaciones` WHERE `concepto_codigo` = 'SRV_TOUR_TITICACA_F26D'");
$pdo->exec("DELETE FROM `reservas` WHERE `correlativo` IN ('RSV-2026-000091', 'RSV-2026-000092')");
$pdo->exec("DELETE FROM `venta_lineas` WHERE `concepto_codigo` = 'SRV_TOUR_TITICACA_F26D'");
$pdo->exec("DELETE FROM `ventas` WHERE `correlativo` IN ('VTA-2026-890001', 'VTA-2026-890002')");
$pdo->exec("DELETE FROM `ofertas_items_edicion` WHERE `item_comercial_id` IN (SELECT `id` FROM `items_comerciales` WHERE `codigo` = 'SRV_TOUR_TITICACA_F26D')");
$pdo->exec("DELETE FROM `item_configuracion_operativa` WHERE `item_comercial_id` IN (SELECT `id` FROM `items_comerciales` WHERE `codigo` = 'SRV_TOUR_TITICACA_F26D')");
$pdo->exec("DELETE FROM `items_comerciales` WHERE `codigo` = 'SRV_TOUR_TITICACA_F26D'");
$pdo->exec("DELETE FROM `categorias_items` WHERE `codigo` = 'CAT_OPS_F26D'");
$pdo->exec("DELETE FROM `clientes` WHERE `id` = 8904");
$pdo->exec("DELETE FROM `ediciones_candelaria` WHERE `codigo` = 'candelaria-2026-f26d'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// ==============================================================================
// BLOQUE 1: MIGRACIÓN, ESTRUCTURA DDL Y PARIDAD DE ESQUEMA
// ==============================================================================
echo "--- BLOQUE 1: MIGRACIÓN, ESTRUCTURA DDL Y PARIDAD DE ESQUEMA ---\n";

    $stmtTablas = $pdo->query("SHOW TABLES");
    $tablas = $stmtTablas->fetchAll(PDO::FETCH_COLUMN);

    $tablasF26D = [
        'operacion_salidas_secuencias',
        'proveedores',
        'operacion_recursos',
        'operacion_salidas',
        'operacion_salida_recursos',
        'operacion_salida_prestaciones',
        'operacion_asistencias',
        'operacion_incidencias',
    ];

    foreach ($tablasF26D as $t) {
        afirmar(in_array($t, $tablas, true), "1." . (array_search($t, $tablasF26D) + 1) . ": Tabla '{$t}' existe en base de datos");
    }

    // Verificar Módulo 23 y Permisos 60-71
    $stmtMod = $pdo->prepare("SELECT `nombre`, `codigo` FROM `modulos` WHERE `id` = 23");
    $stmtMod->execute();
    $mod23 = $stmtMod->fetch(PDO::FETCH_ASSOC);
    afirmar($mod23 !== false && $mod23['codigo'] === 'operaciones', "1.9: Módulo 23 'operaciones' registrado formalmente");

    $stmtPerms = $pdo->query("SELECT `id`, `codigo` FROM `permisos` WHERE `modulo_id` = 23 ORDER BY `id` ASC");
    $permsOps = $stmtPerms->fetchAll(PDO::FETCH_KEY_PAIR);
    afirmar(count($permsOps) === 12, "1.10: Exactamente 12 permisos canónicos en módulo 23");

    $permisosEsperados = [
        60 => 'operacion.ver',
        61 => 'operacion.gestionar_salidas',
        62 => 'operacion.asignar_prestaciones',
        63 => 'operacion.asignar_recursos',
        64 => 'operacion.checkin',
        65 => 'operacion.ejecutar',
        66 => 'operacion.registrar_incidencias',
        67 => 'proveedores.ver',
        68 => 'proveedores.gestionar',
        69 => 'recursos.ver',
        70 => 'recursos.gestionar',
        71 => 'entregas.despachar',
    ];

    foreach ($permisosEsperados as $pid => $pcod) {
        afirmar(isset($permsOps[$pid]) && $permsOps[$pid] === $pcod, "1.11.{$pid}: Permiso {$pid} '{$pcod}' registrado");
    }

    $stmtMig = $pdo->query("SELECT COUNT(*) FROM `migraciones_control` WHERE `migracion` LIKE '%000015_crear_modulo_operaciones_y_recursos_rbac%'");
    afirmar((int) $stmtMig->fetchColumn() === 1, "1.12: Migración 000015 registrada en migraciones_control");

    // Verificar paridad con esquema_base.sql
    $dbTemp = 'test_candelaria_schema_f26d_' . bin2hex(random_bytes(4));
    $pdo->exec("CREATE DATABASE `{$dbTemp}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    try {
        $pdoTemp = new PDO(
            "mysql:host=" . ($_ENV['BD_HOST'] ?? '127.0.0.1') . ";port=" . ($_ENV['BD_PUERTO'] ?? '3306') . ";dbname={$dbTemp};charset=utf8mb4",
            $_ENV['BD_USUARIO'] ?? 'root',
            $_ENV['BD_CLAVE'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $esquemaSql = file_get_contents(__DIR__ . '/../base_datos/esquema/esquema_base.sql');
        $pdoTemp->exec($esquemaSql);

        $stmtTablasTemp = $pdoTemp->query("SHOW TABLES");
        $tablasTemp = $stmtTablasTemp->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tablasF26D as $t) {
            afirmar(in_array($t, $tablasTemp, true), "1.13: Instalación limpia incluye '{$t}'");
        }
        afirmar(count($tablasTemp) >= 51, "1.14: Instalación limpia de esquema_base.sql crea al menos 51 tablas oficiales");
    } finally {
        $pdo->exec("DROP DATABASE IF EXISTS `{$dbTemp}`");
    }

    // Iniciar transacción de prueba para aislamiento total de fixtures y ejecución
    $pdo->beginTransaction();

    try {
        // ==============================================================================
        // SETUP DE FIXTURES SINTÉTICOS (ZERO-PII: CERO DATOS REALES)
        // ==============================================================================
    $orgId = 10000;
    $edicionId = 2026;

    $pdo->exec("
        INSERT INTO `ediciones_candelaria` (`id`, `organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`)
        VALUES (2026, 10000, 'candelaria-2026-f26d', 'FESTIVIDAD CANDELARIA 2026 F26D', 2026, 'OPERACION', '2026-02-01', '2026-02-15')
        ON DUPLICATE KEY UPDATE `estado` = 'OPERACION'
    ");

    // Actores sintéticos
    $adminSinteticoId = 8901;
    $operadorSinteticoId = 8902;
    $vendedorSinteticoId = 8903;

    $pdo->exec("
        INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`)
        VALUES
        (8901, 10000, 'NATURAL', 1, '89880001', 'ADMIN', 'SINTETICO F26D', 'admin.f26d@test.local'),
        (8902, 10000, 'NATURAL', 1, '89880002', 'OPERADOR', 'SINTETICO F26D', 'operador.f26d@test.local'),
        (8903, 10000, 'NATURAL', 1, '89880003', 'VENDEDOR', 'SINTETICO F26D', 'vendedor.f26d@test.local')
        ON DUPLICATE KEY UPDATE `nombres` = VALUES(`nombres`)
    ");

    $pdo->exec("
        INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `es_superadmin_plataforma`, `estado`)
        VALUES
        (8901, 10000, 8901, 'admin_sintetico_f26d', 'Admin Sintetico F26D', 'admin.f26d@test.local', '\$2y\$10\$abcdefghijklmnopqrstuv', 1, 'ACTIVO'),
        (8902, 10000, 8902, 'operador_sintetico_f26d', 'Operador Sintetico F26D', 'operador.f26d@test.local', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO'),
        (8903, 10000, 8903, 'vendedor_sintetico_f26d', 'Vendedor Sintetico F26D', 'vendedor.f26d@test.local', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO')
        ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'
    ");

    $pdo->exec("
        INSERT IGNORE INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES
        (8901, 1),
        (8902, 3),
        (8903, 4)
    ");

    $ctxAdmin = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: $adminSinteticoId,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-ops-admin-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF26D/Admin',
        organizacionId: $orgId
    );

    $ctxOperador = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: $operadorSinteticoId,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-ops-op-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF26D/Operador',
        organizacionId: $orgId
    );

    $ctxVendedor = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: $vendedorSinteticoId,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-ops-vend-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF26D/Vendedor',
        organizacionId: $orgId
    );

    // Instanciar repositorios y servicios
    $authzServicio = new AutorizacionServicio(pdo: $pdo);
    $auditoriaRepo = new AuditoriaRepositorio($pdo);
    $edicionRepo = new EdicionRepositorio($pdo);
    $itemRepo = new ItemComercialRepositorio($pdo);
    $itemCfgRepo = new ItemConfiguracionOperativaRepositorio($pdo);
    $reservaRepo = new ReservaRepositorio($pdo);
    $entregaRepo = new EntregaProductoRepositorio($pdo);
    $proveedorRepo = new ProveedorRepositorio($pdo);
    $recursoRepo = new OperacionRecursoRepositorio($pdo);
    $salidaRepo = new OperacionSalidaRepositorio($pdo);
    $personaRepo = new PersonaRepositorio($pdo);

    $operacionServicio = new OperacionServicio(
        salidaRepo: $salidaRepo,
        recursoRepo: $recursoRepo,
        proveedorRepo: $proveedorRepo,
        reservaRepo: $reservaRepo,
        entregaRepo: $entregaRepo,
        edicionRepo: $edicionRepo,
        itemRepo: $itemRepo,
        authzServicio: $authzServicio,
        auditoriaRepo: $auditoriaRepo,
        pdo: $pdo
    );

    $provRecServicio = new ProveedorRecursoServicio(
        proveedorRepo: $proveedorRepo,
        recursoRepo: $recursoRepo,
        authzServicio: $authzServicio,
        auditoriaRepo: $auditoriaRepo,
        pdo: $pdo
    );

    // ==============================================================================
    // BLOQUE 2: RBAC Y AUTORIZACIÓN SOBERANA DE OPERACIONES
    // ==============================================================================
    echo "\n--- BLOQUE 2: RBAC Y AUTORIZACIÓN SOBERANA DE OPERACIONES ---\n";

    afirmar($authzServicio->tienePermiso($adminSinteticoId, 'operacion.ver'), "2.1: Admin tiene 'operacion.ver'");
    afirmar($authzServicio->tienePermiso($adminSinteticoId, 'operacion.gestionar_salidas'), "2.2: Admin tiene 'operacion.gestionar_salidas'");
    afirmar($authzServicio->tienePermiso($adminSinteticoId, 'operacion.ejecutar'), "2.3: Admin tiene 'operacion.ejecutar'");
    afirmar($authzServicio->tienePermiso($operadorSinteticoId, 'operacion.ver'), "2.4: Operador tiene 'operacion.ver'");
    afirmar($authzServicio->tienePermiso($operadorSinteticoId, 'operacion.checkin'), "2.5: Operador tiene 'operacion.checkin'");
    afirmar($authzServicio->tienePermiso($operadorSinteticoId, 'operacion.ejecutar'), "2.6: Operador tiene 'operacion.ejecutar'");
    afirmar(!$authzServicio->tienePermiso($operadorSinteticoId, 'operacion.gestionar_salidas'), "2.7: Operador NO tiene 'operacion.gestionar_salidas'");
    afirmar(!$authzServicio->tienePermiso($vendedorSinteticoId, 'operacion.ver'), "2.8: Usuario sin rol NO tiene 'operacion.ver'");
    afirmar(!$authzServicio->tienePermiso($vendedorSinteticoId, 'operacion.ejecutar'), "2.9: Usuario sin rol NO tiene 'operacion.ejecutar'");

    // Intentar crear salida con operador sin permiso
    $bloqueoRbac = false;
    try {
        $operacionServicio->crearSalida($orgId, $edicionId, 1, 'Salida Bloqueada', '2026-02-05', '07:00:00', '07:30:00', 'Punto', TipoCapacidad::COLECTIVA, 10, $ctxOperador);
    } catch (AccesoDenegadoExcepcion $e) {
        $bloqueoRbac = true;
    }
    afirmar($bloqueoRbac, "2.10: Operador sin permiso 'operacion.gestionar_salidas' es bloqueado con AccesoDenegadoExcepcion");

    // ==============================================================================
    // BLOQUE 3: PROVEEDORES DESACOPLADOS Y RECURSOS FÍSICOS
    // ==============================================================================
    echo "\n--- BLOQUE 3: PROVEEDORES DESACOPLADOS Y RECURSOS FÍSICOS ---\n";

    // 1. Crear Persona Jurídica / Proveedor en personas
    $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'JURIDICA',
        tipoDocumentoId: 2, // RUC
        numeroDocumento: '20601234567',
        razonSocial: 'ASOCIACION DE LANCHEROS TITICACA S.A.C.',
        correoElectronico: 'lancheros.titicaca@test.local',
        telefonoMovil: '951999888'
    ));
    $personaProveedor = $personaRepo->buscarPorDocumento($orgId, 2, '20601234567');
    $personaProveedorId = (int) $personaProveedor->id;

    // Registrar Proveedor (desacoplado, sin duplicar RUC ni nombre)
    $prov = $provRecServicio->registrarProveedor(
        organizacionId: $orgId,
        personaId: $personaProveedorId,
        tipoServicioPrincipal: 'TRANSPORTE_LACUSTRE',
        notasContacto: 'Muelle Banchero Rossi, Puno',
        contexto: $ctxAdmin
    );

    afirmar($prov->id > 0, "3.1: Proveedor registrado exitosamente en el catálogo maestro");
    afirmar($prov->estado === EstadoProveedor::ACTIVO, "3.2: Proveedor nace en estado ACTIVO");
    afirmar($prov->personaId === $personaProveedorId, "3.3: Proveedor referencia directamente a su Persona");

    // Registrar Recurso Externo (Lancha asociada a proveedor)
    $lanchaExterna = $provRecServicio->registrarRecursoFisico(
        organizacionId: $orgId,
        tipoRecurso: TipoRecursoFisico::EMBARCACION_LACUSTRE,
        codigoInterno: 'LANCHA_TITICACA_01',
        nombre: 'EMBARCACION TITICACA EXPLORER I',
        propiedadTipo: PropiedadRecurso::EXTERNO,
        proveedorId: (int) $prov->id,
        capacidadMaxima: 25,
        identificacionOficial: 'PU-1234-BM',
        notas: 'Motor Yamaha 150HP, chalecos salvavidas',
        contexto: $ctxAdmin
    );

    afirmar($lanchaExterna->id > 0, "3.4: Recurso físico externo (lancha) registrado con proveedor");
    afirmar($lanchaExterna->capacidadMaxima === 25, "3.5: Capacidad máxima de la embarcación = 25 plazas");

    // Registrar Recurso Propio (Minivan sin proveedor)
    $minivanPropia = $provRecServicio->registrarRecursoFisico(
        organizacionId: $orgId,
        tipoRecurso: TipoRecursoFisico::VEHICULO_TERRESTRE,
        codigoInterno: 'VAN_PROPIA_01',
        nombre: 'MINIVAN TURISMO MASTER',
        propiedadTipo: PropiedadRecurso::PROPIO,
        proveedorId: null,
        capacidadMaxima: 15,
        identificacionOficial: 'Z3A-789',
        notas: 'Vehículo institucional Candelaria',
        contexto: $ctxAdmin
    );

    afirmar($minivanPropia->propiedadTipo === PropiedadRecurso::PROPIO, "3.6: Recurso propio registrado sin proveedor externo");

    // DB CHECK: Recurso EXTERNO sin proveedor es rechazado
    $recursoExternoSinProvRechazado = false;
    try {
        $provRecServicio->registrarRecursoFisico(
            organizacionId: $orgId,
            tipoRecurso: TipoRecursoFisico::VEHICULO_TERRESTRE,
            codigoInterno: 'VAN_FAIL',
            nombre: 'VAN SIN PROV',
            propiedadTipo: PropiedadRecurso::EXTERNO,
            proveedorId: null,
            capacidadMaxima: 10,
            contexto: $ctxAdmin
        );
    } catch (InvalidArgumentException $e) {
        $recursoExternoSinProvRechazado = true;
    }
    afirmar($recursoExternoSinProvRechazado, "3.7: DB CHECK: Recurso EXTERNO sin proveedor asociado es rechazado");

    // ==============================================================================
    // BLOQUE 4: PERSONAL OPERATIVO SIN USUARIO OBLIGATORIO
    // ==============================================================================
    echo "\n--- BLOQUE 4: PERSONAL OPERATIVO SIN USUARIO OBLIGATORIO ---\n";

    // Registrar guía oficial de turismo como persona (sin cuenta de usuario en el software)
    $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1, // DNI
        numeroDocumento: '40506070',
        nombres: 'EDGAR TITO',
        apellidos: 'VILLASANTE CHOQUE',
        correoElectronico: 'guia.edgar@test.local',
        telefonoMovil: '951112233'
    ));
    $personaGuia = $personaRepo->buscarPorDocumento($orgId, 1, '40506070');
    $guiaPersonaId = (int) $personaGuia->id;

    // Comprobar que NO tiene usuario en el sistema
    $stmtUserCheck = $pdo->prepare("SELECT COUNT(*) FROM `usuarios` WHERE `persona_id` = :pid");
    $stmtUserCheck->execute(['pid' => $guiaPersonaId]);
    afirmar((int) $stmtUserCheck->fetchColumn() === 0, "4.1: Personal operativo (guía) existe en personas y NO tiene cuenta de usuario");

    // ==============================================================================
    // BLOQUE 5: CREACIÓN DE SALIDA OPERATIVA Y CORRELATIVO MONOTÓNICO
    // ==============================================================================
    echo "\n--- BLOQUE 5: CREACIÓN DE SALIDA OPERATIVA Y CORRELATIVO MONOTÓNICO ---\n";

    // Crear ítem comercial Tour Uros
    $catRepo = new CategoriaItemRepositorio($pdo);
    $catRepo->guardar(new CategoriaItem(
        id: null,
        organizacionId: $orgId,
        codigo: 'CAT_OPS_F26D',
        nombre: 'SERVICIOS TURISTICOS OPS'
    ));
    $catOps = $catRepo->buscarPorCodigo('CAT_OPS_F26D', $orgId);

    $itemRepo->guardar(new ItemComercial(
        id: null,
        organizacionId: $orgId,
        categoriaId: (int) $catOps->id,
        codigo: 'SRV_TOUR_TITICACA_F26D',
        nombre: 'FULL DAY LAGO TITICACA UROS Y TAQUILE',
        tipo: TipoItemComercial::SERVICIO,
        unidadMedida: UnidadMedidaItem::PERSONA
    ));
    $itemTourTiticaca = $itemRepo->buscarPorCodigo('SRV_TOUR_TITICACA_F26D', $orgId);
    $tourId = (int) $itemTourTiticaca->id;

    // Configuración operativa explícita
    $itemCfgRepo->guardar(new ItemConfiguracionOperativa(
        id: null,
        itemComercialId: $tourId,
        requiereReserva: true,
        requiereAgendamiento: true,
        requiereParticipantes: true,
        tipoCapacidad: TipoCapacidad::COLECTIVA,
        puntoPartidaPredeterminado: 'Muelle Principal Puno'
    ));

    // Crear Salida Operativa 1 (Colectiva de 20 pax para el 2026-02-05)
    $salida1 = $operacionServicio->crearSalida(
        organizacionId: $orgId,
        edicionId: $edicionId,
        itemComercialId: $tourId,
        titulo: 'GRUPO 1 - FULL DAY TITICACA 05/FEB',
        fechaSalida: '2026-02-05',
        horaCitacion: '07:00:00',
        horaSalida: '07:30:00',
        puntoEncuentro: 'Muelle Lacustre Puno Puerta 2',
        tipoCapacidad: TipoCapacidad::COLECTIVA,
        capacidadMaxima: 20,
        contexto: $ctxAdmin
    );

    afirmar($salida1->id > 0, "5.1: Salida operativa creada exitosamente");
    afirmar(str_starts_with($salida1->correlativo, 'SAL-2026-'), "5.2: Correlativo institucional monotónico asignado ({$salida1->correlativo})");
    afirmar($salida1->estado === EstadoSalida::PROGRAMADA, "5.3: Salida nace en estado canónico PROGRAMADA");
    afirmar($salida1->capacidadMaxima === 20, "5.4: Capacidad máxima fijada en 20 pasajeros");

    // Asignar lancha externa y guía sin usuario a la salida
    $recSalidaLancha = $operacionServicio->asignarRecursoASalida(
        organizacionId: $orgId,
        salidaId: (int) $salida1->id,
        recursoFisicoId: (int) $lanchaExterna->id,
        personaId: null,
        rolOperativo: RolOperativoRecurso::EQUIPO_LOGISTICO,
        notas: 'Lancha externa asignada con patrón',
        contexto: $ctxAdmin
    );
    afirmar($recSalidaLancha->id > 0, "5.5: Embarcación asignada a la salida operativa");

    $recSalidaGuia = $operacionServicio->asignarRecursoASalida(
        organizacionId: $orgId,
        salidaId: (int) $salida1->id,
        recursoFisicoId: null,
        personaId: $guiaPersonaId,
        rolOperativo: RolOperativoRecurso::GUIA_PRINCIPAL,
        notas: 'Guía bilingüe acreditado Dircetur',
        contexto: $ctxAdmin
    );
    afirmar($recSalidaGuia->id > 0, "5.6: Guía asignado a la salida utilizando personas directamente");

    // ==============================================================================
    // BLOQUE 6: ASIGNACIÓN DE PRESTACIONES 1:N Y CONTROL DE CAPACIDAD
    // ==============================================================================
    echo "\n--- BLOQUE 6: ASIGNACIÓN DE PRESTACIONES 1:N Y CONTROL DE CAPACIDAD ---\n";

    // Crear cliente sintético
    $pdo->exec("
        INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`)
        VALUES (8904, {$orgId}, 'NATURAL', 1, '89880004', 'CLIENTE', 'OPERACIONES F26D', 'cliente.ops@test.local')
        ON DUPLICATE KEY UPDATE `nombres` = VALUES(`nombres`)
    ");
    $pdo->exec("
        INSERT INTO `clientes` (`id`, `organizacion_id`, `persona_id`, `estado_comercial`)
        VALUES (8904, {$orgId}, 8904, 'CLIENTE')
        ON DUPLICATE KEY UPDATE `estado_comercial` = 'CLIENTE'
    ");

    // Oferta para itemTourTiticaca
    $stmtOfTour = $pdo->prepare("
        INSERT INTO `ofertas_items_edicion` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`)
        VALUES (:org_id, :edicion_id, :item_id, 'ACTIVO')
    ");
    $stmtOfTour->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'item_id'    => $tourId,
    ]);
    $ofertaTourId = (int) $pdo->lastInsertId();

    // Venta 1
    $stmtV1 = $pdo->prepare("
        INSERT INTO `ventas` (
            `organizacion_id`, `edicion_id`, `cliente_id`, `origen_tipo`,
            `correlativo`, `fecha_venta`, `estado`,
            `cliente_nombre_completo`, `moneda`, `subtotal`, `total`, `creado_por`
        ) VALUES (
            :org_id, :edicion_id, 8904, 'DIRECTA',
            'VTA-2026-890001', '2026-02-02', 'CONFIRMADA',
            'CLIENTE OPERACIONES F26D', 'PEN', 1200.00, 1200.00, :creado_por
        )
    ");
    $stmtV1->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'creado_por' => $adminSinteticoId,
    ]);
    $venta1Id = (int) $pdo->lastInsertId();

    // Líneas de Venta
    $crearLineaVenta = function(string $concepto, float $cantidad, float $precio) use ($pdo, $venta1Id, $tourId, $ofertaTourId): int {
        $stmt = $pdo->prepare("
            INSERT INTO `venta_lineas` (
                `venta_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`,
                `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`,
                `subtotal`, `moneda`
            ) VALUES (
                :venta_id, 'ITEM', :item_id, :of_id, 'SRV_TOUR_TITICACA_F26D',
                :concepto, 'PERSONA', :cantidad, :precio, :subtotal, 'PEN'
            )
        ");
        $stmt->execute([
            'venta_id' => $venta1Id,
            'item_id'  => $tourId,
            'of_id'    => $ofertaTourId,
            'concepto' => $concepto,
            'cantidad' => $cantidad,
            'precio'   => $precio,
            'subtotal' => $cantidad * $precio,
        ]);
        return (int) $pdo->lastInsertId();
    };

    $ventaLinea1Id = $crearLineaVenta('FULL DAY LAGO TITICACA (2 PAX)', 2.0, 150.0);
    $ventaLinea2Id = $crearLineaVenta('FULL DAY LAGO TITICACA (FECHA INC)', 1.0, 150.0);
    $ventaLinea3Id = $crearLineaVenta('FULL DAY LAGO TITICACA (PEQUEÑA 1)', 2.0, 150.0);
    $ventaLinea4Id = $crearLineaVenta('FULL DAY LAGO TITICACA (PEQUEÑA EXCESO)', 2.0, 150.0);
    $ventaLinea5Id = $crearLineaVenta('FULL DAY LAGO TITICACA (DISCRETA)', 2.0, 150.0);

    // Crear Reserva 1 con prestación de 2 pasajeros agendada para 2026-02-05
    $stmtRsv1 = $pdo->prepare("
        INSERT INTO `reservas` (
            `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`,
            `estado`, `cliente_id`, `contacto_nombre`, `creado_por`
        ) VALUES (
            :org_id, :edicion_id, :venta_id, 'RSV-2026-000091',
            'CONFIRMADA', 8904, 'CLIENTE RES 1', :creado_por
        )
    ");
    $stmtRsv1->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'venta_id'   => $venta1Id,
        'creado_por' => $adminSinteticoId,
    ]);
    $reserva1Id = (int) $pdo->lastInsertId();

    $stmtPrest1 = $pdo->prepare("
        INSERT INTO `reserva_prestaciones` (
            `reserva_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`,
            `concepto_nombre`, `unidad_medida`, `cantidad`, `tipo_capacidad`,
            `requiere_agendamiento`, `requiere_participantes`, `estado_agendamiento`,
            `fecha_servicio`, `hora_servicio`
        ) VALUES (
            :rsv_id, :linea_id, :item_id, 'SRV_TOUR_TITICACA_F26D',
            'FULL DAY TITICACA', 'PERSONA', 2.00, 'COLECTIVA',
            1, 1, 'PROGRAMADA', '2026-02-05', '07:30:00'
        )
    ");
    $stmtPrest1->execute([
        'rsv_id'   => $reserva1Id,
        'linea_id' => $ventaLinea1Id,
        'item_id'  => $tourId,
    ]);
    $prestacion1Id = (int) $pdo->lastInsertId();

    $pdo->exec("
        INSERT INTO `reserva_participantes` (
            `reserva_id`, `tipo_documento_id`, `numero_documento`,
            `nombres`, `apellidos`, `nacionalidad`, `rango_etario`, `regimen_alimentario`, `telefono_contacto`
        ) VALUES
        ({$reserva1Id}, 1, '70809001', 'ALBERTO', 'MAMANI CALISAYA', 'PE', 'ADULTO', 'ESTANDAR', '951223344'),
        ({$reserva1Id}, 1, '70809002', 'LUCIA', 'QUISPE VERA', 'PE', 'ADULTO', 'VEGETARIANO', '951223345')
    ");

    $stmtObtenerParts = $pdo->prepare("SELECT id FROM `reserva_participantes` WHERE `reserva_id` = :rsv_id ORDER BY `id` ASC");
    $stmtObtenerParts->execute(['rsv_id' => $reserva1Id]);
    $partIds = $stmtObtenerParts->fetchAll(PDO::FETCH_COLUMN);
    $part1Id = (int) $partIds[0];
    $part2Id = (int) $partIds[1];

    $pdo->exec("INSERT INTO `prestacion_participantes` (`prestacion_id`, `participante_id`) VALUES
        ({$prestacion1Id}, {$part1Id}), ({$prestacion1Id}, {$part2Id})
    ");

    // Asignar Prestación 1 a la Salida 1
    $asig1 = $operacionServicio->asignarPrestacionASalida($orgId, (int) $salida1->id, $prestacion1Id, $ctxAdmin);
    afirmar($asig1->id > 0, "6.1: Prestación 1 (2 pax) asignada formalmente a la Salida 1");

    // Verificar que los 2 participantes fueron registrados en operacion_asistencias en estado PENDIENTE
    $asistenciasSalida = $salidaRepo->obtenerAsistenciasPorSalida((int) $salida1->id);
    afirmar(count($asistenciasSalida) === 2, "6.2: Participantes incorporados automáticamente al control de asistencia de la salida");
    afirmar($asistenciasSalida[0]->estadoAsistencia === EstadoAsistencia::PENDIENTE, "6.3: Asistencia de participante nace en estado PENDIENTE");

    // Intentar doble asignación de la misma prestación (debe ser rechazada)
    $dobleAsigRechazada = false;
    try {
        $operacionServicio->asignarPrestacionASalida($orgId, (int) $salida1->id, $prestacion1Id, $ctxAdmin);
    } catch (\Throwable $e) {
        $dobleAsigRechazada = true;
    }
    afirmar($dobleAsigRechazada, "6.4: Doble asignación de la misma prestación a una salida es rechazada");

    // Crear Venta 2 y Reserva 2 para pruebas auxiliares de capacidad e incompatibilidad
    $stmtV2 = $pdo->prepare("
        INSERT INTO `ventas` (
            `organizacion_id`, `edicion_id`, `cliente_id`, `origen_tipo`,
            `correlativo`, `fecha_venta`, `estado`,
            `cliente_nombre_completo`, `moneda`, `subtotal`, `total`, `creado_por`
        ) VALUES (
            :org_id, :edicion_id, 8904, 'DIRECTA',
            'VTA-2026-890002', '2026-02-02', 'CONFIRMADA',
            'CLIENTE OPERACIONES F26D AUX', 'PEN', 1200.00, 1200.00, :creado_por
        )
    ");
    $stmtV2->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'creado_por' => $adminSinteticoId,
    ]);
    $venta2Id = (int) $pdo->lastInsertId();

    $stmtRsv2 = $pdo->prepare("
        INSERT INTO `reservas` (
            `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`,
            `estado`, `cliente_id`, `contacto_nombre`, `creado_por`
        ) VALUES (
            :org_id, :edicion_id, :venta_id, 'RSV-2026-000092',
            'CONFIRMADA', 8904, 'CLIENTE RES 2', :creado_por
        )
    ");
    $stmtRsv2->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'venta_id'   => $venta2Id,
        'creado_por' => $adminSinteticoId,
    ]);
    $reserva2Id = (int) $pdo->lastInsertId();

    // Intentar asignar prestación con fecha incompatible
    $stmtPrestFechaInc = $pdo->prepare("
        INSERT INTO `reserva_prestaciones` (
            `reserva_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`,
            `concepto_nombre`, `unidad_medida`, `cantidad`, `tipo_capacidad`,
            `requiere_agendamiento`, `requiere_participantes`, `estado_agendamiento`,
            `fecha_servicio`, `hora_servicio`
        ) VALUES (
            :rsv_id, :linea_id, :item_id, 'SRV_TOUR_TITICACA_F26D',
            'FULL DAY TITICACA', 'PERSONA', 1.00, 'COLECTIVA',
            1, 1, 'PROGRAMADA', '2026-02-08', '07:30:00'
        )
    ");
    $stmtPrestFechaInc->execute([
        'rsv_id'   => $reserva2Id,
        'linea_id' => $ventaLinea2Id,
        'item_id'  => $tourId,
    ]);
    $prestFechaIncId = (int) $pdo->lastInsertId();

    $fechaIncRechazada = false;
    try {
        $operacionServicio->asignarPrestacionASalida($orgId, (int) $salida1->id, $prestFechaIncId, $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $fechaIncRechazada = str_contains($e->getMessage(), 'Incompatibilidad de fecha');
    }
    afirmar($fechaIncRechazada, "6.5: Prestación con fecha incompatible es rechazada inmediatamente");

    // ==============================================================================
    // BLOQUE 7: CONTROL ESTRICTO DE CAPACIDAD Y ASIENTO DISCRETO
    // ==============================================================================
    echo "\n--- BLOQUE 7: CONTROL ESTRICTO DE CAPACIDAD Y ASIENTO DISCRETO ---\n";

    // Crear Salida de capacidad pequeña (capacidad = 3 pax)
    $salidaPequena = $operacionServicio->crearSalida(
        organizacionId: $orgId,
        edicionId: $edicionId,
        itemComercialId: $tourId,
        titulo: 'GRUPO MINI 05/FEB',
        fechaSalida: '2026-02-05',
        horaCitacion: '08:00:00',
        horaSalida: '08:30:00',
        puntoEncuentro: 'Muelle Puno',
        tipoCapacidad: TipoCapacidad::COLECTIVA,
        capacidadMaxima: 3,
        contexto: $ctxAdmin
    );

    // Crear Prestación Pequeña 1 (2 pax) y asignarla -> ocupa 2 de 3
    $stmtPrestPeq1 = $pdo->prepare("
        INSERT INTO `reserva_prestaciones` (
            `reserva_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`,
            `concepto_nombre`, `unidad_medida`, `cantidad`, `tipo_capacidad`,
            `requiere_agendamiento`, `requiere_participantes`, `estado_agendamiento`,
            `fecha_servicio`, `hora_servicio`
        ) VALUES (
            :rsv_id, :linea_id, :item_id, 'SRV_TOUR_TITICACA_F26D',
            'FULL DAY TITICACA MINI 1', 'PERSONA', 2.00, 'COLECTIVA',
            1, 0, 'PROGRAMADA', '2026-02-05', '08:30:00'
        )
    ");
    $stmtPrestPeq1->execute([
        'rsv_id'   => $reserva2Id,
        'linea_id' => $ventaLinea3Id,
        'item_id'  => $tourId,
    ]);
    $prestPeq1Id = (int) $pdo->lastInsertId();

    $operacionServicio->asignarPrestacionASalida($orgId, (int) $salidaPequena->id, $prestPeq1Id, $ctxAdmin);

    // Crear Prestación Pequeña 2 de 2 pax que superaría la capacidad (2 + 2 = 4 > 3)
    $stmtPrestExceso = $pdo->prepare("
        INSERT INTO `reserva_prestaciones` (
            `reserva_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`,
            `concepto_nombre`, `unidad_medida`, `cantidad`, `tipo_capacidad`,
            `requiere_agendamiento`, `requiere_participantes`, `estado_agendamiento`,
            `fecha_servicio`, `hora_servicio`
        ) VALUES (
            :rsv_id, :linea_id, :item_id, 'SRV_TOUR_TITICACA_F26D',
            'FULL DAY TITICACA MINI 2', 'PERSONA', 2.00, 'COLECTIVA',
            1, 0, 'PROGRAMADA', '2026-02-05', '08:30:00'
        )
    ");
    $stmtPrestExceso->execute([
        'rsv_id'   => $reserva2Id,
        'linea_id' => $ventaLinea4Id,
        'item_id'  => $tourId,
    ]);
    $prestExcesoId = (int) $pdo->lastInsertId();

    $excesoCapacidadRechazado = false;
    try {
        $operacionServicio->asignarPrestacionASalida($orgId, (int) $salidaPequena->id, $prestExcesoId, $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $excesoCapacidadRechazado = str_contains($e->getMessage(), 'Capacidad excedida');
    }
    afirmar($excesoCapacidadRechazado, "7.1: Transacción rechaza sobre-ocupación en capacidad COLECTIVA (2 + 2 > 3)");

    // Salida DISCRETA: Duplicidad de asiento es rechazada
    $salidaDiscreta = $operacionServicio->crearSalida(
        organizacionId: $orgId,
        edicionId: $edicionId,
        itemComercialId: $tourId,
        titulo: 'GRUPO ASIENTOS NUMERADOS',
        fechaSalida: '2026-02-05',
        horaCitacion: '08:00:00',
        horaSalida: '08:30:00',
        puntoEncuentro: 'Muelle Puno',
        tipoCapacidad: TipoCapacidad::DISCRETA,
        capacidadMaxima: 10,
        contexto: $ctxAdmin
    );

    // Crear Prestación y participantes para la salida DISCRETA
    $stmtPrestDisc = $pdo->prepare("
        INSERT INTO `reserva_prestaciones` (
            `reserva_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`,
            `concepto_nombre`, `unidad_medida`, `cantidad`, `tipo_capacidad`,
            `requiere_agendamiento`, `requiere_participantes`, `estado_agendamiento`,
            `fecha_servicio`, `hora_servicio`
        ) VALUES (
            :rsv_id, :linea_id, :item_id, 'SRV_TOUR_TITICACA_F26D',
            'FULL DAY TITICACA DISCRETA', 'PERSONA', 2.00, 'DISCRETA',
            1, 1, 'PROGRAMADA', '2026-02-05', '08:30:00'
        )
    ");
    $stmtPrestDisc->execute([
        'rsv_id'   => $reserva2Id,
        'linea_id' => $ventaLinea5Id,
        'item_id'  => $tourId,
    ]);
    $prestDiscId = (int) $pdo->lastInsertId();

    $pdo->exec("
        INSERT INTO `reserva_participantes` (
            `reserva_id`, `tipo_documento_id`, `numero_documento`,
            `nombres`, `apellidos`, `nacionalidad`, `rango_etario`, `regimen_alimentario`, `telefono_contacto`
        ) VALUES
        ({$reserva2Id}, 1, '70809003', 'CARLOS', 'CONDORI APAZA', 'PE', 'ADULTO', 'ESTANDAR', '951223346'),
        ({$reserva2Id}, 1, '70809004', 'MARIA', 'FLORES CHOQUE', 'PE', 'ADULTO', 'ESTANDAR', '951223347')
    ");

    $stmtDiscPartIds = $pdo->prepare("SELECT id FROM `reserva_participantes` WHERE `numero_documento` IN ('70809003', '70809004') ORDER BY `id` ASC");
    $stmtDiscPartIds->execute();
    $discPartIds = $stmtDiscPartIds->fetchAll(PDO::FETCH_COLUMN);
    $discPart1Id = (int) $discPartIds[0];
    $discPart2Id = (int) $discPartIds[1];

    $pdo->exec("INSERT INTO `prestacion_participantes` (`prestacion_id`, `participante_id`) VALUES
        ({$prestDiscId}, {$discPart1Id}), ({$prestDiscId}, {$discPart2Id})
    ");

    $operacionServicio->asignarPrestacionASalida($orgId, (int) $salidaDiscreta->id, $prestDiscId, $ctxAdmin);

    // Asignar asiento A-01 al participante 1
    $operacionServicio->marcarCheckin($orgId, (int) $salidaDiscreta->id, $discPart1Id, EstadoAsistencia::PRESENTE, 'A-01', null, $ctxAdmin);

    // Intentar asignar el mismo asiento A-01 al participante 2
    $asientoDuplicadoRechazado = false;
    try {
        $operacionServicio->marcarCheckin($orgId, (int) $salidaDiscreta->id, $discPart2Id, EstadoAsistencia::PRESENTE, 'A-01', null, $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $asientoDuplicadoRechazado = str_contains($e->getMessage(), 'ya se encuentra ocupado');
    }
    afirmar($asientoDuplicadoRechazado, "7.2: Duplicidad de ubicación de asiento en capacidad DISCRETA es rechazada");

    // ==============================================================================
    // BLOQUE 8: MANIFIESTO DE PASAJEROS (PII OPERACIONAL MINIMIZADA)
    // ==============================================================================
    echo "\n--- BLOQUE 8: MANIFIESTO DE PASAJEROS (PII OPERACIONAL MINIMIZADA) ---\n";

    $manifiesto = $operacionServicio->obtenerManifiesto($orgId, (int) $salida1->id, $ctxOperador);

    afirmar($manifiesto['total_pax'] === 2, "8.1: Manifiesto consolida exactamente los 2 pasajeros asignados");
    afirmar($manifiesto['pasajeros'][0]['nombres'] === 'ALBERTO', "8.2: Pasajero 1 presente en manifiesto");
    afirmar($manifiesto['pasajeros'][1]['regimen_alimentario'] === 'VEGETARIANO', "8.3: Requerimiento de menú vegetariano visible en manifiesto");
    afirmar(count($manifiesto['recursos']) === 2, "8.4: Manifiesto incluye embarcación y guía asignados");

    // ==============================================================================
    // BLOQUE 9: CHECK-IN, NO-SHOW Y EJECUCIÓN FÍSICA
    // ==============================================================================
    echo "\n--- BLOQUE 9: CHECK-IN, NO-SHOW Y EJECUCIÓN FÍSICA ---\n";

    // Marcar participante 1 como PRESENTE
    $asist1 = $operacionServicio->marcarCheckin(
        organizacionId: $orgId,
        salidaId: (int) $salida1->id,
        participanteId: $part1Id,
        estadoAsistencia: EstadoAsistencia::PRESENTE,
        asiento: null,
        observacion: 'Abordó con mochila y chaleco salvavidas',
        contexto: $ctxOperador
    );
    afirmar($asist1->estadoAsistencia === EstadoAsistencia::PRESENTE, "9.1: Participante 1 marcado formalmente como PRESENTE");
    afirmar($asist1->marcadoPor === $operadorSinteticoId, "9.2: Actor que registró el check-in auditado");

    // Marcar participante 2 como PRESENTE
    $asist2 = $operacionServicio->marcarCheckin(
        organizacionId: $orgId,
        salidaId: (int) $salida1->id,
        participanteId: $part2Id,
        estadoAsistencia: EstadoAsistencia::PRESENTE,
        asiento: null,
        observacion: 'Abordó conforme',
        contexto: $ctxOperador
    );
    afirmar($asist2->estadoAsistencia === EstadoAsistencia::PRESENTE, "9.3: Participante 2 marcado como PRESENTE");

    // Despachar Salida
    $salidaDespachada = $operacionServicio->despacharSalida($orgId, (int) $salida1->id, $salida1->versionBloqueo, $ctxOperador);
    afirmar($salidaDespachada->estado === EstadoSalida::DESPACHADA, "9.4: Salida transiciona formalmente a DESPACHADA");
    afirmar($salidaDespachada->horaInicioReal !== null, "9.5: Hora de inicio real registrada en la salida");

    // Registrar incidencia de navegación
    $incidencia = $operacionServicio->registrarIncidencia(
        organizacionId: $orgId,
        salidaId: (int) $salida1->id,
        tipoIncidencia: TipoIncidenciaOperativa::CLIMA_FUERZA_MAYOR,
        descripcion: 'Vientos moderados en el canal de salida, se navegó a baja velocidad preventiva',
        accionesTomadas: 'Reducción de nudos por seguridad',
        afectoContinuidad: false,
        contexto: $ctxOperador
    );
    afirmar($incidencia->id > 0, "9.6: Incidencia operativa registrada formalmente");

    // Finalizar Salida
    $salidaFinalizada = $operacionServicio->finalizarSalida($orgId, (int) $salida1->id, $salidaDespachada->versionBloqueo, $ctxOperador);
    afirmar($salidaFinalizada->estado === EstadoSalida::FINALIZADA, "9.7: Salida transiciona a FINALIZADA");
    afirmar($salidaFinalizada->horaFinReal !== null, "9.8: Hora de fin real registrada");

    // Intentar modificar o cancelar una salida FINALIZADA (Inmutabilidad de hechos físicos)
    $cancelarFinalizadaRechazada = false;
    try {
        $operacionServicio->cancelarSalida($orgId, (int) $salida1->id, 'Intento de borrado', $salidaFinalizada->versionBloqueo, $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $cancelarFinalizadaRechazada = str_contains($e->getMessage(), 'estado terminal');
    }
    afirmar($cancelarFinalizadaRechazada, "9.9: Inmutabilidad de hechos consumados: Salida FINALIZADA no puede ser cancelada");

    // ==============================================================================
    // BLOQUE 10: PROYECCIÓN DETERMINISTA HACIA RESERVA
    // ==============================================================================
    echo "\n--- BLOQUE 10: PROYECCIÓN DETERMINISTA HACIA RESERVA ---\n";

    // Como todos los participantes asistieron y la salida finalizó con éxito, la proyección es CUMPLIDA
    $proyeccion = $operacionServicio->proyectarResultadoReserva($orgId, $reserva1Id);
    afirmar($proyeccion === ResultadoProyeccionReserva::CUMPLIDA, "10.1: Proyección hacia Reserva: Estado proyectado = CUMPLIDA determinísticamente");

    // ==============================================================================
    // BLOQUE 11: DESPACHO DE ENTREGAS DE PRODUCTOS
    // ==============================================================================
    echo "\n--- BLOQUE 11: DESPACHO DE ENTREGAS DE PRODUCTOS ---\n";

    // Crear orden de entrega sintética
    $stmtEnt = $pdo->prepare("
        INSERT INTO `entregas_productos` (
            `organizacion_id`, `edicion_id`, `venta_id`, `cliente_id`,
            `correlativo`, `estado`, `contacto_nombre`, `creado_por`
        ) VALUES (
            :org_id, :edicion_id, :venta_id, 8904,
            'ENT-2026-000001', 'PENDIENTE_ENTREGA', 'RECEPTOR PRUEBA', :creado_por
        )
    ");
    $stmtEnt->execute([
        'org_id'     => $orgId,
        'edicion_id' => $edicionId,
        'venta_id'   => $venta1Id,
        'creado_por' => $adminSinteticoId,
    ]);
    $entregaId = (int) $pdo->lastInsertId();

    $entregaDespachada = $operacionServicio->despacharEntrega($orgId, $entregaId, $ctxOperador);
    afirmar($entregaDespachada->estado === EstadoEntregaProducto::ENTREGADO, "11.1: Orden de entrega transiciona formalmente a ENTREGADO");
    afirmar($entregaDespachada->fechaEntrega !== null, "11.2: Fecha de entrega efectiva registrada");

    // ==============================================================================
    // BLOQUE 12: CONCURRENCIA OPTIMISTA (VERSION_BLOQUEO)
    // ==============================================================================
    echo "\n--- BLOQUE 12: CONCURRENCIA OPTIMISTA (VERSION_BLOQUEO) ---\n";

    $conflictoDetectado = false;
    try {
        // Enviar versión obsoleta 1 a salidaFinalizada
        $salidaRepo->actualizarEstado((int) $salida1->id, EstadoSalida::FINALIZADA, 1);
    } catch (ConflictoConcurrenciaExcepcion $e) {
        $conflictoDetectado = true;
    }
    afirmar($conflictoDetectado, "12.1: ConflictoConcurrenciaExcepcion lanzada ante version_bloqueo desfasada en salida");

    // ==============================================================================
    // BLOQUE 13: DESACOPLAMIENTO ESTRICTO (CERO PAGOS / CAJA / SUNAT)
    // ==============================================================================
    echo "\n--- BLOQUE 13: DESACOPLAMIENTO ESTRICTO (CERO PAGOS / CAJA / SUNAT) ---\n";

    afirmar(!in_array('pagos', $tablas, true), "13.1: Desacoplamiento Pagos: CERO tabla 'pagos'");
    afirmar(!in_array('caja_sesiones', $tablas, true), "13.2: Desacoplamiento Caja: CERO tabla 'caja_sesiones'");
    afirmar(!in_array('comprobantes_pago', $tablas, true), "13.3: Desacoplamiento SUNAT: CERO tabla 'comprobantes_pago'");

    // ==============================================================================
    // BLOQUE 14: AUDITORÍA TÉCNICA TRANSVERSAL
    // ==============================================================================
    echo "\n--- BLOQUE 14: AUDITORÍA TÉCNICA TRANSVERSAL ---\n";

    $stmtAud = $pdo->prepare("
        SELECT `accion`
        FROM `auditoria_operaciones`
        WHERE `modulo` = 'operaciones'
        ORDER BY `id` ASC
    ");
    $stmtAud->execute();
    $acciones = array_column($stmtAud->fetchAll(PDO::FETCH_ASSOC), 'accion');

    afirmar(in_array('SALIDA_CREADA', $acciones, true), "14.1: Evento 'SALIDA_CREADA' auditado");
    afirmar(in_array('PRESTACION_ASIGNADA_A_SALIDA', $acciones, true), "14.2: Evento 'PRESTACION_ASIGNADA_A_SALIDA' auditado");
    afirmar(in_array('CHECKIN_REGISTRADO', $acciones, true), "14.3: Evento 'CHECKIN_REGISTRADO' auditado");
    afirmar(in_array('SALIDA_DESPACHADA', $acciones, true), "14.4: Evento 'SALIDA_DESPACHADA' auditado");
    afirmar(in_array('SALIDA_FINALIZADA', $acciones, true), "14.5: Evento 'SALIDA_FINALIZADA' auditado");
    afirmar(in_array('INCIDENCIA_REGISTRADA', $acciones, true), "14.6: Evento 'INCIDENCIA_REGISTRADA' auditado");

} finally {
    // Revertir deterministamente la transacción para dejar la base de datos libre de fixtures
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n[INFO] Transacción de pruebas F2.6D revertida con ROLLBACK determinista.\n";
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DELETE FROM `ediciones_candelaria` WHERE `codigo` = 'candelaria-2026-f26d'");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.6D: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
exit(0);
