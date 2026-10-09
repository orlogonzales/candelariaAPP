<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Ventas\EstadoVenta;
use Aplicacion\Ventas\MotivoAnulacionVenta;
use Aplicacion\Ventas\MotivoCancelacionVenta;
use Aplicacion\Ventas\TipoDescuentoVenta;
use Aplicacion\Ventas\TipoOrigenVenta;
use InvalidArgumentException;

/**
 * Entidad de dominio Venta:
 * Cabecera oficial de ventas comerciales confirmadas con snapshot congelado,
 * procedencia trazable y control de concurrencia optimista.
 */
class Venta
{
    /**
     * @param VentaLinea[] $lineas
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $edicionId,
        public readonly int $clienteId,
        public readonly ?int $cotizacionId,
        public readonly TipoOrigenVenta $origenTipo,
        public readonly string $correlativo,
        public readonly string $fechaVenta,
        public readonly EstadoVenta $estado,
        public readonly string $clienteNombreCompleto,
        public readonly ?string $clienteTipoDocumento,
        public readonly ?string $clienteNumeroDocumento,
        public readonly ?string $clienteTelefono,
        public readonly ?string $clienteEmail,
        public readonly string $moneda,
        public readonly float $subtotal = 0.00,
        public readonly TipoDescuentoVenta $descuentoGlobalTipo = TipoDescuentoVenta::NINGUNO,
        public readonly float $descuentoGlobalValor = 0.00,
        public readonly float $descuentoGlobalMonto = 0.00,
        public readonly ?string $descuentoGlobalMotivo = null,
        public readonly float $descuentoLineasTotal = 0.00,
        public readonly float $total = 0.00,
        public readonly float $montoPagado = 0.00,
        public readonly float $saldoPendiente = 0.00,
        public readonly ?string $estadoFinanciero = 'NO_PAGADA',
        public readonly ?string $terminosCondiciones = null,
        public readonly ?string $notasComerciales = null,
        public readonly ?MotivoCancelacionVenta $motivoCancelacion = null,
        public readonly ?string $motivoCancelacionDetalle = null,
        public readonly ?MotivoAnulacionVenta $motivoAnulacion = null,
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
        if (trim($this->correlativo) === '') {
            throw new InvalidArgumentException("El correlativo formal de la venta no puede estar vacío.");
        }
        if (trim($this->clienteNombreCompleto) === '') {
            throw new InvalidArgumentException("El nombre del cliente en el snapshot comercial no puede estar vacío.");
        }
        if (trim($this->moneda) === '' || strlen($this->moneda) !== 3) {
            throw new InvalidArgumentException("La moneda de la venta debe ser un código ISO 4217 de 3 caracteres.");
        }
        if ($this->subtotal < 0 || $this->total < 0) {
            throw new InvalidArgumentException("El subtotal y total de la venta no pueden ser negativos.");
        }
        if ($this->total > $this->subtotal) {
            throw new InvalidArgumentException("El total de la venta ({$this->total}) no puede ser mayor que el subtotal ({$this->subtotal}).");
        }

        // Invariante de origen
        if ($this->origenTipo === TipoOrigenVenta::COTIZACION && $this->cotizacionId === null) {
            throw new InvalidArgumentException("Una venta con origen COTIZACION exige obligatoriamente cotizacion_id.");
        }
        if ($this->origenTipo === TipoOrigenVenta::DIRECTA && $this->cotizacionId !== null) {
            throw new InvalidArgumentException("Una venta con origen DIRECTA no puede tener cotizacion_id.");
        }

        // Invariante de descuento global
        if ($this->descuentoGlobalMonto > 0 && ($this->descuentoGlobalMotivo === null || trim($this->descuentoGlobalMotivo) === '')) {
            throw new InvalidArgumentException("Todo descuento global mayor a cero exige obligatoriamente un motivo justificado.");
        }

        // Invariante de cancelación
        if ($this->estado === EstadoVenta::CANCELADA) {
            if ($this->motivoCancelacion === null) {
                throw new InvalidArgumentException("Una venta cancelada exige obligatoriamente un motivo_cancelacion.");
            }
            if ($this->motivoCancelacion === MotivoCancelacionVenta::OTRO && ($this->motivoCancelacionDetalle === null || trim($this->motivoCancelacionDetalle) === '')) {
                throw new InvalidArgumentException("El motivo de cancelación 'OTRO' exige un detalle explicativo.");
            }
        } else {
            if ($this->motivoCancelacion !== null || $this->motivoCancelacionDetalle !== null) {
                throw new InvalidArgumentException("Una venta no cancelada no puede contener motivo_cancelacion.");
            }
        }

        // Invariante de anulación
        if ($this->estado === EstadoVenta::ANULADA) {
            if ($this->motivoAnulacion === null) {
                throw new InvalidArgumentException("Una venta anulada exige obligatoriamente un motivo_anulacion.");
            }
            if ($this->motivoAnulacion === MotivoAnulacionVenta::OTRO && ($this->motivoAnulacionDetalle === null || trim($this->motivoAnulacionDetalle) === '')) {
                throw new InvalidArgumentException("El motivo de anulación 'OTRO' exige un detalle explicativo.");
            }
        } else {
            if ($this->motivoAnulacion !== null || $this->motivoAnulacionDetalle !== null) {
                throw new InvalidArgumentException("Una venta no anulada no puede contener motivo_anulacion.");
            }
        }
    }

    public function aArreglo(): array
    {
        return [
            'id'                        => $this->id,
            'organizacion_id'           => $this->organizacionId,
            'edicion_id'                => $this->edicionId,
            'cliente_id'                => $this->clienteId,
            'cotizacion_id'             => $this->cotizacionId,
            'origen_tipo'               => $this->origenTipo->value,
            'correlativo'               => $this->correlativo,
            'fecha_venta'               => $this->fechaVenta,
            'estado'                    => $this->estado->value,
            'cliente_nombre_completo'   => $this->clienteNombreCompleto,
            'cliente_tipo_documento'    => $this->clienteTipoDocumento,
            'cliente_numero_documento'  => $this->clienteNumeroDocumento,
            'cliente_telefono'          => $this->clienteTelefono,
            'cliente_email'             => $this->clienteEmail,
            'moneda'                    => $this->moneda,
            'subtotal'                  => $this->subtotal,
            'descuento_global_tipo'     => $this->descuentoGlobalTipo->value,
            'descuento_global_valor'    => $this->descuentoGlobalValor,
            'descuento_global_monto'    => $this->descuentoGlobalMonto,
            'descuento_global_motivo'   => $this->descuentoGlobalMotivo,
            'descuento_lineas_total'    => $this->descuentoLineasTotal,
            'total'                     => $this->total,
            'monto_pagado'              => $this->montoPagado,
            'saldo_pendiente'           => $this->saldoPendiente,
            'estado_financiero'         => $this->estadoFinanciero,
            'terminos_condiciones'      => $this->terminosCondiciones,
            'notas_comerciales'         => $this->notasComerciales,
            'motivo_cancelacion'        => $this->motivoCancelacion?->value,
            'motivo_cancelacion_detalle'=> $this->motivoCancelacionDetalle,
            'motivo_anulacion'          => $this->motivoAnulacion?->value,
            'motivo_anulacion_detalle'  => $this->motivoAnulacionDetalle,
            'version_bloqueo'           => $this->versionBloqueo,
            'creado_por'                => $this->creadoPor,
            'creado_en'                 => $this->creadoEn,
            'actualizado_en'            => $this->actualizadoEn,
            'lineas'                    => array_map(fn($l) => $l instanceof VentaLinea ? $l->aArreglo() : $l, $this->lineas),
        ];
    }
}
