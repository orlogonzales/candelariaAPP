<!-- resources/vistas/paginas/dashboard/ejecutivo.php -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-chart-line text-danger me-2"></i> Dashboard Ejecutivo
        </h3>
        <p class="text-secondary mb-0">
            Consolidado gerencial de festividades: ventas, recaudación, ocupación operativa, asistencia y postproducción.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex align-items-center gap-2">
            <label for="selectorEdicionDashboard" class="f-s-13 text-muted f-w-600 mb-0 d-none d-sm-inline">Edición:</label>
            <select class="form-select form-select-md b-r-8 shadow-sm" id="selectorEdicionDashboard" style="min-width: 200px;">
                <?php if (empty($ediciones)): ?>
                    <option value="">(Sin ediciones registradas)</option>
                <?php else: ?>
                    <?php foreach ($ediciones as $ed): ?>
                        <option value="<?= (int) $ed['id'] ?>" <?= !empty($ed['es_actual']) ? 'selected' : '' ?>>
                            <?= escapar_html($ed['nombre']) ?> (<?= escapar_html($ed['codigo']) ?>) <?= !empty($ed['es_actual']) ? '★' : '' ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <button type="button" class="btn btn-outline-secondary btn-md shadow-sm" id="btnRefrescarDashboard" title="Actualizar métricas">
                <i class="fa-solid fa-rotate" id="iconoRefrescar"></i>
            </button>
            <a href="<?= url_base('analitica-multianual') ?>" class="btn btn-danger btn-md shadow-sm" id="btnIrAnaliticaMultianual" title="Comparativa Histórica Multianual">
                <i class="fa-solid fa-chart-column me-1"></i> <span class="d-none d-lg-inline">Multianual</span>
            </a>
        </div>
    </div>
</div>

<!-- Banner de Estado de la Edición Seleccionada -->
<div class="alert alert-light border shadow-sm b-r-12 mb-4 d-flex align-items-center justify-content-between p-3" id="bannerEstadoEdicion">
    <div class="d-flex align-items-center gap-3">
        <div class="w-45 h-45 d-flex-center bg-danger-subtle text-danger rounded-circle f-s-20">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div>
            <h5 class="f-w-700 text-dark mb-0" id="dashEdicionNombre">Cargando festividad...</h5>
            <div class="f-s-13 text-muted">
                Código: <span class="badge bg-light text-dark border" id="dashEdicionCodigo">-</span> |
                Año: <span class="f-w-600 text-dark" id="dashEdicionAnio">-</span> |
                Período: <span id="dashEdicionFechas">-</span>
            </div>
        </div>
    </div>
    <div>
        <span class="badge bg-secondary p-2 f-s-12 text-uppercase" id="dashEdicionEstado">SIN ESTADO</span>
    </div>
</div>

<!-- Spinner de Carga Inicial -->
<div id="cargandoDashboard" class="text-center py-5">
    <div class="spinner-border text-danger" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Cargando métricas...</span>
    </div>
    <p class="text-muted mt-3 f-s-14">Calculando indicadores financieros y operativos en tiempo real...</p>
</div>

