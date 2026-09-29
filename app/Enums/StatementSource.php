<?php

namespace App\Enums;

enum StatementSource: string
{
    case Nubank = 'nubank';
    case NubankCredit = 'nubank_credit';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
