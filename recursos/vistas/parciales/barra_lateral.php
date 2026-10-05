<?php
$contextoBarra = \Nucleo\Http\ContextoOperacion::actual();
$puedeVerOrg = false;
$puedeVerUsuarios = false;
if ($contextoBarra !== null && $contextoBarra->usuarioId !== null) {
    $authzBarra = new \Aplicacion\Autorizacion\AutorizacionServicio();
    $puedeVerOrg = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'organizacion.ver');
    $puedeVerUsuarios = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'usuarios.ver');
}
$seccionActual = $seccionActiva ?? 'dashboard';
$esConfig = in_array($seccionActual, ['usuarios', 'organizacion'], true);

$repoOrgBarra = new \Aplicacion\Repositorios\OrganizacionRepositorio();
$orgOperativa = $repoOrgBarra->buscarPorId($contextoBarra->organizacionId ?? 10000);
$isotipoOrg = $orgOperativa?->isotipoUrl ? url_subida($orgOperativa->isotipoUrl) : null;
$nombreOrgDisplay = $orgOperativa?->nombreComercial ?: 'O.G. Estudio Creativo';
?>
    <!-- Navegación y Barras Laterales (Alina + CandelariaAPP) -->
    <nav class="app-navbar">
        <!-- 1. Barra Lateral Compacta (Semi-Side-Nav) con Tooltips Funcionales -->
        <div class="semi-side-nav">
            <div class="py-4">
               <span class="bg-white text-dark h-40 w-40 d-flex-center b-r-12 mx-auto shadow-sm overflow-hidden" title="<?= escapar_html($nombreOrgDisplay) ?>">
                   <?php if (!empty($isotipoOrg)): ?>
                       <img src="<?= escapar_html($isotipoOrg) ?>" alt="Isotipo" class="w-100 h-100 object-fit-contain p-1">
                   <?php else: ?>
                       <span class="f-w-700 f-s-16">OG</span>
                   <?php endif; ?>
               </span>
            </div>

            <ul class="navbar-menu-list" role="tablist">
                <li class="nav-item">
                    <a href="#" class="nav-link <?= !$esConfig ? 'active' : '' ?>" data-target="menuPrincipal" title="PANEL PRINCIPAL" aria-label="Panel Principal" data-bs-toggle="tooltip" data-bs-placement="right">
                        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link" data-target="menuClientes" title="CLIENTES Y CRM" aria-label="Clientes y CRM" data-bs-toggle="tooltip" data-bs-placement="right">
                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link" data-target="menuOperaciones" title="OPERACIONES Y COBERTURA" aria-label="Operaciones y Cobertura" data-bs-toggle="tooltip" data-bs-placement="right">
                        <i class="fa-solid fa-video" aria-hidden="true"></i>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link" data-target="menuAgenda" title="AGENDA Y CALENDARIO" aria-label="Agenda y Calendario" data-bs-toggle="tooltip" data-bs-placement="right">
                        <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link" data-target="menuPagos" title="PAGOS Y CAJA" aria-label="Pagos y Caja" data-bs-toggle="tooltip" data-bs-placement="right">
                        <i class="fa-solid fa-cash-register" aria-hidden="true"></i>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link" data-target="menuPostproduccion" title="POSTPRODUCCIÓN Y ENTREGAS" aria-label="Postproducción y Entregas" data-bs-toggle="tooltip" data-bs-placement="right">
                        <i class="fa-solid fa-photo-film" aria-hidden="true"></i>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link <?= $esConfig ? 'active' : '' ?>" data-target="menuConfiguracion" title="CONFIGURACIÓN Y PLATAFORMA" aria-label="Configuración y Plataforma" data-bs-toggle="tooltip" data-bs-placement="right">
                        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                    </a>
                </li>
            </ul>

            <span class="bg-primary-800 h-45 w-45 d-flex-center b-r-30 position-relative mx-auto" title="Usuario de Gestión">
               <img alt="avatar" class="img-fluid b-r-30" src="<?= url_activo('alina/images/avatar/01.png') ?>">
               <span class="position-absolute top-0 end-0 p-1 bg-gradient-success border border-light rounded-circle"></span>
            </span>
        </div>

        <!-- 2. Barra Lateral Desplegable (Main-Side-Nav) -->
        <div class="main-side-nav">
            <div>
                <a class="logo d-inline-block px-3 py-2 text-decoration-none" href="<?= url_base() ?>">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-danger f-s-24"><i class="fa-solid fa-fire-flame-curved"></i></span>
                        <div class="d-flex flex-column">
                            <span class="f-s-18 f-w-700 text-dark tracking-wide">CANDELARIA<span class="text-danger">APP</span></span>
                            <span class="f-s-11 text-muted text-uppercase"><?= escapar_html($nombreOrgDisplay) ?></span>
                        </div>
                    </div>
                </a>
                <span class="w-30 h-30 d-none bg-gradient-danger b-r-8 cursor-pointer side-toggle">
                    <i class="fa-solid fa-xmark f-s-18"></i>
                </span>
                <div class="side-search p-3">
                    <div class="position-relative">
                        <input aria-label="Buscar" class="form-control py-2 b-r-18" placeholder="Buscar módulo..." type="search">
                        <i class="fa-solid fa-magnifying-glass f-s-16 text-secondary position-absolute end-0 top-50 translate-middle-y me-3"></i>
                    </div>
                </div>
            </div>

            <div class="nav-wrapper app-scroll app-simple-bar">
                <div class="main-side-menu">
                    <!-- Menú: Principal -->
                    <ul class="main-menu" id="menuPrincipal">
                        <li class="no-sub">
                            <a href="<?= url_base() ?>" class="active">
                                <i class="fa-solid fa-gauge-high me-2 text-secondary"></i> Dashboard
                                <span class="badge bg-gradient-danger badge-dashboard badge-notification ms-2">V1</span>
                            </a>
                        </li>
                        <li class="no-sub">
                            <a href="<?= url_base('usuarios') ?>" id="navPrincipalUsuarios">
                                <i class="fa-solid fa-users-gear me-2 text-secondary"></i> Padrón de Usuarios
                            </a>
                        </li>
                        <li>
                            <a aria-expanded="false" data-bs-toggle="collapse" href="#subEdiciones">
                                <i class="fa-solid fa-calendar-check me-2 text-secondary"></i> Ediciones Candelaria
                            </a>
                            <ul class="collapse" id="subEdiciones">
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Candelaria 2027 (Activa)</a></li>
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Historial de Ediciones</a></li>
                            </ul>
                        </li>
                    </ul>

                    <!-- Menú: Clientes y CRM -->
                    <ul class="main-menu" id="menuClientes" style="display: none;">
                        <li>
                            <a aria-expanded="true" data-bs-toggle="collapse" href="#subCrm">
                                <i class="fa-solid fa-user-plus me-2 text-secondary"></i> Pre-Candelaria / CRM
                            </a>
                            <ul class="collapse show" id="subCrm">
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Prospectos</a></li>
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Cotizaciones</a></li>
                            </ul>
                        </li>
                        <li>
                            <a aria-expanded="false" data-bs-toggle="collapse" href="#subClientes">
                                <i class="fa-solid fa-users me-2 text-secondary"></i> Clientes
                            </a>
                            <ul class="collapse" id="subClientes">
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Padrón Oficial</a></li>
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Conjuntos / Bloques</a></li>
                            </ul>
                        </li>
                    </ul>

                    <!-- Menú: Operaciones -->
                    <ul class="main-menu" id="menuOperaciones" style="display: none;">
                        <li>
                            <a aria-expanded="true" data-bs-toggle="collapse" href="#subOperaciones">
                                <i class="fa-solid fa-video me-2 text-secondary"></i> Cobertura en Campo
                            </a>
                            <ul class="collapse show" id="subOperaciones">
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Asignación de Equipos</a></li>
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Puntos de Veneración</a></li>
                            </ul>
                        </li>
                        <li>
                            <a aria-expanded="false" data-bs-toggle="collapse" href="#subActivos">
                                <i class="fa-solid fa-id-badge me-2 text-secondary"></i> Identificación y Activos
                            </a>
                            <ul class="collapse" id="subActivos">
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Pines y Credenciales</a></li>
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Trazabilidad de Retorno</a></li>
                            </ul>
                        </li>
                    </ul>

                    <!-- Menú: Agenda -->
                    <ul class="main-menu" id="menuAgenda" style="display: none;">
                        <li class="no-sub">
                            <a href="#"><i class="fa-solid fa-calendar-days me-2 text-secondary"></i> Citas y Reuniones Puno</a>
                        </li>
                        <li class="no-sub">
                            <a href="#"><i class="fa-solid fa-masks-theater me-2 text-secondary"></i> Cronograma Folclórico</a>
                        </li>
                    </ul>

                    <!-- Menú: Pagos -->
                    <ul class="main-menu" id="menuPagos" style="display: none;">
                        <li>
                            <a aria-expanded="true" data-bs-toggle="collapse" href="#subCaja">
                                <i class="fa-solid fa-cash-register me-2 text-secondary"></i> Pagos y Caja
                            </a>
                            <ul class="collapse show" id="subCaja">
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Registro de Pagos</a></li>
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Pasarelas y Comisiones</a></li>
                            </ul>
                        </li>
                    </ul>

                    <!-- Menú: Postproducción -->
                    <ul class="main-menu" id="menuPostproduccion" style="display: none;">
                        <li>
                            <a aria-expanded="true" data-bs-toggle="collapse" href="#subEntregas">
                                <i class="fa-solid fa-photo-film me-2 text-secondary"></i> Entregas y Galerías
                            </a>
                            <ul class="collapse show" id="subEntregas">
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Galerías Audiovisuales</a></li>
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Enlaces de Descarga</a></li>
                            </ul>
                        </li>
                    </ul>

                    <!-- Menú: Configuración -->
                    <ul class="main-menu" id="menuConfiguracion" style="<?= $esConfig ? 'display: block;' : 'display: none;' ?>">
                        <?php if ($puedeVerOrg): ?>
                        <li class="no-sub">
                            <a href="<?= url_base('configuracion/organizacion') ?>" id="navConfigOrganizacion" class="<?= $seccionActual === 'organizacion' ? 'active' : '' ?>">
                                <i class="fa-solid fa-building me-2 text-secondary"></i> Organización
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($puedeVerUsuarios): ?>
                        <li>
                            <a aria-expanded="true" data-bs-toggle="collapse" href="#subSeguridad">
                                <i class="fa-solid fa-user-shield me-2 text-secondary"></i> Control de Acceso
                            </a>
                            <ul class="collapse show" id="subSeguridad">
                                <li>
                                    <a href="<?= url_base('usuarios') ?>" id="navConfigUsuarios" class="<?= $seccionActual === 'usuarios' ? 'active' : '' ?>">
                                        <i class="fa-solid fa-users-gear me-2 text-secondary"></i> Padrón de Usuarios
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a aria-expanded="true" data-bs-toggle="collapse" href="#subConfig">
                                <i class="fa-solid fa-sliders me-2 text-secondary"></i> Parámetros
                            </a>
                            <ul class="collapse show" id="subConfig">
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Catálogo Comercial</a></li>
                                <li><a href="#"><i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Auditoría de Operaciones</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
