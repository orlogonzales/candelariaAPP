<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

/**
 * Roles desempeñados por personal y recursos en una salida de campo.
 */
enum RolOperativoRecurso: string
{
    case GUIA_PRINCIPAL      = 'GUIA_PRINCIPAL';
    case GUIA_ASISTENTE      = 'GUIA_ASISTENTE';
    case CONDUCTOR           = 'CONDUCTOR';
    case PATRON_LANCHA       = 'PATRON_LANCHA';
    case COORDINADOR_CAMPO   = 'COORDINADOR_CAMPO';
    case EQUIPO_LOGISTICO    = 'EQUIPO_LOGISTICO';
}
