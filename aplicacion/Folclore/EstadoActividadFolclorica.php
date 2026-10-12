<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

enum EstadoActividadFolclorica: string
{
    case PROGRAMADA   = 'PROGRAMADA';
    case EN_EJECUCION = 'EN_EJECUCION';
    case CONCLUIDA    = 'CONCLUIDA';
    case SUSPENDIDA   = 'SUSPENDIDA';
}
