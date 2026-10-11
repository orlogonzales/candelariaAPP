<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

/**
 * Entidad de dominio para códigos OTP y Magic Links passwordless.
 */
class ClienteCodigoAcceso
{
    public const TIPO_OTP_WHATSAPP = 'OTP_WHATSAPP';
    public const TIPO_MAGIC_LINK_EMAIL = 'MAGIC_LINK_EMAIL';

    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $clienteId,
        public string $tipoAcceso,
        public string $codigoHash,
        public string $destino,
        public bool $utilizado = false,
        public int $intentosFallidos = 0,
        public string $expiraEn = '',
        public ?string $creadoEn = null
    ) {
    }

    public static function desdeArreglo(array $fila): self
    {
        return new self(
            id: isset($fila['id']) ? (int) $fila['id'] : null,
            organizacionId: (int) $fila['organizacion_id'],
            clienteId: (int) $fila['cliente_id'],
            tipoAcceso: (string) $fila['tipo_acceso'],
            codigoHash: (string) $fila['codigo_hash'],
            destino: (string) $fila['destino'],
            utilizado: !empty($fila['utilizado']),
            intentosFallidos: (int) ($fila['intentos_fallidos'] ?? 0),
            expiraEn: (string) $fila['expira_en'],
            creadoEn: $fila['creado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'organizacion_id' => $this->organizacionId,
            'cliente_id' => $this->clienteId,
            'tipo_acceso' => $this->tipoAcceso,
            'codigo_hash' => $this->codigoHash,
            'destino' => $this->destino,
            'utilizado' => $this->utilizado ? 1 : 0,
            'intentos_fallidos' => $this->intentosFallidos,
            'expira_en' => $this->expiraEn,
            'creado_en' => $this->creadoEn,
        ];
    }

    public function esUtilizable(): bool
    {
        if ($this->utilizado) {
            return false;
        }

        if ($this->intentosFallidos >= 3) {
            return false;
        }

        return strtotime($this->expiraEn) > time();
    }
}
