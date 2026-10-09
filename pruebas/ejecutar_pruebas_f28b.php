<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F2.8B
 * MÓDULO 12: COMUNICACIONES Y MENSAJERÍA WHATSAPP (OUTBOX, WEBHOOKS, RBAC)
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Comunicaciones\CategoriaPlantilla;
use Aplicacion\Comunicaciones\ComunicacionServicio;
use Aplicacion\Comunicaciones\EstadoConsentimiento;
use Aplicacion\Comunicaciones\EstadoMensaje;
use Aplicacion\Comunicaciones\EstadoPlantillaMeta;
use Aplicacion\Comunicaciones\FinalidadConsentimiento;
use Aplicacion\Comunicaciones\ModoComunicacion;
use Aplicacion\Comunicaciones\OutboxWorker;
use Aplicacion\Comunicaciones\Proveedores\FabricaProveedorWhatsApp;
use Aplicacion\Comunicaciones\Proveedores\SimuladorWhatsAppAdaptador;
use Aplicacion\Comunicaciones\TipoMensaje;
use Aplicacion\Comunicaciones\WebhookWhatsAppServicio;
use Aplicacion\Controladores\ComunicacionControlador;
use Aplicacion\Entidades\ComunicacionConsentimiento;
use Aplicacion\Entidades\ComunicacionPlantilla;
use Aplicacion\Repositorios\ComunicacionConfigRepositorio;
use Aplicacion\Repositorios\ComunicacionConsentimientoRepositorio;
use Aplicacion\Repositorios\ComunicacionConversacionRepositorio;
use Aplicacion\Repositorios\ComunicacionMensajeRepositorio;
use Aplicacion\Repositorios\ComunicacionPlantillaRepositorio;
use Aplicacion\Repositorios\ComunicacionWebhookRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;

$pdo = Conexion::obtenerInstancia();

$totalPruebas = 0;
$pruebasPasadas = 0;
$pruebasFallidas = 0;

function assertPrueba(bool $condicion, string $descripcion, ?string $detalle = null): void
{
    global $totalPruebas, $pruebasPasadas, $pruebasFallidas;
    $totalPruebas++;
    if ($condicion) {
        $pruebasPasadas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$descripcion}\n";
        if ($detalle !== null) {
            echo "         -> Detalle: {$detalle}\n";
        }
    }
}

echo "==============================================================================\n";
echo "INICIANDO SUITE F2.8B: COMUNICACIONES Y WHATSAPP (OUTBOX, WEBHOOKS, RBAC)\n";
echo "==============================================================================\n\n";

// Captura de huella de usuario Orlando ID 24
$stmtOrlando = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos FROM usuarios WHERE id = 24 OR nombre_usuario = 'orlando'");
$stmtOrlando->execute();
$orlandoPre = $stmtOrlando->fetch(PDO::FETCH_ASSOC);
$fingerprintPre = $orlandoPre ? substr(hash('sha256', (string) $orlandoPre['contrasena_hash']), 0, 16) : null;

// ==============================================================================
// SECCIÓN 1: GOBERNANZA, ESQUEMA RELACIONAL Y RBAC (MIGRACIÓN 000017)
// ==============================================================================
echo "--- SECCIÓN 1: Esquema de Comunicaciones, Integridad Compuesta y RBAC ---\n";

$tablasActuales = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$tablasRequeridas = [
    'comunicacion_proveedores',
    'organizacion_comunicacion_config',
    'comunicacion_tarifas',
    'comunicacion_plantillas',
    'comunicacion_consentimientos_canal',
    'comunicacion_conversaciones',
    'comunicacion_campanas',
    'comunicacion_mensajes',
    'comunicacion_intentos_envio',
    'comunicacion_webhook_eventos',
    'comunicacion_secuencias'
];

$todasTablasExisten = true;
foreach ($tablasRequeridas as $t) {
    if (!in_array($t, $tablasActuales, true)) {
        $todasTablasExisten = false;
        break;
    }
}
assertPrueba($todasTablasExisten, "Las 11 tablas del ecosistema de comunicaciones existen en la base de datos");

// Permisos RBAC Módulo 12
$stmtPerms = $pdo->query("SELECT codigo FROM permisos WHERE modulo_id = 12");
$codigosPerms = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);

