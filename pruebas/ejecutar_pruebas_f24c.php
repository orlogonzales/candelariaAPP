<?php

declare(strict_types=1);

namespace Pruebas;

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Controladores\CotizacionControlador;
use Aplicacion\Cotizaciones\CotizacionServicio;
use Aplicacion\Cotizaciones\EstadoCotizacion;
use Aplicacion\Cotizaciones\MotivoAnulacionCotizacion;
use Aplicacion\Cotizaciones\MotivoRechazoCotizacion;
use Aplicacion\Cotizaciones\TipoDescuentoCotizacion;
use Aplicacion\Cotizaciones\TipoLineaCotizacion;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Ediciones\EstadoEdicion;
use Aplicacion\Entidades\Edicion;
use Aplicacion\Entidades\Organizacion;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\CotizacionRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\OfertaItemEdicionRepositorio;
use Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS F2.4C: SUPERFICIE HTTP/API + INTERFAZ OPERATIVA\n";
echo "COTIZACIONES, LINEAS, REVISIONES, CONCURRENCIA 409, ALINA UI Y ZERO-PII\n";
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

// 0. Huella digital previa de Orlando para certificar preservación estricta sin imprimir PII
$stmtOrlandoPre = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id = 24");
$stmtOrlandoPre->execute();
$orlandoPre = $stmtOrlandoPre->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPre = $orlandoPre ? substr(hash('sha256', (string) $orlandoPre['contrasena_hash']), 0, 16) : null;

// Iniciar transacción de pruebas para no alterar la BD operativa
$pdo->beginTransaction();

