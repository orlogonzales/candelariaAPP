<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Clientes\EstadoCliente;
use InvalidArgumentException;

/**
 * Entidad de dominio Cliente: Perfil comercial soberano y unificado de una Persona en un Tenant.
 * Unicidad: uk_clientes_org_persona (1 persona solo puede tener un perfil de cliente por tenant).
 */
class Cliente
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $personaId,
        public readonly EstadoCliente $estadoComercial = EstadoCliente::CONTACTO,
        public readonly bool $consentimientoOperativo = false,
        public readonly ?string $consentimientoOperativoEn = null,
        public readonly bool $consentimientoPromocional = false,
        public readonly ?string $consentimientoPromocionalEn = null,
        public readonly ?string $origenCaptacion = null,
        public readonly ?string $notasComerciales = null,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
    }

    /**
     * Construye una instancia a partir de un arreglo asociativo de base de datos.
     */
    public static function desdeArreglo(array $datos): self
    {
        $estado = $datos['estado_comercial'] instanceof EstadoCliente
            ? $datos['estado_comercial']
            : EstadoCliente::desdeCadena((string) ($datos['estado_comercial'] ?? 'CONTACTO'));

        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            organizacionId: (int) $datos['organizacion_id'],
            personaId: (int) $datos['persona_id'],
            estadoComercial: $estado,
            consentimientoOperativo: !empty($datos['consentimiento_operativo']),
            consentimientoOperativoEn: $datos['consentimiento_operativo_en'] ?? null,
            consentimientoPromocional: !empty($datos['consentimiento_promocional']),
            consentimientoPromocionalEn: $datos['consentimiento_promocional_en'] ?? null,
            origenCaptacion: $datos['origen_captacion'] ?? null,
            notasComerciales: $datos['notas_comerciales'] ?? null,
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null
        );
    }

    /**
     * Serializa los datos comerciales a un arreglo plano.
     */
    public function aArreglo(): array
    {
        return [
            'id'                          => $this->id,
            'organizacion_id'             => $this->organizacionId,
            'persona_id'                  => $this->personaId,
            'estado_comercial'            => $this->estadoComercial->value,
            'estado_comercial_etiqueta'   => $this->estadoComercial->etiqueta(),
            'consentimiento_operativo'    => $this->consentimientoOperativo,
            'consentimiento_operativo_en' => $this->consentimientoOperativoEn,
            'consentimiento_promocional'  => $this->consentimientoPromocional,
            'consentimiento_promocional_en'=> $this->consentimientoPromocionalEn,
            'origen_captacion'            => $this->origenCaptacion,
            'notas_comerciales'           => $this->notasComerciales,
            'creado_en'                   => $this->creadoEn,
            'actualizado_en'              => $this->actualizadoEn,
        ];
    }
}