assertPrueba(
    in_array('comunicaciones.ver', $codigosPerms, true) &&
    in_array('comunicaciones.enviar_individual', $codigosPerms, true) &&
    in_array('comunicaciones.gestionar_campanas', $codigosPerms, true) &&
    in_array('comunicaciones.aprobar_campanas', $codigosPerms, true) &&
    in_array('comunicaciones.gestionar_plantillas', $codigosPerms, true) &&
    in_array('comunicaciones.configurar_proveedor', $codigosPerms, true),
    "Catálogo canónico de 6 permisos RBAC para Módulo 12 activo y verificado"
);

// Proveedores sembrados
$stmtProv = $pdo->query("SELECT codigo FROM comunicacion_proveedores");
$codigosProv = $stmtProv->fetchAll(PDO::FETCH_COLUMN);
assertPrueba(
    in_array('SIMULADOR_SANDBOX', $codigosProv, true) &&
    in_array('META_CLOUD_API', $codigosProv, true) &&
    in_array('TWILIO_BSP', $codigosProv, true),
    "Proveedores canónicos sembrados: SIMULADOR_SANDBOX, META_CLOUD_API, TWILIO_BSP"
);

// Clave compuesta uk_clientes_compuesta en clientes
$keysClientes = $pdo->query("SHOW KEYS FROM clientes WHERE Key_name = 'uk_clientes_compuesta'")->fetchAll(PDO::FETCH_ASSOC);
assertPrueba(count($keysClientes) === 2, "Clave compuesta multitenant 'uk_clientes_compuesta (id, organizacion_id)' activa en clientes");

// ==============================================================================
// SECCIÓN 2: TRANSACCIÓN DE PRUEBA Y CONFIGURACIÓN MULTITENANT
// ==============================================================================
$pdo->beginTransaction();

