/**
 * ==============================================================================
 * CANDELARIAAPP - ADMINISTRACIÓN DE PROGRAMACIÓN FOLCLÓRICA (folclore.js)
 * Fase 3.1B — Directiva DT-V11-004
 * ==============================================================================
 * - Gestión completa e interactiva de:
 *     * Catálogo de Danzas y Conjuntos Históricos
 *     * Bloques y Filiales Territoriales
 *     * Padrón Anual de Agrupaciones Inscritas
 *     * Actividades Folclóricas y Rol de Presentación Oficial con Procedencia FRFCP
 *     * Distinción visual y funcional de versiones BORRADOR/PRELIMINAR vs OFICIAL CONFIRMADO
 *     * Aprobación y oficialización con SweetAlert2
 *     * Vinculación opcional con Salidas de Operación V1
 * ==============================================================================
 */

'use strict';

class ModuloFolclore {
    constructor() {
        this.csrfToken = window.CANDELARIA_CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        this.baseUrl = window.CANDELARIA_BASE_URL || '';
        if (this.baseUrl.endsWith('/')) {
            this.baseUrl = this.baseUrl.slice(0, -1);
        }

        this.edicionActualId = null;
        this.actividadActualId = null;
        this.programacionActualId = null;

        // Cache local
        this.tiposDanza = [];
        this.conjuntos = [];
        this.inscritos = [];
        this.actividades = [];
        this.versiones = [];
        this.turnos = [];
        this.salidas = [];
        this.tableroTurnos = [];
        this.tableroKpis = {};
        this.personalOperativo = [];

        this.instanciasFlatpickr = {};
        this.init();
    }

    async init() {
        this.inicializarSelectoresFecha();
        this.vincularEventosUI();
        await this.cargarEdiciones();
        await this.cargarTiposDanza();
        await this.cargarConjuntos();

        // Activación de pestaña directa por query param (?tab=conjuntos) o hash (#tab-conjuntos)
        const params = new URLSearchParams(window.location.search);
        const tabParam = params.get('tab');
        const hash = window.location.hash ? window.location.hash.replace('#', '').replace('-btn', '') : null;
        const targetId = tabParam ? `tab-${tabParam}` : (hash ? (hash.startsWith('tab-') ? hash : `tab-${hash}`) : null);
        if (targetId) {
            const btn = document.querySelector(`button[data-bs-target="#${targetId}"]`) || document.getElementById(`${targetId}-btn`);
            if (btn && typeof bootstrap !== 'undefined') {
                const tabInstance = bootstrap.Tab.getOrCreateInstance(btn);
                tabInstance.show();
            }
        }

        // Apertura directa de modal por query param (?modal=danza, ?modal=conjunto, etc.)
        const modalParam = params.get('modal');
        if (modalParam) {
            setTimeout(() => {
                if (modalParam === 'danza') this.abrirModalTipoDanza();
                if (modalParam === 'conjunto') this.abrirModalConjunto();
                if (modalParam === 'inscripcion') this.abrirModalInscripcion();
                if (modalParam === 'turno') this.abrirModalTurno();
                if (modalParam === 'control' || modalParam === 'estado') this.abrirModalControlOperativo();
            }, 600);
        }
    }

