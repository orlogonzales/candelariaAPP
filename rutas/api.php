<?php

declare(strict_types=1);

use Nucleo\Enrutamiento\Enrutador;

/**
 * Rutas de la API REST (API-First) de CandelariaAPP.
 */

Enrutador::get('/api/v1/estado', function () {
    header('Content-Type: application/json; charset=utf-8');
    return json_encode([
        'exito' => true,
        'codigo' => 200,
        'mensaje' => 'API CandelariaAPP V1 en funcionamiento.',
        'datos' => [
            'plataforma' => 'CandelariaAPP',
            'version' => '1.0.0',
            'fase' => 'F1.1C RBAC y Autorización Backend',
            'entorno' => entorno('APP_ENV', 'desarrollo'),
            'php' => PHP_VERSION,
            'frontend' => 'Alina Bootstrap 5 + JS Moderno + Fetch API',
            'iconografia' => 'Font Awesome Free 6.3.0',
            'tipografia' => 'Fira Sans Extra Condensed',
            'hora_servidor' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Endpoint técnico de Autenticación (Login)
Enrutador::post('/api/v1/auth/login', function () {
    header('Content-Type: application/json; charset=utf-8');
    $cuerpo = json_decode(file_get_contents('php://input'), true);
    if (!is_array($cuerpo)) {
        $cuerpo = $_POST;
    }

    $login = (string) ($cuerpo['login'] ?? $cuerpo['usuario'] ?? $cuerpo['correo'] ?? '');
    $password = (string) ($cuerpo['password'] ?? $cuerpo['contrasena'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $agente = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $correlacion = \Nucleo\Http\ContextoOperacion::extraerDeEncabezados($_SERVER);

    if (empty($login) || empty($password)) {
        http_response_code(400);
        return json_encode([
            'exito'   => false,
            'codigo'  => 400,
            'mensaje' => 'Debe ingresar su usuario/correo y contraseña.',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    $servicio = new \Aplicacion\Seguridad\AutenticacionServicio();
    $resultado = $servicio->autenticar($login, $password, $ip, $agente, $correlacion);

    if (!$resultado->exitoso) {
        http_response_code(401);
        return json_encode([
            'exito'   => false,
            'codigo'  => 401,
            'mensaje' => $resultado->mensajeError,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    $usr = $resultado->usuario;
    return json_encode([
        'exito'   => true,
        'codigo'  => 200,
        'mensaje' => 'Autenticación exitosa.',
        'datos'   => [
            'usuario' => [
                'id'              => $usr->id,
                'nombre_usuario'  => $usr->nombreUsuario,
                'nombre_completo' => $usr->nombreCompleto,
                'correo'          => $usr->correoElectronico,
                'estado'          => $usr->estado,
            ],
            'redireccion'    => url_base(),
            'correlacion_id' => $resultado->contexto?->correlacionId,
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Endpoint técnico de Cierre de Sesión (Logout)
Enrutador::post('/api/v1/auth/logout', function () {
    header('Content-Type: application/json; charset=utf-8');
    $token = \Nucleo\Seguridad\ManejadorCookie::extraerDePeticion();
    if ($token === null && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/^Bearer\s+(.+)$/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = trim($m[1]);
        }
    }

    if ($token !== null) {
        $servicio = new \Aplicacion\Seguridad\AutenticacionServicio();
        $servicio->cerrarSesion($token);
    }
    \Nucleo\Seguridad\ManejadorCookie::destruir();

    return json_encode([
        'exito'   => true,
        'codigo'  => 200,
        'mensaje' => 'Sesión cerrada correctamente.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Endpoint técnico de Consulta de Sesión Activa
Enrutador::get('/api/v1/auth/sesion', function () {
    header('Content-Type: application/json; charset=utf-8');
    $middleware = new \Nucleo\Http\Middleware\AutenticacionMiddleware();
    $contexto = $middleware->procesar($_SERVER, $_COOKIE, false);

    if ($contexto === null) {
        http_response_code(401);
        return json_encode([
            'exito'   => false,
            'codigo'  => 401,
            'mensaje' => 'No hay una sesión activa o ha expirado.',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    return json_encode([
        'exito'   => true,
        'codigo'  => 200,
        'mensaje' => 'Sesión activa verificada.',
        'datos'   => [
            'actor_tipo'     => $contexto->actorTipo,
            'usuario_id'     => $contexto->usuarioId,
            'canal'          => $contexto->canalCodigo,
            'correlacion_id' => $contexto->correlacionId,
            'ip'             => $contexto->origenIp,
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Endpoint técnico protegido por Autorización RBAC (Requiere permiso usuarios.ver)
Enrutador::get('/api/v1/usuarios/lista', function () {
    header('Content-Type: application/json; charset=utf-8');

    // 1. Verificar Autenticación (401 si falla)
    $authMiddleware = new \Nucleo\Http\Middleware\AutenticacionMiddleware();
    $contexto = $authMiddleware->procesar($_SERVER, $_COOKIE, true);

    // 2. Verificar Permiso RBAC (403 si carece de usuarios.ver)
    $rbacMiddleware = new \Nucleo\Http\Middleware\AutorizacionMiddleware();
    $rbacMiddleware->verificarPermiso('usuarios.ver', $contexto, true);

    // 3. Operación permitida en Backend
    $repo = new \Aplicacion\Repositorios\UsuarioRepositorio();
    $usuarios = $repo->buscarPorOrganizacion($contexto->organizacionId ?? 1, 10, 0);

    return json_encode([
        'exito'   => true,
        'codigo'  => 200,
        'mensaje' => 'Acceso autorizado al padrón de usuarios.',
        'datos'   => [
            'total'    => count($usuarios),
            'usuarios' => array_map(fn($u) => [
                'id'              => $u->id,
                'nombre_usuario'  => $u->nombreUsuario,
                'nombre_completo' => $u->nombreCompleto,
                'estado'          => $u->estado,
            ], $usuarios)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// ==============================================================================
// GESTIÓN INTEGRAL DE USUARIOS Y CONTROL DE ACCESO (MICROLOTE F1.1D)
// ==============================================================================
Enrutador::get('/api/v1/usuarios', [\Aplicacion\Controladores\UsuarioControlador::class, 'listar']);
Enrutador::get('/api/v1/usuarios/{id}', [\Aplicacion\Controladores\UsuarioControlador::class, 'detalle']);
Enrutador::get('/api/v1/personas/disponibles', [\Aplicacion\Controladores\UsuarioControlador::class, 'personasDisponibles']);
Enrutador::get('/api/v1/tipos-documento', [\Aplicacion\Controladores\UsuarioControlador::class, 'tiposDocumento']);
Enrutador::get('/api/v1/roles', [\Aplicacion\Controladores\UsuarioControlador::class, 'roles']);
Enrutador::post('/api/v1/usuarios', [\Aplicacion\Controladores\UsuarioControlador::class, 'crear']);
Enrutador::put('/api/v1/usuarios/{id}', [\Aplicacion\Controladores\UsuarioControlador::class, 'actualizar']);
Enrutador::patch('/api/v1/usuarios/{id}/estado', [\Aplicacion\Controladores\UsuarioControlador::class, 'cambiarEstado']);
Enrutador::put('/api/v1/usuarios/{id}/roles', [\Aplicacion\Controladores\UsuarioControlador::class, 'sincronizarRoles']);
Enrutador::post('/api/v1/usuarios/{id}/restablecer-clave', [\Aplicacion\Controladores\UsuarioControlador::class, 'restablecerClave']);
Enrutador::delete('/api/v1/usuarios/{id}', [\Aplicacion\Controladores\UsuarioControlador::class, 'eliminar']);

// ==============================================================================
// GESTIÓN DE ORGANIZACIÓN Y BRANDING (MICROLOTE F1.2B)
// ==============================================================================
Enrutador::get('/api/v1/organizacion', [\Aplicacion\Controladores\OrganizacionControlador::class, 'detalle']);
Enrutador::put('/api/v1/organizacion', [\Aplicacion\Controladores\OrganizacionControlador::class, 'actualizar']);
Enrutador::post('/api/v1/organizacion/branding/logo', [\Aplicacion\Controladores\OrganizacionControlador::class, 'actualizarLogo']);
Enrutador::post('/api/v1/organizacion/branding/isotipo', [\Aplicacion\Controladores\OrganizacionControlador::class, 'actualizarIsotipo']);
