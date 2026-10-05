<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Crm\DireccionInteraccion;
use Aplicacion\Crm\TipoInteraccion;
use InvalidArgumentException;

/**
 * Entidad inmutable append-only para interacciones y notas comerciales en CRM.
 */
class InteraccionCrm
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $clienteId,
        public readonly ?int $oportunidadId,
        public readonly int $canalId,
        public readonly TipoInteraccion $tipo,
        public readonly DireccionInteraccion $direccion,
        public readonly string $resumen,
        public readonly ?string $detalle,
        public readonly string $actorTipo,
        public readonly ?int $usuarioId,
        public readonly ?int $actorSistemaId,
        public readonly string $correlacionId,
        public readonly ?string $creadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if (trim($this->resumen) === '') {
            throw new InvalidArgumentException("El resumen de la interacción no puede estar vacío.");
        }

        if (mb_strlen($this->resumen) > 255) {
            throw new InvalidArgumentException("El resumen de la interacción no puede exceder 255 caracteres.");
        }

        if ($this->tipo === TipoInteraccion::NOTA_INTERNA && $this->direccion !== DireccionInteraccion::INTERNA) {
            throw new InvalidArgumentException("Una interacción de tipo NOTA_INTERNA requiere obligatoriamente dirección INTERNA.");
        }

        if ($this->actorTipo === 'HUMANO') {
            if ($this->usuarioId === null || $this->actorSistemaId !== null) {
                throw new InvalidArgumentException("Un actor HUMANO en interacción requiere usuarioId y no permite actorSistemaId.");
            }
        } elseif ($this->actorTipo === 'SISTEMA') {
            if ($this->actorSistemaId === null || $this->usuarioId !== null) {
                throw new InvalidArgumentException("Un actor SISTEMA en interacción requiere actorSistemaId y no permite usuarioId.");
            }
        } else {
            throw new InvalidArgumentException("Tipo de actor inválido: '{$this->actorTipo}'");
        }

        if (empty($this->correlacionId)) {
            throw new InvalidArgumentException("El correlacionId es mandatorio en interacciones comerciales.");
        }
    }

    public static function desdeArreglo(array $datos): self
    {
        $tipo = $datos['tipo'] instanceof TipoInteraccion
            ? $datos['tipo']
            : TipoInteraccion::desdeCadena((string) $datos['tipo']);

        $direccion = $datos['direccion'] instanceof DireccionInteraccion
            ? $datos['direccion']
            : DireccionInteraccion::desdeCadena((string) $datos['direccion']);

        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            organizacionId: (int) $datos['organizacion_id'],
            clienteId: (int) $datos['cliente_id'],
            oportunidadId: isset($datos['oportunidad_id']) && $datos['oportunidad_id'] !== null ? (int) $datos['oportunidad_id'] : null,
            canalId: (int) $datos['canal_id'],
            tipo: $tipo,
            direccion: $direccion,
            resumen: (string) $datos['resumen'],
            detalle: $datos['detalle'] ?? null,
            actorTipo: (string) $datos['actor_tipo'],
            usuarioId: isset($datos['usuario_id']) && $datos['usuario_id'] !== null ? (int) $datos['usuario_id'] : null,
            actorSistemaId: isset($datos['actor_sistema_id']) && $datos['actor_sistema_id'] !== null ? (int) $datos['actor_sistema_id'] : null,
            correlacionId: (string) $datos['correlacion_id'],
            creadoEn: $datos['creado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                 => $this->id,
            'organizacion_id'    => $this->organizacionId,
            'cliente_id'         => $this->clienteId,
            'oportunidad_id'     => $this->oportunidadId,
            'canal_id'           => $this->canalId,
            'tipo'               => $this->tipo->value,
            'tipo_etiqueta'      => $this->tipo->etiqueta(),
            'direccion'          => $this->direccion->value,
            'direccion_etiqueta' => $this->direccion->etiqueta(),
            'resumen'            => $this->resumen,
            'detalle'            => $this->detalle,
            'actor_tipo'         => $this->actorTipo,
            'usuario_id'         => $this->usuarioId,
            'actor_sistema_id'   => $this->actorSistemaId,
            'correlacion_id'     => $this->correlacionId,
            'creado_en'          => $this->creadoEn,
        ];
    }
}
