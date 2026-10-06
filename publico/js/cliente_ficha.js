/**
 * ==============================================================================
 * CANDELARIAAPP - FICHA 360 CLIENTE (cliente_ficha.js)
 * ==============================================================================
 * - Tabs Alina nativos: Resumen 360, Oportunidades, Timeline e Interacciones, Consentimientos.
 * - Timeline Alina comercial asíncrono unificado (interacciones + cambios de etapa).
 * - Registro append-only de interacciones con actor y trazabilidad inmutable.
 * - Apertura de oportunidades vinculadas directamente al cliente.
 * - Gobernanza de consentimientos con evidencia auditable.
 * ==============================================================================
 */

'use strict';

class ModuloClienteFicha {
    constructor() {
        this.clienteId = parseInt(document.getElementById('fichaClienteId')?.value || '0', 10);
        this.modalInteraccion = null;
        this.modalOportunidad = null;
        this.instanciasFlatpickr = {};

        this.init();
    }

    async init() {
        if (!this.clienteId) return;

        this.inicializarPickers();
        this.inicializarModales();
        this.vincularEventosUI();

        await Promise.all([
            this.cargarOportunidades(),
            this.cargarTimeline(),
            this.cargarConsentimientosHistorial(),
            this.cargarCatalogosModales()
        ]);
    }

    inicializarPickers() {
        if (typeof flatpickr !== 'undefined') {
            const configs = {
                dateFormat: 'Y-m-d',
                allowInput: true,
                locale: {
                    firstDayOfWeek: 1,
                    weekdays: {
                        shorthand: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                        longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                    },
                    months: {
                        shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                        longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                    },
                }
            };

            const el = document.getElementById('opClienteSeguimiento');
            if (el) {
                this.instanciasFlatpickr['opClienteSeguimiento'] = flatpickr(el, configs);
            }
        }
    }

    inicializarModales() {
        const elInt = document.getElementById('modalRegistrarInteraccion');
        if (elInt && window.bootstrap?.Modal) {
            this.modalInteraccion = new window.bootstrap.Modal(elInt);
        }

        const elOp = document.getElementById('modalCrearOportunidadCliente');
        if (elOp && window.bootstrap?.Modal) {
            this.modalOportunidad = new window.bootstrap.Modal(elOp);
        }
    }

    vincularEventosUI() {
        // Botones para abrir modal interacción
        document.getElementById('btnModalRegistrarInteraccion')?.addEventListener('click', () => {
            document.getElementById('formRegistrarInteraccion')?.reset();
            this.modalInteraccion?.show();
        });
        document.getElementById('btnNuevaInteraccionTab')?.addEventListener('click', () => {
            document.getElementById('formRegistrarInteraccion')?.reset();
            this.modalInteraccion?.show();
        });

        // Botones para abrir modal oportunidad
        document.getElementById('btnModalNuevaOportunidad')?.addEventListener('click', () => {
            document.getElementById('formCrearOportunidadCliente')?.reset();
            this.modalOportunidad?.show();
        });
        document.getElementById('btnNuevaOportunidadTab')?.addEventListener('click', () => {
            document.getElementById('formCrearOportunidadCliente')?.reset();
            this.modalOportunidad?.show();
        });

        // Submits
        document.getElementById('formRegistrarInteraccion')?.addEventListener('submit', (e) => this.guardarInteraccion(e));
        document.getElementById('formCrearOportunidadCliente')?.addEventListener('submit', (e) => this.guardarOportunidad(e));
        document.getElementById('formRegistrarConsentimiento')?.addEventListener('submit', (e) => this.guardarConsentimiento(e));
    }

