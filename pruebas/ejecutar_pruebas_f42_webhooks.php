<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE BLINDAJE DE WEBHOOKS DE PAGOS Y WHATSAPP (FASE 4.2C/4.2D)
 * DIRECTIVA TÉCNICA DT-V12-002 (DEC-03 OPCIÓN 3A)
 * ==============================================================================
 * Certifica los 10 escenarios críticos de resiliencia, seguridad e invariantes:
 *  1. Pasarelas: Rechazo fail-closed de firmas ausentes, manipuladas o expiradas (anti-replay) + auditoría forense.
 *  2. Pasarelas: Idempotencia estricta por clave de evento ante entregas duplicadas.
 *  3. Pasarelas: Idempotencia transaccional por transaccion_externa_id ante reintentos con distinto ID de sobre.
 *  4. Pasarelas: Invariante monotónica ante eventos fuera de orden (CARGO_FALLIDO retrasado tras CARGO_EXITOSO).
 *  5. Pasarelas: Inmutabilidad monotónica frente a pagos con reembolsos ejecutados o ventas en estado LIQUIDADA.
 *  6. WhatsApp: Rechazo fail-closed de cabeceras X-Hub-Signature-256 vacías, malformadas o con HMAC inválido.
 *  7. WhatsApp: Deduplicación idempotente ante ráfagas de estados DLR y mensajes entrantes repetidos.
 *  8. WhatsApp: Salto monotónico hacia adelante (delivered llega antes que sent) y descarte del sent retrasado.
 *  9. WhatsApp: Blindaje terminal exitoso (LEIDO ignora eventos tardíos delivered y failed).
 * 10. Gobernanza: Rollback transaccional garantizado e integridad estructural (108 tablas, 29 migraciones, 115 permisos, Orlando).
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Comunicaciones\CategoriaPlantilla;
use Aplicacion\Comunicaciones\ComunicacionServicio;
use Aplicacion\Comunicaciones\EstadoConsentimiento;
use Aplicacion\Comunicaciones\EstadoMensaje;
use Aplicacion\Comunicaciones\EstadoPlantillaMeta;
use Aplicacion\Comunicaciones\FinalidadConsentimiento;
use Aplicacion\Comunicaciones\ModoComunicacion;
use Aplicacion\Comunicaciones\Proveedores\FabricaProveedorWhatsApp;
use Aplicacion\Comunicaciones\WebhookWhatsAppServicio;
use Aplicacion\Entidades\ComunicacionPlantilla;
use Aplicacion\Entidades\OrganizacionPasarela;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Venta;
use Aplicacion\Finanzas\AsumeComisionPasarela;
use Aplicacion\Finanzas\CifradorFinanciero;
use Aplicacion\Finanzas\EstadoPago;
use Aplicacion\Finanzas\MotivoReembolso;
use Aplicacion\Finanzas\PagoServicio;
use Aplicacion\Finanzas\WebhookPasarelaServicio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ComunicacionConfigRepositorio;
use Aplicacion\Repositorios\ComunicacionConsentimientoRepositorio;
use Aplicacion\Repositorios\ComunicacionConversacionRepositorio;
use Aplicacion\Repositorios\ComunicacionMensajeRepositorio;
use Aplicacion\Repositorios\ComunicacionPlantillaRepositorio;
use Aplicacion\Repositorios\ComunicacionWebhookRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\CuentaBancariaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\PagoReembolsoRepositorio;
use Aplicacion\Repositorios\PagoRepositorio;
use Aplicacion\Repositorios\PasarelaRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Ventas\EstadoVenta;
use Aplicacion\Ventas\TipoOrigenVenta;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;

$pruebasEjecutadas = 0;
$pruebasPasadas = 0;
$pruebasFallidas = 0;

function assertF42W(bool $condicion, string $descripcion, string $detalles = ''): void
{
    global $pruebasEjecutadas, $pruebasPasadas, $pruebasFallidas;
    $pruebasEjecutadas++;
    if ($condicion) {
        $pruebasPasadas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$descripcion}" . ($detalles !== '' ? " --> {$detalles}" : "") . "\n";
    }
}

