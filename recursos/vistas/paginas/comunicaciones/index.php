<!-- ==============================================================================
     VISTA OFICIAL: GESTIÓN DE COMUNICACIONES Y WHATSAPP (index.php) - FASE 2.8C
     Superficie Administrativa Alina UI para Mensajería Multicanal y Sandbox Meta
============================================================================== -->

<!-- Encabezado de la Sección -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-brands fa-whatsapp text-success me-2"></i> Comunicaciones y WhatsApp
        </h3>
        <p class="text-secondary mb-0">
            Bandeja unificada 360°, plantillas oficiales Meta, historial Outbox, consentimientos legales y simulador sandbox.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTodo" title="Recargar todos los datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <?php if (!empty($permisos['enviarIndividual'])): ?>
            <button type="button" class="btn bg-gradient-success btn-md text-white shadow-sm" id="btnAbrirModalEnviar">
                <i class="fa-solid fa-paper-plane me-2"></i> Enviar Mensaje
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen / KPIs de Comunicaciones -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Mensajes Hoy / Total</span>
                    <h3 class="f-w-700 text-primary mb-0 mt-1" id="kpiMensajesHoyTotal">0 / 0</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-comments"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Tasa de Entrega</span>
                    <h3 class="f-w-700 text-success mb-0 mt-1" id="kpiTasaEntrega">100%</h3>
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
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Tasa de Lectura</span>
                    <h3 class="f-w-700 text-info mb-0 mt-1" id="kpiTasaLectura">0%</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-info-subtle text-info rounded-circle f-s-20">
                    <i class="fa-solid fa-check-double"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Presupuesto USD (Gasto / Límite)</span>
                    <h3 class="f-w-700 text-dark mb-0 mt-1" id="kpiGastoPresupuesto">$0.00 / $50.00</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-dollar-sign"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Navegación por Pestañas Alina (Tabs) -->
<ul class="nav nav-tabs nav-tabs-bordered mb-4" id="comunicacionesTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-conversaciones-link" data-bs-toggle="tab" data-bs-target="#tab-conversaciones" type="button" role="tab">
            <i class="fa-solid fa-inbox me-2"></i> Bandeja de Conversaciones (Inbox 360°)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-plantillas-link" data-bs-toggle="tab" data-bs-target="#tab-plantillas" type="button" role="tab">
            <i class="fa-solid fa-file-code me-2"></i> Plantillas WhatsApp (Sandbox)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-mensajes-link" data-bs-toggle="tab" data-bs-target="#tab-mensajes" type="button" role="tab">
            <i class="fa-solid fa-paper-plane me-2"></i> Historial Outbox
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-consentimientos-link" data-bs-toggle="tab" data-bs-target="#tab-consentimientos" type="button" role="tab">
            <i class="fa-solid fa-shield-halved me-2"></i> Consentimientos (Opt-In / Opt-Out)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-campanas-link" data-bs-toggle="tab" data-bs-target="#tab-campanas" type="button" role="tab">
            <i class="fa-solid fa-bullhorn me-2"></i> Campañas Masivas (SoD)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-configuracion-link" data-bs-toggle="tab" data-bs-target="#tab-configuracion" type="button" role="tab">
            <i class="fa-solid fa-sliders me-2"></i> Configuración & Simulador Sandbox
        </button>
    </li>
</ul>

