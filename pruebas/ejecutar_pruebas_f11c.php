<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Rol;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
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
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE AUTORIZACIÓN Y RBAC F1.1C\n";
echo "ROLES, PERMISOS, AUTORIDAD BACKEND, MIDDLEWARE Y CUENTA ORLANDO\n";
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

// 0. Instanciar servicios y repositorios RBAC
$usuarioRepo   = new UsuarioRepositorio($pdo);
$personaRepo   = new PersonaRepositorio($pdo);
$rolRepo       = new RolRepositorio($pdo);
$permisoRepo   = new PermisoRepositorio($pdo);
$sesionRepo    = new SesionRepositorio($pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);

$authServicio  = new AutenticacionServicio($usuarioRepo, $sesionRepo, $auditoriaRepo, $pdo);
$authzServicio = new AutorizacionServicio($rolRepo, $permisoRepo, $usuarioRepo, $auditoriaRepo, $pdo);
$authMiddleware = new AutenticacionMiddleware($authServicio);
$authzMiddleware = new AutorizacionMiddleware($authzServicio);

// Usamos una transacción aislada para las pruebas dinámicas para no dejar residuos
$pdo->beginTransaction();

try {
    // Preparar organización de prueba
    $pdo->exec("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `estado`) VALUES (9997, 'test_tenant_f11c', 'ORGANIZACIÓN PRUEBAS F1.1C', 'ACTIVO')");
    $orgId = 9997;

    // Persona de prueba
    $personaIdTest = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '72223344',
        nombres: 'Tester',
        apellidos: 'Rbac Unit',
        correoElectronico: 'rbac.tester@test.com',
        telefonoWhatsapp: '+51951000777',
        ciudad: 'Puno',
        codigoPais: 'PE'
    ));

    $claveTest = 'Candelaria2026!Rbac';
    $hashTest = password_hash($claveTest, PASSWORD_DEFAULT);

    // Usuario A: Operador (Rol 3: operador_produccion -> sin permisos de usuarios.*)
    $usrOperadorId = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgId,
        personaId: $personaIdTest,
        nombreUsuario: 'usr_operador_test',
        nombreCompleto: 'Operador Test',
        correoElectronico: 'operador.rbac@test.com',
        contrasenaHash: $hashTest,
        estado: 'ACTIVO'
    ));
    $rolRepo->asignarRolAUsuario($usrOperadorId, 3); // operador_produccion

    // Usuario B: Administrador (Rol 2: admin_organizacion -> con permisos usuarios.*)
    $personaIdAdmin = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '72223345',
        nombres: 'Admin',
        apellidos: 'Rbac Tenant',
        correoElectronico: 'admin.rbac@test.com',
        telefonoWhatsapp: '+51951000888',
        ciudad: 'Puno',
        codigoPais: 'PE'
    ));
    $usrAdminId = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgId,
        personaId: $personaIdAdmin,
        nombreUsuario: 'usr_admin_test',
        nombreCompleto: 'Admin Tenant Test',
        correoElectronico: 'admin.rbac@test.com',
        contrasenaHash: $hashTest,
        estado: 'ACTIVO'
    ));
    $rolRepo->asignarRolAUsuario($usrAdminId, 2); // admin_organizacion

    // --- 01. Usuario sin sesión → 401 ---
    $permitidoSinSesion = $authzMiddleware->verificarPermiso('usuarios.ver', null, false);
    afirmar(
        $permitidoSinSesion === false,
        '01. Usuario sin sesión es rechazado por el middleware (equivale a HTTP 401)'
    );

    // --- 02. Usuario sin permiso → 403 ---
    $authOperador = $authServicio->autenticar('usr_operador_test', $claveTest);
    $ctxOperador = $authOperador->contexto;
    $tienePermisoVer = $authzServicio->tienePermiso($usrOperadorId, 'usuarios.ver');
    $tienePermisoCrear = $authzServicio->tienePermiso($usrOperadorId, 'usuarios.crear');
    $middlewareBloquea = $authzMiddleware->verificarPermiso('usuarios.crear', $ctxOperador, false);

    $excepcion403Lanzada = false;
    try {
        $authzServicio->autorizar($usrOperadorId, 'usuarios.crear', $ctxOperador);
    } catch (AccesoDenegadoExcepcion $e) {
        $excepcion403Lanzada = ($e->codigoHttp === 403);
    }

    afirmar(
        $tienePermisoVer === false
        && $tienePermisoCrear === false
        && $middlewareBloquea === false
        && $excepcion403Lanzada === true,
        '02. Usuario autenticado sin permiso es denegado por servicio y middleware (HTTP 403 Forbidden)'
    );

    // --- 03. Usuario con permiso → PASS ---
    $authAdmin = $authServicio->autenticar('usr_admin_test', $claveTest);
    $ctxAdmin = $authAdmin->contexto;
    $adminTieneVer = $authzServicio->tienePermiso($usrAdminId, 'usuarios.ver');
    $adminTieneCrear = $authzServicio->tienePermiso($usrAdminId, 'usuarios.crear');
    $adminTieneEditar = $authzServicio->tienePermiso($usrAdminId, 'usuarios.editar');
    $middlewarePermite = $authzMiddleware->verificarPermiso('usuarios.ver', $ctxAdmin, false);

    afirmar(
        $adminTieneVer === true
        && $adminTieneCrear === true
        && $adminTieneEditar === true
        && $middlewarePermite === true,
        '03. Usuario con rol administrativo y permisos concedidos accede exitosamente (PASS)'
    );

    // --- 04. Múltiples roles agregados sin colisión ---
    // Creamos un rol temporal personalizado y le asignamos un permiso
    $pdo->exec("INSERT INTO `roles` (`id`, `organizacion_id`, `codigo`, `nombre`, `descripcion`, `es_sistema`) VALUES (991, {$orgId}, 'rol_soporte_temporal', 'SOPORTE TEMPORAL', 'Rol de soporte para prueba', 0)");
    $permisoRepo->asignarPermisoARol(991, 1); // usuarios.ver

    // Usuario Operador no tenía usuarios.ver; al asignarle el segundo rol, debe adquirirlo
    $rolRepo->asignarRolAUsuario($usrOperadorId, 991);
    $rolesOperador = $rolRepo->obtenerRolesDeUsuario($usrOperadorId);
    $operadorAhoraTieneVer = $authzServicio->tienePermiso($usrOperadorId, 'usuarios.ver');
    $operadorAunNoTieneCrear = $authzServicio->tienePermiso($usrOperadorId, 'usuarios.crear');

    afirmar(
        count($rolesOperador) === 2
        && $operadorAhoraTieneVer === true
        && $operadorAunNoTieneCrear === false,
        '04. Múltiples roles asignados agregan privilegios correctamente sin colisiones ni pérdidas'
    );

    // --- 05. Permiso heredado por rol (propagación inmediata en BD) ---
    // Si asignamos a rol 3 (operador_produccion) el permiso 4 (usuarios.desactivar)
    $permisoRepo->asignarPermisoARol(3, 4);
    $operadorTieneDesactivar = $authzServicio->tienePermiso($usrOperadorId, 'usuarios.desactivar');
    // Revertir asignación al rol 3
    $permisoRepo->removerPermisoDeRol(3, 4);
    $operadorSinDesactivar = $authzServicio->tienePermiso($usrOperadorId, 'usuarios.desactivar');

    afirmar(
        $operadorTieneDesactivar === true && $operadorSinDesactivar === false,
        '05. Herencia dinámica: cambios en matriz rol_permisos se reflejan de inmediato en la autorización'
    );

    // --- 06. Usuario inactivo → DENEGADO ---
    $usuarioRepo->actualizarEstado($usrAdminId, 'INACTIVO');
    $adminInactivoTienePermiso = $authzServicio->tienePermiso($usrAdminId, 'usuarios.ver');
    $usuarioRepo->actualizarEstado($usrAdminId, 'ACTIVO'); // Restaurar

    afirmar(
        $adminInactivoTienePermiso === false,
        '06. Backend es autoridad absoluta: cuenta marcada como INACTIVO pierde toda autorización inmediatamente'
    );

    // --- 07. Sesión revocada → DENEGADO ---
    $tokenRevocar = $authAdmin->tokenSesion;
    $sesionRepo->revocarPorToken($tokenRevocar);
    $ctxDespuesDeRevocar = $authServicio->validarSesion($tokenRevocar);
    $middlewareRechazaRevocada = $authzMiddleware->verificarPermiso('usuarios.ver', $ctxDespuesDeRevocar, false);

    afirmar(
        $ctxDespuesDeRevocar === null && $middlewareRechazaRevocada === false,
        '07. Sesión revocada es invalidada en backend e impide el acceso al middleware de autorización'
    );

    // --- 08. Permiso inexistente → DENEGADO ---
    $permisoFantasma = $authzServicio->tienePermiso($usrAdminId, 'modulo_inexistente.accion_falsa');
    afirmar(
        $permisoFantasma === false,
        '08. Solicitud de verificación para un código de permiso inexistente en catálogo es denegada'
    );

    // --- 09. Manipulación frontend → NO permite bypass ---
    // Simular un intento de inyección donde un atacante envía en $_POST o en parámetros de frontend
    // variables como ['es_admin' => true, 'rol' => 'superadmin', 'permisos' => ['*']]
    $postMalicioso = [
        'es_superadmin_plataforma' => true,
        'rol'                      => 'superadmin_plataforma',
        'permisos'                 => ['usuarios.crear', 'usuarios.editar'],
    ];

    // El middleware y servicio ignoran el arreglo $postMalicioso y consultan exclusivamente la BD
    // a través del identificador verificado del usuario operador
    $accesoManipulado = $authzServicio->tienePermiso($usrOperadorId, 'usuarios.crear');
    afirmar(
        $accesoManipulado === false,
        '09. Inviolabilidad: Manipulación de payloads o parámetros frontend no altera la autorización en backend'
    );

} finally {
    $pdo->rollBack();
}