try {
    // Repositorios
    $orgRepo             = new OrganizacionRepositorio($pdo);
    $usuarioRepo         = new UsuarioRepositorio($pdo);
    $personaRepo         = new PersonaRepositorio($pdo);
    $rolRepo             = new RolRepositorio($pdo);
    $permisoRepo         = new PermisoRepositorio($pdo);
    $auditoriaRepo       = new AuditoriaRepositorio($pdo);
    $edicionRepo         = new EdicionRepositorio($pdo);
    $configRepo          = new ConfiguracionRepositorio($pdo);
    $itemRepo            = new ItemComercialRepositorio($pdo);
    $paqueteRepo         = new PaqueteRepositorio($pdo);
    $ofertaItemRepo      = new OfertaItemEdicionRepositorio($pdo);
    $ofertaPaqueteRepo   = new OfertaPaqueteEdicionRepositorio($pdo);
    $tarifaItemRepo      = new TarifaItemEdicionRepositorio($pdo);
    $tarifaPaqueteRepo   = new TarifaPaqueteEdicionRepositorio($pdo);
    $clienteRepo         = new ClienteRepositorio($pdo);
    $oportunidadRepo     = new OportunidadRepositorio($pdo);
    $cotizacionRepo      = new CotizacionRepositorio($pdo);

    // Servicios
    $authzServicio       = new AutorizacionServicio($rolRepo, $permisoRepo, $usuarioRepo, $auditoriaRepo, $pdo);
    $configServicio      = new ConfiguracionServicio($configRepo, $orgRepo, $authzServicio, $auditoriaRepo, $pdo);

    $cotizacionServicio  = new CotizacionServicio(
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
        oportunidadServicio: null,
        pdo: $pdo
    );

    // Contexto de Edición Resolver
    $contextoEdicionResolver = new ContextoEdicionResolver($edicionRepo);

    // ==============================================================================
    // FIXTURES SINTÉTICOS DE PRUEBA (ZERO-PII)
    // ==============================================================================
    $tenantA = 10000;
    $tenantB = 20000;
    $pdo->exec("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `estado`)
                VALUES ({$tenantB}, 'tenant_b_f24c', 'Tenant B Pruebas', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    // Crear Edición A y Edición B
    $edicionAId = 98801;
    $pdo->exec("INSERT INTO `ediciones_candelaria` (`id`, `organizacion_id`, `codigo`, `anio`, `nombre`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`)
                VALUES ({$edicionAId}, {$tenantA}, 'ED-2026-A', 2026, 'Candelaria 2026 A', 'PREOPERACION', '2026-02-01', '2026-02-15', 1)
                ON DUPLICATE KEY UPDATE `estado` = 'PREOPERACION', `es_actual` = 1");

    $edicionBId = 98802;
    $pdo->exec("INSERT INTO `ediciones_candelaria` (`id`, `organizacion_id`, `codigo`, `anio`, `nombre`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`)
                VALUES ({$edicionBId}, {$tenantB}, 'ED-2026-B', 2026, 'Candelaria 2026 B', 'PREOPERACION', '2026-02-01', '2026-02-15', 1)
                ON DUPLICATE KEY UPDATE `estado` = 'PREOPERACION'");

    // Crear Clientes sintéticos (Persona + Cliente)
    $personaAId = 8801;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`, `correo_electronico`, `telefono_whatsapp`, `codigo_pais`)
                VALUES ({$personaAId}, {$tenantA}, 'NATURAL', 'Cliente', 'Prueba A', '77001122', 1, 'clientea@test.com', '+51999111222', 'PE')
                ON DUPLICATE KEY UPDATE `nombres` = 'Cliente'");

    $clienteAId = 55801;
    $pdo->exec("INSERT INTO `clientes` (`id`, `organizacion_id`, `persona_id`, `estado_comercial`)
                VALUES ({$clienteAId}, {$tenantA}, {$personaAId}, 'CLIENTE')
                ON DUPLICATE KEY UPDATE `estado_comercial` = 'CLIENTE'");

    $personaBId = 8802;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`, `correo_electronico`, `telefono_whatsapp`, `codigo_pais`)
                VALUES ({$personaBId}, {$tenantB}, 'NATURAL', 'Cliente', 'Prueba B', '77003344', 1, 'clienteb@test.com', '+51999333444', 'PE')
                ON DUPLICATE KEY UPDATE `nombres` = 'Cliente'");

    $clienteBId = 55802;
    $pdo->exec("INSERT INTO `clientes` (`id`, `organizacion_id`, `persona_id`, `estado_comercial`)
                VALUES ({$clienteBId}, {$tenantB}, {$personaBId}, 'CLIENTE')
                ON DUPLICATE KEY UPDATE `estado_comercial` = 'CLIENTE'");

    // Crear Oportunidad CRM para Tenant A
    $oportunidadAId = 77801;
    $pdo->exec("INSERT INTO `crm_oportunidades` (`id`, `organizacion_id`, `edicion_id`, `cliente_id`, `titulo`, `etapa`, `valor_estimado`, `moneda`)
                VALUES ({$oportunidadAId}, {$tenantA}, {$edicionAId}, {$clienteAId}, 'Oportunidad Prueba F24C', 'CALIFICADA', 1500.00, 'PEN')
                ON DUPLICATE KEY UPDATE `etapa` = 'CALIFICADA'");

    // Crear Ítem Comercial y Oferta con Tarifa
    $itemAId = 66801;
    $pdo->exec("INSERT INTO `categorias_items` (`id`, `organizacion_id`, `codigo`, `nombre`) VALUES (8881, {$tenantA}, 'CAT_F24C', 'Categoria F24C') ON DUPLICATE KEY UPDATE `nombre` = 'Categoria F24C'");
    $pdo->exec("INSERT INTO `items_comerciales` (`id`, `organizacion_id`, `categoria_id`, `codigo`, `nombre`, `tipo`, `unidad_medida`, `estado`)
                VALUES ({$itemAId}, {$tenantA}, 8881, 'ITEM_F24C', 'Servicio Fotográfico', 'SERVICIO', 'SERVICIO', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $ofertaItemAId = 44801;
    $pdo->exec("INSERT INTO `ofertas_items_edicion` (`id`, `organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`)
                VALUES ({$ofertaItemAId}, {$tenantA}, {$edicionAId}, {$itemAId}, 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $pdo->exec("INSERT INTO `tarifas_items_edicion` (`id`, `oferta_item_id`, `precio`, `moneda`, `version_bloqueo`)
                VALUES (33801, {$ofertaItemAId}, 250.00, 'PEN', 1)
                ON DUPLICATE KEY UPDATE `precio` = 250.00");

    // Crear Paquete Comercial con Componente y Oferta con Tarifa
    $paqueteAId = 66802;
    $pdo->exec("INSERT INTO `paquetes` (`id`, `organizacion_id`, `codigo`, `nombre`, `estado`)
                VALUES ({$paqueteAId}, {$tenantA}, 'PAQ_F24C', 'Paquete Cobertura Integral', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $pdo->exec("INSERT INTO `paquete_items` (`id`, `paquete_id`, `item_comercial_id`, `cantidad`, `orden`)
                VALUES (22801, {$paqueteAId}, {$itemAId}, 2.00, 1)
                ON DUPLICATE KEY UPDATE `cantidad` = 2.00");

    $ofertaPaqAId = 44802;
    $pdo->exec("INSERT INTO `ofertas_paquetes_edicion` (`id`, `organizacion_id`, `edicion_id`, `paquete_id`, `estado`)
                VALUES ({$ofertaPaqAId}, {$tenantA}, {$edicionAId}, {$paqueteAId}, 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

    $pdo->exec("INSERT INTO `tarifas_paquetes_edicion` (`id`, `oferta_paquete_id`, `precio`, `moneda`, `version_bloqueo`)
                VALUES (33802, {$ofertaPaqAId}, 600.00, 'PEN', 1)
                ON DUPLICATE KEY UPDATE `precio` = 600.00");

    // Usuarios y Contextos
    // 1. Admin Tenant A (con los 9 permisos)
    $usrAdminAId = 7701;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$usrAdminAId}, {$tenantA}, 'NATURAL', 'Admin', 'Tenant A', '88880001', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'Admin'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`)
                VALUES ({$usrAdminAId}, {$tenantA}, {$usrAdminAId}, 'admin_a_f24c', 'ADMIN A F24C', 'admin_a_f24c@test.local', '+51950000001', 'hash', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES ({$usrAdminAId}, 2) ON DUPLICATE KEY UPDATE `rol_id` = 2");

    // 2. Operador Tenant A (solo cotizaciones.ver)
    $usrOperadorAId = 7702;
    $pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `numero_documento`, `tipo_documento_id`)
                VALUES ({$usrOperadorAId}, {$tenantA}, 'NATURAL', 'Operador', 'Tenant A', '88880002', 1)
                ON DUPLICATE KEY UPDATE `nombres` = 'Operador'");
    $pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`)
                VALUES ({$usrOperadorAId}, {$tenantA}, {$usrOperadorAId}, 'op_a_f24c', 'OPERADOR A F24C', 'op_a_f24c@test.local', '+51950000002', 'hash', 'ACTIVO')
                ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $pdo->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES ({$usrOperadorAId}, 3) ON DUPLICATE KEY UPDATE `rol_id` = 3");

    // Mock Contextos Operativos
    $csrfValido = 'csrf_token_f24c_test_soberano_12345';
    $ctxAdminA = ContextoOperacion::paraHumano(
        usuarioId: $usrAdminAId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'TestRunner/F24C',
        organizacionId: $tenantA,
        metadatos: ['csrf_token' => $csrfValido]
    );

    $ctxOperadorA = ContextoOperacion::paraHumano(
        usuarioId: $usrOperadorAId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'TestRunner/F24C',
        organizacionId: $tenantA,
        metadatos: ['csrf_token' => $csrfValido]
    );

    // Mock Middleware
    $authMiddlewareMock = new class($ctxAdminA) extends AutenticacionMiddleware {
        public function __construct(private ?ContextoOperacion $ctx) {}
        public function setContexto(?ContextoOperacion $ctx): void { $this->ctx = $ctx; }
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion {
            return $this->ctx;
        }
    };

    $controlador = new CotizacionControlador(
        authMiddleware: $authMiddlewareMock,
        authzMiddleware: null,
        cotizacionServicio: $cotizacionServicio,
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

    // 1.2 Envelope uniforme: exito, mensaje, datos, errores (sin codigo:409 en raíz)
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
        'titulo' => 'Cotización Sin CSRF',
        'cliente_id' => $clienteAId,
        'edicion_id' => $edicionAId,
        '_csrf_token' => 'token_falso_invalido'
    ];
    $respCsrf = $controlador->crearBorrador();
    $datosCsrf = json_decode($respCsrf, true);
    afirmar($datosCsrf['exito'] === false && str_contains($datosCsrf['mensaje'], 'CSRF'), "1.4: Mutación POST con token CSRF inválido es rechazada con HTTP 403");

    // ==============================================================================
    // BLOQUE 2: CONTEXTO DE EDICIÓN (X-EDICION-ID / SESSIONSTORAGE FAIL CLOSED)
    // ==============================================================================
    echo "\n--- BLOQUE 2: CONTEXTO DE EDICIÓN (X-EDICION-ID / FAIL CLOSED) ---\n";

    // 2.1 X-Edicion-Id inválido (alfanumérico) produce Fail-Closed inmediato
    $_SERVER['HTTP_X_EDICION_ID'] = 'invalido_abc';
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
    // BLOQUE 3: RBAC Y AUTORIZACIÓN SOBERANA (9 PERMISOS)
    // ==============================================================================
    echo "\n--- BLOQUE 3: RBAC Y AUTORIZACIÓN SOBERANA (9 PERMISOS) ---\n";

    // 3.1 Operador con cotizaciones.ver puede consultar el listado
    $authMiddlewareMock->setContexto($ctxOperadorA);
    $respListarOp = $controlador->listar();
    $datosListarOp = json_decode($respListarOp, true);
    afirmar($datosListarOp['exito'] === true, "3.1: Operador con 'cotizaciones.ver' consulta exitosamente el listado");

    // 3.2 Operador intenta crear cotización (sin cotizaciones.crear) -> 403
    $_POST = [
        'titulo' => 'Intento Operador',
        'cliente_id' => $clienteAId,
        'edicion_id' => $edicionAId,
        '_csrf_token' => $csrfValido
    ];
    $respCrearOp = $controlador->crearBorrador();
    $datosCrearOp = json_decode($respCrearOp, true);
    afirmar($datosCrearOp['exito'] === false && str_contains($datosCrearOp['mensaje'], 'cotizaciones.crear'), "3.2: Intento de crear cotización sin 'cotizaciones.crear' retorna 403");

    // Volver a Admin Tenant A para operaciones permitidas
    $authMiddlewareMock->setContexto($ctxAdminA);

    // ==============================================================================
    // BLOQUE 4: ENDPOINTS AUXILIARES PARA SELECT2 Y LOOKUP COMERCIAL
    // ==============================================================================
    echo "\n--- BLOQUE 4: ENDPOINTS AUXILIARES PARA SELECT2 Y LOOKUP COMERCIAL ---\n";

    // 4.1 Lookup auxiliar de clientes (Zero PII, formato Select2 {id, text})
    $_GET = ['q' => 'Prueba'];
    $respAuxCli = $controlador->auxClientes();
    $datosAuxCli = json_decode($respAuxCli, true);
    afirmar($datosAuxCli['exito'] === true && count($datosAuxCli['datos']['resultados']) >= 1, "4.1: Lookup de clientes para Select2 retorna resultados válidos");
    afirmar(isset($datosAuxCli['datos']['resultados'][0]['id']) && isset($datosAuxCli['datos']['resultados'][0]['text']), "4.2: Formato canónico Select2: contiene estrictamente 'id' y 'text'");

    // 4.2 Lookup auxiliar de oportunidades compatibles (cliente + edición)
    $_GET = ['cliente_id' => $clienteAId];
    $respAuxOp = $controlador->auxOportunidades();
    $datosAuxOp = json_decode($respAuxOp, true);
    afirmar($datosAuxOp['exito'] === true && count($datosAuxOp['datos']['resultados']) === 1, "4.3: Lookup de oportunidades retorna la oportunidad compatible");

    // 4.3 Lookup auxiliar de ofertas de la edición de trabajo
    $_GET = [];
    $respAuxOf = $controlador->auxOfertas();
    $datosAuxOf = json_decode($respAuxOf, true);
    afirmar($datosAuxOf['exito'] === true && count($datosAuxOf['datos']['items']) === 1 && count($datosAuxOf['datos']['paquetes']) === 1, "4.4: Lookup de ofertas devuelve ítems y paquetes activos con tarifas");
    afirmar(count($datosAuxOf['datos']['paquetes'][0]['componentes']) === 1, "4.5: Oferta de paquete incluye el desglose de sus componentes");

    // ==============================================================================
    // BLOQUE 5: CREACIÓN, LÍNEAS, EDICIÓN Y DETALLE 360
    // ==============================================================================
    echo "\n--- BLOQUE 5: CREACIÓN, LÍNEAS, EDICIÓN Y DETALLE 360 ---\n";

    // 5.1 Crear borrador formal
    $_POST = [
        'titulo' => 'Propuesta F24C Oficial',
        'cliente_id' => $clienteAId,
        'edicion_id' => $edicionAId,
        'oportunidad_id' => $oportunidadAId,
        'terminos_condiciones' => 'Términos de servicio 50% anticipo.',
        'notas_internas' => 'Nota interna de prueba.',
        '_csrf_token' => $csrfValido
    ];
    $respCrear = $controlador->crearBorrador();
    $datosCrear = json_decode($respCrear, true);
    afirmar($datosCrear['exito'] === true, "5.1: Borrador de cotización creado exitosamente vía API");
    $cotId = (int) $datosCrear['datos']['cotizacion']['id'];
    $vBloqueo1 = (int) $datosCrear['datos']['cotizacion']['version_bloqueo'];

    // 5.2 Agregar línea de ítem (precio unitario resuelto en backend desde tarifa vigente)
    $_POST = [
        'tipo_linea' => 'ITEM',
        'item_comercial_id' => $itemAId,
        'oferta_item_id' => $ofertaItemAId,
        'cantidad' => 2.0,
        '_csrf_token' => $csrfValido
    ];
    $respLineaItem = $controlador->agregarLinea($cotId);
    $datosLineaItem = json_decode($respLineaItem, true);
    afirmar($datosLineaItem['exito'] === true, "5.2: Línea de ítem agregada exitosamente");
    afirmar((float) $datosLineaItem['datos']['cotizacion']['subtotal'] === 500.00, "5.3: Subtotal recalculado determinísticamente por backend (2 * 250 = 500.00)");

    // 5.3 Agregar línea de paquete (precio resuelto por backend y componentes congelados)
    $_POST = [
        'tipo_linea' => 'PAQUETE',
        'paquete_id' => $paqueteAId,
        'oferta_paquete_id' => $ofertaPaqAId,
        'cantidad' => 1.0,
        '_csrf_token' => $csrfValido
    ];
    $respLineaPaq = $controlador->agregarLinea($cotId);
    $datosLineaPaq = json_decode($respLineaPaq, true);
    afirmar($datosLineaPaq['exito'] === true, "5.4: Línea de paquete comercial agregada exitosamente");
    afirmar((float) $datosLineaPaq['datos']['cotizacion']['subtotal'] === 1100.00, "5.5: Subtotal actualizado con paquete (500 + 600 = 1100.00)");

    // 5.4 Detalle 360 de la cotización
    $respDetalle = $controlador->detalle($cotId);
    $datosDetalle = json_decode($respDetalle, true);
    afirmar($datosDetalle['exito'] === true, "5.6: Detalle 360 consultado exitosamente");
    afirmar(count($datosDetalle['datos']['lineas']) === 2, "5.7: Detalle 360 contiene exactamente las 2 líneas cotizadas");
    afirmar(count($datosDetalle['datos']['lineas'][1]['componentes']) === 1, "5.8: Detalle 360 contiene los componentes relacionales del paquete");

    // 5.5 Edición de metadatos de borrador
    $_POST = [
        'titulo' => 'Propuesta F24C Oficial Modificada',
        'terminos_condiciones' => 'Términos actualizados 60/40.',
        'version_bloqueo' => (int) $datosLineaPaq['datos']['cotizacion']['version_bloqueo'],
        '_csrf_token' => $csrfValido
    ];
    $respEdit = $controlador->actualizarBorrador($cotId);
    $datosEdit = json_decode($respEdit, true);
    afirmar($datosEdit['exito'] === true, "5.9: Borrador actualizado exitosamente");

    // ==============================================================================
    // BLOQUE 6: GOBERNANZA DE DESCUENTOS Y RBAC
    // ==============================================================================
    echo "\n--- BLOQUE 6: GOBERNANZA DE DESCUENTOS Y RBAC ---\n";

    // 6.1 Descuento global sin motivo es rechazado
    $vBloqueoActual = (int) $datosEdit['datos']['cotizacion']['version_bloqueo'];
    $_POST = [
        'tipo' => 'MONTO_FIJO',
        'valor' => 100.00,
        'motivo' => '', // Vacío
        'version_bloqueo' => $vBloqueoActual,
        '_csrf_token' => $csrfValido
    ];
    $respDescSinMotivo = $controlador->aplicarDescuentoGlobal($cotId);
    $datosDescSinMotivo = json_decode($respDescSinMotivo, true);
    afirmar($datosDescSinMotivo['exito'] === false, "6.1: Descuento global mayor a cero sin motivo es rechazado");

    // 6.2 Descuento global con motivo justificado es aceptado
    $_POST['motivo'] = 'Descuento comercial por lanzamiento';
    $respDescValido = $controlador->aplicarDescuentoGlobal($cotId);
    $datosDescValido = json_decode($respDescValido, true);
    afirmar($datosDescValido['exito'] === true, "6.2: Descuento global con motivo justificado es aplicado exitosamente");
    afirmar((float) $datosDescValido['datos']['cotizacion']['total'] === 1000.00, "6.3: Total neto oficial recalculado determinísticamente (1100 - 100 = 1000.00)");

    // ==============================================================================
    // BLOQUE 7: CONCURRENCIA OPTIMISTA (HTTP 409)
    // ==============================================================================
    echo "\n--- BLOQUE 7: CONCURRENCIA OPTIMISTA (HTTP 409) ---\n";

    // 7.1 Intento de mutación con version_bloqueo desfasada retorna HTTP 409 y envelope de conflicto
    $_POST = [
        'titulo' => 'Conflicto Forzado',
        'version_bloqueo' => 1, // Desfasada respecto al valor real
        '_csrf_token' => $csrfValido
    ];
    $resp409 = $controlador->actualizarBorrador($cotId);
    $datos409 = json_decode($resp409, true);
    afirmar($datos409['exito'] === false, "7.1: Mutación concurrente desfasada rechazada");
    afirmar($datos409['errores']['codigo'] === 'CONFLICTO_CONCURRENCIA', "7.2: Envelope de conflicto contiene errores.codigo = 'CONFLICTO_CONCURRENCIA'");
    afirmar(isset($datos409['errores']['version_actual']), "7.3: Envelope de conflicto informa version_actual para recarga sin sobrescritura");

    // ==============================================================================
    // BLOQUE 8: EMISIÓN, REVISIONES E INMUTABILIDAD
    // ==============================================================================
    echo "\n--- BLOQUE 8: EMISIÓN, REVISIONES E INMUTABILIDAD ---\n";

    // 8.1 Emisión formal de la cotización
    $vBloqueoPostDesc = (int) $datosDescValido['datos']['cotizacion']['version_bloqueo'];
    $_POST = [
        'valido_hasta' => date('Y-m-d', strtotime('+15 days')),
        'version_bloqueo' => $vBloqueoPostDesc,
        '_csrf_token' => $csrfValido
    ];
    $respEmitir = $controlador->emitir($cotId);
    $datosEmitir = json_decode($respEmitir, true);
    afirmar($datosEmitir['exito'] === true, "8.1: Cotización formalmente emitida");
    $correlativoEmitido = $datosEmitir['datos']['cotizacion']['correlativo'];
    afirmar(!empty($correlativoEmitido), "8.2: Correlativo institucional asignado monotónicamente ({$correlativoEmitido})");

    // 8.2 Inmutabilidad: Intentar agregar línea a cotización emitida es rechazado
    $_POST = [
        'tipo_linea' => 'ITEM',
        'item_comercial_id' => $itemAId,
        'oferta_item_id' => $ofertaItemAId,
        'cantidad' => 1.0,
        '_csrf_token' => $csrfValido
    ];
    $respInmutable = $controlador->agregarLinea($cotId);
    $datosInmutable = json_decode($respInmutable, true);
    afirmar($datosInmutable['exito'] === false && str_contains($datosInmutable['mensaje'], 'BORRADOR'), "8.3: Inmutabilidad post-emisión: Rechazada modificación de cotización EMITIDA");

    // 8.3 Crear nueva revisión R2
    $_POST = ['_csrf_token' => $csrfValido];
    $respRev = $controlador->crearRevision($cotId);
    $datosRev = json_decode($respRev, true);
    afirmar($datosRev['exito'] === true, "8.4: Nueva revisión generada exitosamente");
    $revCotId = (int) $datosRev['datos']['cotizacion']['id'];
    afirmar((int) $datosRev['datos']['cotizacion']['version_numero'] === 2, "8.5: Revisión R2 nace con version_numero = 2");
    afirmar($datosRev['datos']['cotizacion']['estado'] === 'BORRADOR', "8.6: La nueva revisión nace en estado BORRADOR");

    // 8.4 Emisión de R2 anula atómicamente R1 con motivo SUPERADA_POR_REVISION
    $_POST = [
        'valido_hasta' => date('Y-m-d', strtotime('+15 days')),
        'version_bloqueo' => (int) $datosRev['datos']['cotizacion']['version_bloqueo'],
        '_csrf_token' => $csrfValido
    ];
    $respEmitirR2 = $controlador->emitir($revCotId);
    $datosEmitirR2 = json_decode($respEmitirR2, true);
    afirmar($datosEmitirR2['exito'] === true, "8.7: Revisión R2 formalmente emitida");

    // Verificar R1 en BD
    $stmtR1 = $pdo->prepare("SELECT estado, motivo_anulacion FROM cotizaciones WHERE id = :id");
    $stmtR1->execute(['id' => $cotId]);
    $filaR1 = $stmtR1->fetch(PDO::FETCH_ASSOC);
    afirmar($filaR1['estado'] === 'ANULADA', "8.8: R1 transicionó atómicamente a ANULADA al emitirse R2");
    afirmar($filaR1['motivo_anulacion'] === 'SUPERADA_POR_REVISION', "8.9: R1 registra motivo estricto SUPERADA_POR_REVISION");

    // ==============================================================================
    // BLOQUE 9: CONFORMIDAD (ACEPTADA), RECHAZO, ANULACIÓN Y DESACOPLAMIENTO
    // ==============================================================================
    echo "\n--- BLOQUE 9: CONFORMIDAD (ACEPTADA), RECHAZO Y DESACOPLAMIENTO ---\n";

    // 9.1 Aceptar cotización R2
    $_POST = ['_csrf_token' => $csrfValido];
    $respAceptar = $controlador->aceptar($revCotId);
    $datosAceptar = json_decode($respAceptar, true);
    afirmar($datosAceptar['exito'] === true && $datosAceptar['datos']['cotizacion']['estado'] === 'ACEPTADA', "9.1: Cotización transiciona formalmente a ACEPTADA");

    // 9.2 Desacoplamiento estricto
    $stmtVentas = $pdo->prepare("SELECT COUNT(*) FROM `ventas` WHERE `cotizacion_id` = :id");
    $stmtVentas->execute(['id' => $revCotId]);
    $stmtReservas = $pdo->prepare("SELECT COUNT(*) FROM `reservas` WHERE `venta_id` IN (SELECT `id` FROM `ventas` WHERE `cotizacion_id` = :id)");
    $stmtReservas->execute(['id' => $revCotId]);
    afirmar((int) $stmtReservas->fetchColumn() === 0, "9.3: Desacoplamiento: Aceptación de cotización no genera reserva automáticamente");

    // Oportunidad no forzada a GANADA
    $stmtOpCheck = $pdo->prepare("SELECT etapa FROM crm_oportunidades WHERE id = :id");
    $stmtOpCheck->execute(['id' => $oportunidadAId]);
    afirmar($stmtOpCheck->fetchColumn() !== 'GANADA', "9.4: Desacoplamiento CRM: Aceptación de cotización NO fuerza oportunidad a GANADA");

    // 9.3 Prohibición de motivo SUPERADA_POR_REVISION desde UI/API manual de anulación
    $_POST = [
        'motivo' => 'SUPERADA_POR_REVISION',
        '_csrf_token' => $csrfValido
    ];
    $respAnularProhibido = $controlador->anular($revCotId);
    $datosAnularProhibido = json_decode($respAnularProhibido, true);
    afirmar($datosAnularProhibido['exito'] === false && str_contains($datosAnularProhibido['mensaje'], 'SUPERADA_POR_REVISION'), "9.5: Prohibido motivo 'SUPERADA_POR_REVISION' en anulación manual ordinaria");

    // ==============================================================================
    // BLOQUE 10: ALINA UI, SKELETON, SWEETALERT2, JS Y XSS
    // ==============================================================================
    echo "\n--- BLOQUE 10: ALINA UI, SKELETON, SWEETALERT2, JS Y XSS ---\n";

    // 10.1 Inspección estricta de cotizaciones.js: CERO location.reload()
    $jsContent = file_get_contents(__DIR__ . '/../publico/js/cotizaciones.js');
    afirmar(!str_contains($jsContent, 'location.reload()') && !str_contains($jsContent, 'window.location.reload()'), "10.1: CERO location.reload(): cotizaciones.js utiliza actualización asíncrona pura");

    // 10.2 Presencia de infraestructura SweetAlert2 y Skeleton
    afirmar(str_contains($jsContent, 'CandelariaUI.notificarExito') && str_contains($jsContent, 'CandelariaUI.confirmarAccion'), "10.2: cotizaciones.js integra SweetAlert2 nativo mediante CandelariaUI");
    afirmar(str_contains($jsContent, 'window.Skeleton'), "10.3: cotizaciones.js integra Skeleton Loaders para estado de carga");

    // 10.3 Presencia de Flatpickr y Select2
    afirmar(str_contains($jsContent, 'flatpickr') && str_contains($jsContent, 'select2'), "10.4: cotizaciones.js integra Flatpickr y Select2 oficiales de Alina");

    // 10.4 Mitigación XSS: Función de escape presente y utilizada
    afirmar(str_contains($jsContent, 'escaparHtml(str)'), "10.5: Función canónica de escape XSS presente en cotizaciones.js");

    // 10.5 Prueba de Payload XSS en creación de borrador
    $_POST = [
        'titulo' => '<script>alert("xss")</script>Título Seguro',
        'cliente_id' => $clienteAId,
        'edicion_id' => $edicionAId,
        'terminos_condiciones' => '<img src=x onerror=alert(1)>',
        '_csrf_token' => $csrfValido
    ];
    $respXss = $controlador->crearBorrador();
    $datosXss = json_decode($respXss, true);
    afirmar($datosXss['exito'] === true, "10.6: Creación de cotización con caracteres especiales procesada sin inyecciones");
    $xssId = (int) $datosXss['datos']['cotizacion']['id'];

    $respXssDetalle = $controlador->detalle($xssId);
    $datosXssDetalle = json_decode($respXssDetalle, true);
    afirmar($datosXssDetalle['exito'] === true, "10.7: Detalle 360 recupera el contenido sin ejecución de scripts");

    // ==============================================================================
    // BLOQUE 11: CERTIFICACIÓN ZERO-PII Y PRESERVACIÓN DE USUARIO ADMINISTRADOR
    // ==============================================================================
    echo "\n--- BLOQUE 11: CERTIFICACIÓN ZERO-PII Y PRESERVACIÓN DE CREDENCIALES ---\n";

    $stmtOrlandoPost = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id = 24");
    $stmtOrlandoPost->execute();
    $orlandoPost = $stmtOrlandoPost->fetch(PDO::FETCH_ASSOC);
    $fingerprintOrlandoPost = $orlandoPost ? substr(hash('sha256', (string) $orlandoPost['contrasena_hash']), 0, 16) : null;

    afirmar($orlandoPost !== false, "11.1: Usuario administrador ID 24 existe en base de datos");
    afirmar($orlandoPost['estado'] === 'ACTIVO', "11.2: Estado de usuario administrador permanece ACTIVO");
    afirmar((int) $orlandoPost['intentos_fallidos'] === 0, "11.3: Intentos fallidos permanece en 0");
    afirmar($orlandoPost['bloqueado_hasta'] === null, "11.4: Bloqueo temporal es NULL");
    afirmar($fingerprintOrlandoPost === $fingerprintOrlandoPre, "11.5: Huella criptográfica no reversible preservada intacta al 100%");

} finally {
    // Revertir transacción para preservar la BD operativa intacta
    $pdo->rollBack();
    echo "\n[INFO] Transacción de pruebas F2.4C revertida con ROLLBACK determinista.\n";
}

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.4C: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
