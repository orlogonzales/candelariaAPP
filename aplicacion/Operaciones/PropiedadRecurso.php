<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

/**
 * Régimen de propiedad del recurso operativo.
 */
enum PropiedadRecurso: string
{
    case PROPIO  = 'PROPIO';
    case EXTERNO = 'EXTERNO';
}
