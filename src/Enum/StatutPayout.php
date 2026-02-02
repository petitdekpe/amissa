<?php

namespace App\Enum;

enum StatutPayout: string
{
    case EN_ATTENTE = 'en_attente';
    case TRANSFERE = 'transfere';
    case ECHOUE = 'echoue';
}
