<?php

namespace App\Enums;

enum LoanKind: string
{
    case Cash = 'cash';
    case CardLimit = 'card_limit';

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
            self::Cash => 'Dinheiro',
            self::CardLimit => 'Limite do cartão',
        };
    }
}
