<!-- ==============================================================================
     VISTA OFICIAL: OFERTAS POR EDICIÓN Y TARIFAS (ofertas.php) - FASE 2.3C
     Habilitación de Ítems y Paquetes por Edición, Tarifas y Concurrencia Optimista 409
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-tags text-primary me-2"></i> Ofertas y Tarifas por Edición
        </h3>
        <p class="text-secondary mb-0">
            Habilitación comercial de ítems y paquetes para la edición seleccionada, fijación de tarifas y control de concurrencia optimista.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2 align-items-center">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarOfertas" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <div class="d-flex align-items-center gap-1 bg-white p-1 ps-2 b-r-8 border shadow-sm">
                <i class="fa-solid fa-calendar-check text-primary f-s-14"></i>
                <select class="form-select form-select-sm border-0 f-w-600" id="selectorEdicionContextual" style="min-width: 220px;">
                    <?php if (!empty($ediciones)): ?>
                        <?php foreach ($ediciones as $ed): ?>
                            <option value="<?= (int) $ed->id ?>" <?= $ed->esActual ? 'selected' : '' ?>>
                                <?= escapar_html($ed->nombre) ?> (<?= (int) $ed->anio ?>) <?= $ed->esActual ? '★' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">No hay ediciones activas</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen / KPIs -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Ítems Habilitados</span>
                    <h3 class="f-w-700 text-primary mb-0 mt-1" id="kpiItemsHabilitados">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-box-open"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Paquetes Habilitados</span>
                    <h3 class="f-w-700 text-info mb-0 mt-1" id="kpiPaquetesHabilitados">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-info-subtle text-info rounded-circle f-s-20">
                    <i class="fa-solid fa-boxes-packing"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Tarifas con Tarifa Vigente</span>
                    <h3 class="f-w-700 text-success mb-0 mt-1" id="kpiTarifasVigentes">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Divisa Institucional</span>
                    <h3 class="f-w-700 text-dark mb-0 mt-1" id="kpiMonedaPrincipal"><?= escapar_html($monedaPrincipal ?? 'PEN') ?></h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-dark-subtle text-dark rounded-circle f-s-20">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs de Ofertas -->
<div class="card border-0 shadow-sm b-r-12">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-tabs-bottom px-3 pt-2" id="ofertasTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active f-w-600" id="tabItems-tab" data-bs-toggle="tab" data-bs-target="#tabItems" type="button" role="tab" aria-controls="tabItems" aria-selected="true">
                    <i class="fa-solid fa-box-open me-2 text-primary"></i> Ítems Comerciales en Edición
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link f-w-600" id="tabPaquetes-tab" data-bs-toggle="tab" data-bs-target="#tabPaquetes" type="button" role="tab" aria-controls="tabPaquetes" aria-selected="false">
                    <i class="fa-solid fa-boxes-packing me-2 text-info"></i> Paquetes en Edición
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content" id="ofertasTabContent">
            <!-- TAB 1: ÍTEMS COMERCIALES -->
            <div class="tab-pane fade show active p-3" id="tabItems" role="tabpanel" aria-labelledby="tabItems-tab">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="tablaOfertasItems">
                        <thead class="table-light">
                            <tr>
                                <th class="f-s-12 text-muted text-uppercase">Código</th>
                                <th class="f-s-12 text-muted text-uppercase">Ítem Comercial</th>
                                <th class="f-s-12 text-muted text-uppercase">Tipo / Categoría</th>
                                <th class="f-s-12 text-muted text-uppercase text-center">En Oferta</th>
                                <th class="f-s-12 text-muted text-uppercase text-center">
                                    Capacidad Ref.
                                    <i class="fa-solid fa-circle-question text-secondary ms-1" data-bs-toggle="tooltip" title="Capacidad máxima referencial estimada para esta edición. Sin control transaccional de stock."></i>
                                </th>
                                <th class="f-s-12 text-muted text-uppercase text-end">Tarifa Vigente</th>
                                <th class="f-s-12 text-muted text-uppercase text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyOfertasItems">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: PAQUETES -->
            <div class="tab-pane fade p-3" id="tabPaquetes" role="tabpanel" aria-labelledby="tabPaquetes-tab">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="tablaOfertasPaquetes">
                        <thead class="table-light">
                            <tr>
                                <th class="f-s-12 text-muted text-uppercase">Código</th>
                                <th class="f-s-12 text-muted text-uppercase">Paquete Comercial</th>
                                <th class="f-s-12 text-muted text-uppercase text-center">Ítems Incluidos</th>
                                <th class="f-s-12 text-muted text-uppercase text-center">En Oferta</th>
                                <th class="f-s-12 text-muted text-uppercase text-center">
                                    Capacidad Ref.
                                    <i class="fa-solid fa-circle-question text-secondary ms-1" data-bs-toggle="tooltip" title="Capacidad máxima referencial estimada para esta edición. Sin control transaccional de stock."></i>
                                </th>
                                <th class="f-s-12 text-muted text-uppercase text-end">Tarifa Vigente</th>
                                <th class="f-s-12 text-muted text-uppercase text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyOfertasPaquetes">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: HABILITAR OFERTA / DEFINIR CAPACIDAD REFERENCIAL
