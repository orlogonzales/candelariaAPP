<?php
/**
 * Vista Ficha 360 del Cliente (F2.2C).
 * Alina Tabs + Timeline + KPIs Comerciales.
 */
$nombreDisplay = $persona ? $persona->obtenerNombreCompleto() : 'Cliente #' . $cliente->id;
$docDisplay = ($persona && $persona->numeroDocumento) ? $persona->numeroDocumento : '(Sin Documento)';
$waDisplay = ($persona && $persona->telefonoWhatsapp) ? $persona->telefonoWhatsapp : null;
$correoDisplay = ($persona && $persona->correoElectronico) ? $persona->correoElectronico : '(Sin Correo)';
?>

<!-- Encabezado de la Ficha 360 -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <div class="d-flex align-items-center gap-2 mb-2">
            <a href="<?= url_base('clientes') ?>" class="btn btn-outline-secondary btn-sm" title="Volver al Padrón">
                <i class="fa-solid fa-arrow-left me-1"></i> Padrón
            </a>
            <span class="badge bg-secondary f-s-12">ID: #<?= (int) $cliente->id ?></span>
            <span class="badge bg-<?= match ($cliente->estadoComercial->value) {
                'PROSPECTO' => 'info',
                'ACTIVO'    => 'success',
                'INACTIVO'  => 'secondary',
                'BLOQUEADO' => 'danger',
                default     => 'primary'
            } ?> f-s-12"><?= escapar_html($cliente->estadoComercial->value) ?></span>
            <span class="badge bg-light text-dark border f-s-12"><?= escapar_html($persona?->tipoPersona->value ?? 'NATURAL') ?></span>
        </div>
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-id-card text-primary me-2"></i> <?= escapar_html($nombreDisplay) ?>
        </h3>
        <p class="text-secondary mb-0">
            Ficha 360: Identidad Persona, Oportunidades por Edición, Timeline de Interacciones y Consentimientos.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-success btn-md" id="btnModalRegistrarInteraccion">
                <i class="fa-solid fa-comment-dots me-1"></i> Registrar Interacción
            </button>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnModalNuevaOportunidad">
                <i class="fa-solid fa-bullseye me-1"></i> Nueva Oportunidad
            </button>
        </div>
    </div>
</div>

<input type="hidden" id="fichaClienteId" value="<?= (int) $cliente->id ?>">
<input type="hidden" id="fichaPersonaId" value="<?= (int) $cliente->personaId ?>">

