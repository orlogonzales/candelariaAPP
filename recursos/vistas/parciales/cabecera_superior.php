    <!-- Contenedor Principal de la Aplicación -->
    <div class="app-content">
        <!-- Cabecera Superior (Header Section) -->
        <header class="header-main">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-6 head-left">
                        <div class="d-flex align-items-center gap-3">
                            <span class="cursor-pointer main-side-toggle" title="Alternar Menú">
                               <i class="fa-solid fa-bars-staggered f-s-20 text-secondary"></i>
                            </span>
                            <div class="d-flex align-items-center gap-2">
                                <h4 class="txt-ellipsis-2 mb-0"><?= escapar_html($subtitulo ?? 'Panel de Gestión') ?></h4>
                                <span class="marca-plataforma-insignia d-none d-md-inline-block">CandelariaAPP V1</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 head-right">
                        <ul class="d-flex gap-3 align-items-center justify-content-end mb-0 list-unstyled">
                            <!-- Maximizar Pantalla -->
                            <li class="head-maximize-screen d-none d-sm-block">
                                <span class="h-40 w-40 d-flex-center b-r-50 head-icon cursor-pointer" title="Pantalla Completa">
                                    <i class="fa-solid fa-expand text-secondary"></i>
                                </span>
                            </li>

                            <!-- Alternador Dark / Light Mode (Preservado de Alina) -->
                            <li class="header-dark">
                                <div class="sun-logo h-40 w-40 d-flex-center b-r-50 head-icon cursor-pointer" title="Modo Claro / Oscuro">
                                    <i id="theme-icon" class="fa-solid fa-moon text-secondary"></i>
                                </div>
                            </li>

                            <!-- Notificaciones Operativas -->
                            <li>
                                <span class="h-40 w-40 d-flex-center b-r-50 head-icon position-relative cursor-pointer"
                                      data-bs-toggle="offcanvas" data-bs-target="#notificationCanvas"
                                      aria-controls="notificationCanvas" title="Notificaciones">
                                    <i class="fa-solid fa-bell text-secondary"></i>
                                    <span class="position-absolute top-0 end-0 p-1 bg-danger border border-light rounded-circle"></span>
                                </span>
                            </li>

                            <!-- Perfil de Usuario Dinámico -->
                            <?php
                            $contextoOp = \Nucleo\Http\ContextoOperacion::actual();
                            $nombreUsuarioDisplay = 'USUARIO';
                            $rolUsuarioDisplay = 'USUARIO';
                            $avatarUsuarioDisplay = url_activo('alina/images/avatar/01.png');

                            if ($contextoOp !== null && $contextoOp->usuarioId !== null) {
                                $repoUsr = new \Aplicacion\Repositorios\UsuarioRepositorio();
                                $usrActual = $repoUsr->buscarPorId($contextoOp->usuarioId);
                                if ($usrActual !== null) {
                                    $nombreUsuarioDisplay = $usrActual->nombreCompleto ?: $usrActual->nombreUsuario;
                                    if (!empty($usrActual->avatarUrl)) {
                                        $avatarUsuarioDisplay = $usrActual->avatarUrl;
                                    }
                                }

                                $repoRol = new \Aplicacion\Repositorios\RolRepositorio();
                                $rolesUsr = $repoRol->obtenerRolesDeUsuario($contextoOp->usuarioId);
                                if (!empty($rolesUsr)) {
                                    $rolUsuarioDisplay = $rolesUsr[0]->nombre;
                                }
                            }
                            ?>
                            <li class="d-flex align-items-center gap-2 ms-2">
                                <div class="dropdown">
                                    <a href="#" class="d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
                                        <img src="<?= escapar_html($avatarUsuarioDisplay) ?>" alt="Usuario" class="w-35 h-35 rounded-circle border">
                                        <div class="d-none d-xl-flex flex-column text-start">
                                            <span class="f-s-13 f-w-600 text-dark"><?= escapar_html($nombreUsuarioDisplay) ?></span>
                                            <span class="f-s-11 text-muted text-uppercase"><?= escapar_html($rolUsuarioDisplay) ?></span>
                                        </div>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                        <li><h6 class="dropdown-header text-uppercase">Sesión Activa</h6></li>
                                        <li><a class="dropdown-item" href="<?= url_base('usuarios') ?>"><i class="fa-solid fa-users-gear me-2 text-secondary"></i> Gestión de Usuarios</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="<?= url_base('logout') ?>" id="btnLogoutHeader"><i class="fa-solid fa-right-from-bracket me-2"></i> Cerrar Sesión</a></li>
                                    </ul>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <!-- Migas de Pan Dinámicas -->
        <div class="px-4 py-2 border-bottom bg-white d-flex justify-content-between align-items-center">
            <ul class="app-breadcrumbs mb-0 list-unstyled d-flex align-items-center gap-2 f-s-13">
                <li>
                    <a class="text-decoration-none text-muted" href="<?= url_base() ?>">
                        <i class="fa-solid fa-house me-1"></i> Inicio
                    </a>
                </li>
                <li class="text-muted">/</li>
                <li class="active text-dark f-w-600"><?= escapar_html($tituloSeccion ?? 'Dashboard') ?></li>
            </ul>
            <div class="f-s-12 text-muted d-none d-sm-block">
                <i class="fa-solid fa-building me-1"></i> Tenant: <strong>O.G. Estudio Creativo</strong>
            </div>
        </div>
