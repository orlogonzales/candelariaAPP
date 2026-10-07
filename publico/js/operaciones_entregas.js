/**
 * ==============================================================================
 * CANDELARIAAPP - JAVASCRIPT: DESPACHO DE ENTREGAS Y PRODUCTOS FÍSICOS (operaciones_entregas.js)
 * FASE 2.6E — Gestión y Despacho de Órdenes de Entrega de Bienes Tangibles
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const URL_API_OPERACIONES = window.CANDELARIA_BASE_URL + 'api/v1/operaciones';
    const CSRF_TOKEN = window.CANDELARIA_CSRF_TOKEN || '';

    const modalDetalleEl = document.getElementById('modalDetalleEntrega');
    const modalDetalle = modalDetalleEl ? new bootstrap.Modal(modalDetalleEl) : null;

    let dtEntregas = null;
    let entregasCache = [];
    let entregaActual = null;

    // Elementos DOM
    const btnRecargar = document.getElementById('btnRecargarEntregas');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const btnDespacharDesdeModal = document.getElementById('btnDespacharDesdeModal');

    // Inicializar
    cargarEntregas();

    if (btnRecargar) {
        btnRecargar.addEventListener('click', () => cargarEntregas());
    }

    if (filtroEstado) {
        filtroEstado.addEventListener('change', () => cargarEntregas());
    }

    if (filtroBusqueda) {
        let timerBusqueda = null;
        filtroBusqueda.addEventListener('input', () => {
            clearTimeout(timerBusqueda);
            timerBusqueda = setTimeout(() => {
                cargarEntregas();
            }, 300);
        });
    }

    if (btnDespacharDesdeModal) {
        btnDespacharDesdeModal.addEventListener('click', () => {
            if (entregaActual) {
                confirmarDespacho(entregaActual.id, entregaActual.version_bloqueo);
            }
        });
    }

    // =========================================================================
    // 1. CARGA DE ENTREGAS
    // =========================================================================
    function cargarEntregas() {
        const estado = filtroEstado ? filtroEstado.value : '';
        const busqueda = filtroBusqueda ? filtroBusqueda.value.trim() : '';

        const params = new URLSearchParams();
        if (estado) params.append('estado', estado);
        if (busqueda) params.append('busqueda', busqueda);

        const url = `${URL_API_OPERACIONES}/entregas?${params.toString()}`;

        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                if (data.exito && data.datos && Array.isArray(data.datos.entregas)) {
                    entregasCache = data.datos.entregas;
                    actualizarKpis(entregasCache);
                    renderizarTablaEntregas(entregasCache);
                } else {
                    renderizarTablaEntregas([]);
                }
            })
            .catch(err => {
                console.error('Error cargando entregas:', err);
                renderizarTablaEntregas([]);
            });
    }

    // =========================================================================
    // 2. ACTUALIZACIÓN DE KPIS
    // =========================================================================
    function actualizarKpis(entregas) {
        const total = entregas.length;
        const pendientes = entregas.filter(e => e.estado === 'PENDIENTE').length;
        const entregados = entregas.filter(e => e.estado === 'ENTREGADO').length;

        const kpiTotal = document.getElementById('kpiTotalEntregas');
        const kpiPend = document.getElementById('kpiPendientesEntregas');
        const kpiComp = document.getElementById('kpiCompletadasEntregas');

        if (kpiTotal) kpiTotal.textContent = total;
        if (kpiPend) kpiPend.textContent = pendientes;
        if (kpiComp) kpiComp.textContent = entregados;
    }

    // =========================================================================
    // 3. RENDERIZACIÓN DE TABLA
    // =========================================================================
    function renderizarTablaEntregas(entregas) {
        const tabla = document.getElementById('tablaEntregas');
        if (!tabla) return;

        if (dtEntregas) {
            dtEntregas.destroy();
            dtEntregas = null;
        }

        const tbody = tabla.querySelector('tbody');
        tbody.innerHTML = '';

        if (entregas.length === 0) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="8" class="text-center py-4 text-muted"><i class="fa-solid fa-box-open fa-2x mb-2 d-block text-secondary opacity-50"></i>No se encontraron órdenes de entrega registradas.</td>';
            tbody.appendChild(tr);
            return;
        }

        entregas.forEach(e => {
            const tr = document.createElement('tr');

            let badgeEstado = '';
            if (e.estado === 'PENDIENTE') {
                badgeEstado = '<span class="badge bg-warning-subtle text-warning f-s-12 px-2 py-1"><i class="fa-solid fa-clock me-1"></i> PENDIENTE</span>';
            } else if (e.estado === 'ENTREGADO') {
                badgeEstado = '<span class="badge bg-success-subtle text-success f-s-12 px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i> ENTREGADO</span>';
            } else {
                badgeEstado = `<span class="badge bg-secondary f-s-12 px-2 py-1">${escaparHtml(e.estado)}</span>`;
            }

            const direccion = e.direccion_entrega ? escaparHtml(e.direccion_entrega) : '<span class="text-muted f-s-11">En Tienda / Muelle</span>';
            const fecha = e.fecha_entrega ? e.fecha_entrega : '<span class="text-muted f-s-11">Por Despachar</span>';
            const tel = e.contacto_telefono ? ` <small class="text-muted">(${escaparHtml(e.contacto_telefono)})</small>` : '';

            let btnAccionDespachar = '';
            if (e.estado === 'PENDIENTE') {
                btnAccionDespachar = `
                    <button type="button" class="btn btn-outline-success btn-xs btn-despachar" data-id="${e.id}" data-version="${e.version_bloqueo}" title="Despachar Entrega">
                        <i class="fa-solid fa-truck-ramp-box"></i> Despachar
                    </button>
                `;
            }

            tr.innerHTML = `
                <td><strong class="font-monospace text-primary">${escaparHtml(e.correlativo)}</strong></td>
                <td><span class="badge bg-light text-dark font-monospace border">${escaparHtml(e.venta_correlativo || ('#' + e.venta_id))}</span></td>
                <td>
                    <div class="f-w-600 text-dark">${escaparHtml(e.contacto_nombre)}</div>
                    <div>${tel}</div>
                </td>
                <td><span class="f-s-13">${direccion}</span></td>
                <td>
                    <span class="badge bg-info-subtle text-info f-s-12 px-2 py-1">
                        <i class="fa-solid fa-layer-group me-1"></i> ${e.total_items || 0} ítems (${e.cantidad_bienes || 0} uds)
                    </span>
                </td>
                <td><span class="f-s-13 font-monospace">${fecha}</span></td>
                <td>${badgeEstado}</td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary btn-xs btn-detalle" data-id="${e.id}" title="Ver Detalle de Ítems">
                            <i class="fa-solid fa-eye"></i> Detalle
                        </button>
                        ${btnAccionDespachar}
                    </div>
                </td>
            `;

            tbody.appendChild(tr);
        });

        // Eventos en botones
        tbody.querySelectorAll('.btn-detalle').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                abrirDetalleEntrega(id);
            });
        });

        tbody.querySelectorAll('.btn-despachar').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const version = parseInt(btn.getAttribute('data-version'), 10);
                confirmarDespacho(id, version);
            });
        });

        // DataTables
        if (typeof $.fn.DataTable !== 'undefined') {
            dtEntregas = $(tabla).DataTable({
                responsive: true,
                language: {
                    search: "Filtrar tabla:",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ entregas",
                    paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" },
                    emptyTable: "No hay entregas disponibles"
                },
                order: [[0, 'desc']],
                pageLength: 10,
                dom: 'rt<"d-flex justify-content-between align-items-center mt-3"ip>'
            });
        }
    }

    // =========================================================================
    // 4. DETALLE DE ENTREGA
    // =========================================================================
    function abrirDetalleEntrega(id) {
        fetch(`${URL_API_OPERACIONES}/entregas/${id}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exito || !data.datos || !data.datos.entrega) {
                Swal.fire('Error', data.mensaje || 'No se pudo recuperar el detalle de la orden.', 'error');
                return;
            }

            const e = data.datos.entrega;
            entregaActual = e;

            document.getElementById('lblDetalleCorrelativo').textContent = e.correlativo;
            document.getElementById('lblDetalleContactoNombre').textContent = e.contacto_nombre;
            document.getElementById('lblDetalleContactoTelefono').textContent = e.contacto_telefono || 'No especificado';
            document.getElementById('lblDetalleDireccion').textContent = e.direccion_entrega || 'En Tienda / Muelle';
            document.getElementById('lblDetalleFecha').textContent = e.fecha_entrega || 'Pendiente de despacho físico';

            const badgeCont = document.getElementById('lblDetalleEstadoBadge');
            if (e.estado === 'PENDIENTE') {
                badgeCont.innerHTML = '<span class="badge bg-warning-subtle text-warning f-s-13 px-2 py-1"><i class="fa-solid fa-clock me-1"></i> PENDIENTE</span>';
            } else if (e.estado === 'ENTREGADO') {
                badgeCont.innerHTML = '<span class="badge bg-success-subtle text-success f-s-13 px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i> ENTREGADO</span>';
            } else {
                badgeCont.innerHTML = `<span class="badge bg-secondary f-s-13 px-2 py-1">${escaparHtml(e.estado)}</span>`;
            }

            const contNotas = document.getElementById('contenedorNotasDespacho');
            const lblNotas = document.getElementById('lblDetalleNotas');
            if (e.notas_despacho) {
                lblNotas.textContent = e.notas_despacho;
                contNotas.style.display = 'block';
            } else {
                contNotas.style.display = 'none';
            }

            // Renderizar items
            const tbodyItems = document.getElementById('tbodyItemsEntrega');
            tbodyItems.innerHTML = '';

            if (Array.isArray(e.items) && e.items.length > 0) {
                e.items.forEach(it => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><span class="font-monospace text-primary">${escaparHtml(it.concepto_codigo || '-')}</span></td>
                        <td><strong class="text-dark">${escaparHtml(it.concepto_nombre || '-')}</strong></td>
                        <td class="text-center"><span class="badge bg-light text-secondary border">${escaparHtml(it.unidad_medida || 'NIU')}</span></td>
                        <td class="text-center"><span class="f-w-700 text-primary f-s-14">${it.cantidad}</span></td>
                    `;
                    tbodyItems.appendChild(row);
                });
            } else {
                tbodyItems.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-2">Sin ítems detallados</td></tr>';
            }

            // Control botón despacho modal
            if (btnDespacharDesdeModal) {
                btnDespacharDesdeModal.style.display = (e.estado === 'PENDIENTE') ? 'inline-block' : 'none';
            }

            if (modalDetalle) {
                modalDetalle.show();
            }
        })
        .catch(err => {
            console.error('Error recuperando detalle de entrega:', err);
            Swal.fire('Error', 'No se pudo conectar con el servidor para obtener los datos.', 'error');
        });
    }

    // =========================================================================
    // 5. DESPACHO DE ENTREGA
    // =========================================================================
    function confirmarDespacho(id, versionBloqueo) {
        Swal.fire({
            title: '¿Confirmar Despacho Físico?',
            text: 'Se registrará que los bienes o indumentaria han sido entregados materialmente al cliente.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa-solid fa-truck-ramp-box me-1"></i> Sí, Despachar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                ejecutarDespacho(id, versionBloqueo);
            }
        });
    }

    function ejecutarDespacho(id, versionBloqueo) {
        Swal.fire({
            title: 'Procesando Despacho...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const payload = {
            version_bloqueo: versionBloqueo || 1,
            csrf_token: CSRF_TOKEN
        };

        fetch(`${URL_API_OPERACIONES}/entregas/${id}/despachar`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json().then(data => ({ status: res.status, body: data })))
        .then(({ status, body }) => {
            if (status === 200 && body.exito) {
                Swal.fire('¡Despacho Exitoso!', body.mensaje || 'La orden de entrega ha sido marcada como ENTREGADA.', 'success');
                if (modalDetalle) {
                    modalDetalle.hide();
                }
                cargarEntregas();
            } else if (status === 409) {
                Swal.fire('Conflicto de Concurrencia', body.mensaje || 'La orden fue modificada recientemente por otro usuario. Se recargarán los datos.', 'warning')
                    .then(() => cargarEntregas());
            } else {
                Swal.fire('Error al Despachar', body.mensaje || 'No se pudo completar el despacho de la entrega.', 'error');
            }
        })
        .catch(err => {
            console.error('Error al despachar entrega:', err);
            Swal.fire('Error', 'Ocurrió un error inesperado al comunicarse con el servidor.', 'error');
        });
    }

    // Utilidad escape HTML
    function escaparHtml(texto) {
        if (!texto) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(texto).replace(/[&<>"']/g, m => map[m]);
    }
});
