<!-- ==============================================================================
     VISTA OFICIAL: GESTIÓN DE VENTAS COMERCIALES (index.php) - FASE 2.5C
     Registro, trazabilidad y conversión de cotizaciones con Alina UI
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-cash-register text-primary me-2"></i> Ventas Comerciales
        </h3>
        <p class="text-secondary mb-0">
            Registro, conversión de cotizaciones aprobadas y trazabilidad integral de ventas confirmadas.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <?php if (!empty($permisos['crearDesdeCotizacion'])): ?>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalConversion">
                <i class="fa-solid fa-cart-plus me-2"></i> Nueva Venta (desde Cotización)
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
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Ventas</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalVentas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Confirmadas</span>
                    <h3 class="f-w-700 text-success mb-0 mt-1" id="kpiConfirmadas">-</h3>
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
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Canceladas / Anuladas</span>
                    <h3 class="f-w-700 text-danger mb-0 mt-1" id="kpiCanceladas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-danger-subtle text-danger rounded-circle f-s-20">
                    <i class="fa-solid fa-ban"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Monto Total Vendido</span>
                    <h3 class="f-w-700 text-dark mb-0 mt-1" id="kpiMontoTotal">-</h3>
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
                    <input type="text" class="form-control border-start-0" id="filtroBusqueda" placeholder="Buscar venta, cliente, cotización...">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filtroEstado" aria-label="Filtrar por estado">
                    <option value="">Todos los Estados</option>
                    <option value="CONFIRMADA">Confirmada</option>
                    <option value="CANCELADA">Cancelada</option>
                    <option value="ANULADA">Anulada</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" id="filtroFechaDesde" placeholder="Desde: YYYY-MM-DD" readonly>
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" id="filtroFechaHasta" placeholder="Hasta: YYYY-MM-DD" readonly>
            </div>
            <div class="col-md-3 text-md-end">
                <button type="button" class="btn btn-outline-secondary btn-sm me-1" id="btnLimpiarFiltros" title="Limpiar filtros">
                    <i class="fa-solid fa-filter-circle-xmark me-1"></i> Limpiar
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="btnAplicarFiltros">
                    <i class="fa-solid fa-filter me-1"></i> Filtrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Tabla Principal de Ventas -->
<div class="card border-0 shadow-sm b-r-12">
    <div class="card-body p-3">
        <!-- Skeleton Loader para Tabla -->
        <div id="skeletonVentas" class="py-3">
            <div class="placeholder-glow mb-3">
                <span class="placeholder col-12 py-3 b-r-8"></span>
            </div>
            <div class="placeholder-glow mb-2">
                <span class="placeholder col-12 py-2 b-r-8"></span>
            </div>
            <div class="placeholder-glow mb-2">
                <span class="placeholder col-12 py-2 b-r-8"></span>
            </div>
            <div class="placeholder-glow mb-2">
                <span class="placeholder col-12 py-2 b-r-8"></span>
            </div>
            <div class="placeholder-glow">
                <span class="placeholder col-12 py-2 b-r-8"></span>
            </div>
        </div>

        <!-- Contenedor Real de Tabla -->
        <div class="table-responsive d-none" id="contenedorTablaVentas">
            <table class="table table-hover align-middle w-100" id="tablaVentas">
                <thead class="table-light">
                    <tr>
                        <th class="f-w-600 text-uppercase f-s-12">Correlativo</th>
                        <th class="f-w-600 text-uppercase f-s-12">Fecha</th>
                        <th class="f-w-600 text-uppercase f-s-12">Cliente</th>
                        <th class="f-w-600 text-uppercase f-s-12">Cotización Origen</th>
                        <th class="f-w-600 text-uppercase f-s-12 text-end">Total</th>
                        <th class="f-w-600 text-uppercase f-s-12 text-center">Estado</th>
                        <th class="f-w-600 text-uppercase f-s-12 text-center">Líneas</th>
                        <th class="f-w-600 text-uppercase f-s-12 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody id="cuerpoTablaVentas">
                    <!-- Filas pobladas dinámicamente vía Fetch / DataTables -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 1: CONVERSIÓN DE COTIZACIÓN EN VENTA
