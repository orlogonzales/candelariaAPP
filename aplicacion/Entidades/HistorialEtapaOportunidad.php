<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use InvalidArgumentException;

/**
 * Entidad inmutable append-only para el historial de transiciones de etapas en oportunidades.
 */
class HistorialEtapaOportunidad
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $oportunidadId,
        public readonly ?string $etapaAnterior,
        public readonly string $etapaNueva,
        public readonly ?string $motivo,
        public readonly string $actorTipo,
        public readonly ?int $usuarioId,
        public readonly ?int $actorSistemaId,
        public readonly string $correlacionId,
        public readonly ?string $creadoEn = null
    ) {
        if ($this->actorTipo === 'HUMANO') {
            if ($this->usuarioId === null || $this->actorSistemaId !== null) {
                throw new InvalidArgumentException("Un actor HUMANO en historial de etapas requiere usuarioId no nulo y actorSistemaId nulo.");
            }
        } elseif ($this->actorTipo === 'SISTEMA') {
            if ($this->actorSistemaId === null || $this->usuarioId !== null) {
                throw new InvalidArgumentException("Un actor SISTEMA en historial de etapas requiere actorSistemaId no nulo y usuarioId nulo.");
            }
        } else {
            throw new InvalidArgumentException("Tipo de actor inválido: '{$this->actorTipo}'");
        }

        if (empty($this->correlacionId)) {
            throw new InvalidArgumentException("El correlacionId es mandatorio en historial de etapas.");
        }
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            organizacionId: (int) $datos['organizacion_id'],
            oportunidadId: (int) $datos['oportunidad_id'],
            etapaAnterior: $datos['etapa_anterior'] ?? null,
            etapaNueva: (string) $datos['etapa_nueva'],
            motivo: $datos['motivo'] ?? null,
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
            'id'               => $this->id,
            'organizacion_id'  => $this->organizacionId,
            'oportunidad_id'   => $this->oportunidadId,
            'etapa_anterior'   => $this->etapaAnterior,
            'etapa_nueva'      => $this->etapaNueva,
            'motivo'           => $this->motivo,
            'actor_tipo'       => $this->actorTipo,
            'usuario_id'       => $this->usuarioId,
            'actor_sistema_id' => $this->actorSistemaId,
            'correlacion_id'   => $this->correlacionId,
            'creado_en'        => $this->creadoEn,
        ];
    }
}
