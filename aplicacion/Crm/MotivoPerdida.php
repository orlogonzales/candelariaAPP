<?php

declare(strict_types=1);

namespace Aplicacion\Crm;

use InvalidArgumentException;

/**
 * Catálogo tipado de motivos de pérdida comercial para Oportunidades.
 */
enum MotivoPerdida: string
{
    case PRECIO = 'PRECIO';
    case COMPETENCIA = 'COMPETENCIA';
    case DESISTIO_VIAJE = 'DESISTIO_VIAJE';
    case FECHAS_INCOMPATIBLES = 'FECHAS_INCOMPATIBLES';
    case SIN_RESPUESTA = 'SIN_RESPUESTA';
    case OTRO = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PRECIO => 'Precio elevado / Fuera de presupuesto',
            self::COMPETENCIA => 'Eligió a la competencia',
            self::DESISTIO_VIAJE => 'Desistió del viaje a la Candelaria',
            self::FECHAS_INCOMPATIBLES => 'Incompatibilidad de fechas o itinerario',
            self::SIN_RESPUESTA => 'Sin respuesta del cliente (decisión explícita)',
            self::OTRO => 'Otro motivo (especificado en detalle)',
        };
    }

    public static function desdeCadena(?string $valor): ?self
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        $valNormalizado = strtoupper(trim($valor));
        foreach (self::cases() as $case) {
            if ($case->value === $valNormalizado) {
                return $case;
            }
        }

        throw new InvalidArgumentException("Motivo de pérdida no válido: '{$valor}'");
    }
}
