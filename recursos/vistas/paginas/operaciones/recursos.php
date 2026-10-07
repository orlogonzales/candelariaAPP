<!-- ==============================================================================
     VISTA OFICIAL: PADRÓN DE PROVEEDORES Y RECURSOS (operaciones/recursos.php) - FASE 2.6E
     Gestión de Recursos Físicos (Embarcaciones, Buses) y Proveedores Aliados con Alina UI
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-ship text-primary me-2"></i> Proveedores y Recursos Físicos
        </h3>
        <p class="text-secondary mb-0">
            Catálogo desacoplado de embarcaciones, unidades de transporte y aliados comerciales externos.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarDatos" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <?php if (!empty($permisos['recursosGestionar'])): ?>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalNuevoRecurso">
                <i class="fa-solid fa-plus me-1"></i> Nuevo Recurso Físico
            </button>
            <?php endif; ?>
            <?php if (!empty($permisos['proveedoresGestionar'])): ?>
            <button type="button" class="btn btn-outline-primary btn-md" id="btnAbrirModalNuevoProveedor">
                <i class="fa-solid fa-handshake me-1"></i> Nuevo Proveedor
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Pestañas Principales: Recursos Físicos vs Proveedores -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-body p-3">
        <ul class="nav nav-pills" id="pillsRecursosProveedores" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active f-w-600 px-4" id="pill-recursos" data-bs-toggle="pill" data-bs-target="#tab-recursos" type="button" role="tab">
                    <i class="fa-solid fa-ship me-2"></i> Recursos Físicos (Embarcaciones y Vehículos)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link f-w-600 px-4" id="pill-proveedores" data-bs-toggle="pill" data-bs-target="#tab-proveedores" type="button" role="tab">
                    <i class="fa-solid fa-handshake me-2"></i> Padrón de Proveedores Aliados
                </button>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content" id="contenidoPillsRecursos">
    <!-- 1. PESTAÑA RECURSOS FÍSICOS -->
    <div class="tab-pane fade show active" id="tab-recursos" role="tabpanel">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="tablaRecursosFisicos">
                        <thead class="table-light">
                            <tr>
                                <th class="f-w-600 text-uppercase f-s-12">Código</th>
                                <th class="f-w-600 text-uppercase f-s-12">Nombre del Recurso</th>
                                <th class="f-w-600 text-uppercase f-s-12">Tipo</th>
                                <th class="f-w-600 text-uppercase f-s-12">Propiedad</th>
                                <th class="f-w-600 text-uppercase f-s-12">Proveedor Asociado</th>
                                <th class="f-w-600 text-uppercase f-s-12">Capacidad</th>
                                <th class="f-w-600 text-uppercase f-s-12">Estado</th>
                                <th class="f-w-600 text-uppercase f-s-12 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. PESTAÑA PROVEEDORES ALIADOS -->
    <div class="tab-pane fade" id="tab-proveedores" role="tabpanel">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="tablaProveedores">
                        <thead class="table-light">
                            <tr>
                                <th class="f-w-600 text-uppercase f-s-12">#</th>
                                <th class="f-w-600 text-uppercase f-s-12">Razón Social / Nombre Aliado</th>
                                <th class="f-w-600 text-uppercase f-s-12">Documento</th>
                                <th class="f-w-600 text-uppercase f-s-12">Servicio Principal</th>
                                <th class="f-w-600 text-uppercase f-s-12">Contacto</th>
                                <th class="f-w-600 text-uppercase f-s-12">Recursos</th>
                                <th class="f-w-600 text-uppercase f-s-12">Estado</th>
                                <th class="f-w-600 text-uppercase f-s-12 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: NUEVO RECURSO FÍSICO (modalNuevoRecurso)
============================================================================== -->
<div class="modal fade" id="modalNuevoRecurso" tabindex="-1" aria-labelledby="tituloModalRecurso" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="tituloModalRecurso">
                    <i class="fa-solid fa-ship text-primary me-2"></i> Registrar Recurso Físico
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formNuevoRecurso">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Tipo de Recurso <span class="text-danger">*</span></label>
                            <select class="form-select" id="recTipoRecurso" required>
                                <option value="EMBARCACION">Embarcación / Lancha</option>
                                <option value="VEHICULO_TERRESTRE">Vehículo Terrestre (Bus/Van)</option>
                                <option value="EQUIPO_FOTOGRAFICO">Equipo Fotográfico</option>
                                <option value="EQUIPO_AUDIOVISUAL">Equipo Audiovisual</option>
                                <option value="OTRO">Otro Recurso</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Código Interno <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase font-monospace" id="recCodigoInterno" placeholder="LAN-01, BUS-02..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Nombre / Identificador <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="recNombre" placeholder="Lancha Titicaca Express..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Propiedad <span class="text-danger">*</span></label>
                            <select class="form-select" id="recPropiedadTipo" required>
                                <option value="PROPIO">Propio de la Empresa</option>
                                <option value="EXTERNO">Externo (Tercero Aliado)</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="contenedorSelectProveedor">
                            <label class="form-label f-w-600 f-s-13">Proveedor Asociado <span class="text-danger" id="reqProvAsterisco">*</span></label>
                            <select class="form-select" id="recProveedorId">
                                <option value="">Seleccione proveedor aliado</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-w-600 f-s-13">Capacidad Máxima Plazas <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="recCapacidadMax" value="25" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Matrícula / Placa Oficial</label>
                            <input type="text" class="form-control" id="recIdentOficial" placeholder="Ej. PE-PU-12345-BM / V8X-950">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-w-600 f-s-13">Notas Operativas</label>
                            <input type="text" class="form-control" id="recNotas" placeholder="Especificaciones mecánicas, comodidades...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarRecurso">
                        <i class="fa-solid fa-save me-1"></i> Guardar Recurso
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: NUEVO PROVEEDOR (modalNuevoProveedor)
============================================================================== -->
<div class="modal fade" id="modalNuevoProveedor" tabindex="-1" aria-labelledby="tituloModalProv" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="tituloModalProv">
                    <i class="fa-solid fa-handshake text-primary me-2"></i> Registrar Proveedor Aliado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formNuevoProveedor">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Persona Existente en Padrón <span class="text-danger">*</span></label>
                        <select class="form-select" id="provPersonaId" required style="width: 100%;">
                            <option value="">Buscar persona...</option>
                        </select>
                        <small class="text-muted f-s-11">Seleccione la persona jurídica o natural ya registrada en la base de datos.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Tipo de Servicio Principal</label>
                        <input type="text" class="form-control" id="provTipoServicio" placeholder="Ej. Transporte Lacustre, Transporte Terrestre...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600 f-s-13">Notas de Contacto y Acuerdos</label>
                        <textarea class="form-control" id="provNotas" rows="2" placeholder="Condiciones comerciales, acuerdos de muelle..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarProveedor">
                        <i class="fa-solid fa-save me-1"></i> Registrar Proveedor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
