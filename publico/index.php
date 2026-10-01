<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP - CONTROLADOR FRONTAL OFICIAL (publico/index.php)
 * ==============================================================================
 * Punto de entrada único para solicitudes HTTP/Web y API.
 * PHP 8.3 Nativo &bull; MVC Propio &bull; API-First
 * ==============================================================================
 */

define('CANDELARIA_INICIO', microtime(true));
define('RAIZ_PROYECTO', dirname(__DIR__));

// 1. Cargar Autoload de Composer
if (file_exists(RAIZ_PROYECTO . '/vendor/autoload.php')) {
    require_once RAIZ_PROYECTO . '/vendor/autoload.php';
}

// 2. Cargar variables de entorno
\Nucleo\Soporte\CargadorEntorno::cargar(RAIZ_PROYECTO . '/.env');

// 3. Configuración horaria y errores
date_default_timezone_set(entorno('ZONA_HORARIA', 'America/Lima'));

if (entorno('APP_DEPURACION', false)) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 4. Cargar Definición de Rutas Web y API
require_once RAIZ_PROYECTO . '/rutas/web.php';
require_once RAIZ_PROYECTO . '/rutas/api.php';

// 5. Despachar Solicitud
\Nucleo\Enrutamiento\Enrutador::despachar();
