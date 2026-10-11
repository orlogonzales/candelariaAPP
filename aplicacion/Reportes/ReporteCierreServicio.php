<?php

declare(strict_types=1);

namespace Aplicacion\Reportes;

use Aplicacion\Documentos\MotorPdfServicio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Servicio de Generación y Exportación de Reportes Oficiales de Cierre (Fase 2.12B).
 * 
 * Reportes Oficiales Soportados:
 * 1. Libro de Ingresos y Cobranzas (Finanzas y Conciliación)
 * 2. Manifiesto de Salidas Operativas (Operaciones de Campo y Flota)
 * 3. Padrón Oficial de Asistencia y Check-in (Logística, Seguridad y Ley 29733)
 * 
 * Formatos de Exportación:
 * - CSV protegido contra inyección de fórmulas (Formula Injection) con UTF-8 BOM.
 * - XLSX / XML estructurado con encabezados, estilos y metadatos institucionales.
 * - PDF Institucional formal con membrete, hash de integridad probatorio y leyenda no tributaria.
 */
class ReporteCierreServicio
{
    public const TIPO_LIBRO_INGRESOS = 'LIBRO_INGRESOS';
    public const TIPO_MANIFIESTO_SALIDAS = 'MANIFIESTO_SALIDAS';
    public const TIPO_PADRON_ASISTENCIA = 'PADRON_ASISTENCIA';

    private PDO $pdo;
    private MotorPdfServicio $motorPdf;

    public function __construct(
        ?PDO $pdo = null,
        ?MotorPdfServicio $motorPdf = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->motorPdf = $motorPdf ?? new MotorPdfServicio();
    }

    // ==============================================================================
    // 1. LIBRO DE INGRESOS Y COBRANZAS (FINANZAS)
    // ==============================================================================

