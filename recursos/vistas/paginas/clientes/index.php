<!-- Encabezado de la Sección de Clientes -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-address-book text-primary me-2"></i> Padrón de Clientes
        </h3>
        <p class="text-secondary mb-0">
            Gestión comercial unificada, perfiles de clientes sobre identidad de personas y ciclo de vida de cartera.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalCrear">
                <i class="fa-solid fa-user-plus me-2"></i> Nuevo Cliente
            </button>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen / KPIs de Cartera Comercial -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total en Cartera</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalClientes">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Prospectos</span>
                    <h3 class="f-w-700 text-info mb-0 mt-1" id="kpiProspectos">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-info-subtle text-info rounded-circle f-s-20">
                    <i class="fa-solid fa-seedling"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Clientes Activos</span>
                    <h3 class="f-w-700 text-success mb-0 mt-1" id="kpiClientesActivos">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Inactivos / Bloqueados</span>
                    <h3 class="f-w-700 text-secondary mb-0 mt-1" id="kpiClientesInactivos">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros de Cartera -->
<div class="card border-0 shadow-sm b-r-12 mb-3">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <label for="filtroEstado" class="form-label f-s-12 text-muted mb-1">Estado Comercial</label>
                <select class="form-select form-select-sm" id="filtroEstado">
                    <option value="">Todos los Estados</option>
                    <option value="PROSPECTO">Prospectos</option>
                    <option value="ACTIVO">Activos</option>
                    <option value="INACTIVO">Inactivos</option>
                    <option value="BLOQUEADO">Bloqueados</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="filtroBusqueda" class="form-label f-s-12 text-muted mb-1">Buscar por Nombre, Razón Social, Documento o WhatsApp</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" class="form-control" id="filtroBusqueda" placeholder="Ej. Juan Pérez, 72345678, +51951...">
                </div>
            </div>
            <div class="col-md-3 text-md-end pt-md-3">
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnLimpiarFiltros">
                    <i class="fa-solid fa-filter-circle-xmark me-1"></i> Limpiar Filtros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor Principal de la Tabla con Soporte Skeleton -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-body p-3">
        <div id="contenedorTablaClientes" class="app-datatable-default overflow-auto app-scroll">
            <!-- Tabla inyectada dinámicamente por clientes.js -->
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: REGISTRAR NUEVO CLIENTE (VINCULAR PERSONA O ALTA INTEGRAL)
============================================================================== -->
<div class="modal fade" id="modalCrearCliente" tabindex="-1" aria-labelledby="modalCrearClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCrearClienteLabel">
                    <i class="fa-solid fa-user-plus me-2"></i> Alta de Cliente en Cartera Comercial
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCrearCliente" novalidate>
                <div class="modal-body p-4">
                    <!-- Selector de Modo de Incorporación -->
                    <div class="mb-4 p-3 bg-light b-r-10">
                        <label class="form-label f-w-600 f-s-13 d-block mb-2">Modo de Incorporación:</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="modo_cliente" id="modoExistente" value="existente" checked>
                            <label class="form-check-label f-s-13 f-w-600" for="modoExistente">
                                <i class="fa-solid fa-magnifying-glass me-1 text-primary"></i> Vincular Persona Existente
                            </label>
                        </div>
                        <div class="form-check form-check-inline ms-3">
                            <input class="form-check-input" type="radio" name="modo_cliente" id="modoNueva" value="nueva">
                            <label class="form-check-label f-s-13 f-w-600" for="modoNueva">
                                <i class="fa-solid fa-user-plus me-1 text-success"></i> Registrar Persona + Cliente Nuevo
                            </label>
                        </div>
                    </div>

                    <!-- Panel Modo A: Persona Existente -->
                    <div id="seccionPersonaExistente">
                        <div class="mb-3">
                            <label for="buscarPersonaId" class="form-label f-w-600 f-s-13">Buscar Persona en Organización <span class="text-danger">*</span></label>
                            <select class="form-select select2-persona-ajax" id="buscarPersonaId" name="persona_id" style="width: 100%;">
                                <option value="">Escriba nombre, documento o WhatsApp...</option>
                            </select>
                            <div class="form-text f-s-11">Permite asociar una persona ya registrada que no cuenta aún con perfil comercial.</div>
                        </div>

                        <div id="previewPersonaSeleccionada" class="p-3 border b-r-10 bg-white d-none mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="mb-1 f-w-700" id="previewNombre">-</h6>
                                    <div class="f-s-12 text-muted">
                                        <span id="previewDoc" class="me-3"></span>
                                        <span id="previewCorreo" class="me-3"></span>
                                        <span id="previewWhatsapp"></span>
                                    </div>
                                </div>
                                <span class="badge bg-success-subtle text-success">Persona Lista</span>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Modo B: Nueva Persona -->
                    <div id="seccionPersonaNueva" class="d-none">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="crearTipoPersona" class="form-label f-w-600 f-s-13">Tipo de Persona <span class="text-danger">*</span></label>
                                <select class="form-select" id="crearTipoPersona" name="tipo_persona">
                                    <option value="NATURAL" selected>Persona Natural</option>
                                    <option value="JURIDICA">Persona Jurídica (Empresa)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="crearTipoDoc" class="form-label f-w-600 f-s-13">Tipo de Documento</label>
                                <select class="form-select" id="crearTipoDoc" name="tipo_documento_id">
                                    <option value="">(Sin Documento Inicial)</option>
                                    <?php if (!empty($tiposDocumento)): ?>
                                        <?php foreach ($tiposDocumento as $td): ?>
                                            <option value="<?= (int) $td['id'] ?>"><?= escapar_html($td['codigo'] . ' - ' . $td['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="crearNumDoc" class="form-label f-w-600 f-s-13">Número de Documento</label>
                                <input type="text" class="form-control" id="crearNumDoc" name="numero_documento" placeholder="Ej. 72345678">
                            </div>

                            <!-- Campos Persona Natural -->
                            <div class="col-md-6 campo-natural">
                                <label for="crearNombres" class="form-label f-w-600 f-s-13">Nombres <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="crearNombres" name="nombres" placeholder="Ej. Juan Carlos">
                            </div>
                            <div class="col-md-6 campo-natural">
                                <label for="crearApellidos" class="form-label f-w-600 f-s-13">Apellidos <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="crearApellidos" name="apellidos" placeholder="Ej. Quispe Morales">
                            </div>

                            <!-- Campos Persona Jurídica -->
                            <div class="col-12 campo-juridica d-none">
                                <label for="crearRazonSocial" class="form-label f-w-600 f-s-13">Razón Social <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="crearRazonSocial" name="razon_social" placeholder="Ej. Producciones Altiplano S.A.C.">
                            </div>

                            <div class="col-md-6">
                                <label for="crearCorreo" class="form-label f-w-600 f-s-13">Correo Electrónico</label>
                                <input type="email" class="form-control" id="crearCorreo" name="correo_electronico" placeholder="contacto@cliente.pe">
                            </div>
                            <div class="col-md-6">
                                <label for="crearWhatsapp" class="form-label f-w-600 f-s-13">WhatsApp Principal</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-brands fa-whatsapp text-success"></i></span>
                                    <input type="text" class="form-control" id="crearWhatsapp" name="telefono_whatsapp" placeholder="Ej. +51951234567 o 951234567">
                                </div>
                                <div class="form-text f-s-11">Se normalizará automáticamente a estándar internacional E.164.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Datos Comerciales transversales -->
                    <hr class="my-3 text-muted">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="crearEstadoComercial" class="form-label f-w-600 f-s-13">Estado Comercial Inicial <span class="text-danger">*</span></label>
                            <select class="form-select" id="crearEstadoComercial" name="estado_comercial">
                                <option value="PROSPECTO" selected>PROSPECTO (En Evaluación / Pipeline)</option>
                                <option value="ACTIVO">ACTIVO (Con Operaciones Comerciales)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="crearNotasComerciales" class="form-label f-w-600 f-s-13">Notas Comerciales</label>
                            <input type="text" class="form-control" id="crearNotasComerciales" name="notas_comerciales" placeholder="Ej. Contactado en feria local...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCliente">
                        <i class="fa-solid fa-save me-1"></i> Guardar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: CAMBIAR ESTADO COMERCIAL
============================================================================== -->
<div class="modal fade" id="modalCambiarEstadoCliente" tabindex="-1" aria-labelledby="modalCambiarEstadoClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-warning text-dark b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCambiarEstadoClienteLabel">
                    <i class="fa-solid fa-arrows-spin me-2"></i> Cambiar Estado Comercial
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCambiarEstadoCliente" novalidate>
                <input type="hidden" id="estadoClienteId" name="id">
                <div class="modal-body p-4">
                    <p class="mb-3">
                        Cliente: <strong id="estadoClienteNombre">-</strong>
                    </p>
                    <div class="mb-3">
                        <label for="nuevoEstadoComercial" class="form-label f-w-600 f-s-13">Nuevo Estado <span class="text-danger">*</span></label>
                        <select class="form-select" id="nuevoEstadoComercial" name="estado_comercial" required>
                            <option value="PROSPECTO">PROSPECTO - En etapa de prospección</option>
                            <option value="ACTIVO">ACTIVO - Cartera activa y habilitada</option>
                            <option value="INACTIVO">INACTIVO - Desactivación comercial temporal</option>
                            <option value="BLOQUEADO">BLOQUEADO - Restringido por motivos comerciales</option>
                        </select>
                    </div>
                    <div class="alert alert-warning d-flex align-items-center gap-2 mb-0 f-s-12">
                        <i class="fa-solid fa-triangle-exclamation f-s-16 flex-shrink-0"></i>
                        <span>La desactivación o bloqueo es comercial y preserva toda la historia inmutable de transacciones e interacciones.</span>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark f-w-600" id="btnGuardarEstadoCliente">
                        <i class="fa-solid fa-check me-1"></i> Actualizar Estado
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
