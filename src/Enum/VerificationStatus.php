<?php

namespace App\Enum;

enum VerificationStatus: string
{
    case PENDING = 'PENDING';
    case VERIFIED = 'VERIFIED';
    case REJECTED = 'REJECTED';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::VERIFIED => 'Vérifié',
            self::REJECTED => 'Rejeté',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::PENDING => 'badge bg-warning',
            self::VERIFIED => 'badge bg-success',
            self::REJECTED => 'badge bg-danger',
        };
    }
}