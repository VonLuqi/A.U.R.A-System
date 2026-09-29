<?php

namespace App\Enums;

enum AliasMatchType: string
{
    case Exact = 'exact';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case Regex = 'regex';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