<!-- Tabs Alina Nativas -->
<div class="card border-0 shadow-sm b-r-12 mb-4">
    <div class="card-header bg-white border-bottom p-3">
        <ul class="nav nav-tabs card-header-tabs" id="tabsCliente" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active f-w-600" id="tabResumen-tab" data-bs-toggle="tab" data-bs-target="#tabResumen" type="button" role="tab" aria-controls="tabResumen" aria-selected="true">
                    <i class="fa-solid fa-user-circle me-1 text-primary"></i> Resumen 360
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link f-w-600" id="tabOportunidades-tab" data-bs-toggle="tab" data-bs-target="#tabOportunidades" type="button" role="tab" aria-controls="tabOportunidades" aria-selected="false">
                    <i class="fa-solid fa-bullseye me-1 text-danger"></i> Oportunidades Comerciales
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link f-w-600" id="tabTimeline-tab" data-bs-toggle="tab" data-bs-target="#tabTimeline" type="button" role="tab" aria-controls="tabTimeline" aria-selected="false">
                    <i class="fa-solid fa-timeline me-1 text-success"></i> Timeline e Interacciones
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link f-w-600" id="tabConsentimientos-tab" data-bs-toggle="tab" data-bs-target="#tabConsentimientos" type="button" role="tab" aria-controls="tabConsentimientos" aria-selected="false">
                    <i class="fa-solid fa-shield-halved me-1 text-info"></i> Consentimientos y Privacidad
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4">
        <div class="tab-content" id="tabsClienteContenido">
            
            <!-- TAB 1: RESUMEN 360 -->
            <div class="tab-pane fade show active" id="tabResumen" role="tabpanel" aria-labelledby="tabResumen-tab">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="p-3 border b-r-10 h-100">
                            <h5 class="f-w-700 text-dark mb-3">
                                <i class="fa-solid fa-address-card text-primary me-2"></i> Identidad y Datos Generales
                            </h5>
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <th class="text-muted w-35 f-s-13">Nombre Completo:</th>
                                        <td class="f-w-600 text-dark"><?= escapar_html($nombreDisplay) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted f-s-13">Tipo Persona:</th>
                                        <td><?= escapar_html($persona?->tipoPersona->value ?? 'NATURAL') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted f-s-13">Documento:</th>
                                        <td><code><?= escapar_html($docDisplay) ?></code></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted f-s-13">País / Ciudad:</th>
                                        <td><?= escapar_html(($persona?->codigoPais ?? 'PE') . ' - ' . ($persona?->ciudad ?? 'Puno')) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted f-s-13">Dirección:</th>
                                        <td><?= escapar_html($persona?->direccion ?? '(No registrada)') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted f-s-13">Identidad Maestra:</th>
                                        <td>Persona #<?= (int) $cliente->personaId ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="p-3 border b-r-10 h-100">
                            <h5 class="f-w-700 text-dark mb-3">
                                <i class="fa-solid fa-headset text-success me-2"></i> Canales de Contacto Directo
                            </h5>
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <th class="text-muted w-35 f-s-13">WhatsApp:</th>
                                        <td>
                                            <?php if ($waDisplay): ?>
                                                <a href="https://wa.me/<?= preg_replace('/\D/', '', $waDisplay) ?>" target="_blank" class="text-success f-w-600 text-decoration-none">
                                                    <i class="fa-brands fa-whatsapp me-1"></i> <?= escapar_html($waDisplay) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">(Sin WhatsApp registrado)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted f-s-13">Correo Electrónico:</th>
                                        <td>
                                            <?php if ($correoDisplay && $correoDisplay !== '(Sin Correo)'): ?>
                                                <a href="mailto:<?= escapar_html($correoDisplay) ?>" class="text-primary text-decoration-none">
                                                    <i class="fa-solid fa-envelope me-1"></i> <?= escapar_html($correoDisplay) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted"><?= escapar_html($correoDisplay) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted f-s-13">Teléfono Móvil:</th>
                                        <td><?= escapar_html($persona?->telefonoMovil ?? '(No registrado)') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted f-s-13">Estado Comercial:</th>
                                        <td><span class="badge bg-primary"><?= escapar_html($cliente->estadoComercial->value) ?></span></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted f-s-13">Cliente Desde:</th>
                                        <td><?= date('d/m/Y H:i', strtotime($cliente->creadoEn ?? 'now')) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted f-s-13">Notas Comerciales:</th>
                                        <td class="text-secondary"><?= escapar_html($cliente->notasComerciales ?? '(Sin notas)') ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: OPORTUNIDADES COMERCIALES -->
            <div class="tab-pane fade" id="tabOportunidades" role="tabpanel" aria-labelledby="tabOportunidades-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="f-w-700 text-dark mb-0">
                        <i class="fa-solid fa-bullseye text-primary me-2"></i> Negocios y Oportunidades del Cliente
                    </h5>
                    <button type="button" class="btn btn-sm btn-primary" id="btnNuevaOportunidadTab">
                        <i class="fa-solid fa-plus me-1"></i> Nueva Oportunidad
                    </button>
                </div>
                <div id="contenedorOportunidadesCliente" class="table-responsive">
                    <!-- Tabla inyectada dinámicamente -->
                </div>
            </div>

            <!-- TAB 3: TIMELINE E INTERACCIONES -->
            <div class="tab-pane fade" id="tabTimeline" role="tabpanel" aria-labelledby="tabTimeline-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="f-w-700 text-dark mb-0">
                        <i class="fa-solid fa-timeline text-success me-2"></i> Bitácora Comercial y Seguimiento
                    </h5>
                    <button type="button" class="btn btn-sm btn-success text-white" id="btnNuevaInteraccionTab">
                        <i class="fa-solid fa-plus me-1"></i> Registrar Interacción
                    </button>
                </div>

                <div class="app-timeline-box p-3" id="contenedorTimelineCliente">
                    <!-- Timeline Alina inyectado dinámicamente por cliente_ficha.js -->
                </div>
            </div>

            <!-- TAB 4: CONSENTIMIENTOS Y PRIVACIDAD -->
            <div class="tab-pane fade" id="tabConsentimientos" role="tabpanel" aria-labelledby="tabConsentimientos-tab">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="p-3 border b-r-10">
                            <h5 class="f-w-700 text-dark mb-3">
                                <i class="fa-solid fa-shield-check text-info me-2"></i> Estado de Consentimientos
                            </h5>
                            
                            <div class="p-3 mb-3 bg-light b-r-8">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="f-w-600 f-s-13">Consentimiento Operativo:</span>
                                    <span class="badge bg-<?= $cliente->consentimientoOperativo ? 'success' : 'secondary' ?>" id="badgeConsentimientoOperativo">
                                        <?= $cliente->consentimientoOperativo ? 'OTORGADO' : 'NO OTORGADO' ?>
                                    </span>
                                </div>
                                <div class="f-s-11 text-muted" id="fechaConsentimientoOperativo">
                                    <?= $cliente->consentimientoOperativoEn ? 'Registrado: ' . $cliente->consentimientoOperativoEn : 'Pendiente de consentimiento explícito' ?>
                                </div>
                            </div>

                            <div class="p-3 mb-3 bg-light b-r-8">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="f-w-600 f-s-13">Consentimiento Promocional:</span>
                                    <span class="badge bg-<?= $cliente->consentimientoPromocional ? 'success' : 'secondary' ?>" id="badgeConsentimientoPromocional">
                                        <?= $cliente->consentimientoPromocional ? 'OTORGADO' : 'NO OTORGADO' ?>
                                    </span>
                                </div>
                                <div class="f-s-11 text-muted" id="fechaConsentimientoPromocional">
                                    <?= $cliente->consentimientoPromocionalEn ? 'Registrado: ' . $cliente->consentimientoPromocionalEn : 'Pendiente de consentimiento explícito' ?>
                                </div>
                            </div>

                            <form id="formRegistrarConsentimiento" class="mt-3">
                                <h6 class="f-w-600 f-s-13 mb-2">Actualizar Consentimiento con Evidencia:</h6>
                                <div class="mb-2">
                                    <label class="form-label f-s-12 text-muted mb-1">Tipo de Consentimiento</label>
                                    <select class="form-select form-select-sm" id="consentimientoTipo" name="tipo" required>
                                        <option value="OPERATIVO">OPERATIVO (Comunicaciones de Servicio)</option>
                                        <option value="PROMOCIONAL">PROMOCIONAL (Ofertas y Difusión)</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label f-s-12 text-muted mb-1">Decisión del Cliente</label>
                                    <select class="form-select form-select-sm" id="consentimientoOtorgado" name="otorgado" required>
                                        <option value="1">OTORGA CONSENTIMIENTO (SÍ)</option>
                                        <option value="0">REVOCA / RECHAZA (NO)</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label f-s-12 text-muted mb-1">Medio de Recepción</label>
                                    <select class="form-select form-select-sm" id="consentimientoMedio" name="medio">
                                        <option value="WHATSAPP">WhatsApp</option>
                                        <option value="CORREO">Correo Electrónico</option>
                                        <option value="CONVERSACION_DIRECTA">Conversación Directa / Presencial</option>
                                        <option value="WEB_FORM">Formulario Web</option>
                                        <option value="OTRO">Otro Medio</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label f-s-12 text-muted mb-1">Evidencia / Detalle</label>
                                    <input type="text" class="form-control form-control-sm" id="consentimientoEvidencia" name="evidencia" placeholder="Ej. Aceptado por mensaje de WhatsApp..." required>
                                </div>
                                <button type="submit" class="btn btn-sm btn-info text-white w-100" id="btnGuardarConsentimiento">
                                    <i class="fa-solid fa-file-shield me-1"></i> Registrar en Historial Inmutable
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="p-3 border b-r-10 h-100">
                            <h5 class="f-w-700 text-dark mb-3">
                                <i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i> Pista de Auditoría de Consentimientos
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped f-s-12 mb-0" id="tablaHistorialConsentimientos">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Tipo</th>
                                            <th>Estado</th>
                                            <th>Medio</th>
                                            <th>Evidencia</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyHistorialConsentimientos">
                                        <!-- Inyectado dinámicamente -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: REGISTRAR INTERACCIÓN COMERCIAL (APPEND-ONLY)
