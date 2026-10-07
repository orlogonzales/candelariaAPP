<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

/**
 * Catálogo gobernado de tipos de incidencias ocurridas durante la operación de campo.
 */
enum TipoIncidenciaOperativa: string
{
    case CLIMA_FUERZA_MAYOR        = 'CLIMA_FUERZA_MAYOR';
    case FALLA_MECANICA_VEHICULO   = 'FALLA_MECANICA_VEHICULO';
    case DEMORA_TRANSPORTE         = 'DEMORA_TRANSPORTE';
    case ACCIDENTE_SALUD           = 'ACCIDENTE_SALUD';
    case QUEJA_CLIENTE             = 'QUEJA_CLIENTE';
    case CIERRE_VIAS_PUERTOS       = 'CIERRE_VIAS_PUERTOS';
    case OTRO                      = 'OTRO';
}
