<!-- Encabezado de Sección: Programación Folclórica (Fase 3.1B) -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-masks-theater text-danger me-2"></i> Programación Folclórica
        </h3>
        <p class="text-secondary mb-0">
            Administración del rol oficial de presentación, tipos de danza, agrupaciones históricas e inscripciones por edición.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2 flex-wrap justify-content-md-end">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarDatos" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <div class="dropdown">
                <button class="btn bg-gradient-danger text-white btn-md dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-plus me-1"></i> Nuevo Registro
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow b-r-12">
                    <li><a class="dropdown-item py-2" href="#" id="btnModalTipoDanza"><i class="fa-solid fa-feather me-2 text-primary"></i> Tipo de Danza</a></li>
                    <li><a class="dropdown-item py-2" href="#" id="btnModalConjunto"><i class="fa-solid fa-people-group me-2 text-success"></i> Conjunto Folclórico</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2" href="#" id="btnModalInscripcion"><i class="fa-solid fa-clipboard-check me-2 text-warning"></i> Inscribir en Edición</a></li>
                    <li><a class="dropdown-item py-2" href="#" id="btnModalActividad"><i class="fa-solid fa-calendar-plus me-2 text-info"></i> Actividad de Calendario</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2" href="#" id="btnModalVersion"><i class="fa-solid fa-code-branch me-2 text-danger"></i> Publicar Versión de Rol</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Tarjetas KPIs Resumen -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Tipos de Danza</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalTiposDanza">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-feather"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Conjuntos Registrados</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalConjuntos">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-people-group"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Inscritos en Edición</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalInscritos">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-clipboard-user"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Estado Rol Activo</span>
                    <h5 class="f-w-700 mb-0 mt-1 text-truncate" id="kpiEstadoRol" style="max-width: 170px;">-</h5>
                </div>
                <div class="w-45 h-45 d-flex-center bg-danger-subtle text-danger rounded-circle f-s-20">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Selector de Contexto de Edición y Pestañas de Navegación -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-0 px-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pb-2 border-bottom">
            <ul class="nav nav-pills card-header-pills" id="tabsFolclore" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active f-w-600" id="tab-rol-btn" data-bs-toggle="pill" data-bs-target="#tab-rol" type="button" role="tab" aria-selected="true">
                        <i class="fa-solid fa-list-ol me-2"></i> Rol Oficial de Presentación
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link f-w-600" id="tab-conjuntos-btn" data-bs-toggle="pill" data-bs-target="#tab-conjuntos" type="button" role="tab" aria-selected="false">
                        <i class="fa-solid fa-users-rectangle me-2"></i> Danzas y Agrupaciones
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link f-w-600" id="tab-inscripciones-btn" data-bs-toggle="pill" data-bs-target="#tab-inscripciones" type="button" role="tab" aria-selected="false">
                        <i class="fa-solid fa-calendar-check me-2"></i> Padrón de Edición
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link f-w-600" id="tab-salidas-btn" data-bs-toggle="pill" data-bs-target="#tab-salidas" type="button" role="tab" aria-selected="false">
                        <i class="fa-solid fa-route me-2"></i> Salidas de Operación
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link f-w-600 text-danger" id="tab-seguimiento-btn" data-bs-toggle="pill" data-bs-target="#tab-seguimiento" type="button" role="tab" aria-selected="false">
                        <i class="fa-solid fa-tower-broadcast me-2"></i> Tablero de Seguimiento
                    </button>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <label for="selectorEdicionTrabajo" class="f-s-12 text-muted text-nowrap f-w-600 mb-0">Edición:</label>
                <select id="selectorEdicionTrabajo" class="form-select form-select-sm b-r-8" style="min-width: 180px;">
                    <!-- Cargado dinámicamente -->
                </select>
            </div>
        </div>
    </div>

    <div class="card-body p-3">
        <div class="tab-content" id="tabsFolcloreContent">

            <!-- ==============================================================================
                 PESTAÑA 1: ROL OFICIAL DE PRESENTACIÓN
            ============================================================================== -->
            <div class="tab-pane fade show active" id="tab-rol" role="tabpanel" aria-labelledby="tab-rol-btn">
                <div class="row g-3 align-items-center mb-3 p-3 bg-light b-r-12 border">
                    <div class="col-md-4">
                        <label class="form-label f-s-12 f-w-600 text-muted mb-1">Actividad Folclórica:</label>
                        <select id="selectActividadFiltro" class="form-select b-r-8">
                            <option value="">Seleccione una actividad...</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label f-s-12 f-w-600 text-muted mb-1">Versión y Procedencia Oficial:</label>
                        <div class="d-flex align-items-center gap-2">
                            <select id="selectVersionFiltro" class="form-select b-r-8">
                                <option value="">Sin versiones registradas</option>
                            </select>
                            <button type="button" class="btn btn-outline-info btn-sm text-nowrap" id="btnVerHistorialVersiones" title="Ver procedencia e historial de versiones">
                                <i class="fa-solid fa-clock-rotate-left"></i> Historial
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 text-md-end mt-3 mt-md-0">
                        <div class="d-flex gap-2 justify-content-md-end">
                            <button type="button" class="btn btn-success btn-sm text-nowrap d-none" id="btnOficializarVersion" title="Aprobar y oficializar como versión vigente">
                                <i class="fa-solid fa-stamp me-1"></i> Aprobar Oficial
                            </button>
                            <button type="button" class="btn btn-primary btn-sm text-nowrap" id="btnNuevoTurno">
                                <i class="fa-solid fa-plus me-1"></i> Asignar Turno
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Banner Informativo de Versión y Estado -->
                <div id="bannerEstadoVersion" class="alert alert-warning d-flex align-items-center justify-content-between b-r-12 mb-3 py-2 px-3 d-none">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation f-s-18" id="iconoEstadoVersion"></i>
                        <div>
                            <span class="f-w-700" id="textoBadgeEstadoVersion">VERSIÓN PRELIMINAR</span> — 
                            <span class="f-s-13" id="textoDetalleVersion">Fuente: FRFCP Puno | Emisión: 2026-02-01</span>
                        </div>
                    </div>
                    <span class="badge bg-dark f-s-12" id="badgeActivaVersion">ACTIVA</span>
                </div>

                <!-- Tabla de Turnos de Presentación -->
                <div class="app-datatable-default overflow-auto app-scroll">
                    <table id="tablaTurnos" class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px;">Sorteo</th>
                                <th>Conjunto Folclórico</th>
                                <th>Tipo de Danza</th>
                                <th>Partida Est.</th>
                                <th>Llegada Est.</th>
                                <th>Punto Partida</th>
                                <th>Salida Operativa</th>
                                <th>Estado</th>
                                <th style="width: 100px;" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Inyectado dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ==============================================================================
                 PESTAÑA 2: CATÁLOGO DE DANZAS Y CONJUNTOS
            ============================================================================== -->
            <div class="tab-pane fade" id="tab-conjuntos" role="tabpanel" aria-labelledby="tab-conjuntos-btn">
                <div class="row g-4">
                    <!-- Columna Izquierda: Tipos de Danza -->
                    <div class="col-lg-5">
                        <div class="card border b-r-12 shadow-none">
                            <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                <span class="f-w-700 f-s-14 text-dark"><i class="fa-solid fa-feather text-primary me-2"></i> Tipos de Danza</span>
                                <button type="button" class="btn btn-outline-primary btn-sm py-1" id="btnCrearDanzaSecundario">
                                    <i class="fa-solid fa-plus me-1"></i> Nueva Danza
                                </button>
                            </div>
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table id="tablaTiposDanza" class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Código</th>
                                                <th>Nombre</th>
                                                <th>Categoría</th>
                                                <th class="text-end">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Inyectado dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Conjuntos Folclóricos -->
                    <div class="col-lg-7">
                        <div class="card border b-r-12 shadow-none">
                            <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                <span class="f-w-700 f-s-14 text-dark"><i class="fa-solid fa-people-group text-success me-2"></i> Agrupaciones y Comparsas</span>
                                <button type="button" class="btn btn-outline-success btn-sm py-1" id="btnCrearConjuntoSecundario">
                                    <i class="fa-solid fa-plus me-1"></i> Nuevo Conjunto
                                </button>
                            </div>
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table id="tablaConjuntos" class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Código</th>
                                                <th>Nombre del Conjunto</th>
                                                <th>Tipo Danza</th>
                                                <th>Sede</th>
                                                <th>Bloques</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Inyectado dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==============================================================================
                 PESTAÑA 3: PADRÓN DE EDICIÓN (INSCRIPCIONES)
            ============================================================================== -->
            <div class="tab-pane fade" id="tab-inscripciones" role="tabpanel" aria-labelledby="tab-inscripciones-btn">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="f-w-700 text-dark mb-0"><i class="fa-solid fa-clipboard-check text-warning me-2"></i> Padrón Oficial de Conjuntos Participantes</h6>
                    <button type="button" class="btn btn-warning text-dark btn-sm shadow-sm" id="btnInscribirSecundario">
                        <i class="fa-solid fa-user-plus me-1"></i> Inscribir Agrupación
                    </button>
                </div>
                <div class="app-datatable-default overflow-auto app-scroll">
                    <table id="tablaInscripciones" class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>N° Registro</th>
                                <th>Conjunto Folclórico</th>
                                <th>Delegado Edición</th>
                                <th>Teléfono</th>
                                <th class="text-center">Danzarines Est.</th>
                                <th class="text-center">Bandas</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Inyectado dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ==============================================================================
                 PESTAÑA 4: SALIDAS DE OPERACIÓN VINCULADAS
            ============================================================================== -->
            <div class="tab-pane fade" id="tab-salidas" role="tabpanel" aria-labelledby="tab-salidas-btn">
                <div class="alert alert-info d-flex align-items-center b-r-12 mb-3 py-2 px-3">
                    <i class="fa-solid fa-circle-info f-s-18 me-2"></i>
                    <div class="f-s-13">
                        Vinculación opcional y retrocompatible entre las salidas de campo de CandelariaAPP V1 y los turnos oficiales de presentación folclórica.
                    </div>
                </div>
                <div class="app-datatable-default overflow-auto app-scroll">
                    <table id="tablaSalidasOperativas" class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Correlativo Salida</th>
                                <th>Fecha Programada</th>
                                <th>Hora Partida</th>
                                <th>Turno Folclórico Asociado</th>
                                <th>Conjunto / Danza</th>
                                <th>Estado Salida</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Inyectado dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ==============================================================================
                 PESTAÑA 5: TABLERO DE SEGUIMIENTO Y COBERTURA AUDIOVISUAL (FASE 3.1C)
            ============================================================================== -->
            <div class="tab-pane fade" id="tab-seguimiento" role="tabpanel" aria-labelledby="tab-seguimiento-btn">
                <!-- Tarjetas KPI del Tablero Operativo -->
                <div class="row g-2 mb-3">
                    <div class="col-md-2 col-sm-4 col-6">
                        <div class="card border border-secondary-subtle shadow-none b-r-10 p-2 text-center bg-light">
                            <span class="f-s-11 text-muted text-uppercase f-w-600">Total Conjuntos</span>
                            <h4 class="f-w-700 text-dark mb-0 mt-1" id="kpiSegTotal">0</h4>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6">
                        <div class="card border border-secondary-subtle shadow-none b-r-10 p-2 text-center bg-light">
                            <span class="f-s-11 text-muted text-uppercase f-w-600">Pendientes</span>
                            <h4 class="f-w-700 text-secondary mb-0 mt-1" id="kpiSegPendientes">0</h4>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6">
                        <div class="card border border-primary-subtle shadow-none b-r-10 p-2 text-center bg-primary-subtle">
                            <span class="f-s-11 text-primary text-uppercase f-w-600">En Curso</span>
                            <h4 class="f-w-700 text-primary mb-0 mt-1" id="kpiSegEnCurso">0</h4>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6">
                        <div class="card border border-success-subtle shadow-none b-r-10 p-2 text-center bg-success-subtle">
                            <span class="f-s-11 text-success text-uppercase f-w-600">Finalizados</span>
                            <h4 class="f-w-700 text-success mb-0 mt-1" id="kpiSegFinalizados">0</h4>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6">
                        <div class="card border border-danger-subtle shadow-none b-r-10 p-2 text-center bg-danger-subtle">
                            <span class="f-s-11 text-danger text-uppercase f-w-600">Con Retraso</span>
                            <h4 class="f-w-700 text-danger mb-0 mt-1" id="kpiSegRetrasados">0</h4>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6">
                        <div class="card border border-warning-subtle shadow-none b-r-10 p-2 text-center bg-warning-subtle">
                            <span class="f-s-11 text-warning-emphasis text-uppercase f-w-600">Sin Cobertura</span>
                            <h4 class="f-w-700 text-warning-emphasis mb-0 mt-1" id="kpiSegSinCobertura">0</h4>
                        </div>
                    </div>
                </div>

                <!-- Filtros Interactivos -->
                <div class="row g-2 align-items-center mb-3 p-3 bg-light b-r-12 border">
                    <div class="col-md-3">
                        <label class="form-label f-s-12 f-w-600 text-muted mb-1">Actividad:</label>
                        <select id="selectSegActividad" class="form-select form-select-sm b-r-8">
                            <!-- Inyectado dinámicamente -->
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label f-s-12 f-w-600 text-muted mb-1">Estado Operativo:</label>
                        <select id="selectSegEstado" class="form-select form-select-sm b-r-8">
                            <option value="">-- Todos los estados --</option>
                            <option value="PROGRAMADO">Pendiente</option>
                            <option value="EN_PREPARACION">En Preparación</option>
                            <option value="EN_RUTA">En Desplazamiento</option>
                            <option value="EN_ESCENARIO">En Presentación</option>
                            <option value="FINALIZADO">Finalizado</option>
                            <option value="NO_PRESENTO">No Presentado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label f-s-12 f-w-600 text-muted mb-1">Buscar Conjunto / Danza:</label>
                        <input type="text" class="form-control form-control-sm b-r-8" id="inputSegBusqueda" placeholder="Nombre o Danza...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label f-s-12 f-w-600 text-muted mb-1">Personal Asignado:</label>
                        <select id="selectSegPersonal" class="form-select form-select-sm b-r-8">
                            <option value="">-- Todo el personal --</option>
                            <!-- Inyectado dinámicamente -->
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger btn-sm w-100 b-r-8" id="btnRefrescarSeguimiento" title="Actualizar tablero">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </div>
                </div>

                <!-- Tabla del Tablero Operativo -->
                <div class="table-responsive">
                    <table id="tablaTableroSeguimiento" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px;">Turno</th>
                                <th>Conjunto Folclórico</th>
                                <th>Horario Programado</th>
                                <th>Estado Operativo</th>
                                <th>Horas Reales & Retraso</th>
                                <th>Cobertura Audiovisual</th>
                                <th>Incidencias</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Inyectado dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 1: TIPO DE DANZA
