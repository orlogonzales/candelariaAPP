<!-- recursos/vistas/paginas/dashboard/analitica_multianual.php -->
<div class="row align-items-center mb-4">
    <div class="col-lg-7">
        <div class="d-flex align-items-center gap-2 mb-1">
            <h3 class="f-w-700 text-dark mb-0">
                <i class="fa-solid fa-chart-column text-danger me-2"></i> Analítica Multianual e Inteligencia Histórica
            </h3>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle f-s-11">V1.2</span>
        </div>
        <p class="text-secondary mb-0 f-s-13">
            Comparativa interanual (YoY), curvas de velocidad de preventa (Booking Pace), retención de cohortes, folclore y desempeño operativo entre ediciones.
        </p>
    </div>
    <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
        <div class="d-inline-flex flex-wrap align-items-center gap-2">
            <a href="<?= url_base('dashboard-ejecutivo') ?>" class="btn btn-outline-secondary btn-sm b-r-8 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard Edición
            </a>
            <?php if (!empty($puedeExportar)): ?>
            <div class="btn-group shadow-sm" role="group" aria-label="Exportar Analítica Multianual">
                <button type="button" class="btn btn-outline-success btn-sm" id="btnExportarMultianualCsv" data-formato="csv" title="Descargar CSV (Protegido contra inyección de fórmulas)">
                    <i class="fa-solid fa-file-csv me-1"></i> CSV
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnExportarMultianualXlsx" data-formato="xlsx" title="Descargar Libro Excel (XLSX OpenXML)">
                    <i class="fa-solid fa-file-excel me-1"></i> XLSX
                </button>
                <button type="button" class="btn btn-danger btn-sm" id="btnExportarMultianualPdf" data-formato="pdf" title="Descargar Informe Ejecutivo PDF">
                    <i class="fa-solid fa-file-pdf me-1"></i> PDF Oficial
                </button>
            </div>
            <?php endif; ?>
            <button type="button" class="btn btn-dark btn-sm b-r-8 shadow-sm" id="btnRefrescarAnalitica" title="Recalcular series multianuales">
                <i class="fa-solid fa-rotate" id="iconoRefrescarAnalitica"></i>
            </button>
        </div>
    </div>
</div>

