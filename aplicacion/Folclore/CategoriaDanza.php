<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

enum CategoriaDanza: string
{
    case TRAJES_DE_LUCES = 'TRAJES_DE_LUCES';
    case AUTOCTONO       = 'AUTOCTONO';
    case AFROPERUANO     = 'AFROPERUANO';
    case MESTIZO         = 'MESTIZO';
    case OTRO            = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TRAJES_DE_LUCES => 'Trajes de Luces',
            self::AUTOCTONO       => 'Danza Autóctona / Originaria',
            self::AFROPERUANO     => 'Afroperuano / Saya',
            self::MESTIZO         => 'Mestizo / Tradicional',
            self::OTRO            => 'Otro Género Folclórico',
        };
    }
}
