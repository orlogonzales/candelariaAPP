<!-- ==============================================================================
     VISTA OFICIAL: CATÁLOGO DE PAQUETES COMERCIALES (paquetes.php) - FASE 2.3C
     Administración de Paquetes y Composición Soberana de Ítems
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-boxes-packing text-primary me-2"></i> Paquetes Comerciales
        </h3>
        <p class="text-secondary mb-0">
            Ofertas empaquetadas compuestas por múltiples productos y servicios para comercialización integrada.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <?php if (!empty($permisos['paquetesGestionar'])): ?>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalCrear">
                <i class="fa-solid fa-plus me-2"></i> Nuevo Paquete
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen / KPIs -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Paquetes</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalPaquetes">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-boxes-packing"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Activos</span>
                    <h3 class="f-w-700 text-success mb-0 mt-1" id="kpiActivos">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Inactivos</span>
                    <h3 class="f-w-700 text-secondary mb-0 mt-1" id="kpiInactivos">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-secondary-subtle text-secondary rounded-circle f-s-20">
                    <i class="fa-solid fa-circle-pause"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Con Composición</span>
                    <h3 class="f-w-700 text-info mb-0 mt-1" id="kpiConComposicion">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-info-subtle text-info rounded-circle f-s-20">
                    <i class="fa-solid fa-list-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros de Búsqueda -->
<div class="card border-0 shadow-sm b-r-12 mb-3">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-6">
                <label for="filtroBusqueda" class="form-label f-s-12 text-muted mb-1">Buscar por Código o Nombre</label>
                <div class="position-relative">
                    <input type="search" class="form-control form-control-sm pe-4" id="filtroBusqueda" placeholder="Ej. Paquete Completo, VIP, Folclore...">
                    <i class="fa-solid fa-magnifying-glass f-s-12 text-muted position-absolute end-0 top-50 translate-middle-y me-3"></i>
                </div>
            </div>
            <div class="col-md-3">
                <label for="filtroEstado" class="form-label f-s-12 text-muted mb-1">Estado</label>
                <select class="form-select form-select-sm" id="filtroEstado">
                    <option value="">Todos</option>
                    <option value="1" selected>Activos</option>
                    <option value="0">Inactivos</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Tabla Principal de Paquetes -->
<div class="card border-0 shadow-sm b-r-12">
    <div class="card-body p-0">
        <div class="table-responsive p-3">
            <table class="table table-hover align-middle w-100" id="tablaPaquetes">
                <thead class="table-light">
                    <tr>
                        <th class="f-s-12 text-muted text-uppercase">Código</th>
                        <th class="f-s-12 text-muted text-uppercase">Nombre del Paquete</th>
                        <th class="f-s-12 text-muted text-uppercase">Descripción</th>
                        <th class="f-s-12 text-muted text-uppercase text-center">Ítems Incluidos</th>
                        <th class="f-s-12 text-muted text-uppercase text-center">Estado</th>
                        <th class="f-s-12 text-muted text-uppercase text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyPaquetes">
                    <!-- Filas renderizadas dinámicamente vía DataTables / Fetch -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: CREAR / EDITAR PAQUETE COMERCIAL
============================================================================== -->
<div class="modal fade" id="modalPaquete" tabindex="-1" aria-labelledby="modalPaqueteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalPaqueteLabel">
                    <i class="fa-solid fa-boxes-packing me-2"></i> Nuevo Paquete Comercial
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formPaquete" novalidate>
                <input type="hidden" id="paqueteId" name="id" value="">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="paqueteCodigo" class="form-label f-w-600 f-s-13">Código Único <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="paqueteCodigo" name="codigo" placeholder="Ej. PAQ-VIP-CANDELARIA" required maxlength="50">
                        </div>
                        <div class="col-12">
                            <label for="paqueteNombre" class="form-label f-w-600 f-s-13">Nombre del Paquete <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="paqueteNombre" name="nombre" placeholder="Ej. Cobertura Integral Morenada VIP" required maxlength="150">
                        </div>
                        <div class="col-12">
                            <label for="paqueteDescripcion" class="form-label f-w-600 f-s-13">Descripción</label>
                            <textarea class="form-control" id="paqueteDescripcion" name="descripcion" rows="3" placeholder="Detalle comercial del paquete..."></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch fs-6">
                                <input class="form-check-input" type="checkbox" id="paqueteActivo" name="activo" checked>
                                <label class="form-check-label f-s-14" for="paqueteActivo">Paquete Comercial Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarPaquete">
                        <i class="fa-solid fa-save me-1"></i> Guardar Paquete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: GESTIÓN DE COMPOSICIÓN DEL PAQUETE
