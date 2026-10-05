<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Controladores\UsuarioControlador;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Repositorios\ActorSistemaRepositorio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
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

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE HARDENING Y AUDITORÍA F1.1E\n";
echo "SEGURIDAD_AUTH, ESCALAMIENTO ROLES, AISLAMIENTO MULTI-TENANT IDOR, API SANITIZATION\n";
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

// 0. Instanciar Repositorios y Servicios
$actorSistemaRepo = new ActorSistemaRepositorio($pdo);
$usuarioRepo      = new UsuarioRepositorio($pdo);
$personaRepo      = new PersonaRepositorio($pdo);
$rolRepo          = new RolRepositorio($pdo);
$permisoRepo      = new PermisoRepositorio($pdo);
$sesionRepo       = new SesionRepositorio($pdo);
$auditoriaRepo    = new AuditoriaRepositorio($pdo);

$authServicio   = new AutenticacionServicio($usuarioRepo, $sesionRepo, $auditoriaRepo, $actorSistemaRepo, $pdo);
$authzServicio  = new AutorizacionServicio($rolRepo, $permisoRepo, $usuarioRepo, $auditoriaRepo, $pdo);
$authMiddleware = new AutenticacionMiddleware($authServicio);
$authzMiddleware = new AutorizacionMiddleware($authzServicio);

$usuarioCtrl = new UsuarioControlador(
    $authMiddleware,
    $authzMiddleware,
    $usuarioRepo,
    $personaRepo,
    $rolRepo,
    $sesionRepo,
    $auditoriaRepo,
    $pdo
);

$pdo->beginTransaction();

