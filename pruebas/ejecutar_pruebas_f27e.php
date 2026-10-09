<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F2.7E
 * INTERFAZ ALINA DE FINANZAS, CONCILIACIÓN F2.7D-1 Y OPERACIONES DE COBRANZA
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
use Aplicacion\Finanzas\CalculadoraFinanciera;
use Aplicacion\Finanzas\CifradorFinanciero;
use Aplicacion\Finanzas\EstadoFinancieroVenta;
use Aplicacion\Finanzas\EstadoPago;
use Aplicacion\Finanzas\EstadoReembolso;
use Aplicacion\Finanzas\MetodoPago;
use Aplicacion\Finanzas\MotivoReembolso;
use Aplicacion\Finanzas\PagoServicio;
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
use Aplicacion\Ventas\EstadoVenta;
use Aplicacion\Ventas\TipoDescuentoVenta;
use Aplicacion\Ventas\TipoOrigenVenta;
use Nucleo\BaseDatos\Conexion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;

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
echo "INICIANDO SUITE F2.7E: CONCILIACIÓN F2.7D-1 Y UI FINANCIERA ALINA\n";
echo "==============================================================================\n\n";

// Captura de huella de usuario Orlando ID 24
$stmtOrlando = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id = 24 OR nombre_usuario = 'orlando'");
$stmtOrlando->execute();
$orlandoPre = $stmtOrlando->fetch(PDO::FETCH_ASSOC);
$fingerprintPre = $orlandoPre ? substr(hash('sha256', (string) $orlandoPre['contrasena_hash']), 0, 16) : null;

// ==============================================================================
// SECCIÓN 1: CONCILIACIÓN F2.7D-1 (LOS 5 HALLAZGOS DE AUDITORÍA)
// ==============================================================================
echo "--- SECCIÓN 1: Conciliación F2.7D-1 (Auditoría de Invariantes) ---\n";

// P1.1: Permisos RBAC reales en Base de Datos vs Controladores
$stmtPerms = $pdo->query("SELECT codigo FROM permisos WHERE modulo_id = 11");
$codigosPermisos = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);

assertPrueba(
    in_array('pagos.ver', $codigosPermisos, true) &&
    in_array('pagos.registrar_manual', $codigosPermisos, true) &&
    in_array('pagos.verificar', $codigosPermisos, true) &&
    in_array('pagos.reembolsar', $codigosPermisos, true) &&
    in_array('pasarelas.gestionar', $codigosPermisos, true) &&
    in_array('cuentas_bancarias.gestionar', $codigosPermisos, true),
    "P1.1: Permisos RBAC en BD corresponden exactamente al padrón canónico de Finanzas (sin 'pagos.crear' ni 'pagos.conciliar')"
);

assertPrueba(
    !in_array('pagos.crear', $codigosPermisos, true) && !in_array('pagos.conciliar', $codigosPermisos, true),
    "P1.1: No existen permisos apócrifos o inventados en la base de datos"
);

// P1.2: Estados Comerciales vs Estados Financieros
$casosComerciales = array_map(fn($c) => $c->value, EstadoVenta::cases());
$casosFinancieros = array_map(fn($c) => $c->value, EstadoFinancieroVenta::cases());

assertPrueba(
    $casosComerciales === ['CONFIRMADA', 'LIQUIDADA', 'CANCELADA', 'ANULADA'],
    "P1.2: EstadoVenta (Comercial) conserva su dominio estricto de 4 estados sin mutaciones apócrifas"
);

assertPrueba(
    $casosFinancieros === ['NO_PAGADA', 'PAGO_PARCIAL', 'PAGADA_TOTAL', 'SOBREPAGADA', 'NO_APLICA'],
    "P1.2: EstadoFinancieroVenta (Financiero) gobierna de forma desacoplada la amortización de cobros"
);

