<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

enum ModoComunicacion: string
{
    case SIMULADOR = 'SIMULADOR';
    case PRODUCCION = 'PRODUCCION';

    public function esProduccion(): bool
    {
        return $this === self::PRODUCCION;
    }
}
