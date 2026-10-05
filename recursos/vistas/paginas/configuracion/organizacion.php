<?php
/**
 * Vista Oficial de la Ficha de Organización y Branding (F1.2B).
 * Alineada 100% con los componentes y estilos de Alina (admin-dashboard).
 */
use Nucleo\Http\ContextoOperacion;
use Aplicacion\Autorizacion\AutorizacionServicio;

$contexto = ContextoOperacion::actual();
$puedeEditarOrg = false;
$puedeEditarBranding = false;

if ($contexto !== null && $contexto->usuarioId !== null) {
    $authz = new AutorizacionServicio();
    $puedeEditarOrg = $authz->tienePermiso($contexto->usuarioId, 'organizacion.editar');
    $puedeEditarBranding = $authz->tienePermiso($contexto->usuarioId, 'branding.editar');
}
?>

<!-- Encabezado de la Ficha de Organización -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="f-w-700 text-dark mb-1">
            <i class="fa-solid fa-building text-primary me-2"></i> Ficha de Organización
        </h3>
        <p class="text-secondary mb-0">
            Administración institucional, ubicación fiscal y activos de branding de la empresa operadora.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-md" id="btnRecargarFicha" title="Actualizar datos">
                <i class="fa-solid fa-rotate me-1"></i> Actualizar
            </button>
            <?php if ($puedeEditarOrg): ?>
            <button type="button" class="btn bg-gradient-primary btn-md text-white shadow-sm" id="btnAbrirModalEditar">
                <i class="fa-solid fa-pen-to-square me-2"></i> Editar Ficha
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Alerta de Error de Conexión / Carga (Oculta por defecto) -->
<div id="alertaErrorCarga" class="alert alert-danger d-none align-items-center justify-content-between p-3 b-r-12 shadow-sm mb-4" role="alert">
    <div class="d-flex align-items-center gap-3">
        <span class="f-s-24 text-danger"><i class="fa-solid fa-circle-exclamation"></i></span>
        <div>
            <h6 class="mb-0 f-w-600 text-danger" id="textoErrorCargaTitulo">Error al cargar la ficha</h6>
            <p class="mb-0 text-secondary f-s-13" id="textoErrorCargaDetalle">No fue posible recuperar los datos institucionales.</p>
        </div>
    </div>
    <button type="button" class="btn btn-outline-danger btn-sm" id="btnReintentarCarga">
        <i class="fa-solid fa-rotate-right me-1"></i> Reintentar
    </button>
</div>

<!-- Skeleton Loader Inicial -->
<div id="skeletonOrganizacion" class="row g-4">
    <div class="col-xl-4 col-lg-5">
        <div class="card border-0 shadow-sm b-r-12 h-100">
            <div class="card-body p-4 text-center">
                <div class="skeleton-shimmer skeleton-circle mx-auto mb-3" style="width: 100px; height: 100px;"></div>
                <div class="skeleton-shimmer skeleton-line lg w-75 mx-auto mb-2"></div>
                <div class="skeleton-shimmer skeleton-line sm w-50 mx-auto mb-4"></div>
                <div class="app-divider-v dotted mb-3"></div>
                <div class="skeleton-shimmer skeleton-line w-100 mb-2"></div>
                <div class="skeleton-shimmer skeleton-line w-90 mb-2"></div>
                <div class="skeleton-shimmer skeleton-line w-80 mb-2"></div>
            </div>
        </div>
    </div>
    <div class="col-xl-8 col-lg-7">
        <div class="row g-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm b-r-12">
                    <div class="card-body p-4">
                        <div class="skeleton-shimmer skeleton-line lg w-50 mb-4"></div>
                        <div class="row g-3">
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-line w-100 mb-2"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-line w-100 mb-2"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-line w-100 mb-2"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-line w-100 mb-2"></div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card border-0 shadow-sm b-r-12">
                    <div class="card-body p-4">
                        <div class="skeleton-shimmer skeleton-line lg w-50 mb-4"></div>
                        <div class="row g-3">
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-line w-100 mb-2"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-line w-100 mb-2"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-line w-100 mb-2"></div></div>
                            <div class="col-md-6"><div class="skeleton-shimmer skeleton-line w-100 mb-2"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor Real de la Ficha de Organización (Oculto hasta completar fetch) -->
