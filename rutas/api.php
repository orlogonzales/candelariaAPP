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
            'fase' => 'F0.1 Foundation',
            'entorno' => entorno('APP_ENV', 'desarrollo'),
            'php' => PHP_VERSION,
            'frontend' => 'Alina Bootstrap 5 + JS Moderno + Fetch API',
            'iconografia' => 'Font Awesome Free 6.3.0',
            'tipografia' => 'Fira Sans Extra Condensed',
            'hora_servidor' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});