    /**
     * Extrae y concilia los datos del Libro Oficial de Ingresos y Cobranzas.
     */
    public function obtenerLibroIngresos(int $organizacionId, int $edicionId, array $filtros = []): array
    {
        $this->validarEdicion($organizacionId, $edicionId);

        $sql = "
            SELECT 
                v.id as venta_id,
                v.correlativo as correlativo_venta,
                v.fecha_venta,
                v.cliente_nombre_completo as cliente_nombre,
                v.cliente_numero_documento as cliente_documento,
                v.estado as estado_venta,
                v.estado_financiero,
                v.moneda,
                v.subtotal as subtotal_venta,
                v.total as total_venta,
                v.monto_pagado as total_pagado_venta,
                v.saldo_pendiente as saldo_pendiente_venta,
                p.id as pago_id,
                p.correlativo as correlativo_pago,
                p.metodo_pago,
                p.estado as estado_pago,
                p.monto_cobrado_cliente,
                p.comision_pasarela,
                p.monto_neto_recibido,
                p.fecha_pago,
                p.numero_operacion_bancaria as referencia_transaccion,
                r.correlativo as correlativo_recibo
            FROM `ventas` v
            LEFT JOIN `pagos` p ON p.venta_id = v.id AND p.estado IN ('APROBADO', 'PENDIENTE_VERIFICACION')
            LEFT JOIN `recibos_caja` r ON r.pago_id = p.id
            WHERE v.organizacion_id = :org_id AND v.edicion_id = :ed_id
        ";

        if (!empty($filtros['estado_financiero'])) {
            $sql .= " AND v.estado_financiero = :estado_fin";
        }
        if (!empty($filtros['metodo_pago'])) {
            $sql .= " AND p.metodo_pago = :metodo_pago";
        }
        $sql .= " ORDER BY v.id ASC, p.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $params = ['org_id' => $organizacionId, 'ed_id' => $edicionId];
        if (!empty($filtros['estado_financiero'])) {
            $params['estado_fin'] = $filtros['estado_financiero'];
        }
        if (!empty($filtros['metodo_pago'])) {
            $params['metodo_pago'] = $filtros['metodo_pago'];
        }
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupación y conciliación de totales
        $totalVentasFacturado = 0.0;
        $totalCobrado = 0.0;
        $totalRecaudadoNeto = 0.0;
        $totalComisiones = 0.0;
        $totalSaldos = 0.0;
        $ventasProcesadas = [];
        $items = [];

        foreach ($filas as $f) {
            $vId = (int) $f['venta_id'];
            if (!isset($ventasProcesadas[$vId])) {
                $ventasProcesadas[$vId] = true;
                if (in_array($f['estado_venta'], ['CONFIRMADA', 'LIQUIDADA'], true)) {
                    $totalVentasFacturado += (float) $f['total_venta'];
                    $totalSaldos += (float) $f['saldo_pendiente_venta'];
                }
            }

            if ($f['pago_id'] !== null && $f['estado_pago'] === 'APROBADO') {
                $totalCobrado += (float) $f['monto_cobrado_cliente'];
                $totalRecaudadoNeto += (float) $f['monto_neto_recibido'];
                $totalComisiones += (float) $f['comision_pasarela'];
            }

            $items[] = [
                'venta_id'            => $vId,
                'correlativo_venta'   => $f['correlativo_venta'],
                'fecha_venta'         => $f['fecha_venta'],
                'cliente_nombre'      => $f['cliente_nombre'],
                'cliente_documento'   => $this->enmascararDocumento($f['cliente_documento']),
                'estado_venta'        => $f['estado_venta'],
                'estado_financiero'   => $f['estado_financiero'],
                'moneda'              => $f['moneda'] ?? 'PEN',
                'total_venta'         => (float) $f['total_venta'],
                'saldo_pendiente'     => (float) $f['saldo_pendiente_venta'],
                'correlativo_pago'    => $f['correlativo_pago'] ?? '-',
                'metodo_pago'         => $f['metodo_pago'] ?? '-',
                'estado_pago'         => $f['estado_pago'] ?? '-',
                'monto_cobrado'       => $f['monto_cobrado_cliente'] !== null ? (float) $f['monto_cobrado_cliente'] : 0.00,
                'comision_pasarela'   => $f['comision_pasarela'] !== null ? (float) $f['comision_pasarela'] : 0.00,
                'monto_neto'          => $f['monto_neto_recibido'] !== null ? (float) $f['monto_neto_recibido'] : 0.00,
                'fecha_pago'          => $f['fecha_pago'] ?? '-',
                'referencia_bancaria' => $f['referencia_transaccion'] ?? '-',
                'correlativo_recibo'  => $f['correlativo_recibo'] ?? '-',
            ];
        }

        return [
            'tipo_reporte' => self::TIPO_LIBRO_INGRESOS,
            'titulo'       => 'Libro Oficial de Ingresos y Cobranzas',
            'edicion_id'   => $edicionId,
            'generado_en'  => date('Y-m-d H:i:s'),
            'totales'      => [
                'total_ventas'              => round($totalVentasFacturado, 2),
                'total_ventas_devengadas'   => round($totalVentasFacturado, 2),
                'total_ventas_facturado'    => round($totalVentasFacturado, 2),
                'total_cobrado'             => round($totalCobrado, 2),
                'total_cobros_aprobados'    => round($totalCobrado, 2),
                'total_comisiones_pasarela' => round($totalComisiones, 2),
                'total_comisiones_deduc'    => round($totalComisiones, 2),
                'total_neto_bancos'         => round($totalRecaudadoNeto, 2),
                'total_recaudado_neto'      => round($totalRecaudadoNeto, 2),
                'total_saldo_pendiente'     => round($totalSaldos, 2),
                'total_saldo_por_cobrar'    => round($totalSaldos, 2),
                'total_saldos_por_cobrar'   => round($totalSaldos, 2),
                'cantidad_registros'        => count($items),
                'cantidad_ventas'           => count($ventasProcesadas),
            ],
            'items'        => $items,
        ];
    }

    // ==============================================================================
    // 2. MANIFIESTO DE SALIDAS OPERATIVAS (OPERACIONES DE CAMPO)
    // ==============================================================================

