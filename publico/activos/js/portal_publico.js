/**
 * ==============================================================================
 * CANDELARIAAPP — MOTOR FRONTEND DEL PORTAL PÚBLICO (FASE 2.9C)
 * GESTIÓN DE CARRITO, COTIZADOR BACKEND, CHECKOUT 3 PASOS, TIMER Y CLIENTE
 * ==============================================================================
 */

(function () {
    'use strict';

    window.CandelariaPortal = {
        slug: document.body.dataset.tenantSlug || '',
        edicionId: document.body.dataset.edicionId || '',
        urlBase: document.body.dataset.urlBase || '/',
        carrito: [],
        bloqueoActivo: null,
        timerInterval: null,
        tiempoRestanteSegundos: 900, // 15 minutos default

        init: function () {
            this.cargarCarritoLocal();
            this.vincularEventos();
            this.iniciarTemporizadorSiCorresponde();
        },

        // --- GESTIÓN DE CARRITO & LOCALSTORAGE ---
        cargarCarritoLocal: function () {
            try {
                const data = localStorage.getItem('candelaria_cart_' + this.slug);
                this.carrito = data ? JSON.parse(data) : [];
                this.actualizarInsigniaCarrito();
            } catch (e) {
                this.carrito = [];
            }
        },

        guardarCarritoLocal: function () {
            localStorage.setItem('candelaria_cart_' + this.slug, JSON.stringify(this.carrito));
            this.actualizarInsigniaCarrito();
        },

        actualizarInsigniaCarrito: function () {
            const count = this.carrito.reduce((acc, item) => acc + (parseInt(item.cantidad) || 1), 0);
            const badge = document.getElementById('cart-counter-badge');
            if (badge) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'inline-block' : 'none';
            }
        },

        agregarAlCarrito: function (tipo, referenciaId, nombre, precio, moneda) {
            const index = this.carrito.findIndex(i => i.tipo === tipo && i.referencia_id == referenciaId);
            if (index > -1) {
                this.carrito[index].cantidad += 1;
            } else {
                this.carrito.push({
                    tipo: tipo,
                    referencia_id: parseInt(referenciaId),
                    nombre: nombre,
                    precio: parseFloat(precio),
                    moneda: moneda || 'PEN',
                    cantidad: 1
                });
            }
            this.guardarCarritoLocal();
            this.mostrarNotificacion('¡Agregado!', `"${nombre}" fue añadido al carrito.`, 'success');
        },

        eliminarDelCarrito: function (index) {
            this.carrito.splice(index, 1);
            this.guardarCarritoLocal();
            this.renderizarResumenCarrito();
        },

        // --- COTIZADOR SOBERANO BACKEND ---
        solicitarCotizacionBackend: async function () {
            if (this.carrito.length === 0) return null;

            const lineas = this.carrito.map(item => ({
                tipo: item.tipo,
                referencia_id: item.referencia_id,
                cantidad: item.cantidad
            }));

            try {
                const response = await fetch(`${this.urlBase}api/v1/publico/cotizar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        org_slug: this.slug,
                        lineas: lineas
                    })
                });
                const res = await response.json();
                if (res.exito) {
                    return res.datos;
                } else {
                    throw new Error(res.mensaje || 'Error al cotizar');
                }
            } catch (err) {
                console.error('Error cotizando en backend:', err);
                return null;
            }
        },

        // --- BLOQUEO DE CUPOS CON TTL ---
        iniciarBloqueoCupos: async function (itemId, cantidad, salidaId) {
            try {
                const sesionId = 'sess_' + Math.random().toString(36).substring(2, 15);
                const response = await fetch(`${this.urlBase}api/v1/publico/cupos/bloquear`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        org_slug: this.slug,
                        item_id: itemId,
                        cantidad: cantidad || 1,
                        identificador_sesion: sesionId,
                        salida_id: salidaId || null
                    })
                });
                const data = await response.json();
                if (data.exito) {
                    this.bloqueoActivo = data.datos;
                    sessionStorage.setItem('candelaria_bloqueo_' + this.slug, JSON.stringify(data.datos));
                    return data.datos;
                } else {
                    throw new Error(data.mensaje);
                }
            } catch (e) {
                this.mostrarNotificacion('Cupos Agotados', e.message, 'warning');
                return null;
            }
        },

        // --- TEMPORIZADOR DE CHECKOUT (15 MIN) ---
        iniciarTemporizadorSiCorresponde: function () {
            const timerElem = document.getElementById('checkout-timer');
            if (!timerElem) return;

            let segundos = 900;
            const guardado = sessionStorage.getItem('candelaria_timer_' + this.slug);
            if (guardado) {
                const expira = parseInt(guardado);
                const ahora = Math.floor(Date.now() / 1000);
                segundos = Math.max(0, expira - ahora);
            } else {
                sessionStorage.setItem('candelaria_timer_' + this.slug, (Math.floor(Date.now() / 1000) + 900).toString());
            }

            this.tiempoRestanteSegundos = segundos;

            const actualizarDisplay = () => {
                const min = Math.floor(this.tiempoRestanteSegundos / 60);
                const seg = this.tiempoRestanteSegundos % 60;
                timerElem.textContent = `${min.toString().padStart(2, '0')}:${seg.toString().padStart(2, '0')}`;

                if (this.tiempoRestanteSegundos <= 0) {
                    clearInterval(this.timerInterval);
                    this.mostrarNotificacion('Tiempo Expirado', 'Su tiempo de reserva ha finalizado. Los cupos han sido liberados.', 'error');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2500);
                }
                this.tiempoRestanteSegundos--;
            };

            actualizarDisplay();
            this.timerInterval = setInterval(actualizarDisplay, 1000);
        },

        // --- NOTIFICACIONES SWEETALERT O TOAST ---
        mostrarNotificacion: function (titulo, mensaje, icono) {
            if (window.Swal) {
                Swal.fire({
                    title: titulo,
                    text: mensaje,
                    icon: icono || 'info',
                    confirmButtonColor: '#0c192c',
                    timer: 3500
                });
            } else {
                alert(`${titulo}: ${mensaje}`);
            }
        },

        vincularEventos: function () {
            // Delegación de eventos para botones de compra rápida
            document.addEventListener('click', (e) => {
                const btnAgregar = e.target.closest('.btn-agregar-carrito');
                if (btnAgregar) {
                    const tipo = btnAgregar.dataset.tipo;
                    const id = btnAgregar.dataset.id;
                    const nombre = btnAgregar.dataset.nombre;
                    const precio = btnAgregar.dataset.precio;
                    const moneda = btnAgregar.dataset.moneda;
                    this.agregarAlCarrito(tipo, id, nombre, precio, moneda);
                }
            });
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        window.CandelariaPortal.init();
    });
})();
