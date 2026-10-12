<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

enum EstadoInscripcionConjunto: string
{
    case INSCRITO    = 'INSCRITO';
    case CONFIRMADO  = 'CONFIRMADO';
    case RETIRADO    = 'RETIRADO';
    case SANCIONADO  = 'SANCIONADO';

    public function permiteProgramacion(): bool
    {
        return in_array($this, [self::INSCRITO, self::CONFIRMADO], true);
    }
}
