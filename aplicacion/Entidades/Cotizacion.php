<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Cotizaciones\EstadoCotizacion;
use Aplicacion\Cotizaciones\MotivoAnulacionCotizacion;
use Aplicacion\Cotizaciones\MotivoRechazoCotizacion;
use Aplicacion\Cotizaciones\TipoDescuentoCotizacion;
use InvalidArgumentException;

/**
 * Entidad de dominio Cotizacion:
 * Cabecera oficial de propuestas económicas, presupuestos y revisiones comerciales.
 */
class Cotizacion
{
    /**
     * @param CotizacionLinea[] $lineas
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $edicionId,
        public readonly int $clienteId,
        public readonly string $moneda,
        public readonly ?int $oportunidadId = null,
        public readonly ?string $correlativo = null,
        public readonly ?string $correlativoBase = null,
        public readonly int $versionNumero = 1,
        public readonly ?int $cotizacionOrigenId = null,
        public readonly ?int $cotizacionRaizId = null,
        public readonly string $titulo = 'Cotización Comercial',
        public readonly EstadoCotizacion $estado = EstadoCotizacion::BORRADOR,
        public readonly ?string $fechaEmision = null,
        public readonly ?string $validoHasta = null,
        public readonly float $subtotal = 0.00,
        public readonly TipoDescuentoCotizacion $descuentoGlobalTipo = TipoDescuentoCotizacion::NINGUNO,
        public readonly float $descuentoGlobalValor = 0.00,
        public readonly float $descuentoGlobalMonto = 0.00,
        public readonly ?string $descuentoGlobalMotivo = null,
        public readonly float $descuentoLineasTotal = 0.00,
        public readonly float $total = 0.00,
        public readonly ?string $terminosCondiciones = null,
        public readonly ?string $notasInternas = null,
        public readonly ?MotivoRechazoCotizacion $motivoRechazo = null,
        public readonly ?string $motivoRechazoDetalle = null,
        public readonly ?MotivoAnulacionCotizacion $motivoAnulacion = null,
        public readonly ?string $motivoAnulacionDetalle = null,
        public readonly int $versionBloqueo = 1,
        public readonly int $creadoPor = 1,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null,
        public readonly array $lineas = []
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->organizacionId <= 0) {
            throw new InvalidArgumentException("El ID de organización debe ser un entero positivo.");
        }
        if ($this->edicionId <= 0) {
            throw new InvalidArgumentException("El ID de edición es obligatorio y debe ser un entero positivo.");
        }
        if ($this->clienteId <= 0) {
            throw new InvalidArgumentException("El ID de cliente es obligatorio y debe ser un entero positivo.");
        }
        if ($this->creadoPor <= 0) {
            throw new InvalidArgumentException("El usuario creador debe ser un entero positivo.");
        }
        if ($this->versionNumero < 1) {
            throw new InvalidArgumentException("El número de versión de la cotización debe ser al menos 1.");
        }
        if (trim($this->titulo) === '') {
            throw new InvalidArgumentException("El título de la cotización no puede estar vacío.");
        }

        // Reglas de estado y correlativo formal
        if ($this->estado === EstadoCotizacion::BORRADOR) {
            if ($this->correlativo !== null) {
                throw new InvalidArgumentException("Una cotización en estado BORRADOR no puede poseer correlativo formal.");
            }
            if ($this->fechaEmision !== null) {
                throw new InvalidArgumentException("Una cotización en estado BORRADOR no puede registrar fecha de emisión.");
            }
            if ($this->validoHasta !== null) {
                throw new InvalidArgumentException("Una cotización en estado BORRADOR no puede registrar fecha de vigencia.");
            }
        } else {
            if ($this->correlativo === null || trim($this->correlativo) === '') {
                throw new InvalidArgumentException("Toda cotización no borrador exige un correlativo formal asignado.");
            }
            if ($this->fechaEmision === null) {
                throw new InvalidArgumentException("Toda cotización no borrador exige una fecha de emisión formal.");
            }
            if ($this->validoHasta === null) {
                throw new InvalidArgumentException("Toda cotización no borrador exige una fecha límite de validez.");
            }
        }

        if ($this->fechaEmision !== null && $this->validoHasta !== null && $this->validoHasta < $this->fechaEmision) {
            throw new InvalidArgumentException("La fecha de vigencia ({$this->validoHasta}) no puede ser anterior a la fecha de emisión ({$this->fechaEmision}).");
        }

        if ($this->subtotal < 0) {
            throw new InvalidArgumentException("El subtotal no puede ser negativo.");
        }
        if ($this->total < 0) {
            throw new InvalidArgumentException("El total neto no puede ser negativo.");
        }
        if ($this->total > $this->subtotal) {
            throw new InvalidArgumentException("El total neto ({$this->total}) no puede exceder el subtotal ({$this->subtotal}).");
        }

        if ($this->descuentoGlobalMonto < 0) {
            throw new InvalidArgumentException("El monto de descuento global no puede ser negativo.");
        }
        if ($this->descuentoGlobalMonto > 0) {
            if ($this->descuentoGlobalTipo === TipoDescuentoCotizacion::NINGUNO) {
                throw new InvalidArgumentException("Un descuento global mayor a cero exige especificar su tipo.");
            }
            if ($this->descuentoGlobalMotivo === null || trim($this->descuentoGlobalMotivo) === '') {
                throw new InvalidArgumentException("Todo descuento global aplicado exige un motivo obligatorio.");
            }
        }

        if ($this->estado === EstadoCotizacion::RECHAZADA) {
            if ($this->motivoRechazo === null) {
                throw new InvalidArgumentException("Una cotización rechazada exige un motivo de rechazo formal.");
            }
            if ($this->motivoRechazo === MotivoRechazoCotizacion::OTRO && ($this->motivoRechazoDetalle === null || trim($this->motivoRechazoDetalle) === '')) {
                throw new InvalidArgumentException("El motivo de rechazo 'OTRO' exige una explicación detallada.");
            }
        } else {
            if ($this->motivoRechazo !== null || $this->motivoRechazoDetalle !== null) {
                throw new InvalidArgumentException("Solo las cotizaciones rechazadas pueden registrar motivos de rechazo.");
            }
        }

        if ($this->estado === EstadoCotizacion::ANULADA) {
            if ($this->motivoAnulacion === null) {
                throw new InvalidArgumentException("Una cotización anulada exige un motivo de anulación formal.");
            }
            if ($this->motivoAnulacion === MotivoAnulacionCotizacion::OTRO && ($this->motivoAnulacionDetalle === null || trim($this->motivoAnulacionDetalle) === '')) {
                throw new InvalidArgumentException("El motivo de anulación 'OTRO' exige una explicación detallada.");
            }
        } else {
            if ($this->motivoAnulacion !== null || $this->motivoAnulacionDetalle !== null) {
                throw new InvalidArgumentException("Solo las cotizaciones anuladas pueden registrar motivos de anulación.");
            }
        }
    }

    /**
     * Evaluación de vencimiento en lectura: no muta la base de datos.
     */
    public function estaVencidaEfectiva(?string $fechaReferencia = null): bool
    {
        if ($this->estado !== EstadoCotizacion::EMITIDA) {
            return false;
        }
        $ref = $fechaReferencia ?? date('Y-m-d');
        return $this->validoHasta !== null && $this->validoHasta < $ref;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizacion_id' => $this->organizacionId,
            'edicion_id' => $this->edicionId,
            'cliente_id' => $this->clienteId,
            'oportunidad_id' => $this->oportunidadId,
            'correlativo' => $this->correlativo,
            'correlativo_base' => $this->correlativoBase,
            'version_numero' => $this->versionNumero,
            'cotizacion_origen_id' => $this->cotizacionOrigenId,
            'cotizacion_raiz_id' => $this->cotizacionRaizId,
            'titulo' => $this->titulo,
            'estado' => $this->estado->value,
            'fecha_emision' => $this->fechaEmision,
            'valido_hasta' => $this->validoHasta,
            'moneda' => $this->moneda,
            'subtotal' => $this->subtotal,
            'descuento_global_tipo' => $this->descuentoGlobalTipo->value,
            'descuento_global_valor' => $this->descuentoGlobalValor,
            'descuento_global_monto' => $this->descuentoGlobalMonto,
            'descuento_global_motivo' => $this->descuentoGlobalMotivo,
            'descuento_lineas_total' => $this->descuentoLineasTotal,
            'total' => $this->total,
            'terminos_condiciones' => $this->terminosCondiciones,
            'notas_internas' => $this->notasInternas,
            'motivo_rechazo' => $this->motivoRechazo?->value,
            'motivo_rechazo_detalle' => $this->motivoRechazoDetalle,
            'motivo_anulacion' => $this->motivoAnulacion?->value,
            'motivo_anulacion_detalle' => $this->motivoAnulacionDetalle,
            'version_bloqueo' => $this->versionBloqueo,
            'creado_por' => $this->creadoPor,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'vencida_efectiva' => $this->estaVencidaEfectiva(),
            'lineas' => array_map(fn($l) => $l instanceof CotizacionLinea ? $l->toArray() : $l, $this->lineas),
        ];
    }

    public function aArreglo(): array
    {
        return $this->toArray();
    }
}
