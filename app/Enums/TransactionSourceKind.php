<?php

namespace App\Enums;

enum TransactionSourceKind: string
{
    case Import = 'import';
    case Manual = 'manual';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
