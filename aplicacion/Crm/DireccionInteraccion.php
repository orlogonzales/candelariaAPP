<?php

declare(strict_types=1);

namespace Aplicacion\Crm;

use InvalidArgumentException;

/**
 * Dirección del flujo de comunicación en interacciones comerciales.
 */
enum DireccionInteraccion: string
{
    case ENTRANTE = 'ENTRANTE';
    case SALIENTE = 'SALIENTE';
    case INTERNA = 'INTERNA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ENTRANTE => 'Entrante (Inbound)',
            self::SALIENTE => 'Saliente (Outbound)',
            self::INTERNA  => 'Interna',
        };
    }

    public static function desdeCadena(string $valor): self
    {
        $valNormalizado = strtoupper(trim($valor));
        foreach (self::cases() as $case) {
            if ($case->value === $valNormalizado) {
                return $case;
            }
        }

        throw new InvalidArgumentException("Dirección de interacción no válida: '{$valor}'");
    }
}
