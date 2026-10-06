<?php

declare(strict_types=1);

namespace Aplicacion\Cotizaciones;

enum MotivoRechazoCotizacion: string
{
    case PRECIO_ELEVADO       = 'PRECIO_ELEVADO';
    case COMPETENCIA          = 'COMPETENCIA';
    case CAMBIO_FECHA         = 'CAMBIO_FECHA';
    case CAMBIO_REQUERIMIENTO = 'CAMBIO_REQUERIMIENTO';
    case CLIENTE_DESISTIO     = 'CLIENTE_DESISTIO';
    case OTRO                 = 'OTRO';
}
