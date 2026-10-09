<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

enum FinalidadConsentimiento: string
{
    case TRANSACCIONAL_OPERATIVO = 'TRANSACCIONAL_OPERATIVO';
    case PROMOCIONAL_MARKETING = 'PROMOCIONAL_MARKETING';
}
