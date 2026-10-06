<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Cotizaciones\CotizacionServicio;
use Aplicacion\Cotizaciones\EstadoCotizacion;
use Aplicacion\Cotizaciones\MotivoAnulacionCotizacion;
use Aplicacion\Cotizaciones\MotivoRechazoCotizacion;
use Aplicacion\Cotizaciones\TipoDescuentoCotizacion;
use Aplicacion\Cotizaciones\TipoLineaCotizacion;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\CotizacionRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\OfertaItemEdicionRepositorio;
use Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
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
 * Controlador oficial del Módulo de Cotizaciones Comerciales (Fase 2.4C).
 * Consume el dominio soberano F2.4B sin duplicar reglas de negocio.
 * Implementa API RESTful, Concurrencia Optimista (HTTP 409), Integración Alina y Zero-PII.
 */
class CotizacionControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private CotizacionServicio $cotizacionServicio;
    private CotizacionRepositorio $cotizacionRepo;
    private ClienteRepositorio $clienteRepo;
    private EdicionRepositorio $edicionRepo;
    private OportunidadRepositorio $oportunidadRepo;
    private ItemComercialRepositorio $itemRepo;
    private PaqueteRepositorio $paqueteRepo;
    private OfertaItemEdicionRepositorio $ofertaItemRepo;
    private OfertaPaqueteEdicionRepositorio $ofertaPaqueteRepo;
    private TarifaItemEdicionRepositorio $tarifaItemRepo;
    private TarifaPaqueteEdicionRepositorio $tarifaPaqueteRepo;
    private AuditoriaRepositorio $auditoriaRepo;
    private ConfiguracionServicio $configServicio;
    private ContextoEdicionResolver $contextoEdicionResolver;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?CotizacionServicio $cotizacionServicio = null,
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

        $this->cotizacionRepo = new CotizacionRepositorio($this->pdo);
        $this->clienteRepo = new ClienteRepositorio($this->pdo);
        $this->oportunidadRepo = new OportunidadRepositorio($this->pdo);
        $this->itemRepo = new ItemComercialRepositorio($this->pdo);
        $this->paqueteRepo = new PaqueteRepositorio($this->pdo);
        $this->ofertaItemRepo = new OfertaItemEdicionRepositorio($this->pdo);
        $this->ofertaPaqueteRepo = new OfertaPaqueteEdicionRepositorio($this->pdo);
        $this->tarifaItemRepo = new TarifaItemEdicionRepositorio($this->pdo);
        $this->tarifaPaqueteRepo = new TarifaPaqueteEdicionRepositorio($this->pdo);
        $this->auditoriaRepo = new AuditoriaRepositorio($this->pdo);

        if ($cotizacionServicio !== null) {
            $this->cotizacionServicio = $cotizacionServicio;
        } else {
            $rolRepo = new RolRepositorio($this->pdo);
            $permRepo = new PermisoRepositorio($this->pdo);
            $usrRepo = new UsuarioRepositorio($this->pdo);
            $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $usrRepo, $this->auditoriaRepo, $this->pdo);

            $this->cotizacionServicio = new CotizacionServicio(
                cotizacionRepo: $this->cotizacionRepo,
                clienteRepo: $this->clienteRepo,
                edicionRepo: $this->edicionRepo,
                oportunidadRepo: $this->oportunidadRepo,
                itemRepo: $this->itemRepo,
                paqueteRepo: $this->paqueteRepo,
                ofertaItemRepo: $this->ofertaItemRepo,
                ofertaPaqueteRepo: $this->ofertaPaqueteRepo,
                tarifaItemRepo: $this->tarifaItemRepo,
                tarifaPaqueteRepo: $this->tarifaPaqueteRepo,
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
     * GET /cotizaciones
     * Pantalla principal operativa de Cotizaciones.
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Cotizaciones Comerciales',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (cotizaciones.ver) para consultar el módulo de cotizaciones.'
            ], 'principal');
        }

        $permisos = [
            'ver'              => true,
            'crear'            => $this->authzMiddleware->verificarPermiso('cotizaciones.crear', $contexto, false),
            'editar'           => $this->authzMiddleware->verificarPermiso('cotizaciones.editar', $contexto, false),
            'emitir'           => $this->authzMiddleware->verificarPermiso('cotizaciones.emitir', $contexto, false),
            'crearRevision'    => $this->authzMiddleware->verificarPermiso('cotizaciones.crear_revision', $contexto, false),
            'aceptar'          => $this->authzMiddleware->verificarPermiso('cotizaciones.aceptar', $contexto, false),
            'rechazar'         => $this->authzMiddleware->verificarPermiso('cotizaciones.rechazar', $contexto, false),
            'anular'           => $this->authzMiddleware->verificarPermiso('cotizaciones.anular', $contexto, false),
            'aplicarDescuento' => $this->authzMiddleware->verificarPermiso('cotizaciones.aplicar_descuento', $contexto, false),
        ];

        $monedaPrincipal = (string) $this->configServicio->obtenerPlataforma('plataforma.moneda_principal', 'PEN');

        return Vista::renderizar('cotizaciones/index', [
            'titulo'          => 'Cotizaciones | CandelariaAPP',
            'subtitulo'       => 'Propuestas Económicas y Presupuestos por Edición',
            'tituloSeccion'   => 'Cotizaciones Comerciales',
            'seccionActiva'   => 'cotizaciones',
            'permisos'        => $permisos,
            'monedaPrincipal' => $monedaPrincipal,
            'scriptAdicional' => url_base('publico/js/cotizaciones.js')
        ], 'principal');
    }

    // =========================================================================
    // 2. ENDPOINTS RESTful /api/v1/cotizaciones
    // =========================================================================

    /**
     * GET /api/v1/cotizaciones
     * Listado asíncrono para DataTables con filtros avanzados.
     */
    public function listar(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.ver.');
        }

        $edicionId = $this->resolverEdicionContextual($contexto);

        try {
            $orgId = (int) $contexto->organizacionId;

            $estado = !empty($_GET['estado']) ? trim((string) $_GET['estado']) : null;
            $clienteId = isset($_GET['cliente_id']) && is_numeric($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : null;
            $oportunidadId = isset($_GET['oportunidad_id']) && is_numeric($_GET['oportunidad_id']) ? (int) $_GET['oportunidad_id'] : null;
            $fechaDesde = !empty($_GET['fecha_desde']) ? trim((string) $_GET['fecha_desde']) : null;
            $fechaHasta = !empty($_GET['fecha_hasta']) ? trim((string) $_GET['fecha_hasta']) : null;
            $vigencia = !empty($_GET['vigencia']) ? trim((string) $_GET['vigencia']) : null; // VIGENTE | VENCIDA
            $busqueda = !empty($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : null;

            // Construir consulta con joins informativos mínimos (Zero PII)
            $sql = "
                SELECT 
                    c.`id`,
                    c.`organizacion_id`,
                    c.`edicion_id`,
                    c.`cliente_id`,
                    c.`oportunidad_id`,
                    c.`correlativo`,
                    c.`correlativo_base`,
                    c.`version_numero`,
                    c.`cotizacion_origen_id`,
                    c.`cotizacion_raiz_id`,
                    c.`titulo`,
                    c.`estado`,
                    c.`fecha_emision`,
                    c.`valido_hasta`,
                    c.`moneda`,
                    c.`subtotal`,
                    c.`descuento_global_tipo`,
                    c.`descuento_global_valor`,
                    c.`descuento_global_monto`,
                    c.`descuento_global_motivo`,
                    c.`descuento_lineas_total`,
                    c.`total`,
                    c.`version_bloqueo`,
                    c.`creado_en`,
                    c.`actualizado_en`,
                    CONCAT(p.`nombres`, ' ', p.`apellidos`) AS cliente_nombre,
                    op.`titulo` AS oportunidad_titulo,
                    CONCAT('#', op.`id`) AS oportunidad_codigo,
                    (SELECT COUNT(*) FROM `cotizacion_lineas` cl WHERE cl.`cotizacion_id` = c.`id`) AS total_lineas
                FROM `cotizaciones` c
                INNER JOIN `clientes` cli ON cli.`id` = c.`cliente_id`
                INNER JOIN `personas` p ON p.`id` = cli.`persona_id`
                LEFT JOIN `crm_oportunidades` op ON op.`id` = c.`oportunidad_id`
                WHERE c.`organizacion_id` = :org_id
            ";
            $params = ['org_id' => $orgId];

            if ($edicionId !== null) {
                $sql .= " AND c.`edicion_id` = :edicion_id";
                $params['edicion_id'] = $edicionId;
            }

            if ($estado !== null && $estado !== '') {
                $sql .= " AND c.`estado` = :estado";
                $params['estado'] = $estado;
            }

            if ($clienteId !== null) {
                $sql .= " AND c.`cliente_id` = :cliente_id";
                $params['cliente_id'] = $clienteId;
            }

            if ($oportunidadId !== null) {
                $sql .= " AND c.`oportunidad_id` = :oportunidad_id";
                $params['oportunidad_id'] = $oportunidadId;
            }

            if ($fechaDesde !== null && $fechaDesde !== '') {
                $sql .= " AND c.`fecha_emision` >= :fecha_desde";
                $params['fecha_desde'] = $fechaDesde;
            }

            if ($fechaHasta !== null && $fechaHasta !== '') {
                $sql .= " AND c.`fecha_emision` <= :fecha_hasta";
                $params['fecha_hasta'] = $fechaHasta;
            }

            if ($busqueda !== null && $busqueda !== '') {
                $sql .= " AND (c.`correlativo` LIKE :busqueda OR c.`titulo` LIKE :busqueda OR CONCAT(p.`nombres`, ' ', p.`apellidos`) LIKE :busqueda)";
                $params['busqueda'] = '%' . $busqueda . '%';
            }

            $sql .= " ORDER BY c.`id` DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $hoy = date('Y-m-d');
            $cotizaciones = [];

            foreach ($filas as $f) {
                // Cálculo dinámico de vigencia en lectura (Zero side-effects)
                $estaVencidaEfectiva = false;
                if ($f['estado'] === 'EMITIDA' && !empty($f['valido_hasta']) && $f['valido_hasta'] < $hoy) {
                    $estaVencidaEfectiva = true;
                }

                // Si se solicitó filtro de vigencia
                if ($vigencia === 'VENCIDA' && !$estaVencidaEfectiva && $f['estado'] !== 'VENCIDA') {
                    continue;
                }
                if ($vigencia === 'VIGENTE' && ($estaVencidaEfectiva || in_array($f['estado'], ['VENCIDA', 'ANULADA', 'RECHAZADA'], true))) {
                    continue;
                }

                $cotizaciones[] = [
                    'id'                     => (int) $f['id'],
                    'correlativo'            => $f['correlativo'],
                    'correlativo_base'       => $f['correlativo_base'],
                    'version_numero'         => (int) $f['version_numero'],
                    'titulo'                 => $f['titulo'],
                    'estado'                 => $f['estado'],
                    'fecha_emision'          => $f['fecha_emision'],
                    'valido_hasta'           => $f['valido_hasta'],
                    'esta_vencida_efectiva'  => $estaVencidaEfectiva,
                    'moneda'                 => $f['moneda'],
                    'subtotal'               => (float) $f['subtotal'],
                    'descuento_global_monto' => (float) $f['descuento_global_monto'],
                    'descuento_lineas_total' => (float) $f['descuento_lineas_total'],
                    'total'                  => (float) $f['total'],
                    'version_bloqueo'        => (int) $f['version_bloqueo'],
                    'cliente_id'             => (int) $f['cliente_id'],
                    'cliente_nombre'         => $f['cliente_nombre'] ?: 'Cliente #' . $f['cliente_id'],
                    'oportunidad_id'         => $f['oportunidad_id'] ? (int) $f['oportunidad_id'] : null,
                    'oportunidad_titulo'     => $f['oportunidad_titulo'],
                    'oportunidad_codigo'     => $f['oportunidad_codigo'],
                    'total_lineas'           => (int) $f['total_lineas'],
                    'creado_en'              => $f['creado_en'],
                ];
            }

            return $this->responderJson(true, 200, 'Cotizaciones consultadas exitosamente.', [
                'total'        => count($cotizaciones),
                'cotizaciones' => $cotizaciones,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/cotizaciones/{id}
     * Detalle 360 de cotización: cabecera, líneas, componentes de paquetes, historial de revisiones y auditoría.
     */
    public function detalle(string|int|array $id = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.ver.');
        }

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $cotizacion = $this->cotizacionServicio->obtenerCotizacion((int) $contexto->organizacionId, $idInt, $contexto);

            if ($cotizacion === null) {
                return $this->responderJson(false, 404, "Cotización #{$idInt} no encontrada en su organización.");
            }

            // Datos de cliente
            $stmtCli = $this->pdo->prepare("
                SELECT c.`id`, CONCAT(p.`nombres`, ' ', p.`apellidos`) AS nombre_completo, p.`numero_documento`
                FROM `clientes` c
                INNER JOIN `personas` p ON p.`id` = c.`persona_id`
                WHERE c.`id` = :id
            ");
            $stmtCli->execute(['id' => $cotizacion->clienteId]);
            $clienteData = $stmtCli->fetch(PDO::FETCH_ASSOC);

            // Datos de oportunidad (si existe)
            $oportunidadData = null;
            if ($cotizacion->oportunidadId !== null) {
                $stmtOp = $this->pdo->prepare("
                    SELECT `id`, `titulo`, `etapa`, `valor_estimado`
                    FROM `crm_oportunidades`
                    WHERE `id` = :id
                ");
                $stmtOp->execute(['id' => $cotizacion->oportunidadId]);
                $oportunidadData = $stmtOp->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            // Líneas de cotización con componentes de paquete
            $lineasFormateadas = array_map(fn($l) => $l->toArray(), $cotizacion->lineas);

            // Cadena de revisiones enlazadas
            $revisiones = [];
            $raizId = $cotizacion->cotizacionRaizId ?? $cotizacion->id;
            $stmtRev = $this->pdo->prepare("
                SELECT `id`, `correlativo`, `version_numero`, `estado`, `fecha_emision`, `total`, `creado_en`
                FROM `cotizaciones`
                WHERE (`cotizacion_raiz_id` = :raiz_id1 OR `id` = :raiz_id2)
                  AND `organizacion_id` = :org_id
                ORDER BY `version_numero` ASC
            ");
            $stmtRev->execute([
                'raiz_id1' => $raizId,
                'raiz_id2' => $raizId,
                'org_id'   => $contexto->organizacionId,
            ]);
            $revisiones = $stmtRev->fetchAll(PDO::FETCH_ASSOC);

            // Auditoría comercial visible
            $stmtAud = $this->pdo->prepare("
                SELECT `accion`, `entidad_id`, `creado_en`,
                       CONCAT('Usuario #', `usuario_id`) AS actor_display
                FROM `auditoria_operaciones`
                WHERE `modulo` = 'cotizaciones'
                  AND `organizacion_id` = :org_id
                  AND `entidad_id` = :ent_id
                ORDER BY `id` DESC
                LIMIT 30
            ");
            $stmtAud->execute([
                'org_id' => $contexto->organizacionId,
                'ent_id' => (string) $cotizacion->id,
            ]);
            $auditoria = $stmtAud->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Detalle 360 de la cotización.', [
                'cotizacion'    => $cotizacion->toArray(),
                'lineas'        => $lineasFormateadas,
                'cliente'       => $clienteData ?: ['id' => $cotizacion->clienteId, 'nombre_completo' => 'Cliente #' . $cotizacion->clienteId],
                'oportunidad'   => $oportunidadData,
                'revisiones'    => $revisiones,
                'auditoria'     => $auditoria,
                'esta_vencida'  => $cotizacion->estaVencidaEfectiva(),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/cotizaciones
     * Crea un nuevo borrador de cotización.
     */
    public function crearBorrador(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.crear', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.crear.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $edicionId = isset($cuerpo['edicion_id']) && is_numeric($cuerpo['edicion_id'])
                ? (int) $cuerpo['edicion_id']
                : $this->resolverEdicionContextual($contexto);

            if ($edicionId === null || $edicionId <= 0) {
                return $this->responderJson(false, 422, 'Debe especificar una edición de trabajo válida (X-Edicion-Id o edicion_id).', (object) [], ['edicion_id' => 'CAMPO_REQUERIDO']);
            }

            $clienteId = isset($cuerpo['cliente_id']) && is_numeric($cuerpo['cliente_id']) ? (int) $cuerpo['cliente_id'] : 0;
            if ($clienteId <= 0) {
                return $this->responderJson(false, 422, 'El cliente es obligatorio para crear la cotización.', (object) [], ['cliente_id' => 'CAMPO_REQUERIDO']);
            }

            $titulo = isset($cuerpo['titulo']) ? trim((string) $cuerpo['titulo']) : 'Propuesta Comercial';
            if ($titulo === '') {
                $titulo = 'Propuesta Comercial';
            }

            $oportunidadId = isset($cuerpo['oportunidad_id']) && is_numeric($cuerpo['oportunidad_id']) ? (int) $cuerpo['oportunidad_id'] : null;
            $terminos = isset($cuerpo['terminos_condiciones']) ? trim((string) $cuerpo['terminos_condiciones']) : null;
            $notas = isset($cuerpo['notas_internas']) ? trim((string) $cuerpo['notas_internas']) : null;

            $borrador = $this->cotizacionServicio->crearBorrador(
                organizacionId: $orgId,
                edicionId: $edicionId,
                clienteId: $clienteId,
                titulo: $titulo,
                oportunidadId: $oportunidadId,
                terminosCondiciones: $terminos,
                notasInternas: $notas,
                contexto: $contexto
            );

            return $this->responderJson(true, 201, 'Borrador de cotización creado exitosamente.', [
                'cotizacion' => $borrador->aArreglo(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/cotizaciones/{id}
     * Actualiza metadatos de una cotización en borrador.
     */
    public function actualizarBorrador(string|int|array $id = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.editar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.editar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $titulo = isset($cuerpo['titulo']) ? trim((string) $cuerpo['titulo']) : null;
            $terminos = isset($cuerpo['terminos_condiciones']) ? (string) $cuerpo['terminos_condiciones'] : null;
            $notas = isset($cuerpo['notas_internas']) ? (string) $cuerpo['notas_internas'] : null;
            $versionBloqueo = isset($cuerpo['version_bloqueo']) && is_numeric($cuerpo['version_bloqueo'])
                ? (int) $cuerpo['version_bloqueo']
                : null;

            $actualizada = $this->cotizacionServicio->actualizarBorrador(
                organizacionId: (int) $contexto->organizacionId,
                cotizacionId: $idInt,
                titulo: $titulo,
                terminosCondiciones: $terminos,
                notasInternas: $notas,
                versionBloqueoEsperada: $versionBloqueo,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, 'Cotización actualizada exitosamente.', [
                'cotizacion' => $actualizada->aArreglo(),
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            $cotActual = $this->cotizacionRepo->buscarPorId($idInt, (int) $contexto->organizacionId);
            return $this->responderJson(
                false,
                409,
                'La cotización fue modificada por otro usuario concurrentemente. Por favor recargue los datos para continuar.',
                (object) [],
                [
                    'codigo'         => 'CONFLICTO_CONCURRENCIA',
                    'version_actual' => $cotActual?->versionBloqueo ?? null,
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
     * POST /api/v1/cotizaciones/{id}/lineas
     * Agrega una línea (ITEM o PAQUETE) a una cotización en borrador.
     */
    public function agregarLinea(string|int|array $id = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.editar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.editar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $tipoLinea = strtoupper(trim((string) ($cuerpo['tipo_linea'] ?? 'ITEM')));
            $cantidad = isset($cuerpo['cantidad']) ? (float) $cuerpo['cantidad'] : 1.0;
            $notas = isset($cuerpo['notas']) ? trim((string) $cuerpo['notas']) : null;

            // Descuento opcional
            $descuentoTipoStr = strtoupper(trim((string) ($cuerpo['descuento_tipo'] ?? 'NINGUNO')));
            $descuentoTipo = TipoDescuentoCotizacion::tryFrom($descuentoTipoStr) ?? TipoDescuentoCotizacion::NINGUNO;
            $descuentoValor = isset($cuerpo['descuento_valor']) ? (float) $cuerpo['descuento_valor'] : 0.00;
            $descuentoMotivo = isset($cuerpo['descuento_motivo']) ? trim((string) $cuerpo['descuento_motivo']) : null;

            if ($descuentoTipo !== TipoDescuentoCotizacion::NINGUNO || $descuentoValor > 0) {
                if (!$this->authzMiddleware->verificarPermiso('cotizaciones.aplicar_descuento', $contexto, false)) {
                    return $this->responderJson(false, 403, "No cuenta con el permiso 'cotizaciones.aplicar_descuento' para incluir descuentos.");
                }
            }

            if ($tipoLinea === 'ITEM') {
                $itemId = isset($cuerpo['item_comercial_id']) ? (int) $cuerpo['item_comercial_id'] : 0;
                $ofertaItemId = isset($cuerpo['oferta_item_id']) && is_numeric($cuerpo['oferta_item_id']) ? (int) $cuerpo['oferta_item_id'] : null;

                $actualizada = $this->cotizacionServicio->agregarLineaItem(
                    organizacionId: (int) $contexto->organizacionId,
                    cotizacionId: $idInt,
                    itemComercialId: $itemId,
                    cantidad: $cantidad,
                    precioUnitario: null, // Backend resuelve desde tarifa vigente
                    descuentoTipo: $descuentoTipo,
                    descuentoValor: $descuentoValor,
                    descuentoMotivo: $descuentoMotivo,
                    notas: $notas,
                    ofertaItemId: $ofertaItemId,
                    contexto: $contexto
                );
            } elseif ($tipoLinea === 'PAQUETE') {
                $paqueteId = isset($cuerpo['paquete_id']) ? (int) $cuerpo['paquete_id'] : 0;
                $ofertaPaqueteId = isset($cuerpo['oferta_paquete_id']) && is_numeric($cuerpo['oferta_paquete_id']) ? (int) $cuerpo['oferta_paquete_id'] : null;

                $actualizada = $this->cotizacionServicio->agregarLineaPaquete(
                    organizacionId: (int) $contexto->organizacionId,
                    cotizacionId: $idInt,
                    paqueteId: $paqueteId,
                    cantidad: $cantidad,
                    precioUnitario: null, // Backend resuelve desde tarifa vigente
                    descuentoTipo: $descuentoTipo,
                    descuentoValor: $descuentoValor,
                    descuentoMotivo: $descuentoMotivo,
                    notas: $notas,
                    ofertaPaqueteId: $ofertaPaqueteId,
                    contexto: $contexto
                );
            } else {
                return $this->responderJson(false, 422, 'Tipo de línea no válido. Debe ser ITEM o PAQUETE.');
            }

            return $this->responderJson(true, 201, 'Línea agregada exitosamente a la cotización.', [
                'cotizacion' => $actualizada->aArreglo(),
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            $cotActual = $this->cotizacionRepo->buscarPorId($idInt, (int) $contexto->organizacionId);
            return $this->responderJson(
                false,
                409,
                'La cotización fue modificada concurrentemente.',
                (object) [],
                [
                    'codigo'         => 'CONFLICTO_CONCURRENCIA',
                    'version_actual' => $cotActual?->versionBloqueo ?? null,
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
     * PATCH /api/v1/cotizaciones/{id}/lineas/{lineaId}/descuento
     * Aplica o actualiza el descuento a una línea existente.
     */
    public function aplicarDescuentoLinea(string|int|array $id = 0, string|int|array $lineaId = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.editar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.editar.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.aplicar_descuento', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.aplicar_descuento.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $lineaIdInt = is_array($lineaId) ? (int) ($lineaId['lineaId'] ?? 0) : (int) $lineaId;

        try {
            $tipoStr = strtoupper(trim((string) ($cuerpo['tipo'] ?? 'NINGUNO')));
            $tipo = TipoDescuentoCotizacion::tryFrom($tipoStr) ?? TipoDescuentoCotizacion::NINGUNO;
            $valor = isset($cuerpo['valor']) ? (float) $cuerpo['valor'] : 0.00;
            $motivo = isset($cuerpo['motivo']) ? trim((string) $cuerpo['motivo']) : '';

            $actualizada = $this->cotizacionServicio->aplicarDescuentoLinea(
                organizacionId: (int) $contexto->organizacionId,
                cotizacionId: $idInt,
                lineaId: $lineaIdInt,
                tipo: $tipo,
                valor: $valor,
                motivo: $motivo,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, 'Descuento de línea aplicado exitosamente.', [
                'cotizacion' => $actualizada->aArreglo(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * DELETE /api/v1/cotizaciones/{id}/lineas/{lineaId}
     * Elimina una línea de una cotización en borrador.
     */
    public function eliminarLinea(string|int|array $id = 0, string|int|array $lineaId = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.editar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.editar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $lineaIdInt = is_array($lineaId) ? (int) ($lineaId['lineaId'] ?? 0) : (int) $lineaId;

        try {
            $actualizada = $this->cotizacionServicio->eliminarLinea(
                organizacionId: (int) $contexto->organizacionId,
                cotizacionId: $idInt,
                lineaId: $lineaIdInt,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, 'Línea eliminada exitosamente.', [
                'cotizacion' => $actualizada->aArreglo(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/cotizaciones/{id}/descuento-global
     * Aplica descuento global a la cotización completa.
     */
    public function aplicarDescuentoGlobal(string|int|array $id = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.editar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.editar.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.aplicar_descuento', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.aplicar_descuento.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $tipoStr = strtoupper(trim((string) ($cuerpo['tipo'] ?? 'NINGUNO')));
            $tipo = TipoDescuentoCotizacion::tryFrom($tipoStr) ?? TipoDescuentoCotizacion::NINGUNO;
            $valor = isset($cuerpo['valor']) ? (float) $cuerpo['valor'] : 0.00;
            $motivo = isset($cuerpo['motivo']) ? trim((string) $cuerpo['motivo']) : null;
            $versionBloqueo = isset($cuerpo['version_bloqueo']) && is_numeric($cuerpo['version_bloqueo'])
                ? (int) $cuerpo['version_bloqueo']
                : null;

            if ($versionBloqueo !== null) {
                $cotActual = $this->cotizacionRepo->buscarPorId($idInt, (int) $contexto->organizacionId);
                if ($cotActual !== null && $cotActual->versionBloqueo !== $versionBloqueo) {
                    throw new ConflictoConcurrenciaExcepcion("La cotización fue modificada concurrentemente.", $cotActual->versionBloqueo);
                }
            }

            $actualizada = $this->cotizacionServicio->aplicarDescuentoGlobal(
                organizacionId: (int) $contexto->organizacionId,
                cotizacionId: $idInt,
                tipo: $tipo,
                valor: $valor,
                motivo: (string) $motivo,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, 'Descuento global aplicado exitosamente.', [
                'cotizacion' => $actualizada->toArray(),
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            $cotActual = $this->cotizacionRepo->buscarPorId($idInt, (int) $contexto->organizacionId);
            return $this->responderJson(
                false,
                409,
                'La cotización fue modificada concurrentemente.',
                (object) [],
                [
                    'codigo'         => 'CONFLICTO_CONCURRENCIA',
                    'version_actual' => $cotActual?->versionBloqueo ?? null,
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
     * POST /api/v1/cotizaciones/{id}/emitir
     * Emite formalmente la cotización y congela su contenido comercial.
     */
    public function emitir(string|int|array $id = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.emitir', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.emitir.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $validoHasta = !empty($cuerpo['valido_hasta']) ? trim((string) $cuerpo['valido_hasta']) : null;
            $diasVigencia = isset($cuerpo['dias_vigencia']) && is_numeric($cuerpo['dias_vigencia']) ? (int) $cuerpo['dias_vigencia'] : null;
            if ($validoHasta === null && $diasVigencia !== null && $diasVigencia > 0) {
                $validoHasta = date('Y-m-d', strtotime("+{$diasVigencia} days"));
            }

            $versionBloqueo = isset($cuerpo['version_bloqueo']) && is_numeric($cuerpo['version_bloqueo'])
                ? (int) $cuerpo['version_bloqueo']
                : null;

            if ($versionBloqueo !== null) {
                $cotActual = $this->cotizacionRepo->buscarPorId($idInt, (int) $contexto->organizacionId);
                if ($cotActual !== null && $cotActual->versionBloqueo !== $versionBloqueo) {
                    throw new ConflictoConcurrenciaExcepcion("La cotización fue modificada concurrentemente.", $cotActual->versionBloqueo);
                }
            }

            $emitida = $this->cotizacionServicio->emitir(
                organizacionId: (int) $contexto->organizacionId,
                cotizacionId: $idInt,
                validoHastaManual: $validoHasta,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, "Cotización formalmente emitida con correlativo '{$emitida->correlativo}'.", [
                'cotizacion' => $emitida->toArray(),
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            $cotActual = $this->cotizacionRepo->buscarPorId($idInt, (int) $contexto->organizacionId);
            return $this->responderJson(
                false,
                409,
                'La cotización fue modificada concurrentemente.',
                (object) [],
                [
                    'codigo'         => 'CONFLICTO_CONCURRENCIA',
                    'version_actual' => $cotActual?->versionBloqueo ?? null,
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
     * POST /api/v1/cotizaciones/{id}/revision
     * Genera una nueva revisión (ej. R2) en borrador clonando las líneas de la emitida.
     */
    public function crearRevision(string|int|array $id = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.crear_revision', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.crear_revision.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $revision = $this->cotizacionServicio->crearRevision(
                organizacionId: (int) $contexto->organizacionId,
                cotizacionId: $idInt,
                contexto: $contexto
            );

            return $this->responderJson(true, 201, "Nueva revisión R{$revision->versionNumero} creada exitosamente en borrador.", [
                'cotizacion' => $revision->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/cotizaciones/{id}/aceptar
     * Marca la cotización como ACEPTADA.
     */
    public function aceptar(string|int|array $id = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.aceptar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.aceptar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $aceptada = $this->cotizacionServicio->aceptar(
                organizacionId: (int) $contexto->organizacionId,
                cotizacionId: $idInt,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, 'Cotización aceptada por el cliente.', [
                'cotizacion' => $aceptada->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/cotizaciones/{id}/rechazar
     * Registra el rechazo de la propuesta con motivo estructurado.
     */
    public function rechazar(string|int|array $id = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.rechazar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.rechazar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $motivoStr = strtoupper(trim((string) ($cuerpo['motivo'] ?? '')));
            $motivo = MotivoRechazoCotizacion::tryFrom($motivoStr);
            if ($motivo === null) {
                return $this->responderJson(false, 422, 'Motivo de rechazo no válido.');
            }

            $detalle = isset($cuerpo['detalle']) ? trim((string) $cuerpo['detalle']) : null;

            $rechazada = $this->cotizacionServicio->rechazar(
                organizacionId: (int) $contexto->organizacionId,
                cotizacionId: $idInt,
                motivo: $motivo,
                motivoDetalle: $detalle,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, 'Cotización registrada como rechazada.', [
                'cotizacion' => $rechazada->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/cotizaciones/{id}/anular
     * Registra la anulación formal con motivo administrativo controlado.
     */
    public function anular(string|int|array $id = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.anular', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.anular.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $motivoStr = strtoupper(trim((string) ($cuerpo['motivo'] ?? '')));
            // Prohibir SUPERADA_POR_REVISION desde UI/API manual
            if ($motivoStr === 'SUPERADA_POR_REVISION') {
                return $this->responderJson(false, 422, "El motivo 'SUPERADA_POR_REVISION' es de uso interno exclusivo del ciclo de revisiones.");
            }

            $motivo = MotivoAnulacionCotizacion::tryFrom($motivoStr);
            if ($motivo === null) {
                return $this->responderJson(false, 422, 'Motivo de anulación no válido.');
            }

            $detalle = isset($cuerpo['detalle']) ? trim((string) $cuerpo['detalle']) : null;

            $anulada = $this->cotizacionServicio->anular(
                organizacionId: (int) $contexto->organizacionId,
                cotizacionId: $idInt,
                motivo: $motivo,
                motivoDetalle: $detalle,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, 'Cotización formalmente anulada.', [
                'cotizacion' => $anulada->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 3. ENDPOINTS AUXILIARES PARA SELECT2 Y LOOKUP COMERCIAL
    // =========================================================================

    /**
     * GET /api/v1/cotizaciones/aux/clientes
     * Búsqueda remota de clientes para Select2 (estricto tenant, Zero PII).
     */
    public function auxClientes(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.ver.');
        }

        try {
            $q = trim((string) ($_GET['q'] ?? $_GET['busqueda'] ?? ''));
            $sql = "
                SELECT c.`id`, CONCAT(p.`nombres`, ' ', p.`apellidos`) AS nombre_completo,
                       c.`estado_comercial`
                FROM `clientes` c
                INNER JOIN `personas` p ON p.`id` = c.`persona_id`
                WHERE c.`organizacion_id` = :org_id
                  AND c.`estado_comercial` != 'INACTIVO'
            ";
            $params = ['org_id' => $contexto->organizacionId];

            if ($q !== '') {
                $sql .= " AND (CONCAT(p.`nombres`, ' ', p.`apellidos`) LIKE :q)";
                $params['q'] = '%' . $q . '%';
            }

            $sql .= " ORDER BY p.`apellidos` ASC, p.`nombres` ASC LIMIT 30";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $resultados = array_map(function ($f) {
                return [
                    'id'   => (int) $f['id'],
                    'text' => trim($f['nombre_completo']) ?: 'Cliente #' . $f['id'],
                ];
            }, $filas);

            return $this->responderJson(true, 200, 'Clientes consultados para selector.', [
                'resultados' => $resultados,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/cotizaciones/aux/oportunidades
     * Búsqueda remota de oportunidades compatibles (mismo cliente, tenant y edición).
     */
    public function auxOportunidades(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.ver.');
        }

        try {
            $clienteId = isset($_GET['cliente_id']) && is_numeric($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : null;
            $edicionId = $this->resolverEdicionContextual($contexto);

            $sql = "
                SELECT `id`, `titulo`, `etapa`, `valor_estimado`
                FROM `crm_oportunidades`
                WHERE `organizacion_id` = :org_id
                  AND `etapa` NOT IN ('GANADA', 'PERDIDA', 'CANCELADA')
            ";
            $params = ['org_id' => $contexto->organizacionId];

            if ($clienteId !== null) {
                $sql .= " AND `cliente_id` = :cliente_id";
                $params['cliente_id'] = $clienteId;
            }

            if ($edicionId !== null) {
                $sql .= " AND `edicion_id` = :edicion_id";
                $params['edicion_id'] = $edicionId;
            }

            $sql .= " ORDER BY `id` DESC LIMIT 30";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $resultados = array_map(function ($f) {
                return [
                    'id'    => (int) $f['id'],
                    'text'  => "#{$f['id']} - {$f['titulo']} ({$f['etapa']})",
                    'etapa' => $f['etapa'],
                ];
            }, $filas);

            return $this->responderJson(true, 200, 'Oportunidades consultadas para selector.', [
                'resultados' => $resultados,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/cotizaciones/aux/ofertas
     * Lista las ofertas comerciales activas de la edición de trabajo (Ítems y Paquetes con tarifa vigente).
     */
    public function auxOfertas(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cotizaciones.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso cotizaciones.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $edicionId = $this->resolverEdicionContextual($contexto);

            if ($edicionId === null || $edicionId <= 0) {
                return $this->responderJson(false, 422, 'Debe especificar una edición de trabajo válida.');
            }

            // 1. Ofertas de Ítems activas con tarifa vigente
            $stmtItems = $this->pdo->prepare("
                SELECT 
                    o.`id` AS oferta_item_id,
                    i.`id` AS item_comercial_id,
                    i.`codigo`,
                    i.`nombre`,
                    i.`tipo`,
                    i.`unidad_medida`,
                    i.`descripcion`,
                    t.`precio` AS precio_unitario,
                    t.`moneda`
                FROM `ofertas_items_edicion` o
                INNER JOIN `items_comerciales` i ON i.`id` = o.`item_comercial_id`
                INNER JOIN `tarifas_items_edicion` t ON t.`oferta_item_id` = o.`id`
                WHERE o.`organizacion_id` = :org_id
                  AND o.`edicion_id` = :edicion_id
                  AND o.`estado` = 'ACTIVO'
                  AND i.`estado` = 'ACTIVO'
                ORDER BY i.`nombre` ASC
            ");
            $stmtItems->execute(['org_id' => $orgId, 'edicion_id' => $edicionId]);
            $ofertasItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            // 2. Ofertas de Paquetes activas con tarifa vigente y componentes
            $stmtPaq = $this->pdo->prepare("
                SELECT 
                    o.`id` AS oferta_paquete_id,
                    p.`id` AS paquete_id,
                    p.`codigo`,
                    p.`nombre`,
                    p.`descripcion`,
                    t.`precio` AS precio_unitario,
                    t.`moneda`
                FROM `ofertas_paquetes_edicion` o
                INNER JOIN `paquetes` p ON p.`id` = o.`paquete_id`
                INNER JOIN `tarifas_paquetes_edicion` t ON t.`oferta_paquete_id` = o.`id`
                WHERE o.`organizacion_id` = :org_id
                  AND o.`edicion_id` = :edicion_id
                  AND o.`estado` = 'ACTIVO'
                  AND p.`estado` = 'ACTIVO'
                ORDER BY p.`nombre` ASC
            ");
            $stmtPaq->execute(['org_id' => $orgId, 'edicion_id' => $edicionId]);
            $ofertasPaquetes = $stmtPaq->fetchAll(PDO::FETCH_ASSOC);

            // Para cada paquete, obtener sus componentes para la vista previa
            foreach ($ofertasPaquetes as &$p) {
                $stmtComp = $this->pdo->prepare("
                    SELECT pi.`cantidad`, i.`codigo`, i.`nombre`, i.`unidad_medida`
                    FROM `paquete_items` pi
                    INNER JOIN `items_comerciales` i ON i.`id` = pi.`item_comercial_id`
                    WHERE pi.`paquete_id` = :paq_id
                    ORDER BY pi.`orden` ASC
                ");
                $stmtComp->execute(['paq_id' => $p['paquete_id']]);
                $p['componentes'] = $stmtComp->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($p);

            return $this->responderJson(true, 200, 'Ofertas activas consultadas para la edición.', [
                'items'    => $ofertasItems,
                'paquetes' => $ofertasPaquetes,
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
        error_log("[CotizacionControlador Error] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        return $this->responderJson(false, 500, 'Ha ocurrido un error inesperado al procesar la solicitud en cotizaciones.', (object) [], ['error' => 'ERROR_INTERNO']);
    }
}
