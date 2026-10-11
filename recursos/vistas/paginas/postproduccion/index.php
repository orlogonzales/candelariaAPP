<!-- ==============================================================================
     VISTA OFICIAL: POSTPRODUCCIÓN Y GALERÍAS MULTIMEDIA (postproduccion/index.php) - FASE 2.11
     Gestión de Proyectos Audiovisuales, Álbumes, Subida por Streams y Entregas Parciales
     Alina Admin Theme + CandelariaAPP
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-photo-film text-primary me-2"></i> Postproducción y Galerías Multimedia
        </h3>
        <p class="text-secondary mb-0">
            Control de cobertura audiovisual, producción fotográfica, streaming de video y entregas aprobables por clientes.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarProyectos" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <?php if (!empty($permisos['crear_proyecto'])): ?>
            <button type="button" class="btn btn-primary btn-md shadow-sm" id="btnNuevoProyecto">
                <i class="fa-solid fa-plus me-1"></i> Nuevo Proyecto
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen / KPIs -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12 h-100">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Proyectos</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalProyectos">0</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12 h-100">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">En Postproducción</span>
                    <h3 class="f-w-700 mb-0 mt-1 text-warning" id="kpiEnEdicion">0</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-sliders"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12 h-100">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Entregas Publicadas</span>
                    <h3 class="f-w-700 mb-0 mt-1 text-info" id="kpiEntregas">0</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-info-subtle text-info rounded-circle f-s-20">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12 h-100">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Almacenamiento Total</span>
                    <h3 class="f-w-700 mb-0 mt-1 text-success" id="kpiAlmacenamiento">0 MB</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-hard-drive"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros y Listado de Proyectos -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-body p-3">
        <div class="row g-2 mb-3 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control border-start-0" id="filtroBusqueda" placeholder="Buscar por título, cliente o edición...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="filtroEstado">
                    <option value="">Todos los Estados</option>
                    <option value="PLANIFICADO">Planificado</option>
                    <option value="EN_EDICION">En Edición</option>
                    <option value="EN_REVISION">En Revisión</option>
                    <option value="ENTREGADO_PARCIAL">Entregado Parcial</option>
                    <option value="ENTREGADO_TOTAL">Entregado Total</option>
                    <option value="CERRADO">Cerrado</option>
                </select>
            </div>
            <div class="col-md-4 text-md-end text-muted small" id="contadorResultados">
                Cargando proyectos...
            </div>
        </div>

        <!-- Grid de Proyectos Audiovisuales -->
        <div class="row g-3" id="contenedorProyectos">
            <!-- Renderizado dinámico vía JS -->
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 1: NUEVO PROYECTO AUDIOVISUAL
============================================================================== -->
<div class="modal fade" id="modalNuevoProyecto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-folder-plus text-primary me-2"></i> Nuevo Proyecto de Postproducción
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formNuevoProyecto">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Edición Candelaria *</label>
                            <select class="form-select" name="edicion_id" id="nuevoEdicionId" required>
                                <option value="">Seleccione una festividad...</option>
                                <?php foreach ($ediciones ?? [] as $ed): ?>
                                    <option value="<?= $ed['id'] ?>"><?= escapar_html($ed['nombre']) ?> (<?= $ed['anio'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Cliente Titular *</label>
                            <select class="form-select" name="cliente_id" id="nuevoClienteId" required>
                                <option value="">Seleccione al cliente...</option>
                                <?php foreach ($clientes ?? [] as $cl): ?>
                                    <option value="<?= $cl['id'] ?>"><?= escapar_html($cl['apellidos'] . ' ' . $cl['nombres']) ?> (<?= escapar_html($cl['numero_documento']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Título del Proyecto *</label>
                            <input type="text" class="form-control" name="titulo" placeholder="Ej: Cobertura Danza Diablada Morenada Bellavista" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Descripción / Alcance</label>
                            <textarea class="form-control" name="descripcion" rows="2" placeholder="Detalles de la cobertura, tomas solicitadas, formato final..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Fotógrafo Asignado</label>
                            <select class="form-select" name="fotografo_id">
                                <option value="">Sin asignar</option>
                                <?php foreach ($usuarios ?? [] as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= escapar_html($u['nombre_completo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Editor de Video/Foto</label>
                            <select class="form-select" name="editor_id">
                                <option value="">Sin asignar</option>
                                <?php foreach ($usuarios ?? [] as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= escapar_html($u['nombre_completo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Fecha Límite Entrega</label>
                            <input type="date" class="form-control" name="fecha_limite_entrega">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold" id="btnGuardarProyecto">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Proyecto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 2: DETALLE DEL PROYECTO, GALERÍAS, ARCHIVOS Y ENTREGAS
============================================================================== -->
<div class="modal fade" id="modalDetalleProyecto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="detTituloProyecto">Detalle del Proyecto</h5>
                    <span class="text-white-50 small" id="detSubtituloProyecto">-</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Nav tabs -->
                <ul class="nav nav-pills mb-4" id="pillsTabDetalle" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" id="tab-galerias-btn" data-bs-toggle="pill" data-bs-target="#tab-galerias" type="button" role="tab">
                            <i class="fa-solid fa-images me-1"></i> Álbumes y Archivos (<span id="detTotalArchivos">0</span>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="tab-entregas-btn" data-bs-toggle="pill" data-bs-target="#tab-entregas" type="button" role="tab">
                            <i class="fa-solid fa-box-archive me-1"></i> Entregas y Revisiones (<span id="detTotalEntregas">0</span>)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="pillsTabContent">
                    <!-- Tab 1: Álbumes y Archivos -->
                    <div class="tab-pane fade show active" id="tab-galerias" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">Galerías organizadas por etapa audiovisual</span>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnCrearGaleria">
                                    <i class="fa-solid fa-folder-plus me-1"></i> Nueva Galería
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" id="btnAbrirSubida">
                                    <i class="fa-solid fa-cloud-arrow-up me-1"></i> Subir Archivo
                                </button>
                            </div>
                        </div>
                        <div id="contenedorGaleriasArchivos">
                            <!-- Renderizado de galerías y archivos -->
                        </div>
                    </div>

                    <!-- Tab 2: Entregas al Cliente -->
                    <div class="tab-pane fade" id="tab-entregas" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">Paquetes de entrega puestos a disposición del cliente</span>
                            <button type="button" class="btn btn-success btn-sm fw-semibold" id="btnCrearEntrega">
                                <i class="fa-solid fa-paper-plane me-1"></i> Publicar Nueva Entrega
                            </button>
                        </div>
                        <div id="contenedorEntregasProyecto">
                            <!-- Renderizado de entregas -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 3: CREAR GALERÍA
============================================================================== -->
<div class="modal fade" id="modalCrearGaleria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold">Nueva Galería / Álbum</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCrearGaleria">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Título de la Galería *</label>
                        <input type="text" class="form-control" name="titulo" placeholder="Ej: Fotografías en Graderías Oficiales" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tipo de Galería</label>
                        <select class="form-select" name="tipo_galeria">
                            <option value="FOTOGRAFIA">Fotografía (RAW / JPEG)</option>
                            <option value="VIDEO">Video (MP4 / 4K / Reel)</option>
                            <option value="MIXTO">Mixto (Fotos y Videos)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="2" placeholder="Opcional..."></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="es_publica_cliente" id="galeriaEsPublica" checked>
                        <label class="form-check-label small" for="galeriaEsPublica">Visible en el Espacio del Cliente</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold">Crear Galería</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 4: SUBIR ARCHIVO MULTIMEDIA POR STREAMS
============================================================================== -->
<div class="modal fade" id="modalSubirArchivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-cloud-arrow-up text-primary me-2"></i> Subir Archivo Multimedia
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formSubirArchivo">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Galería Destino *</label>
                        <select class="form-select" id="subirGaleriaId" required>
                            <!-- Llenado dinámicamente -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Archivo Multimedia *</label>
                        <input type="file" class="form-control" id="subirInputFile" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm" required>
                        <div class="form-text small">Formatos permitidos: JPG, PNG, WEBP, MP4, MOV, WEBM. Procesamiento por streams desacoplados.</div>
                    </div>
                    <!-- Barra de progreso -->
                    <div class="progress mb-2 d-none" id="progresoSubidaBox" style="height: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="progresoSubidaBar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div class="text-center small text-muted d-none" id="progresoSubidaTxt">Subiendo y verificando checksum...</div>
                </div>
                <div class="modal-footer bg-light border-0 py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold" id="btnEjecutarSubida">
                        <i class="fa-solid fa-upload me-1"></i> Iniciar Subida
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 5: PUBLICAR ENTREGA PARCIAL O FINAL
============================================================================== -->
<div class="modal fade" id="modalCrearEntrega" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-success text-white border-0 py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-paper-plane me-2"></i> Publicar Entrega al Cliente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCrearEntrega">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Título de la Entrega *</label>
                            <input type="text" class="form-control" name="titulo" placeholder="Ej: Primer Adelanto - Fotos Retocadas en Graderías" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tipo de Entrega</label>
                            <select class="form-select" name="tipo_entrega">
                                <option value="PARCIAL">Entrega Parcial</option>
                                <option value="FINAL">Entrega Final Definitiva</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Notas / Indicaciones para el Cliente</label>
                            <textarea class="form-control" name="notas" rows="2" placeholder="Instrucciones para revisión o visto bueno..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Días de Caducidad de Enlaces</label>
                            <input type="number" class="form-control" name="dias_caducidad" value="30" min="1" max="365">
                        </div>
                    </div>

                    <label class="form-label small fw-bold mb-2">Seleccione los Archivos a Incluir en esta Entrega *</label>
                    <div class="border rounded-3 p-3 bg-light" style="max-height: 250px; overflow-y: auto;" id="listaSeleccionArchivosEntrega">
                        <!-- Checkboxes dinámicos -->
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold">
                        <i class="fa-solid fa-check me-1"></i> Publicar Entrega
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
