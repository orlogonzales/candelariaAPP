<?php

declare(strict_types=1);

namespace Aplicacion\Clientes;

use InvalidArgumentException;

/**
 * Ciclo de vida comercial gobernado del Cliente / Contacto CRM.
 * Define los estados comerciales soberanos y las reglas de transición.
 * REGLA ESTRICTA: CLIENTE_RECURRENTE NO es un estado almacenable;
 * es una métrica transaccional derivada.
 */
enum EstadoCliente: string
{
    case CONTACTO  = 'CONTACTO';
    case PROSPECTO = 'PROSPECTO';
    case CLIENTE   = 'CLIENTE';
    case INACTIVO  = 'INACTIVO';

    /**
     * Etiqueta humana para presentación institucional.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::CONTACTO  => 'CONTACTO',
            self::PROSPECTO => 'PROSPECTO',
            self::CLIENTE   => 'CLIENTE',
            self::INACTIVO  => 'INACTIVO',
        };
    }

    /**
     * Evalúa si una transición hacia un nuevo estado comercial es permitida.
     * CONTACTO  -> PROSPECTO | CLIENTE | INACTIVO
     * PROSPECTO -> CLIENTE | CONTACTO | INACTIVO
     * CLIENTE   -> INACTIVO | PROSPECTO
     * INACTIVO  -> CONTACTO | PROSPECTO | CLIENTE
     */
    public function puedeTransicionarA(self $destino): bool
    {
        if ($this === $destino) {
            return false;
        }

        return match ($this) {
            self::CONTACTO  => in_array($destino, [self::PROSPECTO, self::CLIENTE, self::INACTIVO], true),
            self::PROSPECTO => in_array($destino, [self::CLIENTE, self::CONTACTO, self::INACTIVO], true),
            self::CLIENTE   => in_array($destino, [self::INACTIVO, self::PROSPECTO], true),
            self::INACTIVO  => in_array($destino, [self::CONTACTO, self::PROSPECTO, self::CLIENTE], true),
        };
    }

    /**
     * Convierte de manera segura una cadena en la instancia del Enum.
     * Lanza InvalidArgumentException si no existe o si se intenta usar 'CLIENTE_RECURRENTE'.
     */
    public static function desdeCadena(string $valor): self
    {
        $valorNormalizado = strtoupper(trim($valor));
        if ($valorNormalizado === 'CLIENTE_RECURRENTE') {
            throw new InvalidArgumentException(
                "CLIENTE_RECURRENTE no es un estado comercial almacenable; es una métrica calculada a partir de transacciones."
            );
        }

        $estado = self::tryFrom($valorNormalizado);
        if ($estado === null) {
            throw new InvalidArgumentException("Estado comercial no válido: '{$valor}'.");
        }

        return $estado;
    }
}
