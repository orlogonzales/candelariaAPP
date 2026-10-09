<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones\Proveedores;

class ResultadoDespachoWhatsApp
{
    public function __construct(
        public readonly bool $exito,
        public readonly ?string $wamid = null,
        public readonly int $httpStatus = 200,
        public readonly ?int $errorCode = null,
        public readonly ?int $errorSubcode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $esReintentable = false,
        public readonly int $latenciaMs = 0,
        public readonly array $rawResponse = []
    ) {}

    public static function exitoso(string $wamid, int $latenciaMs = 10, array $raw = []): self
    {
        return new self(
            exito: true,
            wamid: $wamid,
            httpStatus: 200,
            latenciaMs: $latenciaMs,
            rawResponse: $raw
        );
    }

    public static function fallido(
        int $httpStatus,
        ?int $errorCode,
        string $errorMessage,
        bool $esReintentable,
        int $latenciaMs = 20,
        ?int $errorSubcode = null,
        array $raw = []
    ): self {
        return new self(
            exito: false,
            httpStatus: $httpStatus,
            errorCode: $errorCode,
            errorSubcode: $errorSubcode,
            errorMessage: $errorMessage,
            esReintentable: $esReintentable,
            latenciaMs: $latenciaMs,
            rawResponse: $raw
        );
    }
}
