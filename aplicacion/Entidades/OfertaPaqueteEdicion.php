<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Catalogo\EstadoCatalogo;
use InvalidArgumentException;

/**
 * Entidad de dominio OfertaPaqueteEdicion:
 * Habilitación comercial de un paquete para una edición anual específica de la festividad.
 */
class OfertaPaqueteEdicion
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $edicionId,
        public readonly int $paqueteId,
        public readonly EstadoCatalogo $estado = EstadoCatalogo::ACTIVO,
        public readonly ?int $capacidadReferencial = null,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null,
        public readonly ?Paquete $paquete = null,
        public readonly ?TarifaPaqueteEdicion $tarifaVigente = null
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

        if ($this->paqueteId <= 0) {
            throw new InvalidArgumentException("El ID de paquete debe ser un entero positivo.");
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
            'paquete_id' => $this->paqueteId,
            'estado' => $this->estado->value,
            'capacidad_referencial' => $this->capacidadReferencial,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'paquete' => $this->paquete?->toArray(),
            'tarifa_vigente' => $this->tarifaVigente?->toArray(),
        ];
    }
}
