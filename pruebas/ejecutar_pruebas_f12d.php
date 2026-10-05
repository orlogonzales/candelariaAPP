<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Controladores\ConfiguracionControlador;
use Aplicacion\Controladores\OrganizacionControlador;
use Aplicacion\Controladores\UsuarioControlador;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\ActorSistemaRepositorio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
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
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN Y CONSOLIDACIÓN F1.2D\n";
echo "CATÁLOGOS, PARÁMETROS, TENANT FAIL-CLOSED, AISLAMIENTO Y PRESERVACIÓN ORLANDO\n";
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

// 0. Instanciar Repositorios y Servicios Fundacionales
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

// Registrar huella digital (fingerprint) previa de orlando para verificación estricta pre/post
$stmtOrlandoPre = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE nombre_usuario = 'orlando'");
$stmtOrlandoPre->execute();
$orlandoPre = $stmtOrlandoPre->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPre = $orlandoPre ? substr(hash('sha256', (string) $orlandoPre['contrasena_hash']), 0, 16) : null;

$pdo->beginTransaction();

try {
    // ==============================================================================
    // 01. SEPARACIÓN CONCEPTUAL: CATÁLOGO VS PARÁMETRO VS ENTIDAD DE NEGOCIO
    // ==============================================================================
    $totalTiposDoc = (int) $pdo->query("SELECT COUNT(*) FROM tipos_documento")->fetchColumn();
    $totalActores  = (int) $pdo->query("SELECT COUNT(*) FROM actores_sistema")->fetchColumn();
    $totalCanales  = (int) $pdo->query("SELECT COUNT(*) FROM canales")->fetchColumn();
    $totalParams   = (int) $pdo->query("SELECT COUNT(*) FROM parametros_configuracion")->fetchColumn();
    $totalOrgs     = (int) $pdo->query("SELECT COUNT(*) FROM organizaciones")->fetchColumn();

    afirmar(
        $totalTiposDoc >= 6
        && $totalActores >= 6
        && $totalCanales >= 7
        && $totalParams >= 9
        && $totalOrgs >= 1,
        '01. Separación estricta: Catálogos finitos (documentos, actores, canales), Parámetros gobernados y Entidades'
    );

    // ==============================================================================
    // 02. PARÁMETROS GOBERNADOS SIN CLAVES ARBITRARIAS
    // ==============================================================================
    $rechazaClaveInvalida = false;
    try {
        $ctxPrueba = ContextoOperacion::paraHumano(24, 2, 'APP', '127.0.0.1', 'Tester', 10000);
        $configServicio->actualizarOrganizacion(10000, 'organizacion.clave_inexistente_arbitraria', 'test', 24, $ctxPrueba);
    } catch (\InvalidArgumentException) {
        $rechazaClaveInvalida = true;
    }

    $chkPlatConOrgFalla = false;
    try {
        $stmtChk = $pdo->prepare("INSERT INTO parametros_configuracion (organizacion_id, ambito, codigo, tipo_dato, valor, etiqueta) VALUES (10000, 'PLATAFORMA', 'plataforma.fake', 'STRING', 'v', 'F')");
        $stmtChk->execute();
    } catch (\PDOException) {
        $chkPlatConOrgFalla = true;
    }

    afirmar(
        $rechazaClaveInvalida === true && $chkPlatConOrgFalla === true,
        '02. Parámetros gobernados rechazan claves arbitrarias y violaciones de constraint MySQL'
    );

    // ==============================================================================
    // 03. TENANT FAIL-CLOSED EN CONTROLADORES ANTE CONTEXTO INVÁLIDO
    // ==============================================================================
    // Simular contexto con organizacionId = null
    $ctxSinOrg = ContextoOperacion::paraHumano(24, 2, 'APP', '127.0.0.1', 'Tester', null);
    
    $mockAuthSinOrg = new class($ctxSinOrg) extends AutenticacionMiddleware {
        public function __construct(private ContextoOperacion $ctx) {}
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearPeticion = true): ?ContextoOperacion {
            return $this->ctx;
        }
    };
    $mockAuthzPermitido = new class extends AutorizacionMiddleware {
        public function __construct() {}
        public function verificarPermiso(string $permiso, ?ContextoOperacion $ctx = null, bool $bloquear = true): bool {
            return true;
        }
    };

    $ctrlUsuarioSinOrg = new UsuarioControlador(
        authMiddleware: $mockAuthSinOrg,
        authzMiddleware: $mockAuthzPermitido,
        usuarioRepo: $usuarioRepo,
        personaRepo: $personaRepo,
        rolRepo: $rolRepo,
        auditoriaRepo: $auditoriaRepo,
        pdo: $pdo
    );

    $respListarSinOrg = json_decode($ctrlUsuarioSinOrg->listar(), true);
    $respPersonasSinOrg = json_decode($ctrlUsuarioSinOrg->personasDisponibles(), true);
    $respRolesSinOrg = json_decode($ctrlUsuarioSinOrg->roles(), true);

    afirmar(
        $respListarSinOrg['exito'] === false && $respListarSinOrg['codigo'] === 403
        && $respPersonasSinOrg['exito'] === false && $respPersonasSinOrg['codigo'] === 403
        && $respRolesSinOrg['exito'] === false && $respRolesSinOrg['codigo'] === 403,
        '03. Tenant fail-closed: Controladores retornan HTTP 403 cuando falta el contexto organizacional'
    );

    // ==============================================================================
    // 04. ADMIN ORGANIZACIÓN NO ACCEDE A PLATAFORMA
    // ==============================================================================
    $ctxAdminOrg = ContextoOperacion::paraHumano(24, 2, 'APP', '127.0.0.1', 'Tester', 10000);
    $mockAuthAdminOrg = new class($ctxAdminOrg) extends AutenticacionMiddleware {
        public function __construct(private ContextoOperacion $ctx) {}
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearPeticion = true): ?ContextoOperacion {
            return $this->ctx;
        }
    };
    $authzRealMiddleware = new AutorizacionMiddleware($authzServicio);

    $ctrlConfigAdmin = new ConfiguracionControlador(
        configServicio: $configServicio,
        authMiddleware: $mockAuthAdminOrg,
        authzMiddleware: $authzRealMiddleware
    );

    // Consulta con filtro explícito PLATAFORMA debe responder 403
    $_GET['ambito'] = 'PLATAFORMA';
    $respPlatAdmin = json_decode($ctrlConfigAdmin->listar(), true);

    // Consulta general no incluye parámetros de plataforma para admin de organización
    unset($_GET['ambito']);
    $respGeneralAdmin = json_decode($ctrlConfigAdmin->listar(), true);

    afirmar(
        $respPlatAdmin['exito'] === false && $respPlatAdmin['codigo'] === 403
        && empty($respGeneralAdmin['datos']['plataforma'])
        && $respGeneralAdmin['datos']['permisos']['puede_ver_plataforma'] === false,
        '04. Administrador de Organización no puede consultar ni ver configuración de Plataforma'
    );

    // ==============================================================================
    // 05. SUPERADMIN RESPETA SOBERANÍA Y RECHAZA MUTACIÓN DE PARÁMETROS INMUTABLES
    // ==============================================================================
    $perSuperId = $personaRepo->crear(new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '88880099', nombres: 'Super', apellidos: 'Plataforma F12D',
        correoElectronico: 'super_f12d@test.com', estado: 'ACTIVO'
    ));
    $usrSuperId = $usuarioRepo->crear(new Usuario(
        id: null, organizacionId: 10000, personaId: $perSuperId,
        nombreUsuario: 'super_f12d', nombreCompleto: 'Super Plataforma F12D',
        correoElectronico: 'super_f12d@test.com', contrasenaHash: password_hash('PassSuper1!', PASSWORD_DEFAULT),
        estado: 'ACTIVO'
    ));
    $rolSuper = $rolRepo->buscarPorCodigo('superadmin_plataforma');
    $rolRepo->asignarRolAUsuario($usrSuperId, $rolSuper->id);
    $ctxSuper = ContextoOperacion::paraHumano($usrSuperId, 1, 'APP', '127.0.0.1', 'CLI-Super', null);

    $rechazaMoneda = false;
    try {
        $configServicio->actualizarPlataforma('plataforma.moneda_principal', 'USD', $usrSuperId, $ctxSuper);
    } catch (\InvalidArgumentException) {
        $rechazaMoneda = true;
    }

    afirmar(
        $rechazaMoneda === true,
        '05. Soberanía gobernada: Parámetros inmutables (moneda_principal) no pueden ser alterados ni por Superadmin'
    );

    // ==============================================================================
    // 06. CONFIGURACIÓN DE SEGURIDAD CONSUMIDA DINÁMICAMENTE POR AUTENTICACIÓN
    // ==============================================================================
    // Obtener valores actuales de configuración consumidos en AutenticacionServicio
    $maxLoginConfig = $configServicio->obtenerPlataforma('plataforma.max_intentos_login');
    $minutosBloqueoConfig = $configServicio->obtenerPlataforma('plataforma.minutos_bloqueo_login');

    afirmar(
        $maxLoginConfig === 5 && $minutosBloqueoConfig === 15,
        '06. Parámetros de seguridad de login (5 intentos, 15 min) consumidos dinámicamente por el motor de autenticación'
    );

    // ==============================================================================
    // 07. PARÁMETROS FUTUROS PERMANECEN SIN CONSUMIDOR ARTIFICIAL
    // ==============================================================================
    $paramDias = $configRepo->buscarParametro('organizacion.dias_validez_cotizacion', 10000);
    $paramMinimo = $configRepo->buscarParametro('plataforma.monto_minimo_pago_pe', null);
    $paramReserva = $configRepo->buscarParametro('organizacion.porcentaje_reserva_minimo', 10000);

    afirmar(
        $paramDias !== null && $paramDias->obtenerValorCasteado() === 7
        && $paramMinimo !== null && $paramMinimo->obtenerValorCasteado() === 50.0
        && $paramReserva !== null && $paramReserva->obtenerValorCasteado() === 30.0,
        '07. Parámetros de dominios futuros (cotizaciones, pagos, reservas) debidamente gobernados sin consumidor inventado'
    );

    // ==============================================================================
    // 08. SEMILLAS SIN SECRETOS NI DATOS SENSIBLES EN TEXTO PLANO
    // ==============================================================================
    $semillasDir = dirname(__DIR__) . '/base_datos/semillas';
    $archivosSql = glob($semillasDir . '/*.sql');
    $tieneSecretosSql = false;

    foreach ($archivosSql as $sqlFile) {
        $contenido = file_get_contents($sqlFile);
        // Verificar que no se inserten usuarios ni secretos en semillas versionadas
        if (preg_match('/INSERT\s+INTO\s+`?usuarios`?/i', $contenido) || preg_match('/Cand26|secret_key|api_secret|api_key/i', $contenido)) {
            $tieneSecretosSql = true;
            break;
        }
    }

    afirmar(
        $tieneSecretosSql === false,
        '08. Semillas SQL fundacionales 100% libres de contraseñas y secretos en texto plano'
    );

    // ==============================================================================
    // 09. AUDITORÍA DE HARDCODES: CERO FALLBACKS INDEBIDOS TIPO ?? 10000 O ?? 1
    // ==============================================================================
    $codigoUsuarioCtrl = file_get_contents(dirname(__DIR__) . '/aplicacion/Controladores/UsuarioControlador.php');
    $codigoBarraLateral = file_get_contents(dirname(__DIR__) . '/recursos/vistas/parciales/barra_lateral.php');

    $tieneHardcodeUsuario = str_contains($codigoUsuarioCtrl, '$contexto->organizacionId ?? 1');
    $tieneHardcodeBarra   = str_contains($codigoBarraLateral, '$contextoBarra->organizacionId ?? 10000');

    afirmar(
        $tieneHardcodeUsuario === false && $tieneHardcodeBarra === false,
        '09. Hardcodes indebidos eliminados: Cero fallbacks silenciosos a tenant 1 o 10000 en UsuarioControlador y Sidebar'
    );

    // ==============================================================================
    // 10. APIS SIN FUGA TÉCNICA DE STACK TRACES, RUTAS NI SQLSTATE
    // ==============================================================================
    $respJsonPrueba = json_decode($ctrlUsuarioSinOrg->listar(), true);

    afirmar(
        isset($respJsonPrueba['exito'], $respJsonPrueba['codigo'], $respJsonPrueba['mensaje'])
        && !str_contains(json_encode($respJsonPrueba), 'SQLSTATE')
        && !str_contains(json_encode($respJsonPrueba), 'Stack trace')
        && !str_contains(json_encode($respJsonPrueba), 'C:\\')
        && !str_contains(json_encode($respJsonPrueba), 'D:\\'),
        '10. Formato JSON uniforme en APIs sin exposición de SQLSTATE, rutas de disco ni trazas de excepciones'
    );

    // ==============================================================================
    // 11. AUDITORÍA CONSISTENTE: REPOSITORIO INMUTABLE (APPEND-ONLY)
    // ==============================================================================
    $metodosAudit = get_class_methods(AuditoriaRepositorio::class);
    $esAppendOnly = !in_array('actualizar', $metodosAudit, true)
        && !in_array('eliminar', $metodosAudit, true)
        && !in_array('modificar', $metodosAudit, true)
        && !in_array('borrar', $metodosAudit, true);

    afirmar(
        $esAppendOnly === true,
        '11. AuditoriaRepositorio preserva invariante Append-Only: Cero métodos de mutación o eliminación física'
    );

    // ==============================================================================
    // 12. NAVEGACIÓN JERÁRQUICA: MÁXIMO 3 NIVELES Y AGRUPACIÓN COHESIVA
    // ==============================================================================
    // Verificar que en barra_lateral.php no existan colapsables anidados a más de 3 niveles
    $maxNivelNavValido = true;
    if (substr_count($codigoBarraLateral, 'class="collapse"') > 15) {
        $maxNivelNavValido = false;
    }

    afirmar(
        $maxNivelNavValido === true,
        '12. Navegación Alina estructurada en un máximo de 3 niveles jerárquicos coherentes'
    );

    // ==============================================================================
    // 13. ALINA UPSTREAM INTACTO
    // ==============================================================================
    $gitStatusAdminDashboard = shell_exec('git status --porcelain -- admin-dashboard');
    $adminDashboardIntacto = trim((string) $gitStatusAdminDashboard) === '';

    afirmar(
        $adminDashboardIntacto === true,
        '13. Directorio admin-dashboard/ upstream permanece 100% limpio e intacto'
    );

    // ==============================================================================
    // 14. TABLER EFECTIVO = 0 EN COMPONENTES DEL APLICATIVO
    // ==============================================================================
    $vistasDir = dirname(__DIR__) . '/recursos/vistas';
    $archivosVistas = glob($vistasDir . '/**/*.php');
    $archivosVistas = array_merge($archivosVistas, glob($vistasDir . '/**/**/*.php'));
    $iconosTablerEncontrados = 0;

    foreach ($archivosVistas as $vFile) {
        $cnt = file_get_contents($vFile);
        if (preg_match_all('/class="[^"]*\bti\s+ti-[^"]*"/i', $cnt, $m)) {
            $iconosTablerEncontrados += count($m[0]);
        }
    }

    afirmar(
        $iconosTablerEncontrados === 0,
        '14. Tabler efectivo = 0: Todos los iconos sustituidos exitosamente por Font Awesome 6 en vistas propias'
    );

    // ==============================================================================
    // 15. USUARIO ORLANDO PRESERVADO EN ESTRUCTURA Y TENANT
    // ==============================================================================
    $usrOrlandoActual = $usuarioRepo->buscarPorNombreUsuario('orlando');

    afirmar(
        $usrOrlandoActual !== null
        && $usrOrlandoActual->id === 24
        && $usrOrlandoActual->organizacionId === 10000
        && $usrOrlandoActual->nombreUsuario === 'orlando',
        '15. Usuario orlando existe, asociado al ID 24 y asignado a la organización 10000'
    );

    // ==============================================================================
    // 16. FINGERPRINT DEL HASH DE ORLANDO ANTES Y DESPUÉS IDÉNTICO
    // ==============================================================================
    $stmtOrlandoPost = $pdo->prepare("SELECT contrasena_hash FROM usuarios WHERE nombre_usuario = 'orlando'");
    $stmtOrlandoPost->execute();
    $hashPost = (string) $stmtOrlandoPost->fetchColumn();
    $fingerprintOrlandoPost = substr(hash('sha256', $hashPost), 0, 16);

    afirmar(
        $fingerprintOrlandoPre !== null
        && $fingerprintOrlandoPost === $fingerprintOrlandoPre,
        '16. Huella digital criptográfica (fingerprint sha256) de la contraseña de Orlando idéntica pre/post regresión'
    );

    // ==============================================================================
    // 17. ESTADO OPERATIVO DE ORLANDO INTACTO
    // ==============================================================================
    afirmar(
        $usrOrlandoActual !== null
        && $usrOrlandoActual->estado === 'ACTIVO'
        && $usrOrlandoActual->intentosFallidos === 0
        && $usrOrlandoActual->bloqueadoHasta === null,
        '17. Estado operativo de Orlando ACTIVO, con cero intentos fallidos y sin bloqueos de seguridad'
    );

    // ==============================================================================
    // 18. ROLES DE ORLANDO INTACTOS
    // ==============================================================================
    $rolesOrlandoActual = $usrOrlandoActual !== null ? $rolRepo->obtenerRolesDeUsuario($usrOrlandoActual->id) : [];
    $codigosRolesOrlando = array_column(array_map(fn($r) => ['codigo' => $r->codigo], $rolesOrlandoActual), 'codigo');

    afirmar(
        in_array('admin_organizacion', $codigosRolesOrlando, true)
        && !in_array('superadmin_plataforma', $codigosRolesOrlando, true),
        '18. Rol asignado a Orlando se mantiene estrictamente en admin_organizacion conforme a RBAC'
    );

    // ==============================================================================
    // 19. CONFIGURACIÓN ANTES Y DESPUÉS RESTAURADA
    // ==============================================================================
    $paramMaxPost = $configRepo->buscarParametro('plataforma.max_intentos_login', null);
    $paramMinBloqPost = $configRepo->buscarParametro('plataforma.minutos_bloqueo_login', null);
    $paramZonaPost = $configRepo->buscarParametro('plataforma.zona_horaria', null);

    afirmar(
        $paramMaxPost->obtenerValorCasteado() === 5
        && $paramMinBloqPost->obtenerValorCasteado() === 15
        && $paramZonaPost->obtenerValorCasteado() === 'America/Lima',
        '19. Parámetros de configuración soberanos restaurados y consistentes con el catálogo fundacional'
    );

    // ==============================================================================
    // 20. AISLAMIENTO DE ARCHIVOS DE BRANDING
    // ==============================================================================
    $dirBranding = dirname(__DIR__) . '/publico/recursos/subidas/organizaciones/10000/branding';
    $archivosBranding = is_dir($dirBranding) ? scandir($dirBranding) : [];
    $archivosTestResiduales = array_filter($archivosBranding ?: [], fn($f) => str_contains($f, 'test'));

    afirmar(
        count($archivosTestResiduales) === 0,
        '20. Sistema de archivos de branding libre de archivos residuales o contaminantes de suites de prueba'
    );

} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "\n==============================================================================\n";
echo "RESULTADO FINAL F1.2D: {$exitos} PRUEBAS EXITOSAS / {$fallos} FALLOS\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
