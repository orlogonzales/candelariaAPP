<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Dashboard\DashboardEjecutivoServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Http\Vista;
use PDO;
use Throwable;

/**
 * Controlador oficial para el Dashboard Ejecutivo y Métricas de Rendimiento (F2.12C).
 */
class DashboardControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private DashboardEjecutivoServicio $dashboardServicio;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?DashboardEjecutivoServicio $dashboardServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();

        $authzServicio = new AutorizacionServicio(pdo: $this->pdo);
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware($authzServicio);
        $this->dashboardServicio = $dashboardServicio ?? new DashboardEjecutivoServicio($this->pdo);
    }

    /**
     * Renderiza la vista de Alina Admin para el Dashboard Ejecutivo.
     * GET /dashboard-ejecutivo
     */
    public function vista(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('dashboard.ejecutivo.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Dashboard Ejecutivo',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con el privilegio dashboard.ejecutivo.ver para consultar las métricas ejecutivas.'
            ], 'principal');
        }

        // Obtener ediciones disponibles de la organización para el selector reactivo
        $stmtEd = $this->pdo->prepare("
            SELECT id, codigo, nombre, anio, estado, es_actual 
            FROM `ediciones_candelaria` 
            WHERE organizacion_id = :org_id 
            ORDER BY anio DESC
        ");
        $stmtEd->execute(['org_id' => $contexto->organizacionId]);
        $ediciones = $stmtEd->fetchAll(PDO::FETCH_ASSOC);

        return Vista::renderizar('dashboard/ejecutivo', [
            'titulo'          => 'Dashboard Ejecutivo | CandelariaAPP',
            'subtitulo'       => 'Métricas Consolidadas de Festividad y Rendimiento Integral',
            'tituloSeccion'   => 'Dashboard Ejecutivo',
            'seccionActiva'   => 'dashboard_ejecutivo',
            'ediciones'       => $ediciones,
            'scriptAdicional' => url_base('publico/js/dashboard_ejecutivo.js')
        ], 'principal');
    }

    /**
     * GET /api/v1/dashboard/kpis
     * Devuelve los KPIs consolidados de una edición en formato JSON.
     */
    public function kpis(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('dashboard.ejecutivo.ver');

            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $edicionId = isset($_GET['edicion_id']) && $_GET['edicion_id'] !== '' ? (int) $_GET['edicion_id'] : null;

            $datos = $this->dashboardServicio->obtenerKpisEjecutivos(
                (int) $contexto->organizacionId,
                $edicionId
            );

            return $this->responderExito($datos, 'Métricas ejecutivas consolidadas exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al calcular métricas ejecutivas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/dashboard/comparativa
     * Devuelve la comparativa histórica inter-ediciones en formato JSON.
     */
    public function comparativa(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('dashboard.ejecutivo.ver');

            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $datos = $this->dashboardServicio->obtenerComparativaHistorica((int) $contexto->organizacionId);

            return $this->responderExito($datos, 'Comparativa histórica generada exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al obtener comparativa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verifica la sesión activa y el permiso especificado.
     */
    private function verificarSesionYPermiso(string $permiso): ContextoOperacion
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            throw new AccesoDenegadoExcepcion('Sesión no válida o expirada.');
        }

        $autorizado = $this->authzMiddleware->verificarPermiso($permiso, $contexto, false);
        if (!$autorizado) {
            throw new AccesoDenegadoExcepcion("No cuenta con el privilegio requerido: [{$permiso}].");
        }

        return $contexto;
    }

    private function responderExito(mixed $datos = [], string $mensaje = 'Operación exitosa', int $codigo = 200): string
    {
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: application/json; charset=utf-8');
        }
        return json_encode([
            'exito'   => true,
            'mensaje' => $mensaje,
            'datos'   => $datos,
            'codigo'  => $codigo,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function responderError(string $mensaje, int $codigo = 400, array $detalles = []): string
    {
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: application/json; charset=utf-8');
        }
        return json_encode([
            'exito'    => false,
            'mensaje'  => $mensaje,
            'codigo'   => $codigo,
            'detalles' => $detalles,
        ], JSON_UNESCAPED_UNICODE);
    }
}
