/**
 * ==============================================================================
 * CANDELARIAAPP - JAVASCRIPT OFICIAL: RESERVAS Y AGENDAMIENTO (reservas.js)
 * FASE 2.6E — Integración Alina UI, DataTables Asíncrono, Skeleton y SweetAlert2
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // 1. Constantes y Endpoints
    const URL_API_RESERVAS = window.CANDELARIA_BASE_URL + 'api/v1/reservas';
    const CSRF_TOKEN = window.CANDELARIA_CSRF_TOKEN || '';

    // Elementos DOM principales
    const skeletonTabla = document.getElementById('skeletonReservas');
    const contenedorTabla = document.getElementById('contenedorTablaReservas');
    const tablaElemento = document.getElementById('tablaReservas');

    // Filtros
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroProgramacion = document.getElementById('filtroProgramacion');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const btnAplicarFiltros = document.getElementById('btnAplicarFiltros');
    const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltros');
    const btnRecargarTabla = document.getElementById('btnRecargarTabla');

    // Modales Bootstrap
    const modalDetalleEl = document.getElementById('modalDetalleReserva');
    const modalDetalle = modalDetalleEl ? new bootstrap.Modal(modalDetalleEl) : null;
    const modalFormalizarEl = document.getElementById('modalFormalizar');
    const modalFormalizar = modalFormalizarEl ? new bootstrap.Modal(modalFormalizarEl) : null;
    const modalProgramarEl = document.getElementById('modalProgramar');
    const modalProgramar = modalProgramarEl ? new bootstrap.Modal(modalProgramarEl) : null;
    const modalReprogramarEl = document.getElementById('modalReprogramar');
    const modalReprogramar = modalReprogramarEl ? new bootstrap.Modal(modalReprogramarEl) : null;
    const modalAgregarPartEl = document.getElementById('modalAgregarParticipante');
    const modalAgregarPart = modalAgregarPartEl ? new bootstrap.Modal(modalAgregarPartEl) : null;

    let dataTableInstancia = null;
    let reservaActualId = null;
    let tiposDocumentoCache = [];
    let fpFechaDesde = null;
    let fpProgFecha = null;
    let fpReprogFecha = null;

    // Inicializar Flatpickr
    if (typeof flatpickr !== 'undefined') {
        if (filtroFechaDesde) {
            fpFechaDesde = flatpickr(filtroFechaDesde, { dateFormat: 'Y-m-d' });
        }
        const progFechaInput = document.getElementById('progFechaServicio');
        if (progFechaInput) {
            fpProgFecha = flatpickr(progFechaInput, { dateFormat: 'Y-m-d' });
        }
        const reprogFechaInput = document.getElementById('reprogNuevaFecha');
        if (reprogFechaInput) {
            fpReprogFecha = flatpickr(reprogFechaInput, { dateFormat: 'Y-m-d' });
        }
    }

    // Cargar tipos de documento
    cargarTiposDocumento();

    // 2. Inicialización de la Tabla de Reservas
    cargarReservas();

    function cargarReservas() {
        if (skeletonTabla) skeletonTabla.classList.remove('d-none');
        if (contenedorTabla) contenedorTabla.classList.add('d-none');

        const params = new URLSearchParams();
        if (filtroBusqueda && filtroBusqueda.value.trim() !== '') params.append('busqueda', filtroBusqueda.value.trim());
        if (filtroEstado && filtroEstado.value !== '') params.append('estado', filtroEstado.value);
        if (filtroProgramacion && filtroProgramacion.value !== '') params.append('programacion', filtroProgramacion.value);
        if (filtroFechaDesde && filtroFechaDesde.value !== '') params.append('fecha_desde', filtroFechaDesde.value);

        fetch(URL_API_RESERVAS + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(res => {
            if (!res.exito) throw new Error(res.mensaje || 'Error al cargar reservas');
            renderizarTabla(res.datos.reservas || []);
            actualizarKpis(res.datos.reservas || []);
        })
        .catch(err => {
            console.error(err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Carga',
                    text: err.message || 'No se pudieron recuperar las reservas.'
                });
            }
        })
        .finally(() => {
            if (skeletonTabla) skeletonTabla.classList.add('d-none');
            if (contenedorTabla) contenedorTabla.classList.remove('d-none');
        });
    }

    function renderizarTabla(reservas) {
        if (dataTableInstancia) {
            dataTableInstancia.destroy();
            dataTableInstancia = null;
        }

        const tbody = tablaElemento.querySelector('tbody');
        tbody.innerHTML = '';

        reservas.forEach(r => {
            const tr = document.createElement('tr');

            // Badge de Estado Alina
            let badgeClase = 'bg-secondary';
            if (r.estado === 'CONFIRMADA') badgeClase = 'bg-success';
            else if (r.estado === 'PENDIENTE_DATOS') badgeClase = 'bg-warning text-dark';
            else if (r.estado === 'CANCELADA') badgeClase = 'bg-danger';
            else if (r.estado === 'REGISTRADA') badgeClase = 'bg-info text-dark';

            // Prestaciones ratio
            const progTotal = `${r.prestaciones_programadas || 0} / ${r.total_prestaciones || 0}`;
            const progBadge = (parseInt(r.prestaciones_pendientes || 0) === 0 && parseInt(r.total_prestaciones || 0) > 0)
                ? '<span class="badge bg-light-success text-success"><i class="fa-solid fa-check me-1"></i> 100%</span>'
                : `<span class="badge bg-light-warning text-dark">${r.prestaciones_pendientes || 0} pend.</span>`;

            tr.innerHTML = `
                <td>
                    <strong class="text-primary f-s-13">${escaparHtml(r.correlativo)}</strong>
                </td>
                <td>
                    <span class="badge bg-light text-dark border">${escaparHtml(r.venta_correlativo || '-')}</span>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span class="f-w-600 text-dark f-s-13">${escaparHtml(r.contacto_nombre)}</span>
                        <small class="text-muted f-s-11">${escaparHtml(r.contacto_numero_documento || '')} &bull; ${escaparHtml(r.contacto_telefono || '')}</small>
                    </div>
                </td>
                <td>
                    <span class="badge ${badgeClase}">${escaparHtml(r.estado)}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <span class="f-s-12">${progTotal}</span>
                        ${progBadge}
                    </div>
                </td>
                <td>
                    <span class="badge bg-light-info text-info"><i class="fa-solid fa-users me-1"></i> ${r.total_participantes || 0}</span>
                </td>
                <td>
                    <span class="f-s-12 text-muted">${(r.creado_en || '').substring(0, 16)}</span>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-primary btnVerDetalle" data-id="${r.id}" title="Ver Detalle 360">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    ${r.estado !== 'CANCELADA' ? `
                    <button type="button" class="btn btn-sm btn-outline-danger btnCancelarReserva ms-1" data-id="${r.id}" data-correlativo="${escaparHtml(r.correlativo)}" title="Cancelar Reserva">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                    ` : ''}
                </td>
            `;
            tbody.appendChild(tr);
        });

        if (typeof $.fn.DataTable !== 'undefined') {
            dataTableInstancia = $(tablaElemento).DataTable({
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Buscar en tabla...",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ reservas",
                    infoEmpty: "Mostrando 0 a 0 de 0 reservas",
                    infoFiltered: "(filtrado de _MAX_ registros totales)",
                    zeroRecords: "No se encontraron reservas que coincidan con la búsqueda",
                    paginate: {
                        first: "Primero",
                        previous: "Anterior",
                        next: "Siguiente",
                        last: "Último"
                    }
                },
                order: [[6, 'desc']],
                pageLength: 10,
            });
        }
    }

    function actualizarKpis(reservas) {
        let total = reservas.length;
        let confirmadas = 0;
        let pendienteDatos = 0;
        let canceladas = 0;

        reservas.forEach(r => {
            if (r.estado === 'CONFIRMADA') confirmadas++;
            else if (r.estado === 'PENDIENTE_DATOS') pendienteDatos++;
            else if (r.estado === 'CANCELADA') canceladas++;
        });

        const elTotal = document.getElementById('kpiTotalReservas');
        const elConf = document.getElementById('kpiConfirmadas');
        const elPend = document.getElementById('kpiPendienteDatos');
        const elCanc = document.getElementById('kpiCanceladas');

        if (elTotal) elTotal.textContent = total;
        if (elConf) elConf.textContent = confirmadas;
        if (elPend) elPend.textContent = pendienteDatos;
        if (elCanc) elCanc.textContent = canceladas;
    }

    // 3. Detalle 360 de Reserva
    document.addEventListener('click', function (e) {
        const btnDetalle = e.target.closest('.btnVerDetalle');
        if (btnDetalle) {
            const id = btnDetalle.getAttribute('data-id');
            abrirDetalle360(id);
        }

        const btnCancelar = e.target.closest('.btnCancelarReserva');
        if (btnCancelar) {
            const id = btnCancelar.getAttribute('data-id');
            const correlativo = btnCancelar.getAttribute('data-correlativo');
            confirmarCancelacionReserva(id, correlativo);
        }
    });

    function abrirDetalle360(reservaId) {
        reservaActualId = reservaId;
        const skeleton = document.getElementById('skeletonDetalleReserva');
        const contenido = document.getElementById('contenidoDetalleReserva');

        if (skeleton) skeleton.classList.remove('d-none');
        if (contenido) contenido.classList.add('d-none');
        if (modalDetalle) modalDetalle.show();

        fetch(`${URL_API_RESERVAS}/${reservaId}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(res => {
            if (!res.exito) throw new Error(res.mensaje || 'Error al cargar detalle');
            poblarDetalle360(res.datos.reserva);
        })
        .catch(err => {
            console.error(err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: err.message || 'No se pudo cargar el detalle de la reserva.'
                });
            }
        })
        .finally(() => {
            if (skeleton) skeleton.classList.add('d-none');
            if (contenido) contenido.classList.remove('d-none');
        });
    }

    function poblarDetalle360(r) {
        document.getElementById('detalleCorrelativo').textContent = r.correlativo;
        const badgeEstado = document.getElementById('detalleEstadoBadge');
        badgeEstado.textContent = r.estado;
        badgeEstado.className = 'badge ' + (
            r.estado === 'CONFIRMADA' ? 'bg-success' :
            r.estado === 'PENDIENTE_DATOS' ? 'bg-warning text-dark' :
            r.estado === 'CANCELADA' ? 'bg-danger' : 'bg-info text-dark'
        );

        // General
        document.getElementById('detTitularNombre').textContent = r.contacto_nombre;
        document.getElementById('detTitularDoc').textContent = r.contacto_numero_documento || '-';
        document.getElementById('detTitularTel').textContent = r.contacto_telefono || '-';
        document.getElementById('detTitularEmail').textContent = r.contacto_email || '-';
        document.getElementById('detNotasOperativas').textContent = r.notas_operativas || 'Sin notas adicionales.';

        // Venta
        if (r.venta) {
            document.getElementById('detVentaCorrelativo').textContent = r.venta.correlativo;
            document.getElementById('detVentaFecha').textContent = r.venta.fecha_venta;
            document.getElementById('detVentaTotal').textContent = `${r.venta.moneda} ${parseFloat(r.venta.total).toFixed(2)}`;
            document.getElementById('detVentaEstado').textContent = r.venta.estado;
        }

        // Prestaciones
        const listaPrestaciones = document.getElementById('listaPrestacionesContenedor');
        listaPrestaciones.innerHTML = '';
        document.getElementById('tabCountPrestaciones').textContent = r.prestaciones.length;

        r.prestaciones.forEach(p => {
            const card = document.createElement('div');
            card.className = 'card border b-r-8 p-3 shadow-none bg-white';

            let progBadge = p.estado_agendamiento === 'PROGRAMADA'
                ? '<span class="badge bg-success">PROGRAMADA</span>'
                : '<span class="badge bg-warning text-dark">PENDIENTE_PROGRAMAR</span>';

            let infoFecha = p.estado_agendamiento === 'PROGRAMADA'
                ? `<div class="mt-2 text-dark f-s-13">
                     <i class="fa-solid fa-calendar-day text-primary me-1"></i> <strong>Fecha:</strong> ${p.fecha_servicio || '-'}
                     ${p.hora_servicio ? `&bull; <i class="fa-solid fa-clock text-secondary me-1"></i> ${p.hora_servicio}` : ''}
                     ${p.punto_encuentro ? `&bull; <i class="fa-solid fa-location-dot text-danger me-1"></i> ${escaparHtml(p.punto_encuentro)}` : ''}
                   </div>`
                : `<div class="mt-2 text-warning f-s-13"><i class="fa-solid fa-triangle-exclamation me-1"></i> Turno pendiente de agendar</div>`;

            let botonAccion = '';
            if (r.estado !== 'CANCELADA') {
                if (p.estado_agendamiento === 'PENDIENTE_PROGRAMAR') {
                    botonAccion = `<button type="button" class="btn btn-sm btn-primary btnAbrirProgramar" 
                        data-id="${p.id}" data-concepto="${escaparHtml(p.concepto_nombre)}">
                        <i class="fa-solid fa-calendar-plus me-1"></i> Programar
                    </button>`;
                } else if (p.estado_agendamiento === 'PROGRAMADA') {
                    botonAccion = `<button type="button" class="btn btn-sm btn-outline-warning btnAbrirReprogramar" 
                        data-id="${p.id}" data-concepto="${escaparHtml(p.concepto_nombre)}" data-fecha="${p.fecha_servicio || ''}" data-hora="${p.hora_servicio || ''}">
                        <i class="fa-solid fa-arrows-rotate me-1"></i> Reprogramar
                    </button>`;
                }
            }

            // Historial de reprogramaciones
            let historialHtml = '';
            if (p.reprogramaciones && p.reprogramaciones.length > 0) {
                historialHtml = `
                    <div class="mt-3 pt-2 border-top">
                        <span class="f-s-12 text-muted f-w-600 d-block mb-1">
                            <i class="fa-solid fa-clock-rotate-left me-1"></i> Historial de Reprogramaciones (${p.reprogramaciones.length}):
                        </span>
                        <ul class="list-unstyled mb-0 f-s-12 text-secondary ps-2 border-start border-2 border-warning">
                            ${p.reprogramaciones.map(rep => `
                                <li class="mb-1">
                                    <span class="f-w-600 text-dark">${rep.fecha_nueva}</span> (Antes: ${rep.fecha_anterior || 'Inicial'}) &bull;
                                    <span class="badge bg-light text-dark border">${rep.motivo_categoria}</span>: ${escaparHtml(rep.motivo_detalle)}
                                    <small class="text-muted">(${rep.creado_en.substring(0, 16)})</small>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                `;
            }

            // Vínculo con salida operativa si existe
            let vinculoSalidaHtml = '';
            if (p.salida_asignada) {
                vinculoSalidaHtml = `
                    <div class="mt-2 p-2 bg-light-primary b-r-6 d-flex justify-content-between align-items-center">
                        <span class="f-s-12 text-primary f-w-600">
                            <i class="fa-solid fa-route me-1"></i> Asignada a Salida: ${escaparHtml(p.salida_asignada.correlativo)} (${p.salida_asignada.fecha_salida})
                        </span>
                        <span class="badge bg-primary">${p.salida_asignada.estado}</span>
                    </div>
                `;
            }

            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="badge bg-light text-dark border mb-1">${escaparHtml(p.concepto_codigo)}</span>
                        <h6 class="f-w-700 text-dark mb-1">${escaparHtml(p.concepto_nombre)}</h6>
                        <span class="f-s-12 text-muted">Cantidad: <strong>${p.cantidad} ${escaparHtml(p.unidad_medida)}</strong> &bull; Capacidad: <strong>${p.tipo_capacidad}</strong></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        ${progBadge}
                        ${botonAccion}
                    </div>
                </div>
                ${infoFecha}
                ${vinculoSalidaHtml}
                ${historialHtml}
            `;
            listaPrestaciones.appendChild(card);
        });

        // Participantes
        const tbodyPart = document.getElementById('tbodyParticipantes');
        tbodyPart.innerHTML = '';
        document.getElementById('tabCountParticipantes').textContent = r.participantes.length;

        r.participantes.forEach((part, idx) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${idx + 1}</td>
                <td>
                    <span class="f-w-600 text-dark">${escaparHtml(part.nombres)} ${escaparHtml(part.apellidos)}</span>
                </td>
                <td>${escaparHtml(part.numero_documento)}</td>
                <td><span class="badge bg-light text-dark">${escaparHtml(part.nacionalidad || '-')}</span></td>
                <td>${escaparHtml(part.rango_etario || '-')}</td>
                <td><span class="badge bg-light-secondary text-secondary">${escaparHtml(part.regimen_alimentario || 'ESTANDAR')}</span></td>
                <td>${escaparHtml(part.telefono_contacto || '-')}</td>
                <td>${part.es_titular_reserva ? '<span class="badge bg-success">TITULAR</span>' : '-'}</td>
                <td class="text-center">
                    ${r.estado !== 'CANCELADA' ? `
                    <button type="button" class="btn btn-sm btn-outline-danger btnEliminarParticipante" 
                        data-part-id="${part.id}" data-nombre="${escaparHtml(part.nombres + ' ' + part.apellidos)}" title="Eliminar Pasajero">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                    ` : '-'}
                </td>
            `;
            tbodyPart.appendChild(tr);
        });
    }

    // 4. Modal Formalizar Reserva desde Venta
    const btnAbrirFormalizar = document.getElementById('btnAbrirModalFormalizar');
    if (btnAbrirFormalizar) {
        btnAbrirFormalizar.addEventListener('click', function () {
            const selectVenta = document.getElementById('selectVentaFormalizar');
            selectVenta.innerHTML = '<option value="">Cargando ventas confirmadas...</option>';
            document.getElementById('infoVentaSeleccionada').classList.add('d-none');
            if (modalFormalizar) modalFormalizar.show();

            fetch(URL_API_RESERVAS + '/aux/ventas-confirmadas', {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                selectVenta.innerHTML = '<option value="">Seleccione una venta confirmada</option>';
                const ventas = data.datos.ventas || [];
                if (ventas.length === 0) {
                    selectVenta.innerHTML = '<option value="">No hay ventas confirmadas pendientes de reserva</option>';
                    return;
                }
                ventas.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.id;
                    opt.textContent = `${v.correlativo} — ${v.cliente_nombre_completo} (${v.moneda} ${parseFloat(v.total).toFixed(2)})`;
                    opt.setAttribute('data-cliente', v.cliente_nombre_completo);
                    opt.setAttribute('data-total', `${v.moneda} ${parseFloat(v.total).toFixed(2)}`);
                    selectVenta.appendChild(opt);
                });
            })
            .catch(err => {
                selectVenta.innerHTML = '<option value="">Error al cargar ventas</option>';
            });
        });
    }

    const selectVentaFormalizar = document.getElementById('selectVentaFormalizar');
    if (selectVentaFormalizar) {
        selectVentaFormalizar.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            const infoDiv = document.getElementById('infoVentaSeleccionada');
            if (this.value) {
                document.getElementById('formVentaCliente').textContent = selected.getAttribute('data-cliente') || '-';
                document.getElementById('formVentaTotal').textContent = selected.getAttribute('data-total') || '-';
                infoDiv.classList.remove('d-none');
            } else {
                infoDiv.classList.add('d-none');
            }
        });
    }

    const btnEjecutarFormalizar = document.getElementById('btnEjecutarFormalizar');
    if (btnEjecutarFormalizar) {
        btnEjecutarFormalizar.addEventListener('click', function () {
            const ventaId = selectVentaFormalizar ? selectVentaFormalizar.value : '';
            if (!ventaId) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar una venta confirmada.' });
                }
                return;
            }

            btnEjecutarFormalizar.disabled = true;
            btnEjecutarFormalizar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Formalizando...';

            fetch(`${URL_API_RESERVAS}/desde-venta/${ventaId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({ _csrf_token: CSRF_TOKEN })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.exito) throw new Error(data.mensaje || 'Error al formalizar reserva');
                if (modalFormalizar) modalFormalizar.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reserva Formalizada',
                        text: data.mensaje || 'La reserva y compromisos de servicio se generaron exitosamente.'
                    });
                }
                cargarReservas();
            })
            .catch(err => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            })
            .finally(() => {
                btnEjecutarFormalizar.disabled = false;
                btnEjecutarFormalizar.innerHTML = '<i class="fa-solid fa-check me-1"></i> Formalizar Reserva';
            });
        });
    }

    // 5. Modal Programar Prestación
    document.addEventListener('click', function (e) {
        const btnProg = e.target.closest('.btnAbrirProgramar');
        if (btnProg) {
            const pId = btnProg.getAttribute('data-id');
            const concepto = btnProg.getAttribute('data-concepto');

            document.getElementById('progPrestacionId').value = pId;
            document.getElementById('progConceptoNombre').textContent = concepto;
            document.getElementById('progFechaServicio').value = '';
            document.getElementById('progHoraServicio').value = '';
            document.getElementById('progPuntoEncuentro').value = '';

            if (modalProgramar) modalProgramar.show();
        }
    });

    const formProgramar = document.getElementById('formProgramarPrestacion');
    if (formProgramar) {
        formProgramar.addEventListener('submit', function (e) {
            e.preventDefault();
            const pId = document.getElementById('progPrestacionId').value;
            const btnGuardar = document.getElementById('btnGuardarProgramacion');

            const payload = {
                _csrf_token: CSRF_TOKEN,
                fecha_servicio: document.getElementById('progFechaServicio').value,
                hora_servicio: document.getElementById('progHoraServicio').value,
                punto_encuentro: document.getElementById('progPuntoEncuentro').value,
            };

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            fetch(`${URL_API_RESERVAS}/prestaciones/${pId}/programar`, {
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
                if (!data.exito) throw new Error(data.mensaje || 'Error al programar prestación');
                if (modalProgramar) modalProgramar.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Turno Programado',
                        text: 'La prestación ha sido agendada formalmente.'
                    });
                }
                if (reservaActualId) abrirDetalle360(reservaActualId);
                cargarReservas();
            })
            .catch(err => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            })
            .finally(() => {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fa-solid fa-calendar-check me-1"></i> Guardar Programación';
            });
        });
    }

    // 6. Modal Reprogramar Prestación
    document.addEventListener('click', function (e) {
        const btnReprog = e.target.closest('.btnAbrirReprogramar');
        if (btnReprog) {
            const pId = btnReprog.getAttribute('data-id');
            const fecha = btnReprog.getAttribute('data-fecha') || '-';
            const hora = btnReprog.getAttribute('data-hora') || '';

            document.getElementById('reprogPrestacionId').value = pId;
            document.getElementById('reprogActualTexto').textContent = `${fecha} ${hora ? `(${hora})` : ''}`;
            document.getElementById('reprogNuevaFecha').value = '';
            document.getElementById('reprogNuevaHora').value = hora;
            document.getElementById('reprogMotivoCategoria').value = '';
            document.getElementById('reprogMotivoDetalle').value = '';

            if (modalReprogramar) modalReprogramar.show();
        }
    });

    const formReprogramar = document.getElementById('formReprogramarPrestacion');
    if (formReprogramar) {
        formReprogramar.addEventListener('submit', function (e) {
            e.preventDefault();
            const pId = document.getElementById('reprogPrestacionId').value;
            const btnGuardar = document.getElementById('btnGuardarReprogramacion');

            const payload = {
                _csrf_token: CSRF_TOKEN,
                nueva_fecha: document.getElementById('reprogNuevaFecha').value,
                nueva_hora: document.getElementById('reprogNuevaHora').value,
                motivo_categoria: document.getElementById('reprogMotivoCategoria').value,
                motivo_detalle: document.getElementById('reprogMotivoDetalle').value,
            };

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Reprogramando...';

            fetch(`${URL_API_RESERVAS}/prestaciones/${pId}/reprogramar`, {
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
                if (!data.exito) throw new Error(data.mensaje || 'Error al reprogramar prestación');
                if (modalReprogramar) modalReprogramar.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reprogramación Confirmada',
                        text: 'El nuevo turno y el historial inmutable han sido registrados exitosamente.'
                    });
                }
                if (reservaActualId) abrirDetalle360(reservaActualId);
                cargarReservas();
            })
            .catch(err => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            })
            .finally(() => {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fa-solid fa-check me-1"></i> Confirmar Reprogramación';
            });
        });
    }

    // 7. Modal Agregar Pasajero / Participante
    const btnAbrirPart = document.getElementById('btnAbrirModalAgregarParticipante');
    if (btnAbrirPart) {
        btnAbrirPart.addEventListener('click', function () {
            if (!reservaActualId) return;

            document.getElementById('partReservaId').value = reservaActualId;
            document.getElementById('partNombres').value = '';
            document.getElementById('partApellidos').value = '';
            document.getElementById('partNumeroDocumento').value = '';
            document.getElementById('partNacionalidad').value = 'PE';
            document.getElementById('partRangoEtario').value = 'ADULTO';
            document.getElementById('partRegimenAlimentario').value = 'ESTANDAR';
            document.getElementById('partTelefono').value = '';
            document.getElementById('partTalla').value = '';
            document.getElementById('partReqMovilidad').checked = false;
            document.getElementById('partEsTitular').checked = false;

            // Poblar select de tipos de documento
            const selDoc = document.getElementById('partTipoDocumento');
            selDoc.innerHTML = '';
            tiposDocumentoCache.forEach(td => {
                const opt = document.createElement('option');
                opt.value = td.id;
                opt.textContent = td.nombre;
                if (td.codigo === 'DNI') opt.selected = true;
                selDoc.appendChild(opt);
            });

            // Poblar checkboxes de prestaciones de la reserva actual
            const contenedorChecks = document.getElementById('checkPrestacionesParticipante');
            contenedorChecks.innerHTML = '';

            fetch(`${URL_API_RESERVAS}/${reservaActualId}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                const prestaciones = data.datos.reserva.prestaciones || [];
                prestaciones.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'form-check';
                    div.innerHTML = `
                        <input class="form-check-input checkPrestacionId" type="checkbox" value="${p.id}" id="checkPrest_${p.id}" checked>
                        <label class="form-check-label f-s-13" for="checkPrest_${p.id}">
                            ${escaparHtml(p.concepto_nombre)} (${p.cantidad} ${escaparHtml(p.unidad_medida)})
                        </label>
                    `;
                    contenedorChecks.appendChild(div);
                });
            });

            if (modalAgregarPart) modalAgregarPart.show();
        });
    }

    const formAgregarPart = document.getElementById('formAgregarParticipante');
    if (formAgregarPart) {
        formAgregarPart.addEventListener('submit', function (e) {
            e.preventDefault();
            const btnGuardar = document.getElementById('btnGuardarParticipante');
            const rId = document.getElementById('partReservaId').value;

            const selectedPrestaciones = Array.from(document.querySelectorAll('.checkPrestacionId:checked')).map(cb => parseInt(cb.value));

            const payload = {
                _csrf_token: CSRF_TOKEN,
                nombres: document.getElementById('partNombres').value,
                apellidos: document.getElementById('partApellidos').value,
                tipo_documento_id: parseInt(document.getElementById('partTipoDocumento').value),
                numero_documento: document.getElementById('partNumeroDocumento').value,
                nacionalidad: document.getElementById('partNacionalidad').value,
                rango_etario: document.getElementById('partRangoEtario').value,
                regimen_alimentario: document.getElementById('partRegimenAlimentario').value,
                telefono_contacto: document.getElementById('partTelefono').value,
                talla_indumentaria: document.getElementById('partTalla').value,
                requiere_asistencia_movilidad: document.getElementById('partReqMovilidad').checked ? 1 : 0,
                es_titular_reserva: document.getElementById('partEsTitular').checked ? 1 : 0,
                prestacion_ids: selectedPrestaciones,
            };

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            fetch(`${URL_API_RESERVAS}/${rId}/participantes`, {
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
                if (!data.exito) throw new Error(data.mensaje || 'Error al registrar participante');
                if (modalAgregarPart) modalAgregarPart.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Pasajero Registrado',
                        text: 'El participante fue agregado a la reserva exitosamente.'
                    });
                }
                abrirDetalle360(rId);
                cargarReservas();
            })
            .catch(err => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            })
            .finally(() => {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fa-solid fa-save me-1"></i> Guardar Pasajero';
            });
        });
    }

    // 8. Eliminar Pasajero
    document.addEventListener('click', function (e) {
        const btnEliminar = e.target.closest('.btnEliminarParticipante');
        if (btnEliminar) {
            const partId = btnEliminar.getAttribute('data-part-id');
            const nombre = btnEliminar.getAttribute('data-nombre');

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Eliminar Pasajero?',
                    text: `¿Confirma remover a ${nombre} de la reserva y sus prestaciones asociadas?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, remover',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#d33'
                }).then(result => {
                    if (result.isConfirmed) {
                        ejecutarEliminarParticipante(partId);
                    }
                });
            }
        }
    });

    function ejecutarEliminarParticipante(partId) {
        if (!reservaActualId) return;

        fetch(`${URL_API_RESERVAS}/${reservaActualId}/participantes/${partId}`, {
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
            if (!data.exito) throw new Error(data.mensaje || 'Error al eliminar participante');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Eliminado',
                    text: 'El pasajero ha sido removido exitosamente.'
                });
            }
            abrirDetalle360(reservaActualId);
            cargarReservas();
        })
        .catch(err => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    // 9. Cancelación de Reserva
    function confirmarCancelacionReserva(id, correlativo) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '¿Cancelar Reserva?',
                text: `Se cancelará la reserva ${correlativo} y todas sus prestaciones asociadas antes de su despacho a campo.`,
                icon: 'warning',
                input: 'text',
                inputPlaceholder: 'Ingrese el motivo de cancelación obligatorio...',
                showCancelButton: true,
                confirmButtonText: 'Sí, cancelar reserva',
                cancelButtonText: 'Volver',
                confirmButtonColor: '#d33',
                inputValidator: (value) => {
                    if (!value || value.trim() === '') {
                        return 'Debe ingresar un motivo explicativo.';
                    }
                }
            }).then(result => {
                if (result.isConfirmed) {
                    ejecutarCancelarReserva(id, result.value.trim());
                }
            });
        }
    }

    function ejecutarCancelarReserva(id, motivo) {
        fetch(`${URL_API_RESERVAS}/${id}/cancelar`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({
                _csrf_token: CSRF_TOKEN,
                motivo: motivo
            })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'Error al cancelar la reserva');
            if (modalDetalle) modalDetalle.hide();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Reserva Cancelada',
                    text: 'La reserva ha sido cancelada exitosamente.'
                });
            }
            cargarReservas();
        })
        .catch(err => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    const btnCancelarModal = document.getElementById('btnCancelarReservaModal');
    if (btnCancelarModal) {
        btnCancelarModal.addEventListener('click', function () {
            if (reservaActualId) {
                const correlativo = document.getElementById('detalleCorrelativo').textContent;
                confirmarCancelacionReserva(reservaActualId, correlativo);
            }
        });
    }

    // 10. Filtros y Recarga
    if (btnAplicarFiltros) btnAplicarFiltros.addEventListener('click', cargarReservas);
    if (btnLimpiarFiltros) {
        btnLimpiarFiltros.addEventListener('click', function () {
            if (filtroBusqueda) filtroBusqueda.value = '';
            if (filtroEstado) filtroEstado.value = '';
            if (filtroProgramacion) filtroProgramacion.value = '';
            if (filtroFechaDesde && fpFechaDesde) fpFechaDesde.clear();
            cargarReservas();
        });
    }
    if (btnRecargarTabla) btnRecargarTabla.addEventListener('click', cargarReservas);

    // Helpers
    function cargarTiposDocumento() {
        fetch(URL_API_RESERVAS + '/aux/tipos-documento', {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            tiposDocumentoCache = data.datos.tipos || [];
        })
        .catch(e => console.error('Error cargando tipos de documento', e));
    }

    function escaparHtml(texto) {
        if (!texto) return '';
        const mapa = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(texto).replace(/[&<>"']/g, m => mapa[m]);
    }
});