============================================================================== -->
<div class="modal fade" id="modalTipoDanza" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalTipoDanzaTitulo">
                    <i class="fa-solid fa-feather me-2"></i> Registrar Tipo de Danza
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formTipoDanza" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" id="tipoDanzaId" name="id">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Código Único <span class="text-danger">*</span></label>
                        <input type="text" class="form-control b-r-8" id="tipoDanzaCodigo" name="codigo" placeholder="Ej. MORENADA, CAPORALES" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Nombre Oficial de la Danza <span class="text-danger">*</span></label>
                        <input type="text" class="form-control b-r-8" id="tipoDanzaNombre" name="nombre" placeholder="Ej. Morenada y Danzas Pesadas" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Categoría de Origen <span class="text-danger">*</span></label>
                        <select class="form-select b-r-8" id="tipoDanzaCategoria" name="categoria_origen" required>
                            <option value="TRAJES_DE_LUCES">TRAJES DE LUCES</option>
                            <option value="DANZAS_AUTOCTONAS">DANZAS AUTÓCTONAS</option>
                            <option value="MESTIZAS_SENORIALES">MESTIZAS Y SEÑORIALES</option>
                            <option value="OTRO">OTRO</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Descripción / Reseña</label>
                        <textarea class="form-control b-r-8" id="tipoDanzaDescripcion" name="descripcion" rows="2" placeholder="Características estéticas, coreográficas y musicales..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light b-r-bottom-16">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardarTipoDanza">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 2: CONJUNTO FOLCLÓRICO
