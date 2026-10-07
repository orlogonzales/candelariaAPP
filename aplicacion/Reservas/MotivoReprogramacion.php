<?php

declare(strict_types=1);

namespace Aplicacion\Reservas;

/**
 * Categorías válidas de motivo para reprogramaciones de prestaciones.
 */
enum MotivoReprogramacion: string
{
    case SOLICITUD_CLIENTE = 'SOLICITUD_CLIENTE';
    case CLIMA_FUERZA_MAYOR = 'CLIMA_FUERZA_MAYOR';
    case LOGISTICA_OPERATIVA = 'LOGISTICA_OPERATIVA';
    case OTRO = 'OTRO';
}
