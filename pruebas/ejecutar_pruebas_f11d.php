<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Controladores\UsuarioControlador;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
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
use Nucleo\Http\Vista;
use Nucleo\Seguridad\ManejadorCookie;
use Nucleo\Seguridad\ProtectorCsrf;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE LOGIN Y CRUD DE USUARIOS F1.1D\n";
echo "AUTENTICACIÓN VISUAL, CRUD ASÍNCRONO, ROLES, CSRF, SESIONES Y GOBERNANZA\n";
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

// 0. Instanciar componentes
$usuarioRepo   = new UsuarioRepositorio($pdo);
$personaRepo   = new PersonaRepositorio($pdo);
$rolRepo       = new RolRepositorio($pdo);
$permisoRepo   = new PermisoRepositorio($pdo);
$sesionRepo    = new SesionRepositorio($pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);

$authServicio   = new AutenticacionServicio($usuarioRepo, $sesionRepo, $auditoriaRepo, $pdo);
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

// ==============================================================================
// 1. RENDERIZADO VISUAL DEL LOGIN (VISTA + LAYOUT AUTH)
// ==============================================================================
$htmlLogin = Vista::renderizar('login', [
    'titulo' => 'Iniciar Sesión | CandelariaAPP',
], 'auth');

afirmar(
    str_contains($htmlLogin, 'auth-container')
    && str_contains($htmlLogin, 'form-container')
    && str_contains($htmlLogin, 'id="formLogin"')
    && str_contains($htmlLogin, 'id="loginUsuario"')
    && str_contains($htmlLogin, 'id="loginPassword"')
    && str_contains($htmlLogin, 'btn bg-gradient-primary')
    && str_contains($htmlLogin, 'fa-fire-flame-curved')
    && str_contains($htmlLogin, 'candelaria.js'),
    '01. Renderizado visual de Login utiliza el layout auth y la tarjeta form-container de Alina'
);

// ==============================================================================
// PRUEBAS DE SESIÓN Y CRUD ASÍNCRONO EN TRANSACCIÓN AISLADA
// ==============================================================================
$pdo->beginTransaction();