<!-- Barra de Filtros Multianuales -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-body p-3">
        <form id="formFiltrosMultianual" class="row g-2 align-items-end">
            <div class="col-xl-4 col-md-6">
                <label class="form-label f-s-12 f-w-600 text-muted mb-1">
                    <i class="fa-solid fa-calendar-days text-danger me-1"></i> Ediciones a Comparar
                </label>
                <div class="d-flex flex-wrap gap-1 border rounded p-2 bg-light" id="contenedorCheckEdiciones" style="max-height: 76px; overflow-y: auto;">
                    <?php if (empty($ediciones)): ?>
                        <span class="f-s-12 text-muted">Sin ediciones registradas</span>
                    <?php else: ?>
                        <?php foreach ($ediciones as $ed): ?>
                            <div class="form-check form-check-inline mb-0 me-2">
                                <input class="form-check-input check-edicion-comparar" type="checkbox"
                                       id="chkEdicion_<?= (int) $ed['id'] ?>"
                                       value="<?= (int) $ed['id'] ?>" checked>
                                <label class="form-check-label f-s-12 f-w-600" for="chkEdicion_<?= (int) $ed['id'] ?>">
                                    <?= (int) $ed['anio'] ?> <span class="text-muted">(<?= escapar_html($ed['codigo']) ?>)</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-2 col-md-3 col-6">
                <label for="filtroEstadoEdicion" class="form-label f-s-12 f-w-600 text-muted mb-1">Estado Edición</label>
                <select class="form-select form-select-sm b-r-8" id="filtroEstadoEdicion">
                    <option value="">Todos los estados</option>
                    <option value="PREOPERACION">Pre-Operación</option>
                    <option value="OPERACION">En Operación</option>
                    <option value="POSTPRODUCCION_ENTREGA">Postproducción</option>
                    <option value="CERRADA">Cerrada Oficial</option>
                </select>
            </div>

            <div class="col-xl-2 col-md-3 col-6">
                <label for="filtroCanalOrigen" class="form-label f-s-12 f-w-600 text-muted mb-1">Canal Comercial</label>
                <select class="form-select form-select-sm b-r-8" id="filtroCanalOrigen">
                    <option value="">Todos los canales</option>
                    <option value="DIRECTA">Venta Directa / Portal</option>
                    <option value="COTIZACION">Cotización Comercial</option>
                </select>
            </div>

            <div class="col-xl-2 col-md-6">
                <label for="filtroTipoDanza" class="form-label f-s-12 f-w-600 text-muted mb-1">Género Folclórico</label>
                <select class="form-select form-select-sm b-r-8" id="filtroTipoDanza">
                    <option value="">Todos los géneros</option>
                    <?php foreach ($tiposDanza ?? [] as $td): ?>
                        <option value="<?= (int) $td['id'] ?>">
                            <?= escapar_html($td['nombre']) ?> (<?= escapar_html($td['categoria_origen']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-xl-2 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-danger btn-sm w-100 b-r-8">
                    <i class="fa-solid fa-filter me-1"></i> Aplicar
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm b-r-8" id="btnLimpiarFiltrosMultianual" title="Restablecer filtros">
                    <i class="fa-solid fa-eraser"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Estado de Carga -->
<div id="cargandoAnaliticaMultianual" class="text-center py-5">
    <div class="spinner-border text-danger" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Calculando analítica multianual...</span>
    </div>
    <p class="text-muted mt-3 f-s-14">Procesando series históricas inter-ediciones, curvas de preventa y matriz de cohortes...</p>
</div>

<!-- Contenedor Principal de Analítica Multianual -->
<div id="contenidoAnaliticaMultianual" style="display: none;">

    <!-- Fila 1: KPIs Consolidados Multi-Edición -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="f-s-11 text-muted text-uppercase f-w-700">Devengado Histórico Acumulado</span>
                        <div class="w-35 h-35 d-flex-center bg-primary-subtle text-primary rounded-circle">
                            <i class="fa-solid fa-sack-dollar"></i>
                        </div>
                    </div>
                    <h3 class="f-w-700 text-dark mb-1" id="kpiMultiDevengado">S/ 0.00</h3>
                    <div class="f-s-12 text-muted d-flex justify-content-between">
                        <span><strong id="kpiMultiEdicionesQty">0</strong> ediciones</span>
                        <span id="kpiMultiVentasQty">0 ventas activas</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="f-s-11 text-muted text-uppercase f-w-700">Recaudación Neta Consolidada</span>
                        <div class="w-35 h-35 d-flex-center bg-success-subtle text-success rounded-circle">
                            <i class="fa-solid fa-vault"></i>
                        </div>
                    </div>
                    <h3 class="f-w-700 text-success mb-1" id="kpiMultiNeto">S/ 0.00</h3>
                    <div class="f-s-12 text-muted d-flex justify-content-between">
                        <span>Cobranza: <strong id="kpiMultiCobranzaPct">0%</strong></span>
                        <span>Comisiones: <span class="text-danger" id="kpiMultiComisiones">S/ 0.00</span></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="f-s-11 text-muted text-uppercase f-w-700">Variación Interanual (YoY Prom.)</span>
                        <div class="w-35 h-35 d-flex-center bg-danger-subtle text-danger rounded-circle">
                            <i class="fa-solid fa-arrow-trend-up"></i>
                        </div>
                    </div>
                    <h3 class="f-w-700 text-dark mb-1" id="kpiMultiCrecimientoYoy">N/A</h3>
                    <div class="f-s-12 text-muted d-flex justify-content-between">
                        <span>Ticket Global: <strong id="kpiMultiTicketGlobal">S/ 0.00</strong></span>
                        <span id="kpiMultiMejorEdicion">Mejor: -</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="f-s-11 text-muted text-uppercase f-w-700">Fidelización y Escala Cultural</span>
                        <div class="w-35 h-35 d-flex-center bg-info-subtle text-info rounded-circle">
                            <i class="fa-solid fa-users-rays"></i>
                        </div>
                    </div>
                    <h3 class="f-w-700 text-dark mb-1" id="kpiMultiRecurrenciaPct">0.0%</h3>
                    <div class="f-s-12 text-muted d-flex justify-content-between">
                        <span><strong id="kpiMultiConjuntos">0</strong> conjuntos</span>
                        <span><strong id="kpiMultiDanzarines">0</strong> danzarines</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fila 2: Gráfico Comparativo Multianual SVG/CSS + Tabla Financiera YoY -->
    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="f-w-700 text-dark mb-0">
                        <i class="fa-solid fa-chart-bar text-primary me-2"></i> Evolución Devengado vs Recaudación Neta
                    </h6>
                    <span class="badge bg-light text-dark border f-s-11">Comparativa Anual</span>
                </div>
                <div class="card-body p-3" id="contenedorGraficoBarrasMultianual">
                    <!-- Renderizado dinámico por JS -->
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="f-w-700 text-dark mb-0">
                        <i class="fa-solid fa-scale-balanced text-danger me-2"></i> Matriz Financiera y Variación Interanual (YoY)
                    </h6>
                    <span class="badge bg-success-subtle text-success f-s-11">Cálculo Exacto en Centavos</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tablaSerieFinanciera">
                            <thead class="bg-light f-s-11 text-muted text-uppercase">
                                <tr>
                                    <th class="ps-3">Edición</th>
                                    <th class="text-end">Ventas Act.</th>
                                    <th class="text-end">Devengado</th>
                                    <th class="text-center">Var. YoY</th>
                                    <th class="text-end">Neto Recaudado</th>
                                    <th class="text-end">Saldo Pend.</th>
                                    <th class="text-end">Anulado/Canc.</th>
                                    <th class="text-end pe-3">Ticket Prom.</th>
                                </tr>
                            </thead>
                            <tbody class="f-s-13">
                                <!-- Dinámico -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fila 3: Curvas de Preventa (Booking Pace) y Retención / Cohortes de Clientes -->
    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="f-w-700 text-dark mb-0">
                            <i class="fa-solid fa-stopwatch text-warning me-2"></i> Curvas de Preventa (Booking Pace)
                        </h6>
                        <small class="text-muted f-s-11">Velocidad de contratación acumulada respecto al día de inicio de cada edición (T-90 a Cierre)</small>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div id="contenedorGraficoPreventa" class="mb-3"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0 f-s-12" id="tablaCurvasPreventa">
                            <thead class="bg-light text-muted text-uppercase f-s-11">
                                <tr>
                                    <th>Edición</th>
                                    <th class="text-end">T-90</th>
                                    <th class="text-end">T-60</th>
                                    <th class="text-end">T-30</th>
                                    <th class="text-end">T-15</th>
                                    <th class="text-end">T-7</th>
                                    <th class="text-end">T-0 (Inicio)</th>
                                    <th class="text-end">Final (100%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinámico -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="f-w-700 text-dark mb-0">
                            <i class="fa-solid fa-people-arrows text-info me-2"></i> Retención de Clientes y Matriz de Cohortes
                        </h6>
                        <small class="text-muted f-s-11">Clientes nuevos vs recurrentes y seguimiento de recompra inter-ediciones</small>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-hover align-middle mb-0 f-s-12" id="tablaRetencionClientes">
                            <thead class="bg-light text-muted text-uppercase f-s-11">
                                <tr>
                                    <th>Edición</th>
                                    <th class="text-end">Clientes Únicos</th>
                                    <th class="text-end">Nuevos</th>
                                    <th class="text-end">Recurrentes</th>
                                    <th class="text-end">% Recurrencia</th>
                                    <th class="text-end">Ingresos Nuevos</th>
                                    <th class="text-end">Ingresos Recurrentes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinámico -->
                            </tbody>
                        </table>
                    </div>
                    <h6 class="f-s-12 f-w-700 text-uppercase text-muted mb-2">
                        <i class="fa-solid fa-table-cells me-1"></i> Matriz de Cohortes de Adquisición
                    </h6>
                    <div class="table-responsive" id="contenedorMatrizCohortes">
                        <!-- Dinámico -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fila 4: Participación Folclórica Multianual y Desempeño Operativo / Postproducción -->
    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="f-w-700 text-dark mb-0">
                        <i class="fa-solid fa-masks-theater text-danger me-2"></i> Participación Folclórica Multianual (Módulo 07)
                    </h6>
                    <span class="badge bg-danger-subtle text-danger f-s-11">Conjuntos y Danzarines</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 f-s-12" id="tablaSerieFolclore">
                            <thead class="bg-light text-muted text-uppercase f-s-11">
                                <tr>
                                    <th class="ps-3">Edición</th>
                                    <th class="text-end">Conjuntos Act.</th>
                                    <th class="text-end">Danzarines Est.</th>
                                    <th class="text-end">Bandas</th>
                                    <th class="text-end">Turnos (Fin/Tot)</th>
                                    <th class="text-end">Retraso Prom.</th>
                                    <th class="pe-3">Distribución Categorías</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinámico -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="f-w-700 text-dark mb-0">
                        <i class="fa-solid fa-qrcode text-success me-2"></i> Operaciones, Asistencia QR (Online+Offline) y Postproducción
                    </h6>
                    <span class="badge bg-success-subtle text-success f-s-11">Campo y Multimedia</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 f-s-12" id="tablaSerieOperacionesPost">
                            <thead class="bg-light text-muted text-uppercase f-s-11">
                                <tr>
                                    <th class="ps-3">Edición</th>
                                    <th class="text-end">Salidas / Flota</th>
                                    <th class="text-end">Ocupación</th>
                                    <th class="text-end">Asistencia QR</th>
                                    <th class="text-end">Sync Offline</th>
                                    <th class="text-end">Entregas Aprob.</th>
                                    <th class="text-end pe-3">Fotos Selec.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinámico -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
