<?php

declare(strict_types=1);

namespace Aplicacion\Ventas;

/**
 * Procedencia del origen comercial de la venta.
 */
enum TipoOrigenVenta: string
{
    case COTIZACION = 'COTIZACION';
    case DIRECTA    = 'DIRECTA';
}