<!-- Contenido de las Pestañas -->
<div class="tab-content" id="comunicacionesTabsContent">

    <!-- =========================================================================
         TAB 1: BANDEJA DE CONVERSACIONES & CHAT 360°
    ========================================================================== -->
    <div class="tab-pane fade show active" id="tab-conversaciones" role="tabpanel">
        <div class="card border-0 shadow-sm b-r-12 overflow-hidden">
            <div class="card-body p-0">
                <div class="row g-0">
                    <!-- Columna Izquierda: Lista de Hilos -->
                    <div class="col-lg-4 col-md-5 border-end">
                        <div class="p-3 border-bottom bg-light">
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" class="form-control" id="buscarConversacion" placeholder="Buscar cliente o teléfono...">
                            </div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-xs btn-outline-primary active btn-filtro-conv" data-estado="">Todos</button>
                                <button type="button" class="btn btn-xs btn-outline-primary btn-filtro-conv" data-estado="ABIERTA">Abiertas</button>
                                <button type="button" class="btn btn-xs btn-outline-primary btn-filtro-conv" data-estado="EN_ATENCION">En Atención</button>
                                <button type="button" class="btn btn-xs btn-outline-primary btn-filtro-conv" data-estado="CERRADA">Cerradas</button>
                            </div>
                        </div>
                        <div class="overflow-auto" id="listaConversaciones" style="height: 580px;">
                            <div class="p-4 text-center text-muted" id="cargandoConversaciones">
                                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                                <div>Cargando conversaciones...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Visor de Chat Activo -->
                    <div class="col-lg-8 col-md-7 d-flex flex-column" style="height: 650px;">
                        <!-- Header del Hilo Activo -->
                        <div class="p-3 border-bottom bg-white d-flex align-items-center justify-content-between" id="headerChatActivo">
                            <div class="d-flex align-items-center gap-3">
                                <div class="w-40 h-40 d-flex-center bg-success text-white rounded-circle f-s-18">
                                    <i class="fa-brands fa-whatsapp"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 f-w-700 text-dark" id="chatClienteNombre">Seleccione una conversación</h5>
                                    <span class="f-s-12 text-muted" id="chatClienteTelefono">Haga clic en la lista para iniciar</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2" id="chatAcciones" style="display: none !important;">
                                <span class="badge bg-secondary" id="badgeVentana24h">Ventana Desconocida</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAsignarOperadorModal" title="Asignar Operador">
                                    <i class="fa-solid fa-user-plus"></i> Asignar
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="btnCerrarConversacion" title="Cerrar Hilo">
                                    <i class="fa-solid fa-lock"></i> Cerrar
                                </button>
                            </div>
                        </div>

                        <!-- Cuerpo de Mensajes (Burbujas) -->
                        <div class="p-3 overflow-auto flex-grow-1" id="cuerpoMensajesChat" style="background-color: #efeae2;">
                            <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted" id="chatVacioPlaceholder">
                                <i class="fa-regular fa-comments f-s-48 text-secondary mb-2 opacity-50"></i>
                                <p class="mb-0">Seleccione una conversación del panel lateral para visualizar el historial completo.</p>
                            </div>
                        </div>

                        <!-- Footer de Respuesta -->
                        <div class="p-3 border-top bg-white" id="footerChatRespuesta" style="display: none;">
                            <!-- Aviso Ventana 24h Expirada -->
                            <div class="alert alert-warning py-2 px-3 mb-2 f-s-12 d-none" id="alertaVentanaExpirada">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                <strong>Ventana de 24 horas cerrada.</strong> Según directivas de Meta, no puede responder texto libre. Debe enviar una <strong>Plantilla Oficial</strong> para reabrir el hilo.
                                <button type="button" class="btn btn-xs btn-primary ms-2" id="btnAbrirPlantillaDesdeChat">
                                    <i class="fa-solid fa-file-code me-1"></i> Seleccionar Plantilla
                                </button>
                            </div>

                            <!-- Formulario de Respuesta Libre -->
                            <form id="formResponderChat" class="d-flex gap-2">
                                <input type="text" class="form-control" id="inputTextoRespuesta" placeholder="Escriba un mensaje al cliente..." autocomplete="off">
                                <button type="submit" class="btn btn-success px-4" id="btnEnviarRespuestaChat">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Enviar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 2: CATÁLOGO DE PLANTILLAS OFICIALES META
    ========================================================================== -->
    <div class="tab-pane fade" id="tab-plantillas" role="tabpanel">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0 f-w-700 text-dark">Catálogo de Plantillas de WhatsApp (Simulador Sandbox)</h5>
                    <span class="f-s-12 text-muted">Plantillas preconfiguradas para pruebas locales y apertura de ventana de atención. En entorno de producción se sincronizan con Meta Cloud API.</span>
                </div>
                <?php if (!empty($permisos['gestionarPlantillas'])): ?>
                <button type="button" class="btn btn-sm btn-primary" id="btnAbrirModalNuevaPlantilla">
                    <i class="fa-solid fa-plus me-1"></i> Registrar Plantilla
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaPlantillas">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre Interno</th>
                                <th>Idioma</th>
                                <th>Categoría</th>
                                <th>Estado (Simulación)</th>
                                <th>Cuerpo del Mensaje</th>
                                <th>Versión</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoTablaPlantillas">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Cargando catálogo de plantillas...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 3: HISTORIAL OUTBOX / REGISTRO TRANSACCIONAL
    ========================================================================== -->
    <div class="tab-pane fade" id="tab-mensajes" role="tabpanel">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-header bg-white border-bottom p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" id="filtroMensajesBusqueda" placeholder="Buscar por teléfono o nombre...">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="filtroMensajesEstado">
                            <option value="">Todos los Estados</option>
                            <option value="CREADO">CREADO</option>
                            <option value="ENCOLADO">ENCOLADO</option>
                            <option value="EN_PROCESO">EN PROCESO</option>
                            <option value="ENVIADO">ENVIADO</option>
                            <option value="ENTREGADO">ENTREGADO</option>
                            <option value="LEIDO">LEÍDO</option>
                            <option value="FALLIDO">FALLIDO</option>
                            <option value="CANCELADO">CANCELADO</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="filtroMensajesTipo">
                            <option value="">Todos los Tipos</option>
                            <option value="TRANSACCIONAL">TRANSACCIONAL</option>
                            <option value="SESION_SERVICIO">SESIÓN SERVICIO</option>
                            <option value="CAMPANA_MARKETING">CAMPAÑA MARKETING</option>
                        </select>
                    </div>
                    <div class="col-md-5 text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnFiltrarMensajes">
                            <i class="fa-solid fa-filter me-1"></i> Filtrar
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-1" id="btnLimpiarFiltroMensajes">
                            <i class="fa-solid fa-eraser me-1"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaMensajes">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Fecha/Hora</th>
                                <th>Destinatario</th>
                                <th>Teléfono</th>
                                <th>Tipo</th>
                                <th>Estado Monotónico</th>
                                <th>Costo USD</th>
                                <th>Intentos</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoTablaMensajes">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">Cargando historial de mensajes Outbox...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 4: CONSENTIMIENTOS DE CANAL (OPT-IN / OPT-OUT)
    ========================================================================== -->
    <div class="tab-pane fade" id="tab-consentimientos" role="tabpanel">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0 f-w-700 text-dark">Padrón de Consentimientos (Opt-In / Opt-Out)</h5>
                    <span class="f-s-12 text-muted">Cumplimiento legal estricto Ley N° 29733 (Protección de Datos Personales Perú) y RGPD.</span>
                </div>
                <?php if (!empty($permisos['gestionarConsentimientos'])): ?>
                <button type="button" class="btn btn-sm btn-success text-white" id="btnAbrirModalConsentimiento">
                    <i class="fa-solid fa-user-shield me-1"></i> Registrar Consentimiento
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaConsentimientos">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente / Titular</th>
                                <th>Canal</th>
                                <th>Teléfono</th>
                                <th>Finalidad</th>
                                <th>Estado</th>
                                <th>Origen Evidencia</th>
                                <th>Cláusula Aceptada</th>
                                <th>Fecha Registro</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoTablaConsentimientos">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">Cargando padrón de consentimientos...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 5: CAMPAÑAS MASIVAS (SEGREGACIÓN DE DEBERES - SoD)
    ========================================================================== -->
    <div class="tab-pane fade" id="tab-campanas" role="tabpanel">
        <!-- Banner Informativo SoD -->
        <div class="alert alert-info border-0 shadow-sm mb-4 b-r-12">
            <div class="d-flex align-items-start gap-3">
                <i class="fa-solid fa-shield-halved f-s-24 text-info mt-1"></i>
                <div>
                    <h6 class="f-w-700 mb-1">Mecanismo de Segregación de Deberes (SoD) para Campañas Promocionales</h6>
                    <p class="mb-0 f-s-13">
                        Para proteger los presupuestos corporativos y la reputación del número de WhatsApp ante reportes de spam de Meta,
                        <strong>el usuario que crea una campaña promocional NO puede autorizar su ejecución ni liberar su presupuesto.</strong>
                        La aprobación debe ser ejecutada por un rol administrativo independiente con permiso <code>comunicaciones.aprobar_campanas</code>.
                    </p>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0 f-w-700 text-dark">Campañas de Comunicación</h5>
                    <span class="f-s-12 text-muted">Envíos masivos segmentados únicamente para clientes con opt-in de marketing explícito.</span>
                </div>
                <?php if (!empty($permisos['gestionarCampanas'])): ?>
                <button type="button" class="btn btn-sm btn-primary" id="btnAbrirModalNuevaCampana">
                    <i class="fa-solid fa-plus me-1"></i> Crear Campaña
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaCampanas">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre Campaña</th>
                                <th>Plantilla</th>
                                <th>Destinatarios</th>
                                <th>Presupuesto USD</th>
                                <th>Creador</th>
                                <th>Aprobador (SoD)</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoTablaCampanas">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">Cargando campañas...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 6: CONFIGURACIÓN DEL CANAL & SIMULADOR LOCAL SANDBOX
    ========================================================================== -->
    <div class="tab-pane fade" id="tab-configuracion" role="tabpanel">
        <div class="row g-4">
            <!-- Columna Izquierda: Parámetros del Proveedor -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm b-r-12">
                    <div class="card-header bg-white border-bottom p-3">
                        <h5 class="mb-0 f-w-700 text-dark">Configuración del Proveedor WhatsApp</h5>
                        <span class="f-s-12 text-muted">Conectividad Meta Cloud API, modo de operación y presupuesto mensual.</span>
                    </div>
                    <div class="card-body p-4">
                        <form id="formConfiguracionCanal">
                            <div class="mb-3">
                                <label class="form-label f-w-600">Proveedor de Mensajería</label>
                                <select class="form-select" id="cfgProveedorCodigo" name="proveedor_codigo">
                                    <option value="SIMULADOR_SANDBOX">Simulador WhatsApp Local (Sandbox Candelaria)</option>
                                    <option value="META_CLOUD_API">Meta WhatsApp Business Cloud API (Directo)</option>
                                    <option value="TWILIO_BSP">Twilio Messaging API (BSP Partner)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label f-w-600">Modo de Operación</label>
                                <select class="form-select" id="cfgModo" name="modo">
                                    <option value="SIMULADOR">SIMULADOR (Sandbox Seguro - Cero Envíos Reales)</option>
                                    <option value="PILOTO_INTERNO">PILOTO INTERNO (Solo Números Autorizados)</option>
                                    <option value="PRODUCCION">PRODUCCIÓN (Fail-Closed Estricto a Meta)</option>
                                </select>
                                <div class="form-text text-danger f-s-11">
                                    * En PRODUCCIÓN se aplican cobros reales por conversación Meta y fail-closed sin fallback ficticio.
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label f-w-600">Teléfono Emisor</label>
                                    <input type="text" class="form-control" id="cfgTelefono" name="telefono" value="+51999888777" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label f-w-600">Presupuesto Límite Mensual (USD)</label>
                                    <input type="number" step="0.01" class="form-control" id="cfgPresupuesto" name="presupuesto_mensual_limite_usd" value="50.00" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label f-w-600">Webhook Verify Token (Meta)</label>
                                <input type="text" class="form-control" id="cfgVerifyToken" name="webhook_verify_token" value="candelaria_webhook_token_2026" required>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label f-w-600">Meta Phone Number ID</label>
                                    <input type="text" class="form-control" id="cfgPhoneId" name="meta_phone_number_id" placeholder="Opcional en Simulador">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label f-w-600">Meta WABA ID</label>
                                    <input type="text" class="form-control" id="cfgWabaId" name="meta_waba_id" placeholder="Opcional en Simulador">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label f-w-600">Token de Acceso Permanente (Cifrado en Reposo)</label>
                                <input type="password" class="form-control" id="cfgTokenAcceso" name="token_acceso" placeholder="••••••••••••••••••••••••">
                            </div>

                            <div class="mb-4">
                                <label class="form-label f-w-600">Webhook Secret (HMAC SHA-256)</label>
                                <input type="password" class="form-control" id="cfgWebhookSecret" name="webhook_secret" placeholder="••••••••••••••••••••••••">
                            </div>

                            <?php if (!empty($permisos['configurarProveedor'])): ?>
                            <button type="submit" class="btn btn-primary w-100" id="btnGuardarConfiguracion">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Parámetros de Canal
                            </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Consola Interactiva del Simulador Sandbox -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm b-r-12 mb-4">
                    <div class="card-header bg-dark text-white p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-0 f-w-700 text-white"><i class="fa-solid fa-terminal me-2 text-success"></i> Simulador Interactivo Sandbox</h5>
                            <span class="f-s-12 text-light opacity-75">Pruebe el ciclo de vida completo de WhatsApp sin costo ni impacto externo.</span>
                        </div>
                        <span class="badge bg-success">SANDBOX ACTIVO</span>
                    </div>
                    <div class="card-body p-4">
                        <!-- Subsección 1: Simular Mensaje Entrante -->
                        <div class="p-3 bg-light b-r-8 mb-4 border">
                            <h6 class="f-w-700 text-dark mb-2">
                                <i class="fa-solid fa-arrow-down-long text-success me-1"></i> 1. Simular Mensaje Entrante del Cliente
                            </h6>
                            <p class="f-s-12 text-muted mb-3">
                                Simula que un cliente escribe por WhatsApp. Esto <strong>inicia o renueva la ventana de 24 horas</strong> gratuita de Meta y abre el hilo de conversación.
                            </p>
                            <form id="formSimularEntrante">
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control form-control-sm" id="simTelefono" placeholder="Teléfono (+51999111222)" value="+51999111222" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control form-control-sm" id="simNombre" placeholder="Nombre cliente" value="Cliente Simulado" required>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <input type="text" class="form-control form-control-sm" id="simTexto" placeholder="Texto del mensaje del cliente..." value="Hola CandelariaAPP, deseo consultar sobre mi itinerario folclórico." required>
                                </div>
                                <button type="submit" class="btn btn-sm btn-success" id="btnDispararMensajeEntrante">
                                    <i class="fa-solid fa-bolt me-1"></i> Inyectar Mensaje Entrante
                                </button>
                            </form>
                        </div>

                        <!-- Subsección 2: Despacho Inmediato del Outbox Worker -->
                        <div class="p-3 bg-light b-r-8 border">
                            <h6 class="f-w-700 text-dark mb-2">
                                <i class="fa-solid fa-gears text-primary me-1"></i> 2. Ejecutar Outbox Worker Inmediato
                            </h6>
                            <p class="f-s-12 text-muted mb-3">
                                Despacha los mensajes en cola (<code>ENCOLADO</code>), procesa reintentos exponenciales y actualiza estados monotónicos en tiempo real.
                            </p>
                            <button type="button" class="btn btn-sm btn-primary" id="btnEjecutarOutboxWorker">
                                <i class="fa-solid fa-play me-1"></i> Procesar Lote Outbox Ahora
                            </button>
                            <span class="f-s-12 text-muted ms-2" id="outboxWorkerResultado"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ==============================================================================
     MODALES DEL MÓDULO DE COMUNICACIONES
