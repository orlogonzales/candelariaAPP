/**
 * ==============================================================================
 * CANDELARIAAPP - GESTIÓN DE COTIZACIONES COMERCIALES (cotizaciones.js)
 * Fase 2.4C - Arquitectura Alina UI, Fetch API, SweetAlert2 y Concurrencia Optimista
 * ==============================================================================
 */

'use strict';

class ModuloCotizaciones {
    constructor() {
        this.api = new CandelariaClienteApi();
        this.tabla = null;
        this.cotizacionesCache = [];
        this.ofertasCache = { items: [], paquetes: [] };
        this.lineasWizard = [];
        this.pasoActualWizard = 1;
        this.maxPasosWizard = 5;

        // Modales Bootstrap
        this.modalNueva = null;
        this.modalDetalle = null;
        this.modalRechazar = null;
        this.modalAnular = null;
        this.modalEditar = null;

        // Instancias Flatpickr
        this.fpFiltroRango = null;
        this.fpWizardValidez = null;

        this.init();
    }

    async init() {
        this.inicializarModales();
        this.inicializarSelectoresYPickers();
        this.vincularEventosUI();
        await this.cargarOfertasEdicion();
        await this.cargarCotizaciones();
    }

    inicializarModales() {
        if (!window.bootstrap?.Modal) return;

        const mNueva = document.getElementById('modalNuevaCotizacion');
        if (mNueva) this.modalNueva = new window.bootstrap.Modal(mNueva);

        const mDetalle = document.getElementById('modalDetalle360');
        if (mDetalle) this.modalDetalle = new window.bootstrap.Modal(mDetalle);

        const mRechazar = document.getElementById('modalRechazarCotizacion');
        if (mRechazar) this.modalRechazar = new window.bootstrap.Modal(mRechazar);

        const mAnular = document.getElementById('modalAnularCotizacion');
        if (mAnular) this.modalAnular = new window.bootstrap.Modal(mAnular);

        const mEditar = document.getElementById('modalEditarBorrador');
        if (mEditar) this.modalEditar = new window.bootstrap.Modal(mEditar);
    }

    inicializarSelectoresYPickers() {
        // Flatpickr para rango de fechas en filtros
        const elFiltroRango = document.getElementById('filtroRangoFechas');
        if (elFiltroRango && typeof window.flatpickr !== 'undefined') {
            this.fpFiltroRango = window.flatpickr(elFiltroRango, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                locale: {
                    rangeSeparator: ' a '
                },
                onChange: () => this.filtrarCotizaciones()
            });
        }

        // Flatpickr para fecha de validez en Wizard
        const elWizardValidez = document.getElementById('wizardValidoHasta');
        if (elWizardValidez && typeof window.flatpickr !== 'undefined') {
            this.fpWizardValidez = window.flatpickr(elWizardValidez, {
                dateFormat: 'Y-m-d',
                minDate: 'today'
            });
        }

