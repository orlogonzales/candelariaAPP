<?php

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CandelariaAPP - Iniciar Sesión">
    <meta name="author" content="O.G. Estudio Creativo">
    <link rel="icon" href="<?= url_activo('alina/images/logo/favicon.png') ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?= url_activo('alina/images/logo/favicon.png') ?>" type="image/x-icon">
    <title><?= escapar_html($titulo ?? 'Iniciar Sesión | CandelariaAPP') ?></title>

    <!-- Tipografía Oficial: Fira Sans Extra Condensed -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans+Extra+Condensed:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Iconografía Oficial: Font Awesome Free 6.3.0 (Local Alina) -->
    <link rel="stylesheet" href="<?= url_activo('alina/vendor/fontawesome/css/all.css') ?>">

    <!-- Bootstrap 5 CSS (Alina) -->
    <link rel="stylesheet" href="<?= url_activo('alina/vendor/bootstrap/bootstrap.min.css') ?>">

    <!-- Estilos Base Alina -->
    <link rel="stylesheet" href="<?= url_activo('alina/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url_activo('alina/css/responsive.css') ?>">

    <!-- Capa Derivada CandelariaAPP -->
    <link rel="stylesheet" href="<?= url_base('publico/css/candelaria.css') ?>">

    <script>
        window.CANDELARIA_BASE_URL = "<?= url_base() ?>";
    </script>
</head>
<body class="light">

<?= $contenido ?? '' ?>

<!-- Scripts Fundacionales Alina -->
<script src="<?= url_activo('alina/js/jquery-3.6.3.min.js') ?>"></script>
<script src="<?= url_activo('alina/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url_activo('alina/vendor/sweetalert/sweetalert.js') ?>"></script>

<!-- Scripts Propios CandelariaAPP -->
<script src="<?= url_base('publico/js/candelaria.js') ?>"></script>
<?php if (!empty($scriptAdicional)): ?>
    <script src="<?= $scriptAdicional ?>"></script>
<?php endif; ?>
</body>
</html>
