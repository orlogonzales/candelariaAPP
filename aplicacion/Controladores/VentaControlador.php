<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Cotizaciones\CotizacionServicio;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\CotizacionRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Ventas\EstadoVenta;
use Aplicacion\Ventas\MotivoAnulacionVenta;
use Aplicacion\Ventas\MotivoCancelacionVenta;
use Aplicacion\Ventas\TipoLineaVenta;
use Aplicacion\Ventas\TipoOrigenVenta;
use Aplicacion\Ventas\VentaServicio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Http\Vista;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Controlador oficial del Módulo de Ventas Comerciales (Fase 2.5C).
 * Consume el dominio soberano F2.5B sin duplicar reglas de negocio.
 * Implementa API RESTful, Concurrencia Optimista (HTTP 409), Integración Alina y Zero-PII.
 */
class VentaControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private VentaServicio $ventaServicio;
    private VentaRepositorio $ventaRepo;
    private CotizacionRepositorio $cotizacionRepo;
    private ClienteRepositorio $clienteRepo;
    private PersonaRepositorio $personaRepo;
    private EdicionRepositorio $edicionRepo;
    private AuditoriaRepositorio $auditoriaRepo;
    private ConfiguracionServicio $configServicio;
    private ContextoEdicionResolver $contextoEdicionResolver;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?VentaServicio $ventaServicio = null,
        ?EdicionRepositorio $edicionRepo = null,
        ?ConfiguracionServicio $configServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->edicionRepo = $edicionRepo ?? new EdicionRepositorio($this->pdo);
        $this->contextoEdicionResolver = new ContextoEdicionResolver($this->edicionRepo);
        $this->configServicio = $configServicio ?? new ConfiguracionServicio(pdo: $this->pdo);

        $this->ventaRepo = new VentaRepositorio($this->pdo);
        $this->cotizacionRepo = new CotizacionRepositorio($this->pdo);
        $this->clienteRepo = new ClienteRepositorio($this->pdo);
        $this->personaRepo = new PersonaRepositorio($this->pdo);
        $this->auditoriaRepo = new AuditoriaRepositorio($this->pdo);

        if ($ventaServicio !== null) {
            $this->ventaServicio = $ventaServicio;
        } else {
            $rolRepo = new RolRepositorio($this->pdo);
            $permRepo = new PermisoRepositorio($this->pdo);
            $usrRepo = new UsuarioRepositorio($this->pdo);
            $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $usrRepo, $this->auditoriaRepo, $this->pdo);

            $this->ventaServicio = new VentaServicio(
                ventaRepo: $this->ventaRepo,
                cotizacionRepo: $this->cotizacionRepo,
                clienteRepo: $this->clienteRepo,
                personaRepo: $this->personaRepo,
                edicionRepo: $this->edicionRepo,
                configServicio: $this->configServicio,
                authzServicio: $authzServicio,
                auditoriaRepo: $this->auditoriaRepo,
                oportunidadServicio: null,
                pdo: $this->pdo
            );
        }
    }

    // =========================================================================
    // 1. VISTA WEB OPERATIVA
    // =========================================================================

    /**
     * GET /ventas
     * Pantalla principal operativa de Ventas Comerciales.
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('ventas.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Ventas Comerciales',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (ventas.ver) para consultar el módulo de ventas.'
            ], 'principal');
        }

        $permisos = [
            'ver'                  => true,
            'crearDesdeCotizacion' => $this->authzMiddleware->verificarPermiso('ventas.crear_desde_cotizacion', $contexto, false),
            'cancelar'             => $this->authzMiddleware->verificarPermiso('ventas.cancelar', $contexto, false),
            'anular'               => $this->authzMiddleware->verificarPermiso('ventas.anular', $contexto, false),
        ];

        $monedaPrincipal = (string) $this->configServicio->obtenerPlataforma('plataforma.moneda_principal', 'PEN');

        return Vista::renderizar('ventas/index', [
            'titulo'          => 'Ventas Comerciales | CandelariaAPP',
            'subtitulo'       => 'Registro y Trazabilidad de Ventas Confirmadas',
            'tituloSeccion'   => 'Ventas Comerciales',
            'seccionActiva'   => 'ventas',
            'permisos'        => $permisos,
            'monedaPrincipal' => $monedaPrincipal,
            'scriptAdicional' => url_base('publico/js/ventas.js')
        ], 'principal');
    }

    // =========================================================================
    // 2. ENDPOINTS RESTful /api/v1/ventas
    // =========================================================================

    /**
     * GET /api/v1/ventas
     * Listado asíncrono para DataTables con filtros avanzados.
     */
    public function listar(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('ventas.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso ventas.ver.');
        }

        $edicionId = $this->resolverEdicionContextual($contexto);

        try {
            $orgId = (int) $contexto->organizacionId;

            $estado = !empty($_GET['estado']) ? trim((string) $_GET['estado']) : null;
            $clienteId = isset($_GET['cliente_id']) && is_numeric($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : null;
            $cotizacionId = isset($_GET['cotizacion_id']) && is_numeric($_GET['cotizacion_id']) ? (int) $_GET['cotizacion_id'] : null;
            $fechaDesde = !empty($_GET['fecha_desde']) ? trim((string) $_GET['fecha_desde']) : null;
            $fechaHasta = !empty($_GET['fecha_hasta']) ? trim((string) $_GET['fecha_hasta']) : null;
            $busqueda = !empty($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : null;

            $sql = "
                SELECT 
                    v.`id`,
                    v.`organizacion_id`,
                    v.`edicion_id`,
                    v.`cliente_id`,
                    v.`cotizacion_id`,
                    v.`origen_tipo`,
                    v.`correlativo`,
                    v.`fecha_venta`,
                    v.`estado`,
                    v.`cliente_nombre_completo`,
                    v.`cliente_numero_documento`,
                    v.`cliente_telefono`,
                    v.`moneda`,
                    v.`subtotal`,
                    v.`descuento_global_tipo`,
                    v.`descuento_global_valor`,
                    v.`descuento_global_monto`,
                    v.`descuento_lineas_total`,
                    v.`total`,
                    v.`motivo_cancelacion`,
                    v.`motivo_cancelacion_detalle`,
                    v.`motivo_anulacion`,
                    v.`motivo_anulacion_detalle`,
                    v.`version_bloqueo`,
                    v.`creado_en`,
                    v.`actualizado_en`,
                    cot.`correlativo` AS cotizacion_correlativo,
                    (SELECT COUNT(*) FROM `venta_lineas` vl WHERE vl.`venta_id` = v.`id`) AS total_lineas
                FROM `ventas` v
                LEFT JOIN `cotizaciones` cot ON cot.`id` = v.`cotizacion_id`
                WHERE v.`organizacion_id` = :org_id
            ";
            $params = ['org_id' => $orgId];

            if ($edicionId !== null) {
                $sql .= " AND v.`edicion_id` = :edicion_id";
                $params['edicion_id'] = $edicionId;
            }

            if ($estado !== null && $estado !== '') {
                $sql .= " AND v.`estado` = :estado";
                $params['estado'] = $estado;
            }

            if ($clienteId !== null) {
                $sql .= " AND v.`cliente_id` = :cliente_id";
                $params['cliente_id'] = $clienteId;
            }

            if ($cotizacionId !== null) {
                $sql .= " AND v.`cotizacion_id` = :cotizacion_id";
                $params['cotizacion_id'] = $cotizacionId;
            }

            if ($fechaDesde !== null) {
                $sql .= " AND v.`fecha_venta` >= :fecha_desde";
                $params['fecha_desde'] = $fechaDesde;
            }

            if ($fechaHasta !== null) {
                $sql .= " AND v.`fecha_venta` <= :fecha_hasta";
                $params['fecha_hasta'] = $fechaHasta;
            }

            if ($busqueda !== null && $busqueda !== '') {
                $sql .= " AND (
                    v.`correlativo` LIKE :b1 OR
                    v.`cliente_nombre_completo` LIKE :b2 OR
                    v.`cliente_numero_documento` LIKE :b3 OR
                    cot.`correlativo` LIKE :b4
                )";
                $termino = '%' . $busqueda . '%';
                $params['b1'] = $termino;
                $params['b2'] = $termino;
                $params['b3'] = $termino;
                $params['b4'] = $termino;
            }

            $sql .= " ORDER BY v.`id` DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear numéricos de forma estricta
            foreach ($ventas as &$v) {
                $v['subtotal'] = (float) $v['subtotal'];
                $v['total'] = (float) $v['total'];
                $v['descuento_global_monto'] = (float) $v['descuento_global_monto'];
                $v['descuento_lineas_total'] = (float) $v['descuento_lineas_total'];
                $v['total_lineas'] = (int) $v['total_lineas'];
                $v['version_bloqueo'] = (int) $v['version_bloqueo'];
            }
            unset($v);

            return $this->responderJson(true, 200, 'Listado de ventas consultado exitosamente.', [
                'ventas' => $ventas,
                'total'  => count($ventas),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/ventas/{id}
     * Detalle 360 integral de la venta con líneas y componentes.
     */
    public function detalle(string $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('ventas.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso ventas.ver.');
        }

        $idInt = (int) $id;
        if ($idInt <= 0) {
            return $this->responderJson(false, 400, 'Identificador de venta no válido.');
        }

        try {
            $venta = $this->ventaRepo->buscarPorId($idInt, (int) $contexto->organizacionId);
            if ($venta === null) {
                return $this->responderJson(false, 404, "La venta #{$idInt} no existe o no pertenece a su organización.");
            }

            // Datos adicionales informativos
            $cotizacionOrigen = null;
            if ($venta->cotizacionId !== null) {
                $cot = $this->cotizacionRepo->buscarPorId($venta->cotizacionId, (int) $contexto->organizacionId);
                if ($cot !== null) {
                    $cotizacionOrigen = [
                        'id'          => $cot->id,
                        'correlativo' => $cot->correlativo,
                        'titulo'      => $cot->titulo,
                        'fecha'       => $cot->fechaEmision,
                    ];
                }
            }

            return $this->responderJson(true, 200, 'Detalle de venta recuperado exitosamente.', [
                'venta'             => $venta->aArreglo(),
                'cotizacion_origen' => $cotizacionOrigen,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/ventas/desde-cotizacion
     * Convierte formalmente una cotización en estado ACEPTADA a una venta CONFIRMADA.
     */
    public function crearDesdeCotizacion(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        if (!$this->authzMiddleware->verificarPermiso('ventas.crear_desde_cotizacion', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso ventas.crear_desde_cotizacion.');
        }

        $cotizacionId = isset($cuerpo['cotizacion_id']) ? (int) $cuerpo['cotizacion_id'] : 0;
        if ($cotizacionId <= 0) {
            return $this->responderJson(false, 422, 'Debe especificar una cotización válida a convertir.', (object) [], ['cotizacion_id' => 'CAMPO_REQUERIDO']);
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $venta = $this->ventaServicio->crearDesdeCotizacion($orgId, $cotizacionId, $contexto);

            return $this->responderJson(true, 201, "Venta {$venta->correlativo} generada exitosamente a partir de la cotización.", [
                'venta' => $venta->aArreglo(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(
                false,
                409,
                'La cotización fue modificada por otro usuario concurrentemente.',
                (object) [],
                [
                    'codigo' => 'CONFLICTO_CONCURRENCIA',
                ]
            );
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            // Manejar errores de regla de negocio (ej. ya convertida, fail-closed)
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/ventas/{id}/cancelar
     * Cancela comercialmente una venta con motivo estructurado y optimistic locking.
     */
    public function cancelar(string $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        if (!$this->authzMiddleware->verificarPermiso('ventas.cancelar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso ventas.cancelar.');
        }

        $idInt = (int) $id;
        if ($idInt <= 0) {
            return $this->responderJson(false, 400, 'Identificador de venta no válido.');
        }

        $motivoStr = isset($cuerpo['motivo']) ? trim((string) $cuerpo['motivo']) : '';
        $motivo = MotivoCancelacionVenta::tryFrom($motivoStr);
        if ($motivo === null) {
            return $this->responderJson(false, 422, 'El motivo de cancelación especificado no es válido.', (object) [], ['motivo' => 'VALOR_INVALIDO']);
        }

        $motivoDetalle = isset($cuerpo['motivo_detalle']) ? trim((string) $cuerpo['motivo_detalle']) : null;
        $versionEsperada = isset($cuerpo['version_bloqueo']) ? (int) $cuerpo['version_bloqueo'] : null;

        try {
            $orgId = (int) $contexto->organizacionId;
            $cancelada = $this->ventaServicio->cancelar(
                organizacionId: $orgId,
                ventaId: $idInt,
                motivo: $motivo,
                motivoDetalle: $motivoDetalle,
                versionBloqueoEsperada: $versionEsperada,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, "La venta {$cancelada->correlativo} fue cancelada exitosamente.", [
                'venta' => $cancelada->aArreglo(),
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            $ventaActual = $this->ventaRepo->buscarPorId($idInt, (int) $contexto->organizacionId);
            return $this->responderJson(
                false,
                409,
                'La venta fue modificada concurrentemente por otro usuario. Por favor recargue los datos para continuar.',
                (object) [],
                [
                    'codigo'         => 'CONFLICTO_CONCURRENCIA',
                    'version_actual' => $ventaActual?->versionBloqueo ?? null,
                ]
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/ventas/{id}/anular
     * Anula administrativamente una venta con motivo estructurado y optimistic locking.
     */
    public function anular(string $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        if (!$this->authzMiddleware->verificarPermiso('ventas.anular', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso ventas.anular.');
        }

        $idInt = (int) $id;
        if ($idInt <= 0) {
            return $this->responderJson(false, 400, 'Identificador de venta no válido.');
        }

        $motivoStr = isset($cuerpo['motivo']) ? trim((string) $cuerpo['motivo']) : '';
        $motivo = MotivoAnulacionVenta::tryFrom($motivoStr);
        if ($motivo === null) {
            return $this->responderJson(false, 422, 'El motivo de anulación especificado no es válido.', (object) [], ['motivo' => 'VALOR_INVALIDO']);
        }

        $motivoDetalle = isset($cuerpo['motivo_detalle']) ? trim((string) $cuerpo['motivo_detalle']) : null;
        $versionEsperada = isset($cuerpo['version_bloqueo']) ? (int) $cuerpo['version_bloqueo'] : null;

        try {
            $orgId = (int) $contexto->organizacionId;
            $anulada = $this->ventaServicio->anular(
                organizacionId: $orgId,
                ventaId: $idInt,
                motivo: $motivo,
                motivoDetalle: $motivoDetalle,
                versionBloqueoEsperada: $versionEsperada,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, "La venta {$anulada->correlativo} fue anulada exitosamente.", [
                'venta' => $anulada->aArreglo(),
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            $ventaActual = $this->ventaRepo->buscarPorId($idInt, (int) $contexto->organizacionId);
            return $this->responderJson(
                false,
                409,
                'La venta fue modificada concurrentemente por otro usuario. Por favor recargue los datos para continuar.',
                (object) [],
                [
                    'codigo'         => 'CONFLICTO_CONCURRENCIA',
                    'version_actual' => $ventaActual?->versionBloqueo ?? null,
                ]
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/ventas/aux/cotizaciones-aceptadas
     * Endpoint auxiliar para Select2 en el modal de conversión de cotizaciones.
     * Solo retorna cotizaciones ACEPTADAS que aún no han sido convertidas a venta.
     */
    public function auxCotizacionesAceptadas(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('ventas.crear_desde_cotizacion', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso ventas.crear_desde_cotizacion.');
        }

        $edicionId = $this->resolverEdicionContextual($contexto);
        $q = !empty($_GET['q']) ? trim((string) $_GET['q']) : null;

        try {
            $orgId = (int) $contexto->organizacionId;

            $sql = "
                SELECT 
                    c.`id`,
                    c.`correlativo`,
                    c.`titulo`,
                    c.`total`,
                    c.`moneda`,
                    c.`fecha_emision`,
                    CONCAT(p.`nombres`, ' ', p.`apellidos`) AS cliente_nombre,
                    p.`numero_documento` AS cliente_documento
                FROM `cotizaciones` c
                INNER JOIN `clientes` cli ON cli.`id` = c.`cliente_id`
                INNER JOIN `personas` p ON p.`id` = cli.`persona_id`
                LEFT JOIN `ventas` v ON v.`cotizacion_id` = c.`id`
                WHERE c.`organizacion_id` = :org_id
                  AND c.`estado` = 'ACEPTADA'
                  AND v.`id` IS NULL
            ";
            $params = ['org_id' => $orgId];

            if ($edicionId !== null) {
                $sql .= " AND c.`edicion_id` = :edicion_id";
                $params['edicion_id'] = $edicionId;
            }

            if ($q !== null && $q !== '') {
                $sql .= " AND (
                    c.`correlativo` LIKE :q1 OR
                    c.`titulo` LIKE :q2 OR
                    p.`nombres` LIKE :q3 OR
                    p.`apellidos` LIKE :q4 OR
                    p.`numero_documento` LIKE :q5
                )";
                $termino = '%' . $q . '%';
                $params['q1'] = $termino;
                $params['q2'] = $termino;
                $params['q3'] = $termino;
                $params['q4'] = $termino;
                $params['q5'] = $termino;
            }

            $sql .= " ORDER BY c.`id` DESC LIMIT 30";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $resultados = [];
            foreach ($filas as $f) {
                $totalFormateado = number_format((float) $f['total'], 2);
                $resultados[] = [
                    'id'               => (int) $f['id'],
                    'text'             => "{$f['correlativo']} — {$f['cliente_nombre']} ({$f['moneda']} {$totalFormateado})",
                    'correlativo'      => $f['correlativo'],
                    'titulo'           => $f['titulo'],
                    'total'            => (float) $f['total'],
                    'moneda'           => $f['moneda'],
                    'cliente_nombre'   => $f['cliente_nombre'],
                    'cliente_documento'=> $f['cliente_documento'],
                ];
            }

            return $this->responderJson(true, 200, 'Cotizaciones aceptadas disponibles para venta.', [
                'items' => $resultados,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // UTILIDADES PRIVADAS DE CONTROLADOR
    // =========================================================================

    private function resolverEdicionContextual(ContextoOperacion $contexto): ?int
    {
        $candidato = null;
        if (isset($_GET['edicion_id'])) {
            $candidato = (string) $_GET['edicion_id'];
        } elseif (!empty($_SERVER['HTTP_X_EDICION_ID'])) {
            $candidato = (string) $_SERVER['HTTP_X_EDICION_ID'];
        }

        if ($candidato !== null) {
            $ctxResuelto = $this->contextoEdicionResolver->resolver($contexto, $candidato);
            return $ctxResuelto->edicionTrabajoId;
        }

        return null;
    }

    private function obtenerCuerpo(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return $_POST;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function verificarCsrf(ContextoOperacion $contexto, array $cuerpo): ?string
    {
        $tokenEsperado = $contexto->metadatos['csrf_token'] ?? null;
        if ($tokenEsperado !== null) {
            $tokenRecibido = $cuerpo['_csrf_token'] ?? $cuerpo['csrf_token'] ?? $_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if ($tokenRecibido === null || !ProtectorCsrf::validarToken($tokenEsperado, $tokenRecibido)) {
                return $this->responderJson(false, 403, 'Token de seguridad CSRF inválido o ausente.', (object) [], ['csrf' => 'TOKEN_INVALIDO']);
            }
        }
        return null;
    }

    private function responderJson(
        bool $exito,
        int $codigoHttp,
        string $mensaje,
        mixed $datos = [],
        mixed $errores = []
    ): string {
        if (!headers_sent()) {
            http_response_code($codigoHttp);
            header('Content-Type: application/json; charset=utf-8');
        }

        $payload = [
            'exito'   => $exito,
            'mensaje' => $mensaje,
            'datos'   => is_array($datos) && empty($datos) ? (object) [] : ($datos ?? (object) []),
            'errores' => is_array($errores) && empty($errores) ? (object) [] : ($errores ?? (object) []),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function responderErrorSeguro(Throwable $e): string
    {
        error_log("[VentaControlador Error] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        return $this->responderJson(false, 500, 'Ha ocurrido un error inesperado al procesar la solicitud en ventas.', (object) [], ['error' => 'ERROR_INTERNO']);
    }
}
