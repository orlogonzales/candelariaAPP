        <!-- Pie de Página (Footer Section) -->
        <footer class="footer-container py-3 px-4 border-top">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-8 col-12">
                        <p class="footer-text f-w-500 mb-0 text-muted f-s-13">
                            &copy; <?= date('Y') ?> <strong>CandelariaAPP</strong> &bull; Sistema Oficial de Gestión Audiovisual &bull; Versión 1.0.0
                        </p>
                    </div>
                    <div class="col-md-4 col-12 text-md-end text-start mt-2 mt-md-0">
                        <span class="f-s-12 text-muted">
                            Desarrollado para <strong class="text-dark">O.G. Estudio Creativo</strong>
                        </span>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    <!-- Fin app-content -->

    <!-- Botón Volver Arriba (Tap to Top) -->
    <div class="go-top">
        <span class="progress-value">
            <i class="fa-solid fa-chevron-up"></i>
        </span>
    </div>

    <!-- Canvas de Notificaciones Operativas (Offcanvas) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="notificationCanvas" aria-labelledby="notificationCanvasLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title f-w-600" id="notificationCanvasLabel">
                <i class="fa-solid fa-bell me-2 text-danger"></i> Centro de Avisos
            </h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body p-3">
            <div class="p-3 bg-light rounded mb-2 border">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-success">Sistema</span>
                    <small class="text-muted">Fase 0 Foundation</small>
                </div>
                <p class="mb-0 f-s-13 text-dark">Estructura base, arquitectura API-first y plantilla Alina adaptadas exitosamente.</p>
            </div>
            <div class="text-center py-4 text-muted">
                <i class="fa-solid fa-check-double f-s-32 mb-2 text-secondary"></i>
                <p class="f-s-13 mb-0">No hay incidencias operativas pendientes.</p>
            </div>
        </div>
    </div>
