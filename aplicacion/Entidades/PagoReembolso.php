<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Finanzas\EstadoReembolso;
use Aplicacion\Finanzas\MotivoReembolso;

class PagoReembolso
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $pagoId,
        public int $ventaId,
        public string $correlativo,
        public float $montoReembolsado,
        public EstadoReembolso $estado,
        public MotivoReembolso $motivo,
        public string $motivoDetalle,
        public ?string $transaccionReembolsoExternaId = null,
        public int $autorizadoPor = 1,
        public ?string $ejecutadoEn = null,
        public ?string $creadoEn = null,
        public ?string $pagoCorrelativo = null,
        public ?string $ventaCorrelativo = null
    ) {
    }

    public function aArreglo(): array
    {
        return [
            'id'                               => $this->id,
            'organizacion_id'                  => $this->organizacionId,
            'pago_id'                          => $this->pagoId,
            'pago_correlativo'                 => $this->pagoCorrelativo,
            'venta_id'                         => $this->ventaId,
            'venta_correlativo'                => $this->ventaCorrelativo,
            'correlativo'                      => $this->correlativo,
            'monto_reembolsado'                => $this->montoReembolsado,
            'estado'                           => $this->estado->value,
            'motivo'                           => $this->motivo->value,
            'motivo_detalle'                   => $this->motivoDetalle,
            'transaccion_reembolso_externa_id' => $this->transaccionReembolsoExternaId,
            'autorizado_por'                   => $this->autorizadoPor,
            'ejecutado_en'                     => $this->ejecutadoEn,
            'creado_en'                        => $this->creadoEn,
        ];
    }
}
