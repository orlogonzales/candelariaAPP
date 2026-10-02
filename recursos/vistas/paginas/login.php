<div class="auth-container">
    <div class="card form-container shadow-lg">
        <div class="card-body">
            <div class="text-center pt-3">
                <span class="bg-gradient-primary h-75 w-80 overflow-hidden d-flex-center b-r-10 mx-auto p-2 auth-logo text-white f-s-32 shadow-sm">
                    <i class="fa-solid fa-fire-flame-curved"></i>
                </span>
                <h3 class="f-w-600 mb-0 pt-3">
                    CANDELARIA<span class="text-primary">APP</span>
                </h3>
                <p class="text-dark-800 f-w-500 mb-1">
                    Plataforma de Gestión y Producción Audiovisual
                </p>
                <span class="badge bg-light text-secondary border">O.G. Estudio Creativo</span>
            </div>

            <form class="app-form mt-4" id="formLogin" autocomplete="on">
                <div class="mb-3">
                    <label class="form-label text-dark f-w-600" for="loginUsuario">
                        <i class="fa-solid fa-user me-1 text-secondary"></i> Usuario o Correo
                    </label>
                    <input class="form-control py-2" type="text" id="loginUsuario" name="login" placeholder="Ingrese su usuario o correo" required autofocus autocomplete="username">
                </div>

                <div class="mb-3">
                    <label class="form-label text-dark f-w-600" for="loginPassword">
                        <i class="fa-solid fa-lock me-1 text-secondary"></i> Contraseña
                    </label>
                    <div class="input-group">
                        <input class="form-control py-2" type="password" id="loginPassword" name="password" placeholder="Ingrese su contraseña" required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" id="btnToggleClave" title="Mostrar/Ocultar contraseña">
                            <i class="fa-solid fa-eye" id="iconoToggleClave"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="recordarSesion" name="recordar">
                        <label class="form-check-label f-s-14" for="recordarSesion">
                            Recordar sesión
                        </label>
                    </div>
                    <span class="f-s-12 text-muted">Acceso Corporativo</span>
                </div>

                <div class="mb-3">
                    <button type="submit" id="btnSubmitLogin" class="btn bg-gradient-primary w-100 btn-lg b-r-20">
                        <i class="fa-solid fa-right-to-bracket me-2"></i> Iniciar Sesión
                    </button>
                </div>
            </form>

            <div class="text-center pt-2">
                <p class="text-muted f-s-12 mb-0">
                    &copy; <?= date('Y') ?> CandelariaAPP &bull; Festividad de la Virgen de la Candelaria, Puno.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const formLogin = document.getElementById('formLogin');
    const btnSubmit = document.getElementById('btnSubmitLogin');
    const inputUsuario = document.getElementById('loginUsuario');
    const inputClave = document.getElementById('loginPassword');
    const btnToggle = document.getElementById('btnToggleClave');
    const iconoToggle = document.getElementById('iconoToggleClave');

    // Alternar visibilidad de contraseña
    if (btnToggle && inputClave && iconoToggle) {
        btnToggle.addEventListener('click', function () {
            const esPassword = inputClave.type === 'password';
            inputClave.type = esPassword ? 'text' : 'password';
            iconoToggle.className = esPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    }

    // Interceptar submit
    if (formLogin) {
        formLogin.addEventListener('submit', async function (e) {
            e.preventDefault();

            const login = inputUsuario.value.trim();
            const password = inputClave.value;

            if (!login || !password) {
                CandelariaUI.notificarError('Por favor complete todos los campos.');
                return;
            }

            CandelariaUI.procesarBoton(btnSubmit, 'Verificando credenciales...');

            try {
                const urlBase = window.CANDELARIA_BASE_URL || '';
                const respuesta = await fetch(urlBase + '/api/v1/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ login, password })
                });

                const resultado = await respuesta.json();

                if (!respuesta.ok || !resultado.exito) {
                    CandelariaUI.restaurarBoton(btnSubmit);
                    CandelariaUI.notificarError(resultado.mensaje || 'Error al iniciar sesión.');
                    inputClave.value = '';
                    inputClave.focus();
                    return;
                }

                // Autenticación exitosa -> redirección inmediata al Dashboard
                window.location.href = urlBase ? urlBase + '/' : '/';
            } catch (err) {
                console.error('[Login Error]', err);
                CandelariaUI.restaurarBoton(btnSubmit);
                CandelariaUI.notificarError('Error de conexión con el servidor. Intente nuevamente.');
            }
        });
    }
});
</script>
