<?php
/**
 * ==============================================================================
 * CANDELARIAAPP — PORTAL DEL CLIENTE (FASE 3.4 / DT-V11-014)
 * AUTENTICACIÓN PASSWORDLESS, ESTADO DE CUENTA, PAGOS, FOTOGRAFÍAS Y PRIVACIDAD
 * ==============================================================================
 */

$org = $datos['organizacion'] ?? [];
$slug = htmlspecialchars((string) ($org['codigo'] ?? 'candelaria_central'), ENT_QUOTES, 'UTF-8');
$urlBase = url_base();
?>

<div class="py-5">
    <div class="container" style="max-width: 1050px;">
        
        <!-- ESTADO 1: Formulario de Acceso Passwordless (Si no está autenticado) -->
        <div id="cliente-auth-box" class="card border rounded-4 p-4 p-md-5 shadow-lg bg-white mx-auto" style="max-width: 500px;">
            <div class="text-center mb-4">
                <div class="bg-primary-subtle text-primary p-3 rounded-circle d-inline-flex mb-3">
                    <i class="fa-solid fa-user-lock fa-2x"></i>
                </div>
                <h2 class="h4 fw-bold text-dark mb-1">Mi Espacio Candelaria</h2>
                <p class="text-muted small mb-0">Autoservicio para boletos QR, estado de cuenta, recibos y selección de fotografías.</p>
            </div>

            <!-- Paso 1: Ingreso de Teléfono / WhatsApp -->
            <div id="auth-paso-solicitud">
                <form id="form-solicitar-otp" onsubmit="event.preventDefault(); window.solicitarOtp();">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Número de WhatsApp Registrado</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-brands fa-whatsapp text-success"></i></span>
                            <input type="tel" id="auth-telefono" class="form-control" placeholder="+51 987654321" required>
                        </div>
                        <div class="form-text small">Ingrese el mismo número que utilizó al realizar su reserva o compra.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mb-3" id="btn-solicitar-otp">
                        <i class="fa-solid fa-paper-plane me-1"></i> Enviar Código de Acceso
                    </button>
                </form>

                <div class="text-center small text-muted">
                    ¿Acceso seguro sin contraseña? Enviaremos un código OTP de 6 dígitos directamente a su WhatsApp.
                </div>
            </div>

            <!-- Paso 2: Verificación de Código OTP (6 dígitos) -->
            <div id="auth-paso-verificacion" style="display: none;">
                <div class="alert alert-info small border-0 mb-3">
                    <i class="fa-brands fa-whatsapp me-1 text-success"></i> Hemos enviado un código de 6 dígitos a su número de WhatsApp.
                </div>

                <form id="form-verificar-otp" onsubmit="event.preventDefault(); window.verificarOtp();">
                    <div class="mb-3 text-center">
                        <label class="form-label small fw-bold mb-2">Código de Verificación (6 dígitos)</label>
                        <input type="text" id="auth-codigo-otp" class="form-control text-center fw-bold fs-4 tracking-widest mx-auto" maxlength="6" placeholder="000000" style="max-width: 200px; letter-spacing: 6px;" required>
                    </div>

                    <button type="submit" class="btn btn-success w-100 fw-bold py-2 mb-3" id="btn-verificar-otp">
                        <i class="fa-solid fa-unlock me-1"></i> Ingresar a Mi Espacio
                    </button>

                    <div class="d-flex justify-content-between small">
                        <a href="javascript:void(0)" onclick="window.volverPasoTelefono()" class="text-muted text-decoration-none">
                            <i class="fa-solid fa-arrow-left me-1"></i> Cambiar número
                        </a>
                        <span id="otp-timer-count" class="text-muted">Código válido por 10 min</span>
                    </div>
                </form>
            </div>
        </div>

        <!-- ESTADO 2: Panel del Cliente Autenticado -->
        <div id="cliente-portal-dashboard" style="display: none;">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">Mi Espacio Candelaria</h1>
                    <p class="text-muted small mb-0">Gestión de accesos oficiales, boletos QR, estado de cuenta, selección fotográfica y privacidad.</p>
                </div>
                <div class="mt-3 mt-md-0 d-flex align-items-center gap-2">
                    <span id="header-cliente-nombre" class="badge bg-light text-dark border px-3 py-2 fw-semibold">
                        <i class="fa-solid fa-user me-1 text-primary"></i> Cliente
                    </span>
                    <button class="btn btn-outline-danger btn-sm fw-semibold" onclick="window.cerrarSesionCliente()">
                        <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Cerrar Sesión
                    </button>
                </div>
            </div>

            <!-- Pestañas de Navegación del Cliente -->
            <ul class="nav nav-pills mb-4 gap-2 flex-wrap" id="portalClienteTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold px-3 py-2 rounded-pill shadow-sm" id="tab-reservas-btn" data-bs-toggle="pill" data-bs-target="#tab-cliente-reservas" type="button" role="tab">
                        <i class="fa-solid fa-ticket-simple me-2"></i> Mis Reservas y Boletos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold px-3 py-2 rounded-pill shadow-sm" id="tab-cuenta-btn" data-bs-toggle="pill" data-bs-target="#tab-cliente-cuenta" type="button" role="tab" onclick="window.cargarEstadoCuenta()">
                        <i class="fa-solid fa-receipt me-2"></i> Estado de Cuenta y Pagos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold px-3 py-2 rounded-pill shadow-sm" id="tab-recuerdos-btn" data-bs-toggle="pill" data-bs-target="#tab-cliente-recuerdos" type="button" role="tab" onclick="window.cargarMisGalerias()">
                        <i class="fa-solid fa-photo-film me-2"></i> Fotografías y Recuerdos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold px-3 py-2 rounded-pill shadow-sm" id="tab-perfil-btn" data-bs-toggle="pill" data-bs-target="#tab-cliente-perfil" type="button" role="tab" onclick="window.cargarMiPerfil()">
                        <i class="fa-solid fa-user-shield me-2"></i> Mi Perfil y Privacidad
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="portalClienteTabsContent">
                
                <!-- Pestaña 1: Reservas y Boletos -->
                <div class="tab-pane fade show active" id="tab-cliente-reservas" role="tabpanel">
                    <div class="row g-4" id="mis-reservas-lista">
                        <!-- Se poblará dinámicamente con JS -->
                    </div>
                </div>

                <!-- Pestaña 2: Estado de Cuenta y Pagos (Fase 3.4C) -->
                <div class="tab-pane fade" id="tab-cliente-cuenta" role="tabpanel">
                    <!-- Resumen Financiero Soberano -->
                    <div class="row g-3 mb-4" id="cuenta-resumen-cards">
                        <div class="col-md-4">
                            <div class="card border rounded-4 p-3 shadow-sm bg-white">
                                <span class="text-muted small fw-bold">TOTAL CONTRATADO</span>
                                <h3 class="h4 fw-bold text-dark mt-1 mb-0" id="resumen-total-contratado">S/ 0.00</h3>
                                <small class="text-muted" id="resumen-total-ventas">0 contratos</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border rounded-4 p-3 shadow-sm bg-white">
                                <span class="text-muted small fw-bold">TOTAL PAGADO</span>
                                <h3 class="h4 fw-bold text-success mt-1 mb-0" id="resumen-total-pagado">S/ 0.00</h3>
                                <small class="text-success"><i class="fa-solid fa-circle-check me-1"></i> Acreditado en cuentas</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border rounded-4 p-3 shadow-sm bg-white">
                                <span class="text-muted small fw-bold">SALDO PENDIENTE</span>
                                <h3 class="h4 fw-bold text-danger mt-1 mb-0" id="resumen-saldo-pendiente">S/ 0.00</h3>
                                <small class="text-muted" id="resumen-estado-global">Al día</small>
                            </div>
                        </div>
                    </div>

                    <!-- Detalle de Ventas y Pagos -->
                    <div id="cuenta-ventas-lista">
                        <!-- Se poblará dinámicamente con JS -->
                    </div>
                </div>

                <!-- Pestaña 3: Galerías y Recuerdos Multimedia (Fase 3.4D) -->
                <div class="tab-pane fade" id="tab-cliente-recuerdos" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="h6 fw-bold text-dark mb-0"><i class="fa-solid fa-camera-retro me-2 text-primary"></i> Proyectos y Álbumes Fotográficos</h5>
                        <button class="btn btn-outline-primary btn-sm rounded-pill fw-semibold" onclick="window.abrirModalMisSelecciones()">
                            <i class="fa-solid fa-heart me-1 text-danger"></i> Ver Mis Fotos Seleccionadas
                        </button>
                    </div>
                    <div class="row g-4" id="mis-galerias-lista">
                        <!-- Se poblará dinámicamente con JS -->
                    </div>
                </div>

                <!-- Pestaña 4: Mi Perfil y Privacidad (Fase 3.4B) -->
                <div class="tab-pane fade" id="tab-cliente-perfil" role="tabpanel">
                    <div class="card border rounded-4 p-4 shadow-sm bg-white mb-4">
                        <h5 class="h6 fw-bold text-dark mb-3"><i class="fa-solid fa-id-card me-2 text-primary"></i> Datos de Identidad y Contacto</h5>
                        
                        <form id="form-actualizar-perfil" onsubmit="event.preventDefault(); window.guardarPerfilCliente();">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Nombres y Apellidos</label>
                                    <input type="text" id="perfil-nombre-completo" class="form-control bg-light" readonly>
                                    <div class="form-text small">La titularidad oficial no puede modificarse sin validación administrativa.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Documento de Identidad</label>
                                    <input type="text" id="perfil-documento" class="form-control bg-light" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Teléfono WhatsApp <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa-brands fa-whatsapp text-success"></i></span>
                                        <input type="tel" id="perfil-whatsapp" class="form-control" required>
                                    </div>
                                    <div class="form-text small">Canal principal para notificaciones de reservas y envío de códigos de acceso.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Correo Electrónico (Opcional)</label>
                                    <input type="email" id="perfil-email" class="form-control" placeholder="correo@ejemplo.com">
                                    <div class="form-text small">Para recepción de recibos de caja, boletos PDF y comunicaciones oficiales.</div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Consentimientos Ley 29733 -->
                            <h5 class="h6 fw-bold text-dark mb-3"><i class="fa-solid fa-shield-halved me-2 text-primary"></i> Privacidad y Consentimientos (Ley N° 29733)</h5>
                            
                            <div class="card bg-light border-0 p-3 rounded-3 mb-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="perfil-consentimiento-operativo">
                                    <label class="form-check-label fw-bold text-dark" for="perfil-consentimiento-operativo">
                                        Consentimiento Operativo y Transaccional
                                    </label>
                                </div>
                                <p class="small text-muted mb-1">
                                    Autorizo el tratamiento de mis datos personales de contacto para coordinaciones operativas, confirmación de pagos, emisión de boletos QR y avisos sobre mis servicios contratados.
                                </p>
                                <small class="text-muted" id="perfil-operativo-fecha">-</small>
                            </div>

                            <div class="card bg-light border-0 p-3 rounded-3 mb-4">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="perfil-consentimiento-promocional">
                                    <label class="form-check-label fw-bold text-dark" for="perfil-consentimiento-promocional">
                                        Consentimiento Promocional e Informativo
                                    </label>
                                </div>
                                <p class="small text-muted mb-1">
                                    Autorizo el envío de promociones, noticias de la festividad, lanzamientos de ediciones futuras y descuentos especiales mediante WhatsApp y correo electrónico.
                                </p>
                                <small class="text-muted" id="perfil-promocional-fecha">-</small>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary fw-bold px-4" id="btn-guardar-perfil">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Historial de Consentimientos (Trazabilidad Append-Only) -->
                    <div class="card border rounded-4 p-4 shadow-sm bg-white">
                        <h5 class="h6 fw-bold text-dark mb-3"><i class="fa-solid fa-clock-rotate-left me-2 text-secondary"></i> Historial de Consentimientos Registrados</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha / Hora</th>
                                        <th>Tipo</th>
                                        <th>Acción</th>
                                        <th>Canal</th>
                                        <th>Motivo</th>
                                    </tr>
                                </thead>
                                <tbody id="perfil-historial-consentimientos">
                                    <tr><td colspan="5" class="text-center text-muted py-3">Sin registros previos</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- Modal para Boleto Digital QR Criptográfico -->
