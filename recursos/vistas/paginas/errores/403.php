<div class="row justify-content-center text-center py-5">
    <div class="col-lg-6 col-md-8">
        <div class="card border-0 shadow-sm p-4">
            <div class="card-body">
                <div class="text-danger mb-4 f-s-64">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h1 class="f-w-700 text-danger mb-2">403</h1>
                <h3 class="f-w-600 mb-3">Acceso Denegado</h3>
                <p class="text-secondary mb-4">
                    <?= escapar_html($mensaje ?? 'No cuenta con los privilegios necesarios para acceder a este recurso.') ?>
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="<?= url_base() ?>" class="btn bg-gradient-primary text-white">
                        <i class="fa-solid fa-house me-2"></i> Volver al Inicio
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
