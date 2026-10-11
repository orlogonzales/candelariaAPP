<?php
/**
 * ==============================================================================
 * CANDELARIAAPP — PROCESO DE CHECKOUT DE 3 PASOS CON TEMPORIZADOR (FASE 2.9C)
 * ==============================================================================
 */

$org = $datos['organizacion'] ?? [];
$edicion = $datos['edicion'] ?? [];
$slug = htmlspecialchars((string) ($org['codigo'] ?? 'candelaria_central'), ENT_QUOTES, 'UTF-8');
$urlBase = url_base();
?>

<div class="py-5">
    <div class="container" style="max-width: 960px;">
        <!-- Banner del Temporizador de Retención -->
        <div class="timer-banner">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left fa-lg text-warning"></i>
                <div>
                    <div class="fw-bold small">Tiempo de Retención de Cupos</div>
                    <div class="text-white-50" style="font-size: 0.75rem;">Complete sus datos antes de que expire la reserva temporal.</div>
                </div>
            </div>
            <div class="timer-clock" id="checkout-timer">15:00</div>
        </div>

        <!-- Indicador Visual de Pasos (3 Pasos) -->
        <div class="checkout-steps">
            <div class="checkout-step-item active" id="step-node-1">
                <div class="checkout-step-bubble">1</div>
                <div class="checkout-step-label">Resumen y Cupos</div>
            </div>
            <div class="checkout-step-item" id="step-node-2">
                <div class="checkout-step-bubble">2</div>
                <div class="checkout-step-label">Datos del Titular</div>
            </div>
            <div class="checkout-step-item" id="step-node-3">
                <div class="checkout-step-bubble">3</div>
                <div class="checkout-step-label">Pago Seguro</div>
            </div>
        </div>

        <!-- PASO 1: Resumen de Carrito y Validación de Cupos -->
        <div id="checkout-step-1" class="checkout-step-content">
            <div class="card border rounded-4 p-4 shadow-sm mb-4">
                <h2 class="h5 fw-bold text-dark mb-3"><i class="fa-solid fa-basket-shopping me-2 text-primary"></i> Resumen de su Selección</h2>
                
                <div id="cart-empty-message" class="text-center py-4" style="display: none;">
                    <i class="fa-solid fa-cart-shopping fa-2x text-muted mb-2"></i>
                    <p class="text-muted small mb-3">Su carrito está vacío. Agregue paquetes o entradas para continuar.</p>
                    <a href="<?= $urlBase ?>p/<?= $slug ?>/catalogo" class="btn btn-outline-primary btn-sm fw-semibold">
                        Ver Catálogo Oficial
                    </a>
                </div>

                <div id="cart-table-container">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light small">
                                <tr>
                                    <th>Concepto</th>
                                    <th class="text-center" style="width: 120px;">Cantidad</th>
                                    <th class="text-end" style="width: 140px;">Precio Unit.</th>
                                    <th class="text-end" style="width: 140px;">Subtotal</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cart-table-body">
                                <!-- Filas dinámicas por JS -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Desglose Soberano del Backend -->
                    <div class="row justify-content-end mt-3">
                        <div class="col-md-5">
                            <div class="p-3 bg-light rounded-3">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Subtotal Base:</span>
                                    <span id="backend-subtotal">S/ 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between small text-muted mb-2">
                                    <span>IGV (18% Ley Perú):</span>
                                    <span id="backend-igv">S/ 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between fw-bold text-dark border-top pt-2 fs-5">
                                    <span>Total a Pagar:</span>
                                    <span id="backend-total" class="text-primary">S/ 0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="<?= $urlBase ?>p/<?= $slug ?>/catalogo" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Seguir Comprando
                </a>
                <button type="button" class="btn btn-primary fw-bold px-4" id="btn-to-step-2" onclick="window.avanzarPaso(2)">
                    Continuar con los Datos <i class="fa-solid fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>

        <!-- PASO 2: Datos del Titular y Consentimientos Ley 29733 -->
        <div id="checkout-step-2" class="checkout-step-content" style="display: none;">
            <div class="card border rounded-4 p-4 shadow-sm mb-4">
                <h2 class="h5 fw-bold text-dark mb-3"><i class="fa-solid fa-user-check me-2 text-primary"></i> Datos del Titular de la Reserva</h2>
                <p class="text-muted small mb-4">La persona registrada será el contacto principal para la emisión de los boletos digitales.</p>

                <form id="form-cliente-titular">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tipo de Documento</label>
                            <select id="cli-tipo-doc" class="form-select" required>
                                <option value="1">DNI (Perú)</option>
                                <option value="2">Carné de Extranjería</option>
                                <option value="3">Pasaporte</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Número de Documento</label>
                            <input type="text" id="cli-num-doc" class="form-control" placeholder="Ej. 45892314" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nombres</label>
                            <input type="text" id="cli-nombres" class="form-control" placeholder="Ej. Carlos Juan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Apellidos</label>
                            <input type="text" id="cli-apellidos" class="form-control" placeholder="Ej. Quispe Morales" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Correo Electrónico</label>
                            <input type="email" id="cli-correo" class="form-control" placeholder="nombre@correo.com" required>
                            <div class="form-text small">Aquí se enviará la confirmación y el comprobante.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">WhatsApp / Celular</label>
                            <input type="tel" id="cli-telefono" class="form-control" placeholder="+51 987654321" required>
                            <div class="form-text small">Para soporte operativo y alertas del desfile.</div>
                        </div>
                    </div>

                    <!-- Consentimientos Ley 29733 -->
                    <div class="p-3 bg-light rounded-3 border mb-2">
                        <div class="fw-bold small text-dark mb-2"><i class="fa-solid fa-shield-halved text-success me-1"></i> Consentimientos y Protección de Datos</div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="check-terminos" required>
                            <label class="form-check-label small" for="check-terminos">
                                He leído y acepto expresamente los <a href="#" class="text-primary text-decoration-none">Términos y Condiciones</a> del servicio y política de aforo.
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="check-privacidad" required>
                            <label class="form-check-label small" for="check-privacidad">
                                Acepto la <a href="#" class="text-primary text-decoration-none">Política de Privacidad</a> conforme a la Ley N° 29733.
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check-whatsapp" checked>
                            <label class="form-check-label small text-muted" for="check-whatsapp">
                                Autorizo recibir alertas operativas y notificaciones de mi boleto vía WhatsApp.
                            </label>
                        </div>
                    </div>
                </form>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" onclick="window.avanzarPaso(1)">
                    <i class="fa-solid fa-arrow-left me-1"></i> Volver al Resumen
                </button>
                <button type="button" class="btn btn-primary fw-bold px-4" onclick="window.avanzarPaso(3)">
                    Continuar al Pago <i class="fa-solid fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>

        <!-- PASO 3: Método de Pago (Voucher Manual vs Pasarela Sandbox) -->
        <div id="checkout-step-3" class="checkout-step-content" style="display: none;">
            <div class="card border rounded-4 p-4 shadow-sm mb-4">
                <h2 class="h5 fw-bold text-dark mb-3"><i class="fa-solid fa-credit-card me-2 text-primary"></i> Selección de Método de Pago</h2>
                
                <!-- Opciones de Pago -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="form-check p-3 border rounded-3 h-100 cursor-pointer payment-method-card active" id="card-method-voucher" onclick="window.seleccionarMetodo('VOUCHER')">
                            <input class="form-check-input" type="radio" name="metodo_pago" id="metodo-voucher" value="TRANSFERENCIA_BANCARIA" checked>
                            <label class="form-check-label fw-bold d-block mb-1" for="metodo-voucher">
                                <i class="fa-solid fa-money-bill-transfer text-success me-1"></i> Transferencia Bancaria / Yape
                            </label>
                            <div class="small text-muted">Abona a las cuentas oficiales y registra tu comprobante. Retención asegurada por 24 horas.</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check p-3 border rounded-3 h-100 cursor-pointer payment-method-card" id="card-method-sandbox" onclick="window.seleccionarMetodo('SANDBOX')">
                            <input class="form-check-input" type="radio" name="metodo_pago" id="metodo-sandbox" value="PASARELA_NIUBIZ">
                            <label class="form-check-label fw-bold d-block mb-1" for="metodo-sandbox">
                                <i class="fa-solid fa-credit-card text-primary me-1"></i> Tarjeta Online (Modo Sandbox)
                            </label>
                            <div class="small text-muted">Pago inmediato simulado con tarjeta de débito/crédito sin cobro real para pruebas.</div>
                        </div>
                    </div>
                </div>

                <!-- Formulario Dinámico: Voucher Bancario -->
                <div id="seccion-voucher" class="p-3 bg-light rounded-3 border mb-3">
                    <div class="fw-bold small text-dark mb-2">Cuentas Bancarias de Recaudación Oficial:</div>
                    <div class="p-2 bg-white rounded border small mb-3">
                        <div><strong>Banco BCP (Soles):</strong> 191-09823412-0-45 &bull; <strong>CCI:</strong> 0021910009823412045</div>
                        <div><strong>Yape / Plin Oficial:</strong> 951-889-442 (Titular: Festividad Candelaria Oficial)</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Número de Operación Bancaria</label>
                            <input type="text" id="voucher-num-op" class="form-control" placeholder="Ej. 0984512">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">URL / Archivo de Comprobante</label>
                            <input type="text" id="voucher-url" class="form-control" placeholder="https://mi-comprobante.pe/voucher.jpg">
                        </div>
                    </div>
                </div>

                <!-- Formulario Dinámico: Pasarela Sandbox -->
                <div id="seccion-sandbox" class="p-3 bg-light rounded-3 border mb-3" style="display: none;">
                    <div class="d-flex align-items-center gap-2 text-primary fw-bold small mb-2">
                        <i class="fa-solid fa-vial"></i> Simulación de Pasarela de Pagos Niubiz / Izipay (Sandbox)
                    </div>
                    <div class="text-muted small">
                        Al presionar "Completar Pago Simulado", se validará la transacción bancaria de forma inmediata y se formalizará automáticamente su reserva sin generar cobro financiero real.
                    </div>
                </div>

                <!-- Resumen Final Total -->
                <div class="p-3 bg-primary-subtle text-primary rounded-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold fs-6">Monto Total a Formalizar:</div>
                        <div class="small">Impuestos de ley e IGV incluidos</div>
                    </div>
                    <div class="h3 fw-bold mb-0" id="final-total-display">S/ 0.00</div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" onclick="window.avanzarPaso(2)">
                    <i class="fa-solid fa-arrow-left me-1"></i> Modificar Datos
                </button>
                <button type="button" class="btn btn-success btn-lg fw-bold px-4" id="btn-finalizar-checkout" onclick="window.ejecutarCheckoutFinal()">
                    <i class="fa-solid fa-lock me-1"></i> Completar Reserva
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.pasoActual = 1;
window.desgloseActual = null;
window.tokenBloqueoActual = null;

