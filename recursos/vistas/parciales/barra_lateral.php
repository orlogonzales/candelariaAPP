<?php
$contextoBarra = \Nucleo\Http\ContextoOperacion::actual();
$puedeVerOrg = false;
$puedeVerUsuarios = false;
$puedeVerConfigGeneral = false;
$puedeVerEdiciones = false;
$puedeVerClientes = false;
$puedeVerCrm = false;
$puedeAdministrarOrigenes = false;
$puedeVerCatalogo = false;
$puedeVerCotizaciones = false;
$puedeVerVentas = false;
if ($contextoBarra !== null && $contextoBarra->usuarioId !== null) {
    $authzBarra = new \Aplicacion\Autorizacion\AutorizacionServicio();
    $puedeVerOrg = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'organizacion.ver');
    $puedeVerUsuarios = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'usuarios.ver');
    $puedeVerConfigGeneral = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'configuracion_organizacion.ver')
        || $authzBarra->tienePermiso($contextoBarra->usuarioId, 'configuracion_plataforma.ver');
    $puedeVerEdiciones = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'ediciones.ver');
    $puedeVerClientes = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'clientes.ver');
    $puedeVerCrm = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'crm.oportunidades.ver');
    $puedeAdministrarOrigenes = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'crm.origenes.administrar');
    $puedeVerCatalogo = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'catalogo.ver');
    $puedeVerCotizaciones = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'cotizaciones.ver');
    $puedeVerVentas = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'ventas.ver');
    $puedeVerReservas = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'reservas.ver');
    $puedeVerOperaciones = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'operacion.ver');
    $puedeVerProveedores = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'proveedores.ver');
    $puedeVerRecursos = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'recursos.ver');
    $puedeVerEntregas = $authzBarra->tienePermiso($contextoBarra->usuarioId, 'entregas.despachar');
}
$seccionActual = $seccionActiva ?? 'dashboard';
$esConfig = in_array($seccionActual, ['usuarios', 'organizacion', 'configuracion_general'], true);
$esClientesCrm = in_array($seccionActual, ['clientes', 'crm_oportunidades', 'crm_origenes', 'cotizaciones', 'ventas'], true);
$esCatalogo = in_array($seccionActual, ['catalogo_items', 'catalogo_paquetes', 'catalogo_ofertas'], true);
$esOperaciones = in_array($seccionActual, ['reservas', 'operaciones', 'recursos', 'entregas'], true);
$esPrincipal = !$esConfig && !$esClientesCrm && !$esCatalogo && !$esOperaciones;

$repoOrgBarra = new \Aplicacion\Repositorios\OrganizacionRepositorio();
$orgOperativa = ($contextoBarra !== null && $contextoBarra->organizacionId !== null && $contextoBarra->organizacionId > 0)
    ? $repoOrgBarra->buscarPorId($contextoBarra->organizacionId)
    : null;
