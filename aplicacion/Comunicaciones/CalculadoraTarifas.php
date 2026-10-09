<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

use PDO;

class CalculadoraTarifas
{
    public function __construct(private ?PDO $pdo = null) {}

    /**
     * Calcula la tarifa estimada o final en USD por entrega de mensaje.
     */
    public function obtenerTarifa(
        int $proveedorId,
        string $codigoPais,
        CategoriaPlantilla $categoria,
        bool $dentroDeVentanaServicio = false
    ): float {
        // En la política vigente de Meta (desde nov 2024 / julio 2025):
        // Utility dentro de una ventana de servicio de 24h abierta es GRATUITO (0.00 USD).
        if ($categoria === CategoriaPlantilla::UTILITY && $dentroDeVentanaServicio) {
            return 0.00;
        }

        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare("
                SELECT costo_unidad_usd 
                FROM comunicacion_tarifas 
                WHERE proveedor_id = :prov 
                  AND codigo_pais = :pais 
                  AND categoria_plantilla = :cat
                  AND vigente_desde <= CURRENT_DATE()
                  AND (vigente_hasta IS NULL OR vigente_hasta >= CURRENT_DATE())
                ORDER BY vigente_desde DESC 
                LIMIT 1
            ");
            $stmt->execute([
                'prov' => $proveedorId,
                'pais' => strtoupper($codigoPais),
                'cat'  => $categoria->value
            ]);
            $costo = $stmt->fetchColumn();
            if ($costo !== false) {
                return (float) $costo;
            }
        }

        // Tarifas base de referencia Meta para Perú / LATAM (versionadas por categoría)
        return match ($categoria) {
            CategoriaPlantilla::UTILITY        => 0.01500,
            CategoriaPlantilla::MARKETING      => 0.04500,
            CategoriaPlantilla::AUTHENTICATION => 0.02000,
        };
    }
}
