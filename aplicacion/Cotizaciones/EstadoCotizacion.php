<?php

declare(strict_types=1);

namespace Aplicacion\Cotizaciones;

enum EstadoCotizacion: string
{
    case BORRADOR  = 'BORRADOR';
    case EMITIDA   = 'EMITIDA';
    case ACEPTADA  = 'ACEPTADA';
    case RECHAZADA = 'RECHAZADA';
    case VENCIDA   = 'VENCIDA';
    case ANULADA   = 'ANULADA';

    public function esTerminal(): bool
    {
        return match ($this) {
            self::ACEPTADA, self::RECHAZADA, self::ANULADA => true,
            default => false,
        };
    }

    public function esEditable(): bool
    {
        return $this === self::BORRADOR;
    }
}
