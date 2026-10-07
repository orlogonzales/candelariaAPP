<?php

declare(strict_types=1);

namespace Aplicacion\Reservas;

/**
 * Naturaleza funcional del aforo para la prestación/ítem.
 */
enum TipoCapacidad: string
{
    case SIN_CONTROL = 'SIN_CONTROL';
    case COLECTIVA = 'COLECTIVA';
    case DISCRETA = 'DISCRETA';
    case EXCLUSIVA = 'EXCLUSIVA';
}
