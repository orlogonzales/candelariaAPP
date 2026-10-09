<!-- ==============================================================================
     VISTA OFICIAL: GESTIÓN DE FINANZAS, PAGOS Y PASARELAS (index.php) - FASE 2.7E
     Proyección fiel del Núcleo Financiero (F2.7C/F2.7D) con Componentes Alina UI
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-cash-register text-primary me-2"></i> Finanzas, Cobranzas y Caja
        </h3>
        <p class="text-secondary mb-0">
            Libro mayor append-only de pagos, verificación de vouchers, cuentas corporativas y pasarelas.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTodo" title="Recargar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <?php if (!empty($permisos['registrarManual'])): ?>
            <button type="button" class="btn bg-gradient-success btn-md text-white shadow-sm" id="btnAbrirModalPago">
                <i class="fa-solid fa-hand-holding-dollar me-2"></i> Registrar Pago
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen / KPIs Financieros -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Recaudado (Neto)</span>
                    <h3 class="f-w-700 text-success mb-0 mt-1" id="kpiTotalRecaudado">S/ 0.00</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Pagos Aprobados</span>
                    <h3 class="f-w-700 text-primary mb-0 mt-1" id="kpiPagosAprobados">0</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Por Verificar (Vouchers)</span>
                    <h3 class="f-w-700 text-warning mb-0 mt-1" id="kpiPagosPendientes">0</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Reembolsos Emitidos</span>
                    <h3 class="f-w-700 text-danger mb-0 mt-1" id="kpiReembolsosTotal">S/ 0.00</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-danger-subtle text-danger rounded-circle f-s-20">
                    <i class="fa-solid fa-arrow-rotate-left"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Navegación por Pestañas Alina (Tabs) -->
<ul class="nav nav-tabs nav-tabs-bordered mb-4" id="finanzasTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-pagos-link" data-bs-toggle="tab" data-bs-target="#tab-pagos" type="button" role="tab">
            <i class="fa-solid fa-list-check me-2"></i> Libro de Pagos y Transacciones
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-cuentas-link" data-bs-toggle="tab" data-bs-target="#tab-cuentas" type="button" role="tab">
            <i class="fa-solid fa-building-columns me-2"></i> Cuentas Bancarias & Billeteras
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-pasarelas-link" data-bs-toggle="tab" data-bs-target="#tab-pasarelas" type="button" role="tab">
            <i class="fa-solid fa-credit-card me-2"></i> Pasarelas de Pago
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-calculadora-link" data-bs-toggle="tab" data-bs-target="#tab-calculadora" type="button" role="tab">
            <i class="fa-solid fa-calculator me-2"></i> Calculadora de Comisiones
        </button>
    </li>
</ul>