    obtenerHeaders() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': this.csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    inicializarSelectoresFecha() {
        if (typeof flatpickr !== 'undefined') {
            const configFecha = {
                dateFormat: 'Y-m-d',
                allowInput: true,
                locale: {
                    firstDayOfWeek: 1,
                    weekdays: { shorthand: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'], longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'] },
                    months: { shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'], longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] }
                }
            };

            const configFechaHora = {
                ...configFecha,
                enableTime: true,
                dateFormat: 'Y-m-d H:i:s',
                time_24hr: true
            };

            const configHora = {
                enableTime: true,
                noCalendar: true,
                dateFormat: 'H:i:s',
                time_24hr: true
            };

            document.querySelectorAll('.selector-fecha').forEach(el => {
                flatpickr(el, configFecha);
            });

            document.querySelectorAll('.selector-fecha-hora').forEach(el => {
                flatpickr(el, configFechaHora);
            });

            document.querySelectorAll('.selector-hora').forEach(el => {
                flatpickr(el, configHora);
            });
        }
    }

    vincularEventosUI() {
        // Recargar datos
        document.getElementById('btnRecargarDatos')?.addEventListener('click', () => this.recargarTodo());

        // Cambio de edición de trabajo
        const selEd = document.getElementById('selectorEdicionTrabajo');
        if (selEd) {
            selEd.addEventListener('change', (e) => {
                this.edicionActualId = parseInt(e.target.value, 10);
                this.recargarParaEdicion();
            });
        }

        // Filtro de Actividad en Rol
        const selAct = document.getElementById('selectActividadFiltro');
        if (selAct) {
            selAct.addEventListener('change', (e) => {
                this.actividadActualId = e.target.value ? parseInt(e.target.value, 10) : null;
                this.cargarVersionesDeActividad();
            });
        }

        // Filtro de Versión en Rol
        const selVer = document.getElementById('selectVersionFiltro');
        if (selVer) {
            selVer.addEventListener('change', (e) => {
                this.programacionActualId = e.target.value ? parseInt(e.target.value, 10) : null;
                this.actualizarVistaRolYTurnos();
            });
        }

        // Botones de Modales
        document.getElementById('btnModalTipoDanza')?.addEventListener('click', () => this.abrirModalTipoDanza());
        document.getElementById('btnCrearDanzaSecundario')?.addEventListener('click', () => this.abrirModalTipoDanza());

        document.getElementById('btnModalConjunto')?.addEventListener('click', () => this.abrirModalConjunto());
        document.getElementById('btnCrearConjuntoSecundario')?.addEventListener('click', () => this.abrirModalConjunto());

        document.getElementById('btnModalInscripcion')?.addEventListener('click', () => this.abrirModalInscripcion());
        document.getElementById('btnInscribirSecundario')?.addEventListener('click', () => this.abrirModalInscripcion());

        document.getElementById('btnModalActividad')?.addEventListener('click', () => this.abrirModalActividad());
        document.getElementById('btnModalVersion')?.addEventListener('click', () => this.abrirModalVersion());

        document.getElementById('btnNuevoTurno')?.addEventListener('click', () => this.abrirModalTurno());
        document.getElementById('btnVerHistorialVersiones')?.addEventListener('click', () => this.abrirModalHistorialVersiones());
        document.getElementById('btnOficializarVersion')?.addEventListener('click', () => this.confirmarOficializacionVersion());

        // Envío de Formularios
        document.getElementById('formTipoDanza')?.addEventListener('submit', (e) => this.guardarTipoDanza(e));
        document.getElementById('formConjunto')?.addEventListener('submit', (e) => this.guardarConjunto(e));
        document.getElementById('formBloque')?.addEventListener('submit', (e) => this.guardarBloque(e));
        document.getElementById('formInscripcion')?.addEventListener('submit', (e) => this.guardarInscripcion(e));
        document.getElementById('formActividad')?.addEventListener('submit', (e) => this.guardarActividad(e));
        document.getElementById('formVersion')?.addEventListener('submit', (e) => this.guardarVersion(e));
        document.getElementById('formTurno')?.addEventListener('submit', (e) => this.guardarTurno(e));
        document.getElementById('formVincularSalida')?.addEventListener('submit', (e) => this.guardarVinculoSalida(e));
        document.getElementById('btnDesvincularSalida')?.addEventListener('click', () => this.guardarVinculoSalida(null, true));

        // Eventos del Tablero de Seguimiento Operativo (Fase 3.1C)
        document.getElementById('selectSegActividad')?.addEventListener('change', () => this.cargarTableroSeguimiento());
        document.getElementById('selectSegEstado')?.addEventListener('change', () => this.cargarTableroSeguimiento());
        document.getElementById('selectSegPersonal')?.addEventListener('change', () => this.cargarTableroSeguimiento());
        document.getElementById('btnRefrescarSeguimiento')?.addEventListener('click', () => this.cargarTableroSeguimiento());
        document.getElementById('inputSegBusqueda')?.addEventListener('input', () => this.renderizarTableroSeguimiento());
        document.getElementById('tab-seguimiento-btn')?.addEventListener('shown.bs.tab', () => this.cargarTableroSeguimiento());

        document.getElementById('formActualizarEstadoOperativo')?.addEventListener('submit', (e) => this.guardarEstadoOperativo(e));
        document.getElementById('btnHoraPartidaAhora')?.addEventListener('click', () => {
            const input = document.getElementById('segHoraRealPartida');
            if (input) input.value = this.obtenerHoraLimaActual();
        });
        document.getElementById('btnHoraFinAhora')?.addEventListener('click', () => {
            const input = document.getElementById('segHoraRealFin');
            if (input) input.value = this.obtenerHoraLimaActual();
        });
    }

    async recargarTodo() {
        await this.cargarTiposDanza();
        await this.cargarConjuntos();
        if (this.edicionActualId) {
            await this.recargarParaEdicion();
        }
        this.notificar('Datos actualizados correctamente.', 'success');
    }

    // ==============================================================================
    // CARGADORES DE DATOS
    // ==============================================================================

    async cargarEdiciones() {
        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/ediciones`, { credentials: 'same-origin' });
            const data = await resp.json();
            if (data.exito && Array.isArray(data.datos)) {
                const sel = document.getElementById('selectorEdicionTrabajo');
                if (!sel) return;
                sel.innerHTML = '';

                data.datos.forEach(ed => {
                    const opt = document.createElement('option');
                    opt.value = ed.id;
                    opt.textContent = `${ed.anio} — ${ed.nombre}`;
                    if (ed.es_actual) {
                        opt.selected = true;
                        this.edicionActualId = ed.id;
                    }
                    sel.appendChild(opt);
                });

                if (!this.edicionActualId && data.datos.length > 0) {
                    this.edicionActualId = data.datos[0].id;
                }

                if (this.edicionActualId) {
                    await this.recargarParaEdicion();
                }
            }
        } catch (err) {
            console.error('Error al cargar ediciones:', err);
        }
    }

    async recargarParaEdicion() {
        if (!this.edicionActualId) return;
        await this.cargarInscripciones();
        await this.cargarActividades();
        await this.cargarSalidasOperativas();
        await this.cargarTableroSeguimiento();
    }

    async cargarTiposDanza() {
        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/tipos-danza`, { credentials: 'same-origin' });
            const data = await resp.json();
            if (data.exito && Array.isArray(data.datos)) {
                this.tiposDanza = data.datos;
                this.renderizarTiposDanza();
                this.actualizarSelectsTiposDanza();
                document.getElementById('kpiTotalTiposDanza').textContent = this.tiposDanza.length;
            }
        } catch (err) {
            console.error('Error al cargar tipos de danza:', err);
        }
    }

    async cargarConjuntos() {
        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/conjuntos`, { credentials: 'same-origin' });
            const data = await resp.json();
            if (data.exito && Array.isArray(data.datos)) {
                this.conjuntos = data.datos;
                this.renderizarConjuntos();
                this.actualizarSelectsConjuntos();
                document.getElementById('kpiTotalConjuntos').textContent = this.conjuntos.length;
            }
        } catch (err) {
            console.error('Error al cargar conjuntos:', err);
        }
    }

    async cargarInscripciones() {
        if (!this.edicionActualId) return;
        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/ediciones/${this.edicionActualId}/inscripciones`, { credentials: 'same-origin' });
            const data = await resp.json();
            if (data.exito && Array.isArray(data.datos)) {
                this.inscritos = data.datos;
                this.renderizarInscripciones();
                this.actualizarSelectsInscritos();
                document.getElementById('kpiTotalInscritos').textContent = this.inscritos.length;
            }
        } catch (err) {
            console.error('Error al cargar inscripciones:', err);
        }
    }

    async cargarActividades() {
        if (!this.edicionActualId) return;
        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/ediciones/${this.edicionActualId}/actividades`, { credentials: 'same-origin' });
            const data = await resp.json();
            if (data.exito && Array.isArray(data.datos)) {
                this.actividades = data.datos;
                this.actualizarSelectsActividades();
                if (this.actividades.length > 0) {
                    this.actividadActualId = this.actividades[0].id;
                    const selAct = document.getElementById('selectActividadFiltro');
                    if (selAct) selAct.value = this.actividadActualId;
                    await this.cargarVersionesDeActividad();
                } else {
                    this.actividadActualId = null;
                    this.versiones = [];
                    this.turnos = [];
                    this.actualizarVistaRolYTurnos();
                }
            }
        } catch (err) {
            console.error('Error al cargar actividades:', err);
        }
    }

    async cargarVersionesDeActividad() {
        if (!this.actividadActualId) {
            this.versiones = [];
            this.programacionActualId = null;
            this.actualizarVistaRolYTurnos();
            return;
        }

        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/actividades/${this.actividadActualId}/versiones`, { credentials: 'same-origin' });
            const data = await resp.json();
            if (data.exito && Array.isArray(data.datos)) {
                this.versiones = data.datos;
                const selVer = document.getElementById('selectVersionFiltro');
                if (selVer) {
                    selVer.innerHTML = '';
                    if (this.versiones.length === 0) {
                        selVer.innerHTML = '<option value="">Sin versiones registradas</option>';
                        this.programacionActualId = null;
                    } else {
                        let activaFound = false;
                        this.versiones.forEach(v => {
                            const opt = document.createElement('option');
                            opt.value = v.id;
                            const badge = v.es_version_activa ? ' [VIGENTE]' : '';
                            opt.textContent = `v${v.version_numero}.0 — ${v.etiqueta_version} (${v.estado_confirmacion})${badge}`;
                            if (v.es_version_activa && !activaFound) {
                                opt.selected = true;
                                this.programacionActualId = v.id;
                                activaFound = true;
                            }
                            selVer.appendChild(opt);
                        });

                        if (!activaFound && this.versiones.length > 0) {
                            selVer.selectedIndex = 0;
                            this.programacionActualId = this.versiones[0].id;
                        }
                    }
                }
                await this.actualizarVistaRolYTurnos();
            }
        } catch (err) {
            console.error('Error al cargar versiones de actividad:', err);
        }
    }

    async actualizarVistaRolYTurnos() {
        const banner = document.getElementById('bannerEstadoVersion');
        const btnOficializar = document.getElementById('btnOficializarVersion');
        const kpiEstado = document.getElementById('kpiEstadoRol');

        if (!this.programacionActualId) {
            if (banner) banner.classList.add('d-none');
            if (btnOficializar) btnOficializar.classList.add('d-none');
            if (kpiEstado) kpiEstado.textContent = 'Sin Programación';
            this.turnos = [];
            this.renderizarTurnos();
            return;
        }

        const versionActual = this.versiones.find(v => v.id === this.programacionActualId);
        if (versionActual && banner) {
            banner.classList.remove('d-none', 'alert-warning', 'alert-success', 'alert-danger');

            const esOficial = versionActual.estado_confirmacion === 'OFICIAL_CONFIRMADO';
            banner.classList.add(esOficial ? 'alert-success' : 'alert-warning');

            const txtBadge = document.getElementById('textoBadgeEstadoVersion');
            const txtDetalle = document.getElementById('textoDetalleVersion');
            const badgeAct = document.getElementById('badgeActivaVersion');
            const ico = document.getElementById('iconoEstadoVersion');

            if (txtBadge) txtBadge.textContent = esOficial ? `OFICIAL CONFIRMADO v${versionActual.version_numero}.0` : `PRELIMINAR / BORRADOR v${versionActual.version_numero}.0`;
            if (txtDetalle) txtDetalle.textContent = `Fuente: ${versionActual.fuente_origen || 'No especificada'} | Emisión: ${versionActual.fecha_emision_fuente || 'S/F'}`;
            if (badgeAct) {
                badgeAct.textContent = versionActual.es_version_activa ? 'ACTIVA / VIGENTE' : 'HISTÓRICA INACTIVA';
                badgeAct.className = versionActual.es_version_activa ? 'badge bg-success f-s-12' : 'badge bg-secondary f-s-12';
            }
            if (ico) {
                ico.className = esOficial ? 'fa-solid fa-certificate f-s-20 text-success' : 'fa-solid fa-triangle-exclamation f-s-20 text-warning';
            }

            // Botón de oficialización visible solo si no está aprobada
            if (btnOficializar) {
                if (!esOficial) {
                    btnOficializar.classList.remove('d-none');
                } else {
                    btnOficializar.classList.add('d-none');
                }
            }

            if (kpiEstado) {
                kpiEstado.textContent = esOficial ? `OFICIAL v${versionActual.version_numero}.0` : `PRELIMINAR v${versionActual.version_numero}.0`;
                kpiEstado.className = esOficial ? 'f-w-700 mb-0 mt-1 text-success text-truncate' : 'f-w-700 mb-0 mt-1 text-warning text-truncate';
            }
        }

        // Cargar turnos de esta programación
        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/programaciones/${this.programacionActualId}/turnos`, { credentials: 'same-origin' });
            const data = await resp.json();
            if (data.exito && Array.isArray(data.datos)) {
                this.turnos = data.datos;
                this.renderizarTurnos();
            }
        } catch (err) {
            console.error('Error al cargar turnos:', err);
        }
    }

    async cargarSalidasOperativas() {
        if (!this.edicionActualId) return;
        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/operaciones/salidas?edicion_id=${this.edicionActualId}`, { credentials: 'same-origin' });
            const data = await resp.json();
            if (data.exito && Array.isArray(data.datos)) {
                this.salidas = data.datos;
                this.renderizarSalidasOperativas();
            }
        } catch (err) {
            console.error('Error al cargar salidas operativas:', err);
        }
    }

    // ==============================================================================
    // RENDERIZADO VISUAL CON PLANTILLA ALINA
    // ==============================================================================

    renderizarTiposDanza() {
        const tbody = document.querySelector('#tablaTiposDanza tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (this.tiposDanza.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No hay danzas registradas.</td></tr>';
            return;
        }

        this.tiposDanza.forEach(t => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span class="badge bg-light text-dark font-monospace">${this.escapar(t.codigo)}</span></td>
                <td><span class="f-w-600">${this.escapar(t.nombre)}</span></td>
                <td><span class="badge bg-primary-subtle text-primary">${this.escapar(t.categoria_origen)}</span></td>
                <td class="text-end">
                    <button class="btn btn-outline-secondary btn-xs py-0 px-2 btn-editar-danza" data-id="${t.id}" title="Editar Danza">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('.btn-editar-danza').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                this.abrirModalTipoDanza(id);
            });
        });
    }

    renderizarConjuntos() {
        const tbody = document.querySelector('#tablaConjuntos tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (this.conjuntos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay agrupaciones registradas.</td></tr>';
            return;
        }

        this.conjuntos.forEach(c => {
            const colorPill = c.color_emblematico_hex ? `<span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background-color: ${this.escapar(c.color_emblematico_hex)};"></span>` : '';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span class="badge bg-light text-dark font-monospace">${this.escapar(c.codigo)}</span></td>
                <td>${colorPill}<span class="f-w-600">${this.escapar(c.nombre)}</span></td>
                <td><span class="f-s-12 text-muted">${this.escapar(c.tipo_danza_nombre || 'Danza')}</span></td>
                <td><span class="f-s-12">${this.escapar(c.ciudad_sede || 'Puno')}</span></td>
                <td>
                    <button class="btn btn-outline-dark btn-xs py-0 px-2 btn-gestionar-bloques" data-id="${c.id}" data-nombre="${this.escapar(c.nombre)}">
                        <i class="fa-solid fa-network-wired me-1"></i> Filiales
                    </button>
                </td>
                <td class="text-end">
                    <button class="btn btn-outline-secondary btn-xs py-0 px-2 btn-editar-conjunto" data-id="${c.id}" title="Editar Conjunto">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('.btn-editar-conjunto').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                this.abrirModalConjunto(id);
            });
        });

        tbody.querySelectorAll('.btn-gestionar-bloques').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                const nombre = e.currentTarget.dataset.nombre;
                this.abrirModalBloques(id, nombre);
            });
        });
    }

    renderizarInscripciones() {
        const tbody = document.querySelector('#tablaInscripciones tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (this.inscritos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No hay conjuntos inscritos en esta edición.</td></tr>';
            return;
        }

        this.inscritos.forEach(i => {
            const tr = document.createElement('tr');
            const estadoBadge = i.estado === 'CONFIRMADO' ? 'bg-success' : (i.estado === 'INSCRITO' ? 'bg-warning text-dark' : 'bg-danger');
            tr.innerHTML = `
                <td><span class="font-monospace f-w-600">${this.escapar(i.numero_registro_oficial || 'S/N')}</span></td>
                <td><span class="f-w-600">${this.escapar(i.conjunto_nombre || 'Conjunto #' + i.conjunto_id)}</span></td>
                <td><span class="f-s-13">${this.escapar(i.nombre_delegado_edicion || 'No registrado')}</span></td>
                <td><span class="f-s-12 text-muted">${this.escapar(i.telefono_delegado_edicion || '-')}</span></td>
                <td class="text-center"><span class="badge bg-light text-dark">${i.cantidad_estimada_danzarines || 0}</span></td>
                <td class="text-center"><span class="badge bg-light text-dark">${i.cantidad_bandas || 1}</span></td>
                <td><span class="badge ${estadoBadge}">${this.escapar(i.estado)}</span></td>
                <td class="text-end">
                    <button class="btn btn-outline-secondary btn-xs py-0 px-2 btn-editar-inscripcion" data-id="${i.id}" title="Editar Inscripción">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('.btn-editar-inscripcion').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                this.abrirModalInscripcion(id);
            });
        });
    }

    renderizarTurnos() {
        const tbody = document.querySelector('#tablaTurnos tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (this.turnos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No hay turnos asignados en esta versión del rol.</td></tr>';
            return;
        }

        this.turnos.forEach(t => {
            const tr = document.createElement('tr');
            const salidaPill = t.salida_id
                ? `<span class="badge bg-info-subtle text-info font-monospace"><i class="fa-solid fa-route me-1"></i>${this.escapar(t.salida_correlativo || 'SAL')}</span>`
                : `<span class="f-s-11 text-muted">Sin vincular</span>`;

            tr.innerHTML = `
                <td><span class="badge bg-primary rounded-circle p-2 f-s-12">${t.orden_sorteo}</span></td>
                <td><span class="f-w-600 text-dark">${this.escapar(t.conjunto_nombre || 'Conjunto')}</span></td>
                <td><span class="badge bg-light text-secondary">${this.escapar(t.tipo_danza_nombre || 'Danza')}</span></td>
                <td><span class="font-monospace text-primary f-w-600">${this.escapar(t.hora_estimada_partida)}</span></td>
                <td><span class="font-monospace text-muted f-s-12">${this.escapar(t.hora_estimada_llegada || '-')}</span></td>
                <td><span class="f-s-12">${this.escapar(t.punto_partida_especifico || 'Punto Oficial')}</span></td>
                <td>${salidaPill}</td>
                <td><span class="badge bg-secondary-subtle text-secondary">${this.escapar(t.estado_turno)}</span></td>
                <td class="text-end">
                    <div class="d-inline-flex gap-1">
                        <button class="btn btn-outline-secondary btn-xs py-0 px-2 btn-editar-turno" data-id="${t.id}" title="Editar Horario">
                            <i class="fa-solid fa-clock"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-xs py-0 px-2 btn-eliminar-turno" data-id="${t.id}" title="Eliminar Turno">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('.btn-editar-turno').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                this.abrirModalTurno(id);
            });
        });

        tbody.querySelectorAll('.btn-eliminar-turno').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                this.confirmarEliminarTurno(id);
            });
        });
    }

    renderizarSalidasOperativas() {
        const tbody = document.querySelector('#tablaSalidasOperativas tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (this.salidas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No hay salidas operativas registradas en esta edición.</td></tr>';
            return;
        }

        this.salidas.forEach(s => {
            const tr = document.createElement('tr');
            const vinculo = s.orden_presentacion_id
                ? `<span class="badge bg-success-subtle text-success"><i class="fa-solid fa-check me-1"></i>Turno Sorteo #${s.orden_sorteo || s.orden_presentacion_id}</span>`
                : `<span class="badge bg-light text-muted">Sin turno</span>`;

            tr.innerHTML = `
                <td><span class="font-monospace f-w-600">${this.escapar(s.correlativo)}</span></td>
                <td><span class="f-s-12">${this.escapar(s.fecha_programada || '-')}</span></td>
                <td><span class="font-monospace f-s-12">${this.escapar(s.hora_inicio_programada || '-')}</span></td>
                <td>${vinculo}</td>
                <td><span class="f-s-12">${this.escapar(s.conjunto_nombre || 'N/A')}</span></td>
                <td><span class="badge bg-secondary">${this.escapar(s.estado)}</span></td>
                <td class="text-end">
                    <button class="btn btn-outline-primary btn-xs py-0 px-2 btn-vincular-salida" data-id="${s.id}" data-correlativo="${this.escapar(s.correlativo)}" data-turno="${s.orden_presentacion_id || ''}">
                        <i class="fa-solid fa-link me-1"></i> Vincular
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('.btn-vincular-salida').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id, 10);
                const corr = e.currentTarget.dataset.correlativo;
                const turnoId = e.currentTarget.dataset.turno ? parseInt(e.currentTarget.dataset.turno, 10) : null;
                this.abrirModalVincularSalida(id, corr, turnoId);
            });
        });
    }

    // ==============================================================================
    // LLENADO DE SELECTS
    // ==============================================================================

    actualizarSelectsTiposDanza() {
        const selConj = document.getElementById('conjuntoTipoDanzaId');
        if (!selConj) return;
        selConj.innerHTML = '<option value="">Seleccione tipo de danza...</option>';
        this.tiposDanza.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = `${t.nombre} (${t.categoria_origen})`;
            selConj.appendChild(opt);
        });
    }

    actualizarSelectsConjuntos() {
        const selInsc = document.getElementById('inscripcionConjuntoId');
        if (!selInsc) return;
        selInsc.innerHTML = '<option value="">Seleccione conjunto...</option>';
        this.conjuntos.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = `${c.nombre} (${c.codigo})`;
            selInsc.appendChild(opt);
        });
    }

    actualizarSelectsInscritos() {
        const selTurno = document.getElementById('turnoConjuntoEdicionId');
        if (!selTurno) return;
        selTurno.innerHTML = '<option value="">Seleccione conjunto inscrito...</option>';
        this.inscritos.forEach(i => {
            const opt = document.createElement('option');
            opt.value = i.id;
            opt.textContent = `${i.conjunto_nombre || 'Conjunto #' + i.conjunto_id} [Reg: ${i.numero_registro_oficial || 'S/N'}]`;
            selTurno.appendChild(opt);
        });
    }

    actualizarSelectsActividades() {
        const selFiltro = document.getElementById('selectActividadFiltro');
        const selModalVer = document.getElementById('versionActividadId');
        if (selFiltro) {
            selFiltro.innerHTML = '<option value="">Seleccione una actividad...</option>';
            this.actividades.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = `${a.titulo} (${a.codigo})`;
                selFiltro.appendChild(opt);
            });
        }
        if (selModalVer) {
            selModalVer.innerHTML = '<option value="">Seleccione una actividad...</option>';
            this.actividades.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = `${a.titulo} (${a.codigo})`;
                selModalVer.appendChild(opt);
            });
        }
        const selSeg = document.getElementById('selectSegActividad');
        if (selSeg) {
            selSeg.innerHTML = '<option value="">-- Todas las actividades --</option>';
            this.actividades.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = `${a.titulo} (${a.codigo})`;
                selSeg.appendChild(opt);
            });
        }
    }

    // ==============================================================================
    // GESTIÓN DE MODALES Y ACCIONES CRUD
    // ==============================================================================

    abrirModalTipoDanza(id = null) {
        const form = document.getElementById('formTipoDanza');
        if (!form) return;
        form.reset();
        document.getElementById('tipoDanzaId').value = '';

        if (id) {
            const t = this.tiposDanza.find(item => item.id === id);
            if (t) {
                document.getElementById('tipoDanzaId').value = t.id;
                document.getElementById('tipoDanzaCodigo').value = t.codigo;
                document.getElementById('tipoDanzaCodigo').readOnly = true;
                document.getElementById('tipoDanzaNombre').value = t.nombre;
                document.getElementById('tipoDanzaCategoria').value = t.categoria_origen;
                document.getElementById('tipoDanzaDescripcion').value = t.descripcion || '';
                document.getElementById('modalTipoDanzaTitulo').innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i> Editar Tipo de Danza';
            }
        } else {
            document.getElementById('tipoDanzaCodigo').readOnly = false;
            document.getElementById('modalTipoDanzaTitulo').innerHTML = '<i class="fa-solid fa-feather me-2"></i> Registrar Tipo de Danza';
        }

        const modal = new bootstrap.Modal(document.getElementById('modalTipoDanza'));
        modal.show();
    }

    async guardarTipoDanza(e) {
        e.preventDefault();
        const id = document.getElementById('tipoDanzaId').value;
        const payload = {
            codigo: document.getElementById('tipoDanzaCodigo').value.trim(),
            nombre: document.getElementById('tipoDanzaNombre').value.trim(),
            categoria_origen: document.getElementById('tipoDanzaCategoria').value,
            descripcion: document.getElementById('tipoDanzaDescripcion').value.trim()
        };

        if (!payload.nombre) {
            this.notificar('El nombre de la danza es obligatorio.', 'warning');
            return;
        }

        const url = id ? `${this.baseUrl}/api/v1/folclore/tipos-danza/${id}` : `${this.baseUrl}/api/v1/folclore/tipos-danza`;
        const method = id ? 'PUT' : 'POST';

        try {
            const resp = await fetch(url, {
                method: method,
                headers: this.obtenerHeaders(),
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (data.exito) {
                bootstrap.Modal.getInstance(document.getElementById('modalTipoDanza'))?.hide();
                await this.cargarTiposDanza();
                this.notificar(data.mensaje || 'Danza guardada exitosamente.', 'success');
            } else {
                this.notificar(data.mensaje || 'Error al guardar danza.', 'danger');
            }
        } catch (err) {
            this.notificar('Error en la comunicación con el servidor.', 'danger');
        }
    }

    abrirModalConjunto(id = null) {
        const form = document.getElementById('formConjunto');
        if (!form) return;
        form.reset();
        document.getElementById('conjuntoId').value = '';

        if (id) {
            const c = this.conjuntos.find(item => item.id === id);
            if (c) {
                document.getElementById('conjuntoId').value = c.id;
                document.getElementById('conjuntoCodigo').value = c.codigo;
                document.getElementById('conjuntoCodigo').readOnly = true;
                document.getElementById('conjuntoNombre').value = c.nombre;
                document.getElementById('conjuntoTipoDanzaId').value = c.tipo_danza_id;
                document.getElementById('conjuntoSigla').value = c.sigla || '';
                document.getElementById('conjuntoFechaFundacion').value = c.fecha_fundacion || '';
                document.getElementById('conjuntoCiudadSede').value = c.ciudad_sede || 'Puno';
                document.getElementById('conjuntoColorHex').value = c.color_emblematico_hex || '#B22222';
                document.getElementById('conjuntoPresidente').value = c.nombre_presidente || '';
                document.getElementById('conjuntoTelefono').value = c.telefono_contacto || '';
                document.getElementById('conjuntoResena').value = c.resena_historica || '';
                document.getElementById('modalConjuntoTitulo').innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i> Editar Conjunto Folclórico';
            }
        } else {
            document.getElementById('conjuntoCodigo').readOnly = false;
            document.getElementById('modalConjuntoTitulo').innerHTML = '<i class="fa-solid fa-people-group me-2"></i> Registrar Conjunto Folclórico';
        }

        const modal = new bootstrap.Modal(document.getElementById('modalConjunto'));
        modal.show();
    }

    async guardarConjunto(e) {
        e.preventDefault();
        const id = document.getElementById('conjuntoId').value;
        const payload = {
            tipo_danza_id: parseInt(document.getElementById('conjuntoTipoDanzaId').value, 10),
            codigo: document.getElementById('conjuntoCodigo').value.trim(),
            nombre: document.getElementById('conjuntoNombre').value.trim(),
            sigla: document.getElementById('conjuntoSigla').value.trim(),
            fecha_fundacion: document.getElementById('conjuntoFechaFundacion').value,
            ciudad_sede: document.getElementById('conjuntoCiudadSede').value.trim(),
            color_emblematico_hex: document.getElementById('conjuntoColorHex').value,
            nombre_presidente: document.getElementById('conjuntoPresidente').value.trim(),
            telefono_contacto: document.getElementById('conjuntoTelefono').value.trim(),
            resena_historica: document.getElementById('conjuntoResena').value.trim()
        };

        if (!payload.nombre || !payload.tipo_danza_id) {
            this.notificar('El nombre y el tipo de danza son obligatorios.', 'warning');
            return;
        }

        const url = id ? `${this.baseUrl}/api/v1/folclore/conjuntos/${id}` : `${this.baseUrl}/api/v1/folclore/conjuntos`;
        const method = id ? 'PUT' : 'POST';

        try {
            const resp = await fetch(url, {
                method: method,
                headers: this.obtenerHeaders(),
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (data.exito) {
                bootstrap.Modal.getInstance(document.getElementById('modalConjunto'))?.hide();
                await this.cargarConjuntos();
                this.notificar(data.mensaje || 'Conjunto guardado exitosamente.', 'success');
            } else {
                this.notificar(data.mensaje || 'Error al guardar conjunto.', 'danger');
            }
        } catch (err) {
            this.notificar('Error en la comunicación con el servidor.', 'danger');
        }
    }

    async abrirModalBloques(conjuntoId, conjuntoNombre) {
        document.getElementById('bloqueModalConjuntoId').value = conjuntoId;
        document.getElementById('modalBloquesTitulo').innerHTML = `<i class="fa-solid fa-network-wired me-2"></i> Bloques de ${this.escapar(conjuntoNombre)}`;
        document.getElementById('formBloque').reset();
        document.getElementById('bloqueId').value = '';

        await this.cargarBloquesDeConjunto(conjuntoId);

        const modal = new bootstrap.Modal(document.getElementById('modalBloquesConjunto'));
        modal.show();
    }

    async cargarBloquesDeConjunto(conjuntoId) {
        const tbody = document.querySelector('#tablaBloquesDetalle tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-2"><span class="spinner-border spinner-border-sm"></span> Cargando...</td></tr>';

        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/conjuntos/${conjuntoId}/bloques`, { credentials: 'same-origin' });
            const data = await resp.json();
            tbody.innerHTML = '';
            if (data.exito && Array.isArray(data.datos)) {
                if (data.datos.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No hay bloques o filiales registradas.</td></tr>';
                    return;
                }
                data.datos.forEach(b => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><span class="badge bg-light text-dark font-monospace">${this.escapar(b.codigo)}</span></td>
                        <td><span class="f-w-600">${this.escapar(b.nombre)}</span></td>
                        <td><span class="f-s-12">${this.escapar(b.responsable_nombre || '-')}</span></td>
                        <td><span class="f-s-12">${this.escapar(b.telefono_contacto || '-')}</span></td>
                        <td class="text-end">
                            <button class="btn btn-outline-secondary btn-xs py-0 px-2 btn-editar-bloque" data-id="${b.id}" data-nombre="${this.escapar(b.nombre)}" data-resp="${this.escapar(b.responsable_nombre || '')}" data-tel="${this.escapar(b.telefono_contacto || '')}">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                tbody.querySelectorAll('.btn-editar-bloque').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const d = e.currentTarget.dataset;
                        document.getElementById('bloqueId').value = d.id;
                        document.getElementById('bloqueNombre').value = d.nombre;
                        document.getElementById('bloqueResponsable').value = d.resp;
                        document.getElementById('bloqueTelefono').value = d.tel;
                        document.getElementById('bloqueCodigo').readOnly = true;
                        document.getElementById('bloqueSubtituloForm').textContent = 'Editar Bloque / Filial';
                    });
                });
            }
        } catch (err) {
            console.error('Error al cargar bloques:', err);
        }
    }

    async guardarBloque(e) {
        e.preventDefault();
        const conjuntoId = document.getElementById('bloqueModalConjuntoId').value;
        const id = document.getElementById('bloqueId').value;
        const payload = {
            codigo: document.getElementById('bloqueCodigo').value.trim(),
            nombre: document.getElementById('bloqueNombre').value.trim(),
            responsable_nombre: document.getElementById('bloqueResponsable').value.trim(),
            telefono_contacto: document.getElementById('bloqueTelefono').value.trim()
        };

        if (!payload.nombre) {
            this.notificar('El nombre del bloque es obligatorio.', 'warning');
            return;
        }

        const url = id ? `${this.baseUrl}/api/v1/folclore/bloques/${id}` : `${this.baseUrl}/api/v1/folclore/conjuntos/${conjuntoId}/bloques`;
        const method = id ? 'PUT' : 'POST';

        try {
            const resp = await fetch(url, {
                method: method,
                headers: this.obtenerHeaders(),
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (data.exito) {
                document.getElementById('formBloque').reset();
                document.getElementById('bloqueId').value = '';
                document.getElementById('bloqueCodigo').readOnly = false;
                document.getElementById('bloqueSubtituloForm').textContent = 'Añadir Nuevo Bloque / Filial';
                await this.cargarBloquesDeConjunto(conjuntoId);
                this.notificar(data.mensaje || 'Bloque guardado exitosamente.', 'success');
            } else {
                this.notificar(data.mensaje || 'Error al guardar bloque.', 'danger');
            }
        } catch (err) {
            this.notificar('Error en la comunicación con el servidor.', 'danger');
        }
    }

    abrirModalInscripcion(id = null) {
        const form = document.getElementById('formInscripcion');
        if (!form) return;
        form.reset();
        document.getElementById('inscripcionId').value = '';

        if (id) {
            const ins = this.inscritos.find(item => item.id === id);
            if (ins) {
                document.getElementById('inscripcionId').value = ins.id;
                document.getElementById('inscripcionConjuntoId').value = ins.conjunto_id;
                document.getElementById('inscripcionConjuntoId').disabled = true;
                document.getElementById('inscripcionNumeroRegistro').value = ins.numero_registro_oficial || '';
                document.getElementById('inscripcionEstado').value = ins.estado;
                document.getElementById('inscripcionDelegado').value = ins.nombre_delegado_edicion || '';
                document.getElementById('inscripcionTelefonoDelegado').value = ins.telefono_delegado_edicion || '';
                document.getElementById('inscripcionDanzarines').value = ins.cantidad_estimada_danzarines || 0;
                document.getElementById('inscripcionBandas').value = ins.cantidad_bandas || 1;
                document.getElementById('inscripcionObservaciones').value = ins.observaciones || '';
                document.getElementById('modalInscripcionTitulo').innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i> Editar Inscripción en Edición';
            }
        } else {
            document.getElementById('inscripcionConjuntoId').disabled = false;
            document.getElementById('modalInscripcionTitulo').innerHTML = '<i class="fa-solid fa-clipboard-check me-2"></i> Inscribir Agrupación en Edición';
        }

        const modal = new bootstrap.Modal(document.getElementById('modalInscripcion'));
        modal.show();
    }

    async guardarInscripcion(e) {
        e.preventDefault();
        const id = document.getElementById('inscripcionId').value;
        const payload = {
            conjunto_id: parseInt(document.getElementById('inscripcionConjuntoId').value, 10),
            numero_registro_oficial: document.getElementById('inscripcionNumeroRegistro').value.trim(),
            estado: document.getElementById('inscripcionEstado').value,
            nombre_delegado_edicion: document.getElementById('inscripcionDelegado').value.trim(),
            telefono_delegado_edicion: document.getElementById('inscripcionTelefonoDelegado').value.trim(),
            cantidad_estimada_danzarines: parseInt(document.getElementById('inscripcionDanzarines').value, 10) || 0,
            cantidad_bandas: parseInt(document.getElementById('inscripcionBandas').value, 10) || 1,
            observaciones: document.getElementById('inscripcionObservaciones').value.trim()
        };

        const url = id ? `${this.baseUrl}/api/v1/folclore/inscripciones/${id}` : `${this.baseUrl}/api/v1/folclore/ediciones/${this.edicionActualId}/inscripciones`;
        const method = id ? 'PUT' : 'POST';

        try {
            const resp = await fetch(url, {
                method: method,
                headers: this.obtenerHeaders(),
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (data.exito) {
                bootstrap.Modal.getInstance(document.getElementById('modalInscripcion'))?.hide();
                await this.cargarInscripciones();
                this.notificar(data.mensaje || 'Inscripción guardada exitosamente.', 'success');
            } else {
                this.notificar(data.mensaje || 'Error al guardar inscripción.', 'danger');
            }
        } catch (err) {
            this.notificar('Error en la comunicación con el servidor.', 'danger');
        }
    }

    abrirModalActividad() {
        document.getElementById('formActividad')?.reset();
        const modal = new bootstrap.Modal(document.getElementById('modalActividad'));
        modal.show();
    }

    async guardarActividad(e) {
        e.preventDefault();
        const payload = {
            codigo: document.getElementById('actividadCodigo').value.trim(),
            titulo: document.getElementById('actividadTitulo').value.trim(),
            fecha_inicio: document.getElementById('actividadInicio').value,
            fecha_fin: document.getElementById('actividadFin').value,
            lugar_principal: document.getElementById('actividadLugar').value.trim()
        };

        if (!payload.codigo || !payload.titulo || !payload.fecha_inicio) {
            this.notificar('Todos los campos marcados con asterisco son obligatorios.', 'warning');
            return;
        }

        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/ediciones/${this.edicionActualId}/actividades`, {
                method: 'POST',
                headers: this.obtenerHeaders(),
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (data.exito) {
                bootstrap.Modal.getInstance(document.getElementById('modalActividad'))?.hide();
                await this.cargarActividades();
                this.notificar('Actividad folclórica registrada exitosamente.', 'success');
            } else {
                this.notificar(data.mensaje || 'Error al registrar actividad.', 'danger');
            }
        } catch (err) {
            this.notificar('Error en la comunicación con el servidor.', 'danger');
        }
    }

    abrirModalVersion() {
        document.getElementById('formVersion')?.reset();
        const selAct = document.getElementById('versionActividadId');
        if (selAct && this.actividadActualId) {
            selAct.value = this.actividadActualId;
        }
        document.getElementById('versionFechaFuente').value = new Date().toISOString().slice(0, 10);

        const modal = new bootstrap.Modal(document.getElementById('modalVersion'));
        modal.show();
    }

    async guardarVersion(e) {
        e.preventDefault();
        const actividadId = document.getElementById('versionActividadId').value;
        const payload = {
            edicion_id: this.edicionActualId,
            fuente_origen: document.getElementById('versionFuente').value.trim(),
            fecha_emision_fuente: document.getElementById('versionFechaFuente').value,
            etiqueta_version: document.getElementById('versionEtiqueta').value.trim(),
            estado_confirmacion: document.getElementById('versionEstado').value,
            documento_referencia_url: document.getElementById('versionDocUrl').value.trim(),
            notas_version: document.getElementById('versionNotas').value.trim()
        };

        if (!actividadId || !payload.fuente_origen || !payload.fecha_emision_fuente) {
            this.notificar('La actividad, fuente y fecha de emisión son obligatorios.', 'warning');
            return;
        }

        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/actividades/${actividadId}/versiones`, {
                method: 'POST',
                headers: this.obtenerHeaders(),
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (data.exito) {
                bootstrap.Modal.getInstance(document.getElementById('modalVersion'))?.hide();
                await this.cargarVersionesDeActividad();
                this.notificar(data.mensaje || 'Versión publicada exitosamente.', 'success');
            } else {
                this.notificar(data.mensaje || 'Error al publicar versión.', 'danger');
            }
        } catch (err) {
            this.notificar('Error en la comunicación con el servidor.', 'danger');
        }
    }

    async confirmarOficializacionVersion() {
        if (!this.programacionActualId) return;

        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: '¿Aprobar y Oficializar Rol?',
                text: 'Esta acción activará formalmente la versión seleccionada como el rol de presentación oficial vigente para esta festividad y archivará las versiones previas.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fa-solid fa-stamp me-1"></i> Sí, Oficializar',
                cancelButtonText: 'Cancelar'
            });

            if (!result.isConfirmed) return;
        }

        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/programaciones/${this.programacionActualId}/oficializar`, {
                method: 'POST',
                headers: this.obtenerHeaders(),
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (data.exito) {
                await this.cargarVersionesDeActividad();
                this.notificar('La programación ha sido aprobada y oficializada como vigente.', 'success');
            } else {
                this.notificar(data.mensaje || 'Error al oficializar versión.', 'danger');
            }
        } catch (err) {
            this.notificar('Error en la comunicación con el servidor.', 'danger');
        }
    }

    abrirModalTurno(id = null) {
        if (!this.programacionActualId) {
            this.notificar('Debe seleccionar o publicar una versión de programación oficial antes de asignar turnos.', 'warning');
            return;
        }

        const form = document.getElementById('formTurno');
        if (!form) return;
        form.reset();
        document.getElementById('turnoId').value = '';

        if (id) {
            const t = this.turnos.find(item => item.id === id);
            if (t) {
                document.getElementById('turnoId').value = t.id;
                document.getElementById('turnoConjuntoEdicionId').value = t.conjunto_edicion_id;
                document.getElementById('turnoConjuntoEdicionId').disabled = true;
                document.getElementById('turnoOrdenSorteo').value = t.orden_sorteo;
                document.getElementById('turnoHoraPartida').value = t.hora_estimada_partida;
                document.getElementById('turnoHoraLlegada').value = t.hora_estimada_llegada || '';
                document.getElementById('turnoPuntoPartida').value = t.punto_partida_especifico || '';
                document.getElementById('turnoEstado').value = t.estado_turno;
                document.getElementById('turnoObservaciones').value = t.observaciones || '';
                document.getElementById('modalTurnoTitulo').innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i> Editar Turno de Presentación';
            }
        } else {
            document.getElementById('turnoConjuntoEdicionId').disabled = false;
            document.getElementById('turnoOrdenSorteo').value = this.turnos.length + 1;
            document.getElementById('modalTurnoTitulo').innerHTML = '<i class="fa-solid fa-clock me-2"></i> Asignar Turno de Presentación';
        }

        const modal = new bootstrap.Modal(document.getElementById('modalTurno'));
        modal.show();
    }

    async guardarTurno(e) {
        e.preventDefault();
        const id = document.getElementById('turnoId').value;
        const payload = {
            conjunto_edicion_id: parseInt(document.getElementById('turnoConjuntoEdicionId').value, 10),
            orden_sorteo: parseInt(document.getElementById('turnoOrdenSorteo').value, 10),
            hora_estimada_partida: document.getElementById('turnoHoraPartida').value.trim(),
            hora_estimada_llegada: document.getElementById('turnoHoraLlegada').value.trim() || null,
            punto_partida_especifico: document.getElementById('turnoPuntoPartida').value.trim() || null,
            estado_turno: document.getElementById('turnoEstado').value,
            observaciones: document.getElementById('turnoObservaciones').value.trim() || null
        };

        if (!payload.hora_estimada_partida || isNaN(payload.orden_sorteo)) {
            this.notificar('El orden de sorteo y la hora de partida son obligatorios.', 'warning');
            return;
        }

        const url = id ? `${this.baseUrl}/api/v1/folclore/turnos/${id}` : `${this.baseUrl}/api/v1/folclore/programaciones/${this.programacionActualId}/turnos`;
        const method = id ? 'PUT' : 'POST';

        try {
            const resp = await fetch(url, {
                method: method,
                headers: this.obtenerHeaders(),
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (data.exito) {
                bootstrap.Modal.getInstance(document.getElementById('modalTurno'))?.hide();
                await this.actualizarVistaRolYTurnos();
                this.notificar(data.mensaje || 'Turno guardado exitosamente.', 'success');
            } else {
                this.notificar(data.mensaje || 'Error al guardar turno.', 'danger');
            }
        } catch (err) {
            this.notificar('Error en la comunicación con el servidor.', 'danger');
        }
    }

    async confirmarEliminarTurno(id) {
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: '¿Eliminar Turno de Presentación?',
                text: 'El conjunto será retirado de este turno de sorteo en el rol oficial.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (!result.isConfirmed) return;
        }

        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/turnos/${id}`, {
                method: 'DELETE',
                headers: this.obtenerHeaders(),
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (data.exito) {
                await this.actualizarVistaRolYTurnos();
                this.notificar('Turno eliminado exitosamente.', 'success');
            } else {
                this.notificar(data.mensaje || 'Error al eliminar turno.', 'danger');
            }
        } catch (err) {
            this.notificar('Error en la comunicación con el servidor.', 'danger');
        }
    }

    abrirModalHistorialVersiones() {
        const tbody = document.querySelector('#tablaHistorialVersiones tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (this.versiones.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay versiones registradas.</td></tr>';
        } else {
            this.versiones.forEach(v => {
                const tr = document.createElement('tr');
                const esActiva = v.es_version_activa;
                tr.innerHTML = `
                    <td><span class="f-w-700">v${v.version_numero}.0</span> — <span class="f-s-12">${this.escapar(v.etiqueta_version)}</span></td>
                    <td><span class="f-s-12 text-muted">${this.escapar(v.fuente_origen || '-')}</span></td>
                    <td><span class="f-s-12">${this.escapar(v.fecha_emision_fuente || '-')}</span></td>
                    <td><span class="badge ${v.estado_confirmacion === 'OFICIAL_CONFIRMADO' ? 'bg-success' : 'bg-warning text-dark'}">${this.escapar(v.estado_confirmacion)}</span></td>
                    <td><span class="badge ${esActiva ? 'bg-success' : 'bg-secondary'}">${esActiva ? 'VIGENTE' : 'INACTIVA'}</span></td>
                    <td class="text-end">
                        <button class="btn btn-outline-primary btn-xs py-0 px-2 btn-ver-version" data-id="${v.id}">
                            <i class="fa-solid fa-eye me-1"></i> Ver Rol
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('.btn-ver-version').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.currentTarget.dataset.id, 10);
                    this.programacionActualId = id;
                    const selVer = document.getElementById('selectVersionFiltro');
                    if (selVer) selVer.value = id;
                    bootstrap.Modal.getInstance(document.getElementById('modalHistorialVersiones'))?.hide();
                    this.actualizarVistaRolYTurnos();
                });
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('modalHistorialVersiones'));
        modal.show();
    }

    abrirModalVincularSalida(salidaId, correlativo, turnoIdActual = null) {
        document.getElementById('vincularSalidaId').value = salidaId;
        document.getElementById('vincularSalidaTextoInfo').textContent = `Asignar salida de campo ${correlativo} a un turno folclórico:`;

        const sel = document.getElementById('vincularTurnoId');
        if (sel) {
            sel.innerHTML = '<option value="">Seleccione turno folclórico...</option>';
            this.turnos.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = `Turno #${t.orden_sorteo}: ${t.conjunto_nombre} (${t.hora_estimada_partida})`;
                if (turnoIdActual && t.id === turnoIdActual) {
                    opt.selected = true;
                }
                sel.appendChild(opt);
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('modalVincularSalida'));
        modal.show();
    }

    async guardarVinculoSalida(e = null, desvincular = false) {
        if (e) e.preventDefault();
        const salidaId = document.getElementById('vincularSalidaId').value;
        const turnoId = desvincular ? 0 : parseInt(document.getElementById('vincularTurnoId').value, 10);

        if (!desvincular && !turnoId) {
            this.notificar('Seleccione un turno de presentación válido.', 'warning');
            return;
        }

        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/turnos/${turnoId || 1}/vincular-salida`, {
                method: 'POST',
                headers: this.obtenerHeaders(),
                body: JSON.stringify({
                    salida_id: parseInt(salidaId, 10),
                    desvincular: desvincular
                }),
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (data.exito) {
                bootstrap.Modal.getInstance(document.getElementById('modalVincularSalida'))?.hide();
                await this.cargarSalidasOperativas();
                await this.actualizarVistaRolYTurnos();
                this.notificar(data.mensaje || 'Vínculo actualizado.', 'success');
            } else {
                this.notificar(data.mensaje || 'Error al vincular salida.', 'danger');
            }
        } catch (err) {
            this.notificar('Error en la comunicación con el servidor.', 'danger');
        }
    }

    // ==============================================================================
    // SEGUIMIENTO OPERATIVO Y COBERTURA AUDIOVISUAL (FASE 3.1C - DT-V11-008)
    // ==============================================================================

    async cargarTableroSeguimiento() {
        if (!this.edicionActualId) return;

        const selAct = document.getElementById('selectSegActividad')?.value;
        const selEst = document.getElementById('selectSegEstado')?.value;
        const selPers = document.getElementById('selectSegPersonal')?.value;

        let url = `${this.baseUrl}/api/v1/folclore/seguimiento/tablero?edicion_id=${this.edicionActualId}`;
        if (selAct) url += `&actividad_id=${encodeURIComponent(selAct)}`;
        if (selEst) url += `&estado=${encodeURIComponent(selEst)}`;
        if (selPers) url += `&persona_id=${encodeURIComponent(selPers)}`;

        try {
            const resp = await fetch(url, { credentials: 'same-origin', headers: this.obtenerHeaders() });
            const data = await resp.json();

            if (data.exito && data.datos) {
                this.tableroTurnos = data.datos.turnos || [];
                this.tableroKpis = data.datos.kpis || {};

                // Actualizar contadores KPI
                const kpis = this.tableroKpis;
                const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
                setTxt('kpiSegTotal', kpis.total_turnos || 0);
                setTxt('kpiSegPendientes', kpis.pendientes || 0);
                setTxt('kpiSegEnCurso', kpis.en_curso || 0);
                setTxt('kpiSegFinalizados', kpis.finalizados || 0);
                setTxt('kpiSegRetrasados', kpis.con_retraso || 0);
                setTxt('kpiSegSinCobertura', kpis.sin_cobertura || 0);

                this.actualizarSelectorPersonalSeguimiento();
                this.renderizarTableroSeguimiento();
            }
        } catch (err) {
            console.error('Error al cargar datos del tablero de seguimiento:', err);
        }
    }

    actualizarSelectorPersonalSeguimiento() {
        const sel = document.getElementById('selectSegPersonal');
        if (!sel) return;

        const personalMap = new Map();
        this.tableroTurnos.forEach(t => {
            (t.salidas_cobertura || []).forEach(s => {
                (s.personal || []).forEach(p => {
                    if (p.persona_id && !personalMap.has(p.persona_id)) {
                        personalMap.set(p.persona_id, `${p.nombre_completo} (${p.rol_operativo})`);
                    }
                });
            });
        });

        const valorActual = sel.value;
        sel.innerHTML = '<option value="">-- Todo el personal --</option>';
        personalMap.forEach((nombre, id) => {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = nombre;
            if (String(id) === String(valorActual)) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    renderizarTableroSeguimiento() {
        const tbody = document.querySelector('#tablaTableroSeguimiento tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        const filtroTexto = (document.getElementById('inputSegBusqueda')?.value || '').toLowerCase().trim();

        const turnosFiltrados = this.tableroTurnos.filter(t => {
            if (!filtroTexto) return true;
            const conjunto = (t.conjunto_nombre || '').toLowerCase();
            const danza = (t.tipo_danza_nombre || '').toLowerCase();
            const orden = String(t.orden_sorteo);
            return conjunto.includes(filtroTexto) || danza.includes(filtroTexto) || orden.includes(filtroTexto);
        });

        if (turnosFiltrados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="fa-solid fa-masks-theater f-s-24 d-block mb-2 text-secondary"></i>No hay conjuntos en seguimiento que coincidan con los filtros seleccionados.</td></tr>';
            return;
        }

        turnosFiltrados.forEach(t => {
            const tr = document.createElement('tr');

            // 1. Sorteo
            const tdSorteo = `<span class="badge bg-secondary f-s-13 f-w-700 px-2 py-1">#${t.orden_sorteo}</span>`;

            // 2. Conjunto y Danza
            const emblema = t.conjunto_emblema_url
                ? `<img src="${this.escapar(t.conjunto_emblema_url)}" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">`
                : `<div class="w-32 h-32 rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center me-2 f-s-12 f-w-700">${(t.conjunto_nombre || 'C').charAt(0)}</div>`;
            const bloqueInfo = t.bloque_nombre ? `<span class="badge bg-light text-dark border ms-1 f-s-10">${this.escapar(t.bloque_nombre)}</span>` : '';
            const tdConjunto = `
                <div class="d-flex align-items-center">
                    ${emblema}
                    <div>
                        <span class="f-w-700 text-dark d-block">${this.escapar(t.conjunto_nombre)} ${bloqueInfo}</span>
                        <span class="f-s-11 text-muted"><i class="fa-solid fa-feather me-1"></i>${this.escapar(t.tipo_danza_nombre)}</span>
                    </div>
                </div>
            `;

            // 3. Horario Programado
            const tdHorarioProg = `
                <div>
                    <span class="f-w-600 text-dark"><i class="fa-regular fa-clock me-1 text-primary"></i>${t.hora_estimada_partida.substring(0, 5)}</span>
                    ${t.hora_estimada_llegada ? `<span class="f-s-11 text-muted d-block">Est. Fin: ${t.hora_estimada_llegada.substring(0, 5)}</span>` : ''}
                </div>
            `;

            // 4. Estado Operativo
            const badgeClase = t.color_badge || 'secondary';
            const tdEstado = `
                <span class="badge bg-${badgeClase}-subtle text-${badgeClase} f-s-12 px-2 py-1">
                    <i class="fa-solid fa-circle f-s-8 me-1"></i>${this.escapar(t.estado_operativo_etiqueta)}
                </span>
            `;

            // 5. Horas Reales & Retraso
            let retrasoBadge = '';
            if (t.retraso_minutos > 0) {
                retrasoBadge = `<span class="badge bg-danger-subtle text-danger f-s-11 mt-1 d-inline-block"><i class="fa-solid fa-triangle-exclamation me-1"></i>+${t.retraso_minutos} min</span>`;
            } else if (t.estado_turno === 'FINALIZADO' || t.hora_real_partida) {
                retrasoBadge = `<span class="badge bg-success-subtle text-success f-s-11 mt-1 d-inline-block"><i class="fa-solid fa-check me-1"></i>A tiempo</span>`;
            }
            const horaPartida = t.hora_real_partida ? t.hora_real_partida.substring(11, 16) : '--:--';
            const horaFin = t.hora_real_fin ? t.hora_real_fin.substring(11, 16) : '--:--';
            const tdTiempos = `
                <div class="f-s-12">
                    <div><span class="text-muted">Inicio:</span> <strong>${horaPartida}</strong> | <span class="text-muted">Fin:</span> <strong>${horaFin}</strong></div>
                    ${retrasoBadge}
                </div>
            `;

            // 6. Cobertura Audiovisual & Personal
            let coberturaHtml = '';
            const salidas = t.salidas_cobertura || [];
            if (salidas.length === 0) {
                coberturaHtml = `<span class="badge bg-warning-subtle text-warning-emphasis"><i class="fa-solid fa-video-slash me-1"></i>Sin Cuadrilla</span>`;
            } else {
                coberturaHtml = salidas.map(s => {
                    const personalChips = (s.personal || []).map(p =>
                        `<span class="badge bg-light text-dark border me-1 mb-1 f-s-10" title="${this.escapar(p.rol_operativo)}"><i class="fa-solid fa-user me-1 text-primary"></i>${this.escapar(p.nombre_completo)}</span>`
                    ).join('');
                    return `
                        <div class="mb-1">
                            <span class="badge bg-primary-subtle text-primary f-w-600 f-s-11 me-1"><i class="fa-solid fa-camera me-1"></i>${this.escapar(s.correlativo)}</span>
                            <div class="mt-1">${personalChips || '<span class="f-s-10 text-muted">Sin operador asignado</span>'}</div>
                        </div>
                    `;
                }).join('');
            }

            // 7. Incidencias
            const tdIncidencias = t.observaciones
                ? `<div class="f-s-12 text-secondary text-truncate" style="max-width: 180px;" title="${this.escapar(t.observaciones)}"><i class="fa-solid fa-comment-dots text-danger me-1"></i>${this.escapar(t.observaciones)}</div>`
                : `<span class="f-s-11 text-muted">Sin incidencias</span>`;

            // 8. Acciones
            const tdAcciones = `
                <div class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger me-1 btn-control-operativo" data-id="${t.id}" title="Actualizar estado operativo y tiempos">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Estado
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-vincular-cobertura" data-id="${t.id}" title="Vincular salida audiovisual">
                        <i class="fa-solid fa-link"></i>
                    </button>
                </div>
            `;

            tr.innerHTML = `
                <td>${tdSorteo}</td>
                <td>${tdConjunto}</td>
                <td>${tdHorarioProg}</td>
                <td>${tdEstado}</td>
                <td>${tdTiempos}</td>
                <td>${coberturaHtml}</td>
                <td>${tdIncidencias}</td>
                <td>${tdAcciones}</td>
            `;

            tbody.appendChild(tr);
        });

        // Vincular eventos de botones de fila
        tbody.querySelectorAll('.btn-control-operativo').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                this.abrirModalControlOperativo(id);
            });
        });

        tbody.querySelectorAll('.btn-vincular-cobertura').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                this.abrirModalVincularSalidaDesdeTablero(id);
            });
        });
    }

    abrirModalControlOperativo(turnoId) {
        let turno = null;
        if (turnoId) {
            turno = this.tableroTurnos.find(t => t.id === turnoId);
        } else if (this.tableroTurnos && this.tableroTurnos.length > 0) {
            turno = this.tableroTurnos[0];
        }
        if (!turno) return;

        document.getElementById('segTurnoId').value = turno.id;
        document.getElementById('segVersionBloqueo').value = turno.version_bloqueo || 1;
        document.getElementById('segBadgeSorteo').textContent = `Turno #${turno.orden_sorteo}`;
        document.getElementById('segNombreConjunto').textContent = turno.conjunto_nombre || 'Comparsa';
        document.getElementById('segNombreDanza').textContent = turno.tipo_danza_nombre || 'Danza';
        document.getElementById('segHorarioEstimado').textContent = `${turno.hora_estimada_partida.substring(0, 5)} - ${turno.hora_estimada_llegada ? turno.hora_estimada_llegada.substring(0, 5) : '--:--'}`;

        document.getElementById('segEstadoTurno').value = turno.estado_turno || 'PROGRAMADO';
        document.getElementById('segHoraRealPartida').value = turno.hora_real_partida || '';
        document.getElementById('segHoraRealFin').value = turno.hora_real_fin || '';
        document.getElementById('segRetrasoMinutos').value = turno.retraso_minutos ?? 0;
        document.getElementById('segObservaciones').value = turno.observaciones || '';

        const modalEl = document.getElementById('modalActualizarEstadoOperativo');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    abrirModalVincularSalidaDesdeTablero(turnoId) {
        const turno = this.tableroTurnos.find(t => t.id === turnoId);
        const selTurno = document.getElementById('vincularTurnoId');
        if (selTurno && turno) {
            selTurno.value = turno.id;
        }

        const modalEl = document.getElementById('modalVincularSalida');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    async guardarEstadoOperativo(e) {
        e.preventDefault();
        const turnoId = parseInt(document.getElementById('segTurnoId').value, 10);
        const versionBloqueo = parseInt(document.getElementById('segVersionBloqueo').value, 10) || 1;
        const estadoTurno = document.getElementById('segEstadoTurno').value;
        const horaPartida = document.getElementById('segHoraRealPartida').value.trim();
        const horaFin = document.getElementById('segHoraRealFin').value.trim();
        const retrasoVal = document.getElementById('segRetrasoMinutos').value;
        const observaciones = document.getElementById('segObservaciones').value.trim();

        const payload = {
            estado_turno: estadoTurno,
            hora_real_partida: horaPartida || null,
            hora_real_fin: horaFin || null,
            retraso_minutos: retrasoVal !== '' ? parseInt(retrasoVal, 10) : null,
            observaciones: observaciones || null,
            version_bloqueo: versionBloqueo
        };

        const btn = document.getElementById('btnGuardarEstadoOperativo');
        if (btn) btn.disabled = true;

        try {
            const resp = await fetch(`${this.baseUrl}/api/v1/folclore/seguimiento/turnos/${turnoId}/estado`, {
                method: 'PUT',
                headers: this.obtenerHeaders(),
                body: JSON.stringify(payload)
            });
            const data = await resp.json();

            if (resp.status === 409) {
                // Conflicto de concurrencia optimista
                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        icon: 'warning',
                        title: 'Conflicto de Concurrencia (409)',
                        text: data.mensaje || 'El turno fue modificado concurrentemente por otro operador. Se recargarán los datos actualizados.',
                        confirmButtonText: 'Entendido y Recargar'
                    });
                } else {
                    alert(data.mensaje || 'Conflicto de concurrencia: El turno fue modificado por otro operador.');
                }
                bootstrap.Modal.getInstance(document.getElementById('modalActualizarEstadoOperativo'))?.hide();
                await this.cargarTableroSeguimiento();
                return;
            }

            if (data.exito) {
                bootstrap.Modal.getInstance(document.getElementById('modalActualizarEstadoOperativo'))?.hide();
                await this.cargarTableroSeguimiento();
                await this.actualizarVistaRolYTurnos();
                this.notificar(data.mensaje || 'Estado operativo actualizado con éxito.', 'success');
            } else {
                this.notificar(data.mensaje || 'Error al actualizar estado operativo.', 'danger');
            }
        } catch (err) {
            console.error('Error al guardar estado operativo:', err);
            this.notificar('Error de comunicación con el servidor.', 'danger');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    obtenerHoraLimaActual() {
        const ahora = new Date();
        const y = ahora.getFullYear();
        const m = String(ahora.getMonth() + 1).padStart(2, '0');
        const d = String(ahora.getDate()).padStart(2, '0');
        const hh = String(ahora.getHours()).padStart(2, '0');
        const mm = String(ahora.getMinutes()).padStart(2, '0');
        const ss = String(ahora.getSeconds()).padStart(2, '0');
        return `${y}-${m}-${d} ${hh}:${mm}:${ss}`;
    }

    // ==============================================================================
    // NOTIFICACIONES Y SANITIZACIÓN
    // ==============================================================================

    notificar(mensaje, tipo = 'info') {
        if (typeof Swal !== 'undefined') {
            const iconMap = { success: 'success', danger: 'error', warning: 'warning', info: 'info' };
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: iconMap[tipo] || 'info',
                title: mensaje,
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true
            });
        } else {
            alert(mensaje);
        }
    }

    escapar(texto) {
        if (texto === null || texto === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(texto);
        return div.innerHTML;
    }
}

// Inicialización automática tras cargar DOM
document.addEventListener('DOMContentLoaded', () => {
    window.moduloFolclore = new ModuloFolclore();
});
