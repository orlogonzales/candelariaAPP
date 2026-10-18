<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Documentos\BoletoImpresionServicio;
use Aplicacion\Documentos\ContratoDocumentoServicio;
use Aplicacion\Documentos\CotizacionDocumentoServicio;
use Aplicacion\Documentos\MotorPdfServicio;
use Aplicacion\Documentos\ReciboCajaServicio;
use Aplicacion\Servicios\FachadaPortalPublicoServicio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Controlador de Emisión y Descarga Segura de Documentos PDF Oficiales.
 * Aplica control RBAC granular, aislamiento multitenant estricto y streaming optimizado.
 */
class DocumentoControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private MotorPdfServicio $motorPdf;
    private CotizacionDocumentoServicio $cotizacionDocSvc;
    private ContratoDocumentoServicio $contratoDocSvc;
    private ReciboCajaServicio $reciboCajaSvc;
    private BoletoImpresionServicio $boletoDocSvc;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?MotorPdfServicio $motorPdf = null,
        ?CotizacionDocumentoServicio $cotizacionDocSvc = null,
        ?ContratoDocumentoServicio $contratoDocSvc = null,
        ?ReciboCajaServicio $reciboCajaSvc = null,
        ?BoletoImpresionServicio $boletoDocSvc = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->motorPdf = $motorPdf ?? new MotorPdfServicio();

        $this->cotizacionDocSvc = $cotizacionDocSvc ?? new CotizacionDocumentoServicio($this->motorPdf, $this->pdo);
        $this->contratoDocSvc = $contratoDocSvc ?? new ContratoDocumentoServicio($this->motorPdf, $this->pdo);
        $this->reciboCajaSvc = $reciboCajaSvc ?? new ReciboCajaServicio($this->motorPdf, $this->pdo);

        if ($boletoDocSvc !== null) {
            $this->boletoDocSvc = $boletoDocSvc;
        } else {
            $fachadaPortal = FachadaPortalPublicoServicio::crearPorDefecto($this->pdo);
            $this->boletoDocSvc = new BoletoImpresionServicio($this->motorPdf, $fachadaPortal, $this->pdo);
        }
    }

    /**
     * GET /api/v1/documentos/cotizacion/{id}/pdf
     */
    public function descargarCotizacionPdf(array $params = []): void
    {
        try {
            $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
            $this->verificarAlgunPermiso(['documentos.cotizaciones.descargar'], $contexto);

            $cotizacionId = (int) ($params['id'] ?? $_GET['id'] ?? 0);
            if ($cotizacionId <= 0) {
                throw new InvalidArgumentException("ID de cotización inválido.");
            }

            $resultado = $this->cotizacionDocSvc->generarPdf($contexto->organizacionId, $cotizacionId, $contexto->usuarioId);
            $nombreArchivo = sprintf('Cotizacion-%s.pdf', $resultado['correlativo']);

            $this->transmitirPdf($resultado['binario_pdf'], $nombreArchivo);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/documentos/contrato/{id}/pdf
     */
    public function descargarContratoPdf(array $params = []): void
    {
        try {
            $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
            $this->verificarAlgunPermiso(['documentos.contratos.descargar'], $contexto);

            $ventaId = (int) ($params['id'] ?? $_GET['id'] ?? 0);
            if ($ventaId <= 0) {
                throw new InvalidArgumentException("ID de venta inválido.");
            }

            $resultado = $this->contratoDocSvc->generarPdf($contexto->organizacionId, $ventaId, $contexto->usuarioId);
            $nombreArchivo = sprintf('Contrato-%s.pdf', $resultado['correlativo']);

            $this->transmitirPdf($resultado['binario_pdf'], $nombreArchivo);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/documentos/recibo/{id}/pdf
     * Nota: id corresponde al pago_id formal
     */
    public function descargarReciboPdf(array $params = []): void
    {
        try {
            $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
            $this->verificarAlgunPermiso(['documentos.recibos.descargar', 'documentos.recibos.emitir'], $contexto);

            $pagoId = (int) ($params['id'] ?? $_GET['id'] ?? 0);
            if ($pagoId <= 0) {
                throw new InvalidArgumentException("ID de pago inválido.");
            }

            $resultado = $this->reciboCajaSvc->generarRecibo($contexto->organizacionId, $pagoId, (int) $contexto->usuarioId);
            $nombreArchivo = sprintf('Recibo-%s.pdf', $resultado['correlativo']);

            $this->transmitirPdf($resultado['binario_pdf'], $nombreArchivo);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    /**
     * GET /api/v1/documentos/boleto/{reservaId}/pdf
     */
    public function descargarBoletoPdf(array $params = []): void
    {
        try {
            $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
            $this->verificarAlgunPermiso(['documentos.boletos.descargar'], $contexto);

            $reservaId = (int) ($params['reservaId'] ?? $params['id'] ?? $_GET['id'] ?? 0);
            if ($reservaId <= 0) {
                throw new InvalidArgumentException("ID de reserva inválido.");
            }

            $formato = strtoupper((string) ($_GET['formato'] ?? 'A4'));
            if (!in_array($formato, ['A4', 'TICKET_80MM'], true)) {
                $formato = 'A4';
            }

            $resultado = $this->boletoDocSvc->generarBoleto(
                $contexto->organizacionId,
                $reservaId,
                $formato,
                usuarioEmisorId: $contexto->usuarioId
            );

            $nombreArchivo = sprintf('Boleto-%s-%s.pdf', $formato, $resultado['correlativo']);

            $this->transmitirPdf($resultado['binario_pdf'], $nombreArchivo);
        } catch (Throwable $e) {
            $this->manejarExcepcion($e);
        }
    }

    private function verificarAlgunPermiso(array $permisos, $contexto): void
    {
        foreach ($permisos as $p) {
            if ($this->authzMiddleware->verificarPermiso($p, $contexto, false)) {
                return;
            }
        }
        // Si no cumple ninguno, bloquear con el primer permiso
        $this->authzMiddleware->verificarPermiso($permisos[0], $contexto, true);
    }

    private function transmitirPdf(string $binarioPdf, string $nombreArchivo): void
    {
        $descargar = !empty($_GET['descargar']);
        $disposition = $descargar ? 'attachment' : 'inline';

        if (ob_get_level() > 0) {
            ob_clean();
        }

        header('Content-Type: application/pdf');
        header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $nombreArchivo));
        header('Content-Length: ' . strlen($binarioPdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $binarioPdf;
        $GLOBALS['__ultimo_codigo_http_api'] = 200;
        if (php_sapi_name() === 'cli') {
            return;
        }
        exit;
    }

    private function manejarExcepcion(Throwable $e): void
    {
        error_log("[PDF TRACE] " . $e->getMessage() . "\n" . $e->getTraceAsString());
        $codigo = 500;
        if ($e instanceof InvalidArgumentException) {
            $codigo = 400;
        } elseif ($e instanceof \Aplicacion\Excepciones\AccesoDenegadoExcepcion) {
            $codigo = 403;
        }

        $GLOBALS['__ultimo_codigo_http_api'] = $codigo;
        http_response_code($codigo);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'exito'        => false,
            'codigo'       => $codigo,
            'codigo_error' => (new \ReflectionClass($e))->getShortName(),
            'mensaje'      => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
