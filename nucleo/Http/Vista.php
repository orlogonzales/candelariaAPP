<?php

declare(strict_types=1);

namespace Nucleo\Http;

use RuntimeException;

/**
 * Motor de renderizado de vistas nativo para el MVC propio de CandelariaAPP.
 * Nomenclatura en español estricta.
 */
class Vista
{
    private static string $directorioBase = __DIR__ . '/../../recursos/vistas';

    /**
     * Renderiza una vista dentro de un layout base reutilizable.
     */
    public static function renderizar(string $nombreVista, array $datos = [], string $nombreLayout = 'principal'): string
    {
        $archivoVista = self::$directorioBase . '/paginas/' . $nombreVista . '.php';

        if (!file_exists($archivoVista)) {
            throw new RuntimeException("La vista '{$nombreVista}' no existe en {$archivoVista}");
        }

        // Renderizar el contenido de la vista
        $contenido = self::evaluarArchivo($archivoVista, $datos);

        // Si se especifica un layout, renderizar dentro del layout
        if (!empty($nombreLayout)) {
            $archivoLayout = self::$directorioBase . '/layouts/' . $nombreLayout . '.php';
            if (!file_exists($archivoLayout)) {
                throw new RuntimeException("El layout '{$nombreLayout}' no existe en {$archivoLayout}");
            }

            $datosConContenido = array_merge($datos, ['contenido' => $contenido]);
            return self::evaluarArchivo($archivoLayout, $datosConContenido);
        }

        return $contenido;
    }

    /**
     * Renderiza un componente o parcial reutilizable.
     */
    public static function parcial(string $nombreParcial, array $datos = []): string
    {
        $archivoParcial = self::$directorioBase . '/parciales/' . $nombreParcial . '.php';

        if (!file_exists($archivoParcial)) {
            throw new RuntimeException("El parcial '{$nombreParcial}' no existe en {$archivoParcial}");
        }

        return self::evaluarArchivo($archivoParcial, $datos);
    }

    /**
     * Aísla el alcance de variables y captura el búfer de salida PHP.
     */
    private static function evaluarArchivo(string $rutaArchivo, array $datos): string
    {
        extract($datos, EXTR_SKIP);
        ob_start();

        try {
            include $rutaArchivo;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