<div class="modal fade" id="modalBoletoQr" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="ticket-hologram-strip"></div>
            <div class="modal-header bg-dark text-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-qrcode text-warning"></i>
                    <h5 class="modal-title fw-bold fs-6">Boleto Digital QR Oficial</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="badge bg-success-subtle text-success fw-bold mb-3 px-3 py-2">
                    <i class="fa-solid fa-circle-check me-1"></i> DERECHO DE ACCESO VIGENTE
                </div>

                <div class="ticket-qr-container mb-3">
                    <div id="qr-display-container">
                        <img id="qr-img-tag" src="" alt="Boleto QR" class="ticket-qr-canvas">
                    </div>
                    <div class="ticket-stamp-secure mt-2">
                        <i class="fa-solid fa-lock text-success"></i> FIRMA HMAC-SHA256 INVIOLABLE
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 text-start small mb-3">
                    <div class="row g-2">
                        <div class="col-6"><span class="text-muted">Reserva:</span> <strong id="ticket-modal-correlativo">-</strong></div>
                        <div class="col-6"><span class="text-muted">Titular:</span> <strong id="ticket-modal-titular">-</strong></div>
                        <div class="col-12"><span class="text-muted">Edición:</span> <strong id="ticket-modal-edicion">-</strong></div>
                    </div>
                </div>

                <div class="text-muted" style="font-size: 0.75rem;">
                    Presente este código QR en los puntos de control y graderías oficiales. No comparta capturas de pantalla para evitar duplicaciones.
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-2 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <div class="d-flex gap-2">
                    <a id="btn-descargar-boleto-pdf" href="#" target="_blank" class="btn btn-primary btn-sm fw-bold">
                        <i class="fa-solid fa-file-pdf me-1"></i> Descargar PDF
                    </a>
                    <button type="button" class="btn btn-dark btn-sm fw-bold" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Visualización de Galería y Selección Fotográfica (Fase 3.4D) -->
