<?php

declare(strict_types=1);

namespace Aplicacion\Ventas;

/**
 * Discriminador del tipo de línea comercial vendida.
 */
enum TipoLineaVenta: string
{
    case ITEM    = 'ITEM';
    case PAQUETE = 'PAQUETE';
}