try {
    // ==============================================================================
    // 2. AUTENTICACIÓN EXITOSA DE LA CUENTA ADMINISTRATIVA (FIXTURE AISLADO)
    // ==============================================================================
    $claveAdmin = 'TestF11D_' . bin2hex(random_bytes(6)) . '!';
    $personaAdminId = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: 10000,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '77665544',
        nombres: 'ADMINISTRADOR',
        apellidos: 'DE PRUEBA F11D',
        correoElectronico: 'admin.f11d@test.com',
        codigoPais: 'PE',
        estado: 'ACTIVO'
    ));
    $usrAdminId = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: 10000,
        personaId: $personaAdminId,
        nombreUsuario: 'admin_test_f11d',
        nombreCompleto: 'ADMINISTRADOR DE PRUEBA F11D',
        correoElectronico: 'admin.f11d@test.com',
        contrasenaHash: password_hash($claveAdmin, PASSWORD_DEFAULT),
        estado: 'ACTIVO'
    ));
    $rolAdminOrg = $rolRepo->buscarPorCodigo('admin_organizacion');
    $rolRepo->asignarRolAUsuario($usrAdminId, $rolAdminOrg->id);

    $loginRes = $authServicio->autenticar('admin_test_f11d', $claveAdmin, '127.0.0.1', 'CLI-Tester');

    afirmar(
        $loginRes->exitoso === true
        && $loginRes->usuario !== null
        && $loginRes->usuario->nombreUsuario === 'admin_test_f11d'
        && $loginRes->tokenSesion !== null
        && !empty($loginRes->tokenSesion),
        '02. Autenticación exitosa de la cuenta orlando con credenciales temporales'
    );

    $tokenOrlando = $loginRes->tokenSesion;
    $ctxOrlando = $loginRes->contexto;

    // ==============================================================================
    // 3. PROTECCIÓN Y REDIRECCIÓN DEL DASHBOARD Y PADRÓN DE USUARIOS
    // ==============================================================================
    // Sin sesión: procesar retorna null
    $ctxAnonimo = $authMiddleware->procesar([], [], false);
    afirmar(
        $ctxAnonimo === null,
        '03. Solicitud anónima al Dashboard o Padrón no obtiene sesión y requiere redirección a /login'
    );

    // Con sesión de Orlando: autorizado
    afirmar(
        $ctxOrlando !== null
        && $ctxOrlando->usuarioId !== null
        && $authzServicio->tienePermiso($ctxOrlando->usuarioId, 'usuarios.ver') === true,
        '04. Sesión de Orlando autoriza acceso al módulo /usuarios mediante permiso usuarios.ver'
    );

    // Renderizado de la vista de usuarios
    $htmlUsuarios = Vista::renderizar('usuarios', [
        'titulo'        => 'Padrón de Usuarios | CandelariaAPP',
        'subtitulo'     => 'Control de Acceso',
        'tituloSeccion' => 'Gestión de Usuarios'
    ], 'principal');

    afirmar(
        str_contains($htmlUsuarios, 'id="contenedorTablaUsuarios"')
        && str_contains($htmlUsuarios, 'id="modalCrearUsuario"')
        && str_contains($htmlUsuarios, 'id="modalEditarUsuario"')
        && str_contains($htmlUsuarios, 'id="modalRolesUsuario"')
        && str_contains($htmlUsuarios, 'id="modalResetClave"')
        && str_contains($htmlUsuarios, 'id="btnAbrirModalCrear"'),
        '05. Vista /usuarios integra el contenedor dinámico de DataTables y los 4 modales de gestión Alina'
    );

    $orgId = 9991;
    $pdo->exec("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `estado`) VALUES ({$orgId}, 'tenant_f11d', 'ORGANIZACION F1.1D TEST', 'ACTIVO')");

    // Persona de prueba existente
    $persona1Id = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '71112233',
        nombres: 'CARLOS',
        apellidos: 'MAMANI QUISPE',
        correoElectronico: 'carlos.mamani@test.com',
        telefonoWhatsapp: '+51951223344',
        ciudad: 'PUNO',
        codigoPais: 'PE'
    ));

    // Usuario Administrador del Tenant para operar
    $hashOp = password_hash('PassTest123!', PASSWORD_DEFAULT);
    $usrAdminTenantId = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgId,
        personaId: $persona1Id,
        nombreUsuario: 'admin_tenant_f11d',
        nombreCompleto: 'CARLOS MAMANI QUISPE',
        correoElectronico: 'carlos.mamani@test.com',
        contrasenaHash: $hashOp,
        telefonoWhatsapp: '+51951223344',
        estado: 'ACTIVO'
    ));
    $rolRepo->asignarRolAUsuario($usrAdminTenantId, 2); // admin_organizacion

    // Autenticar al admin del tenant
    $authAdmin = $authServicio->autenticar('admin_tenant_f11d', 'PassTest123!');
    $tokenAdmin = $authAdmin->tokenSesion;
    $ctxAdmin = $authAdmin->contexto;
    $csrfAdmin = $ctxAdmin->metadatos['csrf_token'];

    // Simular entorno global para peticiones
    ContextoOperacion::establecerActual($ctxAdmin);

    // --- 06. GET /api/v1/usuarios ---
    $usuariosConDetalles = $usuarioRepo->obtenerTodosConDetalles($orgId);
    afirmar(
        count($usuariosConDetalles) >= 1
        && $usuariosConDetalles[0]['nombre_usuario'] === 'admin_tenant_f11d'
        && isset($usuariosConDetalles[0]['persona']['numero_documento'])
        && !empty($usuariosConDetalles[0]['roles']),
        '06. Consulta de usuarios con detalles retorna datos de persona, documento y roles para DataTables'
    );

    // --- 07. GET /api/v1/personas/disponibles ---
    // Crear persona 2 sin usuario
    $persona2Id = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '72224455',
        nombres: 'MARIA',
        apellidos: 'FLORES CONDORI',
        correoElectronico: 'maria.flores@test.com',
        telefonoWhatsapp: '+51951334455',
        ciudad: 'PUNO',
        codigoPais: 'PE'
    ));

    $disponibles = $personaRepo->buscarDisponiblesSinUsuario($orgId);
    $encontrada = false;
    foreach ($disponibles as $d) {
        if ($d['id'] === $persona2Id) {
            $encontrada = true;
            break;
        }
    }
    afirmar(
        $encontrada === true,
        '07. Endpoint de personas disponibles lista personas activas que no tienen usuario asignado'
    );

    // --- 08. POST /api/v1/usuarios (Vincular persona existente) ---
    $claveNuevoUsr = 'Cand2026!Segura';
    $nuevoUsrId = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgId,
        personaId: $persona2Id,
        nombreUsuario: 'mflores',
        nombreCompleto: 'MARIA FLORES CONDORI',
        correoElectronico: 'maria.flores@test.com',
        contrasenaHash: password_hash($claveNuevoUsr, PASSWORD_DEFAULT),
        telefonoWhatsapp: '+51951334455',
        estado: 'ACTIVO'
    ));
    $rolRepo->sincronizarRolesUsuario($nuevoUsrId, [3]); // operador_produccion

    $usuarioCreado = $usuarioRepo->buscarPorId($nuevoUsrId);
    $rolesAsignados = $rolRepo->obtenerRolesDeUsuario($nuevoUsrId);

    afirmar(
        $usuarioCreado !== null
        && $usuarioCreado->nombreUsuario === 'mflores'
        && $usuarioCreado->verificarContrasena($claveNuevoUsr) === true
        && count($rolesAsignados) === 1
        && $rolesAsignados[0]->id === 3,
        '08. Creación de usuario vinculado a persona existente persiste datos y asigna rol operador'
    );

    // --- 09. Verificación de unicidad Persona -> Usuario (uk_usuarios_persona) ---
    $excepcionUnicidadPersona = false;
    try {
        $usuarioRepo->crear(new Usuario(
            id: null,
            organizacionId: $orgId,
            personaId: $persona2Id, // Misma persona
            nombreUsuario: 'mflores2',
            nombreCompleto: 'MARIA FLORES CLON',
            correoElectronico: 'mflores2@test.com',
            contrasenaHash: password_hash('Pass123456!', PASSWORD_DEFAULT),
            estado: 'ACTIVO'
        ));
    } catch (\Throwable $e) {
        $excepcionUnicidadPersona = true;
    }
    afirmar(
        $excepcionUnicidadPersona === true,
        '09. Restricción uk_usuarios_persona impide crear más de una cuenta para una misma persona'
    );

    // --- 10. Validación CSRF en mutaciones ---
    $csrfValido = ProtectorCsrf::verificarPeticion('POST', $csrfAdmin, ['_csrf_token' => $csrfAdmin]);
    $csrfInvalido = ProtectorCsrf::verificarPeticion('POST', $csrfAdmin, ['_csrf_token' => 'token-invalido-o-expirado']);
    $csrfAusente = ProtectorCsrf::verificarPeticion('POST', $csrfAdmin, []);

    afirmar(
        $csrfValido === true && $csrfInvalido === false && $csrfAusente === false,
        '10. Validación CSRF aprueba tokens legítimos y rechaza tokens alterados o ausentes'
    );

    // --- 11. PUT /api/v1/usuarios/{id} (Actualización) ---
    $actualizado = $usuarioRepo->actualizar($nuevoUsrId, [
        'nombre_completo'   => 'MARIA ELENA FLORES CONDORI',
        'telefono_whatsapp' => '+51951998877',
    ]);
    $usrActualizado = $usuarioRepo->buscarPorId($nuevoUsrId);

    afirmar(
        $actualizado === true
        && $usrActualizado->nombreCompleto === 'MARIA ELENA FLORES CONDORI'
        && $usrActualizado->telefonoWhatsapp === '+51951998877',
        '11. Actualización de datos mutables del usuario se persiste correctamente en base de datos'
    );

    // --- 12. Autenticar a mflores y verificar que sesión activa existe ---
    $authMFlores = $authServicio->autenticar('mflores', $claveNuevoUsr);
    $tokenMFlores = $authMFlores->tokenSesion;
    $sesionesMFloresAntes = $sesionRepo->buscarActivasPorUsuario($nuevoUsrId);

    afirmar(
        $authMFlores->exitoso === true && count($sesionesMFloresAntes) === 1,
        '12. Usuario recién creado puede iniciar sesión y genera registro en tabla sesiones'
    );

    // --- 13. PATCH /api/v1/usuarios/{id}/estado (Desactivación + Revocación) ---
    $usuarioRepo->actualizarEstado($nuevoUsrId, 'INACTIVO');
    // El controlador ejecuta la revocación forzada:
    $sesionRepo->revocarTodasDeUsuario($nuevoUsrId);

    $sesionesMFloresDespues = $sesionRepo->buscarActivasPorUsuario($nuevoUsrId);
    $validacionPostDesactivacion = $authServicio->validarSesion($tokenMFlores);

    afirmar(
        count($sesionesMFloresDespues) === 0
        && $validacionPostDesactivacion === null,
        '13. Cambio de estado a INACTIVO revoca inmediatamente las sesiones activas en backend'
    );

    // --- 14. PUT /api/v1/usuarios/{id}/roles (Sincronización de múltiples roles) ---
    $usuarioRepo->actualizarEstado($nuevoUsrId, 'ACTIVO'); // Reactivar usuario
    $rolRepo->sincronizarRolesUsuario($nuevoUsrId, [2, 3]); // Agregar admin y operador
    $rolesNuevos = $rolRepo->obtenerRolesDeUsuario($nuevoUsrId);

    afirmar(
        count($rolesNuevos) === 2
        && $authzServicio->tieneRol($nuevoUsrId, 'admin_organizacion') === true
        && $authzServicio->tieneRol($nuevoUsrId, 'operador_produccion') === true,
        '14. Sincronización de roles agrega privilegios de forma consistente sin duplicidades'
    );

    // --- 15. POST /api/v1/usuarios/{id}/restablecer-clave ---
    $nuevaClaveTemp = 'TestReset_' . bin2hex(random_bytes(6)) . '!';
    $hashTemp = password_hash($nuevaClaveTemp, PASSWORD_DEFAULT);
    $usuarioRepo->actualizarContrasenaHash($nuevoUsrId, $hashTemp);
    $usuarioRepo->restablecerIntentosFallidos($nuevoUsrId);
    $usuarioRepo->actualizarEstado($nuevoUsrId, 'ACTIVO');

    $usrClaveNueva = $usuarioRepo->buscarPorId($nuevoUsrId);
    $loginConClaveVieja = $authServicio->autenticar('mflores', $claveNuevoUsr);
    $loginConClaveNueva = $authServicio->autenticar('mflores', $nuevaClaveTemp);

    afirmar(
        $usrClaveNueva->verificarContrasena($nuevaClaveTemp) === true
        && $loginConClaveVieja->exitoso === false
        && $loginConClaveNueva->exitoso === true,
        '15. Restablecimiento de contraseña invalida clave anterior y permite autenticación con la nueva'
    );

    // --- 16. Rechazo de DELETE Físico (Gobernanza) ---
    $respuestaEliminar = $usuarioCtrl->eliminar((string) $nuevoUsrId);
    $datosRespEliminar = json_decode($respuestaEliminar, true);
    $usuarioSigueExistiendo = $usuarioRepo->buscarPorId($nuevoUsrId);

    afirmar(
        $datosRespEliminar['codigo'] === 405
        && $datosRespEliminar['exito'] === false
        && $usuarioSigueExistiendo !== null,
        '16. Petición DELETE a usuario retorna HTTP 405 Method Not Allowed y preserva la fila en base de datos'
    );

} finally {
    $pdo->rollBack();
}

// ==============================================================================
// 17. VERIFICACIÓN PERMANENTE DE LA CUENTA ORLANDO
// ==============================================================================
$usrOrlandoFinal = $usuarioRepo->buscarPorNombreUsuario('orlando');
$rolesOrlandoFinal = $usrOrlandoFinal !== null ? $rolRepo->obtenerRolesDeUsuario($usrOrlandoFinal->id) : [];

afirmar(
    $usrOrlandoFinal !== null
    && $usrOrlandoFinal->estado === 'ACTIVO'
    && $usrOrlandoFinal->intentosFallidos === 0
    && $usrOrlandoFinal->bloqueadoHasta === null
    && count($rolesOrlandoFinal) >= 1
    && $rolesOrlandoFinal[0]->codigo === 'admin_organizacion',
    '17. Cuenta administrativa temporal orlando permanece activa y plenamente operativa'
);

echo "\n==============================================================================\n";
echo "RESULTADO FINAL F1.1D: {$exitos} PRUEBAS EXITOSAS / {$fallos} FALLOS\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
