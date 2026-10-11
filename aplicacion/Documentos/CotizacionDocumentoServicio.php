<?php

declare(strict_types=1);

namespace Aplicacion\Documentos;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Servicio de generación de Cotizaciones Oficiales en formato PDF.
 * Respeta estrictamente los datos históricos congelados en cotizacion_lineas (precios inmutables).
 */
class CotizacionDocumentoServicio
{
    public function __construct(
        private MotorPdfServicio $motorPdf,
        private PDO $pdo
    ) {
    }

    /**
     * Genera el archivo PDF oficial de una cotización y registra la bitácora documental.
     *
     * @return array{binario_pdf: string, ruta_privada: string, correlativo: string, hash: string}
     */
    public function generarPdf(int $organizacionId, int $cotizacionId, ?int $usuarioEmisorId = null): array
    {
        // 1. Cargar datos de la cotización y organización con aislamiento multitenant
        $stmtCot = $this->pdo->prepare("
            SELECT c.*, 
                   o.codigo as org_codigo, o.razon_social, o.nombre_comercial, o.numero_documento as ruc,
                   o.sitio_web, o.correo_contacto, o.telefono_contacto, o.direccion as direccion_fiscal,
                   e.nombre as edicion_nombre,
                   cl.id as cliente_id,
                   p.nombres as cliente_nombres, p.apellidos as cliente_apellidos,
                   p.numero_documento as cliente_documento, p.correo_electronico as cliente_correo,
                   p.telefono_whatsapp as cliente_telefono,
                   td.codigo as tipo_doc_codigo
            FROM `cotizaciones` c
            JOIN `organizaciones` o ON c.organizacion_id = o.id
            JOIN `ediciones_candelaria` e ON c.edicion_id = e.id
            JOIN `clientes` cl ON c.cliente_id = cl.id
            JOIN `personas` p ON cl.persona_id = p.id
            LEFT JOIN `tipos_documento` td ON p.tipo_documento_id = td.id
            WHERE c.id = :id AND c.organizacion_id = :org_id
        ");
        $stmtCot->execute(['id' => $cotizacionId, 'org_id' => $organizacionId]);
        $cotizacion = $stmtCot->fetch(PDO::FETCH_ASSOC);

        if (!$cotizacion) {
            throw new InvalidArgumentException("La cotización #{$cotizacionId} no existe en su organización.");
        }

        // 2. Cargar líneas congeladas históricas (precios exactos guardados al emitir)
        $stmtLineas = $this->pdo->prepare("
            SELECT cl.*,
                   CASE 
                       WHEN cl.tipo_linea = 'PAQUETE' THEN pq.nombre
                       ELSE it.nombre
                   END as descripcion_item,
                   CASE 
                       WHEN cl.tipo_linea = 'PAQUETE' THEN pq.codigo
                       ELSE it.codigo
                   END as codigo_referencia
            FROM `cotizacion_lineas` cl
            LEFT JOIN `paquetes` pq ON cl.paquete_id = pq.id
            LEFT JOIN `ofertas_items_edicion` oie ON cl.oferta_item_id = oie.id
            LEFT JOIN `items_comerciales` it ON oie.item_comercial_id = it.id
            WHERE cl.cotizacion_id = :cot_id
            ORDER BY cl.id ASC
        ");
        $stmtLineas->execute(['cot_id' => $cotizacionId]);
        $lineas = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);

        // 3. Renderizar vista HTML formal
        $html = $this->construirHtmlCotizacion($cotizacion, $lineas);

        // 4. Renderizar a binario PDF
        $binarioPdf = $this->motorPdf->renderizar($html, MotorPdfServicio::FORMATO_A4, MotorPdfServicio::ORIENTACION_VERTICAL);

        // 5. Guardar en almacenamiento privado seguro
        $nombreArchivo = sprintf('COT-%s-%s.pdf', $cotizacion['correlativo'], date('Ymd_His'));
        $guardado = $this->motorPdf->guardarEnAlmacenamientoPrivado(
            $binarioPdf,
            $organizacionId,
            'COTIZACIONES',
            $nombreArchivo
        );

        // 6. Registrar en bitácora documentos_emitidos
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
                `correlativo_documento`, `formato`, `version`, `es_vigente`, `estado_documento`,
                `ruta_archivo_privado`, `peso_bytes`,
                `hash_sha256`, `generado_por_usuario_id`, `cliente_titular_id`, `generado_en`
            ) VALUES (
                :org_id, 'COTIZACION', 'COTIZACION', :ent_id,
                :correlativo, 'A4', 1, 1, 'VIGENTE',
                :ruta, :peso,
                :hash, :usuario_id, :cliente_id, NOW()
            )
        ");
        $stmtReg->execute([
            'org_id'       => $organizacionId,
            'ent_id'       => $cotizacionId,
            'correlativo'  => $cotizacion['correlativo'],
            'ruta'         => $guardado['ruta_relativa'],
            'peso'         => $guardado['peso_bytes'],
            'hash'         => $guardado['hash_sha256'],
            'usuario_id'   => $usuarioEmisorValido,
            'cliente_id'   => (int) $cotizacion['cliente_id'],
        ]);

        return [
            'binario_pdf'  => $binarioPdf,
            'ruta_privada' => $guardado['ruta_absoluta'],
            'correlativo'  => (string) $cotizacion['correlativo'],
            'hash'         => $guardado['hash_sha256'],
            'version'      => 1,
        ];
    }

