<?php

declare(strict_types=1);

namespace Aplicacion\Reservas;

/**
 * Clasificación etaria operativa para seguros y aforos.
 */
enum RangoEtarioParticipante: string
{
    case ADULTO = 'ADULTO';
    case MENOR = 'MENOR';
    case INFANTE = 'INFANTE';
}
