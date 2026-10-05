<?php

declare(strict_types=1);

namespace Aplicacion\Configuracion;

use InvalidArgumentException;

/**
 * Servicio de Gobernanza y Políticas de Branding de CandelariaAPP.
 * Define la arquitectura para la gestión segura de logotipos e isotipos
 * de la organización sin almacenar binarios en base de datos.
 */
class BrandingServicio
{
    public const TAMANO_MAXIMO_BYTES = 2097152; // 2 MB

    public const MIMES_PERMITIDOS = [
        'image/webp'    => 'webp',
        'image/png'     => 'png',
        'image/jpeg'    => 'jpg',
        'image/svg+xml' => 'svg',
    ];

    public const TIPOS_VALIDOS = [
        'logo',
        'isotipo',
    ];

    /**
     * Valida que un archivo cumpla con las políticas de formato, peso y seguridad.
     *
     * @throws InvalidArgumentException
     */
    public static function validarArchivo(string $mimeType, int $tamanoBytes, string $extension): void
    {
        $extMin = strtolower(ltrim($extension, '.'));
        $mimeMin = strtolower(trim($mimeType));

        if (!array_key_exists($mimeMin, self::MIMES_PERMITIDOS)) {
            $admitidos = implode(', ', array_keys(self::MIMES_PERMITIDOS));
            throw new InvalidArgumentException("Formato no permitido: '{$mimeType}'. Formatos admitidos: [{$admitidos}].");
        }

        if ($tamanoBytes <= 0) {
            throw new InvalidArgumentException('El archivo de imagen se encuentra vacío.');
        }

        if ($tamanoBytes > self::TAMANO_MAXIMO_BYTES) {
            $maxMb = self::TAMANO_MAXIMO_BYTES / (1024 * 1024);
            throw new InvalidArgumentException("El archivo supera el tamaño máximo permitido de {$maxMb} MB.");
        }

        $extensionEsperada = self::MIMES_PERMITIDOS[$mimeMin];
        if ($extMin !== $extensionEsperada && !($extMin === 'jpeg' && $extensionEsperada === 'jpg')) {
            throw new InvalidArgumentException("Inconsistencia entre extensión '.{$extMin}' y tipo MIME '{$mimeType}'.");
        }
    }

    /**
     * Genera un nombre de archivo seguro, estandarizado e impredecible.
     * Nomenclatura: {tipo}_{YYYYMMDD_His}_{randomHex8}.{ext}
     */
    public static function generarNombreArchivo(string $tipo, string $extension): string
    {
        $tipoMin = strtolower(trim($tipo));
        if (!in_array($tipoMin, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException("Tipo de elemento de branding inválido: '{$tipo}'.");
        }

        $extMin = strtolower(ltrim($extension, '.'));
        $timestamp = date('Ymd_His');
        $random = bin2hex(random_bytes(4));

        return "{$tipoMin}_{$timestamp}_{$random}.{$extMin}";
    }

    /**
     * Retorna la ruta relativa estándar accesible desde el frontend.
     */
    public static function generarRutaRelativa(int $organizacionId, string $nombreArchivo): string
    {
        return "/recursos/subidas/organizaciones/{$organizacionId}/branding/{$nombreArchivo}";
    }

    /**
     * Retorna la ruta absoluta del sistema de archivos local para almacenamiento.
     */
    public static function obtenerRutaFisicaDirectorio(string $raizProyecto, int $organizacionId): string
    {
        return rtrim($raizProyecto, '/\\') . "/publico/recursos/subidas/organizaciones/{$organizacionId}/branding";
    }
}
