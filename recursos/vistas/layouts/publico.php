<?php
/**
 * ==============================================================================
 * CANDELARIAAPP — LAYOUT BASE PÚBLICO SOBERANO (FASE 2.9C)
 * INDEPENDIENTE DE ALINA BACKOFFICE — RESPONSIVE, SEGURO Y ALTA CONVERSIÓN
 * ==============================================================================
 */

$orgData = $datos['organizacion'] ?? [];
$edicionData = $datos['edicion'] ?? [];
$tenantSlug = htmlspecialchars((string) ($orgData['codigo'] ?? 'candelaria_central'), ENT_QUOTES, 'UTF-8');
$edicionId = htmlspecialchars((string) ($edicionData['id'] ?? '1'), ENT_QUOTES, 'UTF-8');
$orgNombre = htmlspecialchars((string) ($orgData['nombre_comercial'] ?? 'Candelaria Oficial'), ENT_QUOTES, 'UTF-8');
$edicionNombre = htmlspecialchars((string) ($edicionData['nombre'] ?? 'Festividad Candelaria'), ENT_QUOTES, 'UTF-8');
$urlBase = rtrim(url_base(), '/') . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($datos['titulo'] ?? 'Portal Oficial | CandelariaAPP', ENT_QUOTES, 'UTF-8') ?></title>
    
    <!-- Meta SEO & Seguridad -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0c192c">

    <!-- CSS Vendor Oficial (Bootstrap 5 & FontAwesome 6) -->
    <link rel="stylesheet" href="<?= $urlBase ?>publico/activos/alina/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $urlBase ?>publico/activos/alina/vendor/fontawesome/css/all.css">
    
    <!-- Estilos Propios del Portal Público (F2.9C) -->
    <link rel="stylesheet" href="<?= $urlBase ?>publico/activos/css/portal_publico.css?v=2.9c">
</head>
<body class="portal-publico" 
      data-tenant-slug="<?= $tenantSlug ?>" 
      data-edicion-id="<?= $edicionId ?>" 
      data-url-base="<?= $urlBase ?>">

    <!-- Barra de Navegación Pública -->
    <header class="portal-navbar">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="<?= $urlBase ?>p/<?= $tenantSlug ?>/catalogo" class="portal-brand">
                <div class="portal-brand-text">
                    <span class="portal-brand-title"><?= $orgNombre ?></span>
                    <span class="portal-brand-edition"><?= $edicionNombre ?></span>
                </div>
            </a>

            <nav class="portal-nav-links d-flex align-items-center gap-2">
                <a href="<?= $urlBase ?>p/<?= $tenantSlug ?>/catalogo" class="nav-link">
                    <i class="fa-solid fa-compass me-1"></i> Catálogo
                </a>
                <a href="<?= $urlBase ?>p/<?= $tenantSlug ?>/checkout" class="nav-link position-relative">
                    <i class="fa-solid fa-shopping-bag me-1"></i> Carrito
                    <span id="cart-counter-badge" class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle" style="display:none; font-size: 0.65rem;">0</span>
                </a>
                <a href="<?= $urlBase ?>p/<?= $tenantSlug ?>/mi-espacio" class="portal-btn-client ms-2">
                    <i class="fa-solid fa-ticket-simple"></i> Mis Boletos
                </a>
            </nav>
        </div>
    </header>

    <!-- Contenido Dinámico de la Página -->
    <main class="flex-grow-1">
        <?= $contenido ?>
    </main>

    <!-- Footer Soberano del Portal Público -->
    <footer class="portal-footer">
        <div class="container">
            <div class="row g-4 pb-4">
                <div class="col-lg-5 col-md-6">
                    <div class="portal-footer-heading"><?= $orgNombre ?></div>
                    <p class="small text-muted mb-2">
                        Plataforma oficial de reservas soberanas para la Festividad Virgen de la Candelaria. Sistema transaccional multitenant con aforo garantizado en tiempo real.
                    </p>
                    <div class="small text-muted">
                        <i class="fa-solid fa-shield-halved text-warning me-1"></i> Protección de Datos Personales garantizada bajo la Ley N° 29733 (Perú).
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="portal-footer-heading">Gestión Segura</div>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><a href="<?= $urlBase ?>p/<?= $tenantSlug ?>/catalogo"><i class="fa-solid fa-chevron-right me-1 text-warning"></i> Paquetes Oficiales</a></li>
                        <li><a href="<?= $urlBase ?>p/<?= $tenantSlug ?>/checkout"><i class="fa-solid fa-chevron-right me-1 text-warning"></i> Proceso de Checkout</a></li>
                        <li><a href="<?= $urlBase ?>p/<?= $tenantSlug ?>/mi-espacio"><i class="fa-solid fa-chevron-right me-1 text-warning"></i> Consulta de Boletos QR</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 col-md-12">
                    <div class="portal-footer-heading">Soporte y Reclamaciones</div>
                    <p class="small text-muted mb-2">
                        ¿Tiene alguna consulta sobre sus entradas o proceso de pago? Contáctenos a través de nuestros canales oficiales o ingrese a su espacio de cliente.
                    </p>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <span class="badge bg-secondary p-2"><i class="fa-solid fa-book-open me-1"></i> Libro de Reclamaciones</span>
                        <span class="badge bg-dark p-2"><i class="fa-solid fa-lock me-1"></i> Pago Cifrado SSL 256-bit</span>
                    </div>
                </div>
            </div>

            <div class="border-top border-secondary pt-3 d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
                <div>&copy; <?= date('Y') ?> <?= $orgNombre ?>. Todos los derechos reservados.</div>
                <div class="mt-2 mt-md-0">Tecnología CandelariaAPP V1 &bull; Desacople Financiero &bull; Cero Sobreventa</div>
            </div>
        </div>
    </footer>

    <!-- Scripts Vendor y Frontend Soberano -->
    <script src="<?= $urlBase ?>publico/activos/alina/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="<?= $urlBase ?>publico/activos/alina/vendor/sweetalert/sweetalert.js"></script>
    <script src="<?= $urlBase ?>publico/activos/js/portal_publico.js?v=2.9c"></script>
    <?php if (!empty($datos['scriptPagina'])): ?>
        <script src="<?= $datos['scriptPagina'] ?>"></script>
    <?php endif; ?>
</body>
</html>
