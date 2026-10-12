<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

enum EstadoConfirmacionProgramacion: string
{
    case BORRADOR            = 'BORRADOR';
    case PRELIMINAR          = 'PRELIMINAR';
    case OFICIAL_CONFIRMADO  = 'OFICIAL_CONFIRMADO';
    case RECTIFICADO         = 'RECTIFICADO';
    case OBSOLETO            = 'OBSOLETO';

    public function esOficial(): bool
    {
        return in_array($this, [self::OFICIAL_CONFIRMADO, self::RECTIFICADO], true);
    }
}
