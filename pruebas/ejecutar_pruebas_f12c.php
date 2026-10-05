<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Controladores\ConfiguracionControlador;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Repositorios\ActorSistemaRepositorio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\SesionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Seguridad\AutenticacionServicio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE CONFIGURACIÓN GENERAL Y PARÁMETROS F1.2C\n";
echo "SOBERANÍA PLATAFORMA/ORGANIZACIÓN, FAIL-CLOSED, CONCURRENCIA 409, CSRF Y AUDITORÍA\n";
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

function crearMockAuthMiddleware(?ContextoOperacion $contexto): AutenticacionMiddleware
{
    return new class($contexto) extends AutenticacionMiddleware {
        private ?ContextoOperacion $ctx;
        public function __construct(?ContextoOperacion $ctx) { $this->ctx = $ctx; }
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion
        {
            if ($this->ctx === null && $bloquearSiInvalido) {
                http_response_code(401);
                throw new \Aplicacion\Excepciones\AccesoDenegadoExcepcion('No autenticado', null, 401);
            }
            return $this->ctx;
        }
    };
}

function simularPeticionJson(array $datos): void
{
    $_POST = $datos;
}

// 0. Instanciar Repositorios y Servicios
$orgRepo       = new OrganizacionRepositorio($pdo);
$usuarioRepo   = new UsuarioRepositorio($pdo);
$personaRepo   = new PersonaRepositorio($pdo);
$rolRepo       = new RolRepositorio($pdo);
$permisoRepo   = new PermisoRepositorio($pdo);
$sesionRepo    = new SesionRepositorio($pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);
$actorSysRepo  = new ActorSistemaRepositorio($pdo);
$configRepo    = new ConfiguracionRepositorio($pdo);

$authzServicio  = new AutorizacionServicio($rolRepo, $permisoRepo, $usuarioRepo, $auditoriaRepo, $pdo);
$configServicio = new ConfiguracionServicio($configRepo, $orgRepo, $authzServicio, $auditoriaRepo, $pdo);
$authServicio   = new AutenticacionServicio($usuarioRepo, $sesionRepo, $auditoriaRepo, $actorSysRepo, $pdo, $configServicio);

$pdo->beginTransaction();

