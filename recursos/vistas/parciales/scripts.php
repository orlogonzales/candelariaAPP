</div>
<!-- Fin app-wrapper -->

<!-- Scripts Fundacionales Reutilizados de Alina -->
<script src="<?= url_activo('alina/js/jquery-3.6.3.min.js') ?>"></script>
<script src="<?= url_activo('alina/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url_activo('alina/vendor/simplebar/simplebar.js') ?>"></script>
<script src="<?= url_activo('alina/js/script.js') ?>"></script>

<!-- SweetAlert2 (Alina Vendor) -->
<script src="<?= url_activo('alina/vendor/sweetalert/sweetalert.js') ?>"></script>

<!-- DataTables (Alina Vendor) -->
<script src="<?= url_activo('alina/vendor/datatable/jquery.dataTables.min.js') ?>"></script>
<script src="<?= url_activo('alina/vendor/datatable/dataTables.responsive.min.js') ?>"></script>

<!-- Skeleton Loaders Centralizado CandelariaAPP -->
<script src="<?= url_base('publico/js/skeleton.js') ?>"></script>

<!-- Scripts Propios Modernos CandelariaAPP (JavaScript Moderno, Fetch API, Tooltips del Sidebar, CandelariaUI) -->
<script src="<?= url_base('publico/js/candelaria.js') ?>"></script>
<?php if (!empty($scriptAdicional)): ?>
    <script src="<?= $scriptAdicional ?>"></script>
<?php endif; ?>
</body>
</html>
