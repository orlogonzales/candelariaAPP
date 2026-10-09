<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Comunicaciones\EstadoMensaje;
use Aplicacion\Comunicaciones\TipoMensaje;
use Aplicacion\Entidades\ComunicacionMensaje;
use PDO;

class ComunicacionMensajeRepositorio
{
    public function __construct(private PDO $pdo) {}

    public function guardar(ComunicacionMensaje $m): ComunicacionMensaje
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO comunicacion_mensajes (
                organizacion_id, conversacion_id, campana_id, tipo_mensaje, direccion, canal,
                destinatario_telefono, destinatario_nombre, cliente_id, venta_id, reserva_id,
                plantilla_id, contenido_texto, parametros_enviados_json, estado, peso_estado,
                wamid, idempotency_key, costo_estimado_usd, costo_calculado_usd, costo_conciliado_usd,
                intentos_realizados, max_intentos, proximo_intento_en, bloqueado_hasta,
                creado_por, correlacion_id
            ) VALUES (
                :org_id, :conv_id, :campana_id, :tipo, :dir, :canal,
                :telefono, :nombre, :cli_id, :vta_id, :res_id,
                :plan_id, :texto, :params, :estado, :peso,
                :wamid, :idemp_key, :c_est, :c_calc, :c_conc,
                :intentos, :max_int, COALESCE(:prox_int, NOW()), :bloq,
                :creado_por, :corr_id
            )
        ");

        $stmt->execute([
            'org_id'     => $m->organizacionId,
            'conv_id'    => $m->conversacionId,
            'campana_id' => $m->campanaId,
            'tipo'       => $m->tipoMensaje->value,
            'dir'        => $m->direccion,
            'canal'      => $m->canal,
            'telefono'   => $m->destinatarioTelefono,
            'nombre'     => $m->destinatarioNombre,
            'cli_id'     => $m->clienteId,
            'vta_id'     => $m->ventaId,
            'res_id'     => $m->reservaId,
            'plan_id'    => $m->plantillaId,
            'texto'      => $m->contenidoTexto,
            'params'     => $m->parametrosEnviadosJson !== null ? json_encode($m->parametrosEnviadosJson, JSON_UNESCAPED_UNICODE) : null,
            'estado'     => $m->estado->value,
            'peso'       => $m->pesoEstado,
            'wamid'      => $m->wamid,
            'idemp_key'  => $m->idempotencyKey,
            'c_est'      => $m->costoEstimadoUsd,
            'c_calc'     => $m->costoCalculadoUsd,
            'c_conc'     => $m->costoConciliadoUsd,
            'intentos'   => $m->intentosRealizados,
            'max_int'    => $m->maxIntentos,
            'prox_int'   => $m->proximoIntentoEn,
            'bloq'       => $m->bloqueadoHasta,
            'creado_por' => $m->creadoPor,
            'corr_id'    => $m->correlacionId
        ]);

