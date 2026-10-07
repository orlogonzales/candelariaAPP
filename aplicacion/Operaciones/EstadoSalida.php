<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

/**
 * Estados del ciclo de vida operativo de una salida de campo.
 * Autoridad soberana exclusiva de Operación (desacoplada de Reserva).
 */
enum EstadoSalida: string
{
    case PROGRAMADA   = 'PROGRAMADA';
    case EN_CHECKIN   = 'EN_CHECKIN';
    case DESPACHADA   = 'DESPACHADA';
    case FINALIZADA   = 'FINALIZADA';
    case INTERRUMPIDA = 'INTERRUMPIDA';
    case CANCELADA    = 'CANCELADA';

    public function permiteAsignacion(): bool
    {
        return $this === self::PROGRAMADA;
    }

    public function permiteCheckin(): bool
    {
        return in_array($this, [self::PROGRAMADA, self::EN_CHECKIN], true);
    }

    public function permiteDespacho(): bool
    {
        return in_array($this, [self::PROGRAMADA, self::EN_CHECKIN], true);
    }

    public function esTerminal(): bool
    {
        return in_array($this, [self::FINALIZADA, self::CANCELADA], true);
    }
}
