<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

use Aplicacion\Comunicaciones\Proveedores\FabricaProveedorWhatsApp;
use Aplicacion\Repositorios\ComunicacionConfigRepositorio;
use Aplicacion\Repositorios\ComunicacionConsentimientoRepositorio;
use Aplicacion\Repositorios\ComunicacionConversacionRepositorio;
use Aplicacion\Repositorios\ComunicacionMensajeRepositorio;
use Aplicacion\Repositorios\ComunicacionWebhookRepositorio;
use RuntimeException;

class WebhookWhatsAppServicio
{
    public function __construct(
        private ComunicacionConfigRepositorio $configRepo,
        private ComunicacionWebhookRepositorio $webhookRepo,
        private ComunicacionMensajeRepositorio $mensajeRepo,
        private ComunicacionConversacionRepositorio $conversacionRepo,
        private ComunicacionConsentimientoRepositorio $consentimientoRepo,
        private FabricaProveedorWhatsApp $fabricaProveedores,
        private ?\PDO $pdo = null
    ) {
        $this->pdo = $this->pdo ?? \Nucleo\BaseDatos\Conexion::obtenerInstancia();
    }

    /**
     * Valida el handshake inicial de verificación del webhook (Meta challenge).
     */
    public function procesarChallenge(int $orgId, string $mode, string $token, string $challenge): ?string
    {
        if ($mode !== 'subscribe') {
            return null;
        }

        if ($this->configRepo->verificarChallengeToken($orgId, $token)) {
            return $challenge;
        }

        return null;
    }

