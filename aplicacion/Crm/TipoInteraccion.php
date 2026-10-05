<?php

declare(strict_types=1);

namespace Aplicacion\Crm;

use InvalidArgumentException;

/**
 * Catálogo gobernado de tipos de interacciones comerciales.
 */
enum TipoInteraccion: string
{
    case LLAMADA = 'LLAMADA';
    case WHATSAPP = 'WHATSAPP';
    case CORREO = 'CORREO';
    case REUNION = 'REUNION';
    case NOTA_INTERNA = 'NOTA_INTERNA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LLAMADA => 'Llamada Telefónica',
            self::WHATSAPP => 'Mensajería WhatsApp',
            self::CORREO => 'Correo Electrónico',
            self::REUNION => 'Reunión Presencial / Virtual',
            self::NOTA_INTERNA => 'Nota Interna de Seguimiento',
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

        throw new InvalidArgumentException("Tipo de interacción no válido: '{$valor}'");
    }
}
