<?php

declare(strict_types=1);

namespace Aplicacion\Documentos;

use Aplicacion\Servicios\FachadaPortalPublicoServicio;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Servicio de generación de Boletos Imprimibles Oficiales con Código QR Criptográfico.
 * Soporta formato institucional A4 y formato térmico continuo de 80mm.
 * Garantiza que la generación o impresión documental NO consume cupo ni altera check-in.
 */
class BoletoImpresionServicio
{
    public function __construct(
        private MotorPdfServicio $motorPdf,
        private FachadaPortalPublicoServicio $fachadaPortal,
        private PDO $pdo
    ) {
    }

    /**
     * Genera boleto en formato A4 con diseño institucional completo.
     *
     * @return array{binario_pdf: string, ruta_privada: string, correlativo: string, hash: string, token_qr: string}
     */
    public function generarBoletoA4(int $organizacionId, int $reservaId, ?int $clienteId = null, ?int $usuarioEmisorId = null): array
    {
        return $this->generarBoleto($organizacionId, $reservaId, 'A4', $clienteId, $usuarioEmisorId);
    }

    /**
     * Genera boleto en formato Ticket Térmico de 80 mm para impresión en taquilla/puerta.
     *
     * @return array{binario_pdf: string, ruta_privada: string, correlativo: string, hash: string, token_qr: string}
     */
    public function generarBoletoTicket80(int $organizacionId, int $reservaId, ?int $clienteId = null, ?int $usuarioEmisorId = null): array
    {
        return $this->generarBoleto($organizacionId, $reservaId, 'TICKET_80MM', $clienteId, $usuarioEmisorId);
    }

    /**
     * Genera el boleto según el formato solicitado y registra la bitácora documental.
     *
     * @return array{binario_pdf: string, ruta_privada: string, correlativo: string, hash: string, token_qr: string}
     */
    public function generarBoleto(
        int $organizacionId,
        int $reservaId,
        string $formato = 'A4',
        ?int $clienteId = null,
        ?int $usuarioEmisorId = null
    ): array {
        // 1. Obtener datos de la reserva y validar tenencia
        $stmtRes = $this->pdo->prepare("
            SELECT r.*,
                   v.correlativo as venta_correlativo, v.estado as venta_estado, v.estado_financiero,
                   o.codigo as org_codigo, o.razon_social, o.nombre_comercial, o.numero_documento as ruc,
                   o.sitio_web, o.correo_contacto, o.telefono_contacto, o.direccion as direccion_fiscal,
                   e.nombre as edicion_nombre, e.anio as edicion_anio
            FROM `reservas` r
            JOIN `ventas` v ON r.venta_id = v.id
            JOIN `organizaciones` o ON r.organizacion_id = o.id
            JOIN `ediciones_candelaria` e ON r.edicion_id = e.id
            WHERE r.id = :id AND r.organizacion_id = :org_id
        ");
        $stmtRes->execute(['id' => $reservaId, 'org_id' => $organizacionId]);
        $reserva = $stmtRes->fetch(PDO::FETCH_ASSOC);

        if (!$reserva) {
            throw new InvalidArgumentException("La reserva #{$reservaId} no existe en su organización.");
        }

        // Resolver titular cliente
        $titularClienteId = (int) $reserva['cliente_id'];
        if ($clienteId !== null && $clienteId !== $titularClienteId) {
            throw new InvalidArgumentException("Acceso denegado: El cliente no es titular de la reserva.");
        }

        // 2. Generar el token QR canónico mediante la fachada oficial
        $resultadoToken = $this->fachadaPortal->generarTokenBoletoQr($organizacionId, $reservaId, $titularClienteId);
        if (empty($resultadoToken['exito'])) {
            throw new RuntimeException("No se pudo emitir el boleto: " . ($resultadoToken['mensaje'] ?? 'Error desconocido'));
        }
        $tokenQr = (string) $resultadoToken['token_qr'];

        // 3. Renderizar imagen QR en Base64 PNG
        $optQr = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale'           => 6,
        ]);
        $qrPngBase64 = (new QRCode($optQr))->render($tokenQr);

