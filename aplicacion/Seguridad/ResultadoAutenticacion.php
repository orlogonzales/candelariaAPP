<?php

declare(strict_types=1);

namespace Aplicacion\Seguridad;

use Aplicacion\Entidades\Sesion;
use Aplicacion\Entidades\Usuario;
use Nucleo\Http\ContextoOperacion;

/**
 * Value Object que encapsula el resultado inmutable de un intento de autenticación.
 */
class ResultadoAutenticacion
{
    public function __construct(
        public readonly bool $exitoso,
        public readonly ?string $mensajeError = null,
        public readonly ?Usuario $usuario = null,
        public readonly ?string $tokenSesion = null,
        public readonly ?ContextoOperacion $contexto = null,
        public readonly ?Sesion $sesion = null
    ) {
    }

    public static function exito(
        Usuario $usuario,
        string $tokenSesion,
        ContextoOperacion $contexto,
        Sesion $sesion
    ): self {
        return new self(
            exitoso: true,
            mensajeError: null,
            usuario: $usuario,
            tokenSesion: $tokenSesion,
            contexto: $contexto,
            sesion: $sesion
        );
    }

    public static function fallo(string $mensajeError): self
    {
        return new self(
            exitoso: false,
            mensajeError: $mensajeError,
            usuario: null,
            tokenSesion: null,
            contexto: null,
            sesion: null
        );
    }
}
