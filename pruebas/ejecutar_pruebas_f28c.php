<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 2.8C (SUPERFICIE ADMINISTRATIVA ALINA)
 * MÓDULO 12: COMUNICACIONES Y MENSAJERÍA WHATSAPP (UI, INBOX 360°, OUTBOX, SoD)
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Comunicaciones\CategoriaPlantilla;
use Aplicacion\Comunicaciones\EstadoPlantillaMeta;
use Aplicacion\Controladores\ComunicacionControlador;
use Aplicacion\Entidades\ComunicacionPlantilla;
use Aplicacion\Repositorios\ComunicacionCampanaRepositorio;
use Aplicacion\Repositorios\ComunicacionConfigRepositorio;
use Aplicacion\Repositorios\ComunicacionConsentimientoRepositorio;
use Aplicacion\Repositorios\ComunicacionConversacionRepositorio;
use Aplicacion\Repositorios\ComunicacionMensajeRepositorio;
use Aplicacion\Repositorios\ComunicacionPlantillaRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;

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
echo "INICIANDO SUITE F2.8C: SUPERFICIE ALINA UI DE COMUNICACIONES Y WHATSAPP\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

// ------------------------------------------------------------------------------
// SECCIÓN 1: Integridad de Archivos de Vista, Rutas y Assets
// ------------------------------------------------------------------------------
echo "--- SECCIÓN 1: Preservación de Assets, Vistas Alina y Rutas Web ---\n";

$rutaVista = __DIR__ . '/../recursos/vistas/paginas/comunicaciones/index.php';
$rutaJs = __DIR__ . '/../publico/js/comunicaciones.js';
$rutaWeb = __DIR__ . '/../rutas/web.php';
$rutaApi = __DIR__ . '/../rutas/api.php';
$rutaBarra = __DIR__ . '/../recursos/vistas/parciales/barra_lateral.php';

registrarAsercion("La vista 'comunicaciones/index.php' existe físicamente", file_exists($rutaVista));
registrarAsercion("El asset JavaScript 'publico/js/comunicaciones.js' existe físicamente", file_exists($rutaJs));

$contenidoWeb = file_get_contents($rutaWeb) ?: '';
registrarAsercion("La ruta web 'GET /comunicaciones' está registrada formalmente", str_contains($contenidoWeb, "'/comunicaciones'"));

$contenidoApi = file_get_contents($rutaApi) ?: '';
registrarAsercion("La ruta API '/api/v1/comunicaciones/kpis' está registrada", str_contains($contenidoApi, "'/api/v1/comunicaciones/kpis'"));
registrarAsercion("La ruta API '/api/v1/comunicaciones/conversaciones' está registrada", str_contains($contenidoApi, "'/api/v1/comunicaciones/conversaciones'"));
registrarAsercion("La ruta API '/api/v1/comunicaciones/simulador/recibir' está registrada", str_contains($contenidoApi, "'/api/v1/comunicaciones/simulador/recibir'"));
registrarAsercion("La ruta API '/api/v1/comunicaciones/outbox/procesar' está registrada", str_contains($contenidoApi, "'/api/v1/comunicaciones/outbox/procesar'"));
registrarAsercion("La ruta API '/api/v1/comunicaciones/campanas/{id}/aprobar' está registrada", str_contains($contenidoApi, "'/api/v1/comunicaciones/campanas/{id}/aprobar'"));

$contenidoBarra = file_get_contents($rutaBarra) ?: '';
registrarAsercion("La barra lateral incluye navegación para 'Comunicaciones / WhatsApp'", str_contains($contenidoBarra, 'menuComunicaciones'));

