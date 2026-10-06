<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use InvalidArgumentException;

/**
 * Entidad de dominio HistorialTarifaPaquete:
 * Registro append-only inmutable de transiciones y cambios de precio en tarifas de paquetes.
 */
class HistorialTarifaPaquete
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tarifaPaqueteId,
        public readonly float $precioAnterior,
        public readonly float $precioNuevo,
        public readonly string $moneda,
        public readonly ?string $motivo,
        public readonly string $actorTipo,
        public readonly ?int $usuarioId,
        public readonly ?int $actorSistemaId,
        public readonly ?int $canalId,
        public readonly string $correlacionId,
        public readonly ?string $creadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->tarifaPaqueteId <= 0) {
            throw new InvalidArgumentException("El ID de tarifa de paquete debe ser un entero positivo.");
        }

        if ($this->precioAnterior < 0.0 || $this->precioNuevo < 0.0) {
            throw new InvalidArgumentException("Los precios en el historial no pueden ser negativos.");
        }

        if (!in_array($this->actorTipo, ['HUMANO', 'SISTEMA'], true)) {
            throw new InvalidArgumentException("El actor_tipo debe ser HUMANO o SISTEMA.");
        }

        if ($this->actorTipo === 'HUMANO' && ($this->usuarioId === null || $this->actorSistemaId !== null)) {
            throw new InvalidArgumentException("Actor HUMANO requiere usuarioId y prohíbe actorSistemaId.");
        }

        if ($this->actorTipo === 'SISTEMA' && ($this->actorSistemaId === null || $this->usuarioId !== null)) {
            throw new InvalidArgumentException("Actor SISTEMA requiere actorSistemaId y prohíbe usuarioId.");
        }

        if (trim($this->correlacionId) === '') {
            throw new InvalidArgumentException("La correlación de auditoría no puede estar vacía.");
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tarifa_paquete_id' => $this->tarifaPaqueteId,
            'precio_anterior' => $this->precioAnterior,
            'precio_nuevo' => $this->precioNuevo,
            'moneda' => $this->moneda,
            'motivo' => $this->motivo,
            'actor_tipo' => $this->actorTipo,
            'usuario_id' => $this->usuarioId,
            'actor_sistema_id' => $this->actorSistemaId,
            'canal_id' => $this->canalId,
            'correlacion_id' => $this->correlacionId,
            'creado_en' => $this->creadoEn,
        ];
    }
}
