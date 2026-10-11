<?php
/**
 * ==============================================================================
 * CANDELARIAAPP — FICHA PÚBLICA DE PAQUETE CON DISPONIBILIDAD REAL (FASE 2.9C)
 * ==============================================================================
 */

$org = $datos['organizacion'] ?? [];
$edicion = $datos['edicion'] ?? [];
$paquete = $datos['paquete'] ?? [];
$componentes = $paquete['componentes'] ?? [];
$slug = htmlspecialchars((string) ($org['codigo'] ?? 'candelaria_central'), ENT_QUOTES, 'UTF-8');
$urlBase = url_base();
$cupos = (int) ($paquete['cupos_disponibles'] ?? 0);
$precioUnitario = (float) ($paquete['precio'] ?? 0);
$precioFormateado = number_format($precioUnitario, 2);
$esAgotado = $cupos <= 0;
?>

<div class="py-5">
    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= $urlBase ?>p/<?= $slug ?>/catalogo" class="text-decoration-none">Catálogo</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars((string) $paquete['nombre'], ENT_QUOTES, 'UTF-8') ?></li>
            </ol>
        </nav>

        <div class="row g-5">
            <!-- Columna Izquierda: Información Detallada del Paquete -->
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary-subtle text-primary fw-bold">PAQUETE OFICIAL CANDELARIA</span>
                    <?php if ($esAgotado): ?>
                        <span class="badge bg-danger fw-bold">Aforo Agotado</span>
                    <?php else: ?>
                        <span class="badge bg-success-subtle text-success fw-bold">
                            <i class="fa-solid fa-circle-check me-1"></i> <?= $cupos ?> cupos confirmados
                        </span>
                    <?php endif; ?>
                </div>

                <h1 class="h2 fw-bold text-dark mb-3"><?= htmlspecialchars((string) $paquete['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>
                
                <div class="lead text-muted mb-4 fs-6">
                    <?= nl2br(htmlspecialchars((string) ($paquete['descripcion'] ?? 'Experiencia completa para disfrutar de las mejores comparsas, danzas y desfiles con comodidad, seguridad y servicio garantizado.'), ENT_QUOTES, 'UTF-8')) ?>
                </div>

                <!-- Servicios y Componentes Incluidos -->
                <div class="card border rounded-4 p-4 mb-4 shadow-sm">
                    <h3 class="h5 fw-bold text-dark mb-3">
                        <i class="fa-solid fa-layer-group text-warning me-2"></i> Servicios Incluidos en el Paquete
                    </h3>

                    <?php if (!empty($componentes)): ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($componentes as $comp): ?>
                                <div class="d-flex align-items-start gap-3 p-3 bg-light rounded-3">
                                    <div class="bg-white p-2 rounded-circle border text-primary shadow-sm">
                                        <i class="fa-solid fa-check"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars((string) $comp['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted small">Cantidad: <?= (float) $comp['cantidad'] ?>x por cada paquete contratado.</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Detalle de inclusiones predeterminadas según programación oficial de la festividad.</p>
                    <?php endif; ?>
                </div>

                <!-- Garantías y Políticas -->
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <div class="fw-bold text-dark small mb-1"><i class="fa-solid fa-shield-halved text-success me-1"></i> Aforo Auditado</div>
                            <div class="text-muted" style="font-size: 0.8rem;">Control estricto de cupos para evitar sobreventa o aglomeraciones no autorizadas.</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 bg-white h-100">
                            <div class="fw-bold text-dark small mb-1"><i class="fa-solid fa-qrcode text-primary me-1"></i> Boleto con Firma Digital</div>
                            <div class="text-muted" style="font-size: 0.8rem;">Código QR inviolable con verificación criptográfica para acceso rápido y seguro.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Tarjeta de Cotización y Reserva Rápida -->
            <div class="col-lg-5">
                <div class="card border rounded-4 p-4 shadow-md sticky-top" style="top: 100px;">
                    <div class="d-flex justify-content-between align-items-baseline mb-3">
                        <span class="text-muted small fw-semibold">Tarifa Oficial</span>
                        <div class="text-end">
                            <span class="h2 fw-bold text-primary mb-0">S/ <?= $precioFormateado ?></span>
                            <div class="text-muted" style="font-size: 0.72rem;">Precio final con IGV (18%)</div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <form id="form-reserva-paquete" onsubmit="event.preventDefault(); window.iniciarCheckoutDirecto();">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">Cantidad de Paquetes / Entradas</label>
                            <div class="input-group">
                                <button class="btn btn-outline-secondary" type="button" onclick="window.cambiarCantidad(-1)">-</button>
                                <input type="number" id="input-cantidad" class="form-control text-center fw-bold" value="1" min="1" max="<?= max(1, $cupos) ?>" <?= $esAgotado ? 'disabled' : '' ?>>
                                <button class="btn btn-outline-secondary" type="button" onclick="window.cambiarCantidad(1)">+</button>
                            </div>
                            <div class="form-text small">Disponibilidad máxima actual: <?= $cupos ?> cupos.</div>
                        </div>

                        <!-- Desglose Financiero en Vivo -->
                        <div class="p-3 bg-light rounded-3 mb-4">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Subtotal Neto:</span>
                                <span id="resumen-subtotal">S/ <?= number_format($precioUnitario / 1.18, 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-2">
                                <span>IGV (18% Ley Perú):</span>
                                <span id="resumen-igv">S/ <?= number_format($precioUnitario - ($precioUnitario / 1.18), 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold text-dark border-top pt-2">
                                <span>Total a Pagar:</span>
                                <span id="resumen-total" class="text-primary">S/ <?= $precioFormateado ?></span>
                            </div>
                        </div>

                        <?php if (!$esAgotado): ?>
                            <button type="button" class="btn btn-primary btn-lg w-100 fw-bold mb-2 shadow-sm" onclick="window.iniciarCheckoutDirecto()">
                                <i class="fa-solid fa-lock me-1"></i> Iniciar Reserva Inmediata
                            </button>
                            <button type="button" class="btn btn-outline-dark w-100 fw-semibold btn-agregar-carrito"
                                    data-tipo="PAQUETE"
                                    data-id="<?= (int) $paquete['id'] ?>"
                                    data-nombre="<?= htmlspecialchars((string) $paquete['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-precio="<?= $precioFormateado ?>"
                                    data-moneda="PEN">
                                <i class="fa-solid fa-cart-plus me-1"></i> Agregar al Carrito
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-lg w-100 fw-bold" disabled>
                                Aforo Completo para esta Edición
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.precioUnitario = <?= $precioUnitario ?>;
window.cuposMaximos = <?= $cupos ?>;

window.cambiarCantidad = function(delta) {
    const input = document.getElementById('input-cantidad');
    let val = parseInt(input.value) || 1;
    val = Math.max(1, Math.min(window.cuposMaximos, val + delta));
    input.value = val;
    window.actualizarDesglose(val);
};

window.actualizarDesglose = function(cant) {
    const total = window.precioUnitario * cant;
    const subtotal = total / 1.18;
    const igv = total - subtotal;

    document.getElementById('resumen-subtotal').textContent = 'S/ ' + subtotal.toFixed(2);
    document.getElementById('resumen-igv').textContent = 'S/ ' + igv.toFixed(2);
    document.getElementById('resumen-total').textContent = 'S/ ' + total.toFixed(2);
};

window.iniciarCheckoutDirecto = function() {
    const cant = parseInt(document.getElementById('input-cantidad').value) || 1;
    window.CandelariaPortal.carrito = [{
        tipo: 'PAQUETE',
        referencia_id: <?= (int) $paquete['id'] ?>,
        nombre: '<?= addslashes((string)$paquete['nombre']) ?>',
        precio: window.precioUnitario,
        moneda: 'PEN',
        cantidad: cant
    }];
    window.CandelariaPortal.guardarCarritoLocal();
    window.location.href = '<?= $urlBase ?>p/<?= $slug ?>/checkout';
};
</script>
