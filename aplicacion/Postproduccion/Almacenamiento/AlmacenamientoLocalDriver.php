<?php

declare(strict_types=1);

namespace Aplicacion\Postproduccion\Almacenamiento;

use InvalidArgumentException;
use RuntimeException;

/**
 * Driver de Almacenamiento Local Privado para Archivos Multimedia Pesados.
 * Procesa transferencias mediante chunks de 64 KB, calculando el checksum SHA-256 al vuelo
 * con un consumo de memoria constante O(1) independientemente del tamaño del archivo.
 */
class AlmacenamientoLocalDriver implements AlmacenamientoMultimediaInterface
{
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

        while (!feof($stream)) {
            $buffer = fread($stream, $chunkSize);
            if ($buffer === false) {
                fclose($destino);
                throw new RuntimeException("Error durante la lectura del stream de entrada.");
            }
            if ($buffer !== '') {
                $escritos = fwrite($destino, $buffer);
                if ($escritos === false) {
                    fclose($destino);
                    throw new RuntimeException("Error durante la escritura del chunk en disco.");
                }
                hash_update($ctxHash, $buffer);
                $totalBytes += $escritos;
            }
        }

        fclose($destino);
        $checksumFinal = hash_final($ctxHash);

        return [
            'ruta_relativa'   => 'almacenamiento/privado/multimedia/' . $rutaLimpia,
            'ruta_absoluta'   => $rutaAbsoluta,
            'peso_bytes'      => $totalBytes,
            'checksum_sha256' => $checksumFinal,
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
            return unlink($rutaAbsoluta);
        }
        return false;
    }

    public function obtenerTamanoBytes(string $rutaRelativa): int
    {
        $rutaAbsoluta = $this->resolverRutaAbsoluta($rutaRelativa);
        if (!file_exists($rutaAbsoluta)) {
            return 0;
        }
        return (int) filesize($rutaAbsoluta);
    }

    public function generarTokenDescargaTemporal(int $archivoId, int $clienteId, int $expiracionSegundos = 3600): string
    {
        $expiraEn = time() + $expiracionSegundos;
        $payload = [
            'aid'   => $archivoId,
            'cid'   => $clienteId,
            'exp'   => $expiraEn,
            'nonce' => bin2hex(random_bytes(8)),
        ];

        $payloadJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payloadB64 = strtr(base64_encode($payloadJson), '+/', '-_');
        $firma = hash_hmac('sha256', $payloadB64, $this->claveSecreta);

        return $payloadB64 . '.' . $firma;
    }

    public function validarTokenDescargaTemporal(string $token): ?array
    {
        $partes = explode('.', $token);
        if (count($partes) !== 2) {
            return null;
        }

        [$payloadB64, $firmaRecibida] = $partes;
        $firmaEsperada = hash_hmac('sha256', $payloadB64, $this->claveSecreta);
        if (!hash_equals($firmaEsperada, $firmaRecibida)) {
            return null; // Firma alterada
        }

        $json = base64_decode(strtr($payloadB64, '-_', '+/'));
        if ($json === false) {
            return null;
        }

        $datos = json_decode($json, true);
        if (!is_array($datos) || empty($datos['aid']) || empty($datos['exp'])) {
            return null;
        }

        if (time() > (int) $datos['exp']) {
            return null; // Enlace expirado
        }

        return [
            'archivo_id' => (int) $datos['aid'],
            'cliente_id' => (int) ($datos['cid'] ?? 0),
            'expira_en'  => (int) $datos['exp'],
        ];
    }

    private function resolverRutaAbsoluta(string $ruta): string
    {
        if (str_starts_with($ruta, 'almacenamiento/privado/multimedia/')) {
            $ruta = substr($ruta, strlen('almacenamiento/privado/multimedia/'));
        }
        $rutaLimpia = ltrim(str_replace(['\\', '..'], ['/', ''], $ruta), '/');
        return $this->directorioBase . '/' . $rutaLimpia;
    }
}
