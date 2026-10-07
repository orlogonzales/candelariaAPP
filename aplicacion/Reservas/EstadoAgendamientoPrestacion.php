<?php

declare(strict_types=1);

namespace Aplicacion\Reservas;

/**
 * Estados de agendamiento para las prestaciones individuales de la reserva.
 */
enum EstadoAgendamientoPrestacion: string
{
    case PENDIENTE_PROGRAMAR = 'PENDIENTE_PROGRAMAR';
    case PROGRAMADA = 'PROGRAMADA';
    case CANCELADA = 'CANCELADA';
}
