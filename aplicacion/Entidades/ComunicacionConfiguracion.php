<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Comunicaciones\ModoComunicacion;

class ComunicacionConfiguracion
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $proveedorId,
        public string $proveedorCodigo,
        public ModoComunicacion $modo,
        public string $numeroTelefonoIdentificador,
        public string $webhookVerifyTokenHash,
        public ?string $metaPhoneNumberId = null,
        public ?string $metaWabaId = null,
        public ?string $metaAppId = null,
        public ?string $tokenAccesoCifrado = null,
        public ?string $webhookSecretCifrado = null,
        public int $limiteMensajesPorSegundo = 10,
        public float $presupuestoMensualLimiteUsd = 50.00,
        public float $gastoAcumuladoMesActualUsd = 0.00,
        public bool $activo = true,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null
    ) {}

    public function presupuestoExcedido(float $montoAdicionalUsd = 0.00): bool
    {
        if ($this->presupuestoMensualLimiteUsd <= 0.00) {
            return false;
        }

        return ($this->gastoAcumuladoMesActualUsd + $montoAdicionalUsd) > $this->presupuestoMensualLimiteUsd;
    }

    public function aArreglo(): array
    {
        return [
            'id'                             => $this->id,
            'organizacion_id'                => $this->organizacionId,
            'proveedor_id'                   => $this->proveedorId,
            'proveedor_codigo'               => $this->proveedorCodigo,
            'modo'                           => $this->modo->value,
            'numero_telefono_identificador'  => $this->numeroTelefonoIdentificador,
            'meta_phone_number_id'           => $this->metaPhoneNumberId,
            'meta_waba_id'                   => $this->metaWabaId,
            'meta_app_id'                    => $this->metaAppId,
            'limite_mensajes_por_segundo'    => $this->limiteMensajesPorSegundo,
            'presupuesto_mensual_limite_usd' => $this->presupuestoMensualLimiteUsd,
            'gasto_acumulado_mes_actual_usd' => $this->gastoAcumuladoMesActualUsd,
            'presupuesto_excedido'           => $this->presupuestoExcedido(),
            'activo'                         => $this->activo,
            'creado_en'                      => $this->creadoEn,
            'actualizado_en'                 => $this->actualizadoEn,
        ];
    }
}
