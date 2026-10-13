<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Operaciones\OperacionOfflineEvento;
use Aplicacion\Operaciones\OperacionOfflineLote;
use PDO;

/**
 * Repositorio para la persistencia y consulta de lotes y eventos de sincronización offline.
 */
class OperacionOfflineRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crearLote(OperacionOfflineLote $lote): OperacionOfflineLote
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `operacion_offline_lotes` (
                `organizacion_id`, `edicion_id`, `operador_id`, `lote_uuid`,
                `dispositivo_info`, `total_escaneos`, `total_confirmados`,
                `total_conflictos`, `total_rechazados`, `estado`, `ip_origen`,
                `creado_en`, `procesado_en`
            ) VALUES (
                :org_id, :edicion_id, :operador_id, :lote_uuid,
                :dispositivo, :total_escaneos, :total_confirmados,
                :total_conflictos, :total_rechazados, :estado, :ip,
                CURRENT_TIMESTAMP, :procesado_en
            )
        ");

        $stmt->execute([
            'org_id'             => $lote->organizacionId,
            'edicion_id'         => $lote->edicionId,
            'operador_id'        => $lote->operadorId,
            'lote_uuid'          => $lote->loteUuid,
            'dispositivo'        => $lote->dispositivoInfo,
            'total_escaneos'     => $lote->totalEscaneos,
            'total_confirmados'  => $lote->totalConfirmados,
            'total_conflictos'   => $lote->totalConflictos,
            'total_rechazados'   => $lote->totalRechazados,
            'estado'             => $lote->estado,
            'ip'                 => $lote->ipOrigen,
            'procesado_en'       => $lote->procesadoEn,
        ]);

        $lote->id = (int) $this->pdo->lastInsertId();
        return $lote;
    }

    public function actualizarTotalesLote(
        int $loteId,
        int $totalEscaneos,
        int $confirmados,
        int $conflictos,
        int $rechazados,
        string $estado
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE `operacion_offline_lotes`
            SET `total_escaneos` = :tot,
                `total_confirmados` = :conf,
                `total_conflictos` = :confl,
                `total_rechazados` = :rech,
                `estado` = :estado,
                `procesado_en` = CURRENT_TIMESTAMP
            WHERE `id` = :id
        ");

        return $stmt->execute([
            'id'    => $loteId,
            'tot'   => $totalEscaneos,
            'conf'  => $confirmados,
            'confl' => $conflictos,
            'rech'  => $rechazados,
            'estado' => $estado,
        ]);
    }

    public function buscarLotePorUuid(string $uuid): ?OperacionOfflineLote
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `operacion_offline_lotes` WHERE `lote_uuid` = :uuid LIMIT 1
        ");
        $stmt->execute(['uuid' => $uuid]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? OperacionOfflineLote::desdeArreglo($fila) : null;
    }

    public function existeEventoPorIdempotencia(string $idempotenciaHash): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM `operacion_offline_eventos` WHERE `idempotencia_hash` = :hash
        ");
        $stmt->execute(['hash' => $idempotenciaHash]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function crearEvento(OperacionOfflineEvento $evento): OperacionOfflineEvento
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `operacion_offline_eventos` (
                `lote_id`, `organizacion_id`, `edicion_id`, `operador_id`,
                `idempotencia_hash`, `token_qr_hash`, `tipo_credencial`, `referencia_id`,
                `resultado_local`, `resultado_servidor`, `motivo_resolucion`,
                `escaneado_en`, `sincronizado_en`, `metadatos_json`
            ) VALUES (
                :lote_id, :org_id, :edicion_id, :operador_id,
                :idempotencia_hash, :token_qr_hash, :tipo, :ref_id,
                :res_local, :res_servidor, :motivo,
                :escaneado_en, CURRENT_TIMESTAMP, :metadatos
            )
        ");

        $stmt->execute([
            'lote_id'           => $evento->loteId,
            'org_id'            => $evento->organizacionId,
            'edicion_id'        => $evento->edicionId,
            'operador_id'       => $evento->operadorId,
            'idempotencia_hash' => $evento->idempotenciaHash,
            'token_qr_hash'     => $evento->tokenQrHash,
            'tipo'              => $evento->tipoCredencial,
            'ref_id'            => $evento->referenciaId,
            'res_local'         => $evento->resultadoLocal,
            'res_servidor'      => $evento->resultadoServidor,
            'motivo'            => $evento->motivoResolucion,
            'escaneado_en'      => $evento->escaneadoEn,
            'metadatos'         => $evento->metadatosJson !== null ? json_encode($evento->metadatosJson, JSON_UNESCAPED_UNICODE) : null,
        ]);

        $evento->id = (int) $this->pdo->lastInsertId();
        return $evento;
    }

    public function obtenerUltimoEventoPorQrHash(int $organizacionId, string $tokenQrHash): ?OperacionOfflineEvento
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `operacion_offline_eventos`
            WHERE `organizacion_id` = :org_id AND `token_qr_hash` = :hash
            ORDER BY `id` DESC LIMIT 1
        ");
        $stmt->execute(['org_id' => $organizacionId, 'hash' => $tokenQrHash]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? OperacionOfflineEvento::desdeArreglo($fila) : null;
    }

    public function listarLotesPorOrganizacion(int $organizacionId, int $edicionId, int $limite = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT l.*, u.nombre_usuario as operador_nombre
            FROM `operacion_offline_lotes` l
            JOIN `usuarios` u ON l.operador_id = u.id
            WHERE l.organizacion_id = :org_id AND l.edicion_id = :ed_id
            ORDER BY l.id DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':org_id', $organizacionId, PDO::PARAM_INT);
        $stmt->bindValue(':ed_id', $edicionId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarEventosPorLote(int $loteId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, u.nombre_usuario as operador_nombre
            FROM `operacion_offline_eventos` e
            JOIN `usuarios` u ON e.operador_id = u.id
            WHERE e.lote_id = :lote_id
            ORDER BY e.id ASC
        ");
        $stmt->execute(['lote_id' => $loteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
