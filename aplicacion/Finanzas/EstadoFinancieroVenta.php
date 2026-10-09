<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas;

enum EstadoFinancieroVenta: string
{
    case NO_PAGADA = 'NO_PAGADA';
    case PAGO_PARCIAL = 'PAGO_PARCIAL';
    case PAGADA_TOTAL = 'PAGADA_TOTAL';
    case SOBREPAGADA = 'SOBREPAGADA';
    case NO_APLICA = 'NO_APLICA';

    public function permiteLiquidacion(): bool
    {
        return $this === self::PAGADA_TOTAL || $this === self::SOBREPAGADA;
    }
}