<div class="modal fade" id="modalGaleriaClienteDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-photo-film text-warning"></i>
                    <div>
                        <h5 class="modal-title fw-bold fs-6 mb-0" id="cliGaleriaTitulo">-</h5>
                        <small class="text-white-50" id="cliGaleriaSubtitulo">-</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Alerta de Edición Cerrada / Recuerdo Histórico Soberano -->
                <div id="cliAlertaHistorico" class="alert alert-success border-0 small d-none mb-3">
                    <i class="fa-solid fa-shield-halved me-1"></i>
                    <strong>Recuerdo Histórico Garantizado:</strong> Esta festividad ha concluido su ciclo oficial, pero sus recuerdos fotográficos y audiovisuales permanecen permanentemente disponibles para consulta y descarga.
                </div>

                <!-- Entregas para Aprobación del Cliente -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-box-archive me-1 text-primary"></i> Entregas y Revisiones de Material</h6>
                    <div id="cliEntregasLista" class="row g-2">
                        <!-- Llenado dinámico -->
                    </div>
                </div>

                <!-- Álbumes y Selección de Fotografías -->
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-images me-1 text-primary"></i> Álbumes y Fotografías del Proyecto</h6>
                        <span class="badge bg-primary-subtle text-primary fw-bold" id="galeria-contador-seleccionadas">
                            <i class="fa-solid fa-heart me-1 text-danger"></i> 0 seleccionadas
                        </span>
                    </div>
                    <div id="cliGaleriasArchivosLista">
                        <!-- Llenado dinámico -->
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Ver Mis Fotos Seleccionadas (Consolidado Global) -->
<div class="modal fade" id="modalMisSelecciones" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-heart text-danger"></i>
                    <h5 class="modal-title fw-bold fs-6 mb-0">Mis Fotografías Seleccionadas</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-3">Estas son las fotografías que ha marcado como favoritas en sus proyectos multimedia oficiales:</p>
                <div class="row g-3" id="lista-mis-selecciones-modal">
                    <!-- Dinámico -->
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Lightbox para Vista Previa de Fotografía -->
<div class="modal fade" id="modalLightboxFoto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 bg-dark text-white rounded-4 overflow-hidden">
            <div class="modal-header border-0 py-2">
                <span class="small fw-semibold" id="lightbox-titulo-foto">-</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 text-center bg-black position-relative" style="min-height: 350px;">
                <img id="lightbox-img" src="" alt="Vista previa" class="img-fluid" style="max-height: 70vh; object-fit: contain;">
            </div>
            <div class="modal-footer border-0 py-2 d-flex justify-content-between bg-dark">
                <span class="small text-white-50" id="lightbox-info-foto">-</span>
                <div id="lightbox-acciones" class="d-flex gap-2">
                    <!-- Botón de selección y descarga -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Observar Entrega -->
<div class="modal fade" id="modalObservarEntrega" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-warning text-dark border-0 py-3">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="fa-solid fa-comment-dots me-1"></i> Registrar Observaciones de Entrega
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-2">Indique a nuestro equipo los detalles o ajustes que requiere en las fotos o videos:</p>
                <textarea id="textoObservacionCliente" class="form-control" rows="4" placeholder="Ej: Solicito corregir el brillo en la toma grupal o recortar los primeros segundos del video reel..."></textarea>
            </div>
            <div class="modal-footer bg-light border-0 py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning btn-sm fw-bold" id="btnEnviarObservacion">
                    <i class="fa-solid fa-paper-plane me-1"></i> Enviar Observaciones
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('token')) {
    window.tokenSesionCliente = urlParams.get('token');
    try {
        localStorage.setItem('candelaria_cliente_token_' + (window.CandelariaPortal?.slug || 'candelaria_central'), window.tokenSesionCliente);
    } catch(e) {}
} else {
    window.tokenSesionCliente = localStorage.getItem('candelaria_cliente_token_' + (window.CandelariaPortal?.slug || 'candelaria_central'));
}
let entregaParaObservarId = null;
let galeriaActualActiva = null;

// ==============================================================================
// AUTENTICACIÓN PASSWORDLESS (OTP WHATSAPP)
// ==============================================================================
window.solicitarOtp = async function() {
    const tel = document.getElementById('auth-telefono').value.trim();
    const btn = document.getElementById('btn-solicitar-otp');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Enviando...';

    try {
        const res = await fetch('<?= $urlBase ?>api/v1/publico/portal/solicitar-acceso', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                org_slug: window.CandelariaPortal.slug,
                tipo_canal: 'WHATSAPP',
                identificador_destino: tel
            })
        });
        const data = await res.json();
        if (data.exito) {
            document.getElementById('auth-paso-solicitud').style.display = 'none';
            document.getElementById('auth-paso-verificacion').style.display = 'block';
            window.CandelariaPortal.mostrarNotificacion('Código Enviado', data.mensaje, 'success');
        } else {
            throw new Error(data.mensaje);
        }
    } catch (e) {
        window.CandelariaPortal.mostrarNotificacion('Error', e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Enviar Código de Acceso';
    }
};

window.verificarOtp = async function() {
    const tel = document.getElementById('auth-telefono').value.trim();
    const codigo = document.getElementById('auth-codigo-otp').value.trim();
    const btn = document.getElementById('btn-verificar-otp');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Verificando...';

    try {
        const res = await fetch('<?= $urlBase ?>api/v1/publico/portal/verificar-acceso', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                org_slug: window.CandelariaPortal.slug,
                tipo_canal: 'WHATSAPP',
                identificador_destino: tel,
                codigo_acceso: codigo
            })
        });
        const data = await res.json();
        if (data.exito && data.datos && data.datos.token_sesion) {
            window.tokenSesionCliente = data.datos.token_sesion;
            localStorage.setItem('candelaria_cliente_token_' + window.CandelariaPortal.slug, data.datos.token_sesion);
            window.CandelariaPortal.mostrarNotificacion('¡Bienvenido!', 'Sesión de cliente verificada con éxito.', 'success');
            window.cargarPortalCliente();
        } else {
            throw new Error(data.mensaje || 'Código inválido o expirado.');
        }
    } catch (e) {
        window.CandelariaPortal.mostrarNotificacion('Error de Verificación', e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-unlock me-1"></i> Ingresar a Mi Espacio';
    }
};

