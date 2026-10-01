<?php

declare(strict_types=1);

use Nucleo\Enrutamiento\Enrutador;
use Nucleo\Http\Vista;

/**
 * Rutas Web de CandelariaAPP.
 */

Enrutador::get('/', function () {
    return Vista::renderizar('inicio', [
        'titulo' => 'CandelariaAPP V1 | Panel de Gestión',
        'subtitulo' => 'Panel de Gestión y Producción Audiovisual',
        'tituloSeccion' => 'Vista General'
    ], 'principal');
});
