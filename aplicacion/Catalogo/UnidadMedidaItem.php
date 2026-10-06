<?php

declare(strict_types=1);

namespace Aplicacion\Catalogo;

/**
 * Catálogo controlado y cerrado de unidades de medida para ítems comerciales.
 */
enum UnidadMedidaItem: string
{
    case UNIDAD = 'UNIDAD';
    case PERSONA = 'PERSONA';
    case NOCHE = 'NOCHE';
    case HABITACION = 'HABITACION';
    case TICKET = 'TICKET';
    case SERVICIO = 'SERVICIO';
    case TRAMO = 'TRAMO';
    case DIA = 'DIA';
    case HORA = 'HORA';

    public static function esValida(string $valor): bool
    {
        return self::tryFrom(strtoupper(trim($valor))) !== null;
    }
}
