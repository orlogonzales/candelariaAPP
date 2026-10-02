<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP - CONFIGURACIÓN DE SEGURIDAD, AUTENTICACIÓN Y SESIONES (F1.1B)
 * ==============================================================================
 * Centraliza parámetros críticos para evitar números mágicos dispersos.
 * Permite personalización vía variables de entorno manteniendo fallbacks seguros.
 * ==============================================================================
 */

return [
    // Políticas de Fuerza Bruta y Bloqueo
    'intentos_fallidos_maximos' => (int) entorno('AUTH_MAX_INTENTOS', 5),
    'minutos_bloqueo'           => (int) entorno('AUTH_MINUTOS_BLOQUEO', 15),

    // Políticas de Expiración y Ciclo de Vida de Sesión
    'timeout_inactividad_segundos'  => (int) entorno('AUTH_TIMEOUT_INACTIVIDAD', 7200), // 2 horas
    'tiempo_vida_absoluto_segundos' => (int) entorno('AUTH_TIEMPO_VIDA_ABSOLUTO', 86400), // 24 horas

    // Configuración de Cookies de Sesión
    'cookie' => [
        'nombre'   => (string) entorno('AUTH_COOKIE_NOMBRE', 'candelaria_sesion'),
        'ruta'     => '/',
        'dominio'  => entorno('AUTH_COOKIE_DOMINIO', null),
        'httponly' => true,
        'samesite' => 'Lax',
        // 'Secure' solo se activa si HTTPS está explícitamente presente o en entorno producción
        'seguro'   => (bool) entorno(
            'AUTH_COOKIE_SECURE',
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || entorno('APP_ENV') === 'produccion'
        ),
    ],

    // Parámetros de Protección CSRF
    'csrf' => [
        'nombre_campo'         => '_csrf_token',
        'nombre_encabezado'    => 'X-CSRF-Token',
        'longitud_bytes'       => 32,
        'tiempo_vida_segundos' => (int) entorno('CSRF_TIMEOUT', 7200),
    ],
];
