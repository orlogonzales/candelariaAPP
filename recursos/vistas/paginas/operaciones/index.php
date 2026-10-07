<!-- ==============================================================================
     VISTA OFICIAL: TABLERO DE SALIDAS OPERATIVAS (operaciones/index.php) - FASE 2.6E
     Ejecución física de campo, Manifiestos, Check-in táctil e Incidencias con Alina UI
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-person-hiking text-primary me-2"></i> Operaciones de Campo y Salidas
        </h3>
        <p class="text-secondary mb-0">
            Control físico de salidas, asignación de turnos, manifiestos oficiales, check-in táctil y bitácora de incidencias.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <?php if (!empty($permisos['gestionarSalidas'])): ?>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalNuevaSalida">
                <i class="fa-solid fa-plus me-2"></i> Nueva Salida Operativa
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen / KPIs de Operación -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Salidas Registradas</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalSalidas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-route"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Programadas</span>
                    <h3 class="f-w-700 text-info mb-0 mt-1" id="kpiProgramadas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-info-subtle text-info rounded-circle f-s-20">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">En Ejecución (Despachadas)</span>
                    <h3 class="f-w-700 text-warning mb-0 mt-1" id="kpiDespachadas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-person-walking-luggage"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Finalizadas con Éxito</span>
                    <h3 class="f-w-700 text-success mb-0 mt-1" id="kpiFinalizadas">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros Avanzados de Salidas -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control border-start-0" id="filtroBusqueda" placeholder="Buscar salida, título, servicio...">
                </div>
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" id="filtroFecha" placeholder="Fecha: YYYY-MM-DD" readonly>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filtroEstado" aria-label="Filtrar por estado">
                    <option value="">Todos los Estados</option>
                    <option value="PROGRAMADA">Programada</option>
                    <option value="DESPACHADA">Despachada / En Curso</option>
                    <option value="FINALIZADA">Finalizada</option>
                    <option value="INTERRUMPIDA">Interrumpida</option>
                    <option value="CANCELADA">Cancelada</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filtroServicio" aria-label="Filtrar por servicio">
                    <option value="">Todos los Servicios</option>
                </select>
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

<!-- Tabla Principal de Salidas Operativas -->
<div class="card border-0 shadow-sm b-r-12">
    <div class="card-body p-3">
        <!-- Skeleton Loader -->
        <div id="skeletonSalidas" class="py-3">
            <div class="placeholder-glow mb-3"><span class="placeholder col-12 py-3 b-r-8"></span></div>
            <div class="placeholder-glow mb-2"><span class="placeholder col-12 py-2 b-r-8"></span></div>
            <div class="placeholder-glow mb-2"><span class="placeholder col-12 py-2 b-r-8"></span></div>
            <div class="placeholder-glow mb-2"><span class="placeholder col-12 py-2 b-r-8"></span></div>
            <div class="placeholder-glow"><span class="placeholder col-12 py-2 b-r-8"></span></div>
        </div>

        <!-- Contenedor Real de Tabla -->
        <div class="table-responsive d-none" id="contenedorTablaSalidas">
            <table class="table table-hover align-middle w-100" id="tablaSalidas">
                <thead class="table-light">
                    <tr>
                        <th class="f-w-600 text-uppercase f-s-12">Correlativo</th>
                        <th class="f-w-600 text-uppercase f-s-12">Servicio / Título</th>
                        <th class="f-w-600 text-uppercase f-s-12">Fecha y Horario</th>
                        <th class="f-w-600 text-uppercase f-s-12">Capacidad / Pax</th>
                        <th class="f-w-600 text-uppercase f-s-12">Estado</th>
                        <th class="f-w-600 text-uppercase f-s-12">Check-in</th>
                        <th class="f-w-600 text-uppercase f-s-12 text-center">Acciones de Campo</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: CREAR / EDITAR SALIDA OPERATIVA (modalSalidaForm)
