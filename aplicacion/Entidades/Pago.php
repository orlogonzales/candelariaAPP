<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Finanzas\AsumeComisionPasarela;
use Aplicacion\Finanzas\EstadoPago;
use Aplicacion\Finanzas\MetodoPago;

class Pago
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $edicionId,
        public int $ventaId,
        public string $correlativo,
        public MetodoPago $metodoPago,
        public EstadoPago $estado,
        public string $moneda,
        public float $montoCobradoCliente,
        public float $comisionPorcentajeAplicada,
        public float $comisionFijaAplicada,
        public float $comisionPasarela,
        public float $montoNetoRecibido,
        public float $montoAplicadoVenta,
        public float $montoExcedente,
        public AsumeComisionPasarela $comisionAsumidaPor,
        public float $montoReembolsadoAcumulado,
        public string $fechaPago,
        public ?int $cuentaBancariaId = null,
        public ?int $organizacionPasarelaId = null,
        public ?string $numeroOperacionBancaria = null,
        public ?string $boucherComprobanteUrl = null,
        public ?string $notasOperativas = null,
        public ?string $claveIdempotencia = null,
        public int $versionBloqueo = 1,
        public ?int $verificadoPor = null,
        public ?string $verificadoEn = null,
        public int $creadoPor = 1,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null,
        public ?string $ventaCorrelativo = null,
        public ?string $bancoNombre = null,
        public ?string $pasarelaNombre = null
    ) {
    }

    public function montoNetoAporteVenta(): float
    {
        return max(0.0, round($this->montoAplicadoVenta - $this->montoReembolsadoAcumulado, 2));
    }

    public function aArreglo(): array
    {
        return [
            'id'                           => $this->id,
            'organizacion_id'              => $this->organizacionId,
            'edicion_id'                   => $this->edicionId,
            'venta_id'                     => $this->ventaId,
            'venta_correlativo'            => $this->ventaCorrelativo,
            'correlativo'                  => $this->correlativo,
            'metodo_pago'                  => $this->metodoPago->value,
            'estado'                       => $this->estado->value,
            'moneda'                       => $this->moneda,
            'monto_cobrado_cliente'        => $this->montoCobradoCliente,
            'comision_porcentaje_aplicada' => $this->comisionPorcentajeAplicada,
            'comision_fija_aplicada'       => $this->comisionFijaAplicada,
            'comision_pasarela'            => $this->comisionPasarela,
            'monto_neto_recibido'          => $this->montoNetoRecibido,
            'monto_aplicado_venta'         => $this->montoAplicadoVenta,
            'monto_excedente'              => $this->montoExcedente,
            'comision_asumida_por'         => $this->comisionAsumidaPor->value,
            'monto_reembolsado_acumulado'  => $this->montoReembolsadoAcumulado,
            'monto_neto_aporte_venta'      => $this->montoNetoAporteVenta(),
            'fecha_pago'                   => $this->fechaPago,
            'cuenta_bancaria_id'           => $this->cuentaBancariaId,
            'banco_nombre'                 => $this->bancoNombre,
            'organizacion_pasarela_id'     => $this->organizacionPasarelaId,
            'pasarela_nombre'              => $this->pasarelaNombre,
            'numero_operacion_bancaria'    => $this->numeroOperacionBancaria,
            'boucher_comprobante_url'      => $this->boucherComprobanteUrl,
            'notas_operativas'             => $this->notasOperativas,
            'clave_idempotencia'           => $this->claveIdempotencia,
            'version_bloqueo'              => $this->versionBloqueo,
            'verificado_por'               => $this->verificadoPor,
            'verificado_en'                => $this->verificadoEn,
            'creado_por'                   => $this->creadoPor,
            'creado_en'                    => $this->creadoEn,
            'actualizado_en'               => $this->actualizadoEn,
        ];
    }
}
