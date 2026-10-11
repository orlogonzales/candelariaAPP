<?php

declare(strict_types=1);

namespace Aplicacion\Dashboard;

use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Servicio de Dominio Oficial para el Dashboard Ejecutivo y Métricas de Rendimiento (F2.12C).
 * 
 * Reglas de Negocio y Fórmulas de KPIs:
 * 1. Ventas Devengadas = SUM(ventas.total) WHERE estado IN ('CONFIRMADA', 'LIQUIDADA').
 * 2. Recaudación Neta = SUM(pagos.monto_neto_recibido) WHERE estado = 'APROBADO'.
 * 3. Total Cobrado Cliente = SUM(pagos.monto_cobrado_cliente) WHERE estado = 'APROBADO'.
 * 4. Comisiones de Pasarela = SUM(pagos.comision_pasarela) WHERE estado = 'APROBADO'.
 * 5. Saldo Pendiente = SUM(ventas.saldo_pendiente) WHERE estado IN ('CONFIRMADA', 'LIQUIDADA').
 * 6. % Cobranza = (Total Cobrado Cliente / Ventas Devengadas) * 100.
 * 7. Ticket Promedio = Ventas Devengadas / Cantidad Ventas Activas.
 * 8. Ocupación de Flota = (Pasajeros Agendados / Capacidad Total Ofertada) * 100.
 * 9. Tasa de Asistencia = (Participantes con Check-in / Total Participantes Nómina) * 100.
 * 10. Tasa de Aprobación Entregas = (Entregas Aprobadas / Entregas Totales Despachadas) * 100.
 * 
 * Garantías:
 * - Aislamiento Multitenant riguroso por organizacion_id.
 * - Cero fallback o invento de gastos/comisiones cuando no existan registros.
 * - No mezcla ingresos cobrados con ventas devengadas.
 * - Soporta casos con cero registros (empty state seguro) sin divisiones entre cero.
 */
