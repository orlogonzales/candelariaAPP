/**
 * ==============================================================================
 * CANDELARIAAPP - JAVASCRIPT OFICIAL: OPERACIONES DE CAMPO (operaciones.js)
 * FASE 2.6E — Tablero de Salidas, Armado, Manifiesto, Check-in táctil e Incidencias
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // 1. Endpoints y Configuración Base
    const URL_API_OPERACIONES = window.CANDELARIA_BASE_URL + 'api/v1/operaciones';
    const CSRF_TOKEN = window.CANDELARIA_CSRF_TOKEN || '';

    // Elementos DOM Principales
    const skeletonTabla = document.getElementById('skeletonSalidas');
    const contenedorTabla = document.getElementById('contenedorTablaSalidas');
    const tablaElemento = document.getElementById('tablaSalidas');

    // Filtros
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroFecha = document.getElementById('filtroFecha');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroServicio = document.getElementById('filtroServicio');
    const btnAplicarFiltros = document.getElementById('btnAplicarFiltros');
    const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltros');
    const btnRecargarTabla = document.getElementById('btnRecargarTabla');

    // Modales Bootstrap
    const modalSalidaFormEl = document.getElementById('modalSalidaForm');
    const modalSalidaForm = modalSalidaFormEl ? new bootstrap.Modal(modalSalidaFormEl) : null;
    const modalDetalleSalidaEl = document.getElementById('modalDetalleSalida');
    const modalDetalleSalida = modalDetalleSalidaEl ? new bootstrap.Modal(modalDetalleSalidaEl) : null;
    const modalAsignarRecursoEl = document.getElementById('modalAsignarRecurso');
    const modalAsignarRecurso = modalAsignarRecursoEl ? new bootstrap.Modal(modalAsignarRecursoEl) : null;
    const modalManifiestoEl = document.getElementById('modalManifiesto');
    const modalManifiesto = modalManifiestoEl ? new bootstrap.Modal(modalManifiestoEl) : null;
    const modalCheckinEl = document.getElementById('modalCheckin');
    const modalCheckin = modalCheckinEl ? new bootstrap.Modal(modalCheckinEl) : null;
    const modalIncidenciaEl = document.getElementById('modalIncidencia');
    const modalIncidencia = modalIncidenciaEl ? new bootstrap.Modal(modalIncidenciaEl) : null;
    const modalVerIncidenciasEl = document.getElementById('modalVerIncidencias');
    const modalVerIncidencias = modalVerIncidenciasEl ? new bootstrap.Modal(modalVerIncidenciasEl) : null;

    let dataTableInstancia = null;
    let salidaActualId = null;
    let salidaActualData = null;
    let fpFiltroFecha = null;
    let fpSalidaFecha = null;

    // Inicializar Flatpickr
    if (typeof flatpickr !== 'undefined') {
        if (filtroFecha) fpFiltroFecha = flatpickr(filtroFecha, { dateFormat: 'Y-m-d' });
        const salidaFechaInput = document.getElementById('salidaFecha');
        if (salidaFechaInput) fpSalidaFecha = flatpickr(salidaFechaInput, { dateFormat: 'Y-m-d' });
    }

    // Cargar Servicios para Filtro y Formulario
    cargarServiciosComerciales();

    // 2. Cargar Salidas Operativas
    cargarSalidas();

    function cargarSalidas() {
        if (skeletonTabla) skeletonTabla.classList.remove('d-none');
        if (contenedorTabla) contenedorTabla.classList.add('d-none');

        const params = new URLSearchParams();
        if (filtroBusqueda && filtroBusqueda.value.trim() !== '') params.append('busqueda', filtroBusqueda.value.trim());
        if (filtroFecha && filtroFecha.value !== '') params.append('fecha', filtroFecha.value);
        if (filtroEstado && filtroEstado.value !== '') params.append('estado', filtroEstado.value);
        if (filtroServicio && filtroServicio.value !== '') params.append('item_id', filtroServicio.value);

        fetch(`${URL_API_OPERACIONES}/salidas?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'Error al cargar salidas');
            renderizarTablaSalidas(data.datos.salidas || []);
            actualizarKpis(data.datos.salidas || []);
        })
        .catch(err => {
            console.error(err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error de Carga', text: err.message });
            }
        })
        .finally(() => {
            if (skeletonTabla) skeletonTabla.classList.add('d-none');
            if (contenedorTabla) contenedorTabla.classList.remove('d-none');
        });
    }

    function renderizarTablaSalidas(salidas) {
        if (dataTableInstancia) {
            dataTableInstancia.destroy();
            dataTableInstancia = null;
        }

        const tbody = tablaElemento.querySelector('tbody');
        tbody.innerHTML = '';

        salidas.forEach(s => {
            const tr = document.createElement('tr');

            // Badge de Estado Alina
            let badgeClase = 'bg-secondary';
            if (s.estado === 'PROGRAMADA') badgeClase = 'bg-info text-dark';
            else if (s.estado === 'DESPACHADA') badgeClase = 'bg-warning text-dark';
            else if (s.estado === 'FINALIZADA') badgeClase = 'bg-success';
            else if (s.estado === 'INTERRUMPIDA') badgeClase = 'bg-danger';
            else if (s.estado === 'CANCELADA') badgeClase = 'bg-dark';

            // Ocupación
            const ocupados = parseFloat(s.pasajeros_asignados || 0);
            const maximo = s.capacidad_maxima ? parseInt(s.capacidad_maxima) : null;
            let ocupacionHtml = `${ocupados}`;
            if (maximo) {
                const porcentaje = Math.min(100, Math.round((ocupados / maximo) * 100));
                let colorBar = 'bg-primary';
                if (porcentaje >= 90) colorBar = 'bg-danger';
                else if (porcentaje >= 70) colorBar = 'bg-warning';

                ocupacionHtml = `
                    <div class="d-flex flex-column" style="min-width: 90px;">
                        <span class="f-s-12 f-w-600">${ocupados} / ${maximo} pax</span>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar ${colorBar}" style="width: ${porcentaje}%"></div>
                        </div>
                    </div>
                `;
            } else {
                ocupacionHtml = `<span class="f-s-12">${ocupados} pax (${escaparHtml(s.tipo_capacidad)})</span>`;
            }

            // Check-in ratio
            const presentes = parseInt(s.presentes_count || 0);
            const noShow = parseInt(s.no_show_count || 0);
            const checkinHtml = `
                <div class="d-flex align-items-center gap-1 f-s-12">
                    <span class="badge bg-light-success text-success">${presentes} pres.</span>
                    ${noShow > 0 ? `<span class="badge bg-light-danger text-danger">${noShow} no-show</span>` : ''}
                </div>
            `;

            tr.innerHTML = `
                <td>
                    <strong class="text-primary f-s-13">${escaparHtml(s.correlativo)}</strong>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span class="f-w-600 text-dark f-s-13">${escaparHtml(s.titulo)}</span>
                        <small class="text-muted f-s-11">${escaparHtml(s.servicio_nombre)}</small>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column f-s-12">
                        <span><i class="fa-solid fa-calendar text-primary me-1"></i> ${s.fecha_salida}</span>
                        <small class="text-muted"><i class="fa-solid fa-clock text-secondary me-1"></i> Cit: ${s.hora_citacion.substring(0, 5)} | Zarpe: ${s.hora_salida.substring(0, 5)}</small>
                    </div>
                </td>
                <td>${ocupacionHtml}</td>
                <td><span class="badge ${badgeClase}">${escaparHtml(s.estado)}</span></td>
                <td>${checkinHtml}</td>
                <td class="text-center">
                    <div class="d-inline-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary btnArmadoSalida" data-id="${s.id}" title="Armado y Prestaciones">
                            <i class="fa-solid fa-boxes-packing"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info btnVerManifiesto" data-id="${s.id}" title="Manifiesto Oficial">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning btnAbrirCheckin" data-id="${s.id}" title="Check-in Táctil de Campo">
                            <i class="fa-solid fa-clipboard-user"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary btnIncidenciasSalida" data-id="${s.id}" data-count="${s.incidencias_count || 0}" title="Bitácora de Incidencias">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            ${parseInt(s.incidencias_count || 0) > 0 ? `<span class="badge bg-danger ms-1">${s.incidencias_count}</span>` : ''}
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        if (typeof $.fn.DataTable !== 'undefined') {
            dataTableInstancia = $(tablaElemento).DataTable({
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Buscar salida...",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ salidas",
                    infoEmpty: "Mostrando 0 salidas",
                    paginate: { first: "Primero", previous: "Anterior", next: "Siguiente", last: "Último" }
                },
                order: [[2, 'desc']],
                pageLength: 10,
            });
        }
    }

    function actualizarKpis(salidas) {
        let total = salidas.length;
        let prog = 0;
        let desp = 0;
        let fin = 0;

        salidas.forEach(s => {
            if (s.estado === 'PROGRAMADA') prog++;
            else if (s.estado === 'DESPACHADA') desp++;
            else if (s.estado === 'FINALIZADA') fin++;
        });

        const elTotal = document.getElementById('kpiTotalSalidas');
        const elProg = document.getElementById('kpiProgramadas');
        const elDesp = document.getElementById('kpiDespachadas');
        const elFin = document.getElementById('kpiFinalizadas');

        if (elTotal) elTotal.textContent = total;
        if (elProg) elProg.textContent = prog;
        if (elDesp) elDesp.textContent = desp;
        if (elFin) elFin.textContent = fin;
    }

    // 3. Crear Nueva Salida Operativa
    const btnAbrirNueva = document.getElementById('btnAbrirModalNuevaSalida');
    if (btnAbrirNueva) {
        btnAbrirNueva.addEventListener('click', function () {
            document.getElementById('salidaFormId').value = '';
            document.getElementById('salidaFormVersion').value = '';
            document.getElementById('salidaTitulo').value = '';
            document.getElementById('salidaFecha').value = '';
            document.getElementById('salidaHoraCitacion').value = '07:30';
            document.getElementById('salidaHoraSalida').value = '08:00';
            document.getElementById('salidaPuntoEncuentro').value = 'Puerto Lacustre de Puno - Muelle Principal';
            document.getElementById('salidaCapacidadMax').value = '25';
            document.getElementById('contenedorSelectServicio').classList.remove('d-none');
            document.getElementById('contenedorTipoCapacidad').classList.remove('d-none');
            document.getElementById('tituloModalSalida').innerHTML = '<i class="fa-solid fa-route text-primary me-2"></i> Nueva Salida Operativa';

            if (modalSalidaForm) modalSalidaForm.show();
        });
    }

    const formSalida = document.getElementById('formSalidaOperativa');
    if (formSalida) {
        formSalida.addEventListener('submit', function (e) {
            e.preventDefault();
            const btnGuardar = document.getElementById('btnGuardarSalida');
            const salidaId = document.getElementById('salidaFormId').value;
            const esEdicion = salidaId !== '';

            const payload = {
                _csrf_token: CSRF_TOKEN,
                edicion_id: 1, // resuelto o implícito
                item_comercial_id: parseInt(document.getElementById('salidaServicioId').value),
                titulo: document.getElementById('salidaTitulo').value,
                fecha_salida: document.getElementById('salidaFecha').value,
                hora_citacion: document.getElementById('salidaHoraCitacion').value,
                hora_salida: document.getElementById('salidaHoraSalida').value,
                punto_encuentro: document.getElementById('salidaPuntoEncuentro').value,
                tipo_capacidad: document.getElementById('salidaTipoCapacidad').value,
                capacidad_maxima: document.getElementById('salidaCapacidadMax').value ? parseInt(document.getElementById('salidaCapacidadMax').value) : null,
                version_bloqueo: parseInt(document.getElementById('salidaFormVersion').value || 1),
            };

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            const url = esEdicion ? `${URL_API_OPERACIONES}/salidas/${salidaId}` : `${URL_API_OPERACIONES}/salidas`;
            const method = esEdicion ? 'PUT' : 'POST';

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (!data.exito) throw new Error(data.mensaje || 'Error al guardar salida operativa');
                if (modalSalidaForm) modalSalidaForm.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: esEdicion ? 'Salida Actualizada' : 'Salida Creada',
                        text: data.mensaje || 'La salida operativa ha sido registrada exitosamente.'
                    });
                }
                cargarSalidas();
            })
            .catch(err => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            })
            .finally(() => {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fa-solid fa-save me-1"></i> Guardar Salida';
            });
        });
    }

    // 4. Detalle y Armado de Salida (Asignación de Prestaciones y Recursos)
    document.addEventListener('click', function (e) {
        const btnArmado = e.target.closest('.btnArmadoSalida');
        if (btnArmado) {
            const id = btnArmado.getAttribute('data-id');
            abrirArmadoSalida(id);
        }
    });

    function abrirArmadoSalida(salidaId) {
        salidaActualId = salidaId;

        fetch(`${URL_API_OPERACIONES}/salidas/${salidaId}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'Error al cargar salida');
            salidaActualData = data.datos.salida;
            poblarArmadoSalida(salidaActualData);
            if (modalDetalleSalida) modalDetalleSalida.show();
        })
        .catch(err => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    function poblarArmadoSalida(s) {
        document.getElementById('armadoCorrelativo').textContent = s.correlativo;
        const bEstado = document.getElementById('armadoEstadoBadge');
        bEstado.textContent = s.estado;
        bEstado.className = 'badge ' + (
            s.estado === 'PROGRAMADA' ? 'bg-info text-dark' :
            s.estado === 'DESPACHADA' ? 'bg-warning text-dark' :
            s.estado === 'FINALIZADA' ? 'bg-success' : 'bg-secondary'
        );

        document.getElementById('armadoCapacidadBadge').textContent = `Ocupación: ${s.ocupacion_actual} / ${s.capacidad_maxima || '∞'} pax (${s.tipo_capacidad})`;
        document.getElementById('armadoCountPrestaciones').textContent = s.prestaciones.length;
        document.getElementById('armadoCountRecursos').textContent = s.recursos.length;

        // 1. Prestaciones Asignadas
        const contAsignadas = document.getElementById('listaPrestacionesAsignadas');
        contAsignadas.innerHTML = '';

        if (s.prestaciones.length === 0) {
            contAsignadas.innerHTML = '<div class="p-3 bg-light text-muted text-center b-r-8 f-s-13">No hay prestaciones asignadas a esta salida aún.</div>';
        } else {
            s.prestaciones.forEach(p => {
                const card = document.createElement('div');
                card.className = 'card border b-r-8 p-3 bg-white shadow-none';
                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-light text-dark border">${escaparHtml(p.reserva_correlativo)}</span>
                            <h6 class="f-w-700 text-dark mb-0 mt-1">${escaparHtml(p.concepto_nombre)}</h6>
                            <small class="text-muted">${escaparHtml(p.titular_reserva)} &bull; <strong>${p.cantidad_pasajeros} pax</strong></small>
                        </div>
                        ${s.estado === 'PROGRAMADA' ? `
                        <button type="button" class="btn btn-sm btn-outline-danger btnDesasignarPrestacion" data-prest-id="${p.prestacion_id}" title="Desasignar de la salida">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                `;
                contAsignadas.appendChild(card);
            });
        }

        // 2. Prestaciones Compatibles Disponibles
        const contCompatibles = document.getElementById('listaPrestacionesCompatibles');
        contCompatibles.innerHTML = '<div class="p-3 text-center text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Buscando turnos compatibles...</div>';

        fetch(`${URL_API_OPERACIONES}/salidas/${s.id}/prestaciones-compatibles`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            const compatibles = data.datos.prestaciones || [];
            contCompatibles.innerHTML = '';

            if (compatibles.length === 0) {
                contCompatibles.innerHTML = '<div class="p-3 bg-light text-muted text-center b-r-8 f-s-13">No hay prestaciones compatibles disponibles para la fecha y servicio de esta salida.</div>';
                return;
            }

            compatibles.forEach(c => {
                const card = document.createElement('div');
                card.className = 'card border b-r-8 p-3 bg-white shadow-none';

                let accionAsignar = '';
                if (s.estado === 'PROGRAMADA') {
                    if (c.es_compatible) {
                        accionAsignar = `
                            <button type="button" class="btn btn-sm btn-success btnAsignarPrestacion" data-prest-id="${c.id}" data-pax="${c.cantidad}">
                                <i class="fa-solid fa-plus me-1"></i> Asignar (${c.cantidad} pax)
                            </button>
                        `;
                    } else {
                        accionAsignar = `
                            <span class="badge bg-light-danger text-danger p-2 f-s-11" title="${escaparHtml(c.motivo_incompatibilidad)}">
                                <i class="fa-solid fa-ban me-1"></i> ${escaparHtml(c.motivo_incompatibilidad)}
                            </span>
                        `;
                    }
                }

                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-light text-dark border">${escaparHtml(c.reserva_correlativo)}</span>
                            <h6 class="f-w-700 text-dark mb-0 mt-1">${escaparHtml(c.concepto_nombre)}</h6>
                            <small class="text-muted">${escaparHtml(c.titular_reserva)} &bull; <strong>${c.cantidad} pax</strong> ${c.hora_servicio ? `&bull; Hora: ${c.hora_servicio}` : ''}</small>
                        </div>
                        <div>${accionAsignar}</div>
                    </div>
                `;
                contCompatibles.appendChild(card);
            });
        });

        // 3. Recursos y Personal Asignado
        const tbodyRecursos = document.getElementById('tbodyRecursosSalida');
        tbodyRecursos.innerHTML = '';

        if (s.recursos.length === 0) {
            tbodyRecursos.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No hay recursos ni personal asignados a esta salida.</td></tr>';
        } else {
            s.recursos.forEach(rec => {
                const tr = document.createElement('tr');
                const recFisicoNombre = rec.codigo_interno ? `${rec.recurso_nombre} (${rec.codigo_interno})` : '-';
                const personaNombre = rec.persona_nombre || '-';

                tr.innerHTML = `
                    <td><span class="badge bg-light text-dark border">${escaparHtml(rec.rol_operativo)}</span></td>
                    <td><strong>${escaparHtml(recFisicoNombre)}</strong></td>
                    <td>${escaparHtml(personaNombre)}</td>
                    <td><small class="text-muted">${escaparHtml(rec.notas || '-')}</small></td>
                    <td class="text-center">
                        ${s.estado === 'PROGRAMADA' ? `
                        <button type="button" class="btn btn-sm btn-outline-danger btnDesasignarRecurso" data-asig-id="${rec.asignacion_id}" title="Remover recurso">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        ` : '-'}
                    </td>
                `;
                tbodyRecursos.appendChild(tr);
            });
        }
    }

    // Acciones dentro de Armado: Asignar / Desasignar Prestación
    document.addEventListener('click', function (e) {
        const btnAsignar = e.target.closest('.btnAsignarPrestacion');
        if (btnAsignar) {
            const pId = btnAsignar.getAttribute('data-prest-id');
            ejecutarAsignarPrestacion(salidaActualId, pId, btnAsignar);
        }

        const btnDesasig = e.target.closest('.btnDesasignarPrestacion');
        if (btnDesasig) {
            const pId = btnDesasig.getAttribute('data-prest-id');
            ejecutarDesasignarPrestacion(salidaActualId, pId, btnDesasig);
        }

        const btnDesasigRec = e.target.closest('.btnDesasignarRecurso');
        if (btnDesasigRec) {
            const asigId = btnDesasigRec.getAttribute('data-asig-id');
            ejecutarDesasignarRecurso(salidaActualId, asigId);
        }
    });

    function ejecutarAsignarPrestacion(salidaId, prestacionId, boton) {
        boton.disabled = true;
        boton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch(`${URL_API_OPERACIONES}/salidas/${salidaId}/prestaciones`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({
                _csrf_token: CSRF_TOKEN,
                prestacion_id: parseInt(prestacionId)
            })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'Error al asignar prestación');
            abrirArmadoSalida(salidaId);
            cargarSalidas();
        })
        .catch(err => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Conflicto / Capacidad Excedida', text: err.message });
            }
            boton.disabled = false;
            boton.innerHTML = '<i class="fa-solid fa-plus me-1"></i> Asignar';
        });
    }

    function ejecutarDesasignarPrestacion(salidaId, prestacionId, boton) {
        boton.disabled = true;
        fetch(`${URL_API_OPERACIONES}/salidas/${salidaId}/prestaciones/${prestacionId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({ _csrf_token: CSRF_TOKEN })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'Error al desasignar');
            abrirArmadoSalida(salidaId);
            cargarSalidas();
        })
        .catch(err => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    // Modal Asignar Recurso / Personal a la Salida
    const btnAbrirAsigRecurso = document.getElementById('btnAbrirModalAsignarRecurso');
    if (btnAbrirAsigRecurso) {
        btnAbrirAsigRecurso.addEventListener('click', function () {
            if (!salidaActualId) return;

            document.getElementById('asigRecursoSalidaId').value = salidaActualId;
            document.getElementById('asigRolOperativo').value = '';
            document.getElementById('asigNotas').value = '';

            // Cargar recursos disponibles
            const selRecurso = document.getElementById('asigRecursoFisicoId');
            selRecurso.innerHTML = '<option value="">Ninguno / Solo Personal</option>';
            fetch(`${URL_API_OPERACIONES}/aux/recursos-activos`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                (data.datos.recursos || []).forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = `${r.nombre} (${r.codigo_interno} - Cap. ${r.capacidad_maxima} pax)`;
                    selRecurso.appendChild(opt);
                });
            });

            // Cargar personas para guía/chofer
            const selPersona = document.getElementById('asigPersonaId');
            selPersona.innerHTML = '<option value="">Ninguno / Solo Vehículo</option>';
            fetch(`${URL_API_OPERACIONES}/aux/personas`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                (data.datos.personas || []).forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = `${p.text} (${p.numero_documento || 'Sin doc'})`;
                    selPersona.appendChild(opt);
                });
            });

            if (modalAsignarRecurso) modalAsignarRecurso.show();
        });
    }

    const formAsigRecurso = document.getElementById('formAsignarRecursoSalida');
    if (formAsigRecurso) {
        formAsigRecurso.addEventListener('submit', function (e) {
            e.preventDefault();
            const btnGuardar = document.getElementById('btnGuardarAsigRecurso');
            const salidaId = document.getElementById('asigRecursoSalidaId').value;

            const recFisicoId = document.getElementById('asigRecursoFisicoId').value;
            const persId = document.getElementById('asigPersonaId').value;

            const payload = {
                _csrf_token: CSRF_TOKEN,
                rol_operativo: document.getElementById('asigRolOperativo').value,
                recurso_fisico_id: recFisicoId ? parseInt(recFisicoId) : null,
                persona_id: persId ? parseInt(persId) : null,
                notas: document.getElementById('asigNotas').value,
            };

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Asignando...';

            fetch(`${URL_API_OPERACIONES}/salidas/${salidaId}/recursos`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (!data.exito) throw new Error(data.mensaje || 'Error al asignar recurso');
                if (modalAsignarRecurso) modalAsignarRecurso.hide();
                abrirArmadoSalida(salidaId);
            })
            .catch(err => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            })
            .finally(() => {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fa-solid fa-save me-1"></i> Asignar';
            });
        });
    }

    function ejecutarDesasignarRecurso(salidaId, asigId) {
        fetch(`${URL_API_OPERACIONES}/salidas/${salidaId}/recursos/${asigId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({ _csrf_token: CSRF_TOKEN })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'Error al desasignar');
            abrirArmadoSalida(salidaId);
        })
        .catch(err => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    // 5. Manifiesto Oficial de Salida
    document.addEventListener('click', function (e) {
        const btnManif = e.target.closest('.btnVerManifiesto');
        if (btnManif) {
            const id = btnManif.getAttribute('data-id');
            abrirManifiesto(id);
        }
    });

    function abrirManifiesto(salidaId) {
        fetch(`${URL_API_OPERACIONES}/salidas/${salidaId}/manifiesto`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'Error al cargar manifiesto');
            poblarManifiesto(data.datos);
            if (modalManifiesto) modalManifiesto.show();
        })
        .catch(err => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    function poblarManifiesto(m) {
        const s = m.salida;
        document.getElementById('manifCorrelativo').textContent = s.correlativo;
        document.getElementById('manifEstadoBadge').textContent = s.estado;
        document.getElementById('manifServicio').textContent = s.titulo;
        document.getElementById('manifFecha').textContent = s.fecha_salida;
        document.getElementById('manifHora').textContent = `${s.hora_citacion.substring(0, 5)} / ${s.hora_salida.substring(0, 5)}`;
        document.getElementById('manifPunto').textContent = s.punto_encuentro;

        // Recursos asignados
        const transporte = m.recursos.find(r => r.rol_operativo === 'TRANSPORTE_PRINCIPAL');
        const guia = m.recursos.find(r => r.rol_operativo === 'GUIA_OFICIAL');

        document.getElementById('manifTransporte').textContent = transporte ? 'Asignado' : 'Sin asignar';
        document.getElementById('manifGuia').textContent = guia ? 'Asignado' : 'Sin asignar';

        // Resumen
        document.getElementById('manifTotalPax').textContent = m.resumen.total_pax;
        document.getElementById('manifPresentes').textContent = m.resumen.presentes;
        document.getElementById('manifNoShow').textContent = m.resumen.no_show;
        document.getElementById('manifPendientes').textContent = m.resumen.pendientes;

        // Pasajeros
        const tbody = document.getElementById('tbodyManifiestoPasajeros');
        tbody.innerHTML = '';

        if (m.pasajeros.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">No hay pasajeros asignados a esta salida.</td></tr>';
            return;
        }

        m.pasajeros.forEach(p => {
            const tr = document.createElement('tr');
            let estadoBadge = '<span class="badge bg-secondary">PENDIENTE</span>';
            if (p.estado_asistencia === 'PRESENTE') estadoBadge = '<span class="badge bg-success">PRESENTE</span>';
            else if (p.estado_asistencia === 'NO_SHOW') estadoBadge = '<span class="badge bg-danger">NO_SHOW</span>';

            tr.innerHTML = `
                <td class="text-center font-monospace f-w-700">${escaparHtml(p.ubicacion_asiento || '-')}</td>
                <td><strong class="text-dark">${escaparHtml(p.apellidos)}, ${escaparHtml(p.nombres)}</strong></td>
                <td>${escaparHtml(p.numero_documento)}</td>
                <td class="text-center">${escaparHtml(p.nacionalidad || '-')}</td>
                <td>${escaparHtml(p.rango_etario || '-')}</td>
                <td>
                    <span class="badge bg-light text-dark border">${escaparHtml(p.regimen_alimentario || 'ESTANDAR')}</span>
                    ${p.requiere_asistencia_movilidad ? '<span class="badge bg-warning text-dark ms-1">Movilidad</span>' : ''}
                </td>
                <td>${escaparHtml(p.telefono_contacto || '-')}</td>
                <td><span class="badge bg-light text-dark">${escaparHtml(p.reserva_correlativo)}</span></td>
                <td class="text-center">${estadoBadge}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // 6. Check-in Táctil de Campo
    document.addEventListener('click', function (e) {
        const btnCheckin = e.target.closest('.btnAbrirCheckin');
        if (btnCheckin) {
            const id = btnCheckin.getAttribute('data-id');
            abrirCheckinCampo(id);
        }
    });

    function abrirCheckinCampo(salidaId) {
        salidaActualId = salidaId;

        fetch(`${URL_API_OPERACIONES}/salidas/${salidaId}/checkin`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'Error al cargar check-in');
            poblarCheckinCampo(data.datos);
            if (modalCheckin) modalCheckin.show();
        })
        .catch(err => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    function poblarCheckinCampo(m) {
        const s = m.salida;
        document.getElementById('checkinSalidaCorrelativo').textContent = s.correlativo;

        const bEstado = document.getElementById('checkinEstadoSalidaBadge');
        bEstado.textContent = s.estado;
        bEstado.className = 'badge ' + (
            s.estado === 'PROGRAMADA' ? 'bg-info text-dark' :
            s.estado === 'DESPACHADA' ? 'bg-warning text-dark' :
            s.estado === 'FINALIZADA' ? 'bg-success' : 'bg-secondary'
        );

        document.getElementById('checkinTotalPresentes').textContent = m.resumen.presentes;
        document.getElementById('checkinTotalNoShow').textContent = m.resumen.no_show;
        document.getElementById('checkinTotalPendientes').textContent = m.resumen.pendientes;

        // Botones de ejecución según el estado real de la salida (Sin inventar máquinas paralelas)
        const contAcciones = document.getElementById('contenedorAccionesEjecucion');
        contAcciones.innerHTML = '';

        if (s.estado === 'PROGRAMADA') {
            contAcciones.innerHTML = `
                <button type="button" class="btn btn-success btn-sm btnAccionEjecutar" data-accion="despachar" data-version="${s.version_bloqueo}">
                    <i class="fa-solid fa-anchor me-1"></i> Despachar Salida
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm btnAccionEjecutar" data-accion="cancelar" data-version="${s.version_bloqueo}">
                    <i class="fa-solid fa-ban me-1"></i> Cancelar Salida
                </button>
            `;
        } else if (s.estado === 'DESPACHADA') {
            contAcciones.innerHTML = `
                <button type="button" class="btn btn-primary btn-sm btnAccionEjecutar" data-accion="finalizar" data-version="${s.version_bloqueo}">
                    <i class="fa-solid fa-flag-checkered me-1"></i> Finalizar Salida
                </button>
                <button type="button" class="btn btn-warning btn-sm btnAccionEjecutar" data-accion="interrumpir" data-version="${s.version_bloqueo}">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Interrumpir
                </button>
            `;
        }

        // Renderizar lista táctil de pasajeros
        const contPasajeros = document.getElementById('listaPasajerosCheckin');
        contPasajeros.innerHTML = '';

        if (m.pasajeros.length === 0) {
            contPasajeros.innerHTML = '<div class="p-4 text-center text-muted bg-light b-r-8">No hay pasajeros asignados a esta salida.</div>';
            return;
        }

        m.pasajeros.forEach(p => {
            const card = document.createElement('div');
            card.className = 'card border b-r-8 p-3 shadow-none bg-white itemPasajeroCheckin';
            card.setAttribute('data-busqueda', `${p.nombres} ${p.apellidos} ${p.numero_documento}`.toLowerCase());

            const esDiscreta = s.tipo_capacidad === 'DISCRETA';

            card.innerHTML = `
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-3">
                        ${esDiscreta ? `
                        <div style="width: 70px;">
                            <input type="text" class="form-control form-control-sm text-center font-monospace inputAsientoCheckin" 
                                placeholder="Asiento" value="${escaparHtml(p.ubicacion_asiento || '')}" 
                                data-part-id="${p.participante_id}">
                        </div>
                        ` : ''}
                        <div>
                            <h6 class="f-w-700 text-dark mb-0">${escaparHtml(p.apellidos)}, ${escaparHtml(p.nombres)}</h6>
                            <small class="text-muted">${escaparHtml(p.numero_documento)} &bull; Nac: ${escaparHtml(p.nacionalidad || 'PE')} &bull; ${escaparHtml(p.reserva_correlativo)}</small>
                            ${p.regimen_alimentario && p.regimen_alimentario !== 'ESTANDAR' ? `<span class="badge bg-light-warning text-dark ms-1">${p.regimen_alimentario}</span>` : ''}
                        </div>
                    </div>
                    <!-- Botones táctiles de 1 toque (Solo estados válidos: PRESENTE / NO_SHOW / PENDIENTE) -->
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btnMarcarAsistencia ${p.estado_asistencia === 'PRESENTE' ? 'btn-success' : 'btn-outline-success'}" 
                            data-part-id="${p.participante_id}" data-estado="PRESENTE">
                            <i class="fa-solid fa-check me-1"></i> PRESENTE
                        </button>
                        <button type="button" class="btn btn-sm btnMarcarAsistencia ${p.estado_asistencia === 'NO_SHOW' ? 'btn-danger' : 'btn-outline-danger'}" 
                            data-part-id="${p.participante_id}" data-estado="NO_SHOW">
                            <i class="fa-solid fa-xmark me-1"></i> NO SHOW
                        </button>
                        <button type="button" class="btn btn-sm btnMarcarAsistencia ${p.estado_asistencia === 'PENDIENTE' ? 'btn-secondary' : 'btn-outline-secondary'}" 
                            data-part-id="${p.participante_id}" data-estado="PENDIENTE">
                            PENDIENTE
                        </button>
                    </div>
                </div>
            `;
            contPasajeros.appendChild(card);
        });
    }

    // Buscador rápido táctil en modal check-in
    const inputBuscarCheckin = document.getElementById('buscarPasajeroCheckin');
    if (inputBuscarCheckin) {
        inputBuscarCheckin.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.itemPasajeroCheckin');
            items.forEach(it => {
                const texto = it.getAttribute('data-busqueda') || '';
                it.style.display = texto.includes(query) ? '' : 'none';
            });
        });
    }

    // Marcar asistencia en campo
    document.addEventListener('click', function (e) {
        const btnAsist = e.target.closest('.btnMarcarAsistencia');
        if (btnAsist) {
            const partId = btnAsist.getAttribute('data-part-id');
            const nuevoEstado = btnAsist.getAttribute('data-estado');

            // Asiento si existe input
            const inputAsiento = document.querySelector(`.inputAsientoCheckin[data-part-id="${partId}"]`);
            const asiento = inputAsiento ? inputAsiento.value.trim() : null;

            ejecutarCheckin(salidaActualId, partId, nuevoEstado, asiento);
        }
    });

    function ejecutarCheckin(salidaId, partId, nuevoEstado, asiento) {
        fetch(`${URL_API_OPERACIONES}/salidas/${salidaId}/checkin`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({
                _csrf_token: CSRF_TOKEN,
                participante_id: parseInt(partId),
                estado_asistencia: nuevoEstado,
                ubicacion_asiento: asiento,
            })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'Error al registrar check-in');
            abrirCheckinCampo(salidaId);
            cargarSalidas();
        })
        .catch(err => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error Check-in', text: err.message });
            }
        });
    }

    // Transiciones de ejecución de la salida (Despachar, Finalizar, Interrumpir, Cancelar)
    document.addEventListener('click', function (e) {
        const btnAccion = e.target.closest('.btnAccionEjecutar');
        if (btnAccion) {
            const accion = btnAccion.getAttribute('data-accion');
            const version = parseInt(btnAccion.getAttribute('data-version') || 1);

            if (accion === 'despachar') {
                confirmarEjecucion('¿Despachar Salida a Campo?', 'La salida transicionará a DESPACHADA registrando la hora real de inicio de actividades.', 'despachar', version);
            } else if (accion === 'finalizar') {
                confirmarEjecucion('¿Finalizar Salida Operativa?', 'La salida culminará exitosamente registrando la hora de término de campo.', 'finalizar', version);
            } else if (accion === 'interrumpir') {
                solicitarMotivoEjecucion('Interrumpir Salida Operativa', 'interrumpir', version);
            } else if (accion === 'cancelar') {
                solicitarMotivoEjecucion('Cancelar Salida Operativa', 'cancelar', version);
            }
        }
    });

    function confirmarEjecucion(titulo, texto, accion, version) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: titulo,
                text: texto,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, confirmar',
                cancelButtonText: 'Volver'
            }).then(result => {
                if (result.isConfirmed) {
                    enviarTransicionSalida(salidaActualId, accion, { version_bloqueo: version });
                }
            });
        }
    }

    function solicitarMotivoEjecucion(titulo, accion, version) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: titulo,
                input: 'text',
                inputPlaceholder: 'Ingrese el motivo justificado...',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Confirmar',
                cancelButtonText: 'Volver',
                confirmButtonColor: '#d33',
                inputValidator: (v) => { if (!v || v.trim() === '') return 'El motivo es obligatorio.'; }
            }).then(result => {
                if (result.isConfirmed) {
                    enviarTransicionSalida(salidaActualId, accion, { version_bloqueo: version, motivo: result.value.trim() });
                }
            });
        }
    }

    function enviarTransicionSalida(salidaId, accion, payloadExtra) {
        const payload = Object.assign({ _csrf_token: CSRF_TOKEN }, payloadExtra);

        fetch(`${URL_API_OPERACIONES}/salidas/${salidaId}/${accion}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'Error en transición de salida');
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'success', title: 'Operación Actualizada', text: data.mensaje });
            }
            abrirCheckinCampo(salidaId);
            cargarSalidas();
        })
        .catch(err => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    // 7. Incidencias Operativas
    document.addEventListener('click', function (e) {
        const btnInc = e.target.closest('.btnIncidenciasSalida');
        if (btnInc) {
            const id = btnInc.getAttribute('data-id');
            const count = parseInt(btnInc.getAttribute('data-count') || 0);

            if (count > 0) {
                abrirVerIncidencias(id);
            } else {
                abrirRegistrarIncidencia(id);
            }
        }
    });

    function abrirRegistrarIncidencia(salidaId) {
        document.getElementById('incidenciaSalidaId').value = salidaId;
        document.getElementById('incTipo').value = '';
        document.getElementById('incDescripcion').value = '';
        document.getElementById('incAcciones').value = '';
        document.getElementById('incAfecto').checked = false;

        if (modalIncidencia) modalIncidencia.show();
    }

    function abrirVerIncidencias(salidaId) {
        fetch(`${URL_API_OPERACIONES}/salidas/${salidaId}/incidencias`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            const lista = data.datos.incidencias || [];
            const cont = document.getElementById('listaIncidenciasSalida');
            cont.innerHTML = '';

            lista.forEach(inc => {
                const item = document.createElement('div');
                item.className = 'card border b-r-8 p-3 bg-light shadow-none';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-danger">${escaparHtml(inc.tipo_incidencia)}</span>
                        <small class="text-muted">${inc.registrado_en}</small>
                    </div>
                    <p class="mb-1 text-dark f-w-600">${escaparHtml(inc.descripcion)}</p>
                    ${inc.acciones_tomadas ? `<small class="text-secondary d-block"><strong>Acciones:</strong> ${escaparHtml(inc.acciones_tomadas)}</small>` : ''}
                    ${inc.afecto_continuidad ? '<span class="badge bg-warning text-dark mt-2">Afectó Continuidad</span>' : ''}
                `;
                cont.appendChild(item);
            });

            // Botón para agregar una nueva desde este modal
            const divBoton = document.createElement('div');
            divBoton.className = 'text-end mt-2';
            divBoton.innerHTML = `
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnNuevaIncidenciaDesdeModal">
                    <i class="fa-solid fa-plus me-1"></i> Registrar Otra Incidencia
                </button>
            `;
            cont.appendChild(divBoton);

            document.getElementById('btnNuevaIncidenciaDesdeModal').addEventListener('click', () => {
                if (modalVerIncidencias) modalVerIncidencias.hide();
                abrirRegistrarIncidencia(salidaId);
            });

            if (modalVerIncidencias) modalVerIncidencias.show();
        });
    }

    const formIncidencia = document.getElementById('formIncidenciaOperativa');
    if (formIncidencia) {
        formIncidencia.addEventListener('submit', function (e) {
            e.preventDefault();
            const btnGuardar = document.getElementById('btnGuardarIncidencia');
            const salidaId = document.getElementById('incidenciaSalidaId').value;

            const payload = {
                _csrf_token: CSRF_TOKEN,
                tipo_incidencia: document.getElementById('incTipo').value,
                descripcion: document.getElementById('incDescripcion').value,
                acciones_tomadas: document.getElementById('incAcciones').value,
                afecto_continuidad: document.getElementById('incAfecto').checked ? 1 : 0,
            };

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Registrando...';

            fetch(`${URL_API_OPERACIONES}/salidas/${salidaId}/incidencias`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (!data.exito) throw new Error(data.mensaje || 'Error al registrar incidencia');
                if (modalIncidencia) modalIncidencia.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Incidencia Registrada', text: 'El evento ha sido asentado en la bitácora de campo.' });
                }
                cargarSalidas();
            })
            .catch(err => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            })
            .finally(() => {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fa-solid fa-save me-1"></i> Registrar Incidencia';
            });
        });
    }

    // 8. Filtros y Recarga
    if (btnAplicarFiltros) btnAplicarFiltros.addEventListener('click', cargarSalidas);
    if (btnLimpiarFiltros) {
        btnLimpiarFiltros.addEventListener('click', function () {
            if (filtroBusqueda) filtroBusqueda.value = '';
            if (filtroFecha && fpFiltroFecha) fpFiltroFecha.clear();
            if (filtroEstado) filtroEstado.value = '';
            if (filtroServicio) filtroServicio.value = '';
            cargarSalidas();
        });
    }
    if (btnRecargarTabla) btnRecargarTabla.addEventListener('click', cargarSalidas);

    // Helpers
    function cargarServiciosComerciales() {
        fetch(`${URL_API_OPERACIONES}/aux/servicios`, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(data => {
            const servicios = data.datos.servicios || [];
            const selFiltro = document.getElementById('filtroServicio');
            const selForm = document.getElementById('salidaServicioId');

            servicios.forEach(s => {
                if (selFiltro) {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = `${s.nombre} (${s.codigo})`;
                    selFiltro.appendChild(opt);
                }
                if (selForm) {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = `${s.nombre} (${s.codigo})`;
                    selForm.appendChild(opt);
                }
            });
        });
    }

    function escaparHtml(texto) {
        if (!texto) return '';
        const mapa = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(texto).replace(/[&<>"']/g, m => mapa[m]);
    }
});
