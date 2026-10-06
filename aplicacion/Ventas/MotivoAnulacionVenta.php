<?php

declare(strict_types=1);

namespace Aplicacion\Ventas;

/**
 * Catálogo gobernado de motivos de anulación administrativa de una venta.
 */
enum MotivoAnulacionVenta: string
{
    case ERROR_REGISTRO       = 'ERROR_REGISTRO';
    case DUPLICIDAD_VENTA     = 'DUPLICIDAD_VENTA';
    case FRAUDE_SUPLANTACION  = 'FRAUDE_SUPLANTACION';
    case OTRO                 = 'OTRO';

    public function requiereDetalle(): bool
    {
        return $this === self::OTRO;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::ERROR_REGISTRO      => 'Error de Registro de Datos',
            self::DUPLICIDAD_VENTA    => 'Duplicidad de Venta',
            self::FRAUDE_SUPLANTACION => 'Fraude o Suplantación',
            self::OTRO                => 'Otro Motivo Justificado',
        };
    }
}
