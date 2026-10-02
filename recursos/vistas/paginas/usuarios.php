<!-- Encabezado de la Sección de Usuarios -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-users-gear text-primary me-2"></i> Padrón General de Usuarios
        </h3>
        <p class="text-secondary mb-0">
            Administración de cuentas de acceso, asignación de roles RBAC y trazabilidad de identidad institucional.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarTabla" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalCrear">
                <i class="fa-solid fa-user-plus me-2"></i> Nuevo Usuario
            </button>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen de Usuarios -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Total Usuarios</span>
                    <h3 class="f-w-700 mb-0 mt-1" id="kpiTotalUsuarios">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-primary-subtle text-primary rounded-circle f-s-20">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Usuarios Activos</span>
                    <h3 class="f-w-700 text-success mb-0 mt-1" id="kpiUsuariosActivos">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-success-subtle text-success rounded-circle f-s-20">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Inactivos / Bloqueados</span>
                    <h3 class="f-w-700 text-danger mb-0 mt-1" id="kpiUsuariosInactivos">-</h3>
                </div>
                <div class="w-45 h-45 d-flex-center bg-danger-subtle text-danger rounded-circle f-s-20">
                    <i class="fa-solid fa-user-lock"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm b-r-12">
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="f-s-12 text-muted text-uppercase f-w-600">Modelo de Seguridad</span>
                    <h5 class="f-w-600 text-dark mb-0 mt-2">RBAC 6 Permisos</h5>
                </div>
                <div class="w-45 h-45 d-flex-center bg-warning-subtle text-warning rounded-circle f-s-20">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor Principal de la Tabla de Usuarios con Soporte para Skeleton -->
