<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

/**
 * Estados de habilitación del proveedor en la organización.
 */
enum EstadoProveedor: string
{
    case ACTIVO     = 'ACTIVO';
    case INACTIVO   = 'INACTIVO';
    case SUSPENDIDO = 'SUSPENDIDO';
}
