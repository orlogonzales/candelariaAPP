<?php

declare(strict_types=1);

namespace Aplicacion\Dashboard;

use Aplicacion\Documentos\MotorPdfServicio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use PDO;
use RuntimeException;

/**
 * Servicio de Dominio Oficial para Analítica Multianual y Comparativa Histórica (Fase 4.1 — DT-V12-002).
 *
 * Responsabilidades:
 * 1. Serie financiera multianual por edición (devengado, cobrado bruto, comisiones, neto, reembolsos,
 *    saldos pendientes, anulaciones/cancelaciones, ticket promedio y % de cobranza) calculada en centavos.
 * 2. Variaciones interanuales (YoY) absolutas y porcentuales seguras ante base cero.
 * 3. Evolución de reservas, participantes y tasas de conversión/cancelación.
 * 4. Curvas de preventa (Booking Pace) en hitos relativos a la fecha de inicio de cada edición
 *    (T-90, T-60, T-30, T-15, T-7, T-0 y cierre final).
 * 5. Retención y recurrencia de clientes (clientes nuevos vs recurrentes y matriz de cohortes entre ediciones).
 * 6. Participación folclórica multianual (conjuntos inscritos, danzarines estimados, distribución por género/categoría,
 *    turnos oficiales y retraso promedio).
 * 7. Indicadores operativos, asistencia QR (online + sincronización offline) y postproducción/selección fotográfica.
 * 8. Exportaciones institucionales en CSV (con blindaje anti-Formula Injection), XLSX (OpenXML ECMA-376) y PDF (Dompdf).
 *
 * Garantías arquitectónicas (DEC-01):
 * - Opera exclusivamente sobre las 108 tablas relacionales existentes sin tablas adicionales.
 * - Cero consultas N+1: todas las dimensiones se obtienen mediante agregaciones SQL agrupadas por edicion_id.
 * - Aislamiento estricto multi-tenant por organizacion_id.
 */
class AnaliticaMultianualServicio
{
    private PDO $pdo;
    private MotorPdfServicio $motorPdf;

    /**
     * Ventanas relativas en días respecto a ediciones_candelaria.fecha_inicio para curvas de preventa.
     */
    private const HITOS_PREVENTA = [
        ['codigo' => 'T-90',  'etiqueta' => '90 días antes (T-90)', 'dias_offset' => -90],
        ['codigo' => 'T-60',  'etiqueta' => '60 días antes (T-60)', 'dias_offset' => -60],
        ['codigo' => 'T-30',  'etiqueta' => '30 días antes (T-30)', 'dias_offset' => -30],
        ['codigo' => 'T-15',  'etiqueta' => '15 días antes (T-15)', 'dias_offset' => -15],
        ['codigo' => 'T-7',   'etiqueta' => '7 días antes (T-7)',   'dias_offset' => -7],
        ['codigo' => 'T-0',   'etiqueta' => 'Inicio festividad (T-0)', 'dias_offset' => 0],
        ['codigo' => 'FINAL', 'etiqueta' => 'Cierre / Acumulado Total', 'dias_offset' => null],
    ];

    public function __construct(?PDO $pdo = null, ?MotorPdfServicio $motorPdf = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->motorPdf = $motorPdf ?? new MotorPdfServicio();
    }

    /**
     * Obtiene el informe analítico multianual completo para una organización y los filtros indicados.
     *
     * Filtros soportados:
     * - edicion_ids: array<int>|string (lista de IDs separados por coma o arreglo de enteros)
     * - anio_desde: int|null
     * - anio_hasta: int|null
     * - estado_edicion: string|null (PREOPERACION, OPERACION, POSTPRODUCCION_ENTREGA, CERRADA)
     * - canal_origen: string|null (PORTAL_PUBLICO, BACKOFFICE, COTIZACION, WHATSAPP, etc.)
     * - tipo_danza_id: int|null
     */
    public function obtenerAnaliticaMultianual(int $organizacionId, array $filtros = []): array
    {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException('El identificador de organización es inválido.');
        }

        $filtrosNormalizados = $this->normalizarFiltros($filtros);
        $ediciones = $this->obtenerEdicionesFiltradas($organizacionId, $filtrosNormalizados);

        if (empty($ediciones)) {
            return $this->generarEstructuraVacia($organizacionId, $filtrosNormalizados);
        }

        // Ediciones ordenadas cronológicamente (anio ASC, id ASC) para cálculos YoY, cohortes y series
        $edicionIds = array_map( static fn(array $e): int => (int) $e['id'], $ediciones);

        // 1. Agregaciones SQL por lote (cero N+1)
        $ventasPorEdicion = $this->consultarAgregadosVentas($organizacionId, $edicionIds, $filtrosNormalizados);
        $pagosPorEdicion = $this->consultarAgregadosPagos($organizacionId, $edicionIds, $filtrosNormalizados);
        $reembolsosPorEdicion = $this->consultarAgregadosReembolsos($organizacionId, $edicionIds, $filtrosNormalizados);
        $canalesPorEdicion = $this->consultarDistribucionCanalesPorEdicion($organizacionId, $edicionIds, $filtrosNormalizados);
        $cierresPorEdicion = $this->consultarActasCierrePorEdicion($organizacionId, $edicionIds);

        // 2. Construir Serie Financiera + Variaciones YoY en centavos
        $serieFinanciera = $this->construirSerieFinanciera(
            $ediciones,
            $ventasPorEdicion,
            $pagosPorEdicion,
            $reembolsosPorEdicion,
            $canalesPorEdicion,
            $cierresPorEdicion
        );

        // 3. Construir Serie de Reservas, Participantes y Conversión + YoY
        $serieReservas = $this->construirSerieReservas($organizacionId, $ediciones, $edicionIds);

        // 4. Construir Curvas de Preventa (Booking Pace) relativas a fecha_inicio
        $curvasPreventa = $this->construirCurvasPreventa($organizacionId, $ediciones, $edicionIds, $filtrosNormalizados);

        // 5. Construir Retención de Clientes y Matriz de Cohortes
        $retencionClientes = $this->construirRetencionYCohortes($organizacionId, $ediciones, $edicionIds, $filtrosNormalizados);

        // 6. Construir Serie de Participación Folclórica Multianual
        $serieFolclore = $this->construirSerieFolclore($organizacionId, $ediciones, $edicionIds, $filtrosNormalizados);

        // 7. Construir Serie de Operaciones, Asistencia QR (Online + Offline) y Postproducción
        $serieOperacionesPost = $this->construirSerieOperacionesYPostproduccion($organizacionId, $ediciones, $edicionIds);

        // 8. Construir Resumen Ejecutivo Consolidado Multi-Edición
        $resumenConsolidado = $this->construirResumenConsolidado(
            $serieFinanciera,
            $serieReservas,
            $retencionClientes,
            $serieFolclore,
            $serieOperacionesPost
        );