<!-- Contenedor Principal de Métricas (Oculto mientras carga) -->
<div id="contenidoDashboard" style="display: none;">

    <!-- Fila 1: KPIs Principales (Tarjetas Superiores) -->
    <div class="row g-3 mb-4">
        <!-- KPI 1: Ventas Devengadas -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="f-s-12 text-muted text-uppercase f-w-600">Ventas Devengadas</span>
                        <div class="w-40 h-40 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-18">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                    <h3 class="f-w-700 text-dark mb-1" id="kpiTotalDevengado">S/ 0.00</h3>
                    <div class="f-s-12 text-muted d-flex justify-content-between align-items-center">
                        <span><strong id="kpiVentasActivas">0</strong> ventas activas</span>
                        <span class="text-secondary" id="kpiTicketPromedio">Ticket: S/ 0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 2: Recaudación y % Cobranza -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="f-s-12 text-muted text-uppercase f-w-600">Recaudación Neta</span>
                        <div class="w-40 h-40 d-flex-center bg-success-subtle text-success rounded-circle f-s-18">
                            <i class="fa-solid fa-money-bill-trend-up"></i>
                        </div>
                    </div>
                    <h3 class="f-w-700 text-success mb-1" id="kpiTotalRecaudado">S/ 0.00</h3>
                    <div class="f-s-12 text-muted d-flex justify-content-between align-items-center">
                        <span>Cobrado: <strong id="kpiPctCobranza">0%</strong></span>
                        <span class="text-danger" id="kpiSaldoPendiente">Pendiente: S/ 0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 3: Ocupación de Flota Operativa -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="f-s-12 text-muted text-uppercase f-w-600">Ocupación Flota</span>
                        <div class="w-40 h-40 d-flex-center bg-info-subtle text-info rounded-circle f-s-18">
                            <i class="fa-solid fa-bus"></i>
                        </div>
                    </div>
                    <h3 class="f-w-700 text-info mb-1" id="kpiOcupacionFlota">0%</h3>
                    <div class="f-s-12 text-muted d-flex justify-content-between align-items-center">
                        <span><strong id="kpiPasajerosAgendados">0</strong> pasajeros</span>
                        <span>Capacidad: <span id="kpiCapacidadFlota">0</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 4: Asistencia Efectiva QR (Ley 29733) -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="f-s-12 text-muted text-uppercase f-w-600">Asistencia Efectiva</span>
                        <div class="w-40 h-40 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-18">
                            <i class="fa-solid fa-clipboard-user"></i>
                        </div>
                    </div>
                    <h3 class="f-w-700 text-dark mb-1" id="kpiTasaAsistencia">0%</h3>
                    <div class="f-s-12 text-muted d-flex justify-content-between align-items-center">
                        <span>Check-in: <strong id="kpiAsistentesQty">0</strong></span>
                        <span class="text-danger">No-Show: <strong id="kpiAusentesQty">0</strong> (<span id="kpiTasaNoShow">0%</span>)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fila 2: Finanzas y Canales Comerciales -->
    <div class="row g-4 mb-4">
        <!-- Desglose Financiero -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title f-w-700 text-dark mb-0 f-s-16">
                        <i class="fa-solid fa-scale-balanced text-primary me-2"></i> Conciliación Financiera y Cobranzas
                    </h5>
                    <span class="badge bg-light text-secondary border">Auditable</span>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="f-s-13 text-secondary">Progreso de Cobranza Global</span>
                            <span class="f-s-13 f-w-700 text-dark" id="progresoCobranzaTexto">0%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div id="progresoCobranzaBarra" class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle mb-0">
                            <tbody>
                                <tr class="border-bottom">
                                    <td class="text-secondary py-2"><i class="fa-solid fa-cart-shopping me-2 text-primary"></i> Total Devengado (Ventas Aprobadas):</td>
                                    <td class="text-end f-w-700 py-2 text-dark" id="detDevengado">S/ 0.00</td>
                                </tr>
                                <tr class="border-bottom">
                                    <td class="text-secondary py-2"><i class="fa-solid fa-hand-holding-dollar me-2 text-success"></i> Cobrado al Cliente (Ingreso Bruto):</td>
                                    <td class="text-end f-w-700 py-2 text-success" id="detCobradoCliente">S/ 0.00</td>
                                </tr>
                                <tr class="border-bottom">
                                    <td class="text-secondary py-2"><i class="fa-solid fa-percent me-2 text-warning"></i> Comisiones de Pasarela Acreditadas:</td>
                                    <td class="text-end f-w-600 py-2 text-warning" id="detComisionesPasarela">S/ 0.00</td>
                                </tr>
                                <tr class="border-bottom">
                                    <td class="text-secondary py-2"><i class="fa-solid fa-vault me-2 text-info"></i> Recaudación Neta Líquida en Cuenta:</td>
                                    <td class="text-end f-w-700 py-2 text-info" id="detRecaudacionNeta">S/ 0.00</td>
                                </tr>
                                <tr>
                                    <td class="text-secondary py-2"><i class="fa-solid fa-clock me-2 text-danger"></i> Saldo Pendiente por Cobrar:</td>
                                    <td class="text-end f-w-700 py-2 text-danger" id="detSaldoPendiente">S/ 0.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-around mt-3 pt-3 border-top text-center f-s-12">
                        <div>
                            <span class="badge bg-success-subtle text-success p-1 px-2 mb-1 d-inline-block">PAGADAS</span>
                            <div class="f-w-700 f-s-14" id="detVentasPagadas">0</div>
                        </div>
                        <div>
                            <span class="badge bg-warning-subtle text-warning p-1 px-2 mb-1 d-inline-block">PARCIALES</span>
                            <div class="f-w-700 f-s-14" id="detVentasParciales">0</div>
                        </div>
                        <div>
                            <span class="badge bg-danger-subtle text-danger p-1 px-2 mb-1 d-inline-block">NO PAGADAS</span>
                            <div class="f-w-700 f-s-14" id="detVentasNoPagadas">0</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Canales Comerciales y Distribución -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title f-w-700 text-dark mb-0 f-s-16">
                        <i class="fa-solid fa-bullhorn text-danger me-2"></i> Canales de Venta & Captación
                    </h5>
                    <span class="badge bg-light text-secondary border">Comercial</span>
                </div>
                <div class="card-body p-3">
                    <div id="listaCanalesComerciales">
                        <p class="text-muted text-center py-4">Sin datos de canales comerciales registrados.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fila 3: Operaciones de Campo y Postproducción -->
    <div class="row g-4 mb-4">
        <!-- Salidas y Operaciones de Flota -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title f-w-700 text-dark mb-0 f-s-16">
                        <i class="fa-solid fa-route text-success me-2"></i> Operaciones y Salidas de Campo
                    </h5>
                    <span class="badge bg-light text-dark border" id="badgeTotalSalidas">0 Salidas</span>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0" id="tablaSalidasCampo">
                            <thead class="table-light f-s-12">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Salida / Ruta</th>
                                    <th>Capacidad</th>
                                    <th>Agendados</th>
                                    <th class="text-end">Ocupación</th>
                                </tr>
                            </thead>
                            <tbody class="f-s-13" id="tbodySalidasCampo">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">No hay salidas programadas para esta edición.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Postproducción, Galerías y Entregas -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm b-r-12 h-100">
                <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title f-w-700 text-dark mb-0 f-s-16">
                        <i class="fa-solid fa-photo-film text-info me-2"></i> Postproducción y Entregas Multimedia
                    </h5>
                    <span class="badge bg-light text-secondary border">F2.11</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3 mb-3 text-center">
                        <div class="col-sm-4">
                            <div class="p-2 border rounded bg-light">
                                <span class="f-s-11 text-muted text-uppercase d-block">Proyectos</span>
                                <span class="f-w-700 f-s-18 text-dark" id="kpiProyectosQty">0</span>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-2 border rounded bg-light">
                                <span class="f-s-11 text-muted text-uppercase d-block">Entregas Despachadas</span>
                                <span class="f-w-700 f-s-18 text-primary" id="kpiEntregasQty">0</span>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-2 border rounded bg-light">
                                <span class="f-s-11 text-muted text-uppercase d-block">Tasa Aprobación</span>
                                <span class="f-w-700 f-s-18 text-success" id="kpiTasaAprobacion">0%</span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle mb-0">
                            <tbody>
                                <tr class="border-bottom">
                                    <td class="text-secondary py-2"><i class="fa-solid fa-check-circle me-2 text-success"></i> Entregas Aprobadas por Cliente:</td>
                                    <td class="text-end f-w-700 py-2 text-success" id="detEntregasAprobadas">0</td>
                                </tr>
                                <tr class="border-bottom">
                                    <td class="text-secondary py-2"><i class="fa-solid fa-hourglass-half me-2 text-warning"></i> Entregas Pendientes de Respuesta:</td>
                                    <td class="text-end f-w-600 py-2 text-warning" id="detEntregasPendientes">0</td>
                                </tr>
                                <tr class="border-bottom">
                                    <td class="text-secondary py-2"><i class="fa-solid fa-images me-2 text-info"></i> Galerías Fotográficas Creadas:</td>
                                    <td class="text-end f-w-600 py-2 text-info" id="detGaleriasQty">0</td>
                                </tr>
                                <tr>
                                    <td class="text-secondary py-2"><i class="fa-solid fa-hard-drive me-2 text-secondary"></i> Almacenamiento Utilizado:</td>
                                    <td class="text-end f-w-700 py-2 text-dark" id="detAlmacenamientoMb">0.00 MB</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fila 4: Comparativa Histórica Inter-Ediciones -->
    <div class="card border-0 shadow-sm b-r-12 mb-4">
        <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
            <h5 class="card-title f-w-700 text-dark mb-0 f-s-16">
                <i class="fa-solid fa-timeline text-primary me-2"></i> Comparativa Histórica Inter-Ediciones
            </h5>
            <span class="badge bg-light text-muted border">Persistencia Real</span>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaComparativaEdiciones">
                    <thead class="table-light f-s-12">
                        <tr>
                            <th>Año</th>
                            <th>Código</th>
                            <th>Festividad</th>
                            <th>Estado</th>
                            <th class="text-end">Ventas Devengadas</th>
                            <th class="text-end">Recaudación Neta</th>
                            <th class="text-center">Cant. Ventas</th>
                            <th class="text-end">Ticket Promedio</th>
                        </tr>
                    </thead>
                    <tbody class="f-s-13" id="tbodyComparativaEdiciones">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">Cargando histórico inter-ediciones...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
