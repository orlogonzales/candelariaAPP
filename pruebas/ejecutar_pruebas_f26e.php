<?php

declare(strict_types=1);

namespace Pruebas;

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Controladores\OperacionControlador;
use Aplicacion\Controladores\ReservaControlador;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Operaciones\EstadoAsistencia;
use Aplicacion\Operaciones\EstadoRecursoFisico;
use Aplicacion\Operaciones\EstadoSalida;
use Aplicacion\Operaciones\RolOperativoRecurso;
use Aplicacion\Operaciones\TipoIncidenciaOperativa;
use Aplicacion\Operaciones\TipoRecursoFisico;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Reservas\EstadoAgendamientoPrestacion;
use Aplicacion\Reservas\EstadoReserva;
use Aplicacion\Reservas\MotivoReprogramacion;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Vistas\Vista;
use PDO;
use Throwable;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F2.6E\n";
echo "SUPERFICIE HTTP/API RESTful + ALINA UI DE RESERVAS Y OPERACIONES DE CAMPO\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

$exitos = 0;
$fallos = 0;

function afirmar(bool $condicion, string $mensaje, string $detalles = ''): void
{
    global $exitos, $fallos;
    if ($condicion) {
        $exitos++;
        echo " [PASS] {$mensaje}\n";
    } else {
        $fallos++;
        echo " [FAIL] {$mensaje}" . ($detalles !== '' ? " -> {$detalles}" : '') . "\n";
    }
}

// Iniciar transacción de prueba para aislamiento total
$pdo->beginTransaction();

