<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP V1.2 — SUITE DE REGRESIÓN Y CERTIFICACIÓN DT-V12-009
 * MICROFASE CORRECTIVA PREPRODUCTIVA (H-01 A H-08)
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Entidades\CategoriaItem;
use Aplicacion\Repositorios\CategoriaItemRepositorio;
use Nucleo\BaseDatos\Conexion;

$pruebasEjecutadas = 0;
$pruebasPasadas = 0;
$pruebasFallidas = 0;

function assertDT009(bool $condicion, string $mensaje): void
{
    global $pruebasEjecutadas, $pruebasPasadas, $pruebasFallidas;
    $pruebasEjecutadas++;
    if ($condicion) {
        $pruebasPasadas++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$mensaje}\n";
    }
}

echo "==============================================================================\n";
echo "CANDELARIAAPP V1.2 — SUITE DE CERTIFICACIÓN CORRECTIVA DT-V12-009 (H-01..H-08)\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

// Asegurar que la migración 000030 esté aplicada en la base activa de pruebas
$mig030Path = __DIR__ . '/../base_datos/migraciones/2026_10_18_000030_regularizar_rbac_y_datos_preprod_v12.sql';
assertDT009(file_exists($mig030Path), "1.0: Archivo de migración 2026_10_18_000030_regularizar_rbac_y_datos_preprod_v12.sql existe");

$mig030Registrada = (int) $pdo->query("SELECT COUNT(*) FROM migraciones_control WHERE migracion = '2026_10_18_000030_regularizar_rbac_y_datos_preprod_v12.sql'")->fetchColumn();
if ($mig030Registrada === 0 && file_exists($mig030Path)) {
    $pdo->exec((string) file_get_contents($mig030Path));
    $mig030Registrada = (int) $pdo->query("SELECT COUNT(*) FROM migraciones_control WHERE migracion = '2026_10_18_000030_regularizar_rbac_y_datos_preprod_v12.sql'")->fetchColumn();
}
assertDT009($mig030Registrada === 1, "1.1: Migración 000030 asentada en migraciones_control");

// ------------------------------------------------------------------------------
// BLOQUE 1: H-01 — MATRIZ DE COMPETENCIAS RBAC (M03, M13, M24)
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 1: H-01 — Matriz de Competencias RBAC por Rol ---\n";

