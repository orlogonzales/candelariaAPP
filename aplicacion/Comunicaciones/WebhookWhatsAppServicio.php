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
     * Procesa la carga útil (payload) entrante con validación HMAC fail-closed,
     * bloqueo pesimista por fila, deduplicación e invariante monotónica ante eventos fuera de orden.
     */
    public function procesarPayload(int $orgId, string $rawPayload, string $firmaCabecera): array
    {
        $config = $this->configRepo->obtenerPorOrganizacion($orgId);
        if (!$config) {
            throw new RuntimeException("Organización {$orgId} no encontrada para webhook de WhatsApp.");
        }

        // 1. Validación estricta fail-closed de firma criptográfica X-Hub-Signature-256
        $firmaLimpia = trim($firmaCabecera);
        if ($firmaLimpia === '' || !str_starts_with($firmaLimpia, 'sha256=') || strlen($firmaLimpia) < 15) {
            throw new RuntimeException("Firma de webhook inválida (HMAC-SHA256 no coincide o cabecera ausente/malformada).");
        }

        $secretos = $this->configRepo->descifrarSecretos($config);
        $webhookSecret = trim((string) ($secretos['webhook_secret'] ?? ''));
        if ($webhookSecret === '') {
            throw new RuntimeException("Firma de webhook inválida (HMAC-SHA256 no coincide: secreto de tenant no configurado).");
        }

        $adaptador = $this->fabricaProveedores->obtenerPorCodigo($config->proveedorCodigo);
        if (!$adaptador->validarFirmaWebhook($rawPayload, $firmaLimpia, $webhookSecret)) {
            throw new RuntimeException("Firma de webhook inválida (HMAC-SHA256 no coincide).");
        }

        $data = json_decode($rawPayload, true);
        if (!is_array($data)) {
            throw new RuntimeException("Payload JSON inválido.");
        }

        $eventosProcesados = 0;
        $eventosDuplicados = 0;
        $eventosFueraDeOrdenIgnorados = 0;

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $entries = $data['entry'] ?? [];
            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];
                foreach ($changes as $change) {
                    $val = $change['value'] ?? [];

                    // A. Actualizaciones de estado de entrega (DLR - Status Updates)
                    $statuses = $val['statuses'] ?? [];
                    foreach ($statuses as $st) {
                        $wamid = trim((string) ($st['id'] ?? ''));
                        $statusName = strtolower(trim((string) ($st['status'] ?? '')));
                        if ($wamid === '' || $statusName === '') {
                            continue;
                        }

                        $eventId = "status_{$wamid}_{$statusName}";

                        // Deduplicación idempotente atómica
                        $esNuevo = $this->webhookRepo->registrarEvento($orgId, $eventId, 'message_status', $wamid, $st);
                        if (!$esNuevo) {
                            $eventosDuplicados++;
                            continue;
                        }

                        // Bloqueo pesimista sobre la fila del mensaje para serializar webhooks concurrentes
                        $stmtLockMsg = $this->pdo->prepare("
                            SELECT `id`, `organizacion_id`, `estado`, `peso_estado`, `costo_estimado_usd`
                            FROM `comunicacion_mensajes`
                            WHERE `wamid` = :wamid AND `organizacion_id` = :org_id
                            LIMIT 1
                            FOR UPDATE
                        ");
                        $stmtLockMsg->execute(['wamid' => $wamid, 'org_id' => $orgId]);
                        $filaMsg = $stmtLockMsg->fetch(\PDO::FETCH_ASSOC);

                        if ($filaMsg) {
                            $nuevoEstado = match ($statusName) {
                                'sent'      => EstadoMensaje::ENVIADO,
                                'delivered' => EstadoMensaje::ENTREGADO,
                                'read'      => EstadoMensaje::LEIDO,
                                'failed'    => EstadoMensaje::FALLIDO,
                                default     => null
                            };

                            if ($nuevoEstado !== null) {
                                $transicionado = $this->mensajeRepo->actualizarEstadoMonotonico(
                                    id: (int) $filaMsg['id'],
                                    orgId: $orgId,
                                    nuevoEstado: $nuevoEstado,
                                    wamid: $wamid,
                                    costoCalculado: (float) ($filaMsg['costo_estimado_usd'] ?? 0.0)
                                );
                                if (!$transicionado) {
                                    $eventosFueraDeOrdenIgnorados++;
                                }
                            }
                        }

                        $this->webhookRepo->marcarProcesado($orgId, $eventId);
                        $eventosProcesados++;
                    }

                    // B. Mensajes entrantes de clientes (Inbound Messages)
                    $messages = $val['messages'] ?? [];
                    foreach ($messages as $inMsg) {
                        $inMsgId = trim((string) ($inMsg['id'] ?? ''));
                        if ($inMsgId === '') {
                            continue;
                        }
                        $from = (string) ($inMsg['from'] ?? '');
                        $textBody = $inMsg['text']['body'] ?? '';
                        $eventId = "inbound_{$inMsgId}";

                        $esNuevo = $this->webhookRepo->registrarEvento($orgId, $eventId, 'messages', $inMsgId, $inMsg);
                        if (!$esNuevo) {
                            $eventosDuplicados++;
                            continue;
                        }

                        // Normalizar teléfono
                        $telefonoE164 = str_starts_with($from, '+') ? $from : '+' . $from;

                        // Procesar palabra clave de Opt-out automático (BAJA, STOP, CANCELAR)
                        $textoLimpio = strtoupper(trim((string) $textBody));
                        if (in_array($textoLimpio, ['BAJA', 'CANCELAR', 'STOP', 'DETENER', 'DESUSCRIBIR'], true)) {
                            $this->procesarOptOutInbound($orgId, $telefonoE164, $inMsgId);
                        }

                        $this->webhookRepo->marcarProcesado($orgId, $eventId);
                        $eventosProcesados++;
                    }
                }
            }

            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return [
                'exito'                            => true,
                'eventos_procesados'               => $eventosProcesados,
                'eventos_duplicados'               => $eventosDuplicados,
                'eventos_fuera_de_orden_ignorados' => $eventosFueraDeOrdenIgnorados,
            ];
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
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
