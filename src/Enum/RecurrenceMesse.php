<?php

namespace App\Enum;

enum RecurrenceMesse: string
{
    case QUOTIDIENNE = 'quotidienne';
    case HEBDOMADAIRE = 'hebdomadaire';
    case MENSUELLE = 'mensuelle';
}
