<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

use InvalidArgumentException;

/**
 * Estados del turno de presentación folclórica.
 * Mantiene compatibilidad estricta con los valores ENUM de base de datos mientras
 * ofrece normalización de alias operativos (DT-V11-008) y control de máquina de estados.
 */
enum EstadoTurnoPresentacion: string
{
    case PROGRAMADO     = 'PROGRAMADO';
    case EN_PREPARACION = 'EN_PREPARACION';
    case EN_ESCENARIO   = 'EN_ESCENARIO';
    case EN_RUTA        = 'EN_RUTA';
    case FINALIZADO     = 'FINALIZADO';
    case NO_PRESENTO    = 'NO_PRESENTO';

    /**
     * Etiqueta amigable oficial para la interfaz de Alina y reportes ejecutivos.
     */
    public function etiquetaOperativa(): string
    {
        return match ($this) {
            self::PROGRAMADO     => 'Pendiente',
            self::EN_PREPARACION => 'En Preparación',
            self::EN_RUTA        => 'En Desplazamiento',
            self::EN_ESCENARIO   => 'En Presentación',
            self::FINALIZADO     => 'Finalizado',
            self::NO_PRESENTO    => 'No Presentado',
        };
    }

    /**
     * Clase de estilo cromático del sistema de diseño Alina.
     */
    public function colorBadgeAlina(): string
    {
        return match ($this) {
            self::PROGRAMADO     => 'secondary',
            self::EN_PREPARACION => 'warning',
            self::EN_RUTA        => 'info',
            self::EN_ESCENARIO   => 'primary',
            self::FINALIZADO     => 'success',
            self::NO_PRESENTO    => 'danger',
        };
    }

    /**
     * Normaliza entradas del cliente aceptando tanto códigos canónicos como alias operativos.
     */
    public static function normalizar(string|self $valor): self
    {
        if ($valor instanceof self) {
            return $valor;
        }

        $normalizado = strtoupper(trim($valor));

        return match ($normalizado) {
            'PROGRAMADO', 'PENDIENTE'                     => self::PROGRAMADO,
            'EN_PREPARACION', 'PREPARACION'               => self::EN_PREPARACION,
            'EN_RUTA', 'EN_DESPLAZAMIENTO', 'DESPLAZAMIENTO', 'RUTA' => self::EN_RUTA,
            'EN_ESCENARIO', 'EN_PRESENTACION', 'PRESENTACION', 'ESCENARIO' => self::EN_ESCENARIO,
            'FINALIZADO', 'TERMINADO', 'CONCLUIDO'        => self::FINALIZADO,
            'NO_PRESENTO', 'NO_PRESENTADO', 'AUSENTE'     => self::NO_PRESENTO,
            default => throw new InvalidArgumentException("Estado de turno no reconocido o incompatible: '{$valor}'."),
        };
    }

    /**
     * Retorna la lista de transiciones permitidas desde este estado.
     *
     * @return array<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::PROGRAMADO => [
                self::EN_PREPARACION,
                self::EN_RUTA,
                self::NO_PRESENTO,
            ],
            self::EN_PREPARACION => [
                self::EN_RUTA,
                self::EN_ESCENARIO,
                self::NO_PRESENTO,
            ],
            self::EN_RUTA => [
                self::EN_ESCENARIO,
                self::FINALIZADO,
                self::NO_PRESENTO,
            ],
            self::EN_ESCENARIO => [
                self::FINALIZADO,
                self::NO_PRESENTO,
            ],
            self::FINALIZADO, self::NO_PRESENTO => [],
        };
    }

    /**
     * Valida si es permitida la transición hacia el nuevo estado (idempotencia permitida).
     */
    public function puedeTransicionarA(self $nuevoEstado): bool
    {
        if ($this === $nuevoEstado) {
            return true;
        }

        return in_array($nuevoEstado, $this->transicionesPermitidas(), true);
    }

    /**
     * Determina si el estado es final/terminal.
     */
    public function esTerminal(): bool
    {
        return $this === self::FINALIZADO || $this === self::NO_PRESENTO;
    }
}
