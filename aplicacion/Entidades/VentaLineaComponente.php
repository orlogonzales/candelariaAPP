<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use InvalidArgumentException;

/**
 * Entidad de dominio VentaLineaComponente:
 * Snapshot relacional inmutable de los productos y servicios individuales
 * que componen un paquete comercial vendido.
 */
class VentaLineaComponente
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $ventaLineaId,
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
        if ($this->ventaLineaId < 0) {
            throw new InvalidArgumentException("El ID de línea de venta debe ser un entero válido.");
        }
        if (trim($this->itemCodigo) === '') {
            throw new InvalidArgumentException("El código del ítem componente no puede estar vacío.");
        }
        if (trim($this->itemNombre) === '') {
            throw new InvalidArgumentException("El nombre del ítem componente no puede estar vacío.");
        }
        if (!in_array($this->itemTipo, ['PRODUCTO', 'SERVICIO'], true)) {
            throw new InvalidArgumentException("El tipo del componente debe ser PRODUCTO o SERVICIO.");
        }
        if ($this->cantidad <= 0) {
            throw new InvalidArgumentException("La cantidad del componente debe ser estrictamente mayor a 0.");
        }
    }

    public function aArreglo(): array
    {
        return [
            'id'                  => $this->id,
            'venta_linea_id'      => $this->ventaLineaId,
            'item_comercial_id'   => $this->itemComercialId,
            'item_codigo'         => $this->itemCodigo,
            'item_nombre'         => $this->itemNombre,
            'item_tipo'           => $this->itemTipo,
            'unidad_medida'       => $this->unidadMedida,
            'cantidad'            => $this->cantidad,
            'nota'                => $this->nota,
            'orden'               => $this->orden,
            'creado_en'           => $this->creadoEn,
        ];
    }
}