window.seleccionarMetodo = function(tipo) {
    if (tipo === 'VOUCHER') {
        document.getElementById('metodo-voucher').checked = true;
        document.getElementById('seccion-voucher').style.display = 'block';
        document.getElementById('seccion-sandbox').style.display = 'none';
        document.getElementById('card-method-voucher').classList.add('border-primary');
        document.getElementById('card-method-sandbox').classList.remove('border-primary');
    } else {
        document.getElementById('metodo-sandbox').checked = true;
        document.getElementById('seccion-voucher').style.display = 'none';
        document.getElementById('seccion-sandbox').style.display = 'block';
        document.getElementById('card-method-sandbox').classList.add('border-primary');
        document.getElementById('card-method-voucher').classList.remove('border-primary');
    }
};

window.renderizarTablaCarrito = function() {
    const tbody = document.getElementById('cart-table-body');
    const container = document.getElementById('cart-table-container');
    const emptyMsg = document.getElementById('cart-empty-message');
    const btnContinuar = document.getElementById('btn-to-step-2');

    if (!window.CandelariaPortal.carrito || window.CandelariaPortal.carrito.length === 0) {
        container.style.display = 'none';
        emptyMsg.style.display = 'block';
        if (btnContinuar) btnContinuar.disabled = true;
        return;
    }

    container.style.display = 'block';
    emptyMsg.style.display = 'none';
    if (btnContinuar) btnContinuar.disabled = false;

    tbody.innerHTML = '';
    window.CandelariaPortal.carrito.forEach((item, idx) => {
        const sub = (item.precio * item.cantidad).toFixed(2);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <span class="fw-bold text-dark">${item.nombre}</span>
                <span class="badge bg-secondary-subtle text-secondary small ms-1">${item.tipo}</span>
            </td>
            <td class="text-center">
                <input type="number" class="form-control form-control-sm text-center mx-auto" style="width: 70px;" value="${item.cantidad}" min="1" max="10" onchange="window.actualizarCantidadCarrito(${idx}, this.value)">
            </td>
            <td class="text-end text-muted small">S/ ${item.precio.toFixed(2)}</td>
            <td class="text-end fw-bold text-primary">S/ ${sub}</td>
            <td class="text-end">
                <button type="button" class="btn btn-link text-danger p-0" onclick="window.CandelariaPortal.eliminarDelCarrito(${idx}); window.renderizarTablaCarrito();">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    window.actualizarCotizacion();
};

window.actualizarCantidadCarrito = function(idx, cant) {
    const val = parseInt(cant) || 1;
    window.CandelariaPortal.carrito[idx].cantidad = Math.max(1, val);
    window.CandelariaPortal.guardarCarritoLocal();
    window.renderizarTablaCarrito();
};

window.actualizarCotizacion = async function() {
    const cotizacion = await window.CandelariaPortal.solicitarCotizacionBackend();
    if (cotizacion) {
        window.desgloseActual = cotizacion;
        document.getElementById('backend-subtotal').textContent = 'S/ ' + parseFloat(cotizacion.subtotal).toFixed(2);
        document.getElementById('backend-igv').textContent = 'S/ ' + parseFloat(cotizacion.total_impuestos).toFixed(2);
        document.getElementById('backend-total').textContent = 'S/ ' + parseFloat(cotizacion.total).toFixed(2);
        document.getElementById('final-total-display').textContent = 'S/ ' + parseFloat(cotizacion.total).toFixed(2);
    }
};

window.avanzarPaso = function(paso) {
    if (paso === 2) {
        if (!window.CandelariaPortal.carrito || window.CandelariaPortal.carrito.length === 0) {
            window.CandelariaPortal.mostrarNotificacion('Carrito Vacío', 'Agregue productos antes de continuar.', 'warning');
            return;
        }
    }

    if (paso === 3) {
        const numDoc = document.getElementById('cli-num-doc').value.trim();
        const nombres = document.getElementById('cli-nombres').value.trim();
        const apellidos = document.getElementById('cli-apellidos').value.trim();
        const correo = document.getElementById('cli-correo').value.trim();
        const terminos = document.getElementById('check-terminos').checked;
        const privacidad = document.getElementById('check-privacidad').checked;

        if (!numDoc || !nombres || !apellidos || !correo) {
            window.CandelariaPortal.mostrarNotificacion('Datos Incompletos', 'Complete todos los campos del titular.', 'warning');
            return;
        }

        if (!terminos || !privacidad) {
            window.CandelariaPortal.mostrarNotificacion('Consentimiento Legal', 'Debe aceptar los Términos y la Política de Privacidad (Ley 29733).', 'warning');
            return;
        }
    }

    window.pasoActual = paso;
    document.querySelectorAll('.checkout-step-content').forEach(el => el.style.display = 'none');
    document.getElementById('checkout-step-' + paso).style.display = 'block';

    document.querySelectorAll('.checkout-step-item').forEach((el, i) => {
        el.classList.remove('active', 'completed');
        if (i + 1 < paso) el.classList.add('completed');
        if (i + 1 === paso) el.classList.add('active');
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.ejecutarCheckoutFinal = async function() {
    const btn = document.getElementById('btn-finalizar-checkout');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Procesando Reserva...';

    try {
        const metodoPago = document.querySelector('input[name="metodo_pago"]:checked').value;
        const payload = {
            org_slug: window.CandelariaPortal.slug,
            lineas: window.CandelariaPortal.carrito.map(c => ({
                tipo: c.tipo,
                referencia_id: c.referencia_id,
                cantidad: c.cantidad
            })),
            cliente: {
                tipo_documento_id: parseInt(document.getElementById('cli-tipo-doc').value),
                numero_documento: document.getElementById('cli-num-doc').value.trim(),
                nombres: document.getElementById('cli-nombres').value.trim(),
                apellidos: document.getElementById('cli-apellidos').value.trim(),
                correo: document.getElementById('cli-correo').value.trim(),
                telefono: document.getElementById('cli-telefono').value.trim()
            },
            consentimientos: {
                terminos_condiciones: document.getElementById('check-terminos').checked,
                politica_privacidad: document.getElementById('check-privacidad').checked,
                whatsapp_transaccional: document.getElementById('check-whatsapp').checked
            },
            metodo_pago: metodoPago,
            comprobante: {
                numero_operacion: document.getElementById('voucher-num-op').value.trim() || 'OP-PENDIENTE',
                voucher_url: document.getElementById('voucher-url').value.trim() || null
            }
        };

        // Bloquear cupos primero si no están bloqueados
        const primerItem = window.CandelariaPortal.carrito[0];
        const resBloqueo = await window.CandelariaPortal.iniciarBloqueoCupos(primerItem.referencia_id, primerItem.cantidad);
        if (!resBloqueo) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-lock me-1"></i> Completar Reserva';
            return;
        }
        payload.token_bloqueo = resBloqueo.token_bloqueo;

        const response = await fetch('<?= $urlBase ?>api/v1/publico/checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const res = await response.json();
        if (res.exito) {
            // Limpiar carrito
            window.CandelariaPortal.carrito = [];
            window.CandelariaPortal.guardarCarritoLocal();

            // Guardar confirmación en sessionStorage y redirigir
            sessionStorage.setItem('candelaria_last_checkout_' + window.CandelariaPortal.slug, JSON.stringify(res.datos));
            window.location.href = '<?= $urlBase ?>p/<?= $slug ?>/checkout/confirmacion';
        } else {
            throw new Error(res.mensaje || 'Error al procesar el checkout');
        }
    } catch (e) {
        window.CandelariaPortal.mostrarNotificacion('Error en Checkout', e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-lock me-1"></i> Completar Reserva';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.renderizarTablaCarrito();
});
</script>
