<?php
/**
 * Vista Oficial de Configuración General y Parámetros Operativos (F1.2C).
 * Componentes y estilos 100% alineados con el sistema de diseño Alina (admin-dashboard).
 */
use Nucleo\Http\ContextoOperacion;
use Aplicacion\Autorizacion\AutorizacionServicio;
use Nucleo\Seguridad\ProtectorCsrf;

$contexto = ContextoOperacion::actual();
$puedeVerOrg = false;
$puedeEditarOrg = false;
$puedeVerPlat = false;
$puedeEditarPlat = false;

if ($contexto !== null && $contexto->usuarioId !== null) {
    $authz = new AutorizacionServicio();
    $puedeVerOrg = $authz->tienePermiso($contexto->usuarioId, 'configuracion_organizacion.ver');
    $puedeEditarOrg = $authz->tienePermiso($contexto->usuarioId, 'configuracion_organizacion.editar');
    $puedeVerPlat = $authz->tienePermiso($contexto->usuarioId, 'configuracion_plataforma.ver');
    $puedeEditarPlat = $authz->tienePermiso($contexto->usuarioId, 'configuracion_plataforma.editar')
        && $authz->esSuperadmin($contexto->usuarioId);
}
?>

<!-- Token CSRF Centralizado para Peticiones Asíncronas -->
<input type="hidden" id="csrfTokenConfig" value="<?= escapar_html(ProtectorCsrf::obtenerOCrearToken()) ?>">

<!-- Encabezado de Configuración General -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-sliders text-primary me-2"></i> Configuración General y Parámetros
        </h3>
        <p class="text-secondary mb-0">
            Administración de políticas comerciales, parámetros operativos y gobernanza soberana del sistema.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex align-items-center gap-2">
            <span class="badge bg-light-primary text-primary px-3 py-2 b-r-8 f-s-12">
                <i class="fa-solid fa-shield-check me-1"></i> Entorno Gobernado
            </span>
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarConfig" title="Actualizar parámetros">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
        </div>
    </div>
</div>

<!-- Alerta de Error de Conexión / Carga (Oculta por defecto) -->
<div id="alertaErrorConfig" class="alert alert-danger d-none align-items-center justify-content-between p-3 b-r-12 shadow-sm mb-4" role="alert">
    <div class="d-flex align-items-center gap-3">
        <span class="f-s-24 text-danger"><i class="fa-solid fa-circle-exclamation"></i></span>
        <div>
            <h6 class="mb-0 f-w-600 text-danger" id="textoErrorConfigTitulo">Error al cargar la configuración</h6>
            <p class="mb-0 text-secondary f-s-13" id="textoErrorConfigDetalle">No fue posible recuperar los parámetros gobernados.</p>
        </div>
    </div>
    <button type="button" class="btn btn-outline-danger btn-sm" id="btnReintentarConfig">
        <i class="fa-solid fa-rotate-right me-1"></i> Reintentar
    </button>
</div>

<!-- Skeleton Loader Inicial -->
<div id="skeletonConfiguracion" class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body p-4">
                <div class="skeleton-shimmer skeleton-line lg w-25 mb-4"></div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card bg-light border-0 p-3 b-r-12">
                            <div class="skeleton-shimmer skeleton-line w-50 mb-2"></div>
                            <div class="skeleton-shimmer skeleton-line sm w-75 mb-3"></div>
                            <div class="skeleton-shimmer skeleton-line lg w-100"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light border-0 p-3 b-r-12">
                            <div class="skeleton-shimmer skeleton-line w-50 mb-2"></div>
                            <div class="skeleton-shimmer skeleton-line sm w-75 mb-3"></div>
                            <div class="skeleton-shimmer skeleton-line lg w-100"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light border-0 p-3 b-r-12">
                            <div class="skeleton-shimmer skeleton-line w-50 mb-2"></div>
                            <div class="skeleton-shimmer skeleton-line sm w-75 mb-3"></div>
                            <div class="skeleton-shimmer skeleton-line lg w-100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor Real de Configuración (Oculto hasta completar fetch) -->
