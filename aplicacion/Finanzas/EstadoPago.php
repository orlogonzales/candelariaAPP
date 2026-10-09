<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas;

enum EstadoPago: string
{
    case PENDIENTE_VERIFICACION = 'PENDIENTE_VERIFICACION';
    case APROBADO = 'APROBADO';
    case RECHAZADO = 'RECHAZADO';
    case ANULADO = 'ANULADO';

    public function esAprobado(): bool
    {
        return $this === self::APROBADO;
    }

    public function permiteVerificacion(): bool
    {
        return $this === self::PENDIENTE_VERIFICACION;
    }
}
