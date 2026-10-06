<!-- ==============================================================================
     VISTA OFICIAL: CATÁLOGO DE ÍTEMS COMERCIALES (items.php) - FASE 2.3C
     Administración de Productos y Servicios y Modal Contextual de Categorías
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-box-open text-primary me-2"></i> Catálogo de Ítems Comerciales
        </h3>
        <p class="text-secondary mb-0">
            Padrón maestro de productos tangibles y servicios intangibles disponibles para conformar paquetes y ofertas por edición.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <?php if (!empty($permisos['categoriasGestionar'])): ?>
            <button type="button" class="btn btn-outline-primary btn-md shadow-sm" id="btnAbrirModalCategorias">
                <i class="fa-solid fa-tags me-1"></i> Categorías
            </button>
            <?php endif; ?>
            <?php if (!empty($permisos['itemsGestionar'])): ?>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalCrear">
                <i class="fa-solid fa-plus me-2"></i> Nuevo Ítem
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
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Ítems</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalItems">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Productos</span>
                    <h3 class="f-w-700 text-info mb-0 mt-1" id="kpiTotalProductos">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-info-subtle text-info rounded-circle f-s-20">
                    <i class="fa-solid fa-cube"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Servicios</span>
                    <h3 class="f-w-700 text-success mb-0 mt-1" id="kpiTotalServicios">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Categorías</span>
                    <h3 class="f-w-700 text-warning mb-0 mt-1" id="kpiTotalCategorias"><?= count($categorias ?? []) ?></h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-folder-tree"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros de Búsqueda -->
<div class="card border-0 shadow-sm b-r-12 mb-3">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <label for="filtroBusqueda" class="form-label f-s-12 text-muted mb-1">Buscar por Código o Nombre</label>
                <div class="position-relative">
                    <input type="search" class="form-control form-control-sm pe-4" id="filtroBusqueda" placeholder="Ej. Álbum, Traje, Fotografía...">
                    <i class="fa-solid fa-magnifying-glass f-s-12 text-muted position-absolute end-0 top-50 translate-middle-y me-3"></i>
                </div>
            </div>
            <div class="col-md-3">
                <label for="filtroTipo" class="form-label f-s-12 text-muted mb-1">Tipo de Ítem</label>
                <select class="form-select form-select-sm" id="filtroTipo">
                    <option value="">Todos los Tipos</option>
                    <option value="PRODUCTO">PRODUCTO (Tangible)</option>
                    <option value="SERVICIO">SERVICIO (Intangible)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filtroCategoria" class="form-label f-s-12 text-muted mb-1">Categoría</label>
                <select class="form-select form-select-sm" id="filtroCategoria">
                    <option value="">Todas las Categorías</option>
                    <?php if (!empty($categorias)): ?>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= (int) $cat->id ?>"><?= escapar_html($cat->nombre) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
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

<!-- Tabla Principal de Ítems Comerciales -->
<div class="card border-0 shadow-sm b-r-12">
    <div class="card-body p-0">
        <div class="table-responsive p-3" id="contenedorTablaItems">
            <table class="table table-hover align-middle w-100" id="tablaItems">
                <thead class="table-light">
                    <tr>
                        <th class="f-s-12 text-muted text-uppercase">Código</th>
                        <th class="f-s-12 text-muted text-uppercase">Nombre Comercial</th>
                        <th class="f-s-12 text-muted text-uppercase">Tipo</th>
                        <th class="f-s-12 text-muted text-uppercase">Categoría</th>
                        <th class="f-s-12 text-muted text-uppercase">Unidad</th>
                        <th class="f-s-12 text-muted text-uppercase text-center">Estado</th>
                        <th class="f-s-12 text-muted text-uppercase text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyItems">
                    <!-- Filas renderizadas dinámicamente vía DataTables / Fetch -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: CREAR / EDITAR ÍTEM COMERCIAL
