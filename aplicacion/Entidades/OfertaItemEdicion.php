<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Catalogo\EstadoCatalogo;
use InvalidArgumentException;

/**
 * Entidad de dominio OfertaItemEdicion:
 * Habilitación comercial de un ítem para una edición anual específica de la festividad.
 */
class OfertaItemEdicion
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $edicionId,
        public readonly int $itemComercialId,
        public readonly EstadoCatalogo $estado = EstadoCatalogo::ACTIVO,
        public readonly ?int $capacidadReferencial = null,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null,
        public readonly ?ItemComercial $itemComercial = null,
        public readonly ?TarifaItemEdicion $tarifaVigente = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->organizacionId <= 0) {
            throw new InvalidArgumentException("El ID de organización debe ser un entero positivo.");
        }

        if ($this->edicionId <= 0) {
            throw new InvalidArgumentException("El ID de edición debe ser un entero positivo.");
        }

        if ($this->itemComercialId <= 0) {
            throw new InvalidArgumentException("El ID de ítem comercial debe ser un entero positivo.");
        }

        if ($this->capacidadReferencial !== null && $this->capacidadReferencial < 0) {
            throw new InvalidArgumentException("La capacidad referencial de la oferta no puede ser negativa.");
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizacion_id' => $this->organizacionId,
            'edicion_id' => $this->edicionId,
            'item_comercial_id' => $this->itemComercialId,
            'estado' => $this->estado->value,
            'capacidad_referencial' => $this->capacidadReferencial,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'item_comercial' => $this->itemComercial?->toArray(),
            'tarifa_vigente' => $this->tarifaVigente?->toArray(),
        ];
    }
}
