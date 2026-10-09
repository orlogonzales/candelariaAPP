<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

enum CategoriaPlantilla: string
{
    case UTILITY = 'UTILITY';
    case MARKETING = 'MARKETING';
    case AUTHENTICATION = 'AUTHENTICATION';

    public function finalidadRequerida(): FinalidadConsentimiento
    {
        return match ($this) {
            self::UTILITY, self::AUTHENTICATION => FinalidadConsentimiento::TRANSACCIONAL_OPERATIVO,
            self::MARKETING                     => FinalidadConsentimiento::PROMOCIONAL_MARKETING,
        };
    }
}
