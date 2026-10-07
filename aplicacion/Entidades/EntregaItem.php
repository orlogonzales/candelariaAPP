<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use InvalidArgumentException;

/**
 * Entidad de dominio EntregaItem:
 * Ítem físico tangible individual asociado a una orden de entrega.
 */
class EntregaItem
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $entregaId,
        public readonly int $ventaLineaId,
        public readonly ?int $ventaLineaComponenteId,
        public readonly int $itemComercialId,
        public readonly string $conceptoCodigo,
        public readonly string $conceptoNombre,
        public readonly string $unidadMedida,
        public readonly float $cantidad,
        public readonly ?string $creadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->cantidad <= 0) {
            throw new InvalidArgumentException("La cantidad del ítem de entrega debe ser estrictamente mayor a 0.");
        }
        if (trim($this->conceptoCodigo) === '') {
            throw new InvalidArgumentException("El código de concepto no puede estar vacío.");
        }
        if (trim($this->conceptoNombre) === '') {
            throw new InvalidArgumentException("El nombre de concepto no puede estar vacío.");
        }
    }
}
