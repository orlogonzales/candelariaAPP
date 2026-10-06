<?php

declare(strict_types=1);

namespace Aplicacion\Cotizaciones;

enum MotivoAnulacionCotizacion: string
{
    case SUPERADA_POR_REVISION            = 'SUPERADA_POR_REVISION';
    case ERROR_DATOS                      = 'ERROR_DATOS';
    case CAMBIO_CONDICIONES_ORGANIZACION  = 'CAMBIO_CONDICIONES_ORGANIZACION';
    case EXPIRACION_DEFINITIVA            = 'EXPIRACION_DEFINITIVA';
    case OTRO                             = 'OTRO';
}
