<!-- Encabezado de la Sección de Orígenes Comerciales -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-arrow-up-right-from-square text-primary me-2"></i> Orígenes Comerciales
        </h3>
        <p class="text-secondary mb-0">
            Catálogo relacional e institucional de fuentes de captación y canales comerciales por organización.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalCrear">
                <i class="fa-solid fa-plus me-2"></i> Nuevo Origen
            </button>
        </div>
    </div>
</div>

<!-- Contenedor Principal de la Tabla de Orígenes Comerciales -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-body p-3">
        <div id="contenedorTablaOrigenes" class="app-datatable-default overflow-auto app-scroll">
            <!-- Tabla inyectada dinámicamente por crm_origenes.js -->
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 1: REGISTRAR NUEVO ORIGEN COMERCIAL
============================================================================== -->
<div class="modal fade" id="modalCrearOrigen" tabindex="-1" aria-labelledby="modalCrearOrigenLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCrearOrigenLabel">
                    <i class="fa-solid fa-plus me-2"></i> Nuevo Origen Comercial
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCrearOrigen" novalidate>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="crearCodigo" class="form-label f-w-600 f-s-13">Código Canónico <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="crearCodigo" name="codigo" placeholder="Ej. TIKTOK_ADS" required maxlength="50" style="text-transform: uppercase;">
                        <div class="form-text f-s-11">Identificador en mayúsculas. Será inmutable tras la creación.</div>
                    </div>
                    <div class="mb-3">
                        <label for="crearNombre" class="form-label f-w-600 f-s-13">Nombre Descriptivo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="crearNombre" name="nombre" placeholder="Ej. Campañas Publicitarias en TikTok" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label for="crearDescripcion" class="form-label f-w-600 f-s-13">Descripción</label>
                        <textarea class="form-control" id="crearDescripcion" name="descripcion" rows="2" placeholder="Detalle sobre este canal de prospección..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="crearOrden" class="form-label f-w-600 f-s-13">Orden de Visualización</label>
                        <input type="number" class="form-control" id="crearOrden" name="orden" value="0" min="0" max="999">
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCrearOrigen">
                        <i class="fa-solid fa-save me-1"></i> Guardar Origen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 2: EDITAR ORIGEN COMERCIAL (CÓDIGO INMUTABLE)
============================================================================== -->
<div class="modal fade" id="modalEditarOrigen" tabindex="-1" aria-labelledby="modalEditarOrigenLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalEditarOrigenLabel">
                    <i class="fa-solid fa-pen-to-square me-2"></i> Editar Origen Comercial
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarOrigen" novalidate>
                <input type="hidden" id="editarOrigenId" name="id">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Código Canónico</label>
                        <input type="text" class="form-control bg-light" id="editarCodigoDisplay" readonly disabled>
                        <div class="form-text f-s-11">El código es inmutable para proteger el historial de oportunidades.</div>
                    </div>
                    <div class="mb-3">
                        <label for="editarNombre" class="form-label f-w-600 f-s-13">Nombre Descriptivo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editarNombre" name="nombre" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label for="editarDescripcion" class="form-label f-w-600 f-s-13">Descripción</label>
                        <textarea class="form-control" id="editarDescripcion" name="descripcion" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editarOrden" class="form-label f-w-600 f-s-13">Orden de Visualización</label>
                        <input type="number" class="form-control" id="editarOrden" name="orden" min="0" max="999">
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarEditarOrigen">
                        <i class="fa-solid fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