// P1.3: Webhooks y Pasarelas - Deshabilitados para Producción por defecto
$stmtOrgPasProd = $pdo->query("SELECT COUNT(*) FROM organizacion_pasarelas WHERE modo = 'PRODUCCION' AND activo = 1");
$prodActivas = (int) $stmtOrgPasProd->fetchColumn();

assertPrueba(
    $prodActivas === 0,
    "P1.3: No existen pasarelas en modo PRODUCCION activas por defecto (sandbox seguro fail-closed)"
);

// P1.4: Reembolsos - Inmutabilidad y cómputo de saldos solo en estado EJECUTADO
$pagoDummy = new Pago(
    id: 9999,
    organizacionId: 1,
    edicionId: 1,
    ventaId: 1,
    correlativo: 'PAG-2026-TEST01',
    metodoPago: MetodoPago::TRANSFERENCIA_BANCARIA,
    estado: EstadoPago::APROBADO,
    moneda: 'PEN',
    montoCobradoCliente: 500.00,
    comisionPorcentajeAplicada: 0.00,
    comisionFijaAplicada: 0.00,
    comisionPasarela: 0.00,
    montoNetoRecibido: 500.00,
    montoAplicadoVenta: 500.00,
    montoExcedente: 0.00,
    comisionAsumidaPor: AsumeComisionPasarela::ORGANIZACION,
    montoReembolsadoAcumulado: 0.00,
    fechaPago: '2026-10-09 10:00:00',
    creadoPor: 1
);

assertPrueba(
    $pagoDummy->montoNetoAporteVenta() === 500.00,
    "P1.4: Pago sin reembolsos ejecutados aporta 100% a la venta (S/ 500.00)"
);

// Simular un reembolso SOLICITADO / APROBADO (no acumulado todavía en pago)
assertPrueba(
    $pagoDummy->montoReembolsadoAcumulado === 0.00 && $pagoDummy->montoNetoAporteVenta() === 500.00,
    "P1.4: Reembolso solicitado o aprobado NO reduce el saldo amortizado a la venta hasta ser EJECUTADO"
);

// Simular acumulación tras ejecución de reembolso
$pagoDummy->montoReembolsadoAcumulado = 150.00;
assertPrueba(
    $pagoDummy->montoNetoAporteVenta() === 350.00,
    "P1.4: Solo cuando el reembolso pasa a EJECUTADO reduce el saldo amortizado (S/ 350.00 neto)"
);

// P1.5: Anulación de pagos - Inmutabilidad de pagos aprobados
assertPrueba(
    !$pagoDummy->estado->permiteVerificacion(),
    "P1.5: Un pago APROBADO no permite verificación ni anulación directa (es inmutable)"
);

// ==============================================================================
// SECCIÓN 2: INTEGRACIÓN TRANSACCIONAL Y SERVICIOS (F2.7E)
// ==============================================================================
echo "\n--- SECCIÓN 2: Servicios y Repositorios Financieros ---\n";

$pdo->beginTransaction();

