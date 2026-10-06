<!-- Encabezado de la Sección de Oportunidades CRM -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-bullseye text-primary me-2"></i> Oportunidades Comerciales
        </h3>
        <p class="text-secondary mb-0">
            Pipeline comercial, seguimiento de intenciones por edición y avance de etapas con concurrencia optimista.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalCrear">
                <i class="fa-solid fa-plus me-2"></i> Nueva Oportunidad
            </button>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen / KPIs de Pipeline -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Oportunidades</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalOportunidades">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Pipeline Abierto</span>
                    <h3 class="f-w-700 text-info mb-0 mt-1" id="kpiPipelineAbierto">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-info-subtle text-info rounded-circle f-s-20">
                    <i class="fa-solid fa-filter-circle-dollar"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Ganadas</span>
                    <h3 class="f-w-700 text-success mb-0 mt-1" id="kpiGanadas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-trophy"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Perdidas</span>
                    <h3 class="f-w-700 text-danger mb-0 mt-1" id="kpiPerdidas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-danger-subtle text-danger rounded-circle f-s-20">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros de Oportunidades y Pipeline -->
<div class="card border-0 shadow-sm b-r-12 mb-3">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <label for="filtroEdicion" class="form-label f-s-12 text-muted mb-1">Edición Candelaria</label>
                <select class="form-select form-select-sm" id="filtroEdicion">
                    <option value="">Todas las Ediciones</option>
                    <?php if (!empty($ediciones)): ?>
                        <?php foreach ($ediciones as $ed): ?>
                            <option value="<?= (int) $ed->id ?>" <?= $ed->esActual ? 'selected' : '' ?>>
                                <?= escapar_html($ed->nombre) ?> (<?= (int) $ed->anio ?>) <?= $ed->esActual ? '★' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="filtroEtapa" class="form-label f-s-12 text-muted mb-1">Etapa de Pipeline</label>
                <select class="form-select form-select-sm" id="filtroEtapa">
                    <option value="">Todas las Etapas</option>
                    <option value="NUEVA">NUEVA</option>
                    <option value="CONTACTADO">CONTACTADO</option>
                    <option value="PROPUESTA">PROPUESTA</option>
                    <option value="NEGOCIACION">NEGOCIACIÓN</option>
                    <option value="GANADA">GANADA</option>
                    <option value="PERDIDA">PERDIDA</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="filtroAsesor" class="form-label f-s-12 text-muted mb-1">Asesor Responsable</label>
                <select class="form-select form-select-sm" id="filtroAsesor">
                    <option value="">Todos los Asesores</option>
                    <?php if (!empty($asesores)): ?>
                        <?php foreach ($asesores as $usr): ?>
                            <option value="<?= (int) $usr->id ?>"><?= escapar_html($usr->nombreCompleto) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="filtroOrigen" class="form-label f-s-12 text-muted mb-1">Origen Comercial</label>
                <select class="form-select form-select-sm" id="filtroOrigen">
                    <option value="">Todos los Orígenes</option>
                    <?php if (!empty($origenes)): ?>
                        <?php foreach ($origenes as $orig): ?>
                            <option value="<?= (int) $orig->id ?>"><?= escapar_html($orig->nombre) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filtroBusqueda" class="form-label f-s-12 text-muted mb-1">Buscar por Negocio o Cliente</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" class="form-control" id="filtroBusqueda" placeholder="Ej. Morenada, Pérez...">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor Principal de la Tabla de Oportunidades -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-body p-3">
        <div id="contenedorTablaOportunidades" class="app-datatable-default overflow-auto app-scroll">
            <!-- Tabla inyectada dinámicamente por crm_oportunidades.js -->
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 1: REGISTRAR NUEVA OPORTUNIDAD COMERCIAL
============================================================================== -->
<div class="modal fade" id="modalCrearOportunidad" tabindex="-1" aria-labelledby="modalCrearOportunidadLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCrearOportunidadLabel">
                    <i class="fa-solid fa-bullseye me-2"></i> Nueva Oportunidad Comercial
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCrearOportunidad" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="crearEdicionId" class="form-label f-w-600 f-s-13">Edición Candelaria <span class="text-danger">*</span></label>
                            <select class="form-select" id="crearEdicionId" name="edicion_id" required>
                                <option value="">Seleccione Edición...</option>
                                <?php if (!empty($ediciones)): ?>
                                    <?php foreach ($ediciones as $ed): ?>
                                        <option value="<?= (int) $ed->id ?>" <?= $ed->esActual ? 'selected' : '' ?>>
                                            <?= escapar_html($ed->nombre) ?> (<?= (int) $ed->anio ?>) <?= $ed->esActual ? '★' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="crearClienteId" class="form-label f-w-600 f-s-13">Cliente / Prospecto <span class="text-danger">*</span></label>
                            <select class="form-select select2-cliente-ajax" id="crearClienteId" name="cliente_id" style="width: 100%;" required>
                                <option value="">Buscar cliente por nombre o documento...</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="crearTitulo" class="form-label f-w-600 f-s-13">Título del Negocio / Intención <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="crearTitulo" name="titulo" placeholder="Ej. Cobertura Morenada Laykakota 2026" required maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label for="crearValorEstimado" class="form-label f-w-600 f-s-13">Valor Estimado</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted f-w-600">PEN S/.</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="crearValorEstimado" name="valor_estimado" placeholder="0.00">
                            </div>
                            <div class="form-text f-s-11">Moneda soberana institucional (PEN).</div>
                        </div>
                        <div class="col-md-4">
                            <label for="crearAsesorId" class="form-label f-w-600 f-s-13">Asesor Responsable</label>
                            <select class="form-select" id="crearAsesorId" name="usuario_asignado_id">
                                <option value="">(Sin Asignar)</option>
                                <?php if (!empty($asesores)): ?>
                                    <?php foreach ($asesores as $usr): ?>
                                        <option value="<?= (int) $usr->id ?>"><?= escapar_html($usr->nombreCompleto) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="crearOrigenId" class="form-label f-w-600 f-s-13">Origen Comercial</label>
                            <select class="form-select" id="crearOrigenId" name="origen_comercial_id">
                                <option value="">(Sin Origen Específico)</option>
                                <?php if (!empty($origenes)): ?>
                                    <?php foreach ($origenes as $orig): ?>
                                        <option value="<?= (int) $orig->id ?>"><?= escapar_html($orig->nombre) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="crearSeguimientoEn" class="form-label f-w-600 f-s-13">Próximo Seguimiento</label>
                            <input type="text" class="form-control candelaria-flatpickr bg-white" id="crearSeguimientoEn" name="proximo_seguimiento_en" placeholder="AAAA-MM-DD">
                        </div>
                        <div class="col-12">
                            <label for="crearNotas" class="form-label f-w-600 f-s-13">Notas Iniciales</label>
                            <textarea class="form-control" id="crearNotas" name="notas" rows="2" placeholder="Requerimientos iniciales del cliente..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCrear">
                        <i class="fa-solid fa-save me-1"></i> Abrir Oportunidad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 2: CAMBIAR ETAPA (CONCURRENCIA OPTIMISTA + MOTIVOS)