============================================================================== -->
<div class="modal fade" id="modalRegistrarInteraccion" tabindex="-1" aria-labelledby="modalRegistrarInteraccionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-success text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalRegistrarInteraccionLabel">
                    <i class="fa-solid fa-comment-dots me-2"></i> Registrar Interacción Comercial
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formRegistrarInteraccion" novalidate>
                <input type="hidden" name="cliente_id" value="<?= (int) $cliente->id ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="interaccionCanalId" class="form-label f-w-600 f-s-13">Canal de Contacto <span class="text-danger">*</span></label>
                            <select class="form-select" id="interaccionCanalId" name="canal_id" required>
                                <option value="1">WhatsApp</option>
                                <option value="2">Llamada Telefónica</option>
                                <option value="3">Correo Electrónico</option>
                                <option value="4">Reunión Presencial</option>
                                <option value="5">Plataforma Web</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="interaccionOportunidadId" class="form-label f-w-600 f-s-13">Oportunidad Vinculada (Opcional)</label>
                            <select class="form-select" id="interaccionOportunidadId" name="oportunidad_id">
                                <option value="">(Interacción General del Cliente)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="interaccionTipo" class="form-label f-w-600 f-s-13">Tipo de Interacción <span class="text-danger">*</span></label>
                            <select class="form-select" id="interaccionTipo" name="tipo" required>
                                <option value="WHATSAPP">Mensaje WhatsApp</option>
                                <option value="LLAMADA">Llamada Telefónica</option>
                                <option value="CORREO">Correo Electrónico</option>
                                <option value="REUNION">Reunión / Cita</option>
                                <option value="NOTA">Nota Interna de Seguimiento</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="interaccionDireccion" class="form-label f-w-600 f-s-13">Dirección del Contacto <span class="text-danger">*</span></label>
                            <select class="form-select" id="interaccionDireccion" name="direccion" required>
                                <option value="SALIENTE">SALIENTE (Contactamos al cliente)</option>
                                <option value="ENTRANTE">ENTRANTE (El cliente se comunicó)</option>
                                <option value="INTERNA">INTERNA (Anotación entre asesores)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="interaccionResumen" class="form-label f-w-600 f-s-13">Resumen de la Conversación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="interaccionResumen" name="resumen" placeholder="Ej. Solicitó información de paquete fotográfico..." required maxlength="255">
                        </div>
                        <div class="col-12">
                            <label for="interaccionDetalle" class="form-label f-w-600 f-s-13">Detalle / Acuerdos de Seguimiento</label>
                            <textarea class="form-control" id="interaccionDetalle" name="detalle" rows="3" placeholder="Detalles específicos, preferencias del cliente o fecha acordada para próxima llamada..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnGuardarInteraccion">
                        <i class="fa-solid fa-save me-1"></i> Guardar Interacción
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL: CREAR OPORTUNIDAD PARA ESTE CLIENTE
============================================================================== -->
<div class="modal fade" id="modalCrearOportunidadCliente" tabindex="-1" aria-labelledby="modalCrearOportunidadClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCrearOportunidadClienteLabel">
                    <i class="fa-solid fa-bullseye me-2"></i> Nueva Oportunidad Comercial
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCrearOportunidadCliente" novalidate>
                <input type="hidden" name="cliente_id" value="<?= (int) $cliente->id ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="opClienteEdicionId" class="form-label f-w-600 f-s-13">Edición Candelaria <span class="text-danger">*</span></label>
                            <select class="form-select" id="opClienteEdicionId" name="edicion_id" required>
                                <option value="">Seleccione Edición...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="opClienteOrigenId" class="form-label f-w-600 f-s-13">Origen Comercial</label>
                            <select class="form-select" id="opClienteOrigenId" name="origen_comercial_id">
                                <option value="">(Sin Origen Específico)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="opClienteTitulo" class="form-label f-w-600 f-s-13">Título del Negocio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="opClienteTitulo" name="titulo" placeholder="Ej. Cobertura Morenada Laykakota 2026" required maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label for="opClienteValor" class="form-label f-w-600 f-s-13">Valor Estimado</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted f-w-600">Monto</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="opClienteValor" name="valor_estimado" placeholder="0.00">
                            </div>
                            <div class="form-text f-s-11">Moneda soberana resuelta por plataforma al momento de creación.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="opClienteAsesor" class="form-label f-w-600 f-s-13">Asesor Responsable</label>
                            <select class="form-select" id="opClienteAsesor" name="usuario_asignado_id">
                                <option value="">(Sin Asignar)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="opClienteSeguimiento" class="form-label f-w-600 f-s-13">Próximo Seguimiento</label>
                            <input type="text" class="form-control candelaria-flatpickr bg-white" id="opClienteSeguimiento" name="proximo_seguimiento_en" placeholder="AAAA-MM-DD">
                        </div>
                        <div class="col-12">
                            <label for="opClienteNotas" class="form-label f-w-600 f-s-13">Notas Comerciales Iniciales</label>
                            <textarea class="form-control" id="opClienteNotas" name="notas" rows="2" placeholder="Requerimientos iniciales del prospecto..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarOpCliente">
                        <i class="fa-solid fa-save me-1"></i> Abrir Oportunidad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
