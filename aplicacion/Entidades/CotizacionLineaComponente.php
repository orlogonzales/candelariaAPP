<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use InvalidArgumentException;

/**
 * Snapshot inmutable del componente incluido dentro de un paquete cotizado.
 */
class CotizacionLineaComponente
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $cotizacionLineaId,
        public readonly ?int $itemComercialId,
        public readonly string $itemCodigo,
        public readonly string $itemNombre,
        public readonly string $itemTipo,
        public readonly string $unidadMedida,
        public readonly float $cantidad = 1.00,
        public readonly ?string $nota = null,
        public readonly int $orden = 0,
        public readonly ?string $creadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->cotizacionLineaId < 0) {
            throw new InvalidArgumentException("El ID de línea de cotización debe ser válido.");
        }
        if (trim($this->itemCodigo) === '') {
            throw new InvalidArgumentException("El código del componente no puede estar vacío.");
        }
        if (trim($this->itemNombre) === '') {
            throw new InvalidArgumentException("El nombre del componente no puede estar vacío.");
        }
        if ($this->cantidad <= 0) {
            throw new InvalidArgumentException("La cantidad del componente debe ser mayor a cero.");
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'cotizacion_linea_id' => $this->cotizacionLineaId,
            'item_comercial_id' => $this->itemComercialId,
            'item_codigo' => $this->itemCodigo,
            'item_nombre' => $this->itemNombre,
            'item_tipo' => $this->itemTipo,
            'unidad_medida' => $this->unidadMedida,
            'cantidad' => $this->cantidad,
            'nota' => $this->nota,
            'orden' => $this->orden,
            'creado_en' => $this->creadoEn,
        ];
    }
}
