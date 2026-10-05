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
     * Formatos expresamente permitidos para carga física de archivos.
     * SVG se encuentra restringido temporalmente por políticas de seguridad y sanitización vectorial.
     */
    public const FORMATOS_SUBIDA_PERMITIDOS = ['png', 'jpg', 'jpeg', 'webp'];

    public const MIMES_SUBIDA_PERMITIDOS = [
        'image/webp' => 'webp',
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
    ];

    /**
     * Valida que un archivo cumpla con las políticas de formato, peso y seguridad.
     * Preservado para consistencia y compatibilidad con el catálogo general F1.2A.
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
     * Valida exhaustivamente un archivo físico subido al servidor.
     * Aplica detección MIME en servidor (finfo), comprobación de integridad de imagen (getimagesize),
     * restricción explícita de SVG y neutralización de nombres peligrosos/dobles extensiones.
     *
     * @return array{extension: string, mime: string, ancho: int, alto: int}
     * @throws InvalidArgumentException
     */
    public static function validarSubidaBranding(string $rutaTemporal, string $nombreOriginal, int $tamanoBytes): array
    {
        if (!file_exists($rutaTemporal) || !is_readable($rutaTemporal)) {
            throw new InvalidArgumentException('El archivo temporal de carga no existe o no es accesible.');
        }

        if ($tamanoBytes <= 0) {
            throw new InvalidArgumentException('El archivo de imagen subido se encuentra vacío.');
        }

        if ($tamanoBytes > self::TAMANO_MAXIMO_BYTES) {
            $maxMb = self::TAMANO_MAXIMO_BYTES / (1024 * 1024);
            throw new InvalidArgumentException("El archivo supera el tamaño máximo permitido de {$maxMb} MB.");
        }

        // 1. Sanitizar y examinar nombre original y posibles dobles extensiones peligrosas
        $nombreLimpio = basename(str_replace(['\\', '/', "\0"], '', $nombreOriginal));
        if (preg_match('/\.(php|phtml|phar|exe|sh|bat|cmd|js|vbs|pl|cgi)\b/i', $nombreLimpio)) {
            throw new InvalidArgumentException('Nombre de archivo peligroso: detección de extensiones ejecutables no permitidas.');
        }

        $partes = explode('.', $nombreLimpio);
        $extOriginal = strtolower(end($partes));

        // 2. Política de SVG: rechazo controlado antes de procesamiento
        if ($extOriginal === 'svg' || str_contains(strtolower($nombreLimpio), '.svg')) {
            throw new InvalidArgumentException(
                'El formato SVG se encuentra temporalmente deshabilitado por políticas de sanitización y seguridad vectorial. Utilice PNG, JPG o WEBP.'
            );
        }

        // 3. Detección MIME real en backend con fileinfo (no confía en el header cliente)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo ? finfo_file($finfo, $rutaTemporal) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($mimeReal === false || empty($mimeReal)) {
            throw new InvalidArgumentException('No fue posible determinar el tipo de contenido real del archivo.');
        }

        $mimeRealMin = strtolower(trim($mimeReal));

        if ($mimeRealMin === 'image/svg+xml' || str_contains($mimeRealMin, 'svg')) {
            throw new InvalidArgumentException(
                'El formato SVG se encuentra temporalmente deshabilitado por políticas de sanitización y seguridad vectorial. Utilice PNG, JPG o WEBP.'
            );
        }

        if (!array_key_exists($mimeRealMin, self::MIMES_SUBIDA_PERMITIDOS)) {
            throw new InvalidArgumentException(
                "Tipo de contenido no permitido o peligroso: '{$mimeReal}'. Formatos autorizados para branding: PNG, JPG, WEBP."
            );
        }

        $extEsperada = self::MIMES_SUBIDA_PERMITIDOS[$mimeRealMin];
        if ($extOriginal !== $extEsperada && !($extOriginal === 'jpeg' && $extEsperada === 'jpg')) {
            throw new InvalidArgumentException(
                "Inconsistencia de seguridad: la extensión declarada '.{$extOriginal}' no corresponde con el contenido real detectado '{$mimeReal}'."
            );
        }

        // 4. Verificación de integridad de imagen y dimensiones (previene payloads PHP camuflados en imágenes)
        $infoImagen = @getimagesize($rutaTemporal);
        if ($infoImagen === false || empty($infoImagen[0]) || empty($infoImagen[1])) {
            throw new InvalidArgumentException('El archivo subido no es una imagen gráfica válida o está corrupto.');
        }

        return [
            'extension' => $extEsperada,
            'mime'      => $mimeRealMin,
            'ancho'     => (int) $infoImagen[0],
            'alto'      => (int) $infoImagen[1],
        ];
    }

    /**
     * Almacena físicamente el archivo en el directorio institucional gobernado.
     * Retorna metadatos con el nombre seguro generado y la ruta relativa para persistencia en BD.
     *
     * @return array{nombre_archivo: string, ruta_relativa: string, ruta_fisica: string}
     */
    public static function almacenarArchivo(
        string $rutaTemporal,
        int $organizacionId,
        string $tipo,
        string $extension,
        string $raizProyecto
    ): array {
        $nombreArchivo = self::generarNombreArchivo($tipo, $extension);
        $dirFisico = self::obtenerRutaFisicaDirectorio($raizProyecto, $organizacionId);

        if (!is_dir($dirFisico)) {
            if (!mkdir($dirFisico, 0755, true) && !is_dir($dirFisico)) {
                throw new \RuntimeException("No fue posible crear el directorio de almacenamiento: {$dirFisico}");
            }
        }

        $rutaFisica = $dirFisico . DIRECTORY_SEPARATOR . $nombreArchivo;

        // Soporta tanto subidas HTTP (move_uploaded_file) como testing local/CLI (copy)
        $guardado = is_uploaded_file($rutaTemporal)
            ? move_uploaded_file($rutaTemporal, $rutaFisica)
            : copy($rutaTemporal, $rutaFisica);

        if (!$guardado || !file_exists($rutaFisica)) {
            throw new \RuntimeException('Error de entrada/salida al guardar el archivo de branding.');
        }

        @chmod($rutaFisica, 0644);

        return [
            'nombre_archivo' => $nombreArchivo,
            'ruta_relativa'  => self::generarRutaRelativa($organizacionId, $nombreArchivo),
            'ruta_fisica'    => $rutaFisica,
        ];
    }

    /**
     * Retira de forma segura un archivo de branding anterior para evitar huérfanos.
     * Valida estrictamente que la ruta pertenezca al directorio de la organización (anti-path traversal).
     */
    public static function eliminarArchivoAnterior(string $raizProyecto, int $organizacionId, ?string $rutaRelativa): bool
    {
        if (empty($rutaRelativa)) {
            return false;
        }

        // Validar que la ruta comience exactamente con la ruta canónica de la organización
        $prefijoEsperado = "/recursos/subidas/organizaciones/{$organizacionId}/branding/";
        if (!str_starts_with($rutaRelativa, $prefijoEsperado)) {
            return false;
        }

        // Prevenir path traversal
        if (str_contains($rutaRelativa, '..')) {
            return false;
        }

        $nombreArchivo = basename($rutaRelativa);
        $rutaFisica = self::obtenerRutaFisicaDirectorio($raizProyecto, $organizacionId) . DIRECTORY_SEPARATOR . $nombreArchivo;

        if (file_exists($rutaFisica) && is_file($rutaFisica)) {
            return @unlink($rutaFisica);
        }

        return false;
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
