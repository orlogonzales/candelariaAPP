<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F2.7C
 * DOMINIO FINANCIERO, PAGOS, PASARELAS, COMISIONES Y LIQUIDACIÓN DE VENTAS
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Entidades\CuentaBancariaOrganizacion;
use Aplicacion\Entidades\Edicion;
use Aplicacion\Entidades\OrganizacionPasarela;
use Aplicacion\Entidades\Pago;
use Aplicacion\Entidades\PagoReembolso;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Entidades\Venta;
use Aplicacion\Entidades\VentaLinea;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
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
use Aplicacion\Ventas\TipoLineaVenta;
use Aplicacion\Ventas\TipoOrigenVenta;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F2.7C\n";
echo "DOMINIO FINANCIERO, PAGOS, PASARELAS, COMISIONES Y LIQUIDACIÓN DE VENTAS\n";
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

    $cuentaRepo = new CuentaBancariaRepositorio($pdo);
    $pasarelaRepo = new PasarelaRepositorio($pdo);
    $pagoRepo = new PagoRepositorio($pdo);
    $reembolsoRepo = new PagoReembolsoRepositorio($pdo);

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

    // ==========================================================================
    // FIXTURES DETERMINISTAS
    // ==========================================================================
    $orgIdA = 10000; // Tenant Principal
    $orgIdB = 20000; // Tenant B para pruebas de aislamiento

    // Tenant B fixture
    $pdo->exec("
        INSERT INTO organizaciones (id, codigo, razon_social, nombre_comercial, numero_documento, estado)
        VALUES (20000, 'tenant_test_b', 'Tenant Test B SAC', 'Tenant B', '20999999999', 'ACTIVO')
        ON DUPLICATE KEY UPDATE estado = 'ACTIVO'
    ");

    // Actores sintéticos deterministas para pruebas RBAC
    $adminSinteticoAId = 9921;
    $operadorSinteticoAId = 9922;
    $adminSinteticoBId = 9923;

    $pdo->exec("
        INSERT INTO personas (id, organizacion_id, tipo_persona, tipo_documento_id, numero_documento, nombres, apellidos, correo_electronico)
        VALUES
        (9921, 10000, 'NATURAL', 1, '99210001', 'ADMIN', 'FINANZAS A', 'admin.fin.a@test.local'),
        (9922, 10000, 'NATURAL', 1, '99220002', 'OPERADOR', 'FINANZAS A', 'op.fin.a@test.local'),
        (9923, 20000, 'NATURAL', 1, '99230003', 'ADMIN', 'FINANZAS B', 'admin.fin.b@test.local')
        ON DUPLICATE KEY UPDATE nombres = VALUES(nombres)
    ");

    $pdo->exec("
        INSERT INTO usuarios (id, organizacion_id, persona_id, nombre_usuario, nombre_completo, correo_electronico, contrasena_hash, es_superadmin_plataforma, estado)
        VALUES
        (9921, 10000, 9921, 'admin_fin_a', 'Admin Finanzas A', 'admin.fin.a@test.local', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO'),
        (9922, 10000, 9922, 'operador_fin_a', 'Operador Finanzas A', 'op.fin.a@test.local', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO'),
        (9923, 20000, 9923, 'admin_fin_b', 'Admin Finanzas B', 'admin.fin.b@test.local', '\$2y\$10\$abcdefghijklmnopqrstuv', 0, 'ACTIVO')
        ON DUPLICATE KEY UPDATE estado = 'ACTIVO'
    ");

    $pdo->exec("
        INSERT IGNORE INTO usuario_roles (usuario_id, rol_id) VALUES
        (9921, 2),
        (9922, 3),
        (9923, 2)
    ");

    // Contexto Admin A (rol_id = 2 en Org 10000)
    $ctxAdminA = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: $adminSinteticoAId,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-fin-admina-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF27C/AdminA',
        organizacionId: $orgIdA
    );

    // Contexto Operador A (rol_id = 4 en Org 10000 - solo pagos.ver y pagos.registrar_manual)
    $ctxOperadorA = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: $operadorSinteticoAId,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-fin-operadora-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF27C/OperadorA',
        organizacionId: $orgIdA
    );

    // Contexto Admin B (rol_id = 2 en Org 20000)
    $ctxAdminB = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: $adminSinteticoBId,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: 'corr-test-fin-adminb-01',
        origenIp: '127.0.0.1',
        agenteUsuario: 'SuiteF27C/AdminB',
        organizacionId: $orgIdB
    );

    // Edición A
    $stmtEdic = $pdo->prepare("SELECT id FROM ediciones_candelaria WHERE organizacion_id = :org_id ORDER BY id ASC LIMIT 1");
    $stmtEdic->execute(['org_id' => $orgIdA]);
    $edicionAId = (int) $stmtEdic->fetchColumn();
    if ($edicionAId === 0) {
        $stmtInsEd = $pdo->prepare("
            INSERT INTO ediciones_candelaria (organizacion_id, codigo, nombre, anio, estado, fecha_inicio, fecha_fin, es_actual)
            VALUES (:org, 'CAN-2027-TEST', 'Candelaria 2027 Test', 2027, 'OPERACION', '2027-02-01', '2027-02-15', 1)
        ");
        $stmtInsEd->execute(['org' => $orgIdA]);
        $edicionAId = (int) $pdo->lastInsertId();
    }

    // Cliente A
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

    // Venta A1 (Total S/ 1,000.00)
    $correlativoV1 = $ventaRepo->generarSiguienteCorrelativo($orgIdA, 2027);
    $venta1 = new Venta(
        id: null,
        organizacionId: $orgIdA,
        edicionId: $edicionAId,
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
    $venta1Id = $guardada1->id;

    // ==========================================================================
    // BLOQUE 1: CIFRADOR FINANCIERO AES-256-GCM Y CALCULADORA DECIMAL BCMATH
    // ==========================================================================
    echo "\n--- BLOQUE 1: CIFRADOR AUTENTICADO AES-256-GCM Y CALCULADORA BCMATH ---\n";

    $cifrador = new CifradorFinanciero('clave_maestra_de_prueba_segura_32_bytes_ok');
    $secretoPlano = 'sk_live_culqi_secret_token_123456789';
    $cifrado = $cifrador->cifrar($secretoPlano);
    afirmar($cifrado !== $secretoPlano && strlen($cifrado) > 30, 'Cifrado autenticado AES-256-GCM genera payload base64 no trivial');

    $descifrado = $cifrador->descifrar($cifrado);
    afirmar($descifrado === $secretoPlano, 'Descifrado recupera exactamente el secreto original');

    $payloadCorrupto = substr_replace($cifrado, 'X', 15, 1);
    $falloTamper = false;
    try {
        $cifrador->descifrar($payloadCorrupto);
    } catch (\RuntimeException $e) {
        $falloTamper = true;
    }
    afirmar($falloTamper, 'GCM rechaza criptográficamente payload alterado (Tamper-Proof tag verification)');

    // Calculadora BCMath
    $calcSuma = CalculadoraFinanciera::sumar('100.5555', '200.4444');
    afirmar($calcSuma === '301.00', 'CalculadoraFinanciera::sumar aplica BCMath y redondeo a 2 decimales (301.00)');

    $comisionOrg = CalculadoraFinanciera::calcularComision('1000.00', '3.99', '1.00', AsumeComisionPasarela::ORGANIZACION);
    afirmar($comisionOrg['monto_cobrado_cliente'] === '1000.00', 'D-01 (ORGANIZACION): Cliente paga precio de lista exacto S/ 1000.00');
    afirmar($comisionOrg['comision_pasarela'] === '40.90', 'D-01 (ORGANIZACION): Comisión calculada exactamente S/ 40.90');
    afirmar($comisionOrg['monto_neto_recibido'] === '959.10', 'D-01 (ORGANIZACION): Empresa recibe líquido S/ 959.10');

    $comisionCli = CalculadoraFinanciera::calcularComision('1000.00', '3.99', '1.00', AsumeComisionPasarela::CLIENTE);
    afirmar($comisionCli['monto_cobrado_cliente'] === '1042.60', 'D-01 (CLIENTE): Cliente asume recargo exacto S/ 1042.60');
    afirmar($comisionCli['monto_neto_recibido'] === '1000.00', 'D-01 (CLIENTE): Empresa recibe líquido exactamente S/ 1000.00');
    afirmar($comisionCli['comision_pasarela'] === '42.60', 'D-01 (CLIENTE): Comisión trasladada calculada S/ 42.60');

    // ==========================================================================
    // BLOQUE 2: PADRÓN DE CUENTAS BANCARIAS DE ORGANIZACIÓN Y MULTITENANCY
    // ==========================================================================
    echo "\n--- BLOQUE 2: CUENTAS BANCARIAS DE ORGANIZACIÓN Y MULTITENANT ---\n";

    $cuentaBcpA = new CuentaBancariaOrganizacion(
        id: null,
        organizacionId: $orgIdA,
        bancoNombre: 'BCP',
        tipoCuenta: 'CORRIENTE',
        moneda: 'PEN',
        titularNombre: 'O.G. ESTUDIO CREATIVO S.A.C.',
        numeroCuenta: '193-98765432-0-12',
        codigoInterbancario: '00219300987654320123',
        aliasIdentificador: 'BCP Operativo Principal'
    );
    $cuentaBcpAId = $cuentaRepo->crear($cuentaBcpA);
    afirmar($cuentaBcpAId > 0, 'Cuenta bancaria creada exitosamente en cuentas_bancarias_organizacion');

    $cuentaConsultada = $cuentaRepo->buscarPorId($cuentaBcpAId, $orgIdA);
    afirmar($cuentaConsultada !== null && $cuentaConsultada->bancoNombre === 'BCP', 'Buscar cuenta por ID y organización exitoso');

    $cuentaIdor = $cuentaRepo->buscarPorId($cuentaBcpAId, $orgIdB);
    afirmar($cuentaIdor === null, 'Anti-IDOR: Tenant B no puede acceder a cuenta bancaria de Tenant A');

    // ==========================================================================
    // BLOQUE 3: CATÁLOGO DE PASARELAS Y HABILITACIÓN POR EDICIÓN
    // ==========================================================================
    echo "\n--- BLOQUE 3: CATÁLOGO DE PASARELAS Y HABILITACIÓN POR EDICIÓN ---\n";

    $catalogoPasarelas = $pasarelaRepo->listarCatalogo();
    afirmar(count($catalogoPasarelas) >= 5, 'Catálogo institucional contiene al menos 5 pasarelas sembradas (CULQI, NIUBIZ, STRIPE...)');

    $pasCulqi = $pasarelaRepo->buscarPasarelaPorCodigo('CULQI');
    afirmar($pasCulqi !== null && $pasCulqi->tipoIntegracion === 'CHECKOUT_WEB', 'Búsqueda por código de pasarela CULQI exitosa');

    // Configurar Culqi para Tenant A con regla D-01 CLIENTE
    $cfgCulqiA = new OrganizacionPasarela(
        id: null,
        organizacionId: $orgIdA,
        pasarelaId: $pasCulqi->id,
        modo: 'TEST',
        identificadorComercio: 'pk_test_sample123',
        credencialSecretaEnc: $cifrador->cifrar('sk_test_sample456'),
        webhookSecretoEnc: $cifrador->cifrar('whsec_sample789'),
        porcentajeComision: 3.99,
        comisionFija: 1.00,
        asumeComision: AsumeComisionPasarela::CLIENTE,
        activo: true
    );
    $orgPasarelaAId = $pasarelaRepo->guardarConfiguracionOrganizacion($cfgCulqiA);
    afirmar($orgPasarelaAId > 0, 'Configuración de pasarela guardada con secreto cifrado y regla D-01');

    // Habilitar Culqi en Edición 2027
    $pasarelaRepo->habilitarPasarelaEdicion($orgIdA, $edicionAId, $orgPasarelaAId, true);
    $pasHabilitadas = $pasarelaRepo->listarPasarelasHabilitadasEdicion($orgIdA, $edicionAId);
    afirmar(count($pasHabilitadas) >= 1, 'Pasarela habilitada exitosamente para la edición anual activa');

    // ==========================================================================
    // BLOQUE 4: COBRO MANUAL EN EFECTIVO (APROBACIÓN INMEDIATA Y AMORTIZACIÓN)
    // ==========================================================================
    echo "\n--- BLOQUE 4: COBRO MANUAL EN EFECTIVO Y AMORTIZACIÓN ---\n";

    // Cobro parcial de S/ 400.00 en efectivo
    $pago1 = $pagoServicio->registrarPagoManual(
        organizacionId: $orgIdA,
        edicionId: $edicionAId,
        ventaId: $venta1Id,
        datos: [
            'metodo_pago'        => 'EFECTIVO',
            'monto'              => 400.00,
            'notas_operativas'   => 'Anticipo 40% entregado en caja física',
            'clave_idempotencia' => 'idemp_pago_efectivo_001',
        ],
        contexto: $ctxAdminA
    );

    afirmar($pago1->id > 0, 'Pago en efectivo registrado con ID numérico');
    afirmar((bool) preg_match('/^PAG-\d{4}-\d{6}$/', $pago1->correlativo), 'Correlativo de pago PAG-AAAA-XXXXXX generado concurrentemente');
    afirmar($pago1->estado === EstadoPago::APROBADO, 'Pago en efectivo queda APROBADO de forma inmediata');
    afirmar($pago1->montoAplicadoVenta === 400.00, 'Monto aplicado a la venta es exactamente S/ 400.00');

    // Verificar amortización en venta
    $venta1Consultada = $ventaRepo->buscarPorId($venta1Id, $orgIdA);
    afirmar($venta1Consultada->montoPagado === 400.00, 'Venta: monto_pagado acumulado actualizado a S/ 400.00');
    afirmar($venta1Consultada->saldoPendiente === 600.00, 'Venta: saldo_pendiente recalculado a S/ 600.00');
    afirmar($venta1Consultada->estadoFinanciero === 'PAGO_PARCIAL', 'Venta: estado_financiero conmuta a PAGO_PARCIAL');

    // Idempotencia: reintento con misma clave
    $pago1Reintento = $pagoServicio->registrarPagoManual(
        organizacionId: $orgIdA,
        edicionId: $edicionAId,
        ventaId: $venta1Id,
        datos: [
            'metodo_pago'        => 'EFECTIVO',
            'monto'              => 400.00,
            'clave_idempotencia' => 'idemp_pago_efectivo_001',
        ],
        contexto: $ctxAdminA
    );
    afirmar($pago1Reintento->id === $pago1->id, 'Idempotencia: Reintento con misma clave devuelve el pago original sin duplicar cobro');

    // ==========================================================================
    // BLOQUE 5: COBRO POR TRANSFERENCIA (PENDIENTE -> APROBACIÓN CON BOUCHER)
    // ==========================================================================
    echo "\n--- BLOQUE 5: TRANSFERENCIA BANCARIA Y VERIFICACIÓN DE BOUCHER ---\n";

    $pago2 = $pagoServicio->registrarPagoManual(
        organizacionId: $orgIdA,
        edicionId: $edicionAId,
        ventaId: $venta1Id,
        datos: [
            'metodo_pago'               => 'TRANSFERENCIA_BANCARIA',
            'monto'                     => 300.00,
            'cuenta_bancaria_id'        => $cuentaBcpAId,
            'numero_operacion_bancaria' => 'OP-BCP-778899',
            'boucher_comprobante_url'   => 'almacenamiento/archivos/bouchers/boucher_001.jpg',
            'clave_idempotencia'        => 'idemp_pago_transf_002',
        ],
        contexto: $ctxOperadorA
    );

    afirmar($pago2->estado === EstadoPago::PENDIENTE_VERIFICACION, 'Transferencia bancaria queda PENDIENTE_VERIFICACION');
    // Como está pendiente, la venta no amortiza todavía
    $venta1PostP2 = $ventaRepo->buscarPorId($venta1Id, $orgIdA);
    afirmar($venta1PostP2->montoPagado === 400.00 && $venta1PostP2->saldoPendiente === 600.00, 'Pago pendiente de verificación NO muta el saldo de la venta');

    // Operador sin permiso pagos.verificar intenta aprobar -> 403
    $falloPermVerif = false;
    try {
        $pagoServicio->verificarPagoManual(
            organizacionId: $orgIdA,
            pagoId: $pago2->id,
            aprobar: true,
            versionBloqueoEsperada: 1,
            contexto: $ctxOperadorA
        );
    } catch (AccesoDenegadoExcepcion $e) {
        $falloPermVerif = true;
    }
    afirmar($falloPermVerif, 'Operador sin privilegio pagos.verificar es rechazado con AccesoDenegadoExcepcion');

    // Admin A aprueba la transferencia
    $pago2Aprobado = $pagoServicio->verificarPagoManual(
        organizacionId: $orgIdA,
        pagoId: $pago2->id,
        aprobar: true,
        versionBloqueoEsperada: 1,
        notas: 'Depósito verificado en banca por internet BCP',
        contexto: $ctxAdminA
    );
    afirmar($pago2Aprobado->estado === EstadoPago::APROBADO, 'Transferencia pasa a estado APROBADO tras verificación');
    afirmar($pago2Aprobado->verificadoPor === $adminSinteticoAId, 'ID de usuario verificador registrado formalmente');

    // Venta ahora acumula S/ 700.00 pagados y resta S/ 300.00 de saldo
    $venta1PostAprob = $ventaRepo->buscarPorId($venta1Id, $orgIdA);
    afirmar($venta1PostAprob->montoPagado === 700.00, 'Venta: monto_pagado acumulado sube a S/ 700.00 tras aprobación');
    afirmar($venta1PostAprob->saldoPendiente === 300.00, 'Venta: saldo_pendiente baja a S/ 300.00');

    // ==========================================================================
    // BLOQUE 6: INVARIANTE DE MONTO MÍNIMO GLOBAL (S/ 50.00)
    // ==========================================================================
    echo "\n--- BLOQUE 6: MONTO MÍNIMO GLOBAL (S/ 50.00) ---\n";

    $falloMinimo = false;
    try {
        $pagoServicio->registrarPagoManual(
            organizacionId: $orgIdA,
            edicionId: $edicionAId,
            ventaId: $venta1Id,
            datos: [
                'metodo_pago' => 'EFECTIVO',
                'monto'       => 20.00, // Menor que 50.00 y saldo es 300.00
            ],
            contexto: $ctxAdminA
        );
    } catch (\InvalidArgumentException $e) {
        $falloMinimo = true;
    }
    afirmar($falloMinimo, 'Intento de pago inferior al monto mínimo global (S/ 50.00) es rechazado');

    // ==========================================================================
    // BLOQUE 7: TRATAMIENTO RIGUROSO DE SOBREPAGOS
    // ==========================================================================
    echo "\n--- BLOQUE 7: TRATAMIENTO DE SOBREPAGOS (EXCEDENTE A FAVOR) ---\n";

    // Saldo pendiente es S/ 300.00. Cliente paga S/ 350.00 en efectivo
    $pago3 = $pagoServicio->registrarPagoManual(
        organizacionId: $orgIdA,
        edicionId: $edicionAId,
        ventaId: $venta1Id,
        datos: [
            'metodo_pago'        => 'EFECTIVO',
            'monto'              => 350.00,
            'clave_idempotencia' => 'idemp_pago_sobrepago_003',
        ],
        contexto: $ctxAdminA
    );

    afirmar($pago3->montoAplicadoVenta === 300.00, 'Sobrepago: monto_aplicado_venta se topa exactamente en el saldo adeudado (S/ 300.00)');
    afirmar($pago3->montoExcedente === 50.00, 'Sobrepago: monto_excedente almacena S/ 50.00 a favor del cliente');

    $venta1Sobrepagada = $ventaRepo->buscarPorId($venta1Id, $orgIdA);
    afirmar($venta1Sobrepagada->montoPagado === 1000.00, 'Venta: monto_pagado alcanza exactamente el total de S/ 1,000.00');
    afirmar($venta1Sobrepagada->saldoPendiente === 0.00, 'Venta: saldo_pendiente es exactamente 0.00 (nunca negativo)');
    afirmar($venta1Sobrepagada->estadoFinanciero === 'SOBREPAGADA', 'Venta: estado_financiero conmuta a SOBREPAGADA');

    // ==========================================================================
    // BLOQUE 8: ASIENTOS COMPENSATORIOS DE REEMBOLSO (APPEND-ONLY)
    // ==========================================================================
    echo "\n--- BLOQUE 8: ASIENTOS COMPENSATORIOS DE REEMBOLSO (APPEND-ONLY) ---\n";

    // Devolución formal de S/ 100.00 sobre el pago 1
    $reembolso1 = $pagoServicio->registrarReembolso(
        organizacionId: $orgIdA,
        pagoId: $pago1->id,
        montoReembolso: 100.00,
        motivo: MotivoReembolso::AJUSTE_COMERCIAL,
        motivoDetalle: 'Devolución acordada por cambio de categoría',
        contexto: $ctxAdminA
    );

    afirmar($reembolso1->id > 0, 'Reembolso compensatorio registrado con ID propio');
    afirmar((bool) preg_match('/^REEM-\d{4}-\d{6}$/', $reembolso1->correlativo), 'Correlativo de reembolso REEM-AAAA-XXXXXX generado');
    afirmar($reembolso1->estado === EstadoReembolso::EJECUTADO, 'Reembolso compensatorio queda en estado EJECUTADO');

    // Verificar inmutabilidad del pago 1: su estado sigue siendo APROBADO
    $pago1PostReem = $pagoRepo->buscarPorId($pago1->id, $orgIdA);
    afirmar($pago1PostReem->estado === EstadoPago::APROBADO, 'Inmutabilidad: El pago original APROBADO NO muta de estado');
    afirmar($pago1PostReem->montoReembolsadoAcumulado === 100.00, 'Pago original acumula S/ 100.00 de reembolsos ejecutados');
    afirmar($pago1PostReem->montoNetoAporteVenta() === 300.00, 'Aporte neto del pago a la venta se deriva a S/ 300.00');

    // Venta ahora tiene saldo pendiente de S/ 100.00
    $venta1PostReem = $ventaRepo->buscarPorId($venta1Id, $orgIdA);
    afirmar($venta1PostReem->montoPagado === 900.00, 'Venta: monto_pagado neto disminuye a S/ 900.00');
    afirmar($venta1PostReem->saldoPendiente === 100.00, 'Venta: saldo_pendiente se incrementa a S/ 100.00 tras devolución');
    afirmar($venta1PostReem->estadoFinanciero === 'PAGO_PARCIAL', 'Venta: estado_financiero retorna a PAGO_PARCIAL');

    // Intento de reembolsar más de lo disponible en el pago
    $falloExcesoReem = false;
    try {
        $pagoServicio->registrarReembolso(
            organizacionId: $orgIdA,
            pagoId: $pago1->id,
            montoReembolso: 350.00, // Disponible solo 300.00
            motivo: MotivoReembolso::AJUSTE_COMERCIAL,
            motivoDetalle: 'Intento de exceso',
            contexto: $ctxAdminA
        );
    } catch (\InvalidArgumentException $e) {
        $falloExcesoReem = true;
    }
    afirmar($falloExcesoReem, 'Intento de reembolso mayor al saldo remanente del pago es rechazado');

    // ==========================================================================
    // BLOQUE 9: TRANSICIÓN COMERCIAL A LIQUIDADA
    // ==========================================================================
    echo "\n--- BLOQUE 9: TRANSICIÓN COMERCIAL A LIQUIDADA ---\n";

    // Intento de liquidar venta1 con saldo pendiente de S/ 100.00 -> Rechazado
    $falloLiquidarConSaldo = false;
    try {
        $pagoServicio->liquidarVenta($venta1Id, $orgIdA, $ctxAdminA);
    } catch (\InvalidArgumentException $e) {
        $falloLiquidarConSaldo = true;
    }
    afirmar($falloLiquidarConSaldo, 'Intento de liquidar venta con saldo pendiente S/ 100.00 es rechazado con 422');

    // Registrar pago liquidatorio exacto de S/ 100.00
    $pagoLiquidacion = $pagoServicio->registrarPagoManual(
        organizacionId: $orgIdA,
        edicionId: $edicionAId,
        ventaId: $venta1Id,
        datos: [
            'metodo_pago' => 'EFECTIVO',
            'monto'       => 100.00,
        ],
        contexto: $ctxAdminA
    );
    afirmar($pagoLiquidacion->estado === EstadoPago::APROBADO, 'Pago liquidatorio de S/ 100.00 aprobado');

    $estadoCuentaFinal = $pagoServicio->obtenerEstadoCuentaVenta($venta1Id, $orgIdA, $ctxAdminA);
    afirmar($estadoCuentaFinal['saldo_pendiente'] === 0.00, 'Estado de cuenta: saldo_pendiente es exactamente 0.00');
    afirmar($estadoCuentaFinal['permite_liquidacion'] === true, 'Estado de cuenta: permite_liquidacion es true');

    // Ahora liquidar la venta exitosamente
    $pagoServicio->liquidarVenta($venta1Id, $orgIdA, $ctxAdminA);
    $ventaLiquidada = $ventaRepo->buscarPorId($venta1Id, $orgIdA);
    afirmar($ventaLiquidada->estado === EstadoVenta::LIQUIDADA, 'Venta transiciona formalmente a estado comercial LIQUIDADA');

    // Inmutabilidad de venta liquidada: intento de nuevo pago rechazado
    $falloPagoEnLiquidada = false;
    try {
        $pagoServicio->registrarPagoManual(
            organizacionId: $orgIdA,
            edicionId: $edicionAId,
            ventaId: $venta1Id,
            datos: [
                'metodo_pago' => 'EFECTIVO',
                'monto'       => 50.00,
            ],
            contexto: $ctxAdminA
        );
    } catch (\InvalidArgumentException $e) {
        $falloPagoEnLiquidada = true;
    }
    afirmar($falloPagoEnLiquidada, 'Venta LIQUIDADA no admite nuevos cobros regulares');

    // ==========================================================================
    // BLOQUE 10: ANTI-IDOR, CONCURRENCIA 409 Y PRESERVACIÓN DE ORLANDO
    // ==========================================================================
    echo "\n--- BLOQUE 10: ANTI-IDOR, CONCURRENCIA 409 Y PRESERVACIÓN DE ORLANDO ---\n";

    // Anti-IDOR: Tenant B intenta consultar pago de Tenant A
    $pagoIdor = $pagoRepo->buscarPorId($pago1->id, $orgIdB);
    afirmar($pagoIdor === null, 'Anti-IDOR: Tenant B no puede acceder a pago de Tenant A');

    // Anti-IDOR: Tenant B intenta registrar pago en venta de Tenant A
    $falloIdorVenta = false;
    try {
        $pagoServicio->registrarPagoManual(
            organizacionId: $orgIdB,
            edicionId: $edicionAId,
            ventaId: $venta1Id,
            datos: [
                'metodo_pago' => 'EFECTIVO',
                'monto'       => 50.00,
            ],
            contexto: $ctxAdminB
        );
    } catch (\InvalidArgumentException $e) {
        $falloIdorVenta = true;
    }
    afirmar($falloIdorVenta, 'Anti-IDOR: Tenant B no puede registrar pagos en venta de Tenant A');

    // Crear venta2 CONFIRMADA para probar concurrencia de pagos
    $correlativoV2 = $ventaRepo->generarSiguienteCorrelativo($orgIdA, 2027);
    $venta2 = new Venta(
        id: null,
        organizacionId: $orgIdA,
        edicionId: $edicionAId,
        clienteId: $clienteAId,
        cotizacionId: null,
        origenTipo: TipoOrigenVenta::DIRECTA,
        correlativo: $correlativoV2,
        fechaVenta: '2027-01-16',
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
    $guardada2 = $ventaRepo->guardar($venta2);
    $venta2Id = $guardada2->id;

    // Conflicto de Concurrencia 409 en verificación
    $pagoTest409 = $pagoServicio->registrarPagoManual(
        organizacionId: $orgIdA,
        edicionId: $edicionAId,
        ventaId: $venta2Id,
        datos: [
            'metodo_pago'               => 'TRANSFERENCIA_BANCARIA',
            'monto'                     => 50.00,
            'numero_operacion_bancaria' => 'OP-409-TEST',
            'aprobacion_inmediata'      => false,
        ],
        contexto: $ctxAdminA
    );

    $fallo409 = false;
    try {
        $pagoServicio->verificarPagoManual(
            organizacionId: $orgIdA,
            pagoId: $pagoTest409->id,
            aprobar: true,
            versionBloqueoEsperada: 99, // Desincronizado
            contexto: $ctxAdminA
        );
    } catch (ConflictoConcurrenciaExcepcion $e) {
        $fallo409 = true;
    }
    afirmar($fallo409, 'Control de Concurrencia: Verificación con version_bloqueo desfasada arroja ConflictoConcurrenciaExcepcion (HTTP 409)');

    // Preservación estricta de Orlando (ID 24)
    $stmtOrlandoPost = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id = 24 OR nombre_usuario = 'orlando'");
    $stmtOrlandoPost->execute();
    $orlandoPost = $stmtOrlandoPost->fetch(PDO::FETCH_ASSOC);
    $fingerprintOrlandoPost = $orlandoPost ? substr(hash('sha256', (string) $orlandoPost['contrasena_hash']), 0, 16) : null;

    afirmar($fingerprintOrlandoPre === $fingerprintOrlandoPost, 'Preservación de Orlando: Huella SHA-256 de contraseña intacta');
    afirmar($orlandoPost['estado'] === 'ACTIVO', 'Preservación de Orlando: Estado ACTIVO');
    afirmar($orlandoPost['intentos_fallidos'] == 0, 'Preservación de Orlando: Intentos fallidos en 0');

} finally {
    // Revertir transacción para dejar la base de datos libre de fixtures de prueba
    $pdo->rollBack();
}

echo "\n==============================================================================\n";
echo "RESULTADOS SUITE F2.7C: ÉXITOS = {$exitos} | FALLOS = {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
