<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

/**
 * Resultado proyectado determinísticamente hacia la cabecera de Reserva
 * una vez consolidados los hechos físicos de sus prestaciones en campo.
 */
enum ResultadoProyeccionReserva: string
{
    case CUMPLIDA          = 'CUMPLIDA';
    case CUMPLIDA_PARCIAL  = 'CUMPLIDA_PARCIAL';
    case NO_SHOW_TOTAL     = 'NO_SHOW_TOTAL';
    case INTERRUMPIDA      = 'INTERRUMPIDA';
    case CANCELADA         = 'CANCELADA';
}
