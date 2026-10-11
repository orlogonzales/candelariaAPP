<!-- Encabezado de la Sección de Ediciones Candelaria -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-calendar-check text-primary me-2"></i> Ediciones Candelaria
        </h3>
        <p class="text-secondary mb-0">
            Administración del ciclo de vida de festividades, estados de producción y contexto operativo activo.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalCrear">
                <i class="fa-solid fa-plus me-2"></i> Nueva Edición
            </button>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen / KPIs de Ediciones -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Ediciones</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalEdiciones">-</h3>
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
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Edición Institucional</span>
                    <h5 class="f-w-700 text-success mb-0 mt-1 text-truncate" style="max-width: 170px;" id="kpiEdicionActual">-</h5>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-star"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Contexto Esta Pestaña</span>
                    <h5 class="f-w-700 text-info mb-0 mt-1 text-truncate" style="max-width: 170px;" id="kpiEdicionPestana">-</h5>
                </div>
                <div class="w-45 h-45 d-flex-center bg-info-subtle text-info rounded-circle f-s-20">
                    <i class="fa-solid fa-window-maximize"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Gobernanza Ciclo</span>
                    <h5 class="f-w-600 text-dark mb-0 mt-2">Máquina de Estados</h5>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-diagram-project"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor Principal de la Tabla con Soporte Skeleton -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-body p-3">
        <div id="contenedorTablaEdiciones" class="app-datatable-default overflow-auto app-scroll">
            <!-- Tabla inyectada dinámicamente por ediciones.js -->
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 1: CREAR NUEVA EDICIÓN
============================================================================== -->
<div class="modal fade" id="modalCrearEdicion" tabindex="-1" aria-labelledby="modalCrearEdicionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCrearEdicionLabel">
                    <i class="fa-solid fa-calendar-plus me-2"></i> Registrar Nueva Edición Candelaria
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCrearEdicion" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="crearCodigo" class="form-label f-w-600 f-s-13">Código Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="crearCodigo" name="codigo" placeholder="Ej. candelaria-2027" required maxlength="50" style="text-transform: lowercase;">
                            <div class="form-text f-s-11">Identificador slug canónico en minúsculas (ej: candelaria-2027).</div>
                        </div>
                        <div class="col-md-6">
                            <label for="crearAnio" class="form-label f-w-600 f-s-13">Año de la Festividad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="crearAnio" name="anio" placeholder="Ej. 2027" required min="2000" max="2100">
                            <div class="form-text f-s-11">Unicidad estricta por organización.</div>
                        </div>
                        <div class="col-12">
                            <label for="crearNombre" class="form-label f-w-600 f-s-13">Nombre Oficial de la Edición <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="crearNombre" name="nombre" placeholder="Ej. Festividad Virgen de la Candelaria 2027" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label for="crearFechaInicio" class="form-label f-w-600 f-s-13">Fecha de Inicio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control candelaria-flatpickr bg-white" id="crearFechaInicio" name="fecha_inicio" placeholder="AAAA-MM-DD" required>
                        </div>
                        <div class="col-md-6">
                            <label for="crearFechaFin" class="form-label f-w-600 f-s-13">Fecha de Culminación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control candelaria-flatpickr bg-white" id="crearFechaFin" name="fecha_fin" placeholder="AAAA-MM-DD" required>
                        </div>
                        <div class="col-12">
                            <label for="crearDescripcion" class="form-label f-w-600 f-s-13">Descripción / Notas Operativas</label>
                            <textarea class="form-control" id="crearDescripcion" name="descripcion" rows="3" placeholder="Detalles de organización, cobertura audiovisual o metas de la festividad..."></textarea>
                        </div>
                    </div>
                    <div class="alert alert-info d-flex align-items-center gap-2 mt-3 mb-0 f-s-12">
                        <i class="fa-solid fa-circle-info f-s-16 flex-shrink-0"></i>
                        <span>La edición se inicializará automáticamente en estado <strong>PREOPERACION</strong> y con <code>es_actual = 0</code>.</span>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCrear">
                        <i class="fa-solid fa-save me-1"></i> Guardar Edición
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 2: EDITAR EDICIÓN (CONCURRENCIA OPTIMISTA + SKELETON)
============================================================================== -->
<div class="modal fade" id="modalEditarEdicion" tabindex="-1" aria-labelledby="modalEditarEdicionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalEditarEdicionLabel">
                    <i class="fa-solid fa-pen-to-square me-2"></i> Modificar Datos de la Edición
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarEdicion" novalidate>
                <input type="hidden" id="editarEdicionId" name="id">
                <input type="hidden" id="editarActualizadoEnEsperado" name="actualizado_en_esperado">

                <div class="modal-body p-4">
                    <!-- Skeleton Loader mientras se cargan los datos del servidor -->
                    <div id="skeletonEditarEdicion" class="d-none">
                        <div class="row g-3">
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-input"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-input"></div></div>
                            <div class="col-12"><div class="skeleton-shimmer skeleton-input"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-input"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-input"></div></div>
                            <div class="col-12"><div class="skeleton-shimmer skeleton-line lg w-100"></div></div>
                        </div>
                    </div>

                    <div id="panelFormEditar">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label f-w-600 f-s-13">Código Slug</label>
                                <input type="text" class="form-control bg-light" id="editarCodigo" readonly disabled>
                                <div class="form-text f-s-11">El código de edición es inmutable tras su creación.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label f-w-600 f-s-13">Año</label>
                                <input type="text" class="form-control bg-light" id="editarAnio" readonly disabled>
                                <div class="form-text f-s-11">El año de festividad es inmutable.</div>
                            </div>
                            <div class="col-12">
                                <label for="editarNombre" class="form-label f-w-600 f-s-13">Nombre Oficial <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editarNombre" name="nombre" required maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label for="editarFechaInicio" class="form-label f-w-600 f-s-13">Fecha de Inicio <span class="text-danger">*</span></label>
                                <input type="text" class="form-control candelaria-flatpickr bg-white" id="editarFechaInicio" name="fecha_inicio" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editarFechaFin" class="form-label f-w-600 f-s-13">Fecha de Culminación <span class="text-danger">*</span></label>
                                <input type="text" class="form-control candelaria-flatpickr bg-white" id="editarFechaFin" name="fecha_fin" required>
                            </div>
                            <div class="col-12">
                                <label for="editarDescripcion" class="form-label f-w-600 f-s-13">Descripción / Notas Operativas</label>
                                <textarea class="form-control" id="editarDescripcion" name="descripcion" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarEditar">
                        <i class="fa-solid fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 3: CAMBIAR ESTADO DE LA EDICIÓN (GOBERNANZA DE CICLO DE VIDA)