        return [
            'organizacion_id'                  => $organizacionId,
            'generado_en'                      => date('Y-m-d H:i:s'),
            'filtros_aplicados'                => $filtrosNormalizados,
            'ediciones_evaluadas'              => array_map(static fn(array $e): array => [
                'id'           => (int) $e['id'],
                'codigo'       => (string) $e['codigo'],
                'nombre'       => (string) $e['nombre'],
                'anio'         => (int) $e['anio'],
                'estado'       => (string) $e['estado'],
                'fecha_inicio' => (string) $e['fecha_inicio'],
                'fecha_fin'    => (string) $e['fecha_fin'],
                'es_actual'    => (bool) $e['es_actual'],
            ], $ediciones),
            'resumen_consolidado'              => $resumenConsolidado,
            'serie_financiera'                 => $serieFinanciera,
            'serie_reservas'                   => $serieReservas,
            'curvas_preventa'                  => $curvasPreventa,
            'retencion_clientes'               => $retencionClientes,
            'serie_folclore'                   => $serieFolclore,
            'serie_operaciones_postproduccion' => $serieOperacionesPost,
        ];
    }

    // ==============================================================================
    // 1. FILTRADO Y RESOLUCIÓN DE EDICIONES
    // ==============================================================================

    private function normalizarFiltros(array $filtros): array
    {
        $edicionIds = [];
        if (!empty($filtros['edicion_ids'])) {
            $rawIds = is_array($filtros['edicion_ids'])
                ? $filtros['edicion_ids']
                : explode(',', (string) $filtros['edicion_ids']);
            foreach ($rawIds as $id) {
                $val = (int) trim((string) $id);
                if ($val > 0) {
                    $edicionIds[] = $val;
                }
            }
            $edicionIds = array_values(array_unique($edicionIds));
        }

        $anioDesde = isset($filtros['anio_desde']) && $filtros['anio_desde'] !== '' ? (int) $filtros['anio_desde'] : null;
        $anioHasta = isset($filtros['anio_hasta']) && $filtros['anio_hasta'] !== '' ? (int) $filtros['anio_hasta'] : null;

        $estadosValidos = ['PREOPERACION', 'OPERACION', 'POSTPRODUCCION_ENTREGA', 'CERRADA'];
        $estadoEdicion = isset($filtros['estado_edicion']) && in_array($filtros['estado_edicion'], $estadosValidos, true)
            ? (string) $filtros['estado_edicion']
            : null;

        $canalOrigen = isset($filtros['canal_origen']) && trim((string) $filtros['canal_origen']) !== ''
            ? strtoupper(trim((string) $filtros['canal_origen']))
            : null;

        $tipoDanzaId = isset($filtros['tipo_danza_id']) && (int) $filtros['tipo_danza_id'] > 0
            ? (int) $filtros['tipo_danza_id']
            : null;

        return [
            'edicion_ids'    => $edicionIds,
            'anio_desde'     => $anioDesde,
            'anio_hasta'     => $anioHasta,
            'estado_edicion' => $estadoEdicion,
            'canal_origen'   => $canalOrigen,
            'tipo_danza_id'  => $tipoDanzaId,
        ];
    }

    private function obtenerEdicionesFiltradas(int $organizacionId, array $filtros): array
    {
        $sql = "
            SELECT id, organizacion_id, codigo, nombre, anio, fecha_inicio, fecha_fin, descripcion, estado, es_actual
            FROM `ediciones_candelaria`
            WHERE `organizacion_id` = :org_id
        ";
        $params = ['org_id' => $organizacionId];

        if (!empty($filtros['edicion_ids'])) {
            $placeholders = [];
            foreach ($filtros['edicion_ids'] as $idx => $edId) {
                $key = "ed_id_{$idx}";
                $placeholders[] = ":{$key}";
                $params[$key] = $edId;
            }
            $sql .= " AND `id` IN (" . implode(', ', $placeholders) . ")";
        }

        if ($filtros['anio_desde'] !== null) {
            $sql .= " AND `anio` >= :anio_desde";
            $params['anio_desde'] = $filtros['anio_desde'];
        }

        if ($filtros['anio_hasta'] !== null) {
            $sql .= " AND `anio` <= :anio_hasta";
            $params['anio_hasta'] = $filtros['anio_hasta'];
        }

        if ($filtros['estado_edicion'] !== null) {
            $sql .= " AND `estado` = :estado_edicion";
            $params['estado_edicion'] = $filtros['estado_edicion'];
        }

        $sql .= " ORDER BY `anio` ASC, `id` ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==============================================================================
    // 2. CONSULTAS AGREGADAS FINANCIERAS (EN CENTAVOS, CERO N+1)
    // ==============================================================================

    private function consultarAgregadosVentas(int $organizacionId, array $edicionIds, array $filtros): array
    {
        [$inSql, $params] = $this->construirClausulaIn('v.edicion_id', $edicionIds, ['org_id' => $organizacionId]);

        $filtroCanalSql = '';
        if ($filtros['canal_origen'] !== null) {
            $filtroCanalSql = ' AND v.origen_tipo = :canal_origen';
            $params['canal_origen'] = $filtros['canal_origen'];
        }

        $sql = "
            SELECT
                v.edicion_id,
                COUNT(*) AS ventas_registradas_qty,
                COALESCE(SUM(CASE WHEN v.estado IN ('CONFIRMADA', 'LIQUIDADA') THEN 1 ELSE 0 END), 0) AS ventas_activas_qty,
                COALESCE(SUM(CASE WHEN v.estado = 'CANCELADA' THEN 1 ELSE 0 END), 0) AS ventas_canceladas_qty,
                COALESCE(SUM(CASE WHEN v.estado = 'ANULADA' THEN 1 ELSE 0 END), 0) AS ventas_anuladas_qty,
                COALESCE(SUM(CASE WHEN v.estado IN ('CONFIRMADA', 'LIQUIDADA') THEN ROUND(v.total * 100) ELSE 0 END), 0) AS devengado_centavos,
                COALESCE(SUM(CASE WHEN v.estado IN ('CONFIRMADA', 'LIQUIDADA') THEN ROUND(v.saldo_pendiente * 100) ELSE 0 END), 0) AS saldo_pendiente_centavos,
                COALESCE(SUM(CASE WHEN v.estado IN ('CANCELADA', 'ANULADA') THEN ROUND(v.total * 100) ELSE 0 END), 0) AS anulado_cancelado_centavos,
                COALESCE(SUM(CASE WHEN v.estado IN ('CONFIRMADA', 'LIQUIDADA') AND v.estado_financiero = 'PAGADA_TOTAL' THEN 1 ELSE 0 END), 0) AS ventas_pagadas_total_qty,
                COALESCE(SUM(CASE WHEN v.estado IN ('CONFIRMADA', 'LIQUIDADA') AND v.estado_financiero = 'PAGO_PARCIAL' THEN 1 ELSE 0 END), 0) AS ventas_pago_parcial_qty,
                COALESCE(SUM(CASE WHEN v.estado IN ('CONFIRMADA', 'LIQUIDADA') AND v.estado_financiero = 'NO_PAGADA' THEN 1 ELSE 0 END), 0) AS ventas_no_pagadas_qty
            FROM `ventas` v
            WHERE v.organizacion_id = :org_id
              AND {$inSql}
              {$filtroCanalSql}
            GROUP BY v.edicion_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f['edicion_id']] = $f;
        }
        return $mapa;
    }

    private function consultarAgregadosPagos(int $organizacionId, array $edicionIds, array $filtros): array
    {
        [$inSql, $params] = $this->construirClausulaIn('p.edicion_id', $edicionIds, ['org_id' => $organizacionId]);

        $joinCanalSql = '';
        $filtroCanalSql = '';
        if ($filtros['canal_origen'] !== null) {
            $joinCanalSql = ' INNER JOIN `ventas` v ON v.id = p.venta_id AND v.organizacion_id = p.organizacion_id';
            $filtroCanalSql = ' AND v.origen_tipo = :canal_origen';
            $params['canal_origen'] = $filtros['canal_origen'];
        }

        $sql = "
            SELECT
                p.edicion_id,
                COUNT(*) AS transacciones_pago_qty,
                COALESCE(SUM(CASE WHEN p.estado = 'APROBADO' THEN 1 ELSE 0 END), 0) AS pagos_aprobados_qty,
                COALESCE(SUM(CASE WHEN p.estado = 'PENDIENTE_VERIFICACION' THEN 1 ELSE 0 END), 0) AS pagos_pendientes_qty,
                COALESCE(SUM(CASE WHEN p.estado = 'RECHAZADO' THEN 1 ELSE 0 END), 0) AS pagos_rechazados_qty,
                COALESCE(SUM(CASE WHEN p.estado = 'APROBADO' THEN ROUND(p.monto_cobrado_cliente * 100) ELSE 0 END), 0) AS cobrado_bruto_centavos,
                COALESCE(SUM(CASE WHEN p.estado = 'APROBADO' THEN ROUND(p.comision_pasarela * 100) ELSE 0 END), 0) AS comisiones_pasarela_centavos,
                COALESCE(SUM(CASE WHEN p.estado = 'APROBADO' THEN ROUND(p.monto_neto_recibido * 100) ELSE 0 END), 0) AS recaudacion_neta_centavos,
                COALESCE(SUM(CASE WHEN p.estado = 'APROBADO' THEN ROUND(p.monto_reembolsado_acumulado * 100) ELSE 0 END), 0) AS reembolsado_acumulado_centavos
            FROM `pagos` p
            {$joinCanalSql}
            WHERE p.organizacion_id = :org_id
              AND {$inSql}
              {$filtroCanalSql}
            GROUP BY p.edicion_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f['edicion_id']] = $f;
        }
        return $mapa;
    }

    private function consultarAgregadosReembolsos(int $organizacionId, array $edicionIds, array $filtros): array
    {
        [$inSql, $params] = $this->construirClausulaIn('p.edicion_id', $edicionIds, ['org_id' => $organizacionId]);

        $joinCanalSql = '';
        $filtroCanalSql = '';
        if ($filtros['canal_origen'] !== null) {
            $joinCanalSql = ' INNER JOIN `ventas` v ON v.id = p.venta_id AND v.organizacion_id = p.organizacion_id';
            $filtroCanalSql = ' AND v.origen_tipo = :canal_origen';
            $params['canal_origen'] = $filtros['canal_origen'];
        }

        $sql = "
            SELECT
                p.edicion_id,
                COUNT(r.id) AS reembolsos_ejecutados_qty,
                COALESCE(SUM(ROUND(r.monto_reembolsado * 100)), 0) AS monto_reembolsos_tabla_centavos
            FROM `pago_reembolsos` r
            INNER JOIN `pagos` p ON p.id = r.pago_id AND p.organizacion_id = r.organizacion_id
            {$joinCanalSql}
            WHERE r.organizacion_id = :org_id
              AND r.estado = 'EJECUTADO'
              AND {$inSql}
              {$filtroCanalSql}
            GROUP BY p.edicion_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f['edicion_id']] = $f;
        }
        return $mapa;
    }

    private function consultarDistribucionCanalesPorEdicion(int $organizacionId, array $edicionIds, array $filtros): array
    {
        [$inSql, $params] = $this->construirClausulaIn('v.edicion_id', $edicionIds, ['org_id' => $organizacionId]);

        $filtroCanalSql = '';
        if ($filtros['canal_origen'] !== null) {
            $filtroCanalSql = ' AND v.origen_tipo = :canal_origen';
            $params['canal_origen'] = $filtros['canal_origen'];
        }

        $sql = "
            SELECT
                v.edicion_id,
                v.origen_tipo AS canal,
                COUNT(*) AS cantidad_ventas,
                COALESCE(SUM(ROUND(v.total * 100)), 0) AS devengado_centavos
            FROM `ventas` v
            WHERE v.organizacion_id = :org_id
              AND v.estado IN ('CONFIRMADA', 'LIQUIDADA')
              AND {$inSql}
              {$filtroCanalSql}
            GROUP BY v.edicion_id, v.origen_tipo
            ORDER BY v.edicion_id ASC, devengado_centavos DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapa = [];
        foreach ($filas as $f) {
            $edId = (int) $f['edicion_id'];
            $mapa[$edId][] = [
                'canal'              => (string) $f['canal'],
                'cantidad_ventas'    => (int) $f['cantidad_ventas'],
                'devengado_centavos' => (int) $f['devengado_centavos'],
                'volumen_devengado'  => $this->centavosASoles((int) $f['devengado_centavos']),
            ];
        }
        return $mapa;
    }

    private function consultarActasCierrePorEdicion(int $organizacionId, array $edicionIds): array
    {
        [$inSql, $params] = $this->construirClausulaIn('c.edicion_id', $edicionIds, ['org_id' => $organizacionId]);

        $sql = "
            SELECT c.edicion_id, c.id AS cierre_id, c.tipo_cierre, c.estado, c.fecha_cierre, c.hash_integridad_cierre
            FROM `edicion_cierres` c
            WHERE c.organizacion_id = :org_id
              AND c.estado = 'VIGENTE'
              AND {$inSql}
            ORDER BY c.id DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapa = [];
        foreach ($filas as $f) {
            $edId = (int) $f['edicion_id'];
            if (!isset($mapa[$edId])) {
                $mapa[$edId] = $f;
            }
        }
        return $mapa;
    }

    private function construirSerieFinanciera(
        array $ediciones,
        array $ventasPorEdicion,
        array $pagosPorEdicion,
        array $reembolsosPorEdicion,
        array $canalesPorEdicion,
        array $cierresPorEdicion
    ): array {
        $serie = [];
        $previa = null;

        foreach ($ediciones as $edicion) {
            $edId = (int) $edicion['id'];
            $v = $ventasPorEdicion[$edId] ?? [];
            $p = $pagosPorEdicion[$edId] ?? [];
            $r = $reembolsosPorEdicion[$edId] ?? [];
            $cierre = $cierresPorEdicion[$edId] ?? null;

            $ventasRegQty = (int) ($v['ventas_registradas_qty'] ?? 0);
            $ventasActQty = (int) ($v['ventas_activas_qty'] ?? 0);
            $ventasCancQty = (int) ($v['ventas_canceladas_qty'] ?? 0);
            $ventasAnulQty = (int) ($v['ventas_anuladas_qty'] ?? 0);

            $devengadoCent = (int) ($v['devengado_centavos'] ?? 0);
            $saldoPendienteCent = (int) ($v['saldo_pendiente_centavos'] ?? 0);
            $anuladoCanceladoCent = (int) ($v['anulado_cancelado_centavos'] ?? 0);

            $cobradoBrutoCent = (int) ($p['cobrado_bruto_centavos'] ?? 0);
            $comisionesCent = (int) ($p['comisiones_pasarela_centavos'] ?? 0);
            $recaudacionNetaCent = (int) ($p['recaudacion_neta_centavos'] ?? 0);

            $reembolsosPagosCent = (int) ($p['reembolsado_acumulado_centavos'] ?? 0);
            $reembolsosTablaCent = (int) ($r['monto_reembolsos_tabla_centavos'] ?? 0);
            $reembolsosCent = max($reembolsosPagosCent, $reembolsosTablaCent);

            $ticketPromedioCent = $ventasActQty > 0 ? (int) round($devengadoCent / $ventasActQty) : 0;
            $pctCobranza = $devengadoCent > 0 ? round(($cobradoBrutoCent / $devengadoCent) * 100, 2) : 0.00;
            $pctSaldoPendiente = $devengadoCent > 0 ? round(($saldoPendienteCent / $devengadoCent) * 100, 2) : 0.00;

            // Distribución por canal con porcentajes exactos
            $canalesEdicion = $canalesPorEdicion[$edId] ?? [];
            foreach ($canalesEdicion as &$cItem) {
                $cItem['porcentaje_volumen'] = $devengadoCent > 0
                    ? round(($cItem['devengado_centavos'] / $devengadoCent) * 100, 2)
                    : 0.00;
            }
            unset($cItem);

            // Cálculo de variación interanual YoY frente a la edición inmediatamente anterior
            $variacionYoy = $this->calcularVariacionFinancieraYoy(
                $previa,
                $devengadoCent,
                $recaudacionNetaCent,
                $cobradoBrutoCent,
                $ventasActQty,
                $ticketPromedioCent
            );

            $filaActual = [
                'edicion_id'                     => $edId,
                'codigo'                         => (string) $edicion['codigo'],
                'nombre'                         => (string) $edicion['nombre'],
                'anio'                           => (int) $edicion['anio'],
                'estado'                         => (string) $edicion['estado'],
                'fecha_inicio'                   => (string) $edicion['fecha_inicio'],
                'fecha_fin'                      => (string) $edicion['fecha_fin'],
                'es_actual'                      => (bool) $edicion['es_actual'],
                'tiene_acta_cierre'              => $cierre !== null,
                'acta_cierre'                    => $cierre ? [
                    'cierre_id'              => (int) $cierre['cierre_id'],
                    'tipo_cierre'            => (string) $cierre['tipo_cierre'],
                    'fecha_cierre'           => (string) $cierre['fecha_cierre'],
                    'hash_integridad_cierre' => (string) $cierre['hash_integridad_cierre'],
                ] : null,
                // Conteos de ventas
                'ventas_registradas_qty'         => $ventasRegQty,
                'ventas_activas_qty'             => $ventasActQty,
                'ventas_canceladas_qty'          => $ventasCancQty,
                'ventas_anuladas_qty'            => $ventasAnulQty,
                'ventas_pagadas_total_qty'       => (int) ($v['ventas_pagadas_total_qty'] ?? 0),
                'ventas_pago_parcial_qty'        => (int) ($v['ventas_pago_parcial_qty'] ?? 0),
                'ventas_no_pagadas_qty'          => (int) ($v['ventas_no_pagadas_qty'] ?? 0),
                'pagos_aprobados_qty'            => (int) ($p['pagos_aprobados_qty'] ?? 0),
                'pagos_pendientes_qty'           => (int) ($p['pagos_pendientes_qty'] ?? 0),
                // Importes exactos en centavos (enteros)
                'devengado_centavos'             => $devengadoCent,
                'cobrado_bruto_centavos'         => $cobradoBrutoCent,
                'comisiones_pasarela_centavos'   => $comisionesCent,
                'recaudacion_neta_centavos'      => $recaudacionNetaCent,
                'reembolsos_centavos'            => $reembolsosCent,
                'saldo_pendiente_centavos'       => $saldoPendienteCent,
                'anulado_cancelado_centavos'     => $anuladoCanceladoCent,
                'ticket_promedio_centavos'       => $ticketPromedioCent,
                // Importes expresados en moneda (PEN) con 2 decimales
                'total_devengado'                => $this->centavosASoles($devengadoCent),
                'total_cobrado_cliente'          => $this->centavosASoles($cobradoBrutoCent),
                'total_comisiones_pasarela'      => $this->centavosASoles($comisionesCent),
                'total_neto_recaudado'           => $this->centavosASoles($recaudacionNetaCent),
                'total_reembolsos'               => $this->centavosASoles($reembolsosCent),
                'total_saldo_pendiente'          => $this->centavosASoles($saldoPendienteCent),
                'total_anulado_cancelado'        => $this->centavosASoles($anuladoCanceladoCent),
                'ticket_promedio'                => $this->centavosASoles($ticketPromedioCent),
                // Indicadores porcentuales
                'porcentaje_cobranza'            => $pctCobranza,
                'porcentaje_saldo_pendiente'     => $pctSaldoPendiente,
                // Canales y Variación YoY
                'canales'                        => $canalesEdicion,
                'variacion_yoy'                  => $variacionYoy,
            ];

            $serie[] = $filaActual;
            $previa = $filaActual;
        }

        return $serie;
    }

    private function calcularVariacionFinancieraYoy(
        ?array $previa,
        int $devengadoCent,
        int $netoCent,
        int $cobradoCent,
        int $ventasActQty,
        int $ticketCent
    ): array {
        if ($previa === null) {
            return [
                'disponible'               => false,
                'edicion_base_id'          => null,
                'edicion_base_codigo'      => null,
                'edicion_base_anio'        => null,
                'delta_devengado_centavos' => 0,
                'delta_devengado'          => 0.00,
                'delta_devengado_pct'      => null,
                'tendencia_devengado'      => 'PRIMERA_EDICION',
                'delta_neto_centavos'      => 0,
                'delta_neto'               => 0.00,
                'delta_neto_pct'           => null,
                'delta_cobrado_centavos'   => 0,
                'delta_cobrado'            => 0.00,
                'delta_cobrado_pct'        => null,
                'delta_ventas_qty'         => 0,
                'delta_ventas_pct'         => null,
                'delta_ticket_centavos'    => 0,
                'delta_ticket'             => 0.00,
                'delta_ticket_pct'         => null,
            ];
        }

        $baseDevCent = (int) $previa['devengado_centavos'];
        $baseNetoCent = (int) $previa['recaudacion_neta_centavos'];
        $baseCobCent = (int) $previa['cobrado_bruto_centavos'];
        $baseVentasQty = (int) $previa['ventas_activas_qty'];
        $baseTicketCent = (int) $previa['ticket_promedio_centavos'];

        $deltaDevCent = $devengadoCent - $baseDevCent;
        $deltaNetoCent = $netoCent - $baseNetoCent;
        $deltaCobCent = $cobradoCent - $baseCobCent;
        $deltaVentasQty = $ventasActQty - $baseVentasQty;
        $deltaTicketCent = $ticketCent - $baseTicketCent;

        $deltaDevPct = $baseDevCent > 0 ? round(($deltaDevCent / $baseDevCent) * 100, 2) : null;
        $deltaNetoPct = $baseNetoCent > 0 ? round(($deltaNetoCent / $baseNetoCent) * 100, 2) : null;
        $deltaCobPct = $baseCobCent > 0 ? round(($deltaCobCent / $baseCobCent) * 100, 2) : null;
        $deltaVentasPct = $baseVentasQty > 0 ? round(($deltaVentasQty / $baseVentasQty) * 100, 2) : null;
        $deltaTicketPct = $baseTicketCent > 0 ? round(($deltaTicketCent / $baseTicketCent) * 100, 2) : null;

        $tendencia = 'ESTABLE';
        if ($baseDevCent === 0 && $devengadoCent > 0) {
            $tendencia = 'NUEVA_ACTIVIDAD';
        } elseif ($deltaDevCent > 0) {
            $tendencia = 'CRECIMIENTO';
        } elseif ($deltaDevCent < 0) {
            $tendencia = 'CAIDA';
        }

        return [
            'disponible'               => true,
            'edicion_base_id'          => (int) $previa['edicion_id'],
            'edicion_base_codigo'      => (string) $previa['codigo'],
            'edicion_base_anio'        => (int) $previa['anio'],
            'delta_devengado_centavos' => $deltaDevCent,
            'delta_devengado'          => $this->centavosASoles($deltaDevCent),
            'delta_devengado_pct'      => $deltaDevPct,
            'tendencia_devengado'      => $tendencia,
            'delta_neto_centavos'      => $deltaNetoCent,
            'delta_neto'               => $this->centavosASoles($deltaNetoCent),
            'delta_neto_pct'           => $deltaNetoPct,
            'delta_cobrado_centavos'   => $deltaCobCent,
            'delta_cobrado'            => $this->centavosASoles($deltaCobCent),
            'delta_cobrado_pct'        => $deltaCobPct,
            'delta_ventas_qty'         => $deltaVentasQty,
            'delta_ventas_pct'         => $deltaVentasPct,
            'delta_ticket_centavos'    => $deltaTicketCent,
            'delta_ticket'             => $this->centavosASoles($deltaTicketCent),
            'delta_ticket_pct'         => $deltaTicketPct,
        ];
    }

    // ==============================================================================
    // 3. EVOLUCIÓN DE RESERVAS, PARTICIPANTES Y CONVERSIÓN
    // ==============================================================================

    private function construirSerieReservas(int $organizacionId, array $ediciones, array $edicionIds): array
    {
        [$inSql, $params] = $this->construirClausulaIn('r.edicion_id', $edicionIds, ['org_id' => $organizacionId]);

        $sqlRes = "
            SELECT
                r.edicion_id,
                COUNT(*) AS total_reservas,
                COALESCE(SUM(CASE WHEN r.estado IN ('CONFIRMADA', 'REGISTRADA', 'PENDIENTE_DATOS') THEN 1 ELSE 0 END), 0) AS reservas_activas,
                COALESCE(SUM(CASE WHEN r.estado = 'CONFIRMADA' THEN 1 ELSE 0 END), 0) AS reservas_confirmadas,
                COALESCE(SUM(CASE WHEN r.estado IN ('REGISTRADA', 'PENDIENTE_DATOS') THEN 1 ELSE 0 END), 0) AS reservas_registradas,
                COALESCE(SUM(CASE WHEN r.estado = 'CANCELADA' THEN 1 ELSE 0 END), 0) AS reservas_canceladas
            FROM `reservas` r
            WHERE r.organizacion_id = :org_id
              AND {$inSql}
            GROUP BY r.edicion_id
        ";
        $stmtRes = $this->pdo->prepare($sqlRes);
        $stmtRes->execute($params);
        $mapaRes = [];
        foreach ($stmtRes->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapaRes[(int) $f['edicion_id']] = $f;
        }

        // Participantes por edición en reservas activas
        $sqlPart = "
            SELECT
                r.edicion_id,
                COUNT(rp.id) AS participantes_nominados_qty,
                COUNT(DISTINCT r.id) AS reservas_con_nomina_qty
            FROM `reservas` r
            LEFT JOIN `reserva_participantes` rp ON rp.reserva_id = r.id
            WHERE r.organizacion_id = :org_id
              AND r.estado IN ('CONFIRMADA', 'REGISTRADA', 'PENDIENTE_DATOS')
              AND {$inSql}
            GROUP BY r.edicion_id
        ";
        $stmtPart = $this->pdo->prepare($sqlPart);
        $stmtPart->execute($params);
        $mapaPart = [];
        foreach ($stmtPart->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapaPart[(int) $f['edicion_id']] = $f;
        }

        $serie = [];
        $previa = null;

        foreach ($ediciones as $edicion) {
            $edId = (int) $edicion['id'];
            $r = $mapaRes[$edId] ?? [];
            $pt = $mapaPart[$edId] ?? [];

            $totalRes = (int) ($r['total_reservas'] ?? 0);
            $activas = (int) ($r['reservas_activas'] ?? 0);
            $confirmadas = (int) ($r['reservas_confirmadas'] ?? 0);
            $registradas = (int) ($r['reservas_registradas'] ?? 0);
            $canceladas = (int) ($r['reservas_canceladas'] ?? 0);
            $participantes = (int) ($pt['participantes_nominados_qty'] ?? 0);

            // Si hay reservas activas sin filas en reserva_participantes aún, considerar al menos 1 titular por reserva activa
            $totalParticipantesEstimados = max($participantes, $activas);

            $tasaConversion = $totalRes > 0 ? round(($activas / $totalRes) * 100, 2) : 0.00;
            $tasaCancelacion = $totalRes > 0 ? round(($canceladas / $totalRes) * 100, 2) : 0.00;

            $deltaActivas = $previa !== null ? ($activas - (int) $previa['reservas_activas']) : 0;
            $deltaActivasPct = ($previa !== null && (int) $previa['reservas_activas'] > 0)
                ? round(($deltaActivas / (int) $previa['reservas_activas']) * 100, 2)
                : null;

            $fila = [
                'edicion_id'                  => $edId,
                'codigo'                      => (string) $edicion['codigo'],
                'anio'                        => (int) $edicion['anio'],
                'total_reservas'              => $totalRes,
                'reservas_activas'            => $activas,
                'reservas_confirmadas'        => $confirmadas,
                'reservas_registradas'        => $registradas,
                'reservas_canceladas'         => $canceladas,
                'participantes_nominados_qty' => $participantes,
                'participantes_totales_qty'   => $totalParticipantesEstimados,
                'tasa_conversion_pct'         => $tasaConversion,
                'tasa_cancelacion_pct'        => $tasaCancelacion,
                'variacion_yoy'               => [
                    'disponible'         => $previa !== null,
                    'edicion_base_anio'  => $previa !== null ? (int) $previa['anio'] : null,
                    'delta_activas_qty'  => $deltaActivas,
                    'delta_activas_pct'  => $deltaActivasPct,
                ],
            ];

            $serie[] = $fila;
            $previa = $fila;
        }

        return $serie;
    }

    // ==============================================================================
    // 4. CURVAS DE PREVENTA (BOOKING PACE RELATIVO A FECHA_INICIO)
    // ==============================================================================

    private function construirCurvasPreventa(
        int $organizacionId,
        array $ediciones,
        array $edicionIds,
        array $filtros
    ): array {
        [$inSql, $params] = $this->construirClausulaIn('v.edicion_id', $edicionIds, ['org_id' => $organizacionId]);

        $filtroCanalSql = '';
        if ($filtros['canal_origen'] !== null) {
            $filtroCanalSql = ' AND v.origen_tipo = :canal_origen';
            $params['canal_origen'] = $filtros['canal_origen'];
        }

        // Obtenemos las ventas activas agrupadas por edicion_id y fecha_venta
        $sql = "
            SELECT
                v.edicion_id,
                v.fecha_venta,
                COUNT(*) AS ventas_dia_qty,
                COALESCE(SUM(ROUND(v.total * 100)), 0) AS devengado_dia_centavos
            FROM `ventas` v
            WHERE v.organizacion_id = :org_id
              AND v.estado IN ('CONFIRMADA', 'LIQUIDADA')
              AND {$inSql}
              {$filtroCanalSql}
            GROUP BY v.edicion_id, v.fecha_venta
            ORDER BY v.edicion_id ASC, v.fecha_venta ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filasPorEdicion = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $filasPorEdicion[(int) $f['edicion_id']][] = $f;
        }

        $edicionesCurvas = [];
        foreach ($ediciones as $edicion) {
            $edId = (int) $edicion['id'];
            $fechaInicioStr = (string) $edicion['fecha_inicio'];
            $tsInicio = strtotime($fechaInicioStr . ' 00:00:00');
            $ventasDias = $filasPorEdicion[$edId] ?? [];

            // Calcular total final de la edición
            $totalFinalQty = 0;
            $totalFinalCent = 0;
            foreach ($ventasDias as $vd) {
                $totalFinalQty += (int) $vd['ventas_dia_qty'];
                $totalFinalCent += (int) $vd['devengado_dia_centavos'];
            }

            $hitosEdicion = [];
            foreach (self::HITOS_PREVENTA as $hito) {
                $diasOffset = $hito['dias_offset'];
                $fechaCorte = $diasOffset !== null
                    ? date('Y-m-d', strtotime("{$diasOffset} days", $tsInicio))
                    : null;

                $acumQty = 0;
                $acumCent = 0;
                foreach ($ventasDias as $vd) {
                    if ($fechaCorte === null || (string) $vd['fecha_venta'] <= $fechaCorte) {
                        $acumQty += (int) $vd['ventas_dia_qty'];
                        $acumCent += (int) $vd['devengado_dia_centavos'];
                    }
                }

                $pctAvanceMonto = $totalFinalCent > 0
                    ? round(($acumCent / $totalFinalCent) * 100, 2)
                    : 0.00;
                $pctAvanceQty = $totalFinalQty > 0
                    ? round(($acumQty / $totalFinalQty) * 100, 2)
                    : 0.00;

                $hitosEdicion[] = [
                    'codigo_hito'                 => $hito['codigo'],
                    'etiqueta'                    => $hito['etiqueta'],
                    'dias_offset'                 => $diasOffset,
                    'fecha_corte'                 => $fechaCorte ?? (string) $edicion['fecha_fin'],
                    'ventas_acumuladas_qty'       => $acumQty,
                    'devengado_acumulado_centavos'=> $acumCent,
                    'devengado_acumulado'         => $this->centavosASoles($acumCent),
                    'porcentaje_avance_monto'     => $pctAvanceMonto,
                    'porcentaje_avance_ventas'    => $pctAvanceQty,
                ];
            }

            $edicionesCurvas[] = [
                'edicion_id'             => $edId,
                'codigo'                 => (string) $edicion['codigo'],
                'nombre'                 => (string) $edicion['nombre'],
                'anio'                   => (int) $edicion['anio'],
                'fecha_inicio'           => $fechaInicioStr,
                'total_final_ventas_qty' => $totalFinalQty,
                'total_final_devengado'  => $this->centavosASoles($totalFinalCent),
                'hitos'                  => $hitosEdicion,
            ];
        }

        return [
            'hitos_definicion' => self::HITOS_PREVENTA,
            'series_ediciones' => $edicionesCurvas,
        ];
    }

    // ==============================================================================
    // 5. RETENCIÓN DE CLIENTES, NUEVOS VS RECURRENTES Y MATRIZ DE COHORTES
    // ==============================================================================

    private function construirRetencionYCohortes(
        int $organizacionId,
        array $ediciones,
        array $edicionIds,
        array $filtros
    ): array {
        // Obtenemos todo el historial de participación de clientes por edición de la organización
        // ordenado cronológicamente por año de edición para identificar con exactitud la primera edición de cada cliente.
        $filtroCanalSql = '';
        $params = ['org_id' => $organizacionId];
        if ($filtros['canal_origen'] !== null) {
            $filtroCanalSql = ' AND v.origen_tipo = :canal_origen';
            $params['canal_origen'] = $filtros['canal_origen'];
        }

        $sql = "
            SELECT
                e.id AS edicion_id,
                e.anio,
                e.codigo,
                v.cliente_id,
                COUNT(v.id) AS compras_edicion_qty,
                COALESCE(SUM(ROUND(v.total * 100)), 0) AS devengado_cliente_centavos
            FROM `ventas` v
            INNER JOIN `ediciones_candelaria` e ON e.id = v.edicion_id AND e.organizacion_id = v.organizacion_id
            WHERE v.organizacion_id = :org_id
              AND v.estado IN ('CONFIRMADA', 'LIQUIDADA')
              {$filtroCanalSql}
            GROUP BY e.id, e.anio, e.codigo, v.cliente_id
            ORDER BY e.anio ASC, e.id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Determinar primera edición histórica de cada cliente en la organización
        $primeraEdicionPorCliente = [];
        $clientesPorEdicion = [];
        $gastoClientePorEdicion = [];

        foreach ($filas as $f) {
            $edId = (int) $f['edicion_id'];
            $cliId = (int) $f['cliente_id'];
            $gastoCent = (int) $f['devengado_cliente_centavos'];

            if (!isset($primeraEdicionPorCliente[$cliId])) {
                $primeraEdicionPorCliente[$cliId] = $edId;
            }
            $clientesPorEdicion[$edId][$cliId] = true;
            $gastoClientePorEdicion[$edId][$cliId] = $gastoCent;
        }

        $seriePorEdicion = [];
        $clientesEdicionAnterior = [];
        $clientesHistoricosVistos = [];

        foreach ($ediciones as $edicion) {
            $edId = (int) $edicion['id'];
            $clientesActualesMap = $clientesPorEdicion[$edId] ?? [];
            $clientesActualesIds = array_keys($clientesActualesMap);
            $totalUnicos = count($clientesActualesIds);

            $nuevosQty = 0;
            $recurrentesQty = 0;
            $retenidosDeAnteriorQty = 0;
            $ingresosNuevosCent = 0;
            $ingresosRecurrentesCent = 0;

            foreach ($clientesActualesIds as $cliId) {
                $gasto = $gastoClientePorEdicion[$edId][$cliId] ?? 0;
                $esNuevo = (($primeraEdicionPorCliente[$cliId] ?? $edId) === $edId) && !isset($clientesHistoricosVistos[$cliId]);

                if ($esNuevo) {
                    $nuevosQty++;
                    $ingresosNuevosCent += $gasto;
                } else {
                    $recurrentesQty++;
                    $ingresosRecurrentesCent += $gasto;
                }

                if (isset($clientesEdicionAnterior[$cliId])) {
                    $retenidosDeAnteriorQty++;
                }

                $clientesHistoricosVistos[$cliId] = true;
            }

            $totalEdicionAnterior = count($clientesEdicionAnterior);
            $tasaRecurrenciaPct = $totalUnicos > 0 ? round(($recurrentesQty / $totalUnicos) * 100, 2) : 0.00;
            $tasaRetencionDirectaPct = $totalEdicionAnterior > 0
                ? round(($retenidosDeAnteriorQty / $totalEdicionAnterior) * 100, 2)
                : 0.00;

            $seriePorEdicion[] = [
                'edicion_id'                        => $edId,
                'codigo'                            => (string) $edicion['codigo'],
                'nombre'                            => (string) $edicion['nombre'],
                'anio'                              => (int) $edicion['anio'],
                'clientes_unicos_activos'           => $totalUnicos,
                'clientes_nuevos'                   => $nuevosQty,
                'clientes_recurrentes'              => $recurrentesQty,
                'clientes_retenidos_edicion_previa' => $retenidosDeAnteriorQty,
                'tasa_recurrencia_pct'              => $tasaRecurrenciaPct,
                'tasa_retencion_directa_pct'        => $tasaRetencionDirectaPct,
                'ingresos_nuevos_centavos'          => $ingresosNuevosCent,
                'ingresos_recurrentes_centavos'     => $ingresosRecurrentesCent,
                'ingresos_clientes_nuevos'          => $this->centavosASoles($ingresosNuevosCent),
                'ingresos_clientes_recurrentes'     => $this->centavosASoles($ingresosRecurrentesCent),
            ];

            $clientesEdicionAnterior = $clientesActualesMap;
        }

        // Construir Matriz de Cohortes entre las ediciones evaluadas
        $matrizCohortes = [];
        foreach ($ediciones as $idxOrigen => $edOrigen) {
            $edOrigenId = (int) $edOrigen['id'];
            // Clientes cuya cohorte de adquisición fue edOrigenId
            $clientesCohorte = [];
            foreach ($primeraEdicionPorCliente as $cliId => $primeraEdId) {
                if ($primeraEdId === $edOrigenId) {
                    $clientesCohorte[] = $cliId;
                }
            }
            $tamanoCohorte = count($clientesCohorte);

            $seguimiento = [];
            foreach ($ediciones as $idxDestino => $edDestino) {
                $edDestinoId = (int) $edDestino['id'];
                if ($idxDestino < $idxOrigen) {
                    continue;
                }

                $activosEnDestino = 0;
                foreach ($clientesCohorte as $cliId) {
                    if (isset($clientesPorEdicion[$edDestinoId][$cliId])) {
                        $activosEnDestino++;
                    }
                }

                $pctRetencion = $tamanoCohorte > 0 ? round(($activosEnDestino / $tamanoCohorte) * 100, 2) : 0.00;
                $seguimiento[] = [
                    'edicion_destino_id'     => $edDestinoId,
                    'edicion_destino_codigo' => (string) $edDestino['codigo'],
                    'edicion_destino_anio'   => (int) $edDestino['anio'],
                    'clientes_activos'       => $activosEnDestino,
                    'retencion_pct'          => $pctRetencion,
                ];
            }

            $matrizCohortes[] = [
                'edicion_origen_id'     => $edOrigenId,
                'edicion_origen_codigo' => (string) $edOrigen['codigo'],
                'edicion_origen_anio'   => (int) $edOrigen['anio'],
                'tamano_cohorte'        => $tamanoCohorte,
                'evolucion'             => $seguimiento,
            ];
        }

        return [
            'serie_por_edicion' => $seriePorEdicion,
            'matriz_cohortes'   => $matrizCohortes,
        ];
    }

    // ==============================================================================
    // 6. PARTICIPACIÓN FOLCLÓRICA MULTIANUAL
    // ==============================================================================

    private function construirSerieFolclore(
        int $organizacionId,
        array $ediciones,
        array $edicionIds,
        array $filtros
    ): array {
        [$inSql, $params] = $this->construirClausulaIn('i.edicion_id', $edicionIds, ['org_id' => $organizacionId]);

        $filtroTipoDanzaSql = '';
        if ($filtros['tipo_danza_id'] !== null) {
            $filtroTipoDanzaSql = ' AND c.tipo_danza_id = :tipo_danza_id';
            $params['tipo_danza_id'] = $filtros['tipo_danza_id'];
        }

        // 1. Inscripciones de conjuntos y estimación de danzarines/bandas por edición
        $sqlInsc = "
            SELECT
                i.edicion_id,
                COUNT(i.id) AS conjuntos_inscritos_total,
                COALESCE(SUM(CASE WHEN i.estado IN ('INSCRITO', 'CONFIRMADO') THEN 1 ELSE 0 END), 0) AS conjuntos_activos,
                COALESCE(SUM(CASE WHEN i.estado = 'CONFIRMADO' THEN 1 ELSE 0 END), 0) AS conjuntos_confirmados,
                COALESCE(SUM(CASE WHEN i.estado IN ('RETIRADO', 'SANCIONADO') THEN 1 ELSE 0 END), 0) AS conjuntos_baja,
                COALESCE(SUM(CASE WHEN i.estado IN ('INSCRITO', 'CONFIRMADO') THEN i.cantidad_estimada_danzarines ELSE 0 END), 0) AS danzarines_estimados_total,
                COALESCE(SUM(CASE WHEN i.estado IN ('INSCRITO', 'CONFIRMADO') THEN i.cantidad_bandas ELSE 0 END), 0) AS bandas_total
            FROM `edicion_conjunto_inscripciones` i
            INNER JOIN `conjuntos_folcloricos` c ON c.id = i.conjunto_id AND c.organizacion_id = i.organizacion_id
            WHERE i.organizacion_id = :org_id
              AND {$inSql}
              {$filtroTipoDanzaSql}
            GROUP BY i.edicion_id
        ";
        $stmtInsc = $this->pdo->prepare($sqlInsc);
        $stmtInsc->execute($params);
        $mapaInsc = [];
        foreach ($stmtInsc->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapaInsc[(int) $f['edicion_id']] = $f;
        }

        // 2. Distribución por categoría de danza (TRAJES_DE_LUCES, AUTOCTONO, etc.) por edición
        $sqlCat = "
            SELECT
                i.edicion_id,
                td.categoria_origen,
                COUNT(i.id) AS conjuntos_qty,
                COALESCE(SUM(i.cantidad_estimada_danzarines), 0) AS danzarines_qty
            FROM `edicion_conjunto_inscripciones` i
            INNER JOIN `conjuntos_folcloricos` c ON c.id = i.conjunto_id AND c.organizacion_id = i.organizacion_id
            INNER JOIN `tipos_danza` td ON td.id = c.tipo_danza_id AND td.organizacion_id = i.organizacion_id
            WHERE i.organizacion_id = :org_id
              AND i.estado IN ('INSCRITO', 'CONFIRMADO')
              AND {$inSql}
              {$filtroTipoDanzaSql}
            GROUP BY i.edicion_id, td.categoria_origen
            ORDER BY i.edicion_id ASC, conjuntos_qty DESC
        ";
        $stmtCat = $this->pdo->prepare($sqlCat);
        $stmtCat->execute($params);
        $mapaCat = [];
        foreach ($stmtCat->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapaCat[(int) $f['edicion_id']][] = [
                'categoria_origen' => (string) $f['categoria_origen'],
                'conjuntos_qty'    => (int) $f['conjuntos_qty'],
                'danzarines_qty'   => (int) $f['danzarines_qty'],
            ];
        }

        // 3. Actividades, turnos programados en versión activa y retrasos promedio
        [$inSqlProg, $paramsProg] = $this->construirClausulaIn('po.edicion_id', $edicionIds, ['org_id' => $organizacionId]);
        $sqlTurnos = "
            SELECT
                po.edicion_id,
                COUNT(DISTINCT po.actividad_id) AS actividades_con_rol_qty,
                COUNT(op.id) AS turnos_programados_qty,
                COALESCE(SUM(CASE WHEN op.estado_turno = 'FINALIZADO' THEN 1 ELSE 0 END), 0) AS turnos_finalizados_qty,
                COALESCE(ROUND(AVG(COALESCE(op.retraso_minutos, 0)), 1), 0.0) AS retraso_promedio_minutos
            FROM `folclore_programaciones_oficiales` po
            LEFT JOIN `folclore_orden_presentacion` op ON op.programacion_oficial_id = po.id
            WHERE po.organizacion_id = :org_id
              AND po.es_version_activa = 1
              AND {$inSqlProg}
            GROUP BY po.edicion_id
        ";
        $stmtTurnos = $this->pdo->prepare($sqlTurnos);
        $stmtTurnos->execute($paramsProg);
        $mapaTurnos = [];
        foreach ($stmtTurnos->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapaTurnos[(int) $f['edicion_id']] = $f;
        }

        // 4. Participantes comerciales vinculados a conjuntos/bloques folclóricos
        [$inSqlPart, $paramsPart] = $this->construirClausulaIn('r.edicion_id', $edicionIds, ['org_id' => $organizacionId]);
        $sqlPartFolc = "
            SELECT
                r.edicion_id,
                COUNT(rp.id) AS participantes_vinculados_folclore
            FROM `reserva_participantes` rp
            INNER JOIN `reservas` r ON r.id = rp.reserva_id
            WHERE r.organizacion_id = :org_id
              AND rp.conjunto_id IS NOT NULL
              AND r.estado IN ('CONFIRMADA', 'REGISTRADA', 'PENDIENTE_DATOS')
              AND {$inSqlPart}
            GROUP BY r.edicion_id
        ";
        $stmtPartFolc = $this->pdo->prepare($sqlPartFolc);
        $stmtPartFolc->execute($paramsPart);
        $mapaPartFolc = [];
        foreach ($stmtPartFolc->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapaPartFolc[(int) $f['edicion_id']] = (int) $f['participantes_vinculados_folclore'];
        }

        $serie = [];
        foreach ($ediciones as $edicion) {
            $edId = (int) $edicion['id'];
            $ins = $mapaInsc[$edId] ?? [];
            $tur = $mapaTurnos[$edId] ?? [];

            $serie[] = [
                'edicion_id'                        => $edId,
                'codigo'                            => (string) $edicion['codigo'],
                'nombre'                            => (string) $edicion['nombre'],
                'anio'                              => (int) $edicion['anio'],
                'conjuntos_inscritos_total'         => (int) ($ins['conjuntos_inscritos_total'] ?? 0),
                'conjuntos_activos'                 => (int) ($ins['conjuntos_activos'] ?? 0),
                'conjuntos_confirmados'             => (int) ($ins['conjuntos_confirmados'] ?? 0),
                'conjuntos_baja'                    => (int) ($ins['conjuntos_baja'] ?? 0),
                'danzarines_estimados_total'        => (int) ($ins['danzarines_estimados_total'] ?? 0),
                'bandas_total'                      => (int) ($ins['bandas_total'] ?? 0),
                'actividades_con_rol_qty'           => (int) ($tur['actividades_con_rol_qty'] ?? 0),
                'turnos_programados_qty'            => (int) ($tur['turnos_programados_qty'] ?? 0),
                'turnos_finalizados_qty'            => (int) ($tur['turnos_finalizados_qty'] ?? 0),
                'retraso_promedio_minutos'          => (float) ($tur['retraso_promedio_minutos'] ?? 0.0),
                'participantes_vinculados_folclore' => $mapaPartFolc[$edId] ?? 0,
                'distribucion_categorias'           => $mapaCat[$edId] ?? [],
            ];
        }

        return $serie;
    }

    // ==============================================================================
    // 7. OPERACIONES, ASISTENCIA QR (ONLINE + OFFLINE) Y POSTPRODUCCIÓN MULTIANUAL
    // ==============================================================================

    private function construirSerieOperacionesYPostproduccion(
        int $organizacionId,
        array $ediciones,
        array $edicionIds
    ): array {
        // 1. Ocupación de salidas operativas por edición
        [$inSqlSal, $paramsSal] = $this->construirClausulaIn('s.edicion_id', $edicionIds, ['org_id' => $organizacionId]);
        $sqlSal = "
            SELECT
                s.edicion_id,
                COUNT(DISTINCT s.id) AS total_salidas_programadas,
                COALESCE(SUM(s.capacidad_maxima), 0) AS capacidad_total_flota,
                COALESCE(SUM(COALESCE(osp_agg.pasajeros_salida, 0)), 0) AS pasajeros_agendados_total
            FROM `operacion_salidas` s
            LEFT JOIN (
                SELECT salida_id, SUM(cantidad_pasajeros) AS pasajeros_salida
                FROM `operacion_salida_prestaciones`
                GROUP BY salida_id
            ) osp_agg ON osp_agg.salida_id = s.id
            WHERE s.organizacion_id = :org_id
              AND {$inSqlSal}
            GROUP BY s.edicion_id
        ";
        $stmtSal = $this->pdo->prepare($sqlSal);
        $stmtSal->execute($paramsSal);
        $mapaSal = [];
        foreach ($stmtSal->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapaSal[(int) $f['edicion_id']] = $f;
        }

        // 2. Asistencia QR Online (reservas con check-in y tabla operacion_asistencias)
        [$inSqlAsis, $paramsAsis] = $this->construirClausulaIn('r.edicion_id', $edicionIds, ['org_id' => $organizacionId]);
        $sqlAsis = "
            SELECT
                r.edicion_id,
                r.id AS reserva_id,
                r.notas_operativas,
                COUNT(rp.id) AS participantes_reserva
            FROM `reservas` r
            LEFT JOIN `reserva_participantes` rp ON rp.reserva_id = r.id
            WHERE r.organizacion_id = :org_id
              AND r.estado IN ('CONFIRMADA', 'REGISTRADA', 'PENDIENTE_DATOS')
              AND {$inSqlAsis}
            GROUP BY r.edicion_id, r.id, r.notas_operativas
        ";
        $stmtAsis = $this->pdo->prepare($sqlAsis);
        $stmtAsis->execute($paramsAsis);
        $mapaAsisOnline = [];
        foreach ($stmtAsis->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $edId = (int) $f['edicion_id'];
            if (!isset($mapaAsisOnline[$edId])) {
                $mapaAsisOnline[$edId] = ['participantes_total' => 0, 'asistieron_online' => 0];
            }
            $qty = max(1, (int) $f['participantes_reserva']);
            $mapaAsisOnline[$edId]['participantes_total'] += $qty;
            if (str_contains((string) ($f['notas_operativas'] ?? ''), '[CHECKIN_QR_REGISTRADO')) {
                $mapaAsisOnline[$edId]['asistieron_online'] += $qty;
            }
        }

        // 3. Sincronización Offline QR (operacion_offline_lotes y operacion_offline_eventos)
        [$inSqlOff, $paramsOff] = $this->construirClausulaIn('e.edicion_id', $edicionIds, ['org_id' => $organizacionId]);
        $sqlOff = "
            SELECT
                e.edicion_id,
                COUNT(DISTINCT e.lote_id) AS lotes_sincronizados_qty,
                COUNT(e.id) AS eventos_totales_qty,
                COALESCE(SUM(CASE WHEN e.resultado_servidor = 'CONFIRMADO' THEN 1 ELSE 0 END), 0) AS eventos_confirmados_qty,
                COALESCE(SUM(CASE WHEN e.resultado_servidor = 'CONFLICTO_DUPLICADO' THEN 1 ELSE 0 END), 0) AS eventos_conflicto_qty
            FROM `operacion_offline_eventos` e
            WHERE e.organizacion_id = :org_id
              AND {$inSqlOff}
            GROUP BY e.edicion_id
        ";
        $stmtOff = $this->pdo->prepare($sqlOff);
        $stmtOff->execute($paramsOff);
        $mapaOff = [];
        foreach ($stmtOff->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapaOff[(int) $f['edicion_id']] = $f;
        }

        // 4. Postproducción: proyectos, entregas, galerías, archivos y selecciones fotográficas
        [$inSqlPost, $paramsPost] = $this->construirClausulaIn('p.edicion_id', $edicionIds, ['org_id' => $organizacionId]);
        $sqlProy = "
            SELECT
                p.edicion_id,
                COUNT(p.id) AS proyectos_total,
                COALESCE(SUM(CASE WHEN p.estado = 'ENTREGADO' THEN 1 ELSE 0 END), 0) AS proyectos_entregados,
                COALESCE(SUM(CASE WHEN p.estado IN ('EN_EDICION', 'EN_REVISION', 'LISTO_ENTREGA') THEN 1 ELSE 0 END), 0) AS proyectos_en_curso
            FROM `postproduccion_proyectos` p
            WHERE p.organizacion_id = :org_id
              AND {$inSqlPost}
            GROUP BY p.edicion_id
        ";
        $stmtProy = $this->pdo->prepare($sqlProy);
        $stmtProy->execute($paramsPost);
        $mapaProy = [];
        foreach ($stmtProy->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapaProy[(int) $f['edicion_id']] = $f;
        }

        $sqlEnt = "
            SELECT
                p.edicion_id,
                COUNT(e.id) AS entregas_totales,
                COALESCE(SUM(CASE WHEN e.estado_entrega = 'APROBADA_CLIENTE' THEN 1 ELSE 0 END), 0) AS entregas_aprobadas,
                COALESCE(SUM(CASE WHEN e.estado_entrega = 'OBSERVADA_CLIENTE' THEN 1 ELSE 0 END), 0) AS entregas_observadas
            FROM `postproduccion_entregas` e
            INNER JOIN `postproduccion_proyectos` p ON p.id = e.proyecto_id AND p.organizacion_id = e.organizacion_id
            WHERE e.organizacion_id = :org_id
              AND {$inSqlPost}
            GROUP BY p.edicion_id
        ";
        $stmtEnt = $this->pdo->prepare($sqlEnt);
        $stmtEnt->execute($paramsPost);
        $mapaEnt = [];
        foreach ($stmtEnt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapaEnt[(int) $f['edicion_id']] = $f;
        }

        $sqlSel = "
            SELECT
                p.edicion_id,
                COUNT(s.id) AS selecciones_activas_qty,
                COUNT(DISTINCT s.cliente_id) AS clientes_con_seleccion_qty
            FROM `postproduccion_selecciones` s
            INNER JOIN `postproduccion_proyectos` p ON p.id = s.proyecto_id AND p.organizacion_id = s.organizacion_id
            WHERE s.organizacion_id = :org_id
              AND s.estado = 'SELECCIONADA'
              AND {$inSqlPost}
            GROUP BY p.edicion_id
        ";
        $stmtSel = $this->pdo->prepare($sqlSel);
        $stmtSel->execute($paramsPost);
        $mapaSel = [];
        foreach ($stmtSel->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapaSel[(int) $f['edicion_id']] = $f;
        }

        $serie = [];
        foreach ($ediciones as $edicion) {
            $edId = (int) $edicion['id'];
            $sal = $mapaSal[$edId] ?? [];
            $asis = $mapaAsisOnline[$edId] ?? ['participantes_total' => 0, 'asistieron_online' => 0];
            $off = $mapaOff[$edId] ?? [];
            $proy = $mapaProy[$edId] ?? [];
            $ent = $mapaEnt[$edId] ?? [];
            $sel = $mapaSel[$edId] ?? [];

            $capTotal = (int) ($sal['capacidad_total_flota'] ?? 0);
            $agendadosTotal = (int) ($sal['pasajeros_agendados_total'] ?? 0);
            $ocupacionPct = $capTotal > 0 ? round(($agendadosTotal / $capTotal) * 100, 2) : 0.00;

            $participantesTotal = (int) $asis['participantes_total'];
            $asistieronOnline = (int) $asis['asistieron_online'];
            $confirmadosOffline = (int) ($off['eventos_confirmados_qty'] ?? 0);
            $totalAsistieron = min($participantesTotal, $asistieronOnline + $confirmadosOffline);
            if ($participantesTotal === 0 && $confirmadosOffline > 0) {
                $totalAsistieron = $confirmadosOffline;
            }
            $tasaAsistenciaPct = $participantesTotal > 0 ? round(($totalAsistieron / $participantesTotal) * 100, 2) : 0.00;

            $entregasTot = (int) ($ent['entregas_totales'] ?? 0);
            $entregasApr = (int) ($ent['entregas_aprobadas'] ?? 0);
            $tasaAprobacionPct = $entregasTot > 0 ? round(($entregasApr / $entregasTot) * 100, 2) : 0.00;

            $serie[] = [
                'edicion_id'                   => $edId,
                'codigo'                       => (string) $edicion['codigo'],
                'anio'                         => (int) $edicion['anio'],
                'salidas_programadas'          => (int) ($sal['total_salidas_programadas'] ?? 0),
                'capacidad_total_flota'        => $capTotal,
                'pasajeros_agendados'          => $agendadosTotal,
                'tasa_ocupacion_flota_pct'     => $ocupacionPct,
                'participantes_nomina'         => $participantesTotal,
                'asistencias_confirmadas'      => $totalAsistieron,
                'lotes_offline_sincronizados'  => (int) ($off['lotes_sincronizados_qty'] ?? 0),
                'eventos_offline_confirmados'  => $confirmadosOffline,
                'eventos_offline_conflictos'   => (int) ($off['eventos_conflicto_qty'] ?? 0),
                'tasa_asistencia_pct'          => $tasaAsistenciaPct,
                'proyectos_postproduccion'     => (int) ($proy['proyectos_total'] ?? 0),
                'proyectos_entregados'         => (int) ($proy['proyectos_entregados'] ?? 0),
                'entregas_totales'             => $entregasTot,
                'entregas_aprobadas'           => $entregasApr,
                'tasa_aprobacion_entregas_pct' => $tasaAprobacionPct,
                'selecciones_fotograficas_qty' => (int) ($sel['selecciones_activas_qty'] ?? 0),
                'clientes_con_seleccion_qty'   => (int) ($sel['clientes_con_seleccion_qty'] ?? 0),
            ];
        }

        return $serie;
    }

    // ==============================================================================
    // 8. RESUMEN EJECUTIVO CONSOLIDADO MULTI-EDICIÓN
    // ==============================================================================

    private function construirResumenConsolidado(
        array $serieFinanciera,
        array $serieReservas,
        array $retencionClientes,
        array $serieFolclore,
        array $serieOperacionesPost
    ): array {
        $devengadoTotalCent = 0;
        $cobradoBrutoTotalCent = 0;
        $comisionesTotalCent = 0;
        $netoTotalCent = 0;
        $reembolsosTotalCent = 0;
        $saldoPendienteTotalCent = 0;
        $anuladoCanceladoTotalCent = 0;
        $ventasActivasTotal = 0;

        $mejorEdicion = null;
        $variacionesYoyValidas = [];

        foreach ($serieFinanciera as $f) {
            $devengadoTotalCent += (int) $f['devengado_centavos'];
            $cobradoBrutoTotalCent += (int) $f['cobrado_bruto_centavos'];
            $comisionesTotalCent += (int) $f['comisiones_pasarela_centavos'];
            $netoTotalCent += (int) $f['recaudacion_neta_centavos'];
            $reembolsosTotalCent += (int) $f['reembolsos_centavos'];
            $saldoPendienteTotalCent += (int) $f['saldo_pendiente_centavos'];
            $anuladoCanceladoTotalCent += (int) $f['anulado_cancelado_centavos'];
            $ventasActivasTotal += (int) $f['ventas_activas_qty'];

            if ($mejorEdicion === null || (int) $f['devengado_centavos'] > (int) $mejorEdicion['devengado_centavos']) {
                $mejorEdicion = [
                    'edicion_id'        => (int) $f['edicion_id'],
                    'codigo'            => (string) $f['codigo'],
                    'nombre'            => (string) $f['nombre'],
                    'anio'              => (int) $f['anio'],
                    'devengado_centavos'=> (int) $f['devengado_centavos'],
                    'total_devengado'   => (float) $f['total_devengado'],
                    'total_neto'        => (float) $f['total_neto_recaudado'],
                ];
            }

            if ($f['variacion_yoy']['delta_devengado_pct'] !== null) {
                $variacionesYoyValidas[] = (float) $f['variacion_yoy']['delta_devengado_pct'];
            }
        }

        $reservasActivasTotal = 0;
        $participantesTotal = 0;
        foreach ($serieReservas as $r) {
            $reservasActivasTotal += (int) $r['reservas_activas'];
            $participantesTotal += (int) $r['participantes_totales_qty'];
        }

        $conjuntosAcumulados = 0;
        $danzarinesAcumulados = 0;
        foreach ($serieFolclore as $fl) {
            $conjuntosAcumulados += (int) $fl['conjuntos_activos'];
            $danzarinesAcumulados += (int) $fl['danzarines_estimados_total'];
        }

        $seleccionesAcumuladas = 0;
        foreach ($serieOperacionesPost as $op) {
            $seleccionesAcumuladas += (int) $op['selecciones_fotograficas_qty'];
        }

        $crecimientoPromedioYoy = !empty($variacionesYoyValidas)
            ? round(array_sum($variacionesYoyValidas) / count($variacionesYoyValidas), 2)
            : null;

        $ticketPromedioGlobalCent = $ventasActivasTotal > 0
            ? (int) round($devengadoTotalCent / $ventasActivasTotal)
            : 0;
        $porcentajeCobranzaGlobal = $devengadoTotalCent > 0
            ? round(($cobradoBrutoTotalCent / $devengadoTotalCent) * 100, 2)
            : 0.00;

        // Retención promedio en ediciones subsecuentes
        $tasasRecurrencia = [];
        foreach ($retencionClientes['serie_por_edicion'] as $idx => $ret) {
            if ($idx > 0 && (int) $ret['clientes_unicos_activos'] > 0) {
                $tasasRecurrencia[] = (float) $ret['tasa_recurrencia_pct'];
            }
        }
        $tasaRecurrenciaPromedio = !empty($tasasRecurrencia)
            ? round(array_sum($tasasRecurrencia) / count($tasasRecurrencia), 2)
            : 0.00;

        return [
            'ediciones_evaluadas_qty'          => count($serieFinanciera),
            'devengado_acumulado_centavos'     => $devengadoTotalCent,
            'cobrado_bruto_acumulado_centavos' => $cobradoBrutoTotalCent,
            'comisiones_acumuladas_centavos'   => $comisionesTotalCent,
            'recaudacion_neta_acumulada_centavos' => $netoTotalCent,
            'reembolsos_acumulados_centavos'   => $reembolsosTotalCent,
            'saldo_pendiente_acumulado_centavos' => $saldoPendienteTotalCent,
            'anulado_cancelado_acumulado_centavos' => $anuladoCanceladoTotalCent,
            'total_devengado_acumulado'        => $this->centavosASoles($devengadoTotalCent),
            'total_cobrado_acumulado'          => $this->centavosASoles($cobradoBrutoTotalCent),
            'total_comisiones_acumuladas'      => $this->centavosASoles($comisionesTotalCent),
            'total_neto_acumulado'             => $this->centavosASoles($netoTotalCent),
            'total_reembolsos_acumulados'      => $this->centavosASoles($reembolsosTotalCent),
            'total_saldo_pendiente_acumulado'  => $this->centavosASoles($saldoPendienteTotalCent),
            'total_anulado_cancelado_acumulado'=> $this->centavosASoles($anuladoCanceladoTotalCent),
            'ventas_activas_acumuladas'        => $ventasActivasTotal,
            'ticket_promedio_global'           => $this->centavosASoles($ticketPromedioGlobalCent),
            'porcentaje_cobranza_global'       => $porcentajeCobranzaGlobal,
            'reservas_activas_acumuladas'      => $reservasActivasTotal,
            'participantes_acumulados'         => $participantesTotal,
            'conjuntos_folcloricos_acumulados' => $conjuntosAcumulados,
            'danzarines_estimados_acumulados'  => $danzarinesAcumulados,
            'selecciones_fotograficas_acumuladas' => $seleccionesAcumuladas,
            'crecimiento_promedio_yoy_pct'     => $crecimientoPromedioYoy,
            'tasa_recurrencia_promedio_pct'    => $tasaRecurrenciaPromedio,
            'mejor_edicion_historica'          => $mejorEdicion,
        ];
    }

    // ==============================================================================
    // 9. EXPORTADORES MULTIANUALES OFICIALES (CSV, XLSX, PDF) — MICROFASE 4.1E
    // ==============================================================================

    /**
     * Prepara el dataset tabular unificado por edición + resumen consolidado para exportación.
     */
    public function construirReporteExportable(int $organizacionId, array $filtros = []): array
    {
        $analitica = $this->obtenerAnaliticaMultianual($organizacionId, $filtros);

        $mapReservas = [];
        foreach ($analitica['serie_reservas'] as $r) {
            $mapReservas[(int) $r['edicion_id']] = $r;
        }

        $mapRetencion = [];
        foreach ($analitica['retencion_clientes']['serie_por_edicion'] as $ret) {
            $mapRetencion[(int) $ret['edicion_id']] = $ret;
        }

        $mapFolclore = [];
        foreach ($analitica['serie_folclore'] as $fl) {
            $mapFolclore[(int) $fl['edicion_id']] = $fl;
        }

        $mapOperaciones = [];
        foreach ($analitica['serie_operaciones_postproduccion'] as $op) {
            $mapOperaciones[(int) $op['edicion_id']] = $op;
        }

        $items = [];
        foreach ($analitica['serie_financiera'] as $fin) {
            $edId = (int) $fin['edicion_id'];
            $res = $mapReservas[$edId] ?? [];
            $ret = $mapRetencion[$edId] ?? [];
            $fl = $mapFolclore[$edId] ?? [];
            $op = $mapOperaciones[$edId] ?? [];

            $yoyPct = $fin['variacion_yoy']['delta_devengado_pct'];
            $yoyStr = $yoyPct !== null ? ($yoyPct >= 0 ? '+' . number_format((float)$yoyPct, 2) . '%' : number_format((float)$yoyPct, 2) . '%') : 'N/A (Base Inicial)';

            $items[] = [
                'anio'                     => (int) $fin['anio'],
                'codigo_edicion'           => (string) $fin['codigo'],
                'nombre_edicion'           => (string) $fin['nombre'],
                'estado_edicion'           => (string) $fin['estado'],
                'ventas_activas'           => (int) $fin['ventas_activas_qty'],
                'ventas_devengadas_pen'    => (float) $fin['total_devengado'],
                'variacion_yoy_devengado'  => $yoyStr,
                'cobrado_bruto_pen'        => (float) $fin['total_cobrado_cliente'],
                'comisiones_pasarela_pen'  => (float) $fin['total_comisiones_pasarela'],
                'recaudacion_neta_pen'     => (float) $fin['total_neto_recaudado'],
                'reembolsos_pen'           => (float) $fin['total_reembolsos'],
                'saldo_pendiente_pen'      => (float) $fin['total_saldo_pendiente'],
                'anulado_cancelado_pen'    => (float) $fin['total_anulado_cancelado'],
                'ticket_promedio_pen'      => (float) $fin['ticket_promedio'],
                'cobranza_pct'             => (float) $fin['porcentaje_cobranza'],
                'reservas_activas'         => (int) ($res['reservas_activas'] ?? 0),
                'conversion_reservas_pct'  => (float) ($res['tasa_conversion_pct'] ?? 0.0),
                'clientes_unicos'          => (int) ($ret['clientes_unicos_activos'] ?? 0),
                'clientes_nuevos'          => (int) ($ret['clientes_nuevos'] ?? 0),
                'clientes_recurrentes'     => (int) ($ret['clientes_recurrentes'] ?? 0),
                'recurrencia_clientes_pct' => (float) ($ret['tasa_recurrencia_pct'] ?? 0.0),
                'conjuntos_folclore'       => (int) ($fl['conjuntos_activos'] ?? 0),
                'danzarines_estimados'     => (int) ($fl['danzarines_estimados_total'] ?? 0),
                'ocupacion_flota_pct'      => (float) ($op['tasa_ocupacion_flota_pct'] ?? 0.0),
                'asistencia_qr_pct'        => (float) ($op['tasa_asistencia_pct'] ?? 0.0),
                'selecciones_fotos'        => (int) ($op['selecciones_fotograficas_qty'] ?? 0),
            ];
        }

        $rc = $analitica['resumen_consolidado'];
        return [
            'titulo'      => 'Informe Oficial de Analítica Multianual y Comparativa Inter-Ediciones',
            'generado_en' => $analitica['generado_en'],
            'items'       => $items,
            'totales'     => [
                'ediciones_evaluadas'             => (int) $rc['ediciones_evaluadas_qty'],
                'ventas_activas_acumuladas'       => (int) $rc['ventas_activas_acumuladas'],
                'total_devengado_acumulado_pen'   => (float) $rc['total_devengado_acumulado'],
                'total_cobrado_acumulado_pen'     => (float) $rc['total_cobrado_acumulado'],
                'total_comisiones_acumuladas_pen' => (float) $rc['total_comisiones_acumuladas'],
                'total_neto_acumulado_pen'        => (float) $rc['total_neto_acumulado'],
                'total_reembolsos_acumulados_pen' => (float) $rc['total_reembolsos_acumulados'],
                'total_saldo_pendiente_pen'       => (float) $rc['total_saldo_pendiente_acumulado'],
                'ticket_promedio_global_pen'      => (float) $rc['ticket_promedio_global'],
                'porcentaje_cobranza_global_pct'  => (float) $rc['porcentaje_cobranza_global'],
                'crecimiento_promedio_yoy_pct'    => $rc['crecimiento_promedio_yoy_pct'] !== null ? (float) $rc['crecimiento_promedio_yoy_pct'] : 'N/A',
                'tasa_recurrencia_promedio_pct'   => (float) $rc['tasa_recurrencia_promedio_pct'],
            ],
            'analitica'   => $analitica,
        ];
    }

    /**
     * Exporta la comparativa multianual a CSV con BOM UTF-8 y blindaje anti-Formula Injection.
     */
    public function exportarCsv(int $organizacionId, array $filtros = []): string
    {
        $reporte = $this->construirReporteExportable($organizacionId, $filtros);
        $buffer = fopen('php://memory', 'r+');
        if ($buffer === false) {
            throw new RuntimeException('No se pudo inicializar el buffer en memoria para exportación CSV.');
        }

        fwrite($buffer, "\xEF\xBB\xBF");
        fputcsv($buffer, [$this->sanitizarParaCsv($reporte['titulo'])]);
        fputcsv($buffer, [$this->sanitizarParaCsv('Generado: ' . $reporte['generado_en'] . ' | CandelariaAPP V1.2')]);
        fputcsv($buffer, []);

        $items = $reporte['items'];
        if (empty($items)) {
            fputcsv($buffer, [$this->sanitizarParaCsv('No se encontraron ediciones para los filtros especificados.')]);
        } else {
            $columnas = array_keys($items[0]);
            fputcsv($buffer, array_map(fn(string $c) => $this->sanitizarParaCsv(strtoupper(str_replace('_', ' ', $c))), $columnas));

            foreach ($items as $fila) {
                $filaSanitizada = [];
                foreach ($fila as $valor) {
                    $filaSanitizada[] = $this->sanitizarParaCsv($valor);
                }
                fputcsv($buffer, $filaSanitizada);
            }
        }

        if (!empty($reporte['totales'])) {
            fputcsv($buffer, []);
            fputcsv($buffer, [$this->sanitizarParaCsv('--- RESUMEN CONSOLIDADO MULTIANUAL ---')]);
            foreach ($reporte['totales'] as $k => $v) {
                fputcsv($buffer, [
                    $this->sanitizarParaCsv(strtoupper(str_replace('_', ' ', (string) $k))),
                    $this->sanitizarParaCsv($v),
                ]);
            }
        }

        rewind($buffer);
        $contenido = stream_get_contents($buffer);
        fclose($buffer);

        return (string) $contenido;
    }

    /**
     * Exporta la comparativa multianual a paquete XLSX (OpenXML ECMA-376) comprimido con ZipArchive.
     */
    public function exportarXlsx(int $organizacionId, array $filtros = []): string
    {
        if (!class_exists('\ZipArchive')) {
            throw new RuntimeException('La extensión ZipArchive no está disponible para generar paquetes XLSX.');
        }

        $reporte = $this->construirReporteExportable($organizacionId, $filtros);
        $tmpFile = tempnam(sys_get_temp_dir(), 'candelaria_multianual_xlsx_');
        $zip = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo inicializar el paquete temporal OpenXML.');
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n"
            . '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n"
            . '  <Default Extension="xml" ContentType="application/xml"/>' . "\n"
            . '  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\n"
            . '  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n"
            . '  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\n"
            . '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
            . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\n"
            . '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
            . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' . "\n"
            . '  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . "\n"
            . '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n"
            . '  <sheets><sheet name="Analitica Multianual" sheetId="1" r:id="rId1"/></sheets>' . "\n"
            . '</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n"
            . '  <fonts count="3">' . "\n"
            . '    <font><sz val="11"/><name val="Calibri"/></font>' . "\n"
            . '    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' . "\n"
            . '    <font><b/><sz val="13"/><color rgb="FF8B0000"/><name val="Calibri"/></font>' . "\n"
            . '  </fonts>' . "\n"
            . '  <fills count="4">' . "\n"
            . '    <fill><patternFill patternType="none"/></fill>' . "\n"
            . '    <fill><patternFill patternType="gray125"/></fill>' . "\n"
            . '    <fill><patternFill patternType="solid"><fgColor rgb="FF1B365D"/></patternFill></fill>' . "\n"
            . '    <fill><patternFill patternType="solid"><fgColor rgb="FFEAEAEA"/></patternFill></fill>' . "\n"
            . '  </fills>' . "\n"
            . '  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' . "\n"
            . '  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' . "\n"
            . '  <cellXfs count="4">' . "\n"
            . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . "\n"
            . '    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' . "\n"
            . '    <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' . "\n"
            . '    <xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/>' . "\n"
            . '  </cellXfs>' . "\n"
            . '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        $titulo = htmlspecialchars((string) $reporte['titulo'], ENT_XML1, 'UTF-8');
        $generado = htmlspecialchars((string) $reporte['generado_en'], ENT_XML1, 'UTF-8');
        $items = $reporte['items'];

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . "\n";

        $filaActual = 1;
        $sheetXml .= '  <row r="' . $filaActual . '"><c r="A' . $filaActual . '" s="2" t="inlineStr"><is><t>' . $titulo . '</t></is></c></row>' . "\n";
        $filaActual++;
        $sheetXml .= '  <row r="' . $filaActual . '"><c r="A' . $filaActual . '" t="inlineStr"><is><t>Fecha de Emisión: ' . $generado . ' | CandelariaAPP V1.2</t></is></c></row>' . "\n";
        $filaActual += 2;

        if (!empty($items)) {
            $sheetXml .= '  <row r="' . $filaActual . '">' . "\n";
            $colIdx = 1;
            foreach (array_keys($items[0]) as $col) {
                $ref = $this->numeroAColumnaExcel($colIdx) . $filaActual;
                $colNombre = htmlspecialchars(strtoupper(str_replace('_', ' ', (string) $col)), ENT_XML1, 'UTF-8');
                $sheetXml .= '    <c r="' . $ref . '" s="1" t="inlineStr"><is><t>' . $colNombre . '</t></is></c>' . "\n";
                $colIdx++;
            }
            $sheetXml .= '  </row>' . "\n";
            $filaActual++;

            foreach ($items as $item) {
                $sheetXml .= '  <row r="' . $filaActual . '">' . "\n";
                $colIdx = 1;
                foreach ($item as $valor) {
                    $ref = $this->numeroAColumnaExcel($colIdx) . $filaActual;
                    if (is_int($valor) || is_float($valor)) {
                        $sheetXml .= '    <c r="' . $ref . '"><v>' . $valor . '</v></c>' . "\n";
                    } else {
                        $sanitizado = htmlspecialchars((string) $this->sanitizarParaCsv($valor), ENT_XML1, 'UTF-8');
                        $sheetXml .= '    <c r="' . $ref . '" t="inlineStr"><is><t>' . $sanitizado . '</t></is></c>' . "\n";
                    }
                    $colIdx++;
                }
                $sheetXml .= '  </row>' . "\n";
                $filaActual++;
            }
        }

        if (!empty($reporte['totales'])) {
            $filaActual++;
            $sheetXml .= '  <row r="' . $filaActual . '">'
                . '<c r="A' . $filaActual . '" s="3" t="inlineStr"><is><t>RESUMEN CONSOLIDADO MULTIANUAL</t></is></c>'
                . '<c r="B' . $filaActual . '" s="3" t="inlineStr"><is><t>VALOR</t></is></c>'
                . '</row>' . "\n";
            $filaActual++;

            foreach ($reporte['totales'] as $k => $v) {
                $nombreClave = htmlspecialchars(strtoupper(str_replace('_', ' ', (string) $k)), ENT_XML1, 'UTF-8');
                $sheetXml .= '  <row r="' . $filaActual . '">';
                $sheetXml .= '<c r="A' . $filaActual . '" t="inlineStr"><is><t>' . $nombreClave . '</t></is></c>';
                if (is_int($v) || is_float($v)) {
                    $sheetXml .= '<c r="B' . $filaActual . '"><v>' . $v . '</v></c>';
                } else {
                    $sheetXml .= '<c r="B' . $filaActual . '" t="inlineStr"><is><t>' . htmlspecialchars((string) $v, ENT_XML1, 'UTF-8') . '</t></is></c>';
                }
                $sheetXml .= '</row>' . "\n";
                $filaActual++;
            }
        }

        $sheetXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $binario = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return (string) $binario;
    }

    /**
     * Exporta el informe analítico multianual a documento PDF institucional (A4 Horizontal / Landscape).
     */
    public function exportarPdf(int $organizacionId, array $filtros = [], array $tenantInfo = []): string
    {
        $reporte = $this->construirReporteExportable($organizacionId, $filtros);
        $analitica = $reporte['analitica'];
        $rc = $analitica['resumen_consolidado'];

        $titulo = htmlspecialchars((string) $reporte['titulo'], ENT_QUOTES, 'UTF-8');
        $generado = htmlspecialchars((string) $reporte['generado_en'], ENT_QUOTES, 'UTF-8');
        $orgNombre = htmlspecialchars((string) ($tenantInfo['nombre_comercial'] ?? 'Organización CandelariaAPP'), ENT_QUOTES, 'UTF-8');

        $hashIntegridad = hash('sha256', (string) json_encode($analitica['serie_financiera'], JSON_UNESCAPED_UNICODE));
        $hashCorto = substr($hashIntegridad, 0, 16) . '...' . substr($hashIntegridad, -16);

        $html = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5px; color: #1F2937; margin: 14px; }
                .header-table { width: 100%; border-bottom: 2px solid #8B0000; padding-bottom: 8px; margin-bottom: 10px; }
                .header-title { font-size: 13px; font-weight: bold; color: #8B0000; text-transform: uppercase; }
                .header-subtitle { font-size: 8.5px; color: #4B5563; margin-top: 2px; }
                .section-title { font-size: 10px; font-weight: bold; color: #1B365D; margin: 10px 0 4px 0; text-transform: uppercase; border-left: 3px solid #8B0000; padding-left: 6px; }
                .kpi-grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                .kpi-box { border: 1px solid #D1D5DB; background-color: #F9FAFB; padding: 6px; text-align: center; width: 16.6%; }
                .kpi-label { font-size: 7.5px; color: #6B7280; text-transform: uppercase; }
                .kpi-value { font-size: 10.5px; font-weight: bold; color: #111827; margin-top: 2px; }
                .table-data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                .table-data th { background-color: #1B365D; color: #FFFFFF; font-weight: bold; padding: 4px; text-align: left; font-size: 7.5px; }
                .table-data td { padding: 4px; border-bottom: 1px solid #E5E7EB; font-size: 7.5px; }
                .table-data tr:nth-child(even) td { background-color: #F9FAFB; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 7px; color: #6B7280; border-top: 1px solid #D1D5DB; padding-top: 4px; text-align: center; }
            </style>
        </head>
        <body>
            <table class="header-table">
                <tr>
                    <td>
                        <div class="header-title">' . $titulo . '</div>
                        <div class="header-subtitle"><strong>Organización:</strong> ' . $orgNombre . ' | <strong>Ediciones Evaluadas:</strong> ' . (int) $rc['ediciones_evaluadas_qty'] . '</div>
                    </td>
                    <td class="text-end" style="font-size: 7.5px; color: #4B5563;">
                        <div><strong>Emisión Oficial:</strong> ' . $generado . '</div>
                        <div><strong>Sello SHA-256:</strong> ' . $hashCorto . '</div>
                        <div style="color: #9B1C1C; font-weight: bold;">REPORTE GERENCIAL INTERNO (NO TRIBUTARIO)</div>
                    </td>
                </tr>
            </table>

            <table class="kpi-grid">
                <tr>
                    <td class="kpi-box">
                        <div class="kpi-label">Devengado Acumulado</div>
                        <div class="kpi-value">S/ ' . number_format((float) $rc['total_devengado_acumulado'], 2) . '</div>
                    </td>
                    <td class="kpi-box">
                        <div class="kpi-label">Recaudación Neta</div>
                        <div class="kpi-value">S/ ' . number_format((float) $rc['total_neto_acumulado'], 2) . '</div>
                    </td>
                    <td class="kpi-box">
                        <div class="kpi-label">Saldo Pendiente</div>
                        <div class="kpi-value">S/ ' . number_format((float) $rc['total_saldo_pendiente_acumulado'], 2) . '</div>
                    </td>
                    <td class="kpi-box">
                        <div class="kpi-label">Ticket Promedio Global</div>
                        <div class="kpi-value">S/ ' . number_format((float) $rc['ticket_promedio_global'], 2) . '</div>
                    </td>
                    <td class="kpi-box">
                        <div class="kpi-label">Crecimiento YoY Prom.</div>
                        <div class="kpi-value">' . ($rc['crecimiento_promedio_yoy_pct'] !== null ? number_format((float) $rc['crecimiento_promedio_yoy_pct'], 2) . '%' : 'N/A') . '</div>
                    </td>
                    <td class="kpi-box">
                        <div class="kpi-label">Recurrencia Clientes</div>
                        <div class="kpi-value">' . number_format((float) $rc['tasa_recurrencia_promedio_pct'], 2) . '%</div>
                    </td>
                </tr>
            </table>

            <div class="section-title">1. Comparativa Financiera, Comercial y Operativa por Edición</div>
            <table class="table-data">
                <thead>
                    <tr>
                        <th>Año / Código</th>
                        <th>Estado</th>
                        <th class="text-end">Ventas Act.</th>
                        <th class="text-end">Devengado (S/)</th>
                        <th class="text-end">Var. YoY</th>
                        <th class="text-end">Cobrado Bruto</th>
                        <th class="text-end">Comisiones</th>
                        <th class="text-end">Neto Recaudado</th>
                        <th class="text-end">Saldo Pend.</th>
                        <th class="text-end">Ticket Prom.</th>
                        <th class="text-end">Clientes (N/R)</th>
                        <th class="text-end">Conjuntos / Danz.</th>
                        <th class="text-end">Ocup. / Asist.</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($reporte['items'] as $it) {
            $html .= '<tr>
                <td><strong>' . (int) $it['anio'] . '</strong> - ' . htmlspecialchars((string) $it['codigo_edicion'], ENT_QUOTES, 'UTF-8') . '</td>
                <td>' . htmlspecialchars((string) $it['estado_edicion'], ENT_QUOTES, 'UTF-8') . '</td>
                <td class="text-end">' . (int) $it['ventas_activas'] . '</td>
                <td class="text-end"><strong>' . number_format((float) $it['ventas_devengadas_pen'], 2) . '</strong></td>
                <td class="text-end">' . htmlspecialchars((string) $it['variacion_yoy_devengado'], ENT_QUOTES, 'UTF-8') . '</td>
                <td class="text-end">' . number_format((float) $it['cobrado_bruto_pen'], 2) . '</td>
                <td class="text-end">' . number_format((float) $it['comisiones_pasarela_pen'], 2) . '</td>
                <td class="text-end">' . number_format((float) $it['recaudacion_neta_pen'], 2) . '</td>
                <td class="text-end">' . number_format((float) $it['saldo_pendiente_pen'], 2) . '</td>
                <td class="text-end">' . number_format((float) $it['ticket_promedio_pen'], 2) . '</td>
                <td class="text-end">' . (int) $it['clientes_unicos'] . ' (' . (int) $it['clientes_nuevos'] . '/' . (int) $it['clientes_recurrentes'] . ')</td>
                <td class="text-end">' . (int) $it['conjuntos_folclore'] . ' / ' . number_format((int) $it['danzarines_estimados']) . '</td>
                <td class="text-end">' . number_format((float) $it['ocupacion_flota_pct'], 1) . '% / ' . number_format((float) $it['asistencia_qr_pct'], 1) . '%</td>
            </tr>';
        }

        $html .= '</tbody></table>
            <div class="footer">
                CandelariaAPP V1.2 &copy; ' . date('Y') . ' | Analítica Multianual e Inteligencia Comparativa de Ediciones | Sello de Integridad: ' . $hashCorto . '
            </div>
        </body>
        </html>';

        return $this->motorPdf->renderizar($html, MotorPdfServicio::FORMATO_A4, 'landscape');
    }

    /**
     * Sanitiza celdas contra Formula Injection (CSV / Spreadsheet Injection).
     */
    public function sanitizarParaCsv(mixed $valor): mixed
    {
        if (is_int($valor) || is_float($valor)) {
            return $valor;
        }
        if ($valor === null) {
            return '';
        }
        $str = (string) $valor;
        if ($str === '') {
            return '';
        }
        if (is_numeric($str) && !str_starts_with($str, '+') && !str_starts_with($str, '-')) {
            return $valor;
        }

        $peligrosos = ['=', '+', '-', '@', "\t", "\r", ';', '%', '|'];
        if (in_array($str[0], $peligrosos, true)) {
            return "'" . $str;
        }

        $strTrim = ltrim($str, ' ');
        if ($strTrim !== '' && in_array($strTrim[0], $peligrosos, true)) {
            return "'" . $str;
        }

        return $str;
    }

    // ==============================================================================
    // 10. HELPERS INTERNOS
    // ==============================================================================

    private function centavosASoles(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    /**
     * Construye una cláusula IN segura con parámetros nombrados.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function construirClausulaIn(string $columna, array $valores, array $paramsBase = []): array
    {
        $placeholders = [];
        $params = $paramsBase;
        $prefijo = str_replace('.', '_', $columna);

        foreach (array_values($valores) as $idx => $val) {
            $key = "{$prefijo}_{$idx}";
            $placeholders[] = ":{$key}";
            $params[$key] = (int) $val;
        }

        return [$columna . ' IN (' . implode(', ', $placeholders) . ')', $params];
    }

    private function numeroAColumnaExcel(int $colNumber): string
    {
        $letra = '';
        while ($colNumber > 0) {
            $modulo = ($colNumber - 1) % 26;
            $letra = chr(65 + $modulo) . $letra;
            $colNumber = (int) (($colNumber - $modulo) / 26);
        }
        return $letra;
    }

    private function generarEstructuraVacia(int $organizacionId, array $filtros): array
    {
        return [
            'organizacion_id'                  => $organizacionId,
            'generado_en'                      => date('Y-m-d H:i:s'),
            'filtros_aplicados'                => $filtros,
            'ediciones_evaluadas'              => [],
            'resumen_consolidado'              => [
                'ediciones_evaluadas_qty'              => 0,
                'devengado_acumulado_centavos'         => 0,
                'cobrado_bruto_acumulado_centavos'     => 0,
                'comisiones_acumuladas_centavos'       => 0,
                'recaudacion_neta_acumulada_centavos'  => 0,
                'reembolsos_acumulados_centavos'       => 0,
                'saldo_pendiente_acumulado_centavos'   => 0,
                'anulado_cancelado_acumulado_centavos' => 0,
                'total_devengado_acumulado'            => 0.00,
                'total_cobrado_acumulado'              => 0.00,
                'total_comisiones_acumuladas'          => 0.00,
                'total_neto_acumulado'                 => 0.00,
                'total_reembolsos_acumulados'          => 0.00,
                'total_saldo_pendiente_acumulado'      => 0.00,
                'total_anulado_cancelado_acumulado'    => 0.00,
                'ventas_activas_acumuladas'            => 0,
                'ticket_promedio_global'               => 0.00,
                'porcentaje_cobranza_global'           => 0.00,
                'reservas_activas_acumuladas'          => 0,
                'participantes_acumulados'             => 0,
                'conjuntos_folcloricos_acumulados'     => 0,
                'danzarines_estimados_acumulados'      => 0,
                'selecciones_fotograficas_acumuladas'  => 0,
                'crecimiento_promedio_yoy_pct'         => null,
                'tasa_recurrencia_promedio_pct'        => 0.00,
                'mejor_edicion_historica'              => null,
            ],
            'serie_financiera'                 => [],
            'serie_reservas'                   => [],
            'curvas_preventa'                  => [
                'hitos_definicion' => self::HITOS_PREVENTA,
                'series_ediciones' => [],
            ],
            'retencion_clientes'               => [
                'serie_por_edicion' => [],
                'matriz_cohortes'   => [],
            ],
            'serie_folclore'                   => [],
            'serie_operaciones_postproduccion' => [],
        ];
    }
}