class DashboardEjecutivoServicio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Obtiene el consolidado de métricas e indicadores clave de rendimiento (KPIs) para una edición específica.
     */
    public function obtenerKpisEjecutivos(int $organizacionId, ?int $edicionId = null, array $filtros = []): array
    {
        $edicion = $this->resolverEdicion($organizacionId, $edicionId);
        if (!$edicion) {
            return $this->generarEstructuraVacia(null);
        }

        $edId = (int) $edicion['id'];

        // 1. VENTAS Y FINANZAS (DEVENGADO, COBRADO, SALDOS, COMISIONES)
        $kpisFinanzas = $this->calcularFinanzas($organizacionId, $edId, $filtros);

        // 2. RESERVAS Y ESTADOS DE CONVERSIÓN
        $kpisReservas = $this->calcularReservas($organizacionId, $edId, $filtros);

        // 3. OPERACIONES DE CAMPO Y OCUPACIÓN DE FLOTA
        $kpisOperaciones = $this->calcularOperaciones($organizacionId, $edId, $filtros);

        // 4. PADRÓN DE ASISTENCIA Y CHECK-IN (LEY 29733)
        $kpisAsistencia = $this->calcularAsistencia($organizacionId, $edId, $filtros);

        // 5. POSTPRODUCCIÓN Y ENTREGAS AUDIOVISUALES
        $kpisPostproduccion = $this->calcularPostproduccion($organizacionId, $edId, $filtros);

        // 6. CANALES COMERCIALES Y TICKET PROMEDIO
        $distribucionCanales = $this->calcularDistribucionCanales($organizacionId, $edId);

        // 7. COMPARATIVA INTER-EDICIONES HISTÓRICA
        $comparativaHistorica = $this->obtenerComparativaHistorica($organizacionId);

        return [
            'organizacion_id'      => $organizacionId,
            'edicion_actual'       => [
                'id'          => $edId,
                'codigo'      => $edicion['codigo'],
                'nombre'      => $edicion['nombre'],
                'anio'        => (int) $edicion['anio'],
                'estado'      => $edicion['estado'],
                'fecha_inicio'=> $edicion['fecha_inicio'],
                'fecha_fin'   => $edicion['fecha_fin'],
                'es_actual'   => (bool) $edicion['es_actual'],
            ],
            'generado_en'          => date('Y-m-d H:i:s'),
            'kpis'                 => [
                'finanzas'         => $kpisFinanzas,
                'reservas'         => $kpisReservas,
                'operaciones'      => $kpisOperaciones,
                'asistencia'       => $kpisAsistencia,
                'postproduccion'   => $kpisPostproduccion,
                'canales'          => $distribucionCanales,
            ],
            'comparativa_ediciones'=> $comparativaHistorica,
        ];
    }

    /**
     * Calcula métricas financieras estrictas separando devengado de recaudado.
     */
    private function calcularFinanzas(int $organizacionId, int $edicionId, array $filtros): array
    {
        // Consulta agregada de Ventas
        $stmtV = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_ventas_registradas,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') THEN 1 ELSE 0 END), 0) as total_ventas_activas,
                COALESCE(SUM(CASE WHEN estado = 'CANCELADA' THEN 1 ELSE 0 END), 0) as total_ventas_canceladas,
                COALESCE(SUM(CASE WHEN estado = 'ANULADA' THEN 1 ELSE 0 END), 0) as total_ventas_anuladas,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') THEN total ELSE 0 END), 0.00) as total_devengado,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') THEN saldo_pendiente ELSE 0 END), 0.00) as total_saldo_pendiente,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') AND estado_financiero = 'PAGADA_TOTAL' THEN 1 ELSE 0 END), 0) as ventas_pagadas_total,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') AND estado_financiero = 'PAGO_PARCIAL' THEN 1 ELSE 0 END), 0) as ventas_pago_parcial,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') AND estado_financiero = 'NO_PAGADA' THEN 1 ELSE 0 END), 0) as ventas_no_pagadas
            FROM `ventas`
            WHERE `organizacion_id` = :org_id AND `edicion_id` = :ed_id
        ");
        $stmtV->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $v = $stmtV->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalDevengado = (float) ($v['total_devengado'] ?? 0.00);
        $totalSaldoPendiente = (float) ($v['total_saldo_pendiente'] ?? 0.00);
        $ventasActivas = (int) ($v['total_ventas_activas'] ?? 0);

        // Consulta agregada de Pagos efectivamente aprobados
        $stmtP = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_transacciones_pago,
                COALESCE(SUM(CASE WHEN estado = 'APROBADO' THEN 1 ELSE 0 END), 0) as pagos_aprobados_qty,
                COALESCE(SUM(CASE WHEN estado = 'PENDIENTE_VERIFICACION' THEN 1 ELSE 0 END), 0) as pagos_pendientes_qty,
                COALESCE(SUM(CASE WHEN estado = 'RECHAZADO' THEN 1 ELSE 0 END), 0) as pagos_rechazados_qty,
                COALESCE(SUM(CASE WHEN estado = 'APROBADO' THEN monto_cobrado_cliente ELSE 0 END), 0.00) as total_cobrado_cliente,
                COALESCE(SUM(CASE WHEN estado = 'APROBADO' THEN monto_neto_recibido ELSE 0 END), 0.00) as total_neto_recaudado,
                COALESCE(SUM(CASE WHEN estado = 'APROBADO' THEN comision_pasarela ELSE 0 END), 0.00) as total_comisiones_pasarela,
                COALESCE(SUM(CASE WHEN estado = 'APROBADO' THEN monto_reembolsado_acumulado ELSE 0 END), 0.00) as total_reembolsos
            FROM `pagos`
            WHERE `organizacion_id` = :org_id AND `edicion_id` = :ed_id
        ");
        $stmtP->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $p = $stmtP->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalCobrado = (float) ($p['total_cobrado_cliente'] ?? 0.00);
        $totalNeto = (float) ($p['total_neto_recaudado'] ?? 0.00);
        $totalComisiones = (float) ($p['total_comisiones_pasarela'] ?? 0.00);
        $totalReembolsos = (float) ($p['total_reembolsos'] ?? 0.00);

        // Fórmulas KPI
        $porcentajeCobranza = $totalDevengado > 0.00 ? round(($totalCobrado / $totalDevengado) * 100, 2) : 0.00;
        $porcentajeSaldoPendiente = $totalDevengado > 0.00 ? round(($totalSaldoPendiente / $totalDevengado) * 100, 2) : 0.00;
        $ticketPromedio = $ventasActivas > 0 ? round($totalDevengado / $ventasActivas, 2) : 0.00;

        return [
            'total_devengado'            => round($totalDevengado, 2),
            'total_cobrado_cliente'      => round($totalCobrado, 2),
            'total_neto_recaudado'       => round($totalNeto, 2),
            'total_comisiones_pasarela'  => round($totalComisiones, 2),
            'total_saldo_pendiente'      => round($totalSaldoPendiente, 2),
            'total_reembolsos'           => round($totalReembolsos, 2),
            'porcentaje_cobranza'        => $porcentajeCobranza,
            'porcentaje_saldo_pendiente' => $porcentajeSaldoPendiente,
            'ticket_promedio'            => $ticketPromedio,
            'ventas_totales_registradas' => (int) ($v['total_ventas_registradas'] ?? 0),
            'ventas_activas_confirmadas' => $ventasActivas,
            'ventas_activas_qty'         => $ventasActivas,
            'ventas_pagadas_total'       => (int) ($v['ventas_pagadas_total'] ?? 0),
            'ventas_pagadas_total_qty'   => (int) ($v['ventas_pagadas_total'] ?? 0),
            'ventas_pago_parcial'        => (int) ($v['ventas_pago_parcial'] ?? 0),
            'ventas_pago_parcial_qty'    => (int) ($v['ventas_pago_parcial'] ?? 0),
            'ventas_no_pagadas'          => (int) ($v['ventas_no_pagadas'] ?? 0),
            'ventas_no_pagadas_qty'      => (int) ($v['ventas_no_pagadas'] ?? 0),
            'pagos_aprobados_qty'        => (int) ($p['pagos_aprobados_qty'] ?? 0),
            'pagos_pendientes_qty'       => (int) ($p['pagos_pendientes_qty'] ?? 0),
        ];
    }

    /**
     * Calcula métricas del ciclo de vida de Reservas.
     */
    private function calcularReservas(int $organizacionId, int $edicionId, array $filtros): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_reservas,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'REGISTRADA') THEN 1 ELSE 0 END), 0) as reservas_activas,
                COALESCE(SUM(CASE WHEN estado = 'CONFIRMADA' THEN 1 ELSE 0 END), 0) as reservas_confirmadas,
                COALESCE(SUM(CASE WHEN estado = 'REGISTRADA' THEN 1 ELSE 0 END), 0) as reservas_registradas,
                COALESCE(SUM(CASE WHEN estado = 'CANCELADA' THEN 1 ELSE 0 END), 0) as reservas_canceladas
            FROM `reservas`
            WHERE `organizacion_id` = :org_id AND `edicion_id` = :ed_id
        ");
        $stmt->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalReservas = (int) ($r['total_reservas'] ?? 0);
        $activas = (int) ($r['reservas_activas'] ?? 0);
        $canceladas = (int) ($r['reservas_canceladas'] ?? 0);

        $tasaConversion = $totalReservas > 0 ? round(($activas / $totalReservas) * 100, 2) : 0.00;
        $tasaCancelacion = $totalReservas > 0 ? round(($canceladas / $totalReservas) * 100, 2) : 0.00;

        return [
            'total_reservas'       => $totalReservas,
            'reservas_activas'     => $activas,
            'reservas_confirmadas' => (int) ($r['reservas_confirmadas'] ?? 0),
            'reservas_registradas' => (int) ($r['reservas_registradas'] ?? 0),
            'reservas_canceladas'  => $canceladas,
            'tasa_conversion_pct'  => $tasaConversion,
            'tasa_cancelacion_pct' => $tasaCancelacion,
        ];
    }

    /**
     * Calcula métricas operativas de campo y ocupación de flota.
     */
    private function calcularOperaciones(int $organizacionId, int $edicionId, array $filtros): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                s.id as salida_id,
                s.correlativo,
                s.titulo,
                s.fecha_salida,
                s.capacidad_maxima,
                s.estado,
                COALESCE(SUM(osp.cantidad_pasajeros), 0) as pasajeros_agendados
            FROM `operacion_salidas` s
            LEFT JOIN `operacion_salida_prestaciones` osp ON osp.salida_id = s.id
            WHERE s.organizacion_id = :org_id AND s.edicion_id = :ed_id
            GROUP BY s.id
            ORDER BY s.fecha_salida ASC, s.hora_salida ASC
        ");
        $stmt->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $salidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $capacidadTotal = 0;
        $agendadosTotal = 0;
        $salidasDetalle = [];

        foreach ($salidas as $s) {
            $capMax = (int) ($s['capacidad_maxima'] ?? 0);
            $agendados = (int) ($s['pasajeros_agendados'] ?? 0);
            $capacidadTotal += $capMax;
            $agendadosTotal += $agendados;

            $ocupacionSalida = $capMax > 0 ? round(($agendados / $capMax) * 100, 1) : 0.0;
            $salidasDetalle[] = [
                'salida_id'            => (int) $s['salida_id'],
                'correlativo'          => $s['correlativo'],
                'titulo'               => $s['titulo'],
                'fecha_salida'         => $s['fecha_salida'],
                'estado'               => $s['estado'],
                'capacidad_maxima'     => $capMax,
                'pasajeros_agendados'  => $agendados,
                'porcentaje_ocupacion' => $ocupacionSalida,
            ];
        }

        $tasaOcupacionGlobal = $capacidadTotal > 0 ? round(($agendadosTotal / $capacidadTotal) * 100, 2) : 0.00;

        return [
            'total_salidas_programadas' => count($salidas),
            'capacidad_total_flota'     => $capacidadTotal,
            'pasajeros_agendados_total' => $agendadosTotal,
            'cupos_libres_flota'        => max(0, $capacidadTotal - $agendadosTotal),
            'tasa_ocupacion_flota_pct'  => $tasaOcupacionGlobal,
            'salidas'                   => $salidasDetalle,
        ];
    }

    /**
     * Calcula métricas de asistencia y check-in presencial (Ley 29733).
     */
    private function calcularAsistencia(int $organizacionId, int $edicionId, array $filtros): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                r.id as reserva_id,
                r.notas_operativas,
                COUNT(part.id) as participantes_reserva
            FROM `reservas` r
            LEFT JOIN `reserva_participantes` part ON part.reserva_id = r.id
            WHERE r.organizacion_id = :org_id AND r.edicion_id = :ed_id
              AND r.estado IN ('CONFIRMADA', 'REGISTRADA')
            GROUP BY r.id
        ");
        $stmt->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalParticipantes = 0;
        $totalAsistieron = 0;
        $totalAusentes = 0;

        foreach ($filas as $f) {
            $qty = max(1, (int)$f['participantes_reserva']);
            $totalParticipantes += $qty;

            $notas = (string) ($f['notas_operativas'] ?? '');
            if (str_contains($notas, '[CHECKIN_QR_REGISTRADO')) {
                $totalAsistieron += $qty;
            } else {
                $totalAusentes += $qty;
            }
        }

        $tasaAsistencia = $totalParticipantes > 0 ? round(($totalAsistieron / $totalParticipantes) * 100, 2) : 0.00;
        $tasaNoShow = $totalParticipantes > 0 ? round(($totalAusentes / $totalParticipantes) * 100, 2) : 0.00;

        return [
            'total_participantes' => $totalParticipantes,
            'total_asistieron'    => $totalAsistieron,
            'total_ausentes'      => $totalAusentes,
            'tasa_asistencia_pct' => $tasaAsistencia,
            'tasa_no_show_pct'    => $tasaNoShow,
        ];
    }

    /**
     * Calcula métricas de postproducción y entregas audiovisuales.
     */
    private function calcularPostproduccion(int $organizacionId, int $edicionId, array $filtros): array
    {
        // Proyectos de postproducción vinculados a la edición
        $stmtProy = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_proyectos,
                COALESCE(SUM(CASE WHEN estado = 'ENTREGADO' THEN 1 ELSE 0 END), 0) as proyectos_entregados,
                COALESCE(SUM(CASE WHEN estado = 'EN_EDICION' THEN 1 ELSE 0 END), 0) as proyectos_en_edicion
            FROM `postproduccion_proyectos`
            WHERE `organizacion_id` = :org_id AND `edicion_id` = :ed_id
        ");
        $stmtProy->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $proy = $stmtProy->fetch(PDO::FETCH_ASSOC) ?: [];

        // Entregas despachadas
        $stmtEnt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_entregas,
                COALESCE(SUM(CASE WHEN e.estado_entrega = 'APROBADA_CLIENTE' THEN 1 ELSE 0 END), 0) as entregas_aprobadas,
                COALESCE(SUM(CASE WHEN e.estado_entrega = 'OBSERVADA_CLIENTE' THEN 1 ELSE 0 END), 0) as entregas_observadas,
                COALESCE(SUM(CASE WHEN e.estado_entrega IN ('PUBLICADA', 'PREPARADA') THEN 1 ELSE 0 END), 0) as entregas_pendientes
            FROM `postproduccion_entregas` e
            JOIN `postproduccion_proyectos` p ON e.proyecto_id = p.id
            WHERE p.organizacion_id = :org_id AND p.edicion_id = :ed_id
        ");
        $stmtEnt->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $ent = $stmtEnt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalEntregas = (int) ($ent['total_entregas'] ?? 0);
        $aprobadas = (int) ($ent['entregas_aprobadas'] ?? 0);
        $tasaAprobacion = $totalEntregas > 0 ? round(($aprobadas / $totalEntregas) * 100, 2) : 0.00;

        // Galerías creadas
        $stmtGal = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM `postproduccion_galerias` g
            JOIN `postproduccion_proyectos` p ON g.proyecto_id = p.id
            WHERE p.organizacion_id = :org_id AND p.edicion_id = :ed_id
        ");
        $stmtGal->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $galeriasCreadas = (int) $stmtGal->fetchColumn();

        // Cuota de almacenamiento utilizada
        $stmtBytes = $this->pdo->prepare("
            SELECT COALESCE(SUM(a.peso_bytes), 0) as bytes_usados
            FROM `postproduccion_archivos` a
            JOIN `postproduccion_proyectos` p ON a.proyecto_id = p.id
            WHERE p.organizacion_id = :org_id AND p.edicion_id = :ed_id
        ");
        $stmtBytes->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $bytesUsados = (int) $stmtBytes->fetchColumn();
        $mbUsados = round($bytesUsados / (1024 * 1024), 2);

        return [
            'total_proyectos'          => (int) ($proy['total_proyectos'] ?? 0),
            'proyectos_entregados'     => (int) ($proy['proyectos_entregados'] ?? 0),
            'proyectos_en_edicion'     => (int) ($proy['proyectos_en_edicion'] ?? 0),
            'entregas_totales'         => $totalEntregas,
            'entregas_aprobadas'       => $aprobadas,
            'entregas_observadas'      => (int) ($ent['entregas_observadas'] ?? 0),
            'entregas_pendientes'      => (int) ($ent['entregas_pendientes'] ?? 0),
            'tasa_aprobacion_entregas_pct' => $tasaAprobacion,
            'galerias_creadas'         => $galeriasCreadas,
            'almacenamiento_bytes'     => $bytesUsados,
            'almacenamiento_total_mb'  => $mbUsados,
        ];
    }

    /**
     * Calcula la distribución de ventas por canal comercial.
     */
    private function calcularDistribucionCanales(int $organizacionId, int $edicionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                origen_tipo as canal,
                COUNT(*) as cantidad_ventas,
                COALESCE(SUM(total), 0.00) as volumen_devengado
            FROM `ventas`
            WHERE `organizacion_id` = :org_id AND `edicion_id` = :ed_id
              AND `estado` IN ('CONFIRMADA', 'LIQUIDADA')
            GROUP BY `origen_tipo`
            ORDER BY volumen_devengado DESC
        ");
        $stmt->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalVolumen = 0.0;
        foreach ($filas as $f) {
            $totalVolumen += (float) $f['volumen_devengado'];
        }

        $canales = [];
        foreach ($filas as $f) {
            $monto = (float) $f['volumen_devengado'];
            $pct = $totalVolumen > 0 ? round(($monto / $totalVolumen) * 100, 1) : 0.0;
            $canales[] = [
                'canal'             => $f['canal'],
                'cantidad_ventas'   => (int) $f['cantidad_ventas'],
                'volumen_devengado' => round($monto, 2),
                'porcentaje_volumen'=> $pct,
            ];
        }

        return $canales;
    }

    /**
     * Obtiene la comparativa histórica entre las ediciones de la organización.
     */
    public function obtenerComparativaHistorica(int $organizacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id as edicion_id,
                e.codigo,
                e.nombre,
                e.anio,
                e.estado,
                COALESCE(v.ventas_devengadas, 0.00) as ventas_devengadas,
                COALESCE(v.cantidad_ventas, 0) as cantidad_ventas,
                COALESCE(p.recaudacion_neta, 0.00) as recaudacion_neta
            FROM `ediciones_candelaria` e
            LEFT JOIN (
                SELECT `edicion_id`, 
                       COALESCE(SUM(`total`), 0.00) as ventas_devengadas,
                       COUNT(*) as cantidad_ventas
                FROM `ventas` 
                WHERE `estado` IN ('CONFIRMADA', 'LIQUIDADA') AND `organizacion_id` = :org_id1
                GROUP BY `edicion_id`
            ) v ON v.edicion_id = e.id
            LEFT JOIN (
                SELECT `edicion_id`,
                       COALESCE(SUM(`monto_neto_recibido`), 0.00) as recaudacion_neta
                FROM `pagos`
                WHERE `estado` = 'APROBADO' AND `organizacion_id` = :org_id2
                GROUP BY `edicion_id`
            ) p ON p.edicion_id = e.id
            WHERE e.organizacion_id = :org_id3
            ORDER BY e.anio DESC
            LIMIT 10
        ");
        $stmt->execute([
            'org_id1' => $organizacionId,
            'org_id2' => $organizacionId,
            'org_id3' => $organizacionId
        ]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $devengado = (float) $f['ventas_devengadas'];
            $neto = (float) $f['recaudacion_neta'];
            $qty = (int) $f['cantidad_ventas'];
            $ticket = $qty > 0 ? round($devengado / $qty, 2) : 0.00;

            $resultado[] = [
                'edicion_id'         => (int) $f['edicion_id'],
                'codigo'             => $f['codigo'],
                'nombre'             => $f['nombre'],
                'anio'               => (int) $f['anio'],
                'estado'             => $f['estado'],
                'ventas_devengadas'  => round($devengado, 2),
                'recaudacion_neta'   => round($neto, 2),
                'cantidad_ventas'    => $qty,
                'ticket_promedio'    => $ticket,
            ];
        }

        return $resultado;
    }

    private function resolverEdicion(int $organizacionId, ?int $edicionId): ?array
    {
        if ($edicionId !== null && $edicionId > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM `ediciones_candelaria` WHERE id = :id AND organizacion_id = :org_id");
            $stmt->execute(['id' => $edicionId, 'org_id' => $organizacionId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                return $res;
            }
        }

        // Buscar edición actual o la más reciente
        $stmtActual = $this->pdo->prepare("
            SELECT * FROM `ediciones_candelaria` 
            WHERE organizacion_id = :org_id AND es_actual = 1 
            ORDER BY anio DESC LIMIT 1
        ");
        $stmtActual->execute(['org_id' => $organizacionId]);
        $actual = $stmtActual->fetch(PDO::FETCH_ASSOC);
        if ($actual) {
            return $actual;
        }

        $stmtReciente = $this->pdo->prepare("
            SELECT * FROM `ediciones_candelaria` 
            WHERE organizacion_id = :org_id 
            ORDER BY anio DESC LIMIT 1
        ");
        $stmtReciente->execute(['org_id' => $organizacionId]);
        $reciente = $stmtReciente->fetch(PDO::FETCH_ASSOC);
        return $reciente ?: null;
    }

    private function generarEstructuraVacia(?int $organizacionId): array
    {
        return [
            'organizacion_id'       => $organizacionId,
            'edicion_actual'        => null,
            'generado_en'           => date('Y-m-d H:i:s'),
            'kpis'                  => [
                'finanzas'          => [
                    'total_devengado'            => 0.00,
                    'total_cobrado_cliente'      => 0.00,
                    'total_neto_recaudado'       => 0.00,
                    'total_comisiones_pasarela'  => 0.00,
                    'total_saldo_pendiente'      => 0.00,
                    'total_reembolsos'           => 0.00,
                    'porcentaje_cobranza'        => 0.00,
                    'porcentaje_saldo_pendiente' => 0.00,
                    'ticket_promedio'            => 0.00,
                    'ventas_totales_registradas' => 0,
                    'ventas_activas_confirmadas' => 0,
                    'ventas_pagadas_total'       => 0,
                    'ventas_pago_parcial'        => 0,
                    'ventas_no_pagadas'          => 0,
                    'pagos_aprobados_qty'        => 0,
                    'pagos_pendientes_qty'       => 0,
                ],
                'reservas'          => [
                    'total_reservas'       => 0,
                    'reservas_activas'     => 0,
                    'reservas_confirmadas' => 0,
                    'reservas_registradas' => 0,
                    'reservas_canceladas'  => 0,
                    'tasa_conversion_pct'  => 0.00,
                    'tasa_cancelacion_pct' => 0.00,
                ],
                'operaciones'       => [
                    'total_salidas_programadas' => 0,
                    'capacidad_total_flota'     => 0,
                    'pasajeros_agendados_total' => 0,
                    'cupos_libres_flota'        => 0,
                    'tasa_ocupacion_flota_pct'  => 0.00,
                    'salidas'                   => [],
                ],
                'asistencia'        => [
                    'total_participantes' => 0,
                    'total_asistieron'    => 0,
                    'total_ausentes'      => 0,
                    'tasa_asistencia_pct' => 0.00,
                    'tasa_no_show_pct'    => 0.00,
                ],
                'postproduccion'    => [
                    'total_proyectos'      => 0,
                    'proyectos_entregados' => 0,
                    'proyectos_en_edicion' => 0,
                    'total_entregas'       => 0,
                    'entregas_aprobadas'   => 0,
                    'entregas_observadas'  => 0,
                    'entregas_pendientes'  => 0,
                    'tasa_aprobacion_pct'  => 0.00,
                    'almacenamiento_bytes' => 0,
                    'almacenamiento_mb'    => 0.00,
                ],
                'canales'           => [],
            ],
            'comparativa_ediciones' => [],
        ];
    }
}