    /**
     * Extrae y estructura los despachos de salidas de campo, vehículos y guías.
     */
    public function obtenerManifiestoSalidas(int $organizacionId, int $edicionId, array $filtros = []): array
    {
        $this->validarEdicion($organizacionId, $edicionId);

        $sql = "
            SELECT 
                s.id as salida_id,
                s.correlativo as correlativo_salida,
                s.titulo as titulo_salida,
                s.fecha_salida,
                s.hora_citacion,
                s.hora_salida,
                s.punto_encuentro,
                s.tipo_capacidad,
                s.capacidad_maxima,
                s.estado as estado_salida,
                ic.nombre as item_nombre,
                COALESCE(SUM(osp.cantidad_pasajeros), 0) as total_pasajeros_agendados
            FROM `operacion_salidas` s
            JOIN `items_comerciales` ic ON s.item_comercial_id = ic.id
            LEFT JOIN `operacion_salida_prestaciones` osp ON osp.salida_id = s.id
            WHERE s.organizacion_id = :org_id AND s.edicion_id = :ed_id
        ";

        if (!empty($filtros['estado_salida'])) {
            $sql .= " AND s.estado = :estado_salida";
        }
        $sql .= " GROUP BY s.id ORDER BY s.fecha_salida ASC, s.hora_salida ASC";

        $stmt = $this->pdo->prepare($sql);
        $params = ['org_id' => $organizacionId, 'ed_id' => $edicionId];
        if (!empty($filtros['estado_salida'])) {
            $params['estado_salida'] = $filtros['estado_salida'];
        }
        $stmt->execute($params);
        $salidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $totalCapacidad = 0;
        $totalAgendados = 0;

        foreach ($salidas as $s) {
            $salidaId = (int) $s['salida_id'];
            $capMax = (int) ($s['capacidad_maxima'] ?? 0);
            $agendados = (int) ($s['total_pasajeros_agendados'] ?? 0);

            $totalCapacidad += $capMax;
            $totalAgendados += $agendados;

            // Consultar recursos asignados (Vehículo, Conductor, Guía)
            $stmtRec = $this->pdo->prepare("
                SELECT 
                    osr.rol_operativo,
                    r.identificacion_oficial as vehiculo_placa,
                    r.nombre as vehiculo_nombre,
                    p.nombres, p.apellidos, p.numero_documento
                FROM `operacion_salida_recursos` osr
                LEFT JOIN `operacion_recursos` r ON osr.recurso_fisico_id = r.id
                LEFT JOIN `personas` p ON osr.persona_id = p.id
                WHERE osr.salida_id = :salida_id
            ");
            $stmtRec->execute(['salida_id' => $salidaId]);
            $recursos = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

            $guia = '-';
            $conductor = '-';
            $vehiculo = '-';

            foreach ($recursos as $rec) {
                if (in_array($rec['rol_operativo'], ['GUIA_PRINCIPAL', 'GUIA_ASISTENTE'], true)) {
                    $guia = trim(($rec['nombres'] ?? '') . ' ' . ($rec['apellidos'] ?? ''));
                } elseif (in_array($rec['rol_operativo'], ['CONDUCTOR', 'PATRON_LANCHA'], true)) {
                    $conductor = trim(($rec['nombres'] ?? '') . ' ' . ($rec['apellidos'] ?? ''));
                }
                if (!empty($rec['vehiculo_placa']) || !empty($rec['vehiculo_nombre'])) {
                    $vehiculo = trim(($rec['vehiculo_nombre'] ?? '') . ' [' . ($rec['vehiculo_placa'] ?? 'S/P') . ']');
                }
            }

            // Ocupación en porcentaje
            $porcentajeOcupacion = $capMax > 0 ? round(($agendados / $capMax) * 100, 1) : 0.0;

            $items[] = [
                'salida_id'            => $salidaId,
                'correlativo_salida'   => $s['correlativo_salida'],
                'titulo_salida'        => $s['titulo_salida'],
                'item_nombre'          => $s['item_nombre'],
                'fecha_salida'         => $s['fecha_salida'],
                'hora_citacion'        => substr((string)$s['hora_citacion'], 0, 5),
                'hora_salida'          => substr((string)$s['hora_salida'], 0, 5),
                'punto_encuentro'      => $s['punto_encuentro'] ?? 'Punto Designado',
                'estado_salida'        => $s['estado_salida'],
                'capacidad_maxima'     => $capMax,
                'pasajeros_agendados'  => $agendados,
                'porcentaje_ocupacion' => $porcentajeOcupacion,
                'vehiculo_asignado'    => $vehiculo,
                'conductor_asignado'   => $conductor,
                'guia_asignado'        => $guia,
            ];
        }

        $ocupacionPct = $totalCapacidad > 0 ? round(($totalAgendados / $totalCapacidad) * 100, 1) : 0.0;

        return [
            'tipo_reporte' => self::TIPO_MANIFIESTO_SALIDAS,
            'titulo'       => 'Manifiesto Oficial de Salidas Operativas y Flota',
            'edicion_id'   => $edicionId,
            'generado_en'  => date('Y-m-d H:i:s'),
            'totales'      => [
                'total_salidas'             => count($items),
                'total_capacidad_flota'     => $totalCapacidad,
                'capacidad_total_ofert'     => $totalCapacidad,
                'total_pasajeros_asignados' => $totalAgendados,
                'total_pasajeros_agend'     => $totalAgendados,
                'tasa_ocupacion_flota_pct'  => $ocupacionPct,
                'ocupacion_promedio_pct'    => $ocupacionPct,
            ],
            'items'        => $items,
        ];
    }

    // ==============================================================================
    // 3. PADRÓN OFICIAL DE ASISTENCIA Y CHECK-IN (LEY 29733)
    // ==============================================================================

    /**
     * Extrae el padrón individual de participantes, boletos y registro atómico de asistencia.
     * Aplica minimización de datos personales conforme a la Ley N° 29733.
     */
    public function obtenerPadronAsistencia(int $organizacionId, int $edicionId, array $filtros = []): array
    {
        $this->validarEdicion($organizacionId, $edicionId);

        $sql = "
            SELECT 
                r.id as reserva_id,
                r.correlativo as correlativo_reserva,
                r.cliente_id,
                r.notas_operativas,
                cli_p.nombres as titular_nombres,
                cli_p.apellidos as titular_apellidos,
                cli_p.numero_documento as titular_documento,
                part.id as participante_id,
                part.nombres as participante_nombres,
                part.apellidos as participante_apellidos,
                part.numero_documento as participante_documento,
                doc.correlativo_documento as codigo_seguridad,
                doc.hash_sha256 as token_qr,
                s.correlativo as correlativo_salida,
                s.titulo as titulo_salida
            FROM `reservas` r
            JOIN `clientes` cli ON r.cliente_id = cli.id
            JOIN `personas` cli_p ON cli.persona_id = cli_p.id
            LEFT JOIN `reserva_participantes` part ON part.reserva_id = r.id
            LEFT JOIN `documentos_emitidos` doc ON doc.entidad_tipo = 'RESERVA' AND doc.entidad_id = r.id AND doc.es_vigente = 1
            LEFT JOIN `reserva_prestaciones` rp ON rp.reserva_id = r.id
            LEFT JOIN `operacion_salida_prestaciones` osp ON osp.prestacion_id = rp.id
            LEFT JOIN `operacion_salidas` s ON osp.salida_id = s.id
            WHERE r.organizacion_id = :org_id AND r.edicion_id = :ed_id
              AND r.estado IN ('CONFIRMADA', 'REGISTRADA')
            ORDER BY r.id ASC, part.id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $totalAsistieron = 0;
        $totalAusentes = 0;

        foreach ($filas as $f) {
            $notasOp = (string) ($f['notas_operativas'] ?? '');
            $estadoCheckin = 'PENDIENTE';
            $fechaCheckin = '-';

            if (str_contains($notasOp, '[CHECKIN_QR_REGISTRADO')) {
                $estadoCheckin = 'INGRESADO';
                if (preg_match('/\[CHECKIN_QR_REGISTRADO:\s*([^\]]+)\]/', $notasOp, $coincidencias)) {
                    $fechaCheckin = trim($coincidencias[1]);
                }
            }

            if (!empty($filtros['estado_checkin'])) {
                if ($filtros['estado_checkin'] === 'INGRESADO' && $estadoCheckin !== 'INGRESADO') {
                    continue;
                }
                if ($filtros['estado_checkin'] !== 'INGRESADO' && $estadoCheckin === 'INGRESADO') {
                    continue;
                }
            }

            if ($estadoCheckin === 'INGRESADO') {
                $totalAsistieron++;
            } else {
                $totalAusentes++;
            }

            $nomParticipante = trim(($f['participante_nombres'] ?? '') . ' ' . ($f['participante_apellidos'] ?? ''));
            if ($nomParticipante === '') {
                $nomParticipante = trim(($f['titular_nombres'] ?? '') . ' ' . ($f['titular_apellidos'] ?? ''));
            }

            $docRaw = !empty($f['participante_documento']) ? (string)$f['participante_documento'] : (string)($f['titular_documento'] ?? '');
            $docEnmascarado = $this->enmascararDocumento($docRaw);

            $tokenRaw = !empty($f['token_qr']) ? (string)$f['token_qr'] : (!empty($f['codigo_seguridad']) ? (string)$f['codigo_seguridad'] : '');
            $tokenCorto = !empty($tokenRaw) ? (substr($tokenRaw, 0, 10) . '...' . substr($tokenRaw, -6)) : '-';

            $codSeguridad = !empty($f['codigo_seguridad']) ? (string)$f['codigo_seguridad'] : ('SEC-' . str_pad((string)$f['reserva_id'], 6, '0', STR_PAD_LEFT));

            $items[] = [
                'reserva_id'            => (int) $f['reserva_id'],
                'correlativo_reserva'   => $f['correlativo_reserva'],
                'titular_nombre'        => trim(($f['titular_nombres'] ?? '') . ' ' . ($f['titular_apellidos'] ?? '')),
                'participante_nombre'   => $nomParticipante,
                'documento_minimizado'  => $docEnmascarado,
                'codigo_seguridad_qr'   => $codSeguridad,
                'token_qr_resumido'     => $tokenCorto,
                'estado_checkin'        => $estadoCheckin,
                'fecha_checkin'         => $fechaCheckin,
                'salida_asignada'       => !empty($f['correlativo_salida']) ? ($f['correlativo_salida'] . ' - ' . $f['titulo_salida']) : 'Sin Salida Asignada',
                'notas_operativas'      => $notasOp,
            ];
        }

        $totalRegistros = count($items);

        return [
            'tipo_reporte' => self::TIPO_PADRON_ASISTENCIA,
            'titulo'       => 'Padrón Oficial de Asistencia y Check-in de Participantes',
            'edicion_id'   => $edicionId,
            'generado_en'  => date('Y-m-d H:i:s'),
            'totales'      => [
                'total_participantes'   => $totalRegistros,
                'total_asistieron'      => $totalAsistieron,
                'total_ausentes'        => $totalAusentes,
                'tasa_asistencia_pct'   => $totalRegistros > 0 ? round(($totalAsistieron / $totalRegistros) * 100, 1) : 0.0,
            ],
            'items'        => $items,
        ];
    }

    // ==============================================================================
    // 4. EXPORTADORES OFICIALES (CSV, XLSX, PDF)
    // ==============================================================================

    /**
     * Exporta el reporte a formato CSV protegido rigurosamente contra inyección de fórmulas.
     * Incorpora UTF-8 BOM (\xEF\xBB\xBF) para compatibilidad nativa con Microsoft Excel.
     */
    public function exportarCsv(string $tipoReporte, array $reporteData): string
    {
        $buffer = fopen('php://memory', 'r+');
        if ($buffer === false) {
            throw new \RuntimeException("No se pudo inicializar buffer en memoria para CSV.");
        }

        // 1. Escribir BOM UTF-8
        fwrite($buffer, "\xEF\xBB\xBF");

        // 2. Encabezados institucionales del reporte
        fputcsv($buffer, [$this->sanitizarParaCsv($reporteData['titulo'] ?? 'Reporte Oficial')]);
        fputcsv($buffer, [$this->sanitizarParaCsv('Generado: ' . ($reporteData['generado_en'] ?? date('Y-m-d H:i:s')))]);
        fputcsv($buffer, [$this->sanitizarParaCsv('CandelariaAPP - Sistema Oficial de Gestión de Festividades')]);
        fputcsv($buffer, []); // Línea vacía separadora

        $items = $reporteData['items'] ?? [];

        if (empty($items)) {
            fputcsv($buffer, [$this->sanitizarParaCsv('No se encontraron registros para los filtros especificados.')]);
            rewind($buffer);
            $csv = stream_get_contents($buffer);
            fclose($buffer);
            return (string)$csv;
        }

        // 3. Escribir fila de cabeceras de columnas
        $columnas = array_keys($items[0]);
        $cabecerasSanitizadas = array_map(fn($col) => $this->sanitizarParaCsv(strtoupper(str_replace('_', ' ', $col))), $columnas);
        fputcsv($buffer, $cabecerasSanitizadas);

        // 4. Escribir filas de datos sanitizadas
        foreach ($items as $item) {
            $filaSanitizada = [];
            foreach ($item as $valor) {
                $filaSanitizada[] = $this->sanitizarParaCsv($valor);
            }
            fputcsv($buffer, $filaSanitizada);
        }

        // 5. Escribir bloque de totales
        if (!empty($reporteData['totales'])) {
            fputcsv($buffer, []);
            fputcsv($buffer, [$this->sanitizarParaCsv('--- RESUMEN Y TOTALES OFICIALES ---')]);
            foreach ($reporteData['totales'] as $clave => $val) {
                fputcsv($buffer, [
                    $this->sanitizarParaCsv(strtoupper(str_replace('_', ' ', $clave))),
                    $this->sanitizarParaCsv($val)
                ]);
            }
        }

        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        return (string) $csv;
    }

    /**
     * Exporta el reporte a formato XLSX estructurado mediante XML SpreadsheetML de alta compatibilidad.
     */
    public function exportarXlsx(string $tipoReporte, array $reporteData): string
    {
        $titulo = htmlspecialchars((string)($reporteData['titulo'] ?? 'Reporte Oficial'), ENT_XML1, 'UTF-8');
        $generado = htmlspecialchars((string)($reporteData['generado_en'] ?? date('Y-m-d H:i:s')), ENT_XML1, 'UTF-8');
        $items = $reporteData['items'] ?? [];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<!-- Microsoft Excel XML SpreadsheetML Formato Oficial CandelariaAPP -->' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

        // Estilos
        $xml .= '<Styles>' . "\n";
        $xml .= ' <Style ss:ID="Default" ss:Name="Normal"><Font ss:FontName="Calibri" ss:Size="11" ss:Color="#000000"/><Alignment ss:Vertical="Center"/></Style>' . "\n";
        $xml .= ' <Style ss:ID="Titulo"><Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#8B0000"/><Alignment ss:Horizontal="Left"/></Style>' . "\n";
        $xml .= ' <Style ss:ID="Header"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1B365D" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>' . "\n";
        $xml .= ' <Style ss:ID="DatoNumero"><NumberFormat ss:Format="#,##0.00"/><Alignment ss:Horizontal="Right"/></Style>' . "\n";
        $xml .= ' <Style ss:ID="DatoTexto"><NumberFormat ss:Format="@"/><Alignment ss:Horizontal="Left"/></Style>' . "\n";
        $xml .= ' <Style ss:ID="DatoCentro"><Alignment ss:Horizontal="Center"/></Style>' . "\n";
        $xml .= ' <Style ss:ID="Total"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#000000"/><Interior ss:Color="#EAEAEA" ss:Pattern="Solid"/></Style>' . "\n";
        $xml .= '</Styles>' . "\n";

        $xml .= '<Worksheet ss:Name="Reporte">' . "\n";
        $xml .= '<Table>' . "\n";

        // Fila de Título
        $xml .= '<Row><Cell ss:StyleID="Titulo"><Data ss:Type="String">' . $titulo . '</Data></Cell></Row>' . "\n";
        $xml .= '<Row><Cell><Data ss:Type="String">Fecha de Emisión: ' . $generado . ' | CandelariaAPP Oficial</Data></Cell></Row>' . "\n";
        $xml .= '<Row></Row>' . "\n";

        if (!empty($items)) {
            // Cabeceras
            $xml .= '<Row>' . "\n";
            foreach (array_keys($items[0]) as $col) {
                $nombreCol = htmlspecialchars(strtoupper(str_replace('_', ' ', $col)), ENT_XML1, 'UTF-8');
                $xml .= ' <Cell ss:StyleID="Header"><Data ss:Type="String">' . $nombreCol . '</Data></Cell>' . "\n";
            }
            $xml .= '</Row>' . "\n";

            // Datos
            foreach ($items as $item) {
                $xml .= '<Row>' . "\n";
                foreach ($item as $valor) {
                    if (is_int($valor) || is_float($valor) || (is_numeric($valor) && strlen((string)$valor) > 0 && !str_starts_with((string)$valor, '0') && !str_contains((string)$valor, '-'))) {
                        $xml .= ' <Cell ss:StyleID="DatoNumero"><Data ss:Type="Number">' . $valor . '</Data></Cell>' . "\n";
                    } else {
                        $sanitizado = htmlspecialchars((string) $this->sanitizarParaCsv($valor), ENT_XML1, 'UTF-8');
                        $xml .= ' <Cell ss:StyleID="DatoTexto"><Data ss:Type="String">' . $sanitizado . '</Data></Cell>' . "\n";
                    }
                }
                $xml .= '</Row>' . "\n";
            }
        }

        // Totales al pie
        if (!empty($reporteData['totales'])) {
            $xml .= '<Row></Row>' . "\n";
            $xml .= '<Row><Cell ss:StyleID="Total"><Data ss:Type="String">RESUMEN OFICIAL</Data></Cell><Cell ss:StyleID="Total"><Data ss:Type="String">VALOR</Data></Cell></Row>' . "\n";
            foreach ($reporteData['totales'] as $clave => $val) {
                $nombreClave = htmlspecialchars(strtoupper(str_replace('_', ' ', $clave)), ENT_XML1, 'UTF-8');
                $xml .= '<Row><Cell ss:StyleID="DatoTexto"><Data ss:Type="String">' . $nombreClave . '</Data></Cell>';
                if (is_numeric($val)) {
                    $xml .= '<Cell ss:StyleID="DatoNumero"><Data ss:Type="Number">' . $val . '</Data></Cell></Row>' . "\n";
                } else {
                    $xml .= '<Cell ss:StyleID="DatoTexto"><Data ss:Type="String">' . htmlspecialchars((string)$val, ENT_XML1, 'UTF-8') . '</Data></Cell></Row>' . "\n";
                }
            }
        }

        $xml .= '</Table>' . "\n";
        $xml .= '</Worksheet>' . "\n";
        $xml .= '</Workbook>';

        return $xml;
    }

    /**
     * Exporta el reporte a documento PDF institucional mediante Dompdf.
     */
    public function exportarPdf(string $tipoReporte, array $reporteData, array $tenantInfo, array $edicionInfo): string
    {
        $titulo = htmlspecialchars((string)($reporteData['titulo'] ?? 'Reporte Oficial'), ENT_QUOTES, 'UTF-8');
        $generado = htmlspecialchars((string)($reporteData['generado_en'] ?? date('Y-m-d H:i:s')), ENT_QUOTES, 'UTF-8');
        $orgNombre = htmlspecialchars((string)($tenantInfo['nombre_comercial'] ?? 'Organización Candelaria'), ENT_QUOTES, 'UTF-8');
        $edicionNombre = htmlspecialchars((string)($edicionInfo['nombre'] ?? 'Edición Festividad'), ENT_QUOTES, 'UTF-8');

        // Hash de integridad del reporte
        $hashIntegridad = hash('sha256', json_encode($reporteData, JSON_UNESCAPED_UNICODE));
        $hashResumido = substr($hashIntegridad, 0, 16) . '...' . substr($hashIntegridad, -16);

        $orientacion = ($tipoReporte === self::TIPO_PADRON_ASISTENCIA) ? 'portrait' : 'landscape';

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #333; margin: 15px; }
                .header-table { width: 100%; border-bottom: 2px solid #8B0000; padding-bottom: 8px; margin-bottom: 12px; }
                .header-title { font-size: 14px; font-weight: bold; color: #8B0000; text-transform: uppercase; }
                .header-meta { font-size: 8px; color: #666; }
                .table-data { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .table-data th { background-color: #1B365D; color: #FFFFFF; font-weight: bold; padding: 5px 4px; text-align: left; font-size: 8px; }
                .table-data td { padding: 4px; border-bottom: 1px solid #E0E0E0; font-size: 8px; }
                .table-data tr:nth-child(even) td { background-color: #F9FAFB; }
                .totales-box { width: 45%; margin-top: 15px; border: 1px solid #D1D5DB; background: #F3F4F6; padding: 8px; float: right; }
                .totales-box table { width: 100%; border-collapse: collapse; }
                .totales-box td { padding: 3px; font-size: 8.5px; }
                .totales-box td.label { font-weight: bold; }
                .totales-box td.val { text-align: right; font-weight: bold; }
                .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 7.5px; color: #777; border-top: 1px solid #CCC; padding-top: 4px; text-align: center; }
                .badge { padding: 2px 4px; border-radius: 3px; font-weight: bold; font-size: 7.5px; }
                .badge-success { background: #DEF7EC; color: #03543F; }
                .badge-warning { background: #FEF08A; color: #854D0E; }
                .badge-danger { background: #FDE8E8; color: #9B1C1C; }
                .badge-info { background: #E1EFFE; color: #1E429F; }
            </style>
        </head>
        <body>
            <table class="header-table">
                <tr>
                    <td>
                        <div class="header-title">' . $titulo . '</div>
                        <div class="header-meta"><strong>Organización:</strong> ' . $orgNombre . ' | <strong>Festividad:</strong> ' . $edicionNombre . '</div>
                    </td>
                    <td style="text-align: right; font-size: 8px; color: #555;">
                        <div><strong>Corte Oficial:</strong> ' . $generado . '</div>
                        <div><strong>Sello Hash:</strong> ' . $hashResumido . '</div>
                        <div style="color: #9B1C1C; font-weight: bold;">DOCUMENTO DE CONTROL INTERNO (NO TRIBUTARIO)</div>
                    </td>
                </tr>
            </table>';

        $items = $reporteData['items'] ?? [];
        if (!empty($items)) {
            $html .= '<table class="table-data">';
            $html .= '<thead><tr>';
            foreach (array_keys($items[0]) as $col) {
                $html .= '<th>' . htmlspecialchars(strtoupper(str_replace('_', ' ', $col)), ENT_QUOTES, 'UTF-8') . '</th>';
            }
            $html .= '</tr></thead><tbody>';

            foreach ($items as $item) {
                $html .= '<tr>';
                foreach ($item as $k => $v) {
                    $strV = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
                    if (str_contains($k, 'estado')) {
                        $clase = 'badge-info';
                        if (in_array($v, ['APROBADO', 'CONFIRMADA', 'PAGADA_TOTAL', 'INGRESADO', 'FINALIZADA'], true)) {
                            $clase = 'badge-success';
                        } elseif (in_array($v, ['PENDIENTE', 'PAGO_PARCIAL', 'REGISTRADA', 'EN_CHECKIN'], true)) {
                            $clase = 'badge-warning';
                        } elseif (in_array($v, ['RECHAZADO', 'CANCELADA', 'ANULADA', 'NO_SHOW', 'NO_PAGADA'], true)) {
                            $clase = 'badge-danger';
                        }
                        $html .= '<td><span class="badge ' . $clase . '">' . $strV . '</span></td>';
                    } elseif (is_numeric($v) && !str_starts_with((string)$v, '0')) {
                        $html .= '<td style="text-align: right;">' . number_format((float)$v, 2) . '</td>';
                    } else {
                        $html .= '<td>' . $strV . '</td>';
                    }
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<p style="text-align: center; color: #666; padding: 20px;">No se registraron movimientos en el período evaluado.</p>';
        }

        // Totales al pie
        if (!empty($reporteData['totales'])) {
            $html .= '<div class="totales-box"><table>';
            foreach ($reporteData['totales'] as $k => $val) {
                $strK = htmlspecialchars(strtoupper(str_replace('_', ' ', $k)), ENT_QUOTES, 'UTF-8');
                $strVal = is_numeric($val) ? number_format((float)$val, 2) : htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
                $html .= '<tr><td class="label">' . $strK . ':</td><td class="val">' . $strVal . '</td></tr>';
            }
            $html .= '</table></div><div style="clear: both;"></div>';
        }

        $html .= '<div class="footer">
            CandelariaAPP &copy; ' . date('Y') . ' | Este reporte oficial tiene carácter probatorio interno y no reemplaza comprobantes electrónicos de pago conforme a normativa SUNAT.
        </div>
        </body></html>';

        return $this->motorPdf->renderizar($html, MotorPdfServicio::FORMATO_A4, $orientacion);
    }

    // ==============================================================================
    // 5. HELPER DE SEGURIDAD Y PRIVACIDAD
    // ==============================================================================

    /**
     * Sanitiza celdas contra Formula Injection (CSV Injection).
     * Si el contenido comienza con caracteres de comando (+, -, =, @, \t, \r), antepone un apóstrofe.
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

        // Si es puramente numérico (ej: "250.50"), no alterarlo
        if (is_numeric($str) && !str_starts_with($str, '+') && !str_starts_with($str, '-')) {
            return $valor;
        }

        // Detectar si el primer caracter es fórmula o comando peligroso
        $primerCaracter = $str[0];
        if (in_array($primerCaracter, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }

        return $str;
    }

    /**
     * Minimiza el número de documento de identidad para proteger datos personales (Ley N° 29733).
     * Ejemplo: "72819283" -> "72****83"
     */
    public function enmascararDocumento(?string $documento): string
    {
        if ($documento === null || trim($documento) === '') {
            return '-';
        }
        $doc = trim($documento);
        $len = strlen($doc);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        $visiblesInicio = 2;
        $visiblesFin = 2;
        return substr($doc, 0, $visiblesInicio) . '****' . substr($doc, -$visiblesFin);
    }

    private function validarEdicion(int $organizacionId, int $edicionId): void
    {
        $stmt = $this->pdo->prepare("SELECT id FROM `ediciones_candelaria` WHERE id = :id AND organizacion_id = :org_id");
        $stmt->execute(['id' => $edicionId, 'org_id' => $organizacionId]);
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException("La edición #{$edicionId} no existe o no pertenece a la organización.");
        }
    }
}
