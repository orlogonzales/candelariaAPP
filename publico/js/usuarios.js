/**
 * ==============================================================================
 * CANDELARIAAPP - GESTIÓN ASÍNCRONA DE USUARIOS (usuarios.js)
 * ==============================================================================
 * - CRUD 100% asíncrono con Fetch API, DataTables y Modales Bootstrap 5 (Alina)
 * - Cumplimiento del ciclo de estados:
 *     Skeleton -> Carga inicial y refresco
 *     Disabled + Spinner -> Procesamiento de botones
 *     SweetAlert2 -> Retroalimentación y confirmaciones
 *     Modificación parcial -> Actualización de tabla sin location.reload()
 * - Cero eliminación física (DELETE prohibido).
 * ==============================================================================
 */

'use strict';

class ModuloUsuarios {
    constructor() {
        this.instanciaDataTable = null;
        this.catalogoRoles = [];
        this.catalogoTiposDoc = [];
        this.personasDisponibles = [];
        this.usuariosCache = [];

        this.init();
    }

    async init() {
        this.vincularEventosUI();
        await this.cargarCatalogosAuxiliares();
        await this.cargarUsuarios();
    }

    /**
     * Vincula listeners de botones principales y formularios modales.
     */
    vincularEventosUI() {
        // Botón recargar
        const btnRecargar = document.getElementById('btnRecargarTabla');
        if (btnRecargar) {
            btnRecargar.addEventListener('click', () => this.cargarUsuarios());
        }

        // Botón abrir modal nuevo usuario
        const btnNuevo = document.getElementById('btnAbrirModalCrear');
        if (btnNuevo) {
            btnNuevo.addEventListener('click', () => this.abrirModalCrear());
        }

        // Radios de selección de modo de persona (existente vs nueva)
        const radioExistente = document.getElementById('modoPersonaExistente');
        const radioNueva = document.getElementById('modoPersonaNueva');
        const panelExistente = document.getElementById('panelPersonaExistente');
        const panelNueva = document.getElementById('panelPersonaNueva');

        if (radioExistente && radioNueva && panelExistente && panelNueva) {
            radioExistente.addEventListener('change', () => {
                panelExistente.classList.remove('d-none');
                panelNueva.classList.add('d-none');
            });
            radioNueva.addEventListener('change', () => {
                panelExistente.classList.add('d-none');
                panelNueva.classList.remove('d-none');
            });
        }

        // Tipo de persona (Natural vs Jurídica) en modal crear
        const selectTipoPersona = document.getElementById('crearTipoPersona');
        if (selectTipoPersona) {
            selectTipoPersona.addEventListener('change', (e) => {
                const esNatural = e.target.value === 'NATURAL';
                document.querySelectorAll('.campos-natural').forEach(el => el.classList.toggle('d-none', !esNatural));
                document.querySelectorAll('.campos-juridica').forEach(el => el.classList.toggle('d-none', esNatural));
            });
        }

        // Toggle visibilidad de contraseña en modal crear
        const btnToggleClaveCrear = document.getElementById('btnToggleClaveCrear');
        const inputClaveCrear = document.getElementById('crearContrasena');
        const iconoClaveCrear = document.getElementById('iconoToggleClaveCrear');
        if (btnToggleClaveCrear && inputClaveCrear && iconoClaveCrear) {
            btnToggleClaveCrear.addEventListener('click', () => {
                const esPass = inputClaveCrear.type === 'password';
                inputClaveCrear.type = esPass ? 'text' : 'password';
                iconoClaveCrear.className = esPass ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            });
        }

        // Submit form crear usuario
        const formCrear = document.getElementById('formCrearUsuario');
        if (formCrear) {
            formCrear.addEventListener('submit', (e) => this.guardarNuevoUsuario(e));
        }

        // Submit form editar usuario
        const formEditar = document.getElementById('formEditarUsuario');
        if (formEditar) {
            formEditar.addEventListener('submit', (e) => this.guardarEdicionUsuario(e));
        }

        // Botón guardar roles
        const btnGuardarRoles = document.getElementById('btnGuardarRolesModal');
        if (btnGuardarRoles) {
            btnGuardarRoles.addEventListener('click', () => this.guardarRolesUsuario());
        }

        // Botón confirmar restablecimiento de contraseña
        const btnResetClave = document.getElementById('btnConfirmarReset');
        if (btnResetClave) {
            btnResetClave.addEventListener('click', () => this.ejecutarRestablecerClave());
        }

        // Botón copiar clave generada
        const btnCopiar = document.getElementById('btnCopiarClaveGenerada');
        if (btnCopiar) {
            btnCopiar.addEventListener('click', () => {
                const texto = document.getElementById('textoClaveGenerada')?.innerText;
                if (texto) {
                    navigator.clipboard.writeText(texto);
                    btnCopiar.innerHTML = '<i class="fa-solid fa-check"></i> ¡Copiado!';
                    setTimeout(() => {
                        btnCopiar.innerHTML = '<i class="fa-solid fa-copy"></i> Copiar';
                    }, 2000);
                }
            });
        }
    }