<div class="card border-0 shadow-sm b-r-12">
    <div class="card-body p-3">
        <!-- Contenedor dinámico donde actúa el Skeleton Loader -->
        <div id="contenedorTablaUsuarios" class="app-datatable-default overflow-auto app-scroll">
            <!-- La tabla será inyectada e inicializada por usuarios.js -->
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 1: CREAR USUARIO (+ VINCULAR O REGISTRAR PERSONA)
============================================================================== -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="modalCrearUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalCrearUsuarioLabel">
                    <i class="fa-solid fa-user-plus me-2"></i> Dar de Alta Nuevo Usuario
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCrearUsuario">
                <div class="modal-body p-4">
                    <!-- Selector de Identidad: Persona Existente vs Nueva Persona -->
                    <div class="mb-4 p-3 bg-light b-r-10 border">
                        <label class="form-label f-w-600 text-dark mb-2">
                            <i class="fa-solid fa-id-card me-1 text-primary"></i> Identidad de la Persona
                        </label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modo_persona" id="modoPersonaExistente" value="existente" checked>
                                <label class="form-check-label f-s-14" for="modoPersonaExistente">
                                    Vincular a persona existente
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modo_persona" id="modoPersonaNueva" value="nueva">
                                <label class="form-check-label f-s-14" for="modoPersonaNueva">
                                    Registrar nueva persona en el acto
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Persona Existente -->
                    <div id="panelPersonaExistente" class="mb-3">
                        <label class="form-label f-w-600" for="selectPersonaExistente">
                            Seleccione la Persona <span class="text-danger">*</span>
                        </label>
                        <select class="form-select py-2" id="selectPersonaExistente" name="persona_id">
                            <option value="">-- Cargando personas disponibles... --</option>
                        </select>
                        <div class="form-text f-s-12 text-muted">
                            Solo se muestran personas activas del tenant que aún no tienen cuenta de usuario asignada.
                        </div>
                    </div>

                    <!-- Panel Nueva Persona (oculto por defecto) -->
                    <div id="panelPersonaNueva" class="mb-3 d-none border p-3 b-r-10">
                        <h6 class="f-w-600 text-primary mb-3">Datos de la Nueva Persona</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label f-s-13 f-w-600">Tipo de Persona <span class="text-danger">*</span></label>
                                <select class="form-select py-2" id="crearTipoPersona" name="tipo_persona">
                                    <option value="NATURAL" selected>Persona Natural</option>
                                    <option value="JURIDICA">Persona Jurídica</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label f-s-13 f-w-600">Tipo Documento <span class="text-danger">*</span></label>
                                <select class="form-select py-2" id="crearTipoDoc" name="tipo_documento_id">
                                    <option value="1">DNI</option>
                                    <option value="2">RUC</option>
                                    <option value="3">PASAPORTE</option>
                                    <option value="4">CARNET EXTRANJERÍA</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label f-s-13 f-w-600">N° Documento <span class="text-danger">*</span></label>
                                <input type="text" class="form-control py-2" id="crearNumDoc" name="numero_documento" placeholder="Número de doc.">
                            </div>

                            <!-- Campos Persona Natural -->
                            <div class="col-md-6 campos-natural">
                                <label class="form-label f-s-13 f-w-600">Nombres <span class="text-danger">*</span></label>
                                <input type="text" class="form-control py-2" id="crearNombres" name="nombres" placeholder="Ej. Juan Carlos">
                            </div>
                            <div class="col-md-6 campos-natural">
                                <label class="form-label f-s-13 f-w-600">Apellidos <span class="text-danger">*</span></label>
                                <input type="text" class="form-control py-2" id="crearApellidos" name="apellidos" placeholder="Ej. Pérez Quispe">
                            </div>

                            <!-- Campos Persona Jurídica -->
                            <div class="col-md-8 campos-juridica d-none">
                                <label class="form-label f-s-13 f-w-600">Razón Social <span class="text-danger">*</span></label>
                                <input type="text" class="form-control py-2" id="crearRazonSocial" name="razon_social" placeholder="Razón Social Oficial">
                            </div>
                            <div class="col-md-4 campos-juridica d-none">
                                <label class="form-label f-s-13 f-w-600">Nombre Comercial</label>
                                <input type="text" class="form-control py-2" id="crearNombreComercial" name="nombre_comercial" placeholder="Opcional">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label f-s-13 f-w-600">WhatsApp / Teléfono</label>
                                <input type="text" class="form-control py-2" id="crearPersonaWhatsapp" name="persona_whatsapp" placeholder="+51 9...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label f-s-13 f-w-600">Ciudad</label>
                                <input type="text" class="form-control py-2" id="crearCiudad" name="ciudad" value="PUNO" placeholder="Ciudad">
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Credenciales de Usuario -->
                    <h6 class="f-w-600 text-primary mb-3">
                        <i class="fa-solid fa-key me-1"></i> Credenciales y Datos de Acceso
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="crearNombreUsuario">
                                Nombre de Usuario (Login) <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control py-2" id="crearNombreUsuario" name="nombre_usuario" placeholder="ej. usuario.puno" required autocomplete="username">
                            <div class="form-text f-s-11">Solo letras minúsculas, números, puntos y guiones.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="crearCorreo">
                                Correo Electrónico <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control py-2" id="crearCorreo" name="correo_electronico" placeholder="correo@institucional.pe" required autocomplete="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="crearContrasena">
                                Contraseña Inicial <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control py-2" id="crearContrasena" name="contrasena" placeholder="Mínimo 8 caracteres" required autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" id="btnToggleClaveCrear" title="Mostrar/Ocultar">
                                    <i class="fa-solid fa-eye" id="iconoToggleClaveCrear"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="crearEstado">
                                Estado Inicial <span class="text-danger">*</span>
                            </label>
                            <select class="form-select py-2" id="crearEstado" name="estado">
                                <option value="ACTIVO" selected>ACTIVO (Habilitado)</option>
                                <option value="INACTIVO">INACTIVO (Suspendido)</option>
                                <option value="BLOQUEADO">BLOQUEADO</option>
                            </select>
                        </div>
                    </div>

                    <!-- Asignación Inicial de Roles -->
                    <div class="mt-4">
                        <label class="form-label f-w-600 text-dark mb-2">
                            <i class="fa-solid fa-shield-halved me-1 text-primary"></i> Asignar Roles de Acceso
                        </label>
                        <div class="border p-3 b-r-10 bg-light" id="contenedorRolesCrear">
                            <div class="text-muted f-s-12">Cargando catálogo de roles...</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top-0 py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn bg-gradient-primary text-white" id="btnGuardarUsuario">
                        <i class="fa-solid fa-floppy-disk me-2"></i> Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 2: EDITAR USUARIO
