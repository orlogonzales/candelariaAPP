<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

class PasarelaPago
{
    public function __construct(
        public ?int $id,
        public string $codigo,
        public string $nombre,
        public ?string $descripcion,
        public string $tipoIntegracion,
        public string $protocoloWebhook,
        public bool $soportaReembolsos,
        public bool $activo,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null
    ) {
    }

    public function aArreglo(): array
    {
        return [
            'id'                 => $this->id,
            'codigo'             => $this->codigo,
            'nombre'             => $this->nombre,
            'descripcion'        => $this->descripcion,
            'tipo_integracion'   => $this->tipoIntegracion,
            'protocolo_webhook'  => $this->protocoloWebhook,
            'soporta_reembolsos' => $this->soportaReembolsos,
            'activo'             => $this->activo,
            'creado_en'          => $this->creadoEn,
            'actualizado_en'     => $this->actualizadoEn,
        ];
    }
}
