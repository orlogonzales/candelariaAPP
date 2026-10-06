<?php

declare(strict_types=1);

namespace Aplicacion\Ventas;

/**
 * Discriminador del tipo de descuento aplicado a una venta o línea.
 */
enum TipoDescuentoVenta: string
{
    case NINGUNO    = 'NINGUNO';
    case PORCENTAJE = 'PORCENTAJE';
    case MONTO_FIJO = 'MONTO_FIJO';
}
