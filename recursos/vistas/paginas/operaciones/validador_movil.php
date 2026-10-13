<?php
/**
 * Vista Alina: Validador Móvil QR y Operación de Campo Offline (Fase 3.3 / DT-V11-010)
 */
$edicionNombre = !empty($edicion['nombre']) ? htmlspecialchars($edicion['nombre']) : 'Edición Candelaria 2026';
$edicionAnio = !empty($edicion['anio']) ? htmlspecialchars((string) $edicion['anio']) : '2026';
$edicionId = !empty($edicion['id']) ? (int) $edicion['id'] : 1;
?>

<div class="row g-3 mb-4" id="contenedorValidadorMovil" data-edicion-id="<?= $edicionId ?>" data-csrf="<?= htmlspecialchars($tokenCsrf ?? '') ?>">
    <!-- Encabezado de Operación de Campo -->
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-gradient-primary text-white p-3 rounded-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <span class="badge bg-light text-primary fw-bold mb-1">Módulo 23 · Control de Campo</span>
                    <h4 class="mb-0 fw-bold d-flex align-items-center gap-2">
                        <i class="fa-solid fa-qrcode"></i> Validador Móvil QR
                    </h4>
                    <small class="text-white-50">Edición: <?= $edicionNombre ?> (<?= $edicionAnio ?>) · Operador: <?= htmlspecialchars($usuario->nombreUsuario ?? 'Operador') ?></small>
                </div>
                <!-- Píldora reactiva de conectividad -->
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill bg-success px-3 py-2 fs-6 shadow-sm" id="badgeEstadoRed">
                        <i class="fa-solid fa-wifi me-1"></i> <span id="textoEstadoRed">ONLINE</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" id="btnToggleModoRed" title="Simular conmutación de red Online/Offline">
                        <i class="fa-solid fa-toggle-on me-1"></i> Forzar Offline
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Estado de Sincronización y Acciones Rápidas -->
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-3">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-md-6 d-flex align-items-center gap-3">
                        <div class="avatar avatar-md bg-light-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                            <i class="fa-solid fa-database fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">Paquete Offline Local: <span id="estadoPaqueteOffline" class="text-warning">Sin descargar</span></div>
                            <small class="text-muted" id="detallesPaqueteOffline">Vigencia: No iniciada · Credenciales en caché: 0</small>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" id="btnDescargarPaquete">
                            <i class="fa-solid fa-cloud-arrow-down me-1"></i> Descargar Turno Offline
                        </button>
                        <button type="button" class="btn btn-warning btn-sm rounded-pill text-dark fw-bold position-relative" id="btnSincronizarLote">
                            <i class="fa-solid fa-rotate me-1"></i> Sincronizar Lote
                            <span class="badge bg-danger rounded-pill ms-1 d-none" id="badgeColaPendiente">0</span>
                        </button>
                        <button type="button" class="btn btn-light btn-sm rounded-pill border" id="btnVerHistorialLotes" data-bs-toggle="modal" data-bs-target="#modalHistorialLotes">
                            <i class="fa-solid fa-clock-rotate-left me-1"></i> Historial
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Área Principal de Escaneo y Resultados -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-3">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="fa-solid fa-camera text-primary"></i> Visor de Cámara y Lector QR
                </h6>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" id="btnConmutarCamara" title="Alternar Cámara">
                        <i class="fa-solid fa-camera-rotate"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnLinterna" title="Encender Linterna">
                        <i class="fa-solid fa-bolt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0 position-relative bg-dark text-center" style="min-height: 280px; display: flex; align-items: center; justify-content: center;">
                <video id="videoScanner" playsinline class="w-100" style="max-height: 380px; object-fit: cover;"></video>
                <canvas id="canvasScanner" class="d-none"></canvas>

                <!-- Mira de Enfoque y Animación de Escaneo -->
                <div class="position-absolute top-50 start-50 translate-middle pointer-events-none" id="miraEscaneo" style="width: 200px; height: 200px; border: 2px dashed rgba(255, 255, 255, 0.7); border-radius: 16px; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.4);">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #00d26a; animation: animLaser 2s infinite alternate;"></div>
                </div>

                <!-- Mensaje de Estado / Cámara Desactivada -->
                <div id="visorEstadoMensaje" class="position-absolute text-white p-3 rounded" style="background: rgba(0,0,0,0.7); display: none;">
                    <i class="fa-solid fa-circle-notch fa-spin me-2"></i> Iniciando cámara...
                </div>
            </div>
            <div class="card-footer bg-light py-2 px-3 text-center">
                <small class="text-muted">Apunte la cámara hacia el código QR del boleto o acreditación para validación instantánea.</small>
            </div>
        </div>

        <!-- Entrada Manual de Contingencia -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="fa-solid fa-keyboard text-secondary"></i> Ingreso Manual de Contingencia
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="input-group input-group-lg">
                    <input type="text" id="inputCodigoManual" class="form-control text-uppercase fw-bold font-monospace" placeholder="Ej. RES-2026-0001" autocomplete="off">
                    <button class="btn btn-primary px-4 fw-bold" type="button" id="btnValidarManual">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Validar
                    </button>
                </div>
                <div class="form-text mt-2 text-muted">
                    Utilice este campo si el código QR físico está deteriorado o la pantalla del cliente tiene brillo insuficiente.
                </div>
            </div>
        </div>
    </div>

    <!-- Panel de Resultados y Cola de Escaneos -->
    <div class="col-12 col-lg-5">
        <!-- Tarjeta de Resultado de Validación Dinámica -->
        <div class="card border-0 shadow-sm rounded-4 mb-3" id="tarjetaResultado" style="display: none;">
            <div class="card-body p-4 text-center" id="cuerpoResultado">
                <!-- Se inyecta dinámicamente con JS -->
            </div>
        </div>

        <!-- Cola de Escaneos Recientes de la Sesión -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="fa-solid fa-list-check text-primary"></i> Bitácora de Sesión
                </h6>
                <span class="badge bg-light text-dark fw-bold" id="badgeTotalSesion">0 escaneos</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 380px;">
                    <table class="table table-hover align-middle mb-0" id="tablaBitacoraSesion">
                        <thead class="table-light fs-7">
                            <tr>
                                <th class="ps-3">Hora</th>
                                <th>Credencial</th>
                                <th>Resultado</th>
                                <th class="text-end pe-3">Sincronizado</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyBitacoraSesion">
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="fa-regular fa-clipboard fs-4 d-block mb-1"></i>
                                    Aún no se han registrado validaciones en esta sesión.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Historial de Lotes Sincronizados -->
<div class="modal fade" id="modalHistorialLotes" tabindex="-1" aria-labelledby="modalHistorialLotesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold" id="modalHistorialLotesLabel">
                    <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Historial de Lotes Offline
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0" id="tablaHistorialLotes">
                        <thead class="table-light">
                            <tr>
                                <th>Lote UUID</th>
                                <th>Operador</th>
                                <th>Escaneos</th>
                                <th>Confirmados</th>
                                <th>Conflictos</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyHistorialLotes">
                            <tr>
                                <td colspan="7" class="text-center py-3 text-muted">Cargando historial de lotes...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes animLaser {
    0% { top: 0%; opacity: 0.8; }
    100% { top: 98%; opacity: 0.8; }
}
.fs-7 { font-size: 0.82rem; }
</style>
