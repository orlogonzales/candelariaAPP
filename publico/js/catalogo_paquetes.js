/**
 * ==============================================================================
 * CANDELARIAAPP - CATÁLOGO DE PAQUETES COMERCIALES (catalogo_paquetes.js) - FASE 2.3C
 * ==============================================================================
 * - Catálogo maestro de paquetes
 * - Composición de ítems con Select2 asíncrono
 * - CERO recargas completas de página
 * ==============================================================================
 */

'use strict';

class ModuloCatalogoPaquetes {
    constructor() {
        this.api = new CandelariaClienteApi();
        this.instanciaDataTable = null;
        this.paquetesCache = [];
        this.modalPaquete = null;
        this.modalComposicion = null;
        this.paqueteActivoComposicion = null;
        this.composicionLocal = [];

        this.init();
    }

    async init() {
        this.inicializarModales();
        this.inicializarSelect2Items();
        this.vincularEventosUI();
        await this.cargarPaquetes();
    }

    inicializarModales() {
        if (!window.bootstrap?.Modal) return;

        const elPaq = document.getElementById('modalPaquete');
        if (elPaq) this.modalPaquete = new window.bootstrap.Modal(elPaq);

        const elComp = document.getElementById('modalComposicion');
        if (elComp) this.modalComposicion = new window.bootstrap.Modal(elComp);
    }

    inicializarSelect2Items() {
        const selectEl = $('#compSelectItemId');
        if (window.jQuery && selectEl.length && $.fn.select2) {
            selectEl.select2({
                dropdownParent: $('#modalComposicion'),
                placeholder: 'Buscar ítem por nombre o código...',
                allowClear: true,
                minimumInputLength: 1,
                ajax: {
                    url: `${window.CANDELARIA_BASE_URL || ''}/api/v1/catalogo/items/buscar`,
                    dataType: 'json',
                    delay: 250,
                    data: (params) => ({
                        q: params.term
                    }),
                    processResults: (data) => {
                        if (!data.exito || !Array.isArray(data.datos)) {
                            return { results: [] };
                        }
                        return {
                            results: data.datos.map(item => ({
                                id: item.id,
                                text: `[${item.codigo}] ${item.nombre} (${item.tipo} - ${item.unidadMedida})`,
                                item: item
                            }))
                        };
                    },
                    cache: true
                }
            });
        }
    }