============================================================================== -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1" aria-labelledby="modalCambiarEstadoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-dark text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCambiarEstadoLabel">
                    <i class="fa-solid fa-diagram-project me-2"></i> Transición de Estado de Edición
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCambiarEstado" novalidate>
                <input type="hidden" id="estadoEdicionId" name="id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Edición Seleccionada</label>
                        <div class="p-2 bg-light rounded border f-w-600 text-dark" id="estadoEdicionNombre">-</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Estado Actual</label>
                        <div id="estadoEdicionActualBadge">-</div>
                    </div>
                    <div class="mb-3">
                        <label for="selectNuevoEstado" class="form-label f-w-600 f-s-13">Nuevo Estado Operativo <span class="text-danger">*</span></label>
                        <select class="form-select" id="selectNuevoEstado" name="nuevo_estado" required>
                            <!-- Opciones inyectadas dinámicamente según la máquina de estados -->
                        </select>
                    </div>
                    <div id="panelMotivoRetroceso" class="mb-3 d-none">
                        <label for="inputMotivoRetroceso" class="form-label f-w-600 f-s-13 text-danger">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Motivo Obligatorio del Retroceso <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control border-danger" id="inputMotivoRetroceso" name="motivo_retroceso" rows="3" placeholder="Explique detalladamente la justificación técnica u operativa del retroceso excepcional..."></textarea>
                        <div class="form-text f-s-11 text-danger">Este retroceso quedará registrado con auditoría inmutable vinculada al operador.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark" id="btnEjecutarCambioEstado">
                        <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Aplicar Transición
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 4: ASISTENTE DE CIERRE ADMINISTRATIVO Y FINANCIERO (F2.12A)
============================================================================== -->
<div class="modal fade" id="modalAsistenteCierre" tabindex="-1" aria-labelledby="modalAsistenteCierreLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-dark text-white b-r-top-16 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="w-35 h-35 d-flex-center bg-danger text-white rounded-circle">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <div>
                        <h5 class="modal-title f-w-700 mb-0" id="modalAsistenteCierreLabel">Asistente de Cierre Administrativo y Financiero</h5>
                        <span class="f-s-12 text-light-emphasis" id="cierreEdicionSubtitulo">Auditoría previa, diagnóstico de incidencias y congelamiento oficial</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="cierreEdicionId" value="">
                
                <!-- Loading State -->
                <div id="cierreLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 f-s-13">Auditoría en proceso: Evaluando ventas, caja, pagos, salidas y postproducción...</p>
                </div>

                <!-- Contenido Dinámico del Diagnóstico -->
                <div id="cierreContenido" class="d-none">
                    <!-- Banner de Estado General -->
                    <div id="cierreDictamenBanner" class="p-3 b-r-12 mb-4">
                        <!-- Inyectado por JS: Apto Definitivo, Apto Condicionado o Bloqueado -->
                    </div>

                    <!-- Cuadrícula de Balance Financiero Congelable -->
                    <h6 class="f-w-700 text-dark mb-3"><i class="fa-solid fa-scale-balanced me-2 text-primary"></i>Balance Financiero y Conciliación de Festividad</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card bg-light border-0 b-r-10 p-3">
                                <span class="f-s-11 text-muted text-uppercase f-w-600">Total Facturado</span>
                                <h4 class="f-w-700 text-dark mb-0 mt-1" id="diagTotalFacturado">S/ 0.00</h4>
                                <span class="f-s-11 text-muted" id="diagVentasActivas">0 ventas confirmadas</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light border-0 b-r-10 p-3">
                                <span class="f-s-11 text-muted text-uppercase f-w-600">Total Recaudado</span>
                                <h4 class="f-w-700 text-success mb-0 mt-1" id="diagTotalRecaudado">S/ 0.00</h4>
                                <span class="f-s-11 text-success" id="diagRecibosCaja">Recibos emitidos: 0</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light border-0 b-r-10 p-3">
                                <span class="f-s-11 text-muted text-uppercase f-w-600">Saldo por Cobrar</span>
                                <h4 class="f-w-700 text-warning mb-0 mt-1" id="diagSaldoPorCobrar">S/ 0.00</h4>
                                <span class="f-s-11 text-muted" id="diagVentasConSaldo">0 con saldo pendiente</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light border-0 b-r-10 p-3">
                                <span class="f-s-11 text-muted text-uppercase f-w-600">Operaciones & Entregas</span>
                                <h4 class="f-w-700 text-info mb-0 mt-1" id="diagSalidasFinalizadas">0 Salidas</h4>
                                <span class="f-s-11 text-muted" id="diagEntregasAprobadas">0 entregas aprobadas</span>
                            </div>
                        </div>
                    </div>

                    <!-- Panel de Incidencias y Observaciones -->
                    <h6 class="f-w-700 text-dark mb-2"><i class="fa-solid fa-list-check me-2 text-primary"></i>Diagnóstico de Incidencias Operativas</h6>
                    <div id="cierreListaIncidencias" class="mb-4">
                        <!-- Inyectado dinámicamente -->
                    </div>

                    <!-- Formulario de Ejecución de Cierre -->
                    <form id="formEjecutarCierre" novalidate>
                        <div class="card border b-r-12 p-3 bg-light">
                            <h6 class="f-w-700 text-dark mb-3"><i class="fa-solid fa-file-signature me-2 text-primary"></i>Modalidad y Acta de Cierre</h6>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="form-check p-3 border rounded bg-white" id="optCierreDefinitivoContainer">
                                        <input class="form-check-input" type="radio" name="tipo_cierre" id="tipoCierreDefinitivo" value="DEFINITIVO">
                                        <label class="form-check-label f-w-600 text-dark" for="tipoCierreDefinitivo">
                                            <i class="fa-solid fa-circle-check text-success me-1"></i> Cierre Definitivo (Sin Saldos ni Pendientes)
                                        </label>
                                        <div class="form-text f-s-12 text-muted mt-1">Requiere 0 incidencias bloqueantes y 0 observaciones financieras.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check p-3 border rounded bg-white" id="optCierreCondicionadoContainer">
                                        <input class="form-check-input" type="radio" name="tipo_cierre" id="tipoCierreCondicionado" value="CONDICIONADO">
                                        <label class="form-check-label f-w-600 text-dark" for="tipoCierreCondicionado">
                                            <i class="fa-solid fa-file-contract text-warning me-1"></i> Cierre Condicionado (Con Justificación y Cuentas x Cobrar)
                                        </label>
                                        <div class="form-text f-s-12 text-muted mt-1">Permite cuentas por cobrar legítimas y entregas audiovisuales en proceso bajo autorización.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Campos para Cierre Condicionado -->
                            <div id="panelCamposCondicionado" class="d-none">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-7">
                                        <label for="cierreJustificacion" class="form-label f-w-600 f-s-13 text-dark">
                                            Justificación Formal del Cierre Condicionado <span class="text-danger">*</span>
                                        </label>
                                        <textarea class="form-control" id="cierreJustificacion" name="justificacion" rows="3" placeholder="Explique las obligaciones legítimas pendientes, compromisos de pago acordados con clientes o cronograma de entrega audiovisual..."></textarea>
                                    </div>
                                    <div class="col-md-5">
                                        <label for="cierreAutorizador" class="form-label f-w-600 f-s-13 text-dark">
                                            Usuario que Autoriza el Cierre Condicionado <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="cierreAutorizador" name="autorizador_id">
                                            <option value="">Seleccione autorizador formal...</option>
                                        </select>
                                        <div class="form-text f-s-11 text-muted">Debe ser un usuario con facultades superiores en la organización.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Checkbox de Advertencia Soberana -->
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="cierreConfirmacion" required>
                                <label class="form-check-label f-s-13 text-dark" for="cierreConfirmacion">
                                    <strong>Certifico la veracidad del balance.</strong> Comprendo que la edición quedará en estado <span class="badge bg-danger">CERRADA</span>, bloqueando nuevas ventas y modificaciones operativas, y que el snapshot quedará congelado con hash SHA-256 inmutable.
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger" id="btnEjecutarCierreOficial" disabled>
                                <i class="fa-solid fa-lock me-1"></i> Asentar Cierre Oficial
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 5: ACTA DE CIERRE VIGENTE Y REAPERTURA EXCEPCIONAL
============================================================================== -->
<div class="modal fade" id="modalActaCierre" tabindex="-1" aria-labelledby="modalActaCierreLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-dark text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-700" id="modalActaCierreLabel">
                    <i class="fa-solid fa-stamp me-2 text-warning"></i> Acta Oficial de Cierre de Festividad
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 bg-light b-r-12 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="f-w-700 f-s-15 text-dark" id="actaEdicionNombre">-</span>
                        <span id="actaTipoBadge" class="badge bg-danger">-</span>
                    </div>
                    <div class="f-s-12 text-muted mb-1"><strong>Fecha de Cierre:</strong> <span id="actaFecha">-</span></div>
                    <div class="f-s-12 text-muted mb-1"><strong>Asentado por:</strong> <span id="actaUsuario">-</span></div>
                    <div id="actaAutorizadorFila" class="f-s-12 text-muted mb-1 d-none"><strong>Autorizado por:</strong> <span id="actaAutorizador">-</span></div>
                    <div id="actaJustificacionFila" class="f-s-12 text-muted mb-2 d-none"><strong>Justificación:</strong> <span id="actaJustificacion" class="fst-italic">-</span></div>
                    <div class="f-s-11 text-muted font-monospace text-break">
                        <strong>Hash de Integridad (SHA-256):</strong> <span id="actaHash" class="text-primary">-</span>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <span class="f-s-12 text-muted"><i class="fa-solid fa-shield-halved me-1 text-primary"></i> Certificación Criptográfica HMAC</span>
                        <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2 f-s-12" id="btnVerificarIntegridadCierre">
                            <i class="fa-solid fa-check-double me-1"></i> Auditar Sellado
                        </button>
                    </div>
                </div>

                <!-- Snapshot financiero resumido -->
                <h6 class="f-w-700 text-dark mb-2">Cifras Congeladas al Cierre</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <tbody id="actaTablaCifras">
                            <!-- Inyectado por JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Sección de Reapertura Excepcional -->
                <div id="seccionReaperturaExcepcional" class="p-3 border border-danger-subtle bg-danger-subtle b-r-12 mt-4">
                    <h6 class="f-w-700 text-danger mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Procedimiento Extraordinario de Reapertura</h6>
                    <p class="f-s-12 text-danger-emphasis mb-3">
                        Solo debe utilizarse ante rectificaciones formales justificadas. El cierre actual pasará a estado REABIERTO_EXCEPCIONAL y quedará en bitácora de auditoría inmutable.
                    </p>
                    <button type="button" class="btn btn-outline-danger btn-sm f-w-600" id="btnMostrarFormReapertura">
                        <i class="fa-solid fa-unlock-keyhole me-1"></i> Solicitar Reapertura Excepcional
                    </button>

                    <form id="formReapertura" class="d-none mt-3" novalidate>
                        <div class="mb-3">
                            <label for="reaperturaMotivo" class="form-label f-w-600 f-s-12 text-dark">
                                Motivo Formal y Fundamentado (Mínimo 20 caracteres) <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="reaperturaMotivo" rows="3" required placeholder="Fundamente detalladamente la necesidad de reabrir la festividad..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="reaperturaAutorizador" class="form-label f-w-600 f-s-12 text-dark">
                                Usuario Autorizador de la Reapertura <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="reaperturaAutorizador" required>
                                <option value="">Seleccione autorizador...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="reaperturaClaveAutorizador" class="form-label f-w-600 f-s-12 text-dark">
                                Contraseña del Autorizador (Firma de Cuatro Ojos) <span class="text-danger">*</span>
                            </label>
                            <input type="password" class="form-control" id="reaperturaClaveAutorizador" required placeholder="Contraseña de confirmación del usuario autorizador...">
                            <div class="form-text f-s-11 text-muted">Acredita la aprobación expresa y voluntaria del usuario autorizador.</div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light btn-sm" id="btnCancelarReapertura">Cancelar</button>
                            <button type="submit" class="btn btn-danger btn-sm" id="btnConfirmarReapertura">
                                <i class="fa-solid fa-unlock me-1"></i> Confirmar y Reabrir Festividad
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 6: REPORTES OFICIALES DE EDICIÓN Y CIERRE (FASE 2.12B)
============================================================================== -->
<div class="modal fade" id="modalReportesOficiales" tabindex="-1" aria-labelledby="modalReportesOficialesLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-700" id="modalReportesOficialesLabel">
                    <i class="fa-solid fa-file-invoice me-2"></i> Reportes Oficiales de Festividad y Cierre
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 bg-light b-r-12 mb-4 d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <span class="f-s-12 text-muted text-uppercase f-w-600 d-block">Festividad Seleccionada</span>
                        <h5 class="f-w-700 text-dark mb-0" id="repEdicionTitulo">-</h5>
                    </div>
                    <div class="text-end mt-2 mt-sm-0">
                        <span id="repEdicionEstadoBadge" class="badge bg-secondary">-</span>
                        <div class="f-s-11 text-muted mt-1">Corte oficial generado con hash probatorio</div>
                    </div>
                </div>

                <!-- Tabs de Navegación entre los 3 Reportes Oficiales -->
                <ul class="nav nav-tabs nav-tabs-bordered mb-3" id="tabReportesOficiales" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-w-600" id="tab-libro-ingresos" data-bs-toggle="tab" data-bs-target="#panel-libro-ingresos" type="button" role="tab" aria-selected="true">
                            <i class="fa-solid fa-coins text-success me-2"></i> Libro de Ingresos y Cobranzas
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600" id="tab-manifiesto-salidas" data-bs-toggle="tab" data-bs-target="#panel-manifiesto-salidas" type="button" role="tab" aria-selected="false">
                            <i class="fa-solid fa-bus-simple text-info me-2"></i> Manifiesto de Salidas Operativas
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600" id="tab-padron-asistencia" data-bs-toggle="tab" data-bs-target="#panel-padron-asistencia" type="button" role="tab" aria-selected="false">
                            <i class="fa-solid fa-user-check text-warning me-2"></i> Padrón Oficial de Asistencia (Ley 29733)
                        </button>
                    </li>
                </ul>

                <!-- Contenido de las Pestañas -->
                <div class="tab-content" id="tabContentReportes">
                    <!-- 1. LIBRO DE INGRESOS -->
                    <div class="tab-pane fade show active" id="panel-libro-ingresos" role="tabpanel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-3 bg-body-tertiary rounded">
                            <div class="d-flex flex-wrap gap-3">
                                <div><span class="f-s-11 text-muted d-block">TOTAL DEVENGADO:</span><strong class="f-s-14 text-dark" id="repLibroTotalVentas">S/ 0.00</strong></div>
                                <div><span class="f-s-11 text-muted d-block">TOTAL COBRADO:</span><strong class="f-s-14 text-success" id="repLibroTotalCobrado">S/ 0.00</strong></div>
                                <div><span class="f-s-11 text-muted d-block">COMISIONES:</span><strong class="f-s-14 text-secondary" id="repLibroTotalComisiones">S/ 0.00</strong></div>
                                <div><span class="f-s-11 text-muted d-block">SALDO PENDIENTE:</span><strong class="f-s-14 text-danger" id="repLibroTotalSaldos">S/ 0.00</strong></div>
                            </div>
                            <div class="d-flex gap-2 mt-2 mt-md-0">
                                <button type="button" class="btn btn-outline-success btn-sm btn-descargar-reporte" data-tipo="LIBRO_INGRESOS" data-formato="csv">
                                    <i class="fa-solid fa-file-csv me-1"></i> CSV
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm btn-descargar-reporte" data-tipo="LIBRO_INGRESOS" data-formato="xlsx">
                                    <i class="fa-solid fa-file-excel me-1"></i> Excel (XLSX)
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-descargar-reporte" data-tipo="LIBRO_INGRESOS" data-formato="pdf">
                                    <i class="fa-solid fa-file-pdf me-1"></i> PDF Oficial
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive app-scroll" style="max-height: 380px;">
                            <table class="table table-sm table-hover align-middle table-bordered mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Venta</th>
                                        <th>Cliente</th>
                                        <th>Doc</th>
                                        <th>Financiero</th>
                                        <th class="text-end">Total Venta</th>
                                        <th>Pago / Recibo</th>
                                        <th>Método</th>
                                        <th class="text-end">Monto Cobrado</th>
                                        <th class="text-end">Comisión</th>
                                        <th class="text-end">Neto</th>
                                    </tr>
                                </thead>
                                <tbody id="repLibroTbody">
                                    <tr><td colspan="10" class="text-center py-3 text-muted">Cargando datos...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 2. MANIFIESTO DE SALIDAS -->
                    <div class="tab-pane fade" id="panel-manifiesto-salidas" role="tabpanel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-3 bg-body-tertiary rounded">
                            <div class="d-flex flex-wrap gap-3">
                                <div><span class="f-s-11 text-muted d-block">TOTAL SALIDAS:</span><strong class="f-s-14 text-dark" id="repSalidasTotal">0</strong></div>
                                <div><span class="f-s-11 text-muted d-block">CAPACIDAD FLOTA:</span><strong class="f-s-14 text-info" id="repSalidasCapacidad">0</strong></div>
                                <div><span class="f-s-11 text-muted d-block">PASAJEROS ASIGNADOS:</span><strong class="f-s-14 text-primary" id="repSalidasPasajeros">0</strong></div>
                                <div><span class="f-s-11 text-muted d-block">TASA OCUPACIÓN:</span><strong class="f-s-14 text-success" id="repSalidasOcupacion">0%</strong></div>
                            </div>
                            <div class="d-flex gap-2 mt-2 mt-md-0">
                                <button type="button" class="btn btn-outline-success btn-sm btn-descargar-reporte" data-tipo="MANIFIESTO_SALIDAS" data-formato="csv">
                                    <i class="fa-solid fa-file-csv me-1"></i> CSV
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm btn-descargar-reporte" data-tipo="MANIFIESTO_SALIDAS" data-formato="xlsx">
                                    <i class="fa-solid fa-file-excel me-1"></i> Excel (XLSX)
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-descargar-reporte" data-tipo="MANIFIESTO_SALIDAS" data-formato="pdf">
                                    <i class="fa-solid fa-file-pdf me-1"></i> PDF Oficial
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive app-scroll" style="max-height: 380px;">
                            <table class="table table-sm table-hover align-middle table-bordered mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Código Salida</th>
                                        <th>Paquete / Servicio</th>
                                        <th>Fecha / Citación</th>
                                        <th>Vehículo / Placa</th>
                                        <th>Conductor</th>
                                        <th>Guía Oficial</th>
                                        <th class="text-center">Capacidad</th>
                                        <th class="text-center">Pasajeros</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="repSalidasTbody">
                                    <tr><td colspan="9" class="text-center py-3 text-muted">Cargando datos...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 3. PADRÓN DE ASISTENCIA -->
                    <div class="tab-pane fade" id="panel-padron-asistencia" role="tabpanel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-3 bg-body-tertiary rounded">
                            <div class="d-flex flex-wrap gap-3">
                                <div><span class="f-s-11 text-muted d-block">TOTAL PARTICIPANTES:</span><strong class="f-s-14 text-dark" id="repPadronTotal">0</strong></div>
                                <div><span class="f-s-11 text-muted d-block">CHECK-IN CONFIRMADO:</span><strong class="f-s-14 text-success" id="repPadronAsistieron">0</strong></div>
                                <div><span class="f-s-11 text-muted d-block">AUSENTES (NO-SHOW):</span><strong class="f-s-14 text-danger" id="repPadronAusentes">0</strong></div>
                                <div><span class="f-s-11 text-muted d-block">TASA ASISTENCIA:</span><strong class="f-s-14 text-primary" id="repPadronTasa">0%</strong></div>
                            </div>
                            <div class="d-flex gap-2 mt-2 mt-md-0">
                                <button type="button" class="btn btn-outline-success btn-sm btn-descargar-reporte" data-tipo="PADRON_ASISTENCIA" data-formato="csv">
                                    <i class="fa-solid fa-file-csv me-1"></i> CSV
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm btn-descargar-reporte" data-tipo="PADRON_ASISTENCIA" data-formato="xlsx">
                                    <i class="fa-solid fa-file-excel me-1"></i> Excel (XLSX)
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-descargar-reporte" data-tipo="PADRON_ASISTENCIA" data-formato="pdf">
                                    <i class="fa-solid fa-file-pdf me-1"></i> PDF Oficial
                                </button>
                            </div>
                        </div>
                        <div class="alert alert-info py-2 px-3 f-s-12 mb-3">
                            <i class="fa-solid fa-shield-halved me-1"></i> <strong>Cumplimiento Ley N° 29733:</strong> Los números de documento de identidad y códigos QR se muestran minimizados y enmascarados para protección estricta de la privacidad de los participantes.
                        </div>
                        <div class="table-responsive app-scroll" style="max-height: 380px;">
                            <table class="table table-sm table-hover align-middle table-bordered mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Reserva</th>
                                        <th>Titular</th>
                                        <th>Participante</th>
                                        <th>Doc. Minimizado</th>
                                        <th>Cód. Seguridad</th>
                                        <th>Estado Check-in</th>
                                        <th>Timestamp Check-in</th>
                                        <th>Salida Asignada</th>
                                    </tr>
                                </thead>
                                <tbody id="repPadronTbody">
                                    <tr><td colspan="8" class="text-center py-3 text-muted">Cargando datos...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>


