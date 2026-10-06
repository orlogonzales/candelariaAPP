/**
 * ==============================================================================
 * CANDELARIAAPP - GESTIÓN DE VENTAS COMERCIALES (ventas.js)
 * Fase 2.5C - Arquitectura Alina UI, Fetch API, SweetAlert2 y Concurrencia Optimista
 * ==============================================================================
 */

'use strict';

class ModuloVentas {
    constructor() {
        this.api = new CandelariaClienteApi();
        this.tabla = null;
        this.ventasCache = [];
        this.ventaSeleccionada = null;

        // Modales Bootstrap
        this.modalConvertir = null;
        this.modalDetalle = null;
        this.modalCancelar = null;
        this.modalAnular = null;

        // Instancias Flatpickr
        this.fpFechaDesde = null;
        this.fpFechaHasta = null;

        this.init();
    }

    async init() {
        this.inicializarModales();
        this.inicializarSelectoresYPickers();
        this.vincularEventosUI();
        await this.cargarVentas();
    }

    inicializarModales() {
        if (!window.bootstrap?.Modal) return;

        const mConvertir = document.getElementById('modalConvertirVenta');
        if (mConvertir) this.modalConvertir = new window.bootstrap.Modal(mConvertir);

        const mDetalle = document.getElementById('modalDetalleVenta');
        if (mDetalle) this.modalDetalle = new window.bootstrap.Modal(mDetalle);

        const mCancelar = document.getElementById('modalCancelarVenta');
        if (mCancelar) this.modalCancelar = new window.bootstrap.Modal(mCancelar);

        const mAnular = document.getElementById('modalAnularVenta');
        if (mAnular) this.modalAnular = new window.bootstrap.Modal(mAnular);
    }

    inicializarSelectoresYPickers() {
        // Flatpickr para fechas en filtros
        if (typeof window.flatpickr !== 'undefined') {
            const elDesde = document.getElementById('filtroFechaDesde');
            if (elDesde) {
                this.fpFechaDesde = window.flatpickr(elDesde, {
                    dateFormat: 'Y-m-d',
                    allowInput: false
                });
            }

            const elHasta = document.getElementById('filtroFechaHasta');
            if (elHasta) {
                this.fpFechaHasta = window.flatpickr(elHasta, {
                    dateFormat: 'Y-m-d',
                    allowInput: false
                });
            }
        }

        // Select2 remoto para cotizaciones aceptadas
        if (typeof window.$ !== 'undefined' && window.$.fn.select2) {
            const $selectCot = window.$('#selectCotizacionAceptada');
            $selectCot.select2({
                dropdownParent: window.$('#modalConvertirVenta'),
                placeholder: 'Buscar cotización aceptada por correlativo o cliente...',
                allowClear: true,
                width: '100%',
                ajax: {
                    url: `${this.api.urlBase}/ventas/aux/cotizaciones-aceptadas`,
                    dataType: 'json',
                    delay: 250,
                    data: (params) => ({ q: params.term || '' }),
                    processResults: (res) => ({
                        results: res.datos?.items || []
                    }),
                    cache: true
                },
                minimumInputLength: 0
            });

            $selectCot.on('select2:select', (e) => {
                const data = e.params.data;
                this.mostrarPreviewCotizacion(data);
            });

            $selectCot.on('select2:clear', () => {
                this.ocultarPreviewCotizacion();
            });
        }
    }

    mostrarPreviewCotizacion(data) {
        const preview = document.getElementById('previewCotizacionContenedor');
        if (!preview) return;

        document.getElementById('prevCorrelativo').textContent = data.correlativo || '-';
        document.getElementById('prevCliente').textContent = data.cliente_nombre || '-';
        document.getElementById('prevDocumento').textContent = data.cliente_documento || '-';
        
        const moneda = data.moneda || window.CANDELARIA_MONEDA_DEFECTO || 'PEN';
        const total = parseFloat(data.total || 0).toFixed(2);
        document.getElementById('prevTotal').textContent = `${moneda} ${total}`;

        preview.classList.remove('d-none');
    }

    ocultarPreviewCotizacion() {
        const preview = document.getElementById('previewCotizacionContenedor');
        if (preview) preview.classList.add('d-none');
    }