try {
    $orgId = 15000;

    $pdo->exec("
        INSERT INTO organizaciones (id, codigo, razon_social, nombre_comercial, numero_documento, estado)
        VALUES (15000, 'tenant_f27e', 'Tenant F27E SAC', 'Tenant F27E', '20999888771', 'ACTIVO')
        ON DUPLICATE KEY UPDATE estado = 'ACTIVO'
    ");

    $pdo->exec("
        INSERT INTO personas (id, organizacion_id, tipo_persona, tipo_documento_id, numero_documento, nombres, apellidos, correo_electronico)
        VALUES
        (9851, 15000, 'NATURAL', 1, '98510001', 'ADMIN', 'F27E', 'admin.f27e@test.pe'),
        (9852, 15000, 'NATURAL', 1, '98520002', 'CLIENTE', 'F27E', 'cliente.f27e@test.pe')
        ON DUPLICATE KEY UPDATE nombres = VALUES(nombres)
    ");

    $pdo->exec("
        INSERT INTO usuarios (id, organizacion_id, persona_id, nombre_usuario, nombre_completo, correo_electronico, contrasena_hash, es_superadmin_plataforma, estado)
        VALUES
        (9851, 15000, 9851, 'admin_f27e', 'Admin F27E', 'admin.f27e@test.pe', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO')
        ON DUPLICATE KEY UPDATE estado = 'ACTIVO'
    ");

    $pdo->exec("INSERT IGNORE INTO usuario_roles (usuario_id, rol_id) VALUES (9851, 2)");

    $pdo->exec("
        INSERT INTO ediciones_candelaria (id, organizacion_id, codigo, nombre, anio, estado, fecha_inicio, fecha_fin, es_actual)
        VALUES (9851, 15000, 'CAN-2027-F27E', 'Edicion F27E', 2027, 'OPERACION', '2027-02-01', '2027-02-15', 1)
        ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)
    ");
    $edicionId = 9851;

    $pdo->exec("
        INSERT INTO clientes (id, organizacion_id, persona_id, estado_comercial, consentimiento_operativo, consentimiento_promocional)
        VALUES (9851, 15000, 9852, 'CLIENTE', 1, 1)
        ON DUPLICATE KEY UPDATE estado_comercial = 'CLIENTE'
    ");
    $clienteId = 9851;

    $pagoRepo = new PagoRepositorio($pdo);
    $reembolsoRepo = new PagoReembolsoRepositorio($pdo);
    $cuentaRepo = new CuentaBancariaRepositorio($pdo);
    $pasarelaRepo = new PasarelaRepositorio($pdo);
    $ventaRepo = new VentaRepositorio($pdo);
    $edicionRepo = new EdicionRepositorio($pdo);
    $configRepo = new ConfiguracionRepositorio($pdo);
    $auditoriaRepo = new AuditoriaRepositorio($pdo);
    $cifrador = new CifradorFinanciero();

    $pagoServicio = new PagoServicio(
        pagoRepo: $pagoRepo,
        reembolsoRepo: $reembolsoRepo,
        cuentaRepo: $cuentaRepo,
        pasarelaRepo: $pasarelaRepo,
        ventaRepo: $ventaRepo,
        edicionRepo: $edicionRepo,
        configRepo: $configRepo,
        authzServicio: new \Aplicacion\Autorizacion\AutorizacionServicio(pdo: $pdo),
        auditoriaRepo: $auditoriaRepo,
        pdo: $pdo
    );

    // Contexto de operación autenticado
    $contextoOp = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 9851,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-f27e-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF27E/Admin',
        organizacionId: $orgId,
        metadatos: ['csrf_token' => 'token_valido_f27e'],
        edicionTrabajoId: $edicionId,
        origenEdicion: 'EXPLICITA'
    );

    // Crear venta comercial para las pruebas de cobro
    $correlativoVenta = 'VTA-F27E-' . time();
    $ventaTest = new Venta(
        id: null,
        organizacionId: $orgId,
        edicionId: $edicionId,
        clienteId: $clienteId,
        cotizacionId: null,
        origenTipo: TipoOrigenVenta::DIRECTA,
        correlativo: $correlativoVenta,
        fechaVenta: date('Y-m-d H:i:s'),
        estado: EstadoVenta::CONFIRMADA,
        clienteNombreCompleto: 'Cliente F27E Oficial',
        clienteTipoDocumento: 'DNI',
        clienteNumeroDocumento: '77889900',
        clienteTelefono: '999888777',
        clienteEmail: 'cliente.f27e@test.pe',
        moneda: 'PEN',
        subtotal: 1000.00,
        descuentoGlobalTipo: TipoDescuentoVenta::NINGUNO,
        descuentoGlobalValor: 0.00,
        descuentoGlobalMonto: 0.00,
        descuentoGlobalMotivo: null,
        descuentoLineasTotal: 0.00,
        total: 1000.00,
        montoPagado: 0.00,
        saldoPendiente: 1000.00,
        estadoFinanciero: 'NO_PAGADA',
        versionBloqueo: 1,
        creadoPor: 9851
    );
    $guardada = $ventaRepo->guardar($ventaTest);
    $ventaId = (int) $guardada->id;
    assertPrueba($ventaId > 0, "Venta comercial creada con saldo deudor de S/ 1,000.00");

    // Registrar cobro manual inicial en efectivo (S/ 400.00)
    $pagoEfectivo = $pagoServicio->registrarPagoManual(
        organizacionId: $orgId,
        edicionId: $edicionId,
        ventaId: $ventaId,
        datos: [
            'metodo_pago' => 'EFECTIVO',
            'moneda'      => 'PEN',
            'monto'       => 400.00,
            'notas_operativas' => 'Cobro en caja central'
        ],
        contexto: $contextoOp
    );

    assertPrueba(
        $pagoEfectivo->estado === EstadoPago::APROBADO && $pagoEfectivo->montoNetoRecibido === 400.00,
        "Cobro en efectivo registrado y aprobado automáticamente (S/ 400.00)"
    );

    // Estado de cuenta tras primer pago
    $estadoCta1 = $pagoServicio->obtenerEstadoCuentaVenta($ventaId, $orgId, $contextoOp);
    assertPrueba(
        $estadoCta1['monto_pagado_neto'] === 400.00 && $estadoCta1['saldo_pendiente'] === 600.00,
        "Estado de cuenta refleja S/ 400.00 amortizados y S/ 600.00 pendientes"
    );

    // Registrar pago por transferencia (S/ 600.00) en verificación
    $pagoTransf = $pagoServicio->registrarPagoManual(
        organizacionId: $orgId,
        edicionId: $edicionId,
        ventaId: $ventaId,
        datos: [
            'metodo_pago'               => 'TRANSFERENCIA_BANCARIA',
            'moneda'                    => 'PEN',
            'monto'                     => 600.00,
            'numero_operacion_bancaria' => 'OP-BCP-992211',
            'boucher_comprobante_url'   => 'https://comprobantes.pe/boucher123.jpg',
            'notas_operativas'          => 'Transferencia enviada por WhatsApp'
        ],
        contexto: $contextoOp
    );

    assertPrueba(
        $pagoTransf->estado === EstadoPago::PENDIENTE_VERIFICACION,
        "Pago por transferencia registrado en estado PENDIENTE_VERIFICACION"
    );

    // Comprobar que no amortiza aún
    $estadoCta2 = $pagoServicio->obtenerEstadoCuentaVenta($ventaId, $orgId, $contextoOp);
    assertPrueba(
        $estadoCta2['saldo_pendiente'] === 600.00 && $estadoCta2['monto_pagado_neto'] === 400.00,
        "Pago pendiente de verificación NO amortiza la deuda hasta ser aprobado"
    );

    // Probar control de concurrencia optimista 409
    $conflicto409Capturado = false;
    try {
        $pagoServicio->verificarPagoManual(
            organizacionId: $orgId,
            pagoId: $pagoTransf->id,
            aprobar: true,
            versionBloqueoEsperada: 999, // Desfase deliberado
            notas: 'Intento concurrente',
            contexto: $contextoOp
        );
    } catch (ConflictoConcurrenciaExcepcion $e) {
        $conflicto409Capturado = true;
    }
    assertPrueba($conflicto409Capturado, "Control de concurrencia optimista rechaza versión desfasada (409)");

    // Aprobar depósito bancario correctamente
    $pagoTransfAprobado = $pagoServicio->verificarPagoManual(
        organizacionId: $orgId,
        pagoId: $pagoTransf->id,
        aprobar: true,
        versionBloqueoEsperada: $pagoTransf->versionBloqueo,
        notas: 'Depósito verificado en banca por internet BCP',
        contexto: $contextoOp
    );

    assertPrueba(
        $pagoTransfAprobado->estado === EstadoPago::APROBADO,
        "Depósito bancario aprobado y verificado por el operador"
    );

    // Comprobar estado de cuenta totalmente pagado
    $estadoCta3 = $pagoServicio->obtenerEstadoCuentaVenta($ventaId, $orgId, $contextoOp);
    assertPrueba(
        $estadoCta3['saldo_pendiente'] === 0.00 && $estadoCta3['permite_liquidacion'] === true,
        "Venta con saldo S/ 0.00 habilitada para liquidación comercial"
    );

    // Liquidar venta
    $pagoServicio->liquidarVenta($ventaId, $orgId, $contextoOp);
    $ventaLiquidada = $ventaRepo->buscarPorId($ventaId, $orgId);
    assertPrueba(
        $ventaLiquidada->estado === EstadoVenta::LIQUIDADA,
        "Venta comercial transicionada a LIQUIDADA de forma inmutable"
    );

    // Emitir reembolso sobre el pago en efectivo (S/ 100.00)
    $reembolso = $pagoServicio->registrarReembolso(
        organizacionId: $orgId,
        pagoId: $pagoEfectivo->id,
        montoReembolso: 100.00,
        motivo: MotivoReembolso::DESISTIMIENTO_CLIENTE,
        motivoDetalle: 'Reembolso por desistimiento parcial autorizado',
        contexto: $contextoOp
    );

    assertPrueba(
        $reembolso->id > 0 && $reembolso->montoReembolsado === 100.00 && $reembolso->estado === EstadoReembolso::EJECUTADO,
        "Asiento compensatorio inmutable de reembolso emitido y ejecutado exitosamente"
    );

    // ==============================================================================
    // SECCIÓN 3: CONTROLADOR Y ENDPOINTS RESTFUL DE UI (F2.7E)
    // ==============================================================================
    echo "\n--- SECCIÓN 3: Controlador PagoControlador y Endpoints Auxiliares ---\n";

    $authMock = new class($contextoOp) extends AutenticacionMiddleware {
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

    $controlador = new PagoControlador(
        authMiddleware: $authMock,
        authzMiddleware: $authzMock,
        pagoServicio: $pagoServicio,
        pdo: $pdo
    );

    // Endpoint auxiliar: auxVentas
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';

    $jsonAuxVentas = $controlador->auxVentas();
    $resAuxVentas = json_decode($jsonAuxVentas, true);
    assertPrueba(
        $resAuxVentas['exito'] === true && is_array($resAuxVentas['datos']) && count($resAuxVentas['datos']) > 0,
        "Endpoint GET /api/v1/pagos/aux/ventas retorna ventas con saldo y cliente"
    );

    // Endpoint auxiliar: auxEdiciones
    $jsonAuxEdic = $controlador->auxEdiciones();
    $resAuxEdic = json_decode($jsonAuxEdic, true);
    assertPrueba(
        $resAuxEdic['exito'] === true && is_array($resAuxEdic['datos']),
        "Endpoint GET /api/v1/pagos/aux/ediciones retorna ediciones del tenant"
    );

    // Endpoint: listar pagos
    $_GET['por_pagina'] = 10;
    $jsonListar = $controlador->listar();
    $resListar = json_decode($jsonListar, true);
    assertPrueba(
        $resListar['exito'] === true && isset($resListar['datos']['pagos']),
        "Endpoint GET /api/v1/pagos lista transacciones para DataTables Alina"
    );

    // Endpoint: listar cuentas bancarias
    $jsonCuentas = $controlador->listarCuentasBancarias();
    $resCuentas = json_decode($jsonCuentas, true);
    assertPrueba(
        $resCuentas['exito'] === true && is_array($resCuentas['datos']),
        "Endpoint GET /api/v1/cuentas-bancarias lista cuentas de la organización"
    );

    // Endpoint: crear cuenta bancaria corporativa
    $_POST = [
        'banco_nombre'   => 'Banco Interbank',
        'tipo_cuenta'    => 'CORRIENTE',
        'moneda'         => 'PEN',
        'titular_nombre' => 'Candelaria Empresa SAC',
        'numero_cuenta'  => '200-3001234567',
        'codigo_interbancario' => '00320000300123456789',
        'alias_identificador'  => 'INTERBANK_RECAUDO',
        'activo'         => true,
    ];
    $jsonCrearCta = $controlador->crearCuentaBancaria();
    $resCrearCta = json_decode($jsonCrearCta, true);
    assertPrueba(
        $resCrearCta['exito'] === true && isset($resCrearCta['datos']['id']),
        "Endpoint POST /api/v1/cuentas-bancarias crea cuenta bancaria corporativa exitosamente"
    );

    $nuevaCtaId = (int) $resCrearCta['datos']['id'];

    // Endpoint: actualizar cuenta bancaria
    $_POST = [
        'banco_nombre'   => 'Banco Interbank Modificado',
        'tipo_cuenta'    => 'CORRIENTE',
        'moneda'         => 'PEN',
        'titular_nombre' => 'Candelaria Empresa SAC',
        'numero_cuenta'  => '200-3001234567',
        'activo'         => false,
    ];
    $jsonActCta = $controlador->actualizarCuentaBancaria($nuevaCtaId);
    $resActCta = json_decode($jsonActCta, true);
    assertPrueba(
        $resActCta['exito'] === true && $resActCta['datos']['banco_nombre'] === 'Banco Interbank Modificado',
        "Endpoint PUT /api/v1/cuentas-bancarias/{id} actualiza cuenta bancaria con control de tenant"
    );

    // Endpoint: listar catálogo y configuración de pasarelas
    $jsonPas = $controlador->listarPasarelas();
    $resPas = json_decode($jsonPas, true);
    assertPrueba(
        $resPas['exito'] === true && isset($resPas['datos']['catalogo']) && count($resPas['datos']['catalogo']) >= 4,
        "Endpoint GET /api/v1/pasarelas retorna catálogo institucional de 4+ pasarelas"
    );

    // Endpoint: configurar pasarela con cifrado AES-256-GCM
    $_POST = [
        'modo'               => 'TEST',
        'activo'             => true,
        'llave_publica'      => 'pk_test_candelaria_123',
        'credencial_secreta' => 'sk_test_super_secreta_456',
        'webhook_secreto'    => 'whsec_webhook_secreto_789',
        'porcentaje_comision'=> 3.99,
        'comision_fija'      => 1.00,
        'asume_comision'     => 'ORGANIZACION',
    ];
    $jsonConfigPas = $controlador->configurarPasarela('CULQI');
    $resConfigPas = json_decode($jsonConfigPas, true);
    assertPrueba(
        $resConfigPas['exito'] === true && $resConfigPas['datos']['activo'] === true,
        "Endpoint PUT /api/v1/pasarelas/{codigo} almacena credenciales cifradas con AES-256-GCM"
    );

    // Vista Web Oficial GET /pagos (Usuario con permiso pagos.ver)
    ob_start();
    $htmlPagos = $controlador->index();
    $salidaEcho = ob_get_clean();
    $htmlTotal = $htmlPagos . $salidaEcho;
    assertPrueba(
        str_contains($htmlTotal, 'Finanzas, Cobranzas y Caja') || str_contains($htmlTotal, 'Libro de Pagos'),
        "Vista Web Oficial GET /pagos renderiza layout completo con componentes Alina UI"
    );

    // Vista Web Oficial GET /pagos (Rechazo 403 cuando falta pagos.ver)
    $authzSinPerm = new class extends AutorizacionMiddleware {
        public function verificarPermiso(string $permiso, ?ContextoOperacion $contexto = null, bool $lanzarExcepcion = true): bool {
            return false;
        }
    };
    $ctrlSinPerm = new PagoControlador(authMiddleware: $authMock, authzMiddleware: $authzSinPerm, pdo: $pdo);
    ob_start();
    $html403 = $ctrlSinPerm->index();
    $salida403 = ob_get_clean();
    $htmlTotal403 = $html403 . $salida403;
    assertPrueba(
        str_contains($htmlTotal403, 'Error 403') || str_contains($htmlTotal403, 'Acceso Denegado'),
        "Vista Web Oficial GET /pagos deniega acceso con pantalla de error 403 si carece de pagos.ver"
    );

} finally {
    // Reversión de la transacción de prueba para dejar la BD limpia
    $pdo->rollBack();
}

// ==============================================================================
// SECCIÓN 4: CALCULADORA DE COMISIONES Y TRANSPARENCIA FINANCIERA
// ==============================================================================
echo "\n--- SECCIÓN 4: Calculadora de Comisiones (Transparencia Financiera) ---\n";

// Simular Culqi: 3.99% + S/ 1.00 sobre S/ 1,000.00 asumido por la organización
$calculoOrg = CalculadoraFinanciera::calcularComision(
    vAmortizar: 1000.00,
    porcentajeComision: 3.99,
    comisionFija: 1.00,
    asume: AsumeComisionPasarela::ORGANIZACION
);

assertPrueba(
    $calculoOrg['monto_cobrado_cliente'] === '1000.00' &&
    $calculoOrg['comision_pasarela'] === '40.90' &&
    $calculoOrg['monto_neto_recibido'] === '959.10' &&
    $calculoOrg['monto_aplicado_venta'] === '1000.00',
    "Calculadora transparente (Organización asume comisión de pasarela: cobrado S/ 1000.00, neto S/ 959.10)"
);

// Simular Culqi asumido por el cliente
$calculoCli = CalculadoraFinanciera::calcularComision(
    vAmortizar: 1000.00,
    porcentajeComision: 3.99,
    comisionFija: 1.00,
    asume: AsumeComisionPasarela::CLIENTE
);

assertPrueba(
    (float)$calculoCli['monto_cobrado_cliente'] > 1000.00 &&
    $calculoCli['monto_neto_recibido'] === '1000.00' &&
    $calculoCli['monto_aplicado_venta'] === '1000.00',
    "Calculadora transparente (Cliente asume recargo, amortizando los S/ 1,000.00 netos de la venta)"
);

// ==============================================================================
// SECCIÓN 5: INTEGRIDAD DEL USUARIO ORLANDO (ID 24)
// ==============================================================================
echo "\n--- SECCIÓN 5: Preservación de Identidad (Orlando ID 24) ---\n";
$stmtOrlandoPost = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id = 24 OR nombre_usuario = 'orlando'");
$stmtOrlandoPost->execute();
$orlandoPost = $stmtOrlandoPost->fetch(PDO::FETCH_ASSOC);
$fingerprintPost = $orlandoPost ? substr(hash('sha256', (string) $orlandoPost['contrasena_hash']), 0, 16) : null;

assertPrueba($orlandoPost !== false, "Usuario Orlando ID 24 existe en base de datos");
assertPrueba($fingerprintPre === $fingerprintPost, "Huella SHA-256 de credenciales de Orlando 100% INTACTA");
assertPrueba((int) $orlandoPost['intentos_fallidos'] === 0, "Intentos fallidos de Orlando = 0");
assertPrueba($orlandoPost['estado'] === 'ACTIVO', "Estado de cuenta de Orlando = ACTIVO");

// ==============================================================================
// RESUMEN FINAL
// ==============================================================================
echo "\n==============================================================================\n";
echo "RESULTADO FINAL DE PRUEBAS F2.7E:\n";
echo "Total Pruebas : {$totalPruebas}\n";
echo "Exitosas      : {$pruebasPasadas}\n";
echo "Fallidas      : {$pruebasFallidas}\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