    /**
     * Procesa la carga útil (payload) entrante con validación HMAC, deduplicación e idempotencia.
     */
    public function procesarPayload(int $orgId, string $rawPayload, string $firmaCabecera): array
    {
        $config = $this->configRepo->obtenerPorOrganizacion($orgId);
        if (!$config) {
            throw new RuntimeException("Organización {$orgId} no encontrada para webhook de WhatsApp.");
        }

        // 1. Validación de firma criptográfica
        $secretos = $this->configRepo->descifrarSecretos($config);
        $webhookSecret = $secretos['webhook_secret'] ?? '';

        $adaptador = $this->fabricaProveedores->obtenerPorCodigo($config->proveedorCodigo);
        if (!$adaptador->validarFirmaWebhook($rawPayload, $firmaCabecera, $webhookSecret)) {
            throw new RuntimeException("Firma de webhook inválida (HMAC-SHA256 no coincide).");
        }

        $data = json_decode($rawPayload, true);
        if (!is_array($data)) {
            throw new RuntimeException("Payload JSON inválido.");
        }

        $eventosProcesados = 0;
        $eventosDuplicados = 0;

        $entries = $data['entry'] ?? [];
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $val = $change['value'] ?? [];

                // A. Actualizaciones de estado de entrega (DLR - Status Updates)
                $statuses = $val['statuses'] ?? [];
                foreach ($statuses as $st) {
                    $wamid = (string) ($st['id'] ?? '');
                    $statusName = (string) ($st['status'] ?? '');
                    $timestamp = isset($st['timestamp']) ? date('Y-m-d H:i:s', (int) $st['timestamp']) : date('Y-m-d H:i:s');
                    $eventId = "status_{$wamid}_{$statusName}";

                    // Deduplicación idempotente
                    $esNuevo = $this->webhookRepo->registrarEvento($orgId, $eventId, 'message_status', $wamid, $st);
                    if (!$esNuevo) {
                        $eventosDuplicados++;
                        continue;
                    }

                    $msg = $this->mensajeRepo->buscarPorWamid($wamid);
                    if ($msg && $msg->organizacionId === $orgId) {
                        $nuevoEstado = match ($statusName) {
                            'sent'      => EstadoMensaje::ENVIADO,
                            'delivered' => EstadoMensaje::ENTREGADO,
                            'read'      => EstadoMensaje::LEIDO,
                            'failed'    => EstadoMensaje::FALLIDO,
                            default     => null
                        };

                        if ($nuevoEstado !== null) {
                            // Actualización monotónica estricta
                            $this->mensajeRepo->actualizarEstadoMonotonico(
                                id: $msg->id,
                                orgId: $orgId,
                                nuevoEstado: $nuevoEstado,
                                wamid: $wamid,
                                costoCalculado: $msg->costoEstimadoUsd
                            );
                        }
                    }

                    $this->webhookRepo->marcarProcesado($orgId, $eventId);
                    $eventosProcesados++;
                }

                // B. Mensajes entrantes de clientes (Inbound Messages)
                $messages = $val['messages'] ?? [];
                foreach ($messages as $inMsg) {
                    $inMsgId = (string) ($inMsg['id'] ?? '');
                    $from = (string) ($inMsg['from'] ?? '');
                    $textBody = $inMsg['text']['body'] ?? '';
                    $timestamp = isset($inMsg['timestamp']) ? date('Y-m-d H:i:s', (int) $inMsg['timestamp']) : date('Y-m-d H:i:s');
                    $eventId = "inbound_{$inMsgId}";

                    $esNuevo = $this->webhookRepo->registrarEvento($orgId, $eventId, 'messages', $inMsgId, $inMsg);
                    if (!$esNuevo) {
                        $eventosDuplicados++;
                        continue;
                    }

                    // Normalizar teléfono
                    $telefonoE164 = str_starts_with($from, '+') ? $from : '+' . $from;

                    // Procesar palabra clave de Opt-out automático (BAJA, STOP, CANCELAR)
                    $textoLimpio = strtoupper(trim($textBody));
                    if (in_array($textoLimpio, ['BAJA', 'CANCELAR', 'STOP', 'DETENER', 'DESUSCRIBIR'], true)) {
                        // Buscar cliente o registrar revocación por número
                        $this->procesarOptOutInbound($orgId, $telefonoE164, $inMsgId);
                    }

                    $this->webhookRepo->marcarProcesado($orgId, $eventId);
                    $eventosProcesados++;
                }
            }
        }

        return [
            'exito'              => true,
            'eventos_procesados' => $eventosProcesados,
            'eventos_duplicados' => $eventosDuplicados
        ];
    }

    private function procesarOptOutInbound(int $orgId, string $telefono, string $eventId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id FROM clientes c 
            INNER JOIN personas p ON p.id = c.persona_id 
            WHERE c.organizacion_id = :org_id 
              AND (p.telefono_whatsapp = :tel1 OR p.telefono_movil = :tel2)
            LIMIT 1
        ");
        $stmt->execute(['org_id' => $orgId, 'tel1' => $telefono, 'tel2' => $telefono]);
        $clienteId = $stmt->fetchColumn();

        if ($clienteId === false) {
            $stmtMsg = $this->pdo->prepare("
                SELECT cliente_id FROM comunicacion_mensajes 
                WHERE organizacion_id = :org_id 
                  AND destinatario_telefono = :tel 
                  AND cliente_id IS NOT NULL 
                LIMIT 1
            ");
            $stmtMsg->execute(['org_id' => $orgId, 'tel' => $telefono]);
            $clienteId = $stmtMsg->fetchColumn();
        }

        if ($clienteId !== false && $clienteId !== null) {
            $consentimiento = new \Aplicacion\Entidades\ComunicacionConsentimiento(
                id: null,
                organizacionId: $orgId,
                clienteId: (int) $clienteId,
                canal: 'WHATSAPP',
                telefonoDestino: $telefono,
                finalidad: FinalidadConsentimiento::PROMOCIONAL_MARKETING,
                estado: EstadoConsentimiento::REVOCADO,
                origenEvidencia: 'WHATSAPP_INBOUND_KEYWORD',
                correlacionId: $eventId,
                textoClausulaAceptada: 'Revocación por palabra clave inbound (BAJA/STOP)',
                actorTipo: 'SISTEMA',
                revocadoEn: date('Y-m-d H:i:s')
            );

            $this->consentimientoRepo->guardar($consentimiento);
        }
    }
}