    /**
     * Carga catálogos auxiliares (roles, tipos de documento, personas disponibles).
     */
    async cargarCatalogosAuxiliares() {
        try {
            const [respRoles, respTiposDoc] = await Promise.all([
                CandelariaApi.get('roles').catch(() => ({ datos: { roles: [] } })),
                CandelariaApi.get('tipos-documento').catch(() => ({ datos: { tipos_documento: [] } }))
            ]);

            this.catalogoRoles = respRoles?.datos?.roles || [];
            this.catalogoTiposDoc = respTiposDoc?.datos?.tipos_documento || [];

            this.renderizarCheckboxesRolesCrear();
            this.renderizarSelectTiposDoc();
        } catch (e) {
            console.error('[ModuloUsuarios] Error cargando catálogos auxiliares:', e);
        }
    }

    renderizarCheckboxesRolesCrear() {
        const contenedor = document.getElementById('contenedorRolesCrear');
        if (!contenedor) return;

        if (this.catalogoRoles.length === 0) {
            contenedor.innerHTML = '<div class="text-muted f-s-12">No hay roles registrados en el catálogo.</div>';
            return;
        }

        let html = '<div class="row g-2">';
        this.catalogoRoles.forEach(rol => {
            html += `
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input check-rol-crear" type="checkbox" value="${rol.id}" id="rolCrear_${rol.id}">
                        <label class="form-check-label f-s-13 text-dark" for="rolCrear_${rol.id}">
                            <strong>${this.escapar(rol.nombre)}</strong>
                            <div class="f-s-11 text-muted">${this.escapar(rol.descripcion || '')}</div>
                        </label>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        contenedor.innerHTML = html;
    }

    renderizarSelectTiposDoc() {
        const select = document.getElementById('crearTipoDoc');
        if (!select || this.catalogoTiposDoc.length === 0) return;

        select.innerHTML = this.catalogoTiposDoc.map(td =>
            `<option value="${td.id}">${this.escapar(td.codigo)} - ${this.escapar(td.nombre)}</option>`
        ).join('');
    }

    /**
     * Carga la lista de usuarios desde la API y renderiza la DataTable.
     */
    async cargarUsuarios() {
        const contenedor = document.getElementById('contenedorTablaUsuarios');
        if (!contenedor) return;

        // 1. Estado de carga: SKELETON SEMÁNTICO
        Skeleton.show(contenedor, 'table', { filas: 6, columnas: 7 });

        try {
            const respuesta = await CandelariaApi.get('usuarios');
            const usuarios = respuesta?.datos?.usuarios || [];
            this.usuariosCache = usuarios;

            // Actualizar KPIs superiores
            this.actualizarKpis(usuarios);

            // 2. Destruir DataTable previa si existía
            if (this.instanciaDataTable) {
                this.instanciaDataTable.destroy();
                this.instanciaDataTable = null;
            }

            // 3. Ocultar Skeleton y construir tabla
            Skeleton.hide(contenedor);

            this.renderizarTablaHtml(contenedor, usuarios);

            // 4. Inicializar DataTables con Alina/jQuery
            if (window.jQuery && window.jQuery.fn.DataTable) {
                this.instanciaDataTable = window.jQuery('#tablaUsuarios').DataTable({
                    language: {
                        search: "Buscar usuario:",
                        lengthMenu: "Mostrar _MENU_ registros",
                        info: "Mostrando _START_ a _END_ de _TOTAL_ usuarios",
                        infoEmpty: "Mostrando 0 usuarios",
                        infoFiltered: "(filtrado de _MAX_ usuarios totales)",
                        zeroRecords: "No se encontraron usuarios coincidentes",
                        paginate: {
                            first: "Primero",
                            previous: "Anterior",
                            next: "Siguiente",
                            last: "Último"
                        }
                    },
                    order: [[0, 'desc']],
                    pageLength: 10,
                    responsive: true,
                    dom: '<"d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"lf>rt<"d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2"ip>'
                });
            }

            // 5. Vincular listeners de acciones en las filas
            this.vincularAccionesFilas();

        } catch (err) {
            console.error('[ModuloUsuarios] Error cargando usuarios:', err);
            Skeleton.error(contenedor, 'No fue posible cargar el padrón de usuarios: ' + (err.message || 'Error del servidor'), {
                texto: 'Reintentar',
                accion: () => this.cargarUsuarios()
            });
        }
    }

    actualizarKpis(usuarios) {
        const total = usuarios.length;
        const activos = usuarios.filter(u => u.estado === 'ACTIVO').length;
        const inactivos = total - activos;

        const elTotal = document.getElementById('kpiTotalUsuarios');
        const elActivos = document.getElementById('kpiUsuariosActivos');
        const elInactivos = document.getElementById('kpiUsuariosInactivos');

        if (elTotal) elTotal.innerText = total.toString();
        if (elActivos) elActivos.innerText = activos.toString();
        if (elInactivos) elInactivos.innerText = inactivos.toString();
    }

    renderizarTablaHtml(contenedor, usuarios) {
        let filasHtml = '';

        if (usuarios.length === 0) {
            filasHtml = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-users-slash f-s-32 d-block mb-2 text-secondary"></i>
                        No se registraron usuarios en el sistema.
                    </td>
                </tr>
            `;
        } else {
            usuarios.forEach(u => {
                const avatar = u.avatar_url || (window.CANDELARIA_BASE_URL || '') + '/publico/activos/alina/images/avatar/01.png';
                const estadoBadge = this.obtenerBadgeEstado(u.estado);

                // Roles badges
                let rolesBadges = '';
                if (u.roles && u.roles.length > 0) {
                    rolesBadges = u.roles.map(r => `<span class="badge bg-primary-subtle text-primary border me-1 mb-1">${this.escapar(r.nombre)}</span>`).join('');
                } else {
                    rolesBadges = '<span class="text-muted f-s-12">Sin roles</span>';
                }

                // Persona / Documento
                const docTexto = u.persona ? `${this.escapar(u.persona.tipo_documento_codigo)} ${this.escapar(u.persona.numero_documento)}` : '-';
                const personaNombre = u.persona?.nombre_representativo || u.nombre_completo;

                filasHtml += `
                    <tr data-id="${u.id}">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="${this.escapar(avatar)}" alt="Avatar" class="w-35 h-35 rounded-circle border flex-shrink-0">
                                <div>
                                    <div class="f-w-700 text-dark">${this.escapar(u.nombre_usuario)}</div>
                                    <span class="f-s-11 text-muted">#${u.id}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="f-w-600 text-dark">${this.escapar(personaNombre)}</div>
                            <span class="badge bg-light text-secondary border f-s-10">${this.escapar(u.persona?.tipo_persona || 'NATURAL')}</span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">${docTexto}</span>
                        </td>
                        <td>
                            <div class="f-s-13"><i class="fa-solid fa-envelope text-secondary me-1"></i> ${this.escapar(u.correo_electronico)}</div>
                            ${u.telefono_whatsapp ? `<div class="f-s-12 text-muted"><i class="fa-brands fa-whatsapp text-success me-1"></i> ${this.escapar(u.telefono_whatsapp)}</div>` : ''}
                        </td>
                        <td>
                            <div class="d-flex flex-wrap">${rolesBadges}</div>
                        </td>
                        <td>
                            ${estadoBadge}
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                    <li>
                                        <button class="dropdown-item btn-accion-editar" data-id="${u.id}">
                                            <i class="fa-solid fa-user-pen text-primary me-2"></i> Editar Usuario
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item btn-accion-roles" data-id="${u.id}">
                                            <i class="fa-solid fa-shield-halved text-warning me-2"></i> Asignar Roles
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item btn-accion-reset" data-id="${u.id}">
                                            <i class="fa-solid fa-key text-info me-2"></i> Restablecer Clave
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        ${u.estado === 'ACTIVO'
                                            ? `<button class="dropdown-item text-danger btn-accion-estado" data-id="${u.id}" data-estado="INACTIVO">
                                                   <i class="fa-solid fa-user-xmark me-2"></i> Desactivar Cuenta
                                               </button>`
                                            : `<button class="dropdown-item text-success btn-accion-estado" data-id="${u.id}" data-estado="ACTIVO">
                                                   <i class="fa-solid fa-user-check me-2"></i> Activar Cuenta
                                               </button>`
                                        }
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                `;
            });
        }

        contenedor.innerHTML = `
            <table class="display app-data-table default-data-table w-100 table-hover align-middle" id="tablaUsuarios">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Identidad / Persona</th>
                        <th>Documento</th>
                        <th>Contacto</th>
                        <th>Roles Asignados</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${filasHtml}
                </tbody>
            </table>
        `;
    }

    obtenerBadgeEstado(estado) {
        switch (estado) {
            case 'ACTIVO':
                return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-circle f-s-8 me-1"></i> ACTIVO</span>';
            case 'INACTIVO':
                return '<span class="badge bg-secondary-subtle text-secondary border px-2 py-1"><i class="fa-solid fa-circle f-s-8 me-1"></i> INACTIVO</span>';
            case 'BLOQUEADO':
                return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="fa-solid fa-lock f-s-8 me-1"></i> BLOQUEADO</span>';
            default:
                return `<span class="badge bg-light text-dark">${this.escapar(estado)}</span>`;
        }
    }

    /**
     * Vincula listeners de las acciones dentro de la tabla (Editar, Roles, Reset, Estado).
     */
    vincularAccionesFilas() {
        document.querySelectorAll('.btn-accion-editar').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.dataset.id;
                this.abrirModalEditar(id);
            });
        });

        document.querySelectorAll('.btn-accion-roles').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.dataset.id;
                this.abrirModalRoles(id);
            });
        });

        document.querySelectorAll('.btn-accion-reset').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.dataset.id;
                this.abrirModalReset(id);
            });
        });

        document.querySelectorAll('.btn-accion-estado').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.dataset.id;
                const nuevoEstado = e.currentTarget.dataset.estado;
                this.confirmarCambioEstado(id, nuevoEstado);
            });
        });
    }

    /**
     * Modal 1: Abrir y cargar personas disponibles.
     */
    async abrirModalCrear() {
        const modalEl = document.getElementById('modalCrearUsuario');
        if (!modalEl) return;

        // Resetear formulario
        const form = document.getElementById('formCrearUsuario');
        if (form) form.reset();

        document.getElementById('modoPersonaExistente').checked = true;
        document.getElementById('panelPersonaExistente').classList.remove('d-none');
        document.getElementById('panelPersonaNueva').classList.add('d-none');

        // Cargar personas disponibles
        const select = document.getElementById('selectPersonaExistente');
        if (select) {
            select.innerHTML = '<option value="">-- Cargando personas disponibles... --</option>';
            try {
                const resp = await CandelariaApi.get('personas/disponibles');
                const personas = resp?.datos?.personas || [];
                this.personasDisponibles = personas;

                if (personas.length === 0) {
                    select.innerHTML = '<option value="">-- No hay personas activas sin usuario asignado --</option>';
                } else {
                    select.innerHTML = '<option value="">-- Seleccione una persona del tenant --</option>' +
                        personas.map(p => `
                            <option value="${p.id}" data-nombre="${this.escapar(p.nombre_completo)}" data-correo="${this.escapar(p.correo_electronico || '')}" data-whatsapp="${this.escapar(p.telefono_whatsapp || '')}">
                                ${this.escapar(p.nombre_completo)} (${this.escapar(p.tipo_documento_codigo)} ${this.escapar(p.numero_documento)})
                            </option>
                        `).join('');

                    // Autocompletar sugerencias al seleccionar persona
                    select.onchange = () => {
                        const opt = select.options[select.selectedIndex];
                        if (opt && opt.value) {
                            const correo = opt.dataset.correo;
                            const whatsapp = opt.dataset.whatsapp;
                            if (correo && !document.getElementById('crearCorreo').value) {
                                document.getElementById('crearCorreo').value = correo;
                            }
                        }
                    };
                }
            } catch (err) {
                select.innerHTML = '<option value="">-- Error al cargar personas --</option>';
            }
        }

        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    /**
     * Guardar nuevo usuario vía API POST.
     */
    async guardarNuevoUsuario(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarUsuario');
        const modoPersona = document.querySelector('input[name="modo_persona"]:checked')?.value || 'existente';

        const payload = {
            modo_persona: modoPersona,
            nombre_usuario: document.getElementById('crearNombreUsuario').value.trim(),
            correo_electronico: document.getElementById('crearCorreo').value.trim(),
            contrasena: document.getElementById('crearContrasena').value,
            estado: document.getElementById('crearEstado').value,
            roles: Array.from(document.querySelectorAll('.check-rol-crear:checked')).map(cb => parseInt(cb.value, 10))
        };

        if (modoPersona === 'existente') {
            payload.persona_id = parseInt(document.getElementById('selectPersonaExistente').value, 10);
            if (!payload.persona_id) {
                CandelariaUI.notificarError('Debe seleccionar una persona existente.');
                return;
            }
        } else {
            payload.tipo_persona = document.getElementById('crearTipoPersona').value;
            payload.tipo_documento_id = parseInt(document.getElementById('crearTipoDoc').value, 10);
            payload.numero_documento = document.getElementById('crearNumDoc').value.trim();
            payload.telefono_whatsapp = document.getElementById('crearPersonaWhatsapp').value.trim();
            payload.ciudad = document.getElementById('crearCiudad').value.trim();

            if (payload.tipo_persona === 'NATURAL') {
                payload.nombres = document.getElementById('crearNombres').value.trim();
                payload.apellidos = document.getElementById('crearApellidos').value.trim();
            } else {
                payload.razon_social = document.getElementById('crearRazonSocial').value.trim();
                payload.nombre_comercial = document.getElementById('crearNombreComercial').value.trim();
            }
        }

        CandelariaUI.procesarBoton(btn, 'Registrando usuario...');

        try {
            await CandelariaApi.post('usuarios', payload);

            // Cerrar modal
            const modalEl = document.getElementById('modalCrearUsuario');
            const modalInstancia = bootstrap.Modal.getInstance(modalEl);
            if (modalInstancia) modalInstancia.hide();

            CandelariaUI.restaurarBoton(btn);
            await CandelariaUI.notificarExito('El usuario ha sido registrado exitosamente.');

            // Refresco asíncrono sin recarga de página
            await this.cargarUsuarios();
        } catch (err) {
            CandelariaUI.restaurarBoton(btn);
            CandelariaUI.notificarError(err.message || 'Error al crear el usuario.');
        }
    }

    /**
     * Modal 2: Abrir modal editar con datos del usuario.
     */
    async abrirModalEditar(id) {
        const usuario = this.usuariosCache.find(u => u.id == id);
        if (!usuario) return;

        document.getElementById('editarUsuarioId').value = usuario.id;
        document.getElementById('editarLabelUsername').innerText = usuario.nombre_usuario;
        document.getElementById('editarBadgeId').innerText = `ID: #${usuario.id}`;
        document.getElementById('editarNombreCompleto').value = usuario.nombre_completo || '';
        document.getElementById('editarCorreo').value = usuario.correo_electronico || '';
        document.getElementById('editarWhatsapp').value = usuario.telefono_whatsapp || '';
        document.getElementById('editarEstado').value = usuario.estado || 'ACTIVO';

        const modalEl = document.getElementById('modalEditarUsuario');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    async guardarEdicionUsuario(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarEdicion');
        const id = document.getElementById('editarUsuarioId').value;

        const payload = {
            nombre_completo: document.getElementById('editarNombreCompleto').value.trim(),
            correo_electronico: document.getElementById('editarCorreo').value.trim(),
            telefono_whatsapp: document.getElementById('editarWhatsapp').value.trim(),
            estado: document.getElementById('editarEstado').value
        };

        CandelariaUI.procesarBoton(btn, 'Guardando cambios...');

        try {
            await CandelariaApi.put(`usuarios/${id}`, payload);

            const modalEl = document.getElementById('modalEditarUsuario');
            const modalInstancia = bootstrap.Modal.getInstance(modalEl);
            if (modalInstancia) modalInstancia.hide();

            CandelariaUI.restaurarBoton(btn);
            await CandelariaUI.notificarExito('Los datos del usuario han sido actualizados.');

            // Refresco asíncrono
            await this.cargarUsuarios();
        } catch (err) {
            CandelariaUI.restaurarBoton(btn);
            CandelariaUI.notificarError(err.message || 'Error al actualizar usuario.');
        }
    }

    /**
     * Modal 3: Asignar / sincronizar roles RBAC.
     */
    abrirModalRoles(id) {
        const usuario = this.usuariosCache.find(u => u.id == id);
        if (!usuario) return;

        document.getElementById('rolesModalUsuarioId').value = usuario.id;
        document.getElementById('rolesModalNombreUsuario').innerText = `${usuario.nombre_completo} (@${usuario.nombre_usuario})`;

        const contenedor = document.getElementById('rolesModalCheckboxes');
        if (!contenedor) return;

        const rolesAsignadosIds = (usuario.roles || []).map(r => r.id);

        contenedor.innerHTML = this.catalogoRoles.map(r => {
            const checked = rolesAsignadosIds.includes(r.id) ? 'checked' : '';
            return `
                <div class="form-check">
                    <input class="form-check-input check-modal-rol" type="checkbox" value="${r.id}" id="checkRolModal_${r.id}" ${checked}>
                    <label class="form-check-label f-s-13 text-dark" for="checkRolModal_${r.id}">
                        <strong>${this.escapar(r.nombre)}</strong>
                        <div class="f-s-11 text-muted">${this.escapar(r.descripcion || '')}</div>
                    </label>
                </div>
            `;
        }).join('');

        const modalEl = document.getElementById('modalRolesUsuario');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    async guardarRolesUsuario() {
        const btn = document.getElementById('btnGuardarRolesModal');
        const id = document.getElementById('rolesModalUsuarioId').value;
        const roles = Array.from(document.querySelectorAll('.check-modal-rol:checked')).map(cb => parseInt(cb.value, 10));

        CandelariaUI.procesarBoton(btn, 'Sincronizando...');

        try {
            await CandelariaApi.put(`usuarios/${id}/roles`, { roles });

            const modalEl = document.getElementById('modalRolesUsuario');
            const modalInstancia = bootstrap.Modal.getInstance(modalEl);
            if (modalInstancia) modalInstancia.hide();

            CandelariaUI.restaurarBoton(btn);
            await CandelariaUI.notificarExito('Roles asignados correctamente.');

            await this.cargarUsuarios();
        } catch (err) {
            CandelariaUI.restaurarBoton(btn);
            CandelariaUI.notificarError(err.message || 'Error al actualizar roles.');
        }
    }

    /**
     * Modal 4: Restablecer contraseña.
     */
    abrirModalReset(id) {
        const usuario = this.usuariosCache.find(u => u.id == id);
        if (!usuario) return;

        document.getElementById('resetModalUsuarioId').value = usuario.id;
        document.getElementById('resetModalNombreUsuario').innerText = `@${usuario.nombre_usuario}`;
        document.getElementById('resetInputNuevaClave').value = '';
        document.getElementById('resultadoClaveGenerada').classList.add('d-none');

        const modalEl = document.getElementById('modalResetClave');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    async ejecutarRestablecerClave() {
        const btn = document.getElementById('btnConfirmarReset');
        const id = document.getElementById('resetModalUsuarioId').value;
        const nuevaClave = document.getElementById('resetInputNuevaClave').value.trim();

        CandelariaUI.procesarBoton(btn, 'Restableciendo...');

        try {
            const resp = await CandelariaApi.post(`usuarios/${id}/restablecer-clave`, {
                contrasena: nuevaClave
            });

            CandelariaUI.restaurarBoton(btn);

            if (resp?.datos?.clave_temporal) {
                // Mostrar clave autogenerada en el modal para que el admin la copie
                const box = document.getElementById('resultadoClaveGenerada');
                document.getElementById('textoClaveGenerada').innerText = resp.datos.clave_temporal;
                box.classList.remove('d-none');
                CandelariaUI.notificarExito('Contraseña temporal generada con éxito. Cópiela antes de cerrar.');
            } else {
                const modalEl = document.getElementById('modalResetClave');
                const modalInstancia = bootstrap.Modal.getInstance(modalEl);
                if (modalInstancia) modalInstancia.hide();
                CandelariaUI.notificarExito('Contraseña actualizada correctamente.');
            }

            await this.cargarUsuarios();
        } catch (err) {
            CandelariaUI.restaurarBoton(btn);
            CandelariaUI.notificarError(err.message || 'Error al restablecer la contraseña.');
        }
    }

    /**
     * Cambio de estado (ACTIVO / INACTIVO) con SweetAlert2.
     */
    async confirmarCambioEstado(id, nuevoEstado) {
        const accion = nuevoEstado === 'ACTIVO' ? 'activar' : 'desactivar';
        const confirmacion = await CandelariaUI.confirmarAccion(
            `¿Está seguro de que desea ${accion} la cuenta de este usuario? Si la desactiva, cualquier sesión abierta será invalidada de inmediato.`,
            `Confirmar ${accion}`
        );

        if (!confirmacion.isConfirmed) return;

        try {
            await CandelariaApi.patch(`usuarios/${id}/estado`, { estado: nuevoEstado });
            await CandelariaUI.notificarExito(`Cuenta de usuario ${nuevoEstado === 'ACTIVO' ? 'activada' : 'desactivada'} exitosamente.`);
            await this.cargarUsuarios();
        } catch (err) {
            CandelariaUI.notificarError(err.message || 'No fue posible cambiar el estado del usuario.');
        }
    }

    escapar(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }
}

// Inicializar al cargar el DOM o inmediatamente si ya está listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.moduloUsuarios = new ModuloUsuarios();
    });
} else {
    window.moduloUsuarios = new ModuloUsuarios();
}
