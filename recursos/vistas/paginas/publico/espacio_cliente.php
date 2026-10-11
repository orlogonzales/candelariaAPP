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
                    <h1 class="h3 fw-bold text-dark mb-1">Mis Reservas y Boletos</h1>
                    <p class="text-muted small mb-0">Gestión de accesos oficiales para la Festividad Virgen de la Candelaria.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <button class="btn btn-outline-danger btn-sm fw-semibold" onclick="window.cerrarSesionCliente()">
                        <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Cerrar Sesión
                    </button>
                </div>
            </div>

            <!-- Listado de Reservas del Cliente -->
            <div class="row g-4" id="mis-reservas-lista">
                <!-- Se poblará dinámicamente con JS -->
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

<script>
window.tokenSesionCliente = localStorage.getItem('candelaria_cliente_token_' + window.CandelariaPortal.slug);

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
