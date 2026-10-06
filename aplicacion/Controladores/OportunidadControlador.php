<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Crm\EtapaOportunidad;
use Aplicacion\Crm\MotivoPerdida;
use Aplicacion\Crm\OportunidadServicio;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\ConfiguracionOrganizacionRepositorio;
use Aplicacion\Repositorios\ConfiguracionPlataformaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\HistorialEtapaRepositorio;
use Aplicacion\Repositorios\InteraccionCrmRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\OrigenComercialRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Http\Vista;
use Nucleo\Seguridad\ManejadorCookie;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;
use Throwable;

/**
 * Controlador oficial para la gestión comercial de Oportunidades en CRM (F2.2C).
 */
class OportunidadControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private OportunidadServicio $oportunidadServicio;
    private OportunidadRepositorio $oportunidadRepo;
    private HistorialEtapaRepositorio $historialRepo;
    private InteraccionCrmRepositorio $interaccionRepo;
    private ClienteRepositorio $clienteRepo;
    private EdicionRepositorio $edicionRepo;
    private OrigenComercialRepositorio $origenRepo;
    private UsuarioRepositorio $usuarioRepo;
    private ContextoEdicionResolver $contextoEdicionResolver;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?OportunidadServicio $oportunidadServicio = null,
        ?OportunidadRepositorio $oportunidadRepo = null,
        ?HistorialEtapaRepositorio $historialRepo = null,
        ?InteraccionCrmRepositorio $interaccionRepo = null,
        ?ClienteRepositorio $clienteRepo = null,
        ?EdicionRepositorio $edicionRepo = null,
        ?OrigenComercialRepositorio $origenRepo = null,
        ?UsuarioRepositorio $usuarioRepo = null,
        ?ContextoEdicionResolver $contextoEdicionResolver = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->oportunidadRepo = $oportunidadRepo ?? new OportunidadRepositorio($this->pdo);
        $this->historialRepo = $historialRepo ?? new HistorialEtapaRepositorio($this->pdo);
        $this->interaccionRepo = $interaccionRepo ?? new InteraccionCrmRepositorio($this->pdo);
        $this->clienteRepo = $clienteRepo ?? new ClienteRepositorio($this->pdo);
        $this->edicionRepo = $edicionRepo ?? new EdicionRepositorio($this->pdo);
        $this->origenRepo = $origenRepo ?? new OrigenComercialRepositorio($this->pdo);
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
        $this->contextoEdicionResolver = $contextoEdicionResolver ?? new ContextoEdicionResolver($this->edicionRepo);

        if ($oportunidadServicio !== null) {
            $this->oportunidadServicio = $oportunidadServicio;
        } else {
            $rolRepo = new RolRepositorio($this->pdo);
            $permRepo = new PermisoRepositorio($this->pdo);
            $auditoriaRepo = new AuditoriaRepositorio($this->pdo);
            $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $this->usuarioRepo, $auditoriaRepo, $this->pdo);
            $configServicio = new ConfiguracionServicio(
                authzServicio: $authzServicio,
                auditoriaRepo: $auditoriaRepo,
                pdo: $this->pdo
            );

            $this->oportunidadServicio = new OportunidadServicio(
                $this->oportunidadRepo,
                $this->historialRepo,
                $this->clienteRepo,
                $this->edicionRepo,
                $this->origenRepo,
                $this->usuarioRepo,
                $authzServicio,
                $auditoriaRepo,
                $configServicio,
                $this->pdo
            );
        }
    }

    /**
     * GET /crm/oportunidades
     * Renderiza la vista principal del Pipeline y Gestión de Oportunidades.
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.oportunidades.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'CRM Oportunidades',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (crm.oportunidades.ver) para acceder al módulo de oportunidades.'
            ], 'principal');
        }

        $orgId = (int) $contexto->organizacionId;
        $ediciones = $this->edicionRepo->listarPorOrganizacion($orgId);
        $origenes = $this->origenRepo->listarPorOrganizacion($orgId, true);
        $asesores = $this->usuarioRepo->buscarPorOrganizacion($orgId, 200, 0);

        return Vista::renderizar('crm/oportunidades', [
            'titulo'          => 'Oportunidades Comerciales | CandelariaAPP',
            'subtitulo'       => 'Pipeline y Seguimiento de Prospectos por Edición',
            'tituloSeccion'   => 'CRM Oportunidades',
            'seccionActiva'   => 'crm_oportunidades',
            'ediciones'       => $ediciones,
            'origenes'        => $origenes,
            'asesores'        => array_filter($asesores, fn($u) => $u->estado === 'ACTIVO'),
            'etapas'          => EtapaOportunidad::casos(),
            'motivosPerdida'  => MotivoPerdida::casos(),
            'scriptAdicional' => url_base('publico/js/crm_oportunidades.js')
        ], 'principal');
    }

    /**
     * GET /api/v1/crm/oportunidades
     * Listado asíncrono para DataTables con filtros de Edición, Etapa y Asignado.
     */
    public function listar(): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.oportunidades.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso crm.oportunidades.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;

            // Filtro por edición: si viene explícito en query o en header X-Edicion-Id
            $edicionId = null;
            if (isset($_GET['edicion_id']) && is_numeric($_GET['edicion_id'])) {
                $edicionId = (int) $_GET['edicion_id'];
            } elseif (!empty($_SERVER['HTTP_X_EDICION_ID']) && is_numeric($_SERVER['HTTP_X_EDICION_ID'])) {
                $edicionId = (int) $_SERVER['HTTP_X_EDICION_ID'];
            }

            $etapa = isset($_GET['etapa']) && !empty($_GET['etapa']) ? (string) $_GET['etapa'] : null;
            $usuarioAsignadoId = isset($_GET['usuario_asignado_id']) && is_numeric($_GET['usuario_asignado_id'])
                ? (int) $_GET['usuario_asignado_id']
                : null;
            $origenComercialId = isset($_GET['origen_comercial_id']) && is_numeric($_GET['origen_comercial_id'])
                ? (int) $_GET['origen_comercial_id']
                : null;
            $busqueda = isset($_GET['busqueda']) ? (string) $_GET['busqueda'] : null;
            $limite = isset($_GET['limite']) ? max(1, min(200, (int) $_GET['limite'])) : 50;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

            $total = $this->oportunidadRepo->contarConDetalles(
                $orgId,
                $edicionId,
                $etapa,
                $usuarioAsignadoId,
                $origenComercialId,
                $busqueda
            );

            $oportunidades = $this->oportunidadRepo->listarConDetalles(
                $orgId,
                $edicionId,
                $etapa,
                $usuarioAsignadoId,
                $origenComercialId,
                $busqueda,
                $limite,
                $offset
            );

            return $this->responderJson(true, 200, 'Oportunidades consultadas exitosamente.', [
                'total'         => $total,
                'oportunidades' => $oportunidades,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/crm/oportunidades/{id}
     * Detalle completo de la oportunidad, historial de etapas e interacciones asociadas.
     */
    public function detalle(string|int|array $id = 0): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.oportunidades.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso crm.oportunidades.ver.');
        }

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $oportunidad = $this->oportunidadRepo->buscarDetallePorId((int) $contexto->organizacionId, $idInt);
            if ($oportunidad === null) {
                return $this->responderJson(false, 404, 'Oportunidad no encontrada en su organización.');
            }

            $historial = $this->historialRepo->listarConDetalles((int) $contexto->organizacionId, $idInt);
            $interacciones = $this->interaccionRepo->listarConDetalles(
                (int) $contexto->organizacionId,
                (int) $oportunidad['cliente_id'],
                $idInt,
                50
            );

            return $this->responderJson(true, 200, 'Detalle de la oportunidad.', [
                'oportunidad'   => $oportunidad,
                'historial'     => $historial,
                'interacciones' => $interacciones,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/crm/oportunidades
     * Crea una nueva oportunidad comercial. Moneda soberana resuelta por backend.
     */
    public function crear(): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.oportunidades.crear', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso crm.oportunidades.crear.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $this->verificarCsrf($contexto, $cuerpo);

        try {
            $edicionId = isset($cuerpo['edicion_id']) && (int) $cuerpo['edicion_id'] > 0
                ? (int) $cuerpo['edicion_id']
                : null;

            if ($edicionId === null && !empty($_SERVER['HTTP_X_EDICION_ID'])) {
                $edicionId = (int) $_SERVER['HTTP_X_EDICION_ID'];
            }

            if ($edicionId === null || $edicionId <= 0) {
                return $this->responderJson(false, 400, 'Debe especificar la edición a la que corresponde la oportunidad.');
            }

            $clienteId = isset($cuerpo['cliente_id']) ? (int) $cuerpo['cliente_id'] : 0;
            if ($clienteId <= 0) {
                return $this->responderJson(false, 400, 'Debe seleccionar un cliente válido para la oportunidad.');
            }

            $titulo = trim((string) ($cuerpo['titulo'] ?? ''));
            if ($titulo === '') {
                return $this->responderJson(false, 400, 'El título de la oportunidad es obligatorio.');
            }

            $usuarioAsignadoId = isset($cuerpo['usuario_asignado_id']) && (int) $cuerpo['usuario_asignado_id'] > 0
                ? (int) $cuerpo['usuario_asignado_id']
                : null;

            $origenComercialId = isset($cuerpo['origen_comercial_id']) && (int) $cuerpo['origen_comercial_id'] > 0
                ? (int) $cuerpo['origen_comercial_id']
                : null;

            $valorEstimado = isset($cuerpo['valor_estimado']) && is_numeric($cuerpo['valor_estimado'])
                ? (float) $cuerpo['valor_estimado']
                : null;

            $proximoSeguimientoEn = !empty($cuerpo['proximo_seguimiento_en'])
                ? (string) $cuerpo['proximo_seguimiento_en']
                : null;

            $notas = !empty($cuerpo['notas']) ? trim((string) $cuerpo['notas']) : null;

            $oportunidad = $this->oportunidadServicio->crear(
                (int) $contexto->organizacionId,
                $edicionId,
                $clienteId,
                $titulo,
                $usuarioAsignadoId,
                $origenComercialId,
                $valorEstimado,
                $proximoSeguimientoEn,
                $notas,
                $contexto
            );

            return $this->responderJson(true, 201, 'Oportunidad creada exitosamente.', [
                'oportunidad' => $oportunidad->aArreglo(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/crm/oportunidades/{id}
     * Modifica los datos de una oportunidad existente. Concurrencia optimista.
     */
    public function actualizar(string|int|array $id = 0): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.oportunidades.editar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso crm.oportunidades.editar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $this->verificarCsrf($contexto, $cuerpo);

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $titulo = trim((string) ($cuerpo['titulo'] ?? ''));
            if ($titulo === '') {
                return $this->responderJson(false, 400, 'El título de la oportunidad es obligatorio.');
            }

            $origenComercialId = isset($cuerpo['origen_comercial_id']) && (int) $cuerpo['origen_comercial_id'] > 0
                ? (int) $cuerpo['origen_comercial_id']
                : null;

            $valorEstimado = isset($cuerpo['valor_estimado']) && is_numeric($cuerpo['valor_estimado'])
                ? (float) $cuerpo['valor_estimado']
                : null;

            $proximoSeguimientoEn = !empty($cuerpo['proximo_seguimiento_en'])
                ? (string) $cuerpo['proximo_seguimiento_en']
                : null;

            $notas = !empty($cuerpo['notas']) ? trim((string) $cuerpo['notas']) : null;
            $versionBloqueo = isset($cuerpo['version_bloqueo']) ? (int) $cuerpo['version_bloqueo'] : null;

            $oportunidad = $this->oportunidadServicio->editar(
                (int) $contexto->organizacionId,
                $idInt,
                $titulo,
                $origenComercialId,
                $valorEstimado,
                $proximoSeguimientoEn,
                $notas,
                $versionBloqueo,
                $contexto
            );

            return $this->responderJson(true, 200, 'Oportunidad actualizada exitosamente.', [
                'oportunidad' => $oportunidad->aArreglo(),
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(false, 409, 'El registro ha sido modificado concurrentemente por otro usuario. Por favor recargue la oportunidad e intente de nuevo.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/crm/oportunidades/{id}/etapa
     * Transiciona de etapa en el pipeline comercial. Concurrencia optimista y validación de motivos.
     */
    public function cambiarEtapa(string|int|array $id = 0): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.oportunidades.cambiar_etapa', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso crm.oportunidades.cambiar_etapa.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $this->verificarCsrf($contexto, $cuerpo);

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $nuevaEtapa = (string) ($cuerpo['etapa'] ?? '');
            if ($nuevaEtapa === '') {
                return $this->responderJson(false, 400, 'Debe especificar la nueva etapa.');
            }

            $motivoPerdida = !empty($cuerpo['motivo_perdida']) ? (string) $cuerpo['motivo_perdida'] : null;
            $motivoPerdidaDetalle = !empty($cuerpo['motivo_perdida_detalle']) ? (string) $cuerpo['motivo_perdida_detalle'] : null;
            $motivoCambio = !empty($cuerpo['motivo_cambio']) ? (string) $cuerpo['motivo_cambio'] : null;
            $versionBloqueo = isset($cuerpo['version_bloqueo']) ? (int) $cuerpo['version_bloqueo'] : null;

            $oportunidad = $this->oportunidadServicio->cambiarEtapa(
                (int) $contexto->organizacionId,
                $idInt,
                $nuevaEtapa,
                $motivoPerdida,
                $motivoPerdidaDetalle,
                $motivoCambio,
                $versionBloqueo,
                $contexto
            );

            return $this->responderJson(true, 200, 'Etapa actualizada exitosamente.', [
                'oportunidad' => $oportunidad->aArreglo(),
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(false, 409, 'El registro ha sido modificado concurrentemente por otro usuario. Por favor recargue la oportunidad e intente de nuevo.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/crm/oportunidades/{id}/asignar
     * Asigna o reasigna el asesor responsable.
     */
    public function asignar(string|int|array $id = 0): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.oportunidades.asignar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso crm.oportunidades.asignar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $this->verificarCsrf($contexto, $cuerpo);

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $usuarioAsignadoId = isset($cuerpo['usuario_asignado_id']) && (int) $cuerpo['usuario_asignado_id'] > 0
                ? (int) $cuerpo['usuario_asignado_id']
                : null;
            $versionBloqueo = isset($cuerpo['version_bloqueo']) ? (int) $cuerpo['version_bloqueo'] : null;

            $oportunidad = $this->oportunidadServicio->asignarResponsable(
                (int) $contexto->organizacionId,
                $idInt,
                $usuarioAsignadoId,
                $versionBloqueo,
                $contexto
            );

            return $this->responderJson(true, 200, 'Responsable asignado exitosamente.', [
                'oportunidad' => $oportunidad->aArreglo(),
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(false, 409, 'El registro ha sido modificado concurrentemente por otro usuario. Por favor recargue la oportunidad e intente de nuevo.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * DELETE /api/v1/crm/oportunidades/{id}
     * Prohibición absoluta de borrado físico.
     */
    public function eliminar(string|int|array $id = 0): string
    {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode([
            'exito'   => false,
            'codigo'  => 405,
            'mensaje' => 'Método no permitido. La eliminación física de oportunidades está estrictamente prohibida por trazabilidad comercial. Para cerrar el negocio transicione a PERDIDA o GANADA.',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function verificarCsrf(ContextoOperacion $contexto, array $cuerpo = []): void
    {
        $tokenCookie = ManejadorCookie::extraerDePeticion();
        if ($tokenCookie !== null) {
            $tokenEsperado = $contexto->metadatos['csrf_token'] ?? null;
            $tokenRecibido = $cuerpo['_csrf_token'] ?? $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if ($tokenEsperado === null || $tokenRecibido === null || !ProtectorCsrf::validarToken($tokenEsperado, $tokenRecibido)) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'exito'   => false,
                    'codigo'  => 403,
                    'mensaje' => 'Token CSRF inválido o ausente. Por favor recargue la página.',
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
        }
    }

    private function obtenerCuerpo(): array
    {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $dec = json_decode($input, true);
            if (is_array($dec)) {
                return $dec;
            }
        }
        return $_POST;
    }

    private function responderJson(bool $exito, int $codigo, string $mensaje, array $datos = [], array $errores = []): string
    {
        http_response_code($codigo);
        $payload = [
            'exito'   => $exito,
            'codigo'  => $codigo,
            'mensaje' => $mensaje,
        ];

        if (!empty($datos)) {
            $payload['datos'] = $datos;
        }

        if (!empty($errores)) {
            $payload['errores'] = $errores;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function responderErrorSeguro(Throwable $e): string
    {
        http_response_code(500);
        return json_encode([
            'exito'   => false,
            'codigo'  => 500,
            'mensaje' => 'Ha ocurrido un error interno al procesar la operación.',
            'errores' => ['codigo_referencia' => 'ERR_' . substr(md5($e->getMessage() . time()), 0, 8)]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
