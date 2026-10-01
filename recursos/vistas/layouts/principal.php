<?php

use Nucleo\Http\Vista;

// 1. Encabezado HTML y Metadatos
echo Vista::parcial('metas_encabezado', [
    'titulo' => $titulo ?? 'CandelariaAPP - Plataforma de Gestión'
]);

// 2. Cargador inicial
echo Vista::parcial('cargador');

// 3. Barra lateral (Semi-side-nav compacta con tooltips + Main-side-nav desplegable)
echo Vista::parcial('barra_lateral');

// 4. Cabecera superior y migas de pan
echo Vista::parcial('cabecera_superior', [
    'subtitulo' => $subtitulo ?? 'Panel de Gestión',
    'tituloSeccion' => $tituloSeccion ?? 'Dashboard'
]);
?>

<!-- Área de Contenido Principal Dinámico -->
<main class="py-4">
    <div class="container-fluid">
        <?= $contenido ?? '' ?>
    </div>
</main>

<?php
// 5. Pie de página y componentes auxiliares
echo Vista::parcial('pie_pagina');

// 6. Scripts y cierre de documento
echo Vista::parcial('scripts');
?>
