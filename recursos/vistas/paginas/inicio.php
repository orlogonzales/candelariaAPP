<?php
$contextoInicio = \Nucleo\Http\ContextoOperacion::actual();
$usuarioIdInicio = $contextoInicio?->usuarioId;
$nombreUsuarioInicio = (string) ($contextoInicio?->metadatos['nombre_completo'] ?? $contextoInicio?->metadatos['nombre_usuario'] ?? 'Operador Autenticado');

$permisosInicio = [
    'dashboard_ejecutivo' => false,
    'ediciones'           => false,
    'clientes'            => false,
    'crm'                 => false,
    'catalogo'            => false,
    'cotizaciones'        => false,
    'ventas'              => false,
    'reservas'            => false,
    'operaciones_salidas' => false,
    'validador_qr'        => false,
    'entregas_fisicas'    => false,
    'pagos'               => false,
    'comunicaciones'      => false,
    'postproduccion'      => false,
    'folclore'            => false,
    'usuarios'            => false,
];

if ($usuarioIdInicio !== null) {
    try {
        $authzInicio = new \Aplicacion\Autorizacion\AutorizacionServicio();
        $uid = (int) $usuarioIdInicio;
        $permisosInicio = [
            'dashboard_ejecutivo' => $authzInicio->tienePermiso($uid, 'dashboard.ejecutivo.ver'),
            'ediciones'           => $authzInicio->tienePermiso($uid, 'ediciones.ver'),
            'clientes'            => $authzInicio->tienePermiso($uid, 'clientes.ver'),
            'crm'                 => $authzInicio->tienePermiso($uid, 'crm.oportunidades.ver'),
            'catalogo'            => $authzInicio->tienePermiso($uid, 'catalogo.items.ver') || $authzInicio->tienePermiso($uid, 'catalogo.paquetes.ver'),
            'cotizaciones'        => $authzInicio->tienePermiso($uid, 'cotizaciones.ver'),
            'ventas'              => $authzInicio->tienePermiso($uid, 'ventas.ver'),
            'reservas'            => $authzInicio->tienePermiso($uid, 'reservas.ver'),
            'operaciones_salidas' => $authzInicio->tienePermiso($uid, 'operacion.salidas.ver') || $authzInicio->tienePermiso($uid, 'operacion.ver_salidas'),
            'validador_qr'        => $authzInicio->tienePermiso($uid, 'operacion.offline.validar') || $authzInicio->tienePermiso($uid, 'operacion.asistencia.registrar'),
            'entregas_fisicas'    => $authzInicio->tienePermiso($uid, 'operacion.entregas.ver') || $authzInicio->tienePermiso($uid, 'entregas.ver'),
            'pagos'               => $authzInicio->tienePermiso($uid, 'pagos.ver'),
            'comunicaciones'      => $authzInicio->tienePermiso($uid, 'comunicaciones.conversaciones.ver'),
            'postproduccion'      => $authzInicio->tienePermiso($uid, 'postproduccion.proyectos.ver'),
            'folclore'            => $authzInicio->tienePermiso($uid, 'folclore.conjuntos.ver') || $authzInicio->tienePermiso($uid, 'folclore.ver'),
            'usuarios'            => $authzInicio->tienePermiso($uid, 'usuarios.ver'),
        ];
    } catch (\Throwable $e) {
        // Fallback seguro (fail-closed)
    }
}