<!-- Contenido de Pestañas -->
<div class="tab-content" id="finanzasTabsContent">

    <!-- =======================================================================
         TAB 1: LIBRO DE PAGOS Y TRANSACCIONES
         ======================================================================= -->
    <div class="tab-pane fade show active" id="tab-pagos" role="tabpanel">
        <!-- Tarjeta con Filtros -->
        <div class="card border-0 shadow-sm b-r-12 mb-4">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label f-s-12 text-muted mb-1">Buscar por Código, Cliente o Ref.</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
                            <input type="text" class="form-control border-start-0" id="filtroBusqueda" placeholder="PAG-..., cliente, boucher...">
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label f-s-12 text-muted mb-1">Estado de Pago</label>
                        <select class="form-select form-select-sm" id="filtroEstado">
                            <option value="">Todos los Estados</option>
                            <option value="APROBADO">Aprobado</option>
                            <option value="PENDIENTE_VERIFICACION">Pendiente Verificación</option>
                            <option value="RECHAZADO">Rechazado</option>
                            <option value="ANULADO">Anulado</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label f-s-12 text-muted mb-1">Método de Pago</label>
                        <select class="form-select form-select-sm" id="filtroMetodo">
                            <option value="">Todos los Métodos</option>
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TRANSFERENCIA_BANCARIA">Transferencia</option>
                            <option value="BILLETERA_DIGITAL">Billetera Digital (Yape/Plin)</option>
                            <option value="TARJETA_PASARELA">Tarjeta / Pasarela Web</option>
                            <option value="POS_FISICO">POS Físico</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label f-s-12 text-muted mb-1">Fecha Desde</label>
                        <input type="date" class="form-control form-control-sm" id="filtroFechaDesde">
                    </div>
                    <div class="col-md-3 col-sm-12 text-end mt-auto pt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnLimpiarFiltros">
                            <i class="fa-solid fa-filter-circle-xmark me-1"></i> Limpiar
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="btnAplicarFiltros">
                            <i class="fa-solid fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Skeleton Loader para Tabla -->
        <div id="skeletonPagos" class="card border-0 shadow-sm b-r-12 p-4 d-none">
            <div class="placeholder-glow">
                <span class="placeholder col-12 py-3 mb-2 b-r-8 bg-secondary-subtle"></span>
                <span class="placeholder col-12 py-3 mb-2 b-r-8 bg-secondary-subtle"></span>
                <span class="placeholder col-12 py-3 mb-2 b-r-8 bg-secondary-subtle"></span>
                <span class="placeholder col-12 py-3 mb-2 b-r-8 bg-secondary-subtle"></span>
            </div>
        </div>

        <!-- Tabla DataTables de Pagos -->
        <div class="card border-0 shadow-sm b-r-12" id="contenedorTablaPagos">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100" id="tablaPagos">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Correlativo</th>
                                <th>Fecha</th>
                                <th>Venta / Cliente</th>
                                <th>Método</th>
                                <th>Monto Cobrado</th>
                                <th>Desglose Financiero</th>
                                <th>Voucher</th>
                                <th>Estado</th>
                                <th class="text-end pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoTablaPagos">
                            <!-- Inserción dinámica por DataTables / Fetch -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- =======================================================================
         TAB 2: CUENTAS BANCARIAS & BILLETERAS
         ======================================================================= -->
    <div class="tab-pane fade" id="tab-cuentas" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="f-w-700 text-dark mb-0">Cuentas Bancarias de la Organización</h5>
            <?php if (!empty($permisos['gestionarCuentas'])): ?>
            <button type="button" class="btn btn-primary btn-sm" id="btnNuevaCuentaBancaria">
                <i class="fa-solid fa-plus me-1"></i> Nueva Cuenta
            </button>
            <?php endif; ?>
        </div>

        <!-- Grid de Cuentas Bancarias -->
        <div class="row g-3" id="gridCuentasBancarias">
            <!-- Renderizado dinámico de tarjetas bancarias -->
        </div>
    </div>

    <!-- =======================================================================
         TAB 3: PASARELAS DE PAGO Y WEBHOOKS
         ======================================================================= -->
    <div class="tab-pane fade" id="tab-pasarelas" role="tabpanel">
        <div class="alert alert-info border-0 shadow-sm b-r-12 d-flex align-items-center gap-3 mb-4">
            <i class="fa-solid fa-shield-halved f-s-24 text-info"></i>
            <div>
                <strong class="d-block f-s-14">Seguridad Financiera y Modo Sandbox Preventivo</strong>
                <span class="f-s-13 text-secondary">
                    Las credenciales secretas se almacenan cifradas con AES-256-GCM. Los adaptadores técnicos operan en modo Sandbox y permanecen deshabilitados para producción hasta su homologación oficial.
                </span>
            </div>
        </div>

        <div class="row g-3" id="gridPasarelas">
            <!-- Tarjetas de Pasarelas del Catálogo -->
        </div>
    </div>

    <!-- =======================================================================
         TAB 4: CALCULADORA DE COMISIONES
         ======================================================================= -->
    <div class="tab-pane fade" id="tab-calculadora" role="tabpanel">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm b-r-12">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="f-w-700 text-dark mb-1">
                            <i class="fa-solid fa-sliders text-primary me-2"></i> Parámetros de Simulación
                        </h5>
                        <p class="text-secondary f-s-13 mb-0">Calcula los costos de pasarela y el importe neto a percibir.</p>
                    </div>
                    <div class="card-body p-4">
                        <form id="formCalculadora">
                            <div class="mb-3">
                                <label class="form-label f-w-600 f-s-13">Monto Base de Venta (S/)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">S/</span>
                                    <input type="number" step="0.01" min="1" class="form-control form-control-lg" id="calcMontoBase" value="1000.00" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label f-w-600 f-s-13">Pasarela / Canal de Pago</label>
                                <select class="form-select" id="calcPasarela">
                                    <option value="culqi" data-pct="3.99" data-fija="1.00">Culqi Online (3.99% + S/ 1.00 + IGV)</option>
                                    <option value="stripe" data-pct="3.99" data-fija="1.00">Stripe Payments (3.99% + S/ 1.00 + IGV)</option>
                                    <option value="niubiz" data-pct="3.45" data-fija="0.50">Niubiz Pago Web (3.45% + S/ 0.50 + IGV)</option>
                                    <option value="mercadopago" data-pct="3.99" data-fija="1.00">Mercado Pago Perú (3.99% + S/ 1.00 + IGV)</option>
                                    <option value="manual" data-pct="0.00" data-fija="0.00">Transferencia / Billetera / Efectivo (0% Comisión)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label f-w-600 f-s-13">¿Quién asume la comisión de pasarela?</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="calcAsuncion" id="asumeOrg" value="ORGANIZACION" checked>
                                        <label class="form-check-label f-s-13" for="asumeOrg">
                                            La Organización (Se deduce de la venta)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="calcAsuncion" id="asumeCli" value="CLIENTE">
                                        <label class="form-check-label f-s-13" for="asumeCli">
                                            El Cliente (Recargo transparente)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm b-r-12 bg-light">
                    <div class="card-header bg-transparent border-0 pt-4 pb-0">
                        <h5 class="f-w-700 text-dark mb-1">
                            <i class="fa-solid fa-receipt text-success me-2"></i> Desglose Transparente
                        </h5>
                        <p class="text-secondary f-s-13 mb-0">Impacto contable en la venta y acreditación bancaria.</p>
                    </div>
                    <div class="card-body p-4">
                        <ul class="list-group list-group-flush b-r-8 mb-3">
                            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-secondary">Monto Cobrado al Cliente</span>
                                <strong class="f-s-16 text-dark" id="calcResCobrado">S/ 1,000.00</strong>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-secondary">Comisión Porcentual de Pasarela</span>
                                <span class="text-danger" id="calcResComisionPct">- S/ 39.90</span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-secondary">Comisión Fija por Transacción</span>
                                <span class="text-danger" id="calcResComisionFija">- S/ 1.00</span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-secondary">IGV de la Comisión (18%)</span>
                                <span class="text-danger" id="calcResComisionIgv">- S/ 7.36</span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0 py-2 border-top">
                                <strong class="text-dark">Total Comisión Deducida</strong>
                                <strong class="text-danger f-s-15" id="calcResComisionTotal">S/ 48.26</strong>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0 py-3 border-top border-2">
                                <div>
                                    <strong class="d-block text-dark f-s-15">Monto Neto Recibido en Cuenta</strong>
                                    <span class="f-s-12 text-muted">Acreditación líquida para la organización</span>
                                </div>
                                <span class="f-s-20 f-w-700 text-success" id="calcResNetoRecibido">S/ 951.74</span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-secondary">Monto Amortizado a la Deuda de Venta</span>
                                <strong class="text-primary f-s-15" id="calcResAplicadoVenta">S/ 1,000.00</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ==============================================================================
     MODALES INTERACTIVOS ALINA UI
