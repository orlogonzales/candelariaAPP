<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

/**
 * Entidad Usuario: Cuenta de acceso al sistema vinculada obligatoriamente a una Persona.
 * Integra métodos nativos para password_verify() y password_needs_rehash().
 */
class Usuario
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $organizacionId,
        public readonly int $personaId,
        public readonly string $nombreUsuario,
        public readonly string $nombreCompleto,
        public readonly string $correoElectronico,
        public readonly string $contrasenaHash,
        public readonly bool $esSuperadminPlataforma = false,
        public readonly ?string $avatarUrl = null,
        public readonly string $estado = 'ACTIVO', // 'ACTIVO', 'INACTIVO', 'BLOQUEADO'
        public readonly int $intentosFallidos = 0,
        public readonly ?string $bloqueadoHasta = null,
        public readonly ?string $ultimoAccesoEn = null,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
    }

    /**
     * Verifica una contraseña en texto plano contra el hash almacenado mediante password_verify nativo.
     * Cero comparaciones manuales mediante hash_equals.
     */
    public function verificarContrasena(string $contrasenaPlana): bool
    {
        return password_verify($contrasenaPlana, $this->contrasenaHash);
    }

    /**
     * Comprueba si el hash actual necesita ser recalculado conforme a directrices de PHP 8.3+.
     */
    public function necesitaRehash(): bool
    {
        return password_needs_rehash($this->contrasenaHash, PASSWORD_DEFAULT);
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'ACTIVO' && !$this->estaBloqueado();
    }

    public function estaBloqueado(): bool
    {
        if ($this->estado === 'BLOQUEADO') {
            return true;
        }

        if ($this->bloqueadoHasta !== null) {
            return strtotime($this->bloqueadoHasta) > time();
        }

        return false;
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            organizacionId: isset($datos['organizacion_id']) ? (int) $datos['organizacion_id'] : null,
            personaId: (int) $datos['persona_id'],
            nombreUsuario: (string) $datos['nombre_usuario'],
            nombreCompleto: (string) $datos['nombre_completo'],
            correoElectronico: (string) $datos['correo_electronico'],
            contrasenaHash: (string) $datos['contrasena_hash'],
            esSuperadminPlataforma: (bool) ($datos['es_superadmin_plataforma'] ?? false),
            avatarUrl: $datos['avatar_url'] ?? null,
            estado: (string) ($datos['estado'] ?? 'ACTIVO'),
            intentosFallidos: (int) ($datos['intentos_fallidos'] ?? 0),
            bloqueadoHasta: $datos['bloqueado_hasta'] ?? null,
            ultimoAccesoEn: $datos['ultimo_acceso_en'] ?? null,
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null
        );
    }
}
