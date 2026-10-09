<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Comunicaciones\CategoriaPlantilla;
use Aplicacion\Comunicaciones\ComunicacionServicio;
use Aplicacion\Comunicaciones\EstadoConsentimiento;
use Aplicacion\Comunicaciones\EstadoPlantillaMeta;
use Aplicacion\Comunicaciones\FinalidadConsentimiento;
use Aplicacion\Comunicaciones\ModoComunicacion;
use Aplicacion\Comunicaciones\OutboxWorker;
use Aplicacion\Comunicaciones\WebhookWhatsAppServicio;
use Aplicacion\Entidades\ComunicacionPlantilla;
use Aplicacion\Repositorios\ComunicacionCampanaRepositorio;
use Aplicacion\Repositorios\ComunicacionConfigRepositorio;
use Aplicacion\Repositorios\ComunicacionConsentimientoRepositorio;
use Aplicacion\Repositorios\ComunicacionConversacionRepositorio;
use Aplicacion\Repositorios\ComunicacionMensajeRepositorio;
use Aplicacion\Repositorios\ComunicacionPlantillaRepositorio;
use Aplicacion\Repositorios\ComunicacionWebhookRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Excepciones\AccesoDenegadoExcepcion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Http\Vista;
use PDO;
use Throwable;

class ComunicacionControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private ComunicacionServicio $comunicacionServicio;
    private WebhookWhatsAppServicio $webhookServicio;
    private ComunicacionMensajeRepositorio $mensajeRepo;
    private ComunicacionPlantillaRepositorio $plantillaRepo;
    private ComunicacionConversacionRepositorio $conversacionRepo;
    private ComunicacionConsentimientoRepositorio $consentimientoRepo;
    private ComunicacionConfigRepositorio $configRepo;
    private ComunicacionCampanaRepositorio $campanaRepo;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?ComunicacionServicio $comunicacionServicio = null,
        ?WebhookWhatsAppServicio $webhookServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();

        $this->mensajeRepo = new ComunicacionMensajeRepositorio($this->pdo);
        $this->plantillaRepo = new ComunicacionPlantillaRepositorio($this->pdo);
        $this->conversacionRepo = new ComunicacionConversacionRepositorio($this->pdo);
        $this->consentimientoRepo = new ComunicacionConsentimientoRepositorio($this->pdo);
        $this->configRepo = new ComunicacionConfigRepositorio($this->pdo);
        $this->campanaRepo = new ComunicacionCampanaRepositorio($this->pdo);

        $this->comunicacionServicio = $comunicacionServicio ?? new ComunicacionServicio(
            mensajeRepo: $this->mensajeRepo,
            plantillaRepo: $this->plantillaRepo,
            consentimientoRepo: $this->consentimientoRepo,
            conversacionRepo: $this->conversacionRepo,
            configRepo: $this->configRepo
        );

        $this->webhookServicio = $webhookServicio ?? new WebhookWhatsAppServicio(
            configRepo: $this->configRepo,
            webhookRepo: new ComunicacionWebhookRepositorio($this->pdo),
            mensajeRepo: $this->mensajeRepo,
            conversacionRepo: $this->conversacionRepo,
            consentimientoRepo: $this->consentimientoRepo,
            fabricaProveedores: new \Aplicacion\Comunicaciones\Proveedores\FabricaProveedorWhatsApp()
        );
    }

    private function setCodigoHttp(int $codigo): void
    {
        if (!headers_sent()) {
            http_response_code($codigo);
        }
    }

    // =========================================================================
    // 0. VISTA WEB OFICIAL (ALINA UI - F2.8C)
    // =========================================================================

    /**
     * GET /comunicaciones
     * Renderiza la interfaz administrativa oficial de Comunicaciones y WhatsApp.
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('comunicaciones.ver', $contexto, false)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Comunicaciones y WhatsApp',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (comunicaciones.ver) para consultar el módulo de mensajería.'
            ], 'principal');
        }

        $permisos = [
            'ver'                     => true,
            'enviarIndividual'        => $this->authzMiddleware->verificarPermiso('comunicaciones.enviar_individual', $contexto, false),
            'gestionarCampanas'       => $this->authzMiddleware->verificarPermiso('comunicaciones.gestionar_campanas', $contexto, false),
            'aprobarCampanas'         => $this->authzMiddleware->verificarPermiso('comunicaciones.aprobar_campanas', $contexto, false),
            'gestionarPlantillas'     => $this->authzMiddleware->verificarPermiso('comunicaciones.gestionar_plantillas', $contexto, false),
            'configurarProveedor'     => $this->authzMiddleware->verificarPermiso('comunicaciones.configurar_proveedor', $contexto, false),
            'gestionarConsentimientos' => $this->authzMiddleware->verificarPermiso('comunicaciones.gestionar_consentimientos', $contexto, false),
        ];

        return Vista::renderizar('comunicaciones/index', [
            'titulo'          => 'Comunicaciones & WhatsApp | CandelariaAPP',
            'subtitulo'       => 'Bandeja Multicanal, Plantillas Meta, Outbox Transaccional y Campañas Seguras',
            'tituloSeccion'   => 'Comunicaciones & WhatsApp',
            'seccionActiva'   => 'comunicaciones',
            'permisos'        => $permisos,
            'scriptAdicional' => url_base('publico/js/comunicaciones.js')
        ], 'principal');
    }

    /**
     * Handshake de verificación de Meta: GET /api/v1/webhooks/whatsapp
     */
    public function webhookChallenge(): string
    {
        $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
        $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
        $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';
        $orgId = isset($_GET['org_id']) ? (int) $_GET['org_id'] : 1; // Default tenant 1 si no se envía subruta

        $res = $this->webhookServicio->procesarChallenge($orgId, (string) $mode, (string) $token, (string) $challenge);
        if ($res !== null) {
            $this->setCodigoHttp(200);
            return $res;
        }

        $this->setCodigoHttp(403);
        return 'Acceso denegado: Token de verificación de webhook inválido';
    }

    /**
     * Recepción de eventos de Meta: POST /api/v1/webhooks/whatsapp
     */
    public function webhookPayload(): string
    {
        header('Content-Type: application/json; charset=utf-8');
        $orgId = isset($_GET['org_id']) ? (int) $_GET['org_id'] : 1;

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || $rawBody === '') {
            $this->setCodigoHttp(400);
            return json_encode(['exito' => false, 'error' => 'Cuerpo de petición vacío']);
        }

        $firma = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? $_SERVER['X-Hub-Signature-256'] ?? '';

        try {
            $resultado = $this->webhookServicio->procesarPayload($orgId, $rawBody, (string) $firma);
            $this->setCodigoHttp(200);
            return json_encode($resultado);
        } catch (Throwable $e) {
            $this->setCodigoHttp(401);
            return json_encode([
                'exito' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Listar mensajes del tenant: GET /api/v1/comunicaciones/mensajes
     */
    public function listarMensajes(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.ver');

        $porPagina = max(1, min(100, (int) ($_GET['por_pagina'] ?? 20)));
        $estado = $_GET['estado'] ?? null;

        $sql = "SELECT m.*, p.nombre AS plantilla_nombre 
                FROM comunicacion_mensajes m 
                LEFT JOIN comunicacion_plantillas p ON p.id = m.plantilla_id 
                WHERE m.organizacion_id = :org_id";
        $params = ['org_id' => $contexto->organizacionId];

        if (!empty($estado)) {
            $sql .= " AND m.estado = :est";
            $params['est'] = $estado;
        }

        $sql .= " ORDER BY m.id DESC LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json_encode([
            'exito' => true,
            'datos' => [
                'mensajes' => $filas,
                'total'    => count($filas)
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Enviar mensaje transaccional: POST /api/v1/comunicaciones/mensajes/enviar
     */
    public function enviarMensajeTransaccional(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.enviar_individual');

        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $nombre = trim((string) ($_POST['nombre'] ?? 'Cliente'));
        $plantilla = trim((string) ($_POST['plantilla'] ?? ''));
        $idioma = trim((string) ($_POST['idioma'] ?? 'es_PE'));
        $parametros = isset($_POST['parametros']) && is_array($_POST['parametros']) ? $_POST['parametros'] : [];
        $evento = trim((string) ($_POST['evento'] ?? 'MANUAL_OPERADOR'));
        $entidadId = trim((string) ($_POST['entidad_id'] ?? bin2hex(random_bytes(8))));
        $clienteId = !empty($_POST['cliente_id']) ? (int) $_POST['cliente_id'] : null;

        if (empty($telefono) || empty($plantilla)) {
            $this->setCodigoHttp(422);
            return json_encode(['exito' => false, 'error' => 'Teléfono y plantilla son obligatorios']);
        }

        try {
            $mensaje = $this->comunicacionServicio->encolarMensajeTransaccional(
                orgId: $contexto->organizacionId,
                telefonoDestino: $telefono,
                nombreDestinatario: $nombre,
                nombrePlantilla: $plantilla,
                idioma: $idioma,
                parametros: $parametros,
                eventoOrigen: $evento,
                entidadOrigenId: $entidadId,
                clienteId: $clienteId,
                contexto: $contexto
            );

            $this->setCodigoHttp(201);
            return json_encode([
                'exito'   => true,
                'mensaje' => 'Mensaje transaccional encolado exitosamente en Outbox',
                'datos'   => $mensaje->aArreglo()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            $this->setCodigoHttp(400);
            return json_encode(['exito' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Responder en conversación activa: POST /api/v1/comunicaciones/conversaciones/{id}/responder
     */
    public function responderConversacion(int $conversacionId): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.enviar_individual');

        $texto = trim((string) ($_POST['texto'] ?? ''));
        if ($texto === '') {
            $this->setCodigoHttp(422);
            return json_encode(['exito' => false, 'error' => 'El texto de la respuesta es obligatorio']);
        }

        try {
            $mensaje = $this->comunicacionServicio->encolarRespuestaOperador(
                orgId: $contexto->organizacionId,
                conversacionId: $conversacionId,
                textoLibre: $texto,
                contexto: $contexto
            );

            $this->setCodigoHttp(201);
            return json_encode([
                'exito'   => true,
                'mensaje' => 'Respuesta encolada en ventana de atención',
                'datos'   => $mensaje->aArreglo()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            $this->setCodigoHttp(400);
            return json_encode(['exito' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Registrar consentimiento: POST /api/v1/comunicaciones/consentimientos
     */
    public function gestionarConsentimiento(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.gestionar_consentimientos');

        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $finalidadStr = trim((string) ($_POST['finalidad'] ?? 'TRANSACCIONAL_OPERATIVO'));
        $estadoStr = trim((string) ($_POST['estado'] ?? 'CONCEDIDO'));
        $origen = trim((string) ($_POST['origen'] ?? 'WEB_FORMULARIO'));
        $clausula = trim((string) ($_POST['clausula'] ?? 'Aceptación expresa de comunicaciones WhatsApp'));

        if ($clienteId <= 0 || empty($telefono)) {
            $this->setCodigoHttp(422);
            return json_encode(['exito' => false, 'error' => 'cliente_id y telefono son obligatorios']);
        }

        try {
            $finalidad = FinalidadConsentimiento::from($finalidadStr);
            $estado = EstadoConsentimiento::from($estadoStr);

            $c = $this->comunicacionServicio->gestionarConsentimiento(
                orgId: $contexto->organizacionId,
                clienteId: $clienteId,
                telefonoDestino: $telefono,
                finalidad: $finalidad,
                estado: $estado,
                origenEvidencia: $origen,
                textoClausula: $clausula,
                contexto: $contexto
            );

            $this->setCodigoHttp(201);
            return json_encode([
                'exito' => true,
                'datos' => $c->aArreglo()
            ]);
        } catch (Throwable $e) {
            $this->setCodigoHttp(400);
            return json_encode(['exito' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Configuración del canal: GET /api/v1/comunicaciones/configuracion
     */
    public function obtenerConfiguracion(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.configurar_proveedor');

        $cfg = $this->configRepo->obtenerPorOrganizacion($contexto->organizacionId);
        if (!$cfg) {
            return json_encode(['exito' => true, 'datos' => null]);
        }

        return json_encode(['exito' => true, 'datos' => $cfg->aArreglo()]);
    }

    /**
     * Guardar configuración del canal: POST /api/v1/comunicaciones/configuracion
     */
    public function guardarConfiguracion(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.configurar_proveedor');

        $proveedor = trim((string) ($_POST['proveedor_codigo'] ?? 'SIMULADOR_SANDBOX'));
        $modoStr = trim((string) ($_POST['modo'] ?? 'SIMULADOR'));
        $telefono = trim((string) ($_POST['telefono'] ?? '+51999888777'));
        $verifyToken = trim((string) ($_POST['webhook_verify_token'] ?? 'candelaria_webhook_token_2026'));
        $phoneId = !empty($_POST['meta_phone_number_id']) ? (string) $_POST['meta_phone_number_id'] : null;
        $wabaId = !empty($_POST['meta_waba_id']) ? (string) $_POST['meta_waba_id'] : null;
        $tokenAcceso = !empty($_POST['token_acceso']) ? (string) $_POST['token_acceso'] : null;
        $secret = !empty($_POST['webhook_secret']) ? (string) $_POST['webhook_secret'] : null;
        $presupuesto = isset($_POST['presupuesto_mensual_limite_usd']) ? (float) $_POST['presupuesto_mensual_limite_usd'] : 50.00;

        try {
            $modo = ModoComunicacion::from($modoStr);

            if ($modo === ModoComunicacion::PRODUCCION) {
                if ($proveedor === 'SIMULADOR_SANDBOX') {
                    throw new \InvalidArgumentException('El modo PRODUCCION es incompatible con el proveedor SIMULADOR_SANDBOX.');
                }
                if (empty($phoneId) || empty($tokenAcceso) || empty($secret)) {
                    throw new \InvalidArgumentException('El modo PRODUCCION exige credenciales completas de Meta Cloud API (Phone Number ID, Token de Acceso y App Secret).');
                }
                $produccionHabilitada = getenv('WHATSAPP_PRODUCCION_HABILITADA') === 'true';
                if (!$produccionHabilitada) {
                    throw new \DomainException('La activación de WhatsApp en modo PRODUCCION está deshabilitada por directiva institucional en este entorno. Opere exclusivamente en SIMULADOR.');
                }
            }

            $cfg = $this->configRepo->guardar(
                orgId: $contexto->organizacionId,
                proveedorCodigo: $proveedor,
                modo: $modo,
                numeroTelefonoIdentificador: $telefono,
                webhookVerifyToken: $verifyToken,
                metaPhoneNumberId: $phoneId,
                metaWabaId: $wabaId,
                tokenAccesoPlano: $tokenAcceso,
                webhookSecretPlano: $secret,
                presupuestoMensualLimite: $presupuesto
            );

            return json_encode(['exito' => true, 'datos' => $cfg->aArreglo()]);
        } catch (Throwable $e) {
            $this->setCodigoHttp(400);
            return json_encode(['exito' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/v1/comunicaciones/conversaciones
     */
    public function listarConversaciones(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.ver');

        $estado = !empty($_GET['estado']) ? (string) $_GET['estado'] : null;
        $busqueda = !empty($_GET['busqueda']) ? (string) $_GET['busqueda'] : null;
        $limite = max(1, min(100, (int) ($_GET['limite'] ?? 50)));

        $conversaciones = $this->conversacionRepo->listarConDetalles($contexto->organizacionId, $estado, $busqueda, $limite);

        return json_encode([
            'exito' => true,
            'datos' => $conversaciones
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/v1/comunicaciones/conversaciones/{id}/mensajes
     */
    public function obtenerMensajesConversacion(int $conversacionId): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.ver');

        $conversacion = $this->conversacionRepo->buscarPorId($conversacionId, $contexto->organizacionId);
        if (!$conversacion) {
            $this->setCodigoHttp(404);
            return json_encode(['exito' => false, 'error' => 'Conversación no encontrada']);
        }

        $mensajes = $this->conversacionRepo->buscarMensajesPorConversacion($conversacionId, $contexto->organizacionId);

        return json_encode([
            'exito' => true,
            'datos' => [
                'conversacion' => $conversacion->aArreglo(),
                'mensajes'     => $mensajes
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/v1/comunicaciones/conversaciones/{id}/cerrar
     */
    public function cerrarConversacion(int $conversacionId): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.enviar_individual');

        $conversacion = $this->conversacionRepo->buscarPorId($conversacionId, $contexto->organizacionId);
        if (!$conversacion) {
            $this->setCodigoHttp(404);
            return json_encode(['exito' => false, 'error' => 'Conversación no encontrada']);
        }

        $this->conversacionRepo->cerrar($conversacionId, $contexto->organizacionId);

        return json_encode([
            'exito'   => true,
            'mensaje' => 'Conversación cerrada exitosamente'
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/v1/comunicaciones/conversaciones/{id}/asignar
     */
    public function asignarOperadorConversacion(int $conversacionId): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.enviar_individual');

        $operadorId = isset($_POST['operador_id']) ? (int) $_POST['operador_id'] : ($contexto->usuarioId ?? 1);

        $conversacion = $this->conversacionRepo->buscarPorId($conversacionId, $contexto->organizacionId);
        if (!$conversacion) {
            $this->setCodigoHttp(404);
            return json_encode(['exito' => false, 'error' => 'Conversación no encontrada']);
        }

        $this->conversacionRepo->asignarOperador($conversacionId, $contexto->organizacionId, $operadorId);

        return json_encode([
            'exito'   => true,
            'mensaje' => 'Operador asignado a la conversación'
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/v1/comunicaciones/plantillas
     */
    public function listarPlantillas(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.ver');

        $plantillas = $this->plantillaRepo->listar($contexto->organizacionId);
        $datos = array_map(fn($p) => $p->aArreglo(), $plantillas);

        return json_encode([
            'exito' => true,
            'datos' => $datos
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/v1/comunicaciones/plantillas
     */
    public function crearPlantilla(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.gestionar_plantillas');

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $idioma = trim((string) ($_POST['idioma'] ?? 'es_PE'));
        $categoriaStr = trim((string) ($_POST['categoria'] ?? 'UTILITY'));
        $cuerpo = trim((string) ($_POST['cuerpo_texto'] ?? ''));
        $encabezado = trim((string) ($_POST['encabezado_tipo'] ?? 'NINGUNO'));
        $pie = !empty($_POST['pie_texto']) ? trim((string) $_POST['pie_texto']) : null;
        $metaId = !empty($_POST['meta_template_id']) ? trim((string) $_POST['meta_template_id']) : null;
        $parametros = isset($_POST['parametros']) && is_array($_POST['parametros']) ? $_POST['parametros'] : [];

        if (empty($nombre) || empty($cuerpo)) {
            $this->setCodigoHttp(422);
            return json_encode(['exito' => false, 'error' => 'Nombre y cuerpo de plantilla son obligatorios']);
        }

        try {
            $categoria = CategoriaPlantilla::from($categoriaStr);
            $plantilla = new ComunicacionPlantilla(
                id: null,
                organizacionId: $contexto->organizacionId,
                nombre: $nombre,
                idioma: $idioma,
                categoria: $categoria,
                cuerpoTexto: $cuerpo,
                parametrosMapeoJson: $parametros,
                estadoMeta: EstadoPlantillaMeta::APPROVED,
                metaTemplateId: $metaId,
                encabezadoTipo: $encabezado,
                pieTexto: $pie,
                versionLocal: 1,
                activo: true
            );

            $guardada = $this->plantillaRepo->guardar($plantilla);

            return json_encode([
                'exito'   => true,
                'mensaje' => 'Plantilla guardada con éxito',
                'datos'   => $guardada->aArreglo()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            $this->setCodigoHttp(400);
            return json_encode(['exito' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/v1/comunicaciones/consentimientos
     */
    public function listarConsentimientos(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.ver');

        $canal = !empty($_GET['canal']) ? (string) $_GET['canal'] : null;
        $finalidad = !empty($_GET['finalidad']) ? (string) $_GET['finalidad'] : null;
        $estado = !empty($_GET['estado']) ? (string) $_GET['estado'] : null;
        $limite = max(1, min(100, (int) ($_GET['limite'] ?? 100)));

        $datos = $this->consentimientoRepo->listarConDetalles(
            $contexto->organizacionId,
            $canal,
            $finalidad,
            $estado,
            $limite
        );

        return json_encode([
            'exito' => true,
            'datos' => $datos
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/v1/comunicaciones/kpis
     */
    public function obtenerKpis(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.ver');

        $kpis = $this->mensajeRepo->obtenerKpis($contexto->organizacionId);
        $cfg = $this->configRepo->obtenerPorOrganizacion($contexto->organizacionId);

        $kpis['presupuesto_mensual_limite_usd'] = $cfg?->presupuestoMensualLimiteUsd ?? 50.00;
        $kpis['proveedor_codigo'] = $cfg?->proveedorCodigo ?? 'SIMULADOR_SANDBOX';
        $kpis['modo'] = $cfg?->modo->value ?? 'SIMULADOR';
        $kpis['numero_telefono'] = $cfg?->numeroTelefonoIdentificador ?? '+51999888777';

        return json_encode([
            'exito' => true,
            'datos' => $kpis
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/v1/comunicaciones/mensajes/{id}/intentos
     */
    public function obtenerIntentosMensaje(int $mensajeId): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.ver');

        $mensaje = $this->mensajeRepo->buscarPorId($mensajeId, $contexto->organizacionId);
        if (!$mensaje) {
            $this->setCodigoHttp(404);
            return json_encode(['exito' => false, 'error' => 'Mensaje no encontrado']);
        }

        $intentos = $this->mensajeRepo->obtenerIntentos($mensajeId);

        return json_encode([
            'exito' => true,
            'datos' => [
                'mensaje'  => $mensaje->aArreglo(),
                'intentos' => $intentos
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/v1/comunicaciones/simulador/recibir
     * Simula la recepción de un mensaje entrante de un cliente en Sandbox.
     */
    public function simularMensajeEntrante(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.enviar_individual');

        $telefono = trim((string) ($_POST['telefono'] ?? '+51999111222'));
        $nombre = trim((string) ($_POST['nombre'] ?? 'Cliente Sandbox'));
        $texto = trim((string) ($_POST['texto'] ?? 'Hola CandelariaAPP, consulta de prueba en sandbox.'));
        
        $clienteId = !empty($_POST['cliente_id']) ? (int) $_POST['cliente_id'] : null;
        if ($clienteId === null) {
            $stmtCli = $this->pdo->prepare("SELECT id FROM clientes WHERE organizacion_id = :org_id LIMIT 1");
            $stmtCli->execute(['org_id' => $contexto->organizacionId]);
            $cliIdDb = $stmtCli->fetchColumn();
            if ($cliIdDb !== false) {
                $clienteId = (int) $cliIdDb;
            } else {
                $stmtAny = $this->pdo->query("SELECT id, organizacion_id FROM clientes LIMIT 1");
                $any = $stmtAny ? $stmtAny->fetch(PDO::FETCH_ASSOC) : null;
                $clienteId = $any ? (int) $any['id'] : 1;
            }
        }

        if (empty($telefono) || empty($texto)) {
            $this->setCodigoHttp(422);
            return json_encode(['exito' => false, 'error' => 'Teléfono y texto son obligatorios']);
        }

        try {
            $timestamp = date('Y-m-d H:i:s');
            // 1. Obtener o crear conversación abierta
            $conversacion = $this->conversacionRepo->obtenerOCrear($contexto->organizacionId, $clienteId, $telefono);
            // 2. Registrar mensaje entrante y abrir ventana 24h
            $this->conversacionRepo->registrarMensajeEntrante($conversacion->id, $contexto->organizacionId, $timestamp);

            // 3. Crear registro de mensaje entrante en historial
            $msgEntrante = new \Aplicacion\Entidades\ComunicacionMensaje(
                id: null,
                organizacionId: $contexto->organizacionId,
                tipoMensaje: \Aplicacion\Comunicaciones\TipoMensaje::ENTRANTE_CLIENTE,
                direccion: 'ENTRANTE',
                canal: 'WHATSAPP',
                destinatarioTelefono: $telefono,
                destinatarioNombre: $nombre,
                contenidoTexto: $texto,
                idempotencyKey: 'sim_in_' . bin2hex(random_bytes(8)),
                correlacionId: 'corr_' . bin2hex(random_bytes(8)),
                estado: \Aplicacion\Comunicaciones\EstadoMensaje::ENTREGADO,
                pesoEstado: 40,
                conversacionId: $conversacion->id,
                clienteId: $clienteId,
                costoCalculadoUsd: 0.0000,
                intentosRealizados: 1
            );
            $guardado = $this->mensajeRepo->guardar($msgEntrante);

            return json_encode([
                'exito'   => true,
                'mensaje' => 'Mensaje entrante simulado con éxito. Ventana de 24 horas activada.',
                'datos'   => [
                    'conversacion_id' => $conversacion->id,
                    'mensaje'         => $guardado->aArreglo()
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            $this->setCodigoHttp(400);
            return json_encode(['exito' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/v1/comunicaciones/outbox/procesar
     * Ejecuta una ronda del OutboxWorker desde la UI.
     */
    public function procesarOutboxManual(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.configurar_proveedor');

        $limite = min(20, max(1, (int) ($_POST['limite'] ?? 20)));

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lockKey = 'candelaria_outbox_manual_' . $contexto->organizacionId;
        $lockAdquirido = true;

        if ($driver === 'mysql') {
            $stmtLock = $this->pdo->prepare("SELECT GET_LOCK(:lock, 0) AS adquirido");
            $stmtLock->execute(['lock' => $lockKey]);
            $lockAdquirido = ((int) $stmtLock->fetchColumn()) === 1;
        }

        if (!$lockAdquirido) {
            $this->setCodigoHttp(409);
            return json_encode([
                'exito' => false,
                'error' => 'El procesador Outbox ya se encuentra en ejecución activa para esta organización. Intente en unos segundos.'
            ], JSON_UNESCAPED_UNICODE);
        }

        try {
            $tInicio = microtime(true);
            $worker = new OutboxWorker(
                mensajeRepo: $this->mensajeRepo,
                consentimientoRepo: $this->consentimientoRepo,
                plantillaRepo: $this->plantillaRepo,
                configRepo: $this->configRepo,
                fabricaProveedores: new \Aplicacion\Comunicaciones\Proveedores\FabricaProveedorWhatsApp()
            );

            $resultados = $worker->procesarLote(limite: $limite);
            $procesados = count($resultados);
            $tiempoMs = (int) round((microtime(true) - $tInicio) * 1000);

            // Registro inmutable de auditoría dual
            try {
                $auditoriaRepo = new \Aplicacion\Repositorios\AuditoriaRepositorio($this->pdo);
                $auditoriaRepo->registrar(
                    contexto: $contexto,
                    modulo: 'comunicaciones',
                    accion: 'EJECUTAR_OUTBOX_MANUAL',
                    entidadTipo: 'comunicacion_mensajes',
                    entidadId: 'lote_' . date('YmdHis'),
                    datosPrevios: null,
                    datosNuevos: [
                        'limite'              => $limite,
                        'procesados'          => $procesados,
                        'tiempo_ejecucion_ms' => $tiempoMs,
                        'ip'                  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ]
                );
            } catch (Throwable $auditError) {
                error_log("Aviso de auditoría outbox: " . $auditError->getMessage());
            }

            return json_encode([
                'exito'      => true,
                'mensaje'    => "Lote Outbox procesado exitosamente. {$procesados} mensaje(s) despachado(s) en {$tiempoMs}ms.",
                'procesados' => $procesados,
                'tiempo_ms'  => $tiempoMs
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            $this->setCodigoHttp(400);
            return json_encode(['exito' => false, 'error' => $e->getMessage()]);
        } finally {
            if ($driver === 'mysql' && $lockAdquirido) {
                $stmtRelease = $this->pdo->prepare("SELECT RELEASE_LOCK(:lock)");
                $stmtRelease->execute(['lock' => $lockKey]);
            }
        }
    }

    /**
     * GET /api/v1/comunicaciones/campanas
     */
    public function listarCampanas(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.ver');

        $estado = !empty($_GET['estado']) ? (string) $_GET['estado'] : null;
        $campanas = $this->campanaRepo->listar($contexto->organizacionId, $estado);

        return json_encode([
            'exito' => true,
            'datos' => $campanas
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/v1/comunicaciones/campanas
     */
    public function crearCampana(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.gestionar_campanas');

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $plantillaId = (int) ($_POST['plantilla_id'] ?? 0);
        $edicionId = !empty($_POST['edicion_id']) ? (int) $_POST['edicion_id'] : null;
        $presupuesto = isset($_POST['presupuesto_usd']) ? (float) $_POST['presupuesto_usd'] : 0.00;
        $programadaPara = !empty($_POST['programada_para']) ? trim((string) $_POST['programada_para']) : null;
        $criterios = isset($_POST['criterios']) && is_array($_POST['criterios']) ? $_POST['criterios'] : ['optin' => true];

        if (empty($nombre) || $plantillaId <= 0) {
            $this->setCodigoHttp(422);
            return json_encode(['exito' => false, 'error' => 'Nombre y plantilla son requeridos para la campaña']);
        }

        try {
            $campanaId = $this->campanaRepo->crear(
                orgId: $contexto->organizacionId,
                nombre: $nombre,
                plantillaId: $plantillaId,
                edicionId: $edicionId,
                criterios: $criterios,
                presupuestoUsd: $presupuesto,
                programadaPara: $programadaPara,
                creadorId: $contexto->usuarioId ?? 1
            );

            return json_encode([
                'exito'   => true,
                'mensaje' => 'Campaña registrada en estado BORRADOR. Pendiente de aprobación independiente (SoD).',
                'datos'   => ['id' => $campanaId]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            $this->setCodigoHttp(400);
            return json_encode(['exito' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/v1/comunicaciones/campanas/{id}/aprobar
     */
    public function aprobarCampana(int $id): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $contexto = $this->autenticarYAutorizar('comunicaciones.aprobar_campanas');

        try {
            $ok = $this->campanaRepo->aprobar($id, $contexto->organizacionId, $contexto->usuarioId ?? 2);
            if (!$ok) {
                $this->setCodigoHttp(400);
                return json_encode(['exito' => false, 'error' => 'No se pudo aprobar la campaña']);
            }

            return json_encode([
                'exito'   => true,
                'mensaje' => "Campaña #{$id} aprobada formalmente bajo cumplimiento SoD."
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            $this->setCodigoHttp(400);
            return json_encode(['exito' => false, 'error' => $e->getMessage()]);
        }
    }

    private function autenticarYAutorizar(string $permiso): ContextoOperacion
    {
        $contexto = $this->authMiddleware->procesar();
        if ($contexto === null) {
            if (!headers_sent()) {
                $this->setCodigoHttp(401);
            }
            throw new \Aplicacion\Excepciones\AccesoDenegadoExcepcion('Sesión no autenticada');
        }

        $this->authzMiddleware->verificarPermiso($permiso, $contexto, true);

        return $contexto;
    }
}
