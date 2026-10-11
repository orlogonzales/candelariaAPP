<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Servicios\AutenticacionClienteServicio;
use Aplicacion\Servicios\FachadaPortalPublicoServicio;
use InvalidArgumentException;
use Nucleo\Http\Middleware\AutenticacionClienteMiddleware;
use Nucleo\Http\Middleware\ResolucionTenantPublicoMiddleware;
use RuntimeException;
use Throwable;

class PortalPublicoControlador
{
    private FachadaPortalPublicoServicio $fachada;
    private AutenticacionClienteServicio $authClienteSvc;
    private ResolucionTenantPublicoMiddleware $tenantMiddleware;
    private AutenticacionClienteMiddleware $authClienteMiddleware;

    public function __construct(
        ?FachadaPortalPublicoServicio $fachada = null,
        ?AutenticacionClienteServicio $authClienteSvc = null,
        ?ResolucionTenantPublicoMiddleware $tenantMiddleware = null,
        ?AutenticacionClienteMiddleware $authClienteMiddleware = null
    ) {
        $pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();
        $this->tenantMiddleware = $tenantMiddleware ?? new ResolucionTenantPublicoMiddleware($pdo);
        $this->authClienteMiddleware = $authClienteMiddleware ?? new AutenticacionClienteMiddleware();

        if ($authClienteSvc !== null) {
            $this->authClienteSvc = $authClienteSvc;
        } else {
            $sesionRepo = new \Aplicacion\Repositorios\ClienteSesionRepositorio($pdo);
            $codigoRepo = new \Aplicacion\Repositorios\ClienteCodigoAccesoRepositorio($pdo);
            $clienteRepo = new \Aplicacion\Repositorios\ClienteRepositorio($pdo);
            $personaRepo = new \Aplicacion\Repositorios\PersonaRepositorio($pdo);
            $this->authClienteSvc = new AutenticacionClienteServicio($sesionRepo, $codigoRepo, $clienteRepo, $personaRepo);
        }

        if ($fachada !== null) {
            $this->fachada = $fachada;
        } else {
            $bloqueoRepo = new \Aplicacion\Repositorios\ReservaBloqueoTemporalRepositorio($pdo);
            $motorDisp = new \Aplicacion\Servicios\MotorDisponibilidadServicio($bloqueoRepo, $pdo);
            $edicionRepo = new \Aplicacion\Repositorios\EdicionRepositorio($pdo);
            $paqueteRepo = new \Aplicacion\Repositorios\PaqueteRepositorio($pdo);
            $itemRepo = new \Aplicacion\Repositorios\ItemComercialRepositorio($pdo);
            $clienteRepo = new \Aplicacion\Repositorios\ClienteRepositorio($pdo);
            $personaRepo = new \Aplicacion\Repositorios\PersonaRepositorio($pdo);
            $reservaRepo = new \Aplicacion\Repositorios\ReservaRepositorio($pdo);
            $ventaRepo = new \Aplicacion\Repositorios\VentaRepositorio($pdo);
            $pagoRepo = new \Aplicacion\Repositorios\PagoRepositorio($pdo);
            $configRepo = new \Aplicacion\Repositorios\ConfiguracionRepositorio($pdo);
            $configServicio = new \Aplicacion\Configuracion\ConfiguracionServicio(configRepo: $configRepo, pdo: $pdo);
            $authzServicio = new \Aplicacion\Autorizacion\AutorizacionServicio(null, null, null, null, $pdo);
            $auditoriaRepo = new \Aplicacion\Repositorios\AuditoriaRepositorio($pdo);

            $cotizacionRepo = new \Aplicacion\Repositorios\CotizacionRepositorio($pdo);
            $oportunidadRepo = new \Aplicacion\Repositorios\OportunidadRepositorio($pdo);
            $ofertaItemRepo = new \Aplicacion\Repositorios\OfertaItemEdicionRepositorio($pdo);
            $ofertaPaqueteRepo = new \Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio($pdo);
            $tarifaItemRepo = new \Aplicacion\Repositorios\TarifaItemEdicionRepositorio($pdo);
            $tarifaPaqueteRepo = new \Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio($pdo);

            $cotizacionServicio = new \Aplicacion\Cotizaciones\CotizacionServicio(
                $cotizacionRepo,
                $clienteRepo,
                $edicionRepo,
                $oportunidadRepo,
                $itemRepo,
                $paqueteRepo,
                $ofertaItemRepo,
                $ofertaPaqueteRepo,
                $tarifaItemRepo,
                $tarifaPaqueteRepo,
                $configServicio,
                $authzServicio,
                $auditoriaRepo,
                null,
                $pdo
            );

            $ventaServicio = new \Aplicacion\Ventas\VentaServicio(
                $ventaRepo,
                $cotizacionRepo,
                $clienteRepo,
                $personaRepo,
                $edicionRepo,
                $configServicio,
                $authzServicio,
                $auditoriaRepo,
                null,
                $pdo
            );

            $entregaRepo = new \Aplicacion\Repositorios\EntregaProductoRepositorio($pdo);
            $itemCfgRepo = new \Aplicacion\Repositorios\ItemConfiguracionOperativaRepositorio($pdo);
            $reservaServicio = new \Aplicacion\Reservas\ReservaServicio(
                $reservaRepo,
                $entregaRepo,
                $itemCfgRepo,
                $ventaRepo,
                $edicionRepo,
                $itemRepo,
                $authzServicio,
                $auditoriaRepo,
                $pdo
            );

            $reembolsoRepo = new \Aplicacion\Repositorios\PagoReembolsoRepositorio($pdo);
            $cuentaRepo = new \Aplicacion\Repositorios\CuentaBancariaRepositorio($pdo);
            $pasarelaRepo = new \Aplicacion\Repositorios\PasarelaRepositorio($pdo);
            $pagoServicio = new \Aplicacion\Finanzas\PagoServicio(
                $pagoRepo,
                $reembolsoRepo,
                $cuentaRepo,
                $pasarelaRepo,
                $ventaRepo,
                $edicionRepo,
                $configRepo,
                $authzServicio,
                $auditoriaRepo,
                $pdo
            );

            $this->fachada = new FachadaPortalPublicoServicio(
                $motorDisp,
                $this->authClienteSvc,
                $cotizacionServicio,
                $ventaServicio,
                $reservaServicio,
                $pagoServicio,
                $edicionRepo,
                $paqueteRepo,
                $itemRepo,
                $clienteRepo,
                $personaRepo,
                $reservaRepo,
                $ventaRepo,
                $pagoRepo,
                $bloqueoRepo,
                $pdo
            );
        }
    }


