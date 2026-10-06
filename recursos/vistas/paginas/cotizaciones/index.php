<!-- ==============================================================================
     VISTA OFICIAL: GESTIÓN DE COTIZACIONES COMERCIALES (index.php) - FASE 2.4C
     Propuestas económicas y presupuestos por edición con arquitectura Alina UI
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> Cotizaciones Comerciales
        </h3>
        <p class="text-secondary mb-0">
            Administración de propuestas económicas, presupuestos y revisiones por edición festiva.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <?php if (!empty($permisos['crear'])): ?>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalNueva">
                <i class="fa-solid fa-plus me-2"></i> Nueva Cotización
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
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Cotizaciones</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalCotizaciones">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Emitidas / Vigentes</span>
                    <h3 class="f-w-700 text-info mb-0 mt-1" id="kpiEmitidas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-info-subtle text-info rounded-circle f-s-20">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Aceptadas</span>
                    <h3 class="f-w-700 text-success mb-0 mt-1" id="kpiAceptadas">-</h3>
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
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Monto Aceptado</span>
                    <h3 class="f-w-700 text-dark mb-0 mt-1" id="kpiMontoAceptado">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros Avanzados -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control border-start-0" id="filtroBusqueda" placeholder="Buscar correlativo, título...">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filtroEstado" aria-label="Filtrar por estado">
                    <option value="">Todos los Estados</option>
                    <option value="BORRADOR">Borrador</option>
                    <option value="EMITIDA">Emitida</option>
                    <option value="ACEPTADA">Aceptada</option>
                    <option value="RECHAZADA">Rechazada</option>
                    <option value="VENCIDA">Vencida</option>
                    <option value="ANULADA">Anulada</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filtroVigencia" aria-label="Filtrar por vigencia">
                    <option value="">Vigencia (Todas)</option>
                    <option value="VIGENTE">Vigentes</option>
                    <option value="VENCIDA">Vencidas</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-calendar-days"></i></span>
                    <input type="text" class="form-control border-start-0" id="filtroRangoFechas" placeholder="Rango de emisión...">
                </div>
            </div>
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-outline-secondary w-100" id="btnLimpiarFiltros">
                    <i class="fa-solid fa-filter-circle-xmark me-1"></i> Limpiar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Tabla Principal de Cotizaciones -->
<div class="card border-0 shadow-sm b-r-12">
    <div class="card-body p-3">
        <!-- Skeleton de Carga -->
        <div id="skeletonTablaCotizaciones" class="d-none">
            <!-- Renderizado dinámico vía skeleton.js -->
        </div>

        <!-- Contenedor DataTable -->
        <div class="table-responsive" id="contenedorTablaCotizaciones">
            <table class="table table-hover align-middle w-100" id="tablaCotizaciones">
                <thead class="table-light">
                    <tr>
                        <th class="f-s-12 text-uppercase text-secondary f-w-600">Correlativo</th>
                        <th class="f-s-12 text-uppercase text-secondary f-w-600 text-center">Rev.</th>
                        <th class="f-s-12 text-uppercase text-secondary f-w-600">Cliente</th>
                        <th class="f-s-12 text-uppercase text-secondary f-w-600">Oportunidad</th>
                        <th class="f-s-12 text-uppercase text-secondary f-w-600">Emisión</th>
                        <th class="f-s-12 text-uppercase text-secondary f-w-600">Válido Hasta</th>
                        <th class="f-s-12 text-uppercase text-secondary f-w-600 text-center">Estado</th>
                        <th class="f-s-12 text-uppercase text-secondary f-w-600 text-end">Total</th>
                        <th class="f-s-12 text-uppercase text-secondary f-w-600 text-center" style="width: 140px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Filas pobladas asíncronamente vía Fetch API -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 1: NUEVA COTIZACIÓN (WIZARD MULTI-PASO)