        // 4. Obtener detalle de prestaciones / accesos de la reserva
        $stmtPrest = $this->pdo->prepare("
            SELECT rp.*
            FROM `reserva_prestaciones` rp
            WHERE rp.reserva_id = :reserva_id
            ORDER BY rp.fecha_servicio ASC, rp.hora_servicio ASC, rp.id ASC
        ");
        $stmtPrest->execute(['reserva_id' => $reservaId]);
        $prestaciones = $stmtPrest->fetchAll(PDO::FETCH_ASSOC);

        // 5. Construir HTML según formato
        $esTicket = ($formato === 'TICKET_80MM');
        $html = $esTicket
            ? $this->construirHtmlTicket80($reserva, $prestaciones, $qrPngBase64, $tokenQr)
            : $this->construirHtmlA4($reserva, $prestaciones, $qrPngBase64, $tokenQr);

        // 6. Renderizar binario PDF
        $formatoMotor = $esTicket ? MotorPdfServicio::FORMATO_TICKET_80MM : MotorPdfServicio::FORMATO_A4;
        $binarioPdf = $this->motorPdf->renderizar($html, $formatoMotor, MotorPdfServicio::ORIENTACION_VERTICAL);

        // 7. Guardar en almacenamiento privado
        $tipoDocEnum = $esTicket ? 'BOLETO_TICKET_80MM' : 'BOLETO_A4';
        $prefijoArchivo = $esTicket ? 'BOL-TKT' : 'BOL-A4';
        $nombreArchivo = sprintf('%s-%s-%s.pdf', $prefijoArchivo, $reserva['correlativo'], date('Ymd_His'));
        $guardado = $this->motorPdf->guardarEnAlmacenamientoPrivado(
            $binarioPdf,
            $organizacionId,
            'BOLETOS',
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

        $stmtDoc = $this->pdo->prepare("
            INSERT INTO `documentos_emitidos` (
                `organizacion_id`, `tipo_documento`, `entidad_tipo`, `entidad_id`,
                `correlativo_documento`, `formato`, `ruta_archivo_privado`, `peso_bytes`,
                `hash_sha256`, `generado_por_usuario_id`, `cliente_titular_id`, `generado_en`
            ) VALUES (
                :org_id, :tipo_doc, 'RESERVA', :ent_id,
                :correlativo, :formato_str, :ruta, :peso,
                :hash, :usuario_id, :cliente_id, NOW()
            )
        ");
        $stmtDoc->execute([
            'org_id'       => $organizacionId,
            'tipo_doc'     => $tipoDocEnum,
            'ent_id'       => $reservaId,
            'correlativo'  => (string) $reserva['correlativo'],
            'formato_str'  => $esTicket ? 'TICKET_80MM' : 'A4',
            'ruta'         => $guardado['ruta_relativa'],
            'peso'         => $guardado['peso_bytes'],
            'hash'         => $guardado['hash_sha256'],
            'usuario_id'   => $usuarioEmisorValido,
            'cliente_id'   => $titularClienteId,
        ]);

        return [
            'binario_pdf'  => $binarioPdf,
            'ruta_privada' => $guardado['ruta_absoluta'],
            'correlativo'  => (string) $reserva['correlativo'],
            'hash'         => $guardado['hash_sha256'],
            'token_qr'     => $tokenQr,
        ];
    }

    private function construirHtmlA4(array $r, array $prestaciones, string $qrDataUri, string $tokenQr): string
    {
        $estilos = PlantillaDocumentoServicio::obtenerEstilosCssA4();
        $orgNombre = htmlspecialchars((string) ($r['nombre_comercial'] ?? $r['razon_social']), ENT_QUOTES, 'UTF-8');
        $orgRazon = htmlspecialchars((string) $r['razon_social'], ENT_QUOTES, 'UTF-8');
        $orgRuc = htmlspecialchars((string) ($r['ruc'] ?? 'S/R'), ENT_QUOTES, 'UTF-8');
        $titularNombre = htmlspecialchars((string) $r['contacto_nombre'], ENT_QUOTES, 'UTF-8');
        $titularDoc = htmlspecialchars((string) ($r['contacto_numero_documento'] ?? 'No especificado'), ENT_QUOTES, 'UTF-8');
        $titularTel = htmlspecialchars((string) ($r['contacto_telefono'] ?? 'No especificado'), ENT_QUOTES, 'UTF-8');
        $titularEmail = htmlspecialchars((string) ($r['contacto_email'] ?? 'No especificado'), ENT_QUOTES, 'UTF-8');
        $correlativoReserva = htmlspecialchars((string) $r['correlativo'], ENT_QUOTES, 'UTF-8');

        $filasPrestaciones = '';
        $num = 1;
        foreach ($prestaciones as $p) {
            $concepto = htmlspecialchars((string) $p['concepto_nombre'], ENT_QUOTES, 'UTF-8');
            $fecha = !empty($p['fecha_servicio']) ? date('d/m/Y', strtotime((string) $p['fecha_servicio'])) : 'Según programa';
            $hora = !empty($p['hora_servicio']) ? substr((string) $p['hora_servicio'], 0, 5) : 'Por confirmar';
            $filasPrestaciones .= "<tr>
                <td class='text-center'>{$num}</td>
                <td><strong>{$concepto}</strong></td>
                <td class='text-center'>{$fecha}</td>
                <td class='text-center'>{$hora}</td>
                <td class='text-center'><span style='color:green; font-weight:bold;'>CONFIRMADO</span></td>
            </tr>";
            $num++;
        }

        $firmaCorta = htmlspecialchars(substr($tokenQr, -16), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Boleto Oficial {$correlativoReserva}</title>
            <style>{$estilos}</style>
        </head>
        <body>
            <table class="header-tabla">
                <tr>
                    <td class="header-logo">
                        <div class="empresa-titulo">{$orgNombre}</div>
                        <div style="font-size: 8.5pt; color: #4a5568;">RUC: {$orgRuc} | {$orgRazon}</div>
                        <div style="font-size: 8.5pt; color: #c53030; font-weight: bold;">BOLETO OFICIAL DE ACCESO Y EXPERIENCIAS</div>
                        <div style="font-size: 8pt; color: #718096;">Edición: {$r['edicion_nombre']} ({$r['edicion_anio']})</div>
                    </td>
                    <td class="header-info-empresa">
                        <div>{$r['direccion_fiscal']}</div>
                        <div>Teléfono: {$r['telefono_contacto']}</div>
                        <div>Email: {$r['correo_contacto']}</div>
                        <div>Web: {$r['sitio_web']}</div>
                    </td>
                </tr>
            </table>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                    <td style="width: 65%; vertical-align: top; padding-right: 15px;">
                        <div class="doc-titulo-box" style="margin-bottom: 12px;">
                            <div class="doc-titulo">BOLETO ELECTRÓNICO CON ACCESO QR</div>
                            <div class="doc-correlativo">RESERVA N° {$correlativoReserva} (Venta: {$r['venta_correlativo']})</div>
                        </div>

                        <table class="info-grid">
                            <tr>
                                <td class="info-label">Titular Acreditado:</td>
                                <td class="info-value"><strong>{$titularNombre}</strong></td>
                            </tr>
                            <tr>
                                <td class="info-label">Doc. Identidad:</td>
                                <td class="info-value">{$titularDoc}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Teléfono Contacto:</td>
                                <td class="info-value">{$titularTel}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Correo Titular:</td>
                                <td class="info-value">{$titularEmail}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Firma Criptográfica:</td>
                                <td class="info-value"><small style="font-family: monospace;">...{$firmaCorta} (HMAC-SHA256)</small></td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 35%; text-align: center; vertical-align: middle; border: 2px dashed #0c192c; background-color: #f7fafc; padding: 12px;">
                        <div style="font-size: 8pt; font-weight: bold; color: #0c192c; margin-bottom: 6px;">CÓDIGO QR DE CHECK-IN</div>
                        <img src="{$qrDataUri}" width="160" height="160" alt="Código QR de Acceso">
                        <div style="font-size: 7pt; color: #718096; margin-top: 6px;">
                            Presentar este código en los puntos de control y graderías autorizadas.
                        </div>
                    </td>
                </tr>
            </table>

            <h4 style="font-size: 10pt; color: #0c192c; margin-bottom: 8px;">Prestaciones y Accesos Comprendidos:</h4>
            <table class="tabla-items">
                <thead>
                    <tr>
                        <th style="width: 5%;" class="text-center">#</th>
                        <th style="width: 50%;">Prestación / Servicio Contratado</th>
                        <th style="width: 15%;" class="text-center">Fecha</th>
                        <th style="width: 15%;" class="text-center">Hora Aprox.</th>
                        <th style="width: 15%;" class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    {$filasPrestaciones}
                </tbody>
            </table>

            <div class="clausulas-box">
                <h4>Condiciones e Instrucciones Operativas de Ingreso:</h4>
                <ol style="margin: 0; padding-left: 15px; font-size: 8pt;">
                    <li><strong>Presentación Obligatoria:</strong> El titular y sus acompañantes deben presentar este boleto (físico o en pantalla de smartphone) junto con su DNI original o Pasaporte en las puertas de control.</li>
                    <li><strong>Inmutabilidad del Código QR:</strong> Este código QR contiene un token criptográfico HMAC-SHA256 único e intransferible. La reimpresión del documento no consume el derecho de acceso ni altera el estado de la reserva.</li>
                    <li><strong>Check-in en Puerta:</strong> La lectura y validación operativa del código QR se realiza exclusivamente mediante los dispositivos autorizados en los accesos de la organización.</li>
                    <li><strong>Seguridad y Aforo:</strong> Queda terminantemente prohibido el ingreso con pirotecnia, armas, envases de vidrio o sustancias peligrosas. Se aplicará derecho de admisión y aforo.</li>
                </ol>
            </div>

            <div class="footer-pagina">
                Boleto emitido por CandelariaAPP V1 &bull; Federación Regional de Folklore y Cultura de Puno &bull; Autenticidad verificada.
            </div>
        </body>
        </html>
        HTML;
    }

    private function construirHtmlTicket80(array $r, array $prestaciones, string $qrDataUri, string $tokenQr): string
    {
        $estilos = PlantillaDocumentoServicio::obtenerEstilosCssTicket80();
        $orgNombre = htmlspecialchars((string) ($r['nombre_comercial'] ?? $r['razon_social']), ENT_QUOTES, 'UTF-8');
        $orgRuc = htmlspecialchars((string) ($r['ruc'] ?? 'S/R'), ENT_QUOTES, 'UTF-8');
        $titularNombre = htmlspecialchars((string) $r['contacto_nombre'], ENT_QUOTES, 'UTF-8');
        $titularDoc = htmlspecialchars((string) ($r['contacto_numero_documento'] ?? 'S/D'), ENT_QUOTES, 'UTF-8');
        $correlativoReserva = htmlspecialchars((string) $r['correlativo'], ENT_QUOTES, 'UTF-8');
        $edicionNombre = htmlspecialchars((string) $r['edicion_nombre'], ENT_QUOTES, 'UTF-8');
        $fechaEmision = date('d/m/Y H:i');
        $firmaCorta = htmlspecialchars(substr($tokenQr, -12), ENT_QUOTES, 'UTF-8');

        $lineasPrest = '';
        foreach ($prestaciones as $p) {
            $nombre = htmlspecialchars((string) $p['concepto_nombre'], ENT_QUOTES, 'UTF-8');
            $fecha = !empty($p['fecha_servicio']) ? date('d/m', strtotime((string) $p['fecha_servicio'])) : '';
            $lineasPrest .= "<div class='ticket-fila'>&bull; {$nombre} " . ($fecha ? "({$fecha})" : "") . "</div>";
        }

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Ticket {$correlativoReserva}</title>
            <style>{$estilos}</style>
        </head>
        <body>
            <div class="ticket-header">
                <div class="ticket-empresa">{$orgNombre}</div>
                <div style="font-size: 7pt;">RUC: {$orgRuc}</div>
                <div class="ticket-edicion">{$edicionNombre}</div>
                <div class="ticket-titulo">BOLETO DE ACCESO OFICIAL</div>
                <div class="ticket-correlativo">RES: {$correlativoReserva}</div>
            </div>

            <div class="ticket-info">
                <div class="ticket-fila"><strong>TITULAR:</strong> {$titularNombre}</div>
                <div class="ticket-fila"><strong>DOC:</strong> {$titularDoc}</div>
                <div class="ticket-fila"><strong>VENTA:</strong> {$r['venta_correlativo']}</div>
                <div class="ticket-fila"><strong>EMISIÓN:</strong> {$fechaEmision}</div>
            </div>

            <div class="ticket-info">
                <div class="bold" style="margin-bottom: 3px;">PRESTACIONES INCLUIDAS:</div>
                {$lineasPrest}
            </div>

            <div class="ticket-qr-container">
                <img src="{$qrDataUri}" alt="QR Check-in">
                <div style="font-size: 6.5pt; font-family: monospace; margin-top: 3px;">SIG: ...{$firmaCorta}</div>
            </div>

            <div class="ticket-footer">
                <div><strong>INSTRUCCIONES DE ACCESO:</strong></div>
                <div>Portar DNI original para ingreso.</div>
                <div>El código QR se valida en puerta.</div>
                <div style="margin-top: 4px; font-size: 6pt; color: #555;">CandelariaAPP V1 &bull; Ticket Intransferible</div>
            </div>
        </body>
        </html>
        HTML;
    }
}
