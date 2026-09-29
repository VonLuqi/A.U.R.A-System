<?php

namespace App\Enums;

enum StatementFormat: string
{
    case Csv = 'csv';
    case Ofx = 'ofx';
    case CsvCreditCard = 'csv_credit_card';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