============================================================================== -->
<div class="modal fade" id="modalNuevaCotizacion" tabindex="-1" aria-labelledby="modalNuevaCotizacionLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg b-r-12">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="w-35 h-35 d-flex-center bg-primary-subtle text-primary rounded-circle">
                        <i class="fa-solid fa-file-circle-plus"></i>
                    </div>
                    <div>
                        <h5 class="modal-title f-w-700 text-dark mb-0" id="modalNuevaCotizacionLabel">Nueva Propuesta Comercial</h5>
                        <small class="text-secondary">Paso a paso con ofertas activas de la edición de trabajo</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Cabecera de Pasos del Wizard -->
            <div class="px-4 pt-3 pb-2 bg-light-subtle border-bottom">
                <div class="d-flex justify-content-between align-items-center position-relative">
                    <div class="wizard-step-item text-center flex-fill active" data-step="1">
                        <div class="wizard-step-badge mx-auto mb-1"><i class="fa-solid fa-user-tie"></i></div>
                        <span class="f-s-11 text-uppercase f-w-600">1. Cliente</span>
                    </div>
                    <div class="wizard-step-item text-center flex-fill" data-step="2">
                        <div class="wizard-step-badge mx-auto mb-1"><i class="fa-solid fa-boxes-stacked"></i></div>
                        <span class="f-s-11 text-uppercase f-w-600">2. Conceptos</span>
                    </div>
                    <div class="wizard-step-item text-center flex-fill" data-step="3">
                        <div class="wizard-step-badge mx-auto mb-1"><i class="fa-solid fa-percent"></i></div>
                        <span class="f-s-11 text-uppercase f-w-600">3. Descuentos</span>
                    </div>
                    <div class="wizard-step-item text-center flex-fill" data-step="4">
                        <div class="wizard-step-badge mx-auto mb-1"><i class="fa-solid fa-calendar-check"></i></div>
                        <span class="f-s-11 text-uppercase f-w-600">4. Términos</span>
                    </div>
                    <div class="wizard-step-item text-center flex-fill" data-step="5">
                        <div class="wizard-step-badge mx-auto mb-1"><i class="fa-solid fa-check-double"></i></div>
                        <span class="f-s-11 text-uppercase f-w-600">5. Resumen</span>
                    </div>
                </div>
            </div>

            <div class="modal-body p-4">
                <form id="formWizardCotizacion" novalidate>
                    <!-- PASO 1: CLIENTE Y OPORTUNIDAD -->
                    <div class="wizard-pane active" id="wizardPaso1">
                        <h6 class="f-w-700 text-dark mb-3"><i class="fa-solid fa-address-card text-primary me-2"></i> Destinatario de la Propuesta</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="wizardTitulo" class="form-label f-s-13 f-w-600">Título / Concepto de la Cotización <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="wizardTitulo" placeholder="Ej. Paquete Fotográfico y Cobertura Candelaria 2026" required value="Propuesta Comercial">
                            </div>
                            <div class="col-md-6">
                                <label for="wizardClienteSelect" class="form-label f-s-13 f-w-600">Cliente Comercial <span class="text-danger">*</span></label>
                                <select class="form-select" id="wizardClienteSelect" style="width: 100%;" required>
                                    <option value="">Seleccione o busque un cliente...</option>
                                </select>
                                <div class="form-text f-s-11">Clientes registrados del tenant activo.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="wizardOportunidadSelect" class="form-label f-s-13 f-w-600">Oportunidad CRM (Opcional)</label>
                                <select class="form-select" id="wizardOportunidadSelect" style="width: 100%;">
                                    <option value="">Sin oportunidad vinculada</option>
                                </select>
                                <div class="form-text f-s-11">Filtrado por cliente y edición compatibles.</div>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 2: CONCEPTOS Y LÍNEAS -->
                    <div class="wizard-pane d-none" id="wizardPaso2">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="f-w-700 text-dark mb-0"><i class="fa-solid fa-cart-plus text-primary me-2"></i> Selección de Ofertas de la Edición</h6>
                            <span class="badge bg-light text-dark border"><i class="fa-solid fa-coins text-warning me-1"></i> Moneda: <strong class="moneda-display"><?= escapar_html($monedaPrincipal) ?></strong></span>
                        </div>

                        <!-- Selector para agregar línea -->
                        <div class="card bg-light border-0 p-3 mb-3 b-r-8">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label f-s-12 f-w-600">Tipo de Concepto</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="wizardTipoLinea" id="wizardTipoItem" value="ITEM" checked>
                                        <label class="btn btn-outline-primary btn-sm" for="wizardTipoItem"><i class="fa-solid fa-box-open me-1"></i> Ítem</label>
                                        <input type="radio" class="btn-check" name="wizardTipoLinea" id="wizardTipoPaquete" value="PAQUETE">
                                        <label class="btn btn-outline-primary btn-sm" for="wizardTipoPaquete"><i class="fa-solid fa-boxes-packing me-1"></i> Paquete</label>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label for="wizardOfertaSelect" class="form-label f-s-12 f-w-600">Oferta Comercial Activa</label>
                                    <select class="form-select form-select-sm" id="wizardOfertaSelect">
                                        <option value="">Cargando ofertas disponibles...</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="wizardCantidad" class="form-label f-s-12 f-w-600">Cantidad</label>
                                    <input type="number" class="form-control form-control-sm text-end" id="wizardCantidad" value="1" min="0.01" step="0.5">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-primary btn-sm w-100" id="btnWizardAgregarLinea">
                                        <i class="fa-solid fa-plus me-1"></i> Agregar
                                    </button>
                                </div>
                            </div>
                            <!-- Vista previa de componentes del paquete seleccionado -->
                            <div class="mt-2 d-none" id="wizardVistaPreviaComponentes">
                                <small class="text-muted f-s-11 d-block mb-1"><i class="fa-solid fa-circle-info me-1"></i> Componentes congelados que se incluirán en el snapshot relacional:</small>
                                <div class="d-flex flex-wrap gap-1" id="wizardBadgesComponentes"></div>
                            </div>
                        </div>

                        <!-- Tabla de líneas en construcción -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle" id="tablaWizardLineas">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-11 text-uppercase text-secondary">Tipo</th>
                                        <th class="f-s-11 text-uppercase text-secondary">Concepto</th>
                                        <th class="f-s-11 text-uppercase text-secondary">Unidad</th>
                                        <th class="f-s-11 text-uppercase text-secondary text-end">Cant.</th>
                                        <th class="f-s-11 text-uppercase text-secondary text-end">P. Unitario</th>
                                        <th class="f-s-11 text-uppercase text-secondary text-end">Subtotal</th>
                                        <th class="f-s-11 text-uppercase text-secondary text-center" style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr id="filaSinLineasWizard">
                                        <td colspan="7" class="text-center text-muted py-3">No hay conceptos agregados aún. Seleccione una oferta activa arriba.</td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="5" class="text-end f-s-12 text-uppercase">Subtotal Estimado:</th>
                                        <th class="text-end f-s-13 f-w-700" id="wizardSubtotalEstimado">0.00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- PASO 3: DESCUENTOS -->
                    <div class="wizard-pane d-none" id="wizardPaso3">
                        <h6 class="f-w-700 text-dark mb-3"><i class="fa-solid fa-tags text-primary me-2"></i> Gobernanza de Descuentos Comerciales</h6>
                        <?php if (empty($permisos['aplicarDescuento'])): ?>
                            <div class="alert alert-warning border-0 d-flex align-items-center gap-3">
                                <i class="fa-solid fa-shield-halved f-s-24"></i>
                                <div>
                                    <strong>Capacidad Restringida por RBAC:</strong>
                                    Su rol no dispone del permiso soberano <code>cotizaciones.aplicar_descuento</code>. Los precios se calcularán estrictamente según tarifa vigente sin reducciones.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card bg-light border-0 p-3 mb-3 b-r-8">
                                <h6 class="f-s-13 f-w-700 text-secondary mb-2">Descuento Global a la Propuesta</h6>
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label for="wizardDescGlobalTipo" class="form-label f-s-12 f-w-600">Modalidad</label>
                                        <select class="form-select form-select-sm" id="wizardDescGlobalTipo">
                                            <option value="NINGUNO">Sin Descuento Global</option>
                                            <option value="PORCENTAJE">Porcentaje (%)</option>
                                            <option value="MONTO_FIJO">Monto Fijo (<span class="moneda-display"><?= escapar_html($monedaPrincipal) ?></span>)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="wizardDescGlobalValor" class="form-label f-s-12 f-w-600">Valor</label>
                                        <input type="number" class="form-control form-control-sm text-end" id="wizardDescGlobalValor" value="0" min="0" step="0.01" disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="wizardDescGlobalMotivo" class="form-label f-s-12 f-w-600">Motivo Obligatorio <span class="text-danger" id="reqDescGlobal" style="display: none;">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="wizardDescGlobalMotivo" placeholder="Ej. Descuento institucional por volumen" disabled>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info border-0 py-2 f-s-12">
                                <i class="fa-solid fa-circle-info me-1"></i> Todo descuento mayor a cero es auditado inmutablemente y requiere justificación obligatoria.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- PASO 4: VIGENCIA Y TÉRMINOS -->
                    <div class="wizard-pane d-none" id="wizardPaso4">
                        <h6 class="f-w-700 text-dark mb-3"><i class="fa-solid fa-file-contract text-primary me-2"></i> Vigencia y Términos Comerciales</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="wizardValidoHasta" class="form-label f-s-13 f-w-600">Fecha Límite de Validez</label>
                                <input type="text" class="form-control" id="wizardValidoHasta" placeholder="Seleccione fecha límite de la oferta...">
                                <div class="form-text f-s-11">Si se emite formalmente, por defecto regirán los días institucionales configurados.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="wizardNotasInternas" class="form-label f-s-13 f-w-600">Notas Internas (Privadas para el Equipo)</label>
                                <textarea class="form-control" id="wizardNotasInternas" rows="2" placeholder="Observaciones operativas internas..."></textarea>
                            </div>
                            <div class="col-md-12">
                                <label for="wizardTerminosCondiciones" class="form-label f-s-13 f-w-600">Términos y Condiciones Comerciales</label>
                                <textarea class="form-control" id="wizardTerminosCondiciones" rows="4" placeholder="Condiciones de pago, entrega de material audiovisual, cobertura, etc."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 5: RESUMEN Y CONFIRMACIÓN -->
                    <div class="wizard-pane d-none" id="wizardPaso5">
                        <h6 class="f-w-700 text-dark mb-3"><i class="fa-solid fa-clipboard-check text-primary me-2"></i> Resumen de la Propuesta</h6>
                        <div class="card border p-3 b-r-8 mb-3">
                            <div class="row g-2 mb-3 border-bottom pb-2">
                                <div class="col-md-6">
                                    <span class="text-muted f-s-12 d-block">Título:</span>
                                    <strong class="f-s-14" id="resumenTitulo">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted f-s-12 d-block">Cliente:</span>
                                    <strong class="f-s-14" id="resumenCliente">-</strong>
                                </div>
                            </div>
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered" id="tablaResumenLineas">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Concepto</th>
                                            <th class="text-center">Tipo</th>
                                            <th class="text-end">Cant.</th>
                                            <th class="text-end">P. Unitario</th>
                                            <th class="text-end">Total Neto</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="row justify-content-end">
                                <div class="col-md-5">
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <span class="text-muted">Subtotal:</span>
                                        <strong id="resumenSubtotal">0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 border-bottom text-danger">
                                        <span>Descuento Global:</span>
                                        <strong id="resumenDescGlobal">0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 f-s-16 text-primary">
                                        <span class="f-w-700">Total Propuesta:</span>
                                        <span class="f-w-700"><span class="moneda-display"><?= escapar_html($monedaPrincipal) ?></span> <span id="resumenTotalFinal">0.00</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-light border f-s-12 mb-0">
                            <i class="fa-solid fa-info-circle text-primary me-1"></i> La propuesta se registrará en estado <strong>BORRADOR</strong>. Puede seguir editándola o emitirla formalmente cuando esté conforme.
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer bg-light border-0 py-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnWizardAnterior" disabled>
                    <i class="fa-solid fa-arrow-left me-1"></i> Anterior
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnWizardSiguiente">
                        Siguiente <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                    <button type="button" class="btn bg-gradient-success text-white btn-sm d-none" id="btnGuardarBorradorWizard">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Borrador
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 2: DETALLE 360 DE COTIZACIÓN
============================================================================== -->
<div class="modal fade" id="modalDetalle360" tabindex="-1" aria-labelledby="modalDetalle360Label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg b-r-12">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="w-35 h-35 d-flex-center bg-info-subtle text-info rounded-circle">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div>
                        <h5 class="modal-title f-w-700 text-dark mb-0" id="modalDetalle360Label">Detalle 360 de Cotización</h5>
                        <small class="text-secondary" id="detalleSubtitulo">Cargando información...</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4" id="cuerpoDetalle360">
                <!-- Skeleton de Carga -->
                <div id="skeletonDetalle360">
                    <div class="skeleton-shimmer skeleton-line lg w-50 mb-3"></div>
                    <div class="skeleton-shimmer skeleton-line w-100 mb-2"></div>
                    <div class="skeleton-shimmer skeleton-line w-75 mb-4"></div>
                    <div class="skeleton-shimmer skeleton-table-row"></div>
                </div>
                <div id="contenidoDetalle360" class="d-none">
                    <!-- Se llena dinámicamente con JavaScript -->
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-2 d-flex justify-content-between">
                <div id="detalleAccionesContextuales" class="d-flex gap-2">
                    <!-- Botones contextuales según estado y permisos -->
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 3: RECHAZAR COTIZACIÓN
============================================================================== -->
<div class="modal fade" id="modalRechazarCotizacion" tabindex="-1" aria-labelledby="modalRechazarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow b-r-12">
            <div class="modal-header bg-danger-subtle border-0 py-3">
                <div class="d-flex align-items-center gap-2 text-danger">
                    <i class="fa-solid fa-circle-xmark f-s-20"></i>
                    <h5 class="modal-title f-w-700 mb-0" id="modalRechazarLabel">Rechazar Propuesta Comercial</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formRechazarCotizacion">
                <input type="hidden" id="rechazarCotizacionId">
                <div class="modal-body p-4">
                    <p class="text-secondary f-s-13 mb-3">
                        Indique el motivo comercial estructurado por el cual el cliente no aceptó la propuesta:
                    </p>
                    <div class="mb-3">
                        <label for="rechazarMotivo" class="form-label f-s-13 f-w-600">Motivo del Rechazo <span class="text-danger">*</span></label>
                        <select class="form-select" id="rechazarMotivo" required>
                            <option value="">Seleccione motivo estructurado...</option>
                            <option value="PRECIO_ELEVADO">Precio fuera de presupuesto</option>
                            <option value="COMPETENCIA">Optó por otra opción / competencia</option>
                            <option value="CAMBIO_FECHA">Cambio de planes o fecha</option>
                            <option value="CAMBIO_REQUERIMIENTO">Requerimientos cambiaron significativamente</option>
                            <option value="CLIENTE_DESISTIO">Cliente desistió de participar</option>
                            <option value="OTRO">Otro motivo (requiere detalle)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="rechazarDetalle" class="form-label f-s-13 f-w-600">Explicación / Detalle <span class="text-danger" id="reqRechazoDetalle" style="display: none;">*</span></label>
                        <textarea class="form-control" id="rechazarDetalle" rows="3" placeholder="Detalle adicional sobre el rechazo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btnConfirmarRechazo">
                        <i class="fa-solid fa-ban me-1"></i> Registrar Rechazo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 4: ANULAR COTIZACIÓN