// ------------------------------------------------------------------------------
// SECCIÓN 2: Renderizado Web Oficial y Autorización RBAC (index)
// ------------------------------------------------------------------------------
// Obtener o garantizar organización válida
$stmtOrg = $pdo->query("SELECT id FROM organizaciones LIMIT 1");
$orgId = $stmtOrg->fetchColumn();
if ($orgId === false) {
    $pdo->exec("INSERT INTO organizaciones (id, codigo, razon_social, nombre_comercial, numero_documento, estado) VALUES (1, 'org_candelaria', 'Candelaria SAC', 'CandelariaAPP', '20123456789', 'ACTIVO') ON DUPLICATE KEY UPDATE estado = 'ACTIVO'");
    $orgId = 1;
} else {
    $orgId = (int) $orgId;
}

// Obtener o crear usuarios válidos para pruebas de operador y SoD
$stmtUsers = $pdo->query("SELECT id FROM usuarios ORDER BY id ASC");
$allUsers = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);
$userCreador = (int) ($allUsers[0] ?? 24);
if (count($allUsers) < 2) {
    $pdo->exec("INSERT INTO personas (id, organizacion_id, tipo_persona, tipo_documento_id, numero_documento, nombres, apellidos) VALUES (77702, {$orgId}, 'NATURAL', 1, '77702002', 'Admin', 'SoD') ON DUPLICATE KEY UPDATE nombres = 'Admin'");
    $pdo->exec("INSERT INTO usuarios (id, organizacion_id, persona_id, nombre_usuario, nombre_completo, correo_electronico, contrasena_hash, estado) VALUES (77702, {$orgId}, 77702, 'admin_sod', 'Admin SoD', 'sod@test.pe', '\$2y\$10\$abcdefghijklmnopqrstuv', 'ACTIVO') ON DUPLICATE KEY UPDATE estado = 'ACTIVO'");
    $userAprobador = 77702;
} else {
    $userAprobador = (int) $allUsers[1];
}

// Configurar contexto de operador autorizado
$contextoSuperAdmin = new ContextoOperacion(
    actorTipo: 'HUMANO',
    usuarioId: $userCreador,
    actorSistemaId: null,
    actorSistemaCodigo: null,
    canalId: 1,
    canalCodigo: 'WEB_ALINA',
    correlacionId: 'corr_test_f28c_alina_2026_operador',
    origenIp: '127.0.0.1',
    agenteUsuario: 'PHPUnit/F2.8C',
    organizacionId: $orgId
);
ContextoOperacion::establecerActual($contextoSuperAdmin);

$mockAuth = new class($contextoSuperAdmin) extends AutenticacionMiddleware {
    public function __construct(private ?ContextoOperacion $ctx) {}
    public function procesar(?array $servidor = null, ?array $cookies = null, bool $exigirAutenticacion = true): ?ContextoOperacion {
        return $this->ctx;
    }
};

$mockAuthzPermitido = new class extends AutorizacionMiddleware {
    public function verificarPermiso(string $permiso, ?ContextoOperacion $contexto = null, bool $lanzarExcepcion = true): bool {
        return true;
    }
};

$mockAuthzDenegado = new class extends AutorizacionMiddleware {
    public function verificarPermiso(string $permiso, ?ContextoOperacion $contexto = null, bool $lanzarExcepcion = true): bool {
        if ($lanzarExcepcion) {
            throw new \Nucleo\Excepciones\AccesoDenegadoExcepcion("Permiso denegado para {$permiso}");
        }
        return false;
    }
};

// 1. Acceso denegado (403)
$controladorSinPermiso = new ComunicacionControlador(
    authMiddleware: $mockAuth,
    authzMiddleware: $mockAuthzDenegado,
    pdo: $pdo
);
$html403 = $controladorSinPermiso->index();
registrarAsercion("Usuario sin permiso 'comunicaciones.ver' recibe vista de Error 403", str_contains($html403, 'Error 403') || str_contains($html403, 'Acceso Denegado'));

// 2. Acceso permitido (200 Alina)
$controlador = new ComunicacionControlador(
    authMiddleware: $mockAuth,
    authzMiddleware: $mockAuthzPermitido,
    pdo: $pdo
);

