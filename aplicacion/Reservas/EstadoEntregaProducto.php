<?php

declare(strict_types=1);

namespace Aplicacion\Reservas;

/**
 * Estados del despacho/entrega de bienes tangibles.
 */
enum EstadoEntregaProducto: string
{
    case PENDIENTE_ENTREGA = 'PENDIENTE_ENTREGA';
    case ENTREGADO = 'ENTREGADO';
    case CANCELADO = 'CANCELADO';
}
