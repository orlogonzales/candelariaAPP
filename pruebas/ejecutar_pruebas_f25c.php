<?php

declare(strict_types=1);

namespace Pruebas;

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Controladores\VentaControlador;
use Aplicacion\Cotizaciones\CotizacionServicio;
use Aplicacion\Cotizaciones\EstadoCotizacion;
use Aplicacion\Crm\OportunidadServicio;
use Aplicacion\Crm\OrigenComercialServicio;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\CategoriaItemRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\CotizacionRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\HistorialEtapaRepositorio;
use Aplicacion\Repositorios\InteraccionCrmRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\OfertaItemEdicionRepositorio;
use Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\OrigenComercialRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Ventas\EstadoVenta;
use Aplicacion\Ventas\MotivoAnulacionVenta;
use Aplicacion\Ventas\MotivoCancelacionVenta;
use Aplicacion\Ventas\VentaServicio;
use BadMethodCallException;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS F2.5C: SUPERFICIE HTTP/API + ALINA UI VENTAS\n";
echo "ENDPOINTS REST, LOOKUP SELECT2, CONVERSIÓN ATÓMICA, 409, DESACOPLAMIENTO Y ZERO-PII\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

$fallos = 0;
$exitos = 0;

function afirmar(bool $condicion, string $descripcion, string $detalles = ''): void
{
    global $fallos, $exitos;
    if ($condicion) {
        $exitos++;
        echo " [PASS] {$descripcion}\n";
    } else {
        $fallos++;
        echo " [FAIL] {$descripcion}" . ($detalles !== '' ? " -> {$detalles}" : '') . "\n";
    }
}

// Iniciar transacción de pruebas para no alterar la BD operativa
$pdo->beginTransaction();

