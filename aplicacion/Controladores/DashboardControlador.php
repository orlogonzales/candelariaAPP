<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Dashboard\AnaliticaMultianualServicio;
use Aplicacion\Dashboard\DashboardEjecutivoServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Http\Vista;
use PDO;
use Throwable;

/**
 * Controlador oficial para el Dashboard Ejecutivo y Analítica Multianual (F2.12C / F4.1).
 */
class DashboardControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private DashboardEjecutivoServicio $dashboardServicio;
    private AnaliticaMultianualServicio $analiticaServicio;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?DashboardEjecutivoServicio $dashboardServicio = null,
        ?PDO $pdo = null,
        ?AnaliticaMultianualServicio $analiticaServicio = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();

        $authzServicio = new AutorizacionServicio(pdo: $this->pdo);
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware($authzServicio);
        $this->dashboardServicio = $dashboardServicio ?? new DashboardEjecutivoServicio($this->pdo);
        $this->analiticaServicio = $analiticaServicio ?? new AnaliticaMultianualServicio($this->pdo);
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
     * Renderiza la vista de Alina Admin para Analítica Multianual e Inteligencia Histórica.
     * GET /analitica-multianual
     */
    public function vistaAnaliticaMultianual(): string
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
                'subtitulo'     => 'Analítica Multianual',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con el privilegio dashboard.ejecutivo.ver para consultar la analítica multianual.'
            ], 'principal');
        }

        $stmtEd = $this->pdo->prepare("
            SELECT id, codigo, nombre, anio, estado, fecha_inicio, fecha_fin, es_actual
            FROM `ediciones_candelaria`
            WHERE organizacion_id = :org_id
            ORDER BY anio DESC, id DESC
        ");
        $stmtEd->execute(['org_id' => $contexto->organizacionId]);
        $ediciones = $stmtEd->fetchAll(PDO::FETCH_ASSOC);

        $stmtDanza = $this->pdo->prepare("
            SELECT id, codigo, nombre, categoria_origen
            FROM `tipos_danza`
            WHERE organizacion_id = :org_id AND activo = 1
            ORDER BY nombre ASC
        ");
        $stmtDanza->execute(['org_id' => $contexto->organizacionId]);
        $tiposDanza = $stmtDanza->fetchAll(PDO::FETCH_ASSOC);

        $puedeExportar = $this->authzMiddleware->verificarPermiso('ediciones.reportes.ingresos.exportar', $contexto, false);

        return Vista::renderizar('dashboard/analitica_multianual', [
            'titulo'          => 'Analítica Multianual | CandelariaAPP',
            'subtitulo'       => 'Comparativa Inter-Ediciones, Curvas de Preventa, Cohortes y Desempeño Integral',
            'tituloSeccion'   => 'Analítica Multianual',
            'seccionActiva'   => 'analitica_multianual',
            'ediciones'       => $ediciones,
            'tiposDanza'      => $tiposDanza,
            'puedeExportar'   => $puedeExportar,
            'scriptAdicional' => url_base('publico/js/analitica_multianual.js')
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
     * GET /api/v1/dashboard/analitica-multianual
     * Devuelve el informe analítico multianual completo (series financieras YoY, preventa, cohortes, folclore y operaciones).
     */
    public function analiticaMultianual(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('dashboard.ejecutivo.ver');

            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $datos = $this->analiticaServicio->obtenerAnaliticaMultianual(
                (int) $contexto->organizacionId,
                $_GET
            );

            return $this->responderExito($datos, 'Analítica multianual consolidada exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al calcular analítica multianual: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/dashboard/analitica-multianual/exportar
     * Exporta la comparativa multianual en formato CSV, XLSX o PDF institucional.
     * Exige el permiso explícito 'ediciones.reportes.ingresos.exportar'.
     */
    public function exportarAnaliticaMultianual(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('ediciones.reportes.ingresos.exportar');

            if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
                return $this->responderError('Contexto organizacional ausente.', 403);
            }

            $formato = strtolower(trim((string) ($_GET['formato'] ?? 'csv')));
            if (!in_array($formato, ['csv', 'xlsx', 'pdf'], true)) {
                return $this->responderError("Formato de exportación no soportado: '{$formato}'. Use csv, xlsx o pdf.", 422);
            }

            $orgId = (int) $contexto->organizacionId;
            $slugBase = 'analitica-multianual-org-' . $orgId;

            if ($formato === 'csv') {
                $contenido = $this->analiticaServicio->exportarCsv($orgId, $_GET);
                $GLOBALS['__ultimo_codigo_http_api'] = 200;
                if (php_sapi_name() === 'cli') {
                    return $contenido;
                }
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: text/csv; charset=utf-8');
                    header(sprintf('Content-Disposition: attachment; filename="%s_%s.csv"', $slugBase, date('Ymd_His')));
                    header('Content-Length: ' . strlen($contenido));
                }
                echo $contenido;
                if (php_sapi_name() === 'cli') {
                    return $contenido;
                }
                exit;
            }

            if ($formato === 'xlsx') {
                $contenido = $this->analiticaServicio->exportarXlsx($orgId, $_GET);
                $GLOBALS['__ultimo_codigo_http_api'] = 200;
                if (php_sapi_name() === 'cli') {
                    return $contenido;
                }
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header(sprintf('Content-Disposition: attachment; filename="%s_%s.xlsx"', $slugBase, date('Ymd_His')));
                    header('Content-Length: ' . strlen($contenido));
                }
                echo $contenido;
                exit;
            }

            // Formato PDF
            $stmtOrg = $this->pdo->prepare("SELECT nombre_comercial, razon_social, numero_documento FROM `organizaciones` WHERE `id` = :id LIMIT 1");
            $stmtOrg->execute(['id' => $orgId]);
            $tenantInfo = $stmtOrg->fetch(PDO::FETCH_ASSOC) ?: [];

            $contenido = $this->analiticaServicio->exportarPdf($orgId, $_GET, $tenantInfo);
            if (php_sapi_name() === 'cli') {
                return $contenido;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/pdf');
                header(sprintf('Content-Disposition: attachment; filename="%s_%s.pdf"', $slugBase, date('Ymd_His')));
                header('Content-Length: ' . strlen($contenido));
            }
            echo $contenido;
            exit;
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderError($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return $this->responderError($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->responderError('Error interno al exportar analítica multianual: ' . $e->getMessage(), 500);
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