============================================================================== -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-dark text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalEditarUsuarioLabel">
                    <i class="fa-solid fa-user-pen me-2"></i> Modificar Datos de Usuario
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarUsuario">
                <input type="hidden" id="editarUsuarioId" name="id">
                <div class="modal-body p-4">
                    <div class="mb-3 p-2 bg-light b-r-8 d-flex justify-content-between align-items-center border">
                        <div>
                            <span class="f-s-11 text-muted text-uppercase">Nombre de Usuario:</span>
                            <div class="f-w-700 text-dark" id="editarLabelUsername">-</div>
                        </div>
                        <span class="badge bg-primary" id="editarBadgeId">ID: -</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="editarNombreCompleto">
                            Nombre Completo <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control py-2" id="editarNombreCompleto" name="nombre_completo" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="editarCorreo">
                            Correo Electrónico <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control py-2" id="editarCorreo" name="correo_electronico" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="editarWhatsapp">
                            Teléfono / WhatsApp
                        </label>
                        <input type="text" class="form-control py-2" id="editarWhatsapp" name="telefono_whatsapp" placeholder="+51 9...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="editarEstado">
                            Estado de la Cuenta
                        </label>
                        <select class="form-select py-2" id="editarEstado" name="estado">
                            <option value="ACTIVO">ACTIVO (Permite acceso al sistema)</option>
                            <option value="INACTIVO">INACTIVO (Sesión revocada e inhabilitada)</option>
                            <option value="BLOQUEADO">BLOQUEADO (Bloqueo administrativo)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top-0 py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn bg-gradient-primary text-white" id="btnGuardarEdicion">
                        <i class="fa-solid fa-floppy-disk me-2"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 3: GESTIÓN DE ROLES RBAC
============================================================================== -->
<div class="modal fade" id="modalRolesUsuario" tabindex="-1" aria-labelledby="modalRolesUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-gradient-primary text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalRolesUsuarioLabel">
                    <i class="fa-solid fa-shield-halved me-2"></i> Asignación de Roles RBAC
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="rolesModalUsuarioId">
                <div class="mb-3 pb-2 border-bottom">
                    <span class="f-s-12 text-muted">Usuario Seleccionado:</span>
                    <h5 class="f-w-700 text-dark mb-0 mt-1" id="rolesModalNombreUsuario">-</h5>
                </div>
                <p class="text-secondary f-s-13 mb-3">
                    Marque los roles que corresponden a este usuario. La matriz de permisos backend se actualizará inmediatamente sin recarga.
                </p>
                <div id="rolesModalCheckboxes" class="d-flex flex-column gap-2 border p-3 b-r-10 bg-light">
                    <!-- Inyectado dinámicamente -->
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0 py-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn bg-gradient-primary text-white" id="btnGuardarRolesModal">
                    <i class="fa-solid fa-check me-2"></i> Sincronizar Roles
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================================
     MODAL 4: RESTABLECER CONTRASEÑA
============================================================================== -->
<div class="modal fade" id="modalResetClave" tabindex="-1" aria-labelledby="modalResetClaveLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header bg-gradient-danger text-white b-r-top-16 py-3">
                <h5 class="modal-title f-w-600" id="modalResetClaveLabel">
                    <i class="fa-solid fa-key me-2"></i> Restablecer Contraseña
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="resetModalUsuarioId">
                <div class="alert alert-warning d-flex align-items-center gap-2 f-s-13 mb-3">
                    <i class="fa-solid fa-triangle-exclamation text-warning f-s-20"></i>
                    <div>
                        Esta acción modificará la clave del usuario <strong id="resetModalNombreUsuario"></strong> e invalidará inmediatamente todas sus sesiones activas.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label f-s-13 f-w-600" for="resetInputNuevaClave">
                        Nueva Contraseña (Opcional)
                    </label>
                    <input type="password" class="form-control py-2" id="resetInputNuevaClave" placeholder="Dejar vacío para autogenerar clave segura">
                    <div class="form-text f-s-12 text-muted">
                        Si deja el campo en blanco, el sistema generará una clave aleatoria temporal de alta entropía.
                    </div>
                </div>

                <div id="resultadoClaveGenerada" class="d-none alert alert-success p-3 b-r-10 mt-3">
                    <div class="f-s-12 text-success f-w-600 mb-1">Clave Temporal Generada (Copie antes de cerrar):</div>
                    <div class="d-flex align-items-center justify-content-between bg-white p-2 border b-r-8">
                        <code class="f-s-16 f-w-700 text-dark" id="textoClaveGenerada">-</code>
                        <button type="button" class="btn btn-sm btn-outline-success" id="btnCopiarClaveGenerada" title="Copiar al portapapeles">
                            <i class="fa-solid fa-copy"></i> Copiar
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0 py-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn bg-gradient-danger text-white" id="btnConfirmarReset">
                    <i class="fa-solid fa-rotate me-2"></i> Restablecer Clave
                </button>
            </div>
        </div>
    </div>
</div>
