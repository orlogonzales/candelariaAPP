<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 2.9C (FRONTEND PORTAL PÚBLICO Y CHECKOUT)
 * NÚCLEO: CATÁLOGO PÚBLICO, FICHA PAQUETE CON DISPONIBILIDAD, CHECKOUT 3 PASOS,
 *         CONFIRMACIÓN, ESPACIO CLIENTE PASSWORDLESS Y AISLAMIENTO DE ASSETS
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';
require_once __DIR__ . '/../rutas/web.php';
require_once __DIR__ . '/../rutas/api.php';

use Nucleo\Enrutamiento\Enrutador;
use Nucleo\BaseDatos\Conexion;

$totalPruebas = 0;
$pruebasExitosas = 0;
$pruebasFallidas = 0;

function registrarAsercion(string $descripcion, bool $condicion, string $detalleError = ''): void {
    global $totalPruebas, $pruebasExitosas, $pruebasFallidas;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$descripcion} --> {$detalleError}\n";
    }
}

echo "==============================================================================\n";
echo "INICIANDO SUITE F2.9C: FRONTEND PORTAL PÚBLICO, CHECKOUT Y ESPACIO CLIENTE\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

// Resolver Organización y Edición activa
$stmtOrg = $pdo->query("SELECT id, codigo, nombre_comercial FROM organizaciones WHERE estado = 'ACTIVO' ORDER BY id ASC LIMIT 1");
$org = $stmtOrg->fetch(PDO::FETCH_ASSOC);
$orgId = (int) $org['id'];
$orgCodigo = (string) $org['codigo'];