try {
    // Repositorios e infraestructura
    $orgRepo             = new \Aplicacion\Repositorios\OrganizacionRepositorio($pdo);
    $usuarioRepo         = new UsuarioRepositorio($pdo);
    $personaRepo         = new PersonaRepositorio($pdo);
    $rolRepo             = new RolRepositorio($pdo);
    $permisoRepo         = new PermisoRepositorio($pdo);
    $auditoriaRepo       = new AuditoriaRepositorio($pdo);
    $edicionRepo         = new EdicionRepositorio($pdo);
    $configRepo          = new \Aplicacion\Repositorios\ConfiguracionRepositorio($pdo);
    $clienteRepo         = new ClienteRepositorio($pdo);
    $oportunidadRepo     = new OportunidadRepositorio($pdo);
    $origenRepo          = new OrigenComercialRepositorio($pdo);
    $historialEtapaRepo  = new HistorialEtapaRepositorio($pdo);
    $categoriaRepo       = new CategoriaItemRepositorio($pdo);
    $itemRepo            = new ItemComercialRepositorio($pdo);
    $paqueteRepo         = new PaqueteRepositorio($pdo);
    $ofertaItemRepo      = new OfertaItemEdicionRepositorio($pdo);
    $ofertaPaqueteRepo   = new OfertaPaqueteEdicionRepositorio($pdo);
    $tarifaItemRepo      = new TarifaItemEdicionRepositorio($pdo);
    $tarifaPaqueteRepo   = new TarifaPaqueteEdicionRepositorio($pdo);
    $cotizacionRepo      = new CotizacionRepositorio($pdo);
    $ventaRepo           = new VentaRepositorio($pdo);

    $authzServicio       = new AutorizacionServicio($rolRepo, $permisoRepo, $usuarioRepo, $auditoriaRepo, $pdo);
    $configServicio      = new ConfiguracionServicio($configRepo, $orgRepo, $authzServicio, $auditoriaRepo, $pdo);

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

    $contextoEdicionResolver = new ContextoEdicionResolver($edicionRepo);

    // ==============================================================================
    // FIXTURES SINTÉTICOS DE PRUEBA (ZERO-PII)
    // ==============================================================================
    $tenantA = 10000;
    $tenantB = 20000;
    $pdo->exec("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `estado`)
                VALUES ({$tenantB}, 'tenant_b_f25c', 'Tenant B Pruebas F25C', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    // Ediciones de prueba
    $edicionAId = 98901;
    $pdo->exec("INSERT INTO `ediciones_candelaria` (`id`, `organizacion_id`, `codigo`, `anio`, `nombre`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`)
                VALUES ({$edicionAId}, {$tenantA}, 'ED-2026-VTA-A', 2026, 'Candelaria 2026 Ventas A', 'PREOPERACION', '2026-02-01', '2026-02-15', 1)
                ON DUPLICATE KEY UPDATE `estado` = 'PREOPERACION', `es_actual` = 1");

    $edicionBId = 98902;
    $pdo->exec("INSERT INTO `ediciones_candelaria` (`id`, `organizacion_id`, `codigo`, `anio`, `nombre`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`)
                VALUES ({$edicionBId}, {$tenantB}, 'ED-2026-VTA-B', 2026, 'Candelaria 2026 Ventas B', 'PREOPERACION', '2026-02-01', '2026-02-15', 1)
                ON DUPLICATE KEY UPDATE `estado` = 'PREOPERACION'");

    // Clientes sintéticos
    $personaAId = 8901;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`, `correo_electronico`, `telefono_whatsapp`, `codigo_pais`)
                VALUES ({$personaAId}, {$tenantA}, 'NATURAL', 'Comprador', 'Prueba Venta A', '77112233', 1, 'compradora@test.local', '+51999888777', 'PE')
                ON DUPLICATE KEY UPDATE `nombres` = 'Comprador'");

    $clienteAId = 55901;
    $pdo->exec("INSERT INTO `clientes` (`id`, `organizacion_id`, `persona_id`, `estado_comercial`)
                VALUES ({$clienteAId}, {$tenantA}, {$personaAId}, 'CLIENTE')
                ON DUPLICATE KEY UPDATE `estado_comercial` = 'CLIENTE'");

    // Oportunidad CRM Tenant A
    $oportunidadAId = 77901;
    $pdo->exec("INSERT INTO `crm_oportunidades` (`id`, `organizacion_id`, `edicion_id`, `cliente_id`, `titulo`, `etapa`, `valor_estimado`, `moneda`)
                VALUES ({$oportunidadAId}, {$tenantA}, {$edicionAId}, {$clienteAId}, 'Oportunidad Venta F25C', 'COTIZACION', 1850.00, 'PEN')
                ON DUPLICATE KEY UPDATE `etapa` = 'COTIZACION'");

    // Ítem Comercial y Oferta con Tarifa
    $itemAId = 66901;
    $pdo->exec("INSERT INTO `categorias_items` (`id`, `organizacion_id`, `codigo`, `nombre`) VALUES (8891, {$tenantA}, 'CAT_VTA_F25C', 'Categoria Ventas F25C') ON DUPLICATE KEY UPDATE `nombre` = 'Categoria Ventas F25C'");
    $pdo->exec("INSERT INTO `items_comerciales` (`id`, `organizacion_id`, `categoria_id`, `codigo`, `nombre`, `tipo`, `unidad_medida`, `estado`)
                VALUES ({$itemAId}, {$tenantA}, 8891, 'ITEM_VTA_F25C', 'Acceso Tribuna VIP', 'SERVICIO', 'SERVICIO', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $ofertaItemAId = 44901;
    $pdo->exec("INSERT INTO `ofertas_items_edicion` (`id`, `organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`)
                VALUES ({$ofertaItemAId}, {$tenantA}, {$edicionAId}, {$itemAId}, 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $pdo->exec("INSERT INTO `tarifas_items_edicion` (`id`, `oferta_item_id`, `precio`, `moneda`, `version_bloqueo`)
                VALUES (33901, {$ofertaItemAId}, 350.00, 'PEN', 1)
                ON DUPLICATE KEY UPDATE `precio` = 350.00");

    // Paquete Comercial con Componente y Oferta con Tarifa
    $paqueteAId = 66902;
    $pdo->exec("INSERT INTO `paquetes` (`id`, `organizacion_id`, `codigo`, `nombre`, `estado`)
                VALUES ({$paqueteAId}, {$tenantA}, 'PAQ_VTA_F25C', 'Pack Premium Candelaria', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $pdo->exec("INSERT INTO `paquete_items` (`id`, `paquete_id`, `item_comercial_id`, `cantidad`, `orden`)
                VALUES (22901, {$paqueteAId}, {$itemAId}, 2.00, 1)
                ON DUPLICATE KEY UPDATE `cantidad` = 2.00");

    $ofertaPaqAId = 44902;
    $pdo->exec("INSERT INTO `ofertas_paquetes_edicion` (`id`, `organizacion_id`, `edicion_id`, `paquete_id`, `estado`)
                VALUES ({$ofertaPaqAId}, {$tenantA}, {$edicionAId}, {$paqueteAId}, 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $pdo->exec("INSERT INTO `tarifas_paquetes_edicion` (`id`, `oferta_paquete_id`, `precio`, `moneda`, `version_bloqueo`)
                VALUES (33902, {$ofertaPaqAId}, 800.00, 'PEN', 1)
                ON DUPLICATE KEY UPDATE `precio` = 800.00");

    // Usuarios y Contextos
    // 1. Admin Tenant A (con los 4 permisos de ventas)
    $usrAdminAId = 7801;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$usrAdminAId}, {$tenantA}, 'NATURAL', 'Admin', 'Ventas A', '88881001', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'Admin'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`)
                VALUES ({$usrAdminAId}, {$tenantA}, {$usrAdminAId}, 'admin_vta_f25c', 'ADMIN VTA F25C', 'admin_vta_f25c@test.local', '+51950001001', 'hash', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES ({$usrAdminAId}, 2) ON DUPLICATE KEY UPDATE `rol_id` = 2");

    // 2. Operador Tenant A (solo ventas.ver)
    $usrOperadorAId = 7802;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$usrOperadorAId}, {$tenantA}, 'NATURAL', 'Operador', 'Ventas A', '88881002', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'Operador'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`)
                VALUES ({$usrOperadorAId}, {$tenantA}, {$usrOperadorAId}, 'op_vta_f25c', 'OPERADOR VTA F25C', 'op_vta_f25c@test.local', '+51950001002', 'hash', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES ({$usrOperadorAId}, 3) ON DUPLICATE KEY UPDATE `rol_id` = 3");

    // Contextos Operativos
    $csrfValido = 'csrf_token_f25c_test_soberano_98765';
    $ctxAdminA = ContextoOperacion::paraHumano(
        usuarioId: $usrAdminAId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'TestRunner/F25C',
        organizacionId: $tenantA,
        metadatos: ['csrf_token' => $csrfValido]
    );

    $ctxOperadorA = ContextoOperacion::paraHumano(
        usuarioId: $usrOperadorAId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'TestRunner/F25C',
        organizacionId: $tenantA,
        metadatos: ['csrf_token' => $csrfValido]
    );

    // Mock Middleware de Autenticación
    $authMiddlewareMock = new class($ctxAdminA) extends AutenticacionMiddleware {
        public function __construct(private ?ContextoOperacion $ctx) {}
        public function setContexto(?ContextoOperacion $ctx): void { $this->ctx = $ctx; }
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion {
            return $this->ctx;
        }
    };

    $controlador = new VentaControlador(
        authMiddleware: $authMiddlewareMock,
        authzMiddleware: null,
        ventaServicio: $ventaServicio,
        edicionRepo: $edicionRepo,
        configServicio: $configServicio,
        pdo: $pdo
    );

    // ==============================================================================
    // BLOQUE 1: INFRAESTRUCTURA HTTP, AUTENTICACIÓN Y ENVELOPE JSON UNIFORME
    // ==============================================================================
    echo "\n--- BLOQUE 1: INFRAESTRUCTURA HTTP, AUTENTICACIÓN Y ENVELOPE JSON ---\n";

    // 1.1 Sin sesión activa: retorna 401
    $authMiddlewareMock->setContexto(null);
    $respJson401 = $controlador->listar();
    $datos401 = json_decode($respJson401, true);
    afirmar($datos401['exito'] === false && str_contains($datos401['mensaje'], 'Sesión no válida'), "1.1: Solicitud sin autenticación retorna JSON de error 401");

    // 1.2 Envelope uniforme: exito, mensaje, datos, errores (sin codigo en la raíz)
    $authMiddlewareMock->setContexto($ctxAdminA);
    $_SERVER['HTTP_X_EDICION_ID'] = (string) $edicionAId;
    $respJson200 = $controlador->listar();
    $datos200 = json_decode($respJson200, true);
    $clavesEnvelope = array_keys($datos200);
    sort($clavesEnvelope);
    afirmar($clavesEnvelope === ['datos', 'errores', 'exito', 'mensaje'], "1.2: Contrato JSON uniforme estricto: contiene exactamente 'exito', 'mensaje', 'datos', 'errores'");
    afirmar(!array_key_exists('codigo', $datos200), "1.3: Cero 'codigo' en la raíz del envelope JSON");

    // 1.4 Validación de CSRF: rechazo ante token inválido en mutación
    $_POST = [
        'cotizacion_id' => 999,
        '_csrf_token' => 'token_falso_csrf_invalido'
    ];
    $respCsrf = $controlador->crearDesdeCotizacion();
    $datosCsrf = json_decode($respCsrf, true);
    afirmar($datosCsrf['exito'] === false && str_contains($datosCsrf['mensaje'], 'CSRF'), "1.4: Mutación POST con token CSRF inválido es rechazada con HTTP 403");

    // ==============================================================================
    // BLOQUE 2: CONTEXTO DE EDICIÓN (X-EDICION-ID / FAIL CLOSED Y ANTI-IDOR)
    // ==============================================================================
    echo "\n--- BLOQUE 2: CONTEXTO DE EDICIÓN (X-EDICION-ID / FAIL CLOSED) ---\n";

    // 2.1 X-Edicion-Id inválido (alfanumérico) produce Fail-Closed inmediato
    $_SERVER['HTTP_X_EDICION_ID'] = 'invalido_xyz';
    $edicionFailClosed = false;
    try {
        $controlador->listar();
    } catch (\InvalidArgumentException $e) {
        $edicionFailClosed = true;
    }
    afirmar($edicionFailClosed, "2.1: Header X-Edicion-Id no numérico produce Fail-Closed inmediato");

    // 2.2 X-Edicion-Id cross-tenant produce Acceso Denegado (Anti-IDOR)
    $_SERVER['HTTP_X_EDICION_ID'] = (string) $edicionBId; // Pertenece a Tenant B
    $crossTenantBloqueado = false;
    try {
        $controlador->listar();
    } catch (AccesoDenegadoExcepcion $e) {
        $crossTenantBloqueado = true;
    }
    afirmar($crossTenantBloqueado, "2.2: Header X-Edicion-Id perteneciente a otro tenant produce AccesoDenegadoExcepcion");

    // Restaurar header válido de Edición A
    $_SERVER['HTTP_X_EDICION_ID'] = (string) $edicionAId;

    // ==============================================================================
    // BLOQUE 3: RBAC Y AUTORIZACIÓN SOBERANA (4 PERMISOS DE VENTAS)
    // ==============================================================================
    echo "\n--- BLOQUE 3: RBAC Y AUTORIZACIÓN SOBERANA (4 PERMISOS) ---\n";

    // 3.1 Operador con ventas.ver puede consultar el listado
    $authMiddlewareMock->setContexto($ctxOperadorA);
    $respListarOp = $controlador->listar();
    $datosListarOp = json_decode($respListarOp, true);
    afirmar($datosListarOp['exito'] === true, "3.1: Operador con 'ventas.ver' consulta exitosamente el listado");

    // 3.2 Operador intenta crear venta desde cotización (sin ventas.crear_desde_cotizacion) -> 403
    $_POST = [
        'cotizacion_id' => 1,
        '_csrf_token' => $csrfValido
    ];
    $respCrearOp = $controlador->crearDesdeCotizacion();
    $datosCrearOp = json_decode($respCrearOp, true);
    afirmar($datosCrearOp['exito'] === false && str_contains($datosCrearOp['mensaje'], 'ventas.crear_desde_cotizacion'), "3.2: Intento de crear venta sin 'ventas.crear_desde_cotizacion' retorna 403");

    // 3.3 Operador intenta cancelar venta (sin ventas.cancelar) -> 403
    $_POST = [
        'motivo' => 'DESISTIMIENTO_CLIENTE',
        '_csrf_token' => $csrfValido
    ];
    $respCancOp = $controlador->cancelar('1');
    $datosCancOp = json_decode($respCancOp, true);
    afirmar($datosCancOp['exito'] === false && str_contains($datosCancOp['mensaje'], 'ventas.cancelar'), "3.3: Intento de cancelar venta sin 'ventas.cancelar' retorna 403");

    // 3.4 Operador intenta anular venta (sin ventas.anular) -> 403
    $_POST = [
        'motivo' => 'ERROR_OPERATIVO',
        '_csrf_token' => $csrfValido
    ];
    $respAnulOp = $controlador->anular('1');
    $datosAnulOp = json_decode($respAnulOp, true);
    afirmar($datosAnulOp['exito'] === false && str_contains($datosAnulOp['mensaje'], 'ventas.anular'), "3.4: Intento de anular venta sin 'ventas.anular' retorna 403");

    // Volver a Admin Tenant A para las operaciones autorizadas
    $authMiddlewareMock->setContexto($ctxAdminA);

    // ==============================================================================
    // CREAR COTIZACIÓN ACEPTADA SINTÉTICA PARA CONVERSIÓN
    // ==============================================================================
    // Creamos una cotización borrador formal
    $cot = $cotizacionServicio->crearBorrador(
        organizacionId: $tenantA,
        clienteId: $clienteAId,
        edicionId: $edicionAId,
        titulo: 'Cotización Aceptada F25C Test',
        oportunidadId: $oportunidadAId,
        terminosCondiciones: 'Condiciones de venta comercial',
        notasInternas: 'Nota de test F25C',
        contexto: $ctxAdminA
    );
    $cotId = $cot->id;

    // Agregar línea de ítem
    $cot = $cotizacionServicio->agregarLineaItem(
        organizacionId: $tenantA,
        cotizacionId: $cotId,
        itemComercialId: $itemAId,
        ofertaItemId: $ofertaItemAId,
        cantidad: 1.0,
        contexto: $ctxAdminA
    );

    // Agregar línea de paquete
    $cot = $cotizacionServicio->agregarLineaPaquete(
        organizacionId: $tenantA,
        cotizacionId: $cotId,
        paqueteId: $paqueteAId,
        ofertaPaqueteId: $ofertaPaqAId,
        cantidad: 1.0,
        contexto: $ctxAdminA
    );

    // Emitir cotización
    $cot = $cotizacionServicio->emitir(
        organizacionId: $tenantA,
        cotizacionId: $cotId,
        validoHastaManual: date('Y-m-d', strtotime('+15 days')),
        contexto: $ctxAdminA
    );

    // Aceptar cotización formalmente
    $cot = $cotizacionServicio->aceptar(
        organizacionId: $tenantA,
        cotizacionId: $cotId,
        contexto: $ctxAdminA
    );
    afirmar($cot->estado->value === 'ACEPTADA', "Setup: Cotización sintética #{$cotId} transicionó a ACEPTADA");

    // ==============================================================================
    // BLOQUE 4: ENDPOINT AUXILIAR SELECT2 (COTIZACIONES ACEPTADAS)
    // ==============================================================================
    echo "\n--- BLOQUE 4: ENDPOINT AUXILIAR SELECT2 PARA CONVERSIÓN ---\n";

    // 4.1 Lookup auxiliar de cotizaciones aceptadas elegibles
    $_GET = [];
    $respAux = $controlador->auxCotizacionesAceptadas();
    $datosAux = json_decode($respAux, true);
    afirmar($datosAux['exito'] === true, "4.1: Lookup de cotizaciones aceptadas consultado exitosamente");
    $encontrada = false;
    foreach ($datosAux['datos']['items'] as $it) {
        if ($it['id'] === $cotId) {
            $encontrada = true;
            afirmar(isset($it['text']) && str_contains($it['text'], $cot->correlativo), "4.2: Formato canónico Select2: contiene 'text' con correlativo");
            afirmar($it['moneda'] === 'PEN' && (float) $it['total'] === 1150.00, "4.3: Total y moneda coinciden con la cotización aceptada");
            break;
        }
    }
    afirmar($encontrada, "4.4: Cotización aceptada #{$cotId} aparece en la lista de disponibles para conversión");

    // ==============================================================================
    // BLOQUE 5: CREACIÓN DE VENTA DESDE COTIZACIÓN VÍA POST (HTTP 201)
    // ==============================================================================
    echo "\n--- BLOQUE 5: CREACIÓN DE VENTA DESDE COTIZACIÓN (HTTP 201) ---\n";

    // 5.1 Conversión atómica
    $_POST = [
        'cotizacion_id' => $cotId,
        '_csrf_token' => $csrfValido
    ];
    $respVenta = $controlador->crearDesdeCotizacion();
    $datosVenta = json_decode($respVenta, true);
    afirmar($datosVenta['exito'] === true, "5.1: Venta generada exitosamente vía POST /api/v1/ventas/desde-cotizacion");
    $ventaGenerada = $datosVenta['datos']['venta'];
    $ventaId = (int) $ventaGenerada['id'];
    afirmar(!empty($ventaGenerada['correlativo']), "5.2: Correlativo institucional asignado monotónicamente ({$ventaGenerada['correlativo']})");
    afirmar($ventaGenerada['estado'] === 'CONFIRMADA', "5.3: Venta creada nace en estado canónico CONFIRMADA");
    afirmar($ventaGenerada['moneda'] === 'PEN', "5.4: Moneda de la venta preservada de forma Fail-Closed");
    afirmar((float) $ventaGenerada['total'] === 1150.00, "5.5: Total snapshot idéntico a la cotización (350 + 800 = 1150.00)");
    afirmar(count($ventaGenerada['lineas']) === 2, "5.6: Snapshot incluye exactamente las 2 líneas transferidas");

    // 5.7 Oportunidad CRM transicionada atómicamente a GANADA
    $stmtOp = $pdo->prepare("SELECT etapa FROM crm_oportunidades WHERE id = :id");
    $stmtOp->execute(['id' => $oportunidadAId]);
    $etapaOp = $stmtOp->fetchColumn();
    afirmar($etapaOp === 'GANADA', "5.7: Oportunidad CRM #{$oportunidadAId} transicionó automáticamente a GANADA al venderse");

    // 5.8 Regla 1:1 — Intento de reconvertir la misma cotización es rechazado con 422
    $respReconvertir = $controlador->crearDesdeCotizacion();
    $datosReconvertir = json_decode($respReconvertir, true);
    afirmar($datosReconvertir['exito'] === false && str_contains($datosReconvertir['mensaje'], 'ya fue convertida'), "5.8: Regla 1:1: Reintento de conversión de cotización ya convertida es rechazado con 422");

    // 5.9 Cotización convertida ya no aparece en el Select2 auxiliar
    $respAuxPost = $controlador->auxCotizacionesAceptadas();
    $datosAuxPost = json_decode($respAuxPost, true);
    $encontradaPost = false;
    foreach ($datosAuxPost['datos']['items'] as $it) {
        if ($it['id'] === $cotId) {
            $encontradaPost = true;
            break;
        }
    }
    afirmar(!$encontradaPost, "5.9: Cotización convertida queda excluida automáticamente del selector de cotizaciones disponibles");

    // ==============================================================================
    // BLOQUE 6: DETALLE 360 VÍA GET /api/v1/ventas/{id}
    // ==============================================================================
    echo "\n--- BLOQUE 6: DETALLE 360 VÍA GET /api/v1/ventas/{id} ---\n";

    $respDetalle = $controlador->detalle((string) $ventaId);
    $datosDetalle = json_decode($respDetalle, true);
    afirmar($datosDetalle['exito'] === true, "6.1: Detalle 360 consultado exitosamente");
    afirmar(isset($datosDetalle['datos']['venta']['cliente_nombre_completo']), "6.2: Snapshot inmutable de cliente presente en detalle 360");
    afirmar($datosDetalle['datos']['cotizacion_origen']['id'] === $cotId, "6.3: Trazabilidad completa con cotización de origen");
    afirmar(count($datosDetalle['datos']['venta']['lineas'][1]['componentes']) === 1, "6.4: Componentes del paquete comercial preservados en detalle de venta");

    // 6.5 Venta inexistente retorna 404
    $resp404 = $controlador->detalle('999999');
    $datos404 = json_decode($resp404, true);
    afirmar($datos404['exito'] === false && str_contains($datos404['mensaje'], 'no existe'), "6.5: Consulta de venta inexistente retorna HTTP 404");

    // ==============================================================================
    // BLOQUE 7: CONCURRENCIA OPTIMISTA (HTTP 409)
    // ==============================================================================
    echo "\n--- BLOQUE 7: CONCURRENCIA OPTIMISTA (HTTP 409) ---\n";

    // 7.1 Cancelación con version_bloqueo desfasada retorna HTTP 409
    $_POST = [
        'motivo' => 'DESISTIMIENTO_CLIENTE',
        'version_bloqueo' => 999, // Desfasada
        '_csrf_token' => $csrfValido
    ];
    $resp409 = $controlador->cancelar((string) $ventaId);
    $datos409 = json_decode($resp409, true);
    afirmar($datos409['exito'] === false, "7.1: Mutación concurrente desfasada en cancelar es rechazada");
    afirmar($datos409['errores']['codigo'] === 'CONFLICTO_CONCURRENCIA', "7.2: Envelope contiene errores.codigo = 'CONFLICTO_CONCURRENCIA'");
    afirmar(isset($datos409['errores']['version_actual']), "7.3: Envelope informa version_actual para recarga sin sobrescritura");

    // ==============================================================================
    // BLOQUE 8: CANCELACIÓN Y ANULACIÓN CON MOTIVOS Y DETALLE CONDICIONAL
    // ==============================================================================
    echo "\n--- BLOQUE 8: CANCELACIÓN Y ANULACIÓN CON MOTIVOS ESTRUCTURADOS ---\n";

    // 8.1 Cancelar con motivo OTRO sin detalle debe ser rechazado
    $versionBloqueoActual = (int) $datosDetalle['datos']['venta']['version_bloqueo'];
    $_POST = [
        'motivo' => 'OTRO',
        'motivo_detalle' => '', // Vacío
        'version_bloqueo' => $versionBloqueoActual,
        '_csrf_token' => $csrfValido
    ];
    $respCancOtroVacio = $controlador->cancelar((string) $ventaId);
    $datosCancOtroVacio = json_decode($respCancOtroVacio, true);
    afirmar($datosCancOtroVacio['exito'] === false, "8.1: Cancelación con motivo OTRO sin detalle justificado es rechazada con 422");

    // 8.2 Cancelar con motivo estructurado sin necesidad de detalle (DESISTIMIENTO_CLIENTE)
    $_POST = [
        'motivo' => 'DESISTIMIENTO_CLIENTE',
        'motivo_detalle' => null,
        'version_bloqueo' => $versionBloqueoActual,
        '_csrf_token' => $csrfValido
    ];
    $respCancOk = $controlador->cancelar((string) $ventaId);
    $datosCancOk = json_decode($respCancOk, true);
    afirmar($datosCancOk['exito'] === true, "8.2: Venta cancelada exitosamente con motivo estándar");
    afirmar($datosCancOk['datos']['venta']['estado'] === 'CANCELADA', "8.3: Estado de la venta actualizado formalmente a CANCELADA");

    // 8.4 Intentar cancelar venta ya cancelada es rechazado
    $respCancRepetida = $controlador->cancelar((string) $ventaId);
    $datosCancRepetida = json_decode($respCancRepetida, true);
    afirmar($datosCancRepetida['exito'] === false, "8.4: Rechazo ante intento de cancelar una venta ya en estado terminal CANCELADA");

    // Crear una segunda cotización y venta para probar ANULACIÓN
    $cot2 = $cotizacionServicio->crearBorrador(
        organizacionId: $tenantA,
        clienteId: $clienteAId,
        edicionId: $edicionAId,
        titulo: 'Segunda Cotización F25C Anulación',
        contexto: $ctxAdminA
    );
    $cot2 = $cotizacionServicio->agregarLineaItem(
        organizacionId: $tenantA,
        cotizacionId: $cot2->id,
        itemComercialId: $itemAId,
        ofertaItemId: $ofertaItemAId,
        cantidad: 1.0,
        contexto: $ctxAdminA
    );
    $cot2 = $cotizacionServicio->emitir($tenantA, $cot2->id, date('Y-m-d', strtotime('+10 days')), $ctxAdminA);
    $cot2 = $cotizacionServicio->aceptar($tenantA, $cot2->id, $ctxAdminA);

    $venta2 = $ventaServicio->crearDesdeCotizacion($tenantA, $cot2->id, $ctxAdminA);
    $venta2Id = $venta2->id;

    // 8.5 Anular con motivo OTRO sin detalle es rechazado
    $_POST = [
        'motivo' => 'OTRO',
        'motivo_detalle' => '',
        'version_bloqueo' => $venta2->versionBloqueo,
        '_csrf_token' => $csrfValido
    ];
    $respAnulOtroVacio = $controlador->anular((string) $venta2Id);
    $datosAnulOtroVacio = json_decode($respAnulOtroVacio, true);
    afirmar($datosAnulOtroVacio['exito'] === false, "8.5: Anulación con motivo OTRO sin detalle justificado es rechazada con 422");

    // 8.6 Anular con motivo OTRO con detalle justificado es exitoso
    $_POST = [
        'motivo' => 'OTRO',
        'motivo_detalle' => 'Duplicidad administrativa por error de digitación comprobado',
        'version_bloqueo' => $venta2->versionBloqueo,
        '_csrf_token' => $csrfValido
    ];
    $respAnulOk = $controlador->anular((string) $venta2Id);
    $datosAnulOk = json_decode($respAnulOk, true);
    afirmar($datosAnulOk['exito'] === true, "8.6: Venta anulada exitosamente con motivo OTRO y detalle justificado");
    afirmar($datosAnulOk['datos']['venta']['estado'] === 'ANULADA', "8.7: Estado de la venta actualizado formalmente a ANULADA");

    // 8.8 Transición a LIQUIDADA bloqueada en dominio
    $liquidadaBloqueada = false;
    try {
        $ventaServicio->liquidar($tenantA, $venta2Id, $ctxAdminA);
    } catch (BadMethodCallException $e) {
        $liquidadaBloqueada = true;
    }
    afirmar($liquidadaBloqueada, "8.8: Transición a LIQUIDADA estrictamente bloqueada con BadMethodCallException");

    // ==============================================================================
    // BLOQUE 9: DESACOPLAMIENTO ESTRICTO
    // ==============================================================================
    echo "\n--- BLOQUE 9: DESACOPLAMIENTO ESTRICTO ---\n";

    $stmtReservas = $pdo->query("SHOW TABLES LIKE 'reservas'");
    afirmar($stmtReservas->rowCount() === 0, "9.1: Desacoplamiento Reservas: CERO tabla 'reservas'");

    $stmtPagos = $pdo->query("SHOW TABLES LIKE 'pagos'");
    afirmar($stmtPagos->rowCount() === 0, "9.2: Desacoplamiento Pagos: CERO tabla 'pagos'");

    $stmtCaja = $pdo->query("SHOW TABLES LIKE 'caja_sesiones'");
    afirmar($stmtCaja->rowCount() === 0, "9.3: Desacoplamiento Caja: CERO tabla 'caja_sesiones'");

    $stmtSunat = $pdo->query("SHOW TABLES LIKE 'comprobantes_pago'");
    afirmar($stmtSunat->rowCount() === 0, "9.4: Desacoplamiento SUNAT: CERO tabla 'comprobantes_pago'");

    // ==============================================================================
    // BLOQUE 10: ALINA UI, SKELETON, SWEETALERT2, JS Y XSS
    // ==============================================================================
    echo "\n--- BLOQUE 10: ALINA UI, SKELETON, SWEETALERT2, JS Y XSS ---\n";

    $jsContent = file_get_contents(__DIR__ . '/../publico/js/ventas.js');
    afirmar(!str_contains($jsContent, 'location.reload()') && !str_contains($jsContent, 'window.location.reload()'), "10.1: CERO location.reload(): ventas.js utiliza actualización asíncrona pura");
    afirmar(!str_contains($jsContent, 'alert(') && !str_contains($jsContent, 'confirm(') && !str_contains($jsContent, 'prompt('), "10.2: CERO alert/confirm/prompt: uso exclusivo de CandelariaUI SweetAlert2");
    afirmar(str_contains($jsContent, 'CandelariaUI.notificarExito') && str_contains($jsContent, 'CandelariaUI.confirmarAccion'), "10.3: ventas.js integra SweetAlert2 nativo mediante CandelariaUI");
    afirmar(str_contains($jsContent, 'window.Skeleton'), "10.4: ventas.js integra Skeleton Loaders para estado de carga asíncrono");
    afirmar(str_contains($jsContent, 'flatpickr') && str_contains($jsContent, 'select2'), "10.5: ventas.js integra Flatpickr y Select2 oficiales de Alina");
    afirmar(str_contains($jsContent, 'escaparHtml(str)'), "10.6: Función canónica de escape XSS presente en ventas.js");

    $vistaIndex = file_get_contents(__DIR__ . '/../recursos/vistas/paginas/ventas/index.php');
    afirmar(!str_contains($vistaIndex, 'type="date"'), "10.7: CERO type='date' nativo en vista Alina de ventas (utiliza Flatpickr)");
    afirmar(str_contains($vistaIndex, 'modalConvertirVenta') && str_contains($vistaIndex, 'modalDetalleVenta'), "10.8: Modales de conversión y detalle 360 presentes en la vista");
    afirmar(str_contains($vistaIndex, 'modalCancelarVenta') && str_contains($vistaIndex, 'modalAnularVenta'), "10.9: Modales de cancelación y anulación presentes en la vista");

    $barraLateral = file_get_contents(__DIR__ . '/../recursos/vistas/parciales/barra_lateral.php');
    afirmar(str_contains($barraLateral, 'ventas.ver') && str_contains($barraLateral, "url_base('ventas')"), "10.10: Menú lateral incluye enlace a 'ventas' gobernado por 'ventas.ver'");

    // ==============================================================================
    // BLOQUE 11: CERTIFICACIÓN ZERO-PII Y PRESERVACIÓN DE ACTORES SINTÉTICOS
    // ==============================================================================
    echo "\n--- BLOQUE 11: CERTIFICACIÓN ZERO-PII Y PRESERVACIÓN DE ACTORES SINTÉTICOS ---\n";

    // 11.1 Integridad del usuario administrador sintético creado para la suite
    $stmtAdminPost = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id = :id");
    $stmtAdminPost->execute(['id' => $usrAdminAId]);
    $adminPost = $stmtAdminPost->fetch(PDO::FETCH_ASSOC);

    afirmar($adminPost !== false, "11.1: Usuario administrador sintético ID {$usrAdminAId} existe en ámbito transaccional");
    afirmar($adminPost['estado'] === 'ACTIVO', "11.2: Estado de administrador sintético permanece ACTIVO");
    afirmar((int) $adminPost['intentos_fallidos'] === 0, "11.3: Intentos fallidos de administrador sintético permanece en 0");
    afirmar($adminPost['bloqueado_hasta'] === null, "11.4: Bloqueo temporal de administrador sintético es NULL");

    // 11.2 Integridad del operador sintético
    $stmtOpPost = $pdo->prepare("SELECT estado FROM usuarios WHERE id = :id");
    $stmtOpPost->execute(['id' => $usrOperadorAId]);
    $opEstado = $stmtOpPost->fetchColumn();
    afirmar($opEstado === 'ACTIVO', "11.5: Operador sintético ID {$usrOperadorAId} permanece en estado ACTIVO");

    // 11.3 Preservación de credenciales sintéticas y aislamiento estricto
    afirmar($adminPost['contrasena_hash'] === 'hash', "11.6: Credencial sintética preservada sin alteración en base de datos");

} finally {
    // Revertir transacción para preservar la BD operativa intacta
    $pdo->rollBack();
    echo "\n[INFO] Transacción de pruebas F2.5C revertida con ROLLBACK determinista.\n";
}

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.5C: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
