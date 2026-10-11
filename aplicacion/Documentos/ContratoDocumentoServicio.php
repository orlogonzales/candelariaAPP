<?php

declare(strict_types=1);

namespace Aplicacion\Documentos;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Servicio de generación y gestión del ciclo de vida de Contratos Oficiales de Servicios Turísticos en PDF.
 * Governa formalmente el ciclo de vida: BORRADOR -> EMITIDO -> ACEPTADO -> ANULADO,
 * separando estrictamente la emisión contractual, la aceptación del cliente, la confirmación comercial y el pago acreditado.
 */
class ContratoDocumentoServicio
{
    public const VERSION_CLAUSULAS_CANONICA = 'v1.0-2026';

    public function __construct(
        private MotorPdfServicio $motorPdf,
        private PDO $pdo
    ) {
    }

    /**
     * Emite o recupera el contrato formal para una venta en estado CONFIRMADA.
     * Si el contrato ya existe y no se fuerza la reemisión, retorna el documento vigente de forma idempotente.
     *
     * @return array{binario_pdf: string, ruta_privada: string, correlativo: string, hash: string, version: int, estado_contrato: string}
     */
    public function generarPdf(
        int $organizacionId,
        int $ventaId,
        ?int $usuarioEmisorId = null,
        bool $forzarReemision = false,
        ?string $motivoReemision = null
    ): array {
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

        // 2. Validación de Estado Comercial: Solo ventas CONFIRMADAS pueden emitir contrato
        if ($venta['estado'] !== 'CONFIRMADA') {
            throw new InvalidArgumentException("Solo se pueden generar contratos para ventas en estado CONFIRMADA (estado actual: {$venta['estado']}).");
        }

        $clienteId = !empty($venta['cliente_id']) ? (int) $venta['cliente_id'] : 0;
        if ($clienteId <= 0) {
            throw new InvalidArgumentException("La venta #{$ventaId} no cuenta con un cliente titular formalizado.");
        }

        // 3. Correlativo del contrato (CON-YYYY-NNNNNN basado en correlativo de venta)
        $correlativoContrato = str_replace('VTA-', 'CON-', (string) $venta['correlativo']);
        if ($correlativoContrato === $venta['correlativo']) {
            $correlativoContrato = 'CON-' . $venta['correlativo'];
        }

        // 4. Ciclo de vida contractual: sincronizar o recuperar registro en `contratos_venta`
        $stmtCto = $this->pdo->prepare("
            SELECT * FROM `contratos_venta`
            WHERE `organizacion_id` = :org_id AND `venta_id` = :venta_id
            LIMIT 1
        ");
        $stmtCto->execute(['org_id' => $organizacionId, 'venta_id' => $ventaId]);
        $contratoVenta = $stmtCto->fetch(PDO::FETCH_ASSOC);

        $clausulasTexto = $this->obtenerTextoClausulas();
        $clausulasHash = hash('sha256', $clausulasTexto);

        if (!$contratoVenta) {
            $usuarioEmisorValido = null;
            if ($usuarioEmisorId !== null && $usuarioEmisorId > 0) {
                $stmtUsr = $this->pdo->prepare("SELECT id FROM `usuarios` WHERE id = :id");
                $stmtUsr->execute(['id' => $usuarioEmisorId]);
                if ($stmtUsr->fetchColumn()) {
                    $usuarioEmisorValido = $usuarioEmisorId;
                }
            }

            $stmtInsCto = $this->pdo->prepare("
                INSERT INTO `contratos_venta` (
                    `organizacion_id`, `venta_id`, `cliente_id`, `codigo_contrato`,
                    `version_clausulas`, `clausulas_hash`, `estado_contrato`,
                    `canal_emision`, `emitido_en`, `emitido_por_usuario_id`
                ) VALUES (
                    :org_id, :venta_id, :cliente_id, :codigo,
                    :version_clausulas, :clausulas_hash, 'EMITIDO',
                    :canal, NOW(), :usuario_id
                )
            ");
            $stmtInsCto->execute([
                'org_id'            => $organizacionId,
                'venta_id'          => $ventaId,
                'cliente_id'        => $clienteId,
                'codigo'            => $correlativoContrato,
                'version_clausulas' => self::VERSION_CLAUSULAS_CANONICA,
                'clausulas_hash'    => $clausulasHash,
                'canal'             => (string) ($venta['origen_tipo'] ?? 'BACKOFFICE'),
                'usuario_id'        => $usuarioEmisorValido,
            ]);

            $contratoVenta = [
                'id'                => (int) $this->pdo->lastInsertId(),
                'codigo_contrato'   => $correlativoContrato,
                'version_clausulas' => self::VERSION_CLAUSULAS_CANONICA,
                'clausulas_hash'    => $clausulasHash,
                'estado_contrato'   => 'EMITIDO',
                'emitido_en'        => date('Y-m-d H:i:s'),
                'aceptado_en'       => null,
                'aceptado_ip'       => null,
                'aceptado_user_agent' => null,
            ];
        } elseif ($forzarReemision && $contratoVenta['estado_contrato'] === 'ANULADO') {
            $stmtReset = $this->pdo->prepare("
                UPDATE `contratos_venta`
                SET `estado_contrato` = 'EMITIDO',
                    `anulado_en` = NULL,
                    `anulado_motivo` = NULL,
                    `aceptado_en` = NULL
                WHERE `id` = :id
            ");
            $stmtReset->execute(['id' => $contratoVenta['id']]);
            $contratoVenta['estado_contrato'] = 'EMITIDO';
        }

        // 5. Verificar idempotencia documental e histórico en `documentos_emitidos`
        $stmtDocExistente = $this->pdo->prepare("
            SELECT * FROM `documentos_emitidos`
            WHERE `organizacion_id` = :org_id
              AND `tipo_documento` = 'CONTRATO'
              AND `entidad_tipo` = 'VENTA'
              AND `entidad_id` = :venta_id
              AND `es_vigente` = 1
            ORDER BY `version` DESC
            LIMIT 1
        ");
        $stmtDocExistente->execute(['org_id' => $organizacionId, 'venta_id' => $ventaId]);
        $docVigente = $stmtDocExistente->fetch(PDO::FETCH_ASSOC);

        // Si ya existe versión vigente en disco y no se fuerza reemisión, retornar archivo histórico
        if ($docVigente && !$forzarReemision) {
            $rutaFisica = dirname(__DIR__, 2) . '/' . $docVigente['ruta_archivo_privado'];
            if (file_exists($rutaFisica)) {
                $contenido = (string) file_get_contents($rutaFisica);
                return [
                    'binario_pdf'     => $contenido,
                    'ruta_privada'    => $rutaFisica,
                    'correlativo'     => $correlativoContrato,
                    'hash'            => $docVigente['hash_sha256'],
                    'version'         => (int) $docVigente['version'],
                    'estado_contrato' => (string) $contratoVenta['estado_contrato'],
                ];
            }
        }

        // 6. Cargar líneas de la venta
        $stmtLineas = $this->pdo->prepare("
            SELECT vl.*
            FROM `venta_lineas` vl
            WHERE vl.venta_id = :venta_id
            ORDER BY vl.orden ASC, vl.id ASC
        ");
        $stmtLineas->execute(['venta_id' => $ventaId]);
        $lineas = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);

        // 7. Calcular nueva versión inmutable
        $nuevaVersion = 1;
        if ($docVigente) {
            $nuevaVersion = (int) $docVigente['version'] + 1;
        }

        // 8. Renderizar vista HTML formal (incluye estado contractual y evidencia de aceptación si existe)
        $html = $this->construirHtmlContrato($venta, $lineas, $contratoVenta, $nuevaVersion);

        // 9. Renderizar a binario PDF A4
        $binarioPdf = $this->motorPdf->renderizar($html, MotorPdfServicio::FORMATO_A4, MotorPdfServicio::ORIENTACION_VERTICAL);

        // 10. Guardar en almacenamiento privado con nombre versionado (nunca sobrescribe documentos históricos)
        $nombreArchivo = sprintf('%s_v%d_%s.pdf', $correlativoContrato, $nuevaVersion, date('Ymd_His'));
        $guardado = $this->motorPdf->guardarEnAlmacenamientoPrivado(
            $binarioPdf,
            $organizacionId,
            'CONTRATOS',
            $nombreArchivo
        );

        // 11. Inmutabilidad: Si existía una versión anterior, marcarla como SUPERSEDIDA
        if ($docVigente) {
            $stmtSupersedido = $this->pdo->prepare("
                UPDATE `documentos_emitidos`
                SET `es_vigente` = 0, `estado_documento` = 'SUPERSEDIDO'
                WHERE `id` = :id
            ");
            $stmtSupersedido->execute(['id' => $docVigente['id']]);
        }

        // 12. Registrar en bitácora documentos_emitidos con versión explícita
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
                `motivo_reemision`, `ruta_archivo_privado`, `peso_bytes`,
                `hash_sha256`, `generado_por_usuario_id`, `cliente_titular_id`, `generado_en`
            ) VALUES (
                :org_id, 'CONTRATO', 'VENTA', :ent_id,
                :correlativo, 'A4', :version, 1, 'VIGENTE',
                :motivo, :ruta, :peso,
                :hash, :usuario_id, :cliente_id, NOW()
            )
        ");
        $stmtReg->execute([
            'org_id'       => $organizacionId,
            'ent_id'       => $ventaId,
            'correlativo'  => $correlativoContrato,
            'version'      => $nuevaVersion,
            'motivo'       => $motivoReemision,
            'ruta'         => $guardado['ruta_relativa'],
            'peso'         => $guardado['peso_bytes'],
            'hash'         => $guardado['hash_sha256'],
            'usuario_id'   => $usuarioEmisorValido,
            'cliente_id'   => $clienteId,
        ]);

        return [
            'binario_pdf'     => $binarioPdf,
            'ruta_privada'    => $guardado['ruta_absoluta'],
            'correlativo'     => $correlativoContrato,
            'hash'            => $guardado['hash_sha256'],
            'version'         => $nuevaVersion,
            'estado_contrato' => (string) $contratoVenta['estado_contrato'],
        ];
    }

    /**
     * Registra la aceptación contractual y consentimiento explícito del cliente titular.
     * Perfecciona el contrato y genera una nueva versión formal del PDF que certifica la aceptación.
     */
    public function registrarAceptacionCliente(
        int $organizacionId,
        int $ventaId,
        int $clienteId,
        string $ip,
        string $userAgent
    ): array {
        $stmtCto = $this->pdo->prepare("
            SELECT * FROM `contratos_venta`
            WHERE `organizacion_id` = :org_id AND `venta_id` = :venta_id
            LIMIT 1
        ");
        $stmtCto->execute(['org_id' => $organizacionId, 'venta_id' => $ventaId]);
        $contrato = $stmtCto->fetch(PDO::FETCH_ASSOC);

        if (!$contrato) {
            // Si aún no se había emitido la entidad, generar primero la emisión inicial
            $this->generarPdf($organizacionId, $ventaId);
            $stmtCto->execute(['org_id' => $organizacionId, 'venta_id' => $ventaId]);
            $contrato = $stmtCto->fetch(PDO::FETCH_ASSOC);
        }

        if ((int) $contrato['cliente_id'] !== $clienteId) {
            throw new InvalidArgumentException("Acceso denegado: Solo el cliente titular de la venta puede prestar consentimiento contractual.");
        }

        if ($contrato['estado_contrato'] === 'ANULADO') {
            throw new InvalidArgumentException("No se puede aceptar un contrato que ha sido formalmente anulado.");
        }

        // Actualizar contrato a estado ACEPTADO con evidencia verificable
        $textoConsentimiento = "El cliente manifiesta conformidad y acepta íntegramente las cláusulas generales y específicas de prestación de servicios turísticos según Ley 29571.";
        $stmtUpd = $this->pdo->prepare("
            UPDATE `contratos_venta`
            SET `estado_contrato` = 'ACEPTADO',
                `aceptado_en` = NOW(),
                `aceptado_ip` = :ip,
                `aceptado_user_agent` = :ua,
                `aceptado_por_cliente_id` = :cliente_id,
                `consentimiento_texto` = :consentimiento
            WHERE `id` = :id
        ");
        $stmtUpd->execute([
            'id'             => $contrato['id'],
            'ip'             => $ip,
            'ua'             => substr($userAgent, 0, 255),
            'cliente_id'     => $clienteId,
            'consentimiento' => $textoConsentimiento,
        ]);

        // Forzar reemisión del PDF para registrar la versión perfeccionada con sello de aceptación
        return $this->generarPdf(
            $organizacionId,
            $ventaId,
            null,
            true,
            'Aceptación y perfeccionamiento electrónico del contrato por el cliente titular'
        );
    }

    /**
     * Anula un contrato de servicios turísticos registrando el motivo y revocando la vigencia del documento.
     */
    public function anularContrato(
        int $organizacionId,
        int $ventaId,
        string $motivo,
        ?int $usuarioId = null
    ): void {
        $stmtUpd = $this->pdo->prepare("
            UPDATE `contratos_venta`
            SET `estado_contrato` = 'ANULADO',
                `anulado_en` = NOW(),
                `anulado_motivo` = :motivo,
                `anulado_por_usuario_id` = :usr_id
            WHERE `organizacion_id` = :org_id AND `venta_id` = :venta_id
        ");
        $stmtUpd->execute([
            'org_id'   => $organizacionId,
            'venta_id' => $ventaId,
            'motivo'   => $motivo,
            'usr_id'   => $usuarioId,
        ]);

        // Marcar documento emitido como ANULADO
        $stmtDoc = $this->pdo->prepare("
            UPDATE `documentos_emitidos`
            SET `es_vigente` = 0, `estado_documento` = 'ANULADO', `motivo_reemision` = :motivo
            WHERE `organizacion_id` = :org_id
              AND `tipo_documento` = 'CONTRATO'
              AND `entidad_tipo` = 'VENTA'
              AND `entidad_id` = :venta_id
              AND `es_vigente` = 1
        ");
        $stmtDoc->execute([
            'org_id'   => $organizacionId,
            'venta_id' => $ventaId,
            'motivo'   => $motivo,
        ]);
    }

    private function construirHtmlContrato(array $v, array $lineas, array $contratoVenta, int $version): string
    {
        $estilos = PlantillaDocumentoServicio::obtenerEstilosCssA4();
        $correlativoContrato = htmlspecialchars((string) $contratoVenta['codigo_contrato'], ENT_QUOTES, 'UTF-8');
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
        $ipFormalizacion = htmlspecialchars((string) ($v['ip_origen'] ?? '127.0.0.1'), ENT_QUOTES, 'UTF-8');

        $estadoContrato = (string) ($contratoVenta['estado_contrato'] ?? 'EMITIDO');
        $esAceptado = ($estadoContrato === 'ACEPTADO');

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
        $estadoFinanciero = htmlspecialchars((string) ($v['estado_financiero'] ?? 'PENDIENTE'), ENT_QUOTES, 'UTF-8');

        // Distinción gráfica entre Contrato Emitido y Contrato Aceptado
        if ($esAceptado) {
            $badgeEstado = "<span style='background-color: #276749; color: #ffffff; padding: 4px 8px; border-radius: 4px; font-weight: bold;'>ACEPTADO Y PERFECCIONADO</span>";
            $selloAceptacion = "
                <div style='margin-top: 15px; padding: 10px 14px; background-color: #f0fff4; border: 1px solid #9ae6b4; border-left: 4px solid #38a169; font-size: 8pt; color: #22543d;'>
                    <strong>EVIDENCIA DE ACEPTACIÓN ELECTRÓNICA Y CONSENTIMIENTO INFORMADO (LEY 29571)</strong><br>
                    Aceptado formalmente por: <strong>{$clienteNombre}</strong> ({$clienteDoc})<br>
                    Fecha y Hora de Aceptación: <strong>{$contratoVenta['aceptado_en']}</strong> &bull; IP: <strong>{$contratoVenta['aceptado_ip']}</strong><br>
                    Huella de Términos (SHA-256): <span style='font-family: monospace; font-size: 7.5pt;'>{$contratoVenta['clausulas_hash']}</span>
                </div>
            ";
        } else {
            $badgeEstado = "<span style='background-color: #d69e2e; color: #ffffff; padding: 4px 8px; border-radius: 4px; font-weight: bold;'>EMITIDO (PENDIENTE DE CONFORMIDAD/FIRMA)</span>";
            $selloAceptacion = "
                <div style='margin-top: 15px; padding: 8px 12px; background-color: #fffaf0; border: 1px solid #feebc8; border-left: 4px solid #dd6b20; font-size: 8pt; color: #7b341e;'>
                    <strong>ESTADO DEL DOCUMENTO:</strong> Propuesta contractual formal emitida. Requiere suscripción física o confirmación electrónica del cliente titular para su perfeccionamiento definitivo.
                </div>
            ";
        }

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
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 65%;">
                            <div class="doc-titulo">CONTRATO DE PRESTACIÓN DE SERVICIOS TURÍSTICOS Y EXPERIENCIAS</div>
                            <div class="doc-correlativo">N° {$correlativoContrato} &bull; Versión: v{$version} (Ref. Venta: {$v['correlativo']})</div>
                        </td>
                        <td style="width: 35%; text-align: right; vertical-align: middle;">
                            {$badgeEstado}
                        </td>
                    </tr>
                </table>
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
                    <td class="info-label">Condición Financiera Venta:</td>
                    <td class="info-value"><strong>{$estadoFinanciero}</strong> (No confunde contrato emitido con pago acreditado)</td>
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
                <h4>Cláusulas Contractuales Generales (Versión: {$contratoVenta['version_clausulas']}):</h4>
                <ol style="margin: 0; padding-left: 14px; font-size: 7.8pt;">
                    <li><strong>PRIMERA (Objeto):</strong> EL PRESTADOR se compromete a brindar las prestaciones, accesos, traslados o paquetes detallados en la presente orden contractual durante la Festividad de la Virgen de la Candelaria.</li>
                    <li><strong>SEGUNDA (Cronograma y Aforo):</strong> La prestación efectiva se regirá estrictamente por el cronograma oficial de la Federación Regional de Folklore y Cultura de Puno (FRFCP) y las resoluciones de aforo vigentes de las autoridades de seguridad y defensa civil.</li>
                    <li><strong>TERCERA (Check-in e Identidad):</strong> Para acceder a los servicios, el titular y los beneficiarios deberán portar su Documento Nacional de Identidad original y presentar el Boleto Oficial provisto de código QR unívoco intransferible. La emisión, descarga o reimpresión del boleto no consuma el acceso.</li>
                    <li><strong>CUARTA (Reprogramación y Fuerza Mayor):</strong> Las eventuales postergaciones derivadas de conmoción civil, fuerza mayor o disposiciones gubernamentales imprevistas darán lugar a la reprogramación de prestaciones de mutuo acuerdo, con apego irrestricto al Código de Protección y Defensa del Consumidor (Ley N° 29571).</li>
                    <li><strong>QUINTA (Comportamiento y Seguridad):</strong> EL CLIENTE se obliga a respetar las instrucciones de los coordinadores de campo y los límites de aforo, quedando prohibido el ingreso con elementos inflamables o sustancias ilícitas.</li>
                    <li><strong>SEXTA (Comprobación Financiera):</strong> La emisión del presente contrato no constituye acreditación de pago cancelado. La validación definitiva de los servicios y entrega de boletos queda sujeta a la cancelación total y emisión de Recibo Interno de Caja.</li>
                </ol>
            </div>

            {$selloAceptacion}

            <table class="firmas-tabla">
                <tr>
                    <td>
                        <div class="linea-firma">
                            <strong>{$orgNombre}</strong><br>
                            Conformidad del Prestador de Servicios<br>
                            <small style="color: #718096;">Representante / Asesor Comercial</small>
                        </div>
                    </td>
                    <td>
                        <div class="linea-firma">
                            <strong>{$clienteNombre}</strong><br>
                            Conformidad del Cliente Titular<br>
                            <small style="color: #718096;">{$clienteDoc} &bull; "Firma / Aceptación de Términos"</small>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="footer-pagina">
                Documento Inmutable registrado en CandelariaAPP V1 &bull; Huella Digital: {$contratoVenta['clausulas_hash']} &bull; Versión Documental v{$version}
            </div>
        </body>
        </html>
        HTML;
    }

    private function obtenerTextoClausulas(): string
    {
        return "PRIMERA: Objeto de prestación de servicios turísticos. "
            . "SEGUNDA: Cronograma oficial FRFCP y aforo. "
            . "TERCERA: Check-in, identidad y control QR no consumado en descarga. "
            . "CUARTA: Reprogramaciones y fuerza mayor Ley 29571. "
            . "QUINTA: Deberes del usuario y seguridad. "
            . "SEXTA: Distinción entre contrato emitido, venta confirmada y pago cancelado.";
    }
}
