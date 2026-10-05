<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CandelariaAPP - Plataforma de Gestión y Producción Audiovisual Candelaria">
    <meta name="author" content="O.G. Estudio Creativo">
    <link rel="icon" href="<?= url_activo('alina/images/logo/favicon.png') ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?= url_activo('alina/images/logo/favicon.png') ?>" type="image/x-icon">
    <title><?= escapar_html($titulo ?? 'CandelariaAPP | Panel de Gestión') ?></title>

    <!-- Tipografía Oficial: Fira Sans Extra Condensed (Google Fonts) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans+Extra+Condensed:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Iconografía Oficial: Font Awesome Free 6.3.0 (Local, sin CDN) -->
    <link rel="stylesheet" href="<?= url_activo('alina/vendor/fontawesome/css/all.css') ?>">

    <!-- Bootstrap 5 CSS (Alina) -->
    <link rel="stylesheet" href="<?= url_activo('alina/vendor/bootstrap/bootstrap.min.css') ?>">

    <!-- Simplebar CSS (Alina) -->
    <link rel="stylesheet" href="<?= url_activo('alina/vendor/simplebar/simplebar.css') ?>">

    <!-- Estilos Base Alina -->
    <link rel="stylesheet" href="<?= url_activo('alina/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url_activo('alina/css/responsive.css') ?>">

    <!-- DataTables CSS (Alina Vendor) -->
    <link rel="stylesheet" href="<?= url_activo('alina/vendor/datatable/jquery.dataTables.min.css') ?>">

    <!-- Flatpickr CSS (Alina Vendor) -->
    <link rel="stylesheet" href="<?= url_activo('alina/vendor/flatpickr/flatpickr.min.css') ?>">

    <!-- Capa Derivada CandelariaAPP (Sobrescritura tipográfica, tooltips y personalización) -->
    <link rel="stylesheet" href="<?= url_base('publico/css/candelaria.css') ?>">

    <!-- Infraestructura Visual de Skeleton Loaders -->
    <link rel="stylesheet" href="<?= url_base('publico/css/skeleton.css') ?>">

    <?php
    $contextoOperacionActual = \Nucleo\Http\ContextoOperacion::actual();
    $csrfTokenActual = $contextoOperacionActual?->metadatos['csrf_token'] ?? '';
    ?>
    <meta name="csrf-token" content="<?= escapar_html($csrfTokenActual) ?>">
    <meta name="url-base" content="<?= url_base() ?>">
    <script>
        window.CANDELARIA_BASE_URL = "<?= url_base() ?>";
        window.CANDELARIA_CSRF_TOKEN = "<?= escapar_html($csrfTokenActual) ?>";
    </script>
</head>
<body class="light">
<div class="app-wrapper">
