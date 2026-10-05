<?php

declare(strict_types=1);

use Aplicacion\Seguridad\AutenticacionServicio;
use Nucleo\Enrutamiento\Enrutador;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Http\Vista;
use Nucleo\Seguridad\ManejadorCookie;

/**
 * Rutas Web de CandelariaAPP.
 * Control de acceso a nivel de página (login, logout, dashboard, usuarios).
 */

// 1. Pantalla de Inicio de Sesión (Login)
Enrutador::get('/login', function () {
    $authMiddleware = new AutenticacionMiddleware();
    $contexto = $authMiddleware->procesar($_SERVER, $_COOKIE, false);

    // Si ya existe sesión activa válida, redirigir directo al Dashboard
    if ($contexto !== null) {
        header('Location: ' . url_base());
        exit;
    }

    return Vista::renderizar('login', [
        'titulo' => 'Iniciar Sesión | CandelariaAPP',
    ], 'auth');
});

// 2. Cierre de Sesión Web (Logout)
Enrutador::get('/logout', function () {
    $token = ManejadorCookie::extraerDePeticion();
    if ($token !== null) {
        $servicio = new AutenticacionServicio();
        $servicio->cerrarSesion($token);
    }
    ManejadorCookie::destruir();

    header('Location: ' . url_base('login'));
    exit;
});

// 3. Panel de Control Principal (Dashboard)
Enrutador::get('/', function () {
    $authMiddleware = new AutenticacionMiddleware();
    $contexto = $authMiddleware->procesar($_SERVER, $_COOKIE, false);

    // Si no está autenticado, redirigir a la pantalla de login
    if ($contexto === null) {
        header('Location: ' . url_base('login'));
        exit;
    }

    return Vista::renderizar('inicio', [
        'titulo'        => 'CandelariaAPP V1 | Panel de Gestión',
        'subtitulo'     => 'Panel de Gestión y Producción Audiovisual',
        'tituloSeccion' => 'Vista General'
    ], 'principal');
});

// 4. Módulo de Gestión de Usuarios y Roles (CRUD Asíncrono)
Enrutador::get('/usuarios', function () {
    $authMiddleware = new AutenticacionMiddleware();
    $contexto = $authMiddleware->procesar($_SERVER, $_COOKIE, false);

    if ($contexto === null) {
        header('Location: ' . url_base('login'));
        exit;
    }

    $authzMiddleware = new AutorizacionMiddleware();
    if (!$authzMiddleware->verificarPermiso('usuarios.ver', $contexto, false)) {
        http_response_code(403);
        return Vista::renderizar('errores/403', [
            'titulo'        => 'Acceso Denegado | CandelariaAPP',
            'subtitulo'     => 'Control de Acceso',
            'tituloSeccion' => 'Error 403',
            'mensaje'       => 'No cuenta con los privilegios necesarios (usuarios.ver) para acceder al padrón de usuarios.'
        ], 'principal');
    }

    return Vista::renderizar('usuarios', [
        'titulo'          => 'Padrón de Usuarios | CandelariaAPP',
        'subtitulo'       => 'Control de Acceso y Gestión de Identidades',
        'tituloSeccion'   => 'Gestión de Usuarios',
        'scriptAdicional' => url_base('publico/js/usuarios.js')
    ], 'principal');
});

// 5. Módulo de Configuración — Ficha de Organización y Branding (F1.2B)
Enrutador::get('/configuracion/organizacion', function () {
    $authMiddleware = new AutenticacionMiddleware();
    $contexto = $authMiddleware->procesar($_SERVER, $_COOKIE, false);

    if ($contexto === null) {
        header('Location: ' . url_base('login'));
        exit;
    }

    $authzMiddleware = new AutorizacionMiddleware();
    if (!$authzMiddleware->verificarPermiso('organizacion.ver', $contexto, false)) {
        http_response_code(403);
        return Vista::renderizar('errores/403', [
            'titulo'        => 'Acceso Denegado | CandelariaAPP',
            'subtitulo'     => 'Configuración de Organización',
            'tituloSeccion' => 'Error 403',
            'mensaje'       => 'No cuenta con los privilegios necesarios (organizacion.ver) para consultar la ficha de organización.'
        ], 'principal');
    }

    return Vista::renderizar('configuracion/organizacion', [
        'titulo'          => 'Ficha de Organización | CandelariaAPP',
        'subtitulo'       => 'Configuración Institucional y Branding',
        'tituloSeccion'   => 'Organización',
        'seccionActiva'   => 'organizacion',
        'scriptAdicional' => url_base('publico/js/organizacion.js')
    ], 'principal');
});
