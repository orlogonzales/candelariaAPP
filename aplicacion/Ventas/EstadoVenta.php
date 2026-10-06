<?php

declare(strict_types=1);

namespace Aplicacion\Ventas;

/**
 * Máquina de estados gobernada para el ciclo de vida de Ventas Comerciales.
 */
enum EstadoVenta: string
{
    case CONFIRMADA = 'CONFIRMADA';
    case LIQUIDADA  = 'LIQUIDADA';
    case CANCELADA  = 'CANCELADA';
    case ANULADA    = 'ANULADA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CONFIRMADA => 'Venta Confirmada',
            self::LIQUIDADA  => 'Venta Liquidada',
            self::CANCELADA  => 'Venta Cancelada',
            self::ANULADA    => 'Venta Anulada',
        };
    }

    public function esTerminal(): bool
    {
        return match ($this) {
            self::LIQUIDADA, self::CANCELADA, self::ANULADA => true,
            default => false,
        };
    }

    /**
     * Valida si es legal transicionar hacia otro estado:
     * - Desde CONFIRMADA se puede pasar a CANCELADA y ANULADA.
     * - LIQUIDADA queda modelada para fases posteriores pero sin transición funcional en F2.5B.
     * - Estados terminales no pueden transicionar a ningún otro.
     */
    public function puedeTransicionarA(self $destino): bool
    {
        if ($this->esTerminal()) {
            return false;
        }

        if ($this === self::CONFIRMADA) {
            return $destino === self::CANCELADA || $destino === self::ANULADA;
        }

        return false;
    }
}
