<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas;

enum AsumeComisionPasarela: string
{
    case ORGANIZACION = 'ORGANIZACION';
    case CLIENTE = 'CLIENTE';
}
