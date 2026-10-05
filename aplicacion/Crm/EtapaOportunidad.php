<?php

declare(strict_types=1);

namespace Aplicacion\Crm;

use InvalidArgumentException;

/**
 * Catálogo gobernado de etapas del pipeline comercial de Oportunidades.
 */
enum EtapaOportunidad: string
{
    case NUEVA = 'NUEVA';
    case CONTACTADA = 'CONTACTADA';
    case CALIFICADA = 'CALIFICADA';
    case COTIZACION = 'COTIZACION';
    case NEGOCIACION = 'NEGOCIACION';
    case GANADA = 'GANADA';
    case PERDIDA = 'PERDIDA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NUEVA => 'Nueva Oportunidad',
            self::CONTACTADA => 'Contactada',
            self::CALIFICADA => 'Calificada',
            self::COTIZACION => 'Cotización Enviada',
            self::NEGOCIACION => 'En Negociación',
            self::GANADA => 'Ganada (Cierre Exitoso)',
            self::PERDIDA => 'Perdida',
        };
    }

    public function esTerminal(): bool
    {
        return $this === self::GANADA || $this === self::PERDIDA;
    }

    public function orden(): int
    {
        return match ($this) {
            self::NUEVA => 1,
            self::CONTACTADA => 2,
            self::CALIFICADA => 3,
            self::COTIZACION => 4,
            self::NEGOCIACION => 5,
            self::GANADA => 6,
            self::PERDIDA => 99,
        };
    }

    /**
     * Valida si la transición hacia otra etapa es legal:
     * - No se puede transicionar desde estados terminales (GANADA o PERDIDA).
     * - Desde cualquier etapa activa se puede pasar a PERDIDA (con motivo).
     * - Se permite avance hacia adelante (saltos permitidos).
     * - No se permiten retrocesos ordinarios.
     */
    public function puedeTransicionarA(self $destino): bool
    {
        if ($this->esTerminal()) {
            return false;
        }

        if ($this === $destino) {
            return false;
        }

        if ($destino === self::PERDIDA) {
            return true;
        }

        if ($destino === self::GANADA) {
            return true;
        }

        return $destino->orden() > $this->orden();
    }

    public static function desdeCadena(string $valor): self
    {
        $valNormalizado = strtoupper(trim($valor));
        foreach (self::cases() as $case) {
            if ($case->value === $valNormalizado) {
                return $case;
            }
        }

        throw new InvalidArgumentException("Etapa de oportunidad comercial no válida: '{$valor}'");
    }
}
