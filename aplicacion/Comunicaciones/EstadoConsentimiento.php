<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

enum EstadoConsentimiento: string
{
    case CONCEDIDO = 'CONCEDIDO';
    case REVOCADO = 'REVOCADO';

    public function esValido(): bool
    {
        return $this === self::CONCEDIDO;
    }
}
