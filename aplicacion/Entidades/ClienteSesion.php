<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

/**
 * Entidad de dominio para las sesiones seguras del cliente en el portal público.
 */
class ClienteSesion
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $clienteId,
        public string $tokenSesionHash,
        public string $ipCreacion,
        public ?string $userAgent,
        public string $expiraEn,
        public bool $revocado = false,
        public ?string $creadoEn = null,
        public ?string $ultimoAccesoEn = null
    ) {
    }

    public static function desdeArreglo(array $fila): self
    {
        return new self(
            id: isset($fila['id']) ? (int) $fila['id'] : null,
            organizacionId: (int) $fila['organizacion_id'],
            clienteId: (int) $fila['cliente_id'],
            tokenSesionHash: (string) $fila['token_sesion_hash'],
            ipCreacion: (string) $fila['ip_creacion'],
            userAgent: $fila['user_agent'] ?? null,
            expiraEn: (string) $fila['expira_en'],
            revocado: !empty($fila['revocado']),
            creadoEn: $fila['creado_en'] ?? null,
            ultimoAccesoEn: $fila['ultimo_acceso_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'organizacion_id' => $this->organizacionId,
            'cliente_id' => $this->clienteId,
            'token_sesion_hash' => $this->tokenSesionHash,
            'ip_creacion' => $this->ipCreacion,
            'user_agent' => $this->userAgent,
            'expira_en' => $this->expiraEn,
            'revocado' => $this->revocado ? 1 : 0,
            'creado_en' => $this->creadoEn,
            'ultimo_acceso_en' => $this->ultimoAccesoEn,
        ];
    }

    public function esValida(): bool
    {
        if ($this->revocado) {
            return false;
        }

        return strtotime($this->expiraEn) > time();
    }
}