    vincularEventosUI() {
        // Botón Actualizar
        const btnRecargar = document.getElementById('btnRecargarTabla');
        if (btnRecargar) {
            btnRecargar.addEventListener('click', () => this.cargarVentas());
        }

        // Botón Abrir Modal de Conversión
        const btnAbrirModal = document.getElementById('btnAbrirModalConversion');
        if (btnAbrirModal) {
            btnAbrirModal.addEventListener('click', () => this.abrirModalConversion());
        }

        // Botón Confirmar Conversión a Venta
        const btnConfirmarConversion = document.getElementById('btnConfirmarConversion');
        if (btnConfirmarConversion) {
            btnConfirmarConversion.addEventListener('click', () => this.ejecutarConversion());
        }

        // Botones de Filtro
        const btnFiltrar = document.getElementById('btnAplicarFiltros');
        if (btnFiltrar) {
            btnFiltrar.addEventListener('click', () => this.cargarVentas());
        }

        const btnLimpiar = document.getElementById('btnLimpiarFiltros');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => this.limpiarFiltros());
        }

        // Búsqueda en vivo (debounce)
        const inputBusqueda = document.getElementById('filtroBusqueda');
        if (inputBusqueda) {
            let timeout = null;
            inputBusqueda.addEventListener('input', () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => this.cargarVentas(), 350);
            });
        }

        // Cambio en select de estado
        const selectEstado = document.getElementById('filtroEstado');
        if (selectEstado) {
            selectEstado.addEventListener('change', () => this.cargarVentas());
        }

        // Dinámica de detalle requerido en Cancelación
        const selectMotivoCancel = document.getElementById('selectMotivoCancelacion');
        if (selectMotivoCancel) {
            selectMotivoCancel.addEventListener('change', () => {
                const esOtro = selectMotivoCancel.value === 'OTRO';
                const reqAsterisk = document.getElementById('reqDetalleCancelacion');
                const txtDetalle = document.getElementById('cancelarMotivoDetalle');
                if (reqAsterisk) reqAsterisk.classList.toggle('d-none', !esOtro);
                if (txtDetalle) txtDetalle.required = esOtro;
            });
        }

        // Dinámica de detalle requerido en Anulación
        const selectMotivoAnul = document.getElementById('selectMotivoAnulacion');
        if (selectMotivoAnul) {
            selectMotivoAnul.addEventListener('change', () => {
                const esOtro = selectMotivoAnul.value === 'OTRO';
                const reqAsterisk = document.getElementById('reqDetalleAnulacion');
                const txtDetalle = document.getElementById('anularMotivoDetalle');
                if (reqAsterisk) reqAsterisk.classList.toggle('d-none', !esOtro);
                if (txtDetalle) txtDetalle.required = esOtro;
            });
        }

        // Botón Confirmar Cancelación
        const btnConfCancel = document.getElementById('btnConfirmarCancelacion');
        if (btnConfCancel) {
            btnConfCancel.addEventListener('click', () => this.ejecutarCancelacion());
        }

        // Botón Confirmar Anulación
        const btnConfAnul = document.getElementById('btnConfirmarAnulacion');
        if (btnConfAnul) {
            btnConfAnul.addEventListener('click', () => this.ejecutarAnulacion());
        }

        // Botones de acción rápida dentro del modal de detalle
        const btnModalDetCancel = document.getElementById('btnAccionCancelarVenta');
        if (btnModalDetCancel) {
            btnModalDetCancel.addEventListener('click', () => {
                if (this.ventaSeleccionada) {
                    if (this.modalDetalle) this.modalDetalle.hide();
                    this.abrirModalCancelar(this.ventaSeleccionada.id, this.ventaSeleccionada.correlativo, this.ventaSeleccionada.versionBloqueo);
                }
            });
        }

        const btnModalDetAnul = document.getElementById('btnAccionAnularVenta');
        if (btnModalDetAnul) {
            btnModalDetAnul.addEventListener('click', () => {
                if (this.ventaSeleccionada) {
                    if (this.modalDetalle) this.modalDetalle.hide();
                    this.abrirModalAnular(this.ventaSeleccionada.id, this.ventaSeleccionada.correlativo, this.ventaSeleccionada.versionBloqueo);
                }
            });
        }
    }

    limpiarFiltros() {
        const b = document.getElementById('filtroBusqueda');
        if (b) b.value = '';
        const e = document.getElementById('filtroEstado');
        if (e) e.value = '';
        if (this.fpFechaDesde) this.fpFechaDesde.clear();
        if (this.fpFechaHasta) this.fpFechaHasta.clear();
        this.cargarVentas();
    }

    // =========================================================================
    // CARGA DE DATOS Y RENDERIZADO DE TABLA (DATA TABLES / FETCH)
    // =========================================================================

    async cargarVentas() {
        const skeleton = document.getElementById('skeletonVentas');
        const contenedor = document.getElementById('contenedorTablaVentas');

        if (skeleton) skeleton.classList.remove('d-none');
        if (skeleton && window.Skeleton) {
            window.Skeleton.show('#skeletonVentas', 'table', { filas: 5, columnas: 8 });
        }
        if (contenedor) contenedor.classList.add('d-none');

        const params = new URLSearchParams();

        const busqueda = document.getElementById('filtroBusqueda')?.value.trim();
        if (busqueda) params.append('busqueda', busqueda);

        const estado = document.getElementById('filtroEstado')?.value;
        if (estado) params.append('estado', estado);

        const desde = document.getElementById('filtroFechaDesde')?.value;
        if (desde) params.append('fecha_desde', desde);

        const hasta = document.getElementById('filtroFechaHasta')?.value;
        if (hasta) params.append('fecha_hasta', hasta);

        try {
            const url = `ventas?${params.toString()}`;
            const res = await this.api.peticion(url);

            if (res.exito) {
                this.ventasCache = res.datos?.ventas || [];
                this.renderizarTabla(this.ventasCache);
                this.actualizarKPIs(this.ventasCache);
            } else {
                CandelariaUI.notificarError(res.mensaje || 'Error al cargar ventas.');
            }
        } catch (err) {
            CandelariaUI.notificarError('No se pudo conectar con el servidor para consultar las ventas.');
        } finally {
            if (skeleton && window.Skeleton) {
                window.Skeleton.hide('#skeletonVentas');
            }
            if (skeleton) skeleton.classList.add('d-none');
            if (contenedor) contenedor.classList.remove('d-none');
        }
    }

    renderizarTabla(ventas) {
        const cuerpo = document.getElementById('cuerpoTablaVentas');
        if (!cuerpo) return;

        if (this.tabla && typeof window.$ !== 'undefined' && window.$.fn.DataTable) {
            window.$('#tablaVentas').DataTable().destroy();
        }

        cuerpo.innerHTML = '';

        if (ventas.length === 0) {
            cuerpo.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-receipt f-s-32 mb-2 d-block text-secondary"></i>
                        No se encontraron ventas registradas con los filtros aplicados.
                    </td>
                </tr>
            `;
            return;
        }

        ventas.forEach((v) => {
            const tr = document.createElement('tr');

            // Badge Estado
            let badgeEstado = '';
            if (v.estado === 'CONFIRMADA') {
                badgeEstado = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 b-r-6"><i class="fa-solid fa-circle-check me-1"></i>Confirmada</span>';
            } else if (v.estado === 'CANCELADA') {
                badgeEstado = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 b-r-6"><i class="fa-solid fa-ban me-1"></i>Cancelada</span>';
            } else if (v.estado === 'ANULADA') {
                badgeEstado = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 b-r-6"><i class="fa-solid fa-xmark me-1"></i>Anulada</span>';
            } else {
                badgeEstado = `<span class="badge bg-secondary px-2 py-1 b-r-6">${this.escaparHtml(v.estado)}</span>`;
            }

            // Origen Badge
            const badgeOrigen = `<span class="badge bg-light text-secondary border ms-1 f-s-10">${this.escaparHtml(v.origen_tipo || 'COTIZACION')}</span>`;
            const cotOrigenText = v.cotizacion_correlativo ? `<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fa-solid fa-file-invoice me-1"></i>${this.escaparHtml(v.cotizacion_correlativo)}</span>` : '<span class="text-muted f-s-12">Directa</span>';

            const totalFormateado = `${this.escaparHtml(v.moneda)} ${parseFloat(v.total).toFixed(2)}`;

            // Botones de acción según permisos y estado
            const puedeCancelar = window.CANDELARIA_PERMISOS?.cancelar && v.estado === 'CONFIRMADA';
            const puedeAnular = window.CANDELARIA_PERMISOS?.anular && v.estado === 'CONFIRMADA';

            tr.innerHTML = `
                <td class="f-w-600 text-dark">
                    ${this.escaparHtml(v.correlativo)}
                    ${badgeOrigen}
                </td>
                <td class="f-s-13 text-secondary">${this.escaparHtml(v.fecha_venta || '-')}</td>
                <td>
                    <span class="d-block f-w-600 text-dark">${this.escaparHtml(v.cliente_nombre_completo)}</span>
                    <small class="text-muted f-s-11"><i class="fa-solid fa-id-card me-1"></i>${this.escaparHtml(v.cliente_numero_documento || '-')}</small>
                </td>
                <td>${cotOrigenText}</td>
                <td class="text-end f-w-700 text-dark f-s-14">${totalFormateado}</td>
                <td class="text-center">${badgeEstado}</td>
                <td class="text-center"><span class="badge bg-light text-dark border">${parseInt(v.total_lineas || 0, 10)}</span></td>
                <td class="text-end">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Acciones">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm b-r-10 border-0">
                            <li>
                                <button class="dropdown-item py-2 btn-ver-detalle" data-id="${v.id}">
                                    <i class="fa-solid fa-eye text-primary me-2"></i> Ver Detalle 360
                                </button>
                            </li>
                            ${puedeCancelar ? `
                            <li>
                                <button class="dropdown-item py-2 text-warning btn-cancelar-venta" data-id="${v.id}" data-correlativo="${this.escaparHtml(v.correlativo)}" data-version="${v.version_bloqueo}">
                                    <i class="fa-solid fa-ban me-2"></i> Cancelar Venta
                                </button>
                            </li>
                            ` : ''}
                            ${puedeAnular ? `
                            <li>
                                <button class="dropdown-item py-2 text-danger btn-anular-venta" data-id="${v.id}" data-correlativo="${this.escaparHtml(v.correlativo)}" data-version="${v.version_bloqueo}">
                                    <i class="fa-solid fa-xmark me-2"></i> Anular Venta
                                </button>
                            </li>
                            ` : ''}
                        </ul>
                    </div>
                </td>
            `;

            cuerpo.appendChild(tr);
        });

        // Vincular eventos de botones de la tabla
        cuerpo.querySelectorAll('.btn-ver-detalle').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                this.cargarDetalle(id);
            });
        });

        cuerpo.querySelectorAll('.btn-cancelar-venta').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const correlativo = btn.getAttribute('data-correlativo');
                const version = btn.getAttribute('data-version');
                this.abrirModalCancelar(id, correlativo, version);
            });
        });

        cuerpo.querySelectorAll('.btn-anular-venta').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const correlativo = btn.getAttribute('data-correlativo');
                const version = btn.getAttribute('data-version');
                this.abrirModalAnular(id, correlativo, version);
            });
        });

        // Inicializar DataTable
        if (typeof window.$ !== 'undefined' && window.$.fn.DataTable) {
            this.tabla = window.$('#tablaVentas').DataTable({
                pageLength: 15,
                language: {
                    search: "Filtrar tabla:",
                    lengthMenu: "Mostrar _MENU_ ventas",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ ventas",
                    infoEmpty: "Sin ventas que mostrar",
                    paginate: {
                        first: "Primero",
                        last: "Último",
                        next: "Siguiente",
                        previous: "Anterior"
                    }
                },
                order: [[1, 'desc']]
            });
        }
    }

    actualizarKPIs(ventas) {
        let total = ventas.length;
        let confirmadas = 0;
        let canceladas = 0;
        let montoTotal = 0;
        let moneda = window.CANDELARIA_MONEDA_DEFECTO || 'PEN';

        ventas.forEach((v) => {
            if (v.estado === 'CONFIRMADA') {
                confirmadas++;
                montoTotal += parseFloat(v.total || 0);
            } else if (v.estado === 'CANCELADA' || v.estado === 'ANULADA') {
                canceladas++;
            }
            if (v.moneda) moneda = v.moneda;
        });

        const elTot = document.getElementById('kpiTotalVentas');
        if (elTot) elTot.textContent = total;

        const elConf = document.getElementById('kpiConfirmadas');
        if (elConf) elConf.textContent = confirmadas;

        const elCanc = document.getElementById('kpiCanceladas');
        if (elCanc) elCanc.textContent = canceladas;

        const elMonto = document.getElementById('kpiMontoTotal');
        if (elMonto) elMonto.textContent = `${moneda} ${montoTotal.toFixed(2)}`;
    }

    // =========================================================================
    // MODAL DE CONVERSIÓN (COTIZACIÓN -> VENTA)
    // =========================================================================

    abrirModalConversion() {
        if (!this.modalConvertir) return;

        // Limpiar formulario y selector
        const $select = window.$('#selectCotizacionAceptada');
        if ($select.length) {
            $select.val(null).trigger('change');
        }
        this.ocultarPreviewCotizacion();

        this.modalConvertir.show();
    }

    async ejecutarConversion() {
        const select = document.getElementById('selectCotizacionAceptada');
        const cotizacionId = select ? select.value : '';

        if (!cotizacionId) {
            CandelariaUI.notificarError('Debe seleccionar una cotización aceptada para convertirla en venta.');
            return;
        }

        const confirmacion = await CandelariaUI.confirmarAccion(
            'Esta operación generará la venta comercial confirmada de forma definitiva y marcará la oportunidad CRM asociada como GANADA.',
            '¿Generar venta confirmada?',
            'Sí, generar venta'
        );

        if (!confirmacion.isConfirmed) return;

        const btn = document.getElementById('btnConfirmarConversion');
        CandelariaUI.procesarBoton(btn, 'Generando venta...');

        try {
            const res = await this.api.peticion('ventas/desde-cotizacion', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.CANDELARIA_CSRF || ''
                },
                body: JSON.stringify({
                    cotizacion_id: parseInt(cotizacionId, 10),
                    _csrf_token: window.CANDELARIA_CSRF || ''
                })
            });

            if (res.exito) {
                if (this.modalConvertir) this.modalConvertir.hide();
                await CandelariaUI.notificarExito(res.mensaje || 'Venta confirmada exitosamente.', '¡Venta Creada!');
                await this.cargarVentas();
            } else {
                CandelariaUI.notificarError(res.mensaje || 'Error al generar la venta.');
            }
        } catch (err) {
            CandelariaUI.notificarError('Error de comunicación con el servidor al convertir la cotización.');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    // =========================================================================
    // DETALLE 360 INTEGRAL
    // =========================================================================

    async cargarDetalle(id) {
        if (!this.modalDetalle) return;

        const skeleton = document.getElementById('skeletonDetalleVenta');
        const contenido = document.getElementById('contenidoDetalleVenta');

        if (skeleton) skeleton.classList.remove('d-none');
        if (skeleton && window.Skeleton) {
            window.Skeleton.show('#skeletonDetalleVenta', 'detail');
        }
        if (contenido) contenido.classList.add('d-none');

        this.modalDetalle.show();

        try {
            const res = await this.api.peticion(`ventas/${id}`);

            if (res.exito && res.datos?.venta) {
                const v = res.datos.venta;
                this.ventaSeleccionada = v;
                this.renderizarDetalle(v, res.datos.cotizacion_origen);
            } else {
                CandelariaUI.notificarError(res.mensaje || 'No se pudo obtener el detalle de la venta.');
                this.modalDetalle.hide();
            }
        } catch (err) {
            CandelariaUI.notificarError('Error al cargar la venta solicitada.');
            this.modalDetalle.hide();
        } finally {
            if (skeleton && window.Skeleton) {
                window.Skeleton.hide('#skeletonDetalleVenta');
            }
            if (skeleton) skeleton.classList.add('d-none');
            if (contenido) contenido.classList.remove('d-none');
        }
    }

    renderizarDetalle(v, cotOrigen) {
        document.getElementById('detCorrelativoHeader').textContent = v.correlativo || '-';

        // Badge de estado
        const elBadge = document.getElementById('detEstadoBadge');
        if (elBadge) {
            elBadge.className = 'badge b-r-6 px-3 py-2 ';
            if (v.estado === 'CONFIRMADA') elBadge.className += 'bg-success';
            else if (v.estado === 'CANCELADA') elBadge.className += 'bg-warning text-dark';
            else if (v.estado === 'ANULADA') elBadge.className += 'bg-danger';
            else elBadge.className += 'bg-secondary';
            elBadge.textContent = v.estado;
        }

        // Cliente
        document.getElementById('detClienteNombre').textContent = v.cliente_nombre_completo || v.clienteNombreCompleto || '-';
        document.getElementById('detClienteDocumento').textContent = v.cliente_numero_documento || v.clienteNumeroDocumento || '-';
        document.getElementById('detClienteTelefono').textContent = v.cliente_telefono || v.clienteTelefono || '-';

        // Procedencia
        document.getElementById('detOrigenTipo').textContent = v.origen_tipo || v.origenTipo || '-';
        document.getElementById('detFechaVenta').textContent = v.fecha_venta || v.fechaVenta || '-';
        
        const elCotOrigen = document.getElementById('detCotizacionOrigen');
        if (elCotOrigen) {
            const cotIdVal = v.cotizacion_id || v.cotizacionId;
            elCotOrigen.textContent = cotOrigen ? cotOrigen.correlativo : (cotIdVal ? `#${cotIdVal}` : 'Venta Directa');
        }

        // Snapshot Económico
        const moneda = v.moneda || 'PEN';
        document.getElementById('detSubtotal').textContent = `${moneda} ${parseFloat(v.subtotal).toFixed(2)}`;
        
        const descMonto = parseFloat(v.descuento_global_monto || v.descuentoGlobalMonto || 0);
        document.getElementById('detDescuentoGlobal').textContent = descMonto > 0 ? `-${moneda} ${descMonto.toFixed(2)}` : `${moneda} 0.00`;
        document.getElementById('detTotalVenta').textContent = `${moneda} ${parseFloat(v.total).toFixed(2)}`;

        // Alerta de estado terminal
        const alertaTerminal = document.getElementById('alertaEstadoTerminal');
        const titAlerta = document.getElementById('tituloAlertaTerminal');
        const txtAlerta = document.getElementById('textoAlertaTerminal');

        const motivoCanc = v.motivo_cancelacion || v.motivoCancelacion;
        const detalleCanc = v.motivo_cancelacion_detalle || v.motivoCancelacionDetalle;
        const motivoAnul = v.motivo_anulacion || v.motivoAnulacion;
        const detalleAnul = v.motivo_anulacion_detalle || v.motivoAnulacionDetalle;

        if (v.estado === 'CANCELADA') {
            alertaTerminal.className = 'alert alert-warning border-0 b-r-10 mb-4';
            titAlerta.textContent = `Venta Cancelada: Motivo ${motivoCanc || 'No especificado'}`;
            txtAlerta.textContent = detalleCanc ? `Detalle: ${detalleCanc}` : 'Sin detalle adicional especificado.';
            alertaTerminal.classList.remove('d-none');
        } else if (v.estado === 'ANULADA') {
            alertaTerminal.className = 'alert alert-danger border-0 b-r-10 mb-4';
            titAlerta.textContent = `Venta Anulada Administrativamente: Motivo ${motivoAnul || 'No especificado'}`;
            txtAlerta.textContent = detalleAnul ? `Detalle: ${detalleAnul}` : 'Sin detalle adicional especificado.';
            alertaTerminal.classList.remove('d-none');
        } else {
            alertaTerminal.classList.add('d-none');
        }

        // Renderizar tabla de líneas y componentes
        const cuerpoLineas = document.getElementById('cuerpoDetalleLineas');
        if (cuerpoLineas) {
            cuerpoLineas.innerHTML = '';
            const lineas = v.lineas || [];

            if (lineas.length === 0) {
                cuerpoLineas.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Sin líneas comerciales registradas.</td></tr>';
            } else {
                lineas.forEach((l) => {
                    const tr = document.createElement('tr');
                    const tipoLin = l.tipo_linea || l.tipoLinea;
                    const esPaq = tipoLin === 'PAQUETE';
                    const badgeTipo = esPaq 
                        ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">PAQUETE</span>'
                        : '<span class="badge bg-light text-secondary border">ÍTEM</span>';

                    const descLinea = parseFloat(l.descuento_monto || l.descuentoMonto || 0);
                    const descTxt = descLinea > 0 ? `<span class="text-danger">-${moneda} ${descLinea.toFixed(2)}</span>` : '<span class="text-muted">-</span>';
                    const cNombre = l.concepto_nombre || l.conceptoNombre || '-';
                    const cCodigo = l.concepto_codigo || l.conceptoCodigo || '';
                    const uMedida = l.unidad_medida || l.unidadMedida || '';
                    const pUnit = parseFloat(l.precio_unitario || l.precioUnitario || 0);

                    tr.innerHTML = `
                        <td class="ps-3">${badgeTipo}</td>
                        <td>
                            <strong class="d-block text-dark">${this.escaparHtml(cNombre)}</strong>
                            <small class="text-muted font-monospace">${this.escaparHtml(cCodigo)}</small>
                        </td>
                        <td><span class="badge bg-light text-dark border">${this.escaparHtml(uMedida)}</span></td>
                        <td class="text-center f-w-600">${parseFloat(l.cantidad).toFixed(2)}</td>
                        <td class="text-end">${moneda} ${pUnit.toFixed(2)}</td>
                        <td class="text-end">${descTxt}</td>
                        <td class="text-end pe-3 f-w-700 text-dark">${moneda} ${parseFloat(l.subtotal).toFixed(2)}</td>
                    `;
                    cuerpoLineas.appendChild(tr);

                    // Si es paquete y tiene componentes, agregar fila de componentes
                    if (esPaq && l.componentes && l.componentes.length > 0) {
                        const trComp = document.createElement('tr');
                        trComp.className = 'bg-light-subtle';
                        let componentesHtml = '<div class="p-2 ps-4 f-s-12 text-secondary"><strong class="text-muted d-block mb-1"><i class="fa-solid fa-boxes-packing me-1"></i> Componentes congelados en el paquete:</strong><ul class="mb-0 ps-3">';
                        l.componentes.forEach((c) => {
                            const iNombre = c.item_nombre || c.itemNombre || '-';
                            const iCodigo = c.item_codigo || c.itemCodigo || '';
                            const cUMedida = c.unidad_medida || c.unidadMedida || '';
                            componentesHtml += `<li>${this.escaparHtml(iNombre)} (${this.escaparHtml(iCodigo)}) — Cantidad: <strong>${parseFloat(c.cantidad).toFixed(2)}</strong> ${this.escaparHtml(cUMedida)}</li>`;
                        });
                        componentesHtml += '</ul></div>';

                        trComp.innerHTML = `<td colspan="7" class="p-0">${componentesHtml}</td>`;
                        cuerpoLineas.appendChild(trComp);
                    }
                });
            }
        }

        // Términos y Notas
        document.getElementById('detTerminosCondiciones').textContent = v.terminos_condiciones || v.terminosCondiciones || 'Sin términos específicos.';
        document.getElementById('detNotasComerciales').textContent = v.notas_comerciales || v.notasComerciales || 'Sin notas comerciales.';
        document.getElementById('detVersionBloqueo').textContent = v.version_bloqueo || v.versionBloqueo || 1;

        // Botones de acción dentro del modal
        const btnCancel = document.getElementById('btnAccionCancelarVenta');
        const btnAnul = document.getElementById('btnAccionAnularVenta');

        if (btnCancel) {
            const puedeCancel = window.CANDELARIA_PERMISOS?.cancelar && v.estado === 'CONFIRMADA';
            btnCancel.classList.toggle('d-none', !puedeCancel);
        }

        if (btnAnul) {
            const puedeAnul = window.CANDELARIA_PERMISOS?.anular && v.estado === 'CONFIRMADA';
            btnAnul.classList.toggle('d-none', !puedeAnul);
        }
    }

    // =========================================================================
    // CANCELACIÓN COMERCIAL
    // =========================================================================

    abrirModalCancelar(id, correlativo, versionBloqueo) {
        if (!this.modalCancelar) return;

        document.getElementById('cancelarVentaId').value = id;
        document.getElementById('cancelarVersionBloqueo').value = versionBloqueo;
        document.getElementById('cancelarCorrelativoTexto').textContent = correlativo;

        const selectMotivo = document.getElementById('selectMotivoCancelacion');
        if (selectMotivo) selectMotivo.value = '';

        const txtDetalle = document.getElementById('cancelarMotivoDetalle');
        if (txtDetalle) {
            txtDetalle.value = '';
            txtDetalle.required = false;
        }

        const req = document.getElementById('reqDetalleCancelacion');
        if (req) req.classList.add('d-none');

        this.modalCancelar.show();
    }

    async ejecutarCancelacion() {
        const id = document.getElementById('cancelarVentaId').value;
        const version = document.getElementById('cancelarVersionBloqueo').value;
        const selectMotivo = document.getElementById('selectMotivoCancelacion');
        const txtDetalle = document.getElementById('cancelarMotivoDetalle');

        const motivo = selectMotivo?.value;
        const detalle = txtDetalle?.value.trim();

        if (!motivo) {
            CandelariaUI.notificarError('Debe seleccionar un motivo obligatorio para cancelar la venta.');
            return;
        }

        if (motivo === 'OTRO' && !detalle) {
            txtDetalle?.classList.add('is-invalid');
            CandelariaUI.notificarError('El motivo OTRO exige obligatoriamente un detalle explicativo.');
            return;
        }

        const btn = document.getElementById('btnConfirmarCancelacion');
        CandelariaUI.procesarBoton(btn, 'Cancelando...');

        try {
            const res = await this.api.peticion(`ventas/${id}/cancelar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.CANDELARIA_CSRF || ''
                },
                body: JSON.stringify({
                    motivo: motivo,
                    motivo_detalle: detalle || null,
                    version_bloqueo: parseInt(version, 10),
                    _csrf_token: window.CANDELARIA_CSRF || ''
                })
            });

            if (res.exito) {
                if (this.modalCancelar) this.modalCancelar.hide();
                await CandelariaUI.notificarExito(res.mensaje || 'Venta cancelada exitosamente.');
                await this.cargarVentas();
            } else if (res.errores?.codigo === 'CONFLICTO_CONCURRENCIA') {
                if (this.modalCancelar) this.modalCancelar.hide();
                await CandelariaUI.notificarError(
                    'La venta fue modificada por otro usuario concurrentemente. Se actualizarán los datos.',
                    'Conflicto de Concurrencia (409)'
                );
                await this.cargarVentas();
            } else {
                CandelariaUI.notificarError(res.mensaje || 'Error al cancelar la venta.');
            }
        } catch (err) {
            CandelariaUI.notificarError('Error de comunicación con el servidor al cancelar la venta.');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    // =========================================================================
    // ANULACIÓN ADMINISTRATIVA
    // =========================================================================

    abrirModalAnular(id, correlativo, versionBloqueo) {
        if (!this.modalAnular) return;

        document.getElementById('anularVentaId').value = id;
        document.getElementById('anularVersionBloqueo').value = versionBloqueo;
        document.getElementById('anularCorrelativoTexto').textContent = correlativo;

        const selectMotivo = document.getElementById('selectMotivoAnulacion');
        if (selectMotivo) selectMotivo.value = '';

        const txtDetalle = document.getElementById('anularMotivoDetalle');
        if (txtDetalle) {
            txtDetalle.value = '';
            txtDetalle.required = false;
        }

        const req = document.getElementById('reqDetalleAnulacion');
        if (req) req.classList.add('d-none');

        this.modalAnular.show();
    }

    async ejecutarAnulacion() {
        const id = document.getElementById('anularVentaId').value;
        const version = document.getElementById('anularVersionBloqueo').value;
        const selectMotivo = document.getElementById('selectMotivoAnulacion');
        const txtDetalle = document.getElementById('anularMotivoDetalle');

        const motivo = selectMotivo?.value;
        const detalle = txtDetalle?.value.trim();

        if (!motivo) {
            CandelariaUI.notificarError('Debe seleccionar un motivo obligatorio para anular la venta.');
            return;
        }

        if (motivo === 'OTRO' && !detalle) {
            txtDetalle?.classList.add('is-invalid');
            CandelariaUI.notificarError('El motivo OTRO exige obligatoriamente una justificación explicativa.');
            return;
        }

        const btn = document.getElementById('btnConfirmarAnulacion');
        CandelariaUI.procesarBoton(btn, 'Anulando...');

        try {
            const res = await this.api.peticion(`ventas/${id}/anular`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.CANDELARIA_CSRF || ''
                },
                body: JSON.stringify({
                    motivo: motivo,
                    motivo_detalle: detalle || null,
                    version_bloqueo: parseInt(version, 10),
                    _csrf_token: window.CANDELARIA_CSRF || ''
                })
            });

            if (res.exito) {
                if (this.modalAnular) this.modalAnular.hide();
                await CandelariaUI.notificarExito(res.mensaje || 'Venta anulada formalmente.');
                await this.cargarVentas();
            } else if (res.errores?.codigo === 'CONFLICTO_CONCURRENCIA') {
                if (this.modalAnular) this.modalAnular.hide();
                await CandelariaUI.notificarError(
                    'La venta fue modificada por otro usuario concurrentemente. Se actualizarán los datos.',
                    'Conflicto de Concurrencia (409)'
                );
                await this.cargarVentas();
            } else {
                CandelariaUI.notificarError(res.mensaje || 'Error al anular la venta.');
            }
        } catch (err) {
            CandelariaUI.notificarError('Error de comunicación con el servidor al anular la venta.');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    // =========================================================================
    // UTILIDADES
    // =========================================================================

    escaparHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

// Inicialización automática al cargar el DOM
document.addEventListener('DOMContentLoaded', () => {
    window.moduloVentas = new ModuloVentas();
});
