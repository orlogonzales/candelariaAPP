/**
 * Dashboard Ejecutivo y Métricas de Rendimiento (F2.12C)
 * Interacción AJAX reactiva, gráficos nativos CSS/Bootstrap y renderizado seguro.
 */

document.addEventListener('DOMContentLoaded', function () {
    const selectorEdicion = document.getElementById('selectorEdicionDashboard');
    const btnRefrescar = document.getElementById('btnRefrescarDashboard');
    const iconoRefrescar = document.getElementById('iconoRefrescar');
    const cargandoDiv = document.getElementById('cargandoDashboard');
    const contenidoDiv = document.getElementById('contenidoDashboard');

    // Inicializar
    cargarDashboardCompleto();

    if (selectorEdicion) {
        selectorEdicion.addEventListener('change', function () {
            cargarKpisEdicion(this.value);
        });
    }

    if (btnRefrescar) {
        btnRefrescar.addEventListener('click', function () {
            const edicionId = selectorEdicion ? selectorEdicion.value : null;
            cargarDashboardCompleto(edicionId);
        });
    }

    function cargarDashboardCompleto(edicionId = null) {
        animarRefresco(true);
        mostrarCargando(true);

        const idSeleccionado = edicionId || (selectorEdicion ? selectorEdicion.value : null);

        Promise.all([
            obtenerKpis(idSeleccionado),
            obtenerComparativa()
        ])
        .then(([respKpis, respComp]) => {
            mostrarCargando(false);
            animarRefresco(false);

            if (respKpis && respKpis.exito) {
                renderizarKpis(respKpis.datos);
            } else {
                mostrarAlertaError(respKpis ? respKpis.mensaje : 'No se pudieron obtener los KPIs.');
            }

            if (respComp && respComp.exito) {
                renderizarComparativa(respComp.datos);
            }
        })
        .catch(err => {
            console.error('Error cargando Dashboard Ejecutivo:', err);
            mostrarCargando(false);
            animarRefresco(false);
            mostrarAlertaError('Ocurrió un error al procesar las métricas ejecutivas.');
        });
    }

    function cargarKpisEdicion(edicionId) {
        animarRefresco(true);
        obtenerKpis(edicionId)
            .then(resp => {
                animarRefresco(false);
                if (resp && resp.exito) {
                    renderizarKpis(resp.datos);
                } else {
                    mostrarAlertaError(resp ? resp.mensaje : 'Error al consultar edición.');
                }
            })
            .catch(err => {
                animarRefresco(false);
                console.error(err);
                mostrarAlertaError('No se pudo conectar con el servidor.');
            });
    }

    function obtenerKpis(edicionId) {
        const urlBase = (window.urlBase || '').replace(/\/$/, '');
        let endpoint = `${urlBase}/api/v1/dashboard/kpis`;
        if (edicionId) {
            endpoint += `?edicion_id=${encodeURIComponent(edicionId)}`;
        }

        return fetch(endpoint, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(r => r.json());
    }

    function obtenerComparativa() {
        const urlBase = (window.urlBase || '').replace(/\/$/, '');
        const endpoint = `${urlBase}/api/v1/dashboard/comparativa`;

        return fetch(endpoint, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(r => r.json());
    }

    function renderizarKpis(datos) {
        if (!datos) return;

        const ed = datos.edicion_actual;
        const kpis = datos.kpis;

        // 1. Cabecera y Estado de la Edición
        if (ed) {
            document.getElementById('dashEdicionNombre').textContent = ed.nombre || 'Edición Candelaria';
            document.getElementById('dashEdicionCodigo').textContent = ed.codigo || '-';
            document.getElementById('dashEdicionAnio').textContent = ed.anio || '-';
            
            const fi = ed.fecha_inicio ? ed.fecha_inicio.substring(0, 10) : 'Pendiente';
            const ff = ed.fecha_fin ? ed.fecha_fin.substring(0, 10) : 'Pendiente';
            document.getElementById('dashEdicionFechas').textContent = `${fi} al ${ff}`;

            const badgeEstado = document.getElementById('dashEdicionEstado');
            badgeEstado.textContent = ed.estado || 'DESCONOCIDO';
            badgeEstado.className = 'badge p-2 f-s-12 text-uppercase ' + obtenerClaseEstado(ed.estado);

            if (selectorEdicion && ed.id && selectorEdicion.value !== String(ed.id)) {
                selectorEdicion.value = String(ed.id);
            }
        }

        // 2. Tarjetas Superiores
        const f = kpis.finanzas || {};
        const r = kpis.reservas || {};
        const op = kpis.operaciones || {};
        const as = kpis.asistencia || {};
        const pp = kpis.postproduccion || {};

        document.getElementById('kpiTotalDevengado').textContent = formatearSoles(f.total_devengado);
        document.getElementById('kpiVentasActivas').textContent = f.ventas_activas_qty || 0;
        document.getElementById('kpiTicketPromedio').textContent = `Ticket: ${formatearSoles(f.ticket_promedio)}`;

        document.getElementById('kpiTotalRecaudado').textContent = formatearSoles(f.total_neto_recaudado);
        document.getElementById('kpiPctCobranza').textContent = `${(f.porcentaje_cobranza || 0).toFixed(1)}%`;
        document.getElementById('kpiSaldoPendiente').textContent = `Pendiente: ${formatearSoles(f.total_saldo_pendiente)}`;

        document.getElementById('kpiOcupacionFlota').textContent = `${(op.tasa_ocupacion_flota_pct || 0).toFixed(1)}%`;
        document.getElementById('kpiPasajerosAgendados').textContent = op.pasajeros_agendados_total || 0;
        document.getElementById('kpiCapacidadFlota').textContent = op.capacidad_total_flota || 0;

        document.getElementById('kpiTasaAsistencia').textContent = `${(as.tasa_asistencia_pct || 0).toFixed(1)}%`;
        document.getElementById('kpiAsistentesQty').textContent = as.total_asistieron || 0;
        document.getElementById('kpiAusentesQty').textContent = as.total_ausentes || 0;
        document.getElementById('kpiTasaNoShow').textContent = `${(as.tasa_no_show_pct || 0).toFixed(1)}%`;

        // 3. Conciliación Financiera y Progreso de Cobranza
        const pctCob = Math.min(100, Math.max(0, f.porcentaje_cobranza || 0));
        document.getElementById('progresoCobranzaTexto').textContent = `${pctCob.toFixed(1)}%`;
        const barraProgreso = document.getElementById('progresoCobranzaBarra');
        barraProgreso.style.width = `${pctCob}%`;
        barraProgreso.className = `progress-bar progress-bar-striped progress-bar-animated ${pctCob >= 90 ? 'bg-success' : pctCob >= 50 ? 'bg-info' : 'bg-warning'}`;

        document.getElementById('detDevengado').textContent = formatearSoles(f.total_devengado);
        document.getElementById('detCobradoCliente').textContent = formatearSoles(f.total_cobrado_cliente);
        document.getElementById('detComisionesPasarela').textContent = `- ${formatearSoles(f.total_comisiones_pasarela)}`;
        document.getElementById('detRecaudacionNeta').textContent = formatearSoles(f.total_neto_recaudado);
        document.getElementById('detSaldoPendiente').textContent = formatearSoles(f.total_saldo_pendiente);

        document.getElementById('detVentasPagadas').textContent = f.ventas_pagadas_total_qty || 0;
        document.getElementById('detVentasParciales').textContent = f.ventas_pago_parcial_qty || 0;
        document.getElementById('detVentasNoPagadas').textContent = f.ventas_no_pagadas_qty || 0;

        // 4. Canales Comerciales
        renderizarCanalesComerciales(kpis.canales || []);

        // 5. Salidas de Campo
        renderizarSalidasCampo(op.salidas || []);

        // 6. Postproducción y Entregas
        document.getElementById('kpiProyectosQty').textContent = pp.total_proyectos || 0;
        document.getElementById('kpiEntregasQty').textContent = pp.entregas_totales || 0;
        document.getElementById('kpiTasaAprobacion').textContent = `${(pp.tasa_aprobacion_entregas_pct || 0).toFixed(1)}%`;
        
        document.getElementById('detEntregasAprobadas').textContent = pp.entregas_aprobadas || 0;
        document.getElementById('detEntregasPendientes').textContent = pp.entregas_pendientes || 0;
        document.getElementById('detGaleriasQty').textContent = pp.galerias_creadas || 0;
        
        const mb = pp.almacenamiento_total_mb || 0;
        document.getElementById('detAlmacenamientoMb').textContent = mb >= 1024 
            ? `${(mb / 1024).toFixed(2)} GB` 
            : `${mb.toFixed(2)} MB`;
    }

    function renderizarCanalesComerciales(canales) {
        const contenedor = document.getElementById('listaCanalesComerciales');
        if (!contenedor) return;

        if (!canales || canales.length === 0) {
            contenedor.innerHTML = '<p class="text-muted text-center py-4">Sin datos de canales comerciales registrados.</p>';
            return;
        }

        let html = '';
        canales.forEach(c => {
            const pct = (c.porcentaje_volumen || 0).toFixed(1);
            html += `
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="f-s-13 f-w-600 text-dark">
                            <i class="fa-solid fa-tag me-1 text-danger"></i> ${escaparHtml(c.canal)}
                            <span class="f-s-11 text-muted f-w-400">(${c.cantidad_ventas} ventas)</span>
                        </span>
                        <span class="f-s-13 text-secondary">
                            <strong>${formatearSoles(c.volumen_devengado)}</strong> (${pct}%)
                        </span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-gradient-danger" role="progressbar" style="width: ${pct}%;"></div>
                    </div>
                </div>
            `;
        });

        contenedor.innerHTML = html;
    }

    function renderizarSalidasCampo(salidas) {
        const tbody = document.getElementById('tbodySalidasCampo');
        const badgeTotal = document.getElementById('badgeTotalSalidas');
        if (!tbody) return;

        if (badgeTotal) {
            badgeTotal.textContent = `${salidas.length} Salidas`;
        }

        if (!salidas || salidas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No hay salidas programadas para esta edición.</td></tr>';
            return;
        }

        let html = '';
        salidas.forEach(s => {
            const pct = s.porcentaje_ocupacion || 0;
            const claseOcup = pct >= 90 ? 'bg-danger' : (pct >= 60 ? 'bg-warning text-dark' : 'bg-success');
            html += `
                <tr>
                    <td class="text-muted">${escaparHtml(s.fecha_salida ? s.fecha_salida.substring(0, 10) : '-')}</td>
                    <td>
                        <strong class="text-dark">${escaparHtml(s.titulo || 'Salida Operativa')}</strong>
                        <div class="f-s-11 text-muted">${escaparHtml(s.correlativo || '')}</div>
                    </td>
                    <td class="text-center">${s.capacidad_maxima}</td>
                    <td class="text-center"><strong>${s.pasajeros_agendados}</strong></td>
                    <td class="text-end">
                        <span class="badge ${claseOcup} p-1 px-2">${pct.toFixed(0)}%</span>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function renderizarComparativa(comparativa) {
        const tbody = document.getElementById('tbodyComparativaEdiciones');
        if (!tbody) return;

        if (!comparativa || comparativa.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No existen registros históricos de ediciones.</td></tr>';
            return;
        }

        let html = '';
        comparativa.forEach(item => {
            html += `
                <tr>
                    <td><strong class="text-dark">${item.anio}</strong></td>
                    <td><span class="badge bg-light text-dark border">${escaparHtml(item.codigo)}</span></td>
                    <td>${escaparHtml(item.nombre)}</td>
                    <td><span class="badge ${obtenerClaseEstado(item.estado)} p-1 px-2 f-s-11">${escaparHtml(item.estado)}</span></td>
                    <td class="text-end f-w-600">${formatearSoles(item.ventas_devengadas)}</td>
                    <td class="text-end f-w-600 text-success">${formatearSoles(item.recaudacion_neta)}</td>
                    <td class="text-center">${item.cantidad_ventas}</td>
                    <td class="text-end text-secondary">${formatearSoles(item.ticket_promedio)}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // Funciones Utilitarias
    function formatearSoles(monto) {
        const num = parseFloat(monto) || 0;
        return 'S/ ' + num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escaparHtml(texto) {
        if (!texto) return '';
        const mapa = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(texto).replace(/[&<>"']/g, m => mapa[m]);
    }

    function obtenerClaseEstado(estado) {
        switch (estado) {
            case 'PREOPERACION': return 'bg-secondary text-white';
            case 'OPERACION': return 'bg-primary text-white';
            case 'POSTPRODUCCION_ENTREGA': return 'bg-warning text-dark';
            case 'CERRADA': return 'bg-dark text-white';
            default: return 'bg-secondary text-white';
        }
    }

    function mostrarCargando(cargando) {
        if (cargandoDiv) cargandoDiv.style.display = cargando ? 'block' : 'none';
        if (contenidoDiv) contenidoDiv.style.display = cargando ? 'none' : 'block';
    }

    function animarRefresco(activo) {
        if (iconoRefrescar) {
            if (activo) {
                iconoRefrescar.classList.add('fa-spin');
            } else {
                iconoRefrescar.classList.remove('fa-spin');
            }
        }
    }

    function mostrarAlertaError(mensaje) {
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: 'Error de Métricas',
                text: mensaje,
                confirmButtonColor: '#d33'
            });
        } else {
            alert(mensaje);
        }
    }
});
