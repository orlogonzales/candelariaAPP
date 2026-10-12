/**
 * ==============================================================================
 * CANDELARIAAPP - JAVASCRIPT OFICIAL: COMUNICACIONES Y WHATSAPP (comunicaciones.js)
 * FASE 2.8C — Integración Alina UI, Bandeja Chat 360°, Ventana 24h, Sandbox y SoD
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // 1. Constantes y URLs de API
    const rawBase = window.CANDELARIA_BASE_URL || '/';
    const BASE_URL = rawBase.endsWith('/') ? rawBase : (rawBase + '/');
    const URL_API_KPIS = BASE_URL + 'api/v1/comunicaciones/kpis';
    const URL_API_CONVERSACIONES = BASE_URL + 'api/v1/comunicaciones/conversaciones';
    const URL_API_PLANTILLAS = BASE_URL + 'api/v1/comunicaciones/plantillas';
    const URL_API_MENSAJES = BASE_URL + 'api/v1/comunicaciones/mensajes';
    const URL_API_CONSENTIMIENTOS = BASE_URL + 'api/v1/comunicaciones/consentimientos';
    const URL_API_CAMPANAS = BASE_URL + 'api/v1/comunicaciones/campanas';
    const URL_API_CONFIG = BASE_URL + 'api/v1/comunicaciones/configuracion';
    const URL_API_SIMULADOR = BASE_URL + 'api/v1/comunicaciones/simulador/recibir';
    const URL_API_OUTBOX = BASE_URL + 'api/v1/comunicaciones/outbox/procesar';
    const URL_API_AUT = BASE_URL + 'api/v1/comunicaciones/automatizaciones';
    const URL_API_AUT_PROBAR = BASE_URL + 'api/v1/comunicaciones/automatizaciones/probar';

    const CSRF_TOKEN = window.CANDELARIA_CSRF_TOKEN || '';

    const jsonHeaders = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };

    // 2. Elementos DOM
    const btnRecargarTodo = document.getElementById('btnRecargarTodo');
    const kpiMensajesHoyTotal = document.getElementById('kpiMensajesHoyTotal');
    const kpiTasaEntrega = document.getElementById('kpiTasaEntrega');
    const kpiTasaLectura = document.getElementById('kpiTasaLectura');
    const kpiGastoPresupuesto = document.getElementById('kpiGastoPresupuesto');

    // Bandeja de Conversaciones
    const listaConversaciones = document.getElementById('listaConversaciones');
    const buscarConversacion = document.getElementById('buscarConversacion');
    const chatClienteNombre = document.getElementById('chatClienteNombre');
    const chatClienteTelefono = document.getElementById('chatClienteTelefono');
    const chatAcciones = document.getElementById('chatAcciones');
    const badgeVentana24h = document.getElementById('badgeVentana24h');
    const cuerpoMensajesChat = document.getElementById('cuerpoMensajesChat');
    const footerChatRespuesta = document.getElementById('footerChatRespuesta');
    const alertaVentanaExpirada = document.getElementById('alertaVentanaExpirada');
    const formResponderChat = document.getElementById('formResponderChat');
    const inputTextoRespuesta = document.getElementById('inputTextoRespuesta');
    const btnCerrarConversacion = document.getElementById('btnCerrarConversacion');
    const btnAsignarOperadorModal = document.getElementById('btnAsignarOperadorModal');
    const btnAbrirPlantillaDesdeChat = document.getElementById('btnAbrirPlantillaDesdeChat');

    // Catálogo de Plantillas
    const cuerpoTablaPlantillas = document.getElementById('cuerpoTablaPlantillas');
    const btnAbrirModalNuevaPlantilla = document.getElementById('btnAbrirModalNuevaPlantilla');

    // Historial Outbox
    const cuerpoTablaMensajes = document.getElementById('cuerpoTablaMensajes');
    const filtroMensajesBusqueda = document.getElementById('filtroMensajesBusqueda');
    const filtroMensajesEstado = document.getElementById('filtroMensajesEstado');
    const filtroMensajesTipo = document.getElementById('filtroMensajesTipo');
    const btnFiltrarMensajes = document.getElementById('btnFiltrarMensajes');
    const btnLimpiarFiltroMensajes = document.getElementById('btnLimpiarFiltroMensajes');

    // Consentimientos
    const cuerpoTablaConsentimientos = document.getElementById('cuerpoTablaConsentimientos');
    const btnAbrirModalConsentimiento = document.getElementById('btnAbrirModalConsentimiento');

    // Campañas
    const cuerpoTablaCampanas = document.getElementById('cuerpoTablaCampanas');
    const btnAbrirModalNuevaCampana = document.getElementById('btnAbrirModalNuevaCampana');

    // Configuración y Simulador
    const formConfiguracionCanal = document.getElementById('formConfiguracionCanal');
    const formSimularEntrante = document.getElementById('formSimularEntrante');
    const btnEjecutarOutboxWorker = document.getElementById('btnEjecutarOutboxWorker');
    const outboxWorkerResultado = document.getElementById('outboxWorkerResultado');

    // Modales Bootstrap
    const modalEnviarMensajeEl = document.getElementById('modalEnviarMensaje');
    const modalEnviarMensaje = modalEnviarMensajeEl ? new bootstrap.Modal(modalEnviarMensajeEl) : null;
    const modalNuevaPlantillaEl = document.getElementById('modalNuevaPlantilla');
    const modalNuevaPlantilla = modalNuevaPlantillaEl ? new bootstrap.Modal(modalNuevaPlantillaEl) : null;
    const modalDetalleMensajeEl = document.getElementById('modalDetalleMensaje');
    const modalDetalleMensaje = modalDetalleMensajeEl ? new bootstrap.Modal(modalDetalleMensajeEl) : null;
    const modalConsentimientoEl = document.getElementById('modalConsentimiento');
    const modalConsentimiento = modalConsentimientoEl ? new bootstrap.Modal(modalConsentimientoEl) : null;
    const modalNuevaCampanaEl = document.getElementById('modalNuevaCampana');
    const modalNuevaCampana = modalNuevaCampanaEl ? new bootstrap.Modal(modalNuevaCampanaEl) : null;
    const modalAsignarOperadorEl = document.getElementById('modalAsignarOperador');
    const modalAsignarOperador = modalAsignarOperadorEl ? new bootstrap.Modal(modalAsignarOperadorEl) : null;

    // Estado local
    let conversacionActivaId = null;
    let conversacionActivaData = null;
    let cachePlantillas = [];
    let cacheConversaciones = [];

    // =========================================================================
    // INICIALIZACIÓN
    // =========================================================================
    cargarKpis();
    cargarConversaciones();
    cargarPlantillas();
    cargarHistorialMensajes();
    cargarConsentimientos();
    cargarCampanas();
    cargarConfiguracion();
    cargarAutomatizaciones();

    // Eventos Globales
    if (btnRecargarTodo) {
        btnRecargarTodo.addEventListener('click', function () {
            cargarKpis();
            cargarConversaciones();
            cargarPlantillas();
            cargarHistorialMensajes();
            cargarConsentimientos();
            cargarCampanas();
            cargarAutomatizaciones();
            mostrarToast('Datos sincronizados', 'info');
        });
    }

    // Auto-activación de tab o modal vía URL
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam === 'automatizaciones') {
        const tabAutLink = document.getElementById('tab-automatizaciones-link');
        if (tabAutLink && typeof bootstrap !== 'undefined') {
            const bsTab = new bootstrap.Tab(tabAutLink);
            bsTab.show();
        }
    }
    const modalParam = urlParams.get('modal');
    if (modalParam === 'automatizacion') {
        setTimeout(() => abrirModalNuevaAutomatizacion(), 400);
    } else if (modalParam === 'probar') {
        setTimeout(() => {
            if (cacheAutomatizaciones.length > 0) {
                abrirModalProbarAutomatizacion(cacheAutomatizaciones[0].id);
            }
        }, 600);
    }

    // =========================================================================
    // 1. CARGA DE KPIS
    // =========================================================================
    function cargarKpis() {
        fetch(URL_API_KPIS, { headers: jsonHeaders })
            .then(res => res.json())
            .then(data => {
                if (data.exito && data.datos) {
                    const d = data.datos;
                    if (kpiMensajesHoyTotal) kpiMensajesHoyTotal.textContent = `${d.mensajes_hoy} / ${d.total_mensajes}`;
                    if (kpiTasaEntrega) kpiTasaEntrega.textContent = `${d.tasa_entrega_pct}%`;
                    if (kpiTasaLectura) kpiTasaLectura.textContent = `${d.tasa_lectura_pct}%`;
                    if (kpiGastoPresupuesto) {
                        const gastado = parseFloat(d.gasto_total_usd || 0).toFixed(2);
                        const limite = parseFloat(d.presupuesto_mensual_limite_usd || 50).toFixed(2);
                        kpiGastoPresupuesto.textContent = `$${gastado} / $${limite}`;
                    }
                }
            })
            .catch(err => console.error('Error cargando KPIs:', err));
    }

    // =========================================================================
    // 2. BANDEJA DE CONVERSACIONES (INBOX 360°)
    // =========================================================================
    function cargarConversaciones(estadoFiltro = '', busqueda = '') {
        let url = URL_API_CONVERSACIONES + '?limite=50';
        if (estadoFiltro) url += '&estado=' + encodeURIComponent(estadoFiltro);
        if (busqueda) url += '&busqueda=' + encodeURIComponent(busqueda);

        fetch(url, { headers: jsonHeaders })
            .then(res => res.json())
            .then(data => {
                if (data.exito && Array.isArray(data.datos)) {
                    cacheConversaciones = data.datos;
                    renderizarListaConversaciones(data.datos);
                }
            })
            .catch(err => {
                console.error('Error cargando conversaciones:', err);
                if (listaConversaciones) {
                    listaConversaciones.innerHTML = '<div class="p-3 text-danger text-center">Error al conectar con la bandeja</div>';
                }
            });
    }

    function renderizarListaConversaciones(conversaciones) {
        if (!listaConversaciones) return;

        if (conversaciones.length === 0) {
            listaConversaciones.innerHTML = `
                <div class="p-4 text-center text-muted">
                    <i class="fa-regular fa-folder-open f-s-32 mb-2 opacity-50"></i>
                    <p class="mb-0">No se encontraron conversaciones activas.</p>
                </div>
            `;
            return;
        }

        let html = '';
        conversaciones.forEach(c => {
            const activaClass = (c.id === conversacionActivaId) ? 'bg-light border-primary' : '';
            const estadoBadge = obtenerBadgeEstadoConversacion(c.estado);
            const ventanaValida = esVentana24hActiva(c.ventana_servicio_expira_en);
            const ventanaBadge = ventanaValida 
                ? '<span class="badge bg-success-subtle text-success border border-success f-s-10">24h Activa</span>'
                : '<span class="badge bg-danger-subtle text-danger border border-danger f-s-10">24h Vencida</span>';

            html += `
                <div class="p-3 border-bottom cursor-pointer conv-item ${activaClass}" data-id="${c.id}" style="cursor: pointer; transition: background 0.15s ease;">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <strong class="text-dark text-truncate f-s-14" style="max-width: 170px;">${escaparHtml(c.cliente_nombre || c.telefono_cliente)}</strong>
                        <span class="f-s-11 text-muted">${formatearHora(c.ultimo_mensaje_cliente_en || c.creado_en)}</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="f-s-12 text-muted text-truncate" style="max-width: 180px;">${escaparHtml(c.telefono_cliente)}</span>
                        ${ventanaBadge}
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <span class="f-s-11 text-muted"><i class="fa-solid fa-headset me-1"></i>${c.operador_nombre ? escaparHtml(c.operador_nombre) : 'Sin asignar'}</span>
                        ${estadoBadge}
                    </div>
                </div>
            `;
        });

        listaConversaciones.innerHTML = html;

        // Asignar listeners a cada ítem
        document.querySelectorAll('.conv-item').forEach(el => {
            el.addEventListener('click', function () {
                const id = parseInt(this.getAttribute('data-id'), 10);
                seleccionarConversacion(id);
            });
        });
    }

    function seleccionarConversacion(id) {
        conversacionActivaId = id;
        document.querySelectorAll('.conv-item').forEach(el => {
            el.classList.toggle('bg-light', parseInt(el.getAttribute('data-id'), 10) === id);
            el.classList.toggle('border-primary', parseInt(el.getAttribute('data-id'), 10) === id);
        });

        if (chatClienteNombre) chatClienteNombre.textContent = 'Cargando conversación #' + id + '...';
        if (chatAcciones) chatAcciones.style.setProperty('display', 'flex', 'important');
        if (footerChatRespuesta) footerChatRespuesta.style.display = 'block';

        fetch(URL_API_CONVERSACIONES + '/' + id + '/mensajes', { headers: jsonHeaders })
            .then(res => res.json())
            .then(data => {
                if (data.exito && data.datos) {
                    conversacionActivaData = data.datos.conversacion;
                    const c = data.datos.conversacion;
                    const mensajes = data.datos.mensajes;

                    const nombreCliente = c.cliente_nombre || c.clienteNombre || c.telefono_cliente || c.telefonoCliente || 'Cliente';
                    const numConversacion = c.numero_conversacion || c.numeroConversacion || c.id;
                    const telCliente = c.telefono_cliente || c.telefonoCliente || '';
                    const expiraEn = c.ventana_servicio_expira_en || c.ventanaServicioExpiraEn;

                    if (chatClienteNombre) chatClienteNombre.textContent = nombreCliente;
                    if (chatClienteTelefono) chatClienteTelefono.textContent = `Hilo #${numConversacion} (${telCliente}) · Estado: ${c.estado}`;

                    // Evaluar Ventana 24h
                    const ventanaActiva = esVentana24hActiva(expiraEn);
                    if (badgeVentana24h) {
                        if (ventanaActiva) {
                            badgeVentana24h.className = 'badge bg-success';
                            badgeVentana24h.innerHTML = '<i class="fa-solid fa-clock me-1"></i> Ventana 24h Abierta';
                            if (alertaVentanaExpirada) alertaVentanaExpirada.classList.add('d-none');
                            if (inputTextoRespuesta) {
                                inputTextoRespuesta.disabled = false;
                                inputTextoRespuesta.placeholder = 'Escriba un mensaje al cliente...';
                            }
                        } else {
                            badgeVentana24h.className = 'badge bg-danger';
                            badgeVentana24h.innerHTML = '<i class="fa-solid fa-lock me-1"></i> Ventana 24h Expirada';
                            if (alertaVentanaExpirada) alertaVentanaExpirada.classList.remove('d-none');
                            if (inputTextoRespuesta) {
                                inputTextoRespuesta.disabled = true;
                                inputTextoRespuesta.placeholder = 'Ventana cerrada. Utilice una plantilla oficial.';
                            }
                        }
                    }

                    renderizarMensajesChat(mensajes);
                }
            })
            .catch(err => {
                console.error('Error cargando mensajes de la conversación:', err);
                mostrarToast('Error al cargar mensajes del hilo', 'error');
            });
    }

    function renderizarMensajesChat(mensajes) {
        if (!cuerpoMensajesChat) return;

        if (!mensajes || mensajes.length === 0) {
            cuerpoMensajesChat.innerHTML = `
                <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                    <i class="fa-regular fa-message f-s-32 mb-2 opacity-50"></i>
                    <p class="mb-0">No hay mensajes registrados en este hilo.</p>
                </div>
            `;
            return;
        }

        let html = '';
        mensajes.forEach(m => {
            const esSaliente = (m.direccion === 'SALIENTE');
            const alignClass = esSaliente ? 'justify-content-end' : 'justify-content-start';
            const bubbleBg = esSaliente ? 'bg-success text-white' : 'bg-white text-dark';
            const estadoIcon = esSaliente ? obtenerIconoEstadoMeta(m.estado) : '';

            html += `
                <div class="d-flex ${alignClass} mb-3">
                    <div class="card border-0 shadow-sm b-r-12 p-3 ${bubbleBg}" style="max-width: 75%; border-radius: 12px;">
                        ${m.plantilla_nombre ? `<div class="f-s-11 opacity-75 mb-1"><i class="fa-solid fa-file-code me-1"></i>Plantilla: <strong>${escaparHtml(m.plantilla_nombre)}</strong></div>` : ''}
                        <div class="f-s-13 text-break" style="white-space: pre-wrap;">${escaparHtml(m.contenido_texto || '')}</div>
                        <div class="d-flex align-items-center justify-content-end gap-1 mt-1 f-s-10 opacity-75">
                            <span>${formatearHora(m.creado_en)}</span>
                            ${estadoIcon}
                        </div>
                    </div>
                </div>
            `;
        });

        cuerpoMensajesChat.innerHTML = html;
        cuerpoMensajesChat.scrollTop = cuerpoMensajesChat.scrollHeight;
    }

    // Filtros de conversaciones
    document.querySelectorAll('.btn-filtro-conv').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.btn-filtro-conv').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const estado = this.getAttribute('data-estado');
            cargarConversaciones(estado, buscarConversacion ? buscarConversacion.value : '');
        });
    });

    if (buscarConversacion) {
        let timer = null;
        buscarConversacion.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const activo = document.querySelector('.btn-filtro-conv.active');
                const estado = activo ? activo.getAttribute('data-estado') : '';
                cargarConversaciones(estado, this.value.trim());
            }, 300);
        });
    }

    // Responder en hilo activo
    if (formResponderChat) {
        formResponderChat.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!conversacionActivaId) return;

            const texto = inputTextoRespuesta ? inputTextoRespuesta.value.trim() : '';
            if (!texto) return;

            const formData = new FormData();
            formData.append('texto', texto);

            fetch(URL_API_CONVERSACIONES + '/' + conversacionActivaId + '/responder', {
                method: 'POST',
                headers: jsonHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.exito) {
                    inputTextoRespuesta.value = '';
                    seleccionarConversacion(conversacionActivaId);
                    cargarKpis();
                    mostrarToast('Mensaje de respuesta encolado en Outbox', 'success');
                } else {
                    mostrarToast(data.error || 'Error al responder', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                mostrarToast('Error en la comunicación con el servidor', 'error');
            });
        });
    }

    // Cerrar conversación
    if (btnCerrarConversacion) {
        btnCerrarConversacion.addEventListener('click', function () {
            if (!conversacionActivaId) return;

            Swal.fire({
                title: '¿Cerrar conversación?',
                text: 'El hilo se marcará como CERRADO y requerirá un nuevo mensaje entrante o plantilla para reactivarse.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, cerrar conversación',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(URL_API_CONVERSACIONES + '/' + conversacionActivaId + '/cerrar', {
                        method: 'POST',
                        headers: jsonHeaders
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.exito) {
                            mostrarToast('Conversación cerrada con éxito', 'success');
                            seleccionarConversacion(conversacionActivaId);
                            cargarConversaciones();
                        } else {
                            mostrarToast(data.error || 'No se pudo cerrar la conversación', 'error');
                        }
                    });
                }
            });
        });
    }

    // Asignar operador modal
    if (btnAsignarOperadorModal) {
        btnAsignarOperadorModal.addEventListener('click', function () {
            if (!conversacionActivaId) return;
            const inputId = document.getElementById('asigConversacionId');
            if (inputId) inputId.value = conversacionActivaId;
            if (modalAsignarOperador) modalAsignarOperador.show();
        });
    }

    const formAsignarOperador = document.getElementById('formAsignarOperador');
    if (formAsignarOperador) {
        formAsignarOperador.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = document.getElementById('asigConversacionId').value;
            const opId = document.getElementById('asigOperadorId').value;

            const fd = new FormData();
            fd.append('operador_id', opId);

            fetch(URL_API_CONVERSACIONES + '/' + id + '/asignar', {
                method: 'POST',
                headers: jsonHeaders,
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.exito) {
                    if (modalAsignarOperador) modalAsignarOperador.hide();
                    mostrarToast('Operador asignado correctamente', 'success');
                    seleccionarConversacion(id);
                    cargarConversaciones();
                } else {
                    mostrarToast(data.error || 'Error al asignar', 'error');
                }
            });
        });
    }

    // Botón abrir plantilla desde chat cuando ventana está expirada
    if (btnAbrirPlantillaDesdeChat) {
        btnAbrirPlantillaDesdeChat.addEventListener('click', function () {
            if (conversacionActivaData) {
                const telInput = document.getElementById('envioTelefono');
                const nomInput = document.getElementById('envioNombre');
                if (telInput) telInput.value = conversacionActivaData.telefonoCliente;
                if (nomInput) nomInput.value = 'Cliente WhatsApp';
            }
            if (modalEnviarMensaje) modalEnviarMensaje.show();
        });
    }

    // =========================================================================
    // 3. CATÁLOGO DE PLANTILLAS
    // =========================================================================
    function cargarPlantillas() {
        fetch(URL_API_PLANTILLAS, { headers: jsonHeaders })
            .then(res => res.json())
            .then(data => {
                if (data.exito && Array.isArray(data.datos)) {
                    cachePlantillas = data.datos;
                    renderizarTablaPlantillas(data.datos);
                    poblarSelectsPlantillas(data.datos);
                }
            })
            .catch(err => console.error('Error cargando plantillas:', err));
    }

    function renderizarTablaPlantillas(plantillas) {
        if (!cuerpoTablaPlantillas) return;

        if (plantillas.length === 0) {
            cuerpoTablaPlantillas.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">No hay plantillas autorizadas registradas en esta organización.</td>
                </tr>
            `;
            return;
        }

        let html = '';
        plantillas.forEach(p => {
            const badgeMeta = obtenerBadgeEstadoMeta(p.estadoMeta || p.estado_meta);
            const categoriaBadge = `<span class="badge bg-light text-dark border">${p.categoria}</span>`;

            html += `
                <tr>
                    <td><strong>${escaparHtml(p.nombre)}</strong></td>
                    <td><code>${escaparHtml(p.idioma)}</code></td>
                    <td>${categoriaBadge}</td>
                    <td>${badgeMeta}</td>
                    <td class="text-truncate" style="max-width: 250px;" title="${escaparHtml(p.cuerpoTexto || p.cuerpo_texto || '')}">
                        ${escaparHtml(p.cuerpoTexto || p.cuerpo_texto || '')}
                    </td>
                    <td>v${p.versionLocal || p.version_local || 1}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-xs btn-outline-primary btn-usar-plantilla" data-nombre="${escaparHtml(p.nombre)}">
                            <i class="fa-solid fa-paper-plane me-1"></i> Usar
                        </button>
                    </td>
                </tr>
            `;
        });

        cuerpoTablaPlantillas.innerHTML = html;

        document.querySelectorAll('.btn-usar-plantilla').forEach(btn => {
            btn.addEventListener('click', function () {
                const nom = this.getAttribute('data-nombre');
                const sel = document.getElementById('envioPlantillaSelect');
                if (sel) sel.value = nom;
                if (modalEnviarMensaje) modalEnviarMensaje.show();
            });
        });
    }

    function poblarSelectsPlantillas(plantillas) {
        const envioSelect = document.getElementById('envioPlantillaSelect');
        const campanaSelect = document.getElementById('campanaPlantillaSelect');

        if (envioSelect) {
            let html = '<option value="">Seleccione una plantilla...</option>';
            plantillas.forEach(p => {
                html += `<option value="${escaparHtml(p.nombre)}">${escaparHtml(p.nombre)} (${p.categoria} - ${p.idioma})</option>`;
            });
            envioSelect.innerHTML = html;
        }

        if (campanaSelect) {
            let html = '<option value="">Seleccione plantilla autorizada...</option>';
            plantillas.filter(p => p.categoria === 'MARKETING').forEach(p => {
                html += `<option value="${p.id}">${escaparHtml(p.nombre)} (${p.idioma})</option>`;
            });
            campanaSelect.innerHTML = html;
        }
    }

    // Guardar nueva plantilla
    const formNuevaPlantilla = document.getElementById('formNuevaPlantilla');
    if (formNuevaPlantilla) {
        formNuevaPlantilla.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);

            fetch(URL_API_PLANTILLAS, {
                method: 'POST',
                headers: jsonHeaders,
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.exito) {
                    if (modalNuevaPlantilla) modalNuevaPlantilla.hide();
                    formNuevaPlantilla.reset();
                    mostrarToast('Plantilla registrada exitosamente', 'success');
                    cargarPlantillas();
                } else {
                    mostrarToast(data.error || 'Error al guardar plantilla', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                mostrarToast('Error de red al guardar plantilla', 'error');
            });
        });
    }

    if (btnAbrirModalNuevaPlantilla) {
        btnAbrirModalNuevaPlantilla.addEventListener('click', () => {
            if (modalNuevaPlantilla) modalNuevaPlantilla.show();
        });
    }

    // =========================================================================
    // 4. HISTORIAL OUTBOX / REGISTRO TRANSACCIONAL
    // =========================================================================
    function cargarHistorialMensajes() {
        let url = URL_API_MENSAJES + '?por_pagina=100';
        if (filtroMensajesEstado && filtroMensajesEstado.value) url += '&estado=' + encodeURIComponent(filtroMensajesEstado.value);
        if (filtroMensajesTipo && filtroMensajesTipo.value) url += '&tipo=' + encodeURIComponent(filtroMensajesTipo.value);

        fetch(url, { headers: jsonHeaders })
            .then(res => res.json())
            .then(data => {
                if (data.exito && data.datos && Array.isArray(data.datos.mensajes)) {
                    renderizarTablaMensajes(data.datos.mensajes);
                }
            })
            .catch(err => console.error('Error cargando mensajes:', err));
    }

    function renderizarTablaMensajes(mensajes) {
        if (!cuerpoTablaMensajes) return;

        if (mensajes.length === 0) {
            cuerpoTablaMensajes.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">No se encontraron registros de mensajes en Outbox.</td>
                </tr>
            `;
            return;
        }

        let html = '';
        mensajes.forEach(m => {
            const badgeEstado = obtenerBadgeEstadoMensaje(m.estado);
            const costo = parseFloat(m.costo_calculado_usd || 0).toFixed(4);

            html += `
                <tr>
                    <td><strong>#${m.id}</strong></td>
                    <td class="f-s-12">${formatearFechaHora(m.creado_en)}</td>
                    <td>${escaparHtml(m.destinatario_nombre || 'Cliente')}</td>
                    <td><code>${escaparHtml(m.destinatario_telefono)}</code></td>
                    <td><span class="badge bg-light text-dark border">${m.tipo_mensaje}</span></td>
                    <td>${badgeEstado}</td>
                    <td>$${costo}</td>
                    <td><span class="badge bg-secondary">${m.intentos_realizados || 0}</span></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-xs btn-outline-secondary btn-ver-intentos" data-id="${m.id}">
                            <i class="fa-solid fa-list-check me-1"></i> Intentos
                        </button>
                    </td>
                </tr>
            `;
        });

        cuerpoTablaMensajes.innerHTML = html;

        document.querySelectorAll('.btn-ver-intentos').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                abrirDetalleMensaje(id);
            });
        });
    }

    if (btnFiltrarMensajes) {
        btnFiltrarMensajes.addEventListener('click', cargarHistorialMensajes);
    }
    if (btnLimpiarFiltroMensajes) {
        btnLimpiarFiltroMensajes.addEventListener('click', function () {
            if (filtroMensajesBusqueda) filtroMensajesBusqueda.value = '';
            if (filtroMensajesEstado) filtroMensajesEstado.value = '';
            if (filtroMensajesTipo) filtroMensajesTipo.value = '';
            cargarHistorialMensajes();
        });
    }

    function abrirDetalleMensaje(id) {
        fetch(URL_API_MENSAJES + '/' + id + '/intentos', { headers: jsonHeaders })
            .then(res => res.json())
            .then(data => {
                if (data.exito && data.datos) {
                    const m = data.datos.mensaje;
                    const intentos = data.datos.intentos;

                    document.getElementById('detMsgId').textContent = '#' + m.id;
                    document.getElementById('detMsgEstado').innerHTML = obtenerBadgeEstadoMensaje(m.estado);
                    document.getElementById('detMsgWamid').textContent = m.wamid || 'Sin wamid asignado por Meta';
                    document.getElementById('detMsgContenido').textContent = m.contenido_texto || '(Sin texto libre)';

                    const tbody = document.getElementById('cuerpoTablaIntentos');
                    if (tbody) {
                        if (intentos.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Sin reintentos registrados</td></tr>';
                        } else {
                            let rows = '';
                            intentos.forEach(i => {
                                rows += `
                                    <tr>
                                        <td>${i.intento_numero}</td>
                                        <td><code>${i.http_status}</code></td>
                                        <td>${i.meta_error_code || '-'}</td>
                                        <td class="text-truncate" style="max-width: 250px;">${escaparHtml(i.meta_error_message || 'OK')}</td>
                                        <td>${i.latencia_ms} ms</td>
                                        <td class="f-s-11">${i.ejecutado_en || '-'}</td>
                                    </tr>
                                `;
                            });
                            tbody.innerHTML = rows;
                        }
                    }

                    if (modalDetalleMensaje) modalDetalleMensaje.show();
                }
            });
    }

    // =========================================================================
    // 5. CONSENTIMIENTOS (OPT-IN / OPT-OUT)
    // =========================================================================
    function cargarConsentimientos() {
        fetch(URL_API_CONSENTIMIENTOS, { headers: jsonHeaders })
            .then(res => res.json())
            .then(data => {
                if (data.exito && Array.isArray(data.datos)) {
                    renderizarTablaConsentimientos(data.datos);
                }
            })
            .catch(err => console.error('Error cargando consentimientos:', err));
    }

    function renderizarTablaConsentimientos(consentimientos) {
        if (!cuerpoTablaConsentimientos) return;

        if (consentimientos.length === 0) {
            cuerpoTablaConsentimientos.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">No se registran consentimientos de canal para esta organización.</td>
                </tr>
            `;
            return;
        }

        let html = '';
        consentimientos.forEach(c => {
            const estadoBadge = (c.estado === 'CONCEDIDO')
                ? '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>CONCEDIDO</span>'
                : '<span class="badge bg-danger"><i class="fa-solid fa-ban me-1"></i>REVOCADO</span>';

            html += `
                <tr>
                    <td><strong>${escaparHtml(c.cliente_nombre || 'Cliente #' + c.cliente_id)}</strong></td>
                    <td><span class="badge bg-light text-dark border">${c.canal}</span></td>
                    <td><code>${escaparHtml(c.telefono_destino)}</code></td>
                    <td><span class="badge bg-info-subtle text-info border border-info">${c.finalidad}</span></td>
                    <td>${estadoBadge}</td>
                    <td class="f-s-12">${c.origen_evidencia}</td>
                    <td class="text-truncate f-s-12" style="max-width: 200px;" title="${escaparHtml(c.texto_clausula_aceptada || '')}">${escaparHtml(c.texto_clausula_aceptada || '-')}</td>
                    <td class="f-s-11">${formatearFechaHora(c.creado_en)}</td>
                    <td class="text-end">
                        ${c.estado === 'CONCEDIDO' ? `
                            <button type="button" class="btn btn-xs btn-outline-danger btn-revocar-cons" data-cli="${c.cliente_id}" data-tel="${c.telefono_destino}" data-fin="${c.finalidad}">
                                <i class="fa-solid fa-user-xmark me-1"></i> Revocar
                            </button>
                        ` : '<span class="text-muted f-s-11">Revocado</span>'}
                    </td>
                </tr>
            `;
        });

        cuerpoTablaConsentimientos.innerHTML = html;

        document.querySelectorAll('.btn-revocar-cons').forEach(btn => {
            btn.addEventListener('click', function () {
                const cli = this.getAttribute('data-cli');
                const tel = this.getAttribute('data-tel');
                const fin = this.getAttribute('data-fin');
                revocarConsentimientoRapido(cli, tel, fin);
            });
        });
    }

    function revocarConsentimientoRapido(clienteId, telefono, finalidad) {
        Swal.fire({
            title: '¿Revocar consentimiento?',
            text: `Se revocará el consentimiento de ${finalidad} para el cliente. El sistema rechazará futuros envíos.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, revocar opt-in',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('cliente_id', clienteId);
                fd.append('telefono', telefono);
                fd.append('finalidad', finalidad);
                fd.append('estado', 'REVOCADO');
                fd.append('origen', 'PANEL_ADMIN_REVOCACION');
                fd.append('clausula', 'Revocación explícita solicitada por titular de datos.');

                fetch(URL_API_CONSENTIMIENTOS, {
                    method: 'POST',
                    headers: jsonHeaders,
                    body: fd
                })
                .then(res => res.json())
                .then(data => {
                    if (data.exito) {
                        mostrarToast('Consentimiento revocado con trazabilidad legal', 'success');
                        cargarConsentimientos();
                    } else {
                        mostrarToast(data.error || 'Error al revocar', 'error');
                    }
                });
            }
        });
    }

    const formConsentimiento = document.getElementById('formConsentimiento');
    if (formConsentimiento) {
        formConsentimiento.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);

            fetch(URL_API_CONSENTIMIENTOS, {
                method: 'POST',
                headers: jsonHeaders,
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.exito) {
                    if (modalConsentimiento) modalConsentimiento.hide();
                    formConsentimiento.reset();
                    mostrarToast('Consentimiento registrado con firma digital', 'success');
                    cargarConsentimientos();
                } else {
                    mostrarToast(data.error || 'Error al registrar consentimiento', 'error');
                }
            });
        });
    }

    if (btnAbrirModalConsentimiento) {
        btnAbrirModalConsentimiento.addEventListener('click', () => {
            if (modalConsentimiento) modalConsentimiento.show();
        });
    }

    // =========================================================================
    // 6. CAMPAÑAS MASIVAS (SEGREGACIÓN DE DEBERES - SoD)
    // =========================================================================
    function cargarCampanas() {
        fetch(URL_API_CAMPANAS, { headers: jsonHeaders })
            .then(res => res.json())
            .then(data => {
                if (data.exito && Array.isArray(data.datos)) {
                    renderizarTablaCampanas(data.datos);
                }
            })
            .catch(err => console.error('Error cargando campañas:', err));
    }

    function renderizarTablaCampanas(campanas) {
        if (!cuerpoTablaCampanas) return;

        if (campanas.length === 0) {
            cuerpoTablaCampanas.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">No hay campañas registradas en esta organización.</td>
                </tr>
            `;
            return;
        }

        let html = '';
        campanas.forEach(c => {
            const badgeEstado = obtenerBadgeEstadoCampana(c.estado);
            const presupuesto = parseFloat(c.presupuesto_asignado_usd || 0).toFixed(2);

            html += `
                <tr>
                    <td><strong>#${c.id}</strong></td>
                    <td><strong>${escaparHtml(c.nombre)}</strong></td>
                    <td>${escaparHtml(c.plantilla_nombre || 'Plantilla #' + c.plantilla_id)}</td>
                    <td><span class="badge bg-light text-dark border">${c.total_destinatarios || 0} clientes</span></td>
                    <td>$${presupuesto}</td>
                    <td class="f-s-12">${escaparHtml(c.creador_nombre || 'Usuario #' + c.creada_por_usuario_id)}</td>
                    <td class="f-s-12">${c.aprobador_nombre ? `<span class="text-success"><i class="fa-solid fa-check-circle me-1"></i>${escaparHtml(c.aprobador_nombre)}</span>` : '<span class="text-muted">Pendiente</span>'}</td>
                    <td>${badgeEstado}</td>
                    <td class="text-end">
                        ${c.estado === 'BORRADOR' ? `
                            <button type="button" class="btn btn-xs btn-success text-white btn-aprobar-campana" data-id="${c.id}" data-creador="${c.creada_por_usuario_id}">
                                <i class="fa-solid fa-signature me-1"></i> Aprobar (SoD)
                            </button>
                        ` : `<span class="badge bg-light text-muted border">Bloqueada</span>`}
                    </td>
                </tr>
            `;
        });

        cuerpoTablaCampanas.innerHTML = html;

        document.querySelectorAll('.btn-aprobar-campana').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                aprobarCampanaModal(id);
            });
        });
    }

    function aprobarCampanaModal(id) {
        Swal.fire({
            title: 'Aprobación de Campaña (SoD)',
            text: `Se verificará que usted NO sea el usuario creador de la Campaña #${id} y que cuente con privilegios de autorización formal.`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Autorizar y Liberar Presupuesto',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(URL_API_CAMPANAS + '/' + id + '/aprobar', {
                    method: 'POST',
                    headers: jsonHeaders
                })
                .then(res => res.json())
                .then(data => {
                    if (data.exito) {
                        mostrarToast(data.mensaje || 'Campaña autorizada formalmente', 'success');
                        cargarCampanas();
                    } else {
                        Swal.fire({
                            title: 'Restricción de Seguridad SoD',
                            text: data.error || 'No se pudo aprobar la campaña',
                            icon: 'error'
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    mostrarToast('Error de red al aprobar campaña', 'error');
                });
            }
        });
    }

    const formNuevaCampana = document.getElementById('formNuevaCampana');
    if (formNuevaCampana) {
        formNuevaCampana.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);

            fetch(URL_API_CAMPANAS, {
                method: 'POST',
                headers: jsonHeaders,
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.exito) {
                    if (modalNuevaCampana) modalNuevaCampana.hide();
                    formNuevaCampana.reset();
                    mostrarToast('Campaña creada en estado BORRADOR', 'success');
                    cargarCampanas();
                } else {
                    mostrarToast(data.error || 'Error al crear campaña', 'error');
                }
            });
        });
    }

    if (btnAbrirModalNuevaCampana) {
        btnAbrirModalNuevaCampana.addEventListener('click', () => {
            if (modalNuevaCampana) modalNuevaCampana.show();
        });
    }

    // =========================================================================
    // 7. CONFIGURACIÓN DEL CANAL Y SIMULADOR SANDBOX
    // =========================================================================
    function cargarConfiguracion() {
        fetch(URL_API_CONFIG, { headers: jsonHeaders })
            .then(res => res.json())
            .then(data => {
                if (data.exito && data.datos) {
                    const c = data.datos;
                    const elProv = document.getElementById('cfgProveedorCodigo');
                    const elModo = document.getElementById('cfgModo');
                    const elTel = document.getElementById('cfgTelefono');
                    const elPres = document.getElementById('cfgPresupuesto');
                    const elToken = document.getElementById('cfgVerifyToken');
                    const elPhone = document.getElementById('cfgPhoneId');
                    const elWaba = document.getElementById('cfgWabaId');

                    if (elProv && c.proveedor_codigo) elProv.value = c.proveedor_codigo;
                    if (elModo && c.modo) elModo.value = c.modo;
                    if (elTel && c.numero_telefono_identificador) elTel.value = c.numero_telefono_identificador;
                    if (elPres && c.presupuesto_mensual_limite_usd) elPres.value = c.presupuesto_mensual_limite_usd;
                    if (elToken && c.webhook_verify_token) elToken.value = c.webhook_verify_token;
                    if (elPhone && c.meta_phone_number_id) elPhone.value = c.meta_phone_number_id;
                    if (elWaba && c.meta_waba_id) elWaba.value = c.meta_waba_id;
                }
            });
    }

    if (formConfiguracionCanal) {
        formConfiguracionCanal.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);

            fetch(URL_API_CONFIG, {
                method: 'POST',
                headers: jsonHeaders,
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.exito) {
                    mostrarToast('Configuración del canal guardada exitosamente', 'success');
                    cargarKpis();
                } else {
                    mostrarToast(data.error || 'Error al guardar configuración', 'error');
                }
            });
        });
    }

    // Simular Mensaje Entrante en Sandbox
    if (formSimularEntrante) {
        formSimularEntrante.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData();
            fd.append('telefono', document.getElementById('simTelefono').value);
            fd.append('nombre', document.getElementById('simNombre').value);
            fd.append('texto', document.getElementById('simTexto').value);

            fetch(URL_API_SIMULADOR, {
                method: 'POST',
                headers: jsonHeaders,
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.exito) {
                    mostrarToast('Mensaje entrante inyectado en Sandbox. Ventana 24h activada.', 'success');
                    cargarConversaciones();
                    cargarHistorialMensajes();
                    if (data.datos && data.datos.conversacion_id) {
                        seleccionarConversacion(data.datos.conversacion_id);
                    }
                } else {
                    mostrarToast(data.error || 'Error en simulador', 'error');
                }
            });
        });
    }

    // Despacho Manual de Outbox Worker
    if (btnEjecutarOutboxWorker) {
        btnEjecutarOutboxWorker.addEventListener('click', function () {
            btnEjecutarOutboxWorker.disabled = true;
            if (outboxWorkerResultado) outboxWorkerResultado.textContent = 'Procesando cola...';

            fetch(URL_API_OUTBOX, {
                method: 'POST',
                headers: jsonHeaders
            })
            .then(res => res.json())
            .then(data => {
                btnEjecutarOutboxWorker.disabled = false;
                if (data.exito) {
                    if (outboxWorkerResultado) outboxWorkerResultado.textContent = data.mensaje;
                    mostrarToast(data.mensaje, 'success');
                    cargarHistorialMensajes();
                    cargarKpis();
                    if (conversacionActivaId) seleccionarConversacion(conversacionActivaId);
                } else {
                    if (outboxWorkerResultado) outboxWorkerResultado.textContent = data.error || 'Fallo al procesar';
                    mostrarToast(data.error || 'Error en outbox', 'error');
                }
            })
            .catch(err => {
                btnEjecutarOutboxWorker.disabled = false;
                console.error(err);
                if (outboxWorkerResultado) outboxWorkerResultado.textContent = 'Error de red';
            });
        });
    }

    // Enviar Mensaje Transaccional Modal
    const btnAbrirModalEnviar = document.getElementById('btnAbrirModalEnviar');
    if (btnAbrirModalEnviar) {
        btnAbrirModalEnviar.addEventListener('click', () => {
            if (modalEnviarMensaje) modalEnviarMensaje.show();
        });
    }

    const formEnviarMensajeTransaccional = document.getElementById('formEnviarMensajeTransaccional');
    if (formEnviarMensajeTransaccional) {
        formEnviarMensajeTransaccional.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);

            fetch(URL_API_MENSAJES + '/enviar', {
                method: 'POST',
                headers: jsonHeaders,
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.exito) {
                    if (modalEnviarMensaje) modalEnviarMensaje.hide();
                    formEnviarMensajeTransaccional.reset();
                    mostrarToast('Mensaje transaccional encolado en Outbox', 'success');
                    cargarHistorialMensajes();
                    cargarKpis();
                    cargarConversaciones();
                } else {
                    mostrarToast(data.error || 'Error al encolar mensaje', 'error');
                }
            });
        });
    }

    // =========================================================================
    // HELPERS DE FORMATEO Y ESTADOS
    // =========================================================================
    function esVentana24hActiva(expiraEn) {
        if (!expiraEn) return false;
        const ahora = new Date();
        const expira = new Date(expiraEn.replace(' ', 'T'));
        return expira > ahora;
    }

    function obtenerBadgeEstadoConversacion(estado) {
        switch (estado) {
            case 'ABIERTA':
                return '<span class="badge bg-success">Abierta</span>';
            case 'EN_ATENCION':
                return '<span class="badge bg-warning text-dark">En Atención</span>';
            case 'CERRADA':
                return '<span class="badge bg-secondary">Cerrada</span>';
            default:
                return `<span class="badge bg-light text-dark">${escaparHtml(estado)}</span>`;
        }
    }

    function obtenerBadgeEstadoMeta(estado) {
        switch (estado) {
            case 'APPROVED':
                return '<span class="badge bg-primary-subtle text-primary border border-primary"><i class="fa-solid fa-check me-1"></i>Válida (Simulación)</span>';
            case 'PAUSED':
                return '<span class="badge bg-secondary-subtle text-secondary border border-secondary"><i class="fa-solid fa-pause me-1"></i>Pausada</span>';
            case 'REJECTED':
                return '<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-xmark me-1"></i>Rechazada</span>';
            case 'PENDING':
                return '<span class="badge bg-warning-subtle text-warning border border-warning"><i class="fa-solid fa-clock me-1"></i>Pendiente</span>';
            default:
                return `<span class="badge bg-light text-dark border">${escaparHtml(estado || 'LOCAL')}</span>`;
        }
    }

    function obtenerBadgeEstadoMensaje(estado) {
        switch (estado) {
            case 'CREADO':
                return '<span class="badge bg-secondary">CREADO</span>';
            case 'ENCOLADO':
                return '<span class="badge bg-primary">ENCOLADO</span>';
            case 'EN_PROCESO':
                return '<span class="badge bg-info text-dark">EN PROCESO</span>';
            case 'REINTENTO_PROGRAMADO':
                return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-rotate me-1"></i>REINTENTO</span>';
            case 'ENVIADO':
                return '<span class="badge bg-info">ENVIADO</span>';
            case 'ENTREGADO':
                return '<span class="badge bg-success">ENTREGADO</span>';
            case 'LEIDO':
                return '<span class="badge bg-success"><i class="fa-solid fa-check-double me-1"></i>LEÍDO</span>';
            case 'FALLIDO':
                return '<span class="badge bg-danger"><i class="fa-solid fa-circle-exclamation me-1"></i>FALLIDO</span>';
            case 'CANCELADO':
                return '<span class="badge bg-dark">CANCELADO</span>';
            default:
                return `<span class="badge bg-light text-dark">${escaparHtml(estado)}</span>`;
        }
    }

    function obtenerBadgeEstadoCampana(estado) {
        switch (estado) {
            case 'BORRADOR':
                return '<span class="badge bg-warning text-dark">BORRADOR</span>';
            case 'APROBADA':
                return '<span class="badge bg-primary">APROBADA</span>';
            case 'PROGRAMADA':
                return '<span class="badge bg-info">PROGRAMADA</span>';
            case 'EN_EJECUCION':
                return '<span class="badge bg-success">EN EJECUCIÓN</span>';
            case 'PAUSADA':
                return '<span class="badge bg-secondary">PAUSADA</span>';
            case 'COMPLETADA':
                return '<span class="badge bg-success"><i class="fa-solid fa-check-double me-1"></i>COMPLETADA</span>';
            case 'CANCELADA':
                return '<span class="badge bg-danger">CANCELADA</span>';
            default:
                return `<span class="badge bg-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    function obtenerIconoEstadoMeta(estado) {
        switch (estado) {
            case 'ENVIADO':
                return '<i class="fa-solid fa-check text-light opacity-75" title="Enviado a Meta"></i>';
            case 'ENTREGADO':
                return '<i class="fa-solid fa-check-double text-light opacity-75" title="Entregado al cliente"></i>';
            case 'LEIDO':
                return '<i class="fa-solid fa-check-double text-info" title="Leído por el cliente"></i>';
            case 'FALLIDO':
                return '<i class="fa-solid fa-circle-exclamation text-danger" title="Error en envío"></i>';
            default:
                return '<i class="fa-regular fa-clock opacity-50" title="En cola"></i>';
        }
    }

    // =========================================================================
    // 8. AUTOMATIZACIONES Y DISPARADORES WHATSAPP (FASE 3.2 - DT-V11-009)
    // =========================================================================
    let cacheAutomatizaciones = [];
    const tbodyAutomatizaciones = document.getElementById('tbodyAutomatizaciones');
    const filtroEventoAutomatizacion = document.getElementById('filtroEventoAutomatizacion');
    const btnNuevaAutomatizacion = document.getElementById('btnNuevaAutomatizacion');
    const modalCrearAutomatizacionEl = document.getElementById('modalCrearAutomatizacion');
    const modalCrearAutomatizacion = modalCrearAutomatizacionEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalCrearAutomatizacionEl) : null;
    const formAutomatizacion = document.getElementById('formAutomatizacion');
    const autPlantillaSelect = document.getElementById('autPlantillaId');
    const modalProbarAutomatizacionEl = document.getElementById('modalProbarAutomatizacion');
    const modalProbarAutomatizacion = modalProbarAutomatizacionEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalProbarAutomatizacionEl) : null;
    const btnEjecutarPruebaPreflight = document.getElementById('btnEjecutarPruebaPreflight');
    const btnCargarPayloadEjemplo = document.getElementById('btnCargarPayloadEjemplo');

    if (filtroEventoAutomatizacion) {
        filtroEventoAutomatizacion.addEventListener('change', function () {
            cargarAutomatizaciones(this.value);
        });
    }

    if (btnNuevaAutomatizacion) {
        btnNuevaAutomatizacion.addEventListener('click', function () {
            abrirModalNuevaAutomatizacion();
        });
    }

    if (formAutomatizacion) {
        formAutomatizacion.addEventListener('submit', guardarAutomatizacion);
    }

    if (btnCargarPayloadEjemplo) {
        btnCargarPayloadEjemplo.addEventListener('click', function () {
            const reglaId = document.getElementById('probarReglaId').value;
            const regla = cacheAutomatizaciones.find(r => r.id == reglaId);
            if (regla) {
                const ejemplo = obtenerPayloadEjemplo(regla.evento_disparador);
                document.getElementById('probarPayloadJson').value = JSON.stringify(ejemplo, null, 2);
            }
        });
    }

    if (btnEjecutarPruebaPreflight) {
        btnEjecutarPruebaPreflight.addEventListener('click', ejecutarPruebaPreflight);
    }

    function cargarAutomatizaciones(eventoFiltro = '') {
        if (!tbodyAutomatizaciones) return;
        tbodyAutomatizaciones.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando reglas de automatización...
                </td>
            </tr>
        `;

        let url = URL_API_AUT;
        if (eventoFiltro) {
            url += '?evento=' + encodeURIComponent(eventoFiltro);
        }

        fetch(url, { headers: jsonHeaders })
            .then(res => res.json())
            .then(data => {
                if (data.exito && Array.isArray(data.reglas)) {
                    cacheAutomatizaciones = data.reglas;
                    renderizarTablaAutomatizaciones(cacheAutomatizaciones);
                } else {
                    tbodyAutomatizaciones.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">Error: ${data.error || 'No se pudieron cargar las reglas'}</td></tr>`;
                }
            })
            .catch(err => {
                console.error('Error cargando automatizaciones:', err);
                tbodyAutomatizaciones.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">Error de conexión al cargar automatizaciones</td></tr>`;
            });
    }

    function renderizarTablaAutomatizaciones(reglas) {
        if (!tbodyAutomatizaciones) return;

        if (reglas.length === 0) {
            tbodyAutomatizaciones.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-robot f-s-32 mb-2 d-block text-secondary opacity-50"></i>
                        No se encontraron reglas de automatización configuradas.
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        reglas.forEach(r => {
            const badgeTipo = r.tipo_comunicacion === 'TRANSACCIONAL'
                ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">TRANSACCIONAL</span>'
                : '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">PROMOCIONAL</span>';

            const badgeEvento = obtenerBadgeEvento(r.evento_disparador);

            const badgeEstado = r.activo
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">ACTIVO</span>'
                : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">INACTIVO</span>';

            const esperaTexto = r.minutos_diferidos > 0
                ? `<span class="badge bg-light text-dark border"><i class="fa-regular fa-clock me-1"></i>+${r.minutos_diferidos} min</span>`
                : '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-bolt me-1"></i>Inmediato</span>';

            html += `
                <tr>
                    <td>
                        <strong class="text-dark d-block">${escaparHtml(r.nombre)}</strong>
                        <code class="f-s-11 text-muted">${escaparHtml(r.codigo)}</code>
                    </td>
                    <td>${badgeEvento}</td>
                    <td>
                        <span class="d-block f-w-600 f-s-13">${escaparHtml(r.plantilla_nombre || 'Plantilla #' + r.plantilla_id)}</span>
                        <div class="mt-1">${badgeTipo}</div>
                    </td>
                    <td>${esperaTexto}</td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border f-s-12">${r.total_disparados}</span>
                        <div class="f-s-10 text-muted mt-1">${r.ultimo_disparo_en ? formatearFechaHora(r.ultimo_disparo_en) : 'Sin disparos'}</div>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input switch-estado-aut" type="checkbox" role="switch" data-id="${r.id}" ${r.activo ? 'checked' : ''}>
                        </div>
                        <div class="mt-1">${badgeEstado}</div>
                    </td>
                    <td class="text-end pe-3">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-info btn-probar-aut" data-id="${r.id}" title="Simulación Pre-flight">
                                <i class="fa-solid fa-vial"></i> Probar
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-editar-aut" data-id="${r.id}" title="Editar Regla">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbodyAutomatizaciones.innerHTML = html;

        // Listeners para switches de estado
        tbodyAutomatizaciones.querySelectorAll('.switch-estado-aut').forEach(sw => {
            sw.addEventListener('change', function () {
                const id = this.getAttribute('data-id');
                const nuevoEstado = this.checked;
                toggleEstadoAutomatizacion(id, nuevoEstado);
            });
        });

        // Listeners para botón Probar
        tbodyAutomatizaciones.querySelectorAll('.btn-probar-aut').forEach(b => {
            b.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                abrirModalProbarAutomatizacion(id);
            });
        });

        // Listeners para botón Editar
        tbodyAutomatizaciones.querySelectorAll('.btn-editar-aut').forEach(b => {
            b.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                abrirModalEditarAutomatizacion(id);
            });
        });
    }

    function obtenerBadgeEvento(evento) {
        switch (evento) {
            case 'reserva.confirmada':
                return '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-ticket me-1"></i>reserva.confirmada</span>';
            case 'reserva.cancelada':
                return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-ban me-1"></i>reserva.cancelada</span>';
            case 'recibo.emitido':
                return '<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fa-solid fa-receipt me-1"></i>recibo.emitido</span>';
            case 'folclore.turno.recordatorio':
                return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fa-solid fa-bell me-1"></i>folclore.turno.recordatorio</span>';
            case 'folclore.turno.actualizado':
                return '<span class="badge bg-dark-subtle text-dark border border-dark-subtle"><i class="fa-solid fa-clock-rotate-left me-1"></i>folclore.turno.actualizado</span>';
            default:
                return `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">${escaparHtml(evento)}</span>`;
        }
    }

    function poblarSelectPlantillasAutomatizacion(plantillaSeleccionadaId = null) {
        if (!autPlantillaSelect) return;
        autPlantillaSelect.innerHTML = '<option value="">Seleccione plantilla oficial...</option>';

        cachePlantillas.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.nombre} (${p.idioma}) - [${p.categoria}]`;
            if (plantillaSeleccionadaId && p.id == plantillaSeleccionadaId) {
                opt.selected = true;
            }
            autPlantillaSelect.appendChild(opt);
        });
    }

    function abrirModalNuevaAutomatizacion() {
        if (!formAutomatizacion || !modalCrearAutomatizacion) return;
        formAutomatizacion.reset();
        document.getElementById('autId').value = '';
        document.getElementById('tituloModalAutomatizacion').innerHTML = '<i class="fa-solid fa-robot text-primary me-2"></i> Nueva Regla de Automatización';
        document.getElementById('autCondicionesJson').value = '';
        document.getElementById('autMapeoParametrosJson').value = JSON.stringify({ "1": "cliente_nombre", "2": "codigo_reserva" }, null, 2);
        document.getElementById('autActivo').checked = true;

        poblarSelectPlantillasAutomatizacion();
        modalCrearAutomatizacion.show();
    }

    function abrirModalEditarAutomatizacion(id) {
        const regla = cacheAutomatizaciones.find(r => r.id == id);
        if (!regla || !modalCrearAutomatizacion) return;

        document.getElementById('autId').value = regla.id;
        document.getElementById('tituloModalAutomatizacion').innerHTML = `<i class="fa-solid fa-pen-to-square text-primary me-2"></i> Editar Regla #${regla.id} (${escaparHtml(regla.codigo)})`;
        document.getElementById('autCodigo').value = regla.codigo;
        document.getElementById('autNombre').value = regla.nombre;
        document.getElementById('autDescripcion').value = regla.descripcion || '';
        document.getElementById('autEvento').value = regla.evento_disparador;
        document.getElementById('autTipoComunicacion').value = regla.tipo_comunicacion;
        document.getElementById('autMinutosDiferidos').value = regla.minutos_diferidos || 0;
        document.getElementById('autCondicionesJson').value = regla.condiciones_json ? JSON.stringify(regla.condiciones_json, null, 2) : '';
        document.getElementById('autMapeoParametrosJson').value = regla.mapeo_parametros_json ? JSON.stringify(regla.mapeo_parametros_json, null, 2) : '';
        document.getElementById('autActivo').checked = !!regla.activo;

        poblarSelectPlantillasAutomatizacion(regla.plantilla_id);
        modalCrearAutomatizacion.show();
    }

    function guardarAutomatizacion(e) {
        e.preventDefault();
        const id = document.getElementById('autId').value;
        const codigo = document.getElementById('autCodigo').value.trim();
        const nombre = document.getElementById('autNombre').value.trim();
        const descripcion = document.getElementById('autDescripcion').value.trim();
        const eventoDisparador = document.getElementById('autEvento').value;
        const plantillaId = parseInt(document.getElementById('autPlantillaId').value, 10);
        const tipoComunicacion = document.getElementById('autTipoComunicacion').value;
        const minutosDiferidos = parseInt(document.getElementById('autMinutosDiferidos').value || '0', 10);
        const condicionesTexto = document.getElementById('autCondicionesJson').value.trim();
        const mapeoTexto = document.getElementById('autMapeoParametrosJson').value.trim();
        const activo = document.getElementById('autActivo').checked;

        let condicionesJson = null;
        if (condicionesTexto) {
            try {
                condicionesJson = JSON.parse(condicionesTexto);
            } catch (err) {
                mostrarToast('El campo Condiciones no es un JSON válido', 'error');
                return;
            }
        }

        let mapeoParametrosJson = {};
        if (mapeoTexto) {
            try {
                mapeoParametrosJson = JSON.parse(mapeoTexto);
            } catch (err) {
                mostrarToast('El campo Mapeo de Parámetros no es un JSON válido', 'error');
                return;
            }
        }

        const payload = {
            codigo,
            nombre,
            descripcion: descripcion || null,
            evento_disparador: eventoDisparador,
            plantilla_id: plantillaId,
            tipo_comunicacion: tipoComunicacion,
            minutos_diferidos: minutosDiferidos,
            condiciones_json: condicionesJson,
            mapeo_parametros_json: mapeoParametrosJson,
            activo
        };

        const esEdicion = !!id;
        const url = esEdicion ? `${URL_API_AUT}/${id}` : URL_API_AUT;
        const method = esEdicion ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: {
                ...jsonHeaders,
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.exito) {
                mostrarToast(data.mensaje || 'Regla guardada con éxito', 'success');
                if (modalCrearAutomatizacion) modalCrearAutomatizacion.hide();
                cargarAutomatizaciones();
            } else {
                mostrarToast(data.error || 'No se pudo guardar la regla', 'error');
            }
        })
        .catch(err => {
            console.error('Error al guardar automatización:', err);
            mostrarToast('Error de conexión al guardar la regla', 'error');
        });
    }

    function toggleEstadoAutomatizacion(id, activo) {
        fetch(`${URL_API_AUT}/${id}/estado`, {
            method: 'POST',
            headers: {
                ...jsonHeaders,
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({ activo })
        })
        .then(res => res.json())
        .then(data => {
            if (data.exito) {
                mostrarToast(data.mensaje, 'success');
                cargarAutomatizaciones();
            } else {
                mostrarToast(data.error || 'Error al cambiar estado', 'error');
                cargarAutomatizaciones();
            }
        })
        .catch(err => {
            console.error('Error al actualizar estado:', err);
            mostrarToast('Error de conexión', 'error');
            cargarAutomatizaciones();
        });
    }

    function abrirModalProbarAutomatizacion(id) {
        const regla = cacheAutomatizaciones.find(r => r.id == id);
        if (!regla || !modalProbarAutomatizacion) return;

        document.getElementById('probarReglaId').value = regla.id;
        document.getElementById('probarReglaNombre').textContent = `${regla.nombre} (${regla.codigo})`;
        document.getElementById('probarReglaEvento').textContent = regla.evento_disparador;
        document.getElementById('probarReglaDescripcion').textContent = regla.descripcion || 'Sin descripción';

        const ejemplo = obtenerPayloadEjemplo(regla.evento_disparador);
        document.getElementById('probarPayloadJson').value = JSON.stringify(ejemplo, null, 2);

        document.getElementById('probarResultadosContainer').classList.add('d-none');
        modalProbarAutomatizacion.show();
    }

    function obtenerPayloadEjemplo(evento) {
        switch (evento) {
            case 'reserva.confirmada':
                return {
                    "destinatario_telefono": "+51951234567",
                    "destinatario_nombre": "Carlos Mamani",
                    "codigo_reserva": "RES-2027-0091",
                    "monto_total": "S/ 350.00",
                    "estado": "CONFIRMADA",
                    "entidad_id": 91
                };
            case 'reserva.cancelada':
                return {
                    "destinatario_telefono": "+51951234567",
                    "destinatario_nombre": "Carlos Mamani",
                    "codigo_reserva": "RES-2027-0091",
                    "motivo": "Expiración de plazo de pago",
                    "estado": "CANCELADA",
                    "entidad_id": 91
                };
            case 'recibo.emitido':
                return {
                    "destinatario_telefono": "+51951234567",
                    "destinatario_nombre": "Carlos Mamani",
                    "numero_recibo": "REC-2026-00045",
                    "monto_pagado": "S/ 200.00",
                    "saldo_pendiente": "S/ 150.00",
                    "entidad_id": 45
                };
            case 'folclore.turno.recordatorio':
                return {
                    "destinatario_telefono": "+51951888999",
                    "destinatario_nombre": "Directiva Morenada Central",
                    "conjunto_nombre": "Morenada Central Puno",
                    "hora_partida": "09:30 AM",
                    "punto_partida": "Arco Deustua",
                    "entidad_id": 12
                };
            case 'folclore.turno.actualizado':
                return {
                    "destinatario_telefono": "+51951888999",
                    "destinatario_nombre": "Directiva Morenada Central",
                    "conjunto_nombre": "Morenada Central Puno",
                    "nuevo_horario": "10:15 AM",
                    "retraso_minutos": 45,
                    "entidad_id": 12
                };
            default:
                return {
                    "destinatario_telefono": "+51951234567",
                    "destinatario_nombre": "Usuario Prueba",
                    "entidad_id": 1
                };
        }
    }

    function ejecutarPruebaPreflight() {
        const reglaId = document.getElementById('probarReglaId').value;
        const payloadTexto = document.getElementById('probarPayloadJson').value.trim();

        let payloadJson = {};
        try {
            payloadJson = JSON.parse(payloadTexto);
        } catch (err) {
            mostrarToast('El payload debe ser un JSON válido', 'error');
            return;
        }

        fetch(URL_API_AUT_PROBAR, {
            method: 'POST',
            headers: {
                ...jsonHeaders,
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({
                regla_id: parseInt(reglaId, 10),
                payload: payloadJson
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.exito && data.resultado) {
                const res = data.resultado;
                const container = document.getElementById('probarResultadosContainer');
                container.classList.remove('d-none');

                const badgeCond = document.getElementById('probarBadgeCondiciones');
                if (res.cumple_condiciones) {
                    badgeCond.className = 'badge bg-success';
                    badgeCond.innerHTML = '<i class="fa-solid fa-check me-1"></i> Cumple Filtros';
                } else {
                    badgeCond.className = 'badge bg-danger';
                    badgeCond.innerHTML = '<i class="fa-solid fa-xmark me-1"></i> No Cumple Filtros';
                }

                document.getElementById('probarIdempotencyKey').textContent = res.idempotency_key_prevista;
                document.getElementById('probarMensajeRenderizado').textContent = res.texto_renderizado;
                document.getElementById('probarParametrosMapeados').textContent = JSON.stringify(res.parametros_extraidos, null, 2);

                mostrarToast('Prueba pre-flight ejecutada', 'success');
            } else {
                mostrarToast(data.error || 'Error al ejecutar prueba', 'error');
            }
        })
        .catch(err => {
            console.error('Error ejecutando prueba preflight:', err);
            mostrarToast('Error de conexión al ejecutar prueba', 'error');
        });
    }

    function formatearHora(fechaStr) {
        if (!fechaStr) return '';
        try {
            const d = new Date(fechaStr.replace(' ', 'T'));
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return fechaStr;
        }
    }

    function formatearFechaHora(fechaStr) {
        if (!fechaStr) return '-';
        try {
            const d = new Date(fechaStr.replace(' ', 'T'));
            return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return fechaStr;
        }
    }

    function escaparHtml(texto) {
        if (typeof texto !== 'string') return '';
        return texto
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function mostrarToast(mensaje, tipo = 'info') {
        if (typeof Swal !== 'undefined' && Swal.mixin) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: tipo,
                title: mensaje
            });
        } else {
            console.log(`[TOAST ${tipo.toUpperCase()}]: ${mensaje}`);
        }
    }
});