============================================================================== -->

<!-- Modal 1: Enviar Mensaje Transaccional Individual -->
<div class="modal fade" id="modalEnviarMensaje" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title f-w-700"><i class="fa-brands fa-whatsapp me-2"></i> Enviar Mensaje Transaccional</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEnviarMensajeTransaccional">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-w-600">Número de Teléfono (WhatsApp)</label>
                        <input type="text" class="form-control" name="telefono" id="envioTelefono" placeholder="+51999888777" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600">Nombre del Destinatario</label>
                        <input type="text" class="form-control" name="nombre" id="envioNombre" placeholder="Orlando Choque" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600">Plantilla Oficial Autorizada</label>
                        <select class="form-select" name="plantilla" id="envioPlantillaSelect" required>
                            <option value="">Seleccione una plantilla...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600">Evento / Motivo de Disparo</label>
                        <input type="text" class="form-control" name="evento" id="envioEvento" value="ENVIO_MANUAL_ALINA" required>
                    </div>
                    <div class="p-3 bg-light b-r-8 border mb-3">
                        <label class="form-label f-w-600 mb-1 f-s-12">Vista Previa de Parámetros Dinámicos</label>
                        <div id="vistaPreviaParametros" class="f-s-12 text-muted">
                            Seleccione una plantilla para ver sus variables mapeables.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnConfirmarEnvioMensaje">
                        <i class="fa-solid fa-paper-plane me-1"></i> Encolar en Outbox
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Registrar / Crear Plantilla Oficial -->
<div class="modal fade" id="modalNuevaPlantilla" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title f-w-700"><i class="fa-solid fa-file-code me-2"></i> Registrar Plantilla WhatsApp</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formNuevaPlantilla">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label f-w-600">Nombre Técnico (Meta)</label>
                            <input type="text" class="form-control" name="nombre" placeholder="ej. notificacion_reserva_v1" required>
                            <div class="form-text f-s-11">Solo minúsculas y guiones bajos (snake_case).</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-w-600">Idioma</label>
                            <select class="form-select" name="idioma">
                                <option value="es_PE">Español (es_PE)</option>
                                <option value="es_LA">Español (es_LA)</option>
                                <option value="en_US">Inglés (en_US)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-w-600">Categoría Meta</label>
                            <select class="form-select" name="categoria">
                                <option value="UTILITY">UTILITY (Operativa)</option>
                                <option value="MARKETING">MARKETING (Promocional)</option>
                                <option value="AUTHENTICATION">AUTHENTICATION (Seguridad)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-w-600">Cuerpo del Texto</label>
                        <textarea class="form-control" name="cuerpo_texto" rows="4" placeholder="Hola {{1}}, tu reserva #{{2}} para la Festividad de la Candelaria ha sido confirmada..." required></textarea>
                        <div class="form-text f-s-11">Utilice marcadores de posición <code>{{1}}</code>, <code>{{2}}</code> para variables dinámicas.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label f-w-600">Tipo de Encabezado</label>
                            <select class="form-select" name="encabezado_tipo">
                                <option value="NINGUNO">Ninguno</option>
                                <option value="TEXTO">Texto</option>
                                <option value="IMAGEN">Imagen</option>
                                <option value="DOCUMENTO">Documento PDF</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-w-600">Pie de Página (Opcional)</label>
                            <input type="text" class="form-control" name="pie_texto" placeholder="CandelariaAPP Oficial">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarPlantilla">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Plantilla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Detalle de Mensaje & Historial de Intentos -->
