<?php

declare(strict_types=1);

namespace Aplicacion\Documentos;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Servicio de emisión de Recibos de Caja Internos en formato PDF.
 * Exclusivo para pagos en estado APROBADO.
 * Contiene la leyenda legal expresa de no validez tributaria SUNAT.
 */
class ReciboCajaServicio
{
    public const LEYENDA_LEGAL_SUNAT = 'COMPROBANTE INTERNO DE CAJA — NO CONSTITUYE COMPROBANTE DE PAGO TRIBUTARIO SUNAT';

    public function __construct(
        private MotorPdfServicio $motorPdf,
        private PDO $pdo
    ) {
    }

    /**
     * Genera un recibo de caja interno correlativo y su correspondiente PDF oficial.
     *
     * @return array{recibo_id: int, correlativo: string, binario_pdf: string, ruta_privada: string, hash: string}
     */
    public function generarRecibo(int $organizacionId, int $pagoId, int $cajeroUsuarioId): array
    {
        // 1. Verificar si ya existe un recibo emitido para este pago (idempotencia)
        $stmtExistente = $this->pdo->prepare("
            SELECT rc.*, p.estado as pago_estado
            FROM `recibos_caja` rc
            JOIN `pagos` p ON rc.pago_id = p.id
            WHERE rc.pago_id = :pago_id AND rc.organizacion_id = :org_id
        ");
        $stmtExistente->execute(['pago_id' => $pagoId, 'org_id' => $organizacionId]);
        $reciboExistente = $stmtExistente->fetch(PDO::FETCH_ASSOC);

        if ($reciboExistente) {
            // Si ya existe el recibo, lo reconstruimos o retornamos
            return $this->regenerarPdfReciboExistente($reciboExistente, $cajeroUsuarioId);
        }

        // 2. Cargar datos del pago con bloqueo / validación multitenant
        $stmtPago = $this->pdo->prepare("
            SELECT p.*,
                   v.correlativo as venta_correlativo, v.total as venta_total,
                   v.monto_pagado as venta_monto_pagado, v.saldo_pendiente as venta_saldo_pendiente,
                   v.cliente_nombre_completo, v.cliente_numero_documento, v.cliente_tipo_documento,
                   v.cliente_email, v.cliente_telefono, v.cliente_id,
                   o.codigo as org_codigo, o.razon_social, o.nombre_comercial, o.numero_documento as ruc,
                   o.sitio_web, o.correo_contacto, o.telefono_contacto, o.direccion as direccion_fiscal,
                   e.nombre as edicion_nombre, e.anio as edicion_anio,
                   u.nombre_completo as cajero_nombre, u.correo_electronico as cajero_email
            FROM `pagos` p
            JOIN `ventas` v ON p.venta_id = v.id
            JOIN `organizaciones` o ON p.organizacion_id = o.id
            JOIN `ediciones_candelaria` e ON p.edicion_id = e.id
            LEFT JOIN `usuarios` u ON u.id = :cajero_id
            WHERE p.id = :pago_id AND p.organizacion_id = :org_id
        ");
        $stmtPago->execute([
            'pago_id'   => $pagoId,
            'org_id'    => $organizacionId,
            'cajero_id' => $cajeroUsuarioId
        ]);
        $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);

        if (!$pago) {
            throw new InvalidArgumentException("El pago #{$pagoId} no existe en su organización.");
        }

        // 3. Regla de Negocio Crítica: Solo pagos APROBADO pueden originar recibos de caja
        if ($pago['estado'] !== 'APROBADO') {
            throw new InvalidArgumentException("Solo se pueden emitir recibos de caja para pagos en estado APROBADO (estado actual: {$pago['estado']}).");
        }

        // 4. Generación atómica del correlativo de recibo (REC-YYYY-NNNNNN)
        $anio = (int) date('Y');
        $correlativo = $this->generarSiguienteCorrelativo($organizacionId, $anio);

        // 5. Inserción en recibos_caja
        $cajeroValidoId = $cajeroUsuarioId;
        $stmtUsr = $this->pdo->prepare("SELECT id FROM `usuarios` WHERE id = :id");
        $stmtUsr->execute(['id' => $cajeroUsuarioId]);
        if (!$stmtUsr->fetchColumn()) {
            $cajeroValidoId = (int) $this->pdo->query("SELECT id FROM `usuarios` WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
        }

        $concepto = "Abono a Venta {$pago['venta_correlativo']} - Festividad Candelaria {$pago['edicion_anio']}";
        $montoRecibido = (float) $pago['monto_cobrado_cliente'];

        $stmtIns = $this->pdo->prepare("
            INSERT INTO `recibos_caja` (
                `organizacion_id`, `pago_id`, `venta_id`, `correlativo`,
                `monto_recibido`, `moneda`, `metodo_pago`, `numero_operacion_bancaria`,
                `cajero_usuario_id`, `titular_nombre`, `titular_documento`, `concepto`,
                `observaciones`, `emitido_en`
            ) VALUES (
                :org_id, :pago_id, :venta_id, :correlativo,
                :monto, :moneda, :metodo_pago, :num_operacion,
                :cajero_id, :titular_nombre, :titular_doc, :concepto,
                :observaciones, NOW()
            )
        ");
        $stmtIns->execute([
            'org_id'        => $organizacionId,
            'pago_id'       => $pagoId,
            'venta_id'      => $pago['venta_id'],
            'correlativo'   => $correlativo,
            'monto'         => $montoRecibido,
            'moneda'        => $pago['moneda'],
            'metodo_pago'   => $pago['metodo_pago'],
            'num_operacion' => $pago['numero_operacion'] ?? null,
            'cajero_id'     => $cajeroValidoId,
            'titular_nombre'=> $pago['cliente_nombre_completo'],
            'titular_doc'   => $pago['cliente_numero_documento'],
            'concepto'      => $concepto,
            'observaciones' => $pago['observaciones'] ?? 'Recepción conforme en tesorería',
        ]);
        $reciboId = (int) $this->pdo->lastInsertId();

        // 6. Renderizar HTML formal para A4
        $html = $this->construirHtmlRecibo($pago, $correlativo, $concepto, $montoRecibido);

        // 7. Renderizar a binario PDF
        $binarioPdf = $this->motorPdf->renderizar($html, MotorPdfServicio::FORMATO_A4, MotorPdfServicio::ORIENTACION_VERTICAL);

        // 8. Guardar en almacenamiento privado
        $nombreArchivo = sprintf('%s-%s.pdf', $correlativo, date('Ymd_His'));
        $guardado = $this->motorPdf->guardarEnAlmacenamientoPrivado(
            $binarioPdf,
            $organizacionId,
            'RECIBOS_CAJA',
            $nombreArchivo
        );

        // 9. Actualizar hash en recibos_caja
        $stmtUpdHash = $this->pdo->prepare("UPDATE `recibos_caja` SET `hash_sha256` = :hash WHERE `id` = :id");
        $stmtUpdHash->execute(['hash' => $guardado['hash_sha256'], 'id' => $reciboId]);

        // 10. Registrar en documentos_emitidos
        $stmtDoc = $this->pdo->prepare("
            INSERT INTO `documentos_emitidos` (
                `organizacion_id`, `tipo_documento`, `entidad_tipo`, `entidad_id`,
                `correlativo_documento`, `formato`, `ruta_archivo_privado`, `peso_bytes`,
                `hash_sha256`, `generado_por_usuario_id`, `cliente_titular_id`, `generado_en`
            ) VALUES (
                :org_id, 'RECIBO_CAJA', 'PAGO', :ent_id,
                :correlativo, 'A4', :ruta, :peso,
                :hash, :usuario_id, :cliente_id, NOW()
            )
        ");
        $stmtDoc->execute([
            'org_id'       => $organizacionId,
            'ent_id'       => $pagoId,
            'correlativo'  => $correlativo,
            'ruta'         => $guardado['ruta_relativa'],
            'peso'         => $guardado['peso_bytes'],
            'hash'         => $guardado['hash_sha256'],
            'usuario_id'   => $cajeroValidoId,
            'cliente_id'   => !empty($pago['cliente_id']) ? (int) $pago['cliente_id'] : null,
        ]);

        return [
            'recibo_id'    => $reciboId,
            'correlativo'  => $correlativo,
            'binario_pdf'  => $binarioPdf,
            'ruta_privada' => $guardado['ruta_absoluta'],
            'hash'         => $guardado['hash_sha256'],
        ];
    }

    /**
     * Regenera o recupera el PDF de un recibo ya registrado previamente.
     */
    private function regenerarPdfReciboExistente(array $rc, int $cajeroUsuarioId): array
    {
        $stmtPago = $this->pdo->prepare("
            SELECT p.*,
                   v.correlativo as venta_correlativo, v.total as venta_total,
                   v.monto_pagado as venta_monto_pagado, v.saldo_pendiente as venta_saldo_pendiente,
                   v.cliente_nombre_completo, v.cliente_numero_documento, v.cliente_tipo_documento,
                   v.cliente_email, v.cliente_telefono, v.cliente_id,
                   o.codigo as org_codigo, o.razon_social, o.nombre_comercial, o.numero_documento as ruc,
                   o.sitio_web, o.correo_contacto, o.telefono_contacto, o.direccion as direccion_fiscal,
                   e.nombre as edicion_nombre, e.anio as edicion_anio,
                   u.nombre_completo as cajero_nombre, u.correo_electronico as cajero_email
            FROM `pagos` p
            JOIN `ventas` v ON p.venta_id = v.id
            JOIN `organizaciones` o ON p.organizacion_id = o.id
            JOIN `ediciones_candelaria` e ON p.edicion_id = e.id
            LEFT JOIN `usuarios` u ON u.id = :cajero_id
            WHERE p.id = :pago_id AND p.organizacion_id = :org_id
        ");
        $stmtPago->execute([
            'pago_id'   => $rc['pago_id'],
            'org_id'    => $rc['organizacion_id'],
            'cajero_id' => $cajeroUsuarioId
        ]);
        $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);

        $html = $this->construirHtmlRecibo($pago, $rc['correlativo'], $rc['concepto'], (float) $rc['monto_recibido']);
        $binarioPdf = $this->motorPdf->renderizar($html, MotorPdfServicio::FORMATO_A4, MotorPdfServicio::ORIENTACION_VERTICAL);

        $nombreArchivo = sprintf('%s-%s.pdf', $rc['correlativo'], date('Ymd_His'));
        $guardado = $this->motorPdf->guardarEnAlmacenamientoPrivado(
            $binarioPdf,
            (int) $rc['organizacion_id'],
            'RECIBOS_CAJA',
            $nombreArchivo
        );

        return [
            'recibo_id'    => (int) $rc['id'],
            'correlativo'  => (string) $rc['correlativo'],
            'binario_pdf'  => $binarioPdf,
            'ruta_privada' => $guardado['ruta_absoluta'],
            'hash'         => $guardado['hash_sha256'],
        ];
    }

    private function generarSiguienteCorrelativo(int $organizacionId, int $anio): string
    {
        $stmtLock = $this->pdo->prepare("
            INSERT INTO `recibos_caja_secuencias` (`organizacion_id`, `anio`, `ultimo_numero`)
            VALUES (:org_id, :anio, 1)
            ON DUPLICATE KEY UPDATE `ultimo_numero` = `ultimo_numero` + 1
        ");
        $stmtLock->execute(['org_id' => $organizacionId, 'anio' => $anio]);

        $stmtGet = $this->pdo->prepare("
            SELECT `ultimo_numero`
            FROM `recibos_caja_secuencias`
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
        ");
        $stmtGet->execute(['org_id' => $organizacionId, 'anio' => $anio]);
        $numero = (int) $stmtGet->fetchColumn();

        return sprintf('REC-%d-%06d', $anio, $numero);
    }

    private function construirHtmlRecibo(array $p, string $correlativo, string $concepto, float $monto): string
    {
        $estilos = PlantillaDocumentoServicio::obtenerEstilosCssA4();
        $orgNombre = htmlspecialchars((string) ($p['nombre_comercial'] ?? $p['razon_social']), ENT_QUOTES, 'UTF-8');
        $orgRazon = htmlspecialchars((string) $p['razon_social'], ENT_QUOTES, 'UTF-8');
        $orgRuc = htmlspecialchars((string) ($p['ruc'] ?? 'S/R'), ENT_QUOTES, 'UTF-8');
        $clienteNombre = htmlspecialchars(trim((string) $p['cliente_nombre_completo']), ENT_QUOTES, 'UTF-8');
        $clienteDoc = htmlspecialchars(($p['cliente_tipo_documento'] ?? 'DOC') . ': ' . ($p['cliente_numero_documento'] ?? 'S/D'), ENT_QUOTES, 'UTF-8');
        $moneda = htmlspecialchars((string) ($p['moneda'] ?? 'PEN'), ENT_QUOTES, 'UTF-8');
        $montoFormateado = number_format($monto, 2);
        $fechaEmision = date('d/m/Y H:i:s');
        $cajeroNombre = htmlspecialchars((string) ($p['cajero_nombre'] ?? 'Cajero Autorizado'), ENT_QUOTES, 'UTF-8');
        $numOperacion = htmlspecialchars((string) ($p['numero_operacion'] ?? 'PAGO_DIRECTO_CAJA'), ENT_QUOTES, 'UTF-8');
        $metodoPago = htmlspecialchars((string) $p['metodo_pago'], ENT_QUOTES, 'UTF-8');
        $leyendaSunat = self::LEYENDA_LEGAL_SUNAT;

        $totalVenta = number_format((float) $p['venta_total'], 2);
        $saldoVenta = number_format((float) $p['venta_saldo_pendiente'], 2);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Recibo de Caja {$correlativo}</title>
            <style>{$estilos}</style>
        </head>
        <body>
            <table class="header-tabla">
                <tr>
                    <td class="header-logo">
                        <div class="empresa-titulo">{$orgNombre}</div>
                        <div style="font-size: 8.5pt; color: #4a5568;">Razón Social: {$orgRazon}</div>
                        <div style="font-size: 8.5pt; color: #4a5568;">RUC: {$orgRuc}</div>
                        <div style="font-size: 8pt; color: #718096;">Edición: {$p['edicion_nombre']} ({$p['edicion_anio']})</div>
                    </td>
                    <td class="header-info-empresa">
                        <div>{$p['direccion_fiscal']}</div>
                        <div>Teléfono: {$p['telefono_contacto']}</div>
                        <div>Email: {$p['correo_contacto']}</div>
                        <div>Web: {$p['sitio_web']}</div>
                    </td>
                </tr>
            </table>

            <div class="doc-titulo-box">
                <div class="doc-titulo">RECIBO DE INGRESO A CAJA (COMPROBANTE INTERNO)</div>
                <div class="doc-correlativo">N° {$correlativo}</div>
            </div>

            <!-- ADVERTENCIA LEGAL OBLIGATORIA CONFORME A AUDITORÍA TÉCNICA -->
            <div class="leyenda-legal">
                &#9888; {$leyendaSunat} &#9888;<br>
                <small style="font-weight: normal; color: #742a2a; display: block; margin-top: 2px;">
                    Este documento acredita únicamente la recepción material o bancaria de fondos en la tesorería de la organización.
                    No sustituye la Boleta de Venta o Factura Electrónica regulada por la Resolución de Superintendencia de SUNAT.
                </small>
            </div>

            <table class="info-grid" style="margin-top: 20px;">
                <tr>
                    <td class="info-label">Recibimos de:</td>
                    <td class="info-value"><strong>{$clienteNombre}</strong></td>
                    <td class="info-label">Fecha y Hora:</td>
                    <td class="info-value">{$fechaEmision}</td>
                </tr>
                <tr>
                    <td class="info-label">Documento Identidad:</td>
                    <td class="info-value">{$clienteDoc}</td>
                    <td class="info-label">Medio de Pago:</td>
                    <td class="info-value"><strong>{$metodoPago}</strong></td>
                </tr>
                <tr>
                    <td class="info-label">Operación / Ref.:</td>
                    <td class="info-value">{$numOperacion}</td>
                    <td class="info-label">Venta Asociada:</td>
                    <td class="info-value">{$p['venta_correlativo']} (Ref. Pago: {$p['correlativo']})</td>
                </tr>
                <tr>
                    <td class="info-label">Concepto de Abono:</td>
                    <td class="info-value" colspan="3">{$concepto}</td>
                </tr>
            </table>

            <div style="background-color: #f7fafc; border: 2px solid #cbd5e0; padding: 15px; margin: 25px 0; text-align: center;">
                <div style="font-size: 11pt; color: #4a5568; text-transform: uppercase; font-weight: bold; margin-bottom: 5px;">
                    Importe Total Recibido en Caja
                </div>
                <div style="font-size: 24pt; font-weight: bold; color: #0c192c;">
                    {$moneda} {$montoFormateado}
                </div>
                <div style="font-size: 8.5pt; color: #718096; margin-top: 5px;">
                    Estado de Venta: Total Contratado: {$moneda} {$totalVenta} | Saldo Pendiente Actual: {$moneda} {$saldoVenta}
                </div>
            </div>

            <div class="clausulas-box">
                <h4>Observaciones de Tesorería:</h4>
                <ul style="margin: 0; padding-left: 15px; font-size: 8pt;">
                    <li>El presente recibo deja constancia del abono efectivamente registrado y validado en las cuentas de la organización.</li>
                    <li>Conserve este documento como comprobante de pago interno hasta la culminación de sus actividades programadas.</li>
                    <li>Cualquier aclaración sobre este abono debe citar el correlativo {$correlativo} y el código de pago {$p['correlativo']}.</li>
                </ul>
            </div>

            <table class="firmas-tabla" style="margin-top: 50px;">
                <tr>
                    <td>
                        <div class="linea-firma">
                            <strong>{$cajeroNombre}</strong><br>
                            Caja y Tesorería Responsable<br>
                            <small style="color: #718096;">Firma y Sello de Recepción Conforme</small>
                        </div>
                    </td>
                    <td>
                        <div class="linea-firma">
                            <strong>{$clienteNombre}</strong><br>
                            Cliente / Pagador Titular<br>
                            <small style="color: #718096;">Firma de Conformidad de Abono</small>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="footer-pagina">
                Documento de Control Interno de Tesorería &bull; CandelariaAPP V1 &bull; Prohibida su adulteración o cesión indebida.
            </div>
        </body>
        </html>
        HTML;
    }
}
