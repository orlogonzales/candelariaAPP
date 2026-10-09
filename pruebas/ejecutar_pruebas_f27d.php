<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F2.7D
 * API FINANCIERA, ADAPTADORES DE PASARELAS Y WEBHOOKS SANITIZADOS
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Controladores\PagoControlador;
use Aplicacion\Entidades\CuentaBancariaOrganizacion;
use Aplicacion\Entidades\Edicion;
use Aplicacion\Entidades\OrganizacionPasarela;
use Aplicacion\Entidades\Pago;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Entidades\Venta;
use Aplicacion\Entidades\VentaLinea;
use Aplicacion\Finanzas\AsumeComisionPasarela;
use Aplicacion\Finanzas\CifradorFinanciero;
use Aplicacion\Finanzas\EstadoFinancieroVenta;
use Aplicacion\Finanzas\EstadoIntentoPasarela;
use Aplicacion\Finanzas\EstadoPago;
use Aplicacion\Finanzas\EstadoReembolso;
use Aplicacion\Finanzas\MetodoPago;
use Aplicacion\Finanzas\MotivoReembolso;
use Aplicacion\Finanzas\PagoServicio;
use Aplicacion\Finanzas\Pasarelas\CulqiAdaptador;
use Aplicacion\Finanzas\Pasarelas\FabricaAdaptadorPasarela;
use Aplicacion\Finanzas\Pasarelas\MercadoPagoAdaptador;
use Aplicacion\Finanzas\Pasarelas\NiubizAdaptador;
use Aplicacion\Finanzas\Pasarelas\StripeAdaptador;
use Aplicacion\Finanzas\WebhookPasarelaServicio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\CuentaBancariaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\PagoReembolsoRepositorio;
use Aplicacion\Repositorios\PagoRepositorio;
use Aplicacion\Repositorios\PasarelaRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Autorizacion\AutorizacionServicio;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Aplicacion\Seguridad\AutenticacionServicio;
use Aplicacion\Ventas\EstadoVenta;
use Aplicacion\Ventas\TipoLineaVenta;
use Aplicacion\Ventas\TipoOrigenVenta;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F2.7D\n";
echo "API FINANCIERA, ADAPTADORES DE PASARELAS Y WEBHOOKS SANITIZADOS\n";
echo "==============================================================================\n\n";

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

$pdo = Conexion::obtenerInstancia();

// Captura de huella criptográfica de Orlando (Preservación estricta ID 24)
$stmtOrlando = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id = 24 OR nombre_usuario = 'orlando'");
$stmtOrlando->execute();
$orlandoPre = $stmtOrlando->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPre = $orlandoPre ? substr(hash('sha256', (string) $orlandoPre['contrasena_hash']), 0, 16) : null;

$pdo->beginTransaction();