$htmlVista = $controlador->index();
registrarAsercion("El controlador renderiza la vista HTML correctamente", !empty($htmlVista));
registrarAsercion("La vista contiene el título 'Comunicaciones y WhatsApp'", str_contains($htmlVista, 'Comunicaciones y WhatsApp'));
registrarAsercion("La vista contiene la pestaña 'Bandeja de Conversaciones (Inbox 360°)'", str_contains($htmlVista, 'Bandeja de Conversaciones'));
registrarAsercion("La vista contiene el panel 'Simulador Interactivo Sandbox'", str_contains($htmlVista, 'Simulador Interactivo Sandbox'));
registrarAsercion("La vista contiene la advertencia legal 'Segregación de Deberes (SoD)'", str_contains($htmlVista, 'Segregación de Deberes (SoD)'));
registrarAsercion("La vista contiene el modal 'modalEnviarMensaje'", str_contains($htmlVista, 'id="modalEnviarMensaje"'));

// ------------------------------------------------------------------------------
// SECCIÓN 3: Endpoints de Bandeja 360°, Ventana 24h y Asignación
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 3: Bandeja 360°, Visor de Hilos y Ventana 24h ---\n";

$_GET = [];
$_POST = [];

// 1. Obtener KPIs
$resKpisJson = $controlador->obtenerKpis();
$resKpis = json_decode($resKpisJson, true);
registrarAsercion("Endpoint GET /api/v1/comunicaciones/kpis retorna estructura válida", ($resKpis['exito'] ?? false) === true);
registrarAsercion("KPIs incluyen total_mensajes, tasa_entrega_pct y presupuesto límite", isset($resKpis['datos']['tasa_entrega_pct'], $resKpis['datos']['presupuesto_mensual_limite_usd']));

// 2. Simular mensaje entrante de cliente (activa ventana 24h)
// Asegurar que existe al menos un cliente en org $orgId
$stmtCliExist = $pdo->prepare("SELECT id FROM clientes WHERE organizacion_id = :org_id LIMIT 1");
$stmtCliExist->execute(['org_id' => $orgId]);
$clienteIdOrg1 = $stmtCliExist->fetchColumn();

if ($clienteIdOrg1 === false) {
    // Buscar una persona de org o crear una
    $stmtPer = $pdo->prepare("SELECT id FROM personas WHERE organizacion_id = :org_id LIMIT 1");
    $stmtPer->execute(['org_id' => $orgId]);
    $perId = $stmtPer->fetchColumn();
    if ($perId === false) {
        $pdo->exec("INSERT INTO personas (id, organizacion_id, tipo_persona, tipo_documento_id, numero_documento, nombres, apellidos) VALUES (77701, {$orgId}, 'NATURAL', 1, '77701001', 'Test', 'F28C') ON DUPLICATE KEY UPDATE nombres = 'Test'");
        $perId = 77701;
    }
    $pdo->exec("INSERT INTO clientes (id, organizacion_id, persona_id, estado_comercial, consentimiento_operativo, consentimiento_promocional) VALUES (77701, {$orgId}, {$perId}, 'CLIENTE', 1, 1) ON DUPLICATE KEY UPDATE estado_comercial = 'CLIENTE'");
    $clienteIdOrg1 = 77701;
}

$_POST = [
    'telefono'   => '+51951234567',
    'nombre'     => 'Cliente F28C Test',
    'texto'      => 'Hola Candelaria, consulta para la festividad 2027.',
    'cliente_id' => (int) $clienteIdOrg1
];
$resSimJson = $controlador->simularMensajeEntrante();
$resSim = json_decode($resSimJson, true);
registrarAsercion("POST /api/v1/comunicaciones/simulador/recibir inyecta mensaje entrante", ($resSim['exito'] ?? false) === true, $resSim['error'] ?? 'desconocido');
$convId = (int) ($resSim['datos']['conversacion_id'] ?? 0);
registrarAsercion("Se creó/asoció una conversación válida (#{$convId})", $convId > 0);

