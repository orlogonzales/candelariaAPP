<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use InvalidArgumentException;

/**
 * Entidad Organización: Representa a la empresa o tenant operador de CandelariaAPP.
 * Centraliza la identidad institucional, localización fiscal y directrices de branding.
 */
class Organizacion
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $codigo,
        public readonly string $nombreComercial,
        public readonly ?string $razonSocial = null,
        public readonly ?int $tipoDocumentoId = 2,
        public readonly ?string $numeroDocumento = null,
        public readonly ?string $direccion = null,
        public readonly string $codigoPais = 'PE',
        public readonly ?string $departamento = null,
        public readonly ?string $provincia = null,
        public readonly ?string $distrito = null,
        public readonly ?string $correoContacto = null,
        public readonly ?string $sitioWeb = null,
        public readonly ?string $telefonoContacto = null,
        public readonly ?string $telefonoWhatsapp = null,
        public readonly ?string $contactoNombre = null,
        public readonly ?string $contactoCargo = null,
        public readonly ?string $logoUrl = null,
        public readonly ?string $isotipoUrl = null,
        public readonly ?array $marcaConfiguracion = null,
        public readonly string $estado = 'ACTIVO',
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if (empty(trim($this->codigo))) {
            throw new InvalidArgumentException('El código único de la organización es obligatorio.');
        }

        if (!preg_match('/^[a-z0-9_\-]+$/', $this->codigo)) {
            throw new InvalidArgumentException('El código de organización solo admite caracteres alfanuméricos en minúsculas, guiones y guiones bajos.');
        }

        if (empty(trim($this->nombreComercial))) {
            throw new InvalidArgumentException('El nombre comercial de la organización es obligatorio.');
        }

        if (strlen($this->codigoPais) !== 2) {
            throw new InvalidArgumentException('El código de país debe tener exactamente 2 caracteres ISO 3166-1 alpha-2.');
        }

        if (!in_array($this->estado, ['ACTIVO', 'INACTIVO', 'SUSPENDIDO'], true)) {
            throw new InvalidArgumentException("Estado de organización no válido: {$this->estado}");
        }

        if (!empty($this->correoContacto) && !filter_var($this->correoContacto, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("El formato del correo de contacto es inválido: {$this->correoContacto}");
        }
    }

    public static function desdeArreglo(array $fila): self
    {
        $marcaJson = null;
        if (!empty($fila['marca_configuracion_json'])) {
            $marcaJson = is_array($fila['marca_configuracion_json'])
                ? $fila['marca_configuracion_json']
                : json_decode((string) $fila['marca_configuracion_json'], true);
        }

        return new self(
            id: isset($fila['id']) ? (int) $fila['id'] : null,
            codigo: normalizar_minusculas(trim((string) ($fila['codigo'] ?? ''))),
            nombreComercial: normalizar_mayusculas(trim((string) ($fila['nombre_comercial'] ?? ''))),
            razonSocial: !empty($fila['razon_social']) ? normalizar_mayusculas(trim((string) $fila['razon_social'])) : null,
            tipoDocumentoId: isset($fila['tipo_documento_id']) && $fila['tipo_documento_id'] !== null ? (int) $fila['tipo_documento_id'] : 2,
            numeroDocumento: !empty($fila['numero_documento']) ? trim((string) $fila['numero_documento']) : null,
            direccion: !empty($fila['direccion']) ? normalizar_mayusculas(trim((string) $fila['direccion'])) : null,
            codigoPais: strtoupper(trim((string) ($fila['codigo_pais'] ?? 'PE'))),
            departamento: !empty($fila['departamento']) ? normalizar_mayusculas(trim((string) $fila['departamento'])) : null,
            provincia: !empty($fila['provincia']) ? normalizar_mayusculas(trim((string) $fila['provincia'])) : null,
            distrito: !empty($fila['distrito']) ? normalizar_mayusculas(trim((string) $fila['distrito'])) : null,
            correoContacto: !empty($fila['correo_contacto']) ? normalizar_minusculas(trim((string) $fila['correo_contacto'])) : null,
            sitioWeb: !empty($fila['sitio_web']) ? normalizar_minusculas(trim((string) $fila['sitio_web'])) : null,
            telefonoContacto: !empty($fila['telefono_contacto']) ? trim((string) $fila['telefono_contacto']) : null,
            telefonoWhatsapp: !empty($fila['telefono_whatsapp']) ? trim((string) $fila['telefono_whatsapp']) : null,
            contactoNombre: !empty($fila['contacto_nombre']) ? normalizar_mayusculas(trim((string) $fila['contacto_nombre'])) : null,
            contactoCargo: !empty($fila['contacto_cargo']) ? normalizar_mayusculas(trim((string) $fila['contacto_cargo'])) : null,
            logoUrl: !empty($fila['logo_url']) ? trim((string) $fila['logo_url']) : null,
            isotipoUrl: !empty($fila['isotipo_url']) ? trim((string) $fila['isotipo_url']) : null,
            marcaConfiguracion: $marcaJson,
            estado: strtoupper(trim((string) ($fila['estado'] ?? 'ACTIVO'))),
            creadoEn: $fila['creado_en'] ?? null,
            actualizadoEn: $fila['actualizado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                       => $this->id,
            'codigo'                   => $this->codigo,
            'nombre_comercial'         => $this->nombreComercial,
            'razon_social'             => $this->razonSocial,
            'tipo_documento_id'        => $this->tipoDocumentoId,
            'numero_documento'         => $this->numeroDocumento,
            'direccion'                => $this->direccion,
            'codigo_pais'              => $this->codigoPais,
            'departamento'             => $this->departamento,
            'provincia'                => $this->provincia,
            'distrito'                 => $this->distrito,
            'correo_contacto'          => $this->correoContacto,
            'sitio_web'                => $this->sitioWeb,
            'telefono_contacto'        => $this->telefonoContacto,
            'telefono_whatsapp'        => $this->telefonoWhatsapp,
            'contacto_nombre'          => $this->contactoNombre,
            'contacto_cargo'           => $this->contactoCargo,
            'logo_url'                 => $this->logoUrl,
            'isotipo_url'              => $this->isotipoUrl,
            'marca_configuracion_json' => $this->marcaConfiguracion ? json_encode($this->marcaConfiguracion, JSON_UNESCAPED_UNICODE) : null,
            'estado'                   => $this->estado,
            'creado_en'                => $this->creadoEn,
            'actualizado_en'           => $this->actualizadoEn,
        ];
    }
}
