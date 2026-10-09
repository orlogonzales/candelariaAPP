/**
 * ==============================================================================
 * CANDELARIAAPP - JAVASCRIPT OFICIAL: FINANZAS, PAGOS Y PASARELAS (pagos.js)
 * FASE 2.7E — Integración Alina UI, DataTables Asíncrono, SweetAlert2 y Manejo 409
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // 1. Constantes y URLs de API
    const BASE_URL = window.CANDELARIA_BASE_URL || '/';
    const URL_API_PAGOS = BASE_URL + 'api/v1/pagos';
    const URL_API_VENTAS = BASE_URL + 'api/v1/ventas';
    const URL_API_CUENTAS = BASE_URL + 'api/v1/cuentas-bancarias';
    const URL_API_PASARELAS = BASE_URL + 'api/v1/pasarelas';
    const CSRF_TOKEN = window.CANDELARIA_CSRF_TOKEN || '';

    // Headers predeterminados
    const jsonHeaders = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF_TOKEN,
    };

    // 2. Elementos DOM Principales
    const skeletonPagos = document.getElementById('skeletonPagos');
    const contenedorTablaPagos = document.getElementById('contenedorTablaPagos');
    const tablaPagosEl = document.getElementById('tablaPagos');
    const cuerpoTablaPagos = document.getElementById('cuerpoTablaPagos');

    // Filtros
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroMetodo = document.getElementById('filtroMetodo');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const btnAplicarFiltros = document.getElementById('btnAplicarFiltros');
    const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltros');
    const btnRecargarTodo = document.getElementById('btnRecargarTodo');

    // KPIs
    const kpiTotalRecaudado = document.getElementById('kpiTotalRecaudado');
    const kpiPagosAprobados = document.getElementById('kpiPagosAprobados');
    const kpiPagosPendientes = document.getElementById('kpiPagosPendientes');
    const kpiReembolsosTotal = document.getElementById('kpiReembolsosTotal');

    // Modales Bootstrap
    const modalRegistrarPagoEl = document.getElementById('modalRegistrarPago');
    const modalRegistrarPago = modalRegistrarPagoEl ? new bootstrap.Modal(modalRegistrarPagoEl) : null;
    const modalVerificarVoucherEl = document.getElementById('modalVerificarVoucher');
    const modalVerificarVoucher = modalVerificarVoucherEl ? new bootstrap.Modal(modalVerificarVoucherEl) : null;
    const modalEstadoCuentaVentaEl = document.getElementById('modalEstadoCuentaVenta');
    const modalEstadoCuentaVenta = modalEstadoCuentaVentaEl ? new bootstrap.Modal(modalEstadoCuentaVentaEl) : null;
    const modalEmitirReembolsoEl = document.getElementById('modalEmitirReembolso');
    const modalEmitirReembolso = modalEmitirReembolsoEl ? new bootstrap.Modal(modalEmitirReembolsoEl) : null;
    const modalCuentaBancariaEl = document.getElementById('modalCuentaBancaria');
    const modalCuentaBancaria = modalCuentaBancariaEl ? new bootstrap.Modal(modalCuentaBancariaEl) : null;
    const modalConfigurarPasarelaEl = document.getElementById('modalConfigurarPasarela');
    const modalConfigurarPasarela = modalConfigurarPasarelaEl ? new bootstrap.Modal(modalConfigurarPasarelaEl) : null;

    // Formularios
    const formRegistrarPago = document.getElementById('formRegistrarPago');
    const formEmitirReembolso = document.getElementById('formEmitirReembolso');
    const formCuentaBancaria = document.getElementById('formCuentaBancaria');
    const formConfigurarPasarela = document.getElementById('formConfigurarPasarela');
    const formCalculadora = document.getElementById('formCalculadora');

    // Variables de Estado
    let dataTablePagos = null;
    let cachePagos = [];
    let cacheCuentas = [];
    let cachePasarelasCatalogo = [];
    let cachePasarelasConfigs = [];
    let cacheVentasPendientes = [];

    // =========================================================================
    // INICIALIZACIÓN
    // =========================================================================
    inicializar();

    function inicializar() {
        vincularEventos();
        cargarPagos();
        cargarCuentasBancarias();
        cargarPasarelas();
        cargarVentasAuxiliares();
        calcularSimulacion();
    }

    // =========================================================================
    // VINCULACIÓN DE EVENTOS
    // =========================================================================
    function vincularEventos() {
        if (btnAplicarFiltros) btnAplicarFiltros.addEventListener('click', () => cargarPagos());
        if (btnLimpiarFiltros) btnLimpiarFiltros.addEventListener('click', limpiarFiltros);
        if (btnRecargarTodo) btnRecargarTodo.addEventListener('click', () => {
            cargarPagos();
            cargarCuentasBancarias();
            cargarPasarelas();
            cargarVentasAuxiliares();
        });

        // Modal Registrar Pago
        const btnAbrirModalPago = document.getElementById('btnAbrirModalPago');
        if (btnAbrirModalPago) {
            btnAbrirModalPago.addEventListener('click', () => {
                if (formRegistrarPago) formRegistrarPago.reset();
                actualizarCamposBancarios();
                if (modalRegistrarPago) modalRegistrarPago.show();
            });
        }

        const pagoMetodoSelect = document.getElementById('pagoMetodo');
        if (pagoMetodoSelect) {
            pagoMetodoSelect.addEventListener('change', actualizarCamposBancarios);
        }

        const pagoVentaSelect = document.getElementById('pagoVentaId');
        if (pagoVentaSelect) {
            pagoVentaSelect.addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                const saldo = opt ? opt.getAttribute('data-saldo') : '0.00';
                const inputSaldo = document.getElementById('pagoSaldoInfo');
                if (inputSaldo) inputSaldo.value = 'S/ ' + parseFloat(saldo || 0).toFixed(2);
                const inputMonto = document.getElementById('pagoMonto');
                if (inputMonto && (!inputMonto.value || parseFloat(inputMonto.value) <= 0)) {
                    inputMonto.value = parseFloat(saldo || 0).toFixed(2);
                }
            });
        }

        if (formRegistrarPago) {
            formRegistrarPago.addEventListener('submit', guardarPagoManual);
        }

        // Verificación de Vouchers
        const btnAprobarVoucher = document.getElementById('btnAprobarVoucher');
        if (btnAprobarVoucher) btnAprobarVoucher.addEventListener('click', () => ejecutarVerificacion(true));

        const btnRechazarVoucher = document.getElementById('btnRechazarVoucher');
        if (btnRechazarVoucher) btnRechazarVoucher.addEventListener('click', () => ejecutarVerificacion(false));

        // Reembolsos
        if (formEmitirReembolso) {
            formEmitirReembolso.addEventListener('submit', guardarReembolso);
        }

        // Cuentas Bancarias
        const btnNuevaCuenta = document.getElementById('btnNuevaCuentaBancaria');
        if (btnNuevaCuenta) {
            btnNuevaCuenta.addEventListener('click', () => abrirModalCuenta());
        }
        if (formCuentaBancaria) {
            formCuentaBancaria.addEventListener('submit', guardarCuentaBancaria);
        }

        // Pasarelas
        if (formConfigurarPasarela) {
            formConfigurarPasarela.addEventListener('submit', guardarConfiguracionPasarela);
        }

        // Calculadora de comisiones
        if (formCalculadora) {
            ['calcMontoBase', 'calcPasarela'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('input', calcularSimulacion);
            });
            document.querySelectorAll('input[name="calcAsuncion"]').forEach(r => {
                r.addEventListener('change', calcularSimulacion);
            });
        }
    }

    function limpiarFiltros() {
        if (filtroBusqueda) filtroBusqueda.value = '';
        if (filtroEstado) filtroEstado.value = '';
        if (filtroMetodo) filtroMetodo.value = '';
        if (filtroFechaDesde) filtroFechaDesde.value = '';
        cargarPagos();
    }

    function actualizarCamposBancarios() {
        const metodo = document.getElementById('pagoMetodo')?.value;
        const camposBancarios = document.querySelectorAll('.seccion-bancaria');
        const esBancario = (metodo === 'TRANSFERENCIA_BANCARIA' || metodo === 'BILLETERA_DIGITAL' || metodo === 'POS_FISICO');

        camposBancarios.forEach(el => {
            if (esBancario) {
                el.classList.remove('d-none');
            } else {
                el.classList.add('d-none');
            }
        });
    }

    // =========================================================================
    // 1. CARGA DE PAGOS Y LIBRO MAYOR
    // =========================================================================
    function cargarPagos() {
        if (skeletonPagos) skeletonPagos.classList.remove('d-none');
        if (contenedorTablaPagos) contenedorTablaPagos.classList.add('d-none');

        const params = new URLSearchParams();
        if (filtroBusqueda && filtroBusqueda.value.trim() !== '') params.append('busqueda', filtroBusqueda.value.trim());
        if (filtroEstado && filtroEstado.value !== '') params.append('estado', filtroEstado.value);
        if (filtroMetodo && filtroMetodo.value !== '') params.append('metodo_pago', filtroMetodo.value);
        if (filtroFechaDesde && filtroFechaDesde.value !== '') params.append('fecha_desde', filtroFechaDesde.value);
        params.append('por_pagina', '100');

        fetch(URL_API_PAGOS + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(json => {
            if (!json.exito) throw new Error(json.mensaje || 'Error al listar pagos');
            cachePagos = json.datos.pagos || [];
            renderizarTablaPagos(cachePagos);
            actualizarKpis(cachePagos);
        })
        .catch(err => {
            console.error(err);
            mostrarError('Error al recuperar pagos: ' + err.message);
        })
        .finally(() => {
            if (skeletonPagos) skeletonPagos.classList.add('d-none');
            if (contenedorTablaPagos) contenedorTablaPagos.classList.remove('d-none');
        });
    }

    function renderizarTablaPagos(pagos) {
        if (dataTablePagos) {
            dataTablePagos.destroy();
            dataTablePagos = null;
        }

        if (!cuerpoTablaPagos) return;
        cuerpoTablaPagos.innerHTML = '';

        if (pagos.length === 0) {
            cuerpoTablaPagos.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-receipt f-s-30 mb-2 d-block text-secondary opacity-50"></i>
                        No se registraron transacciones financieras con los filtros seleccionados.
                    </td>
                </tr>
            `;
            return;
        }

        pagos.forEach(p => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-id', p.id);

            // Badges de estado Alina
            let badgeEstado = '';
            switch (p.estado) {
                case 'APROBADO':
                    badgeEstado = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i> Aprobado</span>';
                    break;
                case 'PENDIENTE_VERIFICACION':
                    badgeEstado = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="fa-solid fa-clock me-1"></i> Por Verificar</span>';
                    break;
                case 'RECHAZADO':
                    badgeEstado = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="fa-solid fa-circle-xmark me-1"></i> Rechazado</span>';
                    break;
                case 'ANULADO':
                    badgeEstado = '<span class="badge bg-secondary-subtle text-secondary px-2 py-1">Anulado</span>';
                    break;
                default:
                    badgeEstado = `<span class="badge bg-light text-dark">${p.estado}</span>`;
            }

            // Icono de método
            let metodoIcono = '<i class="fa-solid fa-money-bill text-secondary me-1"></i>';
            if (p.metodo_pago === 'TRANSFERENCIA_BANCARIA') metodoIcono = '<i class="fa-solid fa-building-columns text-primary me-1"></i>';
            if (p.metodo_pago === 'BILLETERA_DIGITAL') metodoIcono = '<i class="fa-solid fa-qrcode text-info me-1"></i>';
            if (p.metodo_pago === 'TARJETA_PASARELA') metodoIcono = '<i class="fa-solid fa-credit-card text-success me-1"></i>';
            if (p.metodo_pago === 'POS_FISICO') metodoIcono = '<i class="fa-solid fa-calculator text-dark me-1"></i>';

            // Desglose
            const desgloseHtml = `
                <div class="f-s-11">
                    <span class="text-success f-w-600">Neto: S/ ${formatearMonto(p.monto_neto_recibido)}</span><br>
                    <span class="text-danger">Com: S/ ${formatearMonto(p.comision_pasarela)}</span>
                    ${parseFloat(p.monto_excedente || 0) > 0 ? `<br><span class="text-info">Excedente: S/ ${formatearMonto(p.monto_excedente)}</span>` : ''}
                    ${parseFloat(p.monto_reembolsado_acumulado || 0) > 0 ? `<br><span class="text-danger f-w-700">Reemb: S/ ${formatearMonto(p.monto_reembolsado_acumulado)}</span>` : ''}
                </div>
            `;

            // Voucher preview
            let voucherHtml = '<span class="text-muted f-s-12">-</span>';
            if (p.boucher_comprobante_url) {
                voucherHtml = `
                    <button type="button" class="btn btn-sm btn-outline-info btn-ver-voucher py-0 px-2" data-url="${escaparHtml(p.boucher_comprobante_url)}" data-pago='${JSON.stringify(p)}'>
                        <i class="fa-solid fa-image me-1"></i> Voucher
                    </button>
                `;
            }

            // Acciones disponibles
            let accionesHtml = `
                <button type="button" class="btn btn-sm btn-outline-primary btn-ver-estado-cuenta" data-venta-id="${p.venta_id}" title="Estado de Cuenta 360°">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </button>
            `;

            if (p.estado === 'PENDIENTE_VERIFICACION') {
                accionesHtml += `
                    <button type="button" class="btn btn-sm btn-warning text-dark ms-1 btn-abrir-verificar" data-id="${p.id}" title="Verificar Depósito">
                        <i class="fa-solid fa-check"></i> Verificar
                    </button>
                `;
            }

            if (p.estado === 'APROBADO') {
                const disponibleReembolso = parseFloat(p.monto_cobrado_cliente || 0) - parseFloat(p.monto_reembolsado_acumulado || 0);
                if (disponibleReembolso > 0) {
                    accionesHtml += `
                        <button type="button" class="btn btn-sm btn-outline-danger ms-1 btn-abrir-reembolso" data-id="${p.id}" data-disponible="${disponibleReembolso.toFixed(2)}" title="Emitir Reembolso">
                            <i class="fa-solid fa-arrow-rotate-left"></i>
                        </button>
                    `;
                }
            }

            tr.innerHTML = `
                <td class="ps-3"><strong class="f-w-700 text-dark">${p.correlativo}</strong></td>
                <td><span class="f-s-12 text-secondary">${p.fecha_pago ? p.fecha_pago.substring(0, 16) : '-'}</span></td>
                <td>
                    <span class="d-block f-w-600 text-dark">${p.venta_correlativo || 'Venta #' + p.venta_id}</span>
                    <span class="f-s-11 text-muted">${p.banco_nombre || p.pasarela_nombre || ''}</span>
                </td>
                <td><span class="f-s-12">${metodoIcono} ${p.metodo_pago.replace('_', ' ')}</span></td>
                <td><strong class="f-s-14 text-dark">${p.moneda} ${formatearMonto(p.monto_cobrado_cliente)}</strong></td>
                <td>${desgloseHtml}</td>
                <td>${voucherHtml}</td>
                <td>${badgeEstado}</td>
                <td class="text-end pe-3">${accionesHtml}</td>
            `;

            cuerpoTablaPagos.appendChild(tr);
        });

        // Asignar eventos de acciones
        cuerpoTablaPagos.querySelectorAll('.btn-ver-estado-cuenta').forEach(b => {
            b.addEventListener('click', () => abrirEstadoCuentaVenta(parseInt(b.getAttribute('data-venta-id'))));
        });
        cuerpoTablaPagos.querySelectorAll('.btn-abrir-verificar').forEach(b => {
            b.addEventListener('click', () => abrirVerificacionVoucher(parseInt(b.getAttribute('data-id'))));
        });
        cuerpoTablaPagos.querySelectorAll('.btn-abrir-reembolso').forEach(b => {
            b.addEventListener('click', () => abrirModalReembolso(parseInt(b.getAttribute('data-id')), parseFloat(b.getAttribute('data-disponible'))));
        });
        cuerpoTablaPagos.querySelectorAll('.btn-ver-voucher').forEach(b => {
            b.addEventListener('click', () => {
                const pago = JSON.parse(b.getAttribute('data-pago'));
                abrirVerificacionVoucher(pago.id);
            });
        });

        // Inicializar DataTables si está disponible
        if (typeof $.fn.DataTable !== 'undefined') {
            dataTablePagos = $(tablaPagosEl).DataTable({
                pageLength: 20,
                language: {
                    search: "Buscar:",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ pagos",
                    paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" }
                }
            });
        }
    }

    function actualizarKpis(pagos) {
        let totalNeto = 0;
        let aprobados = 0;
        let pendientes = 0;
        let totalReembolsado = 0;

        pagos.forEach(p => {
            if (p.estado === 'APROBADO') {
                aprobados++;
                totalNeto += parseFloat(p.monto_neto_recibido || 0);
                totalReembolsado += parseFloat(p.monto_reembolsado_acumulado || 0);
            } else if (p.estado === 'PENDIENTE_VERIFICACION') {
                pendientes++;
            }
        });

        if (kpiTotalRecaudado) kpiTotalRecaudado.textContent = 'S/ ' + formatearMonto(totalNeto);
        if (kpiPagosAprobados) kpiPagosAprobados.textContent = aprobados;
        if (kpiPagosPendientes) kpiPagosPendientes.textContent = pendientes;
        if (kpiReembolsosTotal) kpiReembolsosTotal.textContent = 'S/ ' + formatearMonto(totalReembolsado);
    }

    // =========================================================================
    // 2. REGISTRO DE COBRO MANUAL
    // =========================================================================
    function guardarPagoManual(e) {
        e.preventDefault();

        const ventaId = document.getElementById('pagoVentaId')?.value;
        const metodo = document.getElementById('pagoMetodo')?.value;
        const moneda = document.getElementById('pagoMoneda')?.value || 'PEN';
        const monto = parseFloat(document.getElementById('pagoMonto')?.value || 0);
        const cuentaBancariaId = document.getElementById('pagoCuentaBancariaId')?.value;
        const numeroOperacion = document.getElementById('pagoNumeroOperacion')?.value;
        const boucherUrl = document.getElementById('pagoBoucherUrl')?.value;
        const notas = document.getElementById('pagoNotas')?.value;

        if (!ventaId) {
            mostrarAlerta('Debe seleccionar una venta válida.', 'warning');
            return;
        }
        if (isNaN(monto) || monto <= 0) {
            mostrarAlerta('El monto debe ser estrictamente mayor a 0.00.', 'warning');
            return;
        }

        const payload = {
            venta_id: parseInt(ventaId),
            metodo_pago: metodo,
            moneda: moneda,
            monto: monto,
            cuenta_bancaria_id: cuentaBancariaId ? parseInt(cuentaBancariaId) : null,
            numero_operacion_bancaria: numeroOperacion ? numeroOperacion.trim() : null,
            boucher_comprobante_url: boucherUrl ? boucherUrl.trim() : null,
            notas_operativas: notas ? notas.trim() : null,
            csrf_token: CSRF_TOKEN
        };

        const btn = document.getElementById('btnGuardarPago');
        if (btn) btn.disabled = true;

        fetch(URL_API_PAGOS + '/manual', {
            method: 'POST',
            headers: jsonHeaders,
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(json => {
            if (!json.exito) throw new Error(json.mensaje || 'Error al registrar cobro');
            if (modalRegistrarPago) modalRegistrarPago.hide();
            mostrarExito('Cobro registrado correctamente (' + json.datos.correlativo + ').');
            cargarPagos();
            cargarVentasAuxiliares();
        })
        .catch(err => {
            console.error(err);
            mostrarError(err.message || 'No se pudo registrar el pago.');
        })
        .finally(() => {
            if (btn) btn.disabled = false;
        });
    }

    // =========================================================================
    // 3. VERIFICACIÓN DE VOUCHERS (OPTIMISTIC LOCKING 409)
    // =========================================================================
    function abrirVerificacionVoucher(pagoId) {
        const pago = cachePagos.find(p => p.id === pagoId);
        if (!pago) return;

        document.getElementById('verifPagoId').value = pago.id;
        document.getElementById('verifVersionBloqueo').value = pago.version_bloqueo || 1;
        document.getElementById('verifCorrelativo').textContent = pago.correlativo;
        document.getElementById('verifVentaCorrelativo').textContent = pago.venta_correlativo || 'Venta #' + pago.venta_id;
        document.getElementById('verifMonto').textContent = `${pago.moneda} ${formatearMonto(pago.monto_cobrado_cliente)}`;
        document.getElementById('verifMetodo').textContent = pago.metodo_pago;
        document.getElementById('verifBanco').textContent = pago.banco_nombre || 'N/A';
        document.getElementById('verifNumOperacion').textContent = pago.numero_operacion_bancaria || 'Sin número';
        document.getElementById('verifFecha').textContent = pago.fecha_pago;
        document.getElementById('verifNotas').value = pago.notas_operativas || '';

        const contVoucher = document.getElementById('verifContenedorVoucher');
        if (contVoucher) {
            if (pago.boucher_comprobante_url) {
                contVoucher.innerHTML = `
                    <div class="position-relative">
                        <img src="${escaparHtml(pago.boucher_comprobante_url)}" alt="Voucher" class="img-fluid b-r-8 shadow-sm border" style="max-height: 260px; object-fit: contain;">
                        <a href="${escaparHtml(pago.boucher_comprobante_url)}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-dark position-absolute bottom-0 end-0 m-2 opacity-75">
                            <i class="fa-solid fa-up-right-from-square"></i> Abrir Original
                        </a>
                    </div>
                `;
            } else {
                contVoucher.innerHTML = `<span class="text-muted f-s-13"><i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i> Sin URL de voucher registrada</span>`;
            }
        }

        if (modalVerificarVoucher) modalVerificarVoucher.show();
    }

    function ejecutarVerificacion(aprobar) {
        const pagoId = parseInt(document.getElementById('verifPagoId')?.value);
        const versionBloqueo = parseInt(document.getElementById('verifVersionBloqueo')?.value || 1);
        const notas = document.getElementById('verifNotas')?.value;

        if (!pagoId) return;

        const payload = {
            aprobar: aprobar,
            version_bloqueo: versionBloqueo,
            notas: notas ? notas.trim() : null,
            csrf_token: CSRF_TOKEN
        };

        const accionTexto = aprobar ? 'aprobar y acreditar' : 'rechazar';

        Swal.fire({
            title: `¿Confirmar verificación?`,
            text: `¿Está seguro de ${accionTexto} este depósito bancario?`,
            icon: aprobar ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: aprobar ? 'Sí, Aprobar' : 'Sí, Rechazar',
            confirmButtonColor: aprobar ? '#198754' : '#dc3545',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;

            fetch(URL_API_PAGOS + `/${pagoId}/verificar`, {
                method: 'POST',
                headers: jsonHeaders,
                body: JSON.stringify(payload)
            })
            .then(res => {
                if (res.status === 409) {
                    throw new Error('CONFLICTO_409: El comprobante fue verificado concurrentemente por otro operador.');
                }
                return res.json();
            })
            .then(json => {
                if (!json.exito) throw new Error(json.mensaje || 'Error al verificar');
                if (modalVerificarVoucher) modalVerificarVoucher.hide();
                mostrarExito(aprobar ? 'Depósito verificado y venta amortizada.' : 'Depósito bancario rechazado.');
                cargarPagos();
                cargarVentasAuxiliares();
            })
            .catch(err => {
                console.error(err);
                if (err.message.includes('CONFLICTO_409')) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Conflicto de Concurrencia (409)',
                        text: 'El estado del pago cambió mientras lo revisaba. Los datos han sido recargados automáticamente.',
                    }).then(() => {
                        if (modalVerificarVoucher) modalVerificarVoucher.hide();
                        cargarPagos();
                    });
                } else {
                    mostrarError(err.message || 'Error en la verificación.');
                }
            });
        });
    }

    // =========================================================================
    // 4. DETALLE 360° Y ESTADO DE CUENTA DE VENTA
    // =========================================================================
    function abrirEstadoCuentaVenta(ventaId) {
        if (!ventaId) return;

        fetch(URL_API_VENTAS + `/${ventaId}/estado-cuenta`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(json => {
            if (!json.exito) throw new Error(json.mensaje || 'Error al recuperar estado de cuenta');
            renderizarEstadoCuentaVenta(json.datos);
            if (modalEstadoCuentaVenta) modalEstadoCuentaVenta.show();
        })
        .catch(err => {
            console.error(err);
            mostrarError(err.message || 'No se pudo cargar el estado de cuenta.');
        });
    }

    function renderizarEstadoCuentaVenta(data) {
        document.getElementById('ctaCorrelativoVenta').textContent = `Venta: ${data.correlativo} | Estado Comercial: ${data.estado_comercial} | Estado Financiero: ${data.estado_financiero}`;
        document.getElementById('ctaTotalContractual').textContent = 'S/ ' + formatearMonto(data.total_contractual);
        document.getElementById('ctaMontoPagadoNeto').textContent = 'S/ ' + formatearMonto(data.monto_pagado_neto);
        document.getElementById('ctaSaldoPendiente').textContent = 'S/ ' + formatearMonto(data.saldo_pendiente);
        document.getElementById('ctaSaldoAFavor').textContent = 'S/ ' + formatearMonto(data.saldo_a_favor_cliente);

        const textoPolitica = document.getElementById('ctaPoliticaReservaTexto');
        if (textoPolitica && data.politica_anticipo) {
            textoPolitica.textContent = `Anticipo Mínimo (${data.politica_anticipo.porcentaje_minimo}%): S/ ${formatearMonto(data.politica_anticipo.monto_minimo_reserva)} — ${data.politica_anticipo.anticipo_cubierto ? '✓ Cubierto' : '✗ Pendiente'}`;
        }

        // Botón Liquidar si corresponde
        const contLiquidar = document.getElementById('ctaContenedorLiquidar');
        if (contLiquidar) {
            if (data.permite_liquidacion) {
                contLiquidar.innerHTML = `
                    <button type="button" class="btn btn-success btn-sm" id="btnLiquidarVenta" data-id="${data.venta_id}">
                        <i class="fa-solid fa-flag-checkered me-1"></i> Liquidar Venta
                    </button>
                `;
                document.getElementById('btnLiquidarVenta')?.addEventListener('click', () => ejecutarLiquidacionVenta(data.venta_id));
            } else {
                contLiquidar.innerHTML = `<span class="badge bg-secondary-subtle text-secondary f-s-12">Liquidación no disponible (saldo pendiente o venta no confirmada)</span>`;
            }
        }

        // Historial de pagos
        const cuerpoPagos = document.getElementById('ctaCuerpoPagos');
        if (cuerpoPagos) {
            cuerpoPagos.innerHTML = '';
            if (!data.pagos || data.pagos.length === 0) {
                cuerpoPagos.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-muted">Sin cobros imputados a esta venta.</td></tr>`;
            } else {
                data.pagos.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${p.correlativo}</strong></td>
                        <td>${p.fecha_pago ? p.fecha_pago.substring(0, 10) : '-'}</td>
                        <td>${p.metodo_pago}</td>
                        <td>S/ ${formatearMonto(p.monto_cobrado_cliente)}</td>
                        <td class="text-success f-w-600">S/ ${formatearMonto(p.monto_aplicado_venta)}</td>
                        <td class="text-danger">S/ ${formatearMonto(p.monto_reembolsado_acumulado)}</td>
                        <td><span class="badge ${p.estado === 'APROBADO' ? 'bg-success' : 'bg-warning text-dark'}">${p.estado}</span></td>
                    `;
                    cuerpoPagos.appendChild(tr);
                });
            }
        }
    }

    function ejecutarLiquidacionVenta(ventaId) {
        Swal.fire({
            title: '¿Liquidar Venta Comercialmente?',
            text: 'Esta acción cambiará el estado comercial de la venta a LIQUIDADA de forma inmutable.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, Liquidar',
            confirmButtonColor: '#198754',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;

            fetch(URL_API_VENTAS + `/${ventaId}/liquidar`, {
                method: 'POST',
                headers: jsonHeaders,
                body: JSON.stringify({ csrf_token: CSRF_TOKEN })
            })
            .then(res => res.json())
            .then(json => {
                if (!json.exito) throw new Error(json.mensaje || 'Error al liquidar');
                mostrarExito('Venta liquidada con éxito.');
                if (modalEstadoCuentaVenta) modalEstadoCuentaVenta.hide();
                cargarPagos();
            })
            .catch(err => mostrarError(err.message || 'Error al liquidar la venta.'));
        });
    }

    // =========================================================================
    // 5. EMISIÓN DE REEMBOLSOS COMPENSATORIOS
    // =========================================================================
    function abrirModalReembolso(pagoId, disponible) {
        document.getElementById('reemPagoId').value = pagoId;
        document.getElementById('reemSaldoDisponibleTexto').textContent = 'S/ ' + formatearMonto(disponible);
        const montoInput = document.getElementById('reemMonto');
        if (montoInput) {
            montoInput.max = disponible;
            montoInput.value = disponible;
        }
        if (formEmitirReembolso) formEmitirReembolso.reset();
        document.getElementById('reemPagoId').value = pagoId;
        document.getElementById('reemSaldoDisponibleTexto').textContent = 'S/ ' + formatearMonto(disponible);
        if (montoInput) montoInput.value = disponible;

        if (modalEmitirReembolso) modalEmitirReembolso.show();
    }

    function guardarReembolso(e) {
        e.preventDefault();

        const pagoId = parseInt(document.getElementById('reemPagoId')?.value);
        const monto = parseFloat(document.getElementById('reemMonto')?.value || 0);
        const motivo = document.getElementById('reemMotivo')?.value;
        const detalle = document.getElementById('reemDetalle')?.value;
        const transExt = document.getElementById('reemTransExterna')?.value;

        if (!pagoId || isNaN(monto) || monto <= 0) {
            mostrarAlerta('Ingrese un monto válido a reembolsar.', 'warning');
            return;
        }
        if (!motivo) {
            mostrarAlerta('Debe seleccionar el motivo auditado del reembolso.', 'warning');
            return;
        }
        if (!detalle || detalle.trim() === '') {
            mostrarAlerta('Debe ingresar el detalle explicativo obligatorio.', 'warning');
            return;
        }

        const payload = {
            monto: monto,
            motivo: motivo,
            motivo_detalle: detalle.trim(),
            transaccion_externa_id: transExt ? transExt.trim() : null,
            csrf_token: CSRF_TOKEN
        };

        Swal.fire({
            title: '¿Confirmar Reembolso?',
            text: `Se emitirá un asiento compensatorio por S/ ${monto.toFixed(2)}.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Confirmar Reembolso',
            confirmButtonColor: '#dc3545',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;

            const btn = document.getElementById('btnConfirmarReembolso');
            if (btn) btn.disabled = true;

            fetch(URL_API_PAGOS + `/${pagoId}/reembolsar`, {
                method: 'POST',
                headers: jsonHeaders,
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(json => {
                if (!json.exito) throw new Error(json.mensaje || 'Error al emitir reembolso');
                if (modalEmitirReembolso) modalEmitirReembolso.hide();
                mostrarExito('Reembolso registrado y ejecutado exitosamente (' + json.datos.correlativo + ').');
                cargarPagos();
                cargarVentasAuxiliares();
            })
            .catch(err => mostrarError(err.message || 'No se pudo emitir el reembolso.'))
            .finally(() => {
                if (btn) btn.disabled = false;
            });
        });
    }

    // =========================================================================
    // 6. GESTIÓN DE CUENTAS BANCARIAS
    // =========================================================================
    function cargarCuentasBancarias() {
        fetch(URL_API_CUENTAS, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(json => {
            if (!json.exito) return;
            cacheCuentas = json.datos || [];
            renderizarGridCuentas(cacheCuentas);
            poblarSelectCuentas(cacheCuentas);
        })
        .catch(err => console.error('Error al cargar cuentas bancarias:', err));
    }

    function renderizarGridCuentas(cuentas) {
        const grid = document.getElementById('gridCuentasBancarias');
        if (!grid) return;
        grid.innerHTML = '';

        if (cuentas.length === 0) {
            grid.innerHTML = `
                <div class="col-12 text-center py-4 text-muted">
                    <i class="fa-solid fa-building-columns f-s-30 mb-2 text-secondary opacity-50 d-block"></i>
                    No hay cuentas bancarias registradas en la organización.
                </div>
            `;
            return;
        }

        cuentas.forEach(c => {
            const col = document.createElement('div');
            col.className = 'col-lg-4 col-md-6';
            col.innerHTML = `
                <div class="card border shadow-sm b-r-12 h-100 ${!c.activo ? 'opacity-75 bg-light' : ''}">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge ${c.moneda === 'USD' ? 'bg-success' : 'bg-primary'}">${c.moneda}</span>
                            <span class="badge ${c.activo ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'}">
                                ${c.activo ? 'Activa' : 'Inactiva'}
                            </span>
                        </div>
                        <h6 class="f-w-700 text-dark mb-1">${escaparHtml(c.banco_nombre)}</h6>
                        <span class="f-s-12 text-muted d-block mb-2">${c.tipo_cuenta.replace('_', ' ')}</span>
                        
                        <div class="bg-light p-2 b-r-8 mb-2 f-s-13">
                            <span class="text-secondary d-block f-s-11">N° de Cuenta:</span>
                            <strong class="text-dark">${escaparHtml(c.numero_cuenta)}</strong>
                            ${c.codigo_interbancario ? `<span class="text-secondary d-block f-s-11 mt-1">CCI: ${escaparHtml(c.codigo_interbancario)}</span>` : ''}
                        </div>

                        <span class="f-s-12 text-secondary d-block mb-3">
                            <i class="fa-solid fa-user me-1"></i> Titular: ${escaparHtml(c.titular_nombre)}
                        </span>

                        <div class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-cuenta" data-id="${c.id}">
                                <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                            </button>
                        </div>
                    </div>
                </div>
            `;
            grid.appendChild(col);
        });

        grid.querySelectorAll('.btn-editar-cuenta').forEach(b => {
            b.addEventListener('click', () => {
                const id = parseInt(b.getAttribute('data-id'));
                const cuenta = cacheCuentas.find(c => c.id === id);
                if (cuenta) abrirModalCuenta(cuenta);
            });
        });
    }

    function poblarSelectCuentas(cuentas) {
        const select = document.getElementById('pagoCuentaBancariaId');
        if (!select) return;
        select.innerHTML = '<option value="">Seleccione cuenta de abono...</option>';
        cuentas.filter(c => c.activo).forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = `${c.banco_nombre} (${c.moneda}) - ${c.numero_cuenta}`;
            select.appendChild(opt);
        });
    }

    function abrirModalCuenta(cuenta = null) {
        if (formCuentaBancaria) formCuentaBancaria.reset();
        document.getElementById('ctaId').value = cuenta ? cuenta.id : '';
        document.getElementById('modalCuentaTitulo').innerHTML = cuenta
            ? '<i class="fa-solid fa-pen-to-square text-primary me-2"></i> Editar Cuenta Bancaria'
            : '<i class="fa-solid fa-plus text-primary me-2"></i> Nueva Cuenta Bancaria';

        if (cuenta) {
            document.getElementById('ctaBanco').value = cuenta.banco_nombre;
            document.getElementById('ctaTipo').value = cuenta.tipo_cuenta;
            document.getElementById('ctaMoneda').value = cuenta.moneda;
            document.getElementById('ctaTitular').value = cuenta.titular_nombre;
            document.getElementById('ctaNumero').value = cuenta.numero_cuenta;
            document.getElementById('ctaCci').value = cuenta.codigo_interbancario || '';
            document.getElementById('ctaAlias').value = cuenta.alias_identificador || '';
            document.getElementById('ctaQrUrl').value = cuenta.qr_imagen_url || '';
            document.getElementById('ctaActivo').checked = cuenta.activo;
            document.getElementById('ctaInstrucciones').value = cuenta.instrucciones_pago || '';
        }

        if (modalCuentaBancaria) modalCuentaBancaria.show();
    }

    function guardarCuentaBancaria(e) {
        e.preventDefault();

        const id = document.getElementById('ctaId')?.value;
        const payload = {
            banco_nombre: document.getElementById('ctaBanco')?.value.trim(),
            tipo_cuenta: document.getElementById('ctaTipo')?.value,
            moneda: document.getElementById('ctaMoneda')?.value,
            titular_nombre: document.getElementById('ctaTitular')?.value.trim(),
            numero_cuenta: document.getElementById('ctaNumero')?.value.trim(),
            codigo_interbancario: document.getElementById('ctaCci')?.value.trim() || null,
            alias_identificador: document.getElementById('ctaAlias')?.value.trim() || null,
            qr_imagen_url: document.getElementById('ctaQrUrl')?.value.trim() || null,
            activo: document.getElementById('ctaActivo')?.checked,
            instrucciones_pago: document.getElementById('ctaInstrucciones')?.value.trim() || null,
            csrf_token: CSRF_TOKEN
        };

        const url = id ? `${URL_API_CUENTAS}/${id}` : URL_API_CUENTAS;
        const method = id ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: jsonHeaders,
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(json => {
            if (!json.exito) throw new Error(json.mensaje || 'Error al guardar cuenta');
            if (modalCuentaBancaria) modalCuentaBancaria.hide();
            mostrarExito('Cuenta bancaria guardada exitosamente.');
            cargarCuentasBancarias();
        })
        .catch(err => mostrarError(err.message || 'Error al procesar la cuenta bancaria.'));
    }

    // =========================================================================
    // 7. GESTIÓN Y CONFIGURACIÓN DE PASARELAS
    // =========================================================================
    function cargarPasarelas() {
        fetch(URL_API_PASARELAS, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(json => {
            if (!json.exito) return;
            cachePasarelasCatalogo = json.datos.catalogo || [];
            cachePasarelasConfigs = json.datos.configuraciones || [];
            renderizarGridPasarelas(cachePasarelasCatalogo, cachePasarelasConfigs);
        })
        .catch(err => console.error('Error al cargar pasarelas:', err));
    }

    function renderizarGridPasarelas(catalogo, configs) {
        const grid = document.getElementById('gridPasarelas');
        if (!grid) return;
        grid.innerHTML = '';

        catalogo.forEach(p => {
            const config = configs.find(c => c.pasarela_codigo === p.codigo);
            const activa = config ? config.activo : false;
            const ambiente = config ? config.ambiente : 'SANDBOX';

            const col = document.createElement('div');
            col.className = 'col-lg-4 col-md-6';
            col.innerHTML = `
                <div class="card border shadow-sm b-r-12 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-light text-dark border">${p.tipo_integracion}</span>
                            <span class="badge ${activa ? 'bg-success' : 'bg-secondary'}">${activa ? 'Habilitada' : 'Deshabilitada'}</span>
                        </div>
                        <h6 class="f-w-700 text-dark mb-1">${escaparHtml(p.nombre)}</h6>
                        <p class="f-s-12 text-secondary mb-2">${escaparHtml(p.descripcion)}</p>

                        <div class="bg-light p-2 b-r-8 mb-3 f-s-12">
                            <span class="text-secondary d-block">Ambiente: <strong class="text-warning">${ambiente}</strong></span>
                            <span class="text-secondary d-block">Webhook: <code>${p.protocolo_webhook}</code></span>
                            <span class="text-secondary d-block">Reembolsos: ${p.soporta_reembolsos ? '✓ Soportado' : '✗ No soportado'}</span>
                            ${config ? `<span class="text-secondary d-block mt-1">Comisión: <strong>${config.comision_porcentaje}% + S/ ${config.comision_fija}</strong> (${config.asume_comision})</span>` : ''}
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-config-pasarela" data-codigo="${p.codigo}">
                                <i class="fa-solid fa-gears me-1"></i> Configurar
                            </button>
                        </div>
                    </div>
                </div>
            `;
            grid.appendChild(col);
        });

        grid.querySelectorAll('.btn-config-pasarela').forEach(b => {
            b.addEventListener('click', () => {
                const codigo = b.getAttribute('data-codigo');
                abrirModalConfigPasarela(codigo);
            });
        });
    }

    function abrirModalConfigPasarela(codigo) {
        const pasarela = cachePasarelasCatalogo.find(p => p.codigo === codigo);
        const config = cachePasarelasConfigs.find(c => c.pasarela_codigo === codigo);
        if (!pasarela) return;

        if (formConfigurarPasarela) formConfigurarPasarela.reset();
        document.getElementById('pasarelaCodigoModal').value = codigo;
        document.getElementById('pasarelaNombreModal').textContent = pasarela.nombre;

        document.getElementById('pasAmbiente').value = config ? config.ambiente : 'SANDBOX';
        document.getElementById('pasActivo').checked = config ? config.activo : false;
        document.getElementById('pasLlavePublica').value = config ? (config.llave_publica || '') : '';
        document.getElementById('pasComisionPct').value = config ? config.comision_porcentaje : '3.99';
        document.getElementById('pasComisionFija').value = config ? config.comision_fija : '1.00';
        document.getElementById('pasAsumeComision').value = config ? config.asume_comision : 'ORGANIZACION';

        if (modalConfigurarPasarela) modalConfigurarPasarela.show();
    }

    function guardarConfiguracionPasarela(e) {
        e.preventDefault();

        const codigo = document.getElementById('pasarelaCodigoModal')?.value;
        if (!codigo) return;

        const payload = {
            ambiente: document.getElementById('pasAmbiente')?.value || 'SANDBOX',
            activo: document.getElementById('pasActivo')?.checked,
            llave_publica: document.getElementById('pasLlavePublica')?.value.trim() || null,
            credencial_secreta: document.getElementById('pasCredencialSecreta')?.value.trim() || null,
            webhook_secreto: document.getElementById('pasWebhookSecreto')?.value.trim() || null,
            comision_porcentaje: parseFloat(document.getElementById('pasComisionPct')?.value || 0),
            comision_fija: parseFloat(document.getElementById('pasComisionFija')?.value || 0),
            asume_comision: document.getElementById('pasAsumeComision')?.value,
            csrf_token: CSRF_TOKEN
        };

        fetch(URL_API_PASARELAS + `/${codigo}`, {
            method: 'PUT',
            headers: jsonHeaders,
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(json => {
            if (!json.exito) throw new Error(json.mensaje || 'Error al guardar pasarela');
            if (modalConfigurarPasarela) modalConfigurarPasarela.hide();
            mostrarExito('Configuración de pasarela actualizada exitosamente.');
            cargarPasarelas();
        })
        .catch(err => mostrarError(err.message || 'Error al configurar pasarela.'));
    }

    // =========================================================================
    // 8. SIMULADOR / CALCULADORA DE COMISIONES
    // =========================================================================
    function calcularSimulacion() {
        const montoBase = parseFloat(document.getElementById('calcMontoBase')?.value || 0);
        const selPasarela = document.getElementById('calcPasarela');
        const opt = selPasarela ? selPasarela.options[selPasarela.selectedIndex] : null;
        const pct = opt ? parseFloat(opt.getAttribute('data-pct') || 0) : 0;
        const fija = opt ? parseFloat(opt.getAttribute('data-fija') || 0) : 0;
        const asumeCliente = document.getElementById('asumeCli')?.checked;

        let cobrado = montoBase;
        let comPct = 0;
        let comFija = fija;
        let comIgv = 0;
        let comTotal = 0;
        let neto = 0;
        let aplicadoVenta = montoBase;

        if (asumeCliente) {
            // El cliente asume la comisión como recargo
            if (pct > 0 || fija > 0) {
                // Tasa efectiva con IGV (18%)
                const tasaEfectivaPct = (pct * 1.18) / 100.0;
                const fijaConIgv = fija * 1.18;
                cobrado = (montoBase + fijaConIgv) / (1.0 - tasaEfectivaPct);
            }
            cobrado = Math.round(cobrado * 100) / 100;
            comPct = Math.round((cobrado * (pct / 100.0)) * 100) / 100;
            comFija = fija;
            comIgv = Math.round(((comPct + comFija) * 0.18) * 100) / 100;
            comTotal = Math.round((comPct + comFija + comIgv) * 100) / 100;
            neto = Math.round((cobrado - comTotal) * 100) / 100;
            aplicadoVenta = montoBase;
        } else {
            // La organización asume la comisión
            cobrado = montoBase;
            comPct = Math.round((cobrado * (pct / 100.0)) * 100) / 100;
            comFija = fija;
            comIgv = Math.round(((comPct + comFija) * 0.18) * 100) / 100;
            comTotal = Math.round((comPct + comFija + comIgv) * 100) / 100;
            neto = Math.round((cobrado - comTotal) * 100) / 100;
            aplicadoVenta = cobrado;
        }

        document.getElementById('calcResCobrado').textContent = 'S/ ' + formatearMonto(cobrado);
        document.getElementById('calcResComisionPct').textContent = '- S/ ' + formatearMonto(comPct);
        document.getElementById('calcResComisionFija').textContent = '- S/ ' + formatearMonto(comFija);
        document.getElementById('calcResComisionIgv').textContent = '- S/ ' + formatearMonto(comIgv);
        document.getElementById('calcResComisionTotal').textContent = 'S/ ' + formatearMonto(comTotal);
        document.getElementById('calcResNetoRecibido').textContent = 'S/ ' + formatearMonto(neto);
        document.getElementById('calcResAplicadoVenta').textContent = 'S/ ' + formatearMonto(aplicadoVenta);
    }

    // =========================================================================
    // 9. AUXILIARES: VENTAS PENDIENTES
    // =========================================================================
    function cargarVentasAuxiliares() {
        fetch(URL_API_PAGOS + '/aux/ventas', { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(json => {
            if (!json.exito) return;
            cacheVentasPendientes = json.datos || [];
            const select = document.getElementById('pagoVentaId');
            if (!select) return;

            select.innerHTML = '<option value="">Seleccione una venta...</option>';
            cacheVentasPendientes.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.id;
                opt.setAttribute('data-saldo', v.saldo_pendiente);
                opt.textContent = `${v.correlativo} - ${v.cliente_nombre_completo} (Total: ${v.moneda} ${formatearMonto(v.total)} | Deuda: ${v.moneda} ${formatearMonto(v.saldo_pendiente)})`;
                select.appendChild(opt);
            });
        })
        .catch(err => console.error('Error al cargar ventas auxiliares:', err));
    }

    // =========================================================================
    // AYUDANTES GENERALES
    // =========================================================================
    function formatearMonto(monto) {
        const val = parseFloat(monto || 0);
        return isNaN(val) ? '0.00' : val.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escaparHtml(texto) {
        if (!texto) return '';
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function mostrarExito(mensaje) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Operación Exitosa',
                text: mensaje,
                timer: 2500,
                showConfirmButton: false
            });
        } else {
            alert(mensaje);
        }
    }

    function mostrarError(mensaje) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error Financiero',
                text: mensaje
            });
        } else {
            alert(mensaje);
        }
    }

    function mostrarAlerta(mensaje, icon = 'info') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: icon,
                title: 'Atención',
                text: mensaje
            });
        } else {
            alert(mensaje);
        }
    }
});
