/**
 * ==============================================================================
 * CANDELARIAAPP - CATÁLOGO DE ORÍGENES COMERCIALES (crm_origenes.js)
 * ==============================================================================
 * - Administración del catálogo de canales de captación por organización
 * - Código canónico inmutable para proteger el historial de oportunidades
 * - Desactivación lógica en lugar de borrado físico
 * - DataTables Alina y Fetch API
 * ==============================================================================
 */

'use strict';

class ModuloCrmOrigenes {
    constructor() {
        this.instanciaDataTable = null;
        this.origenesCache = [];
        this.modalCrear = null;
        this.modalEditar = null;

        this.init();
    }

    async init() {
        this.inicializarModales();
        this.vincularEventosUI();
        await this.cargarOrigenes();
    }

    inicializarModales() {
        if (!window.bootstrap?.Modal) return;

        const elCrear = document.getElementById('modalCrearOrigen');
        if (elCrear) this.modalCrear = new window.bootstrap.Modal(elCrear);

        const elEditar = document.getElementById('modalEditarOrigen');
        if (elEditar) this.modalEditar = new window.bootstrap.Modal(elEditar);
    }

    vincularEventosUI() {
        document.getElementById('btnRecargarTabla')?.addEventListener('click', () => this.cargarOrigenes());

        document.getElementById('btnAbrirModalCrear')?.addEventListener('click', () => {
            document.getElementById('formCrearOrigen')?.reset();
            this.modalCrear?.show();
        });

        document.getElementById('formCrearOrigen')?.addEventListener('submit', (e) => this.guardarCrear(e));
        document.getElementById('formEditarOrigen')?.addEventListener('submit', (e) => this.guardarEditar(e));
    }

    async cargarOrigenes() {
        const contenedor = document.getElementById('contenedorTablaOrigenes');
        if (!contenedor) return;

        try {
            Skeleton.show(contenedor, 'table', { filas: 5, columnas: 6 });

            const resp = await window.CandelariaApi.get('crm/origenes');
            const origenes = resp?.datos?.origenes || [];
            this.origenesCache = origenes;

            if (this.instanciaDataTable) {
                this.instanciaDataTable.destroy();
                this.instanciaDataTable = null;
            }

            Skeleton.hide(contenedor);
            this.renderizarTabla(contenedor, origenes);

            if (window.jQuery && window.jQuery.fn.DataTable) {
                this.instanciaDataTable = window.jQuery('#tablaOrigenes').DataTable({
                    language: {
                        search: "Buscar origen:",
                        lengthMenu: "Mostrar _MENU_ orígenes",
                        info: "Mostrando _START_ a _END_ de _TOTAL_ orígenes",
                        infoEmpty: "Mostrando 0 orígenes",
                        infoFiltered: "(filtrado de _MAX_ totales)",
                        zeroRecords: "No se encontraron orígenes comerciales",
                        paginate: {
                            first: "Primero",
                            previous: "Anterior",
                            next: "Siguiente",
                            last: "Último"
                        }
                    },
                    order: [[0, 'asc']], // Orden ascendente
                    pageLength: 10,
                    responsive: true,
                    dom: '<"d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"lf>rt<"d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2"ip>'
                });
            }

            this.vincularAccionesFilas();

        } catch (err) {
            console.error('[ModuloCrmOrigenes] Error al cargar orígenes comerciales:', err);
            Skeleton.error(contenedor, 'No fue posible cargar los orígenes: ' + (err.message || 'Error del servidor'), {
                texto: 'Reintentar',
                accion: () => this.cargarOrigenes()
            });
        }
    }

    renderizarTabla(contenedor, origenes) {
        let filasHtml = '';

        if (origenes.length === 0) {
            filasHtml = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-arrow-up-right-from-square f-s-32 d-block mb-2 text-secondary"></i>
                        No hay orígenes comerciales registrados.
                    </td>
                </tr>
            `;
        } else {
            origenes.forEach(o => {
                const activo = o.activo === true || o.activo === 1;
                const badgeEstado = activo 
                    ? '<span class="badge bg-success">ACTIVO</span>' 
                    : '<span class="badge bg-secondary">INACTIVO</span>';

                filasHtml += `
                    <tr>
                        <td class="text-center f-w-600">${o.orden}</td>
                        <td><code>${o.codigo}</code></td>
                        <td><strong class="text-dark">${o.nombre}</strong></td>
                        <td class="text-muted f-s-13">${o.descripcion || '-'}</td>
                        <td>${badgeEstado}</td>
                        <td>
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-editar-origen" data-id="${o.id}" title="Editar">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-${activo ? 'warning' : 'success'} btn-toggle-origen" data-id="${o.id}" data-activo="${activo ? '1' : '0'}" title="${activo ? 'Desactivar' : 'Activar'}">
                                    <i class="fa-solid fa-${activo ? 'ban' : 'check'}"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
        }

        contenedor.innerHTML = `
            <table class="table table-hover align-middle w-100" id="tablaOrigenes">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 60px;" class="text-center">Orden</th>
                        <th style="width: 160px;">Código Canónico</th>
                        <th>Nombre del Origen</th>
                        <th>Descripción</th>
                        <th style="width: 90px;">Estado</th>
                        <th style="width: 90px;" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>${filasHtml}</tbody>
            </table>
        `;
    }