============================================================================== -->
<div class="modal fade" id="modalAnularCotizacion" tabindex="-1" aria-labelledby="modalAnularLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow b-r-12">
            <div class="modal-header bg-dark-subtle border-0 py-3">
                <div class="d-flex align-items-center gap-2 text-dark">
                    <i class="fa-solid fa-trash-can f-s-20"></i>
                    <h5 class="modal-title f-w-700 mb-0" id="modalAnularLabel">Anulación de Cotización</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAnularCotizacion">
                <input type="hidden" id="anularCotizacionId">
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0 py-2 f-s-12 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> La anulación es una acción administrativa irreversible. El documento pasará a estado <strong>ANULADA</strong>.
                    </div>
                    <div class="mb-3">
                        <label for="anularMotivo" class="form-label f-s-13 f-w-600">Motivo Administrativo <span class="text-danger">*</span></label>
                        <select class="form-select" id="anularMotivo" required>
                            <option value="">Seleccione motivo administrativo...</option>
                            <option value="ERROR_DATOS">Error en los datos de la propuesta</option>
                            <option value="CAMBIO_CONDICIONES_ORGANIZACION">Cambio de condiciones internas del tenant</option>
                            <option value="EXPIRACION_DEFINITIVA">Expiración definitiva sin continuidad</option>
                            <option value="OTRO">Otro motivo (requiere detalle)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="anularDetalle" class="form-label f-s-13 f-w-600">Justificación / Detalle <span class="text-danger" id="reqAnularDetalle" style="display: none;">*</span></label>
                        <textarea class="form-control" id="anularDetalle" rows="3" placeholder="Detalle justificativo de la anulación..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark btn-sm" id="btnConfirmarAnulacion">
                        <i class="fa-solid fa-xmark me-1"></i> Anular Cotización
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 5: EDITAR BORRADOR
============================================================================== -->
<div class="modal fade" id="modalEditarBorrador" tabindex="-1" aria-labelledby="modalEditarBorradorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg b-r-12">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="w-35 h-35 d-flex-center bg-primary-subtle text-primary rounded-circle">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </div>
                    <h5 class="modal-title f-w-700 text-dark mb-0" id="modalEditarBorradorLabel">Editar Borrador de Cotización</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarBorrador">
                <input type="hidden" id="editBorradorId">
                <input type="hidden" id="editVersionBloqueo">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="editTitulo" class="form-label f-s-13 f-w-600">Título / Concepto <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editTitulo" required>
                    </div>
                    <div class="mb-3">
                        <label for="editNotasInternas" class="form-label f-s-13 f-w-600">Notas Internas</label>
                        <textarea class="form-control" id="editNotasInternas" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editTerminos" class="form-label f-s-13 f-w-600">Términos y Condiciones</label>
                        <textarea class="form-control" id="editTerminos" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarEdicionBorrador">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
