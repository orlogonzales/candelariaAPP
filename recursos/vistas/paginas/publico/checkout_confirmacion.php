<?php
/**
 * ==============================================================================
 * CANDELARIAAPP — CONFIRMACIÓN DE CHECKOUT Y RECIBO VISUAL (FASE 2.9C)
 * ==============================================================================
 */

$org = $datos['organizacion'] ?? [];
$slug = htmlspecialchars((string) ($org['codigo'] ?? 'candelaria_central'), ENT_QUOTES, 'UTF-8');
$urlBase = url_base();
?>

<div class="py-5">
    <div class="container" style="max-width: 720px;">
        <div class="card border rounded-4 p-5 text-center shadow-lg bg-white">
            <div id="confirmacion-icono" class="mb-3">
                <i class="fa-solid fa-clock-rotate-left fa-4x text-warning"></i>
            </div>

            <h1 class="h3 fw-bold text-dark mb-2" id="confirmacion-titulo">Voucher Registrado en Revisión</h1>
            <p class="text-muted small mb-4" id="confirmacion-subtitulo">
                Su comprobante ha sido ingresado al sistema. Sus cupos se encuentran retenidos formalmente por 24 horas mientras nuestro equipo administrativo valida la operación bancaria.
            </p>

            <div class="p-4 bg-light rounded-3 text-start mb-4 border">
                <div class="row g-3 small">
                    <div class="col-sm-6">
                        <span class="text-muted">Estado del Pago:</span>
                        <div class="fw-bold" id="conf-estado-pago">
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-spinner fa-spin me-1"></i> PENDIENTE DE VERIFICACIÓN</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted">Estado de la Reserva:</span>
                        <div class="fw-bold" id="conf-estado-reserva">
                            <span class="badge bg-secondary">REGISTRADA (EN ESPERA)</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted">Correlativo de Venta:</span>
                        <div class="fw-bold text-dark fs-6" id="conf-correlativo">VTA-PENDIENTE</div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted">Vigencia de Retención:</span>
                        <div class="fw-bold text-dark" id="conf-vigencia">24 horas de protección de aforo</div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info border-0 small text-start mb-4">
                <i class="fa-solid fa-circle-info me-1"></i> <strong>Información Importante:</strong>
                Una vez confirmado el abono por el operador, se emitirá su Boleto Digital QR inviolable en su espacio personal de cliente. Puede consultar el estado en cualquier momento con su número de WhatsApp.
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                <a href="<?= $urlBase ?>p/<?= $slug ?>/catalogo" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-compass me-1"></i> Volver al Catálogo
                </a>
                <a href="<?= $urlBase ?>p/<?= $slug ?>/mi-espacio" class="btn btn-primary fw-bold">
                    <i class="fa-solid fa-ticket-simple me-1"></i> Ir a Mi Espacio y Boletos
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const raw = sessionStorage.getItem('candelaria_last_checkout_' + window.CandelariaPortal.slug);
    if (!raw) return;

    try {
        const data = JSON.parse(raw);
        if (data.correlativo_venta) {
            document.getElementById('conf-correlativo').textContent = data.correlativo_venta;
        }

        if (data.modo === 'PASARELA_ONLINE_SANDBOX' || data.estado_pago === 'APROBADO') {
            document.getElementById('confirmacion-icono').innerHTML = '<i class="fa-solid fa-circle-check fa-4x text-success"></i>';
            document.getElementById('confirmacion-titulo').textContent = '¡Reserva y Pago Confirmados!';
            document.getElementById('confirmacion-subtitulo').textContent = 'Su transacción ha sido validada. Su derecho de acceso está activo y su boleto digital ha sido emitido con éxito.';
            document.getElementById('conf-estado-pago').innerHTML = '<span class="badge bg-success">PAGADA TOTAL (APROBADO)</span>';
            document.getElementById('conf-estado-reserva').innerHTML = '<span class="badge bg-success">CONFIRMADA</span>';
        }
    } catch (e) {
        console.error(e);
    }
});
</script>
