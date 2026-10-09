<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

enum EstadoMensaje: string
{
    case ENCOLADO             = 'ENCOLADO';
    case EN_PROCESO           = 'EN_PROCESO';
    case REINTENTO_PROGRAMADO = 'REINTENTO_PROGRAMADO';
    case ENVIADO              = 'ENVIADO';
    case ENTREGADO            = 'ENTREGADO';
    case LEIDO                = 'LEIDO';
    case FALLIDO              = 'FALLIDO';
    case CANCELADO            = 'CANCELADO';

    public function peso(): int
    {
        return match ($this) {
            self::ENCOLADO             => 10,
            self::EN_PROCESO           => 20,
            self::REINTENTO_PROGRAMADO => 25,
            self::ENVIADO              => 30,
            self::ENTREGADO            => 40,
            self::LEIDO                => 50,
            self::FALLIDO              => 90,
            self::CANCELADO            => 95,
        };
    }

    /**
     * Máquina de estados explícita: define los estados a los que se puede transicionar contractualmente.
     * Regla de oro: un mensaje ENTREGADO o LEIDO NUNCA puede pasar a FALLIDO, CANCELADO o estados previos.
     *
     * @return array<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::ENCOLADO             => [self::EN_PROCESO, self::CANCELADO],
            self::EN_PROCESO           => [self::ENVIADO, self::REINTENTO_PROGRAMADO, self::FALLIDO, self::CANCELADO],
            self::REINTENTO_PROGRAMADO => [self::EN_PROCESO, self::FALLIDO, self::CANCELADO],
            self::ENVIADO              => [self::ENTREGADO, self::LEIDO, self::FALLIDO],
            self::ENTREGADO            => [self::LEIDO], // Inmutable ante fallos tardíos
            self::LEIDO                => [],            // Terminal exitoso absoluto
            self::FALLIDO              => [],            // Terminal fallido absoluto
            self::CANCELADO            => [],            // Terminal cancelado absoluto
        };
    }

    /**
     * Estados de origen válidos desde los cuales se puede transicionar a un estado destino.
     *
     * @return array<string>
     */
    public static function estadosOrigenValidosPara(self $destino): array
    {
        return match ($destino) {
            self::ENCOLADO             => [],
            self::EN_PROCESO           => [self::ENCOLADO->value, self::REINTENTO_PROGRAMADO->value],
            self::REINTENTO_PROGRAMADO => [self::EN_PROCESO->value],
            self::ENVIADO              => [self::EN_PROCESO->value],
            self::ENTREGADO            => [self::ENVIADO->value],
            self::LEIDO                => [self::ENVIADO->value, self::ENTREGADO->value],
            self::FALLIDO              => [self::EN_PROCESO->value, self::REINTENTO_PROGRAMADO->value, self::ENVIADO->value],
            self::CANCELADO            => [self::ENCOLADO->value, self::EN_PROCESO->value, self::REINTENTO_PROGRAMADO->value],
        };
    }

    /**
     * Valida si la transición entre este estado y el nuevo estado está contractualmente permitida.
     */
    public function puedeAvanzarHacia(self $nuevo): bool
    {
        if ($this === $nuevo) {
            return false;
        }

        return in_array($nuevo, $this->transicionesPermitidas(), true);
    }

    /**
     * Validador flexible que acepta instancia enum o string.
     */
    public function puedeTransicionarHacia(string|self $nuevo): bool
    {
        $destino = is_string($nuevo) ? self::tryFrom($nuevo) : $nuevo;
        if ($destino === null) {
            return false;
        }

        return $this->puedeAvanzarHacia($destino);
    }

    public function esTerminal(): bool
    {
        return $this === self::LEIDO || $this === self::CANCELADO || $this === self::FALLIDO;
    }
}