try {
    // ==============================================================================
    // SEED & CONFIGURACIÓN TEMPORAL DE TEST
    // ==============================================================================
    // 1. Organismos de prueba para aislamiento IDOR
    $stmtOrg = $pdo->prepare("INSERT INTO `organizaciones` (`codigo`, `nombre_comercial`, `razon_social`, `numero_documento`, `estado`) VALUES (:cod, :nom, :raz, :doc, 'ACTIVO')");
    $stmtOrg->execute([':cod' => 'tenant_alpha', ':nom' => 'TENANT ALPHA', ':raz' => 'TENANT ALPHA S.A.C.', ':doc' => '20111111111']);
    $orgAlphaId = (int) $pdo->lastInsertId();

    $stmtOrg->execute([':cod' => 'tenant_beta', ':nom' => 'TENANT BETA', ':raz' => 'TENANT BETA S.A.C.', ':doc' => '20222222222']);
    $orgBetaId = (int) $pdo->lastInsertId();

    // 2. Personas de prueba
    $perAlpha = new Persona(
        id: null, organizacionId: $orgAlphaId, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '71111111', nombres: 'Operador', apellidos: 'Alpha', estado: 'ACTIVO'
    );
    $perAlphaId = $personaRepo->crear($perAlpha);

    $perBeta = new Persona(
        id: null, organizacionId: $orgBetaId, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '72222222', nombres: 'Operador', apellidos: 'Beta', estado: 'ACTIVO'
    );
    $perBetaId = $personaRepo->crear($perBeta);

    $perSuper = new Persona(
        id: null, organizacionId: $orgAlphaId, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '73333333', nombres: 'Super', apellidos: 'Admin', estado: 'ACTIVO'
    );
    $perSuperId = $personaRepo->crear($perSuper);

    // 3. Usuarios de prueba
    $claveTest = 'TestSegura123!' . bin2hex(random_bytes(4));
    $hashTest = password_hash($claveTest, PASSWORD_DEFAULT);

    $usrAlpha = new Usuario(
        id: null, organizacionId: $orgAlphaId, personaId: $perAlphaId,
        nombreUsuario: 'admin_alpha', nombreCompleto: 'Admin Alpha',
        correoElectronico: 'alpha@test.com', contrasenaHash: $hashTest, estado: 'ACTIVO'
    );
    $usrAlphaId = $usuarioRepo->crear($usrAlpha);

    $usrBeta = new Usuario(
        id: null, organizacionId: $orgBetaId, personaId: $perBetaId,
        nombreUsuario: 'admin_beta', nombreCompleto: 'Admin Beta',
        correoElectronico: 'beta@test.com', contrasenaHash: $hashTest, estado: 'ACTIVO'
    );
    $usrBetaId = $usuarioRepo->crear($usrBeta);

    $usrSuper = new Usuario(
        id: null, organizacionId: $orgAlphaId, personaId: $perSuperId,
        nombreUsuario: 'super_root', nombreCompleto: 'Super Root',
        correoElectronico: 'super@test.com', contrasenaHash: $hashTest,
        esSuperadminPlataforma: true, estado: 'ACTIVO'
    );
    $usrSuperId = $usuarioRepo->crear($usrSuper);

    // Asignar roles: Alpha y Beta -> admin_organizacion (id 2)
    $rolAdminOrg = $rolRepo->buscarPorCodigo('admin_organizacion');
    $rolSuper = $rolRepo->buscarPorCodigo('superadmin_plataforma');
    $rolRepo->asignarRolAUsuario($usrAlphaId, $rolAdminOrg->id);
    $rolRepo->asignarRolAUsuario($usrBetaId, $rolAdminOrg->id);
    $rolRepo->asignarRolAUsuario($usrSuperId, $rolSuper->id);

    // Contextos de operación para simulación de llamadas
    $ctxAlpha = ContextoOperacion::paraHumano(
        usuarioId: $usrAlphaId,
        canalId: 1,
        canalCodigo: 'APP',
        origenIp: '127.0.0.1',
        agenteUsuario: 'CLI-Tester',
        organizacionId: $orgAlphaId
    );

    $ctxBeta = ContextoOperacion::paraHumano(
        usuarioId: $usrBetaId,
        canalId: 1,
        canalCodigo: 'APP',
        origenIp: '127.0.0.1',
        agenteUsuario: 'CLI-Tester',
        organizacionId: $orgBetaId
    );

    $ctxSuper = ContextoOperacion::paraHumano(
        usuarioId: $usrSuperId,
        canalId: 1,
        canalCodigo: 'APP',
        origenIp: '127.0.0.1',
        agenteUsuario: 'CLI-Tester',
        organizacionId: $orgAlphaId
    );

    // ==============================================================================
    // BLOQUE 1: SEGURIDAD_AUTH (H-01) — 8 PRUEBAS
    // ==============================================================================

    // 01. Login fallido por usuario inexistente
    $authInexistente = $authServicio->autenticar('no_existe_' . bin2hex(random_bytes(4)), 'clave_erronea');
    $stmtUltimaAuditoria = $pdo->query("SELECT * FROM auditoria_operaciones WHERE accion = 'LOGIN_FALLIDO' ORDER BY id DESC LIMIT 1");
    $auditInexistente = $stmtUltimaAuditoria->fetch(PDO::FETCH_ASSOC);

    afirmar(
        $authInexistente->exitoso === false
        && $auditInexistente['actor_tipo'] === 'SISTEMA'
        && (int) $auditInexistente['actor_sistema_id'] === $actorSistemaRepo->obtenerIdPorCodigo('SEGURIDAD_AUTH')
        && $auditInexistente['usuario_id'] === null
        && (int) $auditInexistente['canal_id'] === 1,
        '01. SEGURIDAD_AUTH: Login fallido por usuario inexistente audita con actor SISTEMA (SEGURIDAD_AUTH), canal APP, usuario_id = null'
    );

    // 02. Login fallido por contraseña incorrecta
    $authClaveIncorrecta = $authServicio->autenticar('admin_alpha', 'clave_totalmente_falsa');
    $auditClave = $pdo->query("SELECT * FROM auditoria_operaciones WHERE accion = 'LOGIN_FALLIDO' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    afirmar(
        $authClaveIncorrecta->exitoso === false
        && $auditClave['actor_tipo'] === 'SISTEMA'
        && (int) $auditClave['actor_sistema_id'] === $actorSistemaRepo->obtenerIdPorCodigo('SEGURIDAD_AUTH')
        && $auditClave['usuario_id'] === null,
        '02. SEGURIDAD_AUTH: Login fallido por contraseña incorrecta audita con actor SISTEMA (SEGURIDAD_AUTH), canal APP, usuario_id = null'
    );

    // 03. Login fallido por cuenta inactiva
    $usuarioRepo->actualizarEstado($usrBetaId, 'INACTIVO');
    $authInactiva = $authServicio->autenticar('admin_beta', $claveTest);
    $auditInactiva = $pdo->query("SELECT * FROM auditoria_operaciones WHERE accion = 'LOGIN_FALLIDO' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $usuarioRepo->actualizarEstado($usrBetaId, 'ACTIVO'); // Restaurar

    afirmar(
        $authInactiva->exitoso === false
        && $auditInactiva['actor_tipo'] === 'SISTEMA'
        && (int) $auditInactiva['actor_sistema_id'] === $actorSistemaRepo->obtenerIdPorCodigo('SEGURIDAD_AUTH')
        && $auditInactiva['usuario_id'] === null,
        '03. SEGURIDAD_AUTH: Login fallido por cuenta inactiva audita con actor SISTEMA (SEGURIDAD_AUTH), canal APP, usuario_id = null'
    );

    // 04. Login fallido por cuenta bloqueada
    $usuarioRepo->actualizarEstado($usrBetaId, 'BLOQUEADO');
    $pdo->prepare("UPDATE usuarios SET bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = :id")->execute([':id' => $usrBetaId]);
    $authBloqueada = $authServicio->autenticar('admin_beta', $claveTest);
    $auditBloqueada = $pdo->query("SELECT * FROM auditoria_operaciones WHERE accion = 'LOGIN_FALLIDO' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $usuarioRepo->actualizarEstado($usrBetaId, 'ACTIVO');
    $usuarioRepo->restablecerIntentosFallidos($usrBetaId);

    afirmar(
        $authBloqueada->exitoso === false
        && $auditBloqueada['actor_tipo'] === 'SISTEMA'
        && (int) $auditBloqueada['actor_sistema_id'] === $actorSistemaRepo->obtenerIdPorCodigo('SEGURIDAD_AUTH')
        && $auditBloqueada['usuario_id'] === null,
        '04. SEGURIDAD_AUTH: Login fallido por cuenta bloqueada audita con actor SISTEMA (SEGURIDAD_AUTH), canal APP, usuario_id = null'
    );

    // 05. Evento de bloqueo automático audita con actor SISTEMA
    for ($i = 0; $i < 5; $i++) {
        $authServicio->autenticar('admin_beta', 'clave_falsa_' . $i);
    }
    $auditBloqueoAuto = $pdo->query("SELECT * FROM auditoria_operaciones WHERE accion = 'BLOQUEO' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $usuarioRepo->actualizarEstado($usrBetaId, 'ACTIVO');
    $usuarioRepo->restablecerIntentosFallidos($usrBetaId);

    afirmar(
        $auditBloqueoAuto !== false
        && $auditBloqueoAuto['actor_tipo'] === 'SISTEMA'
        && (int) $auditBloqueoAuto['actor_sistema_id'] === $actorSistemaRepo->obtenerIdPorCodigo('SEGURIDAD_AUTH'),
        '05. SEGURIDAD_AUTH: Evento de bloqueo automático audita con actor SISTEMA (SEGURIDAD_AUTH)'
    );

    // 06. Resolución dinámica por código en ActorSistemaRepositorio
    $idSeguridadAuth = $actorSistemaRepo->obtenerIdPorCodigo('SEGURIDAD_AUTH');
    $actorObj = $actorSistemaRepo->buscarPorCodigo('SEGURIDAD_AUTH');

    afirmar(
        $idSeguridadAuth !== null
        && $actorObj !== null
        && $actorObj->codigo === 'SEGURIDAD_AUTH'
        && $actorObj->esCritico === true
        && $actorObj->activo === true,
        '06. SEGURIDAD_AUTH: Código de actor resuelto dinámicamente mediante ActorSistemaRepositorio (sin ID numérico hardcodeado)'
    );

    // 07. Erradicación: Cero eventos de preautenticación atribuidos a SISTEMA_CLI o LANDING
    $cliId = $actorSistemaRepo->obtenerIdPorCodigo('SISTEMA_CLI');
    $landingId = $actorSistemaRepo->obtenerIdPorCodigo('LANDING_CANDELARIA');
    $stmtAtribucionErronea = $pdo->prepare("SELECT COUNT(*) FROM auditoria_operaciones WHERE accion = 'LOGIN_FALLIDO' AND actor_sistema_id IN (:cli, :landing)");
    $stmtAtribucionErronea->execute([':cli' => $cliId, ':landing' => $landingId]);
    $conteoErroneo = (int) $stmtAtribucionErronea->fetchColumn();

    afirmar(
        $conteoErroneo === 0,
        '07. Erradicación: Cero eventos de login preautenticación atribuidos indebidamente a SISTEMA_CLI o LANDING_CANDELARIA'
    );

    // 08. Semilla: Registro SEGURIDAD_AUTH persistido y configurado en actores_sistema
    $actorDb = $pdo->query("SELECT * FROM actores_sistema WHERE codigo = 'SEGURIDAD_AUTH' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    afirmar(
        $actorDb !== false
        && $actorDb['nombre'] === 'Motor de Autenticación y Control de Acceso'
        && (int) $actorDb['es_critico'] === 1
        && (int) $actorDb['activo'] === 1,
        '08. Semilla: Registro canónico SEGURIDAD_AUTH existe en base de datos con flags es_critico=1 y activo=1'
    );

    // ==============================================================================
    // BLOQUE 2: ESCALAMIENTO DE PRIVILEGIOS H-02 (P1) — 4 PRUEBAS
    // ==============================================================================

    // 09. AutorizacionServicio::puedeAsignarRoles() rechaza que operador no superadmin asigne superadmin
    $puedeAlphaAsignarSuper = $authzServicio->puedeAsignarRoles($usrAlphaId, [$rolSuper->id]);
    $puedeAlphaAsignarAdmin = $authzServicio->puedeAsignarRoles($usrAlphaId, [$rolAdminOrg->id]);

    afirmar(
        $puedeAlphaAsignarSuper === false && $puedeAlphaAsignarAdmin === true,
        '09. H-02: AutorizacionServicio::puedeAsignarRoles() rechaza asignación de superadmin_plataforma por parte de no-superadmins'
    );

    // 10. AutorizacionServicio::puedeAsignarRoles() permite a superadmin asignar cualquier rol
    $puedeSuperAsignarCualquiera = $authzServicio->puedeAsignarRoles($usrSuperId, [$rolSuper->id, $rolAdminOrg->id]);

    afirmar(
        $puedeSuperAsignarCualquiera === true,
        '10. H-02: AutorizacionServicio::puedeAsignarRoles() permite a superadministrador asignar cualquier rol'
    );

    // 11. UsuarioControlador::crear() rechaza payload con superadmin_plataforma si operador no es superadmin
    ContextoOperacion::establecerActual($ctxAlpha);
    // Inyectar en middleware simulado
    $mockAuthMiddleware = new class($ctxAlpha) extends AutenticacionMiddleware {
        public function __construct(private ContextoOperacion $ctx) {}
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearPeticion = true): ?ContextoOperacion {
            return $this->ctx;
        }
    };

    $mockAuthzMiddleware = new class($authzServicio) extends AutorizacionMiddleware {
        public function __construct(private AutorizacionServicio $as) { parent::__construct($as); }
        public function verificarPermiso(string $permiso, ?ContextoOperacion $contexto = null, bool $bloquear = true): bool {
            return true;
        }
    };

    $ctrlEscalamiento = new UsuarioControlador(
        $mockAuthMiddleware,
        $mockAuthzMiddleware,
        $usuarioRepo,
        $personaRepo,
        $rolRepo,
        $sesionRepo,
        $auditoriaRepo,
        $pdo
    );

    // Simular POST de creación maliciosa con rol superadmin_plataforma
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $payloadMaliciosoCrear = [
        'modo_persona'       => 'existente',
        'persona_id'         => $perAlphaId,
        'nombre_usuario'     => 'hacker_super_' . bin2hex(random_bytes(3)),
        'correo_electronico' => 'hack_' . bin2hex(random_bytes(3)) . '@test.com',
        'contrasena'         => 'H@ck123456!',
        'roles'              => [$rolSuper->id], // Intento de escalamiento
    ];
    // En php CLI podemos inyectar datos simulando el cuerpo
    $refMetodoCuerpo = new ReflectionProperty($ctrlEscalamiento, 'usuarioRepo');
    // Para inyectar el cuerpo en CLI sin php://input, usamos $_POST
    $_POST = $payloadMaliciosoCrear;

    $jsonEscalamientoCrear = $ctrlEscalamiento->crear();
    $resEscalamientoCrear = json_decode($jsonEscalamientoCrear, true);

    afirmar(
        isset($resEscalamientoCrear['codigo'])
        && $resEscalamientoCrear['codigo'] === 403
        && str_contains($resEscalamientoCrear['mensaje'], 'Acceso denegado'),
        '11. H-02: UsuarioControlador::crear() rechaza con HTTP 403 payload que incluya superadmin_plataforma para operador admin_organizacion'
    );

    // 12. UsuarioControlador::sincronizarRoles() rechaza con HTTP 403 intento de escalamiento
    $_POST = ['roles' => [$rolSuper->id]];
    $jsonEscalamientoSync = $ctrlEscalamiento->sincronizarRoles((string) $usrAlphaId);
    $resEscalamientoSync = json_decode($jsonEscalamientoSync, true);

    afirmar(
        isset($resEscalamientoSync['codigo'])
        && $resEscalamientoSync['codigo'] === 403
        && str_contains($resEscalamientoSync['mensaje'], 'Acceso denegado'),
        '12. H-02: UsuarioControlador::sincronizarRoles() rechaza con HTTP 403 asignación de superadmin_plataforma por parte de admin_organizacion'
    );

    // ==============================================================================
    // BLOQUE 3: AISLAMIENTO MULTI-TENANT IDOR H-03 (P2) — 8 PRUEBAS
    // ==============================================================================

    // 13. verificarAlcanceOrganizacion() retorna false ante cross-tenant
    $alcanceCrossTenant = $authzServicio->verificarAlcanceOrganizacion($usrAlphaId, $orgBetaId);
    afirmar(
        $alcanceCrossTenant === false,
        '13. H-03: AutorizacionServicio::verificarAlcanceOrganizacion() retorna false cuando operador y recurso pertenecen a distintas organizaciones'
    );

    // 14. verificarAlcanceOrganizacion() retorna true para misma organización
    $alcanceMismaOrg = $authzServicio->verificarAlcanceOrganizacion($usrAlphaId, $orgAlphaId);
    afirmar(
        $alcanceMismaOrg === true,
        '14. H-03: AutorizacionServicio::verificarAlcanceOrganizacion() retorna true cuando operador y recurso pertenecen a la misma organización'
    );

    // 15. verificarAlcanceOrganizacion() retorna true para superadmin cross-tenant
    $alcanceSuperadmin = $authzServicio->verificarAlcanceOrganizacion($usrSuperId, $orgBetaId);
    afirmar(
        $alcanceSuperadmin === true,
        '15. H-03: AutorizacionServicio::verificarAlcanceOrganizacion() autoriza alcance universal para Superadministrador de Plataforma'
    );

    // 16. UsuarioControlador::detalle() retorna 404 ante petición IDOR cross-tenant
    $jsonDetalleIdor = $ctrlEscalamiento->detalle((string) $usrBetaId);
    $resDetalleIdor = json_decode($jsonDetalleIdor, true);

    afirmar(
        isset($resDetalleIdor['codigo'])
        && $resDetalleIdor['codigo'] === 404
        && $resDetalleIdor['exito'] === false,
        '16. H-03: UsuarioControlador::detalle() responde HTTP 404 Not Found ante IDOR cross-tenant (no revela existencia)'
    );

    // 17. UsuarioControlador::actualizar() retorna 404 ante IDOR cross-tenant
    $_POST = ['nombre_completo' => 'Nombre Alterado Ilegalmente'];
    $jsonActualizarIdor = $ctrlEscalamiento->actualizar((string) $usrBetaId);
    $resActualizarIdor = json_decode($jsonActualizarIdor, true);

    afirmar(
        isset($resActualizarIdor['codigo'])
        && $resActualizarIdor['codigo'] === 404
        && $resActualizarIdor['exito'] === false,
        '17. H-03: UsuarioControlador::actualizar() responde HTTP 404 Not Found ante mutación IDOR cross-tenant'
    );

    // 18. UsuarioControlador::cambiarEstado() retorna 404 ante IDOR cross-tenant
    $_POST = ['estado' => 'INACTIVO'];
    $jsonEstadoIdor = $ctrlEscalamiento->cambiarEstado((string) $usrBetaId);
    $resEstadoIdor = json_decode($jsonEstadoIdor, true);

    afirmar(
        isset($resEstadoIdor['codigo'])
        && $resEstadoIdor['codigo'] === 404
        && $resEstadoIdor['exito'] === false,
        '18. H-03: UsuarioControlador::cambiarEstado() responde HTTP 404 Not Found ante cambio de estado cross-tenant'
    );

    // 19. UsuarioControlador::sincronizarRoles() retorna 404 ante IDOR cross-tenant
    $_POST = ['roles' => [$rolAdminOrg->id]];
    $jsonSyncRolesIdor = $ctrlEscalamiento->sincronizarRoles((string) $usrBetaId);
    $resSyncRolesIdor = json_decode($jsonSyncRolesIdor, true);

    afirmar(
        isset($resSyncRolesIdor['codigo'])
        && $resSyncRolesIdor['codigo'] === 404
        && $resSyncRolesIdor['exito'] === false,
        '19. H-03: UsuarioControlador::sincronizarRoles() responde HTTP 404 Not Found ante sincronización de roles cross-tenant'
    );

    // 20. UsuarioControlador::restablecerClave() retorna 404 ante IDOR cross-tenant
    $_POST = ['contrasena' => 'NuevaClaveIdor123!'];
    $jsonResetIdor = $ctrlEscalamiento->restablecerClave((string) $usrBetaId);
    $resResetIdor = json_decode($jsonResetIdor, true);

    afirmar(
        isset($resResetIdor['codigo'])
        && $resResetIdor['codigo'] === 404
        && $resResetIdor['exito'] === false,
        '20. H-03: UsuarioControlador::restablecerClave() responde HTTP 404 Not Found ante reseteo de clave cross-tenant'
    );

    // ==============================================================================
    // BLOQUE 4: CATÁLOGO DE ROLES H-04 — 2 PRUEBAS
    // ==============================================================================

    // 21. AutorizacionServicio::obtenerRolesAsignables() excluye superadmin_plataforma para admin_organizacion
    $rolesAsignablesAlpha = $authzServicio->obtenerRolesAsignables($usrAlphaId, $orgAlphaId);
    $codigosRolesAlpha = array_map(fn($r) => $r->codigo, $rolesAsignablesAlpha);

    $rolesAsignablesSuper = $authzServicio->obtenerRolesAsignables($usrSuperId, $orgAlphaId);
    $codigosRolesSuper = array_map(fn($r) => $r->codigo, $rolesAsignablesSuper);

    afirmar(
        !in_array('superadmin_plataforma', $codigosRolesAlpha, true)
        && in_array('superadmin_plataforma', $codigosRolesSuper, true),
        '21. H-04: obtenerRolesAsignables() excluye superadmin_plataforma para admin_organizacion y lo incluye para superadmin'
    );

    // 22. UsuarioControlador::roles() no expone superadmin_plataforma a admin_organizacion
    $jsonRolesApi = $ctrlEscalamiento->roles();
    $resRolesApi = json_decode($jsonRolesApi, true);
    $rolesApiCodigos = array_column($resRolesApi['datos']['roles'] ?? [], 'codigo');

    afirmar(
        $resRolesApi['exito'] === true
        && !in_array('superadmin_plataforma', $rolesApiCodigos, true),
        '22. H-04: Endpoint /api/v1/roles no expone el rol superadmin_plataforma a operadores de organización'
    );

    // ==============================================================================
    // BLOQUE 5: SANITIZACIÓN, SECRETOS Y GOBERNANZA H-05, H-06, H-07 — 3 PRUEBAS
    // ==============================================================================

    // 23. Respuesta ante error interno 500 no expone SQLSTATE ni mensaje interno
    // Simulamos fallo en controlador inyectando mock con excepción
    $ctrlConFallo = new class(
        $mockAuthMiddleware,
        $mockAuthzMiddleware,
        new class extends UsuarioRepositorio {
            public function __construct() {}
            public function buscarPorNombreUsuario(string $u): ?Usuario {
                throw new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'app_candelaria.falsa' doesn't exist");
            }
        },
        $personaRepo, $rolRepo, $sesionRepo, $auditoriaRepo, $pdo
    ) extends UsuarioControlador {};

    $_POST = [
        'modo_persona'       => 'existente',
        'persona_id'         => $perAlphaId,
        'nombre_usuario'     => 'test_sql_leak',
        'correo_electronico' => 'leak@test.com',
        'contrasena'         => 'TestLeakPass123!',
    ];

    $jsonFallo500 = $ctrlConFallo->crear();
    $resFallo500 = json_decode($jsonFallo500, true);

    afirmar(
        isset($resFallo500['codigo'])
        && $resFallo500['codigo'] === 500
        && !str_contains($jsonFallo500, 'SQLSTATE')
        && !str_contains($jsonFallo500, 'Table')
        && !str_contains($jsonFallo500, 'app_candelaria')
        && $resFallo500['mensaje'] === 'Error interno al registrar usuario.',
        '23. H-05/H-06: Respuestas de error 500 sanitizadas: 0 exposición de SQLSTATE, nombres de tablas o trazas internas'
    );

    // 24. Censura estricta de contraseñas y secretos en auditoría ([REDACTADO])
    $auditoriaRepo->registrar(
        contexto: $ctxAlpha,
        modulo: 'seguridad',
        accion: 'TEST_SECRETOS',
        entidadTipo: 'USUARIO',
        entidadId: (string) $usrAlphaId,
        datosPrevios: ['contrasena' => 'SecretPass123!', 'password_hash' => 'hash_secreto'],
        datosNuevos: ['token' => 'token_secreto_abc', 'clave' => 'mi_clave_123']
    );

    $ultimoAuditSecretos = $pdo->query("SELECT datos_previos_json, datos_nuevos_json FROM auditoria_operaciones WHERE accion = 'TEST_SECRETOS' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $prevJson = (string) $ultimoAuditSecretos['datos_previos_json'];
    $nuevJson = (string) $ultimoAuditSecretos['datos_nuevos_json'];

    afirmar(
        str_contains($prevJson, '[REDACTADO]')
        && str_contains($nuevJson, '[REDACTADO]')
        && !str_contains($prevJson, 'SecretPass123!')
        && !str_contains($prevJson, 'hash_secreto')
        && !str_contains($nuevJson, 'token_secreto_abc')
        && !str_contains($nuevJson, 'mi_clave_123'),
        '24. H-07: Censura estricta de secretos en auditoría: contraseñas, hashes y tokens reemplazados por [REDACTADO]'
    );

    // 25. Gobernanza: CHECK chk_auditoria_actor respetado sin alteración y DELETE prohibido (HTTP 405)
    $resDelete = json_decode($ctrlEscalamiento->eliminar((string) $usrBetaId), true);
    $stmtCheckMysql = $pdo->query("SELECT CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_NAME = 'chk_auditoria_actor' LIMIT 1");
    $checkRow = $stmtCheckMysql->fetch(PDO::FETCH_ASSOC);

    afirmar(
        isset($resDelete['codigo'])
        && $resDelete['codigo'] === 405
        && $resDelete['exito'] === false
        && $checkRow !== false
        && str_contains((string) $checkRow['CHECK_CLAUSE'], 'actor_tipo'),
        '25. Invariantes de Gobernanza: CHECK chk_auditoria_actor íntegro y vigente, y eliminación física rechazada con HTTP 405'
    );

} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "\n==============================================================================\n";
echo "RESULTADO FINAL F1.1E: {$exitos} PRUEBAS EXITOSAS / {$fallos} FALLOS\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
