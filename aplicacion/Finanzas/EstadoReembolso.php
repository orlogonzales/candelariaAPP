<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas;

enum EstadoReembolso: string
{
    case SOLICITADO = 'SOLICITADO';
    case APROBADO = 'APROBADO';
    case EJECUTADO = 'EJECUTADO';
    case RECHAZADO = 'RECHAZADO';
    case FALLIDO = 'FALLIDO';

    public function impactaSaldo(): bool
    {
        return $this === self::EJECUTADO;
    }
}