============================================================================== -->
<div class="modal fade" id="modalHabilitarOferta" tabindex="-1" aria-labelledby="modalHabilitarOfertaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalHabilitarOfertaLabel">
                    <i class="fa-solid fa-toggle-on me-2"></i> Habilitar Oferta en Edición
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formHabilitarOferta" novalidate>
                <input type="hidden" id="habTipoElemento" name="tipo_elemento" value="ITEM">
                <input type="hidden" id="habElementoId" name="elemento_id" value="">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <span class="text-muted f-s-12 d-block">Elemento Comercial:</span>
                        <h6 class="f-w-700 text-dark mb-0" id="habElementoNombre">-</h6>
                    </div>

                    <div class="mb-3">
                        <label for="habCapacidad" class="form-label f-w-600 f-s-13">
                            Capacidad Referencial (Cupos Estimados)
                            <i class="fa-solid fa-circle-question text-secondary ms-1" data-bs-toggle="tooltip" title="Capacidad máxima referencial estimada para esta edición. Sin control transaccional de stock."></i>
                        </label>
                        <input type="number" class="form-control" id="habCapacidad" name="capacidad_referencial" min="1" step="1" placeholder="Ej. 50 (Opcional)">
                        <div class="form-text f-s-12">
                            Dato puramente orientativo para el equipo comercial; no restringe cotizaciones ni bloquea inventario.
                        </div>
                    </div>

                    <div class="mb-3" id="seccionHabTarifaInicial">
                        <label for="habTarifaInicial" class="form-label f-w-600 f-s-13">
                            Tarifa Inicial (Opcional)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light f-w-600 text-muted" id="habMonedaEtiqueta"><?= escapar_html($monedaPrincipal ?? 'PEN') ?></span>
                            <input type="number" class="form-control" id="habTarifaInicial" name="precio_inicial" min="0.01" step="0.01" placeholder="0.00">
                        </div>
                        <div class="form-text f-s-12">
                            Si se especifica, se fijará la tarifa inicial vigente para este elemento en la edición.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarHabilitar">
                        <i class="fa-solid fa-check me-1"></i> Guardar Oferta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: FIJAR / CAMBIAR TARIFA (Concurrencia Optimista 409)
