<?php

namespace App\Enum;

enum DocumentType: string
{
    case ID_CARD = 'ID_CARD';
    case PASSPORT = 'PASSPORT';
    case PROOF_INCOME = 'PROOF_INCOME';
    case BANK_STATEMENT = 'BANK_STATEMENT';
    case PROOF_ADDRESS = 'PROOF_ADDRESS';
    case BUSINESS_REGISTRATION = 'BUSINESS_REGISTRATION';
    case KBIS = 'KBIS';
    case BALANCE_SHEET = 'BALANCE_SHEET';
    case TAX_RETURN = 'TAX_RETURN';
    case EMPLOYMENT_CONTRACT = 'EMPLOYMENT_CONTRACT';
    case OTHER = 'OTHER';

    public function getLabel(): string
    {
        return match($this) {
            self::ID_CARD => 'Carte d\'identité',
            self::PASSPORT => 'Passeport',
            self::PROOF_INCOME => 'Justificatif de revenus',
            self::BANK_STATEMENT => 'Relevé bancaire',
            self::PROOF_ADDRESS => 'Justificatif de domicile',
            self::BUSINESS_REGISTRATION => 'Extrait d\'immatriculation',
            self::KBIS => 'Extrait Kbis',
            self::BALANCE_SHEET => 'Bilan comptable',
            self::TAX_RETURN => 'Déclaration fiscale',
            self::EMPLOYMENT_CONTRACT => 'Contrat de travail',
            self::OTHER => 'Autre document',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::ID_CARD => 'Pièce d\'identité officielle (carte nationale d\'identité)',
            self::PASSPORT => 'Passeport en cours de validité',
            self::PROOF_INCOME => '3 derniers bulletins de salaire ou attestation de revenus',
            self::BANK_STATEMENT => '3 derniers relevés de compte bancaire',
            self::PROOF_ADDRESS => 'Facture de moins de 3 mois (électricité, gaz, téléphone)',
            self::BUSINESS_REGISTRATION => 'Document officiel d\'immatriculation de l\'entreprise',
            self::KBIS => 'Extrait Kbis de moins de 3 mois',
            self::BALANCE_SHEET => 'Bilan comptable des 2 dernières années',
            self::TAX_RETURN => 'Avis d\'imposition des 2 dernières années',
            self::EMPLOYMENT_CONTRACT => 'Contrat de travail en cours',
            self::OTHER => 'Document complémentaire',
        };
    }

    public function isRequiredForIndividual(): bool
    {
        return in_array($this, [
            self::ID_CARD,
            self::PROOF_INCOME,
            self::PROOF_ADDRESS,
            self::BANK_STATEMENT
        ]);
    }

    public function isRequiredForBusiness(): bool
    {
        return in_array($this, [
            self::KBIS,
            self::BALANCE_SHEET,
            self::BANK_STATEMENT,
            self::TAX_RETURN
        ]);
    }

    public function getMaxFileSize(): int
    {
        return 10 * 1024 * 1024; // 10 MB
    }

    public function getAllowedExtensions(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png'];
    }
}