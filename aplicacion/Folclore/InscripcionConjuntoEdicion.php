<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

class InscripcionConjuntoEdicion
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $edicionId,
        public int $conjuntoId,
        public ?string $numeroRegistroOficial = null,
        public ?string $nombreDelegadoEdicion = null,
        public ?string $telefonoDelegadoEdicion = null,
        public int $cantidadEstimadaDanzarines = 0,
        public int $cantidadBandas = 1,
        public EstadoInscripcionConjunto $estado = EstadoInscripcionConjunto::INSCRITO,
        public ?string $observaciones = null,
        public int $creadoPor = 1,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null,
        public ?string $conjuntoNombre = null,
        public ?string $conjuntoCodigo = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int)$datos['id'] : null,
            organizacionId: (int)$datos['organizacion_id'],
            edicionId: (int)$datos['edicion_id'],
            conjuntoId: (int)$datos['conjunto_id'],
            numeroRegistroOficial: $datos['numero_registro_oficial'] ?? null,
            nombreDelegadoEdicion: $datos['nombre_delegado_edicion'] ?? null,
            telefonoDelegadoEdicion: $datos['telefono_delegado_edicion'] ?? null,
            cantidadEstimadaDanzarines: (int)($datos['cantidad_estimada_danzarines'] ?? 0),
            cantidadBandas: (int)($datos['cantidad_bandas'] ?? 1),
            estado: is_string($datos['estado'])
                ? EstadoInscripcionConjunto::from($datos['estado'])
                : $datos['estado'],
            observaciones: $datos['observaciones'] ?? null,
            creadoPor: (int)($datos['creado_por'] ?? 1),
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null,
            conjuntoNombre: $datos['conjunto_nombre'] ?? null,
            conjuntoCodigo: $datos['conjunto_codigo'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                            => $this->id,
            'organizacion_id'               => $this->organizacionId,
            'edicion_id'                    => $this->edicionId,
            'conjunto_id'                   => $this->conjuntoId,
            'numero_registro_oficial'       => $this->numeroRegistroOficial,
            'nombre_delegado_edicion'       => $this->nombreDelegadoEdicion,
            'telefono_delegado_edicion'     => $this->telefonoDelegadoEdicion,
            'cantidad_estimada_danzarines'  => $this->cantidadEstimadaDanzarines,
            'cantidad_bandas'               => $this->cantidadBandas,
            'estado'                        => $this->estado->value,
            'observaciones'                 => $this->observaciones,
            'creado_por'                    => $this->creadoPor,
        ];
    }
}
