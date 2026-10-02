<?php

declare(strict_types=1);

namespace Aplicacion\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando un usuario autenticado carece de los privilegios requeridos (HTTP 403 Forbidden).
 */
class AccesoDenegadoExcepcion extends RuntimeException
{
    public function __construct(
        string $mensaje = 'Acceso denegado: no cuenta con los permisos necesarios para realizar esta operación.',
        public readonly ?string $permisoRequerido = null,
        public readonly int $codigoHttp = 403
    ) {
        parent::__construct($mensaje, $codigoHttp);
    }
}
