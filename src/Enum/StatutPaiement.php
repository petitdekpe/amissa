<?php

namespace App\Enum;

enum StatutPaiement: string
{
    case EN_ATTENTE = 'en_attente';
    case PAYE = 'paye';
    case ECHOUE = 'echoue';
    case REMBOURSE = 'rembourse';
}
