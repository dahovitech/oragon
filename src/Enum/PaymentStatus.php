<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';
    case CANCELLED = 'CANCELLED';
    case FAILED = 'FAILED';
    case REFUNDED = 'REFUNDED';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::PAID => 'Payé',
            self::OVERDUE => 'En retard',
            self::CANCELLED => 'Annulé',
            self::FAILED => 'Échec',
            self::REFUNDED => 'Remboursé',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
            self::CANCELLED => 'secondary',
            self::FAILED => 'danger',
            self::REFUNDED => 'info',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::PENDING => 'clock',
            self::PAID => 'check-circle',
            self::OVERDUE => 'exclamation-triangle',
            self::CANCELLED => 'times-circle',
            self::FAILED => 'times',
            self::REFUNDED => 'undo',
        };
    }

    public static function getActiveStatuses(): array
    {
        return [self::PENDING, self::OVERDUE];
    }

    public static function getCompletedStatuses(): array
    {
        return [self::PAID, self::REFUNDED];
    }

    public static function getFailedStatuses(): array
    {
        return [self::CANCELLED, self::FAILED];
    }
}