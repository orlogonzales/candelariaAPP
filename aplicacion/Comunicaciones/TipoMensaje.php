<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

enum TipoMensaje: string
{
    case TRANSACCIONAL = 'TRANSACCIONAL';
    case PROMOCIONAL = 'PROMOCIONAL';
    case RESPUESTA_OPERADOR = 'RESPUESTA_OPERADOR';
    case ENTRANTE_CLIENTE = 'ENTRANTE_CLIENTE';

    public function esSaliente(): bool
    {
        return $this !== self::ENTRANTE_CLIENTE;
    }

    public function requierePlantilla(): bool
    {
        return $this === self::TRANSACCIONAL || $this === self::PROMOCIONAL;
    }
}
