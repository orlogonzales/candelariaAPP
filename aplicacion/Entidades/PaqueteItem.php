<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use InvalidArgumentException;

/**
 * Entidad de dominio PaqueteItem:
 * Representa un ítem incluido obligatoriamente dentro de la composición de un paquete comercial.
 */
class PaqueteItem
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $paqueteId,
        public readonly int $itemComercialId,
        public readonly float $cantidad = 1.0,
        public readonly int $orden = 0,
        public readonly ?string $creadoEn = null,
        public readonly ?ItemComercial $itemComercial = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->paqueteId <= 0) {
            throw new InvalidArgumentException("El ID de paquete debe ser un entero positivo.");
        }

        if ($this->itemComercialId <= 0) {
            throw new InvalidArgumentException("El ID de ítem comercial debe ser un entero positivo.");
        }

        if ($this->cantidad <= 0.0) {
            throw new InvalidArgumentException("La cantidad del ítem en el paquete debe ser mayor a cero (recibido: {$this->cantidad}).");
        }

        if ($this->orden < 0) {
            throw new InvalidArgumentException("El orden del ítem en el paquete debe ser no negativo.");
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'paquete_id' => $this->paqueteId,
            'item_comercial_id' => $this->itemComercialId,
            'cantidad' => $this->cantidad,
            'orden' => $this->orden,
            'creado_en' => $this->creadoEn,
            'item_comercial' => $this->itemComercial?->toArray(),
        ];
    }
}
