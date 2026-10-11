<?php

declare(strict_types=1);

/**
 * Worker auxiliar para pruebas de concurrencia simultánea en check-in de boletos QR.
 * Simula dos lectores en puertas distintas escaneando el mismo código QR al unísono.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../../.env');

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Cotizaciones\CotizacionServicio;
use Aplicacion\Finanzas\PagoServicio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteCodigoAccesoRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\ClienteSesionRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\CotizacionRepositorio;
use Aplicacion\Repositorios\CuentaBancariaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\EntregaProductoRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\ItemConfiguracionOperativaRepositorio;
use Aplicacion\Repositorios\OfertaItemEdicionRepositorio;
use Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\PagoReembolsoRepositorio;
use Aplicacion\Repositorios\PagoRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\PasarelaRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\ReservaBloqueoTemporalRepositorio;
use Aplicacion\Repositorios\ReservaRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Reservas\ReservaServicio;
use Aplicacion\Servicios\AutenticacionClienteServicio;
use Aplicacion\Servicios\FachadaPortalPublicoServicio;
use Aplicacion\Servicios\MotorDisponibilidadServicio;
use Aplicacion\Ventas\VentaServicio;
use Nucleo\BaseDatos\Conexion;

$orgId = (int) ($argv[1] ?? 0);
$tokenQr = (string) ($argv[2] ?? '');

$pdo = Conexion::crearNuevaConexion();

$bloqueoRepo = new ReservaBloqueoTemporalRepositorio($pdo);
$sesionRepo = new ClienteSesionRepositorio($pdo);
$codigoRepo = new ClienteCodigoAccesoRepositorio($pdo);
$clienteRepo = new ClienteRepositorio($pdo);
$personaRepo = new PersonaRepositorio($pdo);
$edicionRepo = new EdicionRepositorio($pdo);
$paqueteRepo = new PaqueteRepositorio($pdo);
$itemRepo = new ItemComercialRepositorio($pdo);
$reservaRepo = new ReservaRepositorio($pdo);
$ventaRepo = new VentaRepositorio($pdo);
$pagoRepo = new PagoRepositorio($pdo);
$configRepo = new ConfiguracionRepositorio($pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);
$entregaRepo = new EntregaProductoRepositorio($pdo);
$itemCfgRepo = new ItemConfiguracionOperativaRepositorio($pdo);
$reembolsoRepo = new PagoReembolsoRepositorio($pdo);
$cuentaRepo = new CuentaBancariaRepositorio($pdo);
$pasarelaRepo = new PasarelaRepositorio($pdo);
$cotizacionRepo = new CotizacionRepositorio($pdo);
$oportunidadRepo = new OportunidadRepositorio($pdo);
$ofertaItemRepo = new OfertaItemEdicionRepositorio($pdo);
$ofertaPaqueteRepo = new OfertaPaqueteEdicionRepositorio($pdo);
$tarifaItemRepo = new TarifaItemEdicionRepositorio($pdo);
$tarifaPaqueteRepo = new TarifaPaqueteEdicionRepositorio($pdo);

$authClienteSvc = new AutenticacionClienteServicio($sesionRepo, $codigoRepo, $clienteRepo, $personaRepo);
$motorDisp = new MotorDisponibilidadServicio($bloqueoRepo, $pdo);
$configServicio = new ConfiguracionServicio(configRepo: $configRepo, pdo: $pdo);
$authzServicio = new AutorizacionServicio(null, null, null, null, $pdo);

$cotizacionServicio = new CotizacionServicio(
    $cotizacionRepo, $clienteRepo, $edicionRepo, $oportunidadRepo, $itemRepo,
    $paqueteRepo, $ofertaItemRepo, $ofertaPaqueteRepo, $tarifaItemRepo, $tarifaPaqueteRepo,
    $configServicio, $authzServicio, $auditoriaRepo, null, $pdo
);
$ventaServicio = new VentaServicio(
    $ventaRepo, $cotizacionRepo, $clienteRepo, $personaRepo, $edicionRepo,
    $configServicio, $authzServicio, $auditoriaRepo, null, $pdo
);
$reservaServicio = new ReservaServicio(
    $reservaRepo, $entregaRepo, $itemCfgRepo, $ventaRepo, $edicionRepo,
    $itemRepo, $authzServicio, $auditoriaRepo, $pdo
);
$pagoServicio = new PagoServicio(
    $pagoRepo, $reembolsoRepo, $cuentaRepo, $pasarelaRepo, $ventaRepo,
    $edicionRepo, $configRepo, $authzServicio, $auditoriaRepo, $pdo
);

$fachada = new FachadaPortalPublicoServicio(
    $motorDisp, $authClienteSvc, $cotizacionServicio, $ventaServicio,
    $reservaServicio, $pagoServicio, $edicionRepo, $paqueteRepo,
    $itemRepo, $clienteRepo, $personaRepo, $reservaRepo,
    $ventaRepo, $pagoRepo, $bloqueoRepo, $pdo
);

try {
    $res = $fachada->validarTokenBoletoQr($orgId, $tokenQr, marcarUso: true);
    echo json_encode($res);
} catch (\Throwable $e) {
    echo json_encode([
        'valido' => false,
        'motivo' => $e->getMessage(),
        'error'  => get_class($e),
    ]);
}
