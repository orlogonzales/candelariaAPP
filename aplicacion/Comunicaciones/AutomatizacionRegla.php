<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

class AutomatizacionRegla
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public string $codigo,
        public string $nombre,
        public ?string $descripcion,
        public string $eventoDisparador,
        public int $plantillaId,
        public string $tipoComunicacion = 'TRANSACCIONAL',
        public int $minutosDiferidos = 0,
        public ?array $condicionesJson = null,
        public array $mapeoParametrosJson = [],
        public bool $activo = true,
        public int $totalDisparados = 0,
        public ?string $ultimoDisparoEn = null,
        public ?int $creadoPor = null,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null
    ) {}

    /**
     * Evalúa si los datos del evento satisfacen los filtros de la regla.
     */
    public function cumpleCondiciones(array $payload): bool
    {
        if (empty($this->condicionesJson)) {
            return true;
        }

        foreach ($this->condicionesJson as $clave => $criterio) {
            $valorPayload = $payload[$clave] ?? null;

            if (is_array($criterio)) {
                // Operadores avanzados: ['gte' => 100], ['in' => ['CONFIRMADA', 'PAGADA']]
                if (isset($criterio['eq']) && $valorPayload != $criterio['eq']) {
                    return false;
                }
                if (isset($criterio['neq']) && $valorPayload == $criterio['neq']) {
                    return false;
                }
                if (isset($criterio['gte']) && (float)$valorPayload < (float)$criterio['gte']) {
                    return false;
                }
                if (isset($criterio['lte']) && (float)$valorPayload > (float)$criterio['lte']) {
                    return false;
                }
                if (isset($criterio['in']) && is_array($criterio['in']) && !in_array($valorPayload, $criterio['in'], false)) {
                    return false;
                }
            } else {
                // Comparación directa de igualdad
                if ($valorPayload != $criterio) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Transforma el payload del evento en los parámetros que espera la plantilla.
     */
    public function mapearParametros(array $payload): array
    {
        $parametros = [];

        foreach ($this->mapeoParametrosJson as $placeholder => $campoPayload) {
            if (is_string($campoPayload) && isset($payload[$campoPayload])) {
                $parametros[(string)$placeholder] = (string)$payload[$campoPayload];
            } elseif (is_string($campoPayload) && str_starts_with($campoPayload, 'fijo:')) {
                $parametros[(string)$placeholder] = substr($campoPayload, 5);
            } else {
                $parametros[(string)$placeholder] = (string)($payload[$campoPayload] ?? '');
            }
        }

        return $parametros;
    }

    public function aArreglo(): array
    {
        return [
            'id'                    => $this->id,
            'organizacion_id'       => $this->organizacionId,
            'codigo'                => $this->codigo,
            'nombre'                => $this->nombre,
            'descripcion'           => $this->descripcion,
            'evento_disparador'     => $this->eventoDisparador,
            'plantilla_id'          => $this->plantillaId,
            'tipo_comunicacion'     => $this->tipoComunicacion,
            'minutos_diferidos'     => $this->minutosDiferidos,
            'condiciones_json'      => $this->condicionesJson,
            'mapeo_parametros_json' => $this->mapeoParametrosJson,
            'activo'                => $this->activo,
            'total_disparados'      => $this->totalDisparados,
            'ultimo_disparo_en'     => $this->ultimoDisparoEn,
            'creado_por'            => $this->creadoPor,
            'creado_en'             => $this->creadoEn,
            'actualizado_en'        => $this->actualizadoEn,
        ];
    }
}
