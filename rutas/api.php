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
            'fase' => 'F1.1B Autenticación y Sesiones Seguras',
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
