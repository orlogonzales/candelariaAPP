/**
 * ==============================================================================
 * CANDELARIAAPP - GESTIÓN ASÍNCRONA DE CONFIGURACIÓN GENERAL Y PARÁMETROS (F1.2C)
 * ==============================================================================
 * - Consumo de la API REST: /api/v1/configuracion
 * - Separación de soberanía: PLATAFORMA vs ORGANIZACION
 * - Patrón: Skeleton -> Fetch -> Render parcial sin recarga de página
 * - Controles Alina: Basic Switch (.app-switch), Inputs numéricos, Select IANA
 * - Concurrencia optimista con detección de 409 Conflict y refresco in-situ
 * - Botones disabled + spinner durante mutaciones
 * - Alertas y feedback mediante SweetAlert2
 * - CERO location.reload()
 * ==============================================================================
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    // 1. Estado en memoria de los parámetros
    let paramsOrg = {};
    let paramsPlat = {};
    let permisosActuales = {};

    // 2. Elementos DOM principales
    const skeletonEl = document.getElementById('skeletonConfiguracion');
    const contenidoEl = document.getElementById('contenidoConfiguracion');
    const alertaErrorEl = document.getElementById('alertaErrorConfig');
    const btnRecargar = document.getElementById('btnRecargarConfig');
    const btnReintentar = document.getElementById('btnReintentarConfig');
    const csrfToken = document.getElementById('csrfTokenConfig')?.value || '';

    // Inputs y controles de ORGANIZACIÓN
    const inputOrgDiasCotizacion = document.getElementById('inputOrgDiasCotizacion');
    const inputOrgPorcentajeReserva = document.getElementById('inputOrgPorcentajeReserva');
    const switchOrgNotificarWhatsapp = document.getElementById('switchOrgNotificarWhatsapp');
    const estadoWhatsappBadge = document.getElementById('estadoWhatsappBadge');

    const badgeActualizadoDiasCotizacion = document.getElementById('badgeActualizadoDiasCotizacion');
    const badgeActualizadoPorcentajeReserva = document.getElementById('badgeActualizadoPorcentajeReserva');
    const badgeActualizadoWhatsapp = document.getElementById('badgeActualizadoWhatsapp');

    const btnGuardarComercialOrg = document.getElementById('btnGuardarComercialOrg');
    const btnGuardarComunicacionesOrg = document.getElementById('btnGuardarComunicacionesOrg');

    // Inputs y controles de PLATAFORMA
    const inputPlatMoneda = document.getElementById('inputPlatMoneda');
    const inputPlatMontoMinimo = document.getElementById('inputPlatMontoMinimo');
    const selectPlatZonaHoraria = document.getElementById('selectPlatZonaHoraria');
    const switchPlatRegistroPublico = document.getElementById('switchPlatRegistroPublico');
    const estadoRegistroPublicoBadge = document.getElementById('estadoRegistroPublicoBadge');
    const inputPlatMaxIntentos = document.getElementById('inputPlatMaxIntentos');
    const inputPlatMinutosBloqueo = document.getElementById('inputPlatMinutosBloqueo');

    const badgeActualizadoMoneda = document.getElementById('badgeActualizadoMoneda');
    const badgeActualizadoMontoMinimo = document.getElementById('badgeActualizadoMontoMinimo');
    const badgeActualizadoZonaHoraria = document.getElementById('badgeActualizadoZonaHoraria');
    const badgeActualizadoRegistroPublico = document.getElementById('badgeActualizadoRegistroPublico');
    const badgeActualizadoMaxIntentos = document.getElementById('badgeActualizadoMaxIntentos');
    const badgeActualizadoMinutosBloqueo = document.getElementById('badgeActualizadoMinutosBloqueo');

    const btnGuardarFinanzasPlat = document.getElementById('btnGuardarFinanzasPlat');
    const btnGuardarNucleoPlat = document.getElementById('btnGuardarNucleoPlat');
    const btnGuardarSeguridadPlat = document.getElementById('btnGuardarSeguridadPlat');

    /**
     * Resuelve la URL canónica de la API.
     */
    function resolverUrlApi(ruta) {
        const base = window.APP_URL_BASE || '';
        return (base.replace(/\/+$/, '') + '/' + ruta.replace(/^\/+/, '')).replace(/([^:]\/)\/+/g, '$1');
    }

    /**
     * Formatea un timestamp SQL a representación legible.
     */
    function formatearFechaHora(timestamp) {
        if (!timestamp) return 'No registrado';
        const d = new Date(timestamp.replace(' ', 'T'));
        if (isNaN(d.getTime())) return timestamp;
        return d.toLocaleDateString('es-PE', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * Bloquea un botón y añade spinner visual.
     */
    function bloquearBoton(btn, textoSpinner) {
        if (!btn) return;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${textoSpinner}`;
    }

    /**
     * Desbloquea un botón restaurando su estado y texto.
     */
    function desbloquearBoton(btn, textoRestaurar = null) {
        if (!btn) return;
        btn.disabled = false;
        btn.innerHTML = textoRestaurar || btn.dataset.originalHtml || btn.innerHTML;
    }

    /**
     * Actualiza el badge visual del switch de WhatsApp.
     */
    function actualizarBadgeWhatsapp(activo) {
        if (!estadoWhatsappBadge) return;
        if (activo) {
            estadoWhatsappBadge.className = 'badge bg-success-subtle text-success f-s-11';
            estadoWhatsappBadge.textContent = 'ACTIVO';
        } else {
            estadoWhatsappBadge.className = 'badge bg-secondary-subtle text-secondary f-s-11';
            estadoWhatsappBadge.textContent = 'INACTIVO';
        }
    }

    /**
     * Actualiza el badge visual del switch de Registro Público.
     */
    function actualizarBadgeRegistroPublico(activo) {
        if (!estadoRegistroPublicoBadge) return;
        if (activo) {
            estadoRegistroPublicoBadge.className = 'badge bg-danger-subtle text-danger f-s-11';
            estadoRegistroPublicoBadge.textContent = 'HABILITADO';
        } else {
            estadoRegistroPublicoBadge.className = 'badge bg-secondary-subtle text-secondary f-s-11';
            estadoRegistroPublicoBadge.textContent = 'DESACTIVADO';
        }
    }

    /**
     * Carga y procesa la configuración desde la API REST.
     */
    async function cargarConfiguracion() {
        if (skeletonEl) skeletonEl.classList.remove('d-none');
        if (contenidoEl) contenidoEl.classList.add('d-none');
        if (alertaErrorEl) alertaErrorEl.classList.add('d-none');

        try {
            const resp = await fetch(resolverUrlApi('/api/v1/configuracion'), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!resp.ok) {
                const errorData = await resp.json().catch(() => ({}));
                throw new Error(errorData.mensaje || `Error HTTP ${resp.status}`);
            }

            const res = await resp.json();
            if (!res.exito) {
                throw new Error(res.mensaje || 'Error al obtener parámetros.');
            }

            permisosActuales = res.datos.permisos || {};

            // Mapear parámetros de Organización
            paramsOrg = {};
            if (Array.isArray(res.datos.organizacion)) {
                res.datos.organizacion.forEach(item => {
                    paramsOrg[item.codigo] = item;
                });
            }

            // Mapear parámetros de Plataforma
            paramsPlat = {};
            if (Array.isArray(res.datos.plataforma)) {
                res.datos.plataforma.forEach(item => {
                    paramsPlat[item.codigo] = item;
                });
            }

            // Renderizar valores en los controles
            poblarControles();

            if (skeletonEl) skeletonEl.classList.add('d-none');
            if (contenidoEl) contenidoEl.classList.remove('d-none');
        } catch (err) {
            console.error('Error al cargar configuración:', err);
            if (skeletonEl) skeletonEl.classList.add('d-none');
            if (alertaErrorEl) {
                alertaErrorEl.classList.remove('d-none');
                const det = document.getElementById('textoErrorConfigDetalle');
                if (det) det.textContent = err.message || 'No fue posible conectar con el servidor.';
            }
        }
    }

    /**
     * Puebla los controles del formulario a partir del estado en memoria.
     */
    function poblarControles() {
        // --- 1. ORGANIZACIÓN ---
        const paramDias = paramsOrg['organizacion.dias_validez_cotizacion'];
        if (paramDias && inputOrgDiasCotizacion) {
            inputOrgDiasCotizacion.value = paramDias.valor ?? '7';
            if (badgeActualizadoDiasCotizacion) {
                badgeActualizadoDiasCotizacion.textContent = formatearFechaHora(paramDias.actualizado_en);
            }
        }

        const paramReserva = paramsOrg['organizacion.porcentaje_reserva_minimo'];
        if (paramReserva && inputOrgPorcentajeReserva) {
            inputOrgPorcentajeReserva.value = paramReserva.valor ?? '30.00';
            if (badgeActualizadoPorcentajeReserva) {
                badgeActualizadoPorcentajeReserva.textContent = formatearFechaHora(paramReserva.actualizado_en);
            }
        }

        const paramWsp = paramsOrg['organizacion.notificar_whatsapp'];
        if (paramWsp && switchOrgNotificarWhatsapp) {
            const activo = paramWsp.valor === '1' || paramWsp.valor === 'true' || paramWsp.valor === true;
            switchOrgNotificarWhatsapp.checked = activo;
            actualizarBadgeWhatsapp(activo);
            if (badgeActualizadoWhatsapp) {
                badgeActualizadoWhatsapp.textContent = formatearFechaHora(paramWsp.actualizado_en);
            }
        }

        // --- 2. PLATAFORMA ---
        const paramMoneda = paramsPlat['plataforma.moneda_principal'];
        if (paramMoneda && inputPlatMoneda) {
            inputPlatMoneda.value = paramMoneda.valor ?? 'PEN';
            if (badgeActualizadoMoneda) {
                badgeActualizadoMoneda.textContent = formatearFechaHora(paramMoneda.actualizado_en);
            }
        }

        const paramMontoMin = paramsPlat['plataforma.monto_minimo_pago_pe'];
        if (paramMontoMin && inputPlatMontoMinimo) {
            inputPlatMontoMinimo.value = paramMontoMin.valor ?? '50.00';
            if (badgeActualizadoMontoMinimo) {
                badgeActualizadoMontoMinimo.textContent = formatearFechaHora(paramMontoMin.actualizado_en);
            }
        }

        const paramTz = paramsPlat['plataforma.zona_horaria'];
        if (paramTz && selectPlatZonaHoraria) {
            selectPlatZonaHoraria.value = paramTz.valor ?? 'America/Lima';
            if (badgeActualizadoZonaHoraria) {
                badgeActualizadoZonaHoraria.textContent = formatearFechaHora(paramTz.actualizado_en);
            }
        }

        const paramRegPub = paramsPlat['plataforma.permitir_registro_publico'];
        if (paramRegPub && switchPlatRegistroPublico) {
            const activo = paramRegPub.valor === '1' || paramRegPub.valor === 'true' || paramRegPub.valor === true;
            switchPlatRegistroPublico.checked = activo;
            actualizarBadgeRegistroPublico(activo);
            if (badgeActualizadoRegistroPublico) {
                badgeActualizadoRegistroPublico.textContent = formatearFechaHora(paramRegPub.actualizado_en);
            }
        }

        const paramMaxInt = paramsPlat['plataforma.max_intentos_login'];
        if (paramMaxInt && inputPlatMaxIntentos) {
            inputPlatMaxIntentos.value = paramMaxInt.valor ?? '5';
            if (badgeActualizadoMaxIntentos) {
                badgeActualizadoMaxIntentos.textContent = formatearFechaHora(paramMaxInt.actualizado_en);
            }
        }

        const paramMinBloq = paramsPlat['plataforma.minutos_bloqueo_login'];
        if (paramMinBloq && inputPlatMinutosBloqueo) {
            inputPlatMinutosBloqueo.value = paramMinBloq.valor ?? '15';
            if (badgeActualizadoMinutosBloqueo) {
                badgeActualizadoMinutosBloqueo.textContent = formatearFechaHora(paramMinBloq.actualizado_en);
            }
        }
    }

    /**
     * Envía una petición PUT para actualizar parámetros con control de concurrencia.
     */
    async function enviarActualizacion(ambito, parametros, botonActivo, mensajeExito) {
        bloquearBoton(botonActivo, 'Guardando...');

        const payload = {
            _csrf_token: csrfToken,
            ambito: ambito,
            parametros: parametros
        };

        try {
            const resp = await fetch(resolverUrlApi('/api/v1/configuracion'), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });

            const res = await resp.json().catch(() => ({}));

            if (resp.status === 409) {
                // Conflicto de concurrencia optimista
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Conflicto de Concurrencia',
                        text: res.mensaje || 'Los parámetros fueron modificados por otro operador. Se actualizarán los valores más recientes.',
                        confirmButtonText: 'Entendido',
                        customClass: { confirmButton: 'btn btn-warning' }
                    });
                } else {
                    alert(res.mensaje || 'Conflicto de concurrencia.');
                }
                // Refresco in-situ sin recarga completa
                await cargarConfiguracion();
                return;
            }

            if (!resp.ok || !res.exito) {
                throw new Error(res.mensaje || `Error al guardar (HTTP ${resp.status})`);
            }

            // Éxito: Feedback con SweetAlert2
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: mensajeExito || 'Los parámetros fueron actualizados exitosamente.',
                    timer: 2000,
                    showConfirmButton: false,
                    customClass: { popup: 'b-r-16' }
                });
            }

            // Recargar silenciosamente datos para actualizar timestamps de auditoría
            await cargarConfiguracion();
        } catch (err) {
            console.error('Error al actualizar configuración:', err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Validación',
                    text: err.message || 'No fue posible guardar las modificaciones.',
                    confirmButtonText: 'Revisar',
                    customClass: { confirmButton: 'btn btn-danger' }
                });
            } else {
                alert(err.message || 'Error al guardar.');
            }
        } finally {
            desbloquearBoton(botonActivo);
        }
    }

    // ==============================================================================
    // EVENT LISTENERS DE GUARDADO Y REACTIVIDAD
    // ==============================================================================

    // Recargar datos
    if (btnRecargar) {
        btnRecargar.addEventListener('click', () => cargarConfiguracion());
    }
    if (btnReintentar) {
        btnReintentar.addEventListener('click', () => cargarConfiguracion());
    }

    // Toggle reactivo de WhatsApp
    if (switchOrgNotificarWhatsapp) {
        switchOrgNotificarWhatsapp.addEventListener('change', () => {
            actualizarBadgeWhatsapp(switchOrgNotificarWhatsapp.checked);
        });
    }

    // Toggle reactivo de Registro Público
    if (switchPlatRegistroPublico) {
        switchPlatRegistroPublico.addEventListener('change', () => {
            actualizarBadgeRegistroPublico(switchPlatRegistroPublico.checked);
        });
    }

    // 1. Guardar Políticas Comerciales de Organización
    if (btnGuardarComercialOrg) {
        btnGuardarComercialOrg.addEventListener('click', () => {
            const dias = inputOrgDiasCotizacion?.value?.trim();
            const reserva = inputOrgPorcentajeReserva?.value?.trim();

            const parametros = [
                {
                    codigo: 'organizacion.dias_validez_cotizacion',
                    valor: dias,
                    actualizado_en: paramsOrg['organizacion.dias_validez_cotizacion']?.actualizado_en || null
                },
                {
                    codigo: 'organizacion.porcentaje_reserva_minimo',
                    valor: reserva,
                    actualizado_en: paramsOrg['organizacion.porcentaje_reserva_minimo']?.actualizado_en || null
                }
            ];

            enviarActualizacion('ORGANIZACION', parametros, btnGuardarComercialOrg, 'Políticas comerciales guardadas con éxito.');
        });
    }

    // 2. Guardar Comunicaciones de Organización
    if (btnGuardarComunicacionesOrg) {
        btnGuardarComunicacionesOrg.addEventListener('click', () => {
            const activo = switchOrgNotificarWhatsapp?.checked ? '1' : '0';

            const parametros = [
                {
                    codigo: 'organizacion.notificar_whatsapp',
                    valor: activo,
                    actualizado_en: paramsOrg['organizacion.notificar_whatsapp']?.actualizado_en || null
                }
            ];

            enviarActualizacion('ORGANIZACION', parametros, btnGuardarComunicacionesOrg, 'Canales de comunicación actualizados con éxito.');
        });
    }

    // 3. Guardar Finanzas de Plataforma
    if (btnGuardarFinanzasPlat) {
        btnGuardarFinanzasPlat.addEventListener('click', () => {
            const monto = inputPlatMontoMinimo?.value?.trim();

            const parametros = [
                {
                    codigo: 'plataforma.monto_minimo_pago_pe',
                    valor: monto,
                    actualizado_en: paramsPlat['plataforma.monto_minimo_pago_pe']?.actualizado_en || null
                }
            ];

            enviarActualizacion('PLATAFORMA', parametros, btnGuardarFinanzasPlat, 'Parámetros financieros soberanos actualizados.');
        });
    }

    // 4. Guardar Núcleo y Región de Plataforma
    if (btnGuardarNucleoPlat) {
        btnGuardarNucleoPlat.addEventListener('click', () => {
            const tz = selectPlatZonaHoraria?.value;
            const reg = switchPlatRegistroPublico?.checked ? '1' : '0';

            const parametros = [
                {
                    codigo: 'plataforma.zona_horaria',
                    valor: tz,
                    actualizado_en: paramsPlat['plataforma.zona_horaria']?.actualizado_en || null
                },
                {
                    codigo: 'plataforma.permitir_registro_publico',
                    valor: reg,
                    actualizado_en: paramsPlat['plataforma.permitir_registro_publico']?.actualizado_en || null
                }
            ];

            enviarActualizacion('PLATAFORMA', parametros, btnGuardarNucleoPlat, 'Configuración de núcleo y huso horario guardada.');
        });
    }

    // 5. Guardar Seguridad de Plataforma
    if (btnGuardarSeguridadPlat) {
        btnGuardarSeguridadPlat.addEventListener('click', () => {
            const maxInt = inputPlatMaxIntentos?.value?.trim();
            const minBloq = inputPlatMinutosBloqueo?.value?.trim();

            const parametros = [
                {
                    codigo: 'plataforma.max_intentos_login',
                    valor: maxInt,
                    actualizado_en: paramsPlat['plataforma.max_intentos_login']?.actualizado_en || null
                },
                {
                    codigo: 'plataforma.minutos_bloqueo_login',
                    valor: minBloq,
                    actualizado_en: paramsPlat['plataforma.minutos_bloqueo_login']?.actualizado_en || null
                }
            ];

            enviarActualizacion('PLATAFORMA', parametros, btnGuardarSeguridadPlat, 'Políticas de seguridad de autenticación aplicadas.');
        });
    }

    // Carga inicial
    cargarConfiguracion();
});
