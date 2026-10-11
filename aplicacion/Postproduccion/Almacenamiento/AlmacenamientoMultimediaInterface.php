<?php

declare(strict_types=1);

namespace Aplicacion\Postproduccion\Almacenamiento;

/**
 * Contrato de abstracción desacoplada para almacenamiento multimedia (Local o S3/Cloud).
 * Garantiza manipulación por flujos (streams) sin cargar gigabytes en la memoria RAM del servidor.
 */
interface AlmacenamientoMultimediaInterface
{
    /**
     * Guarda un flujo de datos (resource stream) en el almacenamiento privado.
     *
     * @param resource $stream Flujo abierto en modo lectura.
     * @param string $rutaRelativa Ruta dentro del almacenamiento del tenant.
     * @return array{ruta_relativa: string, ruta_absoluta: string, peso_bytes: int, checksum_sha256: string}
     */
    public function guardarStream($stream, string $rutaRelativa): array;

    /**
     * Retorna un flujo de lectura (resource stream) para el archivo solicitado.
     *
     * @return resource
     */
    public function obtenerStream(string $rutaRelativa);

    /**
     * Verifica la existencia física del archivo.
     */
    public function existe(string $rutaRelativa): bool;

    /**
     * Elimina el archivo del almacenamiento.
     */
    public function eliminar(string $rutaRelativa): bool;

    /**
     * Obtiene el tamaño en bytes del archivo.
     */
    public function obtenerTamanoBytes(string $rutaRelativa): int;

    /**
     * Genera un token firmado temporal con tiempo de caducidad para descargas seguras.
     */
    public function generarTokenDescargaTemporal(int $archivoId, int $clienteId, int $expiracionSegundos = 3600): string;

    /**
     * Valida un token temporal de descarga firmado criptográficamente.
     */
    public function validarTokenDescargaTemporal(string $token): ?array;
}