============================================================================== -->

<!-- 1. Modal Registrar Pago Manual -->
<div class="modal fade" id="modalRegistrarPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow b-r-16">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title f-w-700 text-dark">
                    <i class="fa-solid fa-hand-holding-dollar text-success me-2"></i> Registrar Pago Manual
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formRegistrarPago" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Nucleo\Seguridad\ProtectorCsrf::obtenerOCrearToken()) ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Selección de Venta -->
                        <div class="col-md-8">
                            <label class="form-label f-w-600 f-s-13">Venta Comercial <span class="text-danger">*</span></label>
                            <select class="form-select" id="pagoVentaId" name="venta_id" required>
                                <option value="">Seleccione una venta...</option>
                            </select>
                            <div class="invalid-feedback">Debe seleccionar una venta válida.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Saldo Pendiente</label>
                            <input type="text" class="form-control bg-light f-w-700 text-primary" id="pagoSaldoInfo" readonly value="S/ 0.00">
                        </div>

                        <!-- Método y Moneda -->
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Método de Pago <span class="text-danger">*</span></label>
                            <select class="form-select" id="pagoMetodo" name="metodo_pago" required>
                                <option value="EFECTIVO">Efectivo en Caja</option>
                                <option value="TRANSFERENCIA_BANCARIA">Transferencia Bancaria</option>
                                <option value="BILLETERA_DIGITAL">Billetera Digital (Yape / Plin)</option>
                                <option value="POS_FISICO">POS Físico / Tarjeta</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-w-600 f-s-13">Moneda</label>
                            <select class="form-select" id="pagoMoneda" name="moneda">
                                <option value="PEN" selected>PEN (Soles)</option>
                                <option value="USD">USD (Dólares)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-w-600 f-s-13">Monto Cobrado <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control f-w-700 text-dark" id="pagoMonto" name="monto" placeholder="0.00" required>
                        </div>

                        <!-- Datos Bancarios Condicionales -->
                        <div class="col-md-6 seccion-bancaria d-none">
                            <label class="form-label f-w-600 f-s-13">Cuenta Destino</label>
                            <select class="form-select" id="pagoCuentaBancariaId" name="cuenta_bancaria_id">
                                <option value="">Seleccione cuenta de abono...</option>
                            </select>
                        </div>
                        <div class="col-md-6 seccion-bancaria d-none">
                            <label class="form-label f-w-600 f-s-13">Número de Operación</label>
                            <input type="text" class="form-control" id="pagoNumeroOperacion" name="numero_operacion_bancaria" placeholder="Ej: OP-987654321">
                        </div>

                        <!-- URL o Voucher -->
                        <div class="col-12 seccion-bancaria d-none">
                            <label class="form-label f-w-600 f-s-13">Enlace o Referencia del Voucher</label>
                            <input type="url" class="form-control" id="pagoBoucherUrl" name="boucher_comprobante_url" placeholder="https://... o ruta relativa de archivo">
                            <span class="f-s-11 text-muted">URL protegida a la captura del boucher o comprobante emitido por el banco.</span>
                        </div>

                        <!-- Notas Operativas -->
                        <div class="col-12">
                            <label class="form-label f-w-600 f-s-13">Notas Operativas</label>
                            <textarea class="form-control" id="pagoNotas" name="notas_operativas" rows="2" placeholder="Observaciones contables, caja, cajero..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnGuardarPago">
                        <i class="fa-solid fa-check me-1"></i> Confirmar y Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Modal Verificación de Voucher / Comprobante -->
