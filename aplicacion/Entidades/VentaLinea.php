<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Ventas\TipoDescuentoVenta;
use Aplicacion\Ventas\TipoLineaVenta;
use InvalidArgumentException;

/**
 * Entidad de dominio VentaLinea:
 * Línea económica y detalle comercial vendido con snapshot congelado y procedencia trazable.
 */
class VentaLinea
{
    /**
     * @param VentaLineaComponente[] $componentes
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $ventaId,
        public readonly ?int $cotizacionLineaId,
        public readonly TipoLineaVenta $tipoLinea,
        public readonly ?int $itemComercialId,
        public readonly ?int $paqueteId,
        public readonly ?int $ofertaItemId,
        public readonly ?int $ofertaPaqueteId,
        public readonly string $conceptoCodigo,
        public readonly string $conceptoNombre,
        public readonly ?string $conceptoDescripcion,
        public readonly string $unidadMedida,
        public readonly float $cantidad,
        public readonly float $precioUnitario,
        public readonly TipoDescuentoVenta $descuentoTipo = TipoDescuentoVenta::NINGUNO,
        public readonly float $descuentoValor = 0.00,
        public readonly float $descuentoMonto = 0.00,
        public readonly ?string $descuentoMotivo = null,
        public readonly float $subtotal = 0.00,
        public readonly string $moneda = 'PEN',
        public readonly int $orden = 0,
        public readonly ?string $notas = null,
        public readonly ?string $creadoEn = null,
        public readonly array $componentes = []
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->ventaId <= 0) {
            throw new InvalidArgumentException("El ID de venta debe ser un entero positivo.");
        }
        if ($this->cantidad <= 0) {
            throw new InvalidArgumentException("La cantidad debe ser mayor a cero.");
        }
        if ($this->precioUnitario < 0) {
            throw new InvalidArgumentException("El precio unitario no puede ser negativo.");
        }
        if ($this->descuentoMonto < 0) {
            throw new InvalidArgumentException("El monto de descuento no puede ser negativo.");
        }
        if ($this->descuentoMonto > 0 && ($this->descuentoMotivo === null || trim($this->descuentoMotivo) === '')) {
            throw new InvalidArgumentException("Todo descuento mayor a cero exige obligatoriamente un motivo justificado.");
        }
        if ($this->subtotal < 0) {
            throw new InvalidArgumentException("El subtotal no puede ser negativo.");
        }

        // Validación de tipo de línea y sus procedencias
        if ($this->tipoLinea === TipoLineaVenta::ITEM) {
            if ($this->itemComercialId === null || $this->ofertaItemId === null) {
                throw new InvalidArgumentException("Una línea de tipo ITEM exige obligatoriamente item_comercial_id y oferta_item_id.");
            }
            if ($this->paqueteId !== null || $this->ofertaPaqueteId !== null) {
                throw new InvalidArgumentException("Una línea de tipo ITEM no puede asociar paquete_id ni oferta_paquete_id.");
            }
        } elseif ($this->tipoLinea === TipoLineaVenta::PAQUETE) {
            if ($this->paqueteId === null || $this->ofertaPaqueteId === null) {
                throw new InvalidArgumentException("Una línea de tipo PAQUETE exige obligatoriamente paquete_id y oferta_paquete_id.");
            }
            if ($this->itemComercialId !== null || $this->ofertaItemId !== null) {
                throw new InvalidArgumentException("Una línea de tipo PAQUETE no puede asociar item_comercial_id ni oferta_item_id.");
            }
        }
    }

    public function aArreglo(): array
    {
        return [
            'id'                   => $this->id,
            'venta_id'             => $this->ventaId,
            'cotizacion_linea_id'  => $this->cotizacionLineaId,
            'tipo_linea'           => $this->tipoLinea->value,
            'item_comercial_id'    => $this->itemComercialId,
            'paquete_id'           => $this->paqueteId,
            'oferta_item_id'       => $this->ofertaItemId,
            'oferta_paquete_id'    => $this->ofertaPaqueteId,
            'concepto_codigo'      => $this->conceptoCodigo,
            'concepto_nombre'      => $this->conceptoNombre,
            'concepto_descripcion' => $this->conceptoDescripcion,
            'unidad_medida'        => $this->unidadMedida,
            'cantidad'             => $this->cantidad,
            'precio_unitario'      => $this->precioUnitario,
            'descuento_tipo'       => $this->descuentoTipo->value,
            'descuento_valor'      => $this->descuentoValor,
            'descuento_monto'      => $this->descuentoMonto,
            'descuento_motivo'     => $this->descuentoMotivo,
            'subtotal'             => $this->subtotal,
            'moneda'               => $this->moneda,
            'orden'                => $this->orden,
            'notas'                => $this->notas,
            'creado_en'            => $this->creadoEn,
            'componentes'          => array_map(fn($c) => $c instanceof VentaLineaComponente ? $c->aArreglo() : $c, $this->componentes),
        ];
    }
}