// 3. Listar conversaciones
$_GET = ['limite' => 10];
$resConvsJson = $controlador->listarConversaciones();
$resConvs = json_decode($resConvsJson, true);
registrarAsercion("GET /api/v1/comunicaciones/conversaciones retorna lista de hilos", ($resConvs['exito'] ?? false) === true && count($resConvs['datos']) > 0, $resConvs['error'] ?? 'lista vacia');

// 4. Ver mensajes del hilo
$resMsgsJson = $controlador->obtenerMensajesConversacion($convId);
$resMsgs = json_decode($resMsgsJson, true);
registrarAsercion("GET /api/v1/comunicaciones/conversaciones/{id}/mensajes retorna historial del hilo", ($resMsgs['exito'] ?? false) === true && count($resMsgs['datos']['mensajes']) >= 1, $resMsgs['error'] ?? '');

// 5. Asignar operador al hilo
$_POST = ['operador_id' => $userCreador];
$resAsigJson = $controlador->asignarOperadorConversacion($convId);
$resAsig = json_decode($resAsigJson, true);
registrarAsercion("POST /api/v1/comunicaciones/conversaciones/{id}/asignar asigna operador con éxito", ($resAsig['exito'] ?? false) === true, $resAsig['error'] ?? '');

// 6. Responder dentro de la ventana de 24h activa
$_POST = ['texto' => 'Estimado cliente, con gusto le brindamos toda la información.'];
$resRespJson = $controlador->responderConversacion($convId);
$resResp = json_decode($resRespJson, true);
registrarAsercion("POST /api/v1/comunicaciones/conversaciones/{id}/responder encola mensaje de operador en Outbox", ($resResp['exito'] ?? false) === true, $resResp['error'] ?? '');

// 7. Cerrar conversación
$resCerrarJson = $controlador->cerrarConversacion($convId);
$resCerrar = json_decode($resCerrarJson, true);
registrarAsercion("POST /api/v1/comunicaciones/conversaciones/{id}/cerrar marca el hilo como CERRADA", ($resCerrar['exito'] ?? false) === true, $resCerrar['error'] ?? '');

// ------------------------------------------------------------------------------
// SECCIÓN 4: Catálogo de Plantillas y Despacho Outbox
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 4: Plantillas Versionadas y Despacho Outbox ---\n";

// 1. Crear plantilla local
$nombrePlantilla = 'plantilla_f28c_test_' . bin2hex(random_bytes(4));
$_POST = [
    'nombre'          => $nombrePlantilla,
    'idioma'          => 'es_PE',
    'categoria'       => 'UTILITY',
    'cuerpo_texto'    => 'Estimado {{1}}, su código de acceso para Puno es {{2}}.',
    'encabezado_tipo' => 'NINGUNO',
    'pie_texto'       => 'CandelariaAPP'
];
$resPlanJson = $controlador->crearPlantilla();
$resPlan = json_decode($resPlanJson, true);
registrarAsercion("POST /api/v1/comunicaciones/plantillas registra plantilla localmente", ($resPlan['exito'] ?? false) === true);

// 2. Listar plantillas
$resListPlanJson = $controlador->listarPlantillas();
$resListPlan = json_decode($resListPlanJson, true);
registrarAsercion("GET /api/v1/comunicaciones/plantillas retorna catálogo institucional", ($resListPlan['exito'] ?? false) === true && count($resListPlan['datos']) > 0);

// 3. Despacho Outbox manual
$resOutboxJson = $controlador->procesarOutboxManual();
$resOutbox = json_decode($resOutboxJson, true);
registrarAsercion("POST /api/v1/comunicaciones/outbox/procesar despacha la cola de Outbox", ($resOutbox['exito'] ?? false) === true);

// ------------------------------------------------------------------------------
// SECCIÓN 5: Campañas Masivas con Segregación de Deberes (SoD)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 5: Campañas Masivas y Verificación SoD ---\n";

