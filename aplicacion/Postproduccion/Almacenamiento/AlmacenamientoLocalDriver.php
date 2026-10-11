<?php

declare(strict_types=1);

namespace Aplicacion\Postproduccion\Almacenamiento;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Driver de Almacenamiento Local Privado para Archivos Multimedia Pesados.
 * Procesa transferencias mediante chunks de 64 KB, calculando el checksum SHA-256 al vuelo
 * con un consumo de memoria constante O(1) independientemente del tamaño del archivo.
 * Implementa límites de tamaño, verificación de Magic Bytes y limpieza estricta de archivos incompletos.
 */
class AlmacenamientoLocalDriver implements AlmacenamientoMultimediaInterface
{
    public const LIMITE_MAXIMO_ARCHIVO_BYTES = 262144000; // 250 MB

    private string $directorioBase;
    private string $claveSecreta;

    public function __construct(?string $directorioBase = null, ?string $claveSecreta = null)
    {
        $this->directorioBase = $directorioBase ?? dirname(__DIR__, 3) . '/almacenamiento/privado/multimedia';
        $this->claveSecreta = $claveSecreta ?? (string) ($_ENV['APP_CLAVE'] ?? 'candelaria_multimedia_secret_fallback_key_2026');

        if (!is_dir($this->directorioBase)) {
            @mkdir($this->directorioBase, 0755, true);
        }
    }

