<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

enum EstadoMensaje: string
{
    case ENCOLADO = 'ENCOLADO';
    case EN_PROCESO = 'EN_PROCESO';
    case ENVIADO = 'ENVIADO';
    case ENTREGADO = 'ENTREGADO';
    case LEIDO = 'LEIDO';
    case FALLIDO = 'FALLIDO';
    case CANCELADO = 'CANCELADO';

    public function peso(): int
    {
        return match ($this) {
            self::ENCOLADO   => 10,
            self::EN_PROCESO => 20,
            self::ENVIADO    => 30,
            self::ENTREGADO  => 40,
            self::LEIDO      => 50,
            self::FALLIDO    => 90,
            self::CANCELADO  => 95,
        };
    }

    /**
     * Regla de monotonicidad: un evento solo puede avanzar el estado a un peso mayor,
     * impidiendo que entregas tardías o fuera de orden degraden un estado ya alcanzado (ej. LEIDO -> ENTREGADO).
     */
    public function puedeAvanzarHacia(self $nuevo): bool
    {
        if ($this === $nuevo) {
            return false;
        }

        // Estados terminales negativos no admiten retroceso
        if ($this === self::CANCELADO) {
            return false;
        }

        // Si ya falló definitivamente, solo se permite reintentar si se re-encola explícitamente
        if ($this === self::FALLIDO && $nuevo !== self::ENCOLADO) {
            return false;
        }

        // Transición monotónica estricta por peso
        return $nuevo->peso() > $this->peso();
    }

    public function esTerminal(): bool
    {
        return $this === self::LEIDO || $this === self::FALLIDO || $this === self::CANCELADO;
    }
}
