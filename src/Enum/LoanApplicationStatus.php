<?php

namespace App\Enum;

enum LoanApplicationStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case DISBURSED = 'DISBURSED';
    case CANCELLED = 'CANCELLED';

    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Brouillon',
            self::SUBMITTED => 'Soumise',
            self::UNDER_REVIEW => 'En cours d\'examen',
            self::APPROVED => 'Approuvée',
            self::REJECTED => 'Rejetée',
            self::DISBURSED => 'Déboursée',
            self::CANCELLED => 'Annulée',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::DRAFT => 'badge bg-secondary',
            self::SUBMITTED => 'badge bg-info',
            self::UNDER_REVIEW => 'badge bg-warning',
            self::APPROVED => 'badge bg-success',
            self::REJECTED => 'badge bg-danger',
            self::DISBURSED => 'badge bg-primary',
            self::CANCELLED => 'badge bg-dark',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT]);
    }

    public function canBeSubmitted(): bool
    {
        return $this === self::DRAFT;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::DRAFT, self::SUBMITTED, self::UNDER_REVIEW]);
    }
}