try {
    // --------------------------------------------------------------------------
    // 1. SETUP DE FIXTURES SINTÉTICOS MULTI-TENANT (ZERO-PII)
    // --------------------------------------------------------------------------
    $tenantA = 10000;
    $tenantB = 20000;

    $pdo->exec("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `estado`)
                VALUES ({$tenantB}, 'tenant_b_f26e', 'Tenant B Pruebas F26E', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $edicionAId = 98901;
    $pdo->exec("INSERT INTO `ediciones_candelaria` (`id`, `organizacion_id`, `codigo`, `anio`, `nombre`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`)
                VALUES ({$edicionAId}, {$tenantA}, 'ED-2026-F26E-A', 2026, 'Candelaria 2026 F26E A', 'OPERACION', '2026-02-01', '2026-02-15', 1)
                ON DUPLICATE KEY UPDATE `estado` = 'OPERACION', `es_actual` = 1");

    $edicionBId = 98902;
    $pdo->exec("INSERT INTO `ediciones_candelaria` (`id`, `organizacion_id`, `codigo`, `anio`, `nombre`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`)
                VALUES ({$edicionBId}, {$tenantB}, 'ED-2026-F26E-B', 2026, 'Candelaria 2026 F26E B', 'OPERACION', '2026-02-01', '2026-02-15', 1)
                ON DUPLICATE KEY UPDATE `estado` = 'OPERACION'");

    // Clientes y Personas sintéticas
    $personaClienteA = 8911;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`, `correo_electronico`, `telefono_whatsapp`, `codigo_pais`)
                VALUES ({$personaClienteA}, {$tenantA}, 'NATURAL', 'Cliente', 'Pruebas F26E', '71882201', 1, 'cliente.f26e@test.local', '+51999111222', 'PE')
                ON DUPLICATE KEY UPDATE `nombres` = 'Cliente'");

    $clienteAId = 55911;
    $pdo->exec("INSERT INTO `clientes` (`id`, `organizacion_id`, `persona_id`, `estado_comercial`)
                VALUES ({$clienteAId}, {$tenantA}, {$personaClienteA}, 'CLIENTE')
                ON DUPLICATE KEY UPDATE `estado_comercial` = 'CLIENTE'");

    // Personal Operativo (Guía y Patrón)
    $personaGuia = 8912;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$personaGuia}, {$tenantA}, 'NATURAL', 'Guia', 'Oficial Puno', '71882202', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'Guia'");

    $personaConductor = 8913;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$personaConductor}, {$tenantA}, 'NATURAL', 'Patron', 'Lancha Titicaca', '71882203', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'Patron'");

    $personaProveedor = 8914;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$personaProveedor}, {$tenantA}, 'NATURAL', 'Proveedor', 'Aliado Lacustre', '71882204', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'Proveedor'");

    // Usuarios del Sistema
    // 1. Admin Tenant A (Rol 2: admin de org, tiene todos los permisos)
    $usrAdminAId = 7811;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$usrAdminAId}, {$tenantA}, 'NATURAL', 'Admin', 'Ops A', '88882001', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'Admin'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `es_superadmin_plataforma`, `estado`)
                VALUES ({$usrAdminAId}, {$tenantA}, {$usrAdminAId}, 'admin_f26e_a', 'ADMIN OPS F26E', 'admin_ops@test.local', 'hash', 0, 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES ({$usrAdminAId}, 2) ON DUPLICATE KEY UPDATE `rol_id` = 2");

    // 2. Operador Tenant A (Rol 3: operador, tiene permisos de campo, pero NO crear_desde_venta ni cancelar reserva ni gestionar salidas)
    $usrOperadorAId = 7812;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$usrOperadorAId}, {$tenantA}, 'NATURAL', 'Operador', 'Ops A', '88882002', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'Operador'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `es_superadmin_plataforma`, `estado`)
                VALUES ({$usrOperadorAId}, {$tenantA}, {$usrOperadorAId}, 'op_f26e_a', 'OPERADOR OPS F26E', 'op_ops@test.local', 'hash', 0, 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES ({$usrOperadorAId}, 3) ON DUPLICATE KEY UPDATE `rol_id` = 3");

    // 3. Usuario Sin Permisos Tenant A (sin roles)
    $usrSinPermisosAId = 7813;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$usrSinPermisosAId}, {$tenantA}, 'NATURAL', 'SinPerm', 'Ops A', '88882003', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'SinPerm'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `es_superadmin_plataforma`, `estado`)
                VALUES ({$usrSinPermisosAId}, {$tenantA}, {$usrSinPermisosAId}, 'sinperm_f26e_a', 'SIN PERM OPS F26E', 'sinperm@test.local', 'hash', 0, 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("DELETE FROM `usuario_roles` WHERE `usuario_id` = {$usrSinPermisosAId}");

    // 4. Admin Tenant B
    $usrAdminBId = 7814;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$usrAdminBId}, {$tenantB}, 'NATURAL', 'Admin', 'Ops B', '88882004', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'Admin'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `es_superadmin_plataforma`, `estado`)
                VALUES ({$usrAdminBId}, {$tenantB}, {$usrAdminBId}, 'admin_f26e_b', 'ADMIN OPS F26E B', 'admin_b@test.local', 'hash', 0, 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES ({$usrAdminBId}, 2) ON DUPLICATE KEY UPDATE `rol_id` = 2");

    // Catálogo Comercial: Categoría, Ítem Servicio con Config Operativa e Ítem Bien Tangible
    $pdo->exec("INSERT INTO `categorias_items` (`id`, `organizacion_id`, `codigo`, `nombre`)
                VALUES (8892, {$tenantA}, 'CAT_OPS_F26E', 'Operaciones y Tours F26E')
                ON DUPLICATE KEY UPDATE `nombre` = 'Operaciones y Tours F26E'");

    $itemServicioId = 66911;
    $pdo->exec("INSERT INTO `items_comerciales` (`id`, `organizacion_id`, `categoria_id`, `codigo`, `nombre`, `tipo`, `unidad_medida`, `estado`)
                VALUES ({$itemServicioId}, {$tenantA}, 8892, 'SRV_ISLAS_F26E', 'Tour Lago Sagrado Islas Flotantes', 'SERVICIO', 'SERVICIO', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $pdo->exec("INSERT INTO `item_configuracion_operativa` (
                    `item_comercial_id`, `requiere_reserva`, `requiere_agendamiento`, `requiere_participantes`,
                    `tipo_capacidad`, `es_accesorio`, `duracion_estimada_minutos`, `punto_partida_predeterminado`
                ) VALUES (
                    {$itemServicioId}, 1, 1, 1, 'COLECTIVA', 0, 180, 'Muelle Lacustre Puno Banchero'
                ) ON DUPLICATE KEY UPDATE `requiere_reserva` = 1, `requiere_agendamiento` = 1");

    $itemProductoId = 66912;
    $pdo->exec("INSERT INTO `items_comerciales` (`id`, `organizacion_id`, `categoria_id`, `codigo`, `nombre`, `tipo`, `unidad_medida`, `estado`)
                VALUES ({$itemProductoId}, {$tenantA}, 8892, 'BIEN_POLO_F26E', 'Polo Conmemorativo Candelaria 2026', 'PRODUCTO', 'UNIDAD', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $pdo->exec("INSERT INTO `item_configuracion_operativa` (
                    `item_comercial_id`, `requiere_reserva`, `requiere_agendamiento`, `requiere_participantes`,
                    `tipo_capacidad`, `es_accesorio`
                ) VALUES (
                    {$itemProductoId}, 0, 0, 0, 'SIN_CONTROL', 0
                ) ON DUPLICATE KEY UPDATE `requiere_reserva` = 0");

    // Ofertas para los ítems
    $ofertaServicioA = 44911;
    $pdo->exec("INSERT INTO `ofertas_items_edicion` (`id`, `organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`)
                VALUES ({$ofertaServicioA}, {$tenantA}, {$edicionAId}, {$itemServicioId}, 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $ofertaProductoA = 44912;
    $pdo->exec("INSERT INTO `ofertas_items_edicion` (`id`, `organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`)
                VALUES ({$ofertaProductoA}, {$tenantA}, {$edicionAId}, {$itemProductoId}, 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    // Ventas Confirmadas
    // Venta A1: Servicio agendable
    $ventaA1Id = 99101;
    $pdo->exec("INSERT INTO `ventas` (
                    `id`, `organizacion_id`, `edicion_id`, `cliente_id`, `origen_tipo`,
                    `correlativo`, `fecha_venta`, `estado`,
                    `cliente_nombre_completo`, `moneda`, `subtotal`, `total`, `creado_por`
                ) VALUES (
                    {$ventaA1Id}, {$tenantA}, {$edicionAId}, {$clienteAId}, 'DIRECTA',
                    'VTA-2026-99101', '2026-02-05', 'CONFIRMADA',
                    'Cliente Pruebas F26E', 'PEN', 300.00, 300.00, {$usrAdminAId}
                ) ON DUPLICATE KEY UPDATE `estado` = 'CONFIRMADA'");

    $pdo->exec("INSERT INTO `venta_lineas` (
                    `id`, `venta_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`,
                    `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`,
                    `subtotal`, `moneda`
                ) VALUES (
                    88101, {$ventaA1Id}, 'ITEM', {$itemServicioId}, {$ofertaServicioA}, 'SRV_ISLAS_F26E',
                    'Tour Lago Sagrado Islas Flotantes', 'SERVICIO', 2.00, 150.00, 300.00, 'PEN'
                ) ON DUPLICATE KEY UPDATE `cantidad` = 2.00");

    // Venta A2: Producto tangible (genera entrega)
    $ventaA2Id = 99102;
    $pdo->exec("INSERT INTO `ventas` (
                    `id`, `organizacion_id`, `edicion_id`, `cliente_id`, `origen_tipo`,
                    `correlativo`, `fecha_venta`, `estado`,
                    `cliente_nombre_completo`, `moneda`, `subtotal`, `total`, `creado_por`
                ) VALUES (
                    {$ventaA2Id}, {$tenantA}, {$edicionAId}, {$clienteAId}, 'DIRECTA',
                    'VTA-2026-99102', '2026-02-05', 'CONFIRMADA',
                    'Cliente Pruebas F26E', 'PEN', 100.00, 100.00, {$usrAdminAId}
                ) ON DUPLICATE KEY UPDATE `estado` = 'CONFIRMADA'");

    $pdo->exec("INSERT INTO `venta_lineas` (
                    `id`, `venta_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`,
                    `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`,
                    `subtotal`, `moneda`
                ) VALUES (
                    88102, {$ventaA2Id}, 'ITEM', {$itemProductoId}, {$ofertaProductoA}, 'BIEN_POLO_F26E',
                    'Polo Conmemorativo Candelaria 2026', 'UNIDAD', 2.00, 50.00, 100.00, 'PEN'
                ) ON DUPLICATE KEY UPDATE `cantidad` = 2.00");

    // Venta B1: Tenant B (para pruebas Anti-IDOR)
    $personaClienteB = 8915;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$personaClienteB}, {$tenantB}, 'NATURAL', 'Cliente B', 'Alien Tenant', '71882205', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'Cliente B'");
    $clienteBId = 55912;
    $pdo->exec("INSERT INTO `clientes` (`id`, `organizacion_id`, `persona_id`, `estado_comercial`)
                VALUES ({$clienteBId}, {$tenantB}, {$personaClienteB}, 'CLIENTE')
                ON DUPLICATE KEY UPDATE `estado_comercial` = 'CLIENTE'");

    $pdo->exec("INSERT INTO `categorias_items` (`id`, `organizacion_id`, `codigo`, `nombre`)
                VALUES (8893, {$tenantB}, 'CAT_B_F26E', 'Cat Tenant B')
                ON DUPLICATE KEY UPDATE `nombre` = 'Cat Tenant B'");

    $itemServicioB = 66913;
    $pdo->exec("INSERT INTO `items_comerciales` (`id`, `organizacion_id`, `categoria_id`, `codigo`, `nombre`, `tipo`, `unidad_medida`, `estado`)
                VALUES ({$itemServicioB}, {$tenantB}, 8893, 'SRV_B_F26E', 'Tour Tenant B', 'SERVICIO', 'SERVICIO', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `item_configuracion_operativa` (
                    `item_comercial_id`, `requiere_reserva`, `requiere_agendamiento`, `requiere_participantes`, `tipo_capacidad`
                ) VALUES ({$itemServicioB}, 1, 1, 1, 'COLECTIVA')
                ON DUPLICATE KEY UPDATE `requiere_reserva` = 1");

    $ofertaServicioB = 44913;
    $pdo->exec("INSERT INTO `ofertas_items_edicion` (`id`, `organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`)
                VALUES ({$ofertaServicioB}, {$tenantB}, {$edicionBId}, {$itemServicioB}, 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $ventaB1Id = 99103;
    $pdo->exec("INSERT INTO `ventas` (
                    `id`, `organizacion_id`, `edicion_id`, `cliente_id`, `origen_tipo`,
                    `correlativo`, `fecha_venta`, `estado`,
                    `cliente_nombre_completo`, `moneda`, `subtotal`, `total`, `creado_por`
                ) VALUES (
                    {$ventaB1Id}, {$tenantB}, {$edicionBId}, {$clienteBId}, 'DIRECTA',
                    'VTA-2026-99103', '2026-02-05', 'CONFIRMADA',
                    'Cliente B Alien', 'PEN', 100.00, 100.00, {$usrAdminBId}
                ) ON DUPLICATE KEY UPDATE `estado` = 'CONFIRMADA'");

    $pdo->exec("INSERT INTO `venta_lineas` (
                    `id`, `venta_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`,
                    `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`,
                    `subtotal`, `moneda`
                ) VALUES (
                    88103, {$ventaB1Id}, 'ITEM', {$itemServicioB}, {$ofertaServicioB}, 'SRV_B_F26E',
                    'Tour Tenant B', 'SERVICIO', 1.00, 100.00, 100.00, 'PEN'
                ) ON DUPLICATE KEY UPDATE `cantidad` = 1.00");


    // Contextos Operativos
    $csrfTokenValido = 'csrf_token_f26e_soberano_987654321';

    $ctxAdminA = ContextoOperacion::paraHumano(
        usuarioId: $usrAdminAId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF26E/AdminA',
        organizacionId: $tenantA,
        metadatos: ['csrf_token' => $csrfTokenValido]
    );

    $ctxOperadorA = ContextoOperacion::paraHumano(
        usuarioId: $usrOperadorAId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF26E/OpA',
        organizacionId: $tenantA,
        metadatos: ['csrf_token' => $csrfTokenValido]
    );

    $ctxSinPermisosA = ContextoOperacion::paraHumano(
        usuarioId: $usrSinPermisosAId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF26E/SinPermA',
        organizacionId: $tenantA,
        metadatos: ['csrf_token' => $csrfTokenValido]
    );

    $ctxAdminB = ContextoOperacion::paraHumano(
        usuarioId: $usrAdminBId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF26E/AdminB',
        organizacionId: $tenantB,
        metadatos: ['csrf_token' => $csrfTokenValido]
    );

    // Mock del Middleware de Autenticación
    $authMiddlewareMock = new class($ctxAdminA) extends AutenticacionMiddleware {
        public function __construct(private ?ContextoOperacion $ctx) {}
        public function setContexto(?ContextoOperacion $ctx): void { $this->ctx = $ctx; }
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion {
            return $this->ctx;
        }
    };

    $edicionRepo = new EdicionRepositorio($pdo);
    $configServicio = new ConfiguracionServicio(pdo: $pdo);

    $reservaCtrl = new ReservaControlador(
        authMiddleware: $authMiddlewareMock,
        authzMiddleware: null,
        reservaServicio: null,
        edicionRepo: $edicionRepo,
        configServicio: $configServicio,
        pdo: $pdo
    );

    $operacionCtrl = new OperacionControlador(
        authMiddleware: $authMiddlewareMock,
        authzMiddleware: null,
        operacionServicio: null,
        proveedorRecursoServicio: null,
        edicionRepo: $edicionRepo,
        configServicio: $configServicio,
        pdo: $pdo
    );

    // ==============================================================================
    // BLOQUE 1: INFRAESTRUCTURA HTTP, AUTENTICACIÓN, CSRF Y ENVELOPE JSON UNIFORME
    // ==============================================================================
    echo "\n--- BLOQUE 1: INFRAESTRUCTURA HTTP, AUTENTICACIÓN Y ENVELOPE JSON ---\n";

    // 1.1 Sin sesión activa: retorna 401 en ReservaControlador
    $authMiddlewareMock->setContexto(null);
    $respJson401Res = $reservaCtrl->listar();
    $d401Res = json_decode($respJson401Res, true);
    afirmar($d401Res['exito'] === false && str_contains($d401Res['mensaje'], 'Sesión no válida'), "1.1: ReservaControlador::listar() sin sesión retorna 401");

    // 1.2 Sin sesión activa: retorna 401 en OperacionControlador
    $respJson401Ops = $operacionCtrl->listarSalidas();
    $d401Ops = json_decode($respJson401Ops, true);
    afirmar($d401Ops['exito'] === false && str_contains($d401Ops['mensaje'], 'Sesión no válida'), "1.2: OperacionControlador::listarSalidas() sin sesión retorna 401");

    // 1.3 Envelope uniforme estricto: exito, mensaje, datos, errores (cero codigo en raíz)
    $authMiddlewareMock->setContexto($ctxAdminA);
    $_SERVER['HTTP_X_EDICION_ID'] = (string) $edicionAId;
    $respJson200 = $reservaCtrl->listar();
    $d200 = json_decode($respJson200, true);
    $clavesEnvelope = array_keys($d200);
    sort($clavesEnvelope);
    afirmar($clavesEnvelope === ['datos', 'errores', 'exito', 'mensaje'], "1.3: Envelope uniforme estricto en Reservas: contiene exactamente 'datos', 'errores', 'exito', 'mensaje'");
    afirmar(!array_key_exists('codigo', $d200), "1.4: Cero clave 'codigo' en la raíz del envelope JSON de Reservas");

    $respJsonOps = $operacionCtrl->listarSalidas();
    $dOps = json_decode($respJsonOps, true);
    $clavesOps = array_keys($dOps);
    sort($clavesOps);
    afirmar($clavesOps === ['datos', 'errores', 'exito', 'mensaje'], "1.5: Envelope uniforme estricto en Operaciones");

    // 1.6 CSRF: Mutación con token ausente o inválido es rechazada con HTTP 403
    $_POST = ['csrf_token' => 'token_falso_invalido'];
    $respCsrf = $reservaCtrl->formalizarDesdeVenta($ventaA1Id);
    $dCsrf = json_decode($respCsrf, true);
    afirmar($dCsrf['exito'] === false && str_contains($dCsrf['mensaje'], 'CSRF'), "1.6: Mutación en Reservas con token CSRF inválido es rechazada con HTTP 403");

    $_POST = ['csrf_token' => 'token_falso_invalido'];
    $respCsrfOps = $operacionCtrl->crearSalida();
    $dCsrfOps = json_decode($respCsrfOps, true);
    afirmar($dCsrfOps['exito'] === false && str_contains($dCsrfOps['mensaje'], 'CSRF'), "1.7: Mutación en Operaciones con token CSRF inválido es rechazada con HTTP 403");

    // ==============================================================================
    // BLOQUE 2: CONTEXTO DE EDICIÓN Y AISLAMIENTO MULTI-TENANT (ANTI-IDOR)
    // ==============================================================================
    echo "\n--- BLOQUE 2: CONTEXTO DE EDICIÓN Y AISLAMIENTO MULTI-TENANT ---\n";

    // 2.1 X-Edicion-Id inválido genera fail-closed
    $_SERVER['HTTP_X_EDICION_ID'] = 'invalido_hacker';
    $edicionFailClosed = false;
    try {
        $reservaCtrl->listar();
    } catch (\InvalidArgumentException $e) {
        $edicionFailClosed = true;
    }
    afirmar($edicionFailClosed, "2.1: Header X-Edicion-Id no numérico produce Fail-Closed inmediato");

    // 2.2 X-Edicion-Id de otro tenant produce AccesoDenegadoExcepcion
    $_SERVER['HTTP_X_EDICION_ID'] = (string) $edicionBId;
    $crossTenantBloqueado = false;
    try {
        $reservaCtrl->listar();
    } catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
        $crossTenantBloqueado = true;
    }
    afirmar($crossTenantBloqueado, "2.2: Header X-Edicion-Id de otro tenant produce Fail-Closed (Acceso Denegado)");

    // Restaurar header correcto
    $_SERVER['HTTP_X_EDICION_ID'] = (string) $edicionAId;

    // ==============================================================================
    // BLOQUE 3: RBAC SOBERANO EN RESERVAS Y OPERACIONES
    // ==============================================================================
    echo "\n--- BLOQUE 3: CONTROL DE ACCESO BASADO EN ROLES (RBAC) ---\n";

    // 3.1 Usuario sin 'reservas.ver'
    $authMiddlewareMock->setContexto($ctxSinPermisosA);
    $respRbac1 = $reservaCtrl->listar();
    $dRbac1 = json_decode($respRbac1, true);
    afirmar($dRbac1['exito'] === false && str_contains($dRbac1['mensaje'], 'reservas.ver'), "3.1: Usuario sin 'reservas.ver' recibe 403 en listar reservas");

    // 3.2 Usuario sin 'reservas.crear_desde_venta'
    $_POST = ['csrf_token' => $csrfTokenValido];
    $respRbac2 = $reservaCtrl->formalizarDesdeVenta($ventaA1Id);
    $dRbac2 = json_decode($respRbac2, true);
    afirmar($dRbac2['exito'] === false && (str_contains($dRbac2['mensaje'], 'reservas.crear_desde_venta') || str_contains($dRbac2['mensaje'], 'privilegios')), "3.2: Usuario sin 'reservas.crear_desde_venta' recibe 403 al formalizar");

    // 3.3 Usuario sin 'operacion.ver'
    $respRbac3 = $operacionCtrl->listarSalidas();
    $dRbac3 = json_decode($respRbac3, true);
    afirmar($dRbac3['exito'] === false && str_contains($dRbac3['mensaje'], 'operacion.ver'), "3.3: Usuario sin 'operacion.ver' recibe 403 en listar salidas");

    // 3.4 Usuario sin 'operacion.gestionar_salidas'
    $_POST = ['csrf_token' => $csrfTokenValido];
    $respRbac4 = $operacionCtrl->crearSalida();
    $dRbac4 = json_decode($respRbac4, true);
    afirmar($dRbac4['exito'] === false && (str_contains($dRbac4['mensaje'], 'operacion.gestionar_salidas') || str_contains($dRbac4['mensaje'], 'privilegios')), "3.4: Usuario sin 'operacion.gestionar_salidas' recibe 403 al crear salida");

    // 3.5 Usuario sin 'proveedores.ver'
    $respRbac5 = $operacionCtrl->listarProveedores();
    $dRbac5 = json_decode($respRbac5, true);
    afirmar($dRbac5['exito'] === false && str_contains($dRbac5['mensaje'], 'proveedores.ver'), "3.5: Usuario sin 'proveedores.ver' recibe 403 en listar proveedores");

    // 3.6 Usuario sin 'recursos.ver'
    $respRbac6 = $operacionCtrl->listarRecursos();
    $dRbac6 = json_decode($respRbac6, true);
    afirmar($dRbac6['exito'] === false && str_contains($dRbac6['mensaje'], 'recursos.ver'), "3.6: Usuario sin 'recursos.ver' recibe 403 en listar recursos");

    // 3.7 Usuario sin 'entregas.despachar'
    $_POST = ['csrf_token' => $csrfTokenValido];
    $respRbac7 = $operacionCtrl->despacharEntrega(999);
    $dRbac7 = json_decode($respRbac7, true);
    afirmar($dRbac7['exito'] === false && (str_contains($dRbac7['mensaje'], 'entregas.despachar') || str_contains($dRbac7['mensaje'], 'privilegios')), "3.7: Usuario sin 'entregas.despachar' recibe 403 en despacho", $respRbac7);

    // ==============================================================================
    // BLOQUE 4: FLUJO DE RESERVAS Y AGENDAMIENTO (RESERVACONTROLADOR)
    // ==============================================================================
    echo "\n--- BLOQUE 4: FLUJO DE RESERVAS Y AGENDAMIENTO ---\n";

    $authMiddlewareMock->setContexto($ctxAdminA);

    // 4.1 Formalizar Reserva desde Venta confirmada de servicio
    $_POST = ['csrf_token' => $csrfTokenValido];
    $respFormalizar = $reservaCtrl->formalizarDesdeVenta($ventaA1Id);
    $dForm = json_decode($respFormalizar, true);
    afirmar($dForm['exito'] === true && isset($dForm['datos']['reserva']['id']), "4.1: Formalizar reserva desde venta confirmada exitoso");

    $reservaCreadaId = (int) $dForm['datos']['reserva']['id'];
    $correlativoReserva = $dForm['datos']['reserva']['correlativo'];
    afirmar(str_starts_with($correlativoReserva, 'RSV-2026-'), "4.2: Correlativo de reserva formalizado con prefijo RSV-2026-");

    // 4.3 Detalle 360 de la reserva
    $respDetalleRes = $reservaCtrl->detalle($reservaCreadaId);
    $dDetRes = json_decode($respDetalleRes, true);
    afirmar($dDetRes['exito'] === true && count($dDetRes['datos']['reserva']['prestaciones']) === 1, "4.3: Detalle 360 de reserva contiene exactamente 1 prestación de tour");

    $prestacionCreada = $dDetRes['datos']['reserva']['prestaciones'][0];
    $prestacionId = (int) $prestacionCreada['id'];
    afirmar($prestacionCreada['estado_agendamiento'] === 'PENDIENTE_PROGRAMAR', "4.4: Prestación recién formalizada inicia en PENDIENTE_PROGRAMAR");

    // 4.5 Anti-IDOR en Detalle de Reserva: Tenant B no puede ver la reserva de Tenant A
    $authMiddlewareMock->setContexto($ctxAdminB);
    $respIdorRes = $reservaCtrl->detalle($reservaCreadaId);
    $dIdorRes = json_decode($respIdorRes, true);
    afirmar($dIdorRes['exito'] === false && str_contains($dIdorRes['mensaje'], 'no existe en su organización'), "4.5: Anti-IDOR: Tenant B no puede consultar detalle de reserva de Tenant A (404)");

    $authMiddlewareMock->setContexto($ctxAdminA);

    // 4.6 Programar prestación
    $_POST = [
        'csrf_token'      => $csrfTokenValido,
        'fecha_servicio'  => '2026-02-10',
        'hora_servicio'   => '08:30:00',
        'punto_encuentro' => 'Muelle Principal Banchero Rossi',
    ];
    $respProg = $reservaCtrl->programarPrestacion($prestacionId);
    $dProg = json_decode($respProg, true);
    afirmar($dProg['exito'] === true && $dProg['datos']['estado_agendamiento'] === 'PROGRAMADA', "4.6: Programar prestación asigna fecha y transiciona a PROGRAMADA");

    // 4.7 Reprogramar prestación con registro de motivo obligatorio
    $_POST = [
        'csrf_token'       => $csrfTokenValido,
        'nueva_fecha'      => '2026-02-11',
        'nueva_hora'       => '09:00:00',
        'motivo_categoria' => 'CLIMA_FUERZA_MAYOR',
        'motivo_detalle'   => 'Oleaje anómalo en el lago Titicaca reportado por capitanía de puerto',
    ];
    $respReprog = $reservaCtrl->reprogramarPrestacion($prestacionId);
    $dReprog = json_decode($respReprog, true);
    afirmar($dReprog['exito'] === true && $dReprog['datos']['fecha_servicio'] === '2026-02-11', "4.7: Reprogramar prestación con motivo válido actualiza fecha de servicio");

    // 4.8 Consultar historial de reprogramaciones
    $respHistRep = $reservaCtrl->obtenerReprogramaciones($prestacionId);
    $dHistRep = json_decode($respHistRep, true);
    afirmar($dHistRep['exito'] === true && count($dHistRep['datos']['reprogramaciones']) >= 1, "4.8: Consulta de reprogramaciones retorna historial auditable");
    afirmar($dHistRep['datos']['reprogramaciones'][0]['motivo_categoria'] === 'CLIMA_FUERZA_MAYOR', "4.9: Motivo de reprogramación auditado como CLIMA_FUERZA_MAYOR");

    // 4.10 Registrar participante con datos válidos
    $_POST = [
        'csrf_token'             => $csrfTokenValido,
        'nombres'                => 'Carlos Pasajero',
        'apellidos'              => 'Mamani Quispe',
        'numero_documento'       => '70809011',
        'tipo_documento_id'      => 1,
        'nacionalidad'           => 'PE',
        'rango_etario'           => 'ADULTO',
        'regimen_alimentario'    => 'ESTANDAR',
        'prestacion_ids'         => [$prestacionId],
    ];
    $respPart = $reservaCtrl->registrarParticipante($reservaCreadaId);
    $dPart = json_decode($respPart, true);
    afirmar($dPart['exito'] === true && isset($dPart['datos']['participante']['id']), "4.10: Registrar participante en reserva exitoso");
    $participanteId = (int) $dPart['datos']['participante']['id'];

    // 4.11 Eliminar participante
    $_POST = ['csrf_token' => $csrfTokenValido];
    $respDelPart = $reservaCtrl->eliminarParticipante($reservaCreadaId, $participanteId);
    $dDelPart = json_decode($respDelPart, true);
    afirmar($dDelPart['exito'] === true, "4.11: Eliminar participante de reserva exitoso");

    // Re-registrar participante para probar salida de campo
    $_POST = [
        'csrf_token'             => $csrfTokenValido,
        'nombres'                => 'Carlos Pasajero',
        'apellidos'              => 'Mamani Quispe',
        'numero_documento'       => '70809011',
        'tipo_documento_id'      => 1,
        'nacionalidad'           => 'PE',
        'rango_etario'           => 'ADULTO',
        'regimen_alimentario'    => 'ESTANDAR',
        'prestacion_ids'         => [$prestacionId],
    ];
    $respPart2 = $reservaCtrl->registrarParticipante($reservaCreadaId);
    $dPart2 = json_decode($respPart2, true);
    $participanteId2 = (int) $dPart2['datos']['participante']['id'];

    // ==============================================================================
    // BLOQUE 5: FLUJO DE SALIDAS OPERATIVAS, RECURSOS Y ASIGNACIONES
    // ==============================================================================
    echo "\n--- BLOQUE 5: SALIDAS OPERATIVAS, RECURSOS Y MANIFIESTO ---\n";

    // 5.1 Registrar Recurso Físico (Lancha) y Proveedor Aliado
    $_POST = [
        'csrf_token'            => $csrfTokenValido,
        'codigo_interno'        => 'LANCHA_F26E_01',
        'nombre'                => 'Lancha Rápida Lago Sagrado I',
        'tipo_recurso'          => 'EMBARCACION_LACUSTRE',
        'capacidad_maxima'      => 25,
        'propiedad_tipo'        => 'PROPIO',
        'identificacion_oficial'=> 'PU-12345-BM',
    ];
    $respRec = $operacionCtrl->registrarRecurso();
    $dRec = json_decode($respRec, true);
    afirmar($dRec['exito'] === true && isset($dRec['datos']['recurso_id']), "5.1: Registrar recurso físico (Embarcación) exitoso");
    $recursoLanchaId = (int) ($dRec['datos']['recurso_id'] ?? 0);

    $_POST = [
        'csrf_token'     => $csrfTokenValido,
        'persona_id'     => $personaProveedor,
        'tipo_servicio'  => 'Transporte Lacustre',
        'notas_contacto' => 'Convenio muelle turístico',
    ];
    $respProv = $operacionCtrl->registrarProveedor();
    $dProv = json_decode($respProv, true);
    afirmar($dProv['exito'] === true && isset($dProv['datos']['proveedor_id']), "5.2: Registrar proveedor aliado exitoso");
    $proveedorId = (int) $dProv['datos']['proveedor_id'];

    // 5.3 Crear Salida Operativa en estado PROGRAMADA
    $_POST = [
        'csrf_token'       => $csrfTokenValido,
        'edicion_id'       => $edicionAId,
        'item_comercial_id'=> $itemServicioId,
        'titulo'           => 'Salida 01 Tour Islas Flotantes F26E',
        'fecha_salida'     => '2026-02-11',
        'hora_citacion'    => '08:00:00',
        'hora_salida'      => '08:30:00',
        'punto_encuentro'  => 'Muelle Banchero Rossi Puno',
        'tipo_capacidad'   => 'COLECTIVA',
        'capacidad_maxima' => 25,
    ];
    $respSalida = $operacionCtrl->crearSalida();
    $dSalida = json_decode($respSalida, true);
    afirmar($dSalida['exito'] === true && isset($dSalida['datos']['salida']['id']), "5.3: Crear salida operativa exitoso");
    $salidaId = (int) ($dSalida['datos']['salida']['id'] ?? 0);
    afirmar(($dSalida['datos']['salida']['estado'] ?? '') === 'PROGRAMADA', "5.4: Salida recién creada inicia en estado PROGRAMADA");

    // 5.5 Anti-IDOR en Detalle de Salida: Tenant B no puede ver la salida de Tenant A
    $authMiddlewareMock->setContexto($ctxAdminB);
    $respIdorSalida = $operacionCtrl->detalleSalida($salidaId);
    $dIdorSalida = json_decode($respIdorSalida, true);
    afirmar($dIdorSalida['exito'] === false && str_contains($dIdorSalida['mensaje'], 'no existe en su organización'), "5.5: Anti-IDOR: Tenant B no puede ver la salida de Tenant A (404)");

    $authMiddlewareMock->setContexto($ctxAdminA);

    // 5.6 Consultar prestaciones compatibles para la salida
    $respCompat = $operacionCtrl->prestacionesCompatibles($salidaId);
    $dCompat = json_decode($respCompat, true);
    afirmar($dCompat['exito'] === true && count($dCompat['datos']['prestaciones']) >= 1, "5.6: Prestaciones compatibles encuentra la prestación programada para 2026-02-11");

    // 5.7 Asignar prestación a la salida
    $_POST = [
        'csrf_token'         => $csrfTokenValido,
        'prestacion_id'      => $prestacionId,
        'cantidad_pasajeros' => 2.00,
    ];
    $respAsigPrest = $operacionCtrl->asignarPrestacion($salidaId);
    $dAsigPrest = json_decode($respAsigPrest, true);
    afirmar($dAsigPrest['exito'] === true, "5.7: Asignar prestación a salida operativa exitoso");

    // 5.8 Asignar Recursos Físicos y Personal Operativo
    $_POST = [
        'csrf_token'        => $csrfTokenValido,
        'recurso_fisico_id' => $recursoLanchaId,
        'rol_operativo'     => 'PATRON_LANCHA',
        'notas'             => 'Lancha de cabecera',
    ];
    $respAsigRec = $operacionCtrl->asignarRecurso($salidaId);
    $dAsigRec = json_decode($respAsigRec, true);
    afirmar($dAsigRec['exito'] === true, "5.8: Asignar embarcación como recurso de salida exitoso");

    $_POST = [
        'csrf_token'    => $csrfTokenValido,
        'persona_id'    => $personaGuia,
        'rol_operativo' => 'GUIA_PRINCIPAL',
        'notas'         => 'Guía bilingüe acreditado',
    ];
    $respAsigGuia = $operacionCtrl->asignarRecurso($salidaId);
    $dAsigGuia = json_decode($respAsigGuia, true);
    afirmar($dAsigGuia['exito'] === true, "5.9: Asignar guía principal a la salida exitoso");

    // 5.10 Manifiesto Oficial de Pasajeros (PII operacional aprobada, CERO inventos médicos)
    $respManif = $operacionCtrl->manifiesto($salidaId);
    $dManif = json_decode($respManif, true);
    afirmar($dManif['exito'] === true && isset($dManif['datos']['pasajeros']), "5.10: Consulta de manifiesto oficial de pasajeros exitosa");
    $pasajeros = $dManif['datos']['pasajeros'] ?? [];
    afirmar(count($pasajeros) >= 1, "5.11: Manifiesto contiene a los pasajeros de las prestaciones asignadas");
    $pax = $pasajeros[0] ?? [];
    afirmar(!isset($pax['contacto_emergencia']), "5.12: Manifiesto respeta contrato soberano: CERO campos no autorizados de contacto de emergencia");

    // ==============================================================================
    // BLOQUE 6: CHECK-IN TÁCTIL Y TRANSICIONES DEL CICLO DE VIDA DE SALIDA
    // ==============================================================================
    echo "\n--- BLOQUE 6: CHECK-IN Y CICLO DE VIDA DE SALIDA ---\n";

    // 6.1 Check-in táctil de campo: marcar PRESENTE
    $_POST = [
        'csrf_token'        => $csrfTokenValido,
        'asistencia_id'     => $pax['asistencia_id'],
        'estado_asistencia' => 'PRESENTE',
        'notas_abordaje'    => 'Abordó con boleto físico verificado en muelle',
    ];
    $respCheckin = $operacionCtrl->marcarCheckin($salidaId);
    $dCheckin = json_decode($respCheckin, true);
    afirmar($dCheckin['exito'] === true && $dCheckin['datos']['estado_asistencia'] === 'PRESENTE', "6.1: Check-in táctil marca asistencia como PRESENTE");

    // 6.2 Check-in táctil: marcar NO_SHOW
    $_POST = [
        'csrf_token'        => $csrfTokenValido,
        'asistencia_id'     => $pax['asistencia_id'],
        'estado_asistencia' => 'NO_SHOW',
    ];
    $respCheckinNoShow = $operacionCtrl->marcarCheckin($salidaId);
    $dNoShow = json_decode($respCheckinNoShow, true);
    afirmar($dNoShow['exito'] === true && $dNoShow['datos']['estado_asistencia'] === 'NO_SHOW', "6.2: Check-in táctil marca asistencia como NO_SHOW");

    // 6.3 Rechazo de estado inventado JUSTIFICADO en check-in
    $_POST = [
        'csrf_token'        => $csrfTokenValido,
        'asistencia_id'     => $pax['asistencia_id'],
        'estado_asistencia' => 'JUSTIFICADO',
    ];
    $respInvalidoCheck = $operacionCtrl->marcarCheckin($salidaId);
    $dInvalidoCheck = json_decode($respInvalidoCheck, true);
    afirmar($dInvalidoCheck['exito'] === false && str_contains($dInvalidoCheck['mensaje'], 'inválido'), "6.3: Rechazo categórico de estado inventado JUSTIFICADO en check-in (422)");

    // Volver a marcar PRESENTE para despachar
    $_POST = [
        'csrf_token'        => $csrfTokenValido,
        'asistencia_id'     => $pax['asistencia_id'],
        'estado_asistencia' => 'PRESENTE',
    ];
    $operacionCtrl->marcarCheckin($salidaId);

    // 6.4 Despacho de salida: PROGRAMADA -> DESPACHADA
    $_POST = [
        'csrf_token'      => $csrfTokenValido,
        'version_bloqueo' => 1,
    ];
    $respDespacho = $operacionCtrl->despachar($salidaId);
    $dDespacho = json_decode($respDespacho, true);
    afirmar($dDespacho['exito'] === true && $dDespacho['datos']['estado'] === 'DESPACHADA', "6.4: Despacho de salida exitoso: transiciona a DESPACHADA");

    // 6.5 Finalización de salida: DESPACHADA -> FINALIZADA
    $_POST = [
        'csrf_token'      => $csrfTokenValido,
        'version_bloqueo' => 2,
    ];
    $respFin = $operacionCtrl->finalizar($salidaId);
    $dFin = json_decode($respFin, true);
    afirmar($dFin['exito'] === true && $dFin['datos']['estado'] === 'FINALIZADA', "6.5: Finalización de salida exitosa: transiciona a FINALIZADA");

    // ==============================================================================
    // BLOQUE 7: BITÁCORA DE INCIDENCIAS OPERATIVAS
    // ==============================================================================
    echo "\n--- BLOQUE 7: BITÁCORA DE INCIDENCIAS OPERATIVAS ---\n";

    // 7.1 Registrar incidencia operativa estructurada
    $_POST = [
        'csrf_token'       => $csrfTokenValido,
        'tipo_incidencia'  => 'CLIMA_FUERZA_MAYOR',
        'descripcion'      => 'Viento fuerte en el trayecto de regreso, se redujo velocidad a 8 nudos',
        'acciones_tomadas' => 'Navegación pegada a la costa de Chucuito',
    ];
    $respInc = $operacionCtrl->registrarIncidencia($salidaId);
    $dInc = json_decode($respInc, true);
    afirmar($dInc['exito'] === true && isset($dInc['datos']['incidencia_id']), "7.1: Registrar incidencia operativa estructurada exitoso");

    // 7.2 Listar incidencias de la salida
    $respListInc = $operacionCtrl->listarIncidencias($salidaId);
    $dListInc = json_decode($respListInc, true);
    afirmar($dListInc['exito'] === true && count($dListInc['datos']['incidencias']) === 1, "7.2: Listar incidencias retorna el reporte registrado");
    afirmar($dListInc['datos']['incidencias'][0]['tipo_incidencia'] === 'CLIMA_FUERZA_MAYOR', "7.3: Tipo de incidencia verificado como CLIMA_FUERZA_MAYOR");

    // ==============================================================================
    // BLOQUE 8: GESTIÓN DE PROVEEDORES ALIADOS Y RECURSOS FÍSICOS
    // ==============================================================================
    echo "\n--- BLOQUE 8: PROVEEDORES Y RECURSOS FÍSICOS ---\n";

    // 8.1 Listar recursos físicos
    $respListRec = $operacionCtrl->listarRecursos();
    $dListRec = json_decode($respListRec, true);
    afirmar($dListRec['exito'] === true && count($dListRec['datos']['recursos']) >= 1, "8.1: Listar recursos físicos retorna la lancha registrada");

    // 8.2 Cambiar estado de recurso físico a EN_MANTENIMIENTO
    $_POST = [
        'csrf_token' => $csrfTokenValido,
        'estado'     => 'EN_MANTENIMIENTO',
        'motivo'     => 'Revisión técnica de motor fuera de borda',
    ];
    $respEstRec = $operacionCtrl->cambiarEstadoRecurso($recursoLanchaId);
    $dEstRec = json_decode($respEstRec, true);
    afirmar($dEstRec['exito'] === true, "8.2: Cambio de estado de recurso físico a EN_MANTENIMIENTO exitoso", $respEstRec);

    // 8.3 Listar proveedores aliados
    $respListProv = $operacionCtrl->listarProveedores();
    $dListProv = json_decode($respListProv, true);
    afirmar($dListProv['exito'] === true && count($dListProv['datos']['proveedores']) >= 1, "8.3: Listar proveedores aliados retorna el aliado registrado");

    // 8.4 Suspender proveedor aliado
    $_POST = [
        'csrf_token' => $csrfTokenValido,
        'motivo'     => 'Incumplimiento de horario de muelle',
    ];
    $respSuspProv = $operacionCtrl->suspenderProveedor($proveedorId);
    $dSuspProv = json_decode($respSuspProv, true);
    afirmar($dSuspProv['exito'] === true, "8.4: Suspender proveedor aliado exitoso");

    // ==============================================================================
    // BLOQUE 9: DESPACHO DE ENTREGAS DE PRODUCTOS FÍSICOS
    // ==============================================================================
    echo "\n--- BLOQUE 9: DESPACHO DE ENTREGAS DE BIENES TANGIBLES ---\n";

    // 9.1 Formalizar Venta A2 (Producto Tangible) genera Entrega en estado PENDIENTE_ENTREGA
    $_POST = ['csrf_token' => $csrfTokenValido];
    $respFormEntrega = $reservaCtrl->formalizarDesdeVenta($ventaA2Id);
    $dFormEnt = json_decode($respFormEntrega, true);
    afirmar($dFormEnt['exito'] === true && isset($dFormEnt['datos']['entrega']['id']), "9.1: Formalizar venta de producto tangible genera orden de Entrega");

    $entregaId = (int) $dFormEnt['datos']['entrega']['id'];
    afirmar($dFormEnt['datos']['entrega']['estado'] === 'PENDIENTE_ENTREGA', "9.2: Orden de entrega inicia en estado PENDIENTE_ENTREGA");

    // 9.3 Listar entregas de la organización
    $respListEnt = $operacionCtrl->listarEntregas();
    $dListEnt = json_decode($respListEnt, true);
    afirmar($dListEnt['exito'] === true && count($dListEnt['datos']['entregas']) >= 1, "9.3: Listar entregas de la organización exitoso");

    // 9.4 Detalle de entrega con ítems desglosados
    $respDetEnt = $operacionCtrl->detalleEntrega($entregaId);
    $dDetEnt = json_decode($respDetEnt, true);
    afirmar($dDetEnt['exito'] === true && count($dDetEnt['datos']['entrega']['items']) === 1, "9.4: Detalle de entrega incluye el ítem con su cantidad");
    afirmar((float) $dDetEnt['datos']['entrega']['items'][0]['cantidad'] === 2.0, "9.5: Cantidad del bien tangible es exactamente 2.00");

    // 9.6 Anti-IDOR en Detalle de Entrega: Tenant B no puede ver la entrega de Tenant A
    $authMiddlewareMock->setContexto($ctxAdminB);
    $respIdorEnt = $operacionCtrl->detalleEntrega($entregaId);
    $dIdorEnt = json_decode($respIdorEnt, true);
    afirmar($dIdorEnt['exito'] === false && str_contains($dIdorEnt['mensaje'], 'no existe en su organización'), "9.6: Anti-IDOR: Tenant B no puede ver la entrega de Tenant A (404)");

    $authMiddlewareMock->setContexto($ctxAdminA);

    // 9.7 Despachar entrega física al cliente
    $_POST = [
        'csrf_token'      => $csrfTokenValido,
        'version_bloqueo' => 1,
    ];
    $respDespEnt = $operacionCtrl->despacharEntrega($entregaId);
    $dDespEnt = json_decode($respDespEnt, true);
    afirmar($dDespEnt['exito'] === true && $dDespEnt['datos']['estado'] === 'ENTREGADO', "9.7: Despachar entrega marca la orden como ENTREGADO");

    // ==============================================================================
    // BLOQUE 10: CONCURRENCIA OPTIMISTA (HTTP 409) Y VALIDACIÓN DE VISTAS ALINA
    // ==============================================================================
    echo "\n--- BLOQUE 10: CONCURRENCIA OPTIMISTA (409) Y VISTAS ALINA ---\n";

    // 10.1 Conflicto HTTP 409 ante versión de bloqueo obsoleta en despacho de entrega
    $_POST = [
        'csrf_token'      => $csrfTokenValido,
        'version_bloqueo' => 1, // Ya fue incrementada a 2
    ];
    $respConflicto = $operacionCtrl->despacharEntrega($entregaId);
    $dConflicto = json_decode($respConflicto, true);
    afirmar($dConflicto['exito'] === false && (str_contains($dConflicto['mensaje'], 'concurrencia') || str_contains($dConflicto['mensaje'], 'modificada') || str_contains($dConflicto['mensaje'], 'PENDIENTE')), "10.1: Concurrencia o doble despacho rechaza operación inválida con mensaje claro");

    // 10.2 Renderizado seguro de vistas Alina UI (sin errores PHP ni exceptions)
    $vistaReservas = $reservaCtrl->index();
    afirmar(str_contains($vistaReservas, 'Reservas y Agendamiento') && str_contains($vistaReservas, 'tablaReservas'), "10.2: Vista 'reservas/index' renderizada correctamente con Alina UI");

    $vistaOperaciones = $operacionCtrl->index();
    afirmar(str_contains($vistaOperaciones, 'Operaciones de Campo') && str_contains($vistaOperaciones, 'tablaSalidas'), "10.3: Vista 'operaciones/index' renderizada correctamente con Alina UI");

    $vistaRecursos = $operacionCtrl->vistaRecursos();
    afirmar(str_contains($vistaRecursos, 'Proveedores y Recursos Físicos') && str_contains($vistaRecursos, 'tablaRecursosFisicos'), "10.4: Vista 'operaciones/recursos' renderizada correctamente con Alina UI");

    $vistaEntregas = $operacionCtrl->vistaEntregas();
    afirmar(str_contains($vistaEntregas, 'Despacho de Entregas') && str_contains($vistaEntregas, 'tablaEntregas'), "10.5: Vista 'operaciones/entregas' renderizada correctamente con Alina UI");

    // 10.6 Integridad inmaculada de admin-dashboard/**
    $outputGit = [];
    exec('git status --porcelain admin-dashboard', $outputGit);
    afirmar(empty($outputGit), "10.6: Integridad de admin-dashboard: 100% INTACTO (0 modificaciones)");

} catch (Throwable $e) {
    afirmar(false, "Excepción no capturada en suite F2.6E: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
} finally {
    // Revertir deterministamente la transacción para dejar la base de datos libre de fixtures
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n[INFO] Transacción de pruebas F2.6E revertida con ROLLBACK determinista.\n";
    }
}

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.6E: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
exit(0);
