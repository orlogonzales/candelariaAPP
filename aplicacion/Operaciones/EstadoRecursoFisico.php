<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

/**
 * Disponibilidad operativa del recurso físico.
 */
enum EstadoRecursoFisico: string
{
    case DISPONIBLE       = 'DISPONIBLE';
    case EN_MANTENIMIENTO = 'EN_MANTENIMIENTO';
    case BAJA             = 'BAJA';
}
