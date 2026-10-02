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

// Inicialización global en la ventana
window.Candelaria = new CandelariaApp();
window.CandelariaUI = CandelariaUI;
window.CandelariaApi = new CandelariaClienteApi();
