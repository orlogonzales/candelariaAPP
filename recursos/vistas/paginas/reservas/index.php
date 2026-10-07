<!-- ==============================================================================
     VISTA OFICIAL: GESTIÓN DE RESERVAS Y AGENDAMIENTO (index.php) - FASE 2.6E
     Proyección fiel del Dominio de Reservas (F2.6C) con Alina UI
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-calendar-check text-primary me-2"></i> Reservas y Agendamiento
        </h3>
        <p class="text-secondary mb-0">
            Compromisos de servicio, programación de turnos de campo y nóminas operativas de pasajeros.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <?php if (!empty($permisos['crearDesdeVenta'])): ?>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalFormalizar">
                <i class="fa-solid fa-file-signature me-2"></i> Nueva Reserva (desde Venta)
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
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Reservas</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalReservas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-calendar-days"></i>
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
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Pendiente Datos</span>
                    <h3 class="f-w-700 text-warning mb-0 mt-1" id="kpiPendienteDatos">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Canceladas</span>
                    <h3 class="f-w-700 text-danger mb-0 mt-1" id="kpiCanceladas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-danger-subtle text-danger rounded-circle f-s-20">
                    <i class="fa-solid fa-ban"></i>
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
                    <input type="text" class="form-control border-start-0" id="filtroBusqueda" placeholder="Buscar reserva, cliente, venta...">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filtroEstado" aria-label="Filtrar por estado">
                    <option value="">Todos los Estados</option>
                    <option value="REGISTRADA">Registrada</option>
                    <option value="PENDIENTE_DATOS">Pendiente Datos</option>
                    <option value="CONFIRMADA">Confirmada</option>
                    <option value="CANCELADA">Cancelada</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filtroProgramacion" aria-label="Filtrar por programación">
                    <option value="">Programación: Todas</option>
                    <option value="PENDIENTE">Con turnos pendientes</option>
                    <option value="PROGRAMADA">100% Programadas</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" id="filtroFechaDesde" placeholder="Desde: YYYY-MM-DD" readonly>
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

<!-- Tabla Principal de Reservas -->
<div class="card border-0 shadow-sm b-r-12">
    <div class="card-body p-3">
        <!-- Skeleton Loader para Tabla -->
        <div id="skeletonReservas" class="py-3">
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
        <div class="table-responsive d-none" id="contenedorTablaReservas">
            <table class="table table-hover align-middle w-100" id="tablaReservas">
                <thead class="table-light">
                    <tr>
                        <th class="f-w-600 text-uppercase f-s-12">Correlativo</th>
                        <th class="f-w-600 text-uppercase f-s-12">Venta Origen</th>
                        <th class="f-w-600 text-uppercase f-s-12">Titular / Contacto</th>
                        <th class="f-w-600 text-uppercase f-s-12">Estado</th>
                        <th class="f-w-600 text-uppercase f-s-12">Prestaciones</th>
                        <th class="f-w-600 text-uppercase f-s-12">Pasajeros</th>
                        <th class="f-w-600 text-uppercase f-s-12">Fecha Registro</th>
                        <th class="f-w-600 text-uppercase f-s-12 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: DETALLE 360 DE LA RESERVA (modalDetalleReserva)