============================================================================== -->
<div class="modal fade" id="modalSalidaForm" tabindex="-1" aria-labelledby="tituloModalSalida" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="tituloModalSalida">
                    <i class="fa-solid fa-route text-primary me-2"></i> Nueva Salida Operativa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formSalidaOperativa">
                <input type="hidden" id="salidaFormId" name="id">
                <input type="hidden" id="salidaFormVersion" name="version_bloqueo">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6" id="contenedorSelectServicio">
                            <label class="form-label f-w-600 f-s-13">Servicio Comercial <span class="text-danger">*</span></label>
                            <select class="form-select" id="salidaServicioId" name="item_comercial_id" required>
                                <option value="">Seleccione el servicio a operar</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Título / Identificador de la Salida <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="salidaTitulo" name="titulo" placeholder="Ej. Tour Islas Uros - Turno Mañana 1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Fecha de Salida <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="salidaFecha" name="fecha_salida" placeholder="YYYY-MM-DD" required readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Hora de Citación <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="salidaHoraCitacion" name="hora_citacion" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Hora de Salida / Zarpe <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="salidaHoraSalida" name="hora_salida" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Punto de Encuentro / Embarque <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="salidaPuntoEncuentro" name="punto_encuentro" placeholder="Ej. Puerto Lacustre de Puno - Muelle 2" required>
                        </div>
                        <div class="col-md-3" id="contenedorTipoCapacidad">
                            <label class="form-label f-w-600 f-s-13">Tipo Capacidad <span class="text-danger">*</span></label>
                            <select class="form-select" id="salidaTipoCapacidad" name="tipo_capacidad" required>
                                <option value="COLECTIVA">Colectiva</option>
                                <option value="DISCRETA">Discreta (Con Asiento)</option>
                                <option value="EXCLUSIVA">Exclusiva (Privada)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-w-600 f-s-13">Capacidad Máxima</label>
                            <input type="number" class="form-control" id="salidaCapacidadMax" name="capacidad_maxima" min="1" placeholder="Ej. 25 plazas">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarSalida">
                        <i class="fa-solid fa-save me-1"></i> Guardar Salida
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: DETALLE Y ARMADO DE SALIDA (modalDetalleSalida)
============================================================================== -->
<div class="modal fade" id="modalDetalleSalida" tabindex="-1" aria-labelledby="tituloModalArmado" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="modal-title f-w-700" id="tituloModalArmado">
                        Salida <span id="armadoCorrelativo" class="text-primary">-</span>
                    </h5>
                    <span id="armadoEstadoBadge" class="badge bg-secondary">-</span>
                    <span id="armadoCapacidadBadge" class="badge bg-light text-dark border">-</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <ul class="nav nav-tabs mb-4" id="tabsArmadoSalida" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-w-600" id="tab-armado-prestaciones" data-bs-toggle="tab" data-bs-target="#panel-armado-prestaciones" type="button" role="tab">
                            <i class="fa-solid fa-people-group me-1"></i> Prestaciones y Pasajeros (<span id="armadoCountPrestaciones">0</span>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600" id="tab-armado-recursos" data-bs-toggle="tab" data-bs-target="#panel-armado-recursos" type="button" role="tab">
                            <i class="fa-solid fa-ship me-1"></i> Recursos y Personal (<span id="armadoCountRecursos">0</span>)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="contenidoTabsArmado">
                    <!-- Panel Prestaciones: Asignadas + Compatibles Disponibles -->
                    <div class="tab-pane fade show active" id="panel-armado-prestaciones" role="tabpanel">
                        <div class="row g-4">
                            <!-- Columna Izquierda: Prestaciones ya asignadas -->
                            <div class="col-lg-6">
                                <h6 class="f-w-700 text-dark mb-2">
                                    <i class="fa-solid fa-circle-check text-success me-1"></i> Prestaciones Asignadas a esta Salida
                                </h6>
                                <div id="listaPrestacionesAsignadas" class="d-flex flex-column gap-2">
                                    <!-- Renderizado JS -->
                                </div>
                            </div>
                            <!-- Columna Derecha: Prestaciones compatibles disponibles -->
                            <div class="col-lg-6">
                                <h6 class="f-w-700 text-dark mb-2">
                                    <i class="fa-solid fa-plus-circle text-primary me-1"></i> Turnos Compatibles Disponibles para Asignar
                                </h6>
                                <div id="listaPrestacionesCompatibles" class="d-flex flex-column gap-2">
                                    <!-- Renderizado JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Recursos y Personal -->
                    <div class="tab-pane fade" id="panel-armado-recursos" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted f-s-13">Embarcaciones, vehículos y personal de campo asignados a esta salida.</span>
                            <?php if (!empty($permisos['asignarRecursos'])): ?>
                            <button type="button" class="btn btn-sm btn-primary" id="btnAbrirModalAsignarRecurso">
                                <i class="fa-solid fa-plus me-1"></i> Asignar Recurso / Personal
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle w-100" id="tablaRecursosSalida">
                                <thead class="table-light">
                                    <tr>
                                        <th>Rol Operativo</th>
                                        <th>Recurso Físico</th>
                                        <th>Personal Asignado</th>
                                        <th>Notas</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyRecursosSalida"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: ASIGNAR RECURSO / PERSONAL A SALIDA (modalAsignarRecurso)
============================================================================== -->
<div class="modal fade" id="modalAsignarRecurso" tabindex="-1" aria-labelledby="tituloModalAsignarRecurso" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="tituloModalAsignarRecurso">
                    <i class="fa-solid fa-plus-circle text-primary me-2"></i> Asignar Recurso o Personal
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAsignarRecursoSalida">
                <input type="hidden" id="asigRecursoSalidaId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Rol Operativo <span class="text-danger">*</span></label>
                        <select class="form-select" id="asigRolOperativo" required>
                            <option value="">Seleccione el rol</option>
                            <option value="TRANSPORTE_PRINCIPAL">Transporte Principal (Lancha / Bus)</option>
                            <option value="GUIA_OFICIAL">Guía Oficial de Turismo</option>
                            <option value="CONDUCTOR">Conductor / Chofer</option>
                            <option value="CAPITAN_PATRON">Capitán / Patrón de Embarcación</option>
                            <option value="ASISTENTE_LOGISTICA">Asistente de Logística / Campo</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Recurso Físico (Embarcación / Vehículo)</label>
                        <select class="form-select" id="asigRecursoFisicoId">
                            <option value="">Ninguno / Solo Personal</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Personal de Campo (Persona)</label>
                        <select class="form-select" id="asigPersonaId" style="width: 100%;">
                            <option value="">Ninguno / Solo Vehículo</option>
                        </select>
                        <small class="text-muted f-s-11">Busque por nombre o documento. No requiere cuenta de usuario.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Notas Operativas</label>
                        <input type="text" class="form-control" id="asigNotas" placeholder="Instrucciones adicionales...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarAsigRecurso">
                        <i class="fa-solid fa-save me-1"></i> Asignar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: MANIFIESTO OFICIAL DE PASAJEROS (modalManifiesto)
============================================================================== -->
<div class="modal fade" id="modalManifiesto" tabindex="-1" aria-labelledby="tituloModalManifiesto" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light d-print-none">
                <h5 class="modal-title f-w-700" id="tituloModalManifiesto">
                    <i class="fa-solid fa-clipboard-list text-primary me-2"></i> Manifiesto de Pasajeros de Salida
                </h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i> Imprimir Manifiesto
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
            </div>
            <div class="modal-body p-4" id="areaImpresionManifiesto">
                <!-- Encabezado Imprimible -->
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="f-w-700 mb-0">MANIFIESTO OFICIAL DE PASAJEROS</h4>
                            <span class="f-s-12 text-muted text-uppercase">CandelariaAPP &bull; Registro de Operaciones y Zarpe de Campo</span>
                        </div>
                        <div class="text-end">
                            <h5 class="text-primary f-w-700 mb-0" id="manifCorrelativo">SAL-2026-000000</h5>
                            <span class="badge bg-secondary" id="manifEstadoBadge">-</span>
                        </div>
                    </div>
                    <div class="row g-2 mt-2 f-s-13">
                        <div class="col-md-3"><span class="text-muted">Servicio:</span> <strong id="manifServicio">-</strong></div>
                        <div class="col-md-3"><span class="text-muted">Fecha Salida:</span> <strong id="manifFecha">-</strong></div>
                        <div class="col-md-3"><span class="text-muted">Hora Zarpe:</span> <strong id="manifHora">-</strong></div>
                        <div class="col-md-3"><span class="text-muted">Punto Partida:</span> <strong id="manifPunto">-</strong></div>
                        <div class="col-md-6"><span class="text-muted">Embarcación / Vehículo:</span> <strong id="manifTransporte">-</strong></div>
                        <div class="col-md-6"><span class="text-muted">Guía Oficial:</span> <strong id="manifGuia">-</strong></div>
                    </div>
                </div>

                <!-- Resumen de Asistencia -->
                <div class="row g-2 mb-3">
                    <div class="col-3 text-center p-2 bg-light b-r-8"><small class="text-muted d-block">TOTAL PASAJEROS</small><strong class="f-s-16" id="manifTotalPax">0</strong></div>
                    <div class="col-3 text-center p-2 bg-light-success text-success b-r-8"><small class="d-block">PRESENTES</small><strong class="f-s-16" id="manifPresentes">0</strong></div>
                    <div class="col-3 text-center p-2 bg-light-danger text-danger b-r-8"><small class="d-block">NO-SHOW</small><strong class="f-s-16" id="manifNoShow">0</strong></div>
                    <div class="col-3 text-center p-2 bg-light-warning text-dark b-r-8"><small class="d-block">PENDIENTES</small><strong class="f-s-16" id="manifPendientes">0</strong></div>
                </div>

                <!-- Tabla de Pasajeros -->
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle w-100 f-s-12" id="tablaManifiestoPasajeros">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">Asiento</th>
                                <th>Apellidos y Nombres</th>
                                <th>Documento</th>
                                <th>Nac.</th>
                                <th>Edad</th>
                                <th>Régimen / Requerimientos</th>
                                <th>Contacto</th>
                                <th>Reserva</th>
                                <th class="text-center" style="width: 80px;">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyManifiestoPasajeros"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light d-print-none">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: CHECK-IN RESPONSIVE Y TÁCTIL EN CAMPO (modalCheckin)
============================================================================== -->
<div class="modal fade" id="modalCheckin" tabindex="-1" aria-labelledby="tituloModalCheckin" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="modal-title f-w-700 mb-0" id="tituloModalCheckin">
                        <i class="fa-solid fa-clipboard-user text-warning me-2"></i> Check-in en Campo
                    </h5>
                    <span id="checkinSalidaCorrelativo" class="badge bg-warning text-dark">-</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Barra superior de estado de la salida y controles de ejecución -->
                <div class="card border bg-light mb-4 b-r-8 p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <span class="text-muted f-s-12 d-block">Estado de la Salida:</span>
                            <span id="checkinEstadoSalidaBadge" class="badge bg-secondary f-s-13">-</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2" id="contenedorAccionesEjecucion">
                            <!-- Botones dinámicos de Despachar, Finalizar, Interrumpir, Cancelar -->
                        </div>
                    </div>
                </div>

                <!-- Resumen numérico rápido de asistencia -->
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="p-2 text-center bg-light-success text-success b-r-8">
                            <span class="f-s-11 d-block text-uppercase">Presentes</span>
                            <strong class="f-s-18" id="checkinTotalPresentes">0</strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 text-center bg-light-danger text-danger b-r-8">
                            <span class="f-s-11 d-block text-uppercase">No Show</span>
                            <strong class="f-s-18" id="checkinTotalNoShow">0</strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 text-center bg-light-warning text-dark b-r-8">
                            <span class="f-s-11 d-block text-uppercase">Pendientes</span>
                            <strong class="f-s-18" id="checkinTotalPendientes">0</strong>
                        </div>
                    </div>
                </div>

                <!-- Buscador rápido en campo -->
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control py-2" id="buscarPasajeroCheckin" placeholder="Filtrar por pasajero o documento...">
                    </div>
                </div>

                <!-- Lista Táctil de Pasajeros -->
                <div id="listaPasajerosCheckin" class="d-flex flex-column gap-2">
                    <!-- Renderizado dinámico de tarjetas táctiles -->
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: REGISTRAR INCIDENCIA OPERATIVA (modalIncidencia)
============================================================================== -->
<div class="modal fade" id="modalIncidencia" tabindex="-1" aria-labelledby="tituloModalIncidencia" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="tituloModalIncidencia">
                    <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Registrar Incidencia de Campo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formIncidenciaOperativa">
                <input type="hidden" id="incidenciaSalidaId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Tipo de Incidencia <span class="text-danger">*</span></label>
                        <select class="form-select" id="incTipo" required>
                            <option value="">Seleccione el tipo de evento</option>
                            <option value="RETRASO_CLIMATICO">Retraso Climático (Lluvia / Viento / Oleaje)</option>
                            <option value="FALLA_MECANICA_VEHICULO">Falla Mecánica de Vehículo o Embarcación</option>
                            <option value="EMERGENCIA_MEDICA_PASAJERO">Emergencia Médica de Pasajero</option>
                            <option value="BLOQUEO_RUTA_ORDEN_PUBLICO">Bloqueo de Vía u Orden Público</option>
                            <option value="DESVIO_LOGISTICO_CAMPO">Desvío Logístico de Campo</option>
                            <option value="DISCREPANCIA_MANIFIESTO_AUTORIDADES">Discrepancia en Control con Autoridades (DICAPI/SUTRAN)</option>
                            <option value="OTRO">Otro Evento Operativo</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Descripción Detallada del Hecho <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="incDescripcion" rows="3" placeholder="Describa objetivamente lo ocurrido en campo..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Acciones Inmediatas Tomadas</label>
                        <textarea class="form-control" id="incAcciones" rows="2" placeholder="Medidas de mitigación o contingencia adoptadas..."></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="incAfecto" value="1">
                        <label class="form-check-label f-s-13" for="incAfecto">
                            Afectó la continuidad normal de la salida
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btnGuardarIncidencia">
                        <i class="fa-solid fa-save me-1"></i> Registrar Incidencia
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: VER BITÁCORA DE INCIDENCIAS (modalVerIncidencias)
============================================================================== -->
<div class="modal fade" id="modalVerIncidencias" tabindex="-1" aria-labelledby="tituloModalVerInc" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="tituloModalVerInc">
                    <i class="fa-solid fa-list-check text-secondary me-2"></i> Bitácora de Incidencias de la Salida
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div id="listaIncidenciasSalida" class="d-flex flex-column gap-3">
                    <!-- Renderizado dinámico -->
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