<div class="modal fade" id="modalVerificarVoucher" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow b-r-16">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title f-w-700 text-dark">
                    <i class="fa-solid fa-receipt text-warning me-2"></i> Verificación de Comprobante / Voucher
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="verifPagoId">
                <input type="hidden" id="verifVersionBloqueo">

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light b-r-12 h-100">
                            <h6 class="f-w-700 text-dark mb-3">Datos del Depósito Declarado</h6>
                            <ul class="list-unstyled mb-0 f-s-13">
                                <li class="mb-2"><span class="text-muted">Correlativo:</span> <strong id="verifCorrelativo" class="text-dark">-</strong></li>
                                <li class="mb-2"><span class="text-muted">Venta:</span> <strong id="verifVentaCorrelativo" class="text-dark">-</strong></li>
                                <li class="mb-2"><span class="text-muted">Monto:</span> <strong id="verifMonto" class="text-success f-s-15">-</strong></li>
                                <li class="mb-2"><span class="text-muted">Método:</span> <span id="verifMetodo" class="badge bg-secondary">-</span></li>
                                <li class="mb-2"><span class="text-muted">Banco Destino:</span> <span id="verifBanco">-</span></li>
                                <li class="mb-2"><span class="text-muted">N° Operación:</span> <strong id="verifNumOperacion" class="text-primary">-</strong></li>
                                <li><span class="text-muted">Fecha Declarada:</span> <span id="verifFecha">-</span></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border b-r-12 text-center h-100 d-flex flex-column justify-content-center align-items-center bg-white">
                            <h6 class="f-w-700 text-dark mb-2">Vista Previa del Voucher</h6>
                            <div id="verifContenedorVoucher" class="w-100 my-auto text-center">
                                <span class="text-muted f-s-13">Sin imagen de comprobante</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label f-w-600 f-s-13">Notas de Verificación / Conciliación</label>
                        <textarea class="form-control" id="verifNotas" rows="2" placeholder="Observaciones de caja o motivo de rechazo si corresponde..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger" id="btnRechazarVoucher">
                    <i class="fa-solid fa-xmark me-1"></i> Rechazar Depósito
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success" id="btnAprobarVoucher">
                        <i class="fa-solid fa-check-double me-1"></i> Aprobar Depósito Bancario
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. Modal Detalle 360° y Estado de Cuenta de Venta -->
<div class="modal fade" id="modalEstadoCuentaVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow b-r-16">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title f-w-700 text-dark mb-0">
                        <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> Estado de Cuenta de Venta
                    </h5>
                    <span class="f-s-13 text-muted" id="ctaCorrelativoVenta">Venta: -</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Tarjetas de Balance -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-light b-r-12 text-center">
                            <span class="f-s-11 text-muted text-uppercase d-block">Total Contractual</span>
                            <h4 class="f-w-700 text-dark mb-0" id="ctaTotalContractual">S/ 0.00</h4>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-success-subtle b-r-12 text-center">
                            <span class="f-s-11 text-success text-uppercase d-block">Total Amortizado</span>
                            <h4 class="f-w-700 text-success mb-0" id="ctaMontoPagadoNeto">S/ 0.00</h4>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-danger-subtle b-r-12 text-center">
                            <span class="f-s-11 text-danger text-uppercase d-block">Saldo Pendiente</span>
                            <h4 class="f-w-700 text-danger mb-0" id="ctaSaldoPendiente">S/ 0.00</h4>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-info-subtle b-r-12 text-center">
                            <span class="f-s-11 text-info text-uppercase d-block">Saldo a Favor (Excedente)</span>
                            <h4 class="f-w-700 text-info mb-0" id="ctaSaldoAFavor">S/ 0.00</h4>
                        </div>
                    </div>
                </div>

                <!-- Historial de Pagos de la Venta -->
                <h6 class="f-w-700 text-dark mb-2">Historial de Cobros Imputados</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light f-s-12">
                            <tr>
                                <th>Correlativo</th>
                                <th>Fecha</th>
                                <th>Método</th>
                                <th>Monto Cobrado</th>
                                <th>Amortizado a Venta</th>
                                <th>Reembolsado</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="ctaCuerpoPagos" class="f-s-13">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>

                <!-- Política de Reserva y Liquidación -->
                <div class="d-flex justify-content-between align-items-center p-3 bg-light b-r-12">
                    <div>
                        <strong class="d-block f-s-13 text-dark">Política de Reserva Mínima:</strong>
                        <span class="f-s-12 text-secondary" id="ctaPoliticaReservaTexto">Monto mínimo para formalizar servicio: S/ 0.00</span>
                    </div>
                    <div id="ctaContenedorLiquidar">
                        <!-- Botón Liquidar si saldo <= 0 -->
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- 4. Modal Emitir Reembolso Compensatorio -->
<div class="modal fade" id="modalEmitirReembolso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow b-r-16">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title f-w-700 text-danger">
                    <i class="fa-solid fa-arrow-rotate-left me-2"></i> Emitir Reembolso Compensatorio
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEmitirReembolso" novalidate>
                <input type="hidden" id="reemPagoId">
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0 p-3 b-r-12 f-s-13 mb-3">
                        <i class="fa-solid fa-circle-exclamation me-1"></i>
                        Esta acción registrará un asiento compensatorio inmutable. El saldo disponible reembolsable de este cobro es: <strong id="reemSaldoDisponibleTexto">S/ 0.00</strong>.
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Monto a Reembolsar (S/) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control f-w-700 text-danger" id="reemMonto" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Motivo Oficial <span class="text-danger">*</span></label>
                        <select class="form-select" id="reemMotivo" required>
                            <option value="">Seleccione motivo auditado...</option>
                            <option value="DESISTIMIENTO_CLIENTE">Desistimiento del Cliente</option>
                            <option value="FUERZA_MAYOR_CLIMA">Fuerza Mayor / Clima</option>
                            <option value="ERROR_DUPLICIDAD_PAGO">Error / Duplicidad de Cobro</option>
                            <option value="AJUSTE_COMERCIAL">Ajuste Comercial Autorizado</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Detalle Explicativo Obligatorio <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reemDetalle" rows="2" placeholder="Justificación detallada del extorno o devolución..." required></textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label f-w-600 f-s-13">ID Transacción Externa / Transferencia</label>
                        <input type="text" class="form-control" id="reemTransExterna" placeholder="Código de extorno bancario o de pasarela">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="btnConfirmarReembolso">
                        <i class="fa-solid fa-arrow-rotate-left me-1"></i> Ejecutar Reembolso
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 5. Modal Cuenta Bancaria (Crear / Editar) -->
<div class="modal fade" id="modalCuentaBancaria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow b-r-16">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title f-w-700 text-dark" id="modalCuentaTitulo">
                    <i class="fa-solid fa-building-columns text-primary me-2"></i> Cuenta Bancaria Institucional
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCuentaBancaria" novalidate>
                <input type="hidden" id="ctaId">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Entidad Financiera <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ctaBanco" placeholder="BCP, BBVA, Interbank, Yape, etc." required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-w-600 f-s-13">Tipo de Cuenta <span class="text-danger">*</span></label>
                            <select class="form-select" id="ctaTipo" required>
                                <option value="CORRIENTE">Cuenta Corriente</option>
                                <option value="AHORROS">Cuenta de Ahorros</option>
                                <option value="BILLETERA_DIGITAL">Billetera Digital</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-w-600 f-s-13">Moneda <span class="text-danger">*</span></label>
                            <select class="form-select" id="ctaMoneda" required>
                                <option value="PEN">PEN (Soles)</option>
                                <option value="USD">USD (Dólares)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Titular de la Cuenta <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ctaTitular" placeholder="Razón social o nombre oficial" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Número de Cuenta <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ctaNumero" placeholder="191-..." required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Código Interbancario (CCI)</label>
                            <input type="text" class="form-control" id="ctaCci" placeholder="002-191-...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Alias / Identificador Corto</label>
                            <input type="text" class="form-control" id="ctaAlias" placeholder="Ej: BCP Soles Principal">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label f-w-600 f-s-13">URL Imagen QR (Para Billeteras)</label>
                            <input type="url" class="form-control" id="ctaQrUrl" placeholder="https://.../qr.png">
                        </div>
                        <div class="col-md-4 d-flex align-items-center pt-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="ctaActivo" checked>
                                <label class="form-check-label f-s-13" for="ctaActivo">Cuenta Activa</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label f-w-600 f-s-13">Instrucciones de Pago para el Cliente</label>
                            <textarea class="form-control" id="ctaInstrucciones" rows="2" placeholder="Indicaciones para comprobantes y transferencias..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCuenta">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cuenta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 6. Modal Configurar Pasarela -->