============================================================================== -->
<div class="modal fade" id="modalConjunto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-success text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalConjuntoTitulo">
                    <i class="fa-solid fa-people-group me-2"></i> Registrar Conjunto Folclórico
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formConjunto" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" id="conjuntoId" name="id">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control b-r-8" id="conjuntoCodigo" name="codigo" placeholder="Ej. MORENADA-LAYKAKOTA" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label f-s-13 f-w-600">Nombre de la Agrupación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control b-r-8" id="conjuntoNombre" name="nombre" placeholder="Ej. Morenada Central Laykakota" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Tipo de Danza <span class="text-danger">*</span></label>
                            <select class="form-select b-r-8" id="conjuntoTipoDanzaId" name="tipo_danza_id" required>
                                <!-- Inyectado dinámicamente -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Sigla / Abreviatura</label>
                            <input type="text" class="form-control b-r-8" id="conjuntoSigla" name="sigla" placeholder="Ej. MCL">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Fecha de Fundación</label>
                            <input type="text" class="form-control b-r-8 selector-fecha" id="conjuntoFechaFundacion" name="fecha_fundacion" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Ciudad Sede</label>
                            <input type="text" class="form-control b-r-8" id="conjuntoCiudadSede" name="ciudad_sede" placeholder="Puno">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Color Emblemático</label>
                            <input type="color" class="form-control form-control-color w-100 b-r-8" id="conjuntoColorHex" name="color_emblematico_hex" value="#B22222">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Presidente / Representante</label>
                            <input type="text" class="form-control b-r-8" id="conjuntoPresidente" name="nombre_presidente" placeholder="Nombre completo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Teléfono Contacto</label>
                            <input type="text" class="form-control b-r-8" id="conjuntoTelefono" name="telefono_contacto" placeholder="+519...">
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600">Reseña Histórica</label>
                            <textarea class="form-control b-r-8" id="conjuntoResena" name="resena_historica" rows="2" placeholder="Historia, distinciones y trayectoria..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light b-r-bottom-16">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm px-4" id="btnGuardarConjunto">Guardar Conjunto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 3: ADMINISTRACIÓN DE BLOQUES Y FILIALES
