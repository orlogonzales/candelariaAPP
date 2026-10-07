<!-- ==============================================================================
     VISTA OFICIAL: DESPACHO DE ENTREGAS Y PRODUCTOS FÍSICOS (operaciones/entregas.php) - FASE 2.6E
     Control de Órdenes de Entrega de Bienes Tangibles con Alina UI
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-box-open text-primary me-2"></i> Despacho de Entregas y Productos Físicos
        </h3>
        <p class="text-secondary mb-0">
            Control de entrega física de bienes tangibles, indumentaria, merchandising y souvenirs asociados a ventas.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarEntregas" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen / KPIs -->
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Órdenes</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalEntregas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Pendientes de Entrega</span>
                    <h3 class="f-w-700 mb-0 mt-1 text-warning" id="kpiPendientesEntregas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-sm-12">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Entregados con Éxito</span>
                    <h3 class="f-w-700 mb-0 mt-1 text-success" id="kpiCompletadasEntregas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros y Tabla -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-body p-3">
        <div class="row g-2 mb-3 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control border-start-0" id="filtroBusqueda" placeholder="Buscar por correlativo, venta o contacto...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="filtroEstado">
                    <option value="">Todos los Estados</option>
                    <option value="PENDIENTE">PENDIENTE (Por entregar)</option>
                    <option value="ENTREGADO">ENTREGADO (Despachado)</option>
                    <option value="CANCELADO">CANCELADO</option>
                </select>
            </div>
            <div class="col-md-5 text-md-end">
                <span class="text-muted f-s-12">Órdenes generadas automáticamente al formalizar ventas de bienes.</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle w-100" id="tablaEntregas">
                <thead class="table-light">
                    <tr>
                        <th class="f-w-600 text-uppercase f-s-12">Orden Entrega</th>
                        <th class="f-w-600 text-uppercase f-s-12">Venta Origen</th>
                        <th class="f-w-600 text-uppercase f-s-12">Destinatario</th>
                        <th class="f-w-600 text-uppercase f-s-12">Punto / Dirección</th>
                        <th class="f-w-600 text-uppercase f-s-12">Artículos</th>
                        <th class="f-w-600 text-uppercase f-s-12">Fecha Despacho</th>
                        <th class="f-w-600 text-uppercase f-s-12">Estado</th>
                        <th class="f-w-600 text-uppercase f-s-12 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: DETALLE Y DESPACHO DE ENTREGA (modalDetalleEntrega)
============================================================================== -->
<div class="modal fade" id="modalDetalleEntrega" tabindex="-1" aria-labelledby="tituloModalDetalleEntrega" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="tituloModalDetalleEntrega">
                    <i class="fa-solid fa-box text-primary me-2"></i> Orden de Entrega <span id="lblDetalleCorrelativo" class="text-primary"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted f-s-11 text-uppercase f-w-600 mb-0">Destinatario</label>
                        <div class="f-w-700 f-s-15 text-dark" id="lblDetalleContactoNombre">-</div>
                        <div class="text-muted f-s-12" id="lblDetalleContactoTelefono">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted f-s-11 text-uppercase f-w-600 mb-0">Estado</label>
                        <div id="lblDetalleEstadoBadge" class="mt-1">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted f-s-11 text-uppercase f-w-600 mb-0">Dirección / Punto de Entrega</label>
                        <div class="f-w-600 text-dark f-s-13" id="lblDetalleDireccion">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted f-s-11 text-uppercase f-w-600 mb-0">Fecha y Hora de Despacho</label>
                        <div class="f-w-600 text-dark f-s-13" id="lblDetalleFecha">-</div>
                    </div>
                    <div class="col-12" id="contenedorNotasDespacho" style="display: none;">
                        <label class="form-label text-muted f-s-11 text-uppercase f-w-600 mb-0">Notas de Despacho</label>
                        <div class="alert alert-light border mb-0 f-s-13" id="lblDetalleNotas">-</div>
                    </div>
                </div>

                <h6 class="f-w-700 text-dark mb-2">
                    <i class="fa-solid fa-list-check me-1 text-primary"></i> Ítems / Bienes Incluidos en la Orden
                </h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle" id="tablaItemsEntrega">
                        <thead class="table-light">
                            <tr>
                                <th class="f-w-600 f-s-12">Código</th>
                                <th class="f-w-600 f-s-12">Concepto / Producto</th>
                                <th class="f-w-600 f-s-12 text-center">Unidad</th>
                                <th class="f-w-600 f-s-12 text-center">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyItemsEntrega">
                            <!-- Items cargados dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <?php if (!empty($permisos['despachar'])): ?>
                <button type="button" class="btn btn-success btn-sm" id="btnDespacharDesdeModal">
                    <i class="fa-solid fa-truck-ramp-box me-1"></i> Confirmar Despacho Físico
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