try {
    // ==============================================================================
    // CONFIGURACIÓN DE OPERADORES Y CONTEXTOS DE PRUEBA
    // ==============================================================================
    $org10000 = $orgRepo->buscarPorId(10000);
    afirmar($org10000 !== null, '00. Organización operativa 10000 existe en la base de datos');

    $rolSuper = $rolRepo->buscarPorCodigo('superadmin_plataforma');
    $rolAdmin = $rolRepo->buscarPorCodigo('admin_organizacion');
    $rolOper  = $rolRepo->buscarPorCodigo('operador_produccion');

    // 1. Superadministrador de Plataforma
    $perSuper = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '88880001', nombres: 'Super', apellidos: 'Plataforma',
        correoElectronico: 'super_f12c@test.com', estado: 'ACTIVO'
    );
    $perSuperId = $personaRepo->crear($perSuper);

    $usrSuper = new Usuario(
        id: null, organizacionId: 10000, personaId: $perSuperId,
        nombreUsuario: 'super_f12c', nombreCompleto: 'Super Plataforma',
        correoElectronico: 'super_f12c@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        esSuperadminPlataforma: true, estado: 'ACTIVO'
    );
    $usrSuperId = $usuarioRepo->crear($usrSuper);
    $rolRepo->asignarRolAUsuario($usrSuperId, $rolSuper->id);

    // 2. Administrador de Organización (Rol admin_organizacion, permisos 7-11)
    $perAdmin = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '77770001', nombres: 'Admin', apellidos: 'Organizacion',
        correoElectronico: 'admin_f12c@test.com', estado: 'ACTIVO'
    );
    $perAdminId = $personaRepo->crear($perAdmin);

    $usrAdmin = new Usuario(
        id: null, organizacionId: 10000, personaId: $perAdminId,
        nombreUsuario: 'admin_f12c', nombreCompleto: 'Admin Org F12C',
        correoElectronico: 'admin_f12c@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        esSuperadminPlataforma: false, estado: 'ACTIVO'
    );
    $usrAdminId = $usuarioRepo->crear($usrAdmin);
    $rolRepo->asignarRolAUsuario($usrAdminId, $rolAdmin->id);

    // 3. Usuario Operador sin permisos de configuración
    $perRaso = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '66660001', nombres: 'Operador', apellidos: 'Produccion',
        correoElectronico: 'raso_f12c@test.com', estado: 'ACTIVO'
    );
    $perRasoId = $personaRepo->crear($perRaso);

    $usrRaso = new Usuario(
        id: null, organizacionId: 10000, personaId: $perRasoId,
        nombreUsuario: 'raso_f12c', nombreCompleto: 'Operador Produccion',
        correoElectronico: 'raso_f12c@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        esSuperadminPlataforma: false, estado: 'ACTIVO'
    );
    $usrRasoId = $usuarioRepo->crear($usrRaso);
    $rolRepo->asignarRolAUsuario($usrRasoId, $rolOper->id);

    // Tokens CSRF válidos
    $csrfSuper = bin2hex(random_bytes(32));
    $csrfAdmin = bin2hex(random_bytes(32));

    // Contextos de prueba
    $ctxSuper = ContextoOperacion::paraHumano($usrSuperId, 1, 'WEB', '127.0.0.1', 'CLI-Tester', 10000, metadatos: ['csrf_token' => $csrfSuper]);
    $ctxAdmin = ContextoOperacion::paraHumano($usrAdminId, 1, 'WEB', '127.0.0.1', 'CLI-Tester', 10000, metadatos: ['csrf_token' => $csrfAdmin]);
    $ctxRaso  = ContextoOperacion::paraHumano($usrRasoId, 1, 'WEB', '127.0.0.1', 'CLI-Tester', 10000, metadatos: ['csrf_token' => bin2hex(random_bytes(32))]);
    $ctxSinOrg = ContextoOperacion::paraHumano($usrAdminId, 1, 'WEB', '127.0.0.1', 'CLI-Tester', null, metadatos: ['csrf_token' => $csrfAdmin]);

    $authzMiddleware = new AutorizacionMiddleware(autorizacionServicio: $authzServicio);

    // ==============================================================================
    // BLOQUE 1: LISTADO Y VISIBILIDAD GOBERNADA (GET /api/v1/configuracion)
    // ==============================================================================

    // 01. Petición sin autenticación retorna 401
    $ctrlSinAuth = new ConfiguracionControlador(
        authMiddleware: crearMockAuthMiddleware(null),
        authzMiddleware: $authzMiddleware,
        configServicio: $configServicio,
        authzServicio: $authzServicio,
        pdo: $pdo
    );
    $res01 = json_decode($ctrlSinAuth->listar(), true);
    afirmar(
        $res01['exito'] === false && $res01['codigo'] === 401,
        '01. GET /api/v1/configuracion sin sesión activa retorna HTTP 401'
    );

    // 02. Operador sin permisos retorna 403
    $ctrlRaso = new ConfiguracionControlador(
        authMiddleware: crearMockAuthMiddleware($ctxRaso),
        authzMiddleware: $authzMiddleware,
        configServicio: $configServicio,
        authzServicio: $authzServicio,
        pdo: $pdo
    );
    $res02 = json_decode($ctrlRaso->listar(), true);
    afirmar(
        $res02['exito'] === false && $res02['codigo'] === 403,
        '02. GET /api/v1/configuracion por operador sin permisos retorna HTTP 403'
    );

    // 03. Admin de Organización consulta exitosamente su ámbito ORGANIZACION
    $ctrlAdmin = new ConfiguracionControlador(
        authMiddleware: crearMockAuthMiddleware($ctxAdmin),
        authzMiddleware: $authzMiddleware,
        configServicio: $configServicio,
        authzServicio: $authzServicio,
        pdo: $pdo
    );
    $_GET = [];
    $res03 = json_decode($ctrlAdmin->listar(), true);
    afirmar(
        $res03['exito'] === true
        && $res03['codigo'] === 200
        && count($res03['datos']['organizacion']) >= 3
        && empty($res03['datos']['plataforma'])
        && $res03['datos']['permisos']['puede_ver_organizacion'] === true
        && $res03['datos']['permisos']['puede_ver_plataforma'] === false,
        '03. Admin de Organización ve parámetros de su tenant y tiene plataforma bloqueada'
    );

    // 04. Admin de Organización intentando filtrar ?ambito=PLATAFORMA recibe 403
    $_GET = ['ambito' => 'PLATAFORMA'];
    $res04 = json_decode($ctrlAdmin->listar(), true);
    afirmar(
        $res04['exito'] === false && $res04['codigo'] === 403,
        '04. Admin de Organización intentando filtrar ?ambito=PLATAFORMA recibe HTTP 403'
    );

    // 05. Superadministrador consulta exitosamente ambos ámbitos
    $ctrlSuper = new ConfiguracionControlador(
        authMiddleware: crearMockAuthMiddleware($ctxSuper),
        authzMiddleware: $authzMiddleware,
        configServicio: $configServicio,
        authzServicio: $authzServicio,
        pdo: $pdo
    );
    $_GET = [];
    $res05 = json_decode($ctrlSuper->listar(), true);
    afirmar(
        $res05['exito'] === true
        && count($res05['datos']['plataforma']) >= 6
        && count($res05['datos']['organizacion']) >= 3
        && $res05['datos']['permisos']['puede_editar_plataforma'] === true,
        '05. Superadministrador de Plataforma tiene visibilidad y soberanía sobre ambos ámbitos'
    );

    // ==============================================================================
    // BLOQUE 2: SEGURIDAD, CSRF Y FAIL-CLOSED ANTI-IDOR
    // ==============================================================================

    // 06. Mutación PUT sin token CSRF es rechazada con 403
    simularPeticionJson([
        'ambito' => 'ORGANIZACION',
        'codigo' => 'organizacion.notificar_whatsapp',
        'valor'  => '1'
    ]);
    $_SERVER['REQUEST_METHOD'] = 'PUT';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    $res06 = json_decode($ctrlAdmin->actualizar(), true);
    afirmar(
        $res06['exito'] === false && $res06['codigo'] === 403,
        '06. PUT /api/v1/configuracion sin CSRF token retorna HTTP 403'
    );

    // 07. Mutación con token CSRF inválido es rechazada con 403
    simularPeticionJson([
        '_csrf_token' => 'token_falso_123',
        'ambito'      => 'ORGANIZACION',
        'codigo'      => 'organizacion.notificar_whatsapp',
        'valor'       => '1'
    ]);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token_falso_123';
    $res07 = json_decode($ctrlAdmin->actualizar(), true);
    afirmar(
        $res07['exito'] === false && $res07['codigo'] === 403,
        '07. PUT /api/v1/configuracion con CSRF token inválido retorna HTTP 403'
    );

    // 08. Fail-closed anti-IDOR: Sesión sin identificador de organización rechaza con 403
    $ctrlSinOrg = new ConfiguracionControlador(
        authMiddleware: crearMockAuthMiddleware($ctxSinOrg),
        authzMiddleware: $authzMiddleware,
        configServicio: $configServicio,
        authzServicio: $authzServicio,
        pdo: $pdo
    );
    simularPeticionJson([
        '_csrf_token' => $csrfAdmin,
        'ambito'      => 'ORGANIZACION',
        'codigo'      => 'organizacion.notificar_whatsapp',
        'valor'       => '1'
    ]);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfAdmin;
    $res08 = json_decode($ctrlSinOrg->actualizar(), true);
    afirmar(
        $res08['exito'] === false && $res08['codigo'] === 403,
        '08. Fail-closed: Operación de organización sin tenant_id en contexto retorna HTTP 403'
    );

    // ==============================================================================
    // BLOQUE 3: GOBERNANZA DE PARÁMETROS DE ORGANIZACIÓN
    // ==============================================================================

    // 09. Admin actualiza parámetro booleano de organización (notificar_whatsapp)
    $paramWspAntes = $configRepo->buscarParametro('organizacion.notificar_whatsapp', 10000);
    simularPeticionJson([
        '_csrf_token'    => $csrfAdmin,
        'ambito'         => 'ORGANIZACION',
        'codigo'         => 'organizacion.notificar_whatsapp',
        'valor'          => '0',
        'actualizado_en' => $paramWspAntes->actualizadoEn
    ]);
    $res09 = json_decode($ctrlAdmin->actualizar(), true);
    $paramWspDespues = $configRepo->buscarParametro('organizacion.notificar_whatsapp', 10000);
    afirmar(
        $res09['exito'] === true
        && $paramWspDespues->valor === '0',
        '09. Admin actualiza correctamente booleano organizacion.notificar_whatsapp'
    );

    // 10. Admin actualiza parámetro entero de organización (dias_validez_cotizacion) en rango
    $paramDiasAntes = $configRepo->buscarParametro('organizacion.dias_validez_cotizacion', 10000);
    simularPeticionJson([
        '_csrf_token'    => $csrfAdmin,
        'ambito'         => 'ORGANIZACION',
        'codigo'         => 'organizacion.dias_validez_cotizacion',
        'valor'          => 14,
        'actualizado_en' => $paramDiasAntes->actualizadoEn
    ]);
    $res10 = json_decode($ctrlAdmin->actualizar(), true);
    $paramDiasDespues = $configRepo->buscarParametro('organizacion.dias_validez_cotizacion', 10000);
    afirmar(
        $res10['exito'] === true
        && $paramDiasDespues->valor === '14'
        && $paramDiasDespues->obtenerValorCasteado() === 14,
        '10. Admin actualiza entero organizacion.dias_validez_cotizacion a 14 días'
    );

    // 11. Rechazo con 422 si dias_validez_cotizacion excede rango (> 60)
    simularPeticionJson([
        '_csrf_token'    => $csrfAdmin,
        'ambito'         => 'ORGANIZACION',
        'codigo'         => 'organizacion.dias_validez_cotizacion',
        'valor'          => 999,
        'actualizado_en' => $paramDiasDespues->actualizadoEn
    ]);
    $res11 = json_decode($ctrlAdmin->actualizar(), true);
    afirmar(
        $res11['exito'] === false && $res11['codigo'] === 422,
        '11. Valor 999 para dias_validez_cotizacion es rechazado con HTTP 422 (max: 60)'
    );

    // 12. Admin actualiza porcentaje de reserva mínimo dentro del rango admitido (10.0 a 100.0)
    $paramResAntes = $configRepo->buscarParametro('organizacion.porcentaje_reserva_minimo', 10000);
    simularPeticionJson([
        '_csrf_token'    => $csrfAdmin,
        'ambito'         => 'ORGANIZACION',
        'codigo'         => 'organizacion.porcentaje_reserva_minimo',
        'valor'          => '50.00',
        'actualizado_en' => $paramResAntes->actualizadoEn
    ]);
    $res12 = json_decode($ctrlAdmin->actualizar(), true);
    $paramResDespues = $configRepo->buscarParametro('organizacion.porcentaje_reserva_minimo', 10000);
    afirmar(
        $res12['exito'] === true
        && $paramResDespues->valor === '50.00'
        && $paramResDespues->obtenerValorCasteado() === 50.0,
        '12. Admin actualiza decimal organizacion.porcentaje_reserva_minimo a 50.00%'
    );

    // 13. Rechazo con 422 si porcentaje de reserva es menor al mínimo (< 10.0)
    simularPeticionJson([
        '_csrf_token'    => $csrfAdmin,
        'ambito'         => 'ORGANIZACION',
        'codigo'         => 'organizacion.porcentaje_reserva_minimo',
        'valor'          => '5.00',
        'actualizado_en' => $paramResDespues->actualizadoEn
    ]);
    $res13 = json_decode($ctrlAdmin->actualizar(), true);
    afirmar(
        $res13['exito'] === false && $res13['codigo'] === 422,
        '13. Valor 5.00% para porcentaje_reserva_minimo es rechazado con HTTP 422 (min: 10.0)'
    );

    // ==============================================================================
    // BLOQUE 4: GOBERNANZA DE PARÁMETROS SOBERANOS DE PLATAFORMA
    // ==============================================================================

    // 14. Admin de Organización intentando modificar PLATAFORMA es rechazado con 403
    simularPeticionJson([
        '_csrf_token' => $csrfAdmin,
        'ambito'      => 'PLATAFORMA',
        'codigo'      => 'plataforma.monto_minimo_pago_pe',
        'valor'       => '100.00'
    ]);
    $res14 = json_decode($ctrlAdmin->actualizar(), true);
    afirmar(
        $res14['exito'] === false && $res14['codigo'] === 403,
        '14. Admin de Organización intentando modificar PLATAFORMA recibe HTTP 403'
    );

    // 15. Inmutabilidad en tiempo de ejecución: plataforma.moneda_principal (es_editable = 0)
    $paramMoneda = $configRepo->buscarParametro('plataforma.moneda_principal', null);
    simularPeticionJson([
        '_csrf_token'    => $csrfSuper,
        'ambito'         => 'PLATAFORMA',
        'codigo'         => 'plataforma.moneda_principal',
        'valor'          => 'USD',
        'actualizado_en' => $paramMoneda->actualizadoEn
    ]);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfSuper;
    $res15 = json_decode($ctrlSuper->actualizar(), true);
    afirmar(
        $res15['exito'] === false && $res15['codigo'] === 422,
        '15. Mutación de parámetro protegido (plataforma.moneda_principal) rechazada con HTTP 422'
    );

    // 16. Validación IANA de Zona Horaria: Zona válida aceptada
    $paramTzAntes = $configRepo->buscarParametro('plataforma.zona_horaria', null);
    simularPeticionJson([
        '_csrf_token'    => $csrfSuper,
        'ambito'         => 'PLATAFORMA',
        'codigo'         => 'plataforma.zona_horaria',
        'valor'          => 'UTC',
        'actualizado_en' => $paramTzAntes->actualizadoEn
    ]);
    $res16 = json_decode($ctrlSuper->actualizar(), true);
    $paramTzDespues = $configRepo->buscarParametro('plataforma.zona_horaria', null);
    afirmar(
        $res16['exito'] === true && $paramTzDespues->valor === 'UTC',
        '16. Zona horaria IANA válida (UTC) es aceptada y persistida'
    );

    // 17. Validación IANA de Zona Horaria: Zona inválida rechazada con 422
    simularPeticionJson([
        '_csrf_token'    => $csrfSuper,
        'ambito'         => 'PLATAFORMA',
        'codigo'         => 'plataforma.zona_horaria',
        'valor'          => 'Falsa/Zona_Inexistente',
        'actualizado_en' => $paramTzDespues->actualizadoEn
    ]);
    $res17 = json_decode($ctrlSuper->actualizar(), true);
    afirmar(
        $res17['exito'] === false && $res17['codigo'] === 422,
        '17. Zona horaria no perteneciente a IANA es rechazada con HTTP 422'
    );

    // Restaurar zona horaria a America/Lima
    $configServicio->actualizarPlataforma('plataforma.zona_horaria', 'America/Lima', $usrSuperId, $ctxSuper);

    // 18. Superadmin actualiza monto mínimo de pago en soles
    $paramMontoAntes = $configRepo->buscarParametro('plataforma.monto_minimo_pago_pe', null);
    simularPeticionJson([
        '_csrf_token'    => $csrfSuper,
        'ambito'         => 'PLATAFORMA',
        'codigo'         => 'plataforma.monto_minimo_pago_pe',
        'valor'          => '25.50',
        'actualizado_en' => $paramMontoAntes->actualizadoEn
    ]);
    $res18 = json_decode($ctrlSuper->actualizar(), true);
    $paramMontoDespues = $configRepo->buscarParametro('plataforma.monto_minimo_pago_pe', null);
    afirmar(
        $res18['exito'] === true
        && $paramMontoDespues->valor === '25.50'
        && $paramMontoDespues->obtenerValorCasteado() === 25.5,
        '18. Superadmin actualiza plataforma.monto_minimo_pago_pe a S/ 25.50'
    );

    // 19. Superadmin actualiza parámetros de seguridad de login (max intentos y minutos bloqueo)
    $paramMaxAntes = $configRepo->buscarParametro('plataforma.max_intentos_login', null);
    $paramMinAntes = $configRepo->buscarParametro('plataforma.minutos_bloqueo_login', null);

    simularPeticionJson([
        '_csrf_token' => $csrfSuper,
        'ambito'      => 'PLATAFORMA',
        'parametros'  => [
            [
                'codigo'         => 'plataforma.max_intentos_login',
                'valor'          => 3,
                'actualizado_en' => $paramMaxAntes->actualizadoEn
            ],
            [
                'codigo'         => 'plataforma.minutos_bloqueo_login',
                'valor'          => 30,
                'actualizado_en' => $paramMinAntes->actualizadoEn
            ]
        ]
    ]);
    $res19 = json_decode($ctrlSuper->actualizar(), true);
    afirmar(
        $res19['exito'] === true,
        '19. Superadmin actualiza en lote (batch) parámetros de seguridad de autenticación'
    );

    // ==============================================================================
    // BLOQUE 5: INTEGRACIÓN DINÁMICA CON AUTENTICACIÓN
    // ==============================================================================

    // 20. AutenticacionServicio consume dinámicamente max_intentos_login = 3
    $perTestAuth = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '55550001', nombres: 'Usuario', apellidos: 'TestBloqueo',
        correoElectronico: 'bloqueo_f12c@test.com', estado: 'ACTIVO'
    );
    $perTestAuthId = $personaRepo->crear($perTestAuth);
    $usrTestAuth = new Usuario(
        id: null, organizacionId: 10000, personaId: $perTestAuthId,
        nombreUsuario: 'test_bloqueo_f12c', nombreCompleto: 'Usuario Test Bloqueo',
        correoElectronico: 'bloqueo_f12c@test.com', contrasenaHash: password_hash('PassCorrecta1!', PASSWORD_DEFAULT),
        estado: 'ACTIVO'
    );
    $usrTestAuthId = $usuarioRepo->crear($usrTestAuth);

    // Fallo 1
    $authServicio->autenticar('test_bloqueo_f12c', 'PassErronea1', '127.0.0.1');
    $usrCheck1 = $usuarioRepo->buscarPorId($usrTestAuthId);
    $intentos1 = $usrCheck1->intentosFallidos;

    // Fallo 2
    $authServicio->autenticar('test_bloqueo_f12c', 'PassErronea2', '127.0.0.1');
    $usrCheck2 = $usuarioRepo->buscarPorId($usrTestAuthId);
    $intentos2 = $usrCheck2->intentosFallidos;

    // Fallo 3 -> Dispara bloqueo conforme al valor gobernado dinámico (3 intentos)
    $authServicio->autenticar('test_bloqueo_f12c', 'PassErronea3', '127.0.0.1');
    $usrCheck3 = $usuarioRepo->buscarPorId($usrTestAuthId);
    $bloqueado = $usrCheck3->bloqueadoHasta !== null;

    afirmar(
        $intentos1 === 1 && $intentos2 === 2 && $bloqueado,
        '20. AutenticacionServicio aplica el límite dinámico de 3 intentos configurado en plataforma'
    );

    // Restaurar parámetros de login
    $configServicio->actualizarPlataforma('plataforma.max_intentos_login', 5, $usrSuperId, $ctxSuper);
    $configServicio->actualizarPlataforma('plataforma.minutos_bloqueo_login', 15, $usrSuperId, $ctxSuper);

    // ==============================================================================
    // BLOQUE 6: CONCURRENCIA LIGERA OPTIMISTA (HTTP 409 CONFLICT)
    // ==============================================================================

    // 21. Conflicto de concurrencia: timestamp desfasado provoca HTTP 409
    simularPeticionJson([
        '_csrf_token'    => $csrfAdmin,
        'ambito'         => 'ORGANIZACION',
        'codigo'         => 'organizacion.dias_validez_cotizacion',
        'valor'          => 20,
        'actualizado_en' => '2020-01-01 00:00:00' // Timestamp desfasado
    ]);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfAdmin;
    $res21 = json_decode($ctrlAdmin->actualizar(), true);
    afirmar(
        $res21['exito'] === false && $res21['codigo'] === 409,
        '21. Intento de modificación con timestamp desactualizado retorna HTTP 409 Conflict'
    );

    // 22. Actualización exitosa al enviar el timestamp sincronizado
    $paramDiasFresco = $configRepo->buscarParametro('organizacion.dias_validez_cotizacion', 10000);
    simularPeticionJson([
        '_csrf_token'    => $csrfAdmin,
        'ambito'         => 'ORGANIZACION',
        'codigo'         => 'organizacion.dias_validez_cotizacion',
        'valor'          => 21,
        'actualizado_en' => $paramDiasFresco->actualizadoEn
    ]);
    $res22 = json_decode($ctrlAdmin->actualizar(), true);
    afirmar(
        $res22['exito'] === true && $res22['codigo'] === 200,
        '22. Modificación con timestamp vigente se procesa exitosamente (HTTP 200)'
    );

    // ==============================================================================
    // BLOQUE 7: INTEGRIDAD, AUDITORÍA Y NO PROLIFERACIÓN DE SECRETOS
    // ==============================================================================

    // 23. Intento de actualizar parámetro inexistente en catálogo retorna 422
    simularPeticionJson([
        '_csrf_token' => $csrfAdmin,
        'ambito'      => 'ORGANIZACION',
        'codigo'      => 'organizacion.clave_inexistente_arbitraria',
        'valor'       => 'prueba'
    ]);
    $res23 = json_decode($ctrlAdmin->actualizar(), true);
    afirmar(
        $res23['exito'] === false && $res23['codigo'] === 422,
        '23. Catálogo cerrado: Parámetros no gobernados son rechazados (HTTP 422)'
    );

    // 24. Pista de auditoría inmutable registra operaciones de Organización y Plataforma
    $stmtAudit = $pdo->prepare(
        "SELECT accion, entidad_tipo, entidad_id, datos_previos_json, datos_nuevos_json
         FROM auditoria_operaciones
         WHERE modulo = 'configuracion'
         ORDER BY id DESC
         LIMIT 10"
    );
    $stmtAudit->execute();
    $logsAudit = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);

    $tieneAuditOrg = false;
    $tieneAuditPlat = false;
    $sinSecretos = true;

    foreach ($logsAudit as $log) {
        if ($log['accion'] === 'ACTUALIZAR_CONFIGURACION_ORGANIZACION') {
            $tieneAuditOrg = true;
        }
        if ($log['accion'] === 'ACTUALIZAR_CONFIGURACION_PLATAFORMA') {
            $tieneAuditPlat = true;
        }
        $textoCompleto = json_encode($log);
        if (str_contains(strtolower($textoCompleto), 'password') || str_contains(strtolower($textoCompleto), 'secret')) {
            $sinSecretos = false;
        }
    }

    afirmar(
        $tieneAuditOrg && $tieneAuditPlat && $sinSecretos,
        '24. Auditoría inmutable registra ambas operaciones con datos antes/después y libre de secretos'
    );

    // 25. Recuperación limpia de caché interna tras invalidación
    $valorOrgDirecto = $configServicio->obtenerOrganizacion(10000, 'organizacion.dias_validez_cotizacion');
    afirmar(
        $valorOrgDirecto === 21,
        '25. Caché de proceso de ConfiguracionServicio sincronizada y consistente'
    );

} catch (Throwable $e) {
    afirmar(false, 'Excepción inesperada en ejecución de suite F1.2C: ' . $e->getMessage(), $e->getTraceAsString());
} finally {
    $pdo->rollBack();
    // Limpiar variables superglobales
    $_GET = [];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
}

echo "\n==============================================================================\n";
echo "RESULTADOS F1.2C: Exitosos: {$exitos} | Fallidos: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
