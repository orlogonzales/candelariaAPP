<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas;

enum EstadoIntentoPasarela: string
{
    case INICIADO = 'INICIADO';
    case PROCESANDO = 'PROCESANDO';
    case EXITOSO = 'EXITOSO';
    case FALLIDO = 'FALLIDO';
    case EXPIRADO = 'EXPIRADO';
}
