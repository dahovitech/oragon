<?php

namespace App\Enum;

enum AccountType: string
{
    case INDIVIDUAL = 'INDIVIDUAL';
    case BUSINESS = 'BUSINESS';

    public function getLabel(): string
    {
        return match($this) {
            self::INDIVIDUAL => 'Particulier',
            self::BUSINESS => 'Entreprise',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::INDIVIDUAL => 'Compte pour particuliers',
            self::BUSINESS => 'Compte pour entreprises',
        };
    }
}