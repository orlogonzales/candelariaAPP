<?php

declare(strict_types=1);

namespace Aplicacion\Documentos;

/**
 * Servicio encargado del renderizado y estilizado de plantillas HTML para documentos PDF.
 * Aplica diseño sobrio, formal, optimizado para impresión A4 y tickets térmicos de 80mm.
 */
class PlantillaDocumentoServicio
{
    /**
     * Retorna los estilos CSS base para documentos en tamaño A4.
     */
    public static function obtenerEstilosCssA4(): string
    {
        return <<<CSS
            @page {
                margin: 20mm 15mm 20mm 15mm;
                size: a4 portrait;
            }
            body {
                font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
                font-size: 10pt;
                line-height: 1.4;
                color: #1a202c;
                margin: 0;
                padding: 0;
            }
            .header-tabla {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                border-bottom: 2px solid #0c192c;
                padding-bottom: 10px;
            }
            .header-logo {
                width: 50%;
                vertical-align: top;
            }
            .header-info-empresa {
                width: 50%;
                text-align: right;
                vertical-align: top;
                font-size: 8.5pt;
                color: #4a5568;
            }
            .empresa-titulo {
                font-size: 14pt;
                font-weight: bold;
                color: #0c192c;
                margin-bottom: 3px;
            }
            .doc-titulo-box {
                background-color: #f7fafc;
                border: 1px solid #e2e8f0;
                border-left: 4px solid #0c192c;
                padding: 10px 14px;
                margin-bottom: 20px;
            }
            .doc-titulo {
                font-size: 14pt;
                font-weight: bold;
                color: #0c192c;
                margin: 0;
            }
            .doc-correlativo {
                font-size: 11pt;
                color: #b7791f;
                font-weight: bold;
                margin-top: 3px;
            }
            .info-grid {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .info-grid td {
                padding: 4px 6px;
                vertical-align: top;
                font-size: 9pt;
            }
            .info-label {
                font-weight: bold;
                color: #4a5568;
                width: 25%;
            }
            .info-value {
                color: #1a202c;
                width: 75%;
            }
            .tabla-items {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .tabla-items th {
                background-color: #0c192c;
                color: #ffffff;
                font-size: 9pt;
                font-weight: bold;
                padding: 7px 8px;
                text-align: left;
                border: 1px solid #0c192c;
            }
            .tabla-items td {
                padding: 7px 8px;
                border: 1px solid #e2e8f0;
                font-size: 8.5pt;
            }
            .tabla-items tr:nth-child(even) {
                background-color: #f8fafc;
            }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .totales-tabla {
                width: 45%;
                margin-left: auto;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .totales-tabla td {
                padding: 5px 8px;
                font-size: 9pt;
            }
            .totales-tabla .total-final {
                font-size: 11pt;
                font-weight: bold;
                border-top: 2px solid #0c192c;
                border-bottom: 2px solid #0c192c;
                color: #0c192c;
            }
            .clausulas-box {
                background-color: #ffffff;
                border: 1px solid #e2e8f0;
                padding: 12px;
                font-size: 8pt;
                color: #4a5568;
                line-height: 1.4;
                margin-bottom: 25px;
            }
            .clausulas-box h4 {
                font-size: 9pt;
                color: #0c192c;
                margin-top: 0;
                margin-bottom: 6px;
            }
            .firmas-tabla {
                width: 100%;
                border-collapse: collapse;
                margin-top: 30px;
            }
            .firmas-tabla td {
                width: 50%;
                text-align: center;
                vertical-align: bottom;
                padding: 0 20px;
            }
            .linea-firma {
                border-top: 1px solid #718096;
                padding-top: 5px;
                font-size: 8.5pt;
                color: #2d3748;
            }
            .leyenda-legal {
                margin-top: 25px;
                padding: 8px 12px;
                background-color: #fffaf0;
                border: 1px dashed #dd6b20;
                color: #7b341e;
                font-size: 8pt;
                text-align: center;
                font-weight: bold;
            }
            .footer-pagina {
                position: fixed;
                bottom: -10mm;
                left: 0;
                right: 0;
                font-size: 7.5pt;
                color: #a0aec0;
                text-align: center;
                border-top: 1px solid #e2e8f0;
                padding-top: 4px;
            }
        CSS;
    }

    /**
     * Retorna los estilos CSS base para tickets térmicos de 80 mm.
     */
    public static function obtenerEstilosCssTicket80(): string
    {
        return <<<CSS
            @page {
                margin: 5mm 5mm 5mm 5mm;
                size: 80mm 250mm;
            }
            body {
                font-family: 'DejaVu Sans', 'Helvetica', monospace, sans-serif;
                font-size: 8pt;
                line-height: 1.3;
                color: #000000;
                margin: 0;
                padding: 0;
            }
            .ticket-header {
                text-align: center;
                border-bottom: 1px dashed #000000;
                padding-bottom: 6px;
                margin-bottom: 8px;
            }
            .ticket-empresa {
                font-size: 10pt;
                font-weight: bold;
                text-transform: uppercase;
            }
            .ticket-edicion {
                font-size: 8pt;
                font-weight: bold;
            }
            .ticket-titulo {
                font-size: 9pt;
                font-weight: bold;
                margin-top: 4px;
                text-transform: uppercase;
            }
            .ticket-correlativo {
                font-size: 8.5pt;
                font-weight: bold;
                margin-top: 2px;
            }
            .ticket-info {
                margin-bottom: 8px;
                border-bottom: 1px dashed #000000;
                padding-bottom: 6px;
            }
            .ticket-fila {
                display: block;
                margin-bottom: 2px;
            }
            .ticket-qr-container {
                text-align: center;
                margin: 10px 0;
                padding: 6px;
            }
            .ticket-qr-container img {
                width: 140px;
                height: 140px;
            }
            .ticket-footer {
                text-align: center;
                font-size: 7pt;
                border-top: 1px dashed #000000;
                padding-top: 6px;
                margin-top: 8px;
            }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .bold { font-weight: bold; }
        CSS;
    }
}
