<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas;

enum MotivoReembolso: string
{
    case DESISTIMIENTO_CLIENTE = 'DESISTIMIENTO_CLIENTE';
    case FUERZA_MAYOR_CLIMA = 'FUERZA_MAYOR_CLIMA';
    case ERROR_DUPLICIDAD_PAGO = 'ERROR_DUPLICIDAD_PAGO';
    case AJUSTE_COMERCIAL = 'AJUSTE_COMERCIAL';
}