<div class="modal fade" id="modalDetalleMensaje" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title f-w-700"><i class="fa-solid fa-circle-info me-2"></i> Trazabilidad de Mensaje Outbox</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <span class="text-muted f-s-12 d-block">ID Mensaje</span>
                        <strong id="detMsgId">-</strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted f-s-12 d-block">Estado Monotónico</span>
                        <span id="detMsgEstado">-</span>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted f-s-12 d-block">WhatsApp Message ID (wamid)</span>
                        <code id="detMsgWamid" class="f-s-11 text-break">-</code>
                    </div>
                </div>
                <div class="mb-3">
                    <span class="text-muted f-s-12 d-block">Contenido del Mensaje</span>
                    <div class="p-3 bg-light b-r-8 border f-s-13" id="detMsgContenido">-</div>
                </div>
                <div class="mb-3">
                    <h6 class="f-w-700 mb-2">Intentos de Envío Realizados</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>HTTP Status</th>
                                    <th>Error Code</th>
                                    <th>Mensaje Error</th>
                                    <th>Latencia (ms)</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoTablaIntentos">
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Sin intentos registrados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 4: Registrar Consentimiento (Opt-In / Opt-Out) -->
<div class="modal fade" id="modalConsentimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title f-w-700"><i class="fa-solid fa-shield-halved me-2"></i> Registrar Evidencia de Consentimiento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formConsentimiento">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-w-600">ID de Cliente</label>
                        <input type="number" class="form-control" name="cliente_id" id="consClienteId" placeholder="ej. 1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600">Teléfono Destino (+51...)</label>
                        <input type="text" class="form-control" name="telefono" id="consTelefono" placeholder="+51999888777" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600">Finalidad</label>
                        <select class="form-select" name="finalidad" id="consFinalidad">
                            <option value="TRANSACCIONAL_OPERATIVO">TRANSACCIONAL OPERATIVO (Reservas, Vouchers, Itinerarios)</option>
                            <option value="MARKETING_PROMOCIONAL">MARKETING PROMOCIONAL (Ofertas y Campañas Masivas)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600">Estado del Consentimiento</label>
                        <select class="form-select" name="estado" id="consEstado">
                            <option value="CONCEDIDO">CONCEDIDO (Opt-In Positivo)</option>
                            <option value="REVOCADO">REVOCADO (Opt-Out Solicitado)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600">Origen de la Evidencia Legal</label>
                        <select class="form-select" name="origen" id="consOrigen">
                            <option value="WEB_FORMULARIO">Formulario Web / Checkout</option>
                            <option value="FIRMA_CONTRATO">Firma Contractual Física / Digital</option>
                            <option value="WHATSAPP_OPTOUT">Mensaje Entrante WhatsApp ('BAJA' / 'CANCELAR')</option>
                            <option value="ATENCION_PRESENCIAL">Atención Presencial Puno</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600">Cláusula Aceptada</label>
                        <textarea class="form-control" name="clausula" rows="2">Aceptación expresa de comunicaciones WhatsApp según directivas Ley 29733.</textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnGuardarConsentimiento">
                        <i class="fa-solid fa-check me-1"></i> Guardar Evidencia
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 5: Crear Campaña Masiva -->
<div class="modal fade" id="modalNuevaCampana" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title f-w-700"><i class="fa-solid fa-bullhorn me-2"></i> Crear Campaña Masiva</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formNuevaCampana">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-w-600">Nombre de la Campaña</label>
                        <input type="text" class="form-control" name="nombre" placeholder="ej. Promoción Preventa Candelaria 2027" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-w-600">Plantilla Oficial de Marketing</label>
                        <select class="form-select" name="plantilla_id" id="campanaPlantillaSelect" required>
                            <option value="">Seleccione plantilla autorizada...</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label f-w-600">Presupuesto Asignado (USD)</label>
                            <input type="number" step="0.01" class="form-control" name="presupuesto_usd" value="25.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-w-600">Programada Para (Opcional)</label>
                            <input type="datetime-local" class="form-control" name="programada_para">
                        </div>
                    </div>
                    <div class="alert alert-warning py-2 px-3 f-s-12 mb-0">
                        <i class="fa-solid fa-circle-exclamation me-1"></i>
                        La campaña se creará en estado <strong>BORRADOR</strong>. Por política SoD, otro usuario autorizado deberá aprobarla formalmente.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCampana">
                        <i class="fa-solid fa-plus me-1"></i> Crear en Borrador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 6: Asignar Operador a Conversación -->
<div class="modal fade" id="modalAsignarOperador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title f-w-700"><i class="fa-solid fa-user-gear me-2"></i> Asignar Operador</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAsignarOperador">
                <div class="modal-body p-3">
                    <input type="hidden" id="asigConversacionId">
                    <div class="mb-3">
                        <label class="form-label f-w-600">ID Usuario Operador</label>
                        <input type="number" class="form-control" id="asigOperadorId" value="1" required>
                        <div class="form-text f-s-11">Indique el ID del operador asignado para este hilo.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">Asignar</button>
                </div>
            </form>
        </div>
    </div>
</div>
