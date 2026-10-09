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
            WHERE estado IN ('ENCOLADO', 'REINTENTO_PROGRAMADO')
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
              AND estado IN ('ENCOLADO', 'REINTENTO_PROGRAMADO') 
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
            WHERE id = :id 
              AND organizacion_id = :org_id 
              AND estado IN ('ENCOLADO', 'EN_PROCESO')
        ");
        $stmt->execute([
            'wamid'  => $wamid,
            'costo'  => $costoCalculado,
            'id'     => $id,
            'org_id' => $orgId
        ]);
    }

    /**
     * Transición de máquina de estados estricta y segura:
     * El estado solo avanza si el estado actual es un origen contractualmente permitido.
     * Un mensaje ENTREGADO o LEIDO jamás puede ser revertido a FALLIDO ni degradado.
     */
    public function actualizarEstadoMonotonico(
        int $id,
        int $orgId,
        EstadoMensaje $nuevoEstado,
        ?string $wamid = null,
        float $costoCalculado = 0.00
    ): bool {
        $estadosOrigen = EstadoMensaje::estadosOrigenValidosPara($nuevoEstado);
        if (empty($estadosOrigen)) {
            return false;
        }

        $inPlaceholders = implode(',', array_fill(0, count($estadosOrigen), '?'));
        $nuevoPeso = $nuevoEstado->peso();

        $sql = "
            UPDATE comunicacion_mensajes SET
                estado = ?,
                peso_estado = ?,
                wamid = COALESCE(?, wamid),
                costo_calculado_usd = GREATEST(costo_calculado_usd, ?),
                bloqueado_hasta = NULL,
                actualizado_en = NOW()
            WHERE id = ? 
              AND organizacion_id = ? 
              AND estado IN ({$inPlaceholders})
        ";

        $params = array_merge(
            [$nuevoEstado->value, $nuevoPeso, $wamid, $costoCalculado, $id, $orgId],
            $estadosOrigen
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function programarReintento(int $id, int $orgId, int $segundosBackoff): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_mensajes SET
                estado = 'REINTENTO_PROGRAMADO',
                peso_estado = 25,
                proximo_intento_en = DATE_ADD(NOW(), INTERVAL :segundos SECOND),
                bloqueado_hasta = NULL,
                actualizado_en = NOW()
            WHERE id = :id 
              AND organizacion_id = :org_id
              AND estado IN ('EN_PROCESO')
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
            WHERE id = :id 
              AND organizacion_id = :org_id
              AND estado IN ('ENCOLADO', 'EN_PROCESO', 'REINTENTO_PROGRAMADO', 'ENVIADO')
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
            WHERE id = :id 
              AND organizacion_id = :org_id
              AND estado IN ('ENCOLADO', 'EN_PROCESO')
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

    /**
     * Retorna indicadores clave de rendimiento (KPIs) para el dashboard Alina.
     * @return array<string, mixed>
     */
    public function obtenerKpis(int $orgId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total_mensajes,
                SUM(CASE WHEN creado_en >= CURDATE() THEN 1 ELSE 0 END) AS mensajes_hoy,
                SUM(CASE WHEN estado = 'ENVIADO' THEN 1 ELSE 0 END) AS enviados,
                SUM(CASE WHEN estado = 'ENTREGADO' THEN 1 ELSE 0 END) AS entregados,
                SUM(CASE WHEN estado = 'LEIDO' THEN 1 ELSE 0 END) AS leidos,
                SUM(CASE WHEN estado = 'FALLIDO' THEN 1 ELSE 0 END) AS fallidos,
                SUM(CASE WHEN estado IN ('CREADO', 'ENCOLADO', 'EN_PROCESO') THEN 1 ELSE 0 END) AS en_cola,
                COALESCE(SUM(costo_calculado_usd), 0.00) AS gasto_total_usd
            FROM comunicacion_mensajes
            WHERE organizacion_id = :org_id
        ");
        $stmt->execute(['org_id' => $orgId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalEnviados = (int) ($row['enviados'] ?? 0) + (int) ($row['entregados'] ?? 0) + (int) ($row['leidos'] ?? 0);
        $totalEntregados = (int) ($row['entregados'] ?? 0) + (int) ($row['leidos'] ?? 0);
        $totalLeidos = (int) ($row['leidos'] ?? 0);

        $tasaEntrega = $totalEnviados > 0 ? round(($totalEntregados / $totalEnviados) * 100, 1) : 100.0;
        $tasaLectura = $totalEntregados > 0 ? round(($totalLeidos / $totalEntregados) * 100, 1) : 0.0;

        return [
            'total_mensajes'   => (int) ($row['total_mensajes'] ?? 0),
            'mensajes_hoy'     => (int) ($row['mensajes_hoy'] ?? 0),
            'enviados'         => (int) ($row['enviados'] ?? 0),
            'entregados'       => (int) ($row['entregados'] ?? 0),
            'leidos'           => (int) ($row['leidos'] ?? 0),
            'fallidos'         => (int) ($row['fallidos'] ?? 0),
            'en_cola'          => (int) ($row['en_cola'] ?? 0),
            'gasto_total_usd'  => (float) ($row['gasto_total_usd'] ?? 0.00),
            'tasa_entrega_pct' => $tasaEntrega,
            'tasa_lectura_pct' => $tasaLectura
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarParaDataTables(int $orgId, ?string $estado = null, ?string $tipo = null, int $limite = 100): array
    {
        $sql = "
            SELECT m.*, p.nombre AS plantilla_nombre
            FROM comunicacion_mensajes m
            LEFT JOIN comunicacion_plantillas p ON p.id = m.plantilla_id
            WHERE m.organizacion_id = :org_id
        ";
        $params = ['org_id' => $orgId];

        if (!empty($estado)) {
            $sql .= " AND m.estado = :estado";
            $params['estado'] = $estado;
        }

        if (!empty($tipo)) {
            $sql .= " AND m.tipo_mensaje = :tipo";
            $params['tipo'] = $tipo;
        }

        $sql .= " ORDER BY m.id DESC LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerIntentos(int $mensajeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_intentos_envio 
            WHERE mensaje_id = :id 
            ORDER BY intento_numero ASC
        ");
        $stmt->execute(['id' => $mensajeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
