<?php

declare(strict_types=1);

namespace Aplicacion\Ediciones;

/**
 * Ciclo de vida oficial de una Edición Candelaria.
 * Define las fases operativas de la festividad y las reglas de transición.
 */
enum EstadoEdicion: string
{
    case PREOPERACION = 'PREOPERACION';
    case OPERACION = 'OPERACION';
    case POSTPRODUCCION_ENTREGA = 'POSTPRODUCCION_ENTREGA';
    case CERRADA = 'CERRADA';

    /**
     * Retorna la etiqueta formal en español para presentación en vistas humanas.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PREOPERACION            => 'PREOPERACIÓN',
            self::OPERACION               => 'OPERACIÓN',
            self::POSTPRODUCCION_ENTREGA  => 'POSTPRODUCCIÓN / ENTREGA',
            self::CERRADA                 => 'CERRADA',
        };
    }

    /**
     * Indica si el estado es un estado terminal que restringe modificaciones ordinarias.
     */
    public function esTerminal(): bool
    {
        return $this === self::CERRADA;
    }

    /**
     * Evalúa si una transición hacia un nuevo estado es válida según las reglas de negocio.
     *
     * Flujo normal de avance secuencial:
     * PREOPERACION -> OPERACION -> POSTPRODUCCION_ENTREGA -> CERRADA
     *
     * Retroceso operativo extraordinario (requiere motivo formal y permiso ediciones.cambiar_estado):
     * OPERACION -> PREOPERACION
     * POSTPRODUCCION_ENTREGA -> OPERACION
     *
     * Estado CERRADA:
     * Terminal estricto e inmutable. CERO transiciones permitidas (no admite reapertura en F2.1A).
     */
    public function puedeTransicionarA(
        self $destino,
        bool $permitirRetroceso = false
    ): bool {
        // No se permite transición al mismo estado
        if ($this === $destino) {
            return false;
        }

        // El estado CERRADA es terminal estricto e inmutable: ninguna salida permitida
        if ($this === self::CERRADA) {
            return false;
        }

        // 1. Avance normal secuencial
        $avanceSecuencial = match ($this) {
            self::PREOPERACION           => $destino === self::OPERACION,
            self::OPERACION              => $destino === self::POSTPRODUCCION_ENTREGA,
            self::POSTPRODUCCION_ENTREGA => $destino === self::CERRADA,
            self::CERRADA                => false,
        };

        if ($avanceSecuencial) {
            return true;
        }

        // 2. Retroceso controlado entre fases operativas
        if ($permitirRetroceso) {
            return match ($this) {
                self::OPERACION              => $destino === self::PREOPERACION,
                self::POSTPRODUCCION_ENTREGA => $destino === self::OPERACION,
                default                      => false,
            };
        }

        return false;
    }

    /**
     * Convierte de manera segura una cadena en la instancia del Enum.
     */
    public static function intentarDesde(string $valor): ?self
    {
        $valorNormalizado = strtoupper(trim($valor));
        return self::tryFrom($valorNormalizado);
    }
}
