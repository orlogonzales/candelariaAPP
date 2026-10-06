<?php

declare(strict_types=1);

namespace Aplicacion\Catalogo;

/**
 * Discriminador soberano del ítem comercial.
 */
enum TipoItemComercial: string
{
    case PRODUCTO = 'PRODUCTO';
    case SERVICIO = 'SERVICIO';
}