$modulosOperativos = [
    [
        'visible'     => $permisosInicio['dashboard_ejecutivo'],
        'titulo'      => 'Dashboard Ejecutivo y Analítica',
        'categoria'   => 'Dirección y Estrategia',
        'descripcion' => 'Indicadores financieros en tiempo real, embudo comercial, ocupación operativa y comparativa multianual.',
        'icono'       => 'fa-solid fa-chart-pie',
        'color'       => 'primary',
        'url'         => url_base('dashboard-ejecutivo'),
        'cta'         => 'Abrir Dashboard Ejecutivo',
        'url_sec'     => url_base('analitica-multianual'),
        'cta_sec'     => 'Analítica Multianual',
    ],
    [
        'visible'     => $permisosInicio['operaciones_salidas'],
        'titulo'      => 'Salidas de Campo y Embarque',
        'categoria'   => 'Operaciones en Terreno',
        'descripcion' => 'Programación de salidas operativas, manifiesto de pasajeros, asignación de flota/guías y control de asistencia.',
        'icono'       => 'fa-solid fa-compass',
        'color'       => 'success',
        'url'         => url_base('operaciones'),
        'cta'         => 'Gestionar Salidas',
        'url_sec'     => $permisosInicio['validador_qr'] ? url_base('operaciones/validador-movil') : null,
        'cta_sec'     => $permisosInicio['validador_qr'] ? 'Validador Móvil QR' : null,
    ],
    [
        'visible'     => $permisosInicio['postproduccion'],
        'titulo'      => 'Postproducción y Galerías Multimedia',
        'categoria'   => 'Cobertura Audiovisual',
        'descripcion' => 'Gestión de proyectos fotográficos y de video, carga de material de alta resolución, selecciones y entregas.',
        'icono'       => 'fa-solid fa-photo-film',
        'color'       => 'info',
        'url'         => url_base('postproduccion'),
        'cta'         => 'Ir a Proyectos Multimedia',
        'url_sec'     => null,
        'cta_sec'     => null,
    ],
    [
        'visible'     => $permisosInicio['reservas'],
        'titulo'      => 'Reservas, Boletos QR y Entregas',
        'categoria'   => 'Agendamiento y Despacho',
        'descripcion' => 'Padrón de reservas confirmadas, participantes acreditados, impresión de boletos QR y despacho de bienes.',
        'icono'       => 'fa-solid fa-calendar-check',
        'color'       => 'warning',
        'url'         => url_base('reservas'),
        'cta'         => 'Ver Padrón de Reservas',
        'url_sec'     => $permisosInicio['entregas_fisicas'] ? url_base('operaciones/entregas') : null,
        'cta_sec'     => $permisosInicio['entregas_fisicas'] ? 'Despacho de Entregas' : null,
    ],
    [
        'visible'     => $permisosInicio['ventas'] || $permisosInicio['cotizaciones'],
        'titulo'      => 'Gestión Comercial y Contratos',
        'categoria'   => 'Comercial y CRM',
        'descripcion' => 'Cotizaciones históricas congeladas, registro de ventas confirmadas y emisión de contratos turísticos PDF.',
        'icono'       => 'fa-solid fa-file-invoice-dollar',
        'color'       => 'danger',
        'url'         => $permisosInicio['ventas'] ? url_base('ventas') : url_base('cotizaciones'),
        'cta'         => $permisosInicio['ventas'] ? 'Libro de Ventas' : 'Cotizaciones',
        'url_sec'     => $permisosInicio['cotizaciones'] ? url_base('cotizaciones') : null,
        'cta_sec'     => $permisosInicio['cotizaciones'] ? 'Cotizaciones' : null,
    ],
    [
        'visible'     => $permisosInicio['pagos'],
        'titulo'      => 'Tesorería, Cobranzas y Recibos',
        'categoria'   => 'Finanzas y Recaudación',
        'descripcion' => 'Libro mayor de pagos, verificación de transferencias bancarias, pasarelas y emisión de recibos de caja.',
        'icono'       => 'fa-solid fa-vault',
        'color'       => 'secondary',
        'url'         => url_base('pagos'),
        'cta'         => 'Abrir Libro de Pagos',
        'url_sec'     => null,
        'cta_sec'     => null,
    ],
    [
        'visible'     => $permisosInicio['folclore'],
        'titulo'      => 'Programación Folclórica FRFCP',
        'categoria'   => 'Concursos y Paradas',
        'descripcion' => 'Catálogo de conjuntos folclóricos, orden de presentación oficial y seguimiento en tiempo real de danzas.',
        'icono'       => 'fa-solid fa-masks-theater',
        'color'       => 'primary',
        'url'         => url_base('folclore'),
        'cta'         => 'Programación Folclórica',
        'url_sec'     => null,
        'cta_sec'     => null,
    ],
    [
        'visible'     => $permisosInicio['comunicaciones'],
        'titulo'      => 'Centro de Mensajería WhatsApp',
        'categoria'   => 'Atención al Cliente',
        'descripcion' => 'Bandeja omnicanal de conversaciones 24h, plantillas transaccionales, campañas y automatizaciones.',
        'icono'       => 'fa-brands fa-whatsapp',
        'color'       => 'success',
        'url'         => url_base('comunicaciones'),
        'cta'         => 'Abrir Centro WhatsApp',
        'url_sec'     => null,
        'cta_sec'     => null,
    ],
];
?>
<div class="row g-4" id="centro-operaciones-inicio">
    <!-- Cabecera Operativa Principal V1.2 -->
    <div class="col-12">
        <div class="card border-0 shadow-sm overflow-hidden" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
            <div class="card-body p-4 text-white">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge bg-primary px-3 py-1 text-uppercase f-s-11">CandelariaAPP V1.2</span>
                            <span class="badge bg-success px-3 py-1 text-uppercase f-s-11">Centro de Operaciones</span>
                        </div>
                        <h2 class="text-white f-w-700 mb-1">Bienvenido, <?= escapar_html($nombreUsuarioInicio) ?></h2>
                        <p class="text-light opacity-75 mb-0 f-s-14">
                            Plataforma integral de gestión comercial, operaciones de campo, programación folclórica y postproducción audiovisual para la Festividad Virgen de la Candelaria.
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($permisosInicio['dashboard_ejecutivo']): ?>
                            <a href="<?= url_base('dashboard-ejecutivo') ?>" class="btn btn-warning f-w-600 text-dark">
                                <i class="fa-solid fa-chart-line me-1"></i> Dashboard Ejecutivo
                            </a>
                        <?php endif; ?>
                        <?php if ($permisosInicio['validador_qr']): ?>
                            <a href="<?= url_base('operaciones/validador-movil') ?>" class="btn btn-light f-w-600 text-dark">
                                <i class="fa-solid fa-qrcode me-1"></i> Validador Móvil QR
                            </a>
                        <?php endif; ?>
                        <a href="<?= url_base('portal/catalogo') ?>" target="_blank" rel="noopener" class="btn btn-info f-w-600 text-white">
                            <i class="fa-solid fa-globe me-1"></i> Portal Público
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Acceso Rápido Filtradas por Competencias RBAC -->
    <?php foreach ($modulosOperativos as $mod): ?>
        <?php if (!$mod['visible']) continue; ?>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="f-w-600 text-muted f-s-12 text-uppercase"><?= escapar_html($mod['categoria']) ?></span>
                            <div class="h-35 w-35 d-flex-center bg-<?= escapar_html($mod['color']) ?>-subtle text-<?= escapar_html($mod['color']) ?> rounded-circle">
                                <i class="<?= escapar_html($mod['icono']) ?>"></i>
                            </div>
                        </div>
                        <h5 class="f-w-700 text-dark mb-2"><?= escapar_html($mod['titulo']) ?></h5>
                        <p class="text-muted f-s-13 mb-3"><?= escapar_html($mod['descripcion']) ?></p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                        <a href="<?= escapar_html($mod['url']) ?>" class="btn btn-sm btn-<?= escapar_html($mod['color']) ?> f-w-600">
                            <?= escapar_html($mod['cta']) ?> <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                        <?php if (!empty($mod['url_sec']) && !empty($mod['cta_sec'])): ?>
                            <a href="<?= escapar_html($mod['url_sec']) ?>" class="btn btn-sm btn-outline-secondary f-w-600">
                                <?= escapar_html($mod['cta_sec']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Verificador de Conectividad y Estado Operativo del Servicio -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="card-title mb-1 f-w-700 text-dark">
                        <i class="fa-solid fa-heart-pulse me-2 text-success"></i> Estado Operativo de Servicios y Conectividad API
                    </h5>
                    <p class="text-muted f-s-13 mb-0">Verificación en vivo contra el endpoint institucional <code>/api/v1/estado</code>.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm px-3" id="btn-demo-skeleton">
                        <i class="fa-solid fa-rotate me-1"></i> Comprobar Estado API
                    </button>
                </div>
            </div>
            <div class="card-body p-4">
                <div id="contenedor-demo-interactivo" class="p-3 bg-light rounded border">
                    <div class="d-flex align-items-center gap-3">
                        <div class="h-40 w-40 d-flex-center bg-success-subtle text-success rounded-circle">
                            <i class="fa-solid fa-circle-check f-s-18"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-dark f-w-600">Servicios de plataforma activos y sincronizados</h6>
                            <p class="mb-0 text-muted f-s-13">Seleccione una tarjeta superior para operar en su módulo asignado o pulse «Comprobar Estado API» para verificar latencia y conectividad.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnSkeleton = document.getElementById('btn-demo-skeleton');
    const contenedor = document.getElementById('contenedor-demo-interactivo');

    if (btnSkeleton && contenedor) {
        btnSkeleton.addEventListener('click', async () => {
            if (typeof Skeleton !== 'undefined') {
                Skeleton.show(contenedor, 'table', { filas: 2, columnas: 3 });
            }

            try {
                const respuesta = await window.CandelariaApi.get('estado');
                const datos = respuesta.datos || {};

                const tablaHtml = `
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0 bg-white">
                            <thead class="table-light">
                                <tr>
                                    <th>Parámetro Operativo</th>
                                    <th>Valor Reportado</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Plataforma</strong></td>
                                    <td>${window.CandelariaUI ? window.CandelariaUI.escaparHtml(datos.aplicacion || 'CandelariaAPP') : 'CandelariaAPP'} (${window.CandelariaUI ? window.CandelariaUI.escaparHtml(datos.version || '1.2') : '1.2'})</td>
                                    <td><span class="badge bg-success">ONLINE</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Zona Horaria / Timestamp</strong></td>
                                    <td><code>${window.CandelariaUI ? window.CandelariaUI.escaparHtml(datos.zona_horaria || 'America/Lima') : ''}</code> &bull; ${window.CandelariaUI ? window.CandelariaUI.escaparHtml(datos.marca_tiempo || '') : ''}</td>
                                    <td><span class="badge bg-primary">SINCRONIZADO</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                `;
                if (typeof Skeleton !== 'undefined') {
                    Skeleton.hide(contenedor, tablaHtml);
                } else {
                    contenedor.innerHTML = tablaHtml;
                }
            } catch (error) {
                contenedor.innerHTML = `<div class="alert alert-danger mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>Error al consultar estado: ${error.message}</div>`;
            }
        });
    }
});
</script>
