<?php

declare(strict_types=1);

namespace Aplicacion\Cotizaciones;

enum TipoDescuentoCotizacion: string
{
    case NINGUNO    = 'NINGUNO';
    case PORCENTAJE = 'PORCENTAJE';
    case MONTO_FIJO = 'MONTO_FIJO';
}
