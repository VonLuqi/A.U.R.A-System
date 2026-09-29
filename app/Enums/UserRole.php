<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Subadmin = 'subadmin';
    case Visitor = 'visitor';
    case Test = 'test';

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
            self::Admin => 'Admin',
            self::Subadmin => 'Subadmin',
            self::Visitor => 'Visitante',
            self::Test => 'Teste',
        };
    }

    /**
     * Roles that Admin may assign via API (never self-promote to Admin).
     *
     * @return list<string>
     */
    public static function assignableViaApi(): array
    {
        return [
            self::Subadmin->value,
            self::Visitor->value,
            self::Test->value,
        ];
    }
}
