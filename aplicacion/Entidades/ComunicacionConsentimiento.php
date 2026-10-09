<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Comunicaciones\EstadoConsentimiento;
use Aplicacion\Comunicaciones\FinalidadConsentimiento;

class ComunicacionConsentimiento
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $clienteId,
        public string $canal,
        public string $telefonoDestino,
        public FinalidadConsentimiento $finalidad,
        public EstadoConsentimiento $estado,
        public string $origenEvidencia,
        public string $correlacionId,
        public ?string $textoClausulaAceptada = null,
        public ?string $direccionIpRegistro = null,
        public string $actorTipo = 'HUMANO',
        public ?int $usuarioId = null,
        public ?string $creadoEn = null,
        public ?string $revocadoEn = null
    ) {}

    public function aArreglo(): array
    {
        return [
            'id'                      => $this->id,
            'organizacion_id'         => $this->organizacionId,
            'cliente_id'              => $this->clienteId,
            'canal'                   => $this->canal,
            'telefono_destino'        => $this->telefonoDestino,
            'finalidad'               => $this->finalidad->value,
            'estado'                  => $this->estado->value,
            'origen_evidencia'        => $this->origenEvidencia,
            'texto_clausula_aceptada' => $this->textoClausulaAceptada,
            'direccion_ip_registro'   => $this->direccionIpRegistro,
            'actor_tipo'              => $this->actorTipo,
            'usuario_id'              => $this->usuarioId,
            'correlacion_id'          => $this->correlacionId,
            'creado_en'               => $this->creadoEn,
            'revocado_en'             => $this->revocadoEn,
        ];
    }
}
