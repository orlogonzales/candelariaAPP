<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use InvalidArgumentException;

/**
 * Entidad de dominio TarifaPaqueteEdicion:
 * Precio comercial vigente propio para la oferta de un paquete en una edición.
 */
class TarifaPaqueteEdicion
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $ofertaPaqueteId,
        public readonly string $moneda,
        public readonly float $precio,
        public readonly int $versionBloqueo = 1,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->ofertaPaqueteId <= 0) {
            throw new InvalidArgumentException("El ID de oferta de paquete debe ser un entero positivo.");
        }

        $monedaLimpia = strtoupper(trim($this->moneda));
        if (!preg_match('/^[A-Z]{3}$/', $monedaLimpia)) {
            throw new InvalidArgumentException("La moneda debe ser un código ISO 4217 válido de 3 caracteres (recibido: '{$this->moneda}').");
        }

        if ($this->precio < 0.0) {
            throw new InvalidArgumentException("El precio de la tarifa no puede ser negativo (recibido: {$this->precio}).");
        }

        if ($this->versionBloqueo < 1) {
            throw new InvalidArgumentException("La versión de bloqueo debe ser mayor o igual a 1.");
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'oferta_paquete_id' => $this->ofertaPaqueteId,
            'moneda' => $this->moneda,
            'precio' => $this->precio,
            'version_bloqueo' => $this->versionBloqueo,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
