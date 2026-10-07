<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

/**
 * Estados individuales de asistencia y abordaje de participantes en salida.
 */
enum EstadoAsistencia: string
{
    case PENDIENTE = 'PENDIENTE';
    case PRESENTE  = 'PRESENTE';
    case NO_SHOW   = 'NO_SHOW';
}
