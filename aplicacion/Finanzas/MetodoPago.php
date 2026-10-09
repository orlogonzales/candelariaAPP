<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas;

enum MetodoPago: string
{
    case EFECTIVO = 'EFECTIVO';
    case TRANSFERENCIA_BANCARIA = 'TRANSFERENCIA_BANCARIA';
    case BILLETERA_DIGITAL = 'BILLETERA_DIGITAL';
    case TARJETA_PASARELA = 'TARJETA_PASARELA';
    case POS_FISICO = 'POS_FISICO';
}
