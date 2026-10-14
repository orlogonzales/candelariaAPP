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
     * GET /api/v1/publico/portal/reserva/{id}/boleto-pdf
     * Descarga segura de boleto imprimible (A4 o Ticket 80mm) para el titular autenticado.
     */
    public function descargarBoletoPdf(array $params = []): void

    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->authClienteMiddleware->procesar($_SERVER, $_COOKIE, true);
            $reservaId = (int) ($params['id'] ?? $_GET['id'] ?? 0);

            $formato = strtoupper((string) ($_GET['formato'] ?? 'A4'));
            if (!in_array($formato, ['A4', 'TICKET_80MM'], true)) {
                $formato = 'A4';
            }

            $pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();
            $motorPdf = new \Aplicacion\Documentos\MotorPdfServicio();
            $boletoSvc = new \Aplicacion\Documentos\BoletoImpresionServicio($motorPdf, $this->fachada, $pdo);

            $res = $boletoSvc->generarBoleto(
                $tenant['organizacion_id'],
                $reservaId,
                $formato,
                (int) $sesion->clienteId
            );

            $disposition = !empty($_GET['descargar']) ? 'attachment' : 'inline';
            $nombreArchivo = sprintf('Boleto-%s-%s.pdf', $formato, $res['correlativo']);

            if (ob_get_level() > 0) {
                ob_clean();
            }

            header('Content-Type: application/pdf');
            header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $nombreArchivo));
            header('Content-Length: ' . strlen($res['binario_pdf']));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $res['binario_pdf'];
            exit;
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/portal/venta/{id}/contrato-pdf
     * Descarga de contrato oficial para el titular cliente.
     */
    public function descargarContratoPdf(array $params = []): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->authClienteMiddleware->procesar($_SERVER, $_COOKIE, true);
            $ventaId = (int) ($params['id'] ?? $_GET['id'] ?? 0);

            $pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();

            // Anti-IDOR: Validar que la venta pertenezca estrictamente al cliente de la sesión
            $stmtVta = $pdo->prepare("
                SELECT id, cliente_id, correlativo FROM `ventas`
                WHERE id = :id AND organizacion_id = :org_id
            ");
            $stmtVta->execute(['id' => $ventaId, 'org_id' => $tenant['organizacion_id']]);
            $venta = $stmtVta->fetch(PDO::FETCH_ASSOC);

            if (!$venta || (int) $venta['cliente_id'] !== (int) $sesion->clienteId) {
                throw new AccesoDenegadoExcepcion("Acceso denegado: El contrato no pertenece a su cuenta.");
            }

            $motorPdf = new \Aplicacion\Documentos\MotorPdfServicio();
            $contratoSvc = new \Aplicacion\Documentos\ContratoDocumentoServicio($motorPdf, $pdo);

            $res = $contratoSvc->generarPdf($tenant['organizacion_id'], $ventaId);

            $disposition = !empty($_GET['descargar']) ? 'attachment' : 'inline';
            $nombreArchivo = sprintf('Contrato-%s.pdf', $res['correlativo']);

            if (ob_get_level() > 0) {
                ob_clean();
            }

            header('Content-Type: application/pdf');
            header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $nombreArchivo));
            header('Content-Length: ' . strlen($res['binario_pdf']));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $res['binario_pdf'];
            exit;
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/portal/perfil
     * Consulta de datos de identidad, contacto, consentimientos y festividad activa.
     */
    public function miPerfil(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->resolverSesionCliente();

            $perfil = $this->fachada->obtenerPerfilCliente($tenant['organizacion_id'], $sesion->clienteId);

            $this->responderJson([
                'exito' => true,
                'datos' => $perfil,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * PUT /api/v1/publico/portal/perfil
     * Actualización segura de teléfono WhatsApp, correo y consentimientos Ley 29733.
     */
    public function actualizarMiPerfil(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->resolverSesionCliente();
            $body = $this->obtenerCuerpoJson();

            $perfil = $this->fachada->actualizarPerfilCliente(
                $tenant['organizacion_id'],
                $sesion->clienteId,
                $body,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            $this->responderJson([
                'exito'   => true,
                'mensaje' => 'Perfil de contacto y consentimientos actualizados exitosamente.',
                'datos'   => $perfil,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/portal/estado-cuenta
     * Estado financiero consolidado, ventas, conceptos, pagos y recibos con importes del backend.
     */
    public function miEstadoCuenta(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->resolverSesionCliente();

            $estadoCuenta = $this->fachada->obtenerEstadoCuentaCliente($tenant['organizacion_id'], $sesion->clienteId);

            $this->responderJson([
                'exito' => true,
                'datos' => $estadoCuenta,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/portal/recibo/{id}
     * Descarga de recibo de caja PDF generado para un abono verificado del cliente titular (Anti-IDOR).
     */
    public function descargarReciboPdf(array $params = []): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->resolverSesionCliente();
            $id = (int) ($params['id'] ?? $_GET['id'] ?? 0);

            if ($id <= 0) {
                throw new InvalidArgumentException("Identificador de recibo o pago inválido.");
            }

            $pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();

            // Buscar pago o recibo validando pertenencia estricta al cliente
            $stmt = $pdo->prepare("
                SELECT p.id as pago_id, p.organizacion_id, p.estado, p.verificado_por, v.cliente_id
                FROM `pagos` p
                JOIN `ventas` v ON p.venta_id = v.id
                LEFT JOIN `recibos_caja` rc ON rc.pago_id = p.id
                WHERE (p.id = :id_pago OR rc.id = :id_recibo) AND p.organizacion_id = :org_id
                LIMIT 1
            ");
            $stmt->execute([
                'id_pago'   => $id,
                'id_recibo' => $id,
                'org_id'    => $tenant['organizacion_id'],
            ]);
            $pago = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$pago || (int) $pago['cliente_id'] !== (int) $sesion->clienteId) {
                throw new AccesoDenegadoExcepcion("Acceso denegado: El recibo no pertenece a su cuenta.");
            }

            if ($pago['estado'] !== 'APROBADO') {
                throw new InvalidArgumentException("Solo se encuentran disponibles recibos de caja para pagos en estado APROBADO.");
            }

            $cajeroId = !empty($pago['verificado_por']) 
                ? (int) $pago['verificado_por'] 
                : $this->fachada->resolverUsuarioSistemaId($tenant['organizacion_id']);

            $motorPdf = new \Aplicacion\Documentos\MotorPdfServicio();
            $reciboSvc = new \Aplicacion\Documentos\ReciboCajaServicio($motorPdf, $pdo);

            $res = $reciboSvc->generarRecibo($tenant['organizacion_id'], (int) $pago['pago_id'], $cajeroId);

            $disposition = !empty($_GET['descargar']) ? 'attachment' : 'inline';
            $nombreArchivo = sprintf('Recibo-%s.pdf', $res['correlativo']);

            if (ob_get_level() > 0) {
                ob_clean();
            }

            header('Content-Type: application/pdf');
            header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $nombreArchivo));
            header('Content-Length: ' . strlen($res['binario_pdf']));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $res['binario_pdf'];
            exit;
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/portal/mis-galerias
     */
    public function listarMisGalerias(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->resolverSesionCliente();
            $pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();
            $postSvc = new \Aplicacion\Postproduccion\PostproduccionServicio($pdo);

            $proyectos = $postSvc->listarProyectosCliente($tenant['organizacion_id'], $sesion->clienteId);

            $this->responderJson([
                'exito' => true,
                'datos' => $proyectos,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/portal/mis-galerias/{id}
     */
    public function verMiGaleriaDetalle(mixed $id = null): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->resolverSesionCliente();
            $proyectoId = (int) ($id ?? ($_GET['id'] ?? 0));

            $pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();
            $postSvc = new \Aplicacion\Postproduccion\PostproduccionServicio($pdo);

            $detalle = $postSvc->obtenerDetalleProyectoCliente($tenant['organizacion_id'], $proyectoId, $sesion->clienteId);
            if (!$detalle) {
                throw new AccesoDenegadoExcepcion("Proyecto multimedia no encontrado o acceso denegado.");
            }

            $this->responderJson([
                'exito' => true,
                'datos' => $detalle,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/portal/galerias/{id}/fotos
     * Retorna las fotografías de una galería con estado de selección individual persistido (Anti-IDOR).
     */
    public function listarFotosGaleria(mixed $id = null): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->resolverSesionCliente();
            $galeriaId = (int) ($id ?? ($_GET['id'] ?? 0));

            $pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();
            $postSvc = new \Aplicacion\Postproduccion\PostproduccionServicio($pdo);

            $datos = $postSvc->obtenerFotosGaleriaCliente($tenant['organizacion_id'], $galeriaId, $sesion->clienteId);

            $this->responderJson([
                'exito' => true,
                'datos' => $datos,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * POST /api/v1/publico/portal/galerias/{id}/seleccionar
     * Marca o desmarca una fotografía favorita persistiendo en postproduccion_selecciones.
     */
    public function guardarSeleccionFoto(mixed $id = null): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->resolverSesionCliente();
            $galeriaId = (int) ($id ?? ($_GET['id'] ?? 0));
            $body = $this->obtenerCuerpoJson();

            $archivoId = (int) ($body['archivo_id'] ?? 0);
            $seleccionada = isset($body['seleccionada']) ? (bool) $body['seleccionada'] : true;
            $notas = isset($body['notas_cliente']) ? (string) $body['notas_cliente'] : (isset($body['notas']) ? (string) $body['notas'] : null);

            if ($archivoId <= 0) {
                throw new InvalidArgumentException("ID de fotografía inválido.");
            }

            $pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();
            $postSvc = new \Aplicacion\Postproduccion\PostproduccionServicio($pdo);

            $resultado = $postSvc->guardarSeleccionFotoCliente(
                $tenant['organizacion_id'],
                $galeriaId,
                $archivoId,
                $sesion->clienteId,
                $seleccionada,
                $notas
            );

            $this->responderJson([
                'exito'   => true,
                'mensaje' => $seleccionada ? 'Fotografía añadida a su selección de favoritos.' : 'Fotografía retirada de su selección.',
                'datos'   => $resultado,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/portal/mis-selecciones
     * Retorna todas las fotografías seleccionadas por el cliente en todos sus proyectos.
     */
    public function listarMisSelecciones(): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->resolverSesionCliente();

            $pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();
            $postSvc = new \Aplicacion\Postproduccion\PostproduccionServicio($pdo);

            $selecciones = $postSvc->listarMisSeleccionesCliente($tenant['organizacion_id'], $sesion->clienteId);

            $this->responderJson([
                'exito' => true,
                'datos' => $selecciones,
            ]);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/publico/portal/descargar-multimedia/{id}
     */
    public function descargarMiArchivoMultimedia(mixed $id = null): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->resolverSesionCliente();
            $archivoId = (int) ($id ?? ($_GET['id'] ?? 0));

            $esPreview = !empty($_GET['preview']) || !empty($_GET['miniatura']);

            $pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();
            $postSvc = new \Aplicacion\Postproduccion\PostproduccionServicio($pdo);

            // Validación Anti-IDOR para previsualización y descarga
            $archivoInfo = $postSvc->obtenerArchivo($tenant['organizacion_id'], $archivoId);
            if (!$archivoInfo) {
                throw new \Aplicacion\Excepciones\AccesoDenegadoExcepcion("Archivo multimedia no encontrado en su organización.");
            }
            $proyectoInfo = $postSvc->obtenerProyecto($tenant['organizacion_id'], (int) $archivoInfo['proyecto_id']);
            if (!$proyectoInfo || (int) $proyectoInfo['cliente_id'] !== (int) $sesion->clienteId) {
                throw new \Aplicacion\Excepciones\AccesoDenegadoExcepcion("Acceso denegado: El archivo no pertenece a su cuenta.");
            }

            // Si es descarga de archivo original de alta resolución, aplicar compuertas de autorización positiva (DT-V11-015)
            if (!$esPreview) {
                $postSvc->verificarAutorizacionDescargaOriginal(
                    $tenant['organizacion_id'],
                    $archivoId,
                    (int) $sesion->clienteId
                );
            }

            $res = $postSvc->obtenerStreamDescarga(
                $tenant['organizacion_id'],
                $archivoId,
                $sesion->clienteId,
                null,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            $archivo = $res['archivo'];
            $stream = $res['stream'];

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $disposition = $esPreview ? 'inline' : 'attachment';

            header('Content-Type: ' . $archivo['mime_type']);
            header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, basename($archivo['nombre_original'])));
            header('Content-Length: ' . $archivo['peso_bytes']);
            header('Cache-Control: private, no-transform, max-age=86400');

            fpassthru($stream);
            fclose($stream);
            exit(0);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * POST /api/v1/publico/portal/entregas/{id}/responder
     */
    public function responderEntregaMultimedia(mixed $id = null): void
    {
        try {
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $_GET);
            $sesion = $this->resolverSesionCliente();
            $entregaId = (int) ($id ?? ($_GET['id'] ?? 0));
            $body = $this->obtenerCuerpoJson();

            $aprobada = !empty($body['aprobada']);
            $observaciones = isset($body['observaciones']) ? (string) $body['observaciones'] : null;
            $versionBloqueo = isset($body['version_bloqueo']) ? (int) $body['version_bloqueo'] : null;

            $pdo = \Nucleo\BaseDatos\Conexion::obtenerInstancia();
            $postSvc = new \Aplicacion\Postproduccion\PostproduccionServicio($pdo);

            $entregaActualizada = $postSvc->responderEntregaCliente(
                $tenant['organizacion_id'],
                $entregaId,
                $sesion->clienteId,
                $aprobada,
                $observaciones,
                $versionBloqueo
            );

            $this->responderJson([
                'exito'   => true,
                'mensaje' => $aprobada ? 'Entrega aprobada satisfactoriamente.' : 'Observaciones registradas para el equipo de edición.',
                'datos'   => $entregaActualizada,
            ]);
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

    private function resolverSesionCliente(): object
    {
        $sesion = $this->authClienteMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($sesion !== null && !empty($sesion->clienteId)) {
            return $sesion;
        }

        // Si no vino por header/cookie, verificar query param token (e.g. descargas directas)
        $tokenParam = $_GET['token'] ?? null;
        if (!empty($tokenParam)) {
            $sesionData = $this->authClienteSvc->obtenerSesion((string) $tokenParam);
            if ($sesionData !== null && !empty($sesionData['cliente_id'])) {
                return (object) [
                    'clienteId' => (int) $sesionData['cliente_id'],
                    'organizacionId' => (int) ($sesionData['organizacion_id'] ?? 0),
                ];
            }
        }

        throw new AccesoDenegadoExcepcion("Acceso denegado: Sesión de cliente no iniciada o inválida.");
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
        $datos['codigo'] ??= $status;
        $datos['mensaje'] ??= ($datos['exito'] ?? false) ? 'Operación completada con éxito.' : 'Error en la solicitud.';
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }

    private function manejarExcepcion(Throwable $e): void
    {
        $codigo = 500;
        if ($e instanceof InvalidArgumentException) {
            $codigo = 400;
        } elseif ($e instanceof AccesoDenegadoExcepcion) {
            $codigo = 403;
        } elseif ($e instanceof \Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion) {
            $codigo = 409;
        }

        $this->responderJson([
            'exito'        => false,
            'codigo_error' => (new \ReflectionClass($e))->getShortName(),
            'mensaje'      => $e->getMessage(),
        ], $codigo);
    }

    /**
     * GET /p/{slug} y GET /p/{slug}/catalogo
     */
    public function vistaCatalogo(?string $slug = null): string
    {
        try {
            $peticion = $slug !== null ? ['slug_organizacion' => $slug] : $_GET;
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $peticion);
            $edicionInfo = $this->fachada->obtenerEdicionActual($tenant['organizacion_id']);

            $edicionId = $edicionInfo ? (int) $edicionInfo['edicion']['id'] : 0;
            $catalogo = $edicionId > 0 ? $this->fachada->obtenerCatalogoPublico($tenant['organizacion_id'], $edicionId) : ['paquetes' => [], 'items' => []];

            return \Nucleo\Http\Vista::renderizar('publico/catalogo', [
                'titulo'       => 'Catálogo Oficial | ' . $tenant['nombre'],
                'organizacion' => [
                    'id'               => $tenant['organizacion_id'],
                    'codigo'           => $tenant['codigo'],
                    'nombre_comercial' => $tenant['nombre'],
                ],
                'edicion'      => $edicionInfo['edicion'] ?? [],
                'catalogo'     => $catalogo,
            ], 'publico');
        } catch (Throwable $e) {
            http_response_code(404);
            return "<h1>404 No Encontrado</h1><p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
        }
    }

    /**
     * GET /p/{slug}/paquete/{id}
     */
    public function vistaPaquete(mixed $param1 = null, mixed $param2 = null): string
    {
        try {
            if ($param2 !== null) {
                // Si viene de /p/{slug}/paquete/{id}: param1 es slug, param2 es paqueteId
                $slug = is_numeric($param1) ? (string) $param2 : (string) $param1;
                $paqueteId = is_numeric($param2) ? (int) $param2 : (int) $param1;
            } else {
                $slug = is_string($param1) && !is_numeric($param1) ? $param1 : null;
                $paqueteId = is_numeric($param1) ? (int) $param1 : (int) ($_GET['id'] ?? 0);
            }

            $peticion = $slug !== null ? ['slug_organizacion' => $slug] : $_GET;
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $peticion);
            $edicionInfo = $this->fachada->obtenerEdicionActual($tenant['organizacion_id']);
            $edicionId = $edicionInfo ? (int) $edicionInfo['edicion']['id'] : 0;

            $paquete = $this->fachada->obtenerDetallePaquete($tenant['organizacion_id'], $edicionId, (int) $paqueteId);
            if (!$paquete) {
                http_response_code(404);
                return "<h1>Paquete No Encontrado</h1>";
            }

            return \Nucleo\Http\Vista::renderizar('publico/paquete_detalle', [
                'titulo'       => $paquete['nombre'] . ' | ' . $tenant['nombre'],
                'organizacion' => [
                    'id'               => $tenant['organizacion_id'],
                    'codigo'           => $tenant['codigo'],
                    'nombre_comercial' => $tenant['nombre'],
                ],
                'edicion'      => $edicionInfo['edicion'] ?? [],
                'paquete'      => $paquete,
            ], 'publico');
        } catch (Throwable $e) {
            http_response_code(404);
            return "<h1>404 No Encontrado</h1><p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
        }
    }

    /**
     * GET /p/{slug}/checkout
     */
    public function vistaCheckout(?string $slug = null): string
    {
        try {
            $peticion = $slug !== null ? ['slug_organizacion' => $slug] : $_GET;
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $peticion);
            $edicionInfo = $this->fachada->obtenerEdicionActual($tenant['organizacion_id']);

            return \Nucleo\Http\Vista::renderizar('publico/checkout', [
                'titulo'       => 'Checkout y Reserva Segura | ' . $tenant['nombre'],
                'organizacion' => [
                    'id'               => $tenant['organizacion_id'],
                    'codigo'           => $tenant['codigo'],
                    'nombre_comercial' => $tenant['nombre'],
                ],
                'edicion'      => $edicionInfo['edicion'] ?? [],
            ], 'publico');
        } catch (Throwable $e) {
            http_response_code(404);
            return "<h1>404 No Encontrado</h1><p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
        }
    }

    /**
     * GET /p/{slug}/checkout/confirmacion
     */
    public function vistaConfirmacion(?string $slug = null): string
    {
        try {
            $peticion = $slug !== null ? ['slug_organizacion' => $slug] : $_GET;
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $peticion);
            $edicionInfo = $this->fachada->obtenerEdicionActual($tenant['organizacion_id']);

            return \Nucleo\Http\Vista::renderizar('publico/checkout_confirmacion', [
                'titulo'       => 'Confirmación de Reserva | ' . $tenant['nombre'],
                'organizacion' => [
                    'id'               => $tenant['organizacion_id'],
                    'codigo'           => $tenant['codigo'],
                    'nombre_comercial' => $tenant['nombre'],
                ],
                'edicion'      => $edicionInfo['edicion'] ?? [],
            ], 'publico');
        } catch (Throwable $e) {
            http_response_code(404);
            return "<h1>404 No Encontrado</h1><p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
        }
    }

    /**
     * GET /p/{slug}/mi-espacio
     */
    public function vistaEspacioCliente(?string $slug = null): string
    {
        try {
            $peticion = $slug !== null ? ['slug_organizacion' => $slug] : $_GET;
            $tenant = $this->tenantMiddleware->resolver($_SERVER, $peticion);
            $edicionInfo = $this->fachada->obtenerEdicionActual($tenant['organizacion_id']);

            return \Nucleo\Http\Vista::renderizar('publico/espacio_cliente', [
                'titulo'       => 'Mi Espacio y Boletos Digitales | ' . $tenant['nombre'],
                'organizacion' => [
                    'id'               => $tenant['organizacion_id'],
                    'codigo'           => $tenant['codigo'],
                    'nombre_comercial' => $tenant['nombre'],
                ],
                'edicion'      => $edicionInfo['edicion'] ?? [],
            ], 'publico');
        } catch (Throwable $e) {
            http_response_code(404);
            return "<h1>404 No Encontrado</h1><p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
        }
    }
}