        // Select2 remoto para clientes en Wizard
        if (typeof window.$ !== 'undefined' && window.$.fn.select2) {
            const $clienteSelect = window.$('#wizardClienteSelect');
            $clienteSelect.select2({
                dropdownParent: window.$('#modalNuevaCotizacion'),
                placeholder: 'Buscar cliente por nombre o documento...',
                allowClear: true,
                ajax: {
                    url: `${this.api.urlBase}/cotizaciones/aux/clientes`,
                    dataType: 'json',
                    delay: 250,
                    data: (params) => ({ q: params.term || '' }),
                    processResults: (data) => ({
                        results: data.datos?.resultados || []
                    }),
                    cache: true
                },
                minimumInputLength: 0
            });

            // Al seleccionar cliente, recargar oportunidades compatibles
            $clienteSelect.on('change', () => {
                const clienteId = $clienteSelect.val();
                this.actualizarSelectOportunidades(clienteId);
            });

            // Select2 para oportunidad en Wizard
            window.$('#wizardOportunidadSelect').select2({
                dropdownParent: window.$('#modalNuevaCotizacion'),
                placeholder: 'Seleccione oportunidad (opcional)...',
                allowClear: true
            });
        }
    }

    async actualizarSelectOportunidades(clienteId) {
        const $opSelect = window.$('#wizardOportunidadSelect');
        $opSelect.empty().append(new Option('Sin oportunidad vinculada', '', true, true));

        if (!clienteId) return;

        try {
            const resp = await this.api.peticion(`cotizaciones/aux/oportunidades?cliente_id=${clienteId}`);
            if (resp.exito && resp.datos?.resultados) {
                resp.datos.resultados.forEach(op => {
                    $opSelect.append(new Option(op.text, op.id, false, false));
                });
            }
        } catch (error) {
            console.error('[Cotizaciones] Error al cargar oportunidades para cliente:', error);
        }
    }

    vincularEventosUI() {
        // Recargar tabla
        document.getElementById('btnRecargarTabla')?.addEventListener('click', () => this.cargarCotizaciones());

        // Botón abrir modal nueva cotización
        document.getElementById('btnAbrirModalNueva')?.addEventListener('click', () => {
            this.reiniciarWizard();
            this.modalNueva?.show();
        });

        // Eventos del Wizard
        document.getElementById('btnWizardSiguiente')?.addEventListener('click', () => this.avanzarPasoWizard());
        document.getElementById('btnWizardAnterior')?.addEventListener('click', () => this.retrocederPasoWizard());
        document.getElementById('btnGuardarBorradorWizard')?.addEventListener('click', () => this.guardarCotizacionWizard());

        // Cambio de tipo de concepto en Wizard (ITEM vs PAQUETE)
        const radItem = document.getElementById('wizardTipoItem');
        const radPaq = document.getElementById('wizardTipoPaquete');
        radItem?.addEventListener('change', () => this.actualizarSelectorOfertas('ITEM'));
        radPaq?.addEventListener('change', () => this.actualizarSelectorOfertas('PAQUETE'));

        // Cambio de oferta seleccionada para vista previa de componentes
        document.getElementById('wizardOfertaSelect')?.addEventListener('change', (e) => {
            this.actualizarVistaPreviaOferta(e.target.value);
        });

        // Botón agregar línea en Wizard
        document.getElementById('btnWizardAgregarLinea')?.addEventListener('click', () => this.agregarLineaWizard());

        // Descuento global en Wizard
        document.getElementById('wizardDescGlobalTipo')?.addEventListener('change', (e) => {
            const esActivo = e.target.value !== 'NINGUNO';
            const valInput = document.getElementById('wizardDescGlobalValor');
            const motInput = document.getElementById('wizardDescGlobalMotivo');
            const reqSpan = document.getElementById('reqDescGlobal');

            if (valInput) valInput.disabled = !esActivo;
            if (motInput) motInput.disabled = !esActivo;
            if (reqSpan) reqSpan.style.display = esActivo ? 'inline' : 'none';
        });

        // Filtros de tabla
        ['filtroEstado', 'filtroVigencia'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => this.filtrarCotizaciones());
        });

        let debounceFiltro = null;
        document.getElementById('filtroBusqueda')?.addEventListener('input', () => {
            clearTimeout(debounceFiltro);
            debounceFiltro = setTimeout(() => this.filtrarCotizaciones(), 300);
        });

        document.getElementById('btnLimpiarFiltros')?.addEventListener('click', () => {
            const busq = document.getElementById('filtroBusqueda');
            const est = document.getElementById('filtroEstado');
            const vig = document.getElementById('filtroVigencia');
            if (busq) busq.value = '';
            if (est) est.value = '';
            if (vig) vig.value = '';
            this.fpFiltroRango?.clear();
            this.filtrarCotizaciones();
        });

        // Formularios de Rechazo y Anulación
        document.getElementById('rechazarMotivo')?.addEventListener('change', (e) => {
            const req = document.getElementById('reqRechazoDetalle');
            if (req) req.style.display = e.target.value === 'OTRO' ? 'inline' : 'none';
        });

        document.getElementById('formRechazarCotizacion')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.confirmarRechazoSubmit();
        });

        document.getElementById('anularMotivo')?.addEventListener('change', (e) => {
            const req = document.getElementById('reqAnularDetalle');
            if (req) req.style.display = e.target.value === 'OTRO' ? 'inline' : 'none';
        });

        document.getElementById('formAnularCotizacion')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.confirmarAnulacionSubmit();
        });

        // Formulario de edición de borrador
        document.getElementById('formEditarBorrador')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.guardarEdicionBorradorSubmit();
        });

        // Sincronización contextual con selector global de edición
        document.addEventListener('candelaria:edicion-cambiada', () => {
            this.cargarOfertasEdicion();
            this.cargarCotizaciones();
        });
    }

    async cargarOfertasEdicion() {
        try {
            const resp = await this.api.peticion('cotizaciones/aux/ofertas');
            if (resp.exito && resp.datos) {
                this.ofertasCache = {
                    items: resp.datos.items || [],
                    paquetes: resp.datos.paquetes || []
                };
                this.actualizarSelectorOfertas('ITEM');
            }
        } catch (error) {
            console.error('[Cotizaciones] Error al cargar ofertas de la edición:', error);
        }
    }

    actualizarSelectorOfertas(tipo) {
        const select = document.getElementById('wizardOfertaSelect');
        if (!select) return;

        select.innerHTML = '<option value="">Seleccione una oferta activa...</option>';
        const lista = tipo === 'ITEM' ? this.ofertasCache.items : this.ofertasCache.paquetes;

        lista.forEach(o => {
            const idVal = tipo === 'ITEM' ? `ITEM:${o.oferta_item_id}` : `PAQUETE:${o.oferta_paquete_id}`;
            const opt = document.createElement('option');
            opt.value = idVal;
            opt.textContent = `[${o.codigo}] ${o.nombre} - ${o.moneda} ${parseFloat(o.precio_unitario).toFixed(2)}`;
            select.appendChild(opt);
        });

        this.actualizarVistaPreviaOferta(select.value);
    }

    actualizarVistaPreviaOferta(valor) {
        const preview = document.getElementById('wizardVistaPreviaComponentes');
        const badgesContainer = document.getElementById('wizardBadgesComponentes');
        if (!preview || !badgesContainer) return;

        if (!valor || !valor.startsWith('PAQUETE:')) {
            preview.classList.add('d-none');
            badgesContainer.innerHTML = '';
            return;
        }

        const ofertaPaqueteId = parseInt(valor.split(':')[1], 10);
        const paq = this.ofertasCache.paquetes.find(p => parseInt(p.oferta_paquete_id, 10) === ofertaPaqueteId);

        if (paq && paq.componentes && paq.componentes.length > 0) {
            preview.classList.remove('d-none');
            badgesContainer.innerHTML = paq.componentes.map(c => `
                <span class="badge bg-light-primary text-primary border border-primary-subtle py-1 px-2 f-s-11">
                    <i class="fa-solid fa-cube me-1"></i> ${parseFloat(c.cantidad)} ${this.escaparHtml(c.unidad_medida)} de ${this.escaparHtml(c.nombre)}
                </span>
            `).join('');
        } else {
            preview.classList.add('d-none');
            badgesContainer.innerHTML = '';
        }
    }

    agregarLineaWizard() {
        const select = document.getElementById('wizardOfertaSelect');
        const cantInput = document.getElementById('wizardCantidad');
        if (!select || !select.value) {
            CandelariaUI.notificarError('Debe seleccionar una oferta activa para agregar.', 'Atención');
            return;
        }

        const [tipo, ofertaIdStr] = select.value.split(':');
        const ofertaId = parseInt(ofertaIdStr, 10);
        const cantidad = parseFloat(cantInput?.value || '1');

        if (isNaN(cantidad) || cantidad <= 0) {
            CandelariaUI.notificarError('La cantidad debe ser mayor a cero.', 'Atención');
            return;
        }

        let concepto = null;
        if (tipo === 'ITEM') {
            concepto = this.ofertasCache.items.find(i => parseInt(i.oferta_item_id, 10) === ofertaId);
        } else {
            concepto = this.ofertasCache.paquetes.find(p => parseInt(p.oferta_paquete_id, 10) === ofertaId);
        }

        if (!concepto) {
            CandelariaUI.notificarError('No se encontró el concepto en el catálogo activo.', 'Error');
            return;
        }

        const precio = parseFloat(concepto.precio_unitario);
        const subtotal = Math.round(cantidad * precio * 100) / 100;

        this.lineasWizard.push({
            tipo_linea: tipo,
            oferta_id: ofertaId,
            item_comercial_id: tipo === 'ITEM' ? concepto.item_comercial_id : null,
            oferta_item_id: tipo === 'ITEM' ? concepto.oferta_item_id : null,
            paquete_id: tipo === 'PAQUETE' ? concepto.paquete_id : null,
            oferta_paquete_id: tipo === 'PAQUETE' ? concepto.oferta_paquete_id : null,
            codigo: concepto.codigo,
            nombre: concepto.nombre,
            unidad_medida: concepto.unidad_medida || 'PAQUETE',
            cantidad: cantidad,
            precio_unitario: precio,
            subtotal: subtotal,
            moneda: concepto.moneda
        });

        // Reset selector
        select.value = '';
        if (cantInput) cantInput.value = '1';
        this.actualizarVistaPreviaOferta('');
        this.renderizarTablaLineasWizard();
    }

    renderizarTablaLineasWizard() {
        const tbody = document.querySelector('#tablaWizardLineas tbody');
        const subtotalSpan = document.getElementById('wizardSubtotalEstimado');
        if (!tbody) return;

        if (this.lineasWizard.length === 0) {
            tbody.innerHTML = `
                <tr id="filaSinLineasWizard">
                    <td colspan="7" class="text-center text-muted py-3">No hay conceptos agregados aún. Seleccione una oferta activa arriba.</td>
                </tr>
            `;
            if (subtotalSpan) subtotalSpan.textContent = '0.00';
            return;
        }

        let acumulado = 0;
        let html = '';

        this.lineasWizard.forEach((l, idx) => {
            acumulado += l.subtotal;
            const badgeTipo = l.tipo_linea === 'ITEM'
                ? '<span class="badge bg-light-primary text-primary border">ÍTEM</span>'
                : '<span class="badge bg-light-info text-info border">PAQUETE</span>';

            html += `
                <tr>
                    <td class="text-center">${badgeTipo}</td>
                    <td><strong>${this.escaparHtml(l.nombre)}</strong> <small class="text-muted d-block">${this.escaparHtml(l.codigo)}</small></td>
                    <td><span class="badge bg-light text-dark border">${this.escaparHtml(l.unidad_medida)}</span></td>
                    <td class="text-end f-w-600">${l.cantidad}</td>
                    <td class="text-end">${l.precio_unitario.toFixed(2)}</td>
                    <td class="text-end f-w-700">${l.subtotal.toFixed(2)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" title="Eliminar línea" onclick="window.moduloCotizaciones.eliminarLineaWizard(${idx})">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        if (subtotalSpan) subtotalSpan.textContent = acumulado.toFixed(2);
    }

    eliminarLineaWizard(indice) {
        if (indice >= 0 && indice < this.lineasWizard.length) {
            this.lineasWizard.splice(indice, 1);
            this.renderizarTablaLineasWizard();
        }
    }

    avanzarPasoWizard() {
        // Validaciones por paso
        if (this.pasoActualWizard === 1) {
            const titulo = document.getElementById('wizardTitulo')?.value.trim();
            const clienteId = window.$ ? window.$('#wizardClienteSelect').val() : document.getElementById('wizardClienteSelect')?.value;

            if (!titulo) {
                CandelariaUI.notificarError('Debe ingresar un título para la cotización.', 'Campo Requerido');
                return;
            }
            if (!clienteId) {
                CandelariaUI.notificarError('Debe seleccionar un cliente destinatario de la propuesta.', 'Campo Requerido');
                return;
            }
        } else if (this.pasoActualWizard === 2) {
            if (this.lineasWizard.length === 0) {
                CandelariaUI.notificarError('Debe agregar al menos una línea o concepto comercial.', 'Conceptos Requeridos');
                return;
            }
        } else if (this.pasoActualWizard === 3) {
            const tipoDesc = document.getElementById('wizardDescGlobalTipo')?.value;
            const valDesc = parseFloat(document.getElementById('wizardDescGlobalValor')?.value || '0');
            const motDesc = document.getElementById('wizardDescGlobalMotivo')?.value.trim();

            if (tipoDesc !== 'NINGUNO' && valDesc > 0 && !motDesc) {
                CandelariaUI.notificarError('Todo descuento global mayor a cero exige un motivo o justificación obligatoria.', 'Validación de Descuento');
                return;
            }
        }

        if (this.pasoActualWizard < this.maxPasosWizard) {
            this.pasoActualWizard++;
            this.actualizarUIWizard();
        }
    }

    retrocederPasoWizard() {
        if (this.pasoActualWizard > 1) {
            this.pasoActualWizard--;
            this.actualizarUIWizard();
        }
    }

    actualizarUIWizard() {
        // Ocultar todos los paneles
        for (let i = 1; i <= this.maxPasosWizard; i++) {
            const pane = document.getElementById(`wizardPaso${i}`);
            const badge = document.querySelector(`.wizard-step-item[data-step="${i}"]`);
            if (pane) {
                if (i === this.pasoActualWizard) {
                    pane.classList.remove('d-none');
                    pane.classList.add('active');
                } else {
                    pane.classList.add('d-none');
                    pane.classList.remove('active');
                }
            }
            if (badge) {
                if (i <= this.pasoActualWizard) {
                    badge.classList.add('active');
                } else {
                    badge.classList.remove('active');
                }
            }
        }

        // Botones de navegación
        const btnAnt = document.getElementById('btnWizardAnterior');
        const btnSig = document.getElementById('btnWizardSiguiente');
        const btnGuardar = document.getElementById('btnGuardarBorradorWizard');

        if (btnAnt) btnAnt.disabled = (this.pasoActualWizard === 1);

        if (this.pasoActualWizard === this.maxPasosWizard) {
            btnSig?.classList.add('d-none');
            btnGuardar?.classList.remove('d-none');
            this.construirPasoResumen();
        } else {
            btnSig?.classList.remove('d-none');
            btnGuardar?.classList.add('d-none');
        }
    }

    construirPasoResumen() {
        const titulo = document.getElementById('wizardTitulo')?.value.trim();
        const $cliSelect = window.$ ? window.$('#wizardClienteSelect') : null;
        const clienteTexto = $cliSelect ? $cliSelect.find('option:selected').text() : 'Cliente Seleccionado';

        const resTitulo = document.getElementById('resumenTitulo');
        const resCliente = document.getElementById('resumenCliente');
        if (resTitulo) resTitulo.textContent = titulo || '-';
        if (resCliente) resCliente.textContent = clienteTexto || '-';

        let subtotal = 0;
        const tbody = document.querySelector('#tablaResumenLineas tbody');
        if (tbody) {
            tbody.innerHTML = this.lineasWizard.map(l => {
                subtotal += l.subtotal;
                return `
                    <tr>
                        <td><strong>${this.escaparHtml(l.nombre)}</strong></td>
                        <td class="text-center"><span class="badge bg-light text-dark">${l.tipo_linea}</span></td>
                        <td class="text-end">${l.cantidad} ${this.escaparHtml(l.unidad_medida)}</td>
                        <td class="text-end">${l.precio_unitario.toFixed(2)}</td>
                        <td class="text-end f-w-700">${l.subtotal.toFixed(2)}</td>
                    </tr>
                `;
            }).join('');
        }

        // Descuento global
        const tipoDesc = document.getElementById('wizardDescGlobalTipo')?.value || 'NINGUNO';
        const valDesc = parseFloat(document.getElementById('wizardDescGlobalValor')?.value || '0');
        let montoDesc = 0;
        if (tipoDesc === 'PORCENTAJE') {
            montoDesc = Math.round(subtotal * (valDesc / 100) * 100) / 100;
        } else if (tipoDesc === 'MONTO_FIJO') {
            montoDesc = Math.min(subtotal, valDesc);
        }

        const totalFinal = Math.max(0, subtotal - montoDesc);

        document.getElementById('resumenSubtotal').textContent = subtotal.toFixed(2);
        document.getElementById('resumenDescGlobal').textContent = `-${montoDesc.toFixed(2)}`;
        document.getElementById('resumenTotalFinal').textContent = totalFinal.toFixed(2);
    }

    reiniciarWizard() {
        this.pasoActualWizard = 1;
        this.lineasWizard = [];
        const form = document.getElementById('formWizardCotizacion');
        if (form) form.reset();

        if (window.$) {
            window.$('#wizardClienteSelect').val('').trigger('change');
            window.$('#wizardOportunidadSelect').empty().append(new Option('Sin oportunidad vinculada', '', true, true));
        }

        this.renderizarTablaLineasWizard();
        this.actualizarUIWizard();
    }

    async guardarCotizacionWizard() {
        const btnGuardar = document.getElementById('btnGuardarBorradorWizard');
        CandelariaUI.procesarBoton(btnGuardar, 'Creando cotización...');

        try {
            const titulo = document.getElementById('wizardTitulo')?.value.trim();
            const clienteId = parseInt(window.$ ? window.$('#wizardClienteSelect').val() : '0', 10);
            const opVal = window.$ ? window.$('#wizardOportunidadSelect').val() : '';
            const oportunidadId = opVal ? parseInt(opVal, 10) : null;
            const terminos = document.getElementById('wizardTerminosCondiciones')?.value.trim() || null;
            const notas = document.getElementById('wizardNotasInternas')?.value.trim() || null;

            // 1. Crear borrador de cabecera
            const respBorrador = await this.api.peticion('cotizaciones', {
                metodo: 'POST',
                cuerpo: {
                    titulo: titulo,
                    cliente_id: clienteId,
                    oportunidad_id: oportunidadId,
                    terminos_condiciones: terminos,
                    notas_internas: notas
                }
            });

            if (!respBorrador.exito || !respBorrador.datos?.cotizacion) {
                throw new Error(respBorrador.mensaje || 'Error al crear la cabecera de la cotización.');
            }

            const cotizacionId = respBorrador.datos.cotizacion.id;

            // 2. Agregar cada línea seleccionada
            for (const linea of this.lineasWizard) {
                const cuerpoLinea = {
                    tipo_linea: linea.tipo_linea,
                    cantidad: linea.cantidad,
                    item_comercial_id: linea.item_comercial_id,
                    oferta_item_id: linea.oferta_item_id,
                    paquete_id: linea.paquete_id,
                    oferta_paquete_id: linea.oferta_paquete_id,
                    descuento_tipo: 'NINGUNO',
                    descuento_valor: 0
                };

                await this.api.peticion(`cotizaciones/${cotizacionId}/lineas`, {
                    metodo: 'POST',
                    cuerpo: cuerpoLinea
                });
            }

            // 3. Aplicar descuento global si se especificó
            const tipoDesc = document.getElementById('wizardDescGlobalTipo')?.value;
            const valDesc = parseFloat(document.getElementById('wizardDescGlobalValor')?.value || '0');
            const motDesc = document.getElementById('wizardDescGlobalMotivo')?.value.trim();

            if (tipoDesc && tipoDesc !== 'NINGUNO' && valDesc > 0) {
                await this.api.peticion(`cotizaciones/${cotizacionId}/descuento-global`, {
                    metodo: 'POST',
                    cuerpo: {
                        tipo: tipoDesc,
                        valor: valDesc,
                        motivo: motDesc
                    }
                });
            }

            this.modalNueva?.hide();
            await CandelariaUI.notificarExito('Borrador de cotización creado exitosamente.', '¡Guardado!');
            await this.cargarCotizaciones();
        } catch (error) {
            console.error('[Cotizaciones] Error al guardar cotización wizard:', error);
            if (error.status === 409) {
                this.manejarConflictoConcurrencia(error);
            } else {
                CandelariaUI.notificarError(error.message || 'Error al procesar la cotización.', 'Error');
            }
        } finally {
            CandelariaUI.restaurarBoton(btnGuardar);
        }
    }

    async cargarCotizaciones() {
        const contenedorTabla = document.getElementById('contenedorTablaCotizaciones');
        const skeleton = document.getElementById('skeletonTablaCotizaciones');

        if (skeleton && window.Skeleton) {
            skeleton.classList.remove('d-none');
            contenedorTabla?.classList.add('d-none');
            window.Skeleton.show('#skeletonTablaCotizaciones', 'table', { filas: 5, columnas: 9 });
        }

        try {
            const resp = await this.api.peticion('cotizaciones');
            if (resp.exito && resp.datos) {
                this.cotizacionesCache = resp.datos.cotizaciones || [];
                this.actualizarKpis(this.cotizacionesCache);
                this.renderizarDataTable(this.cotizacionesCache);
            }
        } catch (error) {
            console.error('[Cotizaciones] Error al cargar cotizaciones:', error);
            CandelariaUI.notificarError('No se pudo cargar el listado de cotizaciones.', 'Error');
        } finally {
            if (skeleton && window.Skeleton) {
                window.Skeleton.hide('#skeletonTablaCotizaciones');
                skeleton.classList.add('d-none');
                contenedorTabla?.classList.remove('d-none');
            }
        }
    }

    actualizarKpis(lista) {
        const total = lista.length;
        let emitidas = 0;
        let aceptadas = 0;
        let montoAceptado = 0;
        let moneda = 'PEN';

        lista.forEach(c => {
            if (c.moneda) moneda = c.moneda;
            if (c.estado === 'EMITIDA') emitidas++;
            if (c.estado === 'ACEPTADA') {
                aceptadas++;
                montoAceptado += c.total;
            }
        });

        document.getElementById('kpiTotalCotizaciones').textContent = total;
        document.getElementById('kpiEmitidas').textContent = emitidas;
        document.getElementById('kpiAceptadas').textContent = aceptadas;
        document.getElementById('kpiMontoAceptado').textContent = `${moneda} ${montoAceptado.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

        // Actualizar displays de moneda
        document.querySelectorAll('.moneda-display').forEach(el => el.textContent = moneda);
    }

    filtrarCotizaciones() {
        const busqueda = document.getElementById('filtroBusqueda')?.value.toLowerCase().trim() || '';
        const estado = document.getElementById('filtroEstado')?.value || '';
        const vigencia = document.getElementById('filtroVigencia')?.value || '';
        const rango = document.getElementById('filtroRangoFechas')?.value || '';

        let fechaDesde = null;
        let fechaHasta = null;
        if (rango.includes(' a ')) {
            [fechaDesde, fechaHasta] = rango.split(' a ');
        } else if (rango.trim() !== '') {
            fechaDesde = rango.trim();
            fechaHasta = rango.trim();
        }

        const filtradas = this.cotizacionesCache.filter(c => {
            // Filtro de búsqueda
            if (busqueda) {
                const corr = (c.correlativo || '').toLowerCase();
                const tit = (c.titulo || '').toLowerCase();
                const cli = (c.cliente_nombre || '').toLowerCase();
                if (!corr.includes(busqueda) && !tit.includes(busqueda) && !cli.includes(busqueda)) {
                    return false;
                }
            }

            // Filtro por estado
            if (estado && c.estado !== estado) {
                return false;
            }

            // Filtro por vigencia
            if (vigencia === 'VENCIDA' && !c.esta_vencida_efectiva && c.estado !== 'VENCIDA') {
                return false;
            }
            if (vigencia === 'VIGENTE' && (c.esta_vencida_efectiva || ['VENCIDA', 'ANULADA', 'RECHAZADA'].includes(c.estado))) {
                return false;
            }

            // Filtro por rango de fechas
            if (fechaDesde && (!c.fecha_emision || c.fecha_emision < fechaDesde)) {
                return false;
            }
            if (fechaHasta && (!c.fecha_emision || c.fecha_emision > fechaHasta)) {
                return false;
            }

            return true;
        });

        this.renderizarDataTable(filtradas);
    }

    renderizarDataTable(datos) {
        if (this.tabla && typeof window.$.fn.DataTable !== 'undefined') {
            this.tabla.destroy();
        }

        const tbody = document.querySelector('#tablaCotizaciones tbody');
        if (!tbody) return;

        tbody.innerHTML = datos.map(c => {
            const badgeCorrelativo = c.correlativo
                ? `<span class="f-w-700 text-dark">${this.escaparHtml(c.correlativo)}</span>`
                : `<span class="badge bg-gradient-secondary">BORRADOR</span>`;

            const badgeRevision = `<span class="badge bg-light text-dark border">v${c.version_numero}</span>`;

            let badgeEstado = '';
            switch (c.estado) {
                case 'BORRADOR':
                    badgeEstado = '<span class="badge bg-gradient-secondary">Borrador</span>';
                    break;
                case 'EMITIDA':
                    badgeEstado = '<span class="badge bg-gradient-info">Emitida</span>';
                    break;
                case 'ACEPTADA':
                    badgeEstado = '<span class="badge bg-gradient-success">Aceptada</span>';
                    break;
                case 'RECHAZADA':
                    badgeEstado = '<span class="badge bg-gradient-danger">Rechazada</span>';
                    break;
                case 'VENCIDA':
                    badgeEstado = '<span class="badge bg-gradient-warning">Vencida</span>';
                    break;
                case 'ANULADA':
                    badgeEstado = '<span class="badge bg-gradient-dark">Anulada</span>';
                    break;
                default:
                    badgeEstado = `<span class="badge bg-light text-dark">${c.estado}</span>`;
            }

            let badgeVigencia = '';
            if (c.esta_vencida_efectiva && c.estado === 'EMITIDA') {
                badgeVigencia = '<span class="badge bg-danger-subtle text-danger f-s-10 ms-1" title="Fecha límite superada">Vencida</span>';
            }

            const oportunidadDisplay = c.oportunidad_codigo
                ? `<span class="f-s-12 text-secondary" title="${this.escaparHtml(c.oportunidad_titulo)}">${this.escaparHtml(c.oportunidad_codigo)}</span>`
                : '<span class="text-muted f-s-12">-</span>';

            // Botones de acción contextual
            let botonesAcciones = `
                <button type="button" class="btn btn-outline-info btn-sm py-1 px-2 me-1" title="Ver Detalle 360" onclick="window.moduloCotizaciones.abrirDetalle360(${c.id})">
                    <i class="fa-solid fa-eye"></i>
                </button>
            `;

            if (c.estado === 'BORRADOR') {
                botonesAcciones += `
                    <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2 me-1" title="Editar Borrador" onclick="window.moduloCotizaciones.abrirEditarBorrador(${c.id})">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm py-1 px-2 me-1" title="Emitir Cotización" onclick="window.moduloCotizaciones.confirmarEmitir(${c.id}, ${c.version_bloqueo})">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                `;
            } else if (c.estado === 'EMITIDA') {
                botonesAcciones += `
                    <button type="button" class="btn btn-outline-warning btn-sm py-1 px-2 me-1" title="Crear Revisión (R${c.version_numero + 1})" onclick="window.moduloCotizaciones.confirmarCrearRevision(${c.id})">
                        <i class="fa-solid fa-code-branch"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm py-1 px-2 me-1" title="Conformidad / Aceptar" onclick="window.moduloCotizaciones.confirmarAceptar(${c.id})">
                        <i class="fa-solid fa-check"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2 me-1" title="Rechazar Propuesta" onclick="window.moduloCotizaciones.abrirModalRechazar(${c.id})">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm py-1 px-2 me-1" title="Anular Administrativamente" onclick="window.moduloCotizaciones.abrirModalAnular(${c.id})">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                `;
            }

            return `
                <tr>
                    <td>${badgeCorrelativo}<small class="text-muted d-block txt-ellipsis-1">${this.escaparHtml(c.titulo)}</small></td>
                    <td class="text-center">${badgeRevision}</td>
                    <td><strong>${this.escaparHtml(c.cliente_nombre)}</strong></td>
                    <td>${oportunidadDisplay}</td>
                    <td class="f-s-12">${c.fecha_emision || '<span class="text-muted">-</span>'}</td>
                    <td class="f-s-12">${c.valido_hasta ? (c.valido_hasta + badgeVigencia) : '<span class="text-muted">-</span>'}</td>
                    <td class="text-center">${badgeEstado}</td>
                    <td class="text-end f-w-700">${c.moneda} ${c.total.toFixed(2)}</td>
                    <td class="text-center text-nowrap">${botonesAcciones}</td>
                </tr>
            `;
        }).join('');

        if (window.$ && window.$.fn.DataTable) {
            this.tabla = window.$('#tablaCotizaciones').DataTable({
                responsive: true,
                language: {
                    url: `${window.CANDELARIA_BASE_URL || ''}/publico/alina/vendor/datatable/Spanish.json`,
                    emptyTable: 'No se encontraron cotizaciones para los filtros aplicados.'
                },
                order: [[0, 'desc']],
                pageLength: 15,
                bLengthChange: false,
                searching: false // Filtro ya implementado en barra de herramientas
            });
        }
    }

    async abrirDetalle360(id) {
        const modal = this.modalDetalle;
        if (!modal) return;

        const skeleton = document.getElementById('skeletonDetalle360');
        const contenido = document.getElementById('contenidoDetalle360');
        const subtitulo = document.getElementById('detalleSubtitulo');
        const contenedorAcciones = document.getElementById('detalleAccionesContextuales');

        if (skeleton) skeleton.classList.remove('d-none');
        if (contenido) contenido.classList.add('d-none');
        if (contenedorAcciones) contenedorAcciones.innerHTML = '';
        if (subtitulo) subtitulo.textContent = 'Cargando información...';

        modal.show();

        try {
            const resp = await this.api.peticion(`cotizaciones/${id}`);
            if (!resp.exito || !resp.datos) {
                throw new Error(resp.mensaje || 'Error al obtener el detalle de la cotización.');
            }

            const c = resp.datos.cotizacion;
            const cliente = resp.datos.cliente;
            const op = resp.datos.oportunidad;
            const lineas = resp.datos.lineas || [];
            const revisiones = resp.datos.revisiones || [];
            const auditoria = resp.datos.auditoria || [];

            if (subtitulo) {
                subtitulo.textContent = c.correlativo
                    ? `${c.correlativo} (Revisión v${c.version_numero}) - ${c.estado}`
                    : `Borrador #${c.id} - ${c.estado}`;
            }

            // Armar HTML de detalle 360
            let htmlLineas = '';
            lineas.forEach(l => {
                let badgeTipo = l.tipo_linea === 'ITEM'
                    ? '<span class="badge bg-light-primary text-primary border">ÍTEM</span>'
                    : '<span class="badge bg-light-info text-info border">PAQUETE</span>';

                let compHtml = '';
                if (l.tipo_linea === 'PAQUETE' && l.componentes && l.componentes.length > 0) {
                    compHtml = `
                        <div class="mt-2 p-2 bg-light b-r-6 border">
                            <small class="text-muted f-s-11 d-block mb-1 f-w-600"><i class="fa-solid fa-cubes-stacked me-1"></i> Componentes congelados en el snapshot:</small>
                            <div class="d-flex flex-wrap gap-1">
                                ${l.componentes.map(comp => `
                                    <span class="badge bg-white text-dark border py-1 px-2 f-s-11">
                                        ${parseFloat(comp.cantidad)} ${this.escaparHtml(comp.unidad_medida)} de ${this.escaparHtml(comp.item_nombre)}
                                    </span>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }

                htmlLineas += `
                    <tr>
                        <td class="text-center">${badgeTipo}</td>
                        <td>
                            <strong>${this.escaparHtml(l.concepto_nombre)}</strong>
                            <small class="text-muted d-block">${this.escaparHtml(l.concepto_codigo)}</small>
                            ${compHtml}
                        </td>
                        <td><span class="badge bg-light text-dark border">${this.escaparHtml(l.unidad_medida)}</span></td>
                        <td class="text-end f-w-600">${parseFloat(l.cantidad)}</td>
                        <td class="text-end">${parseFloat(l.precio_unitario).toFixed(2)}</td>
                        <td class="text-end text-danger">${parseFloat(l.descuento_monto) > 0 ? ('-' + parseFloat(l.descuento_monto).toFixed(2)) : '0.00'}</td>
                        <td class="text-end f-w-700">${parseFloat(l.subtotal).toFixed(2)}</td>
                    </tr>
                `;
            });

            // Cadena de revisiones
            let htmlRevisiones = '<p class="text-muted f-s-12 mb-0">Esta cotización no tiene revisiones asociadas.</p>';
            if (revisiones.length > 1) {
                htmlRevisiones = `
                    <div class="d-flex flex-wrap gap-2">
                        ${revisiones.map(r => `
                            <button type="button" class="btn ${r.id === c.id ? 'btn-primary' : 'btn-outline-secondary'} btn-sm" onclick="window.moduloCotizaciones.abrirDetalle360(${r.id})">
                                v${r.version_numero} ${r.correlativo ? `(${r.correlativo})` : '(Borrador)'} - ${r.estado}
                            </button>
                        `).join('')}
                    </div>
                `;
            }

            // Historial de auditoría
            let htmlAuditoria = '<p class="text-muted f-s-12 mb-0">Sin eventos de auditoría registrados.</p>';
            if (auditoria.length > 0) {
                htmlAuditoria = `
                    <ul class="list-group list-group-flush f-s-12">
                        ${auditoria.map(a => `
                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-light text-dark border me-2">${this.escaparHtml(a.accion)}</span>
                                    <span>${this.escaparHtml(a.actor_display)}</span>
                                </div>
                                <span class="text-muted f-s-11">${this.escaparHtml(a.creado_en)}</span>
                            </li>
                        `).join('')}
                    </ul>
                `;
            }

            if (contenido) {
                contenido.innerHTML = `
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card border p-3 b-r-8 h-100">
                                <h6 class="f-s-13 f-w-700 text-secondary mb-2"><i class="fa-solid fa-circle-info me-1"></i> Información General</h6>
                                <p class="mb-1 f-s-13"><strong>Título:</strong> ${this.escaparHtml(c.titulo)}</p>
                                <p class="mb-1 f-s-13"><strong>Cliente:</strong> ${this.escaparHtml(cliente.nombre_completo)}</p>
                                <p class="mb-1 f-s-13"><strong>Oportunidad:</strong> ${op ? `[${this.escaparHtml(op.codigo)}] ${this.escaparHtml(op.titulo)}` : 'Ninguna'}</p>
                                <p class="mb-1 f-s-13"><strong>Estado:</strong> <span class="badge bg-primary">${c.estado}</span></p>
                                <p class="mb-0 f-s-13"><strong>Emisión / Validez:</strong> ${c.fecha_emision || 'No emitida'} / ${c.valido_hasta || 'Indefinida'}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border p-3 b-r-8 h-100 bg-light-subtle">
                                <h6 class="f-s-13 f-w-700 text-secondary mb-2"><i class="fa-solid fa-coins me-1"></i> Resumen Económico</h6>
                                <div class="d-flex justify-content-between py-1 border-bottom f-s-13">
                                    <span>Subtotal Líneas:</span>
                                    <strong>${c.moneda} ${parseFloat(c.subtotal).toFixed(2)}</strong>
                                </div>
                                <div class="d-flex justify-content-between py-1 border-bottom f-s-13 text-danger">
                                    <span>Descuento Global:</span>
                                    <strong>-${parseFloat(c.descuento_global_monto).toFixed(2)} ${c.descuento_global_motivo ? `(${this.escaparHtml(c.descuento_global_motivo)})` : ''}</strong>
                                </div>
                                <div class="d-flex justify-content-between py-2 f-s-16 text-primary">
                                    <span class="f-w-700">Total Oficial:</span>
                                    <span class="f-w-700">${c.moneda} ${parseFloat(c.total).toFixed(2)}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pestaña / Tabla de Conceptos -->
                    <h6 class="f-s-14 f-w-700 text-dark mb-2"><i class="fa-solid fa-list-check text-primary me-1"></i> Conceptos y Desglose Comercial</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="f-s-11 text-center">Tipo</th>
                                    <th class="f-s-11">Concepto</th>
                                    <th class="f-s-11">Unidad</th>
                                    <th class="f-s-11 text-end">Cant.</th>
                                    <th class="f-s-11 text-end">P. Unitario</th>
                                    <th class="f-s-11 text-end">Desc.</th>
                                    <th class="f-s-11 text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>${htmlLineas}</tbody>
                        </table>
                    </div>

                    <!-- Términos y Notas -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card border p-3 b-r-8">
                                <h6 class="f-s-12 f-w-700 text-secondary mb-1">Términos y Condiciones</h6>
                                <p class="f-s-12 text-muted mb-0" style="white-space: pre-line;">${this.escaparHtml(c.terminos_condiciones || 'Sin términos especificados.')}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border p-3 b-r-8">
                                <h6 class="f-s-12 f-w-700 text-secondary mb-1">Notas Internas (Equipo)</h6>
                                <p class="f-s-12 text-muted mb-0" style="white-space: pre-line;">${this.escaparHtml(c.notas_internas || 'Sin notas internas.')}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Cadena de Revisiones -->
                    <div class="card border p-3 b-r-8 mb-4">
                        <h6 class="f-s-13 f-w-700 text-secondary mb-2"><i class="fa-solid fa-code-fork me-1"></i> Cadena de Revisiones</h6>
                        ${htmlRevisiones}
                    </div>

                    <!-- Auditoría Comercial -->
                    <div class="card border p-3 b-r-8">
                        <h6 class="f-s-13 f-w-700 text-secondary mb-2"><i class="fa-solid fa-clock-rotate-left me-1"></i> Auditoría Comercial Reciente</h6>
                        ${htmlAuditoria}
                    </div>
                `;
            }

            // Botones contextuales dentro del modal detalle
            if (contenedorAcciones) {
                if (c.estado === 'BORRADOR') {
                    contenedorAcciones.innerHTML = `
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.moduloCotizaciones.abrirEditarBorrador(${c.id})">
                            <i class="fa-solid fa-pen me-1"></i> Editar Borrador
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="window.moduloCotizaciones.confirmarEmitir(${c.id}, ${c.version_bloqueo})">
                            <i class="fa-solid fa-paper-plane me-1"></i> Emitir Cotización
                        </button>
                    `;
                } else if (c.estado === 'EMITIDA') {
                    contenedorAcciones.innerHTML = `
                        <button type="button" class="btn btn-warning btn-sm" onclick="window.moduloCotizaciones.confirmarCrearRevision(${c.id})">
                            <i class="fa-solid fa-code-branch me-1"></i> Nueva Revisión (R${c.version_numero + 1})
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="window.moduloCotizaciones.confirmarAceptar(${c.id})">
                            <i class="fa-solid fa-check me-1"></i> Conformidad Comercial
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="window.moduloCotizaciones.abrirModalRechazar(${c.id})">
                            <i class="fa-solid fa-ban me-1"></i> Rechazar
                        </button>
                        <button type="button" class="btn btn-dark btn-sm" onclick="window.moduloCotizaciones.abrirModalAnular(${c.id})">
                            <i class="fa-solid fa-xmark me-1"></i> Anular
                        </button>
                    `;
                }
            }

            if (skeleton) skeleton.classList.add('d-none');
            if (contenido) contenido.classList.remove('d-none');
        } catch (error) {
            console.error('[Cotizaciones] Error al cargar detalle 360:', error);
            if (subtitulo) subtitulo.textContent = 'Error al cargar datos';
            if (contenido) {
                contenido.innerHTML = `<div class="alert alert-danger">${this.escaparHtml(error.message)}</div>`;
                contenido.classList.remove('d-none');
            }
            if (skeleton) skeleton.classList.add('d-none');
        }
    }

    abrirEditarBorrador(id) {
        const c = this.cotizacionesCache.find(x => x.id === id);
        if (!c) return;

        document.getElementById('editBorradorId').value = c.id;
        document.getElementById('editVersionBloqueo').value = c.version_bloqueo;
        document.getElementById('editTitulo').value = c.titulo;
        document.getElementById('editTerminos').value = c.terminos_condiciones || '';
        document.getElementById('editNotasInternas').value = c.notas_internas || '';

        this.modalEditar?.show();
    }

    async guardarEdicionBorradorSubmit() {
        const id = document.getElementById('editBorradorId')?.value;
        const vBloqueo = parseInt(document.getElementById('editVersionBloqueo')?.value || '1', 10);
        const titulo = document.getElementById('editTitulo')?.value.trim();
        const terminos = document.getElementById('editTerminos')?.value.trim();
        const notas = document.getElementById('editNotasInternas')?.value.trim();
        const btn = document.getElementById('btnGuardarEdicionBorrador');

        CandelariaUI.procesarBoton(btn, 'Guardando...');

        try {
            const resp = await this.api.peticion(`cotizaciones/${id}`, {
                metodo: 'PUT',
                cuerpo: {
                    titulo: titulo,
                    terminos_condiciones: terminos,
                    notas_internas: notas,
                    version_bloqueo: vBloqueo
                }
            });

            if (!resp.exito) {
                throw new Error(resp.mensaje || 'Error al actualizar el borrador.');
            }

            this.modalEditar?.hide();
            await CandelariaUI.notificarExito('Borrador actualizado exitosamente.', '¡Guardado!');
            await this.cargarCotizaciones();
        } catch (error) {
            if (error.status === 409) {
                this.manejarConflictoConcurrencia(error);
            } else {
                CandelariaUI.notificarError(error.message || 'Error al guardar.', 'Error');
            }
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    async confirmarEmitir(id, versionBloqueo) {
        const confirm = await CandelariaUI.confirmarAccion(
            'Al emitir formalmente la cotización se generará su correlativo institucional oficial y su contenido comercial quedará CONGELADO e inmutable. ¿Desea proceder?',
            '¿Emitir Cotización?',
            'Sí, Emitir Cotización'
        );

        if (!confirm.isConfirmed) return;

        try {
            const resp = await this.api.peticion(`cotizaciones/${id}/emitir`, {
                metodo: 'POST',
                cuerpo: {
                    version_bloqueo: versionBloqueo
                }
            });

            if (!resp.exito) {
                throw new Error(resp.mensaje || 'Error al emitir cotización.');
            }

            this.modalDetalle?.hide();
            await CandelariaUI.notificarExito(resp.mensaje || 'Cotización emitida exitosamente.', '¡Emitida!');
            await this.cargarCotizaciones();
        } catch (error) {
            if (error.status === 409) {
                this.manejarConflictoConcurrencia(error);
            } else {
                CandelariaUI.notificarError(error.message || 'Error al emitir.', 'Error');
            }
        }
    }

    async confirmarCrearRevision(id) {
        const confirm = await CandelariaUI.confirmarAccion(
            'Se creará una nueva versión en BORRADOR que clonará exactamente los conceptos de esta propuesta para que pueda realizar ajustes. La versión actual permanecerá vigente hasta que la nueva revisión sea formalmente emitida. ¿Desea continuar?',
            '¿Crear Nueva Revisión?',
            'Sí, Crear Revisión'
        );

        if (!confirm.isConfirmed) return;

        try {
            const resp = await this.api.peticion(`cotizaciones/${id}/revision`, {
                metodo: 'POST'
            });

            if (!resp.exito) {
                throw new Error(resp.mensaje || 'Error al crear revisión.');
            }

            this.modalDetalle?.hide();
            await CandelariaUI.notificarExito(resp.mensaje || 'Nueva revisión creada en borrador.', '¡Revisión Creada!');
            await this.cargarCotizaciones();
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error al crear revisión.', 'Error');
        }
    }

    async confirmarAceptar(id) {
        const confirm = await CandelariaUI.confirmarAccion(
            'Se registrará la conformidad y aceptación formal por parte del cliente. Esta acción transiciona la propuesta a ACEPTADA y finaliza su ciclo de negociación. ¿Confirmar aceptación?',
            '¿Aceptar Cotización?',
            'Sí, Marcar Aceptada'
        );

        if (!confirm.isConfirmed) return;

        try {
            const resp = await this.api.peticion(`cotizaciones/${id}/aceptar`, {
                metodo: 'POST'
            });

            if (!resp.exito) {
                throw new Error(resp.mensaje || 'Error al aceptar cotización.');
            }

            this.modalDetalle?.hide();
            await CandelariaUI.notificarExito('La propuesta fue registrada como Aceptada.', '¡Aceptada!');
            await this.cargarCotizaciones();
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error al aceptar.', 'Error');
        }
    }

    abrirModalRechazar(id) {
        const form = document.getElementById('formRechazarCotizacion');
        if (form) form.reset();
        document.getElementById('rechazarCotizacionId').value = id;
        document.getElementById('reqRechazoDetalle').style.display = 'none';
        this.modalRechazar?.show();
    }

    async confirmarRechazoSubmit() {
        const id = document.getElementById('rechazarCotizacionId')?.value;
        const motivo = document.getElementById('rechazarMotivo')?.value;
        const detalle = document.getElementById('rechazarDetalle')?.value.trim();
        const btn = document.getElementById('btnConfirmarRechazo');

        if (!motivo) {
            CandelariaUI.notificarError('Debe seleccionar un motivo estructurado.', 'Validación');
            return;
        }

        if (motivo === 'OTRO' && !detalle) {
            CandelariaUI.notificarError('Al seleccionar OTRO, el detalle explicativo es obligatorio.', 'Validación');
            return;
        }

        CandelariaUI.procesarBoton(btn, 'Registrando...');

        try {
            const resp = await this.api.peticion(`cotizaciones/${id}/rechazar`, {
                metodo: 'POST',
                cuerpo: {
                    motivo: motivo,
                    detalle: detalle || null
                }
            });

            if (!resp.exito) {
                throw new Error(resp.mensaje || 'Error al rechazar cotización.');
            }

            this.modalRechazar?.hide();
            this.modalDetalle?.hide();
            await CandelariaUI.notificarExito('Cotización registrada formalmente como rechazada.', '¡Registrado!');
            await this.cargarCotizaciones();
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error al registrar rechazo.', 'Error');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    abrirModalAnular(id) {
        const form = document.getElementById('formAnularCotizacion');
        if (form) form.reset();
        document.getElementById('anularCotizacionId').value = id;
        document.getElementById('reqAnularDetalle').style.display = 'none';
        this.modalAnular?.show();
    }

    async confirmarAnulacionSubmit() {
        const id = document.getElementById('anularCotizacionId')?.value;
        const motivo = document.getElementById('anularMotivo')?.value;
        const detalle = document.getElementById('anularDetalle')?.value.trim();
        const btn = document.getElementById('btnConfirmarAnulacion');

        if (!motivo) {
            CandelariaUI.notificarError('Debe seleccionar un motivo de anulación.', 'Validación');
            return;
        }

        if (motivo === 'OTRO' && !detalle) {
            CandelariaUI.notificarError('Al seleccionar OTRO, la justificación es obligatoria.', 'Validación');
            return;
        }

        CandelariaUI.procesarBoton(btn, 'Anulando...');

        try {
            const resp = await this.api.peticion(`cotizaciones/${id}/anular`, {
                metodo: 'POST',
                cuerpo: {
                    motivo: motivo,
                    detalle: detalle || null
                }
            });

            if (!resp.exito) {
                throw new Error(resp.mensaje || 'Error al anular cotización.');
            }

            this.modalAnular?.hide();
            this.modalDetalle?.hide();
            await CandelariaUI.notificarExito('Cotización anulada administrativamente.', '¡Anulada!');
            await this.cargarCotizaciones();
        } catch (error) {
            CandelariaUI.notificarError(error.message || 'Error al anular.', 'Error');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    manejarConflictoConcurrencia(error) {
        const errObj = error.datos?.errores || {};
        const vActual = errObj.version_actual || 'desconocida';
        CandelariaUI.notificarError(
            `Conflicto de concurrencia: la cotización fue modificada por otro usuario (versión actual: ${vActual}). Los datos se recargarán sin sobrescritura.`,
            'Conflicto 409'
        );
        this.modalEditar?.hide();
        this.modalDetalle?.hide();
        this.cargarCotizaciones();
    }

    escaparHtml(str) {
        if (!str && str !== 0) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

// Inicialización global
document.addEventListener('DOMContentLoaded', () => {
    window.moduloCotizaciones = new ModuloCotizaciones();
});
