<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

class ActividadFolcloricaEdicion
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $edicionId,
        public string $codigo,
        public string $titulo,
        public string $fechaInicio,
        public string $fechaFin,
        public string $lugarPrincipal,
        public EstadoActividadFolclorica $estado = EstadoActividadFolclorica::PROGRAMADA,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int)$datos['id'] : null,
            organizacionId: (int)$datos['organizacion_id'],
            edicionId: (int)$datos['edicion_id'],
            codigo: (string)$datos['codigo'],
            titulo: (string)$datos['titulo'],
            fechaInicio: (string)$datos['fecha_inicio'],
            fechaFin: (string)$datos['fecha_fin'],
            lugarPrincipal: (string)$datos['lugar_principal'],
            estado: is_string($datos['estado'])
                ? EstadoActividadFolclorica::from($datos['estado'])
                : $datos['estado'],
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'              => $this->id,
            'organizacion_id' => $this->organizacionId,
            'edicion_id'      => $this->edicionId,
            'codigo'          => $this->codigo,
            'titulo'          => $this->titulo,
            'fecha_inicio'    => $this->fechaInicio,
            'fecha_fin'       => $this->fechaFin,
            'lugar_principal' => $this->lugarPrincipal,
            'estado'          => $this->estado->value,
        ];
    }
}
