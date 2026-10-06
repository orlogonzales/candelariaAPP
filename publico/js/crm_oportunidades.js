/**
 * ==============================================================================
 * CANDELARIAAPP - GESTIÓN ASÍNCRONA DE OPORTUNIDADES Y PIPELINE (crm_oportunidades.js)
 * ==============================================================================
 * - Pipeline comercial contextualizado por edición (header X-Edicion-Id y filtro)
 * - Máquina de estados gobernada (NUEVA -> CONTACTADO -> PROPUESTA -> NEGOCIACION -> GANADA / PERDIDA)
 * - Motivos obligatorios en etapa PERDIDA y detalle obligatorio en OTRO
 * - Concurrencia optimista (409) vía version_bloqueo con aviso amigable y recarga reactiva
 * - Cero recargas de página completas
 * ==============================================================================
 */

'use strict';

class ModuloCrmOportunidades {
    constructor() {
        this.instanciaDataTable = null;
        this.oportunidadesCache = [];
        this.modalCrear = null;
        this.modalEtapa = null;
        this.modalEditar = null;
        this.modalAsignar = null;
        this.instanciasFlatpickr = {};

        this.init();
    }

    async init() {
        this.inicializarPickers();
        this.inicializarModales();
        this.vincularEventosUI();
        this.inicializarSelect2Clientes();

        // Sincronizar filtro de edición con sessionStorage si existe
        const idEnPestana = sessionStorage.getItem('candelaria_edicion_trabajo_id');
        const filtroEd = document.getElementById('filtroEdicion');
        if (idEnPestana && filtroEd) {
            const existeOpcion = Array.from(filtroEd.options).some(opt => opt.value === idEnPestana);
            if (existeOpcion) {
                filtroEd.value = idEnPestana;
            }
        }

        await this.cargarOportunidades();
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

            ['crearSeguimientoEn', 'editarOpSeguimiento'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    this.instanciasFlatpickr[id] = flatpickr(el, configs);
                }
            });
        }
    }

    inicializarModales() {
        if (!window.bootstrap?.Modal) return;

        ['modalCrearOportunidad', 'modalCambiarEtapa', 'modalEditarOportunidad', 'modalAsignarResponsable'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                const nombreProp = id.replace('modal', 'modal');
                this[nombreProp] = new window.bootstrap.Modal(el);
            }
        });
    }

    vincularEventosUI() {
        // Recargar
        document.getElementById('btnRecargarTabla')?.addEventListener('click', () => this.cargarOportunidades());

        // Abrir modal crear
        document.getElementById('btnAbrirModalCrear')?.addEventListener('click', () => {
            document.getElementById('formCrearOportunidad')?.reset();
            const idEnPestana = sessionStorage.getItem('candelaria_edicion_trabajo_id');
            const selectEd = document.getElementById('crearEdicionId');
            if (idEnPestana && selectEd) {
                selectEd.value = idEnPestana;
            }
            if (window.jQuery && window.jQuery('#crearClienteId').length) {
                window.jQuery('#crearClienteId').val(null).trigger('change');
            }
            this.modalCrearOportunidad?.show();
        });

        // Filtros
        ['filtroEdicion', 'filtroEtapa', 'filtroAsesor', 'filtroOrigen'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => this.cargarOportunidades());
        });

        let debounceTimer = null;
        document.getElementById('filtroBusqueda')?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => this.cargarOportunidades(), 350);
        });

        // Toggle condicional en modal cambiar etapa (PERDIDA)
        document.getElementById('etapaNuevaSelect')?.addEventListener('change', (e) => {
            const esPerdida = e.target.value === 'PERDIDA';
            const panel = document.getElementById('seccionMotivoPerdida');
            panel?.classList.toggle('d-none', !esPerdida);
            if (!esPerdida) {
                document.getElementById('etapaMotivoPerdida').value = '';
                document.getElementById('etapaMotivoPerdidaDetalle').value = '';
                document.getElementById('seccionMotivoPerdidaDetalle')?.classList.add('d-none');
            }
        });

        document.getElementById('etapaMotivoPerdida')?.addEventListener('change', (e) => {
            const esOtro = e.target.value === 'OTRO';
            document.getElementById('seccionMotivoPerdidaDetalle')?.classList.toggle('d-none', !esOtro);
        });

        // Submits
        document.getElementById('formCrearOportunidad')?.addEventListener('submit', (e) => this.guardarCrear(e));
        document.getElementById('formCambiarEtapa')?.addEventListener('submit', (e) => this.guardarEtapa(e));
        document.getElementById('formEditarOportunidad')?.addEventListener('submit', (e) => this.guardarEditar(e));
        document.getElementById('formAsignarResponsable')?.addEventListener('submit', (e) => this.guardarAsignar(e));

        // Escuchar evento global de cambio de edición en pestaña
        document.addEventListener('candelaria:edicion-cambiada', (e) => {
            const edId = e.detail?.id;
            const filtroEd = document.getElementById('filtroEdicion');
            if (filtroEd && edId) {
                filtroEd.value = edId;
                this.cargarOportunidades();
            }
        });
    }

    inicializarSelect2Clientes() {
        if (!window.jQuery || !window.jQuery.fn.select2) return;

        const selectEl = window.jQuery('#crearClienteId');
        if (!selectEl.length) return;

        selectEl.select2({
            dropdownParent: window.jQuery('#modalCrearOportunidad'),
            placeholder: 'Buscar cliente por nombre o documento...',
            minimumInputLength: 2,
            ajax: {
                url: `${window.CANDELARIA_BASE_URL}/api/v1/clientes`,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { busqueda: params.term, limite: 25 };
                },
                processResults: function (data) {
                    const clientes = data?.datos?.clientes || [];
                    return {
                        results: clientes.map(c => ({
                            id: c.id,
                            text: `${c.nombre_completo} (${c.numero_documento || 'Sin doc'} - ${c.estado_comercial})`
                        }))
                    };
                },
                cache: true
            }
        });
    }

    async cargarOportunidades() {
        const contenedor = document.getElementById('contenedorTablaOportunidades');
        if (!contenedor) return;

        try {
            Skeleton.show(contenedor, 'table', { filas: 5, columnas: 8 });

            const edicionId = document.getElementById('filtroEdicion')?.value || '';
            const etapa = document.getElementById('filtroEtapa')?.value || '';
            const asesorId = document.getElementById('filtroAsesor')?.value || '';
            const origenId = document.getElementById('filtroOrigen')?.value || '';
            const busqueda = document.getElementById('filtroBusqueda')?.value || '';

            let query = 'crm/oportunidades?limite=100';
            if (edicionId) query += `&edicion_id=${encodeURIComponent(edicionId)}`;
            if (etapa) query += `&etapa=${encodeURIComponent(etapa)}`;
            if (asesorId) query += `&usuario_asignado_id=${encodeURIComponent(asesorId)}`;
            if (origenId) query += `&origen_comercial_id=${encodeURIComponent(origenId)}`;
            if (busqueda) query += `&busqueda=${encodeURIComponent(busqueda)}`;

            const resp = await window.CandelariaApi.get(query);
            const oportunidades = resp?.datos?.oportunidades || [];
            this.oportunidadesCache = oportunidades;

            this.actualizarKpis(oportunidades);

            if (this.instanciaDataTable) {
                this.instanciaDataTable.destroy();
                this.instanciaDataTable = null;
            }

            Skeleton.hide(contenedor);
            this.renderizarTabla(contenedor, oportunidades);

            if (window.jQuery && window.jQuery.fn.DataTable) {
                this.instanciaDataTable = window.jQuery('#tablaOportunidades').DataTable({
                    language: {
                        search: "Buscar en tabla:",
                        lengthMenu: "Mostrar _MENU_ registros",
                        info: "Mostrando _START_ a _END_ de _TOTAL_ oportunidades",
                        infoEmpty: "Mostrando 0 oportunidades",
                        infoFiltered: "(filtrado de _MAX_ totales)",
                        zeroRecords: "No se encontraron oportunidades registradas",
                        paginate: {
                            first: "Primero",
                            previous: "Anterior",
                            next: "Siguiente",
                            last: "Último"
                        }
                    },
                    order: [[0, 'desc']], // ID descendente
                    pageLength: 10,
                    responsive: true,
                    dom: '<"d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"lf>rt<"d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2"ip>'
                });
            }

            this.vincularAccionesFilas();

        } catch (err) {
            console.error('[ModuloCrmOportunidades] Error al cargar oportunidades:', err);
            Skeleton.error(contenedor, 'No fue posible cargar las oportunidades: ' + (err.message || 'Error del servidor'), {
                texto: 'Reintentar',
                accion: () => this.cargarOportunidades()
            });
        }
    }

    actualizarKpis(oportunidades) {
        const total = oportunidades.length;
        const abiertas = oportunidades.filter(o => !['GANADA', 'PERDIDA'].includes(o.etapa)).length;
        const ganadas = oportunidades.filter(o => o.etapa === 'GANADA').length;
        const perdidas = oportunidades.filter(o => o.etapa === 'PERDIDA').length;

        const elTotal = document.getElementById('kpiTotalOportunidades');
        const elAbiertas = document.getElementById('kpiPipelineAbierto');
        const elGanadas = document.getElementById('kpiGanadas');
        const elPerdidas = document.getElementById('kpiPerdidas');

        if (elTotal) elTotal.innerText = total.toString();
        if (elAbiertas) elAbiertas.innerText = abiertas.toString();
        if (elGanadas) elGanadas.innerText = ganadas.toString();
        if (elPerdidas) elPerdidas.innerText = perdidas.toString();
    }

    renderizarTabla(contenedor, oportunidades) {
        let filasHtml = '';

        if (oportunidades.length === 0) {
            filasHtml = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-bullseye f-s-32 d-block mb-2 text-secondary"></i>
                        No se encontraron oportunidades que coincidan con los filtros aplicados.
                    </td>
                </tr>
            `;
        } else {
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
                const asesorTexto = op.usuario_asignado_nombre || '<span class="text-muted f-s-12">(Sin asesor)</span>';
                const seguimientoTexto = op.proximo_seguimiento_en || '-';
                const urlFichaCliente = `${window.CANDELARIA_BASE_URL}/clientes/${op.cliente_id}`;

                filasHtml += `
                    <tr>
                        <td class="f-w-600 text-muted">#${op.id}</td>
                        <td>
                            <strong class="text-dark d-block">${op.titulo}</strong>
                            <span class="f-s-11 text-muted">${op.origen_comercial_nombre || 'Sin origen'}</span>
                        </td>
                        <td>
                            <a href="${urlFichaCliente}" class="text-primary text-decoration-none f-w-600" title="Ver Ficha 360">
                                <i class="fa-solid fa-user me-1"></i> ${op.cliente_nombre_completo}
                            </a>
                            <div class="f-s-11 text-muted">${op.cliente_numero_documento || 'Sin doc'}</div>
                        </td>
                        <td><span class="badge bg-light text-dark border">${op.edicion_nombre}</span></td>
                        <td><span class="badge bg-${etapaClase} px-2 py-1">${op.etapa}</span></td>
                        <td class="f-w-700">${valorTexto}</td>
                        <td>${asesorTexto}</td>
                        <td class="f-s-12">${seguimientoTexto}</td>
                        <td>
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-warning btn-cambiar-etapa" data-id="${op.id}" title="Avanzar / Cerrar Etapa">
                                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-editar-op" data-id="${op.id}" title="Editar Negocio">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info btn-asignar-op" data-id="${op.id}" title="Asignar Responsable">
                                    <i class="fa-solid fa-user-tag"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
        }

        contenedor.innerHTML = `
            <table class="table table-hover align-middle w-100" id="tablaOportunidades">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 45px;">ID</th>
                        <th>Negocio / Título</th>
                        <th>Cliente / Titular</th>
                        <th>Edición</th>
                        <th>Etapa</th>
                        <th>Valor Estimado</th>
                        <th>Asesor</th>
                        <th>Seguimiento</th>
                        <th style="width: 110px;" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>${filasHtml}</tbody>
            </table>
        `;
    }

    vincularAccionesFilas() {
        // Cambiar Etapa
        document.querySelectorAll('.btn-cambiar-etapa').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                const op = this.oportunidadesCache.find(o => parseInt(o.id, 10) === id);
                if (!op) return;

                document.getElementById('etapaOportunidadId').value = op.id;
                document.getElementById('etapaVersionBloqueo').value = op.version_bloqueo;
                document.getElementById('etapaTituloOportunidad').textContent = `#${op.id} - ${op.titulo}`;
                document.getElementById('etapaBadgeActual').textContent = `Etapa Actual: ${op.etapa}`;
                document.getElementById('etapaNuevaSelect').value = '';
                document.getElementById('seccionMotivoPerdida')?.classList.add('d-none');
                document.getElementById('etapaMotivoPerdida').value = '';
                document.getElementById('etapaMotivoPerdidaDetalle').value = '';
                document.getElementById('etapaMotivoCambio').value = '';

                this.modalCambiarEtapa?.show();
            });
        });

        // Editar Oportunidad
        document.querySelectorAll('.btn-editar-op').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                const op = this.oportunidadesCache.find(o => parseInt(o.id, 10) === id);
                if (!op) return;

                document.getElementById('editarOpId').value = op.id;
                document.getElementById('editarOpVersionBloqueo').value = op.version_bloqueo;
                document.getElementById('editarOpClienteDisplay').value = op.cliente_nombre_completo;
                document.getElementById('editarOpEdicionDisplay').value = op.edicion_nombre;
                document.getElementById('editarOpTitulo').value = op.titulo;
                document.getElementById('editarOpValor').value = op.valor_estimado || '';
                document.getElementById('editarOpOrigenId').value = op.origen_comercial_id || '';
                document.getElementById('editarOpSeguimiento').value = op.proximo_seguimiento_en || '';
                document.getElementById('editarOpNotas').value = op.notas || '';

                this.modalEditarOportunidad?.show();
            });
        });

        // Asignar Responsable
        document.querySelectorAll('.btn-asignar-op').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                const op = this.oportunidadesCache.find(o => parseInt(o.id, 10) === id);
                if (!op) return;

                document.getElementById('asignarOpId').value = op.id;
                document.getElementById('asignarOpVersionBloqueo').value = op.version_bloqueo;
                document.getElementById('asignarOpTitulo').textContent = `#${op.id} - ${op.titulo}`;
                document.getElementById('asignarAsesorSelect').value = op.usuario_asignado_id || '';

                this.modalAsignarResponsable?.show();
            });
        });
    }

    async guardarCrear(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarCrear');

        const clienteId = document.getElementById('crearClienteId')?.value;
        const edicionId = document.getElementById('crearEdicionId')?.value;
        const titulo = document.getElementById('crearTitulo')?.value?.trim();

        if (!clienteId || !edicionId || !titulo) {
            CandelariaUI.notificarError('Cliente, Edición y Título son campos obligatorios.', 'Datos Requeridos');
            return;
        }

        const payload = {
            cliente_id: parseInt(clienteId, 10),
            edicion_id: parseInt(edicionId, 10),
            titulo: titulo,
            usuario_asignado_id: document.getElementById('crearAsesorId')?.value 
                ? parseInt(document.getElementById('crearAsesorId').value, 10) 
                : null,
            origen_comercial_id: document.getElementById('crearOrigenId')?.value 
                ? parseInt(document.getElementById('crearOrigenId').value, 10) 
                : null,
            valor_estimado: document.getElementById('crearValorEstimado')?.value 
                ? parseFloat(document.getElementById('crearValorEstimado').value) 
                : null,
            proximo_seguimiento_en: document.getElementById('crearSeguimientoEn')?.value || null,
            notas: document.getElementById('crearNotas')?.value?.trim() || null
        };

        try {
            CandelariaUI.procesarBoton(btn, 'Creando...');
            const resp = await window.CandelariaApi.post('crm/oportunidades', payload);

            CandelariaUI.notificarExito(resp?.mensaje || 'Oportunidad creada exitosamente.', 'Oportunidad Abierta');
            this.modalCrearOportunidad?.hide();
            await this.cargarOportunidades();
        } catch (err) {
            console.error('[ModuloCrmOportunidades] Error al crear oportunidad:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible registrar la oportunidad.', 'Error');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    async guardarEtapa(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarEtapa');

        const id = document.getElementById('etapaOportunidadId')?.value;
        const versionBloqueo = parseInt(document.getElementById('etapaVersionBloqueo')?.value || '1', 10);
        const nuevaEtapa = document.getElementById('etapaNuevaSelect')?.value;

        if (!id || !nuevaEtapa) {
            CandelariaUI.notificarError('Debe seleccionar la nueva etapa.', 'Dato Requerido');
            return;
        }

        const payload = {
            etapa: nuevaEtapa,
            version_bloqueo: versionBloqueo,
            motivo_cambio: document.getElementById('etapaMotivoCambio')?.value?.trim() || null,
            motivo_perdida: nuevaEtapa === 'PERDIDA' ? document.getElementById('etapaMotivoPerdida')?.value : null,
            motivo_perdida_detalle: nuevaEtapa === 'PERDIDA' ? document.getElementById('etapaMotivoPerdidaDetalle')?.value?.trim() : null
        };

        if (nuevaEtapa === 'PERDIDA') {
            if (!payload.motivo_perdida) {
                CandelariaUI.notificarError('Debe especificar el motivo de pérdida.', 'Motivo Requerido');
                return;
            }
            if (payload.motivo_perdida === 'OTRO' && !payload.motivo_perdida_detalle) {
                CandelariaUI.notificarError('El motivo OTRO exige ingresar un detalle explicativo.', 'Detalle Requerido');
                return;
            }
        }

        try {
            CandelariaUI.procesarBoton(btn, 'Actualizando...');
            const resp = await window.CandelariaApi.post(`crm/oportunidades/${id}/etapa`, payload);

            CandelariaUI.notificarExito(resp?.mensaje || 'Etapa actualizada exitosamente.', 'Etapa Actualizada');
            this.modalCambiarEtapa?.hide();
            await this.cargarOportunidades();
        } catch (err) {
            console.error('[ModuloCrmOportunidades] Error al cambiar etapa:', err);
            if (err.status === 409) {
                CandelariaUI.notificarError(
                    'Esta oportunidad fue modificada concurrentemente por otro usuario. Se recargarán los datos actualizados.',
                    'Conflicto de Concurrencia (409)'
                );
                this.modalCambiarEtapa?.hide();
                await this.cargarOportunidades();
            } else {
                CandelariaUI.notificarError(err.message || 'No fue posible actualizar la etapa.', 'Error');
            }
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    async guardarEditar(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarEditarOp');

        const id = document.getElementById('editarOpId')?.value;
        const versionBloqueo = parseInt(document.getElementById('editarOpVersionBloqueo')?.value || '1', 10);
        const titulo = document.getElementById('editarOpTitulo')?.value?.trim();

        if (!id || !titulo) {
            CandelariaUI.notificarError('El título es obligatorio.', 'Dato Requerido');
            return;
        }

        const payload = {
            titulo: titulo,
            version_bloqueo: versionBloqueo,
            origen_comercial_id: document.getElementById('editarOpOrigenId')?.value 
                ? parseInt(document.getElementById('editarOpOrigenId').value, 10) 
                : null,
            valor_estimado: document.getElementById('editarOpValor')?.value 
                ? parseFloat(document.getElementById('editarOpValor').value) 
                : null,
            proximo_seguimiento_en: document.getElementById('editarOpSeguimiento')?.value || null,
            notas: document.getElementById('editarOpNotas')?.value?.trim() || null
        };

        try {
            CandelariaUI.procesarBoton(btn, 'Guardando...');
            const resp = await window.CandelariaApi.put(`crm/oportunidades/${id}`, payload);

            CandelariaUI.notificarExito(resp?.mensaje || 'Oportunidad actualizada exitosamente.', 'Actualizado');
            this.modalEditarOportunidad?.hide();
            await this.cargarOportunidades();
        } catch (err) {
            console.error('[ModuloCrmOportunidades] Error al editar oportunidad:', err);
            if (err.status === 409) {
                CandelariaUI.notificarError(
                    'Esta oportunidad fue modificada concurrentemente por otro usuario. Se recargarán los datos actualizados.',
                    'Conflicto de Concurrencia (409)'
                );
                this.modalEditarOportunidad?.hide();
                await this.cargarOportunidades();
            } else {
                CandelariaUI.notificarError(err.message || 'No fue posible actualizar la oportunidad.', 'Error');
            }
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    async guardarAsignar(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarAsignar');

        const id = document.getElementById('asignarOpId')?.value;
        const versionBloqueo = parseInt(document.getElementById('asignarOpVersionBloqueo')?.value || '1', 10);
        const asesorId = document.getElementById('asignarAsesorSelect')?.value;

        if (!id) return;

        const payload = {
            usuario_asignado_id: asesorId ? parseInt(asesorId, 10) : null,
            version_bloqueo: versionBloqueo
        };

        try {
            CandelariaUI.procesarBoton(btn, 'Asignando...');
            const resp = await window.CandelariaApi.post(`crm/oportunidades/${id}/asignar`, payload);

            CandelariaUI.notificarExito(resp?.mensaje || 'Responsable asignado exitosamente.', 'Asignación Confirmada');
            this.modalAsignarResponsable?.hide();
            await this.cargarOportunidades();
        } catch (err) {
            console.error('[ModuloCrmOportunidades] Error al asignar responsable:', err);
            if (err.status === 409) {
                CandelariaUI.notificarError(
                    'Esta oportunidad fue modificada concurrentemente por otro usuario. Se recargarán los datos actualizados.',
                    'Conflicto de Concurrencia (409)'
                );
                this.modalAsignarResponsable?.hide();
                await this.cargarOportunidades();
            } else {
                CandelariaUI.notificarError(err.message || 'No fue posible asignar el responsable.', 'Error');
            }
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.moduloCrmOportunidades = new ModuloCrmOportunidades();
});