window.cargarPortalCliente = async function() {
    if (!window.tokenSesionCliente) {
        document.getElementById('cliente-auth-box').style.display = 'block';
        document.getElementById('cliente-portal-dashboard').style.display = 'none';
        return;
    }

    try {
        const res = await fetch('<?= $urlBase ?>api/v1/publico/portal/perfil', {
            headers: {
                'Authorization': 'Bearer ' + window.tokenSesionCliente,
                'X-Tenant-Slug': window.CandelariaPortal.slug
            }
        });
        const data = await res.json();
        if (data.exito) {
            document.getElementById('cliente-auth-box').style.display = 'none';
            document.getElementById('cliente-portal-dashboard').style.display = 'block';
            
            const persona = data.datos.persona || {};
            document.getElementById('header-cliente-nombre').innerHTML = `
                <i class="fa-solid fa-user-check me-1 text-success"></i> ${persona.nombre_completo || 'Cliente'}
            `;

            // Cargar reservas iniciales
            window.cargarMisReservas();
        } else {
            window.tokenSesionCliente = null;
            localStorage.removeItem('candelaria_cliente_token_' + window.CandelariaPortal.slug);
            document.getElementById('cliente-auth-box').style.display = 'block';
            document.getElementById('cliente-portal-dashboard').style.display = 'none';
        }
    } catch (e) {
        console.error(e);
    }
};

// ==============================================================================
// GESTIÓN DE RESERVAS Y BOLETOS QR
// ==============================================================================
window.cargarMisReservas = async function() {
    try {
        const res = await fetch('<?= $urlBase ?>api/v1/publico/portal/mis-reservas', {
            headers: {
                'Authorization': 'Bearer ' + window.tokenSesionCliente,
                'X-Tenant-Slug': window.CandelariaPortal.slug
            }
        });
        const data = await res.json();
        if (data.exito) {
            window.renderizarReservasCliente(data.datos || []);
        }
    } catch (e) {
        console.error(e);
    }
};

window.renderizarReservasCliente = function(reservas) {
    const cont = document.getElementById('mis-reservas-lista');
    if (reservas.length === 0) {
        cont.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="fa-solid fa-ticket-simple fa-3x text-muted mb-3"></i>
                <h4 class="h5 fw-bold text-dark">No cuenta con reservas registradas</h4>
                <p class="text-muted small mb-3">Aún no tiene compras formalizadas en esta organización.</p>
                <a href="<?= $urlBase ?>p/<?= $slug ?>/catalogo" class="btn btn-primary btn-sm fw-bold">Ver Catálogo</a>
            </div>
        `;
        return;
    }

    cont.innerHTML = '';
    reservas.forEach(r => {
        const esConfirmada = r.estado === 'CONFIRMADA';
        const esPagada = r.estado_financiero === 'PAGADA_TOTAL' || r.estado_financiero === 'SOBREPAGADA';
        const card = document.createElement('div');
        card.className = 'col-md-6';
        card.innerHTML = `
            <div class="card border rounded-4 p-4 shadow-sm h-100 d-flex flex-column bg-white">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge ${esConfirmada ? 'bg-success' : 'bg-warning text-dark'} small">
                        ${esConfirmada ? '<i class="fa-solid fa-circle-check me-1"></i> CONFIRMADA' : '<i class="fa-solid fa-clock me-1"></i> EN VERIFICACIÓN'}
                    </span>
                    <span class="fw-bold text-primary">${r.correlativo}</span>
                </div>
                <h4 class="h6 fw-bold text-dark mb-1">${r.edicion_nombre}</h4>
                <div class="text-muted small mb-3">Registrado el: ${r.creado_en}</div>

                <div class="p-3 bg-light rounded-3 mb-3 small mt-auto">
                    <div class="d-flex justify-content-between">
                        <span>Total Venta:</span>
                        <strong class="text-dark">S/ ${parseFloat(r.total).toFixed(2)}</strong>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span>Estado Financiero:</span>
                        <span class="badge ${esPagada ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'}">${r.estado_financiero}</span>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    ${esConfirmada && esPagada ? `
                        <button class="btn btn-primary w-100 fw-bold btn-sm" onclick="window.verBoletoQr(${r.id})">
                            <i class="fa-solid fa-qrcode me-1"></i> Ver Boleto QR
                        </button>
                    ` : `
                        <button class="btn btn-outline-secondary w-100 btn-sm" disabled>
                            <i class="fa-solid fa-lock me-1"></i> Boleto en espera de pago
                        </button>
                    `}
                </div>
            </div>
        `;
        cont.appendChild(card);
    });
};

window.verBoletoQr = async function(reservaId) {
    try {
        const res = await fetch(`<?= $urlBase ?>api/v1/publico/portal/reserva/${reservaId}/boleto-qr`, {
            headers: { 'Authorization': 'Bearer ' + window.tokenSesionCliente }
        });
        const data = await res.json();
        if (data.exito) {
            document.getElementById('ticket-modal-correlativo').textContent = data.datos.correlativo;
            document.getElementById('ticket-modal-titular').textContent = data.datos.datos_boleto.titular;
            document.getElementById('ticket-modal-edicion').textContent = data.datos.datos_boleto.edicion;
            
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(data.datos.token_qr)}`;
            document.getElementById('qr-img-tag').src = qrUrl;

            // Enlace de descarga PDF
            document.getElementById('btn-descargar-boleto-pdf').href = `<?= $urlBase ?>api/v1/publico/portal/reserva/${reservaId}/boleto-pdf?token=${encodeURIComponent(window.tokenSesionCliente)}`;

            const modal = new bootstrap.Modal(document.getElementById('modalBoletoQr'));
            modal.show();
        } else {
            window.CandelariaPortal.mostrarNotificacion('Boleto No Disponible', data.mensaje, 'warning');
        }
    } catch (e) {
        window.CandelariaPortal.mostrarNotificacion('Error', 'No se pudo cargar el boleto digital.', 'error');
    }
};

