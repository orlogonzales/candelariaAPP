<?php

declare(strict_types=1);

namespace Aplicacion\Documentos;

use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;
use RuntimeException;

/**
 * Servicio centralizado de renderizado y almacenamiento privado de documentos PDF.
 * Envoltura estricta y segura de Dompdf con soporte para A4 y tickets térmicos de 80mm.
 */
class MotorPdfServicio
{
    public const FORMATO_A4 = 'A4';
    public const FORMATO_TICKET_80MM = 'TICKET_80MM';

    public const ORIENTACION_VERTICAL = 'portrait';
    public const ORIENTACION_HORIZONTAL = 'landscape';

    private string $rutaAlmacenamientoBase;

    public function __construct(?string $rutaAlmacenamientoBase = null)
    {
        $this->rutaAlmacenamientoBase = $rutaAlmacenamientoBase ?? dirname(__DIR__, 2) . '/almacenamiento/privado/documentos';
    }

    /**
     * Renderiza un contenido HTML a binario PDF puro en memoria.
     *
     * @param string $html Contenido HTML completo con estilos CSS inline o incrustados.
     * @param string $formato FORMATO_A4 o FORMATO_TICKET_80MM.
     * @param string $orientacion 'portrait' o 'landscape'.
     * @return string Contenido binario del archivo PDF generado.
     */
    public function renderizar(
        string $html,
        string $formato = self::FORMATO_A4,
        string $orientacion = self::ORIENTACION_VERTICAL
    ): string {
        $opciones = new Options();
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isHtml5ParserEnabled', true);
        $opciones->set('isRemoteEnabled', true); // Permitir Data-URIs base64 para logos y códigos QR
        $opciones->set('chroot', dirname(__DIR__, 2));

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml($html, 'UTF-8');

        if ($formato === self::FORMATO_TICKET_80MM) {
            // Ancho estándar de ticket térmico: 80mm = 226.77 pt (~227 pt)
            // Altura generosa para flujo continuo térmico: 700 pt
            $dompdf->setPaper([0, 0, 226.77, 708.66], self::ORIENTACION_VERTICAL);
        } else {
            $dompdf->setPaper('a4', $orientacion);
        }

        try {
            $dompdf->render();
            $salida = $dompdf->output();
            if ($salida === null || $salida === '') {
                throw new RuntimeException("El motor PDF generó una salida vacía.");
            }
            return $salida;
        } catch (\Throwable $e) {
            throw new RuntimeException("Error durante la generación del documento PDF: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Guarda de forma segura el PDF generado en el almacenamiento privado del tenant.
     *
     * @return array{ruta_relativa: string, ruta_absoluta: string, peso_bytes: int, hash_sha256: string}
     */
    public function guardarEnAlmacenamientoPrivado(
        string $contenidoPdf,
        int $organizacionId,
        string $tipoDocumento,
        string $nombreArchivo
    ): array {
        $anio = date('Y');
        $tipoNormalizado = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($tipoDocumento));
        $nombreLimpio = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $nombreArchivo);

        $directorioDestino = "{$this->rutaAlmacenamientoBase}/{$organizacionId}/{$tipoNormalizado}/{$anio}";
        if (!is_dir($directorioDestino)) {
            if (!mkdir($directorioDestino, 0755, true) && !is_dir($directorioDestino)) {
                throw new RuntimeException("No fue posible crear el directorio privado de almacenamiento: {$directorioDestino}");
            }
        }

        $rutaAbsoluta = "{$directorioDestino}/{$nombreLimpio}";
        $bytesEscritos = file_put_contents($rutaAbsoluta, $contenidoPdf);
        if ($bytesEscritos === false) {
            throw new RuntimeException("Fallo al escribir el archivo PDF en el almacenamiento privado: {$rutaAbsoluta}");
        }

        $hashSha256 = hash('sha256', $contenidoPdf);
        $rutaRelativa = "privado/documentos/{$organizacionId}/{$tipoNormalizado}/{$anio}/{$nombreLimpio}";

        return [
            'ruta_relativa' => $rutaRelativa,
            'ruta_absoluta' => $rutaAbsoluta,
            'peso_bytes'    => (int) $bytesEscritos,
            'hash_sha256'   => $hashSha256,
        ];
    }
}