    /**
     * GET /api/v1/publico/edicion-actual
     */
    public function edicionActual(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $datos = $this->fachada->obtenerEdicionActual($tenant['organizacion_id']);

            if ($datos === null) {
                $this->responderJson([
                    'exito'        => false,
                    'codigo_error' => 'EDICION_NO_ENCONTRADA',
                    'mensaje'      => 'No hay una edición activa configurada para esta organización.',
                ], 404);
                return;
            }

            $this->responderJson([
                'exito' => true,
                'datos' => $datos,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/catalogo
     */
    public function catalogo(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $edicionInfo = $this->fachada->obtenerEdicionActual($tenant['organizacion_id']);

            if ($edicionInfo === null) {
                $this->responderJson([
                    'exito'        => false,
                    'codigo_error' => 'EDICION_NO_ENCONTRADA',
                    'mensaje'      => 'No hay una edición activa disponible.',
                ], 404);
                return;
            }

            $edicionId = (int) $edicionInfo['edicion']['id'];
            $categoriaId = isset($_GET['categoria_id']) ? (int) $_GET['categoria_id'] : null;

            $catalogo = $this->fachada->obtenerCatalogoPublico($tenant['organizacion_id'], $edicionId, $categoriaId);

            $this->responderJson([
                'exito' => true,
                'datos' => $catalogo,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * POST /api/v1/publico/cotizar
     */
    public function cotizar(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_POST);
            $edicionInfo = $this->fachada->obtenerEdicionActual($tenant['organizacion_id']);
            if ($edicionInfo === null) {
                $this->responderJson(['exito' => false, 'mensaje' => 'Edición no activa.'], 404);
                return;
            }

            $cuerpo = $this->obtenerCuerpoJson();
            $lineas = $cuerpo['lineas'] ?? [];

            $desglose = $this->fachada->cotizar($tenant['organizacion_id'], (int) $edicionInfo['edicion']['id'], $lineas);

            $this->responderJson([
                'exito' => true,
                'datos' => $desglose,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * POST /api/v1/publico/cupos/bloquear
     */
    public function bloquearCupos(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_POST);
            $edicionInfo = $this->fachada->obtenerEdicionActual($tenant['organizacion_id']);
            if ($edicionInfo === null) {
                $this->responderJson(['exito' => false, 'mensaje' => 'Edición no activa.'], 404);
                return;
            }

            $cuerpo = $this->obtenerCuerpoJson();
            $itemId = (int) ($cuerpo['item_id'] ?? 0);
            $cantidad = (int) ($cuerpo['cantidad'] ?? 1);
            $sesionId = (string) ($cuerpo['identificador_sesion'] ?? $_COOKIE['PHPSESSID'] ?? bin2hex(random_bytes(16)));
            $fecha = $cuerpo['fecha_servicio'] ?? null;
            $hora = $cuerpo['hora_servicio'] ?? null;
            $salidaId = isset($cuerpo['salida_id']) ? (int) $cuerpo['salida_id'] : null;

            $resultado = $this->fachada->bloquearCupos(
                $tenant['organizacion_id'],
                (int) $edicionInfo['edicion']['id'],
                $itemId,
                $cantidad,
                $sesionId,
                $fecha,
                $hora,
                $salidaId
            );

            if (!$resultado['exito']) {
                $this->responderJson([
                    'exito'        => false,
                    'codigo_error' => 'CUPOS_NO_DISPONIBLES',
                    'mensaje'      => $resultado['mensaje'],
                ], 409);
                return;
            }

            $this->responderJson([
                'exito' => true,
                'datos' => $resultado,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * POST /api/v1/publico/checkout
     */
    public function checkout(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_POST);
            $edicionInfo = $this->fachada->obtenerEdicionActual($tenant['organizacion_id']);
            if ($edicionInfo === null) {
                $this->responderJson(['exito' => false, 'mensaje' => 'Edición no activa.'], 404);
                return;
            }

            $cuerpo = $this->obtenerCuerpoJson();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $resultado = $this->fachada->procesarCheckout(
                $tenant['organizacion_id'],
                (int) $edicionInfo['edicion']['id'],
                $cuerpo,
                $ip,
                $ua
            );

            $this->responderJson([
                'exito' => true,
                'datos' => $resultado,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * POST /api/v1/publico/portal/solicitar-acceso
     */
    public function solicitarAcceso(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_POST);
            $cuerpo = $this->obtenerCuerpoJson();
            $canal = strtoupper(trim((string) ($cuerpo['canal'] ?? 'WHATSAPP')));

            if ($canal === 'WHATSAPP') {
                $telefono = (string) ($cuerpo['telefono'] ?? '');
                $res = $this->authClienteSvc->solicitarOtpWhatsApp($tenant['organizacion_id'], $telefono);
            } else {
                $correo = (string) ($cuerpo['correo'] ?? '');
                $res = $this->authClienteSvc->solicitarMagicLinkEmail($tenant['organizacion_id'], $correo);
            }

            $status = $res['exito'] ? 200 : 400;
            $this->responderJson($res, $status);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * POST /api/v1/publico/portal/verificar-acceso
     */
    public function verificarAcceso(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_POST);
            $cuerpo = $this->obtenerCuerpoJson();
            $canal = strtoupper(trim((string) ($cuerpo['canal'] ?? 'WHATSAPP')));
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

            if ($canal === 'WHATSAPP') {
                $telefono = (string) ($cuerpo['telefono'] ?? '');
                $codigo = (string) ($cuerpo['codigo'] ?? '');
                $res = $this->authClienteSvc->verificarOtpWhatsApp($tenant['organizacion_id'], $telefono, $codigo, $ip, $ua);
            } else {
                $correo = (string) ($cuerpo['correo'] ?? '');
                $token = (string) ($cuerpo['token'] ?? '');
                $res = $this->authClienteSvc->verificarMagicLink($tenant['organizacion_id'], $correo, $token, $ip, $ua);
            }

            if (!$res['exito']) {
                $this->responderJson($res, 401);
                return;
            }

            // Emitir cookie httpOnly
            if (!empty($res['token_sesion'])) {
                setcookie(
                    AutenticacionClienteServicio::COOKIE_CLIENTE_SESION,
                    $res['token_sesion'],
                    [
                        'expires'  => time() + (30 * 86400),
                        'path'     => '/',
                        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]
                );
            }

            $this->responderJson($res);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/portal/mis-reservas
     */
    public function misReservas(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->authClienteMiddleware->procesar($_SERVER, $_COOKIE, true);

            $reservas = $this->fachada->obtenerMisReservas($tenant['organizacion_id'], (int) $sesion->clienteId);

            $this->responderJson([
                'exito' => true,
                'datos' => $reservas,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/portal/reserva/{id}/boleto-qr
     */
    public function boletoQr(array $params = []): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->authClienteMiddleware->procesar($_SERVER, $_COOKIE, true);
            $reservaId = (int) ($params['id'] ?? $_GET['id'] ?? 0);

            $res = $this->fachada->generarTokenBoletoQr($tenant['organizacion_id'], $reservaId, (int) $sesion->clienteId);

            $status = $res['exito'] ? 200 : 403;
            $this->responderJson($res, $status);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * POST /api/v1/publico/portal/cerrar-sesion
     */
    public function cerrarSesion(): void
    {
        $token = $_COOKIE[AutenticacionClienteServicio::COOKIE_CLIENTE_SESION] ?? null;
        if ($token !== null) {
            $this->authClienteSvc->cerrarSesion((string) $token);
            setcookie(AutenticacionClienteServicio::COOKIE_CLIENTE_SESION, '', time() - 3600, '/');
        }

        $this->responderJson([
            'exito'   => true,
            'mensaje' => 'Sesión cerrada correctamente.',
        ]);
    }

    private function obtenerCuerpoJson(): array
    {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $json = json_decode($input, true);
            if (is_array($json)) {
                return $json;
            }
        }
        return $_POST;
    }

    private function responderJson(array $datos, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }

    private function manejarExcepcion(Throwable $e): void
    {
        $codigo = 500;
        if ($e instanceof InvalidArgumentException) {
            $codigo = 400;
        } elseif ($e instanceof AccesoDenegadoExcepcion) {
            $codigo = 403;
        }

        $this->responderJson([
            'exito'        => false,
            'codigo_error' => (new \ReflectionClass($e))->getShortName(),
            'mensaje'      => $e->getMessage(),
        ], $codigo);
    }
}