// ==============================================================================
// ESTADO DE CUENTA, SALDOS Y PAGOS (FASE 3.4C)
// ==============================================================================
window.cargarEstadoCuenta = async function() {
    const cont = document.getElementById('cuenta-ventas-lista');
    cont.innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i></div>';

    try {
        const res = await fetch('<?= $urlBase ?>api/v1/publico/portal/estado-cuenta', {
            headers: {
                'Authorization': 'Bearer ' + window.tokenSesionCliente,
                'X-Tenant-Slug': window.CandelariaPortal.slug
            }
        });
        const data = await res.json();
        if (!data.exito) throw new Error(data.mensaje);

        const resumen = data.datos.resumen || {};
        const ventas = data.datos.ventas || [];

        document.getElementById('resumen-total-contratado').textContent = `S/ ${parseFloat(resumen.total_contratado || 0).toFixed(2)}`;
        document.getElementById('resumen-total-pagado').textContent = `S/ ${parseFloat(resumen.total_pagado || 0).toFixed(2)}`;
        document.getElementById('resumen-saldo-pendiente').textContent = `S/ ${parseFloat(resumen.saldo_pendiente_total || 0).toFixed(2)}`;
        document.getElementById('resumen-total-ventas').textContent = `${resumen.total_ventas || 0} contratos registrados`;

        const badgeEstado = resumen.estado_financiero === 'PAGADA_TOTAL'
            ? '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> Todo pagado</span>'
            : (resumen.estado_financiero === 'PAGADA_PARCIAL'
                ? '<span class="text-warning"><i class="fa-solid fa-hourglass-half me-1"></i> Abonos parciales</span>'
                : '<span class="text-danger"><i class="fa-solid fa-clock me-1"></i> Pendiente de abono</span>');
        document.getElementById('resumen-estado-global').innerHTML = badgeEstado;

        if (ventas.length === 0) {
            cont.innerHTML = `
                <div class="card border rounded-4 p-5 text-center bg-white shadow-sm">
                    <i class="fa-solid fa-receipt fa-3x text-muted mb-3"></i>
                    <h5 class="fw-bold">No registra movimientos financieros</h5>
                    <p class="text-muted small">No cuenta con contratos comerciales ni abonos en esta organización.</p>
                </div>
            `;
            return;
        }

        cont.innerHTML = '';
        ventas.forEach(v => {
            const card = document.createElement('div');
            card.className = 'card border rounded-4 p-4 shadow-sm bg-white mb-4';

            let lineasHtml = '';
            (v.lineas || []).forEach(l => {
                lineasHtml += `
                    <tr>
                        <td>
                            <strong class="text-dark">${l.concepto_nombre}</strong>
                            <div class="text-muted small">${l.concepto_descripcion || ''}</div>
                        </td>
                        <td class="text-center">${parseFloat(l.cantidad).toFixed(0)}</td>
                        <td class="text-end">S/ ${parseFloat(l.precio_unitario).toFixed(2)}</td>
                        <td class="text-end fw-bold">S/ ${parseFloat(l.subtotal).toFixed(2)}</td>
                    </tr>
                `;
            });

            let pagosHtml = '';
            (v.pagos || []).forEach(p => {
                const esAprobado = p.estado === 'APROBADO';
                const tieneRecibo = p.recibo_id > 0;
                pagosHtml += `
                    <div class="border rounded-3 p-3 bg-light mb-2 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div>
                            <span class="badge ${esAprobado ? 'bg-success' : 'bg-warning text-dark'} me-2">${p.estado}</span>
                            <strong class="text-primary">${p.correlativo}</strong>
                            <span class="text-muted small ms-2">${p.fecha_pago} &bull; ${p.metodo_pago}</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="fw-bold text-dark fs-6">S/ ${parseFloat(p.monto_cobrado_cliente).toFixed(2)}</span>
                            ${esAprobado ? `
                                <a href="<?= $urlBase ?>api/v1/publico/portal/recibo/${p.pago_id}?token=${encodeURIComponent(window.tokenSesionCliente)}" target="_blank" class="btn btn-outline-success btn-sm fw-bold">
                                    <i class="fa-solid fa-file-invoice me-1"></i> Recibo de Caja (PDF)
                                </a>
                            ` : `
                                <span class="badge bg-secondary-subtle text-secondary small">En verificación</span>
                            `}
                        </div>
                    </div>
                `;
            });

            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-3 border-bottom pb-3">
                    <div>
                        <span class="badge bg-primary me-2">${v.edicion_nombre}</span>
                        <strong class="text-dark fs-5">${v.correlativo}</strong>
                        <div class="text-muted small">Fecha: ${v.fecha_venta}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge ${v.estado_financiero === 'PAGADA_TOTAL' ? 'bg-success' : 'bg-warning text-dark'} mb-1">
                            ${v.estado_financiero}
                        </span>
                        ${v.tiene_contrato ? `
                            <div>
                                <a href="<?= $urlBase ?>api/v1/publico/portal/venta/${v.id}/contrato-pdf?token=${encodeURIComponent(window.tokenSesionCliente)}" target="_blank" class="btn btn-outline-primary btn-sm fw-semibold mt-1">
                                    <i class="fa-solid fa-file-contract me-1"></i> Contrato Oficial (PDF)
                                </a>
                            </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Detalle de Conceptos -->
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Concepto Contratado</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">P. Unitario</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${lineasHtml}
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Total Contratado:</td>
                                <td class="text-end fw-bold text-dark">S/ ${parseFloat(v.total).toFixed(2)}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end text-success fw-bold">Total Pagado:</td>
                                <td class="text-end text-success fw-bold">S/ ${parseFloat(v.monto_pagado).toFixed(2)}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end text-danger fw-bold">Saldo Pendiente:</td>
                                <td class="text-end text-danger fw-bold">S/ ${parseFloat(v.saldo_pendiente).toFixed(2)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Historial de Abonos / Pagos -->
                <h6 class="fw-bold text-dark small mb-2"><i class="fa-solid fa-money-bill-transfer me-1 text-primary"></i> Abonos y Recibos Registrados</h6>
                <div>
                    ${pagosHtml !== '' ? pagosHtml : '<div class="text-muted small">No se registran abonos directos para esta venta.</div>'}
                </div>
            `;
            cont.appendChild(card);
        });
    } catch (e) {
        cont.innerHTML = `
            <div class="alert alert-danger rounded-4 p-4 text-center">
                <i class="fa-solid fa-triangle-exclamation fa-2x mb-2"></i>
                <h6 class="fw-bold">Error al cargar estado de cuenta</h6>
                <p class="small mb-0">${e.message}</p>
            </div>
        `;
    }
};

// ==============================================================================
// GESTIÓN DE GALERÍAS Y SELECCIÓN FOTOGRÁFICA (FASE 3.4D)
// ==============================================================================
window.cargarMisGalerias = async function() {
    const cont = document.getElementById('mis-galerias-lista');
    cont.innerHTML = '<div class="col-12 text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i></div>';

    try {
        const res = await fetch('<?= $urlBase ?>api/v1/publico/portal/mis-galerias', {
            headers: {
                'Authorization': 'Bearer ' + window.tokenSesionCliente,
                'X-Tenant-Slug': window.CandelariaPortal.slug
            }
        });
        const data = await res.json();
        if (data.exito) {
            window.renderizarGaleriasCliente(data.datos || []);
        } else {
            throw new Error(data.mensaje || 'Error al cargar galerías');
        }
    } catch (e) {
        cont.innerHTML = `
            <div class="col-12 text-center py-5 text-muted">
                <i class="fa-solid fa-photo-film fa-3x mb-3 text-secondary opacity-50"></i>
                <h5 class="fw-bold">No se pudieron cargar sus recuerdos</h5>
                <p class="small text-danger">${e.message}</p>
            </div>
        `;
    }
};

window.renderizarGaleriasCliente = function(galerias) {
    const cont = document.getElementById('mis-galerias-lista');
    if (galerias.length === 0) {
        cont.innerHTML = `
            <div class="col-12 text-center py-5 text-muted bg-white rounded-4 border p-4">
                <i class="fa-solid fa-photo-film fa-3x mb-3 text-secondary opacity-50"></i>
                <h5 class="fw-bold">Aún no cuenta con proyectos audiovisuales disponibles</h5>
                <p class="small">Cuando nuestro equipo procese sus coberturas fotográficas y de danza, aparecerán aquí para su selección y descarga.</p>
            </div>
        `;
        return;
    }

    cont.innerHTML = '';
    galerias.forEach(g => {
        const esCerrada = g.edicion_estado === 'CERRADA';
        const card = document.createElement('div');
        card.className = 'col-md-6';
        card.innerHTML = `
            <div class="card border rounded-4 p-4 shadow-sm h-100 d-flex flex-column bg-white">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge ${esCerrada ? 'bg-dark' : 'bg-primary'} small">
                        <i class="fa-solid ${esCerrada ? 'fa-lock' : 'fa-calendar'} me-1"></i> ${g.edicion_nombre}
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary small">${g.total_archivos || 0} fotos / videos</span>
                </div>
                <h4 class="h6 fw-bold text-dark mb-1">${g.titulo}</h4>
                <p class="text-muted small mb-3">${g.descripcion || 'Cobertura fotográfica y audiovisual oficial.'}</p>

                ${esCerrada ? `
                    <div class="alert alert-success p-2 small border-0 mb-3">
                        <i class="fa-solid fa-shield-halved me-1"></i> Recuerdo Histórico Garantizado
                    </div>
                ` : ''}

                <div class="mt-auto">
                    <button class="btn btn-outline-primary w-100 fw-bold btn-sm" onclick="window.verGaleriaDetalle(${g.id})">
                        <i class="fa-solid fa-images me-1"></i> Ver Álbumes y Seleccionar Fotos
                    </button>
                </div>
            </div>
        `;
        cont.appendChild(card);
    });
};

window.verGaleriaDetalle = async function(proyectoId) {
    try {
        const res = await fetch(`<?= $urlBase ?>api/v1/publico/portal/mis-galerias/${proyectoId}`, {
            headers: {
                'Authorization': 'Bearer ' + window.tokenSesionCliente,
                'X-Tenant-Slug': window.CandelariaPortal.slug
            }
        });
        const data = await res.json();
        if (!data.exito) throw new Error(data.mensaje);

        const pry = data.datos;
        document.getElementById('cliGaleriaTitulo').textContent = pry.titulo;
        document.getElementById('cliGaleriaSubtitulo').textContent = `${pry.edicion_nombre} (${pry.edicion_anio})`;

        // Alerta histórico
        const alertaHist = document.getElementById('cliAlertaHistorico');
        if (pry.edicion_estado === 'CERRADA') {
            alertaHist.classList.remove('d-none');
        } else {
            alertaHist.classList.add('d-none');
        }

        // Renderizar entregas
        const contEnt = document.getElementById('cliEntregasLista');
        contEnt.innerHTML = '';
        const entregas = pry.entregas || [];
        if (entregas.length === 0) {
            contEnt.innerHTML = '<div class="col-12 text-muted small p-2">No hay entregas formales registradas aún.</div>';
        } else {
            entregas.forEach(e => {
                const esAprobada = e.estado_entrega === 'APROBADA_CLIENTE';
                const esObs = e.estado_entrega === 'OBSERVADA_CLIENTE';
                const el = document.createElement('div');
                el.className = 'col-12';
                el.innerHTML = `
                    <div class="border rounded-3 p-3 bg-light d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div>
                            <div class="fw-bold text-dark">
                                <span class="badge bg-primary me-1">Entrega #${e.numero_entrega}</span>
                                ${e.titulo}
                            </div>
                            <div class="small text-muted">${e.notas || ''}</div>
                            ${esObs ? `<div class="small text-warning mt-1"><i class="fa-solid fa-comment me-1"></i> Su observación: ${e.observaciones_cliente}</div>` : ''}
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            ${esAprobada ? `
                                <span class="badge bg-success-subtle text-success p-2"><i class="fa-solid fa-circle-check me-1"></i> Aprobado</span>
                            ` : `
                                <button class="btn btn-sm btn-success fw-bold" onclick="window.responderEntrega(${e.id}, true)">
                                    <i class="fa-solid fa-thumbs-up me-1"></i> Aprobar
                                </button>
                                <button class="btn btn-sm btn-outline-warning fw-bold" onclick="window.abrirModalObservar(${e.id})">
                                    <i class="fa-solid fa-comment-dots me-1"></i> Observar
                                </button>
                            `}
                        </div>
                    </div>
                `;
                contEnt.appendChild(el);
            });
        }

        // Cargar fotos de cada galería mediante endpoint oficial con selecciones
        const contGal = document.getElementById('cliGaleriasArchivosLista');
        contGal.innerHTML = '';
        const galerias = pry.galerias || [];

        if (galerias.length === 0) {
            contGal.innerHTML = '<div class="text-muted small p-2">No hay álbumes disponibles en este momento.</div>';
        } else {
            let totalSeleccionadasGbl = 0;
            for (const g of galerias) {
                const resFotos = await fetch(`<?= $urlBase ?>api/v1/publico/portal/galerias/${g.id}/fotos`, {
                    headers: { 'Authorization': 'Bearer ' + window.tokenSesionCliente }
                });
                const fotosData = await resFotos.json();
                const fotos = (fotosData.exito && fotosData.datos) ? fotosData.datos.fotos : [];
                const selCount = (fotosData.exito && fotosData.datos) ? fotosData.datos.total_seleccionadas : 0;
                totalSeleccionadasGbl += selCount;

                const box = document.createElement('div');
                box.className = 'card border rounded-3 mb-4 overflow-hidden';
                let itemsHtml = '';

                if (fotos.length === 0) {
                    itemsHtml = '<div class="p-3 text-muted small">No hay fotos en esta sección.</div>';
                } else {
                    itemsHtml = '<div class="row g-3 p-3">';
                    fotos.forEach(f => {
                        const esVideo = (f.mime_type || '').startsWith('video/');
                        const previewUrl = `<?= $urlBase ?>api/v1/publico/portal/descargar-multimedia/${f.id}?preview=1&token=${encodeURIComponent(window.tokenSesionCliente)}`;
                        const isSelected = f.es_seleccionada;

                        itemsHtml += `
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <div class="card h-100 border shadow-sm position-relative overflow-hidden">
                                    <div class="position-relative bg-light text-center" style="height: 180px; overflow: hidden; cursor: pointer;" onclick="window.abrirLightboxFoto(${f.id}, '${encodeURIComponent(f.nombre_original)}', '${previewUrl}', ${isSelected}, ${g.id})">
                                        ${esVideo ? `
                                            <div class="h-100 d-flex flex-column align-items-center justify-content-center text-danger">
                                                <i class="fa-solid fa-play-circle fa-3x mb-2"></i>
                                                <span class="badge bg-danger small">Video</span>
                                            </div>
                                        ` : `
                                            <img src="${previewUrl}" class="w-100 h-100 object-fit-cover" alt="${f.nombre_original}" loading="lazy">
                                        `}
                                    </div>
                                    <div class="p-2 d-flex justify-content-between align-items-center bg-white border-top">
                                        <div class="text-truncate small fw-semibold me-2" title="${f.nombre_original}">${f.nombre_original}</div>
                                        <button class="btn btn-sm ${isSelected ? 'btn-danger' : 'btn-outline-secondary'} rounded-circle p-2" title="${isSelected ? 'Deseleccionar' : 'Seleccionar fotografía'}" onclick="window.toggleSeleccionFoto(${g.id}, ${f.id}, ${!isSelected}, this)">
                                            <i class="fa-solid fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    itemsHtml += '</div>';
                }

                box.innerHTML = `
                    <div class="card-header bg-light py-2 px-3 fw-bold small text-dark d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-folder-open me-2 text-warning"></i> ${g.titulo}</span>
                        <span class="badge bg-secondary-subtle text-secondary">${fotos.length} fotos</span>
                    </div>
                    ${itemsHtml}
                `;
                contGal.appendChild(box);
            }

            document.getElementById('galeria-contador-seleccionadas').innerHTML = `
                <i class="fa-solid fa-heart me-1 text-danger"></i> ${totalSeleccionadasGbl} seleccionadas
            `;
        }

        const modal = new bootstrap.Modal(document.getElementById('modalGaleriaClienteDetalle'));
        modal.show();
    } catch (e) {
        window.CandelariaPortal.mostrarNotificacion('Error', e.message, 'error');
    }
};

window.toggleSeleccionFoto = async function(galeriaId, archivoId, seleccionada, btn) {
    try {
        if (btn) btn.disabled = true;
        const res = await fetch(`<?= $urlBase ?>api/v1/publico/portal/galerias/${galeriaId}/seleccionar`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + window.tokenSesionCliente,
                'X-Tenant-Slug': window.CandelariaPortal.slug
            },
            body: JSON.stringify({ archivo_id: archivoId, seleccionada })
        });
        const data = await res.json();
        if (data.exito) {
            if (btn) {
                if (seleccionada) {
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-danger');
                    btn.setAttribute('onclick', `window.toggleSeleccionFoto(${galeriaId}, ${archivoId}, false, this)`);
                } else {
                    btn.classList.remove('btn-danger');
                    btn.classList.add('btn-outline-secondary');
                    btn.setAttribute('onclick', `window.toggleSeleccionFoto(${galeriaId}, ${archivoId}, true, this)`);
                }
            }
            window.CandelariaPortal.mostrarNotificacion('Selección Actualizada', data.mensaje, 'success');
        } else {
            throw new Error(data.mensaje);
        }
    } catch (e) {
        window.CandelariaPortal.mostrarNotificacion('Error', e.message, 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
};

window.abrirLightboxFoto = function(archivoId, nombreOriginal, previewUrl, isSelected, galeriaId) {
    document.getElementById('lightbox-titulo-foto').textContent = decodeURIComponent(nombreOriginal);
    document.getElementById('lightbox-img').src = previewUrl;
    document.getElementById('lightbox-info-foto').textContent = 'Vista previa protegida.';

    const contAcciones = document.getElementById('lightbox-acciones');
    contAcciones.innerHTML = `
        <button class="btn btn-sm ${isSelected ? 'btn-danger' : 'btn-outline-light'} fw-bold" onclick="window.toggleSeleccionFoto(${galeriaId}, ${archivoId}, ${!isSelected}, this)">
            <i class="fa-solid fa-heart me-1"></i> ${isSelected ? 'Seleccionada' : 'Marcar como Favorita'}
        </button>
        <a href="<?= $urlBase ?>api/v1/publico/portal/descargar-multimedia/${archivoId}?token=${encodeURIComponent(window.tokenSesionCliente)}" class="btn btn-sm btn-primary fw-bold" target="_blank">
            <i class="fa-solid fa-download me-1"></i> Descargar Original
        </a>
    `;

    const modal = new bootstrap.Modal(document.getElementById('modalLightboxFoto'));
    modal.show();
};

window.abrirModalMisSelecciones = async function() {
    const cont = document.getElementById('lista-mis-selecciones-modal');
    cont.innerHTML = '<div class="col-12 text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i></div>';

    const modal = new bootstrap.Modal(document.getElementById('modalMisSelecciones'));
    modal.show();

    try {
        const res = await fetch('<?= $urlBase ?>api/v1/publico/portal/mis-selecciones', {
            headers: {
                'Authorization': 'Bearer ' + window.tokenSesionCliente,
                'X-Tenant-Slug': window.CandelariaPortal.slug
            }
        });
        const data = await res.json();
        if (!data.exito) throw new Error(data.mensaje);

        const selecciones = data.datos || [];
        if (selecciones.length === 0) {
            cont.innerHTML = `
                <div class="col-12 text-center py-4 text-muted">
                    <i class="fa-solid fa-heart-crack fa-3x text-muted mb-2"></i>
                    <p class="small mb-0">Aún no ha marcado fotografías como seleccionadas.</p>
                </div>
            `;
            return;
        }

        cont.innerHTML = '';
        selecciones.forEach(s => {
            const previewUrl = `<?= $urlBase ?>api/v1/publico/portal/descargar-multimedia/${s.archivo_id}?preview=1&token=${encodeURIComponent(window.tokenSesionCliente)}`;
            const item = document.createElement('div');
            item.className = 'col-md-4 col-sm-6';
            item.innerHTML = `
                <div class="card h-100 border shadow-sm overflow-hidden">
                    <div style="height: 140px; overflow: hidden; background: #000;">
                        <img src="${previewUrl}" class="w-100 h-100 object-fit-cover" alt="${s.nombre_original}" loading="lazy">
                    </div>
                    <div class="p-2">
                        <div class="text-truncate small fw-bold" title="${s.nombre_original}">${s.nombre_original}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">${s.galeria_titulo} &bull; ${s.proyecto_titulo}</div>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <span class="badge bg-danger-subtle text-danger" style="font-size: 0.7rem;"><i class="fa-solid fa-heart me-1"></i> Favorita</span>
                            <button class="btn btn-outline-danger btn-sm p-1 rounded-circle" title="Quitar de favoritos" onclick="window.quitarSeleccion(${s.galeria_id}, ${s.archivo_id}, this)">
                                <i class="fa-solid fa-trash fa-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            cont.appendChild(item);
        });
    } catch (e) {
        cont.innerHTML = `<div class="col-12 text-danger small text-center">${e.message}</div>`;
    }
};

window.quitarSeleccion = async function(galeriaId, archivoId, btn) {
    if (!confirm('¿Desea desmarcar esta fotografía de sus seleccionadas?')) return;
    await window.toggleSeleccionFoto(galeriaId, archivoId, false, null);
    window.abrirModalMisSelecciones();
};

window.abrirModalObservar = function(entregaId) {
    entregaParaObservarId = entregaId;
    document.getElementById('textoObservacionCliente').value = '';
    const modal = new bootstrap.Modal(document.getElementById('modalObservarEntrega'));
    modal.show();
};

document.getElementById('btnEnviarObservacion').addEventListener('click', async function() {
    if (!entregaParaObservarId) return;
    const obs = document.getElementById('textoObservacionCliente').value.trim();
    if (!obs) {
        alert('Por favor ingrese el motivo o detalle de su observación.');
        return;
    }
    const modalEl = document.getElementById('modalObservarEntrega');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    await window.responderEntrega(entregaParaObservarId, false, obs);
});

window.responderEntrega = async function(entregaId, aprobada, observaciones = null) {
    try {
        const res = await fetch(`<?= $urlBase ?>api/v1/publico/portal/entregas/${entregaId}/responder`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + window.tokenSesionCliente,
                'X-Tenant-Slug': window.CandelariaPortal.slug
            },
            body: JSON.stringify({ aprobada, observaciones })
        });
        const data = await res.json();
        if (data.exito) {
            window.CandelariaPortal.mostrarNotificacion('Operación Exitosa', data.mensaje, 'success');
            const modalDet = bootstrap.Modal.getInstance(document.getElementById('modalGaleriaClienteDetalle'));
            if (modalDet) modalDet.hide();
            window.cargarMisGalerias();
        } else {
            throw new Error(data.mensaje);
        }
    } catch (e) {
        window.CandelariaPortal.mostrarNotificacion('Error', e.message, 'error');
    }
};

// ==============================================================================
// GESTIÓN DE PERFIL Y PRIVACIDAD LEY 29733 (FASE 3.4B)
// ==============================================================================
window.cargarMiPerfil = async function() {
    try {
        const res = await fetch('<?= $urlBase ?>api/v1/publico/portal/perfil', {
            headers: {
                'Authorization': 'Bearer ' + window.tokenSesionCliente,
                'X-Tenant-Slug': window.CandelariaPortal.slug
            }
        });
        const data = await res.json();
        if (!data.exito) throw new Error(data.mensaje);

        const p = data.datos.persona || {};
        const c = data.datos.cliente || {};
        const hist = data.datos.historial_consentimientos || [];

        document.getElementById('perfil-nombre-completo').value = p.nombre_completo || '';
        document.getElementById('perfil-documento').value = `${p.tipo_documento || 'DNI'}: ${p.numero_documento || '-'}`;
        document.getElementById('perfil-whatsapp').value = p.telefono_whatsapp || '';
        document.getElementById('perfil-email').value = p.correo_electronico || '';

        // Switches de Consentimientos
        document.getElementById('perfil-consentimiento-operativo').checked = !!c.consentimiento_operativo;
        document.getElementById('perfil-operativo-fecha').textContent = c.consentimiento_operativo_en 
            ? `Última actualización: ${c.consentimiento_operativo_en}` 
            : 'Pendiente de confirmación';

        document.getElementById('perfil-consentimiento-promocional').checked = !!c.consentimiento_promocional;
        document.getElementById('perfil-promocional-fecha').textContent = c.consentimiento_promocional_en 
            ? `Última actualización: ${c.consentimiento_promocional_en}` 
            : 'Pendiente de confirmación';

        // Historial
        const tbody = document.getElementById('perfil-historial-consentimientos');
        if (hist.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Sin registros previos de cambios en consentimientos.</td></tr>';
        } else {
            tbody.innerHTML = '';
            hist.forEach(h => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${h.creado_en}</td>
                    <td><span class="badge bg-secondary">${h.tipo}</span></td>
                    <td>
                        <span class="badge ${h.accion === 'OTORGAR' ? 'bg-success' : 'bg-danger'}">${h.accion}</span>
                    </td>
                    <td>${h.canal}</td>
                    <td class="text-muted small">${h.motivo || '-'}</td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) {
        window.CandelariaPortal.mostrarNotificacion('Error', e.message, 'error');
    }
};

window.guardarPerfilCliente = async function() {
    const btn = document.getElementById('btn-guardar-perfil');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Guardando...';

    try {
        const payload = {
            telefono_whatsapp: document.getElementById('perfil-whatsapp').value.trim(),
            correo_electronico: document.getElementById('perfil-email').value.trim(),
            consentimiento_operativo: document.getElementById('perfil-consentimiento-operativo').checked,
            consentimiento_promocional: document.getElementById('perfil-consentimiento-promocional').checked
        };

        const res = await fetch('<?= $urlBase ?>api/v1/publico/portal/perfil', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + window.tokenSesionCliente,
                'X-Tenant-Slug': window.CandelariaPortal.slug
            },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.exito) {
            window.CandelariaPortal.mostrarNotificacion('Perfil Actualizado', data.mensaje, 'success');
            window.cargarMiPerfil();
        } else {
            throw new Error(data.mensaje);
        }
    } catch (e) {
        window.CandelariaPortal.mostrarNotificacion('Error al Actualizar', e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios';
    }
};

window.cerrarSesionCliente = async function() {
    if (window.tokenSesionCliente) {
        try {
            await fetch('<?= $urlBase ?>api/v1/publico/portal/cerrar-sesion', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + window.tokenSesionCliente }
            });
        } catch (e) {}
    }
    window.tokenSesionCliente = null;
    localStorage.removeItem('candelaria_cliente_token_' + window.CandelariaPortal.slug);
    window.location.reload();
};

window.volverPasoTelefono = function() {
    document.getElementById('auth-paso-solicitud').style.display = 'block';
    document.getElementById('auth-paso-verificacion').style.display = 'none';
};

document.addEventListener('DOMContentLoaded', async () => {
    await window.cargarPortalCliente();
    const t = (new URLSearchParams(window.location.search)).get('tab');
    if (t === 'cuenta') setTimeout(() => document.getElementById('tab-cuenta-btn')?.click(), 300);
    else if (t === 'recuerdos') setTimeout(() => document.getElementById('tab-recuerdos-btn')?.click(), 300);
    else if (t === 'perfil') setTimeout(() => document.getElementById('tab-perfil-btn')?.click(), 300);
});
</script>
