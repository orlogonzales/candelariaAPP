<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use InvalidArgumentException;

/**
 * Entidad de dominio Origen Comercial: Fuente de adquisición/procedencia
 * administrable y extensible por organización.
 */
class OrigenComercial
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $descripcion = null,
        public readonly bool $activo = true,
        public readonly int $orden = 0,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $codigoNormalizado = strtoupper(trim($this->codigo));
        if ($codigoNormalizado === '') {
            throw new InvalidArgumentException("El código de origen comercial no puede estar vacío.");
        }
        if (trim($this->nombre) === '') {
            throw new InvalidArgumentException("El nombre de origen comercial no puede estar vacío.");
        }
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            organizacionId: (int) $datos['organizacion_id'],
            codigo: (string) $datos['codigo'],
            nombre: (string) $datos['nombre'],
            descripcion: $datos['descripcion'] ?? null,
            activo: !empty($datos['activo']),
            orden: isset($datos['orden']) ? (int) $datos['orden'] : 0,
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'              => $this->id,
            'organizacion_id' => $this->organizacionId,
            'codigo'          => $this->codigo,
            'nombre'          => $this->nombre,
            'descripcion'     => $this->descripcion,
            'activo'          => $this->activo,
            'orden'           => $this->orden,
            'creado_en'       => $this->creadoEn,
            'actualizado_en'  => $this->actualizadoEn,
        ];
    }
}
