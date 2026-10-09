<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Pago;
use Aplicacion\Entidades\PagoIntentoPasarela;
use Aplicacion\Finanzas\AsumeComisionPasarela;
use Aplicacion\Finanzas\EstadoIntentoPasarela;
use Aplicacion\Finanzas\EstadoPago;
use Aplicacion\Finanzas\MetodoPago;
use PDO;

class PagoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function generarSiguienteCorrelativo(int $organizacionId, int $anio): string
    {
        $stmtSec = $this->pdo->prepare("
            INSERT INTO `pagos_secuencias` (`organizacion_id`, `anio`, `ultimo_numero`)
            VALUES (:org_id, :anio, 1)
            ON DUPLICATE KEY UPDATE `ultimo_numero` = `ultimo_numero` + 1
        ");
        $stmtSec->execute(['org_id' => $organizacionId, 'anio' => $anio]);

        $stmtNum = $this->pdo->prepare("
            SELECT `ultimo_numero` FROM `pagos_secuencias`
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
        ");
        $stmtNum->execute(['org_id' => $organizacionId, 'anio' => $anio]);
        $num = (int) $stmtNum->fetchColumn();

        return sprintf('PAG-%04d-%06d', $anio, $num);
    }

    public function registrarPago(Pago $pago): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `pagos` (
                `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`,
                `metodo_pago`, `estado`, `moneda`, `monto_cobrado_cliente`,
                `comision_porcentaje_aplicada`, `comision_fija_aplicada`, `comision_pasarela`,
                `monto_neto_recibido`, `monto_aplicado_venta`, `monto_excedente`,
                `comision_asumida_por`, `monto_reembolsado_acumulado`, `fecha_pago`,
                `cuenta_bancaria_id`, `organizacion_pasarela_id`, `numero_operacion_bancaria`,
                `boucher_comprobante_url`, `notas_operativas`, `clave_idempotencia`,
                `version_bloqueo`, `verificado_por`, `verificado_en`, `creado_por`
            ) VALUES (
                :org_id, :edic_id, :venta_id, :correlativo,
                :metodo, :estado, :moneda, :cobrado,
                :com_pct, :com_fija, :com_pasarela,
                :neto, :aplicado, :excedente,
                :asume, :reembolsado, :fecha,
                :cuenta_id, :org_pas_id, :num_op,
                :boucher, :notas, :idempotencia,
                1, :verificado_por, :verificado_en, :creado_por
            )
        ");

        $stmt->execute([
            'org_id'         => $pago->organizacionId,
            'edic_id'        => $pago->edicionId,
            'venta_id'       => $pago->ventaId,
            'correlativo'    => $pago->correlativo,
            'metodo'         => $pago->metodoPago->value,
            'estado'         => $pago->estado->value,
            'moneda'         => $pago->moneda,
            'cobrado'        => $pago->montoCobradoCliente,
            'com_pct'        => $pago->comisionPorcentajeAplicada,
            'com_fija'       => $pago->comisionFijaAplicada,
            'com_pasarela'   => $pago->comisionPasarela,
            'neto'           => $pago->montoNetoRecibido,
            'aplicado'       => $pago->montoAplicadoVenta,
            'excedente'      => $pago->montoExcedente,
            'asume'          => $pago->comisionAsumidaPor->value,
            'reembolsado'    => $pago->montoReembolsadoAcumulado,
            'fecha'          => $pago->fechaPago,
            'cuenta_id'      => $pago->cuentaBancariaId,
            'org_pas_id'     => $pago->organizacionPasarelaId,
            'num_op'         => $pago->numeroOperacionBancaria,
            'boucher'        => $pago->boucherComprobanteUrl,
            'notas'          => $pago->notasOperativas,
            'idempotencia'   => $pago->claveIdempotencia,
            'verificado_por' => $pago->verificadoPor,
            'verificado_en'  => $pago->verificadoEn,
            'creado_por'     => $pago->creadoPor,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarPorId(int $id, int $organizacionId): ?Pago
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, v.correlativo AS venta_correlativo,
                   cb.banco_nombre AS banco_nombre,
                   pas.nombre AS pasarela_nombre
            FROM `pagos` p
            INNER JOIN `ventas` v ON v.id = p.venta_id
            LEFT JOIN `cuentas_bancarias_organizacion` cb ON cb.id = p.cuenta_bancaria_id
            LEFT JOIN `organizacion_pasarelas` op ON op.id = p.organizacion_pasarela_id
            LEFT JOIN `pasarelas_pago` pas ON pas.id = op.pasarela_id
            WHERE p.id = :id AND p.organizacion_id = :org_id
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        return $f ? $this->hidratar($f) : null;
    }

    public function buscarPorIdempotencia(int $organizacionId, string $claveIdempotencia): ?Pago
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, v.correlativo AS venta_correlativo,
                   cb.banco_nombre AS banco_nombre,
                   pas.nombre AS pasarela_nombre
            FROM `pagos` p
            INNER JOIN `ventas` v ON v.id = p.venta_id
            LEFT JOIN `cuentas_bancarias_organizacion` cb ON cb.id = p.cuenta_bancaria_id
            LEFT JOIN `organizacion_pasarelas` op ON op.id = p.organizacion_pasarela_id
            LEFT JOIN `pasarelas_pago` pas ON pas.id = op.pasarela_id
            WHERE p.organizacion_id = :org_id AND p.clave_idempotencia = :idempotencia
        ");
        $stmt->execute(['org_id' => $organizacionId, 'idempotencia' => $claveIdempotencia]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        return $f ? $this->hidratar($f) : null;
    }

    /**
     * @return Pago[]
     */
    public function listarPorVenta(int $ventaId, int $organizacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, v.correlativo AS venta_correlativo,
                   cb.banco_nombre AS banco_nombre,
                   pas.nombre AS pasarela_nombre
            FROM `pagos` p
            INNER JOIN `ventas` v ON v.id = p.venta_id
            LEFT JOIN `cuentas_bancarias_organizacion` cb ON cb.id = p.cuenta_bancaria_id
            LEFT JOIN `organizacion_pasarelas` op ON op.id = p.organizacion_pasarela_id
            LEFT JOIN `pasarelas_pago` pas ON pas.id = op.pasarela_id
            WHERE p.venta_id = :venta_id AND p.organizacion_id = :org_id
            ORDER BY p.fecha_pago DESC, p.id DESC
        ");
        $stmt->execute(['venta_id' => $ventaId, 'org_id' => $organizacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hidratar'], $filas);
    }

    public function cambiarEstadoVerificacion(
        int $pagoId,
        int $organizacionId,
        EstadoPago $nuevoEstado,
        int $verificadoPor,
        int $versionBloqueoEsperada,
        ?string $notas = null
    ): bool {
        $sql = "
            UPDATE `pagos`
            SET `estado` = :estado,
                `verificado_por` = :verificado_por,
                `verificado_en` = NOW(),
                `version_bloqueo` = `version_bloqueo` + 1
        ";
        $params = [
            'id'             => $pagoId,
            'org_id'         => $organizacionId,
            'estado'         => $nuevoEstado->value,
            'verificado_por' => $verificadoPor,
            'ver_esperada'   => $versionBloqueoEsperada,
        ];

        if ($notas !== null) {
            $sql .= ", `notas_operativas` = CONCAT(COALESCE(`notas_operativas`, ''), '\n[VERIFICACION]: ', :notas)";
            $params['notas'] = $notas;
        }

        $sql .= " WHERE `id` = :id AND `organizacion_id` = :org_id AND `version_bloqueo` = :ver_esperada";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function actualizarBoucherUrl(int $pagoId, int $organizacionId, string $boucherUrl): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `pagos`
            SET `boucher_comprobante_url` = :url,
                `actualizado_en` = NOW()
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute([
            'id'     => $pagoId,
            'org_id' => $organizacionId,
            'url'    => $boucherUrl,
        ]);
    }

    public function acumularReembolsoEjecutado(int $pagoId, int $organizacionId, float $montoReembolso): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `pagos`
            SET `monto_reembolsado_acumulado` = `monto_reembolsado_acumulado` + :monto,
                `version_bloqueo` = `version_bloqueo` + 1
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute([
            'id'     => $pagoId,
            'org_id' => $organizacionId,
            'monto'  => $montoReembolso,
        ]);
    }

    public function actualizarProyeccionFinancieraVenta(
        int $ventaId,
        int $organizacionId,
        float $montoPagado,
        float $saldoPendiente,
        string $estadoFinanciero
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE `ventas`
            SET `monto_pagado` = :pagado,
                `saldo_pendiente` = :saldo,
                `estado_financiero` = :est_fin,
                `actualizado_en` = NOW()
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute([
            'id'      => $ventaId,
            'org_id'  => $organizacionId,
            'pagado'  => $montoPagado,
            'saldo'   => $saldoPendiente,
            'est_fin' => $estadoFinanciero,
        ]);
    }

    public function registrarIntentoPasarela(PagoIntentoPasarela $intento): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `pago_intentos_pasarela` (
                `organizacion_id`, `pago_id`, `venta_id`, `organizacion_pasarela_id`,
                `transaccion_externa_id`, `orden_checkout_id`, `monto`, `moneda`,
                `estado_intento`, `codigo_respuesta_pasarela`, `mensaje_respuesta_pasarela`,
                `payload_solicitud_sanitizado_json`, `payload_respuesta_sanitizado_json`,
                `tarjeta_marca`, `tarjeta_ultimos_cuatro`, `ip_origen`,
                `firma_webhook_recibida`, `clave_idempotencia_webhook`
            ) VALUES (
                :org_id, :pago_id, :venta_id, :org_pas_id,
                :trans_ext, :orden_chk, :monto, :moneda,
                :estado, :cod_resp, :msj_resp,
                :sol_json, :resp_json,
                :marca, :ultimos4, :ip,
                :firma, :idempotencia_wh
            )
        ");

        $stmt->execute([
            'org_id'          => $intento->organizacionId,
            'pago_id'         => $intento->pagoId,
            'venta_id'        => $intento->ventaId,
            'org_pas_id'      => $intento->organizacionPasarelaId,
            'trans_ext'       => $intento->transaccionExternaId,
            'orden_chk'       => $intento->ordenCheckoutId,
            'monto'           => $intento->monto,
            'moneda'          => $intento->moneda,
            'estado'          => $intento->estadoIntento->value,
            'cod_resp'        => $intento->codigoRespuestaPasarela,
            'msj_resp'        => $intento->mensajeRespuestaPasarela,
            'sol_json'        => $intento->payloadSolicitudSanitizado ? json_encode($intento->payloadSolicitudSanitizado) : null,
            'resp_json'       => $intento->payloadRespuestaSanitizado ? json_encode($intento->payloadRespuestaSanitizado) : null,
            'marca'           => $intento->tarjetaMarca,
            'ultimos4'        => $intento->tarjetaUltimosCuatro,
            'ip'              => $intento->ipOrigen,
            'firma'           => $intento->firmaWebhookRecibida,
            'idempotencia_wh' => $intento->claveIdempotenciaWebhook,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarIntentoPorIdempotencia(int $organizacionId, string $claveIdempotencia): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `pago_intentos_pasarela`
            WHERE `organizacion_id` = :org_id
              AND `clave_idempotencia_webhook` = :clave
            LIMIT 1
        ");
        $stmt->execute([
            'org_id' => $organizacionId,
            'clave'  => $claveIdempotencia,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Listado paginado de pagos con filtros múltiples.
     *
     * @param int $organizacionId
     * @param int|null $edicionId
     * @param array $filtros
     * @param int $pagina
     * @param int $porPagina
     * @return array{items: Pago[], total: int, pagina: int, por_pagina: int, total_paginas: int}
     */
    public function listarPaginado(
        int $organizacionId,
        ?int $edicionId = null,
        array $filtros = [],
        int $pagina = 1,
        int $porPagina = 20
    ): array {
        $pagina = max(1, $pagina);
        $porPagina = max(1, min(100, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $where = ["p.`organizacion_id` = :org_id"];
        $params = ['org_id' => $organizacionId];

        if ($edicionId !== null && $edicionId > 0) {
            $where[] = "p.`edicion_id` = :edicion_id";
            $params['edicion_id'] = $edicionId;
        }

        if (!empty($filtros['venta_id'])) {
            $where[] = "p.`venta_id` = :venta_id";
            $params['venta_id'] = (int) $filtros['venta_id'];
        }

        if (!empty($filtros['estado'])) {
            $where[] = "p.`estado` = :estado";
            $params['estado'] = $filtros['estado'] instanceof EstadoPago ? $filtros['estado']->value : (string) $filtros['estado'];
        }

        if (!empty($filtros['metodo_pago'])) {
            $where[] = "p.`metodo_pago` = :metodo_pago";
            $params['metodo_pago'] = $filtros['metodo_pago'] instanceof MetodoPago ? $filtros['metodo_pago']->value : (string) $filtros['metodo_pago'];
        }

        if (!empty($filtros['cuenta_bancaria_id'])) {
            $where[] = "p.`cuenta_bancaria_id` = :cuenta_bancaria_id";
            $params['cuenta_bancaria_id'] = (int) $filtros['cuenta_bancaria_id'];
        }

        if (!empty($filtros['organizacion_pasarela_id'])) {
            $where[] = "p.`organizacion_pasarela_id` = :org_pas_id";
            $params['org_pas_id'] = (int) $filtros['organizacion_pasarela_id'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $where[] = "DATE(p.`fecha_pago`) >= :fecha_desde";
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $where[] = "DATE(p.`fecha_pago`) <= :fecha_hasta";
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }

        if (!empty($filtros['busqueda'])) {
            $where[] = "(p.`correlativo` LIKE :busq OR p.`numero_operacion_bancaria` LIKE :busq OR v.`correlativo` LIKE :busq)";
            $params['busq'] = '%' . trim((string) $filtros['busqueda']) . '%';
        }

        $clausulaWhere = implode(' AND ', $where);

        $sqlCount = "
            SELECT COUNT(*)
            FROM `pagos` p
            INNER JOIN `ventas` v ON v.id = p.venta_id
            WHERE {$clausulaWhere}
        ";
        $stmtCount = $this->pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $sqlItems = "
            SELECT p.*, v.correlativo AS venta_correlativo,
                   cb.banco_nombre AS banco_nombre,
                   pas.nombre AS pasarela_nombre
            FROM `pagos` p
            INNER JOIN `ventas` v ON v.id = p.venta_id
            LEFT JOIN `cuentas_bancarias_organizacion` cb ON cb.id = p.cuenta_bancaria_id
            LEFT JOIN `organizacion_pasarelas` op ON op.id = p.organizacion_pasarela_id
            LEFT JOIN `pasarelas_pago` pas ON pas.id = op.pasarela_id
            WHERE {$clausulaWhere}
            ORDER BY p.`id` DESC
            LIMIT {$porPagina} OFFSET {$offset}
        ";
        $stmtItems = $this->pdo->prepare($sqlItems);
        $stmtItems->execute($params);
        $filas = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(fn($f) => $this->hidratar($f), $filas);

        return [
            'items'         => $items,
            'total'         => $total,
            'pagina'        => $pagina,
            'por_pagina'    => $porPagina,
            'total_paginas' => $total > 0 ? (int) ceil($total / $porPagina) : 1,
        ];
    }

    private function hidratar(array $f): Pago
    {
        return new Pago(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            edicionId: (int) $f['edicion_id'],
            ventaId: (int) $f['venta_id'],
            correlativo: (string) $f['correlativo'],
            metodoPago: MetodoPago::from($f['metodo_pago']),
            estado: EstadoPago::from($f['estado']),
            moneda: (string) $f['moneda'],
            montoCobradoCliente: (float) $f['monto_cobrado_cliente'],
            comisionPorcentajeAplicada: (float) $f['comision_porcentaje_aplicada'],
            comisionFijaAplicada: (float) $f['comision_fija_aplicada'],
            comisionPasarela: (float) $f['comision_pasarela'],
            montoNetoRecibido: (float) $f['monto_neto_recibido'],
            montoAplicadoVenta: (float) $f['monto_aplicado_venta'],
            montoExcedente: (float) $f['monto_excedente'],
            comisionAsumidaPor: AsumeComisionPasarela::from($f['comision_asumida_por']),
            montoReembolsadoAcumulado: (float) $f['monto_reembolsado_acumulado'],
            fechaPago: (string) $f['fecha_pago'],
            cuentaBancariaId: $f['cuenta_bancaria_id'] !== null ? (int) $f['cuenta_bancaria_id'] : null,
            organizacionPasarelaId: $f['organizacion_pasarela_id'] !== null ? (int) $f['organizacion_pasarela_id'] : null,
            numeroOperacionBancaria: $f['numero_operacion_bancaria'],
            boucherComprobanteUrl: $f['boucher_comprobante_url'],
            notasOperativas: $f['notas_operativas'],
            claveIdempotencia: $f['clave_idempotencia'],
            versionBloqueo: (int) $f['version_bloqueo'],
            verificadoPor: $f['verificado_por'] !== null ? (int) $f['verificado_por'] : null,
            verificadoEn: $f['verificado_en'],
            creadoPor: (int) $f['creado_por'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en'],
            ventaCorrelativo: $f['venta_correlativo'] ?? null,
            bancoNombre: $f['banco_nombre'] ?? null,
            pasarelaNombre: $f['pasarela_nombre'] ?? null
        );
    }
}
