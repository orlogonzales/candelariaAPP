<!-- Encabezado de la Sección de Ediciones Candelaria -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-calendar-check text-primary me-2"></i> Ediciones Candelaria
        </h3>
        <p class="text-secondary mb-0">
            Administración del ciclo de vida de festividades, estados de producción y contexto operativo activo.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalCrear">
                <i class="fa-solid fa-plus me-2"></i> Nueva Edición
            </button>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen / KPIs de Ediciones -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Ediciones</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalEdiciones">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Edición Institucional</span>
                    <h5 class="f-w-700 text-success mb-0 mt-1 text-truncate" style="max-width: 170px;" id="kpiEdicionActual">-</h5>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-star"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Contexto Esta Pestaña</span>
                    <h5 class="f-w-700 text-info mb-0 mt-1 text-truncate" style="max-width: 170px;" id="kpiEdicionPestana">-</h5>
                </div>
                <div class="w-45 h-45 d-flex-center bg-info-subtle text-info rounded-circle f-s-20">
                    <i class="fa-solid fa-window-maximize"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Gobernanza Ciclo</span>
                    <h5 class="f-w-600 text-dark mb-0 mt-2">Máquina de Estados</h5>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-diagram-project"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor Principal de la Tabla con Soporte Skeleton -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-body p-3">
        <div id="contenedorTablaEdiciones" class="app-datatable-default overflow-auto app-scroll">
            <!-- Tabla inyectada dinámicamente por ediciones.js -->
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 1: CREAR NUEVA EDICIÓN
============================================================================== -->
<div class="modal fade" id="modalCrearEdicion" tabindex="-1" aria-labelledby="modalCrearEdicionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCrearEdicionLabel">
                    <i class="fa-solid fa-calendar-plus me-2"></i> Registrar Nueva Edición Candelaria
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCrearEdicion" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="crearCodigo" class="form-label f-w-600 f-s-13">Código Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="crearCodigo" name="codigo" placeholder="Ej. candelaria-2027" required maxlength="50" style="text-transform: lowercase;">
                            <div class="form-text f-s-11">Identificador slug canónico en minúsculas (ej: candelaria-2027).</div>
                        </div>
                        <div class="col-md-6">
                            <label for="crearAnio" class="form-label f-w-600 f-s-13">Año de la Festividad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="crearAnio" name="anio" placeholder="Ej. 2027" required min="2000" max="2100">
                            <div class="form-text f-s-11">Unicidad estricta por organización.</div>
                        </div>
                        <div class="col-12">
                            <label for="crearNombre" class="form-label f-w-600 f-s-13">Nombre Oficial de la Edición <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="crearNombre" name="nombre" placeholder="Ej. Festividad Virgen de la Candelaria 2027" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label for="crearFechaInicio" class="form-label f-w-600 f-s-13">Fecha de Inicio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control candelaria-flatpickr bg-white" id="crearFechaInicio" name="fecha_inicio" placeholder="AAAA-MM-DD" required>
                        </div>
                        <div class="col-md-6">
                            <label for="crearFechaFin" class="form-label f-w-600 f-s-13">Fecha de Culminación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control candelaria-flatpickr bg-white" id="crearFechaFin" name="fecha_fin" placeholder="AAAA-MM-DD" required>
                        </div>
                        <div class="col-12">
                            <label for="crearDescripcion" class="form-label f-w-600 f-s-13">Descripción / Notas Operativas</label>
                            <textarea class="form-control" id="crearDescripcion" name="descripcion" rows="3" placeholder="Detalles de organización, cobertura audiovisual o metas de la festividad..."></textarea>
                        </div>
                    </div>
                    <div class="alert alert-info d-flex align-items-center gap-2 mt-3 mb-0 f-s-12">
                        <i class="fa-solid fa-circle-info f-s-16 flex-shrink-0"></i>
                        <span>La edición se inicializará automáticamente en estado <strong>PREOPERACION</strong> y con <code>es_actual = 0</code>.</span>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCrear">
                        <i class="fa-solid fa-save me-1"></i> Guardar Edición
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 2: EDITAR EDICIÓN (CONCURRENCIA OPTIMISTA + SKELETON)
============================================================================== -->
<div class="modal fade" id="modalEditarEdicion" tabindex="-1" aria-labelledby="modalEditarEdicionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalEditarEdicionLabel">
                    <i class="fa-solid fa-pen-to-square me-2"></i> Modificar Datos de la Edición
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarEdicion" novalidate>
                <input type="hidden" id="editarEdicionId" name="id">
                <input type="hidden" id="editarActualizadoEnEsperado" name="actualizado_en_esperado">

                <div class="modal-body p-4">
                    <!-- Skeleton Loader mientras se cargan los datos del servidor -->
                    <div id="skeletonEditarEdicion" class="d-none">
                        <div class="row g-3">
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-input"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-input"></div></div>
                            <div class="col-12"><div class="skeleton-shimmer skeleton-input"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-input"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-input"></div></div>
                            <div class="col-12"><div class="skeleton-shimmer skeleton-line lg w-100"></div></div>
                        </div>
                    </div>

                    <div id="panelFormEditar">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label f-w-600 f-s-13">Código Slug</label>
                                <input type="text" class="form-control bg-light" id="editarCodigo" readonly disabled>
                                <div class="form-text f-s-11">El código de edición es inmutable tras su creación.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label f-w-600 f-s-13">Año</label>
                                <input type="text" class="form-control bg-light" id="editarAnio" readonly disabled>
                                <div class="form-text f-s-11">El año de festividad es inmutable.</div>
                            </div>
                            <div class="col-12">
                                <label for="editarNombre" class="form-label f-w-600 f-s-13">Nombre Oficial <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editarNombre" name="nombre" required maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label for="editarFechaInicio" class="form-label f-w-600 f-s-13">Fecha de Inicio <span class="text-danger">*</span></label>
                                <input type="text" class="form-control candelaria-flatpickr bg-white" id="editarFechaInicio" name="fecha_inicio" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editarFechaFin" class="form-label f-w-600 f-s-13">Fecha de Culminación <span class="text-danger">*</span></label>
                                <input type="text" class="form-control candelaria-flatpickr bg-white" id="editarFechaFin" name="fecha_fin" required>
                            </div>
                            <div class="col-12">
                                <label for="editarDescripcion" class="form-label f-w-600 f-s-13">Descripción / Notas Operativas</label>
                                <textarea class="form-control" id="editarDescripcion" name="descripcion" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarEditar">
                        <i class="fa-solid fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 3: CAMBIAR ESTADO DE LA EDICIÓN (GOBERNANZA DE CICLO DE VIDA)
