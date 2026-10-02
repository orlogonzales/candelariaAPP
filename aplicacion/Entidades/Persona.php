<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use InvalidArgumentException;

/**
 * Entidad Persona: Padrón canónico de identidades naturales o jurídicas.
 * Valida invariantes estructurales estrictas según su tipo de persona.
 */
class Persona
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly string $tipoPersona, // 'NATURAL' | 'JURIDICA'
        public readonly int $tipoDocumentoId,
        public readonly string $numeroDocumento,
        public readonly ?string $nombres = null,
        public readonly ?string $apellidos = null,
        public readonly ?string $razonSocial = null,
        public readonly ?string $nombreComercial = null,
        public readonly ?string $correoElectronico = null,
        public readonly ?string $telefonoMovil = null,
        public readonly ?string $telefonoWhatsapp = null,
        public readonly ?string $direccion = null,
        public readonly ?string $ciudad = null,
        public readonly string $codigoPais = 'PE',
        public readonly string $estado = 'ACTIVO',
        public readonly ?array $metadatos = null,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarConsistencia();
    }

    private function validarConsistencia(): void
    {
        if ($this->tipoPersona === 'NATURAL') {
            if (empty($this->nombres) || empty($this->apellidos)) {
                throw new InvalidArgumentException('Una persona natural debe registrar nombres y apellidos obligatorios.');
            }
            if (!empty($this->razonSocial)) {
                throw new InvalidArgumentException('Una persona natural no puede registrar razón social.');
            }
        } elseif ($this->tipoPersona === 'JURIDICA') {
            if (empty($this->razonSocial)) {
                throw new InvalidArgumentException('Una persona jurídica debe registrar razón social obligatoria.');
            }
            if (!empty($this->nombres) || !empty($this->apellidos)) {
                throw new InvalidArgumentException('Una persona jurídica no puede registrar nombres ni apellidos personales.');
            }
        } else {
            throw new InvalidArgumentException("Tipo de persona no soportado: {$this->tipoPersona}");
        }
    }

    /**
     * Retorna el nombre representativo completo según sea natural o jurídica.
     */
    public function obtenerNombreCompleto(): string
    {
        if ($this->tipoPersona === 'NATURAL') {
            return trim("{$this->nombres} {$this->apellidos}");
        }
        return $this->nombreComercial ? "{$this->razonSocial} ({$this->nombreComercial})" : (string) $this->razonSocial;
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            organizacionId: (int) $datos['organizacion_id'],
            tipoPersona: (string) $datos['tipo_persona'],
            tipoDocumentoId: (int) $datos['tipo_documento_id'],
            numeroDocumento: (string) $datos['numero_documento'],
            nombres: $datos['nombres'] ?? null,
            apellidos: $datos['apellidos'] ?? null,
            razonSocial: $datos['razon_social'] ?? null,
            nombreComercial: $datos['nombre_comercial'] ?? null,
            correoElectronico: $datos['correo_electronico'] ?? null,
            telefonoMovil: $datos['telefono_movil'] ?? null,
            telefonoWhatsapp: $datos['telefono_whatsapp'] ?? null,
            direccion: $datos['direccion'] ?? null,
            ciudad: $datos['ciudad'] ?? null,
            codigoPais: (string) ($datos['codigo_pais'] ?? 'PE'),
            estado: (string) ($datos['estado'] ?? 'ACTIVO'),
            metadatos: isset($datos['metadatos_json']) ? (is_string($datos['metadatos_json']) ? json_decode($datos['metadatos_json'], true) : $datos['metadatos_json']) : null,
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null
        );
    }
}