    private function construirHtmlCotizacion(array $c, array $lineas): string
    {
        $estilos = PlantillaDocumentoServicio::obtenerEstilosCssA4();
        $clienteNombre = htmlspecialchars(trim("{$c['cliente_nombres']} {$c['cliente_apellidos']}"), ENT_QUOTES, 'UTF-8');
        $clienteDoc = htmlspecialchars(($c['tipo_doc_codigo'] ?? 'DOC') . ': ' . ($c['cliente_documento'] ?? 'S/D'), ENT_QUOTES, 'UTF-8');
        $orgNombre = htmlspecialchars($c['nombre_comercial'] ?? $c['razon_social'], ENT_QUOTES, 'UTF-8');
        $orgRuc = htmlspecialchars($c['ruc'] ?? 'S/R', ENT_QUOTES, 'UTF-8');
        $moneda = htmlspecialchars($c['moneda'] ?? 'PEN', ENT_QUOTES, 'UTF-8');

        $filasHtml = '';
        $num = 1;
        foreach ($lineas as $l) {
            $desc = htmlspecialchars($l['descripcion_item'] ?? ('Ítem #' . $l['id']), ENT_QUOTES, 'UTF-8');
            $cant = number_format((float) $l['cantidad'], 2);
            $pu = number_format((float) $l['precio_unitario'], 2);
            $sub = number_format((float) $l['subtotal'], 2);
            $filasHtml .= "<tr>
                <td class='text-center'>{$num}</td>
                <td><strong>{$desc}</strong><br><small style='color:#718096;'>Tipo: {$l['tipo_linea']}</small></td>
                <td class='text-center'>{$cant}</td>
                <td class='text-right'>{$moneda} {$pu}</td>
                <td class='text-right'>{$moneda} {$sub}</td>
            </tr>";
            $num++;
        }

        $totalFormateado = number_format((float) $c['total'], 2);
        $subtotalFormateado = number_format((float) ($c['subtotal'] ?? $c['total']), 2);
        $fechaEmision = date('d/m/Y H:i', strtotime($c['creado_en']));
        $validez = !empty($c['valido_hasta']) ? date('d/m/Y', strtotime($c['valido_hasta'])) : '15 días calendario';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Cotización {$c['correlativo']}</title>
            <style>{$estilos}</style>
        </head>
        <body>
            <table class="header-tabla">
                <tr>
                    <td class="header-logo">
                        <div class="empresa-titulo">{$orgNombre}</div>
                        <div style="font-size: 8.5pt; color: #4a5568;">RUC: {$orgRuc}</div>
                        <div style="font-size: 8pt; color: #718096;">Edición: {$c['edicion_nombre']}</div>
                    </td>
                    <td class="header-info-empresa">
                        <div>{$c['direccion_fiscal']}</div>
                        <div>Teléfono: {$c['telefono_contacto']}</div>
                        <div>Email: {$c['correo_contacto']}</div>
                        <div>Web: {$c['sitio_web']}</div>
                    </td>
                </tr>
            </table>

            <div class="doc-titulo-box">
                <div class="doc-titulo">COTIZACIÓN COMERCIAL FORMAL</div>
                <div class="doc-correlativo">N° {$c['correlativo']}</div>
            </div>

            <table class="info-grid">
                <tr>
                    <td class="info-label">Cliente Titular:</td>
                    <td class="info-value">{$clienteNombre}</td>
                    <td class="info-label">Fecha Emisión:</td>
                    <td class="info-value">{$fechaEmision}</td>
                </tr>
                <tr>
                    <td class="info-label">Documento Identidad:</td>
                    <td class="info-value">{$clienteDoc}</td>
                    <td class="info-label">Vigencia Oferta:</td>
                    <td class="info-value"><strong>{$validez}</strong></td>
                </tr>
                <tr>
                    <td class="info-label">Teléfono / WhatsApp:</td>
                    <td class="info-value">{$c['cliente_telefono']}</td>
                    <td class="info-label">Estado Comercial:</td>
                    <td class="info-value">{$c['estado']}</td>
                </tr>
            </table>

            <table class="tabla-items">
                <thead>
                    <tr>
                        <th style="width: 5%;" class="text-center">#</th>
                        <th style="width: 55%;">Descripción de Prestación / Paquete</th>
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
                    <td class="text-right">Subtotal:</td>
                    <td class="text-right">{$moneda} {$subtotalFormateado}</td>
                </tr>
                <tr>
                    <td class="text-right total-final">TOTAL ESTIMADO:</td>
                    <td class="text-right total-final">{$moneda} {$totalFormateado}</td>
                </tr>
            </table>

            <div class="clausulas-box">
                <h4>Condiciones Comerciales y Operativas:</h4>
                <ol style="margin: 0; padding-left: 15px;">
                    <li>Los precios consignados en la presente cotización son soberanos y congelados durante el plazo de vigencia estipulado.</li>
                    <li>La cotización no garantiza la reserva física ni el bloqueo de cupos de aforo hasta su aceptación y confirmación de pago.</li>
                    <li>Las prestaciones están sujetas al cronograma oficial emitido por la Federación Regional de Folklore y Cultura de Puno.</li>
                    <li>Moneda de pago: {$moneda}. Los abonos bancarios deben ser verificados por caja para la emisión del comprobante respectivo.</li>
                </ol>
            </div>

            <table class="firmas-tabla">
                <tr>
                    <td>
                        <div class="linea-firma">
                            <strong>{$orgNombre}</strong><br>
                            Asesor Comercial / Atención Oficial
                        </div>
                    </td>
                    <td>
                        <div class="linea-firma">
                            <strong>{$clienteNombre}</strong><br>
                            Cliente / Adquirente Titular
                        </div>
                    </td>
                </tr>
            </table>

            <div class="footer-pagina">
                Documento generado por el Sistema Transaccional CandelariaAPP V1 &bull; Prohibida su alteración o manipulación no autorizada.
            </div>
        </body>
        </html>
        HTML;
    }
}
