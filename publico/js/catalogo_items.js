/**
 * ==============================================================================
 * CANDELARIAAPP - CATÁLOGO DE ÍTEMS COMERCIALES (catalogo_items.js) - FASE 2.3C
 * ==============================================================================
 * - Padrón maestro de productos y servicios
 * - Búsqueda, filtrado y renderizado con DataTables
 * - Modales de creación/edición de ítems y gestión de categorías
 * - CERO recargas completas de página
 * ==============================================================================
 */

'use strict';

class ModuloCatalogoItems {
    constructor() {
        this.api = new CandelariaClienteApi();
        this.instanciaDataTable = null;
        this.itemsCache = [];
        this.categoriasCache = [];
        this.modalItem = null;
        this.modalCategorias = null;
        this.itemEnEdicionId = null;
        this.categoriaEnEdicionId = null;

        this.init();
    }

    async init() {
        this.inicializarModales();
        this.vincularEventosUI();
        await this.cargarCategorias();
        await this.cargarItems();
    }

    inicializarModales() {
        if (!window.bootstrap?.Modal) return;

        const elItem = document.getElementById('modalItem');
        if (elItem) this.modalItem = new window.bootstrap.Modal(elItem);

        const elCat = document.getElementById('modalCategorias');
        if (elCat) this.modalCategorias = new window.bootstrap.Modal(elCat);
    }

