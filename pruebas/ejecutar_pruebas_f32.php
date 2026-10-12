<?php

/**
 * ==============================================================================
 * SUITE DE PRUEBAS AUTOMATIZADAS — FASE 3.2: AUTOMATIZACIONES Y WHATSAPP
 * Directiva Técnica DT-V11-009 — CandelariaAPP V1.1
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');

use Aplicacion\Comunicaciones\AutomatizacionRegla;
use Aplicacion\Comunicaciones\CategoriaPlantilla;
use Aplicacion\Comunicaciones\EstadoConsentimiento;
use Aplicacion\Comunicaciones\EstadoMensaje;
use Aplicacion\Comunicaciones\EstadoPlantillaMeta;
use Aplicacion\Comunicaciones\FinalidadConsentimiento;
use Aplicacion\Comunicaciones\ModoComunicacion;
use Aplicacion\Comunicaciones\MotorAutomatizacionesServicio;
use Aplicacion\Comunicaciones\OutboxWorker;
use Aplicacion\Comunicaciones\Proveedores\FabricaProveedorWhatsApp;
use Aplicacion\Entidades\ComunicacionConsentimiento;
use Aplicacion\Entidades\ComunicacionMensaje;
use Aplicacion\Entidades\ComunicacionPlantilla;
use Aplicacion\Repositorios\ComunicacionAutomatizacionRepositorio;
use Aplicacion\Repositorios\ComunicacionCampanaRepositorio;
use Aplicacion\Repositorios\ComunicacionConfigRepositorio;
use Aplicacion\Repositorios\ComunicacionConsentimientoRepositorio;
use Aplicacion\Repositorios\ComunicacionConversacionRepositorio;
use Aplicacion\Repositorios\ComunicacionMensajeRepositorio;
use Aplicacion\Repositorios\ComunicacionPlantillaRepositorio;
use Nucleo\Http\ContextoOperacion;

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dbName = getenv('DB_NAME') ?: 'app_candelaria';

$pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function asertar(bool $condicion, string $mensaje): void
{
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condicion) {
        $passedTests++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$mensaje}\n";
    }
}

echo "==============================================================================\n";
echo "CANDELARIAAPP V1.1 — SUITE FASE 3.2: AUTOMATIZACIONES Y WHATSAPP\n";
echo "Directiva Técnica DT-V11-009\n";
echo "==============================================================================\n\n";

$orgId = 10000;
$orgIdB = 88002;
$usuarioAdminId = 24;
$contexto = new ContextoOperacion(
    actorTipo: 'HUMANO',
    usuarioId: $usuarioAdminId,
    actorSistemaId: null,
    actorSistemaCodigo: null,
    canalId: 1,
    canalCodigo: 'WEB',
    correlacionId: ContextoOperacion::generarCorrelacionId(),
    origenIp: '127.0.0.1',
    agenteUsuario: 'PruebasFase32/1.0',
    organizacionId: $orgId
);

// Repositorios y Servicios
$autRepo = new ComunicacionAutomatizacionRepositorio($pdo);
$plantillaRepo = new ComunicacionPlantillaRepositorio($pdo);
$consentimientoRepo = new ComunicacionConsentimientoRepositorio($pdo);
$conversacionRepo = new ComunicacionConversacionRepositorio($pdo);
$configRepo = new ComunicacionConfigRepositorio($pdo);
$mensajeRepo = new ComunicacionMensajeRepositorio($pdo);
$fabrica = new FabricaProveedorWhatsApp();

// Asegurar configuración de comunicaciones activa para tenant 10000
$cfg = $configRepo->obtenerPorOrganizacion($orgId);
if (!$cfg) {
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
}

$motor = new MotorAutomatizacionesServicio(
    autRepo: $autRepo,
    plantillaRepo: $plantillaRepo,
    consentimientoRepo: $consentimientoRepo,
    conversacionRepo: $conversacionRepo,
    configRepo: $configRepo,
    mensajeRepo: $mensajeRepo
);

$worker = new OutboxWorker(
    mensajeRepo: $mensajeRepo,
    consentimientoRepo: $consentimientoRepo,
    plantillaRepo: $plantillaRepo,
    configRepo: $configRepo,
    fabricaProveedores: $fabrica
);

// ------------------------------------------------------------------------------
// SECCIÓN 1: ESQUEMA Y RBAC (MIGRACIÓN 27)
// ------------------------------------------------------------------------------
echo "--- 1. ESQUEMA Y RBAC (MIGRACIÓN 27) ---\n";

// 1.1 Tabla comunicacion_automatizacion_reglas
$stmtCol = $pdo->query("DESCRIBE comunicacion_automatizacion_reglas");
$columnas = $stmtCol->fetchAll(PDO::FETCH_COLUMN);
asertar(in_array('evento_disparador', $columnas), "Tabla comunicacion_automatizacion_reglas contiene columna evento_disparador");
asertar(in_array('plantilla_id', $columnas), "Tabla comunicacion_automatizacion_reglas contiene columna plantilla_id");
asertar(in_array('minutos_diferidos', $columnas), "Tabla comunicacion_automatizacion_reglas contiene columna minutos_diferidos");
asertar(in_array('condiciones_json', $columnas), "Tabla comunicacion_automatizacion_reglas contiene columna condiciones_json");
asertar(in_array('mapeo_parametros_json', $columnas), "Tabla comunicacion_automatizacion_reglas contiene columna mapeo_parametros_json");

// 1.2 Columna automatizacion_regla_id en comunicacion_mensajes
$stmtMsgCol = $pdo->query("DESCRIBE comunicacion_mensajes");
$colsMsg = $stmtMsgCol->fetchAll(PDO::FETCH_COLUMN);
asertar(in_array('automatizacion_regla_id', $colsMsg), "Tabla comunicacion_mensajes contiene columna automatizacion_regla_id con FK");

// 1.3 Permiso 113 RBAC
$stmtPerm = $pdo->prepare("SELECT * FROM permisos WHERE codigo = 'comunicaciones.automatizaciones.gestionar'");
$stmtPerm->execute();
$permiso = $stmtPerm->fetch();
asertar($permiso !== false, "Permiso 'comunicaciones.automatizaciones.gestionar' registrado en catálogo de permisos");

$stmtRolPerm = $pdo->prepare("SELECT COUNT(*) FROM rol_permisos WHERE permiso_id = :pid");
$stmtRolPerm->execute(['pid' => $permiso['id']]);
$totalRoles = (int)$stmtRolPerm->fetchColumn();
asertar($totalRoles >= 2, "Permiso de automatizaciones asignado a roles de administración");

// Limpieza idempotente de fixtures de pruebas previas
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM comunicacion_mensajes WHERE organizacion_id = {$orgId} AND (destinatario_telefono LIKE '%999888' OR destinatario_telefono LIKE '%777111')");
$pdo->exec("DELETE FROM comunicacion_automatizacion_reglas WHERE organizacion_id = {$orgId} AND codigo IN ('AUT_TEST_EVAL', 'AUT_TEST_REVOC', 'AUT_TURNO_RECORDATORIO')");
$pdo->exec("DELETE FROM comunicacion_plantillas WHERE organizacion_id = {$orgId} AND nombre IN ('confirmacion_reserva_f32', 'turno_folclore_f32')");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// ------------------------------------------------------------------------------
// SECCIÓN 2: PLANTILLAS OFICIALES Y ENTIDAD AUTOMATIZACIONREGLA
// ------------------------------------------------------------------------------
echo "\n--- 2. ENTIDAD AUTOMATIZACIONREGLA Y EVALUACIÓN DE CONDICIONES ---\n";

// Crear plantilla de prueba para confirmación de reserva
$plantillaReserva = new ComunicacionPlantilla(
    id: null,
    organizacionId: $orgId,
    nombre: 'confirmacion_reserva_f32',
    idioma: 'es_PE',
    categoria: CategoriaPlantilla::UTILITY,
    cuerpoTexto: 'Hola {{1}}, tu reserva {{2}} ha sido confirmada por un total de {{3}}. ¡Te esperamos en Puno!',
    parametrosMapeoJson: ['1' => 'nombre', '2' => 'codigo', '3' => 'monto'],
    estadoMeta: EstadoPlantillaMeta::APPROVED,
    activo: true
);
$plantillaGuardada = $plantillaRepo->guardar($plantillaReserva);
asertar($plantillaGuardada->id > 0, "Plantilla oficial de prueba guardada exitosamente (ID: {$plantillaGuardada->id})");

// Evaluar condiciones de entidad
$reglaEntidad = new AutomatizacionRegla(
    id: null,
    organizacionId: $orgId,
    codigo: 'AUT_TEST_EVAL',
    nombre: 'Regla Evaluación',
    descripcion: 'Prueba de condiciones',
    eventoDisparador: 'reserva.confirmada',
    plantillaId: $plantillaGuardada->id,
    tipoComunicacion: 'TRANSACCIONAL',
    minutosDiferidos: 0,
    condicionesJson: [
        'estado' => 'CONFIRMADA',
        'monto'  => ['gte' => 100]
    ],
    mapeoParametrosJson: [
        '1' => 'cliente_nombre',
        '2' => 'codigo_reserva',
        '3' => 'monto_formateado'
    ]
);

asertar($reglaEntidad->cumpleCondiciones(['estado' => 'CONFIRMADA', 'monto' => 150]), "cumpleCondiciones aprueba cuando payload cumple estado y monto >= 100");
asertar(!$reglaEntidad->cumpleCondiciones(['estado' => 'PENDIENTE', 'monto' => 150]), "cumpleCondiciones rechaza cuando estado no coincide");
asertar(!$reglaEntidad->cumpleCondiciones(['estado' => 'CONFIRMADA', 'monto' => 50]), "cumpleCondiciones rechaza cuando monto es inferior a 100");

$mapeo = $reglaEntidad->mapearParametros([
    'cliente_nombre'   => 'Juan Pérez',
    'codigo_reserva'   => 'RES-2027-001',
    'monto_formateado' => 'S/ 250.00'
]);
asertar($mapeo['1'] === 'Juan Pérez' && $mapeo['2'] === 'RES-2027-001' && $mapeo['3'] === 'S/ 250.00', "mapearParametros transforma claves a placeholders numéricos Meta");

// ------------------------------------------------------------------------------
// SECCIÓN 3: REPOSITORIO DE AUTOMATIZACIONES Y CRUD MULTITENANT
// ------------------------------------------------------------------------------
echo "\n--- 3. REPOSITORIO DE AUTOMATIZACIONES Y CRUD MULTITENANT ---\n";

// Guardar regla en BD
$reglaGuardada = $autRepo->guardar($reglaEntidad);
asertar($reglaGuardada->id > 0, "Regla de automatización persistida en BD con ID {$reglaGuardada->id}");

// Buscar por ID y por Código
$recuperada = $autRepo->buscarPorId($reglaGuardada->id, $orgId);
asertar($recuperada !== null && $recuperada->codigo === 'AUT_TEST_EVAL', "buscarPorId recupera la regla correctamente con tipos hidratados");

$porCodigo = $autRepo->buscarPorCodigo('AUT_TEST_EVAL', $orgId);
asertar($porCodigo !== null && $porCodigo->id === $reglaGuardada->id, "buscarPorCodigo localiza unívocamente la regla por tenant");

// Aislamiento Multitenant
$porOtroTenant = $autRepo->buscarPorId($reglaGuardada->id, $orgIdB);
asertar($porOtroTenant === null, "Aislamiento Multitenant: Tenant B no puede acceder a regla de Tenant A");

// Actualizar regla
$reglaGuardada->nombre = 'Regla Evaluación Actualizada';
$reglaGuardada->minutosDiferidos = 10;
$actualizado = $autRepo->actualizar($reglaGuardada);
asertar($actualizado === true, "actualizar() modifica propiedades de la regla satisfactoriamente");

$recuperadaPostUpdate = $autRepo->buscarPorId($reglaGuardada->id, $orgId);
asertar($recuperadaPostUpdate->minutosDiferidos === 10, "Regla actualizada preserva minutos diferidos = 10");

// Desactivar y activar
$autRepo->cambiarEstadoActivo($reglaGuardada->id, $orgId, false);
$inactiva = $autRepo->buscarPorId($reglaGuardada->id, $orgId);
asertar($inactiva->activo === false, "cambiarEstadoActivo(false) desactiva la regla");

$activasPorEvento = $autRepo->listarActivasPorEvento($orgId, 'reserva.confirmada');
$estaPresente = false;
foreach ($activasPorEvento as $r) {
    if ($r->id === $reglaGuardada->id) $estaPresente = true;
}
asertar(!$estaPresente, "listarActivasPorEvento omite reglas inactivas");

// Reactivar
$autRepo->cambiarEstadoActivo($reglaGuardada->id, $orgId, true);
$reactivada = $autRepo->buscarPorId($reglaGuardada->id, $orgId);
asertar($reactivada->activo === true, "cambiarEstadoActivo(true) reactiva la regla");

// ------------------------------------------------------------------------------
// SECCIÓN 4: MOTOR DE DISPARADORES Y GESTIÓN DE CONSENTIMIENTO
// ------------------------------------------------------------------------------
echo "\n--- 4. MOTOR DE DISPARADORES Y CONSENTIMIENTO LEGAL ---\n";

// Configurar consentimiento para cliente de prueba (ID 71038)
$clienteTestId = 71038;
$telefonoTest = '+51951999888';

// Registrar consentimiento transaccional
$consentimiento = new ComunicacionConsentimiento(
    id: null,
    organizacionId: $orgId,
    clienteId: $clienteTestId,
    canal: 'WHATSAPP',
    telefonoDestino: $telefonoTest,
    finalidad: FinalidadConsentimiento::TRANSACCIONAL_OPERATIVO,
    estado: EstadoConsentimiento::CONCEDIDO,
    origenEvidencia: 'PORTAL_PUBLICO_CHECKOUT',
    correlacionId: ContextoOperacion::generarCorrelacionId(),
    direccionIpRegistro: '127.0.0.1'
);
$consentimientoRepo->guardar($consentimiento);

// 4.1 Disparo exitoso
$payloadReserva = [
    'destinatario_telefono' => $telefonoTest,
    'destinatario_nombre'   => 'Carlos Mamani',
    'cliente_id'            => $clienteTestId,
    'reserva_id'            => 5001,
    'cliente_nombre'        => 'Carlos Mamani',
    'codigo_reserva'        => 'RES-2027-0050',
    'monto_formateado'      => 'S/ 350.00',
    'estado'                => 'CONFIRMADA',
    'monto'                 => 350
];

$resultadoDisparo = $motor->dispararEvento($orgId, 'reserva.confirmada', $payloadReserva, $contexto);
asertar($resultadoDisparo['exito'] === true, "dispararEvento retorna exito = true");
asertar($resultadoDisparo['total_disparados'] === 1, "dispararEvento encola exactamente 1 mensaje para la regla coincidente");

$disparadoInfo = $resultadoDisparo['disparados'][0];
asertar(!empty($disparadoInfo['mensaje_id']), "Mensaje Outbox generado con ID válido ({$disparadoInfo['mensaje_id']})");
asertar(!empty($disparadoInfo['idempotency_key']), "Idempotency key determinista asignada al mensaje");

// Verificar mensaje en Outbox
$mensajeEncolado = $mensajeRepo->buscarPorId($disparadoInfo['mensaje_id'], $orgId);
asertar($mensajeEncolado !== null, "Mensaje recuperado de comunicacion_mensajes");
asertar($mensajeEncolado->automatizacionReglaId === $reglaGuardada->id, "Mensaje registra automatizacion_regla_id vinculada");
asertar($mensajeEncolado->estado === EstadoMensaje::ENCOLADO, "Mensaje nace en estado ENCOLADO");
asertar(str_contains($mensajeEncolado->contenidoTexto, 'RES-2027-0050'), "Contenido del mensaje renderizó variables de la reserva");

// 4.2 Despacho Outbox Worker con consentimiento activo
// Asegurar que el mensaje encolado previo tenga proximo_intento_en inmediato
$pdo->prepare("UPDATE comunicacion_mensajes SET proximo_intento_en = NOW() WHERE id = :id")
    ->execute(['id' => $disparadoInfo['mensaje_id']]);

$despachados = $worker->procesarLote(10);
asertar(isset($despachados[$disparadoInfo['mensaje_id']]), "OutboxWorker reservó y procesó el mensaje automatizado");
asertar($despachados[$disparadoInfo['mensaje_id']] === true, "OutboxWorker reportó despacho exitoso vía SimuladorWhatsAppAdaptador");

// Verificar estado final del mensaje
$mensajeDespachado = $mensajeRepo->buscarPorId($disparadoInfo['mensaje_id'], $orgId);
asertar($mensajeDespachado->estado === EstadoMensaje::ENVIADO, "Mensaje avanzó a estado ENVIADO (peso: {$mensajeDespachado->pesoEstado})");
asertar(!empty($mensajeDespachado->wamid), "Mensaje cuenta con WAMID generado por el adaptador: {$mensajeDespachado->wamid}");
asertar($mensajeDespachado->intentosRealizados === 1, "Mensaje registra exactamente 1 intento realizado");

// 4.3 Idempotencia estricta: reintento de disparo no genera duplicado
$resultadoReintento = $motor->dispararEvento($orgId, 'reserva.confirmada', $payloadReserva, $contexto);
asertar($resultadoReintento['total_disparados'] === 0, "Idempotencia: Segundo disparo no crea nuevo mensaje encolado");
asertar(count($resultadoReintento['omitidos']) === 1, "Segundo disparo registra 1 omisión");
asertar($resultadoReintento['omitidos'][0]['motivo'] === 'DUPLICADO_IDEMPOTENTE', "Motivo de omisión es DUPLICADO_IDEMPOTENTE");

// 4.4 Revocación de consentimiento bloquea nuevos disparos
$consentimientoRevocado = new ComunicacionConsentimiento(
    id: null,
    organizacionId: $orgId,
    clienteId: $clienteTestId,
    canal: 'WHATSAPP',
    telefonoDestino: $telefonoTest,
    finalidad: FinalidadConsentimiento::TRANSACCIONAL_OPERATIVO,
    estado: EstadoConsentimiento::REVOCADO,
    origenEvidencia: 'PORTAL_PUBLICO_REVOCACION',
    correlacionId: ContextoOperacion::generarCorrelacionId(),
    direccionIpRegistro: '127.0.0.1'
);
$consentimientoRepo->guardar($consentimientoRevocado);

// Crear nueva regla para probar bloqueo por consentimiento revocado
$reglaRevocada = new AutomatizacionRegla(
    id: null,
    organizacionId: $orgId,
    codigo: 'AUT_TEST_REVOC',
    nombre: 'Regla Test Revocada',
    descripcion: 'Debe fallar si consentimiento revocado',
    eventoDisparador: 'reserva.cancelada',
    plantillaId: $plantillaGuardada->id,
    tipoComunicacion: 'TRANSACCIONAL',
    minutosDiferidos: 0,
    mapeoParametrosJson: ['1' => 'cliente_nombre', '2' => 'codigo_reserva', '3' => 'monto_formateado']
);
$autRepo->guardar($reglaRevocada);

$payloadCancelada = [
    'destinatario_telefono' => $telefonoTest,
    'destinatario_nombre'   => 'Carlos Mamani',
    'cliente_id'            => $clienteTestId,
    'reserva_id'            => 5002,
    'cliente_nombre'        => 'Carlos Mamani',
    'codigo_reserva'        => 'RES-2027-0052',
    'monto_formateado'      => 'S/ 0.00',
    'estado'                => 'CANCELADA'
];

$resRevocado = $motor->dispararEvento($orgId, 'reserva.cancelada', $payloadCancelada, $contexto);
asertar($resRevocado['total_disparados'] === 0, "Consentimiento revocado bloquea generación de mensaje");
asertar($resRevocado['omitidos'][0]['motivo'] === 'SIN_CONSENTIMIENTO_VALIDO', "Motivo de omisión acredita SIN_CONSENTIMIENTO_VALIDO");

// ------------------------------------------------------------------------------
// SECCIÓN 6: SIMULADOR PRE-FLIGHT
// ------------------------------------------------------------------------------
echo "\n--- 6. SIMULACIÓN PRE-FLIGHT DE AUTOMATIZACIONES ---\n";

$simulacion = $motor->probarRegla($orgId, $reglaGuardada->id, [
    'destinatario_telefono' => '+51987654321',
    'cliente_nombre'        => 'Ana Torres',
    'codigo_reserva'        => 'RES-2027-SIM',
    'monto_formateado'      => 'S/ 480.00',
    'estado'                => 'CONFIRMADA',
    'monto'                 => 480
]);

asertar($simulacion['cumple_condiciones'] === true, "Pre-flight: evalúa cumple_condiciones = true");
asertar(str_contains($simulacion['texto_renderizado'], 'Ana Torres'), "Pre-flight: renderiza nombre en vista previa");
asertar(str_contains($simulacion['texto_renderizado'], 'RES-2027-SIM'), "Pre-flight: renderiza código de reserva en vista previa");
asertar(!empty($simulacion['idempotency_key_prevista']), "Pre-flight: genera preview de idempotency key determinista");

// ------------------------------------------------------------------------------
// SECCIÓN 7: EVENTO DE TURNO FOLCLÓRICO
// ------------------------------------------------------------------------------
echo "\n--- 7. AUTOMATIZACIÓN DE EVENTOS FOLCLÓRICOS ---\n";

$plantillaFolclore = new ComunicacionPlantilla(
    id: null,
    organizacionId: $orgId,
    nombre: 'turno_folclore_f32',
    idioma: 'es_PE',
    categoria: CategoriaPlantilla::UTILITY,
    cuerpoTexto: 'Atención {{1}}: Su turno oficial de presentación para {{2}} ha sido programado a las {{3}} en {{4}}.',
    parametrosMapeoJson: ['1' => 'destinatario', '2' => 'conjunto', '3' => 'hora', '4' => 'lugar'],
    estadoMeta: EstadoPlantillaMeta::APPROVED,
    activo: true
);
$plantillaFolcloreGuardada = $plantillaRepo->guardar($plantillaFolclore);

$reglaFolclore = new AutomatizacionRegla(
    id: null,
    organizacionId: $orgId,
    codigo: 'AUT_TURNO_RECORDATORIO',
    nombre: 'Aviso Turno Folclórico',
    descripcion: 'Notificación de horario y punto de partida',
    eventoDisparador: 'folclore.turno.recordatorio',
    plantillaId: $plantillaFolcloreGuardada->id,
    tipoComunicacion: 'TRANSACCIONAL',
    minutosDiferidos: 0,
    mapeoParametrosJson: [
        '1' => 'destinatario_nombre',
        '2' => 'conjunto_nombre',
        '3' => 'hora_partida',
        '4' => 'punto_partida'
    ]
);
$autRepo->guardar($reglaFolclore);

$payloadTurno = [
    'destinatario_telefono' => '+51951777111',
    'destinatario_nombre'   => 'Directiva Diablada Bellavista',
    'conjunto_nombre'       => 'Conjunto Tradicional Diablada Bellavista',
    'hora_partida'          => '11:15 AM',
    'punto_partida'         => 'Esquina Jirón Lima / Deustua',
    'presentacion_id'       => 88
];

$resTurno = $motor->dispararEvento($orgId, 'folclore.turno.recordatorio', $payloadTurno, $contexto);
asertar($resTurno['total_disparados'] === 1, "Evento folclore.turno.recordatorio dispara mensaje correctamente");
$msgFolclore = $mensajeRepo->buscarPorId($resTurno['disparados'][0]['mensaje_id'], $orgId);
asertar(str_contains($msgFolclore->contenidoTexto, 'Diablada Bellavista'), "Mensaje folclórico incluye nombre del conjunto");
asertar(str_contains($msgFolclore->contenidoTexto, '11:15 AM'), "Mensaje folclórico incluye hora oficial de partida");

// ------------------------------------------------------------------------------
// SECCIÓN 8: RESUMEN DE RESULTADOS
// ------------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo "RESULTADOS FASE 3.2: {$totalTests} PRUEBAS EVALUADAS | {$passedTests} PASS | {$failedTests} FAIL\n";
echo "==============================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
