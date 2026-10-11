<?php
/**
 * ==============================================================================
 * CANDELARIAAPP — ESPACIO DEL CLIENTE, RESERVAS Y BOLETOS QR (FASE 2.9C)
 * AUTENTICACIÓN PASSWORDLESS (OTP WHATSAPP / MAGIC LINK) SIN LEAK DE BACKOFFICE
 * ==============================================================================
 */

$org = $datos['organizacion'] ?? [];
$slug = htmlspecialchars((string) ($org['codigo'] ?? 'candelaria_central'), ENT_QUOTES, 'UTF-8');
$urlBase = url_base();
?>

<div class="py-5">
    <div class="container" style="max-width: 900px;">
        
        <!-- ESTADO 1: Formulario de Acceso Passwordless (Si no está autenticado) -->
        <div id="cliente-auth-box" class="card border rounded-4 p-4 p-md-5 shadow-lg bg-white mx-auto" style="max-width: 500px;">
            <div class="text-center mb-4">
                <div class="bg-primary-subtle text-primary p-3 rounded-circle d-inline-flex mb-3">
                    <i class="fa-solid fa-user-lock fa-2x"></i>
                </div>
                <h2 class="h4 fw-bold text-dark mb-1">Mi Espacio Candelaria</h2>
                <p class="text-muted small mb-0">Acceso seguro sin contraseña para consultar sus reservas y boletos digitales QR.</p>
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
                        <div class="form-text small">Ingrese el mismo número que utilizó al realizar su reserva.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mb-3" id="btn-solicitar-otp">
                        <i class="fa-solid fa-paper-plane me-1"></i> Enviar Código de Acceso
                    </button>
                </form>

                <div class="text-center small text-muted">
                    ¿Prefiere ingresar por correo? 
                    <a href="javascript:void(0)" onclick="window.cambiarMetodoEmail()" class="text-primary text-decoration-none fw-semibold">Usar Magic Link</a>
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
                        <span id="otp-timer-count" class="text-muted">Reenviar en 60s</span>
                    </div>
                </form>
            </div>
        </div>

        <!-- ESTADO 2: Panel del Cliente Autenticado -->
        <div id="cliente-portal-dashboard" style="display: none;">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">Mi Espacio Candelaria</h1>
                    <p class="text-muted small mb-0">Gestión de accesos oficiales, boletos QR, contratos y recuerdos multimedia.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <button class="btn btn-outline-danger btn-sm fw-semibold" onclick="window.cerrarSesionCliente()">
                        <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Cerrar Sesión
                    </button>
                </div>
            </div>

            <!-- Pestañas de Navegación del Cliente -->
            <ul class="nav nav-pills mb-4" id="portalClienteTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold px-4 py-2 rounded-pill" id="tab-reservas-btn" data-bs-toggle="pill" data-bs-target="#tab-cliente-reservas" type="button" role="tab">
                        <i class="fa-solid fa-ticket-simple me-2"></i> Mis Reservas y Boletos
                    </button>
                </li>
                <li class="nav-item ms-2" role="presentation">
                    <button class="nav-link fw-bold px-4 py-2 rounded-pill" id="tab-recuerdos-btn" data-bs-toggle="pill" data-bs-target="#tab-cliente-recuerdos" type="button" role="tab" onclick="window.cargarMisGalerias()">
                        <i class="fa-solid fa-photo-film me-2"></i> Mis Recuerdos y Galerías
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

                <!-- Pestaña 2: Galerías y Recuerdos Multimedia (F2.11) -->
                <div class="tab-pane fade" id="tab-cliente-recuerdos" role="tabpanel">
                    <div class="row g-4" id="mis-galerias-lista">
                        <!-- Se poblará dinámicamente con JS -->
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
                    <!-- Código QR Dinámico SVG / Canvas -->
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
                <button type="button" class="btn btn-dark btn-sm fw-bold" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Imprimir Boleto
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Visualización de Galería y Entregas Multimedia (F2.11) -->
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
                    <strong>Recuerdo Histórico Garantizado:</strong> Esta edición ha concluido su ciclo oficial, pero sus recuerdos fotográficos y audiovisuales permanecen permanentemente disponibles para consulta y descarga.
                </div>

                <!-- Entregas para Aprobación del Cliente -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-box-archive me-1 text-primary"></i> Entregas y Revisiones de Material</h6>
                    <div id="cliEntregasLista" class="row g-2">
                        <!-- Llenado dinámico -->
                    </div>
                </div>

                <!-- Álbumes y Archivos Multimedia -->
                <div>
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-images me-1 text-primary"></i> Álbumes y Archivos Multimedia</h6>
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
window.tokenSesionCliente = localStorage.getItem('candelaria_cliente_token_' + window.CandelariaPortal.slug);
let entregaParaObservarId = null;

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
        const res = await fetch('<?= $urlBase ?>api/v1/publico/portal/mis-reservas', {
            headers: {
                'Authorization': 'Bearer ' + window.tokenSesionCliente,
                'X-Tenant-Slug': window.CandelariaPortal.slug
            }
        });
        const data = await res.json();
        if (data.exito) {
            document.getElementById('cliente-auth-box').style.display = 'none';
            document.getElementById('cliente-portal-dashboard').style.display = 'block';
            window.renderizarReservasCliente(data.datos || []);
        } else {
            // Token inválido o revocado
            window.tokenSesionCliente = null;
            localStorage.removeItem('candelaria_cliente_token_' + window.CandelariaPortal.slug);
            document.getElementById('cliente-auth-box').style.display = 'block';
            document.getElementById('cliente-portal-dashboard').style.display = 'none';
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
        const esPagada = r.estado_financiero === 'PAGADA_TOTAL';
        const card = document.createElement('div');
        card.className = 'col-md-6';
        card.innerHTML = `
            <div class="card border rounded-4 p-4 shadow-sm h-100 d-flex flex-column">
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

                <div>
                    ${esConfirmada && esPagada ? `
                        <button class="btn btn-primary w-100 fw-bold btn-sm" onclick="window.verBoletoQr(${r.id})">
                            <i class="fa-solid fa-qrcode me-1"></i> Ver Boleto Digital QR
                        </button>
                    ` : `
                        <button class="btn btn-outline-secondary w-100 btn-sm" disabled>
                            <i class="fa-solid fa-lock me-1"></i> Boleto en espera de validación
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
            
            // Render QR Code SVG API
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(data.datos.token_qr)}`;
            document.getElementById('qr-img-tag').src = qrUrl;

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
// GESTIÓN DE GALERÍAS Y RECUERDOS MULTIMEDIA (FASE 2.11)
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
            <div class="col-12 text-center py-5 text-muted">
                <i class="fa-solid fa-photo-film fa-3x mb-3 text-secondary opacity-50"></i>
                <h5 class="fw-bold">Aún no cuenta con material multimedia disponible</h5>
                <p class="small">Cuando nuestro equipo audiovisual procese sus coberturas de danza o fotografía, aparecerán aquí para su descarga y aprobación.</p>
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
            <div class="card border rounded-4 p-4 shadow-sm h-100 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge ${esCerrada ? 'bg-dark' : 'bg-primary'} small">
                        <i class="fa-solid ${esCerrada ? 'fa-lock' : 'fa-calendar'} me-1"></i> ${g.edicion_nombre}
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary small">${g.total_archivos || 0} archivos</span>
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
                        <i class="fa-solid fa-images me-1"></i> Ver Álbumes y Descargar
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

        // Renderizar galerías y archivos
        const contGal = document.getElementById('cliGaleriasArchivosLista');
        contGal.innerHTML = '';
        const galerias = pry.galerias || [];
        if (galerias.length === 0) {
            contGal.innerHTML = '<div class="text-muted small p-2">No hay archivos disponibles en este momento.</div>';
        } else {
            galerias.forEach(g => {
                const box = document.createElement('div');
                box.className = 'card border rounded-3 mb-3 overflow-hidden';
                let itemsHtml = '';
                const archivos = g.archivos || [];
                if (archivos.length === 0) {
                    itemsHtml = '<div class="p-3 text-muted small">No hay archivos en esta sección.</div>';
                } else {
                    itemsHtml = '<div class="row g-2 p-3">';
                    archivos.forEach(a => {
                        const esVideo = (a.mime_type || '').startsWith('video/');
                        const pesoMb = (parseInt(a.peso_bytes || 0, 10) / (1024 * 1024)).toFixed(1);
                        itemsHtml += `
                            <div class="col-md-4 col-sm-6">
                                <div class="border rounded-3 p-2 bg-white d-flex align-items-center justify-content-between gap-2 shadow-sm">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                        <div class="p-2 rounded-2 ${esVideo ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary'}">
                                            <i class="fa-solid ${esVideo ? 'fa-video' : 'fa-image'}"></i>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="text-truncate small fw-bold" title="${a.nombre_original}">${a.nombre_original}</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">${pesoMb} MB</div>
                                        </div>
                                    </div>
                                    <a href="<?= $urlBase ?>api/v1/publico/portal/descargar-multimedia/${a.id}?token=${encodeURIComponent(window.tokenSesionCliente)}" class="btn btn-sm btn-primary rounded-circle" title="Descargar" target="_blank">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        `;
                    });
                    itemsHtml += '</div>';
                }

                box.innerHTML = `
                    <div class="card-header bg-light py-2 px-3 fw-bold small text-dark d-flex justify-content-between">
                        <span><i class="fa-solid fa-folder me-1 text-warning"></i> ${g.titulo}</span>
                        <span class="text-muted">${archivos.length} archivos</span>
                    </div>
                    ${itemsHtml}
                `;
                contGal.appendChild(box);
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('modalGaleriaClienteDetalle'));
        modal.show();
    } catch (e) {
        window.CandelariaPortal.mostrarNotificacion('Error', e.message, 'error');
    }
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
            // Cerrar modal actual y recargar galerías
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

document.addEventListener('DOMContentLoaded', () => {
    window.cargarPortalCliente();
});
</script>
