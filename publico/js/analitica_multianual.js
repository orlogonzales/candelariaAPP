/**
 * publico/js/analitica_multianual.js
 * Controlador Frontend Reactivo para Analítica Multianual e Inteligencia Histórica (Fase 4.1 — DT-V12-002).
 * Desarrollado en Vanilla JS + Fetch API nativo y visualizaciones vectoriales CSS/SVG sobre Alina Bootstrap 5.
 */

document.addEventListener('DOMContentLoaded', () => {
    const formFiltros = document.getElementById('formFiltrosMultianual');
    const btnRefrescar = document.getElementById('btnRefrescarAnalitica');
    const btnLimpiar = document.getElementById('btnLimpiarFiltrosMultianual');
    const iconoRefrescar = document.getElementById('iconoRefrescarAnalitica');
    const cargandoEl = document.getElementById('cargandoAnaliticaMultianual');
    const contenidoEl = document.getElementById('contenidoAnaliticaMultianual');

    const baseUrl = window.CANDELARIA_URL_BASE || '';

    const fmtMoneda = (val) => {
        const num = parseFloat(val) || 0;
        return 'S/ ' + num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const fmtPct = (val) => {
        if (val === null || val === undefined || val === '') return 'N/A';
        const num = parseFloat(val) || 0;
        return num.toFixed(1) + '%';
    };

    const escapar = (texto) => {
        if (texto === null || texto === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(texto);
        return div.innerHTML;
    };

    const construirQueryFiltros = () => {
        const params = new URLSearchParams();
        const checks = document.querySelectorAll('.check-edicion-comparar:checked');
        const ids = Array.from(checks).map((c) => c.value);
        if (ids.length > 0) {
            params.set('edicion_ids', ids.join(','));
        }

        const estado = document.getElementById('filtroEstadoEdicion')?.value || '';
        if (estado) params.set('estado_edicion', estado);

        const canal = document.getElementById('filtroCanalOrigen')?.value || '';
        if (canal) params.set('canal_origen', canal);

        const tipoDanza = document.getElementById('filtroTipoDanza')?.value || '';
        if (tipoDanza) params.set('tipo_danza_id', tipoDanza);

        return params.toString();
    };

    const cargarAnaliticaMultianual = async () => {
        try {
            if (iconoRefrescar) iconoRefrescar.classList.add('fa-spin');
            if (cargandoEl && contenidoEl.style.display === 'none') {
                cargandoEl.style.display = 'block';
            }

            const query = construirQueryFiltros();
            const url = `${baseUrl}/api/v1/dashboard/analitica-multianual${query ? '?' + query : ''}`;

            const resp = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            });

            const json = await resp.json();
            if (!resp.ok || !json.exito) {
                throw new Error(json.mensaje || 'No se pudo obtener la analítica multianual.');
            }

            renderizarVistaCompleta(json.datos);

            if (cargandoEl) cargandoEl.style.display = 'none';
            if (contenidoEl) contenidoEl.style.display = 'block';
        } catch (err) {
            console.error('[AnaliticaMultianual] Error:', err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Analítica Multianual',
                    text: err.message || 'Ocurrió un problema al consultar las series multianuales.',
                });
            }
        } finally {
            if (iconoRefrescar) iconoRefrescar.classList.remove('fa-spin');
        }
    };

    const renderizarVistaCompleta = (datos) => {
        const rc = datos.resumen_consolidado || {};
        const serieFin = datos.serie_financiera || [];
        const curvas = datos.curvas_preventa || {};
        const retencion = datos.retencion_clientes || {};
        const serieFolclore = datos.serie_folclore || [];
        const serieOpPost = datos.serie_operaciones_postproduccion || [];

        // 1. KPIs Superiores Consolidados
        setTexto('kpiMultiDevengado', fmtMoneda(rc.total_devengado_acumulado));
        setTexto('kpiMultiEdicionesQty', rc.ediciones_evaluadas_qty || 0);
        setTexto('kpiMultiVentasQty', `${rc.ventas_activas_acumuladas || 0} ventas activas`);

        setTexto('kpiMultiNeto', fmtMoneda(rc.total_neto_acumulado));
        setTexto('kpiMultiCobranzaPct', fmtPct(rc.porcentaje_cobranza_global || 0));
        setTexto('kpiMultiComisiones', fmtMoneda(rc.total_comisiones_acumuladas));

        const yoyEl = document.getElementById('kpiMultiCrecimientoYoy');
        if (yoyEl) {
            if (rc.crecimiento_promedio_yoy_pct === null || rc.crecimiento_promedio_yoy_pct === undefined) {
                yoyEl.textContent = 'Base Inicial';
                yoyEl.className = 'f-w-700 text-secondary mb-1';
            } else {
                const valYoy = parseFloat(rc.crecimiento_promedio_yoy_pct);
                const pref = valYoy > 0 ? '+' : '';
                yoyEl.textContent = `${pref}${valYoy.toFixed(2)}%`;
                yoyEl.className = `f-w-700 mb-1 ${valYoy >= 0 ? 'text-success' : 'text-danger'}`;
            }
        }
        setTexto('kpiMultiTicketGlobal', fmtMoneda(rc.ticket_promedio_global));
        setTexto(
            'kpiMultiMejorEdicion',
            rc.mejor_edicion_historica ? `Mejor: ${rc.mejor_edicion_historica.anio} (${rc.mejor_edicion_historica.codigo})` : 'Mejor: -'
        );

        setTexto('kpiMultiRecurrenciaPct', fmtPct(rc.tasa_recurrencia_promedio_pct || 0));
        setTexto('kpiMultiConjuntos', rc.conjuntos_folcloricos_acumulados || 0);
        setTexto('kpiMultiDanzarines', (rc.danzarines_estimados_acumulados || 0).toLocaleString('es-PE'));

        // 2. Gráfico Comparativo de Barras y Tabla Financiera YoY
        renderizarGraficoBarrasFinancieras(serieFin);
        renderizarTablaSerieFinanciera(serieFin);

        // 3. Curvas de Preventa (Booking Pace)
        renderizarCurvasPreventa(curvas);

        // 4. Retención de Clientes y Matriz de Cohortes
        renderizarRetencionYCohortes(retencion);

        // 5. Participación Folclórica Multianual
        renderizarTablaFolclore(serieFolclore);

        // 6. Operaciones, Asistencia QR y Postproducción
        renderizarTablaOperacionesPost(serieOpPost);
    };

    const renderizarGraficoBarrasFinancieras = (serieFin) => {
        const cont = document.getElementById('contenedorGraficoBarrasMultianual');
        if (!cont) return;

        if (!serieFin.length) {
            cont.innerHTML = '<div class="text-center text-muted py-4">Sin ediciones para graficar con los filtros actuales.</div>';
            return;
        }

        let maxMonto = 0;
        serieFin.forEach((f) => {
            if (f.total_devengado > maxMonto) maxMonto = f.total_devengado;
            if (f.total_neto_recaudado > maxMonto) maxMonto = f.total_neto_recaudado;
        });

        let html = '<div class="d-flex flex-column gap-3">';
        serieFin.forEach((f) => {
            const pctDev = maxMonto > 0 ? Math.min(100, Math.round((f.total_devengado / maxMonto) * 100)) : 0;
            const pctNeto = maxMonto > 0 ? Math.min(100, Math.round((f.total_neto_recaudado / maxMonto) * 100)) : 0;
            const yoy = f.variacion_yoy || {};
            let badgeYoy = '<span class="badge bg-light text-muted border">Base Inicial</span>';
            if (yoy.disponible && yoy.delta_devengado_pct !== null) {
                const v = parseFloat(yoy.delta_devengado_pct);
                const cls = v >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                const ico = v >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                badgeYoy = `<span class="badge ${cls}"><i class="fa-solid ${ico} me-1"></i>${v >= 0 ? '+' : ''}${v.toFixed(1)}% YoY</span>`;
            }

            html += `
                <div class="border rounded p-2 bg-light-subtle">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div>
                            <span class="f-w-700 text-dark f-s-13">${f.anio} — ${escapar(f.nombre)}</span>
                            <span class="badge bg-secondary-subtle text-secondary ms-1 f-s-10">${escapar(f.codigo)}</span>
                        </div>
                        ${badgeYoy}
                    </div>
                    <div class="mb-1">
                        <div class="d-flex justify-content-between f-s-11 text-muted mb-1">
                            <span>Devengado (${f.ventas_activas_qty} ventas)</span>
                            <strong class="text-primary">${fmtMoneda(f.total_devengado)}</strong>
                        </div>
                        <div class="progress" style="height: 7px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: ${pctDev}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between f-s-11 text-muted mb-1">
                            <span>Recaudación Neta (${fmtPct(f.porcentaje_cobranza)} cobrado)</span>
                            <strong class="text-success">${fmtMoneda(f.total_neto_recaudado)}</strong>
                        </div>
                        <div class="progress" style="height: 7px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: ${pctNeto}%"></div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        cont.innerHTML = html;
    };

    const renderizarTablaSerieFinanciera = (serieFin) => {
        const tbody = document.querySelector('#tablaSerieFinanciera tbody');
        if (!tbody) return;

        if (!serieFin.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No se encontraron ediciones para los criterios seleccionados.</td></tr>';
            return;
        }

        tbody.innerHTML = serieFin.map((f) => {
            const yoy = f.variacion_yoy || {};
            let yoyHtml = '<span class="text-muted f-s-11">Base</span>';
            if (yoy.disponible) {
                if (yoy.delta_devengado_pct !== null) {
                    const val = parseFloat(yoy.delta_devengado_pct);
                    const cls = val >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                    yoyHtml = `<span class="badge ${cls}">${val >= 0 ? '+' : ''}${val.toFixed(1)}%</span>`;
                } else if (yoy.tendencia_devengado === 'NUEVA_ACTIVIDAD') {
                    yoyHtml = '<span class="badge bg-info-subtle text-info">Nueva Act.</span>';
                } else {
                    yoyHtml = '<span class="badge bg-light text-muted">0.0%</span>';
                }
            }

            const selloCierre = f.tiene_acta_cierre
                ? '<i class="fa-solid fa-lock text-success ms-1" title="Acta de cierre firmada e inmutable"></i>'
                : '';

            return `
                <tr>
                    <td class="ps-3">
                        <div class="f-w-700 text-dark">${f.anio} <span class="text-muted f-s-11">(${escapar(f.codigo)})</span>${selloCierre}</div>
                        <span class="badge bg-light text-dark border f-s-10">${escapar(f.estado)}</span>
                    </td>
                    <td class="text-end f-w-600">${f.ventas_activas_qty}</td>
                    <td class="text-end f-w-700 text-primary">${fmtMoneda(f.total_devengado)}</td>
                    <td class="text-center">${yoyHtml}</td>
                    <td class="text-end f-w-700 text-success">${fmtMoneda(f.total_neto_recaudado)}</td>
                    <td class="text-end ${f.total_saldo_pendiente > 0 ? 'text-warning f-w-600' : 'text-muted'}">${fmtMoneda(f.total_saldo_pendiente)}</td>
                    <td class="text-end text-secondary">${fmtMoneda(f.total_anulado_cancelado)}</td>
                    <td class="text-end pe-3 f-w-600">${fmtMoneda(f.ticket_promedio)}</td>
                </tr>
            `;
        }).join('');
    };

    const renderizarCurvasPreventa = (curvas) => {
        const series = curvas.series_ediciones || [];
        const tbody = document.querySelector('#tablaCurvasPreventa tbody');
        const contGraf = document.getElementById('contenedorGraficoPreventa');
        if (!tbody || !contGraf) return;

        if (!series.length) {
            contGraf.innerHTML = '';
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Sin datos de preventa.</td></tr>';
            return;
        }

        // Barras de progreso de preventa por edición en T-30 y T-0
        contGraf.innerHTML = series.map((s) => {
            const mapaHitos = {};
            (s.hitos || []).forEach((h) => {
                mapaHitos[h.codigo_hito] = h;
            });
            const pctT30 = mapaHitos['T-30'] ? mapaHitos['T-30'].porcentaje_avance_monto : 0;
            const pctT0 = mapaHitos['T-0'] ? mapaHitos['T-0'].porcentaje_avance_monto : 0;

            return `
                <div class="mb-2">
                    <div class="d-flex justify-content-between f-s-11 mb-1">
                        <span class="f-w-700 text-dark">${s.anio} (${escapar(s.codigo)}) — Inicio: ${escapar(s.fecha_inicio)}</span>
                        <span class="text-muted">T-30: <strong>${pctT30}%</strong> | T-0: <strong>${pctT0}%</strong> | Total: <strong>${fmtMoneda(s.total_final_devengado)}</strong></span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-info" style="width: ${pctT30}%" title="T-30: ${pctT30}%"></div>
                        <div class="progress-bar bg-warning" style="width: ${Math.max(0, pctT0 - pctT30)}%" title="T-0: ${pctT0}%"></div>
                        <div class="progress-bar bg-success" style="width: ${Math.max(0, 100 - pctT0)}%" title="Cierre Final: 100%"></div>
                    </div>
                </div>
            `;
        }).join('');

        const codigosOrden = ['T-90', 'T-60', 'T-30', 'T-15', 'T-7', 'T-0', 'FINAL'];
        tbody.innerHTML = series.map((s) => {
            const mapaHitos = {};
            (s.hitos || []).forEach((h) => {
                mapaHitos[h.codigo_hito] = h;
            });
            const celdas = codigosOrden.map((cod) => {
                const h = mapaHitos[cod];
                if (!h) return '<td class="text-end text-muted">-</td>';
                return `
                    <td class="text-end">
                        <div class="f-w-600 text-dark">${fmtMoneda(h.devengado_acumulado)}</div>
                        <small class="text-muted">${h.porcentaje_avance_monto}% (${h.ventas_acumuladas_qty}v)</small>
                    </td>
                `;
            }).join('');

            return `
                <tr>
                    <td class="f-w-700">${s.anio} <small class="text-muted">(${escapar(s.codigo)})</small></td>
                    ${celdas}
                </tr>
            `;
        }).join('');
    };

    const renderizarRetencionYCohortes = (retencion) => {
        const serie = retencion.serie_por_edicion || [];
        const cohortes = retencion.matriz_cohortes || [];
        const tbody = document.querySelector('#tablaRetencionClientes tbody');
        const contMatriz = document.getElementById('contenedorMatrizCohortes');

        if (tbody) {
            if (!serie.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Sin datos de clientes.</td></tr>';
            } else {
                tbody.innerHTML = serie.map((r) => `
                    <tr>
                        <td class="f-w-700">${r.anio} <small class="text-muted">(${escapar(r.codigo)})</small></td>
                        <td class="text-end f-w-600">${r.clientes_unicos_activos}</td>
                        <td class="text-end text-primary f-w-600">${r.clientes_nuevos}</td>
                        <td class="text-end text-success f-w-600">${r.clientes_recurrentes}</td>
                        <td class="text-end"><span class="badge bg-info-subtle text-info">${fmtPct(r.tasa_recurrencia_pct)}</span></td>
                        <td class="text-end">${fmtMoneda(r.ingresos_clientes_nuevos)}</td>
                        <td class="text-end">${fmtMoneda(r.ingresos_clientes_recurrentes)}</td>
                    </tr>
                `).join('');
            }
        }

        if (contMatriz) {
            if (!cohortes.length) {
                contMatriz.innerHTML = '<div class="text-muted f-s-12">Sin cohortes para mostrar.</div>';
                return;
            }

            const aniosCols = cohortes.map((c) => ({ id: c.edicion_origen_id, anio: c.edicion_origen_anio, codigo: c.edicion_origen_codigo }));
            let html = '<table class="table table-sm table-bordered mb-0 f-s-11 align-middle"><thead class="bg-light text-muted"><tr><th>Cohorte Origen</th><th class="text-end">Tamaño</th>';
            aniosCols.forEach((col) => {
                html += `<th class="text-center">${col.anio}</th>`;
            });
            html += '</tr></thead><tbody>';

            cohortes.forEach((c) => {
                const mapEvo = {};
                (c.evolucion || []).forEach((ev) => {
                    mapEvo[ev.edicion_destino_id] = ev;
                });

                html += `<tr><td class="f-w-700">${c.edicion_origen_anio} (${escapar(c.edicion_origen_codigo)})</td><td class="text-end f-w-600">${c.tamano_cohorte} cli.</td>`;
                aniosCols.forEach((col) => {
                    const ev = mapEvo[col.id];
                    if (!ev) {
                        html += '<td class="text-center text-muted bg-light">-</td>';
                    } else {
                        const pct = parseFloat(ev.retencion_pct) || 0;
                        const bg = col.id === c.edicion_origen_id
                            ? 'bg-primary-subtle text-primary f-w-700'
                            : (pct > 0 ? 'bg-success-subtle text-success f-w-600' : 'text-muted');
                        html += `<td class="text-center ${bg}">${pct.toFixed(1)}% (${ev.clientes_activos})</td>`;
                    }
                });
                html += '</tr>';
            });

            html += '</tbody></table>';
            contMatriz.innerHTML = html;
        }
    };

    const renderizarTablaFolclore = (serieFolclore) => {
        const tbody = document.querySelector('#tablaSerieFolclore tbody');
        if (!tbody) return;

        if (!serieFolclore.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Sin registros folclóricos.</td></tr>';
            return;
        }

        tbody.innerHTML = serieFolclore.map((fl) => {
            const cats = (fl.distribucion_categorias || []).map((c) =>
                `<span class="badge bg-light text-dark border me-1">${escapar(c.categoria_origen)}: ${c.conjuntos_qty}</span>`
            ).join('') || '<span class="text-muted f-s-11">Sin inscripciones</span>';

            return `
                <tr>
                    <td class="ps-3 f-w-700">${fl.anio} <small class="text-muted">(${escapar(fl.codigo)})</small></td>
                    <td class="text-end f-w-600">${fl.conjuntos_activos} <small class="text-muted">(${fl.conjuntos_confirmados} conf.)</small></td>
                    <td class="text-end f-w-700 text-danger">${(fl.danzarines_estimados_total || 0).toLocaleString('es-PE')}</td>
                    <td class="text-end">${fl.bandas_total}</td>
                    <td class="text-end">${fl.turnos_finalizados_qty} / ${fl.turnos_programados_qty}</td>
                    <td class="text-end">${fl.retraso_promedio_minutos} min</td>
                    <td class="pe-3">${cats}</td>
                </tr>
            `;
        }).join('');
    };

    const renderizarTablaOperacionesPost = (serieOp) => {
        const tbody = document.querySelector('#tablaSerieOperacionesPost tbody');
        if (!tbody) return;

        if (!serieOp.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Sin registros operativos.</td></tr>';
            return;
        }

        tbody.innerHTML = serieOp.map((op) => `
            <tr>
                <td class="ps-3 f-w-700">${op.anio} <small class="text-muted">(${escapar(op.codigo)})</small></td>
                <td class="text-end">${op.salidas_programadas} sal. (${op.pasajeros_agendados}/${op.capacidad_total_flota})</td>
                <td class="text-end"><span class="badge bg-primary-subtle text-primary">${fmtPct(op.tasa_ocupacion_flota_pct)}</span></td>
                <td class="text-end"><span class="badge bg-success-subtle text-success">${fmtPct(op.tasa_asistencia_pct)}</span> <small class="text-muted">(${op.asistencias_confirmadas}/${op.participantes_nomina})</small></td>
                <td class="text-end">${op.eventos_offline_confirmados} ok / ${op.eventos_offline_conflictos} conf.</td>
                <td class="text-end">${op.entregas_aprobadas}/${op.entregas_totales} (${fmtPct(op.tasa_aprobacion_entregas_pct)})</td>
                <td class="text-end pe-3 f-w-600">${op.selecciones_fotograficas_qty} fotos (${op.clientes_con_seleccion_qty} cli.)</td>
            </tr>
        `).join('');
    };

    const setTexto = (id, valor) => {
        const el = document.getElementById(id);
        if (el) el.textContent = String(valor);
    };

    // Eventos de formulario y botones
    if (formFiltros) {
        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault();
            cargarAnaliticaMultianual();
        });
    }

    if (btnRefrescar) {
        btnRefrescar.addEventListener('click', () => cargarAnaliticaMultianual());
    }

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', () => {
            document.querySelectorAll('.check-edicion-comparar').forEach((c) => { c.checked = true; });
            if (document.getElementById('filtroEstadoEdicion')) document.getElementById('filtroEstadoEdicion').value = '';
            if (document.getElementById('filtroCanalOrigen')) document.getElementById('filtroCanalOrigen').value = '';
            if (document.getElementById('filtroTipoDanza')) document.getElementById('filtroTipoDanza').value = '';
            cargarAnaliticaMultianual();
        });
    }

    // Botones de Exportación (CSV, XLSX, PDF)
    ['btnExportarMultianualCsv', 'btnExportarMultianualXlsx', 'btnExportarMultianualPdf'].forEach((btnId) => {
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.addEventListener('click', () => {
                const formato = btn.getAttribute('data-formato') || 'csv';
                const query = construirQueryFiltros();
                const url = `${baseUrl}/api/v1/dashboard/analitica-multianual/exportar?formato=${encodeURIComponent(formato)}${query ? '&' + query : ''}`;
                window.open(url, '_blank');
            });
        }
    });

    // Carga inicial automática
    cargarAnaliticaMultianual();
});