============================================================================== -->
<div class="modal fade" id="modalBloquesConjunto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-dark text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalBloquesTitulo">
                    <i class="fa-solid fa-network-wired me-2"></i> Bloques y Filiales del Conjunto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="bloqueModalConjuntoId">
                <div class="p-3 bg-light b-r-12 border mb-4">
                    <h6 class="f-w-700 text-dark mb-2" id="bloqueSubtituloForm">Añadir Nuevo Bloque / Filial</h6>
                    <form id="formBloque" novalidate>
                        <input type="hidden" id="bloqueId" name="id">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <input type="text" class="form-control form-control-sm b-r-8" id="bloqueCodigo" name="codigo" placeholder="Código (ej. FIL-AQP)" required>
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control form-control-sm b-r-8" id="bloqueNombre" name="nombre" placeholder="Nombre Filial/Bloque" required>
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control form-control-sm b-r-8" id="bloqueResponsable" name="responsable_nombre" placeholder="Responsable">
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex gap-1">
                                    <input type="text" class="form-control form-control-sm b-r-8" id="bloqueTelefono" name="telefono_contacto" placeholder="Teléfono">
                                    <button type="submit" class="btn btn-primary btn-sm px-3" id="btnGuardarBloque"><i class="fa-solid fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table id="tablaBloquesDetalle" class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Bloque / Filial</th>
                                <th>Responsable</th>
                                <th>Teléfono</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Inyectado dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 bg-light b-r-bottom-16">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 4: INSCRIPCIÓN EN EDICIÓN