<div id="contenidoOrganizacion" class="d-none">
    <div class="row g-4">
        <!-- Columna Izquierda: Perfil Principal e Identidad Visual (Branding) -->
        <div class="col-xl-4 col-lg-5">
            <!-- Tarjeta de Identidad y Estado -->
            <div class="card border-0 shadow-sm b-r-12 mb-4">
                <div class="card-body p-4 text-center">
                    <div class="position-relative d-inline-block mb-3">
                        <div id="contenedorLogoPreview" class="w-100 h-100 d-flex-center b-r-16 bg-light border p-2" style="min-width: 140px; min-height: 140px; max-width: 220px; max-height: 220px; margin: 0 auto; overflow: hidden;">
                            <img id="imgLogoDisplay" src="" alt="Logotipo Institucional" class="img-fluid object-fit-contain d-none" style="max-height: 120px;">
                            <div id="placeholderLogoDisplay" class="d-flex flex-column align-items-center text-muted">
                                <i class="fa-solid fa-image f-s-40 mb-2 text-secondary"></i>
                                <span class="f-s-12">Sin Logotipo</span>
                            </div>
                        </div>
                        <?php if ($puedeEditarBranding): ?>
                        <button type="button" class="btn btn-sm btn-primary rounded-circle position-absolute bottom-0 end-0 shadow" id="btnCambiarLogo" title="Cambiar Logotipo" style="width: 38px; height: 38px;">
                            <i class="fa-solid fa-camera"></i>
                        </button>
                        <?php endif; ?>
                    </div>

                    <h4 class="f-w-700 text-dark mb-1" id="displayNombreComercial">-</h4>
                    <p class="text-secondary f-s-13 mb-2" id="displayRazonSocial">-</p>
                    <div class="mb-3">
                        <span class="badge bg-success-subtle text-success px-3 py-2 b-r-8 f-s-12" id="displayEstadoBadge">ACTIVO</span>
                        <span class="badge bg-light text-dark border px-3 py-2 b-r-8 f-s-12 ms-1" id="displayCodigoTenant">og_estudio</span>
                    </div>

                    <div class="app-divider-v dotted my-3"></div>

                    <!-- Isotipo Institucional -->
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light b-r-12">
                        <div class="d-flex align-items-center gap-3">
                            <div id="contenedorIsotipoPreview" class="w-50 h-50 d-flex-center b-r-10 bg-white border p-1 shadow-sm overflow-hidden flex-shrink-0">
                                <img id="imgIsotipoDisplay" src="" alt="Isotipo" class="img-fluid object-fit-contain d-none" style="max-height: 40px;">
                                <i id="placeholderIsotipoDisplay" class="fa-solid fa-shapes f-s-20 text-secondary"></i>
                            </div>
                            <div class="text-start">
                                <h6 class="mb-0 f-s-14 f-w-600">Isotipo / Favicon</h6>
                                <p class="text-secondary mb-0 f-s-11">Símbolo compacto de marca</p>
                            </div>
                        </div>
                        <?php if ($puedeEditarBranding): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm b-r-8" id="btnCambiarIsotipo" title="Cambiar Isotipo">
                            <i class="fa-solid fa-upload me-1"></i> Subir
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tarjeta de Políticas de Identidad -->
            <div class="card border-0 shadow-sm b-r-12">
                <div class="card-header bg-transparent border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="f-w-700 text-dark f-s-16 mb-0">
                        <i class="fa-solid fa-shield-halved text-primary me-2"></i> Políticas de Branding
                    </h5>
                </div>
                <div class="card-body px-4 py-3">
                    <ul class="list-unstyled mb-0 f-s-13 text-secondary">
                        <li class="mb-2 d-flex align-items-start gap-2">
                            <i class="fa-solid fa-check text-success mt-1"></i>
                            <span>Formatos autorizados: <strong>PNG, JPG, JPEG, WEBP</strong>.</span>
                        </li>
                        <li class="mb-2 d-flex align-items-start gap-2">
                            <i class="fa-solid fa-check text-success mt-1"></i>
                            <span>Tamaño máximo permitido: <strong>2 MB</strong> por archivo.</span>
                        </li>
                        <li class="mb-2 d-flex align-items-start gap-2">
                            <i class="fa-solid fa-circle-exclamation text-warning mt-1"></i>
                            <span>Formato <strong>SVG</strong> deshabilitado temporalmente por sanitización vectorial.</span>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-database text-info mt-1"></i>
                            <span>Almacenamiento en sistema de archivos; cero binarios en MySQL.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Secciones Informativas Detalladas -->
        <div class="col-xl-8 col-lg-7">
            <!-- 1. Datos Institucionales y Fiscales -->
            <div class="card border-0 shadow-sm b-r-12 mb-4">
                <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="f-w-700 text-dark f-s-16 mb-0">
                        <i class="fa-solid fa-id-card text-primary me-2"></i> Datos Institucionales y Fiscales
                    </h5>
                    <span class="badge bg-light text-muted border px-2 py-1 f-s-11">Paso 1</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Razón Social</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaRazonSocial">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Nombre Comercial</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaNombreComercial">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Tipo de Documento</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaTipoDocumento">RUC (Registro Único de Contribuyentes)</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Número de Documento</label>
                            <p class="f-w-600 text-primary f-s-15 mb-0 font-monospace" id="displayFichaNumeroDocumento">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Localización y Domicilio Fiscal -->
            <div class="card border-0 shadow-sm b-r-12 mb-4">
                <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="f-w-700 text-dark f-s-16 mb-0">
                        <i class="fa-solid fa-map-location-dot text-primary me-2"></i> Ubicación y Domicilio Fiscal
                    </h5>
                    <span class="badge bg-light text-muted border px-2 py-1 f-s-11">Paso 2</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Dirección Fiscal / Sede Operativa</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaDireccion">-</p>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">País</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaPais">PERÚ (PE)</p>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Departamento</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaDepartamento">-</p>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Provincia</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaProvincia">-</p>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Distrito</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaDistrito">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Canales de Contacto y Representante -->
            <div class="card border-0 shadow-sm b-r-12">
                <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="f-w-700 text-dark f-s-16 mb-0">
                        <i class="fa-solid fa-address-book text-primary me-2"></i> Contacto y Representación
                    </h5>
                    <span class="badge bg-light text-muted border px-2 py-1 f-s-11">Paso 3</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">WhatsApp Comercial</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaWhatsapp">
                                <i class="fa-brands fa-whatsapp text-success me-1"></i> <span id="valFichaWhatsapp">-</span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Correo Electrónico</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaCorreo">
                                <i class="fa-solid fa-envelope text-secondary me-1"></i> <span id="valFichaCorreo">-</span>
                            </p>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Sitio Web</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaSitioWeb">
                                <i class="fa-solid fa-globe text-info me-1"></i> <a href="#" target="_blank" id="linkFichaSitioWeb" class="text-decoration-none">-</a>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Contacto / Representante</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaContactoNombre">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted f-s-12 text-uppercase mb-1">Cargo del Contacto</label>
                            <p class="f-w-600 text-dark f-s-15 mb-0" id="displayFichaContactoCargo">-</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeEditarOrg): ?>
