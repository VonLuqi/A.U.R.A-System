<?php

namespace App\Enums;

enum GoalKind: string
{
    case Savings = 'savings';
    case DebtPayoff = 'debt_payoff';

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
            self::Savings => 'Poupança',
            self::DebtPayoff => 'Amortização de dívida',
        };
    }
}