============================================================================== -->
<div class="modal fade" id="modalInscripcion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-warning text-dark b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalInscripcionTitulo">
                    <i class="fa-solid fa-clipboard-check me-2"></i> Inscribir Agrupación en Edición
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formInscripcion" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" id="inscripcionId" name="id">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Conjunto Folclórico <span class="text-danger">*</span></label>
                        <select class="form-select b-r-8" id="inscripcionConjuntoId" name="conjunto_id" required>
                            <!-- Inyectado dinámicamente -->
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">N° Registro Oficial FRFCP</label>
                            <input type="text" class="form-control b-r-8" id="inscripcionNumeroRegistro" name="numero_registro_oficial" placeholder="Ej. FRFCP-2026-042">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Estado de Inscripción</label>
                            <select class="form-select b-r-8" id="inscripcionEstado" name="estado">
                                <option value="INSCRITO">INSCRITO</option>
                                <option value="CONFIRMADO">CONFIRMADO</option>
                                <option value="RETIRADO">RETIRADO</option>
                                <option value="DESCALIFICADO">DESCALIFICADO</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Delegado de Edición</label>
                            <input type="text" class="form-control b-r-8" id="inscripcionDelegado" name="nombre_delegado_edicion" placeholder="Nombre delegado">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Teléfono Delegado</label>
                            <input type="text" class="form-control b-r-8" id="inscripcionTelefonoDelegado" name="telefono_delegado_edicion" placeholder="+519...">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Danzarines Estimados</label>
                            <input type="number" class="form-control b-r-8" id="inscripcionDanzarines" name="cantidad_estimada_danzarines" value="250" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Cantidad de Bandas</label>
                            <input type="number" class="form-control b-r-8" id="inscripcionBandas" name="cantidad_bandas" value="2" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Observaciones</label>
                        <textarea class="form-control b-r-8" id="inscripcionObservaciones" name="observaciones" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light b-r-bottom-16">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm text-dark px-4" id="btnGuardarInscripcion">Inscribir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 5: ACTIVIDAD FOLCLÓRICA DE EDICIÓN
