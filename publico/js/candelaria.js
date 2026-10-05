/**
 * ==============================================================================
 * CANDELARIAAPP - JAVASCRIPT MODERNO OFICIAL (candelaria.js)
 * ==============================================================================
 * - JavaScript moderno nativo (ES6+)
 * - CERO jQuery para código propio
 * - Tooltips funcionales e interactivos para el sidebar
 * - Cliente Fetch API nativo y asincrónico para consumo de endpoints
 * - Helper CandelariaUI:
 *     * CARGAR CONTENIDO  -> SKELETON
 *     * PROCESAR ACCIÓN   -> BOTÓN DISABLED + SPINNER
 *     * RESULTADO         -> SWEETALERT2
 *     * ACTUALIZACIÓN     -> PARCIAL ASÍNCRONA (SIN RECARGA COMPLETA)
 * ==============================================================================
 */

'use strict';

class CandelariaApp {
    constructor() {
        this.instanciasTooltips = new Map();
        this.inicializar();
    }

    inicializar() {
        this.inicializarTooltipsSidebar();
        this.observarMutacionesMenu();
        this.sincronizarModoTema();
        console.info('[CandelariaAPP] Infraestructura JavaScript y UI/UX inicializadas.');
    }

    /**
     * Inicializa tooltips de Bootstrap 5 en los enlaces del sidebar compacto.
     */
    inicializarTooltipsSidebar(contexto = document) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
            console.warn('[CandelariaAPP] Bootstrap Tooltip no está disponible en este momento.');
            return;
        }

        const enlacesConTooltip = contexto.querySelectorAll(
            '.navbar-menu-list .nav-link[title], .navbar-menu-list .nav-link[data-bs-title]'
        );

        enlacesConTooltip.forEach(enlace => {
            const existente = bootstrap.Tooltip.getInstance(enlace);
            if (existente) {
                existente.dispose();
            }

            const textoTitulo = enlace.getAttribute('title') || enlace.getAttribute('data-bs-title');
            if (!textoTitulo) return;

            const nuevaInstancia = new bootstrap.Tooltip(enlace, {
                title: textoTitulo,
                placement: 'right',
                trigger: 'hover',
                boundary: 'window',
                customClass: 'candelaria-sidebar-tooltip',
                delay: { show: 100, hide: 150 }
            });

            this.instanciasTooltips.set(enlace, nuevaInstancia);
        });
    }

    observarMutacionesMenu() {
        const contenedorLista = document.querySelector('.navbar-menu-list');
        if (!contenedorLista || typeof MutationObserver === 'undefined') return;

        const observador = new MutationObserver(() => {
            this.inicializarTooltipsSidebar(contenedorLista);
        });

        observador.observe(contenedorLista, { childList: true, subtree: true });
    }

    sincronizarModoTema() {
        const iconoTema = document.getElementById('theme-icon');
        if (!iconoTema) return;

        const actualizarIcono = () => {
            const esOscuro = document.body.classList.contains('dark');
            iconoTema.className = esOscuro ? 'fa-solid fa-moon text-secondary' : 'fa-solid fa-sun text-secondary';
        };

        actualizarIcono();

        const observadorTema = new MutationObserver((mutaciones) => {
            for (const mutacion of mutaciones) {
                if (mutacion.type === 'attributes' && mutacion.attributeName === 'class') {
                    actualizarIcono();
                    break;
                }
            }
        });

        observadorTema.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    }
}

/**
 * Gestor oficial de retroalimentación de UI/UX (Botón + Spinner, SweetAlert2).
 */
const CandelariaUI = {
    /**
     * Pone un botón en estado de procesamiento (Disabled + Spinner).
     * @param {HTMLElement|string} boton
     * @param {string} texto
     */
    procesarBoton(boton, texto = 'Guardando...') {
        const el = typeof boton === 'string' ? document.querySelector(boton) : boton;
        if (!el) return;

        el.dataset.htmlOriginal = el.innerHTML;
        el.disabled = true;
        el.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-2"></i> ${texto}`;
    },

    /**
     * Restaura un botón tras completar la acción.
     * @param {HTMLElement|string} boton
     */
    restaurarBoton(boton) {
        const el = typeof boton === 'string' ? document.querySelector(boton) : boton;
        if (!el) return;

        if (el.dataset.htmlOriginal) {
            el.innerHTML = el.dataset.htmlOriginal;
            delete el.dataset.htmlOriginal;
        }
        el.disabled = false;
    },

    /**
     * Muestra alerta de éxito con SweetAlert2.
     */
    notificarExito(mensaje, titulo = '¡Éxito!') {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                icon: 'success',
                title: titulo,
                text: mensaje,
                confirmButtonColor: '#0d6efd',
                confirmButtonText: 'Aceptar'
            });
        } else {
            alert(mensaje);
        }
    },

    /**
     * Muestra alerta de error con SweetAlert2 sin exponer detalles sensibles.
     */
    notificarError(mensaje, titulo = 'Atención') {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                icon: 'error',
                title: titulo,
                text: mensaje,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Entendido'
            });
        } else {
            alert(mensaje);
        }
    },

    /**
     * Diálogo de confirmación SweetAlert2 para acciones sensibles/destructivas.
     */
    confirmarAccion(mensaje, titulo = '¿Confirmar operación?', textoConfirmar = 'Sí, continuar') {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                title: titulo,
                text: mensaje,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: textoConfirmar,
                cancelButtonText: 'Cancelar'
            });
        } else {
            return Promise.resolve({ isConfirmed: confirm(mensaje) });
        }
    }
};