$isotipoOrg = $orgOperativa?->isotipoUrl ? url_subida($orgOperativa->isotipoUrl) : null;
$nombreOrgDisplay = $orgOperativa?->nombreComercial ?: 'CandelariaAPP';
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
                    <a href="#" class="nav-link <?= $esPrincipal ? 'active' : '' ?>" data-target="menuPrincipal" title="PANEL PRINCIPAL" aria-label="Panel Principal" data-bs-toggle="tooltip" data-bs-placement="right">
                        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link <?= $esClientesCrm ? 'active' : '' ?>" data-target="menuClientes" title="CLIENTES Y CRM" aria-label="Clientes y CRM" data-bs-toggle="tooltip" data-bs-placement="right">
                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link <?= $esCatalogo ? 'active' : '' ?>" data-target="menuCatalogo" title="CATÁLOGO COMERCIAL" aria-label="Catálogo Comercial" data-bs-toggle="tooltip" data-bs-placement="right">
                        <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link <?= $esOperaciones ? 'active' : '' ?>" data-target="menuOperaciones" title="RESERVAS Y OPERACIÓN" aria-label="Reservas y Operación" data-bs-toggle="tooltip" data-bs-placement="right">
                        <i class="fa-solid fa-route" aria-hidden="true"></i>
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
                    <ul class="main-menu" id="menuPrincipal" style="<?= $esPrincipal ? '' : 'display: none;' ?>">
                        <li class="no-sub">
                            <a href="<?= url_base() ?>" class="<?= $seccionActual === 'dashboard' ? 'active' : '' ?>">
                                <i class="fa-solid fa-gauge-high me-2 text-secondary"></i> Dashboard
                                <span class="badge bg-gradient-danger badge-dashboard badge-notification ms-2">V1</span>
                            </a>
                        </li>
                        <li class="no-sub">
                            <a href="<?= url_base('usuarios') ?>" id="navPrincipalUsuarios" class="<?= $seccionActual === 'usuarios' ? 'active' : '' ?>">
                                <i class="fa-solid fa-users-gear me-2 text-secondary"></i> Padrón de Usuarios
                            </a>
                        </li>
                        <?php if ($puedeVerEdiciones): ?>
                        <li class="no-sub">
                            <a href="<?= url_base('ediciones') ?>" id="navPrincipalEdiciones" class="<?= $seccionActual === 'ediciones' ? 'active' : '' ?>">
                                <i class="fa-solid fa-calendar-check me-2 text-secondary"></i> Ediciones Candelaria
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <!-- Menú: Clientes y CRM -->
                    <ul class="main-menu" id="menuClientes" style="<?= $esClientesCrm ? '' : 'display: none;' ?>">
                        <?php if ($puedeVerCrm || $puedeAdministrarOrigenes): ?>
                        <li>
                            <a aria-expanded="true" data-bs-toggle="collapse" href="#subCrm">
                                <i class="fa-solid fa-bullseye me-2 text-secondary"></i> CRM Comercial
                            </a>
                            <ul class="collapse show" id="subCrm">
                                <?php if ($puedeVerCrm): ?>
                                <li>
                                    <a href="<?= url_base('crm/oportunidades') ?>" class="<?= $seccionActual === 'crm_oportunidades' ? 'active' : '' ?>">
                                        <i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Oportunidades y Pipeline
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if ($puedeAdministrarOrigenes): ?>
                                <li>
                                    <a href="<?= url_base('crm/origenes') ?>" class="<?= $seccionActual === 'crm_origenes' ? 'active' : '' ?>">
                                        <i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Orígenes Comerciales
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <?php if ($puedeVerClientes): ?>
                        <li>
                            <a aria-expanded="true" data-bs-toggle="collapse" href="#subClientes">
                                <i class="fa-solid fa-address-book me-2 text-secondary"></i> Cartera Comercial
                            </a>
                            <ul class="collapse show" id="subClientes">
                                <li>
                                    <a href="<?= url_base('clientes') ?>" class="<?= $seccionActual === 'clientes' ? 'active' : '' ?>">
                                        <i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Padrón de Clientes
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <?php if ($puedeVerCotizaciones): ?>
                        <li>
                            <a aria-expanded="true" data-bs-toggle="collapse" href="#subCotizaciones">
                                <i class="fa-solid fa-file-invoice-dollar me-2 text-secondary"></i> Propuestas Comerciales
                            </a>
                            <ul class="collapse show" id="subCotizaciones">
                                <li>
                                    <a href="<?= url_base('cotizaciones') ?>" class="<?= $seccionActual === 'cotizaciones' ? 'active' : '' ?>">
                                        <i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Cotizaciones
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <?php if ($puedeVerVentas): ?>
                        <li>
                            <a aria-expanded="true" data-bs-toggle="collapse" href="#subVentas">
                                <i class="fa-solid fa-cash-register me-2 text-secondary"></i> Ventas Comerciales
                            </a>
                            <ul class="collapse show" id="subVentas">
                                <li>
                                    <a href="<?= url_base('ventas') ?>" class="<?= $seccionActual === 'ventas' ? 'active' : '' ?>">
                                        <i class="fa-solid fa-circle f-s-8 me-2 text-secondary"></i> Registro de Ventas
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <!-- Menú: Catálogo Comercial -->
                    <ul class="main-menu" id="menuCatalogo" style="<?= $esCatalogo ? '' : 'display: none;' ?>">
                        <?php if ($puedeVerCatalogo): ?>
                        <li class="no-sub">
                            <a href="<?= url_base('catalogo/items') ?>" class="<?= $seccionActual === 'catalogo_items' ? 'active' : '' ?>">
                                <i class="fa-solid fa-box-open me-2 text-secondary"></i> Ítems Comerciales
                            </a>
                        </li>
                        <li class="no-sub">
                            <a href="<?= url_base('catalogo/paquetes') ?>" class="<?= $seccionActual === 'catalogo_paquetes' ? 'active' : '' ?>">
                                <i class="fa-solid fa-boxes-packing me-2 text-secondary"></i> Paquetes
                            </a>
                        </li>
                        <li class="no-sub">
                            <a href="<?= url_base('catalogo/ofertas') ?>" class="<?= $seccionActual === 'catalogo_ofertas' ? 'active' : '' ?>">
                                <i class="fa-solid fa-tags me-2 text-secondary"></i> Ofertas por Edición
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <!-- Menú: Reservas y Operación (F2.6E) -->
                    <ul class="main-menu" id="menuOperaciones" style="<?= $esOperaciones ? '' : 'display: none;' ?>">
                        <?php if ($puedeVerReservas): ?>
                        <li class="no-sub">
                            <a href="<?= url_base('reservas') ?>" class="<?= $seccionActual === 'reservas' ? 'active' : '' ?>">
                                <i class="fa-solid fa-calendar-check me-2 text-secondary"></i> Reservas y Turnos
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($puedeVerOperaciones): ?>
                        <li class="no-sub">
                            <a href="<?= url_base('operaciones') ?>" class="<?= $seccionActual === 'operaciones' ? 'active' : '' ?>">
                                <i class="fa-solid fa-person-hiking me-2 text-secondary"></i> Salidas de Campo
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($puedeVerProveedores || $puedeVerRecursos): ?>
                        <li class="no-sub">
                            <a href="<?= url_base('operaciones/recursos') ?>" class="<?= $seccionActual === 'recursos' ? 'active' : '' ?>">
                                <i class="fa-solid fa-ship me-2 text-secondary"></i> Recursos y Proveedores
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($puedeVerEntregas): ?>
                        <li class="no-sub">
                            <a href="<?= url_base('operaciones/entregas') ?>" class="<?= $seccionActual === 'entregas' ? 'active' : '' ?>">
                                <i class="fa-solid fa-box-open me-2 text-secondary"></i> Despacho de Entregas
                            </a>
                        </li>
                        <?php endif; ?>
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
                        <?php if ($puedeVerConfigGeneral): ?>
                        <li class="no-sub">
                            <a href="<?= url_base('configuracion/general') ?>" id="navConfigGeneral" class="<?= $seccionActual === 'configuracion_general' ? 'active' : '' ?>">
                                <i class="fa-solid fa-sliders me-2 text-secondary"></i> Configuración General
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