============================================================================== -->
<div class="modal fade" id="modalActividad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-info text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600">
                    <i class="fa-solid fa-calendar-plus me-2"></i> Registrar Actividad Folclórica
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formActividad" novalidate>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Código de Actividad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control b-r-8" id="actividadCodigo" name="codigo" placeholder="Ej. PARADA_VENERACION_2026" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Título de la Actividad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control b-r-8" id="actividadTitulo" name="titulo" placeholder="Ej. Gran Parada y Veneración a la Virgen de la Candelaria" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Fecha y Hora Inicio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control b-r-8 selector-fecha-hora" id="actividadInicio" name="fecha_inicio" placeholder="YYYY-MM-DD HH:MM:SS" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Fecha y Hora Fin <span class="text-danger">*</span></label>
                            <input type="text" class="form-control b-r-8 selector-fecha-hora" id="actividadFin" name="fecha_fin" placeholder="YYYY-MM-DD HH:MM:SS" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Lugar Principal <span class="text-danger">*</span></label>
                        <input type="text" class="form-control b-r-8" id="actividadLugar" name="lugar_principal" placeholder="Ej. Av. Simón Bolívar - Jr. Lampa" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light b-r-bottom-16">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info text-white btn-sm px-4" id="btnGuardarActividad">Guardar Actividad</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 6: PUBLICAR NUEVA VERSIÓN DE PROGRAMACIÓN OFICIAL