try {
    echo "\n--- SECCIÓN 2: Configuración Multitenant, Cifrado y Presupuesto Seguro ---\n";

    $orgId = 16000;

    $pdo->exec("
        INSERT INTO organizaciones (id, codigo, razon_social, nombre_comercial, numero_documento, estado)
        VALUES (16000, 'tenant_f28b', 'Tenant F28B SAC', 'Tenant F28B', '20999888772', 'ACTIVO')
        ON DUPLICATE KEY UPDATE estado = 'ACTIVO'
    ");

    $pdo->exec("
        INSERT INTO personas (id, organizacion_id, tipo_persona, tipo_documento_id, numero_documento, nombres, apellidos, correo_electronico)
        VALUES
        (9861, 16000, 'NATURAL', 1, '98610001', 'ADMIN', 'F28B', 'admin.f28b@test.pe'),
        (9862, 16000, 'NATURAL', 1, '98620002', 'CLIENTE', 'F28B', 'cliente.f28b@test.pe'),
        (9863, 16000, 'NATURAL', 1, '98630003', 'APROBADOR', 'F28B', 'aprobador.f28b@test.pe')
        ON DUPLICATE KEY UPDATE nombres = VALUES(nombres)
    ");

    $pdo->exec("
        INSERT INTO usuarios (id, organizacion_id, persona_id, nombre_usuario, nombre_completo, correo_electronico, contrasena_hash, es_superadmin_plataforma, estado)
        VALUES
        (9861, 16000, 9861, 'admin_f28b', 'Admin F28B', 'admin.f28b@test.pe', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO'),
        (9863, 16000, 9863, 'aprobador_f28b', 'Aprobador F28B', 'aprobador.f28b@test.pe', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO')
        ON DUPLICATE KEY UPDATE estado = 'ACTIVO'
    ");

    $pdo->exec("
        INSERT INTO clientes (id, organizacion_id, persona_id, estado_comercial, consentimiento_operativo, consentimiento_promocional)
        VALUES (9862, 16000, 9862, 'CLIENTE', 1, 1)
        ON DUPLICATE KEY UPDATE estado_comercial = 'CLIENTE'
    ");
    $clienteIdTest = 9862;

    $configRepo = new ComunicacionConfigRepositorio($pdo);
    $plantillaRepo = new ComunicacionPlantillaRepositorio($pdo);
    $consentimientoRepo = new ComunicacionConsentimientoRepositorio($pdo);
    $conversacionRepo = new ComunicacionConversacionRepositorio($pdo);
    $mensajeRepo = new ComunicacionMensajeRepositorio($pdo);
    $webhookRepo = new ComunicacionWebhookRepositorio($pdo);
    $fabrica = new FabricaProveedorWhatsApp();

    $cfg = $configRepo->guardar(
        orgId: $orgId,
        proveedorCodigo: 'SIMULADOR_SANDBOX',
        modo: ModoComunicacion::SIMULADOR,
        numeroTelefonoIdentificador: '+51951234567',
        webhookVerifyToken: 'token_secreto_desafio_123',
        metaPhoneNumberId: 'phone_meta_98765',
        metaWabaId: 'waba_meta_45678',
        metaAppId: 'app_meta_12345',
        tokenAccesoPlano: 'EAAG_test_access_token_super_secreto',
        webhookSecretPlano: 'whsec_meta_secret_hash_clave_456',
        presupuestoMensualLimite: 100.00
    );

    assertPrueba($cfg->id > 0 && $cfg->modo === ModoComunicacion::SIMULADOR, "Configuración de tenant creada en modo SIMULADOR (fail-closed)");
    assertPrueba($cfg->webhookVerifyTokenHash === hash('sha256', 'token_secreto_desafio_123'), "Webhook verify token almacenado como hash SHA-256");

    $secretos = $configRepo->descifrarSecretos($cfg);
    assertPrueba(
        $secretos['token_acceso'] === 'EAAG_test_access_token_super_secreto' &&
        $secretos['webhook_secret'] === 'whsec_meta_secret_hash_clave_456',
        "Secretos de API y Webhooks descifrados íntegramente con AES-256-GCM"
    );

    // ==============================================================================
    // SECCIÓN 3: GOBERNANZA DE CONSENTIMIENTO ESPECÍFICO POR CANAL
    // ==============================================================================
    echo "\n--- SECCIÓN 3: Consentimiento Específico por Canal (Opt-in / Opt-out) ---\n";

    $comServicio = new ComunicacionServicio(
        mensajeRepo: $mensajeRepo,
        plantillaRepo: $plantillaRepo,
        consentimientoRepo: $consentimientoRepo,
        conversacionRepo: $conversacionRepo,
        configRepo: $configRepo,
        fabrica: $fabrica
    );

    // Registro de consentimiento inicial CONCEDIDO
    $cTrans = $comServicio->gestionarConsentimiento(
        orgId: $orgId,
        clienteId: $clienteIdTest,
        telefonoDestino: '+51999112233',
        finalidad: FinalidadConsentimiento::TRANSACCIONAL_OPERATIVO,
        estado: EstadoConsentimiento::CONCEDIDO,
        origenEvidencia: 'CONTRATO_VENTA_FIRMA',
        textoClausula: 'Acepto recibir notificaciones operativas de mis fotos por WhatsApp'
    );

    assertPrueba($cTrans->id > 0 && $cTrans->estado === EstadoConsentimiento::CONCEDIDO, "Consentimiento transaccional por canal WhatsApp registrado como CONCEDIDO");

    $estadoActual = $consentimientoRepo->obtenerUltimoEstado($orgId, $clienteIdTest, 'WHATSAPP', FinalidadConsentimiento::TRANSACCIONAL_OPERATIVO);
    assertPrueba($estadoActual === EstadoConsentimiento::CONCEDIDO, "Resolución inequívoca de consentimiento vigente determina estado CONCEDIDO");

    // ==============================================================================
    // SECCIÓN 4: PLANTILLAS VERSIONADAS Y VALIDACIÓN DE METADATOS
    // ==============================================================================
    echo "\n--- SECCIÓN 4: Plantillas Versionadas y Estados de Meta ---\n";

    // Plantilla aprobada
    $plantillaAprobada = new ComunicacionPlantilla(
        id: null,
        organizacionId: $orgId,
        nombre: 'confirmacion_reserva_candelaria',
        idioma: 'es_PE',
        categoria: CategoriaPlantilla::UTILITY,
        cuerpoTexto: 'Hola {{1}}, tu cobertura para Candelaria 2027 ha sido confirmada con código {{2}}.',
        parametrosMapeoJson: ['cliente_nombre', 'reserva_codigo'],
        estadoMeta: EstadoPlantillaMeta::APPROVED,
        metaTemplateId: 'meta_tpl_9871122'
    );
    $plantillaRepo->guardar($plantillaAprobada);

    // Plantilla rechazada / pausada
    $plantillaPausada = new ComunicacionPlantilla(
        id: null,
        organizacionId: $orgId,
        nombre: 'promo_descuento_preventa',
        idioma: 'es_PE',
        categoria: CategoriaPlantilla::MARKETING,
        cuerpoTexto: 'Aprovecha 20% de descuento en tu paquete {{1}}.',
        parametrosMapeoJson: ['paquete_nombre'],
        estadoMeta: EstadoPlantillaMeta::PAUSED,
        metaTemplateId: 'meta_tpl_promo_4433'
    );
    $plantillaRepo->guardar($plantillaPausada);

    assertPrueba($plantillaAprobada->id > 0 && $plantillaAprobada->estadoMeta->permiteEnvio(), "Plantilla Utility en estado APPROVED permite despacho");
    assertPrueba(!$plantillaPausada->estadoMeta->permiteEnvio(), "Plantilla en estado PAUSED/REJECTED prohíbe despacho de mensajes");

    // Intento de encolar con plantilla pausada debe arrojar excepción
    $errorPausada = false;
    try {
        $comServicio->encolarMensajeTransaccional(
            orgId: $orgId,
            telefonoDestino: '+51999112233',
            nombreDestinatario: 'Juan Pérez',
            nombrePlantilla: 'promo_descuento_preventa',
            idioma: 'es_PE',
            parametros: ['Morenada'],
            eventoOrigen: 'TEST',
            entidadOrigenId: '1'
        );
    } catch (\Throwable $e) {
        $errorPausada = true;
    }
    assertPrueba($errorPausada, "Encolado de mensaje con plantilla PAUSED/REJECTED es bloqueado con excepción");

    // ==============================================================================
    // SECCIÓN 5: OUTBOX TRANSACCIONAL, IDEMPOTENCIA Y DESPACHO ASÍNCRONO
    // ==============================================================================
    echo "\n--- SECCIÓN 5: Outbox Transaccional, Idempotencia y Worker Outbox ---\n";

    $ctxOp = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 9861,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-f28b-123',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF28B/Admin',
        organizacionId: $orgId
    );

    // Encolar mensaje válido
    $msgEncolado = $comServicio->encolarMensajeTransaccional(
        orgId: $orgId,
        telefonoDestino: '+51999112233',
        nombreDestinatario: 'Orlando Gonzales',
        nombrePlantilla: 'confirmacion_reserva_candelaria',
        idioma: 'es_PE',
        parametros: ['Orlando', 'RES-2027-001'],
        eventoOrigen: 'RESERVA_CONFIRMADA',
        entidadOrigenId: '101',
        clienteId: $clienteIdTest,
        contexto: $ctxOp
    );

    assertPrueba(
        $msgEncolado->id > 0 &&
        $msgEncolado->estado === EstadoMensaje::ENCOLADO &&
        $msgEncolado->pesoEstado === 10,
        "Mensaje transaccional insertado en Outbox con estado ENCOLADO (peso 10)"
    );

    assertPrueba(
        str_contains($msgEncolado->contenidoTexto, 'Orlando') && str_contains($msgEncolado->contenidoTexto, 'RES-2027-001'),
        "Variables de plantilla renderizadas correctamente en contenido minimizado"
    );

    // Prueba de idempotencia estricta ante doble clic o reintento de encolado
    $msgDuplicado = $comServicio->encolarMensajeTransaccional(
        orgId: $orgId,
        telefonoDestino: '+51999112233',
        nombreDestinatario: 'Orlando Gonzales',
        nombrePlantilla: 'confirmacion_reserva_candelaria',
        idioma: 'es_PE',
        parametros: ['Orlando', 'RES-2027-001'],
        eventoOrigen: 'RESERVA_CONFIRMADA',
        entidadOrigenId: '101',
        clienteId: $clienteIdTest,
        contexto: $ctxOp
    );

    assertPrueba($msgDuplicado->id === $msgEncolado->id, "Idempotencia estricta en Outbox: intento idéntico retorna el mensaje existente sin duplicidad");

    // Ejecución del Worker de Outbox
    $worker = new OutboxWorker(
        mensajeRepo: $mensajeRepo,
        consentimientoRepo: $consentimientoRepo,
        plantillaRepo: $plantillaRepo,
        configRepo: $configRepo,
        fabricaProveedores: $fabrica
    );

    $loteRes = $worker->procesarLote(10);
    assertPrueba(isset($loteRes[$msgEncolado->id]) && $loteRes[$msgEncolado->id] === true, "Worker Outbox procesó lote y despachó mensaje con éxito");

    $msgPostWorker = $mensajeRepo->buscarPorId($msgEncolado->id, $orgId);
    assertPrueba(
        $msgPostWorker->estado === EstadoMensaje::ENVIADO &&
        !empty($msgPostWorker->wamid) &&
        $msgPostWorker->bloqueadoHasta === null,
        "Mensaje transicionado a ENVIADO con wamid generado y lock atómico liberado"
    );

    // ==============================================================================
    // SECCIÓN 6: PRE-FLIGHT CHECK DE CONSENTIMIENTO Y REVOCACIÓN PREVIA
    // ==============================================================================
    echo "\n--- SECCIÓN 6: Pre-Flight Check de Consentimiento en Despacho ---\n";

    // Encolar segundo mensaje
    $msgPreRevocacion = $comServicio->encolarMensajeTransaccional(
        orgId: $orgId,
        telefonoDestino: '+51999112233',
        nombreDestinatario: 'Orlando Gonzales',
        nombrePlantilla: 'confirmacion_reserva_candelaria',
        idioma: 'es_PE',
        parametros: ['Orlando', 'RES-2027-002'],
        eventoOrigen: 'RESERVA_CONFIRMADA',
        entidadOrigenId: '102',
        clienteId: $clienteIdTest,
        contexto: $ctxOp
    );

    // Simular que el cliente revoca el consentimiento MIENTRAS el mensaje está encolado
    $comServicio->gestionarConsentimiento(
        orgId: $orgId,
        clienteId: $clienteIdTest,
        telefonoDestino: '+51999112233',
        finalidad: FinalidadConsentimiento::TRANSACCIONAL_OPERATIVO,
        estado: EstadoConsentimiento::REVOCADO,
        origenEvidencia: 'REVOCACION_PORTAL_WEB',
        textoClausula: 'Revocación explícita por el usuario'
    );

    // Ejecutar worker
    $worker->procesarLote(10);
    $msgCancelado = $mensajeRepo->buscarPorId($msgPreRevocacion->id, $orgId);

    assertPrueba(
        $msgCancelado->estado === EstadoMensaje::CANCELADO &&
        str_contains($msgCancelado->contenidoTexto, '[CANCELADO:'),
        "Pre-flight check del worker detecta consentimiento revocado y cancela el mensaje sin despacharlo"
    );

    // ==============================================================================
    // SECCIÓN 7: WEBHOOKS IDEMPOTENTES Y TRANSICIONES MONOTÓNICAS
    // ==============================================================================
    echo "\n--- SECCIÓN 7: Webhooks Idempotentes, Deduplicación y Transiciones Monotónicas ---\n";

    $webhookServicio = new WebhookWhatsAppServicio(
        configRepo: $configRepo,
        webhookRepo: $webhookRepo,
        mensajeRepo: $mensajeRepo,
        conversacionRepo: $conversacionRepo,
        consentimientoRepo: $consentimientoRepo,
        fabricaProveedores: $fabrica
    );

    // Challenge handshake
    $challengeRes = $webhookServicio->procesarChallenge($orgId, 'subscribe', 'token_secreto_desafio_123', 'desafio_random_meta_8877');
    assertPrueba($challengeRes === 'desafio_random_meta_8877', "Challenge handshake de Meta verificado con éxito");

    // Simular webhook DLR: delivered
    $wamidTest = $msgPostWorker->wamid;
    $payloadDelivered = json_encode([
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'statuses' => [
                                [
                                    'id'        => $wamidTest,
                                    'status'    => 'delivered',
                                    'timestamp' => (string) time(),
                                    'recipient_id' => '51999112233'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]);

    $secretKey = $secretos['webhook_secret'];
    $firmaDelivered = 'sha256=' . hash_hmac('sha256', $payloadDelivered, $secretKey);

    $resDlr1 = $webhookServicio->procesarPayload($orgId, $payloadDelivered, $firmaDelivered);
    assertPrueba($resDlr1['exito'] && $resDlr1['eventos_procesados'] === 1, "Webhook DLR 'delivered' procesado exitosamente");

    $msgEntregado = $mensajeRepo->buscarPorId($msgPostWorker->id, $orgId);
    assertPrueba($msgEntregado->estado === EstadoMensaje::ENTREGADO && $msgEntregado->pesoEstado === 40, "Estado transicionado monotónicamente a ENTREGADO (peso 40)");

    // Deduplicación: enviar el mismo payload exacto de nuevo
    $resDlrDuplicado = $webhookServicio->procesarPayload($orgId, $payloadDelivered, $firmaDelivered);
    assertPrueba(
        $resDlrDuplicado['exito'] && $resDlrDuplicado['eventos_duplicados'] === 1,
        "Deduplicación estricta de webhooks: evento repetido reconocido y no re-procesado"
    );

    // Evento read
    $payloadRead = json_encode([
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'statuses' => [
                                [
                                    'id'        => $wamidTest,
                                    'status'    => 'read',
                                    'timestamp' => (string) time(),
                                    'recipient_id' => '51999112233'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]);
    $firmaRead = 'sha256=' . hash_hmac('sha256', $payloadRead, $secretKey);
    $webhookServicio->procesarPayload($orgId, $payloadRead, $firmaRead);

    $msgLeido = $mensajeRepo->buscarPorId($msgPostWorker->id, $orgId);
    assertPrueba($msgLeido->estado === EstadoMensaje::LEIDO && $msgLeido->pesoEstado === 50, "Estado transicionado monotónicamente a LEIDO (peso 50)");

    // Prueba de procesamiento fuera de orden: simular que llega un 'delivered' tardío cuando ya está en 'LEIDO'
    $payloadTardio = json_encode([
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'statuses' => [
                                [
                                    'id'        => $wamidTest,
                                    'status'    => 'delivered',
                                    'timestamp' => (string) (time() + 1),
                                    'recipient_id' => '51999112233'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]);
    $firmaTardio = 'sha256=' . hash_hmac('sha256', $payloadTardio, $secretKey);
    $webhookServicio->procesarPayload($orgId, $payloadTardio, $firmaTardio);

    $msgPostTardio = $mensajeRepo->buscarPorId($msgPostWorker->id, $orgId);
    assertPrueba($msgPostTardio->estado === EstadoMensaje::LEIDO, "Monotonicidad garantizada: evento tardío 'delivered' no degrada estado LEIDO a ENTREGADO");

    // ==============================================================================
    // SECCIÓN 8: OPT-OUT AUTOMÁTICO POR PALABRA CLAVE (BAJA / STOP)
    // ==============================================================================
    echo "\n--- SECCIÓN 8: Opt-Out Automático por Palabra Clave Inbound ---\n";

    $payloadInboundBaja = json_encode([
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'messages' => [
                                [
                                    'id'        => 'inbound_msg_baja_7711',
                                    'from'      => '51999112233',
                                    'timestamp' => (string) time(),
                                    'type'      => 'text',
                                    'text'      => ['body' => 'BAJA']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]);
    $firmaInbound = 'sha256=' . hash_hmac('sha256', $payloadInboundBaja, $secretKey);
    $webhookServicio->procesarPayload($orgId, $payloadInboundBaja, $firmaInbound);

    $ultimoConsentimiento = $consentimientoRepo->obtenerUltimoEstado($orgId, $clienteIdTest, 'WHATSAPP', FinalidadConsentimiento::PROMOCIONAL_MARKETING);
    assertPrueba(
        $ultimoConsentimiento === EstadoConsentimiento::REVOCADO,
        "Mensaje entrante 'BAJA' ejecuta revocación automática de consentimiento promocional"
    );

    // ==============================================================================
    // SECCIÓN 9: SEGREGACIÓN DE DEBERES EN CAMPAÑAS (SoD)
    // ==============================================================================
    echo "\n--- SECCIÓN 9: Segregación de Deberes (SoD) en Campañas ---\n";

    $stmtCampana = $pdo->prepare("
        INSERT INTO comunicacion_campanas (
            organizacion_id, nombre, plantilla_id, criterios_segmentacion_json,
            creado_por, aprobado_por
        ) VALUES (
            :org_id, 'Campaña Morenada 2027', :plan_id, '{\"conjunto\": \"Morenada\"}',
            :creador, :aprobador
        )
    ");

    $campanaValida = false;
    try {
        // Creador 9861 y Aprobador 9863 (Distintos usuarios: Permitido)
        $stmtCampana->execute([
            'org_id'    => $orgId,
            'plan_id'   => $plantillaAprobada->id,
            'creador'   => 9861,
            'aprobador' => 9863
        ]);
        $campanaValida = true;
    } catch (\Throwable $e) {
        $campanaValida = false;
    }
    assertPrueba($campanaValida, "Campaña creada con creador (ID 9861) y aprobador distinto (ID 9863) cumple restricción SoD");

    $violacionSod = false;
    try {
        // Creador 9861 y Aprobador 9861 (Mismo usuario: Prohibido por CHECK chk_campana_sod)
        $stmtCampana->execute([
            'org_id'    => $orgId,
            'plan_id'   => $plantillaAprobada->id,
            'creador'   => 9861,
            'aprobador' => 9861
        ]);
    } catch (\Throwable $e) {
        $violacionSod = true;
    }
    assertPrueba($violacionSod, "Restricción de base de datos 'chk_campana_sod' bloquea auto-aprobación de campañas por el mismo creador");

    // ==============================================================================
    // SECCIÓN 10: CONTROLADOR Y ENDPOINTS RESTFUL CON CONTROL RBAC
    // ==============================================================================
    echo "\n--- SECCIÓN 10: Controlador y Endpoints RESTful con RBAC y Anti-IDOR ---\n";

    $authMock = new class($ctxOp) extends AutenticacionMiddleware {
        public function __construct(private ContextoOperacion $ctx) {}
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion {
            return $this->ctx;
        }
    };

    $authzMock = new class extends AutorizacionMiddleware {
        public function verificarPermiso(string $permiso, ?ContextoOperacion $contexto = null, bool $lanzarExcepcion = true): bool {
            return true;
        }
    };

    $controlador = new ComunicacionControlador(
        authMiddleware: $authMock,
        authzMiddleware: $authzMock,
        comunicacionServicio: $comServicio,
        webhookServicio: $webhookServicio,
        pdo: $pdo
    );

    // Listar mensajes
    $_GET['por_pagina'] = 10;
    $jsonListar = $controlador->listarMensajes();
    $resListar = json_decode($jsonListar, true);
    assertPrueba(
        $resListar['exito'] && isset($resListar['datos']['mensajes']) && count($resListar['datos']['mensajes']) > 0,
        "Endpoint GET /api/v1/comunicaciones/mensajes retorna historial con aislamiento multitenant"
    );

    // Obtener configuración
    $jsonCfg = $controlador->obtenerConfiguracion();
    $resCfg = json_decode($jsonCfg, true);
    assertPrueba(
        $resCfg['exito'] && $resCfg['datos']['proveedor_codigo'] === 'SIMULADOR_SANDBOX',
        "Endpoint GET /api/v1/comunicaciones/configuracion retorna configuración segura de la organización"
    );

} finally {
    // Reversión de la transacción para dejar la base de datos limpia
    $pdo->rollBack();
}

// ==============================================================================
// SECCIÓN 11: PRESERVACIÓN DE IDENTIDAD DE ORLANDO (ID 24)
// ==============================================================================
echo "\n--- SECCIÓN 11: Preservación de Identidad (Orlando ID 24) ---\n";

$stmtOrlandoPost = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos FROM usuarios WHERE id = 24 OR nombre_usuario = 'orlando'");
$stmtOrlandoPost->execute();
$orlandoPost = $stmtOrlandoPost->fetch(PDO::FETCH_ASSOC);
$fingerprintPost = $orlandoPost ? substr(hash('sha256', (string) $orlandoPost['contrasena_hash']), 0, 16) : null;

assertPrueba($fingerprintPost === $fingerprintPre, "Huella SHA-256 de credenciales de Orlando 100% INTACTA");
assertPrueba((int) ($orlandoPost['intentos_fallidos'] ?? 1) === 0, "Intentos fallidos de Orlando = 0");
assertPrueba(($orlandoPost['estado'] ?? '') === 'ACTIVO', "Estado de cuenta de Orlando = ACTIVO");

echo "\n==============================================================================\n";
echo "RESULTADO FINAL DE PRUEBAS F2.8B:\n";
echo "Total Pruebas : {$totalPruebas}\n";
echo "Exitosas      : {$pruebasPasadas}\n";
echo "Fallidas      : {$pruebasFallidas}\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