<!-- Modal Alina para Edición de Información de la Organización -->
<div class="modal fade" id="modalEditarOrganizacion" tabindex="-1" aria-labelledby="modalEditarOrganizacionLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow b-r-16">
            <div class="modal-header bg-light py-3 px-4 border-bottom">
                <h5 class="modal-title f-w-700 text-dark" id="modalEditarOrganizacionLabel">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i> Editar Ficha de Organización
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarOrganizacion" class="app-form" novalidate>
                <input type="hidden" name="_csrf_token" id="csrfTokenOrgForm" value="<?= escapar_html(Nucleo\Seguridad\ProtectorCsrf::obtenerOCrearToken()) ?>">
                <div class="modal-body p-4">
                    <div id="alertaErrorModalOrg" class="alert alert-danger d-none py-2 px-3 mb-3 f-s-13 b-r-8"></div>

                    <!-- Grupo 1: Datos Institucionales -->
                    <h6 class="f-w-700 text-primary text-uppercase f-s-12 mb-3">1. Datos Institucionales</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="inputEditNombreComercial" class="form-label f-s-13 f-w-600">Nombre Comercial <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-store"></i></span>
                                <input type="text" class="form-control" id="inputEditNombreComercial" name="nombre_comercial" required maxlength="150" placeholder="Ej. O.G. ESTUDIO CREATIVO">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="inputEditRazonSocial" class="form-label f-s-13 f-w-600">Razón Social</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-building"></i></span>
                                <input type="text" class="form-control" id="inputEditRazonSocial" name="razon_social" maxlength="200" placeholder="Ej. O.G. ESTUDIO CREATIVO S.A.C.">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="selectEditTipoDocumento" class="form-label f-s-13 f-w-600">Tipo de Documento</label>
                            <select class="form-select" id="selectEditTipoDocumento" name="tipo_documento_id">
                                <option value="2" selected>RUC</option>
                                <option value="1">DNI</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="inputEditNumeroDocumento" class="form-label f-s-13 f-w-600">Número de Documento</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-id-card"></i></span>
                                <input type="text" class="form-control" id="inputEditNumeroDocumento" name="numero_documento" maxlength="30" placeholder="Ej. 20601234567">
                            </div>
                        </div>
                    </div>

                    <!-- Grupo 2: Ubicación y Domicilio Fiscal -->
                    <h6 class="f-w-700 text-primary text-uppercase f-s-12 mb-3">2. Ubicación y Domicilio Fiscal</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label for="inputEditDireccion" class="form-label f-s-13 f-w-600">Dirección</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-location-dot"></i></span>
                                <input type="text" class="form-control" id="inputEditDireccion" name="direccion" maxlength="255" placeholder="Ej. JR. LIMA 450 - CERCADO">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label for="inputEditPais" class="form-label f-s-13 f-w-600">Código País (ISO)</label>
                            <input type="text" class="form-control text-uppercase" id="inputEditPais" name="codigo_pais" maxlength="2" value="PE" required>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label for="inputEditDepartamento" class="form-label f-s-13 f-w-600">Departamento</label>
                            <input type="text" class="form-control" id="inputEditDepartamento" name="departamento" maxlength="100" placeholder="PUNO">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label for="inputEditProvincia" class="form-label f-s-13 f-w-600">Provincia</label>
                            <input type="text" class="form-control" id="inputEditProvincia" name="provincia" maxlength="100" placeholder="PUNO">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label for="inputEditDistrito" class="form-label f-s-13 f-w-600">Distrito</label>
                            <input type="text" class="form-control" id="inputEditDistrito" name="distrito" maxlength="100" placeholder="PUNO">
                        </div>
                    </div>

                    <!-- Grupo 3: Contacto y Representante -->
                    <h6 class="f-w-700 text-primary text-uppercase f-s-12 mb-3">3. Contacto y Representación</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="inputEditWhatsapp" class="form-label f-s-13 f-w-600">Teléfono WhatsApp</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-success"><i class="fa-brands fa-whatsapp"></i></span>
                                <input type="text" class="form-control" id="inputEditWhatsapp" name="telefono_whatsapp" maxlength="30" placeholder="+51 951 123 456">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="inputEditTelefono" class="form-label f-s-13 f-w-600">Teléfono Fijo / Alternativo</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-phone"></i></span>
                                <input type="text" class="form-control" id="inputEditTelefono" name="telefono_contacto" maxlength="50" placeholder="(051) 368940">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="inputEditCorreo" class="form-label f-s-13 f-w-600">Correo Electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" class="form-control" id="inputEditCorreo" name="correo_contacto" maxlength="150" placeholder="contacto@empresa.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="inputEditSitioWeb" class="form-label f-s-13 f-w-600">Sitio Web</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-globe"></i></span>
                                <input type="url" class="form-control" id="inputEditSitioWeb" name="sitio_web" maxlength="200" placeholder="https://www.empresa.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="inputEditContactoNombre" class="form-label f-s-13 f-w-600">Nombre del Contacto</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-user-tie"></i></span>
                                <input type="text" class="form-control" id="inputEditContactoNombre" name="contacto_nombre" maxlength="150" placeholder="Ej. ORLANDO GONZALES">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="inputEditContactoCargo" class="form-label f-s-13 f-w-600">Cargo del Contacto</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-briefcase"></i></span>
                                <input type="text" class="form-control" id="inputEditContactoCargo" name="contacto_cargo" maxlength="100" placeholder="Ej. DIRECTOR GENERAL">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3 px-4 border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn bg-gradient-primary text-white" id="btnGuardarOrganizacion">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($puedeEditarBranding): ?>