============================================================================== -->
<div class="modal fade" id="modalVersion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-danger text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600">
                    <i class="fa-solid fa-code-branch me-2"></i> Publicar Versión de Programación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formVersion" novalidate>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Actividad Folclórica Destino <span class="text-danger">*</span></label>
                        <select class="form-select b-r-8" id="versionActividadId" name="actividad_id" required>
                            <!-- Inyectado dinámicamente -->
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Procedencia / Fuente <span class="text-danger">*</span></label>
                            <input type="text" class="form-control b-r-8" id="versionFuente" name="fuente_origen" value="FRFCP Puno" placeholder="Ej. FRFCP Puno, Comisión Central" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Fecha Emisión Fuente <span class="text-danger">*</span></label>
                            <input type="text" class="form-control b-r-8 selector-fecha" id="versionFechaFuente" name="fecha_emision_fuente" placeholder="YYYY-MM-DD" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Etiqueta de la Versión</label>
                        <input type="text" class="form-control b-r-8" id="versionEtiqueta" name="etiqueta_version" value="Rol Oficial de Parada" placeholder="Ej. Rol Oficial de Parada">
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Estado de Confirmación Inicial</label>
                        <select class="form-select b-r-8" id="versionEstado" name="estado_confirmacion">
                            <option value="PRELIMINAR" selected>PRELIMINAR (Borrador de Trabajo)</option>
                            <option value="OFICIAL_CONFIRMADO">OFICIAL CONFIRMADO (Requiere aprobación)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">URL / Documento de Referencia</label>
                        <input type="url" class="form-control b-r-8" id="versionDocUrl" name="documento_referencia_url" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Notas de Publicación</label>
                        <textarea class="form-control b-r-8" id="versionNotas" name="notas_version" rows="2" placeholder="Motivo de la versión, resolución oficial o ajustes de sorteo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light b-r-bottom-16">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4" id="btnGuardarVersion">Publicar Versión</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 7: ASIGNAR TURNO DE PRESENTACIÓN
============================================================================== -->
<div class="modal fade" id="modalTurno" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalTurnoTitulo">
                    <i class="fa-solid fa-clock me-2"></i> Asignar Turno de Presentación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formTurno" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" id="turnoId" name="id">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Conjunto Inscrito <span class="text-danger">*</span></label>
                        <select class="form-select b-r-8" id="turnoConjuntoEdicionId" name="conjunto_edicion_id" required>
                            <!-- Inyectado dinámicamente -->
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Orden Sorteo <span class="text-danger">*</span></label>
                            <input type="number" class="form-control b-r-8" id="turnoOrdenSorteo" name="orden_sorteo" min="1" value="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Hora Partida <span class="text-danger">*</span></label>
                            <input type="text" class="form-control b-r-8 selector-hora" id="turnoHoraPartida" name="hora_estimada_partida" placeholder="HH:MM:SS" value="08:00:00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Hora Llegada Est.</label>
                            <input type="text" class="form-control b-r-8 selector-hora" id="turnoHoraLlegada" name="hora_estimada_llegada" placeholder="HH:MM:SS">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Punto de Partida Específico</label>
                        <input type="text" class="form-control b-r-8" id="turnoPuntoPartida" name="punto_partida_especifico" placeholder="Ej. Puerta Principal Estadio Enrique Torres Belón">
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Estado del Turno</label>
                        <select class="form-select b-r-8" id="turnoEstado" name="estado_turno">
                            <option value="PROGRAMADO">PROGRAMADO</option>
                            <option value="EN_ESCENARIO">EN ESCENARIO</option>
                            <option value="CULMINADO">CULMINADO</option>
                            <option value="AUSENTE">AUSENTE</option>
                            <option value="REPROGRAMADO">REPROGRAMADO</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Observaciones</label>
                        <textarea class="form-control b-r-8" id="turnoObservaciones" name="observaciones" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light b-r-bottom-16">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardarTurno">Guardar Turno</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 8: HISTORIAL DE VERSIONES CON PROCEDENCIA
============================================================================== -->
<div class="modal fade" id="modalHistorialVersiones" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-dark text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600">
                    <i class="fa-solid fa-clock-rotate-left me-2"></i> Historial y Procedencia de Versiones
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table id="tablaHistorialVersiones" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Versión</th>
                                <th>Fuente Oficial</th>
                                <th>Fecha Emisión</th>
                                <th>Estado</th>
                                <th>Vigencia</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Inyectado dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 bg-light b-r-bottom-16">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 9: VINCULAR SALIDA OPERATIVA A TURNO
