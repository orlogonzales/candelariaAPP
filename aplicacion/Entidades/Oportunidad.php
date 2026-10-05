<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Crm\EtapaOportunidad;
use Aplicacion\Crm\MotivoPerdida;
use InvalidArgumentException;

/**
 * Entidad de dominio Oportunidad Comercial:
 * Intención comercial concreta contextualizada a un Cliente, una Edición y un Tenant.
 */
class Oportunidad
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $edicionId,
        public readonly int $clienteId,
        public readonly ?int $usuarioAsignadoId,
        public readonly ?int $origenComercialId,
        public readonly string $titulo,
        public readonly EtapaOportunidad $etapa = EtapaOportunidad::NUEVA,
        public readonly ?float $valorEstimado = null,
        public readonly string $moneda = 'PEN',
        public readonly ?string $proximoSeguimientoEn = null,
        public readonly ?MotivoPerdida $motivoPerdida = null,
        public readonly ?string $motivoPerdidaDetalle = null,
        public readonly ?string $notas = null,
        public readonly int $versionBloqueo = 1,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if (trim($this->titulo) === '') {
            throw new InvalidArgumentException("El título de la oportunidad no puede estar vacío.");
        }

        if (mb_strlen($this->titulo) > 120) {
            throw new InvalidArgumentException("El título de la oportunidad no puede exceder 120 caracteres.");
        }

        if ($this->valorEstimado !== null && $this->valorEstimado < 0.0) {
            throw new InvalidArgumentException("El valor estimado de la oportunidad no puede ser negativo.");
        }

        $monedaLimpia = strtoupper(trim($this->moneda));
        if (!preg_match('/^[A-Z]{3}$/', $monedaLimpia)) {
            throw new InvalidArgumentException("La moneda debe ser un código ISO 4217 válido de 3 caracteres (recibido: '{$this->moneda}').");
        }

        if ($this->etapa === EtapaOportunidad::PERDIDA) {
            if ($this->motivoPerdida === null) {
                throw new InvalidArgumentException("Una oportunidad en etapa PERDIDA requiere obligatoriamente un motivo de pérdida.");
            }
            if ($this->motivoPerdida === MotivoPerdida::OTRO) {
                if ($this->motivoPerdidaDetalle === null || trim($this->motivoPerdidaDetalle) === '') {
                    throw new InvalidArgumentException("El motivo de pérdida 'OTRO' requiere obligatoriamente un detalle explicativo.");
                }
            }
        } else {
            if ($this->motivoPerdida !== null) {
                throw new InvalidArgumentException("Una oportunidad en etapa activa ({$this->etapa->value}) no puede registrar motivo de pérdida.");
            }
            if ($this->motivoPerdidaDetalle !== null) {
                throw new InvalidArgumentException("Una oportunidad en etapa activa ({$this->etapa->value}) no puede registrar detalle de motivo de pérdida.");
            }
        }

        if ($this->versionBloqueo < 1) {
            throw new InvalidArgumentException("La versión de bloqueo optimista debe ser mayor o igual a 1.");
        }
    }

    public static function desdeArreglo(array $datos): self
    {
        $etapa = $datos['etapa'] instanceof EtapaOportunidad
            ? $datos['etapa']
            : EtapaOportunidad::desdeCadena((string) ($datos['etapa'] ?? 'NUEVA'));

        $motivo = null;
        if (!empty($datos['motivo_perdida'])) {
            $motivo = $datos['motivo_perdida'] instanceof MotivoPerdida
                ? $datos['motivo_perdida']
                : MotivoPerdida::desdeCadena((string) $datos['motivo_perdida']);
        }

        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            organizacionId: (int) $datos['organizacion_id'],
            edicionId: (int) $datos['edicion_id'],
            clienteId: (int) $datos['cliente_id'],
            usuarioAsignadoId: isset($datos['usuario_asignado_id']) && $datos['usuario_asignado_id'] !== null ? (int) $datos['usuario_asignado_id'] : null,
            origenComercialId: isset($datos['origen_comercial_id']) && $datos['origen_comercial_id'] !== null ? (int) $datos['origen_comercial_id'] : null,
            titulo: (string) $datos['titulo'],
            etapa: $etapa,
            valorEstimado: isset($datos['valor_estimado']) && $datos['valor_estimado'] !== null ? (float) $datos['valor_estimado'] : null,
            moneda: strtoupper(trim((string) ($datos['moneda'] ?? 'PEN'))),
            proximoSeguimientoEn: $datos['proximo_seguimiento_en'] ?? null,
            motivoPerdida: $motivo,
            motivoPerdidaDetalle: $datos['motivo_perdida_detalle'] ?? null,
            notas: $datos['notas'] ?? null,
            versionBloqueo: isset($datos['version_bloqueo']) ? (int) $datos['version_bloqueo'] : 1,
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                     => $this->id,
            'organizacion_id'        => $this->organizacionId,
            'edicion_id'             => $this->edicionId,
            'cliente_id'             => $this->clienteId,
            'usuario_asignado_id'    => $this->usuarioAsignadoId,
            'origen_comercial_id'    => $this->origenComercialId,
            'titulo'                 => $this->titulo,
            'etapa'                  => $this->etapa->value,
            'etapa_etiqueta'         => $this->etapa->etiqueta(),
            'valor_estimado'         => $this->valorEstimado,
            'moneda'                 => $this->moneda,
            'proximo_seguimiento_en' => $this->proximoSeguimientoEn,
            'motivo_perdida'         => $this->motivoPerdida?->value,
            'motivo_perdida_etiqueta'=> $this->motivoPerdida?->etiqueta(),
            'motivo_perdida_detalle' => $this->motivoPerdidaDetalle,
            'notas'                  => $this->notas,
            'version_bloqueo'        => $this->versionBloqueo,
            'creado_en'              => $this->creadoEn,
            'actualizado_en'         => $this->actualizadoEn,
        ];
    }
}
