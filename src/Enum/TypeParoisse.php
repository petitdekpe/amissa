<?php

namespace App\Enum;

enum TypeParoisse: string
{
    case PAROISSE = 'paroisse';
    case CHAPELLE = 'chapelle';

    public function label(): string
    {
        return match($this) {
            self::PAROISSE => 'Paroisse',
            self::CHAPELLE => 'Chapelle',
        };
    }
}
