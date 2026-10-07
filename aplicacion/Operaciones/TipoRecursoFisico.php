<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

/**
 * Clasificación de recursos físicos para operaciones de campo.
 */
enum TipoRecursoFisico: string
{
    case VEHICULO_TERRESTRE   = 'VEHICULO_TERRESTRE';
    case EMBARCACION_LACUSTRE = 'EMBARCACION_LACUSTRE';
    case EQUIPO_LOGISTICO     = 'EQUIPO_LOGISTICO';
    case OTRO                 = 'OTRO';
}