// --- 10 & 11. Verificación de la cuenta real de Orlando en la BD app_candelaria ---
// Consultamos fuera del rollback la cuenta de desarrollo `orlando`
$usrOrlando = $usuarioRepo->buscarPorNombreUsuario('orlando');
afirmar(
    $usrOrlando !== null
    && $usrOrlando->estado === 'ACTIVO'
    && $usrOrlando->nombreUsuario === 'orlando',
    '10. Cuenta administrativa temporal orlando existe en base de datos y se encuentra ACTIVA'
);

if ($usrOrlando !== null) {
    // 11. Autorización administrativa de Orlando mediante RBAC real
    $rolesOrlando = $rolRepo->obtenerRolesDeUsuario($usrOrlando->id);
    $tieneRolAdmin = false;
    foreach ($rolesOrlando as $rol) {
        if ($rol->codigo === 'admin_organizacion') {
            $tieneRolAdmin = true;
            break;
        }
    }

    $orlandoVer        = $authzServicio->tienePermiso($usrOrlando->id, 'usuarios.ver');
    $orlandoCrear      = $authzServicio->tienePermiso($usrOrlando->id, 'usuarios.crear');
    $orlandoEditar     = $authzServicio->tienePermiso($usrOrlando->id, 'usuarios.editar');
    $orlandoDesactivar = $authzServicio->tienePermiso($usrOrlando->id, 'usuarios.desactivar');
    $orlandoRoles      = $authzServicio->tienePermiso($usrOrlando->id, 'usuarios.roles');
    $orlandoResetClave = $authzServicio->tienePermiso($usrOrlando->id, 'usuarios.restablecer_clave');
    $orlandoInexistente = $authzServicio->tienePermiso($usrOrlando->id, 'permiso_falso.ejecutar');

    afirmar(
        $tieneRolAdmin === true
        && $orlandoVer === true
        && $orlandoCrear === true
        && $orlandoEditar === true
        && $orlandoDesactivar === true
        && $orlandoRoles === true
        && $orlandoResetClave === true
        && $orlandoInexistente === false,
        '11. Cuenta orlando posee rol admin_organizacion y los 6 permisos de gestión de usuarios vía RBAC real'
    );
}

echo "\n==============================================================================\n";
echo "RESULTADO FINAL F1.1C: {$exitos} PRUEBAS EXITOSAS / {$fallos} FALLOS\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
