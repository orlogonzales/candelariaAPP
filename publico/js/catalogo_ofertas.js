/**
 * ==============================================================================
 * CANDELARIAAPP - OFERTAS POR EDICIÓN Y TARIFAS (catalogo_ofertas.js) - FASE 2.3C
 * ==============================================================================
 * - Habilitación de Ítems y Paquetes en Edición
 * - Fijación y actualización de Tarifas Vigentes
 * - Concurrencia optimista (HTTP 409) con recarga reactiva
 * - Historial append-only auditado con ZERO PII
 * ==============================================================================
 */

'use strict';

class ModuloCatalogoOfertas {
    constructor() {
        this.api = new CandelariaClienteApi();
        this.instanciaDtItems = null;
        this.instanciaDtPaquetes = null;
        this.ofertasData = { items: [], paquetes: [] };
        this.edicionIdActual = null;
        this.modalHabilitar = null;
        this.modalTarifa = null;
        this.modalHistorial = null;

        this.init();
    }

    async init() {
        this.inicializarModales();
        this.sincronizarEdicion();
        this.vincularEventosUI();
        await this.cargarOfertas();
    }

    inicializarModales() {
        if (!window.bootstrap?.Modal) return;

        const elHab = document.getElementById('modalHabilitarOferta');
        if (elHab) this.modalHabilitar = new window.bootstrap.Modal(elHab);

        const elTar = document.getElementById('modalCambiarTarifa');
        if (elTar) this.modalTarifa = new window.bootstrap.Modal(elTar);

        const elHist = document.getElementById('modalHistorialTarifas');
        if (elHist) this.modalHistorial = new window.bootstrap.Modal(elHist);
    }

    sincronizarEdicion() {
        const selector = document.getElementById('selectorEdicionContextual');
        const idEnPestana = sessionStorage.getItem('candelaria_edicion_trabajo_id');

        if (idEnPestana && selector) {
            const existe = Array.from(selector.options).some(o => o.value === idEnPestana);
            if (existe) {
                selector.value = idEnPestana;
            }
        }

        this.edicionIdActual = selector ? parseInt(selector.value, 10) : null;
    }