============================================================================== -->
<div class="modal fade" id="modalComposicion" tabindex="-1" aria-labelledby="modalComposicionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-dark text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalComposicionLabel">
                    <i class="fa-solid fa-layer-group me-2 text-primary"></i> Composición de Ítems del Paquete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Info del Paquete Activo -->
                <div class="alert alert-light border d-flex align-items-center justify-content-between p-3 mb-4 b-r-12">
                    <div>
                        <span class="f-s-12 text-muted text-uppercase d-block">Paquete Seleccionado:</span>
                        <h5 class="f-w-700 text-dark mb-0" id="composicionPaqueteTitulo">-</h5>
                    </div>
                    <span class="badge bg-primary fs-6 px-3 py-2" id="composicionPaqueteCodigo">-</span>
                </div>

                <!-- Formulario Agregar Ítem -->
                <?php if (!empty($permisos['paquetesGestionar'])): ?>
                <div class="card bg-light border-0 b-r-12 p-3 mb-4">
                    <h6 class="f-w-700 text-dark mb-3">
                        <i class="fa-solid fa-plus-circle me-1 text-primary"></i> Agregar Ítem Comercial Incluido
                    </h6>
                    <form id="formAgregarItemComp" novalidate>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label for="compSelectItemId" class="form-label f-s-12 text-muted mb-1">Buscar Producto / Servicio <span class="text-danger">*</span></label>
                                <select class="form-select" id="compSelectItemId" style="width: 100%;">
                                    <option value="">Buscar por nombre o código...</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="compCantidad" class="form-label f-s-12 text-muted mb-1">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="compCantidad" min="1" step="1" value="1" required>
                            </div>
                            <div class="col-md-3">
                                <label for="compNota" class="form-label f-s-12 text-muted mb-1">Nota Referencial</label>
                                <input type="text" class="form-control" id="compNota" placeholder="Ej. Incluye edición digital">
                            </div>
                            <div class="col-md-2 text-end">
                                <button type="button" class="btn btn-primary w-100" id="btnAgregarFilaComp">
                                    <i class="fa-solid fa-plus me-1"></i> Agregar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Tabla de Composición Actual -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="tablaComposicionItems">
                        <thead class="table-light">
                            <tr>
                                <th class="f-s-12 text-muted text-center" style="width: 60px;">Orden</th>
                                <th class="f-s-12 text-muted">Código</th>
                                <th class="f-s-12 text-muted">Ítem Comercial</th>
                                <th class="f-s-12 text-muted">Tipo</th>
                                <th class="f-s-12 text-muted">Unidad</th>
                                <th class="f-s-12 text-muted text-center">Cantidad</th>
                                <th class="f-s-12 text-muted">Nota</th>
                                <th class="f-s-12 text-muted text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyComposicion">
                            <!-- Filas dinámicas de la composición -->
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-secondary border-0 b-r-12 mt-3 mb-0 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-info-circle text-primary f-s-16"></i>
                    <span class="f-s-12">
                        <strong>Regla Soberana:</strong> Todo ítem registrado en la composición se considera incluido con su cantidad estipulada. El Catálogo Comercial no contempla precios adicionales ni opcionalidad interna.
                    </span>
                </div>
            </div>
            <div class="modal-footer bg-light py-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <?php if (!empty($permisos['paquetesGestionar'])): ?>
                <button type="button" class="btn btn-success" id="btnGuardarComposicion">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Composición
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
