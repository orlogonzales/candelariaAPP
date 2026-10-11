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

        // Listeners para el Asistente de Cierre (F2.12A)
        const radiosTipo = document.querySelectorAll('input[name="tipo_cierre"]');
        radiosTipo.forEach(r => r.addEventListener('change', (e) => this.cambiarTipoCierreUI(e.target.value)));

        const formCierre = document.getElementById('formEjecutarCierre');
        if (formCierre) {
            formCierre.addEventListener('submit', (e) => this.ejecutarCierreOficial(e));
        }

        const chkConfirm = document.getElementById('cierreConfirmacion');
        if (chkConfirm) {
            chkConfirm.addEventListener('change', (e) => {
                const btn = document.getElementById('btnEjecutarCierreOficial');
                if (btn) btn.disabled = !e.target.checked;
            });
        }

        const btnShowReap = document.getElementById('btnMostrarFormReapertura');
        if (btnShowReap) {
            btnShowReap.addEventListener('click', () => {
                document.getElementById('formReapertura')?.classList.remove('d-none');
                btnShowReap.classList.add('d-none');
            });
        }
        const btnCancelReap = document.getElementById('btnCancelarReapertura');
        if (btnCancelReap) {
            btnCancelReap.addEventListener('click', () => {
                document.getElementById('formReapertura')?.classList.add('d-none');
                document.getElementById('btnMostrarFormReapertura')?.classList.remove('d-none');
            });
        }

        const formReap = document.getElementById('formReapertura');
        if (formReap) {
            formReap.addEventListener('submit', (e) => this.confirmarReapertura(e));
        }

        const btnVerificar = document.getElementById('btnVerificarIntegridadCierre');
        if (btnVerificar) {
            btnVerificar.addEventListener('click', () => {
                const modalEl = document.getElementById('modalActaCierre');
                const id = modalEl?.dataset?.edicionId;
                if (id) {
                    this.verificarIntegridad(parseInt(id, 10));
                }
            });
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
                                    <li><hr class="dropdown-divider my-1"></li>
                                    ${!esCerrada ? `
                                    <li>
                                        <button class="dropdown-item btn-accion-asistente-cierre text-danger f-w-600" data-id="${ed.id}" data-nombre="${this.escapar(ed.nombre)}">
                                            <i class="fa-solid fa-lock text-danger me-2"></i> Asistente de Cierre
                                        </button>
                                    </li>
                                    ` : `
                                    <li>
                                        <button class="dropdown-item btn-accion-ver-acta-cierre text-dark f-w-600" data-id="${ed.id}" data-nombre="${this.escapar(ed.nombre)}">
                                            <i class="fa-solid fa-stamp text-warning me-2"></i> Ver Acta de Cierre
                                        </button>
                                    </li>
                                    `}
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

        document.querySelectorAll('.btn-accion-asistente-cierre').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                const nombre = e.currentTarget.dataset.nombre;
                this.abrirAsistenteCierre(id, nombre);
            });
        });

        document.querySelectorAll('.btn-accion-ver-acta-cierre').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                const nombre = e.currentTarget.dataset.nombre;
                this.verActaCierre(id, nombre);
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

        const codigo = document.getElementById('crearCodigo').value.trim().toLowerCase();
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

    async abrirAsistenteCierre(id, nombre) {
        const modalEl = document.getElementById('modalAsistenteCierre');
        if (!modalEl) return;

        document.getElementById('cierreEdicionId').value = id;
        document.getElementById('cierreEdicionSubtitulo').textContent = nombre ? `Auditoría y Cierre Oficial de "${nombre}"` : 'Auditoría y Cierre Oficial';

        const loading = document.getElementById('cierreLoading');
        const contenido = document.getElementById('cierreContenido');
        const form = document.getElementById('formEjecutarCierre');

        if (form) form.reset();
        document.getElementById('btnEjecutarCierreOficial').disabled = true;
        document.getElementById('panelCamposCondicionado').classList.add('d-none');

        loading.classList.remove('d-none');
        contenido.classList.add('d-none');

        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();

        try {
            const resp = await window.CandelariaApi.get(`ediciones/${id}/diagnostico-cierre`);
            const diag = resp?.datos;
            if (!diag) throw new Error('Respuesta inválida del diagnóstico.');

            const nombreFinal = nombre || diag.edicion?.nombre || 'Festividad';
            document.getElementById('cierreEdicionSubtitulo').textContent = `Auditoría y Cierre Oficial de "${nombreFinal}"`;

            this.renderizarDiagnosticoCierre(diag);

            loading.classList.add('d-none');
            contenido.classList.remove('d-none');
        } catch (err) {
            console.error('[ModuloEdiciones] Error al cargar diagnóstico:', err);
            loading.innerHTML = `
                <div class="alert alert-danger b-r-10 p-3 mb-0">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> ${this.escapar(err.message || 'Error al conectar con el servidor.')}
                </div>
            `;
        }
    }

    renderizarDiagnosticoCierre(diag) {
        const res = diag.resumen_financiero || {};
        document.getElementById('diagTotalFacturado').textContent = `S/ ${(res.monto_facturado || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 })}`;
        document.getElementById('diagVentasActivas').textContent = `${res.ventas_activas || 0} ventas confirmadas`;

        document.getElementById('diagTotalRecaudado').textContent = `S/ ${(res.monto_recaudado || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 })}`;
        document.getElementById('diagRecibosCaja').textContent = `Recibos emitidos: ${res.recibos_caja_emitidos || 0}`;

        document.getElementById('diagSaldoPorCobrar').textContent = `S/ ${(res.saldo_por_cobrar || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 })}`;
        document.getElementById('diagVentasConSaldo').textContent = (res.saldo_por_cobrar > 0) ? 'Con saldo pendiente' : 'Sin saldos pendientes';

        document.getElementById('diagSalidasFinalizadas').textContent = `${res.salidas_finalizadas || 0} Salidas`;
        document.getElementById('diagEntregasAprobadas').textContent = `${res.entregas_aprobadas || 0} entregas aprobadas`;

        // Banner dictamen
        const banner = document.getElementById('cierreDictamenBanner');
        const optDef = document.getElementById('tipoCierreDefinitivo');
        const optCond = document.getElementById('tipoCierreCondicionado');
        const optDefContainer = document.getElementById('optCierreDefinitivoContainer');

        if (diag.total_bloqueantes > 0) {
            banner.className = 'p-3 b-r-12 mb-4 bg-danger-subtle text-danger border border-danger';
            banner.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-xmark f-s-20"></i>
                    <div>
                        <strong class="f-s-14">Cierre Bloqueado por Incidencias Críticas</strong>
                        <div class="f-s-12">Se detectaron ${diag.total_bloqueantes} impedimento(s) operativo(s) que impiden el cierre. Resuélvalos antes de proceder.</div>
                    </div>
                </div>
            `;
            optDef.disabled = true;
            optCond.disabled = true;
            optDef.checked = false;
            optCond.checked = false;
        } else if (diag.total_observaciones > 0) {
            banner.className = 'p-3 b-r-12 mb-4 bg-warning-subtle text-warning-emphasis border border-warning';
            banner.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation f-s-20"></i>
                    <div>
                        <strong class="f-s-14">Apto para Cierre Condicionado</strong>
                        <div class="f-s-12">Existen ${diag.total_observaciones} observación(es) financieras o de postproducción pendientes. Se permite el cierre registrando justificación y autorizador.</div>
                    </div>
                </div>
            `;
            optDef.disabled = true;
            optDefContainer.classList.add('opacity-50');
            optCond.disabled = false;
            optCond.checked = true;
            this.cambiarTipoCierreUI('CONDICIONADO');
        } else {
            banner.className = 'p-3 b-r-12 mb-4 bg-success-subtle text-success border border-success';
            banner.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-check f-s-20"></i>
                    <div>
                        <strong class="f-s-14">Apto para Cierre Definitivo Pleno</strong>
                        <div class="f-s-12">Balance en orden: No existen incidencias bloqueantes ni saldos pendientes de regularización.</div>
                    </div>
                </div>
            `;
            optDef.disabled = false;
            optDefContainer.classList.remove('opacity-50');
            optCond.disabled = false;
            optDef.checked = true;
            this.cambiarTipoCierreUI('DEFINITIVO');
        }

        // Lista de incidencias
        const lista = document.getElementById('cierreListaIncidencias');
        const todas = [...(diag.bloqueantes || []), ...(diag.observaciones || [])];

        if (todas.length === 0) {
            lista.innerHTML = `
                <div class="p-3 bg-light rounded text-center text-muted f-s-13">
                    <i class="fa-solid fa-check-double text-success me-1"></i> Auditoría impecable: Sin observaciones pendientes de liquidación.
                </div>
            `;
        } else {
            lista.innerHTML = todas.map(item => {
                const esBloq = item.tipo === 'BLOQUEANTE';
                return `
                    <div class="alert ${esBloq ? 'alert-danger border-danger' : 'alert-warning border-warning'} p-3 mb-2 b-r-10 d-flex gap-2 align-items-start">
                        <i class="fa-solid ${esBloq ? 'fa-circle-xmark text-danger' : 'fa-triangle-exclamation text-warning'} mt-1"></i>
                        <div>
                            <strong class="f-s-13 text-dark">${this.escapar(item.titulo || item.codigo)}</strong>
                            <div class="f-s-12 text-secondary mt-1">${this.escapar(item.mensaje)}</div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Cargar usuarios en el select de autorizador
        this.cargarUsuariosAutorizadores();
    }

    async cargarUsuariosAutorizadores() {
        const select = document.getElementById('cierreAutorizador');
        const selectReap = document.getElementById('reaperturaAutorizador');
        if (!select) return;

        try {
            const resp = await window.CandelariaApi.get('usuarios?limite=100');
            const usuarios = resp?.datos?.usuarios || resp?.datos || [];

            const opciones = usuarios.map(u => `<option value="${u.id}">${this.escapar(u.nombres || u.nombre || '')} ${this.escapar(u.apellidos || '')} (${this.escapar(u.correo || u.correo_electronico || '')})</option>`).join('');

            select.innerHTML = `<option value="">Seleccione autorizador formal...</option>${opciones}`;
            if (selectReap) {
                selectReap.innerHTML = `<option value="">Seleccione autorizador...</option>${opciones}`;
            }
        } catch (e) {
            console.warn('[ModuloEdiciones] No fue posible cargar lista de usuarios para autorizador:', e);
        }
    }

    cambiarTipoCierreUI(tipo) {
        const panelCond = document.getElementById('panelCamposCondicionado');
        const txtJust = document.getElementById('cierreJustificacion');
        const selAut = document.getElementById('cierreAutorizador');

        if (tipo === 'CONDICIONADO') {
            panelCond?.classList.remove('d-none');
            if (txtJust) txtJust.required = true;
            if (selAut) selAut.required = true;
        } else {
            panelCond?.classList.add('d-none');
            if (txtJust) txtJust.required = false;
            if (selAut) selAut.required = false;
        }
    }

    async ejecutarCierreOficial(e) {
        e.preventDefault();
        const id = document.getElementById('cierreEdicionId').value;
        const tipoRadio = document.querySelector('input[name="tipo_cierre"]:checked');
        const btn = document.getElementById('btnEjecutarCierreOficial');

        if (!tipoRadio) {
            CandelariaUI.notificarError('Debe seleccionar la modalidad de cierre.');
            return;
        }

        const tipoCierre = tipoRadio.value;
        const justificacion = document.getElementById('cierreJustificacion')?.value || '';
        const autorizadorId = document.getElementById('cierreAutorizador')?.value || null;

        if (tipoCierre === 'CONDICIONADO') {
            if (justificacion.trim().length < 15) {
                CandelariaUI.notificarError('El cierre condicionado exige una justificación formal fundamentada (mínimo 15 caracteres).');
                return;
            }
            if (!autorizadorId) {
                CandelariaUI.notificarError('Debe seleccionar el usuario que autoriza formalmente el cierre condicionado.');
                return;
            }
        }

        const confirmacion = await CandelariaUI.confirmarAccion(
            `¿Confirma el Cierre ${tipoCierre} de la festividad?\n\nLa edición pasará a estado CERRADA y se congelarán las cifras financieras con hash SHA-256 inmutable.`,
            'Asentar Cierre Oficial',
            'Sí, asentar cierre'
        );

        if (!confirmacion.isConfirmed) return;

        try {
            CandelariaUI.procesarBoton(btn, 'Asentando cierre...');

            const payload = {
                tipo_cierre: tipoCierre,
                justificacion: tipoCierre === 'CONDICIONADO' ? justificacion.trim() : null,
                autorizador_id: tipoCierre === 'CONDICIONADO' ? parseInt(autorizadorId, 10) : null,
                version_bloqueo: 1
            };

            const resp = await window.CandelariaApi.post(`ediciones/${id}/cerrar`, payload);

            bootstrap.Modal.getInstance(document.getElementById('modalAsistenteCierre'))?.hide();
            CandelariaUI.notificarExito(resp.mensaje || 'Cierre oficial asentado exitosamente.');

            await this.cargarEdiciones();
            window.CandelariaContextoEdicion?.sincronizarContextoInicial();

        } catch (err) {
            console.error('[ModuloEdiciones] Error al asentar cierre:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible asentar el cierre de la edición.');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    async verActaCierre(id, nombre) {
        const modalEl = document.getElementById('modalActaCierre');
        if (!modalEl) return;

        try {
            const resp = await window.CandelariaApi.get(`ediciones/${id}/cierre`);
            const cierre = resp?.datos;
            if (!cierre) throw new Error('No se encontró acta de cierre para esta edición.');

            document.getElementById('actaEdicionNombre').textContent = nombre;
            document.getElementById('actaTipoBadge').textContent = `CIERRE ${cierre.tipo_cierre}`;
            document.getElementById('actaTipoBadge').className = (cierre.tipo_cierre === 'CONDICIONADO') ? 'badge bg-warning text-dark' : 'badge bg-danger';
            document.getElementById('actaFecha').textContent = cierre.fecha_cierre;
            document.getElementById('actaUsuario').textContent = `${cierre.usuario_cierre_nombres || ''} ${cierre.usuario_cierre_apellidos || ''}`;
            document.getElementById('actaHash').textContent = cierre.hash_integridad_cierre;

            if (cierre.tipo_cierre === 'CONDICIONADO') {
                document.getElementById('actaAutorizadorFila').classList.remove('d-none');
                document.getElementById('actaAutorizador').textContent = `${cierre.autorizador_nombres || ''} ${cierre.autorizador_apellidos || ''}`;
                document.getElementById('actaJustificacionFila').classList.remove('d-none');
                document.getElementById('actaJustificacion').textContent = cierre.justificacion_condicionado || '-';
            } else {
                document.getElementById('actaAutorizadorFila').classList.add('d-none');
                document.getElementById('actaJustificacionFila').classList.add('d-none');
            }

            // Llenar tabla cifras congeladas
            const res = cierre.resumen_financiero || {};
            const tbody = document.getElementById('actaTablaCifras');
            tbody.innerHTML = `
                <tr><th class="w-50 bg-light">Monto Total Facturado:</th><td><strong>S/ ${(res.monto_facturado || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 })}</strong></td></tr>
                <tr><th class="bg-light">Monto Total Recaudado:</th><td class="text-success"><strong>S/ ${(res.monto_recaudado || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 })}</strong></td></tr>
                <tr><th class="bg-light">Saldo por Cobrar Congelado:</th><td class="text-warning"><strong>S/ ${(res.saldo_por_cobrar || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 })}</strong></td></tr>
                <tr><th class="bg-light">Ventas Activas:</th><td>${res.ventas_activas || 0}</td></tr>
                <tr><th class="bg-light">Recibos de Caja Emitidos:</th><td>${res.recibos_caja_emitidos || 0} (S/ ${(res.recibos_caja_monto || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 })})</td></tr>
                <tr><th class="bg-light">Salidas Operativas Finalizadas:</th><td>${res.salidas_finalizadas || 0}</td></tr>
                <tr><th class="bg-light">Entregas Audiovisuales Aprobadas:</th><td>${res.entregas_aprobadas || 0}</td></tr>
            `;

            // Configurar formulario reapertura
            document.getElementById('formReapertura')?.classList.add('d-none');
            document.getElementById('btnMostrarFormReapertura')?.classList.remove('d-none');
            modalEl.dataset.edicionId = id;

            this.cargarUsuariosAutorizadores();

            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        } catch (err) {
            console.error('[ModuloEdiciones] Error al consultar acta:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible cargar el acta de cierre.');
        }
    }

    async confirmarReapertura(e) {
        e.preventDefault();
        const modalEl = document.getElementById('modalActaCierre');
        const id = modalEl?.dataset?.edicionId;
        const motivo = document.getElementById('reaperturaMotivo')?.value || '';
        const autorizadorId = document.getElementById('reaperturaAutorizador')?.value || '';
        const claveAutorizador = document.getElementById('reaperturaClaveAutorizador')?.value || '';
        const btn = document.getElementById('btnConfirmarReapertura');

        if (motivo.trim().length < 20) {
            CandelariaUI.notificarError('La reapertura extraordinaria exige un motivo formal debidamente fundamentado (mínimo 20 caracteres).');
            return;
        }

        if (!autorizadorId) {
            CandelariaUI.notificarError('Debe seleccionar el usuario que autoriza formalmente la reapertura.');
            return;
        }

        if (!claveAutorizador) {
            CandelariaUI.notificarError('Debe ingresar la contraseña del usuario autorizador para la firma de cuatro ojos.');
            return;
        }

        const confirmacion = await CandelariaUI.confirmarAccion(
            '¿Está seguro de reabrir extraordinariamente la festividad?\n\nEsta acción transitará el cierre a REABIERTO_EXCEPCIONAL y quedará grabada en la bitácora inmutable de auditoría.',
            'Reapertura Extraordinaria',
            'Sí, autorizar reapertura'
        );

        if (!confirmacion.isConfirmed) return;

        try {
            CandelariaUI.procesarBoton(btn, 'Autorizando reapertura...');

            const payload = {
                motivo_excepcional: motivo.trim(),
                autorizado_por_usuario_id: parseInt(autorizadorId, 10),
                clave_confirmacion_autorizador: claveAutorizador,
                nuevo_estado: 'POSTPRODUCCION_ENTREGA'
            };

            const resp = await window.CandelariaApi.post(`ediciones/${id}/reabrir`, payload);

            bootstrap.Modal.getInstance(modalEl)?.hide();
            CandelariaUI.notificarExito(resp.mensaje || 'Festividad reabierta exitosamente.');

            await this.cargarEdiciones();
            window.CandelariaContextoEdicion?.sincronizarContextoInicial();

        } catch (err) {
            console.error('[ModuloEdiciones] Error al reabrir edición:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible ejecutar la reapertura.');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    async verificarIntegridad(id) {
        try {
            CandelariaUI.mostrarCargando('Auditando sellado criptográfico HMAC del cierre...');
            const resp = await window.CandelariaApi.get(`ediciones/${id}/cierre/verificar-integridad`);
            CandelariaUI.cerrarCargando();

            const datos = resp?.datos || {};
            if (datos.es_integro) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Sellado Criptográfico Íntegro',
                    html: `
                        <div class="text-start f-s-13">
                            <p class="mb-2 text-success f-w-600"><i class="fa-solid fa-circle-check me-1"></i> El acta y las cifras congeladas no han sufrido alteración alguna.</p>
                            <div class="p-2 bg-light rounded font-monospace f-s-11 mb-2 text-break">
                                <strong>Hash HMAC Verificado:</strong><br>${this.escapar(datos.hash_calculado)}
                            </div>
                            <div class="f-s-12 text-muted">
                                <strong>Tipo de Sellado:</strong> ${this.escapar(datos.tipo_sellado)}<br>
                                <strong>Fecha de Auditoría:</strong> ${this.escapar(datos.verificado_en)}
                            </div>
                        </div>
                    `,
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#0d6efd'
                });
            } else {
                await Swal.fire({
                    icon: 'error',
                    title: 'Alerta de Inconsistencia',
                    html: `
                        <div class="text-start f-s-13">
                            <p class="mb-2 text-danger f-w-600"><i class="fa-solid fa-triangle-exclamation me-1"></i> Se detectó discrepancia entre el hash almacenado y el recálculo formal.</p>
                            <div class="p-2 bg-danger-subtle text-danger rounded font-monospace f-s-11 mb-2 text-break">
                                <strong>Hash Almacenado:</strong> ${this.escapar(datos.hash_almacenado)}<br>
                                <strong>Hash Calculado:</strong> ${this.escapar(datos.hash_calculado)}
                            </div>
                        </div>
                    `,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
            }
        } catch (err) {
            CandelariaUI.cerrarCargando();
            console.error('[ModuloEdiciones] Error al verificar integridad:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible auditar el sellado criptográfico.');
        }
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
