<?php

declare(strict_types=1);

namespace Aplicacion\Ventas;

/**
 * Catálogo gobernado de motivos de cancelación comercial de una venta.
 */
enum MotivoCancelacionVenta: string
{
    case DESISTIMIENTO_CLIENTE       = 'DESISTIMIENTO_CLIENTE';
    case FUERZA_MAYOR_CLIMA          = 'FUERZA_MAYOR_CLIMA';
    case PROBLEMAS_SALUD             = 'PROBLEMAS_SALUD';
    case INCUMPLIMIENTO_ORGANIZACION = 'INCUMPLIMIENTO_ORGANIZACION';
    case OTRO                        = 'OTRO';

    public function requiereDetalle(): bool
    {
        return $this === self::OTRO;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::DESISTIMIENTO_CLIENTE       => 'Desistimiento del Cliente',
            self::FUERZA_MAYOR_CLIMA          => 'Fuerza Mayor / Clima',
            self::PROBLEMAS_SALUD             => 'Problemas de Salud',
            self::INCUMPLIMIENTO_ORGANIZACION => 'Incumplimiento de la Organización',
            self::OTRO                        => 'Otro Motivo Justificado',
        };
    }
}