// Buscar una plantilla id
$plantillaRepo = new ComunicacionPlantillaRepositorio($pdo);
$todasPlan = $plantillaRepo->listar($orgId);
$primeraPlan = $todasPlan[0] ?? null;

if ($primeraPlan !== null) {
    // 1. Crear campaña en BORRADOR por usuario creador
    $_POST = [
        'nombre'          => 'Campaña F28C Promocional Test',
        'plantilla_id'    => $primeraPlan->id,
        'presupuesto_usd' => 30.00,
        'criterios'       => ['edicion' => 2027]
    ];
    $resCampJson = $controlador->crearCampana();
    $resCamp = json_decode($resCampJson, true);
    registrarAsercion("POST /api/v1/comunicaciones/campanas crea campaña en estado BORRADOR", ($resCamp['exito'] ?? false) === true, $resCamp['error'] ?? '');
    $campanaId = (int) ($resCamp['datos']['id'] ?? 0);

    // 2. Intentar aprobar por el MISMO creador -> DEBE FALLAR POR SoD
    $campanaRepo = new ComunicacionCampanaRepositorio($pdo);
    $errorSodDetectado = false;
    try {
        $campanaRepo->aprobar($campanaId, $orgId, $userCreador);
    } catch (InvalidArgumentException $e) {
        $errorSodDetectado = str_contains($e->getMessage(), 'Principio SoD');
    }
    registrarAsercion("Principio SoD bloquea que el creador de la campaña apruebe su propia campaña", $errorSodDetectado);

    // 3. Aprobar por usuario DISTINTO -> DEBE TENER ÉXITO
    $aprobacionExitosa = false;
    try {
        $aprobacionExitosa = $campanaRepo->aprobar($campanaId, $orgId, $userAprobador);
    } catch (Throwable $e) {
        $aprobacionExitosa = false;
    }
    registrarAsercion("Usuario independiente autorizado aprueba la campaña formalmente", $aprobacionExitosa);

    // 4. Listar campañas
    $resListCampJson = $controlador->listarCampanas();
    $resListCamp = json_decode($resListCampJson, true);
    registrarAsercion("GET /api/v1/comunicaciones/campanas retorna listado de campañas", ($resListCamp['exito'] ?? false) === true);
}

// ------------------------------------------------------------------------------
// SECCIÓN 6: Matriz Contractual de Estados, Monotonicidad y REINTENTO_PROGRAMADO
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 6: Matriz de Estados, Monotonicidad y REINTENTO_PROGRAMADO ---\n";
use Aplicacion\Comunicaciones\EstadoMensaje;

registrarAsercion("EstadoMensaje::REINTENTO_PROGRAMADO existe como caso canónico", EstadoMensaje::tryFrom('REINTENTO_PROGRAMADO') !== null);
registrarAsercion("Peso de REINTENTO_PROGRAMADO (25) es coherente entre EN_PROCESO (20) y ENVIADO (30)", EstadoMensaje::REINTENTO_PROGRAMADO->peso() === 25);

registrarAsercion("EN_PROCESO puede transicionar a REINTENTO_PROGRAMADO", EstadoMensaje::EN_PROCESO->puedeAvanzarHacia(EstadoMensaje::REINTENTO_PROGRAMADO));
registrarAsercion("REINTENTO_PROGRAMADO puede transicionar de regreso a EN_PROCESO", EstadoMensaje::REINTENTO_PROGRAMADO->puedeAvanzarHacia(EstadoMensaje::EN_PROCESO));
registrarAsercion("REINTENTO_PROGRAMADO puede transicionar a FALLIDO si agota reintentos", EstadoMensaje::REINTENTO_PROGRAMADO->puedeAvanzarHacia(EstadoMensaje::FALLIDO));