/**
 * Cliente HTTP ligero basado en Fetch API para arquitectura API-First.
 */
class CandelariaClienteApi {
    constructor(urlBase = '') {
        const raiz = window.CANDELARIA_BASE_URL || window.location.origin;
        this.urlBase = urlBase || (raiz.replace(/\/$/, '') + '/api/v1');
    }

    async peticion(endpoint, opciones = {}) {
        const url = `${this.urlBase}/${endpoint.replace(/^\//, '')}`;
        const metodo = (opciones.metodo || 'GET').toUpperCase();

        const encabezados = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            ...(opciones.encabezados || {})
        };

        // Inyectar CSRF token en métodos mutativos
        const metaCsrf = document.querySelector('meta[name="csrf-token"]');
        const tokenCsrf = window.CANDELARIA_CSRF_TOKEN || (metaCsrf ? metaCsrf.getAttribute('content') : '');
        if (tokenCsrf && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(metodo)) {
            encabezados['X-CSRF-Token'] = tokenCsrf;
        }

        // Inyectar contexto explícito de edición por pestaña desde sessionStorage (F2.1B)
        const edicionContextoId = sessionStorage.getItem('candelaria_edicion_trabajo_id');
        if (edicionContextoId && !encabezados['X-Edicion-Id']) {
            encabezados['X-Edicion-Id'] = edicionContextoId;
        }

        const configuracion = {
            method: metodo,
            headers: encabezados,
            ...(opciones.cuerpo !== undefined ? { body: JSON.stringify(opciones.cuerpo) } : {})
        };

        try {
            const respuesta = await fetch(url, configuracion);
            let datos = null;
            try {
                datos = await respuesta.json();
            } catch (jsonErr) {
                datos = { exito: false, mensaje: 'Respuesta no válida del servidor.' };
            }

            if (!respuesta.ok) {
                const err = new Error(datos.mensaje || `Error HTTP ${respuesta.status}`);
                err.status = respuesta.status;
                err.datos = datos;
                throw err;
            }

            return datos;
        } catch (error) {
            console.error(`[CandelariaAPI Error] ${endpoint}:`, error);
            throw error;
        }
    }

    get(endpoint, parametros = {}) {
        const query = new URLSearchParams(parametros).toString();
        const url = query ? `${endpoint}?${query}` : endpoint;
        return this.peticion(url, { metodo: 'GET' });
    }

    post(endpoint, cuerpo) {
        return this.peticion(endpoint, { metodo: 'POST', cuerpo });
    }

    put(endpoint, cuerpo) {
        return this.peticion(endpoint, { metodo: 'PUT', cuerpo });
    }

    patch(endpoint, cuerpo) {
        return this.peticion(endpoint, { metodo: 'PATCH', cuerpo });
    }

    delete(endpoint) {
        return this.peticion(endpoint, { metodo: 'DELETE' });
    }
}

/**
 * Gestor oficial del Contexto de Edición de Trabajo por Pestaña (F2.1B).
 * Utiliza exclusivamente sessionStorage para garantizar aislamiento estricto por pestaña.
 */
