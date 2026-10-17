<?php

declare(strict_types=1);

namespace Aplicacion\Excepciones;

use InvalidArgumentException;
use Throwable;

/**
 * Excepción de dominio lanzada ante errores de validación de reglas de negocio o contratos de entrada (HTTP 422).
 */
class ValidacionExcepcion extends InvalidArgumentException
{
    public function __construct(
        string $mensaje = 'Los datos proporcionados no cumplen las reglas de validación.',
        int $codigo = 422,
        ?Throwable $anterior = null
    ) {
        parent::__construct($mensaje, $codigo, $anterior);
    }
}