<div id="contenidoConfiguracion" class="d-none">

    <!-- Navegación por Pestañas Semánticas de Ámbito -->
    <ul class="nav nav-tabs nav-tabs-bottom border-bottom mb-4" id="configTabs" role="tablist">
        <?php if ($puedeVerOrg): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link active f-w-600 px-4 py-3" id="tab-organizacion" data-bs-toggle="tab" data-bs-target="#panel-organizacion" type="button" role="tab" aria-controls="panel-organizacion" aria-selected="true">
                <i class="fa-solid fa-building me-2 text-primary"></i> Parámetros de Organización
            </button>
        </li>
        <?php endif; ?>
        <?php if ($puedeVerPlat): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= !$puedeVerOrg ? 'active' : '' ?> f-w-600 px-4 py-3" id="tab-plataforma" data-bs-toggle="tab" data-bs-target="#panel-plataforma" type="button" role="tab" aria-controls="panel-plataforma" aria-selected="<?= !$puedeVerOrg ? 'true' : 'false' ?>">
                <i class="fa-solid fa-shield-halved me-2 text-danger"></i> Soberanía de Plataforma
                <span class="badge bg-danger-subtle text-danger ms-2 f-s-11">Superadmin</span>
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Contenido de las Pestañas -->
    <div class="tab-content" id="configTabsContent">

        <!-- ==================================================================== -->
        <!-- PANEL 1: PARÁMETROS DE ORGANIZACIÓN (TENANT OPERATIVO)               -->
        <!-- ==================================================================== -->
        <?php if ($puedeVerOrg): ?>
        <div class="tab-pane fade show active" id="panel-organizacion" role="tabpanel" aria-labelledby="tab-organizacion">
            
            <div class="row g-4">
                <!-- Bloque 1: Parámetros Comerciales -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm b-r-12 h-100">
                        <div class="card-header bg-transparent border-bottom p-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="bg-primary-subtle text-primary p-2 b-r-8">
                                        <i class="fa-solid fa-receipt f-s-18"></i>
                                    </span>
                                    <div>
                                        <h5 class="card-title mb-0 f-w-600">Políticas Comerciales</h5>
                                        <span class="f-s-12 text-secondary">Cotizaciones, contratos y reservas mínimas</span>
                                    </div>
                                </div>
                                <span class="badge bg-light-secondary text-secondary f-s-11">Tenant</span>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            
                            <!-- organizacion.dias_validez_cotizacion -->
                            <div class="mb-4 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <label for="inputOrgDiasCotizacion" class="form-label f-s-14 f-w-600 text-dark mb-1">
                                            Validez de Cotizaciones
                                        </label>
                                        <div class="f-s-12 text-muted">
                                            Plazo de vigencia (días calendario) antes del vencimiento automático.
                                        </div>
                                    </div>
                                    <span class="badge bg-light text-dark border f-s-11" id="badgeActualizadoDiasCotizacion">-</span>
                                </div>
                                <div class="input-group" style="max-width: 240px;">
                                    <input type="number" class="form-control" id="inputOrgDiasCotizacion" min="1" max="60" step="1" <?= !$puedeEditarOrg ? 'disabled' : '' ?>>
                                    <span class="input-group-text bg-light text-secondary f-s-13">días</span>
                                </div>
                                <div class="form-text f-s-11 text-secondary mt-1">Rango admitido: 1 a 60 días.</div>
                            </div>

                            <!-- organizacion.porcentaje_reserva_minimo -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <label for="inputOrgPorcentajeReserva" class="form-label f-s-14 f-w-600 text-dark mb-1">
                                            Porcentaje Mínimo de Reserva
                                        </label>
                                        <div class="f-s-12 text-muted">
                                            Adelanto financiero requerido para bloquear fechas de cobertura.
                                        </div>
                                    </div>
                                    <span class="badge bg-light text-dark border f-s-11" id="badgeActualizadoPorcentajeReserva">-</span>
                                </div>
                                <div class="input-group" style="max-width: 240px;">
                                    <input type="number" class="form-control" id="inputOrgPorcentajeReserva" min="10" max="100" step="0.5" <?= !$puedeEditarOrg ? 'disabled' : '' ?>>
                                    <span class="input-group-text bg-light text-secondary f-s-13">%</span>
                                </div>
                                <div class="form-text f-s-11 text-secondary mt-1">Rango admitido: 10.0% a 100.0%.</div>
                            </div>

                        </div>
                        <?php if ($puedeEditarOrg): ?>
                        <div class="card-footer bg-light border-top p-3 text-end">
                            <button type="button" class="btn bg-gradient-primary btn-sm text-white" id="btnGuardarComercialOrg">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Políticas Comerciales
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bloque 2: Comunicaciones y Notificaciones -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm b-r-12 h-100">
                        <div class="card-header bg-transparent border-bottom p-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="bg-success-subtle text-success p-2 b-r-8">
                                        <i class="fa-brands fa-whatsapp f-s-20"></i>
                                    </span>
                                    <div>
                                        <h5 class="card-title mb-0 f-w-600">Canales de Comunicación</h5>
                                        <span class="f-s-12 text-secondary">Alertas y notificaciones operativas automatizadas</span>
                                    </div>
                                </div>
                                <span class="badge bg-light-secondary text-secondary f-s-11">Tenant</span>
                            </div>
                        </div>
                        <div class="card-body p-4">

                            <!-- organizacion.notificar_whatsapp -->
                            <div class="p-3 bg-light b-r-12 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="pe-3">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <label for="switchOrgNotificarWhatsapp" class="form-label f-s-14 f-w-600 text-dark mb-0 cursor-pointer">
                                                Notificaciones Automáticas por WhatsApp
                                            </label>
                                            <span class="badge bg-success-subtle text-success f-s-11" id="estadoWhatsappBadge">ACTIVO</span>
                                        </div>
                                        <div class="f-s-12 text-muted">
                                            Envío de comprobantes, cotizaciones y recordatorios de citas al cliente vía WhatsApp.
                                        </div>
                                    </div>
                                    <div class="form-check form-switch switch-md app-switch mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" role="switch" id="switchOrgNotificarWhatsapp" <?= !$puedeEditarOrg ? 'disabled' : '' ?>>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                    <span class="f-s-11 text-secondary">Última modificación:</span>
                                    <span class="badge bg-white text-secondary border f-s-11" id="badgeActualizadoWhatsapp">-</span>
                                </div>
                            </div>

                            <div class="alert alert-info border-0 p-3 b-r-8 mb-0">
                                <div class="d-flex gap-2">
                                    <span class="text-info f-s-16"><i class="fa-solid fa-circle-info"></i></span>
                                    <div class="f-s-12">
                                        Las integraciones de mensajería respetan la soberanía de datos del cliente y los números oficiales configurados en la Ficha de Organización.
                                    </div>
                                </div>
                            </div>

                        </div>
                        <?php if ($puedeEditarOrg): ?>
                        <div class="card-footer bg-light border-top p-3 text-end">
                            <button type="button" class="btn bg-gradient-primary btn-sm text-white" id="btnGuardarComunicacionesOrg">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Comunicaciones
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
        <?php endif; ?>

        <!-- ==================================================================== -->
        <!-- PANEL 2: SOBERANÍA DE PLATAFORMA (SUPERADMINISTRADOR)                -->
        <!-- ==================================================================== -->
        <?php if ($puedeVerPlat): ?>
        <div class="tab-pane fade <?= !$puedeVerOrg ? 'show active' : '' ?>" id="panel-plataforma" role="tabpanel" aria-labelledby="tab-plataforma">
            
            <!-- Banner Informativo de Soberanía -->
            <div class="alert alert-warning border-0 p-3 b-r-12 mb-4 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <span class="f-s-28 text-warning"><i class="fa-solid fa-shield-halved"></i></span>
                    <div>
                        <h6 class="mb-0 f-w-700 text-dark">Zona de Parámetros Soberanos de Plataforma</h6>
                        <span class="f-s-12 text-secondary">
                            Cualquier modificación en estos parámetros afecta a todo el sistema y a los mecanismos de seguridad transversales.
                        </span>
                    </div>
                </div>
                <span class="badge bg-danger text-white px-3 py-2 b-r-8 f-s-11">Ámbito: PLATAFORMA</span>
            </div>

            <div class="row g-4">
                
                <!-- Tarjeta 1: Transacciones y Divisa -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm b-r-12 h-100">
                        <div class="card-header bg-transparent border-bottom p-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="bg-danger-subtle text-danger p-2 b-r-8">
                                    <i class="fa-solid fa-coins f-s-18"></i>
                                </span>
                                <div>
                                    <h5 class="card-title mb-0 f-w-600">Transacciones y Moneda</h5>
                                    <span class="f-s-12 text-secondary">Límites financieros y moneda base del sistema</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            
                            <!-- plataforma.moneda_principal (INMUTABLE) -->
                            <div class="mb-4 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <label class="form-label f-s-14 f-w-600 text-dark mb-0">
                                                Moneda Soberana Principal
                                            </label>
                                            <span class="badge bg-secondary-subtle text-secondary f-s-11">
                                                <i class="fa-solid fa-lock me-1"></i> Inmutable
                                            </span>
                                        </div>
                                        <div class="f-s-12 text-muted">
                                            Código ISO 4217 de la divisa nativa. Bloqueado en PEN para la arquitectura peruana.
                                        </div>
                                    </div>
                                    <span class="badge bg-light text-dark border f-s-11" id="badgeActualizadoMoneda">-</span>
                                </div>
                                <div class="input-group" style="max-width: 240px;">
                                    <input type="text" class="form-control bg-light text-dark f-w-600" id="inputPlatMoneda" value="PEN" readonly disabled>
                                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-shield"></i></span>
                                </div>
                            </div>

                            <!-- plataforma.monto_minimo_pago_pe -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <label for="inputPlatMontoMinimo" class="form-label f-s-14 f-w-600 text-dark mb-1">
                                            Monto Mínimo de Pago
                                        </label>
                                        <div class="f-s-12 text-muted">
                                            Monto mínimo admisible para transacciones, amortizaciones y pasarelas.
                                        </div>
                                    </div>
                                    <span class="badge bg-light text-dark border f-s-11" id="badgeActualizadoMontoMinimo">-</span>
                                </div>
                                <div class="input-group" style="max-width: 240px;">
                                    <span class="input-group-text bg-light text-secondary f-s-13">S/</span>
                                    <input type="number" class="form-control" id="inputPlatMontoMinimo" min="1.0" max="10000.0" step="0.5" <?= !$puedeEditarPlat ? 'disabled' : '' ?>>
                                </div>
                                <div class="form-text f-s-11 text-secondary mt-1">Rango admitido: S/ 1.00 a S/ 10,000.00.</div>
                            </div>

                        </div>
                        <?php if ($puedeEditarPlat): ?>
                        <div class="card-footer bg-light border-top p-3 text-end">
                            <button type="button" class="btn bg-gradient-danger btn-sm text-white" id="btnGuardarFinanzasPlat">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Parámetros Financieros
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tarjeta 2: Núcleo y Región -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm b-r-12 h-100">
                        <div class="card-header bg-transparent border-bottom p-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="bg-primary-subtle text-primary p-2 b-r-8">
                                    <i class="fa-solid fa-clock f-s-18"></i>
                                </span>
                                <div>
                                    <h5 class="card-title mb-0 f-w-600">Núcleo y Región</h5>
                                    <span class="f-s-12 text-secondary">Huso horario oficial y políticas de registro</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">

                            <!-- plataforma.zona_horaria (IANA) -->
                            <div class="mb-4 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <label for="selectPlatZonaHoraria" class="form-label f-s-14 f-w-600 text-dark mb-1">
                                            Zona Horaria Oficial (IANA)
                                        </label>
                                        <div class="f-s-12 text-muted">
                                            Huso horario para marcas temporales de auditoría y cronogramas.
                                        </div>
                                    </div>
                                    <span class="badge bg-light text-dark border f-s-11" id="badgeActualizadoZonaHoraria">-</span>
                                </div>
                                <select class="form-select" id="selectPlatZonaHoraria" style="max-width: 320px;" <?= !$puedeEditarPlat ? 'disabled' : '' ?>>
                                    <option value="America/Lima">America/Lima (UTC -05:00 - Perú Oficial)</option>
                                    <option value="UTC">UTC (Tiempo Universal Coordinado)</option>
                                    <option value="America/La_Paz">America/La_Paz (UTC -04:00 - Bolivia)</option>
                                    <option value="America/Bogota">America/Bogota (UTC -05:00 - Colombia)</option>
                                    <option value="America/Santiago">America/Santiago (UTC -03:00 - Chile)</option>
                                </select>
                            </div>

                            <!-- plataforma.permitir_registro_publico -->
                            <div class="p-3 bg-light b-r-12 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="pe-3">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <label for="switchPlatRegistroPublico" class="form-label f-s-14 f-w-600 text-dark mb-0 cursor-pointer">
                                                Permitir Registro Público
                                            </label>
                                            <span class="badge bg-secondary-subtle text-secondary f-s-11" id="estadoRegistroPublicoBadge">DESACTIVADO</span>
                                        </div>
                                        <div class="f-s-12 text-muted">
                                            Permite o bloquea el registro autónomo de nuevos usuarios desde internet.
                                        </div>
                                    </div>
                                    <div class="form-check form-switch switch-md app-switch mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" role="switch" id="switchPlatRegistroPublico" <?= !$puedeEditarPlat ? 'disabled' : '' ?>>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                    <span class="f-s-11 text-secondary">Última modificación:</span>
                                    <span class="badge bg-white text-secondary border f-s-11" id="badgeActualizadoRegistroPublico">-</span>
                                </div>
                            </div>

                        </div>
                        <?php if ($puedeEditarPlat): ?>
                        <div class="card-footer bg-light border-top p-3 text-end">
                            <button type="button" class="btn bg-gradient-danger btn-sm text-white" id="btnGuardarNucleoPlat">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Núcleo y Región
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tarjeta 3: Seguridad y Hardening de Autenticación -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm b-r-12">
                        <div class="card-header bg-transparent border-bottom p-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="bg-danger-subtle text-danger p-2 b-r-8">
                                    <i class="fa-solid fa-user-lock f-s-18"></i>
                                </span>
                                <div>
                                    <h5 class="card-title mb-0 f-w-600">Seguridad y Hardening de Autenticación</h5>
                                    <span class="f-s-12 text-secondary">Políticas anti-fuerza bruta dinámicamente conectadas a AutenticacionServicio</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-4">

                                <!-- plataforma.max_intentos_login -->
                                <div class="col-md-6">
                                    <div class="p-3 border b-r-12">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <label for="inputPlatMaxIntentos" class="form-label f-s-14 f-w-600 text-dark mb-1">
                                                    Intentos Máximos de Login
                                                </label>
                                                <div class="f-s-12 text-muted">
                                                    Número consecutivo de fallos permitidos antes del bloqueo temporal.
                                                </div>
                                            </div>
                                            <span class="badge bg-light text-dark border f-s-11" id="badgeActualizadoMaxIntentos">-</span>
                                        </div>
                                        <div class="input-group" style="max-width: 240px;">
                                            <input type="number" class="form-control" id="inputPlatMaxIntentos" min="3" max="10" step="1" <?= !$puedeEditarPlat ? 'disabled' : '' ?>>
                                            <span class="input-group-text bg-light text-secondary f-s-13">intentos</span>
                                        </div>
                                        <div class="form-text f-s-11 text-secondary mt-1">Rango admitido: 3 a 10 intentos.</div>
                                    </div>
                                </div>

                                <!-- plataforma.minutos_bloqueo_login -->
                                <div class="col-md-6">
                                    <div class="p-3 border b-r-12">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <label for="inputPlatMinutosBloqueo" class="form-label f-s-14 f-w-600 text-dark mb-1">
                                                    Minutos de Bloqueo Temporal
                                                </label>
                                                <div class="f-s-12 text-muted">
                                                    Penalización temporal tras exceder el límite máximo de fallos.
                                                </div>
                                            </div>
                                            <span class="badge bg-light text-dark border f-s-11" id="badgeActualizadoMinutosBloqueo">-</span>
                                        </div>
                                        <div class="input-group" style="max-width: 240px;">
                                            <input type="number" class="form-control" id="inputPlatMinutosBloqueo" min="5" max="1440" step="1" <?= !$puedeEditarPlat ? 'disabled' : '' ?>>
                                            <span class="input-group-text bg-light text-secondary f-s-13">minutos</span>
                                        </div>
                                        <div class="form-text f-s-11 text-secondary mt-1">Rango admitido: 5 a 1440 minutos (hasta 24 horas).</div>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <?php if ($puedeEditarPlat): ?>
                        <div class="card-footer bg-light border-top p-3 text-end">
                            <button type="button" class="btn bg-gradient-danger btn-sm text-white" id="btnGuardarSeguridadPlat">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Políticas de Seguridad
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>
        <?php endif; ?>

    </div>

</div>