    public function guardarStream($stream, string $rutaRelativa): array
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException("El origen de datos proporcionado no es un recurso stream válido.");
        }

        $rutaLimpia = ltrim(str_replace(['\\', '..'], ['/', ''], $rutaRelativa), '/');
        $rutaAbsoluta = $this->directorioBase . '/' . $rutaLimpia;
        $directorioDestino = dirname($rutaAbsoluta);

        if (!is_dir($directorioDestino)) {
            if (!mkdir($directorioDestino, 0755, true) && !is_dir($directorioDestino)) {
                throw new RuntimeException("No fue posible crear la carpeta de almacenamiento privado: {$directorioDestino}");
            }
        }

        $destino = fopen($rutaAbsoluta, 'wb');
        if ($destino === false) {
            throw new RuntimeException("No fue posible abrir el archivo de destino para escritura: {$rutaAbsoluta}");
        }

        $ctxHash = hash_init('sha256');
        $totalBytes = 0;
        $chunkSize = 65536; // Chunks de 64 KB
        $primerChunk = true;
        $mimeDetectado = null;

        try {
            while (!feof($stream)) {
                $buffer = fread($stream, $chunkSize);
                if ($buffer === false) {
                    throw new RuntimeException("Error durante la lectura del stream de entrada.");
                }

                if ($buffer !== '') {
                    // 1. Verificación de Magic Bytes en el primer bloque recibido
                    if ($primerChunk) {
                        $primerChunk = false;
                        if (function_exists('finfo_open')) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeDetectado = finfo_buffer($finfo, substr($buffer, 0, 4096));
                            finfo_close($finfo);

                            $mimesPeligrosos = [
                                'text/x-php', 'application/x-php', 'application/x-httpd-php',
                                'text/x-shellscript', 'application/x-sh',
                                'application/x-executable', 'application/x-dosexec',
                                'text/html', 'application/javascript'
                            ];

                            if (in_array(strtolower((string) $mimeDetectado), $mimesPeligrosos, true) || str_contains(strtolower((string) $buffer), '<?php')) {
                                throw new InvalidArgumentException("Bloqueo de seguridad: El contenido real del archivo ({$mimeDetectado}) contiene código ejecutable no permitido.");
                            }
                        }
                    }

                    // 2. Control de límite máximo de archivo
                    $totalBytes += strlen($buffer);
                    if ($totalBytes > self::LIMITE_MAXIMO_ARCHIVO_BYTES) {
                        throw new InvalidArgumentException("El archivo excede el tamaño máximo permitido por archivo (250 MB).");
                    }

                    $escritos = fwrite($destino, $buffer);
                    if ($escritos === false) {
                        throw new RuntimeException("Error durante la escritura del chunk en disco.");
                    }
                    hash_update($ctxHash, $buffer);
                }
            }

            fclose($destino);
            $destino = null;
        } catch (Throwable $e) {
            // Limpieza estricta de archivos incompletos / residuales
            if ($destino !== null && is_resource($destino)) {
                fclose($destino);
            }
            if (file_exists($rutaAbsoluta)) {
                @unlink($rutaAbsoluta);
            }
            throw $e;
        }

        $checksumFinal = hash_final($ctxHash);

        return [
            'ruta_relativa'   => 'almacenamiento/privado/multimedia/' . $rutaLimpia,
            'ruta_absoluta'   => $rutaAbsoluta,
            'peso_bytes'      => $totalBytes,
            'checksum_sha256' => $checksumFinal,
            'mime_detectado'  => $mimeDetectado,
        ];
    }

    public function obtenerStream(string $rutaRelativa)
    {
        $rutaAbsoluta = $this->resolverRutaAbsoluta($rutaRelativa);
        if (!file_exists($rutaAbsoluta)) {
            throw new RuntimeException("El archivo solicitado no existe en almacenamiento privado: {$rutaRelativa}");
        }

        $stream = fopen($rutaAbsoluta, 'rb');
        if ($stream === false) {
            throw new RuntimeException("No fue posible abrir el flujo de lectura para: {$rutaAbsoluta}");
        }

        return $stream;
    }

    public function existe(string $rutaRelativa): bool
    {
        return file_exists($this->resolverRutaAbsoluta($rutaRelativa));
    }

    public function eliminar(string $rutaRelativa): bool
    {
        $rutaAbsoluta = $this->resolverRutaAbsoluta($rutaRelativa);
        if (file_exists($rutaAbsoluta)) {
            return @unlink($rutaAbsoluta);
        }
        return true;
    }

    public function obtenerTamanoBytes(string $rutaRelativa): int
    {
        $rutaAbsoluta = $this->resolverRutaAbsoluta($rutaRelativa);
        if (!file_exists($rutaAbsoluta)) {
            return 0;
        }
        return (int) (filesize($rutaAbsoluta) ?: 0);
    }

    public function obtenerMetadatos(string $rutaRelativa): array
    {
        $rutaAbsoluta = $this->resolverRutaAbsoluta($rutaRelativa);
        if (!file_exists($rutaAbsoluta)) {
            throw new RuntimeException("Archivo no encontrado: {$rutaRelativa}");
        }

        return [
            'peso_bytes'       => filesize($rutaAbsoluta) ?: 0,
            'ultima_mod'       => filemtime($rutaAbsoluta) ?: time(),
            'checksum_sha256'  => hash_file('sha256', $rutaAbsoluta) ?: '',
        ];
    }

    public function generarTokenDescargaTemporal(int $archivoId, ?int $clienteId, int $expiracionSegundos = 3600): string
    {
        $expiracion = time() + $expiracionSegundos;
        $payload = sprintf('%d:%s:%d', $archivoId, $clienteId !== null ? (string)$clienteId : '0', $expiracion);
        $payloadB64 = base64_encode($payload);
        $firma = hash_hmac('sha256', $payloadB64, $this->claveSecreta);

        return $payloadB64 . '.' . $firma;
    }

    public function validarTokenDescargaTemporal(string $token): ?array
    {
        if (!str_contains($token, '.')) {
            return null;
        }

        $partesToken = explode('.', $token, 2);
        if (count($partesToken) !== 2) {
            return null;
        }

        [$payloadB64, $firmaRecibida] = $partesToken;
        $firmaEsperada = hash_hmac('sha256', $payloadB64, $this->claveSecreta);

        if (!hash_equals($firmaEsperada, $firmaRecibida)) {
            return null;
        }

        $payload = base64_decode($payloadB64, true);
        if ($payload === false) {
            return null;
        }

        $partes = explode(':', $payload);
        if (count($partes) !== 3) {
            return null;
        }

        [$archivoIdStr, $clienteIdStr, $expiracionStr] = $partes;
        $expiracion = (int) $expiracionStr;

        if (time() > $expiracion) {
            return null; // Token expirado
        }

        return [
            'archivo_id' => (int) $archivoIdStr,
            'cliente_id' => $clienteIdStr !== '0' ? (int) $clienteIdStr : null,
            'expira_en'  => $expiracion,
        ];
    }

    private function resolverRutaAbsoluta(string $rutaRelativa): string
    {
        $limpia = ltrim(str_replace(['\\', '..'], ['/', ''], $rutaRelativa), '/');
        if (str_starts_with($limpia, 'almacenamiento/privado/multimedia/')) {
            $limpia = substr($limpia, strlen('almacenamiento/privado/multimedia/'));
        }
        return $this->directorioBase . '/' . $limpia;
    }
}
