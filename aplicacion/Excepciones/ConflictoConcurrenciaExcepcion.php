<?php

declare(strict_types=1);

namespace Aplicacion\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una operación de escritura detecta colisión o concurrencia optimista (HTTP 409 Conflict).
 */
class ConflictoConcurrenciaExcepcion extends RuntimeException
{
    public function __construct(
        string $mensaje = 'El registro ha sido modificado concurrentemente por otro operador.',
        int $codigo = 409
    ) {
        parent::__construct($mensaje, $codigo);
    }
}
