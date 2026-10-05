/**
 * ==============================================================================
 * CANDELARIAAPP - GESTIÓN ASÍNCRONA DE EDICIONES CANDELARIA (ediciones.js)
 * ==============================================================================
 * - CRUD 100% asíncrono con Fetch API, DataTables, Flatpickr y Modales Alina/Bootstrap 5
 * - Ciclo de vida y máquina de estados de edición:
 *     * Avance secuencial ordinario: PREOPERACION -> OPERACION -> POSTPRODUCCION_ENTREGA -> CERRADA
 *     * Retrocesos excepcionales gobernados con motivo obligatorio y auditoría
 *     * Estado terminal CERRADA: inmutable
 * - Concurrencia optimista (409) vía actualizado_en_esperado
 * - Contexto explícito por pestaña (sessionStorage) y sincronización con selector global
 * - Cero location.reload(), actualización reactiva parcial
 * ==============================================================================
 */

'use strict';

class ModuloEdiciones {
    constructor() {
        this.instanciaDataTable = null;
        this.edicionesCache = [];
        this.instanciasFlatpickr = {};

        this.init();
    }

    async init() {
        this.inicializarPickers();
        this.vincularEventosUI();
        await this.cargarEdiciones();
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

            ['crearFechaInicio', 'crearFechaFin', 'editarFechaInicio', 'editarFechaFin'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    this.instanciasFlatpickr[id] = flatpickr(el, configs);
                }
            });
        }
    }

    vincularEventosUI() {
        // Botón recargar
        const btnRecargar = document.getElementById('btnRecargarTabla');
        if (btnRecargar) {
            btnRecargar.addEventListener('click', () => this.cargarEdiciones());
        }

        // Botón abrir modal crear
        const btnNuevo = document.getElementById('btnAbrirModalCrear');
        if (btnNuevo) {
            btnNuevo.addEventListener('click', () => this.abrirModalCrear());
        }

        // Submit form crear
        const formCrear = document.getElementById('formCrearEdicion');
        if (formCrear) {
            formCrear.addEventListener('submit', (e) => this.guardarCrear(e));
        }

        // Submit form editar
        const formEditar = document.getElementById('formEditarEdicion');
        if (formEditar) {
            formEditar.addEventListener('submit', (e) => this.guardarEditar(e));
        }

        // Submit form cambiar estado
        const formEstado = document.getElementById('formCambiarEstado');
        if (formEstado) {
            formEstado.addEventListener('submit', (e) => this.ejecutarCambioEstado(e));
        }

        // Listener para cambio en el selector de nuevo estado (detección de retroceso)
        const selectEstado = document.getElementById('selectNuevoEstado');
        if (selectEstado) {
            selectEstado.addEventListener('change', (e) => this.evaluarMotivoRetroceso(e.target.value));
        }

        // Escuchar evento global de cambio de edición en pestaña
        document.addEventListener('candelaria:edicion-cambiada', (e) => {
            this.actualizarKpiPestana(e.detail?.nombre || 'Ninguna');
        });
    }

    async cargarEdiciones() {
        const contenedor = document.getElementById('contenedorTablaEdiciones');
        if (!contenedor) return;

        try {
            Skeleton.show(contenedor, 'table', { filas: 5, columnas: 7 });

            const resp = await window.CandelariaApi.get('ediciones');
            const ediciones = resp?.datos || [];
            this.edicionesCache = ediciones;

            this.actualizarKpis(ediciones);

            if (this.instanciaDataTable) {
                this.instanciaDataTable.destroy();
                this.instanciaDataTable = null;
            }

            Skeleton.hide(contenedor);
            this.renderizarTabla(contenedor, ediciones);

            if (window.jQuery && window.jQuery.fn.DataTable) {
                this.instanciaDataTable = window.jQuery('#tablaEdiciones').DataTable({
                    language: {
                        search: "Buscar edición:",
                        lengthMenu: "Mostrar _MENU_ ediciones",
                        info: "Mostrando _START_ a _END_ de _TOTAL_ ediciones",
                        infoEmpty: "Mostrando 0 ediciones",
                        infoFiltered: "(filtrado de _MAX_ totales)",
                        zeroRecords: "No se encontraron ediciones coincidentes",
                        paginate: {
                            first: "Primero",
                            previous: "Anterior",
                            next: "Siguiente",
                            last: "Último"
                        }
                    },
                    order: [[2, 'desc']], // Ordenar por Año descendente
                    pageLength: 10,
                    responsive: true,
                    dom: '<"d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"lf>rt<"d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2"ip>'
                });
            }

            this.vincularAccionesFilas();

        } catch (err) {
            console.error('[ModuloEdiciones] Error cargando catálogo de ediciones:', err);
            Skeleton.error(contenedor, 'No fue posible cargar las ediciones: ' + (err.message || 'Error del servidor'), {
                texto: 'Reintentar',
                accion: () => this.cargarEdiciones()
            });
        }
    }

    actualizarKpis(ediciones) {
        const total = ediciones.length;
        const actualInst = ediciones.find(e => e.es_actual === true || e.es_actual === 1);

        const elTotal = document.getElementById('kpiTotalEdiciones');
        const elActual = document.getElementById('kpiEdicionActual');

        if (elTotal) elTotal.innerText = total.toString();
        if (elActual) {
            elActual.innerText = actualInst ? actualInst.nombre : 'No definida';
            elActual.title = actualInst ? actualInst.nombre : 'No definida';
        }

        const idEnPestana = window.CandelariaContextoEdicion?.obtenerEdicionId();
        const edPestana = ediciones.find(e => parseInt(e.id, 10) === parseInt(idEnPestana, 10));
        this.actualizarKpiPestana(edPestana ? edPestana.nombre : (actualInst ? actualInst.nombre : 'Sin seleccionar'));
    }

    actualizarKpiPestana(nombre) {
        const elPestana = document.getElementById('kpiEdicionPestana');
        if (elPestana) {
            elPestana.innerText = nombre;
            elPestana.title = nombre;
        }
    }

    renderizarTabla(contenedor, ediciones) {
        let filasHtml = '';

        if (ediciones.length === 0) {
            filasHtml = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-calendar-xmark f-s-32 d-block mb-2 text-secondary"></i>
                        No se encontraron ediciones registradas para esta organización.
                    </td>
                </tr>
            `;
        } else {
            ediciones.forEach(ed => {
                const esActual = ed.es_actual === true || ed.es_actual === 1;
                const badgeActual = esActual 
                    ? '<span class="badge bg-success text-white shadow-sm px-2 py-1"><i class="fa-solid fa-star me-1"></i> ACTUAL</span>' 
                    : '<span class="text-muted f-s-12">-</span>';
                
                const badgeEstado = this.obtenerBadgeEstado(ed.estado, ed.estado_etiqueta);
                const esCerrada = ed.estado === 'CERRADA';

                filasHtml += `
                    <tr data-id="${ed.id}">
                        <td>
                            <code class="f-w-700 text-primary">${this.escapar(ed.codigo)}</code>
                        </td>
                        <td>
                            <div class="f-w-700 text-dark">${this.escapar(ed.nombre)}</div>
                            ${ed.descripcion ? `<div class="f-s-11 text-muted text-truncate" style="max-width: 280px;" title="${this.escapar(ed.descripcion)}">${this.escapar(ed.descripcion)}</div>` : ''}
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border f-s-12 f-w-600">${ed.anio}</span>
                        </td>
                        <td>
                            <span class="f-s-12"><i class="fa-regular fa-calendar-plus text-secondary me-1"></i> ${ed.fecha_inicio}</span>
                        </td>
                        <td>
                            <span class="f-s-12"><i class="fa-regular fa-calendar-check text-secondary me-1"></i> ${ed.fecha_fin}</span>
                        </td>
                        <td>
                            ${badgeEstado}
                        </td>
                        <td class="text-center">
                            ${badgeActual}
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                    <li>
                                        <button class="dropdown-item btn-accion-seleccionar-pestana" 
                                                data-id="${ed.id}" 
                                                data-nombre="${this.escapar(ed.nombre)}" 
                                                data-codigo="${this.escapar(ed.codigo)}">
                                            <i class="fa-solid fa-arrow-pointer text-info me-2"></i> Operar en esta pestaña
                                        </button>
                                    </li>
                                    ${!esActual ? `
                                    <li>
                                        <button class="dropdown-item btn-accion-seleccionar-actual" data-id="${ed.id}" data-nombre="${this.escapar(ed.nombre)}">
                                            <i class="fa-solid fa-star text-warning me-2"></i> Establecer como Actual
                                        </button>
                                    </li>
                                    ` : ''}
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li>
                                        <button class="dropdown-item btn-accion-editar ${esCerrada ? 'disabled text-muted' : ''}" data-id="${ed.id}" ${esCerrada ? 'disabled' : ''}>
                                            <i class="fa-solid fa-pen-to-square text-primary me-2"></i> Modificar Datos
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item btn-accion-estado ${esCerrada ? 'disabled text-muted' : ''}" data-id="${ed.id}" ${esCerrada ? 'disabled' : ''}>
                                            <i class="fa-solid fa-diagram-project text-dark me-2"></i> Cambiar Estado
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                `;
            });
        }

        contenedor.innerHTML = `
            <table class="display app-data-table default-data-table w-100 table-hover align-middle" id="tablaEdiciones">
                <thead>
                    <tr>
                        <th>Código Slug</th>
                        <th>Nombre de la Edición</th>
                        <th>Año</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Culminación</th>
                        <th>Estado de Ciclo</th>
                        <th class="text-center">Institucional</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${filasHtml}
                </tbody>
            </table>
        `;
    }

    obtenerBadgeEstado(estado, etiqueta) {
        const texto = etiqueta || estado;
        switch (estado) {
            case 'PREOPERACION':
                return `<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="fa-solid fa-hourglass-start me-1"></i> ${this.escapar(texto)}</span>`;
            case 'OPERACION':
                return `<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-play me-1"></i> ${this.escapar(texto)}</span>`;
            case 'POSTPRODUCCION_ENTREGA':
                return `<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="fa-solid fa-film me-1"></i> ${this.escapar(texto)}</span>`;
            case 'CERRADA':
                return `<span class="badge bg-secondary-subtle text-secondary border px-2 py-1"><i class="fa-solid fa-lock me-1"></i> ${this.escapar(texto)}</span>`;
            default:
                return `<span class="badge bg-light text-dark">${this.escapar(texto)}</span>`;
        }
    }

    vincularAccionesFilas() {
        document.querySelectorAll('.btn-accion-editar').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.dataset.id;
                this.abrirModalEditar(id);
            });
        });

        document.querySelectorAll('.btn-accion-estado').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.dataset.id;
                this.abrirModalCambiarEstado(id);
            });
        });

        document.querySelectorAll('.btn-accion-seleccionar-actual').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.dataset.id;
                const nombre = e.currentTarget.dataset.nombre;
                this.confirmarSeleccionarActual(id, nombre);
            });
        });

        document.querySelectorAll('.btn-accion-seleccionar-pestana').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                const nombre = e.currentTarget.dataset.nombre;
                const codigo = e.currentTarget.dataset.codigo;
                this.seleccionarEdicionPestana(id, nombre, codigo);
            });
        });
    }

    abrirModalCrear() {
        const modalEl = document.getElementById('modalCrearEdicion');
        if (!modalEl) return;

        const form = document.getElementById('formCrearEdicion');
        if (form) form.reset();

        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    }

    async guardarCrear(e) {
        e.preventDefault();
        const form = document.getElementById('formCrearEdicion');
        const btnGuardar = document.getElementById('btnGuardarCrear');

        const codigo = document.getElementById('crearCodigo').value.trim().toUpperCase();
        const anio = parseInt(document.getElementById('crearAnio').value, 10);
        const nombre = document.getElementById('crearNombre').value.trim();
        const fechaInicio = document.getElementById('crearFechaInicio').value.trim();
        const fechaFin = document.getElementById('crearFechaFin').value.trim();
        const descripcion = document.getElementById('crearDescripcion').value.trim();

        if (!codigo || !anio || !nombre || !fechaInicio || !fechaFin) {
            CandelariaUI.notificarError('Por favor complete todos los campos obligatorios (*).');
            return;
        }

        try {
            CandelariaUI.procesarBoton(btnGuardar, 'Creando edición...');

            const payload = {
                codigo,
                anio,
                nombre,
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                descripcion: descripcion || null
            };

            const resp = await window.CandelariaApi.post('ediciones', payload);

            bootstrap.Modal.getInstance(document.getElementById('modalCrearEdicion'))?.hide();
            CandelariaUI.notificarExito(resp.mensaje || 'Edición creada exitosamente.');

            await this.cargarEdiciones();
            window.CandelariaContextoEdicion?.sincronizarContextoInicial();

        } catch (err) {
            console.error('[ModuloEdiciones] Error al crear edición:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible registrar la edición.');
        } finally {
            CandelariaUI.restaurarBoton(btnGuardar);
        }
    }

    async abrirModalEditar(id) {
        const modalEl = document.getElementById('modalEditarEdicion');
        if (!modalEl) return;

        const skeleton = document.getElementById('skeletonEditarEdicion');
        const panelForm = document.getElementById('panelFormEditar');

        skeleton.classList.remove('d-none');
        panelForm.classList.add('d-none');

        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();

        try {
            const resp = await window.CandelariaApi.get(`ediciones/${id}`);
            const datos = resp?.datos;
            if (!datos) throw new Error('No se obtuvieron los datos de la edición.');

            document.getElementById('editarEdicionId').value = datos.id;
            document.getElementById('editarActualizadoEnEsperado').value = datos.actualizado_en || '';
            document.getElementById('editarCodigo').value = datos.codigo;
            document.getElementById('editarAnio').value = datos.anio;
            document.getElementById('editarNombre').value = datos.nombre;
            document.getElementById('editarDescripcion').value = datos.descripcion || '';

            if (this.instanciasFlatpickr['editarFechaInicio']) {
                this.instanciasFlatpickr['editarFechaInicio'].setDate(datos.fecha_inicio, true);
            } else {
                document.getElementById('editarFechaInicio').value = datos.fecha_inicio;
            }

            if (this.instanciasFlatpickr['editarFechaFin']) {
                this.instanciasFlatpickr['editarFechaFin'].setDate(datos.fecha_fin, true);
            } else {
                document.getElementById('editarFechaFin').value = datos.fecha_fin;
            }

            skeleton.classList.add('d-none');
            panelForm.classList.remove('d-none');

        } catch (err) {
            console.error('[ModuloEdiciones] Error cargando detalle de edición:', err);
            bsModal.hide();
            CandelariaUI.notificarError(err.message || 'No fue posible consultar la edición solicitada.');
        }
    }

    async guardarEditar(e) {
        e.preventDefault();
        const id = document.getElementById('editarEdicionId').value;
        const btnGuardar = document.getElementById('btnGuardarEditar');

        const nombre = document.getElementById('editarNombre').value.trim();
        const fechaInicio = document.getElementById('editarFechaInicio').value.trim();
        const fechaFin = document.getElementById('editarFechaFin').value.trim();
        const descripcion = document.getElementById('editarDescripcion').value.trim();
        const actualizadoEnEsperado = document.getElementById('editarActualizadoEnEsperado').value;

        if (!nombre || !fechaInicio || !fechaFin) {
            CandelariaUI.notificarError('Por favor complete todos los campos requeridos (*).');
            return;
        }

        try {
            CandelariaUI.procesarBoton(btnGuardar, 'Guardando cambios...');

            const payload = {
                nombre,
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                descripcion: descripcion || null,
                actualizado_en_esperado: actualizadoEnEsperado
            };

            const resp = await window.CandelariaApi.put(`ediciones/${id}`, payload);

            bootstrap.Modal.getInstance(document.getElementById('modalEditarEdicion'))?.hide();
            CandelariaUI.notificarExito(resp.mensaje || 'Edición actualizada correctamente.');

            await this.cargarEdiciones();
            window.CandelariaContextoEdicion?.sincronizarContextoInicial();

        } catch (err) {
            console.error('[ModuloEdiciones] Error al actualizar edición:', err);
            if (err.status === 409) {
                CandelariaUI.notificarError(
                    'Conflicto de concurrencia: Otro operador ha modificado esta edición mientras editabas. Se recargarán los datos actualizados.',
                    'Edición Modificada Simultáneamente'
                );
                await this.cargarEdiciones();
                bootstrap.Modal.getInstance(document.getElementById('modalEditarEdicion'))?.hide();
            } else {
                CandelariaUI.notificarError(err.message || 'Error al guardar modificaciones.');
            }
        } finally {
            CandelariaUI.restaurarBoton(btnGuardar);
        }
    }

    abrirModalCambiarEstado(id) {
        const ed = this.edicionesCache.find(e => parseInt(e.id, 10) === parseInt(id, 10));
        if (!ed) return;

        if (ed.estado === 'CERRADA') {
            CandelariaUI.notificarError('La edición se encuentra CERRADA. Este estado es terminal e inmutable.', 'Estado Terminal');
            return;
        }

        const modalEl = document.getElementById('modalCambiarEstado');
        if (!modalEl) return;

        document.getElementById('estadoEdicionId').value = ed.id;
        document.getElementById('estadoEdicionNombre').textContent = `${ed.nombre} (${ed.codigo} - ${ed.anio})`;
        document.getElementById('estadoEdicionActualBadge').innerHTML = this.obtenerBadgeEstado(ed.estado, ed.estado_etiqueta);

        const select = document.getElementById('selectNuevoEstado');
        select.innerHTML = '<option value="">-- Seleccione el nuevo estado --</option>';

        // Opciones válidas según la máquina de estados
        const opciones = [];
        if (ed.estado === 'PREOPERACION') {
            opciones.push({ valor: 'OPERACION', texto: 'Avanzar a OPERACION (Inicio de Cobertura y Actividades)', esRetroceso: false });
        } else if (ed.estado === 'OPERACION') {
            opciones.push({ valor: 'POSTPRODUCCION_ENTREGA', texto: 'Avanzar a POSTPRODUCCION_ENTREGA (Fin de Cobertura)', esRetroceso: false });
            opciones.push({ valor: 'PREOPERACION', texto: 'Retroceder a PREOPERACION (Excepcional - Requiere motivo)', esRetroceso: true });
        } else if (ed.estado === 'POSTPRODUCCION_ENTREGA') {
            opciones.push({ valor: 'CERRADA', texto: 'Cerrar definitivamente a CERRADA (Estado Terminal)', esRetroceso: false });
            opciones.push({ valor: 'OPERACION', texto: 'Retroceder a OPERACION (Excepcional - Requiere motivo)', esRetroceso: true });
        }

        opciones.forEach(op => {
            const optEl = document.createElement('option');
            optEl.value = op.valor;
            optEl.textContent = op.texto;
            optEl.dataset.esRetroceso = op.esRetroceso ? '1' : '0';
            select.appendChild(optEl);
        });

        // Resetear panel motivo
        document.getElementById('panelMotivoRetroceso').classList.add('d-none');
        document.getElementById('inputMotivoRetroceso').value = '';

        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    }

    evaluarMotivoRetroceso(nuevoEstado) {
        const select = document.getElementById('selectNuevoEstado');
        const selectedOpt = select.options[select.selectedIndex];
        const esRetroceso = selectedOpt?.dataset?.esRetroceso === '1';

        const panel = document.getElementById('panelMotivoRetroceso');
        const input = document.getElementById('inputMotivoRetroceso');

        if (esRetroceso) {
            panel.classList.remove('d-none');
            input.setAttribute('required', 'required');
        } else {
            panel.classList.add('d-none');
            input.removeAttribute('required');
            input.value = '';
        }
    }

    async ejecutarCambioEstado(e) {
        e.preventDefault();
        const id = document.getElementById('estadoEdicionId').value;
        const nuevoEstado = document.getElementById('selectNuevoEstado').value;
        const motivoRetroceso = document.getElementById('inputMotivoRetroceso').value.trim();
        const btnEjecutar = document.getElementById('btnEjecutarCambioEstado');

        if (!nuevoEstado) {
            CandelariaUI.notificarError('Debe seleccionar el nuevo estado al que desea transicionar.');
            return;
        }

        const select = document.getElementById('selectNuevoEstado');
        const selectedOpt = select.options[select.selectedIndex];
        const esRetroceso = selectedOpt?.dataset?.esRetroceso === '1';

        if (esRetroceso && (!motivoRetroceso || motivoRetroceso.length < 5)) {
            CandelariaUI.notificarError('El retroceso extraordinario exige un motivo explícito justificando la acción (mínimo 5 caracteres).');
            return;
        }

        try {
            CandelariaUI.procesarBoton(btnEjecutar, 'Aplicando transición...');

            const payload = {
                nuevo_estado: nuevoEstado,
                motivo_retroceso: esRetroceso ? motivoRetroceso : null
            };

            const resp = await window.CandelariaApi.post(`ediciones/${id}/estado`, payload);

            bootstrap.Modal.getInstance(document.getElementById('modalCambiarEstado'))?.hide();
            CandelariaUI.notificarExito(resp.mensaje || 'Transición de estado completada exitosamente.');

            await this.cargarEdiciones();
            window.CandelariaContextoEdicion?.sincronizarContextoInicial();

        } catch (err) {
            console.error('[ModuloEdiciones] Error al cambiar estado:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible transicionar el estado de la edición.');
        } finally {
            CandelariaUI.restaurarBoton(btnEjecutar);
        }
    }

    async confirmarSeleccionarActual(id, nombre) {
        const confirmacion = await CandelariaUI.confirmarAccion(
            `¿Está seguro de establecer "${nombre}" como la edición institucional activa de la organización?\n\nEsta edición será la predeterminada para todos los nuevos ingresos.`,
            'Establecer Edición Actual',
            'Sí, establecer como actual'
        );

        if (!confirmacion.isConfirmed) return;

        try {
            const resp = await window.CandelariaApi.post(`ediciones/${id}/seleccionar-actual`, {});
            CandelariaUI.notificarExito(resp.mensaje || 'Edición establecida como actual institucional.');

            await this.cargarEdiciones();
            window.CandelariaContextoEdicion?.sincronizarContextoInicial();

        } catch (err) {
            console.error('[ModuloEdiciones] Error al seleccionar edición actual:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible establecer la edición como actual.');
        }
    }

    seleccionarEdicionPestana(id, nombre, codigo) {
        window.CandelariaContextoEdicion?.establecerEdicion(id, nombre, codigo);
        CandelariaUI.notificarExito(`Esta pestaña ahora opera explícitamente en el contexto de "${nombre}".`, 'Contexto de Pestaña Actualizado');
    }

    escapar(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[m]);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.moduloEdiciones = new ModuloEdiciones();
});
