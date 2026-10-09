<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas\Pasarelas;

/**
 * Value Object inmutable que normaliza el resultado de la recepción
 * y análisis de un webhook proveniente de cualquier pasarela de pago.
 */
class EventoWebhookResultado
{
    public function __construct(
        public readonly bool $esValido,
        public readonly string $tipoEvento,          // 'CARGO_EXITOSO', 'CARGO_FALLIDO', 'REEMBOLSO_EXITOSO', 'OTRO'
        public readonly ?string $transaccionExternaId,
        public readonly ?string $ordenCheckoutId,
        public readonly float $monto,
        public readonly string $moneda,
        public readonly ?string $claveIdempotencia,
        public readonly ?string $tarjetaMarca,
        public readonly ?string $tarjetaUltimosCuatro,
        public readonly ?string $codigoRespuesta,
        public readonly ?string $mensajeRespuesta,
        public readonly array $payloadSanitizado,
        public readonly ?int $ventaId = null,
        public readonly float $comisionPasarela = 0.00
    ) {
    }

    public function __get(string $nombre): mixed
    {
        if ($nombre === 'eventoTipo') {
            return $this->tipoEvento;
        }
        return null;
    }

    public function esPagoExitoso(): bool
    {
        return $this->esValido && in_array($this->tipoEvento, ['CARGO_EXITOSO', 'PAGO_EXITOSO'], true);
    }

    public static function invalido(string $mensaje, array $payloadSanitizado = []): self
    {
        return new self(
            esValido: false,
            tipoEvento: 'EVENTO_INVALIDO',
            transaccionExternaId: null,
            ordenCheckoutId: null,
            monto: 0.00,
            moneda: 'PEN',
            claveIdempotencia: null,
            tarjetaMarca: null,
            tarjetaUltimosCuatro: null,
            codigoRespuesta: 'FIRMA_INVALIDA',
            mensajeRespuesta: $mensaje,
            payloadSanitizado: $payloadSanitizado,
            ventaId: null,
            comisionPasarela: 0.00
        );
    }
}
