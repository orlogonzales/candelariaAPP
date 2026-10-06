<?php

declare(strict_types=1);

namespace Aplicacion\Catalogo;

/**
 * Estados de gobernanza para elementos del catálogo comercial.
 */
enum EstadoCatalogo: string
{
    case ACTIVO = 'ACTIVO';
    case INACTIVO = 'INACTIVO';

    public static function esValido(string $valor): bool
    {
        return self::tryFrom(strtoupper(trim($valor))) !== null;
    }
}
