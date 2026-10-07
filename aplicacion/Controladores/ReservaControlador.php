<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\EntregaProductoRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\ItemConfiguracionOperativaRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\ReservaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Reservas\EstadoAgendamientoPrestacion;
use Aplicacion\Reservas\EstadoReserva;
use Aplicacion\Reservas\MotivoReprogramacion;
use Aplicacion\Reservas\ReservaServicio;
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
 * Controlador oficial del Módulo de Reservas y Compromisos de Servicio (F2.6E).
 * Proyecta fielmente el dominio soberano de Reservas (F2.6C).
 */
class ReservaControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private ReservaServicio $reservaServicio;
    private ReservaRepositorio $reservaRepo;
    private VentaRepositorio $ventaRepo;
    private EdicionRepositorio $edicionRepo;
    private AuditoriaRepositorio $auditoriaRepo;
    private ConfiguracionServicio $configServicio;
    private ContextoEdicionResolver $contextoEdicionResolver;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?ReservaServicio $reservaServicio = null,
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

        $this->reservaRepo = new ReservaRepositorio($this->pdo);
        $this->ventaRepo = new VentaRepositorio($this->pdo);
        $this->auditoriaRepo = new AuditoriaRepositorio($this->pdo);

        if ($reservaServicio !== null) {
            $this->reservaServicio = $reservaServicio;
        } else {
            $rolRepo = new RolRepositorio($this->pdo);
            $permRepo = new PermisoRepositorio($this->pdo);
            $usrRepo = new UsuarioRepositorio($this->pdo);
            $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $usrRepo, $this->auditoriaRepo, $this->pdo);

            $entregaRepo = new EntregaProductoRepositorio($this->pdo);
            $itemCfgRepo = new ItemConfiguracionOperativaRepositorio($this->pdo);
            $itemRepo = new ItemComercialRepositorio($this->pdo);

            $this->reservaServicio = new ReservaServicio(
                reservaRepo: $this->reservaRepo,
                entregaRepo: $entregaRepo,
                itemCfgRepo: $itemCfgRepo,
                ventaRepo: $this->ventaRepo,
                edicionRepo: $this->edicionRepo,
                itemRepo: $itemRepo,
                authzServicio: $authzServicio,
                auditoriaRepo: $this->auditoriaRepo,
                pdo: $this->pdo
            );
        }
    }

    // =========================================================================
    // 1. VISTA WEB OPERATIVA
    // =========================================================================

    /**
     * GET /reservas
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('reservas.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Reservas de Servicios',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (reservas.ver) para consultar las reservas.'
            ], 'principal');
        }

        $permisos = [
            'ver'                   => true,
            'crearDesdeVenta'       => $this->authzMiddleware->verificarPermiso('reservas.crear_desde_venta', $contexto, false),
            'programar'             => $this->authzMiddleware->verificarPermiso('reservas.programar', $contexto, false),
            'reprogramar'           => $this->authzMiddleware->verificarPermiso('reservas.reprogramar', $contexto, false),
            'gestionarParticipantes'=> $this->authzMiddleware->verificarPermiso('reservas.gestionar_participantes', $contexto, false),
            'cancelar'              => $this->authzMiddleware->verificarPermiso('reservas.cancelar', $contexto, false),
        ];

        return Vista::renderizar('reservas/index', [
            'titulo'          => 'Reservas y Agendamiento | CandelariaAPP',
            'subtitulo'       => 'Gestión de Compromisos de Servicio, Turnos y Pasajeros',
            'tituloSeccion'   => 'Reservas',
            'seccionActiva'   => 'reservas',
            'permisos'        => $permisos,
            'scriptAdicional' => url_base('publico/js/reservas.js')
        ], 'principal');
    }

    // =========================================================================
    // 2. ENDPOINTS RESTful /api/v1/reservas
    // =========================================================================

    /**
     * GET /api/v1/reservas
     * Listado asíncrono con filtros para DataTables.
     */
    public function listar(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('reservas.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso reservas.ver.');
        }

        $edicionId = $this->resolverEdicionContextual($contexto);

        try {
            $orgId = (int) $contexto->organizacionId;

            $estado = !empty($_GET['estado']) ? trim((string) $_GET['estado']) : null;
            $clienteId = isset($_GET['cliente_id']) && is_numeric($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : null;
            $correlativo = !empty($_GET['correlativo']) ? trim((string) $_GET['correlativo']) : null;
            $fechaDesde = !empty($_GET['fecha_desde']) ? trim((string) $_GET['fecha_desde']) : null;
            $fechaHasta = !empty($_GET['fecha_hasta']) ? trim((string) $_GET['fecha_hasta']) : null;
            $programacion = !empty($_GET['programacion']) ? trim((string) $_GET['programacion']) : null;
            $busqueda = !empty($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : null;

            $sql = "
                SELECT 
                    r.`id`,
                    r.`organizacion_id`,
                    r.`edicion_id`,
                    r.`venta_id`,
                    r.`cliente_id`,
                    r.`correlativo`,
                    r.`estado`,
                    r.`contacto_nombre`,
                    r.`contacto_tipo_documento`,
                    r.`contacto_numero_documento`,
                    r.`contacto_telefono`,
                    r.`contacto_email`,
                    r.`notas_operativas`,
                    r.`version_bloqueo`,
                    r.`creado_en`,
                    v.`correlativo` AS venta_correlativo,
                    v.`total` AS venta_total,
                    v.`moneda` AS venta_moneda,
                    (SELECT COUNT(*) FROM `reserva_prestaciones` rp WHERE rp.`reserva_id` = r.`id`) AS total_prestaciones,
                    (SELECT COUNT(*) FROM `reserva_prestaciones` rp WHERE rp.`reserva_id` = r.`id` AND rp.`estado_agendamiento` = 'PROGRAMADA') AS prestaciones_programadas,
                    (SELECT COUNT(*) FROM `reserva_prestaciones` rp WHERE rp.`reserva_id` = r.`id` AND rp.`estado_agendamiento` = 'PENDIENTE_PROGRAMAR') AS prestaciones_pendientes,
                    (SELECT COUNT(*) FROM `reserva_participantes` rpart WHERE rpart.`reserva_id` = r.`id`) AS total_participantes
                FROM `reservas` r
                INNER JOIN `ventas` v ON v.`id` = r.`venta_id`
                WHERE r.`organizacion_id` = :org_id
            ";

            $params = ['org_id' => $orgId];

            if ($edicionId !== null) {
                $sql .= " AND r.`edicion_id` = :edicion_id";
                $params['edicion_id'] = $edicionId;
            }

            if ($estado !== null && $estado !== '') {
                $sql .= " AND r.`estado` = :estado";
                $params['estado'] = $estado;
            }

            if ($clienteId !== null) {
                $sql .= " AND r.`cliente_id` = :cliente_id";
                $params['cliente_id'] = $clienteId;
            }

            if ($correlativo !== null && $correlativo !== '') {
                $sql .= " AND r.`correlativo` LIKE :correlativo";
                $params['correlativo'] = '%' . $correlativo . '%';
            }

            if ($fechaDesde !== null && $fechaDesde !== '') {
                $sql .= " AND DATE(r.`creado_en`) >= :fecha_desde";
                $params['fecha_desde'] = $fechaDesde;
            }

            if ($fechaHasta !== null && $fechaHasta !== '') {
                $sql .= " AND DATE(r.`creado_en`) <= :fecha_hasta";
                $params['fecha_hasta'] = $fechaHasta;
            }

            if ($programacion === 'PENDIENTE') {
                $sql .= " AND EXISTS (SELECT 1 FROM `reserva_prestaciones` rp WHERE rp.`reserva_id` = r.`id` AND rp.`estado_agendamiento` = 'PENDIENTE_PROGRAMAR')";
            } elseif ($programacion === 'PROGRAMADA') {
                $sql .= " AND NOT EXISTS (SELECT 1 FROM `reserva_prestaciones` rp WHERE rp.`reserva_id` = r.`id` AND rp.`estado_agendamiento` = 'PENDIENTE_PROGRAMAR')";
            }

            if ($busqueda !== null && $busqueda !== '') {
                $sql .= " AND (
                    r.`correlativo` LIKE :busq 
                    OR r.`contacto_nombre` LIKE :busq 
                    OR r.`contacto_numero_documento` LIKE :busq 
                    OR v.`correlativo` LIKE :busq
                )";
                $params['busq'] = '%' . $busqueda . '%';
            }

            $sql .= " ORDER BY r.`id` DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Reservas recuperadas con éxito.', [
                'reservas' => $reservas,
                'total'    => count($reservas),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/reservas/{id}
     * Detalle 360 completo de la reserva.
     */
    public function detalle(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('reservas.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso reservas.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $reserva = $this->reservaRepo->buscarPorId($id);

            if ($reserva === null || $reserva->organizacionId !== $orgId) {
                return $this->responderJson(false, 404, "La reserva #{$id} no existe en su organización.");
            }

            // Venta origen
            $stmtVta = $this->pdo->prepare("
                SELECT id, correlativo, fecha_venta, subtotal, total, moneda, estado, cliente_nombre_completo
                FROM `ventas`
                WHERE `id` = :venta_id
            ");
            $stmtVta->execute(['venta_id' => $reserva->ventaId]);
            $venta = $stmtVta->fetch(PDO::FETCH_ASSOC);

            // Prestaciones con reprogramaciones
            $prestacionesData = [];
            foreach ($reserva->prestaciones as $p) {
                $reprogList = $this->reservaRepo->obtenerReprogramacionesPorPrestacion((int) $p->id);
                $stmtPartPrest = $this->pdo->prepare("
                    SELECT pp.asiento_asignado, rp.id AS participante_id, rp.nombres, rp.apellidos, rp.numero_documento
                    FROM `prestacion_participantes` pp
                    INNER JOIN `reserva_participantes` rp ON rp.id = pp.participante_id
                    WHERE pp.prestacion_id = :prest_id
                ");
                $stmtPartPrest->execute(['prest_id' => $p->id]);
                $partAsignados = $stmtPartPrest->fetchAll(PDO::FETCH_ASSOC);

                // Salida activa asignada si existe
                $stmtSalida = $this->pdo->prepare("
                    SELECT os.id, os.correlativo, os.titulo, os.fecha_salida, os.hora_salida, os.estado, os.punto_encuentro
                    FROM `operacion_salida_prestaciones` osp
                    INNER JOIN `operacion_salidas` os ON os.id = osp.salida_id
                    WHERE osp.prestacion_id = :prest_id AND os.estado <> 'CANCELADA'
                    LIMIT 1
                ");
                $stmtSalida->execute(['prest_id' => $p->id]);
                $salidaAsignada = $stmtSalida->fetch(PDO::FETCH_ASSOC) ?: null;

                $prestacionesData[] = [
                    'id'                     => $p->id,
                    'concepto_codigo'        => $p->conceptoCodigo,
                    'concepto_nombre'        => $p->conceptoNombre,
                    'unidad_medida'          => $p->unidadMedida,
                    'cantidad'               => $p->cantidad,
                    'tipo_capacidad'         => $p->tipoCapacidad->value,
                    'requiere_agendamiento'  => $p->requiereAgendamiento,
                    'requiere_participantes' => $p->requiereParticipantes,
                    'estado_agendamiento'    => $p->estadoAgendamiento->value,
                    'fecha_servicio'         => $p->fechaServicio,
                    'hora_servicio'          => $p->horaServicio,
                    'punto_encuentro'        => $p->puntoEncuentro,
                    'notas_prestacion'       => $p->notasPrestacion,
                    'version_bloqueo'        => $p->versionBloqueo,
                    'participantes'          => $partAsignados,
                    'salida_asignada'        => $salidaAsignada,
                    'reprogramaciones'       => array_map(fn($r) => [
                        'id'               => $r->id,
                        'fecha_anterior'   => $r->fechaAnterior,
                        'fecha_nueva'      => $r->fechaNueva,
                        'hora_anterior'    => $r->horaAnterior,
                        'hora_nueva'       => $r->horaNueva,
                        'motivo_categoria' => $r->motivoCategoria->value,
                        'motivo_detalle'   => $r->motivoDetalle,
                        'creado_en'        => $r->creadoEn,
                    ], $reprogList),
                ];
            }

            // Participantes de la reserva
            $participantesData = array_map(fn($part) => [
                'id'                             => $part->id,
                'persona_id'                     => $part->personaId,
                'tipo_documento_id'              => $part->tipoDocumentoId,
                'numero_documento'               => $part->numeroDocumento,
                'nombres'                        => $part->nombres,
                'apellidos'                      => $part->apellidos,
                'nacionalidad'                   => $part->nacionalidad,
                'rango_etario'                   => $part->rangoEtario?->value,
                'telefono_contacto'              => $part->telefonoContacto,
                'talla_indumentaria'             => $part->tallaIndumentaria,
                'requiere_asistencia_movilidad'  => $part->requiereAsistenciaMovilidad,
                'regimen_alimentario'            => $part->regimenAlimentario?->value,
                'es_titular_reserva'             => $part->esTitularReserva,
            ], $reserva->participantes);

            return $this->responderJson(true, 200, 'Detalle de reserva recuperado.', [
                'reserva' => [
                    'id'                        => $reserva->id,
                    'organizacion_id'           => $reserva->organizacionId,
                    'edicion_id'                => $reserva->edicionId,
                    'venta_id'                  => $reserva->ventaId,
                    'cliente_id'                => $reserva->clienteId,
                    'correlativo'               => $reserva->correlativo,
                    'estado'                    => $reserva->estado->value,
                    'contacto_nombre'           => $reserva->contactoNombre,
                    'contacto_tipo_documento'   => $reserva->contactoTipoDocumento,
                    'contacto_numero_documento'  => $reserva->contactoNumeroDocumento,
                    'contacto_telefono'         => $reserva->contactoTelefono,
                    'contacto_email'            => $reserva->contactoEmail,
                    'notas_operativas'          => $reserva->notasOperativas,
                    'version_bloqueo'           => $reserva->versionBloqueo,
                    'creado_en'                 => $reserva->creadoEn,
                    'actualizado_en'            => $reserva->actualizadoEn,
                    'venta'                     => $venta,
                    'prestaciones'              => $prestacionesData,
                    'participantes'             => $participantesData,
                ]
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/reservas/desde-venta/{ventaId}
     */
    public function formalizarDesdeVenta(int $ventaId): string
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
            $resultado = $this->reservaServicio->formalizarDesdeVenta($orgId, $ventaId, $contexto);

            return $this->responderJson(true, 201, 'Formalización completada exitosamente.', [
                'reserva' => $resultado['reserva'] ? [
                    'id'          => $resultado['reserva']->id,
                    'correlativo' => $resultado['reserva']->correlativo,
                    'estado'      => $resultado['reserva']->estado->value,
                ] : null,
                'entrega' => $resultado['entrega'] ? [
                    'id'          => $resultado['entrega']->id,
                    'correlativo' => $resultado['entrega']->correlativo,
                    'estado'      => $resultado['entrega']->estado->value,
                ] : null,
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
     * POST /api/v1/reservas/prestaciones/{prestacionId}/programar
     */
    public function programarPrestacion(int $prestacionId): string
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

        $fechaServicio = trim((string) ($cuerpo['fecha_servicio'] ?? ''));
        $horaServicio = !empty($cuerpo['hora_servicio']) ? trim((string) $cuerpo['hora_servicio']) : null;
        $puntoEncuentro = !empty($cuerpo['punto_encuentro']) ? trim((string) $cuerpo['punto_encuentro']) : null;

        if ($fechaServicio === '') {
            return $this->responderJson(false, 422, 'La fecha de servicio es obligatoria.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $prestacion = $this->reservaServicio->programarPrestacion(
                organizacionId: $orgId,
                prestacionId: $prestacionId,
                fechaServicio: $fechaServicio,
                horaServicio: $horaServicio,
                puntoEncuentro: $puntoEncuentro,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, 'Prestación programada exitosamente.', [
                'prestacion_id'       => $prestacion->id,
                'fecha_servicio'      => $prestacion->fechaServicio,
                'hora_servicio'       => $prestacion->horaServicio,
                'punto_encuentro'     => $prestacion->puntoEncuentro,
                'estado_agendamiento' => $prestacion->estadoAgendamiento->value,
                'version_bloqueo'     => $prestacion->versionBloqueo,
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
     * POST /api/v1/reservas/prestaciones/{prestacionId}/reprogramar
     */
    public function reprogramarPrestacion(int $prestacionId): string
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

        $nuevaFecha = trim((string) ($cuerpo['nueva_fecha'] ?? ''));
        $nuevaHora = !empty($cuerpo['nueva_hora']) ? trim((string) $cuerpo['nueva_hora']) : null;
        $motivoCatStr = trim((string) ($cuerpo['motivo_categoria'] ?? ''));
        $motivoDetalle = trim((string) ($cuerpo['motivo_detalle'] ?? ''));

        if ($nuevaFecha === '' || $motivoCatStr === '' || $motivoDetalle === '') {
            return $this->responderJson(false, 422, 'La nueva fecha, categoría del motivo y detalle explicativo son obligatorios.');
        }

        try {
            $motivoCat = MotivoReprogramacion::from($motivoCatStr);
        } catch (Throwable) {
            return $this->responderJson(false, 422, "Categoría de motivo inválida ('{$motivoCatStr}').");
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $prestacion = $this->reservaServicio->reprogramarPrestacion(
                organizacionId: $orgId,
                prestacionId: $prestacionId,
                nuevaFecha: $nuevaFecha,
                nuevaHora: $nuevaHora,
                motivoCategoria: $motivoCat,
                motivoDetalle: $motivoDetalle,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, 'Prestación reprogramada exitosamente.', [
                'prestacion_id'       => $prestacion->id,
                'fecha_servicio'      => $prestacion->fechaServicio,
                'hora_servicio'       => $prestacion->horaServicio,
                'estado_agendamiento' => $prestacion->estadoAgendamiento->value,
                'version_bloqueo'     => $prestacion->versionBloqueo,
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
     * GET /api/v1/reservas/prestaciones/{prestacionId}/reprogramaciones
     */
    public function obtenerReprogramaciones(int $prestacionId): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('reservas.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso reservas.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $lista = $this->reservaServicio->obtenerReprogramacionesPrestacion($orgId, $prestacionId, $contexto);

            return $this->responderJson(true, 200, 'Historial de reprogramaciones recuperado.', [
                'reprogramaciones' => array_map(fn($r) => [
                    'id'               => $r->id,
                    'fecha_anterior'   => $r->fechaAnterior,
                    'fecha_nueva'      => $r->fechaNueva,
                    'hora_anterior'    => $r->horaAnterior,
                    'hora_nueva'       => $r->horaNueva,
                    'motivo_categoria' => $r->motivoCategoria->value,
                    'motivo_detalle'   => $r->motivoDetalle,
                    'creado_por'       => $r->creadoPor,
                    'creado_en'        => $r->creadoEn,
                ], $lista)
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
     * POST /api/v1/reservas/{reservaId}/participantes
     */
    public function registrarParticipante(int $reservaId): string
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
            $prestacionIds = isset($cuerpo['prestacion_ids']) && is_array($cuerpo['prestacion_ids'])
                ? array_map('intval', $cuerpo['prestacion_ids'])
                : [];

            $guardado = $this->reservaServicio->registrarParticipante(
                organizacionId: $orgId,
                reservaId: $reservaId,
                datos: $cuerpo,
                prestacionIds: $prestacionIds,
                contexto: $contexto
            );

            return $this->responderJson(true, 201, 'Participante registrado exitosamente.', [
                'participante' => [
                    'id'                => $guardado->id,
                    'reserva_id'        => $guardado->reservaId,
                    'nombres'           => $guardado->nombres,
                    'apellidos'         => $guardado->apellidos,
                    'numero_documento'  => $guardado->numeroDocumento,
                    'nacionalidad'      => $guardado->nacionalidad,
                    'rango_etario'      => $guardado->rangoEtario?->value,
                    'regimen_alimentario' => $guardado->regimenAlimentario?->value,
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
     * DELETE /api/v1/reservas/{reservaId}/participantes/{participanteId}
     */
    public function eliminarParticipante(int $reservaId, int $participanteId): string
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
            $this->reservaServicio->eliminarParticipante($orgId, $reservaId, $participanteId, $contexto);

            return $this->responderJson(true, 200, 'Participante eliminado de la reserva exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/reservas/{id}/cancelar
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

        $motivo = trim((string) ($cuerpo['motivo'] ?? ''));
        if ($motivo === '') {
            return $this->responderJson(false, 422, 'Debe especificar el motivo de cancelación de la reserva.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $cancelada = $this->reservaServicio->cancelarReserva($orgId, $id, $motivo, $contexto);

            return $this->responderJson(true, 200, 'Reserva cancelada exitosamente.', [
                'reserva_id' => $cancelada->id,
                'estado'     => $cancelada->estado->value,
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
     * GET /api/v1/reservas/aux/ventas-confirmadas
     * Lista ventas confirmadas que aún no tienen una reserva formalizada.
     */
    public function auxVentasConfirmadas(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        $edicionId = $this->resolverEdicionContextual($contexto);

        try {
            $orgId = (int) $contexto->organizacionId;
            $sql = "
                SELECT v.id, v.correlativo, v.fecha_venta, v.cliente_nombre_completo, v.total, v.moneda
                FROM `ventas` v
                WHERE v.organizacion_id = :org_id
                  AND v.estado = 'CONFIRMADA'
                  AND v.id NOT IN (SELECT r.venta_id FROM `reservas` r WHERE r.organizacion_id = :org_id)
            ";
            $params = ['org_id' => $orgId];

            if ($edicionId !== null) {
                $sql .= " AND v.edicion_id = :edicion_id";
                $params['edicion_id'] = $edicionId;
            }

            $sql .= " ORDER BY v.id DESC LIMIT 50";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Ventas confirmadas disponibles.', [
                'ventas' => $ventas
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/reservas/aux/tipos-documento
     */
    public function auxTiposDocumento(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        try {
            $stmt = $this->pdo->query("SELECT id, codigo, nombre FROM `tipos_documento` ORDER BY id ASC");
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Tipos de documento recuperados.', [
                'tipos' => $tipos
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
        error_log("[ReservaControlador Error] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        return $this->responderJson(false, 500, 'Ha ocurrido un error inesperado al procesar la solicitud en reservas.', (object) [], ['error' => 'ERROR_INTERNO']);
    }
}