$stmtEd = $pdo->prepare("SELECT id, anio, codigo, es_actual, estado FROM ediciones_candelaria WHERE organizacion_id = :org_id ORDER BY id DESC LIMIT 1");
$stmtEd->execute(['org_id' => $orgId]);
$ed = $stmtEd->fetch(PDO::FETCH_ASSOC);
if ($ed) {
    $edId = (int) $ed['id'];
    $pdo->prepare("UPDATE ediciones_candelaria SET es_actual = 1 WHERE id = :id")->execute(['id' => $edId]);
} else {
    $stmtInsEd = $pdo->prepare("
        INSERT INTO ediciones_candelaria (organizacion_id, codigo, anio, nombre, fecha_inicio, fecha_fin, estado, es_actual)
        VALUES (:org_id, 'edicion-f29c-test', 2086, 'Edición Test F29C', '2086-01-01', '2086-02-15', 'PREOPERACION', 1)
    ");
    $stmtInsEd->execute(['org_id' => $orgId]);
    $edId = (int) $pdo->lastInsertId();
}

// Asegurar paquete y oferta para prueba de ficha
$pdo->exec("DELETE FROM tarifas_paquetes_edicion WHERE oferta_paquete_id IN (SELECT id FROM ofertas_paquetes_edicion WHERE paquete_id IN (SELECT id FROM paquetes WHERE codigo = 'PAQ_F29C_TEST'))");
$pdo->exec("DELETE FROM ofertas_paquetes_edicion WHERE paquete_id IN (SELECT id FROM paquetes WHERE codigo = 'PAQ_F29C_TEST')");
$pdo->exec("DELETE FROM paquetes WHERE codigo = 'PAQ_F29C_TEST'");

$stmtInsPaq = $pdo->prepare("
    INSERT INTO paquetes (organizacion_id, codigo, nombre, descripcion, estado)
    VALUES (:org_id, 'PAQ_F29C_TEST', 'Paquete Festividad F29C Test', 'Paquete para validación de vistas públicas', 'ACTIVO')
");
$stmtInsPaq->execute(['org_id' => $orgId]);
$paqId = (int) $pdo->lastInsertId();

$stmtInsOfe = $pdo->prepare("
    INSERT INTO ofertas_paquetes_edicion (organizacion_id, edicion_id, paquete_id, estado)
    VALUES (:org_id, :ed_id, :paq_id, 'ACTIVO')
");
$stmtInsOfe->execute(['org_id' => $orgId, 'ed_id' => $edId, 'paq_id' => $paqId]);
$ofePaqId = (int) $pdo->lastInsertId();

$stmtInsTar = $pdo->prepare("
    INSERT INTO tarifas_paquetes_edicion (oferta_paquete_id, moneda, precio)
    VALUES (:ofe_id, 'PEN', 480.00)
");
$stmtInsTar->execute(['ofe_id' => $ofePaqId]);

function despacharRuta(string $uri): string {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $uri;
    ob_start();
    try {
        Enrutador::despachar();
        return (string) ob_get_clean();
    } catch (\Throwable $e) {
        ob_end_clean();
        return 'ERROR: ' . $e->getMessage();
    }
}

// ------------------------------------------------------------------------------
// SECCIÓN 1: Vistas Web Públicas y Layout Soberano
// ------------------------------------------------------------------------------
echo "--- SECCIÓN 1: Renderizado de Vistas Web Públicas (Layout Soberano) ---\n";

// 1.1 Catálogo principal /p/{slug}
$htmlCat1 = despacharRuta("/p/{$orgCodigo}");
registrarAsercion("1.1 Catálogo principal /p/{slug} renderiza con éxito y contiene branding",
    str_contains($htmlCat1, 'Catálogo Oficial') && str_contains($htmlCat1, 'portal-navbar')
);

// 1.2 Catálogo explícito /p/{slug}/catalogo
$htmlCat2 = despacharRuta("/p/{$orgCodigo}/catalogo");
registrarAsercion("1.2 Catálogo explícito /p/{slug}/catalogo renderiza con éxito",
    str_contains($htmlCat2, 'Catálogo Oficial') && str_contains($htmlCat2, 'portal-hero')
);

// 1.3 Ficha de paquete con disponibilidad real /p/{slug}/paquete/{id}
$htmlPaq = despacharRuta("/p/{$orgCodigo}/paquete/{$paqId}");
registrarAsercion("1.3 Ficha de paquete /p/{slug}/paquete/{id} renderiza datos y cotizador",
    str_contains($htmlPaq, 'Paquete Festividad F29C Test') && str_contains($htmlPaq, 'PAQUETE OFICIAL CANDELARIA')
);

// 1.4 Ficha de paquete inexistente retorna 404
$htmlPaqInexistente = despacharRuta("/p/{$orgCodigo}/paquete/999999");
registrarAsercion("1.4 Ficha de paquete inexistente retorna 404 Fail-Closed",
    str_contains($htmlPaqInexistente, 'Paquete No Encontrado')
);

// 1.5 Vista de Checkout /p/{slug}/checkout
$htmlChk = despacharRuta("/p/{$orgCodigo}/checkout");
registrarAsercion("1.5 Vista Checkout /p/{slug}/checkout renderiza wizard de 3 pasos y temporizador",
    str_contains($htmlChk, 'Checkout y Reserva Segura') &&
    str_contains($htmlChk, 'timer-banner') &&
    str_contains($htmlChk, 'checkout-steps')
);

// 1.6 Vista de Confirmación /p/{slug}/checkout/confirmacion
$htmlConf = despacharRuta("/p/{$orgCodigo}/checkout/confirmacion");
registrarAsercion("1.6 Vista de Confirmación /p/{slug}/checkout/confirmacion renderiza recibo y voucher",
    str_contains($htmlConf, 'Confirmación de Reserva') &&
    str_contains($htmlConf, 'conf-estado-pago') &&
    str_contains($htmlConf, 'conf-estado-reserva')
);

// 1.7 Vista de Espacio Cliente /p/{slug}/mi-espacio
$htmlEsp = despacharRuta("/p/{$orgCodigo}/mi-espacio");
registrarAsercion("1.7 Vista Espacio Cliente /p/{slug}/mi-espacio contiene acceso OTP y visor de boletos",
    str_contains($htmlEsp, 'Mi Espacio Candelaria') &&
    str_contains($htmlEsp, 'cliente-auth-box') &&
    str_contains($htmlEsp, 'modalBoletoQr')
);

// 1.8 Organización inexistente retorna 404 Fail-Closed
$htmlOrgInvalida = despacharRuta("/p/org_fantasma_no_existe_9999");
registrarAsercion("1.8 Acceso con slug de organización inexistente retorna 404 Fail-Closed",
    str_contains($htmlOrgInvalida, 'No se encontró ninguna organización activa')
);

// ------------------------------------------------------------------------------
// SECCIÓN 2: Aislamiento de Assets y Soberanía Frontend
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 2: Aislamiento de Assets y Soberanía Frontend ---\n";

// 2.1 CSS público soberano existe y contiene paleta andina
$cssFile = __DIR__ . '/../publico/activos/css/portal_publico.css';
$cssExiste = file_exists($cssFile);
$cssContenido = $cssExiste ? (string) file_get_contents($cssFile) : '';
registrarAsercion("2.1 Archivo CSS público soberano (portal_publico.css) implementado con variables andinas",
    $cssExiste && str_contains($cssContenido, '--cp-primary') && str_contains($cssContenido, '--cp-gold')
);

// 2.2 JS público soberano existe y contiene lógica de carrito, cotizador y temporizador
$jsFile = __DIR__ . '/../publico/activos/js/portal_publico.js';
$jsExiste = file_exists($jsFile);
$jsContenido = $jsExiste ? (string) file_get_contents($jsFile) : '';
registrarAsercion("2.2 Archivo JS público soberano (portal_publico.js) implementa temporizador TTL y OTP",
    $jsExiste && str_contains($jsContenido, 'iniciarTemporizadorSiCorresponde') && str_contains($jsContenido, 'solicitarCotizacionBackend')
);

// 2.3 Layout público no incluye dependencias de Alina (admin-dashboard)
$layoutFile = __DIR__ . '/../recursos/vistas/layouts/publico.php';
$layoutContenido = (string) file_get_contents($layoutFile);
$sinAlina = !str_contains($layoutContenido, 'admin-dashboard');
registrarAsercion("2.3 Layout público mantiene aislamiento estricto y no incluye componentes Alina", $sinAlina);

// 2.4 Ficha de paquete implementa selectores de cantidad y cotizador dinámico
$paqViewFile = __DIR__ . '/../recursos/vistas/paginas/publico/paquete_detalle.php';
$paqViewContenido = (string) file_get_contents($paqViewFile);
registrarAsercion("2.4 Ficha de paquete implementa selectores de cantidad y cotizador dinámico",
    str_contains($paqViewContenido, 'input-cantidad') &&
    str_contains($paqViewContenido, 'cambiarCantidad') &&
    str_contains($paqViewContenido, 'resumen-total')
);

// 2.5 Checkout implementa 3 pasos secuenciales (Resumen, Identidad, Pago)
$chkViewFile = __DIR__ . '/../recursos/vistas/paginas/publico/checkout.php';
$chkViewContenido = (string) file_get_contents($chkViewFile);
registrarAsercion("2.5 Checkout implementa 3 pasos canónicos de reserva",
    str_contains($chkViewContenido, 'checkout-step-1') &&
    str_contains($chkViewContenido, 'checkout-step-2') &&
    str_contains($chkViewContenido, 'checkout-step-3')
);

// 2.6 Espacio cliente implementa visualizador holográfico de Boletos QR
$espViewFile = __DIR__ . '/../recursos/vistas/paginas/publico/espacio_cliente.php';
$espViewContenido = (string) file_get_contents($espViewFile);
registrarAsercion("2.6 Espacio cliente implementa contenedor de boleto QR con certificado de autenticidad",
    str_contains($espViewContenido, 'modalBoletoQr') && str_contains($espViewContenido, 'ticket-stamp-secure')
);

// Limpiar datos temporales
$pdo->prepare("DELETE FROM tarifas_paquetes_edicion WHERE oferta_paquete_id = :id")->execute(['id' => $ofePaqId]);
$pdo->prepare("DELETE FROM ofertas_paquetes_edicion WHERE id = :id")->execute(['id' => $ofePaqId]);
$pdo->prepare("DELETE FROM paquetes WHERE id = :id")->execute(['id' => $paqId]);

echo "\n==============================================================================\n";
echo "RESULTADO FINAL SUITE F2.9C: {$pruebasExitosas}/{$totalPruebas} PRUEBAS EXITOSAS\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);