    vincularEventosUI() {
        // Recargar tabla
        document.getElementById('btnRecargarTabla')?.addEventListener('click', () => this.cargarItems());

        // Modal Nuevo Ítem
        document.getElementById('btnAbrirModalCrear')?.addEventListener('click', () => {
            this.limpiarFormItem();
            this.modalItem?.show();
        });

        // Modal Categorías
        document.getElementById('btnAbrirModalCategorias')?.addEventListener('click', () => {
            this.limpiarFormCategoria();
            this.renderizarTablaCategorias();
            this.modalCategorias?.show();
        });

        // Filtros
        ['filtroTipo', 'filtroCategoria', 'filtroEstado'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => this.filtrarItems());
        });

        let debounceTimer = null;
        document.getElementById('filtroBusqueda')?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => this.filtrarItems(), 300);
        });

        // Submit Formulario Ítem
        document.getElementById('formItem')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.guardarItem();
        });

        // Submit Formulario Categoría
        document.getElementById('formCategoria')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.guardarCategoria();
        });

        // Cancelar edición categoría
        document.getElementById('btnCancelarEdicionCat')?.addEventListener('click', () => {
            this.limpiarFormCategoria();
        });
    }

    async cargarCategorias() {
        try {
            const resp = await this.api.peticion('catalogo/categorias');
            const cats = Array.isArray(resp.datos) ? resp.datos : (resp.datos?.categorias || []);
            if (resp.exito && Array.isArray(cats)) {
                this.categoriasCache = cats;
                this.actualizarSelectsCategorias();
            }
        } catch (error) {
            console.error('[CatalogoItems] Error al cargar categorías:', error);
        }
    }

    actualizarSelectsCategorias() {
        const filtro = document.getElementById('filtroCategoria');
        const formSelect = document.getElementById('itemCategoriaId');

        if (filtro) {
            const valActual = filtro.value;
            filtro.innerHTML = '<option value="">Todas las Categorías</option>';
            this.categoriasCache.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.nombre;
                filtro.appendChild(opt);
            });
            filtro.value = valActual;
        }

        if (formSelect) {
            const valActual = formSelect.value;
            formSelect.innerHTML = '<option value="">Seleccione una categoría...</option>';
            this.categoriasCache.filter(c => c.activo).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.nombre;
                formSelect.appendChild(opt);
            });
            formSelect.value = valActual;
        }

        const kpiCat = document.getElementById('kpiTotalCategorias');
        if (kpiCat) kpiCat.textContent = this.categoriasCache.length;
    }

    async cargarItems() {
        const btnRecargar = document.getElementById('btnRecargarTabla');
        if (btnRecargar) {
            btnRecargar.disabled = true;
            btnRecargar.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Cargando...';
        }

        if (window.Skeleton) {
            Skeleton.show('#contenedorTablaItems', 'table', { filas: 5, columnas: 7 });
        }

        try {
            const resp = await this.api.peticion('catalogo/items');
            const items = Array.isArray(resp.datos) ? resp.datos : (resp.datos?.items || []);
            if (resp.exito) {
                this.itemsCache = items;
                this.actualizarKpis();
                this.renderizarTablaItems(this.itemsCache);
            } else {
                CandelariaUI.notificarError(resp.mensaje || 'No se pudieron obtener los ítems.');
            }
        } catch (error) {
            if (window.Skeleton) {
                Skeleton.error('#contenedorTablaItems', 'Error al cargar ítems: ' + (error.message || 'Error del servidor'), {
                    texto: 'Reintentar',
                    accion: () => this.cargarItems()
                });
            } else {
                CandelariaUI.notificarError(error.message || 'Error de conexión al cargar ítems.');
            }
        } finally {
            if (window.Skeleton) {
                Skeleton.hide('#contenedorTablaItems');
            }
            if (btnRecargar) {
                btnRecargar.disabled = false;
                btnRecargar.innerHTML = '<i class="fa-solid fa-rotate me-1"></i> Actualizar';
            }
        }
    }

    actualizarKpis() {
        const total = this.itemsCache.length;
        const productos = this.itemsCache.filter(i => i.tipo === 'PRODUCTO').length;
        const servicios = this.itemsCache.filter(i => i.tipo === 'SERVICIO').length;

        document.getElementById('kpiTotalItems').textContent = total;
        document.getElementById('kpiTotalProductos').textContent = productos;
        document.getElementById('kpiTotalServicios').textContent = servicios;
    }

    filtrarItems() {
        const texto = (document.getElementById('filtroBusqueda')?.value || '').toLowerCase().trim();
        const tipo = document.getElementById('filtroTipo')?.value || '';
        const categoriaId = document.getElementById('filtroCategoria')?.value || '';
        const estado = document.getElementById('filtroEstado')?.value || '';

        const filtrados = this.itemsCache.filter(item => {
            const matchTexto = !texto || 
                (item.codigo && item.codigo.toLowerCase().includes(texto)) || 
                (item.nombre && item.nombre.toLowerCase().includes(texto)) ||
                (item.descripcion && item.descripcion.toLowerCase().includes(texto));

            const matchTipo = !tipo || item.tipo === tipo;
            const matchCat = !categoriaId || String(item.categoriaId) === String(categoriaId);
            const matchEstado = estado === '' || String(item.activo ? 1 : 0) === String(estado);

            return matchTexto && matchTipo && matchCat && matchEstado;
        });

        this.renderizarTablaItems(filtrados);
    }

    renderizarTablaItems(items) {
        if (window.jQuery && $.fn.DataTable.isDataTable('#tablaItems')) {
            $('#tablaItems').DataTable().destroy();
        }

        const tbody = document.getElementById('tbodyItems');
        if (!tbody) return;

        if (items.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-inbox f-s-24 d-block mb-2"></i>
                        No se encontraron ítems comerciales registrados con los filtros seleccionados.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = items.map(item => {
            const esProducto = item.tipo === 'PRODUCTO';
            const badgeTipo = esProducto
                ? '<span class="badge bg-info-subtle text-info f-w-600 px-2 py-1"><i class="fa-solid fa-cube me-1"></i> PRODUCTO</span>'
                : '<span class="badge bg-success-subtle text-success f-w-600 px-2 py-1"><i class="fa-solid fa-hand-holding-heart me-1"></i> SERVICIO</span>';

            const badgeEstado = item.activo
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i> Activo</span>'
                : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="fa-solid fa-circle-xmark me-1"></i> Inactivo</span>';

            const catNombre = item.categoriaNombre || 'Sin Categoría';
            const unidad = item.unidadMedida || 'UNIDAD';

            return `
                <tr data-item-id="${item.id}">
                    <td class="f-w-700 text-dark"><span class="badge bg-light text-dark border">${item.codigo}</span></td>
                    <td>
                        <span class="f-w-600 text-dark d-block">${this.escaparHtml(item.nombre)}</span>
                        ${item.descripcion ? `<span class="f-s-12 text-muted text-truncate d-inline-block" style="max-width: 250px;">${this.escaparHtml(item.descripcion)}</span>` : ''}
                    </td>
                    <td>${badgeTipo}</td>
                    <td><span class="f-s-13 text-secondary"><i class="fa-solid fa-folder me-1 text-warning"></i> ${this.escaparHtml(catNombre)}</span></td>
                    <td><span class="badge bg-light text-secondary border">${unidad}</span></td>
                    <td class="text-center">${badgeEstado}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary btn-sm btn-editar-item" data-id="${item.id}" title="Editar Ítem">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-estado-item" data-id="${item.id}" data-activo="${item.activo ? 1 : 0}" title="${item.activo ? 'Desactivar Ítem' : 'Activar Ítem'}">
                                <i class="fa-solid ${item.activo ? 'fa-toggle-on text-success' : 'fa-toggle-off text-muted'}"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        // DataTable initialization
        if (window.jQuery && $.fn.DataTable) {
            this.instanciaDataTable = $('#tablaItems').DataTable({
                pageLength: 15,
                language: {
                    search: "Filtrar resultados:",
                    lengthMenu: "Mostrar _MENU_ registros por página",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ ítems",
                    infoEmpty: "Mostrando 0 a 0 de 0 ítems",
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
                    { orderable: false, targets: [6] }
                ]
            });
        }

        // Vincular acciones en botones generados
        tbody.querySelectorAll('.btn-editar-item').forEach(btn => {
            btn.addEventListener('click', () => this.abrirModalEditar(btn.dataset.id));
        });

        tbody.querySelectorAll('.btn-estado-item').forEach(btn => {
            btn.addEventListener('click', () => this.cambiarEstadoItem(btn.dataset.id, btn.dataset.activo === '1'));
        });
    }

    limpiarFormItem() {
        this.itemEnEdicionId = null;
        document.getElementById('formItem')?.reset();
        document.getElementById('itemId').value = '';
        document.getElementById('modalItemLabel').innerHTML = '<i class="fa-solid fa-box-open me-2"></i> Nuevo Ítem Comercial';
        document.getElementById('itemActivo').checked = true;
    }

    abrirModalEditar(id) {
        const item = this.itemsCache.find(i => String(i.id) === String(id));
        if (!item) return;

        this.itemEnEdicionId = item.id;
        document.getElementById('itemId').value = item.id;
        document.getElementById('itemTipo').value = item.tipo;
        document.getElementById('itemCategoriaId').value = item.categoriaId;
        document.getElementById('itemCodigo').value = item.codigo;
        document.getElementById('itemNombre').value = item.nombre;
        document.getElementById('itemUnidadMedida').value = item.unidadMedida;
        document.getElementById('itemDescripcion').value = item.descripcion || '';
        document.getElementById('itemActivo').checked = !!item.activo;

        document.getElementById('modalItemLabel').innerHTML = `<i class="fa-solid fa-pen-to-square me-2"></i> Editar Ítem: ${this.escaparHtml(item.codigo)}`;
        this.modalItem?.show();
    }

    async guardarItem() {
        const form = document.getElementById('formItem');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const id = document.getElementById('itemId').value;
        const cuerpo = {
            tipo: document.getElementById('itemTipo').value,
            categoria_id: parseInt(document.getElementById('itemCategoriaId').value, 10),
            codigo: document.getElementById('itemCodigo').value.trim().toUpperCase(),
            nombre: document.getElementById('itemNombre').value.trim(),
            unidad_medida: document.getElementById('itemUnidadMedida').value,
            descripcion: document.getElementById('itemDescripcion').value.trim(),
            activo: document.getElementById('itemActivo').checked
        };

        const btnGuardar = document.getElementById('btnGuardarItem');
        CandelariaUI.procesarBoton(btnGuardar, 'Guardando...');

        try {
            const endpoint = id ? `catalogo/items/${id}` : 'catalogo/items';
            const metodo = id ? 'PUT' : 'POST';

            const resp = await this.api.peticion(endpoint, {
                metodo,
                cuerpo
            });

            if (resp.exito) {
                CandelariaUI.notificarExito(resp.mensaje || 'Ítem comercial guardado correctamente.');
                this.modalItem?.hide();
                await this.cargarItems();
            } else {
                CandelariaUI.notificarError(resp.mensaje || 'No se pudo guardar el ítem.');
            }
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error de procesamiento.');
        } finally {
            CandelariaUI.restaurarBoton(btnGuardar);
        }
    }

    async cambiarEstadoItem(id, estadoActual) {
        const accionTexto = estadoActual ? 'desactivar' : 'activar';
        const confirmado = await CandelariaUI.confirmarAccion(
            `¿Está seguro de que desea ${accionTexto} este ítem comercial?`,
            'Cambio de Estado',
            `Sí, ${accionTexto}`
        );

        if (!confirmado.isConfirmed) return;

        try {
            const resp = await this.api.peticion(`catalogo/items/${id}/estado`, {
                metodo: 'PATCH',
                cuerpo: { activo: !estadoActual }
            });

            if (resp.exito) {
                CandelariaUI.notificarExito(resp.mensaje || `Ítem ${accionTexto}do correctamente.`);
                await this.cargarItems();
            } else {
                CandelariaUI.notificarError(resp.mensaje || 'No se pudo actualizar el estado.');
            }
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error al cambiar estado.');
        }
    }

    // =========================================================================
    // GESTIÓN DE CATEGORÍAS
    // =========================================================================

    limpiarFormCategoria() {
        this.categoriaEnEdicionId = null;
        document.getElementById('formCategoria')?.reset();
        document.getElementById('categoriaId').value = '';
        document.getElementById('tituloFormCategoria').innerHTML = '<i class="fa-solid fa-plus-circle me-1 text-primary"></i> Nueva Categoría';
        document.getElementById('btnCancelarEdicionCat')?.classList.add('d-none');
    }

    renderizarTablaCategorias() {
        const tbody = document.getElementById('tbodyCategorias');
        if (!tbody) return;

        if (this.categoriasCache.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">No hay categorías registradas.</td></tr>';
            return;
        }

        tbody.innerHTML = this.categoriasCache.map(cat => {
            const badgeEstado = cat.activo
                ? '<span class="badge bg-success-subtle text-success">Activa</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Inactiva</span>';

            return `
                <tr>
                    <td class="f-w-700">${cat.codigo}</td>
                    <td class="f-w-600">${this.escaparHtml(cat.nombre)}</td>
                    <td class="text-muted f-s-12">${this.escaparHtml(cat.descripcion || '-')}</td>
                    <td class="text-center">${badgeEstado}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-primary btn-sm btn-editar-cat py-0 px-2" data-id="${cat.id}">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm btn-estado-cat py-0 px-2" data-id="${cat.id}" data-activo="${cat.activo ? 1 : 0}">
                            <i class="fa-solid ${cat.activo ? 'fa-toggle-on text-success' : 'fa-toggle-off text-muted'}"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.btn-editar-cat').forEach(btn => {
            btn.addEventListener('click', () => {
                const cat = this.categoriasCache.find(c => String(c.id) === String(btn.dataset.id));
                if (!cat) return;

                this.categoriaEnEdicionId = cat.id;
                document.getElementById('categoriaId').value = cat.id;
                document.getElementById('catCodigo').value = cat.codigo;
                document.getElementById('catNombre').value = cat.nombre;
                document.getElementById('catDescripcion').value = cat.descripcion || '';
                document.getElementById('tituloFormCategoria').innerHTML = `<i class="fa-solid fa-pen me-1 text-primary"></i> Editar Categoría: ${this.escaparHtml(cat.codigo)}`;
                document.getElementById('btnCancelarEdicionCat')?.classList.remove('d-none');
            });
        });

        tbody.querySelectorAll('.btn-estado-cat').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const activo = btn.dataset.activo === '1';
                try {
                    const resp = await this.api.peticion(`catalogo/categorias/${id}/estado`, {
                        metodo: 'PATCH',
                        cuerpo: { activo: !activo }
                    });
                    if (resp.exito) {
                        await this.cargarCategorias();
                        this.renderizarTablaCategorias();
                        this.actualizarSelectsCategorias();
                    }
                } catch (e) {
                    CandelariaUI.notificarError(e.message || 'Error al cambiar estado.');
                }
            });
        });
    }

    async guardarCategoria() {
        const form = document.getElementById('formCategoria');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const id = document.getElementById('categoriaId').value;
        const cuerpo = {
            codigo: document.getElementById('catCodigo').value.trim().toUpperCase(),
            nombre: document.getElementById('catNombre').value.trim(),
            descripcion: document.getElementById('catDescripcion').value.trim(),
            activo: true
        };

        const btn = document.getElementById('btnGuardarCategoria');
        CandelariaUI.procesarBoton(btn, 'Guardando...');

        try {
            const endpoint = id ? `catalogo/categorias/${id}` : 'catalogo/categorias';
            const metodo = id ? 'PUT' : 'POST';

            const resp = await this.api.peticion(endpoint, {
                metodo,
                cuerpo
            });

            if (resp.exito) {
                CandelariaUI.notificarExito(resp.mensaje || 'Categoría guardada.');
                this.limpiarFormCategoria();
                await this.cargarCategorias();
                this.renderizarTablaCategorias();
                this.actualizarSelectsCategorias();
            } else {
                CandelariaUI.notificarError(resp.mensaje || 'No se pudo guardar la categoría.');
            }
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error al guardar categoría.');
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
    window.moduloCatalogoItems = new ModuloCatalogoItems();
});
