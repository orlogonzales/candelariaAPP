<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use InvalidArgumentException;

/**
 * Entidad de dominio OperacionSalidaPrestacion:
 * Vinculación de una prestación de reserva a una salida operativa compatible.
 */
class OperacionSalidaPrestacion
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $salidaId,
        public readonly int $prestacionId,
        public readonly float $cantidadPasajeros,
        public readonly int $asignadoPor = 1,
        public readonly ?string $asignadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->salidaId <= 0) {
            throw new InvalidArgumentException("El salidaId debe ser un entero positivo.");
        }
        if ($this->prestacionId <= 0) {
            throw new InvalidArgumentException("El prestacionId debe ser un entero positivo.");
        }
        if ($this->cantidadPasajeros <= 0) {
            throw new InvalidArgumentException("La cantidad de pasajeros asignada debe ser mayor a 0.");
        }
    }
}