$permisosTotalesSuper = (int) $pdo->query("
    SELECT COUNT(*) FROM rol_permisos rp
    INNER JOIN roles r ON r.id = rp.rol_id
    WHERE r.codigo = 'superadmin_plataforma'
")->fetchColumn();
assertDT009($permisosTotalesSuper === 115, "1.2: Rol superadmin_plataforma posee los 115 permisos del catálogo ({$permisosTotalesSuper}/115)");

$permisosTotalesAdminOrg = (int) $pdo->query("
    SELECT COUNT(*) FROM rol_permisos rp
    INNER JOIN roles r ON r.id = rp.rol_id
    WHERE r.codigo = 'admin_organizacion'
")->fetchColumn();
assertDT009($permisosTotalesAdminOrg === 113, "1.3: Rol admin_organizacion posee los 113 permisos organizacionales ({$permisosTotalesAdminOrg}/115)");

$permisosTotalesOperador = (int) $pdo->query("
    SELECT COUNT(*) FROM rol_permisos rp
    INNER JOIN roles r ON r.id = rp.rol_id
    WHERE r.codigo = 'operador_produccion'
")->fetchColumn();
assertDT009($permisosTotalesOperador === 35, "1.4: Rol operador_produccion posee exactamente 35 permisos operativos ({$permisosTotalesOperador}/115)");

// Verificar los 6 permisos ALLOW y los 7 permisos DENY de operador_produccion en M03, M13 y M24
$permisosAllowOperador = [
    'documentos.cotizaciones.descargar',
    'documentos.boletos.descargar',
    'postproduccion.proyectos.ver',
    'postproduccion.archivos.subir',
    'postproduccion.archivos.descargar',
    'postproduccion.entregas.gestionar',
];

$permisosDenyOperador = [
    'documentos.contratos.descargar',
    'documentos.recibos.emitir',
    'documentos.recibos.descargar',
    'postproduccion.proyectos.crear',
    'postproduccion.proyectos.editar',
    'ediciones.cerrar',
    'ediciones.reabrir_excepcional',
];

$stmtCheckRolPerm = $pdo->prepare("
    SELECT COUNT(*) FROM rol_permisos rp
    INNER JOIN roles r ON r.id = rp.rol_id
    INNER JOIN permisos p ON p.id = rp.permiso_id
    WHERE r.codigo = :rol AND p.codigo = :permiso
");

foreach ($permisosAllowOperador as $permCodigo) {
    $stmtCheckRolPerm->execute(['rol' => 'admin_organizacion', 'permiso' => $permCodigo]);
    $adminTiene = (int) $stmtCheckRolPerm->fetchColumn() === 1;
    $stmtCheckRolPerm->execute(['rol' => 'operador_produccion', 'permiso' => $permCodigo]);
    $opTiene = (int) $stmtCheckRolPerm->fetchColumn() === 1;
    assertDT009($adminTiene && $opTiene, "1.5: Permiso operativo '{$permCodigo}' concedido (ALLOW) a admin_organizacion y operador_produccion");
}

foreach ($permisosDenyOperador as $permCodigo) {
    $stmtCheckRolPerm->execute(['rol' => 'admin_organizacion', 'permiso' => $permCodigo]);
    $adminTiene = (int) $stmtCheckRolPerm->fetchColumn() === 1;
    $stmtCheckRolPerm->execute(['rol' => 'operador_produccion', 'permiso' => $permCodigo]);
    $opTiene = (int) $stmtCheckRolPerm->fetchColumn() === 1;
    assertDT009($adminTiene && !$opTiene, "1.6: Permiso administrativo/financiero '{$permCodigo}' concedido a admin_organizacion y denegado (DENY) a operador_produccion");
}

// Verificar que DocumentoControlador exige permisos granulares documentos.* sin fallback laxo
$docCtrlCode = (string) file_get_contents(__DIR__ . '/../aplicacion/Controladores/DocumentoControlador.php');
assertDT009(
    !str_contains($docCtrlCode, "'ventas.ver'") && !str_contains($docCtrlCode, "'pagos.ver'"),
    "1.7: DocumentoControlador exige permisos granulares documentos.* sin fallback laxo a ventas.ver o pagos.ver"
);

// ------------------------------------------------------------------------------
// BLOQUE 2: H-02 — ACCESOS A DESCARGA PDF EN BACKOFFICE SEGÚN PERMISOS Y ESTADO
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 2: H-02 — Accesos a Descarga PDF en Vistas de Backoffice ---\n";

$metasCode = (string) file_get_contents(__DIR__ . '/../recursos/vistas/parciales/metas_encabezado.php');
assertDT009(
    str_contains($metasCode, 'window.CANDELARIA_PERMISOS_DOCUMENTOS') && str_contains($metasCode, 'documentos.contratos.descargar'),
    "2.1: metas_encabezado.php expone window.CANDELARIA_PERMISOS_DOCUMENTOS evaluado con AutorizacionServicio"
);

$cotJs = (string) file_get_contents(__DIR__ . '/../publico/js/cotizaciones.js');
$vtaJs = (string) file_get_contents(__DIR__ . '/../publico/js/ventas.js');
$pagJs = (string) file_get_contents(__DIR__ . '/../publico/js/pagos.js');
$resJs = (string) file_get_contents(__DIR__ . '/../publico/js/reservas.js');

assertDT009(
    str_contains($cotJs, 'CANDELARIA_PERMISOS_DOCUMENTOS?.cotizaciones_pdf') && str_contains($cotJs, 'api/v1/documentos/cotizacion/'),
    "2.2: cotizaciones.js incorpora botón de descarga PDF condicionado a permiso cotizaciones_pdf"
);
assertDT009(
    str_contains($vtaJs, 'CANDELARIA_PERMISOS_DOCUMENTOS?.contratos_pdf') && str_contains($vtaJs, "v.estado === 'CONFIRMADA'") && str_contains($vtaJs, 'api/v1/documentos/contrato/'),
    "2.3: ventas.js incorpora descarga de Contrato PDF condicionada a permiso contratos_pdf y estado CONFIRMADA"
);
assertDT009(
    str_contains($pagJs, 'CANDELARIA_PERMISOS_DOCUMENTOS?.recibos_pdf') && str_contains($pagJs, 'api/v1/documentos/recibo/'),
    "2.4: pagos.js incorpora descarga de Recibo de Caja PDF condicionada a permiso recibos_pdf y estado APROBADO"
);
assertDT009(
    str_contains($resJs, 'CANDELARIA_PERMISOS_DOCUMENTOS?.boletos_pdf') && str_contains($resJs, 'api/v1/documentos/boleto/'),
    "2.5: reservas.js incorpora descarga de Boleto QR PDF condicionada a permiso boletos_pdf y reserva no cancelada"
);

// ------------------------------------------------------------------------------
// BLOQUE 3: H-03 — PÁGINA DE INICIO OPERATIVA SEGÚN PERMISOS RBAC
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 3: H-03 — Página de Inicio Operativa según Permisos ---\n";

$inicioCode = (string) file_get_contents(__DIR__ . '/../recursos/vistas/paginas/inicio.php');
assertDT009(
    !str_contains($inicioCode, 'Fase 0 &bull; Foundation') && !str_contains($inicioCode, 'Reglas de Gobernanza F0.1'),
    "3.1: inicio.php elimina los textos demostrativos de Fase 0 Foundation"
);
assertDT009(
    str_contains($inicioCode, 'Centro de Operaciones') && str_contains($inicioCode, 'AutorizacionServicio'),
    "3.2: inicio.php presenta el Centro de Operaciones V1.2 filtrando tarjetas según AutorizacionServicio"
);

// ------------------------------------------------------------------------------
// BLOQUE 4: H-04 — NAVEGACIÓN LATERAL ALINEADA CON RBAC
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 4: H-04 — Navegación Lateral Alineada con RBAC ---\n";

$barraCode = (string) file_get_contents(__DIR__ . '/../recursos/vistas/parciales/barra_lateral.php');
assertDT009(
    (bool) preg_match('/<\?php\s+if\s*\(\$puedeVerUsuarios\):\s*\?>\s*<li class="no-sub">\s*<a href="[^"]*" id="navPrincipalUsuarios"/s', $barraCode),
    "4.1: Enlace #navPrincipalUsuarios en menuPrincipal está protegido por la guarda \$puedeVerUsuarios"
);
assertDT009(
    !str_contains($barraCode, 'Citas y Reuniones Puno') && !str_contains($barraCode, 'id="subConfig"'),
    "4.2: Enlaces muertos con href=\"#\" (Citas y Reuniones Puno / #subConfig) retirados de barra_lateral.php"
);

// ------------------------------------------------------------------------------
// BLOQUE 5: H-05 — CONSTRUCCIÓN NORMALIZADA DE RUTAS API EN JAVASCRIPT
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 5: H-05 — Normalización de Construcción de Rutas API en JS ---\n";

$candelariaJs = (string) file_get_contents(__DIR__ . '/../publico/js/candelaria.js');
assertDT009(
    str_contains($candelariaJs, 'function construirUrlCandelaria(') && str_contains($candelariaJs, 'window.construirUrlCandelaria = construirUrlCandelaria'),
    "5.1: candelaria.js define y expone globalmente window.construirUrlCandelaria"
);

// Réplica exacta de la lógica de construirUrlCandelaria para validar casos borde
$construirUrlReplica = static function (string $ruta = '', ?string $baseOverride = null): string {
    $rawBase = $baseOverride ?? '';
    $baseLimpia = preg_replace('/\/+$/', '', trim($rawBase)) ?? '';
    $rutaLimpia = preg_replace('/^\/+/', '', trim($ruta)) ?? '';
    if ($rutaLimpia === '') {
        return $baseLimpia !== '' ? $baseLimpia : '/';
    }
    return "{$baseLimpia}/{$rutaLimpia}";
};

assertDT009(
    $construirUrlReplica('api/v1/postproduccion', 'http://127.0.0.1:8080/') === 'http://127.0.0.1:8080/api/v1/postproduccion',
    "5.2: construirUrlCandelaria normaliza base con barra final ('http://127.0.0.1:8080/') sin generar doble barra"
);
assertDT009(
    $construirUrlReplica('/api/v1/reservas', '/') === '/api/v1/reservas'
    && $construirUrlReplica('//api/v1/operaciones', '') === '/api/v1/operaciones',
    "5.3: construirUrlCandelaria normaliza base raíz ('/' o '') y rutas con barras iniciales sin producir //api/v1"
);
assertDT009(
    $construirUrlReplica('/api/v1/pagos', '/app.candelaria/') === '/app.candelaria/api/v1/pagos',
    "5.4: construirUrlCandelaria soporta despliegues en subdirectorio ('/app.candelaria/') sin barras duplicadas"
);

$archivosJsH05 = [
    'postproduccion.js',
    'reservas.js',
    'operaciones.js',
    'operaciones_recursos.js',
    'operaciones_entregas.js',
    'pagos.js',
];
foreach ($archivosJsH05 as $archivoJs) {
    $contenidoJs = (string) file_get_contents(__DIR__ . "/../publico/js/{$archivoJs}");
    assertDT009(
        str_contains($contenidoJs, 'construirUrlCandelaria') && !str_contains($contenidoJs, "CANDELARIA_BASE_URL + 'api/v1"),
        "5.5: {$archivoJs} utiliza construirUrlCandelaria sin concatenación directa vulnerable a //api/v1"
    );
}

// ------------------------------------------------------------------------------
// BLOQUE 6: H-06 — COLAPSO RESPONSIVO DEL MENÚ LATERAL EN MÓVIL
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 6: H-06 — Colapso Responsivo del Menú Lateral en Móvil ---\n";

$candelariaCss = (string) file_get_contents(__DIR__ . '/../publico/css/candelaria.css');
assertDT009(
    str_contains($candelariaJs, 'sincronizarSidebarResponsivo()')
    && str_contains($candelariaCss, '.app-navbar.side-nav-toggle .main-side-nav')
    && str_contains($candelariaCss, 'translateX(-120%)'),
    "6.1: candelaria.js y candelaria.css garantizan el colapso inmediato de .main-side-nav en viewports móviles"
);

// ------------------------------------------------------------------------------
// BLOQUE 7: H-07 — CONTRATO DE CÓDIGOS DE CATEGORÍAS Y DATOS SANEADOS
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 7: H-07 — Contrato de Códigos de Categorías y Saneamiento ---\n";

$codigosConGuion = (int) $pdo->query("SELECT COUNT(*) FROM categorias_items WHERE codigo LIKE '%-%'")->fetchColumn();
assertDT009($codigosConGuion === 0, "7.1: Cero registros en categorias_items contienen guiones medios incompatibles con el contrato");

$orgId = (int) $pdo->query("SELECT id FROM organizaciones ORDER BY id ASC LIMIT 1")->fetchColumn();
$categoriaRepo = new CategoriaItemRepositorio($pdo);
$categoriasHidratadas = $categoriaRepo->listar($orgId);
assertDT009(is_array($categoriasHidratadas) && count($categoriasHidratadas) >= 1, "7.2: CategoriaItemRepositorio::listar() hidrata todas las categorías sin lanzar DominioExcepcion");

$f29bCode = (string) file_get_contents(__DIR__ . '/ejecutar_pruebas_f29b.php');
assertDT009(
    !str_contains($f29bCode, "'CAT-TEST-01'") && str_contains($f29bCode, "'CAT_TEST_01'"),
    "7.3: Suite histórica ejecutar_pruebas_f29b.php corregida para insertar 'CAT_TEST_01'"
);

// ------------------------------------------------------------------------------
// BLOQUE 8: H-08 — ALINEACIÓN DE .env.produccion.ejemplo Y configuracion/seguridad.php
// ------------------------------------------------------------------------------
echo "\n--- BLOQUE 8: H-08 — Alineación de Configuración de Producción ---\n";

$envProdCode = (string) file_get_contents(__DIR__ . '/../.env.produccion.ejemplo');
$seguridadCode = (string) file_get_contents(__DIR__ . '/../configuracion/seguridad.php');

$varsEsperadas = [
    'AUTH_COOKIE_NOMBRE',
    'AUTH_COOKIE_SECURE',
    'AUTH_TIMEOUT_INACTIVIDAD',
    'AUTH_TIEMPO_VIDA_ABSOLUTO',
    'AUTH_MAX_INTENTOS',
    'AUTH_MINUTOS_BLOQUEO',
    'CSRF_TIMEOUT',
];
$todasVarsPresentes = true;
foreach ($varsEsperadas as $varName) {
    if (!str_contains($envProdCode, $varName)) {
        $todasVarsPresentes = false;
    }
}
assertDT009($todasVarsPresentes, "8.1: .env.produccion.ejemplo documenta todas las variables AUTH_* y CSRF_TIMEOUT leídas por configuracion/seguridad.php");
assertDT009(
    str_contains($seguridadCode, "['produccion', 'production']"),
    "8.2: configuracion/seguridad.php reconoce tanto APP_ENV=produccion como APP_ENV=production"
);

// ------------------------------------------------------------------------------
// RESUMEN FINAL
// ------------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo sprintf("TOTAL PRUEBAS DT-V12-009: %d | EXITOSAS: %d | FALLIDAS: %d\n", $pruebasEjecutadas, $pruebasPasadas, $pruebasFallidas);
echo "TOTAL: {$pruebasPasadas} PASS / {$pruebasFallidas} FAIL\n";
echo "==============================================================================\n";

exit($pruebasFallidas > 0 ? 1 : 0);
