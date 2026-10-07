<?php

declare(strict_types=1);

namespace Aplicacion\Reservas;

/**
 * Estados administrativos canónicos de la cabecera de Reserva.
 * Nota: Los estados consolidados de ejecución física (CUMPLIDA / CUMPLIDA_PARCIAL)
 * pertenecerán a la derivación operativa al implementarse el subdominio Operación en F2.6D.
 */
enum EstadoReserva: string
{
    case REGISTRADA = 'REGISTRADA';
    case PENDIENTE_DATOS = 'PENDIENTE_DATOS';
    case CONFIRMADA = 'CONFIRMADA';
    case CANCELADA = 'CANCELADA';
}