============================================================================== -->
<div class="modal fade" id="modalConvertirVenta" tabindex="-1" aria-labelledby="modalConvertirVentaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg b-r-16">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title f-w-700" id="modalConvertirVentaLabel">
                    <i class="fa-solid fa-cart-plus text-primary me-2"></i> Generar Venta desde Cotización Aceptada
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info border-0 b-r-10 d-flex align-items-center gap-3 mb-4">
                    <i class="fa-solid fa-circle-info f-s-24 text-info flex-shrink-0"></i>
                    <div class="f-s-13">
                        Seleccione una cotización comercial en estado <strong>ACEPTADA</strong>. La conversión registrará
                        una venta formal inmutable congelando importes, ítems y paquetes, y marcará automáticamente
                        la oportunidad CRM asociada como <strong>GANADA</strong>.
                    </div>
                </div>

                <form id="formConvertirVenta" novalidate>
                    <div class="mb-3">
                        <label for="selectCotizacionAceptada" class="form-label f-w-600">
                            Cotización Aceptada <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="selectCotizacionAceptada" style="width: 100%;" required>
                            <option value="">Buscar por correlativo o nombre de cliente...</option>
                        </select>
                        <div class="invalid-feedback">Por favor seleccione una cotización aceptada.</div>
                    </div>

                    <!-- Panel de Previsualización de la Cotización Seleccionada -->
                    <div id="previewCotizacionContenedor" class="card border border-light-subtle b-r-10 p-3 bg-light-subtle d-none mb-3">
                        <h6 class="f-w-700 text-dark mb-2">
                            <i class="fa-solid fa-file-invoice text-secondary me-1"></i> Resumen de la Propuesta
                        </h6>
                        <div class="row g-2 f-s-13">
                            <div class="col-sm-6">
                                <span class="text-muted">Correlativo:</span>
                                <strong id="prevCorrelativo" class="text-dark d-block">-</strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted">Cliente:</span>
                                <strong id="prevCliente" class="text-dark d-block">-</strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted">Documento:</span>
                                <span id="prevDocumento" class="text-dark d-block">-</span>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted">Total Neto:</span>
                                <strong id="prevTotal" class="text-success f-s-15 d-block">-</strong>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn bg-gradient-primary text-white" id="btnConfirmarConversion">
                    <i class="fa-solid fa-check me-2"></i> Confirmar Conversión a Venta
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 2: DETALLE 360 INTEGRAL DE LA VENTA
============================================================================== -->
<div class="modal fade" id="modalDetalleVenta" tabindex="-1" aria-labelledby="modalDetalleVentaLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg b-r-16">
            <div class="modal-header bg-light border-0 py-3">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="modal-title f-w-700 mb-0" id="modalDetalleVentaLabel">
                        <i class="fa-solid fa-receipt text-primary me-2"></i> Detalle de Venta: <span id="detCorrelativoHeader" class="text-primary">-</span>
                    </h5>
                    <span id="detEstadoBadge" class="badge bg-success b-r-6 px-3 py-2">-</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Skeleton Loader para Detalle -->
                <div id="skeletonDetalleVenta" class="py-3">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4"><span class="placeholder col-12 py-4 b-r-8"></span></div>
                        <div class="col-md-4"><span class="placeholder col-12 py-4 b-r-8"></span></div>
                        <div class="col-md-4"><span class="placeholder col-12 py-4 b-r-8"></span></div>
                    </div>
                    <span class="placeholder col-12 py-5 b-r-8"></span>
                </div>

                <!-- Contenido Real de Detalle -->
                <div id="contenidoDetalleVenta" class="d-none">
                    <!-- Fila Superior: Tarjetas de Cabecera -->
                    <div class="row g-3 mb-4">
                        <!-- Card Cliente -->
                        <div class="col-md-4">
                            <div class="card h-100 border border-light-subtle shadow-none b-r-10 p-3 bg-light-subtle">
                                <h6 class="f-w-700 text-dark mb-2">
                                    <i class="fa-solid fa-user text-primary me-1"></i> Snapshot Cliente
                                </h6>
                                <p class="mb-1 f-s-14 f-w-600 text-dark" id="detClienteNombre">-</p>
                                <p class="mb-1 f-s-13 text-secondary">
                                    <i class="fa-solid fa-id-card me-1"></i> Documento: <span id="detClienteDocumento">-</span>
                                </p>
                                <p class="mb-0 f-s-13 text-secondary">
                                    <i class="fa-solid fa-phone me-1"></i> Teléfono: <span id="detClienteTelefono">-</span>
                                </p>
                            </div>
                        </div>

                        <!-- Card Cotización Origen y Fechas -->
                        <div class="col-md-4">
                            <div class="card h-100 border border-light-subtle shadow-none b-r-10 p-3 bg-light-subtle">
                                <h6 class="f-w-700 text-dark mb-2">
                                    <i class="fa-solid fa-file-invoice text-info me-1"></i> Procedencia y Fecha
                                </h6>
                                <p class="mb-1 f-s-13">
                                    <span class="text-muted">Origen:</span> <strong id="detOrigenTipo" class="text-uppercase">-</strong>
                                </p>
                                <p class="mb-1 f-s-13">
                                    <span class="text-muted">Cotización:</span> <span id="detCotizacionOrigen" class="badge bg-info-subtle text-info">-</span>
                                </p>
                                <p class="mb-0 f-s-13 text-secondary">
                                    <i class="fa-solid fa-calendar me-1"></i> Fecha Venta: <span id="detFechaVenta">-</span>
                                </p>
                            </div>
                        </div>

                        <!-- Card Liquidación Económica -->
                        <div class="col-md-4">
                            <div class="card h-100 border border-light-subtle shadow-none b-r-10 p-3 bg-light-subtle">
                                <h6 class="f-w-700 text-dark mb-2">
                                    <i class="fa-solid fa-calculator text-success me-1"></i> Snapshot Económico
                                </h6>
                                <div class="d-flex justify-content-between f-s-13 mb-1">
                                    <span class="text-muted">Subtotal:</span>
                                    <span id="detSubtotal">-</span>
                                </div>
                                <div class="d-flex justify-content-between f-s-13 mb-1">
                                    <span class="text-muted">Descuento Global:</span>
                                    <span id="detDescuentoGlobal" class="text-danger">-</span>
                                </div>
                                <hr class="my-1">
                                <div class="d-flex justify-content-between f-s-16 f-w-700">
                                    <span>Total Venta:</span>
                                    <span id="detTotalVenta" class="text-success">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección de Alerta si la Venta está Cancelada o Anulada -->
                    <div id="alertaEstadoTerminal" class="alert alert-warning border-0 b-r-10 mb-4 d-none">
                        <h6 class="f-w-700 mb-1" id="tituloAlertaTerminal">-</h6>
                        <p class="mb-0 f-s-13" id="textoAlertaTerminal">-</p>
                    </div>

                    <!-- Tabla de Líneas y Componentes -->
                    <div class="card border border-light-subtle shadow-none b-r-10 mb-4">
                        <div class="card-header bg-light py-2">
                            <h6 class="f-w-700 mb-0">
                                <i class="fa-solid fa-list-check me-2 text-primary"></i> Detalle de Ítems y Paquetes Vendidos
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0" id="tablaDetalleLineas">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Tipo</th>
                                            <th>Concepto</th>
                                            <th>Unidad</th>
                                            <th class="text-center">Cant.</th>
                                            <th class="text-end">Precio Unit.</th>
                                            <th class="text-end">Descuento</th>
                                            <th class="text-end pe-3">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cuerpoDetalleLineas">
                                        <!-- Filas dinámicas -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Términos y Notas Comerciales -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border border-light-subtle shadow-none b-r-10 p-3 h-100">
                                <h6 class="f-w-700 text-dark mb-1">
                                    <i class="fa-solid fa-handshake me-1 text-secondary"></i> Términos y Condiciones Pactados
                                </h6>
                                <p class="f-s-12 text-muted mb-0" id="detTerminosCondiciones">-</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border border-light-subtle shadow-none b-r-10 p-3 h-100">
                                <h6 class="f-w-700 text-dark mb-1">
                                    <i class="fa-solid fa-note-sticky me-1 text-secondary"></i> Notas Comerciales
                                </h6>
                                <p class="f-s-12 text-muted mb-0" id="detNotasComerciales">-</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <div class="d-flex justify-content-between w-100 align-items-center">
                    <div>
                        <span class="f-s-12 text-muted">Versión Concurrente: <code id="detVersionBloqueo">-</code></span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnAccionCancelarVenta">
                            <i class="fa-solid fa-ban me-1"></i> Cancelar Venta
                        </button>
                        <button type="button" class="btn btn-outline-dark btn-sm d-none" id="btnAccionAnularVenta">
                            <i class="fa-solid fa-xmark me-1"></i> Anular Venta
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 3: CANCELAR VENTA
============================================================================== -->
<div class="modal fade" id="modalCancelarVenta" tabindex="-1" aria-labelledby="modalCancelarVentaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg b-r-16">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title f-w-700 text-danger" id="modalCancelarVentaLabel">
                    <i class="fa-solid fa-ban me-2"></i> Cancelar Venta Comercial
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <p class="f-s-14 text-secondary mb-3">
                    Está a punto de cancelar la venta <strong id="cancelarCorrelativoTexto" class="text-dark">-</strong>.
                    Esta acción es irreversible y requiere un motivo formal estructurado.
                </p>

                <form id="formCancelarVenta">
                    <input type="hidden" id="cancelarVentaId">
                    <input type="hidden" id="cancelarVersionBloqueo">

                    <div class="mb-3">
                        <label for="selectMotivoCancelacion" class="form-label f-w-600">
                            Motivo de Cancelación <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="selectMotivoCancelacion" required>
                            <option value="">Seleccione un motivo...</option>
                            <option value="DESISTIMIENTO_CLIENTE">Desistimiento formal del cliente</option>
                            <option value="PLAZO_EXPIRADO_PAGO">Plazo expirado para confirmación</option>
                            <option value="CAMBIO_PLANES">Cambio de planes o reprogramación</option>
                            <option value="FUERZA_MAYOR">Fuerza mayor o contingencia</option>
                            <option value="DUPLICIDAD_ERROR">Duplicidad o error de registro</option>
                            <option value="OTRO">Otro motivo (requiere detalle obligatorio)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="grupoDetalleCancelacion">
                        <label for="cancelarMotivoDetalle" class="form-label f-w-600">
                            Detalle Explicativo <span id="reqDetalleCancelacion" class="text-danger d-none">*</span>
                        </label>
                        <textarea class="form-control" id="cancelarMotivoDetalle" rows="3" placeholder="Indique la justificación formal de la cancelación..."></textarea>
                        <div class="invalid-feedback">Debe especificar un detalle explicativo para este motivo.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarCancelacion">
                    <i class="fa-solid fa-ban me-1"></i> Confirmar Cancelación
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 4: ANULAR VENTA
============================================================================== -->
<div class="modal fade" id="modalAnularVenta" tabindex="-1" aria-labelledby="modalAnularVentaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg b-r-16">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title f-w-700 text-dark" id="modalAnularVentaLabel">
                    <i class="fa-solid fa-xmark text-danger me-2"></i> Anulación Administrativa de Venta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <p class="f-s-14 text-secondary mb-3">
                    Procedimiento administrativo para invalidar la venta <strong id="anularCorrelativoTexto" class="text-dark">-</strong>.
                    Esta acción dejará constancia en el historial de auditoría técnica.
                </p>

                <form id="formAnularVenta">
                    <input type="hidden" id="anularVentaId">
                    <input type="hidden" id="anularVersionBloqueo">

                    <div class="mb-3">
                        <label for="selectMotivoAnulacion" class="form-label f-w-600">
                            Motivo de Anulación <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="selectMotivoAnulacion" required>
                            <option value="">Seleccione un motivo...</option>
                            <option value="ERROR_REGISTRO">Error formal en el registro</option>
                            <option value="SOLICITUD_CLIENTE">Solicitud formal de anulación por el cliente</option>
                            <option value="OPERACION_NO_CONCRETADA">Operación comercial no concretada</option>
                            <option value="FRAUDE_SUPLANTACION">Fraude, sospecha o suplantación</option>
                            <option value="OTRO">Otro motivo (requiere justificación obligatoria)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="grupoDetalleAnulacion">
                        <label for="anularMotivoDetalle" class="form-label f-w-600">
                            Detalle Explicativo <span id="reqDetalleAnulacion" class="text-danger d-none">*</span>
                        </label>
                        <textarea class="form-control" id="anularMotivoDetalle" rows="3" placeholder="Indique la justificación formal de la anulación..."></textarea>
                        <div class="invalid-feedback">Debe especificar un detalle explicativo para este motivo.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                <button type="button" class="btn btn-dark" id="btnConfirmarAnulacion">
                    <i class="fa-solid fa-xmark me-1"></i> Confirmar Anulación
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Metadatos de Contexto e Integración para JavaScript -->
<script>
    window.CANDELARIA_PERMISOS = <?= json_encode($permisos, JSON_UNESCAPED_UNICODE) ?>;
    window.CANDELARIA_MONEDA_DEFECTO = '<?= escapar_html($monedaPrincipal) ?>';
</script>