============================================================================== -->
<div class="modal fade" id="modalCambiarEtapa" tabindex="-1" aria-labelledby="modalCambiarEtapaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-warning text-dark b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCambiarEtapaLabel">
                    <i class="fa-solid fa-arrow-right-arrow-left me-2"></i> Cambiar Etapa en Pipeline
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCambiarEtapa" novalidate>
                <input type="hidden" id="etapaOportunidadId" name="id">
                <input type="hidden" id="etapaVersionBloqueo" name="version_bloqueo">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <span class="text-muted f-s-12 d-block">Oportunidad:</span>
                        <h6 class="f-w-700 text-dark mb-1" id="etapaTituloOportunidad">-</h6>
                        <span class="badge bg-secondary" id="etapaBadgeActual">-</span>
                    </div>

                    <div class="mb-3">
                        <label for="etapaNuevaSelect" class="form-label f-w-600 f-s-13">Nueva Etapa <span class="text-danger">*</span></label>
                        <select class="form-select" id="etapaNuevaSelect" name="etapa" required>
                            <option value="">Seleccione nueva etapa...</option>
                            <option value="NUEVA">NUEVA</option>
                            <option value="CONTACTADO">CONTACTADO</option>
                            <option value="PROPUESTA">PROPUESTA</option>
                            <option value="NEGOCIACION">NEGOCIACIÓN</option>
                            <option value="GANADA">GANADA (Cierre Exitoso)</option>
                            <option value="PERDIDA">PERDIDA (Negocio Descartado)</option>
                        </select>
                    </div>

                    <!-- Panel condicional si es PERDIDA -->
                    <div id="seccionMotivoPerdida" class="p-3 bg-danger-subtle b-r-10 mb-3 d-none">
                        <div class="mb-2">
                            <label for="etapaMotivoPerdida" class="form-label f-w-600 f-s-13 text-danger">Motivo de Pérdida <span class="text-danger">*</span></label>
                            <select class="form-select" id="etapaMotivoPerdida" name="motivo_perdida">
                                <option value="">Seleccione motivo...</option>
                                <option value="PRECIO">PRECIO - Tarifa no ajustada a presupuesto</option>
                                <option value="COMPETENCIA">COMPETENCIA - Contrató a otro proveedor</option>
                                <option value="SIN_PRESUPUESTO">SIN_PRESUPUESTO - Desistió del servicio por fondos</option>
                                <option value="CANCELACION_EVENTO">CANCELACION_EVENTO - Canceló su participación</option>
                                <option value="SIN_RESPUESTA">SIN_RESPUESTA - No respondió tras seguimiento</option>
                                <option value="OTRO">OTRO - Motivo específico (requiere detalle)</option>
                            </select>
                        </div>
                        <div id="seccionMotivoPerdidaDetalle" class="mb-0 d-none">
                            <label for="etapaMotivoPerdidaDetalle" class="form-label f-w-600 f-s-13 text-danger">Detalle Explicativo de la Pérdida <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="etapaMotivoPerdidaDetalle" name="motivo_perdida_detalle" rows="2" placeholder="Explique las razones específicas..."></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="etapaMotivoCambio" class="form-label f-w-600 f-s-13">Motivo / Anotación de Transición</label>
                        <input type="text" class="form-control" id="etapaMotivoCambio" name="motivo_cambio" placeholder="Ej. Cliente aceptó propuesta comercial...">
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark f-w-600" id="btnGuardarEtapa">
                        <i class="fa-solid fa-check me-1"></i> Actualizar Etapa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 3: EDITAR OPORTUNIDAD (CONCURRENCIA OPTIMISTA)