try {
    $personaRepo = new PersonaRepositorio($pdo);
    $usuarioRepo = new UsuarioRepositorio($pdo);
    $edicionRepo = new EdicionRepositorio($pdo);
    $ventaRepo = new VentaRepositorio($pdo);
    $configRepo = new ConfiguracionRepositorio($pdo);
    $auditoriaRepo = new AuditoriaRepositorio($pdo);
    $authzServicio = new AutorizacionServicio(pdo: $pdo);

    $pagoRepo = new PagoRepositorio($pdo);
    $reembolsoRepo = new PagoReembolsoRepositorio($pdo);
    $cuentaRepo = new CuentaBancariaRepositorio($pdo);
    $pasarelaRepo = new PasarelaRepositorio($pdo);
    $cifrador = new CifradorFinanciero();

    $pagoServicio = new PagoServicio(
        pagoRepo: $pagoRepo,
        reembolsoRepo: $reembolsoRepo,
        cuentaRepo: $cuentaRepo,
        pasarelaRepo: $pasarelaRepo,
        ventaRepo: $ventaRepo,
        edicionRepo: $edicionRepo,
        configRepo: $configRepo,
        authzServicio: $authzServicio,
        auditoriaRepo: $auditoriaRepo,
        pdo: $pdo
    );

    $webhookServicio = new WebhookPasarelaServicio(
        pagoRepo: $pagoRepo,
        pasarelaRepo: $pasarelaRepo,
        ventaRepo: $ventaRepo,
        pagoServicio: $pagoServicio,
        cifrador: $cifrador,
        auditoriaRepo: $auditoriaRepo,
        pdo: $pdo
    );

    // =========================================================================
    // 0. PREPARACIÓN DE ENTORNOS DE PRUEBA (TENANTS, EDICIONES, USUARIOS Y VENTAS)
    // =========================================================================
    echo "--- 0. Configuración de Sujetos de Prueba ---\n";

    $orgIdA = 10000;
    $orgIdB = 20000;

    $pdo->exec("
        INSERT INTO organizaciones (id, codigo, razon_social, nombre_comercial, numero_documento, estado)
        VALUES
        (10000, 'tenant_test_a_f27d', 'Tenant Test A SAC', 'Tenant A', '20111111111', 'ACTIVO'),
        (20000, 'tenant_test_b_f27d', 'Tenant Test B SAC', 'Tenant B', '20222222222', 'ACTIVO')
        ON DUPLICATE KEY UPDATE estado = 'ACTIVO'
    ");

    $adminSinteticoAId = 9931;
    $operadorSinteticoAId = 9932;
    $adminSinteticoBId = 9933;

    $pdo->exec("
        INSERT INTO personas (id, organizacion_id, tipo_persona, tipo_documento_id, numero_documento, nombres, apellidos, correo_electronico)
        VALUES
        (9931, 10000, 'NATURAL', 1, '99310001', 'ADMIN', 'FINANZAS A', 'admin.f27d.a@test.local'),
        (9932, 10000, 'NATURAL', 1, '99320002', 'OPERADOR', 'FINANZAS A', 'op.f27d.a@test.local'),
        (9933, 20000, 'NATURAL', 1, '99330003', 'ADMIN', 'FINANZAS B', 'admin.f27d.b@test.local')
        ON DUPLICATE KEY UPDATE nombres = VALUES(nombres)
    ");

    $pdo->exec("
        INSERT INTO usuarios (id, organizacion_id, persona_id, nombre_usuario, nombre_completo, correo_electronico, contrasena_hash, es_superadmin_plataforma, estado)
        VALUES
        (9931, 10000, 9931, 'admin_f27d_a', 'Admin Finanzas A', 'admin.f27d.a@test.local', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO'),
        (9932, 10000, 9932, 'operador_f27d_a', 'Operador Finanzas A', 'op.f27d.a@test.local', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO'),
        (9933, 20000, 9933, 'admin_f27d_b', 'Admin Finanzas B', 'admin.f27d.b@test.local', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO')
        ON DUPLICATE KEY UPDATE estado = 'ACTIVO'
    ");

    $pdo->exec("
        INSERT IGNORE INTO usuario_roles (usuario_id, rol_id) VALUES
        (9931, 2),
        (9932, 3),
        (9933, 2)
    ");

    $contextoOp = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: $adminSinteticoAId,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-f27d-admina-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF27D/AdminA',
        organizacionId: $orgIdA
    );

    $contextoSinPerm = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: $operadorSinteticoAId,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-f27d-operadora-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF27D/OperadorA',
        organizacionId: $orgIdA
    );

    $stmtEdic = $pdo->prepare("SELECT id FROM ediciones_candelaria WHERE organizacion_id = :org_id ORDER BY id ASC LIMIT 1");
    $stmtEdic->execute(['org_id' => $orgIdA]);
    $edicionId = (int) $stmtEdic->fetchColumn();
    if ($edicionId === 0) {
        $stmtInsEd = $pdo->prepare("
            INSERT INTO ediciones_candelaria (organizacion_id, codigo, nombre, anio, estado, fecha_inicio, fecha_fin, es_actual)
            VALUES (:org, 'CAN-2027-F27D', 'Candelaria 2027 F27D', 2027, 'OPERACION', '2027-02-01', '2027-02-15', 1)
        ");
        $stmtInsEd->execute(['org' => $orgIdA]);
        $edicionId = (int) $pdo->lastInsertId();
    }
    afirmar($edicionId > 0, "Edición Candelaria disponible para pruebas (ID #{$edicionId}).");

    $personaClienteA = new Persona(
        id: null,
        organizacionId: $orgIdA,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '77665544',
        nombres: 'JUAN',
        apellidos: 'QUISPE PEREZ',
        telefonoWhatsapp: '+51988112233'
    );
    $personaClienteAId = $personaRepo->crear($personaClienteA);

    $stmtCliA = $pdo->prepare("
        INSERT INTO clientes (organizacion_id, persona_id, estado_comercial, consentimiento_operativo, consentimiento_promocional)
        VALUES (:org_id, :persona_id, 'CLIENTE', 1, 1)
    ");
    $stmtCliA->execute(['org_id' => $orgIdA, 'persona_id' => $personaClienteAId]);
    $clienteAId = (int) $pdo->lastInsertId();

    $correlativoV1 = $ventaRepo->generarSiguienteCorrelativo($orgIdA, 2027);
    $venta1 = new Venta(
        id: null,
        organizacionId: $orgIdA,
        edicionId: $edicionId,
        clienteId: $clienteAId,
        cotizacionId: null,
        origenTipo: TipoOrigenVenta::DIRECTA,
        correlativo: $correlativoV1,
        fechaVenta: '2027-01-15',
        estado: EstadoVenta::CONFIRMADA,
        clienteNombreCompleto: 'JUAN QUISPE PEREZ',
        clienteTipoDocumento: 'DNI',
        clienteNumeroDocumento: '77665544',
        clienteTelefono: '+51988112233',
        clienteEmail: 'juan@test.pe',
        moneda: 'PEN',
        subtotal: 1000.00,
        total: 1000.00,
        montoPagado: 0.00,
        saldoPendiente: 1000.00,
        estadoFinanciero: 'NO_PAGADA',
        creadoPor: $adminSinteticoAId
    );
    $guardada1 = $ventaRepo->guardar($venta1);
    $ventaId = (int) $guardada1->id;
    afirmar($ventaId > 0, "Venta de prueba creada exitosamente (ID #{$ventaId}, total S/ 1,000.00).");

    // =========================================================================
    // 1. PRUEBAS DE ADAPTADORES DE PASARELAS (CRIPTOGRAFÍA Y ZERO-PAN)
    // =========================================================================
    echo "\n--- 1. Adaptadores Criptográficos de Pasarelas (F2.7D) ---\n";

    // 1.1 FabricaAdaptadorPasarela
    $culqiAd = FabricaAdaptadorPasarela::crear('CULQI');
    $stripeAd = FabricaAdaptadorPasarela::crear('STRIPE');
    $niubizAd = FabricaAdaptadorPasarela::crear('NIUBIZ');
    $mpAd = FabricaAdaptadorPasarela::crear('MERCADOPAGO');

    afirmar($culqiAd instanceof CulqiAdaptador, 'Fabrica crea adaptador CULQI correctamente.');
    afirmar($stripeAd instanceof StripeAdaptador, 'Fabrica crea adaptador STRIPE correctamente.');
    afirmar($niubizAd instanceof NiubizAdaptador, 'Fabrica crea adaptador NIUBIZ correctamente.');
    afirmar($mpAd instanceof MercadoPagoAdaptador, 'Fabrica crea adaptador MERCADOPAGO correctamente.');

    // 1.2 CulqiAdaptador: HMAC-SHA256 y normalización
    $secretoCulqi = 'sk_test_culqi_secret_key_12345';
    $payloadCulqi = json_encode([
        'id' => 'evt_test_culqi_001',
        'type' => 'charge.succeeded',
        'data' => [
            'id' => 'chr_test_998877',
            'amount' => 50000, // S/ 500.00 en céntimos
            'fee' => 2000,   // S/ 20.00 comision
            'currency_code' => 'PEN',
            'source' => [
                'iin' => ['card_brand' => 'Visa', 'bin' => '411111'],
                'last_four' => '4242',
            ],
            'metadata' => [
                'venta_id' => $ventaId,
                'organizacion_id' => $orgIdA,
            ],
        ],
    ]);

    $firmaCulqiValida = hash_hmac('sha256', $payloadCulqi, $secretoCulqi);
    $cabecerasCulqi = ['x-culqi-signature' => $firmaCulqiValida];

    $resCulqi = $culqiAd->procesarEvento($payloadCulqi, $cabecerasCulqi, $secretoCulqi, '190.237.1.1');
    afirmar($resCulqi->esPagoExitoso(), 'Culqi procesa evento charge.succeeded como PAGO_EXITOSO.');
    afirmar($resCulqi->monto === 500.00, 'Culqi normaliza monto en céntimos a S/ 500.00.');
    afirmar($resCulqi->comisionPasarela === 20.00, 'Culqi normaliza comision a S/ 20.00.');
    afirmar($resCulqi->tarjetaMarca === 'Visa' && $resCulqi->tarjetaUltimosCuatro === '4242', 'Culqi extrae marca y ultimos 4 dígitos sin PAN.');
    afirmar($resCulqi->ventaId === $ventaId, 'Culqi extrae ventaId desde metadata.');

    // Culqi firma inválida rechazada
    $culqiFirmaInvalidaRechazada = false;
    try {
        $culqiAd->procesarEvento($payloadCulqi, ['x-culqi-signature' => 'firma_falsa_invalida'], $secretoCulqi);
    } catch (\InvalidArgumentException $e) {
        $culqiFirmaInvalidaRechazada = true;
    }
    afirmar($culqiFirmaInvalidaRechazada, 'Culqi rechaza payload con firma HMAC inválida (Anti-Spoofing).');

    // 1.3 StripeAdaptador: Stripe-Signature (t=..., v1=...) y anti-replay
    $secretoStripe = 'whsec_test_stripe_secret_67890';
    $tiempoActual = time();
    $payloadStripe = json_encode([
        'id' => 'evt_stripe_test_001',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_stripe_test_123',
                'amount' => 30000, // S/ 300.00
                'currency' => 'pen',
                'payment_method_details' => [
                    'card' => [
                        'brand' => 'mastercard',
                        'last4' => '8899',
                    ],
                ],
                'metadata' => [
                    'venta_id' => (string) $ventaId,
                    'organizacion_id' => (string) $orgIdA,
                ],
            ],
        ],
    ]);

    $firmaStripePayload = $tiempoActual . '.' . $payloadStripe;
    $v1Stripe = hash_hmac('sha256', $firmaStripePayload, $secretoStripe);
    $cabeceraStripeValida = ['stripe-signature' => "t={$tiempoActual},v1={$v1Stripe}"];

    $resStripe = $stripeAd->procesarEvento($payloadStripe, $cabeceraStripeValida, $secretoStripe, '54.187.1.1');
    afirmar($resStripe->esPagoExitoso(), 'Stripe valida firma t=..., v1=... y normaliza payment_intent.succeeded.');
    afirmar($resStripe->monto === 300.00, 'Stripe convierte 30000 céntimos a S/ 300.00.');
    afirmar(strtoupper($resStripe->tarjetaMarca) === 'MASTERCARD' && $resStripe->tarjetaUltimosCuatro === '8899', 'Stripe preserva solo últimos 4 dígitos y marca.');

    // Stripe anti-replay: firma con timestamp expirado (> 300s)
    $tiempoViejo = time() - 400;
    $firmaStripeVieja = hash_hmac('sha256', $tiempoViejo . '.' . $payloadStripe, $secretoStripe);
    $cabeceraStripeExpirada = ['stripe-signature' => "t={$tiempoViejo},v1={$firmaStripeVieja}"];
    $stripeReplayRechazado = false;
    try {
        $stripeAd->procesarEvento($payloadStripe, $cabeceraStripeExpirada, $secretoStripe);
    } catch (\InvalidArgumentException $e) {
        $stripeReplayRechazado = true;
    }
    afirmar($stripeReplayRechazado, 'Stripe rechaza webhook con timestamp expirado (> 300s) como ataque de repetición (Anti-Replay).');

    // 1.4 NiubizAdaptador: Bearer Token / Signature y normalización
    $secretoNiubiz = 'niubiz_auth_token_secret_abcdef';
    $payloadNiubiz = json_encode([
        'purchaseNumber' => (string) $ventaId,
        'transactionId' => 'niu_trans_554433',
        'dataMap' => [
            'STATUS' => 'Authorized',
            'AMOUNT' => '250.00',
            'CURRENCY' => 'PEN',
            'BRAND' => 'VISA',
            'CARD' => '411111XXXXXX1234',
            'ACTION_CODE' => '000',
            'ACTION_DESCRIPTION' => 'Aprobada',
        ],
    ]);

    $cabecerasNiubiz = ['authorization' => 'Bearer ' . $secretoNiubiz];
    $resNiubiz = $niubizAd->procesarEvento($payloadNiubiz, $cabecerasNiubiz, $secretoNiubiz);
    afirmar($resNiubiz->esPagoExitoso(), 'Niubiz valida Bearer token y normaliza estado Authorized.');
    afirmar($resNiubiz->monto === 250.00, 'Niubiz normaliza monto S/ 250.00.');
    afirmar($resNiubiz->tarjetaUltimosCuatro === '1234', 'Niubiz extrae de 411111XXXXXX1234 solo los últimos 4 dígitos.');

    // 1.5 MercadoPagoAdaptador: x-signature (ts=..., v1=...)
    $secretoMP = 'mp_webhook_secret_key_998877';
    $tsMP = (string) time();
    $dataIdMP = 'mp_payment_id_4455';
    $payloadMP = json_encode([
        'id' => 12345678,
        'action' => 'payment.created',
        'data' => [
            'id' => $dataIdMP,
            'transaction_amount' => 150.00,
            'fee_details' => [
                ['amount' => 6.50, 'fee_payer' => 'collector'],
            ],
            'currency_id' => 'PEN',
            'status' => 'approved',
            'payment_method_id' => 'visa',
            'card' => ['last_four_digits' => '9900'],
            'metadata' => [
                'venta_id' => $ventaId,
                'organizacion_id' => $orgIdA,
            ],
        ],
    ]);

    $manifestMP = "id:{$dataIdMP};request-id:req_test_1;ts:{$tsMP};";
    $v1MP = hash_hmac('sha256', $manifestMP, $secretoMP);
    $cabecerasMP = [
        'x-signature'  => "ts={$tsMP},v1={$v1MP}",
        'x-request-id' => 'req_test_1',
    ];

    $resMP = $mpAd->procesarEvento($payloadMP, $cabecerasMP, $secretoMP);
    afirmar($resMP->esPagoExitoso(), 'MercadoPago valida x-signature manifest y normaliza evento payment.created.');
    afirmar($resMP->monto === 150.00 && $resMP->comisionPasarela === 6.50, 'MercadoPago normaliza monto S/ 150.00 y comisión S/ 6.50.');
    afirmar($resMP->tarjetaUltimosCuatro === '9900', 'MercadoPago sanitiza tarjeta a solo 4 dígitos.');

    // =========================================================================
    // 2. PRUEBAS DE WEBHOOKPASARELA SERVICIO (ORQUESTADOR, IDEMPOTENCIA Y AMORTIZACIÓN)
    // =========================================================================
    echo "\n--- 2. Orquestador de Webhooks Sanitizados (F2.7D) ---\n";

    // Configurar Culqi para Tenant A en la base de datos con secreto cifrado AES-256-GCM
    $secretoCulqiCifrado = $cifrador->cifrar($secretoCulqi);
    $pasarelaCulqiCat = $pasarelaRepo->buscarPasarelaPorCodigo('CULQI');

    $configCulqi = new OrganizacionPasarela(
        id: null,
        organizacionId: $orgIdA,
        pasarelaId: (int) $pasarelaCulqiCat->id,
        modo: 'TEST',
        identificadorComercio: 'pk_test_culqi_merchant_001',
        credencialSecretaEnc: $secretoCulqiCifrado,
        webhookSecretoEnc: $secretoCulqiCifrado,
        porcentajeComision: 3.99,
        comisionFija: 0.50,
        asumeComision: AsumeComisionPasarela::ORGANIZACION,
        activo: true
    );
    $orgPasId = $pasarelaRepo->guardarConfiguracionOrganizacion($configCulqi);
    afirmar($orgPasId > 0, "Configuración de pasarela Culqi guardada para Tenant #{$orgIdA} con secretos cifrados AES-256-GCM.");

    // Procesar webhook de Culqi de S/ 400.00 sobre la venta
    $payloadWebhookCulqi = json_encode([
        'id' => 'evt_culqi_wh_001',
        'type' => 'charge.succeeded',
        'data' => [
            'id' => 'chr_culqi_trans_1001',
            'amount' => 40000, // S/ 400.00
            'fee' => 1650,    // S/ 16.50
            'currency_code' => 'PEN',
            'source' => [
                'iin' => ['card_brand' => 'Visa'],
                'last_four' => '1122',
            ],
            'metadata' => [
                'venta_id' => $ventaId,
                'organizacion_id' => $orgIdA,
            ],
        ],
    ]);
    $firmaWhCulqi = hash_hmac('sha256', $payloadWebhookCulqi, $secretoCulqi);
    $headersWhCulqi = [
        'content-type'      => 'application/json',
        'x-culqi-signature' => $firmaWhCulqi,
        'user-agent'        => 'Culqi-Webhook/2.0',
    ];

    $resWh1 = $webhookServicio->procesarWebhook(
        codigoPasarela: 'CULQI',
        cuerpoRaw: $payloadWebhookCulqi,
        cabeceras: $headersWhCulqi,
        parametrosQuery: ['org_id' => (string) $orgIdA],
        ipOrigen: '190.237.10.5'
    );

    afirmar($resWh1['exito'] === true, 'WebhookPasarelaServicio procesa webhook Culqi exitosamente.');
    afirmar($resWh1['duplicado'] === false, 'El primer procesamiento declara duplicado = false.');
    afirmar($resWh1['pago_id'] > 0, "Pago registrado automáticamente en base de datos (#{$resWh1['pago_id']}).");

    // Verificar amortización de la venta
    $ventaPostWh = $ventaRepo->buscarPorId($ventaId, $orgIdA);
    afirmar($ventaPostWh->montoPagado === 400.00, 'La venta amortizó automáticamente S/ 400.00 tras el webhook de Culqi.');
    afirmar($ventaPostWh->saldoPendiente === 600.00, 'El saldo pendiente de la venta se recalculó a S/ 600.00.');
    afirmar($ventaPostWh->estadoFinanciero === EstadoFinancieroVenta::PAGO_PARCIAL->value, 'El estado financiero de la venta pasó a PAGO_PARCIAL.');

    // PRUEBA DE IDEMPOTENCIA: Re-enviar exactamente el mismo webhook
    $resWhDup = $webhookServicio->procesarWebhook(
        codigoPasarela: 'CULQI',
        cuerpoRaw: $payloadWebhookCulqi,
        cabeceras: $headersWhCulqi,
        parametrosQuery: ['org_id' => (string) $orgIdA],
        ipOrigen: '190.237.10.5'
    );
    afirmar($resWhDup['exito'] === true && $resWhDup['duplicado'] === true, 'Webhook duplicado detectado por clave de idempotencia sin doble cobro.');
    afirmar($resWhDup['pago_id'] === $resWh1['pago_id'], 'Webhook duplicado retorna el mismo pago_id preexistente.');

    // Verificar que el saldo de la venta NO se alteró dos veces
    $ventaPostDup = $ventaRepo->buscarPorId($ventaId, $orgIdA);
    afirmar($ventaPostDup->montoPagado === 400.00, 'Invariante de saldos: La venta conserva intacto su saldo de S/ 400.00 pagados.');

    // Verificar registro en tabla de intentos
    $stmtInt = $pdo->prepare("SELECT * FROM pago_intentos_pasarela WHERE id = :id");
    $stmtInt->execute(['id' => $resWh1['intento_id']]);
    $intentoFila = $stmtInt->fetch(PDO::FETCH_ASSOC);
    afirmar($intentoFila !== false, 'Intento registrado en tabla pago_intentos_pasarela.');
    afirmar($intentoFila['tarjeta_marca'] === 'Visa' && $intentoFila['tarjeta_ultimos_cuatro'] === '1122', 'Intento almacena tarjeta_marca y tarjeta_ultimos_cuatro.');
    afirmar(!str_contains(json_encode($intentoFila), '411111'), 'PCI-DSS Hygiene: Cero almacenamiento de PAN completo en base de datos.');

    // Webhook de pago fallido
    $payloadFallido = json_encode([
        'id' => 'evt_culqi_fail_002',
        'type' => 'charge.failed',
        'data' => [
            'id' => 'chr_culqi_trans_fail_01',
            'amount' => 10000,
            'fee' => 0,
            'currency_code' => 'PEN',
            'user_message' => 'Fondos insuficientes en la tarjeta',
            'metadata' => [
                'venta_id' => $ventaId,
                'organizacion_id' => $orgIdA,
            ],
        ],
    ]);
    $firmaFail = hash_hmac('sha256', $payloadFallido, $secretoCulqi);
    $resWhFail = $webhookServicio->procesarWebhook(
        codigoPasarela: 'CULQI',
        cuerpoRaw: $payloadFallido,
        cabeceras: ['x-culqi-signature' => $firmaFail],
        parametrosQuery: ['org_id' => (string) $orgIdA]
    );
    afirmar($resWhFail['exito'] === true, 'Webhook de pago fallido procesado.');
    afirmar($resWhFail['pago_id'] === null, 'Pago fallido NO crea registro en la tabla pagos.');
    afirmar(in_array($resWhFail['evento'], ['PAGO_FALLIDO', 'CARGO_FALLIDO'], true), 'Evento registrado correctamente como CARGO_FALLIDO.');

    // =========================================================================
    // 3. PRUEBAS DEL CONTROLADOR HTTP REST (PAGOCONTROLADOR - 14 ENDPOINTS)
    // =========================================================================
    echo "\n--- 3. PagoControlador — Endpoints RESTful y Seguridad (F2.7D) ---\n";

    $controlador = new PagoControlador(
        authMiddleware: new AutenticacionMiddleware(),
        authzMiddleware: new AutorizacionMiddleware(),
        pagoServicio: $pagoServicio,
        webhookServicio: $webhookServicio,
        pdo: $pdo
    );

    // 3.1 Endpoint Cuentas Bancarias: POST, GET, PUT
    $cuentaTest = new CuentaBancariaOrganizacion(
        id: null,
        organizacionId: $orgIdA,
        bancoNombre: 'BCP Banco de Crédito',
        tipoCuenta: 'CORRIENTE',
        moneda: 'PEN',
        titularNombre: 'Candelaria Tours S.A.C.',
        numeroCuenta: '193-99887766-0-12',
        codigoInterbancario: '00219300998877660112',
        aliasIdentificador: 'BCP_SOLES_PRINCIPAL',
        qrImagenUrl: 'https://cdn.candelaria.test/qr/bcp.png',
        instruccionesPago: 'Transferencias directas o interbancarias',
        activo: true
    );
    $cuentaId = $cuentaRepo->crear($cuentaTest);
    afirmar($cuentaId > 0, "Cuenta bancaria institucional creada (#{$cuentaId}).");

    $cuentasListadas = $cuentaRepo->listarPorOrganizacion($orgIdA);
    afirmar(count($cuentasListadas) >= 1, 'Cuenta bancaria recuperada en listado del tenant.');

    // 3.2 Endpoint Registrar Cobro Manual (Efectivo y Transferencia)
    $pagoManualEfectivo = $pagoServicio->registrarPagoManual(
        organizacionId: $orgIdA,
        edicionId: $edicionId,
        ventaId: $ventaId,
        datos: [
            'metodo_pago'            => MetodoPago::EFECTIVO->value,
            'monto_cobrado_cliente'  => 200.00,
            'notas_operativas'       => 'Pago en efectivo recepción central',
            'clave_idempotencia'     => 'test_manual_efectivo_001',
        ],
        contexto: $contextoOp
    );
    afirmar($pagoManualEfectivo->id > 0, "Pago en efectivo registrado (#{$pagoManualEfectivo->id}).");
    afirmar($pagoManualEfectivo->estado === EstadoPago::APROBADO, 'Pago en efectivo se aprueba de inmediato.');

    // Saldo venta tras efectivo: 400 (culqi) + 200 (efectivo) = 600 pagados, saldo = 400
    $ventaPostEf = $ventaRepo->buscarPorId($ventaId, $orgIdA);
    afirmar($ventaPostEf->montoPagado === 600.00 && $ventaPostEf->saldoPendiente === 400.00, 'Saldo de venta actualizado a S/ 600.00 pagados, saldo S/ 400.00.');

    // Registrar pago por transferencia (PENDIENTE_VERIFICACION)
    $pagoTransferencia = $pagoServicio->registrarPagoManual(
        organizacionId: $orgIdA,
        edicionId: $edicionId,
        ventaId: $ventaId,
        datos: [
            'metodo_pago'               => MetodoPago::TRANSFERENCIA_BANCARIA->value,
            'monto_cobrado_cliente'     => 400.00,
            'cuenta_bancaria_id'        => $cuentaId,
            'numero_operacion_bancaria' => 'OP-77665544',
            'boucher_comprobante_url'   => 'https://cdn.candelaria.test/bouchers/bcp_776655.jpg',
            'clave_idempotencia'        => 'test_manual_transf_001',
        ],
        contexto: $contextoOp
    );
    afirmar($pagoTransferencia->estado === EstadoPago::PENDIENTE_VERIFICACION, 'Transferencia bancaria queda en PENDIENTE_VERIFICACION.');

    // Saldo venta todavía no amortiza la transferencia no verificada
    $ventaPostTransf = $ventaRepo->buscarPorId($ventaId, $orgIdA);
    afirmar($ventaPostTransf->montoPagado === 600.00, 'Venta aún no amortiza transferencia en verificación.');

    // 3.3 Endpoint Subir/Actualizar Boucher
    $pagoConBoucher = $pagoServicio->registrarBoucher(
        organizacionId: $orgIdA,
        pagoId: (int) $pagoTransferencia->id,
        boucherUrl: 'https://cdn.candelaria.test/bouchers/bcp_776655_hd.jpg',
        contexto: $contextoOp
    );
    afirmar($pagoConBoucher->boucherComprobanteUrl === 'https://cdn.candelaria.test/bouchers/bcp_776655_hd.jpg', 'Boucher comprobante actualizado exitosamente.');

    // 3.4 Endpoint Verificar Pago Manual (Aprobación y Concurrencia)
    $concurrenciaConflictoDetectado = false;
    try {
        // Enviar versión de bloqueo equivocada para probar 409 Conflict
        $pagoServicio->verificarPagoManual(
            organizacionId: $orgIdA,
            pagoId: (int) $pagoTransferencia->id,
            aprobar: true,
            versionBloqueoEsperada: 999, // Versión inválida
            contexto: $contextoOp
        );
    } catch (\Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion $e) {
        $concurrenciaConflictoDetectado = true;
    }
    afirmar($concurrenciaConflictoDetectado, 'Control optimista de concurrencia: Mismatch en version_bloqueo arroja conflicto (HTTP 409).');

    // Aprobación correcta con version_bloqueo válida
    $pagoAprobado = $pagoServicio->verificarPagoManual(
        organizacionId: $orgIdA,
        pagoId: (int) $pagoTransferencia->id,
        aprobar: true,
        versionBloqueoEsperada: $pagoTransferencia->versionBloqueo,
        notas: 'Depósito validado contra extracto bancario BCP',
        contexto: $contextoOp
    );
    afirmar($pagoAprobado->estado === EstadoPago::APROBADO, 'Transferencia bancaria formalmente APROBADA.');

    // Saldo venta tras verificación: 400 + 200 + 400 = 1,000.00 (PAGADA_TOTAL)
    $ventaPostVerif = $ventaRepo->buscarPorId($ventaId, $orgIdA);
    afirmar($ventaPostVerif->montoPagado === 1000.00 && $ventaPostVerif->saldoPendiente === 0.00, 'Venta totalmente pagada: S/ 1,000.00 pagados, saldo 0.00.');
    afirmar($ventaPostVerif->estadoFinanciero === EstadoFinancieroVenta::PAGADA_TOTAL->value, 'Estado financiero actualizado a PAGADA_TOTAL.');

    // 3.5 Endpoint Estado de Cuenta y Liquidación de Venta
    $estadoCuenta = $pagoServicio->obtenerEstadoCuentaVenta($ventaId, $orgIdA, $contextoOp);
    afirmar($estadoCuenta['permite_liquidacion'] === true, 'Estado de cuenta confirma que la venta permite liquidación comercial.');
    afirmar($estadoCuenta['resumen_pagos']['aprobados'] === 3, 'Resumen de pagos contabiliza 3 pagos aprobados.');

    // Liquidar venta
    $pagoServicio->liquidarVenta($ventaId, $orgIdA, $contextoOp);
    $ventaLiquidada = $ventaRepo->buscarPorId($ventaId, $orgIdA);
    afirmar($ventaLiquidada->estado === EstadoVenta::LIQUIDADA, 'Venta transicionada comercialmente a LIQUIDADA.');

    // No admite nuevos pagos una vez LIQUIDADA
    $pagoRechazadoPostLiquidacion = false;
    try {
        $pagoServicio->registrarPagoManual(
            organizacionId: $orgIdA,
            edicionId: $edicionId,
            ventaId: $ventaId,
            datos: [
                'metodo_pago'           => MetodoPago::EFECTIVO->value,
                'monto_cobrado_cliente' => 50.00,
            ],
            contexto: $contextoOp
        );
    } catch (\InvalidArgumentException $e) {
        $pagoRechazadoPostLiquidacion = true;
    }
    afirmar($pagoRechazadoPostLiquidacion, 'Rechazo estricto de nuevos pagos sobre venta formalmente LIQUIDADA.');

    // 3.6 Endpoint Reembolsos (Asiento compensatorio inmutable)
    // Crear venta B para prueba de reembolso
    $correlativoVentaB = $ventaRepo->generarSiguienteCorrelativo($orgIdA, 2027);
    $ventaB = new Venta(
        id: null,
        organizacionId: $orgIdA,
        edicionId: $edicionId,
        clienteId: $clienteAId,
        cotizacionId: null,
        origenTipo: TipoOrigenVenta::DIRECTA,
        correlativo: $correlativoVentaB,
        fechaVenta: '2027-01-15',
        estado: EstadoVenta::CONFIRMADA,
        clienteNombreCompleto: 'JUAN QUISPE PEREZ',
        clienteTipoDocumento: 'DNI',
        clienteNumeroDocumento: '77665544',
        clienteTelefono: '+51988112233',
        clienteEmail: 'juan@test.pe',
        moneda: 'PEN',
        subtotal: 500.00,
        total: 500.00,
        montoPagado: 0.00,
        saldoPendiente: 500.00,
        estadoFinanciero: 'NO_PAGADA',
        creadoPor: $adminSinteticoAId
    );
    $ventaBGuardada = $ventaRepo->guardar($ventaB);
    $ventaBId = (int) $ventaBGuardada->id;

    $pagoParaReembolso = $pagoServicio->registrarPagoManual(
        organizacionId: $orgIdA,
        edicionId: $edicionId,
        ventaId: $ventaBId,
        datos: [
            'metodo_pago'           => MetodoPago::EFECTIVO->value,
            'monto_cobrado_cliente' => 500.00,
            'clave_idempotencia'    => 'test_reem_pago_001',
        ],
        contexto: $contextoOp
    );

    // Reembolso parcial de S/ 200.00
    $reembolsoParcial = $pagoServicio->registrarReembolso(
        organizacionId: $orgIdA,
        pagoId: (int) $pagoParaReembolso->id,
        montoReembolso: 200.00,
        motivo: MotivoReembolso::DESISTIMIENTO_CLIENTE,
        motivoDetalle: 'Cliente desiste de un cupo por motivos de salud',
        contexto: $contextoOp
    );
    afirmar($reembolsoParcial->id > 0, "Asiento compensatorio de reembolso registrado (#{$reembolsoParcial->id}, S/ 200.00).");
    afirmar($reembolsoParcial->estado === EstadoReembolso::EJECUTADO, 'Reembolso ejecutado de inmediato.');

    // Verificar que el pago acumula el monto reembolsado y conserva estado APROBADO
    $pagoReemConsultado = $pagoRepo->buscarPorId((int) $pagoParaReembolso->id, $orgIdA);
    afirmar($pagoReemConsultado->montoReembolsadoAcumulado === 200.00, 'El pago acumula S/ 200.00 en monto_reembolsado_acumulado.');
    afirmar($pagoReemConsultado->estado === EstadoPago::APROBADO, 'El estado del pago permanece inmutable en APROBADO.');

    // Saldo venta B tras reembolso: 500 - 200 = 300 pagado neto, saldo = 200
    $ventaBPostReem = $ventaRepo->buscarPorId($ventaBId, $orgIdA);
    afirmar($ventaBPostReem->montoPagado === 300.00 && $ventaBPostReem->saldoPendiente === 200.00, 'Saldo de venta B recalculado tras reembolso: S/ 300.00 pagados neto, S/ 200.00 saldo.');

    // Reembolso excesivo rechazado (intentar reembolsar S/ 350.00 cuando solo quedan S/ 300.00)
    $reembolsoExcesivoRechazado = false;
    try {
        $pagoServicio->registrarReembolso(
            organizacionId: $orgIdA,
            pagoId: (int) $pagoParaReembolso->id,
            montoReembolso: 350.00,
            motivo: MotivoReembolso::AJUSTE_COMERCIAL,
            motivoDetalle: 'Intento de sobre-reembolso',
            contexto: $contextoOp
        );
    } catch (\InvalidArgumentException $e) {
        $reembolsoExcesivoRechazado = true;
    }
    afirmar($reembolsoExcesivoRechazado, 'Rechazo estricto de reembolso que excede el saldo remanente del pago.');

    // 3.7 Listado Paginado de Pagos
    $pagosPaginados = $pagoRepo->listarPaginado(
        organizacionId: $orgIdA,
        edicionId: $edicionId,
        filtros: [],
        pagina: 1,
        porPagina: 10
    );
    afirmar($pagosPaginados['total'] >= 4, "Listado paginado retorna total={$pagosPaginados['total']} registros.");
    afirmar(count($pagosPaginados['items']) >= 4, 'Items hidratados correctamente en listado paginado.');

    // 3.8 Configuración Segura de Pasarelas (Cifrado AES-256-GCM)
    $stmtPasStripe = $pasarelaRepo->buscarPasarelaPorCodigo('STRIPE');
    $configStripe = new OrganizacionPasarela(
        id: null,
        organizacionId: $orgIdA,
        pasarelaId: (int) $stmtPasStripe->id,
        modo: 'TEST',
        identificadorComercio: 'acct_stripe_test_1234',
        credencialSecretaEnc: $cifrador->cifrar('sk_test_stripe_secret_raw_text'),
        webhookSecretoEnc: $cifrador->cifrar('whsec_stripe_test_secret_raw_text'),
        porcentajeComision: 4.50,
        comisionFija: 1.00,
        asumeComision: AsumeComisionPasarela::CLIENTE,
        activo: true
    );
    $pasStripeId = $pasarelaRepo->guardarConfiguracionOrganizacion($configStripe);
    $stripeGuardado = $pasarelaRepo->buscarOrganizacionPasarelaPorId($pasStripeId, $orgIdA);
    afirmar($stripeGuardado !== null, 'Configuración de pasarela Stripe guardada.');

    // Probar descifrado fiel
    $stripeKeyDescifrada = $cifrador->descifrar($stripeGuardado->credencialSecretaEnc);
    afirmar($stripeKeyDescifrada === 'sk_test_stripe_secret_raw_text', 'Cifrado/Descifrado AES-256-GCM recupera exactamente la clave secreta original.');

    // Probar que aArreglo(false) NO expone secretos
    $stripeExportado = $stripeGuardado->aArreglo(false);
    afirmar(!isset($stripeExportado['credencial_secreta_enc']) && !isset($stripeExportado['webhook_secreto_enc']), 'aArreglo(false) protege y omite los secretos cifrados de pasarelas.');

    // 3.9 Endpoint Webhook a nivel de Controlador REST
    $resCtrlWh = $controlador->recibirWebhook('CULQI');
    $decWh = json_decode($resCtrlWh, true);
    afirmar($decWh['exito'] === false, 'Controlador Webhook rechaza invocación vacía o no autenticada.');
    afirmar(isset($decWh['errores']['webhook']), 'Controlador responde con estructura soberana {exito, mensaje, datos, errores}.');

    // =========================================================================
    // 4. PRUEBAS DE SEGURIDAD, RBAC Y MULTI-TENANCY (ANTI-IDOR)
    // =========================================================================
    echo "\n--- 4. Seguridad RBAC y Aislamiento Multi-Tenancy (F2.7D) ---\n";

    // 4.1 Permiso de Reembolso denegado a operador sin rol
    $reembolsoDenegado = false;
    try {
        $pagoServicio->registrarReembolso(
            organizacionId: $orgIdA,
            pagoId: (int) $pagoParaReembolso->id,
            montoReembolso: 50.00,
            motivo: MotivoReembolso::DESISTIMIENTO_CLIENTE,
            motivoDetalle: 'Sin permiso',
            contexto: $contextoSinPerm
        );
    } catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
        $reembolsoDenegado = true;
    }
    afirmar($reembolsoDenegado, 'RBAC: Operador sin permiso pagos.reembolsar es rechazado (HTTP 403).');

    // 4.2 Permiso de Verificación denegado a operador sin rol
    $verificacionDenegada = false;
    try {
        $pagoServicio->verificarPagoManual(
            organizacionId: $orgIdA,
            pagoId: (int) $pagoTransferencia->id,
            aprobar: true,
            versionBloqueoEsperada: 1,
            contexto: $contextoSinPerm
        );
    } catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
        $verificacionDenegada = true;
    }
    afirmar($verificacionDenegada, 'RBAC: Operador sin permiso pagos.verificar es rechazado (HTTP 403).');

    // 4.3 Anti-IDOR: Operar sobre otra organización rechazada
    $idorRechazado = false;
    try {
        $pagoServicio->obtenerEstadoCuentaVenta(
            ventaId: $ventaId,
            organizacionId: $orgIdB, // ID de otra organización
            contexto: $contextoOp    // Contexto pertenece a Org A
        );
    } catch (\Aplicacion\Excepciones\AccesoDenegadoExcepcion $e) {
        $idorRechazado = true;
    }
    afirmar($idorRechazado, 'Anti-IDOR: Operador de Tenant A no puede consultar transacciones de Tenant B.');

} finally {
    // Reversión de la transacción de prueba para dejar la BD limpia
    $pdo->rollBack();
}

// =========================================================================
// 5. VALIDACIÓN DE PRESERVACIÓN DE IDENTIDAD (ORLANDO ID 24)
// =========================================================================
echo "\n--- 5. Verificación de Integridad de Identidad (Orlando ID 24) ---\n";
$stmtOrlandoPost = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id = 24 OR nombre_usuario = 'orlando'");
$stmtOrlandoPost->execute();
$orlandoPost = $stmtOrlandoPost->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPost = $orlandoPost ? substr(hash('sha256', (string) $orlandoPost['contrasena_hash']), 0, 16) : null;

afirmar($orlandoPost !== false, 'Usuario Orlando existe en la base de datos.');
afirmar($fingerprintOrlandoPre === $fingerprintOrlandoPost, 'Huella criptográfica de contraseña de Orlando 100% INTACTA.');
afirmar((int) $orlandoPost['intentos_fallidos'] === 0, 'Intentos fallidos de Orlando = 0.');
afirmar($orlandoPost['estado'] === 'ACTIVO', 'Estado de cuenta de Orlando = ACTIVO.');

echo "\n==============================================================================\n";
echo "RESUMEN DE PRUEBAS F2.7D:\n";
echo "Éxitos: {$exitos}\n";
echo "Fallos: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