<!-- Modal Alina para Carga / Reemplazo de Branding (Logotipo o Isotipo) -->
<div class="modal fade" id="modalSubirBranding" tabindex="-1" aria-labelledby="modalSubirBrandingLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow b-r-16">
            <div class="modal-header bg-light py-3 px-4 border-bottom">
                <h5 class="modal-title f-w-700 text-dark" id="modalSubirBrandingLabel">
                    <i class="fa-solid fa-upload text-primary me-2"></i> Actualizar Identidad Visual
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formSubirBranding" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="_csrf_token" id="csrfTokenBrandingForm" value="<?= escapar_html(Nucleo\Seguridad\ProtectorCsrf::obtenerOCrearToken()) ?>">
                <input type="hidden" name="tipo_branding" id="inputTipoBrandingModal" value="logo">
                <div class="modal-body p-4 text-center">
                    <div id="alertaErrorModalBranding" class="alert alert-danger d-none py-2 px-3 mb-3 f-s-13 b-r-8 text-start"></div>

                    <!-- Contenedor de Previsualización Local -->
                    <div class="mb-3">
                        <div id="contenedorModalPreview" class="d-flex-center b-r-12 bg-light border p-3 mx-auto overflow-hidden" style="width: 180px; height: 180px;">
                            <img id="imgModalPreview" src="" alt="Previsualización" class="img-fluid object-fit-contain d-none" style="max-height: 150px;">
                            <div id="placeholderModalPreview" class="text-muted">
                                <i class="fa-solid fa-cloud-arrow-up f-s-40 mb-2 text-secondary"></i>
                                <p class="mb-0 f-s-12">Seleccione una imagen</p>
                            </div>
                        </div>
                    </div>

                    <!-- Input de Archivo -->
                    <div class="mb-3 text-start">
                        <label for="inputArchivoBranding" class="form-label f-s-13 f-w-600" id="labelArchivoBranding">Archivo de imagen</label>
                        <input class="form-control" type="file" id="inputArchivoBranding" name="archivo" accept=".png,.jpg,.jpeg,.webp" required>
                        <div class="form-text f-s-11 text-muted">Formatos autorizados: PNG, JPG, WEBP. Tamaño máximo: 2 MB. SVG no admitido.</div>
                    </div>

                    <!-- Metadata de archivo seleccionado -->
                    <div id="infoArchivoSeleccionado" class="d-none text-start p-2 bg-light b-r-8 f-s-12 text-secondary mb-2">
                        <div><strong>Archivo:</strong> <span id="valNombreArchivo">-</span></div>
                        <div><strong>Tamaño:</strong> <span id="valTamanoArchivo">-</span></div>
                        <div><strong>Tipo:</strong> <span id="valTipoArchivo">-</span></div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3 px-4 border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn bg-gradient-primary text-white" id="btnConfirmarSubidaBranding" disabled>
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> Subir y Aplicar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
