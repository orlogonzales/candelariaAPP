<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Finanzas\EstadoIntentoPasarela;

class PagoIntentoPasarela
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public ?int $pagoId,
        public int $ventaId,
        public int $organizacionPasarelaId,
        public ?string $transaccionExternaId,
        public ?string $ordenCheckoutId,
        public float $monto,
        public string $moneda,
        public EstadoIntentoPasarela $estadoIntento,
        public ?string $codigoRespuestaPasarela = null,
        public ?string $mensajeRespuestaPasarela = null,
        public ?array $payloadSolicitudSanitizado = null,
        public ?array $payloadRespuestaSanitizado = null,
        public ?string $tarjetaMarca = null,
        public ?string $tarjetaUltimosCuatro = null,
        public ?string $ipOrigen = null,
        public ?string $firmaWebhookRecibida = null,
        public ?string $claveIdempotenciaWebhook = null,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null
    ) {
    }

    public function aArreglo(): array
    {
        return [
            'id'                                => $this->id,
            'organizacion_id'                   => $this->organizacionId,
            'pago_id'                           => $this->pagoId,
            'venta_id'                          => $this->ventaId,
            'organizacion_pasarela_id'          => $this->organizacionPasarelaId,
            'transaccion_externa_id'            => $this->transaccionExternaId,
            'orden_checkout_id'                 => $this->ordenCheckoutId,
            'monto'                             => $this->monto,
            'moneda'                            => $this->moneda,
            'estado_intento'                    => $this->estadoIntento->value,
            'codigo_respuesta_pasarela'         => $this->codigoRespuestaPasarela,
            'mensaje_respuesta_pasarela'        => $this->mensajeRespuestaPasarela,
            'payload_solicitud_sanitizado_json' => $this->payloadSolicitudSanitizado,
            'payload_respuesta_sanitizado_json' => $this->payloadRespuestaSanitizado,
            'tarjeta_marca'                     => $this->tarjetaMarca,
            'tarjeta_ultimos_cuatro'            => $this->tarjetaUltimosCuatro,
            'ip_origen'                         => $this->ipOrigen,
            'clave_idempotencia_webhook'        => $this->claveIdempotenciaWebhook,
            'creado_en'                         => $this->creadoEn,
            'actualizado_en'                    => $this->actualizadoEn,
        ];
    }
}
