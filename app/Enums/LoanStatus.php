<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Em aberto',
            self::Partial => 'Parcial',
            self::Paid => 'Pago',
            self::Cancelled => 'Cancelado',
        };
    }
}