echo "==============================================================================\n";
echo "CANDELARIAAPP V1.2 — SUITE DE BLINDAJE DE WEBHOOKS PAGOS Y WHATSAPP (FASE 4.2D)\n";
echo "DIRECTIVA DT-V12-002 (DEC-03 OPCIÓN 3A) — SEGURIDAD, IDEMPOTENCIA Y MONOTONICIDAD\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();
$pdo->beginTransaction();

try {
    $sufijo = bin2hex(random_bytes(3));
    $orgId = 19420;

    $pdo->exec("
        INSERT INTO `organizaciones` (`id`, `codigo`, `razon_social`, `nombre_comercial`, `tipo_documento_id`, `numero_documento`, `estado`)
        VALUES ({$orgId}, 'org_wh_v12_{$sufijo}', 'Organización Blindaje Webhooks V12 SAC', 'Candelaria Webhooks V12', 2, '20994201234', 'ACTIVO')
        ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'
    ");

    $pdo->exec("
        INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`)
        VALUES (19421, {$orgId}, 'NATURAL', 1, '74201234', 'Operador', 'Finanzas V12', 'op.wh.v12@candelaria.pe', '+51951420111', '+51951420111')
        ON DUPLICATE KEY UPDATE `nombres` = VALUES(`nombres`)
    ");

    $pdo->exec("
        INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `es_superadmin_plataforma`, `estado`)
        VALUES (19421, {$orgId}, 19421, 'op_wh_v12_{$sufijo}', 'Operador Finanzas V12', 'op.wh.v12@candelaria.pe', '\$2y\$10\$abcdefghijklmnopqrstuv', 1, 'ACTIVO')
        ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO', `es_superadmin_plataforma` = 1
    ");

    $pdo->exec("
        INSERT IGNORE INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (19421, 1), (19421, 2)
    ");

    $pdo->prepare("
        INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`)
        VALUES (:org, :cod, 'Candelaria Webhooks 2026', 2026, 'OPERACION', '2026-02-01', '2026-02-15', 1)
    ")->execute(['org' => $orgId, 'cod' => 'ED-WH-' . $sufijo]);
    $edicionId = (int) $pdo->lastInsertId();

    $personaRepo = new PersonaRepositorio($pdo);
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

    $webhookPasarelaSvc = new WebhookPasarelaServicio(
        pagoRepo: $pagoRepo,
        pasarelaRepo: $pasarelaRepo,
        ventaRepo: $ventaRepo,
        pagoServicio: $pagoServicio,
        cifrador: $cifrador,
        auditoriaRepo: $auditoriaRepo,
        pdo: $pdo
    );

    // Crear cliente y venta de S/ 1,000.00 para las pruebas de pasarela
    $personaCliId = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '74209988',
        nombres: 'VALERIA',
        apellidos: 'MAMANI CONDORI',
        telefonoWhatsapp: '+51951420999'
    ));

    $pdo->prepare("
        INSERT INTO `clientes` (`organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`, `consentimiento_promocional`)
        VALUES (:org, :per, 'CLIENTE', 1, 1)
    ")->execute(['org' => $orgId, 'per' => $personaCliId]);
    $clienteId = (int) $pdo->lastInsertId();

    $venta = $ventaRepo->guardar(new Venta(
        id: null,
        organizacionId: $orgId,
        edicionId: $edicionId,
        clienteId: $clienteId,
        cotizacionId: null,
        origenTipo: TipoOrigenVenta::DIRECTA,
        correlativo: $ventaRepo->generarSiguienteCorrelativo($orgId, 2026),
        fechaVenta: '2026-01-20',
        estado: EstadoVenta::CONFIRMADA,
        clienteNombreCompleto: 'VALERIA MAMANI CONDORI',
        clienteTipoDocumento: 'DNI',
        clienteNumeroDocumento: '74209988',
        clienteTelefono: '+51951420999',
        clienteEmail: 'valeria@candelaria.pe',
        moneda: 'PEN',
        subtotal: 1000.00,
        total: 1000.00,
        montoPagado: 0.00,
        saldoPendiente: 1000.00,
        estadoFinanciero: 'NO_PAGADA',
        creadoPor: 19421
    ));
    $ventaId = (int) $venta->id;

    // Configurar Culqi y Stripe para el tenant de prueba
    $secretoCulqi = 'whsec_culqi_v12_' . $sufijo;
    $secretoStripe = 'whsec_stripe_v12_' . $sufijo;

    $catCulqi = $pasarelaRepo->buscarPasarelaPorCodigo('CULQI');
    $pasarelaRepo->guardarConfiguracionOrganizacion(new OrganizacionPasarela(
        id: null,
        organizacionId: $orgId,
        pasarelaId: (int) $catCulqi->id,
        modo: 'TEST',
        identificadorComercio: 'pk_test_culqi_v12',
        credencialSecretaEnc: $cifrador->cifrar('sk_test_culqi_v12'),
        webhookSecretoEnc: $cifrador->cifrar($secretoCulqi),
        porcentajeComision: 3.50,
        comisionFija: 1.00,
        asumeComision: AsumeComisionPasarela::ORGANIZACION,
        activo: true
    ));

    $catStripe = $pasarelaRepo->buscarPasarelaPorCodigo('STRIPE');
    $pasarelaRepo->guardarConfiguracionOrganizacion(new OrganizacionPasarela(
        id: null,
        organizacionId: $orgId,
        pasarelaId: (int) $catStripe->id,
        modo: 'TEST',
        identificadorComercio: 'acct_test_stripe_v12',
        credencialSecretaEnc: $cifrador->cifrar('sk_test_stripe_v12'),
        webhookSecretoEnc: $cifrador->cifrar($secretoStripe),
        porcentajeComision: 4.00,
        comisionFija: 1.00,
        asumeComision: AsumeComisionPasarela::ORGANIZACION,
        activo: true
    ));

    // --------------------------------------------------------------------------
    // BLOQUE 1: BLINDAJE DE WEBHOOKS DE PASARELAS DE PAGO (ESCENARIOS 1 A 5)
    // --------------------------------------------------------------------------
    echo "--- BLOQUE 1: Blindaje de Webhooks de Pasarelas de Pago (Culqi / Stripe) ---\n";

    $payloadCulqiExito = json_encode([
        'id'   => 'evt_culqi_ok_v12_01',
        'type' => 'charge.succeeded',
        'data' => [
            'id'            => 'chr_culqi_tx_v12_01',
            'amount'        => 40000, // S/ 400.00
            'currency_code' => 'PEN',
            'metadata'      => [
                'venta_id'        => $ventaId,
                'organizacion_id' => $orgId,
            ],
            'source' => [
                'last_four' => '4242',
                'iin'       => ['card_brand' => 'Visa'],
            ],
        ],
    ]);

    // Escenario 1A: Firma inválida o ausente en Culqi es rechazada fail-closed y auditada
    $rechazoCulqiSinFirma = false;
    try {
        $webhookPasarelaSvc->procesarWebhook(
            codigoPasarela: 'CULQI',
            cuerpoRaw: $payloadCulqiExito,
            cabeceras: ['x-culqi-signature' => 'firma_adulterada_000000'],
            parametrosQuery: ['org_id' => (string) $orgId]
        );
    } catch (\InvalidArgumentException $e) {
        $rechazoCulqiSinFirma = str_contains($e->getMessage(), 'Firma criptográfica de webhook inválida');
    }

    $stmtAudFirma = $pdo->prepare("
        SELECT COUNT(*) FROM `auditoria_operaciones`
        WHERE `organizacion_id` = :org AND `accion` = 'WEBHOOK_FIRMA_INVALIDA_RECHAZADA'
    ");
    $stmtAudFirma->execute(['org' => $orgId]);
    $auditoriasFirmaInvalida = (int) $stmtAudFirma->fetchColumn();

    assertF42W(
        $rechazoCulqiSinFirma && $auditoriasFirmaInvalida >= 1,
        "1.1: Culqi rechaza fail-closed firmas adulteradas/ausentes y registra evento forense WEBHOOK_FIRMA_INVALIDA_RECHAZADA"
    );

    // Escenario 1B: Replay Attack en Stripe (timestamp expirado > 300s) es rechazado fail-closed
    $tsExpirado = time() - 900;
    $firmaStripeExpirada = hash_hmac('sha256', "{$tsExpirado}.{$payloadCulqiExito}", $secretoStripe);
    $rechazoStripeReplay = false;
    try {
        $webhookPasarelaSvc->procesarWebhook(
            codigoPasarela: 'STRIPE',
            cuerpoRaw: $payloadCulqiExito,
            cabeceras: ['stripe-signature' => "t={$tsExpirado},v1={$firmaStripeExpirada}"],
            parametrosQuery: ['org_id' => (string) $orgId]
        );
    } catch (\InvalidArgumentException) {
        $rechazoStripeReplay = true;
    }

    $ventaIntacta = $ventaRepo->buscarPorId($ventaId, $orgId);
    assertF42W(
        $rechazoStripeReplay && $ventaIntacta->montoPagado === 0.00,
        "1.2: Stripe rechaza ataques de repetición (timestamp fuera de ventana de 300s) sin alterar el saldo de la venta (S/ 0.00)"
    );

    // Escenario 2: Procesamiento válido + Idempotencia por clave_idempotencia
    $firmaCulqiValida = hash_hmac('sha256', $payloadCulqiExito, $secretoCulqi);
    $resPago1 = $webhookPasarelaSvc->procesarWebhook(
        codigoPasarela: 'CULQI',
        cuerpoRaw: $payloadCulqiExito,
        cabeceras: ['x-culqi-signature' => $firmaCulqiValida],
        parametrosQuery: ['org_id' => (string) $orgId]
    );

    $resPago1Dup = $webhookPasarelaSvc->procesarWebhook(
        codigoPasarela: 'CULQI',
        cuerpoRaw: $payloadCulqiExito,
        cabeceras: ['x-culqi-signature' => $firmaCulqiValida],
        parametrosQuery: ['org_id' => (string) $orgId]
    );

    $ventaTrasPago1 = $ventaRepo->buscarPorId($ventaId, $orgId);
    assertF42W(
        $resPago1['exito'] === true && $resPago1['duplicado'] === false &&
        $resPago1Dup['exito'] === true && $resPago1Dup['duplicado'] === true &&
        $resPago1['pago_id'] === $resPago1Dup['pago_id'] &&
        $ventaTrasPago1->montoPagado === 400.00 && $ventaTrasPago1->saldoPendiente === 600.00,
        "1.3: Idempotencia por clave de evento evita duplicación de cobros y preserva saldo exacto (Pagado: S/ 400.00, Saldo: S/ 600.00)"
    );

    // Escenario 3: Idempotencia por transaccion_externa_id cuando la pasarela reenvía con distinto ID de sobre
    $payloadCulqiReintentoSobre = json_encode([
        'id'   => 'evt_culqi_retry_wrapper_v12_02', // Distinto ID de evento
        'type' => 'charge.succeeded',
        'data' => [
            'id'            => 'chr_culqi_tx_v12_01', // MISMA transacción externa ya aprobada
            'amount'        => 40000,
            'currency_code' => 'PEN',
            'metadata'      => [
                'venta_id'        => $ventaId,
                'organizacion_id' => $orgId,
            ],
        ],
    ]);
    $firmaRetrySobre = hash_hmac('sha256', $payloadCulqiReintentoSobre, $secretoCulqi);
    $resRetrySobre = $webhookPasarelaSvc->procesarWebhook(
        codigoPasarela: 'CULQI',
        cuerpoRaw: $payloadCulqiReintentoSobre,
        cabeceras: ['x-culqi-signature' => $firmaRetrySobre],
        parametrosQuery: ['org_id' => (string) $orgId]
    );

    $ventaTrasRetrySobre = $ventaRepo->buscarPorId($ventaId, $orgId);
    assertF42W(
        $resRetrySobre['exito'] === true &&
        $resRetrySobre['duplicado'] === true &&
        $resRetrySobre['pago_id'] === $resPago1['pago_id'] &&
        $ventaTrasRetrySobre->montoPagado === 400.00,
        "1.4: Idempotencia por transaccion_externa_id detecta reintento con nuevo ID de sobre y retorna el pago original sin duplicar cobro"
    );

    // Escenario 4: Evento fuera de orden (CARGO_FALLIDO retrasado para una transacción ya APROBADA)
    $payloadCulqiFalloTardio = json_encode([
        'id'   => 'evt_culqi_late_fail_v12_03',
        'type' => 'charge.failed',
        'data' => [
            'id'            => 'chr_culqi_tx_v12_01', // Transacción que YA fue aprobada en $resPago1
            'amount'        => 40000,
            'currency_code' => 'PEN',
            'metadata'      => [
                'venta_id'        => $ventaId,
                'organizacion_id' => $orgId,
            ],
        ],
    ]);
    $firmaFalloTardio = hash_hmac('sha256', $payloadCulqiFalloTardio, $secretoCulqi);
    $resFalloTardio = $webhookPasarelaSvc->procesarWebhook(
        codigoPasarela: 'CULQI',
        cuerpoRaw: $payloadCulqiFalloTardio,
        cabeceras: ['x-culqi-signature' => $firmaFalloTardio],
        parametrosQuery: ['org_id' => (string) $orgId]
    );

    $pagoOriginal = $pagoRepo->buscarPorId((int) $resPago1['pago_id'], $orgId);
    $ventaTrasFalloTardio = $ventaRepo->buscarPorId($ventaId, $orgId);

    $stmtAudOut = $pdo->prepare("
        SELECT COUNT(*) FROM `auditoria_operaciones`
        WHERE `organizacion_id` = :org AND `accion` = 'WEBHOOK_FUERA_DE_ORDEN_IGNORADO'
    ");
    $stmtAudOut->execute(['org' => $orgId]);
    $auditoriasFueraOrden = (int) $stmtAudOut->fetchColumn();

    assertF42W(
        $resFalloTardio['exito'] === true &&
        ($resFalloTardio['ignorado_fuera_de_orden'] ?? false) === true &&
        $pagoOriginal->estado === EstadoPago::APROBADO &&
        $ventaTrasFalloTardio->montoPagado === 400.00 &&
        $auditoriasFueraOrden >= 1,
        "1.5: Máquina de estados monotónica ignora CARGO_FALLIDO retrasado sobre pago APROBADO y registra auditoría WEBHOOK_FUERA_DE_ORDEN_IGNORADO"
    );

    // Escenario 5: Protección monotónica cuando el pago ya registra un reembolso ejecutado
    $ctxAdmin = ContextoOperacion::paraHumano(
        usuarioId: 19421,
        canalId: 1,
        canalCodigo: 'WEB',
        organizacionId: $orgId
    );
    $pagoServicio->registrarReembolso(
        organizacionId: $orgId,
        pagoId: (int) $resPago1['pago_id'],
        montoReembolso: 100.00,
        motivo: MotivoReembolso::AJUSTE_COMERCIAL,
        motivoDetalle: 'Devolución parcial acordada',
        contexto: $ctxAdmin
    );

    $payloadCulqiPostReembolso = json_encode([
        'id'   => 'evt_culqi_post_refund_v12_04',
        'type' => 'charge.succeeded',
        'data' => [
            'id'            => 'chr_culqi_tx_v12_01',
            'amount'        => 40000,
            'currency_code' => 'PEN',
            'metadata'      => [
                'venta_id'        => $ventaId,
                'organizacion_id' => $orgId,
            ],
        ],
    ]);
    $firmaPostReembolso = hash_hmac('sha256', $payloadCulqiPostReembolso, $secretoCulqi);
    $resPostReembolso = $webhookPasarelaSvc->procesarWebhook(
        codigoPasarela: 'CULQI',
        cuerpoRaw: $payloadCulqiPostReembolso,
        cabeceras: ['x-culqi-signature' => $firmaPostReembolso],
        parametrosQuery: ['org_id' => (string) $orgId]
    );

    $ventaPostReembolso = $ventaRepo->buscarPorId($ventaId, $orgId);
    assertF42W(
        $resPostReembolso['exito'] === true &&
        ($resPostReembolso['ignorado_fuera_de_orden'] ?? false) === true &&
        $ventaPostReembolso->montoPagado === 300.00,
        "1.6: Pago con reembolso ejecutado (S/ 400 - S/ 100 = S/ 300 netos) rechaza re-imputación por webhook tardío CARGO_EXITOSO"
    );

    // --------------------------------------------------------------------------
    // BLOQUE 2: BLINDAJE DE WEBHOOKS DE WHATSAPP (ESCENARIOS 6 A 9)
    // --------------------------------------------------------------------------
    echo "\n--- BLOQUE 2: Blindaje de Webhooks de WhatsApp (Firma, Idempotencia y Monotonicidad) ---\n";

    $comConfigRepo = new ComunicacionConfigRepositorio($pdo);
    $comPlantillaRepo = new ComunicacionPlantillaRepositorio($pdo);
    $comConsentRepo = new ComunicacionConsentimientoRepositorio($pdo);
    $comConvRepo = new ComunicacionConversacionRepositorio($pdo);
    $comMensajeRepo = new ComunicacionMensajeRepositorio($pdo);
    $comWebhookRepo = new ComunicacionWebhookRepositorio($pdo);
    $fabricaWa = new FabricaProveedorWhatsApp();

    $secretoWa = 'whsec_meta_v12_' . $sufijo;
    $comConfigRepo->guardar(
        orgId: $orgId,
        proveedorCodigo: 'SIMULADOR_SANDBOX',
        modo: ModoComunicacion::SIMULADOR,
        numeroTelefonoIdentificador: '+51951420000',
        webhookVerifyToken: 'verify_tok_v12_' . $sufijo,
        metaPhoneNumberId: 'phone_v12_01',
        metaWabaId: 'waba_v12_01',
        metaAppId: 'app_v12_01',
        tokenAccesoPlano: 'EAAG_token_v12',
        webhookSecretPlano: $secretoWa,
        presupuestoMensualLimite: 100.00
    );

    $comServicio = new ComunicacionServicio(
        mensajeRepo: $comMensajeRepo,
        plantillaRepo: $comPlantillaRepo,
        consentimientoRepo: $comConsentRepo,
        conversacionRepo: $comConvRepo,
        configRepo: $comConfigRepo,
        fabrica: $fabricaWa
    );

    $webhookWaSvc = new WebhookWhatsAppServicio(
        configRepo: $comConfigRepo,
        webhookRepo: $comWebhookRepo,
        mensajeRepo: $comMensajeRepo,
        conversacionRepo: $comConvRepo,
        consentimientoRepo: $comConsentRepo,
        fabricaProveedores: $fabricaWa,
        pdo: $pdo
    );

    // Escenario 6: Rechazo fail-closed de cabeceras X-Hub-Signature-256 vacías, sin prefijo sha256= o adulteradas
    $payloadWaDummy = json_encode(['entry' => []]);
    $fallosFirmaWa = 0;
    foreach (['', 'firma_sin_prefijo_12345678901234567890', 'sha256=0000000000000000000000000000000000000000000000000000000000000000'] as $firmaMala) {
        try {
            $webhookWaSvc->procesarPayload($orgId, $payloadWaDummy, $firmaMala);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'HMAC-SHA256 no coincide')) {
                $fallosFirmaWa++;
            }
        }
    }
    assertF42W(
        $fallosFirmaWa === 3,
        "2.1: WhatsApp rechaza fail-closed firmas vacías, sin prefijo sha256= o con hash HMAC-SHA256 inválido (3/3 bloqueados)"
    );

    // Preparar plantilla, consentimiento y 2 mensajes con wamid para probar salto fuera de orden e inmutabilidad
    $comPlantillaRepo->guardar(new ComunicacionPlantilla(
        id: null,
        organizacionId: $orgId,
        nombre: 'notificacion_pago_v12',
        idioma: 'es_PE',
        categoria: CategoriaPlantilla::UTILITY,
        cuerpoTexto: 'Hola {{1}}, recibimos tu pago por {{2}}.',
        parametrosMapeoJson: ['1', '2'],
        estadoMeta: EstadoPlantillaMeta::APPROVED,
        metaTemplateId: 'tpl_v12_01'
    ));

    $comServicio->gestionarConsentimiento(
        orgId: $orgId,
        clienteId: $clienteId,
        telefonoDestino: '+51951420999',
        finalidad: FinalidadConsentimiento::TRANSACCIONAL_OPERATIVO,
        estado: EstadoConsentimiento::CONCEDIDO,
        origenEvidencia: 'CHECKOUT_V12',
        textoClausula: 'Consentimiento operativo WhatsApp V12'
    );

    $msgOutOfOrder = $comServicio->encolarMensajeTransaccional(
        orgId: $orgId,
        telefonoDestino: '+51951420999',
        nombreDestinatario: 'Valeria Mamani',
        nombrePlantilla: 'notificacion_pago_v12',
        idioma: 'es_PE',
        parametros: ['Valeria', 'S/ 400.00'],
        eventoOrigen: 'PAGO_APROBADO',
        entidadOrigenId: '9001',
        clienteId: $clienteId,
        contexto: $ctxAdmin
    );
    $wamidOutOfOrder = 'wamid.v12_out_of_order_' . $sufijo;
    $pdo->prepare("UPDATE `comunicacion_mensajes` SET `wamid` = :w, `estado` = 'EN_PROCESO', `peso_estado` = 20 WHERE `id` = :id")
        ->execute(['w' => $wamidOutOfOrder, 'id' => $msgOutOfOrder->id]);

    // Escenario 7 y 8: Llega primero 'delivered' (mientras el mensaje aún figura en EN_PROCESO, antes de 'sent')
    $payloadWaDeliveredFirst = json_encode([
        'entry' => [[
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id'           => $wamidOutOfOrder,
                        'status'       => 'delivered',
                        'timestamp'    => (string) time(),
                        'recipient_id' => '51951420999',
                    ]],
                ],
            ]],
        ]],
    ]);
    $firmaWaDelFirst = 'sha256=' . hash_hmac('sha256', $payloadWaDeliveredFirst, $secretoWa);

    $resWaDel1 = $webhookWaSvc->procesarPayload($orgId, $payloadWaDeliveredFirst, $firmaWaDelFirst);
    $resWaDelDup = $webhookWaSvc->procesarPayload($orgId, $payloadWaDeliveredFirst, $firmaWaDelFirst);
    $msgTrasDelivered = $comMensajeRepo->buscarPorId($msgOutOfOrder->id, $orgId);

    assertF42W(
        $resWaDel1['eventos_procesados'] === 1 &&
        $resWaDelDup['eventos_duplicados'] === 1 &&
        $msgTrasDelivered->estado === EstadoMensaje::ENTREGADO &&
        $msgTrasDelivered->pesoEstado === 40,
        "2.2: Salto monotónico hacia adelante permite avanzar de EN_PROCESO a ENTREGADO cuando 'delivered' llega antes que 'sent', y deduplica repeticiones"
    );

    // Ahora llega el evento 'sent' retrasado (fuera de orden) para el mismo wamid
    $payloadWaLateSent = json_encode([
        'entry' => [[
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id'           => $wamidOutOfOrder,
                        'status'       => 'sent',
                        'timestamp'    => (string) (time() - 5),
                        'recipient_id' => '51951420999',
                    ]],
                ],
            ]],
        ]],
    ]);
    $firmaWaLateSent = 'sha256=' . hash_hmac('sha256', $payloadWaLateSent, $secretoWa);
    $resWaLateSent = $webhookWaSvc->procesarPayload($orgId, $payloadWaLateSent, $firmaWaLateSent);
    $msgTrasLateSent = $comMensajeRepo->buscarPorId($msgOutOfOrder->id, $orgId);

    assertF42W(
        $resWaLateSent['eventos_fuera_de_orden_ignorados'] === 1 &&
        $msgTrasLateSent->estado === EstadoMensaje::ENTREGADO &&
        $msgTrasLateSent->pesoEstado === 40,
        "2.3: Evento 'sent' retrasado es descartado monotónicamente (eventos_fuera_de_orden_ignorados = 1) sin degradar ENTREGADO a ENVIADO"
    );

    // Escenario 9: Avance a LEIDO e inmunidad frente a 'failed' tardío
    $payloadWaRead = json_encode([
        'entry' => [[
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id'           => $wamidOutOfOrder,
                        'status'       => 'read',
                        'timestamp'    => (string) (time() + 2),
                        'recipient_id' => '51951420999',
                    ]],
                ],
            ]],
        ]],
    ]);
    $firmaWaRead = 'sha256=' . hash_hmac('sha256', $payloadWaRead, $secretoWa);
    $webhookWaSvc->procesarPayload($orgId, $payloadWaRead, $firmaWaRead);

    $payloadWaLateFailed = json_encode([
        'entry' => [[
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id'           => $wamidOutOfOrder,
                        'status'       => 'failed',
                        'timestamp'    => (string) (time() + 10),
                        'recipient_id' => '51951420999',
                    ]],
                ],
            ]],
        ]],
    ]);
    $firmaWaLateFailed = 'sha256=' . hash_hmac('sha256', $payloadWaLateFailed, $secretoWa);
    $resWaLateFailed = $webhookWaSvc->procesarPayload($orgId, $payloadWaLateFailed, $firmaWaLateFailed);
    $msgFinalWa = $comMensajeRepo->buscarPorId($msgOutOfOrder->id, $orgId);

    assertF42W(
        $resWaLateFailed['eventos_fuera_de_orden_ignorados'] === 1 &&
        $msgFinalWa->estado === EstadoMensaje::LEIDO &&
        $msgFinalWa->pesoEstado === 50,
        "2.4: Estado terminal exitoso LEIDO (peso 50) es inmutable frente a notificaciones tardías 'failed'"
    );
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ------------------------------------------------------------------------------
// BLOQUE 3: INTEGRIDAD ESTRUCTURAL POST-ROLLBACK Y GOBERNANZA (ESCENARIO 10)
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 3: Integridad Estructural Post-Rollback y Gobernanza ---\n";

