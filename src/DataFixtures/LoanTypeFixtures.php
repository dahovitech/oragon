<?php

namespace App\DataFixtures;

use App\Entity\LoanType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class LoanTypeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $loanTypes = [
            [
                'name' => 'Prêt personnel',
                'slug' => 'pret-personnel',
                'description' => 'Financez tous vos projets personnels : vacances, mariage, travaux, véhicule... Notre prêt personnel s\'adapte à vos besoins avec des conditions avantageuses.',
                'minAmount' => 1000,
                'maxAmount' => 50000,
                'minDuration' => 6,
                'maxDuration' => 84,
                'baseInterestRate' => 3.5,
                'allowedAccountTypes' => ['INDIVIDUAL'],
                'requiredDocuments' => ['ID_CARD', 'PROOF_INCOME', 'PROOF_ADDRESS']
            ],
            [
                'name' => 'Prêt immobilier',
                'slug' => 'pret-immobilier',
                'description' => 'Concrétisez votre projet immobilier avec notre prêt immobilier sur mesure. Achat, construction ou travaux, nous vous accompagnons.',
                'minAmount' => 50000,
                'maxAmount' => 500000,
                'minDuration' => 60,
                'maxDuration' => 300,
                'baseInterestRate' => 2.1,
                'allowedAccountTypes' => ['INDIVIDUAL'],
                'requiredDocuments' => ['ID_CARD', 'PROOF_INCOME', 'PROOF_ADDRESS', 'BANK_STATEMENT']
            ],
            [
                'name' => 'Prêt auto',
                'slug' => 'pret-auto',
                'description' => 'Financez l\'achat de votre véhicule neuf ou d\'occasion. Conditions préférentielles et réponse rapide pour concrétiser votre projet mobilité.',
                'minAmount' => 3000,
                'maxAmount' => 75000,
                'minDuration' => 12,
                'maxDuration' => 84,
                'baseInterestRate' => 2.9,
                'allowedAccountTypes' => ['INDIVIDUAL'],
                'requiredDocuments' => ['ID_CARD', 'PROOF_INCOME', 'PROOF_ADDRESS']
            ],
            [
                'name' => 'Prêt étudiant',
                'slug' => 'pret-etudiant',
                'description' => 'Financez vos études supérieures avec notre prêt étudiant avantageux. Remboursement différé possible jusqu\'à la fin des études.',
                'minAmount' => 1000,
                'maxAmount' => 30000,
                'minDuration' => 12,
                'maxDuration' => 120,
                'baseInterestRate' => 1.8,
                'allowedAccountTypes' => ['INDIVIDUAL'],
                'requiredDocuments' => ['ID_CARD', 'PROOF_ADDRESS', 'STUDENT_CERTIFICATE']
            ],
            [
                'name' => 'Prêt professionnel',
                'slug' => 'pret-professionnel',
                'description' => 'Développez votre activité professionnelle avec notre gamme de financements entreprise. Investissement, trésorerie, développement.',
                'minAmount' => 5000,
                'maxAmount' => 200000,
                'minDuration' => 12,
                'maxDuration' => 84,
                'baseInterestRate' => 3.8,
                'allowedAccountTypes' => ['BUSINESS'],
                'requiredDocuments' => ['BUSINESS_REGISTRATION', 'FINANCIAL_STATEMENTS', 'BANK_STATEMENT']
            ],
            [
                'name' => 'Crédit-bail équipement',
                'slug' => 'credit-bail-equipement',
                'description' => 'Financez vos équipements professionnels en crédit-bail. Solution flexible pour renouveler votre matériel sans impact sur votre trésorerie.',
                'minAmount' => 10000,
                'maxAmount' => 300000,
                'minDuration' => 24,
                'maxDuration' => 60,
                'baseInterestRate' => 4.2,
                'allowedAccountTypes' => ['BUSINESS'],
                'requiredDocuments' => ['BUSINESS_REGISTRATION', 'FINANCIAL_STATEMENTS', 'EQUIPMENT_QUOTE']
            ],
            [
                'name' => 'Prêt travaux',
                'slug' => 'pret-travaux',
                'description' => 'Rénovez, agrandissez ou améliorez votre logement. Notre prêt travaux vous accompagne dans tous vos projets d\'amélioration de l\'habitat.',
                'minAmount' => 2000,
                'maxAmount' => 100000,
                'minDuration' => 12,
                'maxDuration' => 120,
                'baseInterestRate' => 3.2,
                'allowedAccountTypes' => ['INDIVIDUAL'],
                'requiredDocuments' => ['ID_CARD', 'PROOF_INCOME', 'PROOF_ADDRESS', 'WORK_ESTIMATE']
            ],
            [
                'name' => 'Crédit revolving',
                'slug' => 'credit-revolving',
                'description' => 'Une réserve d\'argent disponible en permanence pour faire face aux imprévus ou saisir les opportunités. Utilisez selon vos besoins.',
                'minAmount' => 500,
                'maxAmount' => 15000,
                'minDuration' => 6,
                'maxDuration' => 60,
                'baseInterestRate' => 12.5,
                'allowedAccountTypes' => ['INDIVIDUAL'],
                'requiredDocuments' => ['ID_CARD', 'PROOF_INCOME', 'PROOF_ADDRESS']
            ]
        ];

        foreach ($loanTypes as $data) {
            $loanType = new LoanType();
            $loanType->setName($data['name']);
            $loanType->setSlug($data['slug']);
            $loanType->setDescription($data['description']);
            $loanType->setMinAmount($data['minAmount']);
            $loanType->setMaxAmount($data['maxAmount']);
            $loanType->setMinDuration($data['minDuration']);
            $loanType->setMaxDuration($data['maxDuration']);
            $loanType->setBaseInterestRate($data['baseInterestRate']);
            $loanType->setAllowedAccountTypes($data['allowedAccountTypes']);
            $loanType->setRequiredDocuments($data['requiredDocuments']);
            $loanType->setIsActive(true);

            $manager->persist($loanType);
        }

        $manager->flush();
    }
}