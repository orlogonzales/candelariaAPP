<?php

declare(strict_types=1);

namespace Aplicacion\Reservas;

/**
 * Tags cerrados de régimen alimentario (Minimización de datos y sin campos médicos libres).
 */
enum RegimenAlimentario: string
{
    case ESTANDAR = 'ESTANDAR';
    case VEGETARIANO = 'VEGETARIANO';
    case CELIACO = 'CELIACO';
    case OTRO_PARAMETRIZADO = 'OTRO_PARAMETRIZADO';
}