============================================================================== -->
<div class="modal fade" id="modalCambiarTarifa" tabindex="-1" aria-labelledby="modalCambiarTarifaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-success text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCambiarTarifaLabel">
                    <i class="fa-solid fa-coins me-2"></i> Cambiar Tarifa Vigente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCambiarTarifa" novalidate>
                <input type="hidden" id="tarifaTipoElemento" name="tipo_elemento" value="ITEM">
                <input type="hidden" id="tarifaElementoId" name="elemento_id" value="">
                <input type="hidden" id="tarifaId" name="tarifa_id" value="">
                <input type="hidden" id="tarifaVersionBloqueo" name="version_bloqueo" value="1">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <span class="text-muted f-s-12 d-block">Elemento Comercial:</span>
                        <h6 class="f-w-700 text-dark mb-0" id="tarifaElementoNombre">-</h6>
                    </div>

                    <div class="alert alert-light border d-flex justify-content-between align-items-center p-2 px-3 b-r-8 mb-3">
                        <span class="f-s-12 text-muted">Tarifa Actual Vigente:</span>
                        <span class="f-w-700 text-success f-s-15" id="tarifaPrecioActualDisplay">-</span>
                    </div>

                    <div class="mb-3">
                        <label for="tarifaNuevoPrecio" class="form-label f-w-600 f-s-13">Nuevo Precio <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light f-w-600 text-muted" id="tarifaMonedaSpan"><?= escapar_html($monedaPrincipal ?? 'PEN') ?></span>
                            <input type="number" class="form-control form-control-lg text-end f-w-700" id="tarifaNuevoPrecio" name="precio" min="0.01" step="0.01" placeholder="0.00" required>
                        </div>
                        <div class="form-text f-s-12">
                            La divisa es gobernada institucionalmente por la plataforma y no puede modificarse por transacción.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tarifaMotivo" class="form-label f-w-600 f-s-13">Motivo del Cambio de Tarifa <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="tarifaMotivo" name="motivo" rows="2" placeholder="Ej. Actualización por incremento de costos operativos..." required minlength="3"></textarea>
                    </div>

                    <!-- Alerta de Concurrencia Optimista (409) -->
                    <div class="alert alert-danger d-none b-r-8 p-3" id="alertaConflictoConcurrencia">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-triangle-exclamation f-s-20 mt-1"></i>
                            <div>
                                <h6 class="f-w-700 mb-1">Conflicto de Concurrencia Detectado</h6>
                                <p class="f-s-12 mb-2" id="mensajeConflictoConcurrencia">
                                    La tarifa fue modificada por otro usuario concurrentemente.
                                </p>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="btnRecargarTrasConflicto">
                                    <i class="fa-solid fa-rotate me-1"></i> Recargar Datos Actualizados
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnGuardarTarifa">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Tarifa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: HISTORIAL DE TARIFAS (Append-Only Auditado - ZERO PII)
============================================================================== -->
<div class="modal fade" id="modalHistorialTarifas" tabindex="-1" aria-labelledby="modalHistorialTarifasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-dark text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalHistorialTarifasLabel">
                    <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Historial Auditado de Tarifas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-light border d-flex justify-content-between align-items-center p-3 b-r-12 mb-3">
                    <div>
                        <span class="f-s-12 text-muted text-uppercase d-block">Elemento Comercial:</span>
                        <h6 class="f-w-700 text-dark mb-0" id="historialElementoNombre">-</h6>
                    </div>
                    <span class="badge bg-secondary px-3 py-2" id="historialElementoCodigo">-</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle w-100" id="tablaHistorialTarifas">
                        <thead class="table-light">
                            <tr>
                                <th class="f-s-12 text-muted">Fecha y Hora</th>
                                <th class="f-s-12 text-muted text-end">Precio Ant.</th>
                                <th class="f-s-12 text-muted text-end">Precio Nuevo</th>
                                <th class="f-s-12 text-muted text-center">Moneda</th>
                                <th class="f-s-12 text-muted">Motivo del Cambio</th>
                                <th class="f-s-12 text-muted">Actor Responsable</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyHistorialTarifas">
                            <!-- Filas dinámicas del historial -->
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-secondary border-0 b-r-12 mt-3 mb-0 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-success f-s-16"></i>
                    <span class="f-s-12 text-muted">
                        Registro histórico inmutable (append-only) regulado por RBAC institucional. Libre de secretos y PII sensible.
                    </span>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
