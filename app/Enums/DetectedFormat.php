<?php

namespace App\Enums;

/**
 * Internal detection result for statement uploads (PLAN_EXPANSAO §5.1).
 * Values align with StatementFormat for persistence.
 */
enum DetectedFormat: string
{
    case CsvChecking = 'csv';
    case CsvCreditCard = 'csv_credit_card';
    case Ofx = 'ofx';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
