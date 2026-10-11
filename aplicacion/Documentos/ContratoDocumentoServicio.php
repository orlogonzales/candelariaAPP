<?php

declare(strict_types=1);

namespace Aplicacion\Documentos;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Servicio de generación de Contratos Oficiales de Servicios Turísticos en formato PDF.
 * Formaliza la relación contractual a partir de una Venta CONFIRMADA.
 */
class ContratoDocumentoServicio
{
    public function __construct(
        private MotorPdfServicio $motorPdf,
        private PDO $pdo
    ) {
    }

    /**
     * Genera el contrato PDF oficial a partir de una venta CONFIRMADA y registra la bitácora documental.
     *
     * @return array{binario_pdf: string, ruta_privada: string, correlativo: string, hash: string}
     */
    public function generarPdf(int $organizacionId, int $ventaId, ?int $usuarioEmisorId = null): array
    {
        // 1. Cargar datos de la venta, organización, cliente y edición
        $stmtVta = $this->pdo->prepare("
            SELECT v.*,
                   o.codigo as org_codigo, o.razon_social, o.nombre_comercial, o.numero_documento as ruc,
                   o.sitio_web, o.correo_contacto, o.telefono_contacto, o.direccion as direccion_fiscal,
                   e.nombre as edicion_nombre, e.anio as edicion_anio,
                   cl.id as cliente_id_rel,
                   p.direccion as cliente_direccion
            FROM `ventas` v
            JOIN `organizaciones` o ON v.organizacion_id = o.id
            JOIN `ediciones_candelaria` e ON v.edicion_id = e.id
            LEFT JOIN `clientes` cl ON v.cliente_id = cl.id
            LEFT JOIN `personas` p ON cl.persona_id = p.id
            WHERE v.id = :id AND v.organizacion_id = :org_id
        ");
        $stmtVta->execute(['id' => $ventaId, 'org_id' => $organizacionId]);
        $venta = $stmtVta->fetch(PDO::FETCH_ASSOC);

        if (!$venta) {
            throw new InvalidArgumentException("La venta #{$ventaId} no existe en su organización.");
        }

        // 2. Validación de Estado: Solo ventas CONFIRMADAS pueden emitir contrato
        if ($venta['estado'] !== 'CONFIRMADA') {
            throw new InvalidArgumentException("Solo se pueden generar contratos para ventas en estado CONFIRMADA (estado actual: {$venta['estado']}).");
        }

        // 3. Cargar líneas de la venta
        $stmtLineas = $this->pdo->prepare("
            SELECT vl.*
            FROM `venta_lineas` vl
            WHERE vl.venta_id = :venta_id
            ORDER BY vl.orden ASC, vl.id ASC
        ");
        $stmtLineas->execute(['venta_id' => $ventaId]);
        $lineas = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);

        // 4. Correlativo del contrato (CON-YYYY-NNNNNN basado en correlativo de venta)
        $correlativoContrato = str_replace('VTA-', 'CON-', (string) $venta['correlativo']);
        if ($correlativoContrato === $venta['correlativo']) {
            $correlativoContrato = 'CON-' . $venta['correlativo'];
        }

        // 5. Renderizar vista HTML formal
        $html = $this->construirHtmlContrato($venta, $lineas, $correlativoContrato);

        // 6. Renderizar a binario PDF A4
        $binarioPdf = $this->motorPdf->renderizar($html, MotorPdfServicio::FORMATO_A4, MotorPdfServicio::ORIENTACION_VERTICAL);

        // 7. Guardar en almacenamiento privado seguro
        $nombreArchivo = sprintf('%s-%s.pdf', $correlativoContrato, date('Ymd_His'));
        $guardado = $this->motorPdf->guardarEnAlmacenamientoPrivado(
            $binarioPdf,
            $organizacionId,
            'CONTRATOS',
            $nombreArchivo
        );

        // 8. Registrar en bitácora documentos_emitidos
        $usuarioEmisorValido = null;
        if ($usuarioEmisorId !== null && $usuarioEmisorId > 0) {
            $stmtUsr = $this->pdo->prepare("SELECT id FROM `usuarios` WHERE id = :id");
            $stmtUsr->execute(['id' => $usuarioEmisorId]);
            if ($stmtUsr->fetchColumn()) {
                $usuarioEmisorValido = $usuarioEmisorId;
            }
        }

        $stmtReg = $this->pdo->prepare("
            INSERT INTO `documentos_emitidos` (
                `organizacion_id`, `tipo_documento`, `entidad_tipo`, `entidad_id`,
                `correlativo_documento`, `formato`, `ruta_archivo_privado`, `peso_bytes`,
                `hash_sha256`, `generado_por_usuario_id`, `cliente_titular_id`, `generado_en`
            ) VALUES (
                :org_id, 'CONTRATO', 'VENTA', :ent_id,
                :correlativo, 'A4', :ruta, :peso,
                :hash, :usuario_id, :cliente_id, NOW()
            )
        ");
        $stmtReg->execute([
            'org_id'       => $organizacionId,
            'ent_id'       => $ventaId,
            'correlativo'  => $correlativoContrato,
            'ruta'         => $guardado['ruta_relativa'],
            'peso'         => $guardado['peso_bytes'],
            'hash'         => $guardado['hash_sha256'],
            'usuario_id'   => $usuarioEmisorValido,
            'cliente_id'   => !empty($venta['cliente_id']) ? (int) $venta['cliente_id'] : null,
        ]);

        return [
            'binario_pdf'  => $binarioPdf,
            'ruta_privada' => $guardado['ruta_absoluta'],
            'correlativo'  => $correlativoContrato,
            'hash'         => $guardado['hash_sha256'],
        ];
    }

    private function construirHtmlContrato(array $v, array $lineas, string $correlativoContrato): string
    {
        $estilos = PlantillaDocumentoServicio::obtenerEstilosCssA4();
        $clienteNombre = htmlspecialchars(trim((string) $v['cliente_nombre_completo']), ENT_QUOTES, 'UTF-8');
        $clienteDoc = htmlspecialchars(($v['cliente_tipo_documento'] ?? 'DOC') . ': ' . ($v['cliente_numero_documento'] ?? 'S/D'), ENT_QUOTES, 'UTF-8');
        $clienteTel = htmlspecialchars((string) ($v['cliente_telefono'] ?? 'No especificado'), ENT_QUOTES, 'UTF-8');
        $clienteEmail = htmlspecialchars((string) ($v['cliente_email'] ?? 'No especificado'), ENT_QUOTES, 'UTF-8');
        $clienteDir = htmlspecialchars((string) ($v['cliente_direccion'] ?? 'No registrada'), ENT_QUOTES, 'UTF-8');

        $orgNombre = htmlspecialchars((string) ($v['nombre_comercial'] ?? $v['razon_social']), ENT_QUOTES, 'UTF-8');
        $orgRazon = htmlspecialchars((string) $v['razon_social'], ENT_QUOTES, 'UTF-8');
        $orgRuc = htmlspecialchars((string) ($v['ruc'] ?? 'S/R'), ENT_QUOTES, 'UTF-8');
        $moneda = htmlspecialchars((string) ($v['moneda'] ?? 'PEN'), ENT_QUOTES, 'UTF-8');
        $fechaVenta = date('d/m/Y', strtotime((string) $v['fecha_venta']));

        $canalFormalizacion = htmlspecialchars((string) ($v['origen_tipo'] ?? 'SISTEMA_VENTAS'), ENT_QUOTES, 'UTF-8');
        $ipFormalizacion = htmlspecialchars((string) ($v['ip_origen'] ?? '127.0.0.1 (Registro Directo)'), ENT_QUOTES, 'UTF-8');

        $filasHtml = '';
        $num = 1;
        foreach ($lineas as $l) {
            $desc = htmlspecialchars((string) ($l['concepto_nombre'] ?? 'Prestación de Servicios'), ENT_QUOTES, 'UTF-8');
            $cant = number_format((float) $l['cantidad'], 2);
            $pu = number_format((float) $l['precio_unitario'], 2);
            $sub = number_format((float) $l['subtotal'], 2);
            $filasHtml .= "<tr>
                <td class='text-center'>{$num}</td>
                <td><strong>{$desc}</strong><br><small style='color:#718096;'>Código: {$l['concepto_codigo']} | Tipo: {$l['tipo_linea']}</small></td>
                <td class='text-center'>{$cant}</td>
                <td class='text-right'>{$moneda} {$pu}</td>
                <td class='text-right'>{$moneda} {$sub}</td>
            </tr>";
            $num++;
        }

        $totalFormateado = number_format((float) $v['total'], 2);
        $pagadoFormateado = number_format((float) ($v['monto_pagado'] ?? 0), 2);
        $saldoFormateado = number_format((float) ($v['saldo_pendiente'] ?? $v['total']), 2);
        $estadoFinanciero = htmlspecialchars((string) ($v['estado_financiero'] ?? 'NO_PAGADA'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Contrato {$correlativoContrato}</title>
            <style>{$estilos}</style>
        </head>
        <body>
            <table class="header-tabla">
                <tr>
                    <td class="header-logo">
                        <div class="empresa-titulo">{$orgNombre}</div>
                        <div style="font-size: 8.5pt; color: #4a5568;">Razón Social: {$orgRazon}</div>
                        <div style="font-size: 8.5pt; color: #4a5568;">RUC: {$orgRuc}</div>
                        <div style="font-size: 8pt; color: #718096;">Edición Oficial: {$v['edicion_nombre']} ({$v['edicion_anio']})</div>
                    </td>
                    <td class="header-info-empresa">
                        <div>{$v['direccion_fiscal']}</div>
                        <div>Teléfono: {$v['telefono_contacto']}</div>
                        <div>Email: {$v['correo_contacto']}</div>
                        <div>Web: {$v['sitio_web']}</div>
                    </td>
                </tr>
            </table>

            <div class="doc-titulo-box">
                <div class="doc-titulo">CONTRATO DE PRESTACIÓN DE SERVICIOS TURÍSTICOS Y EXPERIENCIAS</div>
                <div class="doc-correlativo">N° {$correlativoContrato} (Ref. Venta: {$v['correlativo']})</div>
            </div>

            <p style="font-size: 8.5pt; text-align: justify; margin-bottom: 12px;">
                Conste por el presente documento el <strong>Contrato de Prestación de Servicios Turísticos, Acceso y Experiencias Culturales</strong> 
                que celebran, de una parte, <strong>{$orgRazon}</strong>, con RUC N° <strong>{$orgRuc}</strong>, en adelante <strong>EL PRESTADOR</strong>; 
                y de otra parte, don/doña <strong>{$clienteNombre}</strong>, identificado(a) con <strong>{$clienteDoc}</strong>, con domicilio en {$clienteDir}, 
                en adelante <strong>EL CLIENTE / USUARIO TITULAR</strong>, en los términos y condiciones siguientes:
            </p>

            <table class="info-grid">
                <tr>
                    <td class="info-label">Cliente Titular:</td>
                    <td class="info-value">{$clienteNombre}</td>
                    <td class="info-label">Fecha Celebración:</td>
                    <td class="info-value">{$fechaVenta}</td>
                </tr>
                <tr>
                    <td class="info-label">Documento Identidad:</td>
                    <td class="info-value">{$clienteDoc}</td>
                    <td class="info-label">Canal / Origen:</td>
                    <td class="info-value">{$canalFormalizacion}</td>
                </tr>
                <tr>
                    <td class="info-label">Teléfono / WhatsApp:</td>
                    <td class="info-value">{$clienteTel}</td>
                    <td class="info-label">IP Formalización:</td>
                    <td class="info-value">{$ipFormalizacion}</td>
                </tr>
                <tr>
                    <td class="info-label">Correo Electrónico:</td>
                    <td class="info-value">{$clienteEmail}</td>
                    <td class="info-label">Estado Financiero:</td>
                    <td class="info-value"><strong>{$estadoFinanciero}</strong></td>
                </tr>
            </table>

            <table class="tabla-items">
                <thead>
                    <tr>
                        <th style="width: 5%;" class="text-center">#</th>
                        <th style="width: 55%;">Prestaciones y Servicios Contratados</th>
                        <th style="width: 10%;" class="text-center">Cant.</th>
                        <th style="width: 15%;" class="text-right">Precio Unit.</th>
                        <th style="width: 15%;" class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    {$filasHtml}
                </tbody>
            </table>

            <table class="totales-tabla">
                <tr>
                    <td class="text-right">Monto Total Contratado:</td>
                    <td class="text-right">{$moneda} {$totalFormateado}</td>
                </tr>
                <tr>
                    <td class="text-right">Abonos Acreditados:</td>
                    <td class="text-right">{$moneda} {$pagadoFormateado}</td>
                </tr>
                <tr>
                    <td class="text-right total-final">Saldo Pendiente:</td>
                    <td class="text-right total-final">{$moneda} {$saldoFormateado}</td>
                </tr>
            </table>

            <div class="clausulas-box">
                <h4>Cláusulas Contractuales Generales:</h4>
                <ol style="margin: 0; padding-left: 14px; font-size: 7.8pt;">
                    <li><strong>PRIMERA (Objeto):</strong> EL PRESTADOR se compromete a brindar las prestaciones, accesos, traslados o paquetes detallados en la presente orden contractual durante la Festividad de la Virgen de la Candelaria.</li>
                    <li><strong>SEGUNDA (Cronograma y Aforo):</strong> La prestación efectiva se regirá estrictamente por el cronograma oficial de la Federación Regional de Folklore y Cultura de Puno (FRFCP) y las resoluciones de aforo vigentes de las autoridades de seguridad y defensa civil.</li>
                    <li><strong>TERCERA (Check-in e Identidad):</strong> Para acceder a los servicios, el titular y los beneficiarios deberán portar su Documento Nacional de Identidad original y presentar el Boleto Oficial provisto de código QR unívoco intransferible.</li>
                    <li><strong>CUARTA (Reprogramación y Fuerza Mayor):</strong> Las eventuales postergaciones derivadas de conmoción civil, fuerza mayor o disposiciones gubernamentales imprevistas darán lugar a la reprogramación de prestaciones de mutuo acuerdo, con apego irrestricto al Código de Protección y Defensa del Consumidor (Ley N° 29571).</li>
                    <li><strong>QUINTA (Comportamiento y Seguridad):</strong> EL CLIENTE se obliga a respetar las instrucciones de los coordinadores de campo y los límites de aforo, quedando prohibido el ingreso con elementos inflamables o sustancias ilícitas.</li>
                    <li><strong>SEXTA (Comprobación de Pago):</strong> La cancelación total del importe estipulado es requisito indispensable para la validación definitiva del servicio y emisión de comprobantes de caja.</li>
                </ol>
            </div>

            <table class="firmas-tabla">
                <tr>
                    <td>
                        <div class="linea-firma">
                            <strong>{$orgNombre}</strong><br>
                            Firma de Conformidad del Prestador<br>
                            <small style="color: #718096;">Representante / Asesor Comercial</small>
                        </div>
                    </td>
                    <td>
                        <div class="linea-firma">
                            <strong>{$clienteNombre}</strong><br>
                            Firma de Conformidad del Cliente Titular<br>
                            <small style="color: #718096;">{$clienteDoc}</small>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="footer-pagina">
                Contrato registrado en el Sistema Transaccional CandelariaAPP V1 &bull; Emisión documental verificada mediante hash SHA-256.
            </div>
        </body>
        </html>
        HTML;
    }
}
