<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Cotizaciones\TipoDescuentoCotizacion;
use Aplicacion\Cotizaciones\TipoLineaCotizacion;
use InvalidArgumentException;

/**
 * Entidad de dominio CotizacionLinea:
 * Detalle comercial con procedencia estricta, snapshot de catálogo y control de descuentos.
 */
class CotizacionLinea
{
    /**
     * @param CotizacionLineaComponente[] $componentes
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $cotizacionId,
        public readonly TipoLineaCotizacion $tipoLinea,
        public readonly ?int $itemComercialId,
        public readonly ?int $paqueteId,
        public readonly ?int $ofertaItemId,
        public readonly ?int $ofertaPaqueteId,
        public readonly string $conceptoCodigo,
        public readonly string $conceptoNombre,
        public readonly ?string $conceptoDescripcion,
        public readonly string $unidadMedida,
        public readonly string $moneda,
        public readonly float $cantidad = 1.00,
        public readonly float $precioUnitario = 0.00,
        public readonly TipoDescuentoCotizacion $descuentoTipo = TipoDescuentoCotizacion::NINGUNO,
        public readonly float $descuentoValor = 0.00,
        public readonly float $descuentoMonto = 0.00,
        public readonly ?string $descuentoMotivo = null,
        public readonly float $subtotal = 0.00,
        public readonly int $orden = 0,
        public readonly ?string $notas = null,
        public readonly ?string $creadoEn = null,
        public readonly array $componentes = []
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->cotizacionId < 0) {
            throw new InvalidArgumentException("El ID de cotización debe ser válido.");
        }

        if ($this->tipoLinea === TipoLineaCotizacion::ITEM) {
            if ($this->itemComercialId === null || $this->itemComercialId <= 0) {
                throw new InvalidArgumentException("Una línea de tipo ITEM exige un itemComercialId válido.");
            }
            if ($this->ofertaItemId === null || $this->ofertaItemId <= 0) {
                throw new InvalidArgumentException("Una línea de tipo ITEM exige un ofertaItemId válido para trazabilidad de procedencia.");
            }
            if ($this->paqueteId !== null || $this->ofertaPaqueteId !== null) {
                throw new InvalidArgumentException("Una línea de tipo ITEM no puede asociar identificadores de paquete u oferta de paquete.");
            }
        } elseif ($this->tipoLinea === TipoLineaCotizacion::PAQUETE) {
            if ($this->paqueteId === null || $this->paqueteId <= 0) {
                throw new InvalidArgumentException("Una línea de tipo PAQUETE exige un paqueteId válido.");
            }
            if ($this->ofertaPaqueteId === null || $this->ofertaPaqueteId <= 0) {
                throw new InvalidArgumentException("Una línea de tipo PAQUETE exige un ofertaPaqueteId válido para trazabilidad de procedencia.");
            }
            if ($this->itemComercialId !== null || $this->ofertaItemId !== null) {
                throw new InvalidArgumentException("Una línea de tipo PAQUETE no puede asociar identificadores de ítem u oferta de ítem.");
            }
        }

        if (trim($this->conceptoCodigo) === '') {
            throw new InvalidArgumentException("El código del concepto cotizado no puede estar vacío.");
        }
        if (trim($this->conceptoNombre) === '') {
            throw new InvalidArgumentException("El nombre del concepto cotizado no puede estar vacío.");
        }
        if ($this->cantidad <= 0) {
            throw new InvalidArgumentException("La cantidad cotizada debe ser mayor a cero.");
        }
        if ($this->precioUnitario < 0) {
            throw new InvalidArgumentException("El precio unitario no puede ser negativo.");
        }
        if ($this->descuentoMonto < 0) {
            throw new InvalidArgumentException("El monto de descuento no puede ser negativo.");
        }
        $bruto = round($this->cantidad * $this->precioUnitario, 2);
        if ($this->descuentoMonto > $bruto) {
            throw new InvalidArgumentException("El descuento de la línea ({$this->descuentoMonto}) no puede exceder el importe bruto ({$bruto}).");
        }
        if ($this->descuentoMonto > 0) {
            if ($this->descuentoTipo === TipoDescuentoCotizacion::NINGUNO) {
                throw new InvalidArgumentException("Un descuento mayor a cero exige especificar el tipo (PORCENTAJE o MONTO_FIJO).");
            }
            if ($this->descuentoMotivo === null || trim($this->descuentoMotivo) === '') {
                throw new InvalidArgumentException("Todo descuento aplicado a una línea exige un motivo obligatorio.");
            }
        }
        if ($this->subtotal < 0) {
            throw new InvalidArgumentException("El subtotal de la línea no puede ser negativo.");
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'cotizacion_id' => $this->cotizacionId,
            'tipo_linea' => $this->tipoLinea->value,
            'item_comercial_id' => $this->itemComercialId,
            'paquete_id' => $this->paqueteId,
            'oferta_item_id' => $this->ofertaItemId,
            'oferta_paquete_id' => $this->ofertaPaqueteId,
            'concepto_codigo' => $this->conceptoCodigo,
            'concepto_nombre' => $this->conceptoNombre,
            'concepto_descripcion' => $this->conceptoDescripcion,
            'unidad_medida' => $this->unidadMedida,
            'cantidad' => $this->cantidad,
            'precio_unitario' => $this->precioUnitario,
            'descuento_tipo' => $this->descuentoTipo->value,
            'descuento_valor' => $this->descuentoValor,
            'descuento_monto' => $this->descuentoMonto,
            'descuento_motivo' => $this->descuentoMotivo,
            'subtotal' => $this->subtotal,
            'moneda' => $this->moneda,
            'orden' => $this->orden,
            'notas' => $this->notas,
            'creado_en' => $this->creadoEn,
            'componentes' => array_map(fn($c) => $c instanceof CotizacionLineaComponente ? $c->toArray() : $c, $this->componentes),
        ];
    }

    public function aArreglo(): array
    {
        return $this->toArray();
    }
}