<div class="modal fade" id="modalConfigurarPasarela" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow b-r-16">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title f-w-700 text-dark">
                    <i class="fa-solid fa-gears text-primary me-2"></i> Configurar Pasarela: <span id="pasarelaNombreModal">-</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formConfigurarPasarela" novalidate>
                <input type="hidden" id="pasarelaCodigoModal">
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0 p-3 b-r-12 f-s-13 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        <strong>Aviso de Seguridad:</strong> Por directiva de gobierno, las transacciones productivas permanecen deshabilitadas por defecto. Opere en ambiente <strong>SANDBOX</strong> con llaves de prueba.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Ambiente Operativo</label>
                            <select class="form-select" id="pasAmbiente">
                                <option value="SANDBOX" selected>SANDBOX (Pruebas / Simulación)</option>
                                <option value="PRODUCCION" disabled>PRODUCCIÓN (Pendiente de Homologación)</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-center pt-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="pasActivo">
                                <label class="form-check-label f-s-13" for="pasActivo">Habilitar Pasarela para el Tenant</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Llave Pública / Identificador de Comercio</label>
                            <input type="text" class="form-control" id="pasLlavePublica" placeholder="pk_test_... o Código de Comercio">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Llave Secreta / Token de Acceso (AES-256-GCM)</label>
                            <input type="password" class="form-control" id="pasCredencialSecreta" placeholder="sk_test_... (Dejar en blanco para mantener actual)">
                        </div>

                        <div class="col-12">
                            <label class="form-label f-w-600 f-s-13">Secreto del Webhook de Notificaciones</label>
                            <input type="password" class="form-control" id="pasWebhookSecreto" placeholder="whsec_... (Dejar en blanco para mantener actual)">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Comisión Porcentual (%)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="pasComisionPct" placeholder="3.99">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Comisión Fija (S/)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="pasComisionFija" placeholder="1.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Asunción de Comisión</label>
                            <select class="form-select" id="pasAsumeComision">
                                <option value="ORGANIZACION">Organización</option>
                                <option value="CLIENTE">Cliente</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarPasarela">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