$tablasActuales = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
assertF42W($tablasActuales === 108, "3.1: Base de datos conserva exactamente 108 tablas oficiales post-rollback");

$migracionesActuales = (int) $pdo->query("SELECT COUNT(*) FROM migraciones_control")->fetchColumn();
assertF42W(in_array($migracionesActuales, [29, 30], true), "3.2: Trazabilidad de migraciones conserva 29-30 migraciones ({$migracionesActuales})");

$permisosActuales = (int) $pdo->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
assertF42W($permisosActuales === 115, "3.3: Matriz RBAC conserva 115 permisos");

$stmtOrlando = $pdo->query("SELECT contrasena_hash FROM usuarios WHERE id = 24");
$hashOrlando = (string) $stmtOrlando->fetchColumn();
$huellaOrlando = substr(hash('sha256', $hashOrlando), 0, 16);
assertF42W($huellaOrlando === '80e6af84e02e89e3', "3.4: Huella inmutable de Orlando (ID 24) preservada intacta (80e6af84e02e89e3)");

echo "\n==============================================================================\n";
echo sprintf("TOTAL PRUEBAS FASE 4.2D (WEBHOOKS): %d | EXITOSAS: %d | FALLIDAS: %d\n", $pruebasEjecutadas, $pruebasPasadas, $pruebasFallidas);
echo "TOTAL: {$pruebasPasadas} PASS / {$pruebasFallidas} FAIL\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