============================================================================== -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1" aria-labelledby="modalCambiarEstadoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-dark text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCambiarEstadoLabel">
                    <i class="fa-solid fa-diagram-project me-2"></i> Transición de Estado de Edición
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCambiarEstado" novalidate>
                <input type="hidden" id="estadoEdicionId" name="id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Edición Seleccionada</label>
                        <div class="p-2 bg-light rounded border f-w-600 text-dark" id="estadoEdicionNombre">-</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Estado Actual</label>
                        <div id="estadoEdicionActualBadge">-</div>
                    </div>
                    <div class="mb-3">
                        <label for="selectNuevoEstado" class="form-label f-w-600 f-s-13">Nuevo Estado Operativo <span class="text-danger">*</span></label>
                        <select class="form-select" id="selectNuevoEstado" name="nuevo_estado" required>
                            <!-- Opciones inyectadas dinámicamente según la máquina de estados -->
                        </select>
                    </div>
                    <div id="panelMotivoRetroceso" class="mb-3 d-none">
                        <label for="inputMotivoRetroceso" class="form-label f-w-600 f-s-13 text-danger">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Motivo Obligatorio del Retroceso <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control border-danger" id="inputMotivoRetroceso" name="motivo_retroceso" rows="3" placeholder="Explique detalladamente la justificación técnica u operativa del retroceso excepcional..."></textarea>
                        <div class="form-text f-s-11 text-danger">Este retroceso quedará registrado con auditoría inmutable vinculada al operador.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark" id="btnEjecutarCambioEstado">
                        <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Aplicar Transición
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
