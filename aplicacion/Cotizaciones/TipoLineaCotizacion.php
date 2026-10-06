<?php

declare(strict_types=1);

namespace Aplicacion\Cotizaciones;

enum TipoLineaCotizacion: string
{
    case ITEM    = 'ITEM';
    case PAQUETE = 'PAQUETE';
}