============================================================================== -->
<div class="modal fade" id="modalItem" tabindex="-1" aria-labelledby="modalItemLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalItemLabel">
                    <i class="fa-solid fa-box-open me-2"></i> Nuevo Ítem Comercial
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formItem" novalidate>
                <input type="hidden" id="itemId" name="id" value="">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="itemTipo" class="form-label f-w-600 f-s-13">Tipo de Ítem <span class="text-danger">*</span></label>
                            <select class="form-select" id="itemTipo" name="tipo" required>
                                <option value="PRODUCTO">PRODUCTO (Bien tangible)</option>
                                <option value="SERVICIO">SERVICIO (Prestación intangible)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="itemCategoriaId" class="form-label f-w-600 f-s-13">Categoría Comercial <span class="text-danger">*</span></label>
                            <select class="form-select" id="itemCategoriaId" name="categoria_id" required>
                                <option value="">Seleccione una categoría...</option>
                                <?php if (!empty($categorias)): ?>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= (int) $cat->id ?>"><?= escapar_html($cat->nombre) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="itemCodigo" class="form-label f-w-600 f-s-13">Código Único <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="itemCodigo" name="codigo" placeholder="Ej. SERV-FOTO-01" required maxlength="50">
                        </div>
                        <div class="col-md-8">
                            <label for="itemNombre" class="form-label f-w-600 f-s-13">Nombre Comercial <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="itemNombre" name="nombre" placeholder="Ej. Cobertura Fotográfica en Traje" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label for="itemUnidadMedida" class="form-label f-w-600 f-s-13">Unidad de Medida <span class="text-danger">*</span></label>
                            <select class="form-select" id="itemUnidadMedida" name="unidad_medida" required>
                                <?php if (!empty($unidadesMedida)): ?>
                                    <?php foreach ($unidadesMedida as $u): ?>
                                        <option value="<?= escapar_html($u['codigo']) ?>" <?= $u['codigo'] === 'SERVICIO' ? 'selected' : '' ?>>
                                            <?= escapar_html($u['etiqueta']) ?> (<?= escapar_html($u['semantica']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="UNIDAD">UNIDAD</option>
                                    <option value="PERSONA">PERSONA</option>
                                    <option value="NOCHE">NOCHE</option>
                                    <option value="HABITACION">HABITACION</option>
                                    <option value="TICKET">TICKET</option>
                                    <option value="SERVICIO" selected>SERVICIO</option>
                                    <option value="TRAMO">TRAMO</option>
                                    <option value="DIA">DIA</option>
                                    <option value="HORA">HORA</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-center pt-4">
                            <div class="form-check form-switch fs-6">
                                <input class="form-check-input" type="checkbox" id="itemActivo" name="activo" checked>
                                <label class="form-check-label f-s-14" for="itemActivo">Ítem Comercial Activo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="itemDescripcion" class="form-label f-w-600 f-s-13">Descripción Detallada</label>
                            <textarea class="form-control" id="itemDescripcion" name="descripcion" rows="3" placeholder="Detalle referencial del producto o servicio..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarItem">
                        <i class="fa-solid fa-save me-1"></i> Guardar Ítem
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: GESTIÓN DE CATEGORÍAS (Contextual)
============================================================================== -->
<div class="modal fade" id="modalCategorias" tabindex="-1" aria-labelledby="modalCategoriasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-secondary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCategoriasLabel">
                    <i class="fa-solid fa-tags me-2"></i> Gestión de Categorías del Catálogo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Formulario Categoría (Crear/Editar) -->
                <div class="card bg-light border-0 b-r-12 p-3 mb-4">
                    <h6 class="f-w-700 text-dark mb-3" id="tituloFormCategoria">
                        <i class="fa-solid fa-plus-circle me-1 text-primary"></i> Nueva Categoría
                    </h6>
                    <form id="formCategoria" novalidate>
                        <input type="hidden" id="categoriaId" name="id" value="">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label for="catCodigo" class="form-label f-s-12 text-muted mb-1">Código <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm text-uppercase" id="catCodigo" name="codigo" placeholder="Ej. FOTO" required maxlength="50">
                            </div>
                            <div class="col-md-5">
                                <label for="catNombre" class="form-label f-s-12 text-muted mb-1">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="catNombre" name="nombre" placeholder="Ej. Fotografía y Video" required maxlength="100">
                            </div>
                            <div class="col-md-4">
                                <label for="catDescripcion" class="form-label f-s-12 text-muted mb-1">Descripción</label>
                                <input type="text" class="form-control form-control-sm" id="catDescripcion" name="descripcion" placeholder="Opcional">
                            </div>
                            <div class="col-12 text-end mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-1 d-none" id="btnCancelarEdicionCat">Cancelar</button>
                                <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarCategoria">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Categoría
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Lista de Categorías -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle w-100" id="tablaCategorias">
                        <thead class="table-light">
                            <tr>
                                <th class="f-s-12 text-muted">Código</th>
                                <th class="f-s-12 text-muted">Nombre</th>
                                <th class="f-s-12 text-muted">Descripción</th>
                                <th class="f-s-12 text-muted text-center">Estado</th>
                                <th class="f-s-12 text-muted text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyCategorias">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
