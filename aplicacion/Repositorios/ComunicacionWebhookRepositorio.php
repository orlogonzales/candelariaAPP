<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use PDO;

class ComunicacionWebhookRepositorio
{
    public function __construct(private PDO $pdo) {}

    /**
     * Registra un evento entrante garantizando idempotencia estricta.
     * Retorna true si es un evento nuevo; false si es un duplicado ya recibido.
     */
    public function registrarEvento(
        int $orgId,
        string $eventId,
        string $tipoEvento,
        ?string $wamid,
        array $payloadResumido
    ): bool {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO comunicacion_webhook_eventos (
                organizacion_id, event_id, tipo_evento, wamid, payload_resumido_json
            ) VALUES (
                :org_id, :event_id, :tipo, :wamid, :payload
            )
        ");

        $stmt->execute([
            'org_id'   => $orgId,
            'event_id' => $eventId,
            'tipo'     => $tipoEvento,
            'wamid'    => $wamid,
            'payload'  => json_encode($payloadResumido, JSON_UNESCAPED_UNICODE)
        ]);

        return $stmt->rowCount() > 0;
    }

    public function marcarProcesado(int $orgId, string $eventId, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_webhook_eventos SET
                procesado = :procesado,
                error_proceso = :error,
                procesado_en = NOW()
            WHERE organizacion_id = :org_id AND event_id = :event_id
        ");
        $stmt->execute([
            'procesado' => $error === null ? 1 : 0,
            'error'     => $error,
            'org_id'    => $orgId,
            'event_id'  => $eventId
        ]);
    }
}