    async cargarOportunidades() {
        const contenedor = document.getElementById('contenedorOportunidadesCliente');
        if (!contenedor) return;

        try {
            Skeleton.show(contenedor, 'table', { filas: 3, columnas: 6 });

            const resp = await window.CandelariaApi.get(`crm/oportunidades?busqueda=&limite=100`);
            const todas = resp?.datos?.oportunidades || [];
            const oportunidades = todas.filter(op => parseInt(op.cliente_id, 10) === this.clienteId);

            Skeleton.hide(contenedor);

            // Rellenar selector de oportunidad en el modal de interacciones
            const selectOpInt = document.getElementById('interaccionOportunidadId');
            if (selectOpInt) {
                selectOpInt.innerHTML = '<option value="">(Interacción General del Cliente)</option>';
                oportunidades.forEach(op => {
                    selectOpInt.innerHTML += `<option value="${op.id}">#${op.id} - ${op.titulo} (${op.etapa})</option>`;
                });
            }

            if (oportunidades.length === 0) {
                contenedor.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-bullseye f-s-32 d-block mb-2 text-secondary"></i>
                        Este cliente no tiene oportunidades comerciales registradas todavía.
                    </div>
                `;
                return;
            }

            let filasHtml = '';
            oportunidades.forEach(op => {
                const etapaClase = {
                    'NUEVA': 'primary',
                    'CONTACTADO': 'info',
                    'PROPUESTA': 'warning text-dark',
                    'NEGOCIACION': 'warning text-dark',
                    'GANADA': 'success',
                    'PERDIDA': 'danger'
                }[op.etapa] || 'secondary';

                const valorTexto = op.valor_estimado ? `${op.moneda} ${parseFloat(op.valor_estimado).toFixed(2)}` : '-';
                const asesorTexto = op.usuario_asignado_nombre || '<span class="text-muted">(Sin asesor)</span>';
                const seguimientoTexto = op.proximo_seguimiento_en || '-';

                filasHtml += `
                    <tr>
                        <td class="f-w-600">#${op.id}</td>
                        <td class="f-w-700">${op.titulo}</td>
                        <td>${op.edicion_nombre || 'Edición'}</td>
                        <td><span class="badge bg-${etapaClase}">${op.etapa}</span></td>
                        <td><strong>${valorTexto}</strong></td>
                        <td>${asesorTexto}</td>
                        <td>${seguimientoTexto}</td>
                    </tr>
                `;
            });

            contenedor.innerHTML = `
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>ID</th>
                            <th>Negocio / Oportunidad</th>
                            <th>Edición</th>
                            <th>Etapa</th>
                            <th>Valor Estimado</th>
                            <th>Asesor</th>
                            <th>Seguimiento</th>
                        </tr>
                    </thead>
                    <tbody>${filasHtml}</tbody>
                </table>
            `;
        } catch (err) {
            console.error('[ModuloClienteFicha] Error al cargar oportunidades:', err);
            Skeleton.error(contenedor, 'Error al cargar las oportunidades comerciales: ' + (err.message || ''));
        }
    }

    async cargarTimeline() {
        const contenedor = document.getElementById('contenedorTimelineCliente');
        if (!contenedor) return;

        try {
            Skeleton.show(contenedor, 'list', { filas: 4 });

            const resp = await window.CandelariaApi.get(`crm/clientes/${this.clienteId}/timeline`);
            const eventos = resp?.datos?.eventos || [];

            Skeleton.hide(contenedor);

            if (eventos.length === 0) {
                contenedor.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-clock-rotate-left f-s-32 d-block mb-2 text-secondary"></i>
                        No hay interacciones o transiciones comerciales registradas para este cliente.
                    </div>
                `;
                return;
            }

            let timelineHtml = '<div class="timeline">';
            eventos.forEach(ev => {
                timelineHtml += `
                    <div class="timeline-item d-flex gap-3 mb-4">
                        <div class="timeline-icon-box flex-shrink-0">
                            <span class="w-35 h-35 d-flex-center rounded-circle bg-${ev.color_clase}-subtle text-${ev.color_clase} f-s-16 shadow-sm">
                                <i class="fa-solid ${ev.icono}"></i>
                            </span>
                        </div>
                        <div class="timeline-content p-3 border b-r-10 flex-grow-1 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                                <h6 class="mb-0 f-w-700 text-dark">${ev.titulo}</h6>
                                <span class="badge bg-light text-muted border f-s-11">
                                    <i class="fa-regular fa-clock me-1"></i> ${ev.fecha}
                                </span>
                            </div>
                            <div class="f-s-12 text-muted mb-2">
                                <span class="badge bg-light text-dark border me-2">${ev.subtitulo}</span>
                                <span>Por: <strong>${ev.autor}</strong></span>
                            </div>
                            ${ev.descripcion ? `<p class="mb-0 f-s-13 text-secondary border-start border-2 ps-2 ms-1">${ev.descripcion}</p>` : ''}
                        </div>
                    </div>
                `;
            });
            timelineHtml += '</div>';

            contenedor.innerHTML = timelineHtml;
        } catch (err) {
            console.error('[ModuloClienteFicha] Error al cargar timeline:', err);
            Skeleton.error(contenedor, 'Error al cargar el timeline de interacciones: ' + (err.message || ''));
        }
    }

