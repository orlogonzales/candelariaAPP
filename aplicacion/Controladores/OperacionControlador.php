<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Operaciones\EstadoAsistencia;
use Aplicacion\Operaciones\EstadoProveedor;
use Aplicacion\Operaciones\EstadoRecursoFisico;
use Aplicacion\Operaciones\EstadoSalida;
use Aplicacion\Operaciones\OperacionServicio;
use Aplicacion\Operaciones\PropiedadRecurso;
use Aplicacion\Operaciones\ProveedorRecursoServicio;
use Aplicacion\Operaciones\RolOperativoRecurso;
use Aplicacion\Operaciones\TipoIncidenciaOperativa;
use Aplicacion\Operaciones\TipoRecursoFisico;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\EntregaProductoRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\OperacionRecursoRepositorio;
use Aplicacion\Repositorios\OperacionSalidaRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\ProveedorRepositorio;
use Aplicacion\Repositorios\ReservaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Reservas\TipoCapacidad;
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
 * Controlador oficial del Módulo de Operaciones de Campo, Salidas, Recursos y Check-in (F2.6E).
 */
class OperacionControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private OperacionServicio $operacionServicio;
    private ProveedorRecursoServicio $proveedorRecursoServicio;
    private OperacionSalidaRepositorio $salidaRepo;
    private OperacionRecursoRepositorio $recursoRepo;
    private ProveedorRepositorio $proveedorRepo;
    private ReservaRepositorio $reservaRepo;
    private EntregaProductoRepositorio $entregaRepo;
    private EdicionRepositorio $edicionRepo;
    private PersonaRepositorio $personaRepo;
    private AuditoriaRepositorio $auditoriaRepo;
    private ConfiguracionServicio $configServicio;
    private ContextoEdicionResolver $contextoEdicionResolver;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?OperacionServicio $operacionServicio = null,
        ?ProveedorRecursoServicio $proveedorRecursoServicio = null,
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

        $this->salidaRepo = new OperacionSalidaRepositorio($this->pdo);
        $this->recursoRepo = new OperacionRecursoRepositorio($this->pdo);
        $this->proveedorRepo = new ProveedorRepositorio($this->pdo);
        $this->reservaRepo = new ReservaRepositorio($this->pdo);
        $this->entregaRepo = new EntregaProductoRepositorio($this->pdo);
        $this->personaRepo = new PersonaRepositorio($this->pdo);
        $this->auditoriaRepo = new AuditoriaRepositorio($this->pdo);

        $rolRepo = new RolRepositorio($this->pdo);
        $permRepo = new PermisoRepositorio($this->pdo);
        $usrRepo = new UsuarioRepositorio($this->pdo);
        $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $usrRepo, $this->auditoriaRepo, $this->pdo);

        $itemRepo = new ItemComercialRepositorio($this->pdo);

        $this->operacionServicio = $operacionServicio ?? new OperacionServicio(
            salidaRepo: $this->salidaRepo,
            recursoRepo: $this->recursoRepo,
            proveedorRepo: $this->proveedorRepo,
            reservaRepo: $this->reservaRepo,
            entregaRepo: $this->entregaRepo,
            edicionRepo: $this->edicionRepo,
            itemRepo: $itemRepo,
            authzServicio: $authzServicio,
            auditoriaRepo: $this->auditoriaRepo,
            pdo: $this->pdo
        );

        $this->proveedorRecursoServicio = $proveedorRecursoServicio ?? new ProveedorRecursoServicio(
            proveedorRepo: $this->proveedorRepo,
            recursoRepo: $this->recursoRepo,
            authzServicio: $authzServicio,
            auditoriaRepo: $this->auditoriaRepo,
            pdo: $this->pdo
        );
    }

    // =========================================================================
    // 1. VISTAS WEB OPERATIVAS
    // =========================================================================

    /**
     * GET /operaciones
     * Tablero de Salidas Operativas y Control de Campo.
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('operacion.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Operaciones de Campo',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (operacion.ver) para consultar operaciones.'
            ], 'principal');
        }

        $permisos = [
            'ver'                  => true,
            'gestionarSalidas'     => $this->authzMiddleware->verificarPermiso('operacion.gestionar_salidas', $contexto, false),
            'asignarPrestaciones'  => $this->authzMiddleware->verificarPermiso('operacion.asignar_prestaciones', $contexto, false),
            'asignarRecursos'      => $this->authzMiddleware->verificarPermiso('operacion.asignar_recursos', $contexto, false),
            'checkin'              => $this->authzMiddleware->verificarPermiso('operacion.checkin', $contexto, false),
            'ejecutar'             => $this->authzMiddleware->verificarPermiso('operacion.ejecutar', $contexto, false),
            'registrarIncidencias' => $this->authzMiddleware->verificarPermiso('operacion.registrar_incidencias', $contexto, false),
            'proveedoresVer'       => $this->authzMiddleware->verificarPermiso('proveedores.ver', $contexto, false),
            'recursosVer'          => $this->authzMiddleware->verificarPermiso('recursos.ver', $contexto, false),
            'entregasDespachar'    => $this->authzMiddleware->verificarPermiso('entregas.despachar', $contexto, false),
        ];

        return Vista::renderizar('operaciones/index', [
            'titulo'          => 'Operaciones de Campo y Salidas | CandelariaAPP',
            'subtitulo'       => 'Control de Salidas, Manifiestos, Check-in y Ejecución',
            'tituloSeccion'   => 'Operaciones',
            'seccionActiva'   => 'operaciones',
            'permisos'        => $permisos,
            'scriptAdicional' => url_base('publico/js/operaciones.js')
        ], 'principal');
    }

    /**
     * GET /operaciones/recursos
     * Padrón de Proveedores y Recursos Físicos.
     */
    public function vistaRecursos(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        $puedeProv = $this->authzMiddleware->verificarPermiso('proveedores.ver', $contexto, false);
        $puedeRec = $this->authzMiddleware->verificarPermiso('recursos.ver', $contexto, false);

        if (!$puedeProv && !$puedeRec) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Proveedores y Recursos',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios para consultar proveedores o recursos.'
            ], 'principal');
        }

        $permisos = [
            'proveedoresVer'       => $puedeProv,
            'proveedoresGestionar' => $this->authzMiddleware->verificarPermiso('proveedores.gestionar', $contexto, false),
            'recursosVer'          => $puedeRec,
            'recursosGestionar'    => $this->authzMiddleware->verificarPermiso('recursos.gestionar', $contexto, false),
        ];

        return Vista::renderizar('operaciones/recursos', [
            'titulo'          => 'Proveedores y Recursos Físicos | CandelariaAPP',
            'subtitulo'       => 'Gestión de Embarcaciones, Vehículos y Terceros Aliados',
            'tituloSeccion'   => 'Recursos Operativos',
            'seccionActiva'   => 'recursos',
            'permisos'        => $permisos,
            'scriptAdicional' => url_base('publico/js/operaciones_recursos.js')
        ], 'principal');
    }

    /**
     * GET /operaciones/entregas
     * Gestión y Despacho de Órdenes de Entrega de Bienes Tangibles.
     */
    public function vistaEntregas(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('entregas.despachar', $contexto, false) &&
            !$this->authzMiddleware->verificarPermiso('operacion.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Despacho de Entregas',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios para gestionar entregas.'
            ], 'principal');
        }

        $permisos = [
            'despachar' => $this->authzMiddleware->verificarPermiso('entregas.despachar', $contexto, false),
        ];

        return Vista::renderizar('operaciones/entregas', [
            'titulo'          => 'Despacho de Entregas y Productos | CandelariaAPP',
            'subtitulo'       => 'Entrega Física de Bienes Tangibles e Indumentaria',
            'tituloSeccion'   => 'Entregas',
            'seccionActiva'   => 'entregas',
            'permisos'        => $permisos,
            'scriptAdicional' => url_base('publico/js/operaciones_entregas.js')
        ], 'principal');
    }

    // =========================================================================
    // 2. ENDPOINTS RESTful SALIDAS OPERATIVAS
    // =========================================================================

    /**
     * GET /api/v1/operaciones/salidas
     */
    public function listarSalidas(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('operacion.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso operacion.ver.');
        }

        $edicionId = $this->resolverEdicionContextual($contexto);

        try {
            $orgId = (int) $contexto->organizacionId;

            $fecha = !empty($_GET['fecha']) ? trim((string) $_GET['fecha']) : null;
            $estado = !empty($_GET['estado']) ? trim((string) $_GET['estado']) : null;
            $itemId = isset($_GET['item_id']) && is_numeric($_GET['item_id']) ? (int) $_GET['item_id'] : null;
            $correlativo = !empty($_GET['correlativo']) ? trim((string) $_GET['correlativo']) : null;

            $sql = "
                SELECT 
                    os.`id`,
                    os.`organizacion_id`,
                    os.`edicion_id`,
                    os.`item_comercial_id`,
                    os.`correlativo`,
                    os.`titulo`,
                    os.`fecha_salida`,
                    os.`hora_citacion`,
                    os.`hora_salida`,
                    os.`hora_inicio_real`,
                    os.`hora_fin_real`,
                    os.`punto_encuentro`,
                    os.`tipo_capacidad`,
                    os.`capacidad_maxima`,
                    os.`estado`,
                    os.`motivo_cancelacion_interrupcion`,
                    os.`version_bloqueo`,
                    os.`creado_en`,
                    ic.`nombre` AS servicio_nombre,
                    ic.`codigo` AS servicio_codigo,
                    (
                        SELECT COALESCE(SUM(osp.`cantidad_pasajeros`), 0)
                        FROM `operacion_salida_prestaciones` osp
                        WHERE osp.`salida_id` = os.`id`
                    ) AS pasajeros_asignados,
                    (
                        SELECT COUNT(*)
                        FROM `operacion_asistencias` oa
                        WHERE oa.`salida_id` = os.`id` AND oa.`estado_asistencia` = 'PRESENTE'
                    ) AS presentes_count,
                    (
                        SELECT COUNT(*)
                        FROM `operacion_asistencias` oa
                        WHERE oa.`salida_id` = os.`id` AND oa.`estado_asistencia` = 'NO_SHOW'
                    ) AS no_show_count,
                    (
                        SELECT COUNT(*)
                        FROM `operacion_incidencias` oi
                        WHERE oi.`salida_id` = os.`id`
                    ) AS incidencias_count
                FROM `operacion_salidas` os
                INNER JOIN `items_comerciales` ic ON ic.`id` = os.`item_comercial_id`
                WHERE os.`organizacion_id` = :org_id
            ";

            $params = ['org_id' => $orgId];

            if ($edicionId !== null) {
                $sql .= " AND os.`edicion_id` = :edicion_id";
                $params['edicion_id'] = $edicionId;
            }

            if ($fecha !== null && $fecha !== '') {
                $sql .= " AND os.`fecha_salida` = :fecha";
                $params['fecha'] = $fecha;
            }

            if ($estado !== null && $estado !== '') {
                $sql .= " AND os.`estado` = :estado";
                $params['estado'] = $estado;
            }

            if ($itemId !== null) {
                $sql .= " AND os.`item_comercial_id` = :item_id";
                $params['item_id'] = $itemId;
            }

            if ($correlativo !== null && $correlativo !== '') {
                $sql .= " AND os.`correlativo` LIKE :correlativo";
                $params['correlativo'] = '%' . $correlativo . '%';
            }

            $sql .= " ORDER BY os.`fecha_salida` DESC, os.`hora_salida` ASC, os.`id` DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $salidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Salidas operativas recuperadas.', [
                'salidas' => $salidas,
                'total'   => count($salidas),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/operaciones/salidas/{id}
     */
    public function detalleSalida(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('operacion.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso operacion.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $salida = $this->salidaRepo->buscarPorId($id, $orgId);

            if ($salida === null) {
                return $this->responderJson(false, 404, "La salida #{$id} no existe en su organización.");
            }

            // Recursos asignados con nombres
            $stmtRec = $this->pdo->prepare("
                SELECT 
                    osr.id AS asignacion_id,
                    osr.rol_operativo,
                    osr.notas,
                    orf.codigo_interno,
                    orf.nombre AS recurso_nombre,
                    orf.tipo_recurso,
                    p.id AS persona_id,
                    CONCAT(p.nombres, ' ', p.apellidos) AS persona_nombre,
                    p.telefono_whatsapp AS persona_telefono
                FROM `operacion_salida_recursos` osr
                LEFT JOIN `operacion_recursos` orf ON orf.id = osr.recurso_fisico_id
                LEFT JOIN `personas` p ON p.id = osr.persona_id
                WHERE osr.salida_id = :salida_id
                ORDER BY osr.id ASC
            ");
            $stmtRec->execute(['salida_id' => $id]);
            $recursos = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

            // Prestaciones asignadas
            $stmtPrest = $this->pdo->prepare("
                SELECT 
                    osp.id AS asignacion_id,
                    osp.prestacion_id,
                    osp.cantidad_pasajeros,
                    osp.asignado_en,
                    rp.concepto_nombre,
                    rp.concepto_codigo,
                    r.id AS reserva_id,
                    r.correlativo AS reserva_correlativo,
                    r.contacto_nombre AS titular_reserva
                FROM `operacion_salida_prestaciones` osp
                INNER JOIN `reserva_prestaciones` rp ON rp.id = osp.prestacion_id
                INNER JOIN `reservas` r ON r.id = rp.reserva_id
                WHERE osp.salida_id = :salida_id
                ORDER BY osp.id ASC
            ");
            $stmtPrest->execute(['salida_id' => $id]);
            $prestaciones = $stmtPrest->fetchAll(PDO::FETCH_ASSOC);

            // Ocupación
            $ocupacionActual = $this->salidaRepo->calcularOcupacionActiva($id);

            return $this->responderJson(true, 200, 'Detalle de salida recuperado.', [
                'salida' => [
                    'id'               => $salida->id,
                    'correlativo'      => $salida->correlativo,
                    'titulo'           => $salida->titulo,
                    'edicion_id'       => $salida->edicionId,
                    'item_comercial_id'=> $salida->itemComercialId,
                    'fecha_salida'     => $salida->fechaSalida,
                    'hora_citacion'    => $salida->horaCitacion,
                    'hora_salida'      => $salida->horaSalida,
                    'hora_inicio_real' => $salida->horaInicioReal,
                    'hora_fin_real'    => $salida->horaFinReal,
                    'punto_encuentro'  => $salida->puntoEncuentro,
                    'tipo_capacidad'   => $salida->tipoCapacidad->value,
                    'capacidad_maxima' => $salida->capacidadMaxima,
                    'ocupacion_actual' => $ocupacionActual,
                    'estado'           => $salida->estado->value,
                    'motivo'           => $salida->motivoCancelacionInterrupcion,
                    'version_bloqueo'  => $salida->versionBloqueo,
                    'creado_en'        => $salida->creadoEn,
                    'recursos'         => $recursos,
                    'prestaciones'     => $prestaciones,
                ]
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/operaciones/salidas
     */
    public function crearSalida(): string
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

        if (!$this->authzMiddleware->verificarPermiso('operacion.gestionar_salidas', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso operacion.gestionar_salidas.');
        }

        $edicionId = (int) ($cuerpo['edicion_id'] ?? 0);
        if ($edicionId <= 0) {
            $edicionId = (int) ($this->resolverEdicionContextual($contexto) ?? 0);
        }
        $itemId = (int) ($cuerpo['item_comercial_id'] ?? 0);
        $titulo = trim((string) ($cuerpo['titulo'] ?? ''));
        $fechaSalida = trim((string) ($cuerpo['fecha_salida'] ?? ''));
        $horaCitacion = trim((string) ($cuerpo['hora_citacion'] ?? ''));
        $horaSalida = trim((string) ($cuerpo['hora_salida'] ?? ''));
        $puntoEncuentro = trim((string) ($cuerpo['punto_encuentro'] ?? ''));
        $tipoCapStr = trim((string) ($cuerpo['tipo_capacidad'] ?? 'COLECTIVA'));
        $capMax = isset($cuerpo['capacidad_maxima']) && is_numeric($cuerpo['capacidad_maxima'])
            ? (int) $cuerpo['capacidad_maxima']
            : null;

        if ($edicionId <= 0 || $itemId <= 0 || $titulo === '' || $fechaSalida === '' || $horaSalida === '' || $puntoEncuentro === '') {
            return $this->responderJson(false, 422, 'Edición, servicio, título, fecha de salida, hora de salida y punto de encuentro son obligatorios.');
        }

        try {
            $tipoCap = TipoCapacidad::from($tipoCapStr);
        } catch (Throwable) {
            return $this->responderJson(false, 422, "Tipo de capacidad inválido ('{$tipoCapStr}').");
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $salida = $this->operacionServicio->crearSalida(
                organizacionId: $orgId,
                edicionId: $edicionId,
                itemComercialId: $itemId,
                titulo: $titulo,
                fechaSalida: $fechaSalida,
                horaCitacion: $horaCitacion !== '' ? $horaCitacion : $horaSalida,
                horaSalida: $horaSalida,
                puntoEncuentro: $puntoEncuentro,
                tipoCapacidad: $tipoCap,
                capacidadMaxima: $capMax,
                contexto: $contexto
            );

            return $this->responderJson(true, 201, 'Salida operativa creada exitosamente.', [
                'salida' => [
                    'id'          => $salida->id,
                    'correlativo' => $salida->correlativo,
                    'titulo'      => $salida->titulo,
                    'estado'      => $salida->estado->value,
                ]
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/operaciones/salidas/{id}
     */
    public function actualizarSalida(int $id): string
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

        try {
            $orgId = (int) $contexto->organizacionId;
            $salida = $this->salidaRepo->buscarPorId($id, $orgId);
            if ($salida === null) {
                return $this->responderJson(false, 404, "La salida #{$id} no existe en su organización.");
            }

            if ($salida->estado->esTerminal()) {
                return $this->responderJson(false, 422, "No se puede editar una salida en estado '{$salida->estado->value}'.");
            }

            $versionEsperada = (int) ($cuerpo['version_bloqueo'] ?? $salida->versionBloqueo);
            $titulo = trim((string) ($cuerpo['titulo'] ?? $salida->titulo));
            $fechaSalida = trim((string) ($cuerpo['fecha_salida'] ?? $salida->fechaSalida));
            $horaCitacion = trim((string) ($cuerpo['hora_citacion'] ?? $salida->horaCitacion));
            $horaSalida = trim((string) ($cuerpo['hora_salida'] ?? $salida->horaSalida));
            $puntoEncuentro = trim((string) ($cuerpo['punto_encuentro'] ?? $salida->puntoEncuentro));
            $capMax = isset($cuerpo['capacidad_maxima']) && is_numeric($cuerpo['capacidad_maxima'])
                ? (int) $cuerpo['capacidad_maxima']
                : $salida->capacidadMaxima;

            $salidaActualizada = new \Aplicacion\Entidades\OperacionSalida(
                id: $id,
                organizacionId: $orgId,
                edicionId: $salida->edicionId,
                itemComercialId: $salida->itemComercialId,
                correlativo: $salida->correlativo,
                titulo: $titulo,
                fechaSalida: $fechaSalida,
                horaCitacion: $horaCitacion,
                horaSalida: $horaSalida,
                puntoEncuentro: $puntoEncuentro,
                tipoCapacidad: $salida->tipoCapacidad,
                capacidadMaxima: $capMax,
                estado: $salida->estado,
                motivoCancelacionInterrupcion: $salida->motivoCancelacionInterrupcion,
                versionBloqueo: $versionEsperada
            );

            $guardada = $this->salidaRepo->guardar($salidaActualizada);

            return $this->responderJson(true, 200, 'Salida actualizada exitosamente.', [
                'salida' => [
                    'id'              => $guardada->id,
                    'titulo'          => $guardada->titulo,
                    'fecha_salida'    => $guardada->fechaSalida,
                    'version_bloqueo' => $guardada->versionBloqueo,
                ]
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(false, 409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/operaciones/salidas/{id}/prestaciones-compatibles
     */
    public function prestacionesCompatibles(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('operacion.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso operacion.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $compatibles = $this->operacionServicio->obtenerPrestacionesCompatiblesParaSalida($orgId, $id, $contexto);

            return $this->responderJson(true, 200, 'Prestaciones compatibles recuperadas.', [
                'prestaciones' => $compatibles,
                'total'        => count($compatibles),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/operaciones/salidas/{id}/prestaciones
     */
    public function asignarPrestacion(int $id): string
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

        $prestacionId = (int) ($cuerpo['prestacion_id'] ?? 0);
        if ($prestacionId <= 0) {
            return $this->responderJson(false, 422, 'Debe especificar el identificador de la prestación a asignar.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $asignacion = $this->operacionServicio->asignarPrestacionASalida($orgId, $id, $prestacionId, $contexto);

            return $this->responderJson(true, 201, 'Prestación asignada a la salida exitosamente.', [
                'asignacion_id'      => $asignacion->id,
                'prestacion_id'      => $asignacion->prestacionId,
                'cantidad_pasajeros' => $asignacion->cantidadPasajeros,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            // Conflicto de capacidad o regla de compatibilidad
            return $this->responderJson(false, 409, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * DELETE /api/v1/operaciones/salidas/{id}/prestaciones/{prestacionId}
     */
    public function desasignarPrestacion(int $id, int $prestacionId): string
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

        try {
            $orgId = (int) $contexto->organizacionId;
            $this->operacionServicio->desasignarPrestacionDeSalida($orgId, $id, $prestacionId, $contexto);

            return $this->responderJson(true, 200, 'Prestación desasignada de la salida exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/operaciones/salidas/{id}/recursos
     */
    public function asignarRecurso(int $id): string
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

        $recursoFisicoId = isset($cuerpo['recurso_fisico_id']) && is_numeric($cuerpo['recurso_fisico_id'])
            ? (int) $cuerpo['recurso_fisico_id']
            : null;
        $personaId = isset($cuerpo['persona_id']) && is_numeric($cuerpo['persona_id'])
            ? (int) $cuerpo['persona_id']
            : null;
        $rolStr = trim((string) ($cuerpo['rol_operativo'] ?? ''));
        $notas = !empty($cuerpo['notas']) ? trim((string) $cuerpo['notas']) : null;

        if ($recursoFisicoId === null && $personaId === null) {
            return $this->responderJson(false, 422, 'Debe especificar un recurso físico (embarcación/vehículo) o una persona (guía/chofer).');
        }

        if ($rolStr === '') {
            return $this->responderJson(false, 422, 'El rol operativo es obligatorio.');
        }

        try {
            $rol = RolOperativoRecurso::from($rolStr);
        } catch (Throwable) {
            return $this->responderJson(false, 422, "Rol operativo inválido ('{$rolStr}').");
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $asignacion = $this->operacionServicio->asignarRecursoASalida(
                organizacionId: $orgId,
                salidaId: $id,
                recursoFisicoId: $recursoFisicoId,
                personaId: $personaId,
                rolOperativo: $rol,
                notas: $notas,
                contexto: $contexto
            );

            return $this->responderJson(true, 201, 'Recurso asignado a la salida exitosamente.', [
                'asignacion_id' => $asignacion->id,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * DELETE /api/v1/operaciones/salidas/{id}/recursos/{asignacionId}
     */
    public function desasignarRecurso(int $id, int $asignacionId): string
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

        try {
            $orgId = (int) $contexto->organizacionId;
            $this->operacionServicio->desasignarRecursoDeSalida($orgId, $id, $asignacionId, $contexto);

            return $this->responderJson(true, 200, 'Recurso desasignado de la salida exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/operaciones/salidas/{id}/manifiesto
     */
    public function manifiesto(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('operacion.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso operacion.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $manifiesto = $this->operacionServicio->obtenerManifiesto($orgId, $id, $contexto);

            $salida = $manifiesto['salida'];

            return $this->responderJson(true, 200, 'Manifiesto recuperado.', [
                'salida' => [
                    'id'              => $salida->id,
                    'correlativo'     => $salida->correlativo,
                    'titulo'          => $salida->titulo,
                    'fecha_salida'    => $salida->fechaSalida,
                    'hora_citacion'   => $salida->horaCitacion,
                    'hora_salida'     => $salida->horaSalida,
                    'punto_encuentro' => $salida->puntoEncuentro,
                    'tipo_capacidad'  => $salida->tipoCapacidad->value,
                    'capacidad_maxima'=> $salida->capacidadMaxima,
                    'estado'          => $salida->estado->value,
                ],
                'resumen' => [
                    'total_pax'  => $manifiesto['total_pax'],
                    'presentes'  => $manifiesto['presentes'],
                    'no_show'    => $manifiesto['no_show'],
                    'pendientes' => $manifiesto['pendientes'],
                ],
                'pasajeros' => array_map(fn($p) => [
                    'asistencia_id'                 => $p['asistencia_id'],
                    'participante_id'               => $p['participante_id'],
                    'ubicacion_asiento'             => $p['ubicacion_asiento'],
                    'estado_asistencia'             => $p['estado_asistencia'],
                    'marcado_en'                    => $p['marcado_en'],
                    'nombres'                       => $p['nombres'],
                    'apellidos'                     => $p['apellidos'],
                    'numero_documento'              => $p['numero_documento'],
                    'nacionalidad'                  => $p['nacionalidad'],
                    'rango_etario'                  => $p['rango_etario'],
                    'regimen_alimentario'           => $p['regimen_alimentario'],
                    'requiere_asistencia_movilidad' => (bool) $p['requiere_asistencia_movilidad'],
                    'telefono_contacto'             => $p['telefono_contacto'],
                    'reserva_correlativo'           => $p['reserva_correlativo'],
                    'contratante_reserva'           => $p['contratante_reserva'],
                ], $manifiesto['pasajeros']),
                'recursos' => array_map(fn($r) => [
                    'id'                => $r->id,
                    'recurso_fisico_id' => $r->recursoFisicoId,
                    'persona_id'        => $r->personaId,
                    'rol_operativo'     => $r->rolOperativo->value,
                    'notas'             => $r->notas,
                ], $manifiesto['recursos']),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/operaciones/salidas/{id}/checkin
     */
    public function listadoCheckin(int $id): string
    {
        return $this->manifiesto($id);
    }

    /**
     * POST /api/v1/operaciones/salidas/{id}/checkin
     */
    public function marcarCheckin(int $id): string
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

        if (!$this->authzMiddleware->verificarPermiso('operacion.checkin', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso operacion.checkin.');
        }

        $participanteId = (int) ($cuerpo['participante_id'] ?? 0);
        if ($participanteId <= 0 && !empty($cuerpo['asistencia_id'])) {
            $stmtAid = $this->pdo->prepare("SELECT participante_id FROM `operacion_asistencias` WHERE `id` = :aid");
            $stmtAid->execute(['aid' => (int) $cuerpo['asistencia_id']]);
            $participanteId = (int) ($stmtAid->fetchColumn() ?: 0);
        }
        $estadoStr = trim((string) ($cuerpo['estado_asistencia'] ?? ''));
        $asiento = !empty($cuerpo['ubicacion_asiento']) ? trim((string) $cuerpo['ubicacion_asiento']) : null;
        $obs = !empty($cuerpo['observacion']) ? trim((string) $cuerpo['observacion']) : null;

        if ($participanteId <= 0 || $estadoStr === '') {
            return $this->responderJson(false, 422, 'Participante y nuevo estado de asistencia son obligatorios.');
        }

        try {
            $estado = EstadoAsistencia::from($estadoStr);
        } catch (Throwable) {
            return $this->responderJson(false, 422, "Estado de asistencia inválido ('{$estadoStr}').");
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $asistencia = $this->operacionServicio->marcarCheckin(
                organizacionId: $orgId,
                salidaId: $id,
                participanteId: $participanteId,
                estadoAsistencia: $estado,
                asiento: $asiento,
                observacion: $obs,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, 'Check-in registrado exitosamente.', [
                'asistencia_id'     => $asistencia->id,
                'participante_id'   => $asistencia->participanteId,
                'estado_asistencia' => $asistencia->estadoAsistencia->value,
                'ubicacion_asiento' => $asistencia->ubicacionAsiento,
                'marcado_en'        => $asistencia->marcadoEn,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/operaciones/salidas/{id}/despachar
     */
    public function despachar(int $id): string
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

        $version = (int) ($cuerpo['version_bloqueo'] ?? 1);

        try {
            $orgId = (int) $contexto->organizacionId;
            $salida = $this->operacionServicio->despacharSalida($orgId, $id, $version, $contexto);

            return $this->responderJson(true, 200, 'Salida despachada a campo exitosamente.', [
                'salida_id'        => $salida->id,
                'estado'           => $salida->estado->value,
                'hora_inicio_real' => $salida->horaInicioReal,
                'version_bloqueo'  => $salida->versionBloqueo,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(false, 409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/operaciones/salidas/{id}/finalizar
     */
    public function finalizar(int $id): string
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

        $version = (int) ($cuerpo['version_bloqueo'] ?? 1);

        try {
            $orgId = (int) $contexto->organizacionId;
            $salida = $this->operacionServicio->finalizarSalida($orgId, $id, $version, $contexto);

            return $this->responderJson(true, 200, 'Salida finalizada exitosamente tras culminar la ejecución.', [
                'salida_id'       => $salida->id,
                'estado'          => $salida->estado->value,
                'hora_fin_real'   => $salida->horaFinReal,
                'version_bloqueo' => $salida->versionBloqueo,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(false, 409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/operaciones/salidas/{id}/interrumpir
     */
    public function interrumpir(int $id): string
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

        $version = (int) ($cuerpo['version_bloqueo'] ?? 1);
        $motivo = trim((string) ($cuerpo['motivo'] ?? ''));

        if ($motivo === '') {
            return $this->responderJson(false, 422, 'Debe especificar el motivo de interrupción de la salida.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $salida = $this->operacionServicio->interrumpirSalida($orgId, $id, $motivo, $version, $contexto);

            return $this->responderJson(true, 200, 'Salida interrumpida formalmente.', [
                'salida_id'       => $salida->id,
                'estado'          => $salida->estado->value,
                'version_bloqueo' => $salida->versionBloqueo,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(false, 409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/operaciones/salidas/{id}/cancelar
     */
    public function cancelar(int $id): string
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

        $version = (int) ($cuerpo['version_bloqueo'] ?? 1);
        $motivo = trim((string) ($cuerpo['motivo'] ?? ''));

        if ($motivo === '') {
            return $this->responderJson(false, 422, 'Debe especificar el motivo de cancelación de la salida.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $salida = $this->operacionServicio->cancelarSalida($orgId, $id, $motivo, $version, $contexto);

            return $this->responderJson(true, 200, 'Salida cancelada exitosamente.', [
                'salida_id'       => $salida->id,
                'estado'          => $salida->estado->value,
                'version_bloqueo' => $salida->versionBloqueo,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(false, 409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/operaciones/salidas/{id}/incidencias
     */
    public function listarIncidencias(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('operacion.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso operacion.ver.');
        }

        try {
            $incidencias = $this->salidaRepo->obtenerIncidenciasPorSalida($id);

            return $this->responderJson(true, 200, 'Incidencias recuperadas.', [
                'incidencias' => array_map(fn($inc) => [
                    'id'                 => $inc->id,
                    'salida_id'          => $inc->salidaId,
                    'tipo_incidencia'    => $inc->tipoIncidencia->value,
                    'descripcion'        => $inc->descripcion,
                    'acciones_tomadas'   => $inc->accionesTomadas,
                    'afecto_continuidad' => $inc->afectoContinuidad,
                    'registrado_por'     => $inc->registradoPor,
                    'registrado_en'      => $inc->registradoEn,
                ], $incidencias)
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/operaciones/salidas/{id}/incidencias
     */
    public function registrarIncidencia(int $id): string
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

        $tipoStr = trim((string) ($cuerpo['tipo_incidencia'] ?? ''));
        $desc = trim((string) ($cuerpo['descripcion'] ?? ''));
        $acciones = !empty($cuerpo['acciones_tomadas']) ? trim((string) $cuerpo['acciones_tomadas']) : null;
        $afecto = (bool) ($cuerpo['afecto_continuidad'] ?? false);

        if ($tipoStr === '' || $desc === '') {
            return $this->responderJson(false, 422, 'Tipo de incidencia y descripción del hecho son obligatorios.');
        }

        try {
            $tipo = TipoIncidenciaOperativa::from($tipoStr);
        } catch (Throwable) {
            return $this->responderJson(false, 422, "Tipo de incidencia no reconocido ('{$tipoStr}').");
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $inc = $this->operacionServicio->registrarIncidencia(
                organizacionId: $orgId,
                salidaId: $id,
                tipoIncidencia: $tipo,
                descripcion: $desc,
                accionesTomadas: $acciones,
                afectoContinuidad: $afecto,
                contexto: $contexto
            );

            return $this->responderJson(true, 201, 'Incidencia operativa registrada exitosamente.', [
                'incidencia_id' => $inc->id,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 3. ENDPOINTS PROVEEDORES Y RECURSOS
    // =========================================================================

    /**
     * GET /api/v1/operaciones/proveedores
     */
    public function listarProveedores(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('proveedores.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso proveedores.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $sql = "
                SELECT 
                    pr.id,
                    pr.organizacion_id,
                    pr.persona_id,
                    pr.tipo_servicio_principal,
                    pr.estado,
                    pr.notas_contacto,
                    pr.creado_en,
                    CONCAT(p.nombres, ' ', p.apellidos) AS nombre_completo,
                    p.numero_documento,
                    p.telefono_whatsapp AS telefono,
                    p.correo_electronico AS email,
                    (SELECT COUNT(*) FROM `operacion_recursos` r WHERE r.proveedor_id = pr.id) AS total_recursos
                FROM `proveedores` pr
                INNER JOIN `personas` p ON p.id = pr.persona_id
                WHERE pr.organizacion_id = :org_id
                ORDER BY pr.id DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['org_id' => $orgId]);
            $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Proveedores recuperados.', [
                'proveedores' => $proveedores,
                'total'       => count($proveedores),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/operaciones/proveedores
     */
    public function registrarProveedor(): string
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

        $personaId = (int) ($cuerpo['persona_id'] ?? 0);
        $tipoServicio = !empty($cuerpo['tipo_servicio_principal']) ? trim((string) $cuerpo['tipo_servicio_principal']) : null;
        $notas = !empty($cuerpo['notas_contacto']) ? trim((string) $cuerpo['notas_contacto']) : null;

        if ($personaId <= 0) {
            return $this->responderJson(false, 422, 'Debe seleccionar una persona existente para registrarla como proveedor.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $prov = $this->proveedorRecursoServicio->registrarProveedor(
                organizacionId: $orgId,
                personaId: $personaId,
                tipoServicioPrincipal: $tipoServicio,
                notasContacto: $notas,
                contexto: $contexto
            );

            return $this->responderJson(true, 201, 'Proveedor registrado exitosamente.', [
                'proveedor_id' => $prov->id,
                'estado'       => $prov->estado->value,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PATCH /api/v1/operaciones/proveedores/{id}/suspender
     */
    public function suspenderProveedor(int $id): string
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

        try {
            $orgId = (int) $contexto->organizacionId;
            $this->proveedorRecursoServicio->suspenderProveedor($orgId, $id, $contexto);

            return $this->responderJson(true, 200, 'Proveedor suspendido exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/operaciones/recursos
     */
    public function listarRecursos(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('recursos.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso recursos.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $sql = "
                SELECT 
                    r.id,
                    r.organizacion_id,
                    r.tipo_recurso,
                    r.codigo_interno,
                    r.nombre,
                    r.propiedad_tipo,
                    r.proveedor_id,
                    r.capacidad_maxima,
                    r.identificacion_oficial,
                    r.estado,
                    r.notas,
                    r.creado_en,
                    CONCAT(p.nombres, ' ', p.apellidos) AS proveedor_nombre
                FROM `operacion_recursos` r
                LEFT JOIN `proveedores` pr ON pr.id = r.proveedor_id
                LEFT JOIN `personas` p ON p.id = pr.persona_id
                WHERE r.organizacion_id = :org_id
                ORDER BY r.id DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['org_id' => $orgId]);
            $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Recursos físicos recuperados.', [
                'recursos' => $recursos,
                'total'    => count($recursos),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/operaciones/recursos
     */
    public function registrarRecurso(): string
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

        $tipoRecStr = trim((string) ($cuerpo['tipo_recurso'] ?? ''));
        $codigo = trim((string) ($cuerpo['codigo_interno'] ?? ''));
        $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
        $propiedadStr = trim((string) ($cuerpo['propiedad_tipo'] ?? 'PROPIO'));
        $proveedorId = isset($cuerpo['proveedor_id']) && is_numeric($cuerpo['proveedor_id']) ? (int) $cuerpo['proveedor_id'] : null;
        $capMax = (int) ($cuerpo['capacidad_maxima'] ?? 1);
        $identOficial = !empty($cuerpo['identificacion_oficial']) ? trim((string) $cuerpo['identificacion_oficial']) : null;
        $notas = !empty($cuerpo['notas']) ? trim((string) $cuerpo['notas']) : null;

        if ($tipoRecStr === '' || $codigo === '' || $nombre === '') {
            return $this->responderJson(false, 422, 'Tipo de recurso, código interno y nombre descriptivo son obligatorios.');
        }

        try {
            $tipoRec = TipoRecursoFisico::from($tipoRecStr);
            $propiedad = PropiedadRecurso::from($propiedadStr);
        } catch (Throwable $e) {
            return $this->responderJson(false, 422, 'Tipo de recurso o tipo de propiedad inválido.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $recurso = $this->proveedorRecursoServicio->registrarRecursoFisico(
                organizacionId: $orgId,
                tipoRecurso: $tipoRec,
                codigoInterno: $codigo,
                nombre: $nombre,
                propiedadTipo: $propiedad,
                proveedorId: $proveedorId,
                capacidadMaxima: $capMax,
                identificacionOficial: $identOficial,
                notas: $notas,
                contexto: $contexto
            );

            return $this->responderJson(true, 201, 'Recurso físico registrado exitosamente.', [
                'recurso_id'     => $recurso->id,
                'codigo_interno' => $recurso->codigoInterno,
                'estado'         => $recurso->estado->value,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PATCH /api/v1/operaciones/recursos/{id}/estado
     */
    public function cambiarEstadoRecurso(int $id): string
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

        $nuevoEstadoStr = trim((string) ($cuerpo['estado'] ?? ''));
        try {
            $nuevoEstado = EstadoRecursoFisico::from($nuevoEstadoStr);
        } catch (Throwable) {
            return $this->responderJson(false, 422, "Estado de recurso inválido ('{$nuevoEstadoStr}').");
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $this->recursoRepo->actualizarEstado($id, $orgId, $nuevoEstado);

            return $this->responderJson(true, 200, 'Estado del recurso actualizado exitosamente.');
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 4. ENDPOINTS DESPACHO DE ENTREGAS
    // =========================================================================

    /**
     * GET /api/v1/operaciones/entregas
     */
    public function listarEntregas(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        $edicionId = $this->resolverEdicionContextual($contexto);

        try {
            $orgId = (int) $contexto->organizacionId;
            $estado = !empty($_GET['estado']) ? trim((string) $_GET['estado']) : null;
            $busqueda = !empty($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : null;

            $sql = "
                SELECT 
                    ep.`id`,
                    ep.`organizacion_id`,
                    ep.`edicion_id`,
                    ep.`venta_id`,
                    ep.`cliente_id`,
                    ep.`correlativo`,
                    ep.`estado`,
                    ep.`contacto_nombre`,
                    ep.`contacto_telefono`,
                    ep.`direccion_entrega`,
                    ep.`fecha_entrega`,
                    ep.`entregado_por`,
                    ep.`notas_despacho`,
                    ep.`version_bloqueo`,
                    ep.`creado_en`,
                    v.`correlativo` AS venta_correlativo,
                    (SELECT COUNT(*) FROM `entrega_items` ei WHERE ei.`entrega_id` = ep.`id`) AS total_items,
                    (SELECT COALESCE(SUM(ei.`cantidad`), 0) FROM `entrega_items` ei WHERE ei.`entrega_id` = ep.`id`) AS cantidad_bienes
                FROM `entregas_productos` ep
                INNER JOIN `ventas` v ON v.`id` = ep.`venta_id`
                WHERE ep.`organizacion_id` = :org_id
            ";

            $params = ['org_id' => $orgId];

            if ($edicionId !== null) {
                $sql .= " AND ep.`edicion_id` = :edicion_id";
                $params['edicion_id'] = $edicionId;
            }

            if ($estado !== null && $estado !== '') {
                $sql .= " AND ep.`estado` = :estado";
                $params['estado'] = $estado;
            }

            if ($busqueda !== null && $busqueda !== '') {
                $sql .= " AND (ep.`correlativo` LIKE :b OR ep.`contacto_nombre` LIKE :b OR v.`correlativo` LIKE :b)";
                $params['b'] = '%' . $busqueda . '%';
            }

            $sql .= " ORDER BY ep.`id` DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Entregas recuperadas.', [
                'entregas' => $entregas,
                'total'    => count($entregas),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/operaciones/entregas/{id}
     */
    public function detalleEntrega(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $entrega = $this->entregaRepo->buscarPorId($id);

            if ($entrega === null || $entrega->organizacionId !== $orgId) {
                return $this->responderJson(false, 404, "La orden de entrega #{$id} no existe en su organización.");
            }

            return $this->responderJson(true, 200, 'Detalle de entrega recuperado.', [
                'entrega' => [
                    'id'                => $entrega->id,
                    'correlativo'       => $entrega->correlativo,
                    'estado'            => $entrega->estado->value,
                    'contacto_nombre'   => $entrega->contactoNombre,
                    'contacto_telefono' => $entrega->contactoTelefono,
                    'direccion_entrega' => $entrega->direccionEntrega,
                    'fecha_entrega'     => $entrega->fechaEntrega,
                    'notas_despacho'    => $entrega->notasDespacho,
                    'version_bloqueo'   => $entrega->versionBloqueo,
                    'creado_en'         => $entrega->creadoEn,
                    'items'             => array_map(fn($it) => [
                        'id'              => $it->id,
                        'concepto_codigo' => $it->conceptoCodigo,
                        'concepto_nombre' => $it->conceptoNombre,
                        'unidad_medida'   => $it->unidadMedida,
                        'cantidad'        => $it->cantidad,
                    ], $entrega->items),
                ]
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/operaciones/entregas/{id}/despachar
     */
    public function despacharEntrega(int $id): string
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

        if (!$this->authzMiddleware->verificarPermiso('entregas.despachar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso entregas.despachar.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;

            $entregaActual = $this->entregaRepo->buscarPorId($id);
            if ($entregaActual === null || $entregaActual->organizacionId !== $orgId) {
                return $this->responderJson(false, 404, "La orden de entrega #{$id} no existe en su organización.");
            }

            if (isset($cuerpo['version_bloqueo'])) {
                $versionEsperada = (int) $cuerpo['version_bloqueo'];
                if ($entregaActual->versionBloqueo !== $versionEsperada) {
                    return $this->responderJson(false, 409, "Conflicto de concurrencia: la orden de entrega fue modificada concurrentemente.");
                }
            }

            $entregada = $this->operacionServicio->despacharEntrega($orgId, $id, $contexto);

            return $this->responderJson(true, 200, 'Orden de entrega despachada exitosamente.', [
                'entrega_id'    => $entregada->id,
                'estado'        => $entregada->estado->value,
                'fecha_entrega' => $entregada->fechaEntrega,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 5. LOOKUPS AUXILIARES
    // =========================================================================

    /**
     * GET /api/v1/operaciones/aux/personas
     * Búsqueda de personas para Select2 remoto (Guías, Choferes, Proveedores).
     */
    public function auxPersonas(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        try {
            $q = !empty($_GET['q']) ? trim((string) $_GET['q']) : '';
            $sql = "
                SELECT id, CONCAT(nombres, ' ', apellidos) AS text, numero_documento, telefono_whatsapp AS telefono, correo_electronico AS email
                FROM `personas`
                WHERE 1 = 1
            ";
            $params = [];

            if ($q !== '') {
                $sql .= " AND (nombres LIKE :q OR apellidos LIKE :q OR numero_documento LIKE :q)";
                $params['q'] = '%' . $q . '%';
            }

            $sql .= " ORDER BY apellidos ASC, nombres ASC LIMIT 30";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $personas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Personas encontradas.', [
                'personas' => $personas
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/operaciones/aux/servicios
     */
    public function auxServicios(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $stmt = $this->pdo->prepare("
                SELECT id, codigo, nombre, tipo_item, unidad_medida
                FROM `items_comerciales`
                WHERE `organizacion_id` = :org_id AND `tipo_item` = 'SERVICIO' AND `estado` = 'ACTIVO'
                ORDER BY `nombre` ASC
            ");
            $stmt->execute(['org_id' => $orgId]);
            $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Servicios comerciales recuperados.', [
                'servicios' => $servicios
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/operaciones/aux/recursos-activos
     */
    public function auxRecursosActivos(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $stmt = $this->pdo->prepare("
                SELECT id, codigo_interno, nombre, tipo_recurso, capacidad_maxima, estado
                FROM `operacion_recursos`
                WHERE `organizacion_id` = :org_id AND `estado` = 'DISPONIBLE'
                ORDER BY `nombre` ASC
            ");
            $stmt->execute(['org_id' => $orgId]);
            $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Recursos activos recuperados.', [
                'recursos' => $recursos
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // UTILIDADES PRIVADAS
    // =========================================================================

    private function resolverEdicionContextual(ContextoOperacion $contexto): ?int
    {
        $candidato = null;
        if (!empty($_GET['edicion_id'])) {
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
        error_log("[OperacionControlador Error] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        return $this->responderJson(false, 500, 'Ha ocurrido un error inesperado al procesar la solicitud en operaciones.', (object) [], ['error' => 'ERROR_INTERNO']);
    }
}