    vincularAccionesFilas() {
        // Editar
        document.querySelectorAll('.btn-editar-origen').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                const o = this.origenesCache.find(item => parseInt(item.id, 10) === id);
                if (!o) return;

                document.getElementById('editarOrigenId').value = o.id;
                document.getElementById('editarCodigoDisplay').value = o.codigo;
                document.getElementById('editarNombre').value = o.nombre;
                document.getElementById('editarDescripcion').value = o.descripcion || '';
                document.getElementById('editarOrden').value = o.orden;

                this.modalEditar?.show();
            });
        });

        // Activar / Desactivar
        document.querySelectorAll('.btn-toggle-origen').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                const activoActual = e.currentTarget.dataset.activo === '1';
                const nuevoEstado = !activoActual;

                const confirm = await CandelariaUI.confirmarAccion(
                    `¿Desea ${nuevoEstado ? 'activar' : 'desactivar'} este origen comercial?`,
                    'Confirmar Cambio de Estado',
                    nuevoEstado ? 'Sí, activar' : 'Sí, desactivar'
                );

                if (!confirm.isConfirmed) return;

                try {
                    const resp = await window.CandelariaApi.patch(`crm/origenes/${id}/estado`, {
                        activo: nuevoEstado
                    });

                    CandelariaUI.notificarExito(resp?.mensaje || 'Estado actualizado con éxito.', 'Actualizado');
                    await this.cargarOrigenes();
                } catch (err) {
                    console.error('[ModuloCrmOrigenes] Error al cambiar estado:', err);
                    CandelariaUI.notificarError(err.message || 'No fue posible cambiar el estado del origen.', 'Error');
                }
            });
        });
    }

    async guardarCrear(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarCrearOrigen');

        const codigo = document.getElementById('crearCodigo')?.value?.trim().toUpperCase();
        const nombre = document.getElementById('crearNombre')?.value?.trim();
        const descripcion = document.getElementById('crearDescripcion')?.value?.trim() || null;
        const orden = parseInt(document.getElementById('crearOrden')?.value || '0', 10);

        if (!codigo || !nombre) {
            CandelariaUI.notificarError('El código y el nombre son obligatorios.', 'Datos Requeridos');
            return;
        }

        try {
            CandelariaUI.procesarBoton(btn, 'Guardando...');
            const resp = await window.CandelariaApi.post('crm/origenes', {
                codigo: codigo,
                nombre: nombre,
                descripcion: descripcion,
                orden: orden
            });

            CandelariaUI.notificarExito(resp?.mensaje || 'Origen comercial registrado exitosamente.', 'Origen Creado');
            this.modalCrear?.hide();
            await this.cargarOrigenes();
        } catch (err) {
            console.error('[ModuloCrmOrigenes] Error al crear origen:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible registrar el origen.', 'Error');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    async guardarEditar(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarEditarOrigen');

        const id = document.getElementById('editarOrigenId')?.value;
        const nombre = document.getElementById('editarNombre')?.value?.trim();
        const descripcion = document.getElementById('editarDescripcion')?.value?.trim() || null;
        const orden = parseInt(document.getElementById('editarOrden')?.value || '0', 10);

        if (!id || !nombre) {
            CandelariaUI.notificarError('El nombre es obligatorio.', 'Dato Requerido');
            return;
        }

        try {
            CandelariaUI.procesarBoton(btn, 'Guardando...');
            const resp = await window.CandelariaApi.put(`crm/origenes/${id}`, {
                nombre: nombre,
                descripcion: descripcion,
                orden: orden
            });

            CandelariaUI.notificarExito(resp?.mensaje || 'Origen comercial actualizado exitosamente.', 'Actualizado');
            this.modalEditar?.hide();
            await this.cargarOrigenes();
        } catch (err) {
            console.error('[ModuloCrmOrigenes] Error al editar origen:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible actualizar el origen.', 'Error');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.moduloCrmOrigenes = new ModuloCrmOrigenes();
});