============================================================================== -->
<div class="modal fade" id="modalDetalleReserva" tabindex="-1" aria-labelledby="tituloModalDetalle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="modal-title f-w-700 text-dark mb-0" id="tituloModalDetalle">
                        Reserva <span id="detalleCorrelativo" class="text-primary">-</span>
                    </h5>
                    <span id="detalleEstadoBadge" class="badge bg-secondary">-</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Skeleton Loader para Detalle -->
                <div id="skeletonDetalleReserva" class="py-4">
                    <div class="placeholder-glow mb-4">
                        <span class="placeholder col-4 py-3 b-r-8"></span>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><span class="placeholder col-12 py-3 b-r-8"></span></div>
                        <div class="col-md-4"><span class="placeholder col-12 py-3 b-r-8"></span></div>
                        <div class="col-md-4"><span class="placeholder col-12 py-3 b-r-8"></span></div>
                    </div>
                    <div class="placeholder-glow mb-2">
                        <span class="placeholder col-12 py-4 b-r-8"></span>
                    </div>
                </div>

                <!-- Contenido Real del Detalle -->
                <div id="contenidoDetalleReserva" class="d-none">
                    <!-- Pestañas de Navegación del Detalle -->
                    <ul class="nav nav-tabs mb-4" id="tabsDetalleReserva" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active f-w-600" id="tab-general" data-bs-toggle="tab" data-bs-target="#panel-general" type="button" role="tab">
                                <i class="fa-solid fa-circle-info me-1"></i> Información General
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link f-w-600" id="tab-prestaciones" data-bs-toggle="tab" data-bs-target="#panel-prestaciones" type="button" role="tab">
                                <i class="fa-solid fa-list-check me-1"></i> Prestaciones y Turnos (<span id="tabCountPrestaciones">0</span>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link f-w-600" id="tab-participantes" data-bs-toggle="tab" data-bs-target="#panel-participantes" type="button" role="tab">
                                <i class="fa-solid fa-users me-1"></i> Nómina de Pasajeros (<span id="tabCountParticipantes">0</span>)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="contenidoTabsDetalle">
                        <!-- Panel 1: Información General y Venta -->
                        <div class="tab-pane fade show active" id="panel-general" role="tabpanel">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="card border bg-light b-r-8 p-3 h-100">
                                        <h6 class="f-w-700 text-dark mb-3"><i class="fa-solid fa-user me-2 text-primary"></i> Contacto y Titular</h6>
                                        <p class="mb-1"><span class="text-muted">Nombre:</span> <strong id="detTitularNombre">-</strong></p>
                                        <p class="mb-1"><span class="text-muted">Documento:</span> <span id="detTitularDoc">-</span></p>
                                        <p class="mb-1"><span class="text-muted">Teléfono:</span> <span id="detTitularTel">-</span></p>
                                        <p class="mb-0"><span class="text-muted">Correo:</span> <span id="detTitularEmail">-</span></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border bg-light b-r-8 p-3 h-100">
                                        <h6 class="f-w-700 text-dark mb-3"><i class="fa-solid fa-receipt me-2 text-success"></i> Venta Origen</h6>
                                        <p class="mb-1"><span class="text-muted">Correlativo Venta:</span> <strong id="detVentaCorrelativo">-</strong></p>
                                        <p class="mb-1"><span class="text-muted">Fecha de Venta:</span> <span id="detVentaFecha">-</span></p>
                                        <p class="mb-1"><span class="text-muted">Total Comercial:</span> <span id="detVentaTotal" class="f-w-700 text-dark">-</span></p>
                                        <p class="mb-0"><span class="text-muted">Estado Venta:</span> <span id="detVentaEstado">-</span></p>
                                    </div>
                                </div>
                            </div>
                            <div class="card border bg-light b-r-8 p-3">
                                <h6 class="f-w-700 text-dark mb-2"><i class="fa-solid fa-note-sticky me-2 text-secondary"></i> Notas Operativas</h6>
                                <p class="mb-0 text-muted f-s-13" id="detNotasOperativas">Sin notas adicionales.</p>
                            </div>
                        </div>

                        <!-- Panel 2: Prestaciones & Turnos -->
                        <div class="tab-pane fade" id="panel-prestaciones" role="tabpanel">
                            <div id="listaPrestacionesContenedor" class="d-flex flex-column gap-3">
                                <!-- Se renderiza dinámicamente con JS -->
                            </div>
                        </div>

                        <!-- Panel 3: Nómina de Pasajeros -->
                        <div class="tab-pane fade" id="panel-participantes" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted f-s-13">Pasajeros registrados para las prestaciones de la reserva.</span>
                                <?php if (!empty($permisos['gestionarParticipantes'])): ?>
                                <button type="button" class="btn btn-sm btn-primary" id="btnAbrirModalAgregarParticipante">
                                    <i class="fa-solid fa-user-plus me-1"></i> Agregar Pasajero
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle w-100" id="tablaParticipantes">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Nombres y Apellidos</th>
                                            <th>Documento</th>
                                            <th>Nacionalidad</th>
                                            <th>Rango Etario</th>
                                            <th>Régimen Alim.</th>
                                            <th>Contacto</th>
                                            <th>Titular</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyParticipantes"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <?php if (!empty($permisos['cancelar'])): ?>
                <button type="button" class="btn btn-outline-danger btn-sm me-auto" id="btnCancelarReservaModal">
                    <i class="fa-solid fa-ban me-1"></i> Cancelar Reserva
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: FORMALIZAR RESERVA DESDE VENTA (modalFormalizar)
============================================================================== -->
<div class="modal fade" id="modalFormalizar" tabindex="-1" aria-labelledby="tituloModalFormalizar" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="tituloModalFormalizar">
                    <i class="fa-solid fa-file-signature text-primary me-2"></i> Formalizar Reserva
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted f-s-13 mb-3">
                    Seleccione una venta confirmada para formalizar concurrentemente sus compromisos de servicio y órdenes de entrega.
                </p>
                <div class="mb-3">
                    <label class="form-label f-w-600 f-s-13">Venta Confirmada <span class="text-danger">*</span></label>
                    <select class="form-select" id="selectVentaFormalizar" required>
                        <option value="">Cargando ventas confirmadas...</option>
                    </select>
                </div>
                <div id="infoVentaSeleccionada" class="p-3 bg-light b-r-8 d-none">
                    <p class="mb-1"><span class="text-muted">Cliente:</span> <strong id="formVentaCliente">-</strong></p>
                    <p class="mb-0"><span class="text-muted">Total:</span> <span id="formVentaTotal" class="f-w-700 text-success">-</span></p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnEjecutarFormalizar">
                    <i class="fa-solid fa-check me-1"></i> Formalizar Reserva
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: PROGRAMAR PRESTACIÓN (modalProgramar)
============================================================================== -->
<div class="modal fade" id="modalProgramar" tabindex="-1" aria-labelledby="tituloModalProgramar" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="tituloModalProgramar">
                    <i class="fa-solid fa-calendar-day text-primary me-2"></i> Programar Turno de Servicio
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formProgramarPrestacion">
                <input type="hidden" id="progPrestacionId" name="prestacion_id">
                <div class="modal-body p-4">
                    <p class="text-muted f-s-13 mb-3">
                        Asigne fecha y hora de ejecución para: <strong id="progConceptoNombre">-</strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Fecha de Servicio <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="progFechaServicio" name="fecha_servicio" placeholder="YYYY-MM-DD" required readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Hora de Servicio</label>
                        <input type="time" class="form-control" id="progHoraServicio" name="hora_servicio">
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Punto de Encuentro</label>
                        <input type="text" class="form-control" id="progPuntoEncuentro" name="punto_encuentro" placeholder="Ej. Muelle Principal, Hotel, Sede...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarProgramacion">
                        <i class="fa-solid fa-calendar-check me-1"></i> Guardar Programación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: REPROGRAMAR PRESTACIÓN (modalReprogramar)
============================================================================== -->
<div class="modal fade" id="modalReprogramar" tabindex="-1" aria-labelledby="tituloModalReprogramar" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="tituloModalReprogramar">
                    <i class="fa-solid fa-arrows-rotate text-warning me-2"></i> Reprogramar Turno
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formReprogramarPrestacion">
                <input type="hidden" id="reprogPrestacionId" name="prestacion_id">
                <div class="modal-body p-4">
                    <div class="p-3 bg-light b-r-8 mb-3">
                        <span class="text-muted f-s-12 d-block">Programación Actual:</span>
                        <strong id="reprogActualTexto">-</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Nueva Fecha <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reprogNuevaFecha" name="nueva_fecha" placeholder="YYYY-MM-DD" required readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Nueva Hora</label>
                        <input type="time" class="form-control" id="reprogNuevaHora" name="nueva_hora">
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Motivo de Reprogramación <span class="text-danger">*</span></label>
                        <select class="form-select" id="reprogMotivoCategoria" name="motivo_categoria" required>
                            <option value="">Seleccione una categoría</option>
                            <option value="SOLICITUD_CLIENTE">Solicitud del Cliente</option>
                            <option value="CLIMA_FUERZA_MAYOR">Condiciones Climáticas / Fuerza Mayor</option>
                            <option value="LOGISTICA_OPERATIVA">Logística Operativa</option>
                            <option value="OTRO">Otro Motivo</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Detalle Explicativo del Motivo <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reprogMotivoDetalle" name="motivo_detalle" rows="2" placeholder="Explique brevemente la justificación del cambio..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btnGuardarReprogramacion">
                        <i class="fa-solid fa-check me-1"></i> Confirmar Reprogramación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: AGREGAR PARTICIPANTE (modalAgregarParticipante)
============================================================================== -->
<div class="modal fade" id="modalAgregarParticipante" tabindex="-1" aria-labelledby="tituloModalParticipante" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="tituloModalParticipante">
                    <i class="fa-solid fa-user-plus text-primary me-2"></i> Registrar Pasajero / Participante
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAgregarParticipante">
                <input type="hidden" id="partReservaId" name="reserva_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Nombres <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="partNombres" name="nombres" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="partApellidos" name="apellidos" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Tipo Documento <span class="text-danger">*</span></label>
                            <select class="form-select" id="partTipoDocumento" name="tipo_documento_id" required></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Número Documento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="partNumeroDocumento" name="numero_documento" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Nacionalidad (Código)</label>
                            <input type="text" class="form-control text-uppercase" id="partNacionalidad" name="nacionalidad" placeholder="PE, BO, AR..." maxlength="3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Rango Etario</label>
                            <select class="form-select" id="partRangoEtario" name="rango_etario">
                                <option value="">No especificado</option>
                                <option value="ADULTO">Adulto</option>
                                <option value="MENOR">Menor</option>
                                <option value="INFANTE">Infante</option>
                                <option value="ADULTO_MAYOR">Adulto Mayor</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Régimen Alimentario</label>
                            <select class="form-select" id="partRegimenAlimentario" name="regimen_alimentario">
                                <option value="ESTANDAR">Estándar</option>
                                <option value="VEGETARIANO">Vegetariano</option>
                                <option value="VEGANO">Vegano</option>
                                <option value="SIN_GLUTEN">Sin Gluten</option>
                                <option value="OTRO">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Teléfono de Contacto</label>
                            <input type="tel" class="form-control" id="partTelefono" name="telefono_contacto">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Talla Indumentaria</label>
                            <input type="text" class="form-control" id="partTalla" name="talla_indumentaria" placeholder="S, M, L, XL...">
                        </div>
                        <div class="col-md-4 d-flex align-items-center mt-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="partReqMovilidad" name="requiere_asistencia_movilidad" value="1">
                                <label class="form-check-label f-s-13" for="partReqMovilidad">
                                    Requiere asistencia de movilidad
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-center mt-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="partEsTitular" name="es_titular_reserva" value="1">
                                <label class="form-check-label f-s-13" for="partEsTitular">
                                    Es titular de la reserva
                                </label>
                            </div>
                        </div>

                        <!-- Asignación a prestaciones -->
                        <div class="col-12 mt-3">
                            <label class="form-label f-w-600 f-s-13">Asignar a Prestaciones de la Reserva:</label>
                            <div id="checkPrestacionesParticipante" class="p-3 bg-light b-r-8 d-flex flex-column gap-2">
                                <!-- Checkboxes renderizados dinámicamente -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarParticipante">
                        <i class="fa-solid fa-save me-1"></i> Guardar Pasajero
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