============================================================================== -->
<div class="modal fade" id="modalEditarOportunidad" tabindex="-1" aria-labelledby="modalEditarOportunidadLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalEditarOportunidadLabel">
                    <i class="fa-solid fa-pen-to-square me-2"></i> Editar Datos de Oportunidad
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarOportunidad" novalidate>
                <input type="hidden" id="editarOpId" name="id">
                <input type="hidden" id="editarOpVersionBloqueo" name="version_bloqueo">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13 text-muted">Cliente</label>
                            <input type="text" class="form-control bg-light" id="editarOpClienteDisplay" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13 text-muted">Edición Candelaria</label>
                            <input type="text" class="form-control bg-light" id="editarOpEdicionDisplay" readonly disabled>
                        </div>
                        <div class="col-12">
                            <label for="editarOpTitulo" class="form-label f-w-600 f-s-13">Título de la Oportunidad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editarOpTitulo" name="titulo" required maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label for="editarOpValor" class="form-label f-w-600 f-s-13">Valor Estimado</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted f-w-600">PEN S/.</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="editarOpValor" name="valor_estimado">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="editarOpOrigenId" class="form-label f-w-600 f-s-13">Origen Comercial</label>
                            <select class="form-select" id="editarOpOrigenId" name="origen_comercial_id">
                                <option value="">(Sin Origen Específico)</option>
                                <?php if (!empty($origenes)): ?>
                                    <?php foreach ($origenes as $orig): ?>
                                        <option value="<?= (int) $orig->id ?>"><?= escapar_html($orig->nombre) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="editarOpSeguimiento" class="form-label f-w-600 f-s-13">Próximo Seguimiento</label>
                            <input type="text" class="form-control candelaria-flatpickr bg-white" id="editarOpSeguimiento" name="proximo_seguimiento_en">
                        </div>
                        <div class="col-12">
                            <label for="editarOpNotas" class="form-label f-w-600 f-s-13">Notas Comerciales</label>
                            <textarea class="form-control" id="editarOpNotas" name="notas" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarEditarOp">
                        <i class="fa-solid fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 4: ASIGNAR ASESOR RESPONSABLE
============================================================================== -->
<div class="modal fade" id="modalAsignarResponsable" tabindex="-1" aria-labelledby="modalAsignarResponsableLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-info text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalAsignarResponsableLabel">
                    <i class="fa-solid fa-user-tag me-2"></i> Asignar Asesor Responsable
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAsignarResponsable" novalidate>
                <input type="hidden" id="asignarOpId" name="id">
                <input type="hidden" id="asignarOpVersionBloqueo" name="version_bloqueo">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <span class="text-muted f-s-12 d-block">Oportunidad:</span>
                        <h6 class="f-w-700 text-dark mb-0" id="asignarOpTitulo">-</h6>
                    </div>
                    <div class="mb-3">
                        <label for="asignarAsesorSelect" class="form-label f-w-600 f-s-13">Nuevo Asesor Responsable</label>
                        <select class="form-select" id="asignarAsesorSelect" name="usuario_asignado_id">
                            <option value="">(Sin Asignar / Desasignar)</option>
                            <?php if (!empty($asesores)): ?>
                                <?php foreach ($asesores as $usr): ?>
                                    <option value="<?= (int) $usr->id ?>"><?= escapar_html($usr->nombreCompleto) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info text-white" id="btnGuardarAsignar">
                        <i class="fa-solid fa-check me-1"></i> Confirmar Asignación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