// Monotonicidad inquebrantable
registrarAsercion("ENTREGADO NO puede retroceder a ENVIADO, ENCOLADO ni pasar a FALLIDO", !EstadoMensaje::ENTREGADO->puedeAvanzarHacia(EstadoMensaje::FALLIDO) && !EstadoMensaje::ENTREGADO->puedeAvanzarHacia(EstadoMensaje::ENVIADO));
registrarAsercion("ENTREGADO solo puede avanzar hacia LEIDO", EstadoMensaje::ENTREGADO->transicionesPermitidas() === [EstadoMensaje::LEIDO]);
registrarAsercion("LEIDO es estado terminal absoluto (transiciones vacías)", empty(EstadoMensaje::LEIDO->transicionesPermitidas()));
registrarAsercion("FALLIDO es estado terminal absoluto (transiciones vacías)", empty(EstadoMensaje::FALLIDO->transicionesPermitidas()));
registrarAsercion("CANCELADO es estado terminal absoluto (transiciones vacías)", empty(EstadoMensaje::CANCELADO->transicionesPermitidas()));

// Transición en Repositorio
$msgRepo = new ComunicacionMensajeRepositorio($pdo);
$stmtInsMsg = $pdo->prepare("
    INSERT INTO comunicacion_mensajes (
        organizacion_id, canal, tipo_mensaje, direccion,
        destinatario_telefono, destinatario_nombre, contenido_texto,
        estado, peso_estado, idempotency_key, correlacion_id, costo_estimado_usd, creado_en
    ) VALUES (
        ?, 'WHATSAPP', 'TRANSACCIONAL', 'SALIENTE',
        '+51955443322', 'Cliente Monotonicidad', 'Mensaje de prueba de transición monotónica',
        'EN_PROCESO', 20, ?, ?, 0.005, NOW()
    )
");
$idempotencyKey = 'test_mono_' . bin2hex(random_bytes(16));
$correlacionId = 'corr_' . bin2hex(random_bytes(16));
$stmtInsMsg->execute([$orgId, $idempotencyKey, $correlacionId]);
$msgId = (int)$pdo->lastInsertId();

$msgRepo->programarReintento($msgId, $orgId, 60);
$stmtVerifR = $pdo->prepare("SELECT estado, peso_estado FROM comunicacion_mensajes WHERE id = ?");
$stmtVerifR->execute([$msgId]);
$filaR = $stmtVerifR->fetch(PDO::FETCH_ASSOC);
registrarAsercion("programarReintento() persiste estado 'REINTENTO_PROGRAMADO' con peso 25", ($filaR['estado'] ?? '') === 'REINTENTO_PROGRAMADO' && (int)($filaR['peso_estado'] ?? 0) === 25);

// Intentar actualizar ENTREGADO a FALLIDO a través de actualizarEstadoMonotonico -> debe retornar false
$stmtSetEnt = $pdo->prepare("UPDATE comunicacion_mensajes SET estado = 'ENTREGADO', peso_estado = 40 WHERE id = ?");
$stmtSetEnt->execute([$msgId]);
$cambioIlegal = $msgRepo->actualizarEstadoMonotonico($msgId, $orgId, EstadoMensaje::FALLIDO);
registrarAsercion("actualizarEstadoMonotonico() rechaza transición ilegal ENTREGADO -> FALLIDO", $cambioIlegal === false);

// ------------------------------------------------------------------------------
// SECCIÓN 7: Aislamiento Multitenant Estricto
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 7: Aislamiento Multitenant Estricto ---\n";
$convRepo = new ComunicacionConversacionRepositorio($pdo);
$convOrg1 = $convRepo->listarConDetalles($orgId, null, null, 100);
$convOrgOtra = $convRepo->listarConDetalles(99998, null, null, 100);

$filasOrgOtraEnOrg1 = array_filter($convOrg1, fn($c) => (int)$c['organizacion_id'] !== $orgId);
registrarAsercion("Bandeja de conversaciones de Org {$orgId} no contiene registros de otras organizaciones", empty($filasOrgOtraEnOrg1));
registrarAsercion("Bandeja de otra organización no retorna conversaciones de Org {$orgId}", empty($convOrgOtra));

// ------------------------------------------------------------------------------
// SECCIÓN 8: Salvaguardas de Seguridad y Modo Producción (Fail-Closed)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 8: Salvaguarda contra Activación Ilegal de Modo Producción ---\n";
// 1. Intentar modo PRODUCCION con SIMULADOR_SANDBOX -> debe fallar con 400
$_POST = [
    'proveedor_codigo' => 'SIMULADOR_SANDBOX',
    'modo'             => 'PRODUCCION',
    'telefono'         => '+51999888777'
];
$resCfgInvalido = json_decode($controlador->guardarConfiguracion(), true);
registrarAsercion("guardarConfiguracion() rechaza PRODUCCION con SIMULADOR_SANDBOX", ($resCfgInvalido['exito'] ?? false) === false);

// 2. Intentar modo PRODUCCION sin flag de activación institucional -> debe fallar con 400
$_POST = [
    'proveedor_codigo'       => 'META_CLOUD_API',
    'modo'                   => 'PRODUCCION',
    'meta_phone_number_id'   => '10987654321',
    'token_acceso'           => 'EAAGfakeToken12345',
    'webhook_secret'         => 'secret_fake_12345',
    'webhook_verify_token'   => 'token_verify_fake'
];
putenv('WHATSAPP_PRODUCCION_HABILITADA=false');
$resCfgProdBloq = json_decode($controlador->guardarConfiguracion(), true);
registrarAsercion("guardarConfiguracion() bloquea PRODUCCION si directiva institucional está deshabilitada", ($resCfgProdBloq['exito'] ?? false) === false);

// ------------------------------------------------------------------------------
// SECCIÓN 9: Auditoría Dual y Límites en Ejecución Manual Outbox
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 9: Auditoría Dual y Límites en Despacho Outbox ---\n";
$_POST = ['limite' => 15];
$resOutboxManual = json_decode($controlador->procesarOutboxManual(), true);
registrarAsercion("procesarOutboxManual() ejecuta lote con límite custom", ($resOutboxManual['exito'] ?? false) === true);

// Verificar si se registró auditoría
$stmtAudit = $pdo->prepare("
    SELECT * FROM auditoria_operaciones 
    WHERE modulo = 'comunicaciones' AND accion = 'EJECUTAR_OUTBOX_MANUAL' 
    ORDER BY id DESC LIMIT 1
");
$stmtAudit->execute();
$auditRow = $stmtAudit->fetch(PDO::FETCH_ASSOC);
registrarAsercion("Ejecución manual de Outbox genera pista inmutable en 'auditoria_operaciones'", $auditRow !== false && (int)$auditRow['organizacion_id'] === $orgId);

// ------------------------------------------------------------------------------
// SECCIÓN 10: Preservación de Identidad (Orlando ID 24)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 10: Preservación de Identidad (Orlando ID 24) ---\n";
$stmtOrl = $pdo->prepare("SELECT id, nombre_usuario, correo_electronico, contrasena_hash, intentos_fallidos, estado FROM usuarios WHERE id = 24");
$stmtOrl->execute();
$orlando = $stmtOrl->fetch(PDO::FETCH_ASSOC);

registrarAsercion("Orlando (ID 24) existe en base de datos", $orlando !== false);
registrarAsercion("Orlando cuenta = ACTIVO", ($orlando['estado'] ?? '') === 'ACTIVO');
registrarAsercion("Orlando intentos_fallidos = 0", (int) ($orlando['intentos_fallidos'] ?? 1) === 0);

// ------------------------------------------------------------------------------
// RESUMEN
// ------------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo "RESULTADO FINAL DE PRUEBAS F2.8C:\n";
echo "Total Pruebas : {$totalPruebas}\n";
echo "Exitosas      : {$pruebasExitosas}\n";
echo "Fallidas      : {$pruebasFallidas}\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