    async cargarConsentimientosHistorial() {
        const tbody = document.getElementById('tbodyHistorialConsentimientos');
        if (!tbody) return;

        try {
            const resp = await window.CandelariaApi.get(`clientes/${this.clienteId}`);
            const hist = resp?.datos?.consentimientos_historial || [];

            if (hist.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Sin historial de consentimientos registrado.</td></tr>';
                return;
            }

            let filasHtml = '';
            hist.forEach(c => {
                const badge = c.otorgado 
                    ? '<span class="badge bg-success">OTORGADO</span>' 
                    : '<span class="badge bg-danger">REVOCADO</span>';

                filasHtml += `
                    <tr>
                        <td>${c.creado_en || '-'}</td>
                        <td><code>${c.tipo_consentimiento}</code></td>
                        <td>${badge}</td>
                        <td>${c.medio_recepcion || '-'}</td>
                        <td class="text-truncate" style="max-width: 180px;">${c.evidencia_referencia || '-'}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = filasHtml;
        } catch (err) {
            console.error('[ModuloClienteFicha] Error al cargar historial de consentimientos:', err);
        }
    }

    async cargarCatalogosModales() {
        try {
            // Ediciones
            const respEd = await window.CandelariaApi.get('ediciones');
            const ediciones = respEd?.datos || [];
            const selectEd = document.getElementById('opClienteEdicionId');
            if (selectEd) {
                selectEd.innerHTML = '<option value="">Seleccione Edición...</option>';
                ediciones.forEach(ed => {
                    selectEd.innerHTML += `<option value="${ed.id}" ${ed.es_actual ? 'selected' : ''}>${ed.nombre} (${ed.anio}) ${ed.es_actual ? '★' : ''}</option>`;
                });
            }

            // Orígenes
            const respOrig = await window.CandelariaApi.get('crm/origenes?solo_activos=1');
            const origenes = respOrig?.datos?.origenes || [];
            const selectOrig = document.getElementById('opClienteOrigenId');
            if (selectOrig) {
                selectOrig.innerHTML = '<option value="">(Sin Origen Específico)</option>';
                origenes.forEach(o => {
                    selectOrig.innerHTML += `<option value="${o.id}">${o.nombre}</option>`;
                });
            }

            // Asesores
            const respUsr = await window.CandelariaApi.get('usuarios');
            const usuarios = respUsr?.datos?.usuarios || [];
            const selectAsesor = document.getElementById('opClienteAsesor');
            if (selectAsesor) {
                selectAsesor.innerHTML = '<option value="">(Sin Asignar)</option>';
                usuarios.forEach(u => {
                    if (u.estado === 'ACTIVO') {
                        selectAsesor.innerHTML += `<option value="${u.id}">${u.nombre_completo}</option>`;
                    }
                });
            }
        } catch (err) {
            console.error('[ModuloClienteFicha] Error al cargar catálogos auxiliares:', err);
        }
    }

    async guardarInteraccion(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarInteraccion');

        const payload = {
            cliente_id: this.clienteId,
            canal_id: parseInt(document.getElementById('interaccionCanalId')?.value || '1', 10),
            oportunidad_id: document.getElementById('interaccionOportunidadId')?.value 
                ? parseInt(document.getElementById('interaccionOportunidadId').value, 10) 
                : null,
            tipo: document.getElementById('interaccionTipo')?.value,
            direccion: document.getElementById('interaccionDireccion')?.value,
            resumen: document.getElementById('interaccionResumen')?.value?.trim(),
            detalle: document.getElementById('interaccionDetalle')?.value?.trim() || null
        };

        if (!payload.resumen) {
            CandelariaUI.notificarError('El resumen de la conversación es obligatorio.', 'Campo Requerido');
            return;
        }

        try {
            CandelariaUI.procesarBoton(btn, 'Guardando...');
            const resp = await window.CandelariaApi.post('crm/interacciones', payload);

            CandelariaUI.notificarExito(resp?.mensaje || 'Interacción registrada con éxito.', 'Registrado');
            this.modalInteraccion?.hide();
            await this.cargarTimeline();
        } catch (err) {
            console.error('[ModuloClienteFicha] Error al registrar interacción:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible registrar la interacción.', 'Error');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    async guardarOportunidad(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarOpCliente');

        const edicionId = document.getElementById('opClienteEdicionId')?.value;
        const titulo = document.getElementById('opClienteTitulo')?.value?.trim();

        if (!edicionId || !titulo) {
            CandelariaUI.notificarError('La edición y el título son obligatorios.', 'Datos Requeridos');
            return;
        }

        const payload = {
            cliente_id: this.clienteId,
            edicion_id: parseInt(edicionId, 10),
            titulo: titulo,
            usuario_asignado_id: document.getElementById('opClienteAsesor')?.value 
                ? parseInt(document.getElementById('opClienteAsesor').value, 10) 
                : null,
            origen_comercial_id: document.getElementById('opClienteOrigenId')?.value 
                ? parseInt(document.getElementById('opClienteOrigenId').value, 10) 
                : null,
            valor_estimado: document.getElementById('opClienteValor')?.value 
                ? parseFloat(document.getElementById('opClienteValor').value) 
                : null,
            proximo_seguimiento_en: document.getElementById('opClienteSeguimiento')?.value || null,
            notas: document.getElementById('opClienteNotas')?.value?.trim() || null
        };

        try {
            CandelariaUI.procesarBoton(btn, 'Creando...');
            const resp = await window.CandelariaApi.post('crm/oportunidades', payload);

            CandelariaUI.notificarExito(resp?.mensaje || 'Oportunidad comercial abierta con éxito.', 'Oportunidad Registrada');
            this.modalOportunidad?.hide();
            await Promise.all([
                this.cargarOportunidades(),
                this.cargarTimeline()
            ]);
        } catch (err) {
            console.error('[ModuloClienteFicha] Error al crear oportunidad:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible registrar la oportunidad.', 'Error');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    async guardarConsentimiento(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarConsentimiento');

        const tipo = document.getElementById('consentimientoTipo')?.value;
        const otorgado = document.getElementById('consentimientoOtorgado')?.value === '1';
        const medio = document.getElementById('consentimientoMedio')?.value;
        const evidencia = document.getElementById('consentimientoEvidencia')?.value?.trim();

        if (!evidencia) {
            CandelariaUI.notificarError('Debe ingresar un detalle de evidencia del consentimiento.', 'Evidencia Requerida');
            return;
        }

        try {
            CandelariaUI.procesarBoton(btn, 'Registrando...');
            const resp = await window.CandelariaApi.post(`clientes/${this.clienteId}/consentimientos`, {
                tipo: tipo,
                otorgado: otorgado,
                medio: medio,
                evidencia: evidencia
            });

            CandelariaUI.notificarExito(resp?.mensaje || 'Consentimiento actualizado con trazabilidad.', 'Consentimiento Registrado');

            // Actualizar badges visuales
            if (tipo === 'OPERATIVO') {
                const bOp = document.getElementById('badgeConsentimientoOperativo');
                if (bOp) {
                    bOp.className = `badge bg-${otorgado ? 'success' : 'secondary'}`;
                    bOp.textContent = otorgado ? 'OTORGADO' : 'NO OTORGADO';
                }
            } else {
                const bPro = document.getElementById('badgeConsentimientoPromocional');
                if (bPro) {
                    bPro.className = `badge bg-${otorgado ? 'success' : 'secondary'}`;
                    bPro.textContent = otorgado ? 'OTORGADO' : 'NO OTORGADO';
                }
            }

            document.getElementById('consentimientoEvidencia').value = '';
            await this.cargarConsentimientosHistorial();
        } catch (err) {
            console.error('[ModuloClienteFicha] Error al registrar consentimiento:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible registrar el consentimiento.', 'Error');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.moduloClienteFicha = new ModuloClienteFicha();
});
