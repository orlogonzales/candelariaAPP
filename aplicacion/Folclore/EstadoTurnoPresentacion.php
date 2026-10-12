<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

enum EstadoTurnoPresentacion: string
{
    case PROGRAMADO     = 'PROGRAMADO';
    case EN_PREPARACION = 'EN_PREPARACION';
    case EN_ESCENARIO   = 'EN_ESCENARIO';
    case EN_RUTA        = 'EN_RUTA';
    case FINALIZADO     = 'FINALIZADO';
    case NO_PRESENTO    = 'NO_PRESENTO';
}
