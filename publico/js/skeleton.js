/**
 * ==============================================================================
 * CANDELARIAAPP - GESTOR CENTRALIZADO DE SKELETON LOADERS (skeleton.js)
 * ==============================================================================
 * API oficial:
 * - Skeleton.show(target, tipo, opciones)
 * - Skeleton.hide(target)
 * - Skeleton.error(target, mensaje, opcionesReintento)
 *
 * CERO setTimeout artificial. La animación persiste estrictamente durante el fetch real.
 * ==============================================================================
 */

'use strict';

const Skeleton = {
    _plantillas: {
        stats(opciones = {}) {
            return `
                <div class="skeleton-stats-widget d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1 me-3">
                        <div class="skeleton-shimmer skeleton-line sm w-50"></div>
                        <div class="skeleton-shimmer skeleton-line lg w-75 my-2"></div>
                        <div class="skeleton-shimmer skeleton-line sm w-25"></div>
                    </div>
                    <div class="skeleton-shimmer skeleton-circle stat-icon flex-shrink-0"></div>
                </div>
            `;
        },

        table(opciones = {}) {
            const filas = opciones.filas || 5;
            const columnas = opciones.columnas || 4;
            let filasHtml = '';

            for (let i = 0; i < filas; i++) {
                let celdasHtml = '';
                for (let j = 0; j < columnas; j++) {
                    const ancho = Math.floor(Math.random() * (90 - 45 + 1)) + 45;
                    celdasHtml += `<div class="skeleton-shimmer skeleton-line w-${ancho} flex-fill my-auto"></div>`;
                }
                filasHtml += `<div class="skeleton-table-row">${celdasHtml}</div>`;
            }

            return `
                <div class="skeleton-table-wrapper">
                    <div class="skeleton-table-row border-bottom pb-2 mb-2">
                        <div class="skeleton-shimmer skeleton-line lg w-100"></div>
                    </div>
                    ${filasHtml}
                </div>
            `;
        },

        form(opciones = {}) {
            const campos = opciones.campos || 4;
            let camposHtml = '';

            for (let i = 0; i < campos; i++) {
                camposHtml += `
                    <div class="col-md-6 skeleton-form-group">
                        <div class="skeleton-shimmer skeleton-line sm w-25 mb-2"></div>
                        <div class="skeleton-shimmer skeleton-input"></div>
                    </div>
                `;
            }

            return `
                <div class="row g-3 p-2">
                    ${camposHtml}
                    <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                        <div class="skeleton-shimmer skeleton-input w-25"></div>
                    </div>
                </div>
            `;
        },

        card(opciones = {}) {
            return `
                <div class="skeleton-card-container">
                    <div class="skeleton-shimmer skeleton-line lg w-50 mb-3"></div>
                    <div class="skeleton-shimmer skeleton-line w-100"></div>
                    <div class="skeleton-shimmer skeleton-line w-90"></div>
                    <div class="skeleton-shimmer skeleton-line w-75 mb-3"></div>
                    <div class="skeleton-shimmer skeleton-input w-25"></div>
                </div>
            `;
        },

        list(opciones = {}) {
            const items = opciones.items || 4;
            let html = '';
            for (let i = 0; i < items; i++) {
                html += `
                    <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                        <div class="skeleton-shimmer skeleton-circle" style="width: 36px; height: 36px;"></div>
                        <div class="flex-grow-1">
                            <div class="skeleton-shimmer skeleton-line sm w-50"></div>
                            <div class="skeleton-shimmer skeleton-line sm w-75 mb-0"></div>
                        </div>
                    </div>
                `;
            }
            return `<div class="p-2">${html}</div>`;
        },

        dashboard(opciones = {}) {
            return `
                <div class="row g-3">
                    <div class="col-md-3">${this.stats()}</div>
                    <div class="col-md-3">${this.stats()}</div>
                    <div class="col-md-3">${this.stats()}</div>
                    <div class="col-md-3">${this.stats()}</div>
                    <div class="col-12">${this.table({ filas: 4 })}</div>
                </div>
            `;
        },

        profile(opciones = {}) {
            return `
                <div class="d-flex flex-column align-items-center p-4">
                    <div class="skeleton-shimmer skeleton-circle mb-3" style="width: 80px; height: 80px;"></div>
                    <div class="skeleton-shimmer skeleton-line lg w-50 mb-2"></div>
                    <div class="skeleton-shimmer skeleton-line sm w-25 mb-3"></div>
                    <div class="skeleton-shimmer skeleton-line w-75"></div>
                </div>
            `;
        }
    },

    /**
     * Muestra el Skeleton correspondiente en el contenedor objetivo.
     * @param {string|HTMLElement} target Selector CSS o elemento DOM.
     * @param {string} tipo 'card'|'table'|'form'|'stats'|'dashboard'|'list'|'profile'
     * @param {object} opciones Parámetros específicos de la plantilla.
     */
    show(target, tipo = 'card', opciones = {}) {
        const elemento = typeof target === 'string' ? document.querySelector(target) : target;
        if (!elemento) return;

        elemento.setAttribute('aria-busy', 'true');
        elemento.classList.add('skeleton-loading-container');

        // Si ya tiene contenido y no se ha guardado, guardarlo para posible restauración
        if (!elemento.dataset.skeletonOriginal) {
            elemento.dataset.skeletonOriginal = elemento.innerHTML;
        }

        const generador = this._plantillas[tipo] || this._plantillas.card;
        elemento.innerHTML = generador.call(this, opciones);
    },

    /**
     * Oculta el Skeleton y remueve el estado de carga.
     * @param {string|HTMLElement} target Selector CSS o elemento DOM.
     * @param {string|null} contenidoNuevo Si se pasa HTML, reemplaza el contenido. Si no, quita busy.
     */
    hide(target, contenidoNuevo = null) {
        const elemento = typeof target === 'string' ? document.querySelector(target) : target;
        if (!elemento) return;

        elemento.removeAttribute('aria-busy');
        elemento.classList.remove('skeleton-loading-container');

        if (contenidoNuevo !== null) {
            elemento.innerHTML = contenidoNuevo;
        }
        delete elemento.dataset.skeletonOriginal;
    },

    /**
     * Muestra un estado visual de error comprensible con opción de reintentar.
     */
    error(target, mensaje = 'No fue posible cargar la información.', opcionesReintento = null) {
        const elemento = typeof target === 'string' ? document.querySelector(target) : target;
        if (!elemento) return;

        elemento.removeAttribute('aria-busy');
        elemento.classList.remove('skeleton-loading-container');

        let botonReintentoHtml = '';
        let btnId = '';
        if (opcionesReintento && typeof opcionesReintento.accion === 'function') {
            btnId = 'btn-reintento-' + Math.random().toString(36).substr(2, 9);
            botonReintentoHtml = `
                <button type="button" id="${btnId}" class="btn btn-sm btn-outline-danger mt-2">
                    <i class="fa-solid fa-rotate-right me-1"></i> ${opcionesReintento.texto || 'Reintentar'}
                </button>
            `;
        }

        elemento.innerHTML = `
            <div class="skeleton-error-state">
                <i class="fa-solid fa-triangle-exclamation text-danger f-s-24 mb-2"></i>
                <p class="text-danger f-w-600 mb-1 f-s-14">${mensaje}</p>
                ${botonReintentoHtml}
            </div>
        `;

        if (btnId) {
            const btn = elemento.querySelector('#' + btnId);
            if (btn) btn.onclick = opcionesReintento.accion;
        }
    }
};

window.Skeleton = Skeleton;
