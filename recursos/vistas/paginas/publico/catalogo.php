<?php
/**
 * ==============================================================================
 * CANDELARIAAPP — CATÁLOGO PÚBLICO OFICIAL DE PAQUETES Y SERVICIOS (FASE 2.9C)
 * ==============================================================================
 */

$org = $datos['organizacion'] ?? [];
$edicion = $datos['edicion'] ?? [];
$catalogo = $datos['catalogo'] ?? ['paquetes' => [], 'items' => []];
$paquetes = $catalogo['paquetes'] ?? [];
$items = $catalogo['items'] ?? [];
$slug = htmlspecialchars((string) ($org['codigo'] ?? 'candelaria_central'), ENT_QUOTES, 'UTF-8');
$urlBase = url_base();
?>

<!-- Hero Banner de la Edición -->
<section class="portal-hero">
    <div class="container position-relative" style="z-index: 2;">
        <span class="portal-hero-pill">
            <i class="fa-solid fa-sparkles"></i> Edición Oficial <?= htmlspecialchars((string) ($edicion['anio'] ?? date('Y')), ENT_QUOTES, 'UTF-8') ?>
        </span>
        <h1 class="portal-hero-title"><?= htmlspecialchars((string) ($edicion['nombre'] ?? 'Festividad Virgen de la Candelaria'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="portal-hero-subtitle">
            <?= htmlspecialchars((string) ($edicion['descripcion'] ?? 'Reserve sus entradas para graderías oficiales, miradores preferenciales, desfiles y servicios turísticos de alta calidad con aforo 100% garantizado.'), ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
</section>

<!-- Catálogo de Paquetes y Servicios -->
<section class="py-5">
    <div class="container">
        <!-- Encabezado de Sección -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
            <div>
                <h2 class="h3 fw-bold text-dark mb-1">Paquetes y Experiencias Disponibles</h2>
                <p class="text-muted small mb-0">Seleccione la opción de su preferencia con cupos auditados en tiempo real.</p>
            </div>
            <div class="mt-3 mt-md-0 d-flex gap-2">
                <span class="badge bg-light text-dark border p-2"><i class="fa-solid fa-lock text-success me-1"></i> Precios Oficiales</span>
                <span class="badge bg-light text-dark border p-2"><i class="fa-solid fa-qrcode text-primary me-1"></i> Boletos con Firma Digital</span>
            </div>
        </div>

        <!-- Listado de Paquetes -->
        <?php if (!empty($paquetes)): ?>
            <div class="row g-4 mb-5">
                <?php foreach ($paquetes as $p): ?>
                    <?php
                    $pId = (int) $p['id'];
                    $cupos = (int) ($p['cupos_disponibles'] ?? 0);
                    $precio = number_format((float) ($p['precio'] ?? 0), 2);
                    $moneda = htmlspecialchars((string) ($p['moneda'] ?? 'PEN'), ENT_QUOTES, 'UTF-8');
                    $esAgotado = $cupos <= 0;
                    $esUltimos = $cupos > 0 && $cupos <= 10;
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card-paquete">
                            <div class="card-paquete-header d-flex justify-content-between align-items-start">
                                <?php if ($esAgotado): ?>
                                    <span class="badge-disponibilidad badge-agotado">
                                        <i class="fa-solid fa-ban"></i> Aforo Agotado
                                    </span>
                                <?php elseif ($esUltimos): ?>
                                    <span class="badge-disponibilidad badge-ultimos-cupos">
                                        <i class="fa-solid fa-bolt"></i> ¡Solo <?= $cupos ?> cupos!
                                    </span>
                                <?php else: ?>
                                    <span class="badge-disponibilidad badge-disponible">
                                        <i class="fa-solid fa-circle-check"></i> <?= $cupos ?> cupos disponibles
                                    </span>
                                <?php endif; ?>

                                <span class="badge bg-light text-secondary border small">PAQUETE</span>
                            </div>

                            <div class="card-paquete-body">
                                <h3 class="h5 fw-bold text-dark mt-2 mb-2"><?= htmlspecialchars((string) $p['nombre'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="text-muted small flex-grow-1 mb-3">
                                    <?= htmlspecialchars((string) ($p['descripcion'] ?? 'Acceso preferencial para el desfile de veneración y parada folklórica.'), ENT_QUOTES, 'UTF-8') ?>
                                </p>

                                <?php if (!empty($p['componentes'])): ?>
                                    <div class="p-2 bg-light rounded-3 mb-3 small">
                                        <div class="fw-bold text-secondary mb-1" style="font-size: 0.75rem;">INCLUYE:</div>
                                        <ul class="list-unstyled mb-0 d-flex flex-column gap-1">
                                            <?php foreach ($p['componentes'] as $comp): ?>
                                                <li class="d-flex align-items-center gap-2 text-dark">
                                                    <i class="fa-solid fa-check text-success small"></i>
                                                    <span><?= (float) $comp['cantidad'] ?>x <?= htmlspecialchars((string) $comp['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="precio-tag mt-auto">
                                    <span class="precio-moneda"><?= $moneda === 'PEN' ? 'Soles (PEN)' : $moneda ?></span>
                                    <span class="precio-monto">S/ <?= $precio ?></span>
                                    <span class="precio-igv-badge">Incluye IGV (18%)</span>
                                </div>
                            </div>

                            <div class="card-paquete-footer">
                                <a href="<?= $urlBase ?>p/<?= $slug ?>/paquete/<?= $pId ?>" class="btn btn-outline-dark btn-sm fw-semibold">
                                    <i class="fa-solid fa-circle-info me-1"></i> Detalles
                                </a>

                                <?php if (!$esAgotado): ?>
                                    <button class="btn btn-primary btn-sm fw-bold btn-agregar-carrito"
                                            data-tipo="PAQUETE"
                                            data-id="<?= $pId ?>"
                                            data-nombre="<?= htmlspecialchars((string) $p['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-precio="<?= $precio ?>"
                                            data-moneda="<?= $moneda ?>">
                                        <i class="fa-solid fa-cart-plus me-1"></i> Reservar
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm fw-bold" disabled>
                                        Agotado
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-light border text-center py-5 mb-5 rounded-4">
                <i class="fa-solid fa-calendar-xmark fa-3x text-muted mb-3"></i>
                <h4 class="h5 fw-bold text-dark">No hay paquetes publicados por el momento</h4>
                <p class="text-muted small mb-0">Nuestro equipo está configurando la grilla oficial. Por favor vuelva a consultar en breve.</p>
            </div>
        <?php endif; ?>

        <!-- Listado de Servicios Individuales (Ítems) -->
        <?php if (!empty($items)): ?>
            <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
                <div>
                    <h3 class="h4 fw-bold text-dark mb-1">Servicios y Entradas Individuales</h3>
                    <p class="text-muted small mb-0">Graderías, servicios fotográficos y traslados por separado.</p>
                </div>
            </div>

            <div class="row g-3">
                <?php foreach ($items as $it): ?>
                    <?php
                    $itId = (int) $it['id'];
                    $cuposIt = (int) ($it['cupos_disponibles'] ?? 0);
                    $precioIt = number_format((float) ($it['precio'] ?? 0), 2);
                    $esAgotadoIt = $cuposIt <= 0;
                    ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="card h-100 border rounded-3 p-3 d-flex flex-column shadow-sm">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-secondary-subtle text-secondary small"><?= htmlspecialchars((string) $it['tipo'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="badge <?= $esAgotadoIt ? 'bg-danger' : 'bg-success' ?> small">
                                    <?= $esAgotadoIt ? 'Agotado' : "{$cuposIt} cupos" ?>
                                </span>
                            </div>
                            <h4 class="h6 fw-bold text-dark mb-1"><?= htmlspecialchars((string) $it['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="text-muted small flex-grow-1 mb-2"><?= htmlspecialchars((string) ($it['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                <span class="fw-bold text-primary">S/ <?= $precioIt ?></span>
                                <?php if (!$esAgotadoIt): ?>
                                    <button class="btn btn-outline-primary btn-sm btn-agregar-carrito"
                                            data-tipo="ITEM"
                                            data-id="<?= $itId ?>"
                                            data-nombre="<?= htmlspecialchars((string) $it['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-precio="<?= $precioIt ?>"
                                            data-moneda="PEN">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
