<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case LATE = 'LATE';
    case MISSED = 'MISSED';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::PAID => 'Payé',
            self::LATE => 'En retard',
            self::MISSED => 'Manqué',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::PAID => 'success',
            self::LATE => 'danger',
            self::MISSED => 'dark',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::PENDING => 'fas fa-clock',
            self::PAID => 'fas fa-check-circle',
            self::LATE => 'fas fa-exclamation-triangle',
            self::MISSED => 'fas fa-times-circle',
        };
    }
}