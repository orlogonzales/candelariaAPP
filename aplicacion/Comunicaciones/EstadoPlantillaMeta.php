<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

enum EstadoPlantillaMeta: string
{
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case PAUSED = 'PAUSED';

    public function permiteEnvio(): bool
    {
        return $this === self::APPROVED;
    }
}
