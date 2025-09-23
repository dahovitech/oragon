<?php

namespace App\Enum;

enum LoanApplicationStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case DOCUMENTS_REQUESTED = 'DOCUMENTS_REQUESTED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CONTRACT_GENERATED = 'CONTRACT_GENERATED';
    case CONTRACT_SIGNED = 'CONTRACT_SIGNED';
    case DISBURSED = 'DISBURSED';
    case ACTIVE = 'ACTIVE';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Brouillon',
            self::SUBMITTED => 'Soumise',
            self::UNDER_REVIEW => 'En cours d\'examen',
            self::DOCUMENTS_REQUESTED => 'Documents demandés',
            self::APPROVED => 'Approuvée',
            self::REJECTED => 'Rejetée',
            self::CONTRACT_GENERATED => 'Contrat généré',
            self::CONTRACT_SIGNED => 'Contrat signé',
            self::DISBURSED => 'Fonds débloqués',
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::DRAFT => 'secondary',
            self::SUBMITTED => 'info',
            self::UNDER_REVIEW => 'warning',
            self::DOCUMENTS_REQUESTED => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CONTRACT_GENERATED => 'primary',
            self::CONTRACT_SIGNED => 'success',
            self::DISBURSED => 'success',
            self::ACTIVE => 'success',
            self::COMPLETED => 'dark',
            self::CANCELLED => 'secondary',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::DRAFT => 'edit',
            self::SUBMITTED => 'paper-plane',
            self::UNDER_REVIEW => 'search',
            self::DOCUMENTS_REQUESTED => 'file-alt',
            self::APPROVED => 'check-circle',
            self::REJECTED => 'times-circle',
            self::CONTRACT_GENERATED => 'file-contract',
            self::CONTRACT_SIGNED => 'pen',
            self::DISBURSED => 'money-check-alt',
            self::ACTIVE => 'play-circle',
            self::COMPLETED => 'check-double',
            self::CANCELLED => 'ban',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::DRAFT => 'La demande est en cours de rédaction',
            self::SUBMITTED => 'La demande a été soumise et est en attente de traitement',
            self::UNDER_REVIEW => 'Votre dossier est en cours d\'examen par nos équipes',
            self::DOCUMENTS_REQUESTED => 'Des documents complémentaires sont requis',
            self::APPROVED => 'Félicitations ! Votre demande a été approuvée',
            self::REJECTED => 'Votre demande n\'a pas pu être acceptée',
            self::CONTRACT_GENERATED => 'Votre contrat est prêt à être signé',
            self::CONTRACT_SIGNED => 'Le contrat a été signé électroniquement',
            self::DISBURSED => 'Les fonds ont été versés sur votre compte',
            self::ACTIVE => 'Votre prêt est actif et les remboursements ont commencé',
            self::COMPLETED => 'Le prêt a été intégralement remboursé',
            self::CANCELLED => 'La demande a été annulée',
        };
    }

    public static function getPendingStatuses(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::UNDER_REVIEW,
            self::DOCUMENTS_REQUESTED,
        ];
    }

    public static function getProcessedStatuses(): array
    {
        return [
            self::APPROVED,
            self::REJECTED,
            self::CONTRACT_GENERATED,
            self::CONTRACT_SIGNED,
            self::DISBURSED,
            self::ACTIVE,
            self::COMPLETED,
            self::CANCELLED,
        ];
    }

    public static function getActiveStatuses(): array
    {
        return [
            self::DISBURSED,
            self::ACTIVE,
        ];
    }

    public static function getCompletedStatuses(): array
    {
        return [
            self::COMPLETED,
            self::CANCELLED,
            self::REJECTED,
        ];
    }

    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    public function canBeApproved(): bool
    {
        return in_array($this, [self::SUBMITTED, self::UNDER_REVIEW, self::DOCUMENTS_REQUESTED]);
    }

    public function canBeRejected(): bool
    {
        return in_array($this, [self::SUBMITTED, self::UNDER_REVIEW, self::DOCUMENTS_REQUESTED]);
    }

    public function canGenerateContract(): bool
    {
        return $this === self::APPROVED;
    }

    public function canBeSigned(): bool
    {
        return $this === self::CONTRACT_GENERATED;
    }

    public function canBeDisbursed(): bool
    {
        return $this === self::CONTRACT_SIGNED;
    }

    public function isComplete(): bool
    {
        return in_array($this, self::getCompletedStatuses());
    }

    public function isActive(): bool
    {
        return in_array($this, self::getActiveStatuses());
    }

    public function getNextPossibleStatuses(): array
    {
        return match($this) {
            self::DRAFT => [self::SUBMITTED],
            self::SUBMITTED => [self::UNDER_REVIEW, self::DOCUMENTS_REQUESTED, self::APPROVED, self::REJECTED],
            self::UNDER_REVIEW => [self::DOCUMENTS_REQUESTED, self::APPROVED, self::REJECTED],
            self::DOCUMENTS_REQUESTED => [self::UNDER_REVIEW, self::APPROVED, self::REJECTED],
            self::APPROVED => [self::CONTRACT_GENERATED, self::REJECTED],
            self::REJECTED => [],
            self::CONTRACT_GENERATED => [self::CONTRACT_SIGNED, self::CANCELLED],
            self::CONTRACT_SIGNED => [self::DISBURSED],
            self::DISBURSED => [self::ACTIVE],
            self::ACTIVE => [self::COMPLETED],
            self::COMPLETED => [],
            self::CANCELLED => [],
        };
    }
}