const CandelariaContextoEdicion = {
    CLAVE_SESSION_ID: 'candelaria_edicion_trabajo_id',
    CLAVE_SESSION_NOMBRE: 'candelaria_edicion_trabajo_nombre',
    CLAVE_SESSION_CODIGO: 'candelaria_edicion_trabajo_codigo',

    escaparHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[m]);
    },

    obtenerEdicionId() {
        const id = sessionStorage.getItem(this.CLAVE_SESSION_ID);
        return id ? parseInt(id, 10) : null;
    },

    establecerEdicion(id, nombre, codigo) {
        sessionStorage.setItem(this.CLAVE_SESSION_ID, id);
        if (nombre) sessionStorage.setItem(this.CLAVE_SESSION_NOMBRE, nombre);
        if (codigo) sessionStorage.setItem(this.CLAVE_SESSION_CODIGO, codigo);
        this.actualizarUISelector(id, nombre);
        document.dispatchEvent(new CustomEvent('candelaria:edicion-cambiada', {
            detail: { id, nombre, codigo }
        }));
    },

    actualizarUISelector(id, nombre) {
        const spanTexto = document.getElementById('textoEdicionGlobal');
        if (spanTexto && nombre) {
            spanTexto.textContent = nombre;
        }
        const items = document.querySelectorAll('.item-selector-edicion');
        items.forEach(item => {
            if (parseInt(item.dataset.edicionId, 10) === parseInt(id, 10)) {
                item.classList.add('active', 'bg-light-primary');
            } else {
                item.classList.remove('active', 'bg-light-primary');
            }
        });
    },

    async sincronizarContextoInicial() {
        try {
            const res = await window.CandelariaApi.get('contexto/edicion');
            if (!res.exito || !res.datos) return;

            const datos = res.datos;
            const edicionActual = datos.edicion_trabajo;
            const idEnSesion = this.obtenerEdicionId();

            if (!idEnSesion && edicionActual) {
                // Primera carga: la pestaña no tenía selección, se guarda la edición institucional inicial
                this.establecerEdicion(edicionActual.id, edicionActual.nombre, edicionActual.codigo);
            } else if (idEnSesion) {
                const nombreGuardado = sessionStorage.getItem(this.CLAVE_SESSION_NOMBRE);
                this.actualizarUISelector(idEnSesion, nombreGuardado || (edicionActual ? edicionActual.nombre : 'Edición Seleccionada'));
            }

            this.renderizarListaDropdown(datos.ediciones || [], idEnSesion || (edicionActual ? edicionActual.id : null));
        } catch (e) {
            console.warn('[CandelariaContextoEdicion] Sincronización de contexto inicial omitida o fallida:', e);
        }
    },

    renderizarListaDropdown(ediciones, idActivo) {
        const listaUl = document.getElementById('listaEdicionesGlobal');
        if (!listaUl) return;

        if (!ediciones.length) {
            listaUl.innerHTML = '<li><span class="dropdown-item-text text-muted f-s-12">No hay ediciones registradas</span></li>';
            return;
        }

        let html = '<li><h6 class="dropdown-header text-uppercase f-s-11 text-muted">Edición de Trabajo (Esta Pestaña)</h6></li>';
        ediciones.forEach(ed => {
            const esSeleccionada = parseInt(ed.id, 10) === parseInt(idActivo, 10);
            const badgeActual = ed.es_actual ? '<span class="badge bg-success-subtle text-success ms-2 f-s-10">ACTUAL</span>' : '';
            html += `
                <li>
                    <a class="dropdown-item item-selector-edicion d-flex align-items-center justify-content-between py-2 px-3 ${esSeleccionada ? 'active bg-light-primary text-primary f-w-600' : ''}"
                       href="javascript:void(0)"
                       data-edicion-id="${ed.id}"
                       data-edicion-nombre="${this.escaparHtml(ed.nombre)}"
                       data-edicion-codigo="${this.escaparHtml(ed.codigo)}">
                        <div class="d-flex flex-column text-start me-2">
                            <span class="f-s-13">${this.escaparHtml(ed.nombre)}</span>
                            <span class="f-s-11 text-muted">Año ${ed.anio} &bull; ${this.escaparHtml(ed.estado_etiqueta || ed.estado)}</span>
                        </div>
                        ${badgeActual}
                    </a>
                </li>
            `;
        });

        const baseUrl = window.CANDELARIA_BASE_URL ? window.CANDELARIA_BASE_URL.replace(/\/$/, '') : '';
        html += '<li><hr class="dropdown-divider my-1"></li>';
        html += `<li><a class="dropdown-item f-s-12 text-primary" href="${baseUrl}/ediciones"><i class="fa-solid fa-sliders me-1"></i> Administrar Ediciones</a></li>`;

        listaUl.innerHTML = html;

        listaUl.querySelectorAll('.item-selector-edicion').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const id = parseInt(link.dataset.edicionId, 10);
                const nombre = link.dataset.edicionNombre;
                const codigo = link.dataset.edicionCodigo;
                CandelariaContextoEdicion.establecerEdicion(id, nombre, codigo);
            });
        });
    }
};

// Inicialización global en la ventana
window.Candelaria = new CandelariaApp();
window.CandelariaUI = CandelariaUI;
window.CandelariaApi = new CandelariaClienteApi();
window.CandelariaContextoEdicion = CandelariaContextoEdicion;

document.addEventListener('DOMContentLoaded', () => {
    // Sincronizar contexto solo si existe contenedor de selector o sesión activa
    if (document.getElementById('contenedorSelectorEdicion')) {
        CandelariaContextoEdicion.sincronizarContextoInicial();
    }
});