============================================================================== -->
<div class="modal fade" id="modalVincularSalida" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-secondary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600">
                    <i class="fa-solid fa-link me-2"></i> Vincular Salida de Campo a Turno
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formVincularSalida" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" id="vincularSalidaId" name="salida_id">
                    <p class="f-s-13 text-muted mb-3" id="vincularSalidaTextoInfo">
                        Seleccione el turno folclórico al cual se asignará esta salida operativa.
                    </p>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Turno de Presentación Folclórico</label>
                        <select class="form-select b-r-8" id="vincularTurnoId" name="orden_presentacion_id">
                            <!-- Inyectado dinámicamente -->
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light b-r-bottom-16">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnDesvincularSalida">Desvincular</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardarVinculoSalida">Asignar Vínculo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 10: CONTROL OPERATIVO Y ESTADO DE TURNO (FASE 3.1C)
============================================================================== -->
<div class="modal fade" id="modalActualizarEstadoOperativo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-danger text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600">
                    <i class="fa-solid fa-stopwatch-20 me-2"></i> Control Operativo de Turno
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formActualizarEstadoOperativo" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" id="segTurnoId" name="turno_id">
                    <input type="hidden" id="segVersionBloqueo" name="version_bloqueo">

                    <div class="p-3 mb-3 bg-light b-r-10 border border-secondary-subtle">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="badge bg-secondary mb-1" id="segBadgeSorteo">Turno #--</span>
                                <h6 class="f-w-700 mb-0 text-dark" id="segNombreConjunto">Cargando comparsa...</h6>
                                <span class="f-s-12 text-muted" id="segNombreDanza">Danza</span>
                            </div>
                            <div class="text-end">
                                <span class="f-s-11 text-muted d-block">Programado:</span>
                                <span class="f-s-13 f-w-700 text-primary" id="segHorarioEstimado">--:--</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Estado Operativo <span class="text-danger">*</span></label>
                        <select class="form-select b-r-8" id="segEstadoTurno" name="estado_turno" required>
                            <option value="PROGRAMADO">Pendiente (En espera de turno)</option>
                            <option value="EN_PREPARACION">En Preparación (Zona de concentración)</option>
                            <option value="EN_RUTA">En Desplazamiento (Avanzando en pasacalle)</option>
                            <option value="EN_ESCENARIO">En Presentación (Frente a estrado/veneración)</option>
                            <option value="FINALIZADO">Finalizado (Culminó participación)</option>
                            <option value="NO_PRESENTO">No Presentado (Ausente / Descalificado)</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-12 f-w-600">Hora Real de Inicio / Partida</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control b-r-left-8" id="segHoraRealPartida" name="hora_real_partida" placeholder="YYYY-MM-DD HH:MM:SS">
                                <button type="button" class="btn btn-outline-secondary" id="btnHoraPartidaAhora" title="Establecer hora actual">Ahora</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-12 f-w-600">Hora Real de Finalización</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control b-r-left-8" id="segHoraRealFin" name="hora_real_fin" placeholder="YYYY-MM-DD HH:MM:SS">
                                <button type="button" class="btn btn-outline-secondary" id="btnHoraFinAhora" title="Establecer hora actual">Ahora</button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Retraso Operativo Estimado (en minutos)</label>
                        <input type="number" class="form-control b-r-8" id="segRetrasoMinutos" name="retraso_minutos" min="0" placeholder="0">
                        <span class="f-s-11 text-muted">Si se deja vacío, el sistema calculará el retraso automáticamente respecto a la hora programada.</span>
                    </div>

                    <div class="mb-2">
                        <label class="form-label f-s-13 f-w-600">Bitácora de Incidencias / Observaciones</label>
                        <textarea class="form-control b-r-8" id="segObservaciones" name="observaciones" rows="3" placeholder="Detalle cualquier demora, condición climática, incidente logístico o contingencia de campo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light b-r-bottom-16">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4" id="btnGuardarEstadoOperativo">Actualizar Estado</button>
                </div>
            </form>
        </div>
    </div>
</div>