    vincularEventosUI() {
        // Recargar
        document.getElementById('btnRecargarTabla')?.addEventListener('click', () => this.cargarPaquetes());

        // Nuevo Paquete
        document.getElementById('btnAbrirModalCrear')?.addEventListener('click', () => {
            this.limpiarFormPaquete();
            this.modalPaquete?.show();
        });

        // Filtros
        document.getElementById('filtroEstado')?.addEventListener('change', () => this.filtrarPaquetes());

        let debounceTimer = null;
        document.getElementById('filtroBusqueda')?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => this.filtrarPaquetes(), 300);
        });

        // Submit Formulario Paquete
        document.getElementById('formPaquete')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.guardarPaquete();
        });

        // Agregar Ítem a Composición
        document.getElementById('btnAgregarFilaComp')?.addEventListener('click', () => {
            this.agregarItemAComposicion();
        });

        // Guardar Composición
        document.getElementById('btnGuardarComposicion')?.addEventListener('click', () => {
            this.guardarComposicion();
        });
    }

    async cargarPaquetes() {
        const btnRecargar = document.getElementById('btnRecargarTabla');
        if (btnRecargar) {
            btnRecargar.disabled = true;
            btnRecargar.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Cargando...';
        }

        try {
            const resp = await this.api.peticion('catalogo/paquetes');
            const paqs = Array.isArray(resp.datos) ? resp.datos : (resp.datos?.paquetes || []);
            if (resp.exito) {
                this.paquetesCache = paqs;
                this.actualizarKpis();
                this.renderizarTablaPaquetes(this.paquetesCache);
            } else {
                CandelariaUI.notificarError(resp.mensaje || 'No se pudieron obtener los paquetes.');
            }
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error de conexión al cargar paquetes.');
        } finally {
            if (btnRecargar) {
                btnRecargar.disabled = false;
                btnRecargar.innerHTML = '<i class="fa-solid fa-rotate me-1"></i> Actualizar';
            }
        }
    }

    actualizarKpis() {
        const total = this.paquetesCache.length;
        const activos = this.paquetesCache.filter(p => p.activo).length;
        const inactivos = total - activos;
        const conComp = this.paquetesCache.filter(p => (p.cantidadItems || 0) > 0).length;

        document.getElementById('kpiTotalPaquetes').textContent = total;
        document.getElementById('kpiActivos').textContent = activos;
        document.getElementById('kpiInactivos').textContent = inactivos;
        document.getElementById('kpiConComposicion').textContent = conComp;
    }

    filtrarPaquetes() {
        const texto = (document.getElementById('filtroBusqueda')?.value || '').toLowerCase().trim();
        const estado = document.getElementById('filtroEstado')?.value || '';

        const filtrados = this.paquetesCache.filter(paq => {
            const matchTexto = !texto ||
                (paq.codigo && paq.codigo.toLowerCase().includes(texto)) ||
                (paq.nombre && paq.nombre.toLowerCase().includes(texto)) ||
                (paq.descripcion && paq.descripcion.toLowerCase().includes(texto));

            const matchEstado = estado === '' || String(paq.activo ? 1 : 0) === String(estado);

            return matchTexto && matchEstado;
        });

        this.renderizarTablaPaquetes(filtrados);
    }

    renderizarTablaPaquetes(paquetes) {
        if (window.jQuery && $.fn.DataTable.isDataTable('#tablaPaquetes')) {
            $('#tablaPaquetes').DataTable().destroy();
        }

        const tbody = document.getElementById('tbodyPaquetes');
        if (!tbody) return;

        if (paquetes.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-inbox f-s-24 d-block mb-2"></i>
                        No se encontraron paquetes registrados con los filtros seleccionados.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = paquetes.map(paq => {
            const badgeEstado = paq.activo
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i> Activo</span>'
                : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="fa-solid fa-circle-xmark me-1"></i> Inactivo</span>';

            const cantItems = paq.cantidadItems || 0;
            const badgeItems = cantItems > 0
                ? `<span class="badge bg-info-subtle text-info f-w-600 px-2 py-1"><i class="fa-solid fa-layer-group me-1"></i> ${cantItems} ítems</span>`
                : '<span class="badge bg-light text-muted border px-2 py-1">Sin ítems</span>';

            return `
                <tr data-paquete-id="${paq.id}">
                    <td class="f-w-700 text-dark"><span class="badge bg-light text-dark border">${paq.codigo}</span></td>
                    <td>
                        <span class="f-w-600 text-dark d-block">${this.escaparHtml(paq.nombre)}</span>
                    </td>
                    <td>
                        <span class="f-s-13 text-muted text-truncate d-inline-block" style="max-width: 320px;">
                            ${paq.descripcion ? this.escaparHtml(paq.descripcion) : '-'}
                        </span>
                    </td>
                    <td class="text-center">${badgeItems}</td>
                    <td class="text-center">${badgeEstado}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-info btn-sm btn-composicion-paq" data-id="${paq.id}" title="Gestionar Composición de Ítems">
                                <i class="fa-solid fa-layer-group"></i> Composición
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm btn-editar-paq" data-id="${paq.id}" title="Editar Paquete">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-estado-paq" data-id="${paq.id}" data-activo="${paq.activo ? 1 : 0}" title="${paq.activo ? 'Desactivar Paquete' : 'Activar Paquete'}">
                                <i class="fa-solid ${paq.activo ? 'fa-toggle-on text-success' : 'fa-toggle-off text-muted'}"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        if (window.jQuery && $.fn.DataTable) {
            this.instanciaDataTable = $('#tablaPaquetes').DataTable({
                pageLength: 15,
                language: {
                    search: "Filtrar resultados:",
                    lengthMenu: "Mostrar _MENU_ registros por página",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ paquetes",
                    infoEmpty: "Mostrando 0 a 0 de 0 paquetes",
                    infoFiltered: "(filtrado de _MAX_ registros en total)",
                    paginate: {
                        first: "Primero",
                        previous: "Anterior",
                        next: "Siguiente",
                        last: "Último"
                    },
                    emptyTable: "No hay datos disponibles en la tabla"
                },
                columnDefs: [
                    { orderable: false, targets: [5] }
                ]
            });
        }

        // Vincular eventos
        tbody.querySelectorAll('.btn-composicion-paq').forEach(btn => {
            btn.addEventListener('click', () => this.abrirModalComposicion(btn.dataset.id));
        });

        tbody.querySelectorAll('.btn-editar-paq').forEach(btn => {
            btn.addEventListener('click', () => this.abrirModalEditar(btn.dataset.id));
        });

        tbody.querySelectorAll('.btn-estado-paq').forEach(btn => {
            btn.addEventListener('click', () => this.cambiarEstadoPaquete(btn.dataset.id, btn.dataset.activo === '1'));
        });
    }

    limpiarFormPaquete() {
        document.getElementById('formPaquete')?.reset();
        document.getElementById('paqueteId').value = '';
        document.getElementById('modalPaqueteLabel').innerHTML = '<i class="fa-solid fa-boxes-packing me-2"></i> Nuevo Paquete Comercial';
        document.getElementById('paqueteActivo').checked = true;
    }

    abrirModalEditar(id) {
        const paq = this.paquetesCache.find(p => String(p.id) === String(id));
        if (!paq) return;

        document.getElementById('paqueteId').value = paq.id;
        document.getElementById('paqueteCodigo').value = paq.codigo;
        document.getElementById('paqueteNombre').value = paq.nombre;
        document.getElementById('paqueteDescripcion').value = paq.descripcion || '';
        document.getElementById('paqueteActivo').checked = !!paq.activo;

        document.getElementById('modalPaqueteLabel').innerHTML = `<i class="fa-solid fa-pen-to-square me-2"></i> Editar Paquete: ${this.escaparHtml(paq.codigo)}`;
        this.modalPaquete?.show();
    }

    async guardarPaquete() {
        const form = document.getElementById('formPaquete');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const id = document.getElementById('paqueteId').value;
        const cuerpo = {
            codigo: document.getElementById('paqueteCodigo').value.trim().toUpperCase(),
            nombre: document.getElementById('paqueteNombre').value.trim(),
            descripcion: document.getElementById('paqueteDescripcion').value.trim(),
            activo: document.getElementById('paqueteActivo').checked
        };

        const btn = document.getElementById('btnGuardarPaquete');
        CandelariaUI.procesarBoton(btn, 'Guardando...');

        try {
            const endpoint = id ? `catalogo/paquetes/${id}` : 'catalogo/paquetes';
            const metodo = id ? 'PUT' : 'POST';

            const resp = await this.api.peticion(endpoint, {
                metodo,
                cuerpo
            });

            if (resp.exito) {
                CandelariaUI.notificarExito(resp.mensaje || 'Paquete comercial guardado.');
                this.modalPaquete?.hide();
                await this.cargarPaquetes();
            } else {
                CandelariaUI.notificarError(resp.mensaje || 'No se pudo guardar el paquete.');
            }
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error de procesamiento.');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    async cambiarEstadoPaquete(id, estadoActual) {
        const accionTexto = estadoActual ? 'desactivar' : 'activar';
        const confirmado = await CandelariaUI.confirmarAccion(
            `¿Está seguro de que desea ${accionTexto} este paquete comercial?`,
            'Cambio de Estado',
            `Sí, ${accionTexto}`
        );

        if (!confirmado.isConfirmed) return;

        try {
            const resp = await this.api.peticion(`catalogo/paquetes/${id}/estado`, {
                metodo: 'PATCH',
                cuerpo: { activo: !estadoActual }
            });

            if (resp.exito) {
                CandelariaUI.notificarExito(resp.mensaje || `Paquete ${accionTexto}do.`);
                await this.cargarPaquetes();
            } else {
                CandelariaUI.notificarError(resp.mensaje || 'No se pudo actualizar el estado.');
            }
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error al cambiar estado.');
        }
    }

    // =========================================================================
    // COMPOSICIÓN DEL PAQUETE
    // =========================================================================

    async abrirModalComposicion(id) {
        const paq = this.paquetesCache.find(p => String(p.id) === String(id));
        if (!paq) return;

        this.paqueteActivoComposicion = paq;
        document.getElementById('composicionPaqueteTitulo').textContent = paq.nombre;
        document.getElementById('composicionPaqueteCodigo').textContent = paq.codigo;

        // Limpiar formulario agregar
        if (window.jQuery && $('#compSelectItemId').length) {
            $('#compSelectItemId').val(null).trigger('change');
        }
        document.getElementById('compCantidad').value = '1';
        document.getElementById('compNota').value = '';

        // Cargar composición desde backend
        try {
            const resp = await this.api.peticion(`catalogo/paquetes/${id}/composicion`);
            if (resp.exito && Array.isArray(resp.datos)) {
                this.composicionLocal = resp.datos.map((item, idx) => ({
                    itemId: item.itemId,
                    codigo: item.itemCodigo || '',
                    nombre: item.itemNombre || '',
                    tipo: item.itemTipo || '',
                    unidadMedida: item.itemUnidadMedida || 'UNIDAD',
                    cantidad: item.cantidad,
                    orden: item.orden || (idx + 1),
                    nota: item.nota || ''
                }));
            } else {
                this.composicionLocal = [];
            }
        } catch (error) {
            this.composicionLocal = [];
            console.error('[CatalogoPaquetes] Error al cargar composición:', error);
        }

        this.renderizarTablaComposicion();
        this.modalComposicion?.show();
    }

    renderizarTablaComposicion() {
        const tbody = document.getElementById('tbodyComposicion');
        if (!tbody) return;

        if (this.composicionLocal.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-boxes-stacked f-s-24 d-block mb-2"></i>
                        Este paquete no tiene ítems asignados a su composición. Agregue productos o servicios con el formulario superior.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.composicionLocal.map((linea, index) => {
            const badgeTipo = linea.tipo === 'PRODUCTO'
                ? '<span class="badge bg-info-subtle text-info">PRODUCTO</span>'
                : '<span class="badge bg-success-subtle text-success">SERVICIO</span>';

            return `
                <tr data-index="${index}">
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm text-center input-orden" value="${linea.orden || (index + 1)}" min="1" style="width: 60px;">
                    </td>
                    <td class="f-w-700 text-dark"><span class="badge bg-light text-dark border">${linea.codigo}</span></td>
                    <td class="f-w-600">${this.escaparHtml(linea.nombre)}</td>
                    <td>${badgeTipo}</td>
                    <td><span class="badge bg-light text-secondary border">${linea.unidadMedida}</span></td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm text-center input-cantidad" value="${linea.cantidad}" min="1" step="1" style="width: 75px; margin: 0 auto;">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm input-nota" value="${this.escaparHtml(linea.nota || '')}" placeholder="Nota referencial...">
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-quitar-fila" data-index="${index}" title="Quitar de la composición">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        // Vincular inputs
        tbody.querySelectorAll('.input-orden').forEach(input => {
            input.addEventListener('change', (e) => {
                const tr = e.target.closest('tr');
                const idx = parseInt(tr.dataset.index, 10);
                this.composicionLocal[idx].orden = parseInt(e.target.value, 10) || (idx + 1);
            });
        });

        tbody.querySelectorAll('.input-cantidad').forEach(input => {
            input.addEventListener('change', (e) => {
                const tr = e.target.closest('tr');
                const idx = parseInt(tr.dataset.index, 10);
                const val = parseInt(e.target.value, 10);
                this.composicionLocal[idx].cantidad = Math.max(1, isNaN(val) ? 1 : val);
            });
        });

        tbody.querySelectorAll('.input-nota').forEach(input => {
            input.addEventListener('change', (e) => {
                const tr = e.target.closest('tr');
                const idx = parseInt(tr.dataset.index, 10);
                this.composicionLocal[idx].nota = e.target.value.trim();
            });
        });

        tbody.querySelectorAll('.btn-quitar-fila').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.index, 10);
                this.composicionLocal.splice(idx, 1);
                // Reordenar
                this.composicionLocal.forEach((l, i) => l.orden = i + 1);
                this.renderizarTablaComposicion();
            });
        });
    }

    agregarItemAComposicion() {
        const select2Data = $('#compSelectItemId').select2('data');
        if (!select2Data || select2Data.length === 0 || !select2Data[0].id) {
            CandelariaUI.notificarError('Debe buscar y seleccionar un ítem comercial.');
            return;
        }

        const itemSeleccionado = select2Data[0].item;
        const itemId = parseInt(select2Data[0].id, 10);
        const cantidad = parseInt(document.getElementById('compCantidad').value, 10) || 1;
        const nota = document.getElementById('compNota').value.trim();

        // Verificar si ya existe en la lista
        const existente = this.composicionLocal.find(l => l.itemId === itemId);
        if (existente) {
            existente.cantidad += cantidad;
            if (nota) existente.nota = nota;
        } else {
            this.composicionLocal.push({
                itemId: itemId,
                codigo: itemSeleccionado.codigo,
                nombre: itemSeleccionado.nombre,
                tipo: itemSeleccionado.tipo,
                unidadMedida: itemSeleccionado.unidadMedida,
                cantidad: cantidad,
                orden: this.composicionLocal.length + 1,
                nota: nota
            });
        }

        // Limpiar formulario agregar
        $('#compSelectItemId').val(null).trigger('change');
        document.getElementById('compCantidad').value = '1';
        document.getElementById('compNota').value = '';

        this.renderizarTablaComposicion();
    }

    async guardarComposicion() {
        if (!this.paqueteActivoComposicion) return;

        const btn = document.getElementById('btnGuardarComposicion');
        CandelariaUI.procesarBoton(btn, 'Guardando...');

        const itemsPayload = this.composicionLocal.map(l => ({
            item_id: l.itemId,
            cantidad: l.cantidad,
            orden: l.orden,
            nota: l.nota || ''
        }));

        try {
            const resp = await this.api.peticion(`catalogo/paquetes/${this.paqueteActivoComposicion.id}/composicion`, {
                metodo: 'PUT',
                cuerpo: { items: itemsPayload }
            });

            if (resp.exito) {
                CandelariaUI.notificarExito('Composición del paquete actualizada correctamente.');
                this.modalComposicion?.hide();
                await this.cargarPaquetes();
            } else {
                CandelariaUI.notificarError(resp.mensaje || 'No se pudo guardar la composición.');
            }
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error de procesamiento.');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    escaparHtml(texto) {
        if (!texto) return '';
        const mapa = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(texto).replace(/[&<>"']/g, m => mapa[m]);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.moduloCatalogoPaquetes = new ModuloCatalogoPaquetes();
});
