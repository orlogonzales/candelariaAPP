<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Comunicaciones\ComunicacionServicio;
use Aplicacion\Comunicaciones\EstadoConsentimiento;
use Aplicacion\Comunicaciones\FinalidadConsentimiento;
use Aplicacion\Comunicaciones\ModoComunicacion;
use Aplicacion\Comunicaciones\WebhookWhatsAppServicio;
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
            http_response_code(200);
            return $res;
        }

        http_response_code(403);
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
            http_response_code(400);
            return json_encode(['exito' => false, 'error' => 'Cuerpo de petición vacío']);
        }

        $firma = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? $_SERVER['X-Hub-Signature-256'] ?? '';

        try {
            $resultado = $this->webhookServicio->procesarPayload($orgId, $rawBody, (string) $firma);
            http_response_code(200);
            return json_encode($resultado);
        } catch (Throwable $e) {
            http_response_code(401);
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
            http_response_code(422);
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

            http_response_code(201);
            return json_encode([
                'exito'   => true,
                'mensaje' => 'Mensaje transaccional encolado exitosamente en Outbox',
                'datos'   => $mensaje->aArreglo()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(400);
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
            http_response_code(422);
            return json_encode(['exito' => false, 'error' => 'El texto de la respuesta es obligatorio']);
        }

        try {
            $mensaje = $this->comunicacionServicio->encolarRespuestaOperador(
                orgId: $contexto->organizacionId,
                conversacionId: $conversacionId,
                textoLibre: $texto,
                contexto: $contexto
            );

            http_response_code(201);
            return json_encode([
                'exito'   => true,
                'mensaje' => 'Respuesta encolada en ventana de atención',
                'datos'   => $mensaje->aArreglo()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(400);
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
        $contexto = $this->autenticarYAutorizar('comunicaciones.ver');

        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $finalidadStr = trim((string) ($_POST['finalidad'] ?? 'TRANSACCIONAL_OPERATIVO'));
        $estadoStr = trim((string) ($_POST['estado'] ?? 'CONCEDIDO'));
        $origen = trim((string) ($_POST['origen'] ?? 'WEB_FORMULARIO'));
        $clausula = trim((string) ($_POST['clausula'] ?? 'Aceptación expresa de comunicaciones WhatsApp'));

        if ($clienteId <= 0 || empty($telefono)) {
            http_response_code(422);
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

            http_response_code(201);
            return json_encode([
                'exito' => true,
                'datos' => $c->aArreglo()
            ]);
        } catch (Throwable $e) {
            http_response_code(400);
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
            http_response_code(400);
            return json_encode(['exito' => false, 'error' => $e->getMessage()]);
        }
    }

    private function autenticarYAutorizar(string $permiso): ContextoOperacion
    {
        $contexto = $this->authMiddleware->procesar();
        if ($contexto === null) {
            http_response_code(401);
            echo json_encode(['exito' => false, 'error' => 'Sesión no autenticada']);
            exit;
        }

        $autorizado = $this->authzMiddleware->verificarPermiso($permiso, $contexto, false);
        if (!$autorizado) {
            http_response_code(403);
            echo json_encode(['exito' => false, 'error' => "Permiso denegado: se requiere '{$permiso}'"]);
            exit;
        }

        return $contexto;
    }
}