    vincularEventosUI() {
        // Selector de Edición
        document.getElementById('selectorEdicionContextual')?.addEventListener('change', (e) => {
            this.edicionIdActual = parseInt(e.target.value, 10);
            sessionStorage.setItem('candelaria_edicion_trabajo_id', String(this.edicionIdActual));
            this.cargarOfertas();
        });

        // Botón Recargar
        document.getElementById('btnRecargarOfertas')?.addEventListener('click', () => this.cargarOfertas());

        // Submit Habilitar Oferta
        document.getElementById('formHabilitarOferta')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.guardarHabilitacionOferta();
        });

        // Submit Guardar Tarifa
        document.getElementById('formCambiarTarifa')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.guardarTarifa();
        });

        // Recargar tras conflicto concurrencia
        document.getElementById('btnRecargarTrasConflicto')?.addEventListener('click', async () => {
            this.modalTarifa?.hide();
            await this.cargarOfertas();
            CandelariaUI.notificarExito('Datos recargados desde el servidor. Ya puede volver a intentar la operación.');
        });
    }

    async cargarOfertas() {
        if (!this.edicionIdActual) {
            CandelariaUI.notificarError('Seleccione una edición válida.');
            return;
        }

        const btnRecargar = document.getElementById('btnRecargarOfertas');
        if (btnRecargar) {
            btnRecargar.disabled = true;
            btnRecargar.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Cargando...';
        }

        if (window.Skeleton) {
            Skeleton.show('#contenedorTablaOfertasItems', 'table', { filas: 5, columnas: 7 });
            Skeleton.show('#contenedorTablaOfertasPaquetes', 'table', { filas: 4, columnas: 7 });
        }

        try {
            const resp = await this.api.peticion(`catalogo/ofertas?edicion_id=${this.edicionIdActual}`);
            if (resp.exito && resp.datos) {
                this.ofertasData = resp.datos;
                this.actualizarKpis();
                this.renderizarTablaItems(this.ofertasData.items || []);
                this.renderizarTablaPaquetes(this.ofertasData.paquetes || []);
            } else {
                CandelariaUI.notificarError(resp.mensaje || 'No se pudieron obtener las ofertas.');
            }
        } catch (error) {
            if (window.Skeleton) {
                Skeleton.error('#contenedorTablaOfertasItems', 'Error al cargar ofertas: ' + (error.message || 'Error del servidor'), {
                    texto: 'Reintentar',
                    accion: () => this.cargarOfertas()
                });
            } else {
                CandelariaUI.notificarError(error.message || 'Error al conectar con el servidor.');
            }
        } finally {
            if (window.Skeleton) {
                Skeleton.hide('#contenedorTablaOfertasItems');
                Skeleton.hide('#contenedorTablaOfertasPaquetes');
            }
            if (btnRecargar) {
                btnRecargar.disabled = false;
                btnRecargar.innerHTML = '<i class="fa-solid fa-rotate me-1"></i> Actualizar';
            }
        }
    }

    actualizarKpis() {
        const items = this.ofertasData.items || [];
        const paquetes = this.ofertasData.paquetes || [];

        const itemsHab = items.filter(i => i.ofertaHabilitada).length;
        const paqHab = paquetes.filter(p => p.ofertaHabilitada).length;

        const conTarifaItems = items.filter(i => i.tarifaVigente !== null).length;
        const conTarifaPaq = paquetes.filter(p => p.tarifaVigente !== null).length;

        document.getElementById('kpiItemsHabilitados').textContent = `${itemsHab} / ${items.length}`;
        document.getElementById('kpiPaquetesHabilitados').textContent = `${paqHab} / ${paquetes.length}`;
        document.getElementById('kpiTarifasVigentes').textContent = conTarifaItems + conTarifaPaq;
    }

    renderizarTablaItems(items) {
        if (window.jQuery && $.fn.DataTable.isDataTable('#tablaOfertasItems')) {
            $('#tablaOfertasItems').DataTable().destroy();
        }

        const tbody = document.getElementById('tbodyOfertasItems');
        if (!tbody) return;

        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No hay ítems comerciales en el catálogo.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(item => {
            const enOferta = !!item.ofertaHabilitada;
            const badgeOferta = enOferta
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-check me-1"></i> Habilitado</span>'
                : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="fa-solid fa-ban me-1"></i> Inactivo</span>';

            const capRef = item.capacidadReferencial !== null && item.capacidadReferencial !== undefined
                ? `<span class="badge bg-light text-dark border">${item.capacidadReferencial}</span>`
                : '<span class="text-muted f-s-12">No especificada</span>';

            let displayTarifa = '<span class="text-muted f-s-12">Sin fijar</span>';
            if (item.tarifaVigente) {
                const precio = parseFloat(item.tarifaVigente.precio).toFixed(2);
                displayTarifa = `<span class="f-w-700 text-success">${item.tarifaVigente.moneda} ${precio}</span>`;
            }

            return `
                <tr>
                    <td class="f-w-700"><span class="badge bg-light text-dark border">${item.itemCodigo}</span></td>
                    <td>
                        <span class="f-w-600 text-dark d-block">${this.escaparHtml(item.itemNombre)}</span>
                    </td>
                    <td>
                        <span class="badge bg-info-subtle text-info me-1">${item.itemTipo}</span>
                        <span class="f-s-12 text-muted">${this.escaparHtml(item.categoriaNombre || '')}</span>
                    </td>
                    <td class="text-center">${badgeOferta}</td>
                    <td class="text-center">${capRef}</td>
                    <td class="text-end">${displayTarifa}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary btn-sm btn-hab-item" data-id="${item.itemId}" data-nombre="${this.escaparHtml(item.itemNombre)}" data-oferta-id="${item.ofertaId || ''}" data-habilitada="${enOferta ? 1 : 0}" data-capacidad="${item.capacidadReferencial || ''}" title="Habilitar u organizar oferta">
                                <i class="fa-solid fa-sliders"></i> Oferta
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm btn-tarifa-item" data-id="${item.itemId}" data-nombre="${this.escaparHtml(item.itemNombre)}" data-tarifa-id="${item.tarifaVigente?.id || ''}" data-version="${item.tarifaVigente?.versionBloqueo || 1}" data-precio="${item.tarifaVigente?.precio || ''}" data-moneda="${item.tarifaVigente?.moneda || 'PEN'}" title="Fijar o cambiar tarifa">
                                <i class="fa-solid fa-coins"></i> Tarifa
                            </button>
                            ${item.tarifaVigente ? `
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-historial-item" data-id="${item.itemId}" data-codigo="${item.itemCodigo}" data-nombre="${this.escaparHtml(item.itemNombre)}" title="Ver historial de cambios">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        if (window.jQuery && $.fn.DataTable) {
            this.instanciaDtItems = $('#tablaOfertasItems').DataTable({
                pageLength: 10,
                language: {
                    search: "Buscar en ítems:",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ ítems en edición",
                    paginate: { previous: "Anterior", next: "Siguiente" }
                }
            });
        }

        // Tooltips de Bootstrap
        if (window.bootstrap?.Tooltip) {
            document.querySelectorAll('#tablaOfertasItems [data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
        }

        // Vincular acciones
        tbody.querySelectorAll('.btn-hab-item').forEach(btn => {
            btn.addEventListener('click', () => this.abrirModalHabilitar('ITEM', btn.dataset.id, btn.dataset.nombre, btn.dataset.ofertaId, btn.dataset.habilitada === '1', btn.dataset.capacidad));
        });

        tbody.querySelectorAll('.btn-tarifa-item').forEach(btn => {
            btn.addEventListener('click', () => this.abrirModalTarifa('ITEM', btn.dataset.id, btn.dataset.nombre, btn.dataset.tarifaId, btn.dataset.version, btn.dataset.precio, btn.dataset.moneda));
        });

        tbody.querySelectorAll('.btn-historial-item').forEach(btn => {
            btn.addEventListener('click', () => this.abrirModalHistorial('ITEM', btn.dataset.id, btn.dataset.codigo, btn.dataset.nombre));
        });
    }

    renderizarTablaPaquetes(paquetes) {
        if (window.jQuery && $.fn.DataTable.isDataTable('#tablaOfertasPaquetes')) {
            $('#tablaOfertasPaquetes').DataTable().destroy();
        }

        const tbody = document.getElementById('tbodyOfertasPaquetes');
        if (!tbody) return;

        if (paquetes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No hay paquetes registrados en el catálogo.</td></tr>';
            return;
        }

        tbody.innerHTML = paquetes.map(paq => {
            const enOferta = !!paq.ofertaHabilitada;
            const badgeOferta = enOferta
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-check me-1"></i> Habilitado</span>'
                : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="fa-solid fa-ban me-1"></i> Inactivo</span>';

            const cantItems = paq.cantidadItems || 0;
            const badgeItems = `<span class="badge bg-info-subtle text-info"><i class="fa-solid fa-layer-group me-1"></i> ${cantItems} ítems</span>`;

            const capRef = paq.capacidadReferencial !== null && paq.capacidadReferencial !== undefined
                ? `<span class="badge bg-light text-dark border">${paq.capacidadReferencial}</span>`
                : '<span class="text-muted f-s-12">No especificada</span>';

            let displayTarifa = '<span class="text-muted f-s-12">Sin fijar</span>';
            if (paq.tarifaVigente) {
                const precio = parseFloat(paq.tarifaVigente.precio).toFixed(2);
                displayTarifa = `<span class="f-w-700 text-success">${paq.tarifaVigente.moneda} ${precio}</span>`;
            }

            return `
                <tr>
                    <td class="f-w-700"><span class="badge bg-light text-dark border">${paq.paqueteCodigo}</span></td>
                    <td>
                        <span class="f-w-600 text-dark d-block">${this.escaparHtml(paq.paqueteNombre)}</span>
                    </td>
                    <td class="text-center">${badgeItems}</td>
                    <td class="text-center">${badgeOferta}</td>
                    <td class="text-center">${capRef}</td>
                    <td class="text-end">${displayTarifa}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary btn-sm btn-hab-paq" data-id="${paq.paqueteId}" data-nombre="${this.escaparHtml(paq.paqueteNombre)}" data-oferta-id="${paq.ofertaId || ''}" data-habilitada="${enOferta ? 1 : 0}" data-capacidad="${paq.capacidadReferencial || ''}" title="Habilitar u organizar oferta">
                                <i class="fa-solid fa-sliders"></i> Oferta
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm btn-tarifa-paq" data-id="${paq.paqueteId}" data-nombre="${this.escaparHtml(paq.paqueteNombre)}" data-tarifa-id="${paq.tarifaVigente?.id || ''}" data-version="${paq.tarifaVigente?.versionBloqueo || 1}" data-precio="${paq.tarifaVigente?.precio || ''}" data-moneda="${paq.tarifaVigente?.moneda || 'PEN'}" title="Fijar o cambiar tarifa">
                                <i class="fa-solid fa-coins"></i> Tarifa
                            </button>
                            ${paq.tarifaVigente ? `
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-historial-paq" data-id="${paq.paqueteId}" data-codigo="${paq.paqueteCodigo}" data-nombre="${this.escaparHtml(paq.paqueteNombre)}" title="Ver historial de cambios">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        if (window.jQuery && $.fn.DataTable) {
            this.instanciaDtPaquetes = $('#tablaOfertasPaquetes').DataTable({
                pageLength: 10,
                language: {
                    search: "Buscar en paquetes:",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ paquetes en edición",
                    paginate: { previous: "Anterior", next: "Siguiente" }
                }
            });
        }

        if (window.bootstrap?.Tooltip) {
            document.querySelectorAll('#tablaOfertasPaquetes [data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
        }

        // Vincular acciones
        tbody.querySelectorAll('.btn-hab-paq').forEach(btn => {
            btn.addEventListener('click', () => this.abrirModalHabilitar('PAQUETE', btn.dataset.id, btn.dataset.nombre, btn.dataset.ofertaId, btn.dataset.habilitada === '1', btn.dataset.capacidad));
        });

        tbody.querySelectorAll('.btn-tarifa-paq').forEach(btn => {
            btn.addEventListener('click', () => this.abrirModalTarifa('PAQUETE', btn.dataset.id, btn.dataset.nombre, btn.dataset.tarifaId, btn.dataset.version, btn.dataset.precio, btn.dataset.moneda));
        });

        tbody.querySelectorAll('.btn-historial-paq').forEach(btn => {
            btn.addEventListener('click', () => this.abrirModalHistorial('PAQUETE', btn.dataset.id, btn.dataset.codigo, btn.dataset.nombre));
        });
    }

    // =========================================================================
    // HABILITAR / EDITAR OFERTA
    // =========================================================================

    abrirModalHabilitar(tipo, id, nombre, ofertaId, habilitada, capacidad) {
        document.getElementById('habTipoElemento').value = tipo;
        document.getElementById('habElementoId').value = id;
        document.getElementById('habElementoNombre').textContent = `[${tipo}] ${nombre}`;
        document.getElementById('habCapacidad').value = capacidad || '';

        // Si ya tiene oferta existente, ocultamos tarifa inicial (se gestiona desde el modal de tarifas)
        const seccionTarifa = document.getElementById('seccionHabTarifaInicial');
        if (ofertaId) {
            seccionTarifa?.classList.add('d-none');
        } else {
            seccionTarifa?.classList.remove('d-none');
            document.getElementById('habTarifaInicial').value = '';
        }

        this.modalHabilitar?.show();
    }

    async guardarHabilitacionOferta() {
        const tipo = document.getElementById('habTipoElemento').value;
        const id = parseInt(document.getElementById('habElementoId').value, 10);
        const capacidad = document.getElementById('habCapacidad').value;
        const tarifaInicial = document.getElementById('habTarifaInicial').value;

        const cuerpo = {
            edicion_id: this.edicionIdActual,
            capacidad_referencial: capacidad ? parseInt(capacidad, 10) : null
        };

        if (tipo === 'ITEM') {
            cuerpo.item_comercial_id = id;
        } else {
            cuerpo.paquete_id = id;
        }

        if (tarifaInicial && parseFloat(tarifaInicial) > 0) {
            cuerpo.precio_inicial = parseFloat(tarifaInicial);
        }

        const btn = document.getElementById('btnGuardarHabilitar');
        CandelariaUI.procesarBoton(btn, 'Guardando...');

        try {
            const endpoint = tipo === 'ITEM' ? 'catalogo/ofertas/items' : 'catalogo/ofertas/paquetes';
            const resp = await this.api.peticion(endpoint, {
                metodo: 'POST',
                cuerpo
            });

            if (resp.exito) {
                CandelariaUI.notificarExito('Oferta guardada correctamente.');
                this.modalHabilitar?.hide();
                await this.cargarOfertas();
            } else {
                CandelariaUI.notificarError(resp.mensaje || 'No se pudo guardar la oferta.');
            }
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error de procesamiento.');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    // =========================================================================
    // CAMBIO DE TARIFA CON CONCURRENCIA OPTIMISTA (409)
    // =========================================================================

    abrirModalTarifa(tipo, id, nombre, tarifaId, version, precio, moneda) {
        document.getElementById('alertaConflictoConcurrencia')?.classList.add('d-none');
        document.getElementById('tarifaTipoElemento').value = tipo;
        document.getElementById('tarifaElementoId').value = id;
        document.getElementById('tarifaId').value = tarifaId || '';
        document.getElementById('tarifaVersionBloqueo').value = version || 1;
        document.getElementById('tarifaElementoNombre').textContent = `[${tipo}] ${nombre}`;

        const displayActual = (tarifaId && precio)
            ? `${moneda || 'PEN'} ${parseFloat(precio).toFixed(2)} (v${version || 1})`
            : 'Sin tarifa previa registrada';
        document.getElementById('tarifaPrecioActualDisplay').textContent = displayActual;

        document.getElementById('tarifaNuevoPrecio').value = precio ? parseFloat(precio).toFixed(2) : '';
        document.getElementById('tarifaMotivo').value = '';

        this.modalTarifa?.show();
    }

    async guardarTarifa() {
        const form = document.getElementById('formCambiarTarifa');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const tipo = document.getElementById('tarifaTipoElemento').value;
        const elementoId = parseInt(document.getElementById('tarifaElementoId').value, 10);
        const tarifaId = document.getElementById('tarifaId').value;
        const versionBloqueo = parseInt(document.getElementById('tarifaVersionBloqueo').value, 10) || 1;
        const nuevoPrecio = parseFloat(document.getElementById('tarifaNuevoPrecio').value);
        const motivo = document.getElementById('tarifaMotivo').value.trim();

        const btn = document.getElementById('btnGuardarTarifa');
        CandelariaUI.procesarBoton(btn, 'Guardando...');
        document.getElementById('alertaConflictoConcurrencia')?.classList.add('d-none');

        try {
            let resp;
            if (!tarifaId) {
                // Tarifa inicial
                const endpoint = tipo === 'ITEM' ? 'catalogo/tarifas/items' : 'catalogo/tarifas/paquetes';
                const cuerpo = {
                    edicion_id: this.edicionIdActual,
                    precio: nuevoPrecio,
                    motivo: motivo || 'Tarifa inicial fijada'
                };
                if (tipo === 'ITEM') cuerpo.item_comercial_id = elementoId;
                else cuerpo.paquete_id = elementoId;

                resp = await this.api.peticion(endpoint, {
                    metodo: 'POST',
                    cuerpo
                });
            } else {
                // Actualización con optimistic locking
                const endpoint = tipo === 'ITEM' ? `catalogo/tarifas/items/${tarifaId}` : `catalogo/tarifas/paquetes/${tarifaId}`;
                const cuerpo = {
                    precio: nuevoPrecio,
                    motivo: motivo,
                    version_bloqueo: versionBloqueo
                };

                resp = await this.api.peticion(endpoint, {
                    metodo: 'PUT',
                    cuerpo
                });
            }

            if (resp.exito) {
                CandelariaUI.notificarExito(resp.mensaje || 'Tarifa guardada con éxito.');
                this.modalTarifa?.hide();
                await this.cargarOfertas();
            } else {
                CandelariaUI.notificarError(resp.mensaje || 'No se pudo actualizar la tarifa.');
            }
        } catch (error) {
            console.error('[CatalogoTarifas Error]', error);

            if (error.status === 409) {
                // Manejo de concurrencia optimista 409
                const alerta = document.getElementById('alertaConflictoConcurrencia');
                const mensaje = document.getElementById('mensajeConflictoConcurrencia');
                if (alerta && mensaje) {
                    mensaje.textContent = error.datos?.mensaje || 'La tarifa fue modificada por otro usuario concurrentemente.';
                    alerta.classList.remove('d-none');
                } else {
                    CandelariaUI.notificarError(error.datos?.mensaje || 'Conflicto de concurrencia. Recargue los datos.');
                }
            } else {
                CandelariaUI.notificarError(error.message || 'Error al guardar la tarifa.');
            }
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    // =========================================================================
    // HISTORIAL DE TARIFAS (Append-Only - ZERO PII)
    // =========================================================================

    async abrirModalHistorial(tipo, elementoId, codigo, nombre) {
        document.getElementById('historialElementoCodigo').textContent = codigo;
        document.getElementById('historialElementoNombre').textContent = `[${tipo}] ${nombre}`;

        const tbody = document.getElementById('tbodyHistorialTarifas');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando historial...</td></tr>';
        }

        this.modalHistorial?.show();
        if (window.Skeleton) {
            Skeleton.show('#contenedorTablaHistorial', 'table', { filas: 3, columnas: 6 });
        }

        try {
            const endpoint = tipo === 'ITEM'
                ? `catalogo/tarifas/items/${elementoId}/historial?edicion_id=${this.edicionIdActual}`
                : `catalogo/tarifas/paquetes/${elementoId}/historial?edicion_id=${this.edicionIdActual}`;

            const resp = await this.api.peticion(endpoint);
            const list = Array.isArray(resp.datos) ? resp.datos : (resp.datos?.historial || []);
            if (resp.exito) {
                this.renderizarTablaHistorial(list);
            } else {
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-danger">No se pudo cargar el historial.</td></tr>';
            }
        } catch (error) {
            if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.escaparHtml(error.message)}</td></tr>`;
        } finally {
            if (window.Skeleton) {
                Skeleton.hide('#contenedorTablaHistorial');
            }
        }
    }

    renderizarTablaHistorial(registros) {
        const tbody = document.getElementById('tbodyHistorialTarifas');
        if (!tbody) return;

        if (registros.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">No se registran variaciones tarifarias históricas.</td></tr>';
            return;
        }

        tbody.innerHTML = registros.map(r => {
            const precioAnt = r.precioAnterior !== null && r.precioAnterior !== undefined
                ? parseFloat(r.precioAnterior).toFixed(2)
                : '-';
            const precioNuevo = parseFloat(r.precioNuevo).toFixed(2);

            const actorTxt = r.actor_display || r.actorDisplay || (r.actorTipo === 'HUMANO' ? 'Operador Comercial' : 'Sistema');
            const badgeActor = r.actorTipo === 'HUMANO'
                ? `<span class="badge bg-light text-dark border"><i class="fa-solid fa-user me-1 text-primary"></i> ${this.escaparHtml(actorTxt)}</span>`
                : `<span class="badge bg-light text-secondary border"><i class="fa-solid fa-robot me-1 text-info"></i> ${this.escaparHtml(actorTxt)}</span>`;

            return `
                <tr>
                    <td class="f-s-12 text-muted">${r.creadoEn || '-'}</td>
                    <td class="text-end">${precioAnt}</td>
                    <td class="text-end f-w-700 text-success">${precioNuevo}</td>
                    <td class="text-center"><span class="badge bg-light text-secondary border">${r.moneda}</span></td>
                    <td class="f-s-13">${this.escaparHtml(r.motivo || '-')}</td>
                    <td class="f-s-12">${badgeActor}</td>
                </tr>
            `;
        }).join('');
    }

    escaparHtml(texto) {
        if (!texto) return '';
        const mapa = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(texto).replace(/[&<>"']/g, m => mapa[m]);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.moduloCatalogoOfertas = new ModuloCatalogoOfertas();
});
