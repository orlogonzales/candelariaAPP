<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

class ProgramacionOficialEdicion
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $edicionId,
        public int $actividadId,
        public int $versionNumero = 1,
        public string $etiquetaVersion = 'Rol Oficial v1.0',
        public string $fuenteOrigen = 'Federación Regional de Folklore y Cultura de Puno (FRFCP)',
        public string $fechaEmisionFuente = '',
        public ?string $documentoReferenciaUrl = null,
        public EstadoConfirmacionProgramacion $estadoConfirmacion = EstadoConfirmacionProgramacion::PRELIMINAR,
        public bool $esVersionActiva = true,
        public ?string $notasVersion = null,
        public int $creadoPor = 1,
        public ?int $aprobadoPor = null,
        public ?string $aprobadoEn = null,
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
            actividadId: (int)$datos['actividad_id'],
            versionNumero: (int)($datos['version_numero'] ?? 1),
            etiquetaVersion: (string)($datos['etiqueta_version'] ?? 'Rol Oficial v1.0'),
            fuenteOrigen: (string)($datos['fuente_origen'] ?? ''),
            fechaEmisionFuente: (string)($datos['fecha_emision_fuente'] ?? ''),
            documentoReferenciaUrl: $datos['documento_referencia_url'] ?? null,
            estadoConfirmacion: is_string($datos['estado_confirmacion'])
                ? EstadoConfirmacionProgramacion::from($datos['estado_confirmacion'])
                : $datos['estado_confirmacion'],
            esVersionActiva: (bool)($datos['es_version_activa'] ?? true),
            notasVersion: $datos['notas_version'] ?? null,
            creadoPor: (int)($datos['creado_por'] ?? 1),
            aprobadoPor: isset($datos['aprobado_por']) ? (int)$datos['aprobado_por'] : null,
            aprobadoEn: $datos['aprobado_en'] ?? null,
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                       => $this->id,
            'organizacion_id'          => $this->organizacionId,
            'edicion_id'               => $this->edicionId,
            'actividad_id'             => $this->actividadId,
            'version_numero'           => $this->versionNumero,
            'etiqueta_version'         => $this->etiquetaVersion,
            'fuente_origen'            => $this->fuenteOrigen,
            'fecha_emision_fuente'     => $this->fechaEmisionFuente,
            'documento_referencia_url' => $this->documentoReferenciaUrl,
            'estado_confirmacion'      => $this->estadoConfirmacion->value,
            'es_version_activa'        => $this->esVersionActiva ? 1 : 0,
            'notas_version'            => $this->notasVersion,
            'creado_por'               => $this->creadoPor,
            'aprobado_por'             => $this->aprobadoPor,
            'aprobado_en'              => $this->aprobadoEn,
        ];
    }
}