        $m->id = (int) $this->pdo->lastInsertId();
        return $m;
    }

    public function buscarPorId(int $id, int $orgId): ?ComunicacionMensaje
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_mensajes 
            WHERE id = :id AND organizacion_id = :org_id
        ");
        $stmt->execute(['id' => $id, 'org_id' => $orgId]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        return $f ? $this->hidratar($f) : null;
    }

    public function buscarPorIdempotencyKey(string $key, int $orgId): ?ComunicacionMensaje
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_mensajes 
            WHERE idempotency_key = :key AND organizacion_id = :org_id
        ");
        $stmt->execute(['key' => $key, 'org_id' => $orgId]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        return $f ? $this->hidratar($f) : null;
    }

    public function buscarPorWamid(string $wamid): ?ComunicacionMensaje
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_mensajes 
            WHERE wamid = :wamid 
            LIMIT 1
        ");
        $stmt->execute(['wamid' => $wamid]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        return $f ? $this->hidratar($f) : null;
    }

    /**
     * Fase 1 del Outbox Worker: Reserva atómica liberando locks inmediatamente.
     * Selecciona candidatos y los bloquea temporalmente a nivel lógico sin mantener transacción larga.
     */
    public function reservarLoteOutbox(int $limite = 20): array
    {
        // 1. Obtener IDs candidatos
        $stmtCandidatos = $this->pdo->prepare("
            SELECT id FROM comunicacion_mensajes
            WHERE estado = 'ENCOLADO'
              AND proximo_intento_en <= NOW()
              AND (bloqueado_hasta IS NULL OR bloqueado_hasta < NOW())
            ORDER BY id ASC
            LIMIT :limite
        ");
        $stmtCandidatos->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmtCandidatos->execute();
        $ids = $stmtCandidatos->fetchAll(PDO::FETCH_COLUMN);

        if (empty($ids)) {
            return [];
        }

        $reservados = [];
        $stmtLock = $this->pdo->prepare("
            UPDATE comunicacion_mensajes SET
                estado = 'EN_PROCESO',
                peso_estado = 20,
                bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 2 MINUTE),
                intentos_realizados = intentos_realizados + 1,
                actualizado_en = NOW()
            WHERE id = :id 
              AND estado = 'ENCOLADO' 
              AND (bloqueado_hasta IS NULL OR bloqueado_hasta < NOW())
        ");

        foreach ($ids as $id) {
            $stmtLock->execute(['id' => $id]);
            if ($stmtLock->rowCount() > 0) {
                // Fila reservada con éxito por este worker
                $stmtGet = $this->pdo->prepare("SELECT * FROM comunicacion_mensajes WHERE id = :id");
                $stmtGet->execute(['id' => $id]);
                $f = $stmtGet->fetch(PDO::FETCH_ASSOC);
                if ($f) {
                    $reservados[] = $this->hidratar($f);
                }
            }
        }

        return $reservados;
    }

    public function actualizarAEnviado(int $id, int $orgId, string $wamid, float $costoCalculado = 0.00): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_mensajes SET
                estado = 'ENVIADO',
                peso_estado = 30,
                wamid = :wamid,
                costo_calculado_usd = GREATEST(costo_calculado_usd, :costo),
                bloqueado_hasta = NULL,
                actualizado_en = NOW()
            WHERE id = :id AND organizacion_id = :org_id
        ");
        $stmt->execute([
            'wamid'  => $wamid,
            'costo'  => $costoCalculado,
            'id'     => $id,
            'org_id' => $orgId
        ]);
    }

    /**
     * Transición monotónica garantizada:
     * El estado solo avanza si el peso del nuevo estado es estrictamente superior al actual.
     */
    public function actualizarEstadoMonotonico(
        int $id,
        int $orgId,
        EstadoMensaje $nuevoEstado,
        ?string $wamid = null,
        float $costoCalculado = 0.00
    ): bool {
        $nuevoPeso = $nuevoEstado->peso();
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_mensajes SET
                estado = :nuevo_est,
                peso_estado = :nuevo_peso,
                wamid = COALESCE(:wamid, wamid),
                costo_calculado_usd = GREATEST(costo_calculado_usd, :costo),
                bloqueado_hasta = NULL,
                actualizado_en = NOW()
            WHERE id = :id 
              AND organizacion_id = :org_id 
              AND peso_estado < :nuevo_peso_cond
        ");

        $stmt->execute([
            'nuevo_est'        => $nuevoEstado->value,
            'nuevo_peso'       => $nuevoPeso,
            'wamid'            => $wamid,
            'costo'            => $costoCalculado,
            'id'               => $id,
            'org_id'           => $orgId,
            'nuevo_peso_cond'  => $nuevoPeso
        ]);

        return $stmt->rowCount() > 0;
    }

    public function programarReintento(int $id, int $orgId, int $segundosBackoff): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_mensajes SET
                estado = 'ENCOLADO',
                peso_estado = 10,
                proximo_intento_en = DATE_ADD(NOW(), INTERVAL :segundos SECOND),
                bloqueado_hasta = NULL,
                actualizado_en = NOW()
            WHERE id = :id AND organizacion_id = :org_id
        ");
        $stmt->execute(['segundos' => $segundosBackoff, 'id' => $id, 'org_id' => $orgId]);
    }

    public function marcarFallido(int $id, int $orgId, string $motivo): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_mensajes SET
                estado = 'FALLIDO',
                peso_estado = 90,
                contenido_texto = CONCAT('[FALLIDO: ', :motivo, '] ', contenido_texto),
                bloqueado_hasta = NULL,
                actualizado_en = NOW()
            WHERE id = :id AND organizacion_id = :org_id
        ");
        $stmt->execute(['motivo' => substr($motivo, 0, 100), 'id' => $id, 'org_id' => $orgId]);
    }

    public function cancelarMensaje(int $id, int $orgId, string $motivo): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_mensajes SET
                estado = 'CANCELADO',
                peso_estado = 95,
                contenido_texto = CONCAT('[CANCELADO: ', :motivo, '] ', contenido_texto),
                bloqueado_hasta = NULL,
                actualizado_en = NOW()
            WHERE id = :id AND organizacion_id = :org_id
        ");
        $stmt->execute(['motivo' => substr($motivo, 0, 100), 'id' => $id, 'org_id' => $orgId]);
    }

    public function registrarIntento(
        int $mensajeId,
        int $intentoNumero,
        ?int $httpStatus,
        ?int $errorCode,
        ?int $errorSubcode,
        ?string $errorMessage,
        ?int $latenciaMs
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO comunicacion_intentos_envio (
                mensaje_id, intento_numero, http_status, meta_error_code,
                meta_error_subcode, meta_error_message, latencia_ms
            ) VALUES (
                :msg_id, :int_num, :status, :err_code,
                :err_sub, :err_msg, :latencia
            )
        ");
        $stmt->execute([
            'msg_id'   => $mensajeId,
            'int_num'  => $intentoNumero,
            'status'   => $httpStatus,
            'err_code' => $errorCode,
            'err_sub'  => $errorSubcode,
            'err_msg'  => $errorMessage !== null ? substr($errorMessage, 0, 255) : null,
            'latencia' => $latenciaMs
        ]);
    }

    public function recuperarMensajesAbandonados(): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_mensajes SET
                estado = 'ENCOLADO',
                peso_estado = 10,
                bloqueado_hasta = NULL,
                actualizado_en = NOW()
            WHERE estado = 'EN_PROCESO' 
              AND bloqueado_hasta < NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function hidratar(array $f): ComunicacionMensaje
    {
        return new ComunicacionMensaje(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            tipoMensaje: TipoMensaje::from((string) $f['tipo_mensaje']),
            direccion: (string) $f['direccion'],
            canal: (string) $f['canal'],
            destinatarioTelefono: (string) $f['destinatario_telefono'],
            destinatarioNombre: (string) $f['destinatario_nombre'],
            contenidoTexto: (string) $f['contenido_texto'],
            idempotencyKey: (string) $f['idempotency_key'],
            correlacionId: (string) $f['correlacion_id'],
            estado: EstadoMensaje::from((string) $f['estado']),
            pesoEstado: (int) $f['peso_estado'],
            conversacionId: $f['conversacion_id'] !== null ? (int) $f['conversacion_id'] : null,
            campanaId: $f['campana_id'] !== null ? (int) $f['campana_id'] : null,
            clienteId: $f['cliente_id'] !== null ? (int) $f['cliente_id'] : null,
            ventaId: $f['venta_id'] !== null ? (int) $f['venta_id'] : null,
            reservaId: $f['reserva_id'] !== null ? (int) $f['reserva_id'] : null,
            plantillaId: $f['plantilla_id'] !== null ? (int) $f['plantilla_id'] : null,
            parametrosEnviadosJson: $f['parametros_enviados_json'] !== null ? json_decode((string) $f['parametros_enviados_json'], true) : null,
            wamid: $f['wamid'] !== null ? (string) $f['wamid'] : null,
            costoEstimadoUsd: (float) $f['costo_estimado_usd'],
            costoCalculadoUsd: (float) $f['costo_calculado_usd'],
            costoConciliadoUsd: (float) $f['costo_conciliado_usd'],
            fechaConciliacion: $f['fecha_conciliacion'] !== null ? (string) $f['fecha_conciliacion'] : null,
            intentosRealizados: (int) $f['intentos_realizados'],
            maxIntentos: (int) $f['max_intentos'],
            proximoIntentoEn: $f['proximo_intento_en'] !== null ? (string) $f['proximo_intento_en'] : null,
            bloqueadoHasta: $f['bloqueado_hasta'] !== null ? (string) $f['bloqueado_hasta'] : null,
            creadoPor: $f['creado_por'] !== null ? (int) $f['creado_por'] : null,
            creadoEn: (string) $f['creado_en'],
            actualizadoEn: $f['actualizado_en'] !== null ? (string) $f['actualizado_en'] : null
        );
    }
}
