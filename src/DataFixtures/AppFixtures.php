<?php

namespace App\DataFixtures;

use App\Entity\Language;
use App\Entity\LoanType;
use App\Entity\LoanTypeTranslation;
use App\Entity\User;
use App\Enum\AccountType;
use App\Enum\VerificationStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Create languages
        $frLanguage = new Language();
        $frLanguage->setCode('fr')
            ->setName('Français')
            ->setNativeName('Français')
            ->setIsActive(true)
            ->setIsDefault(true)
            ->setSortOrder(1);
        $manager->persist($frLanguage);

        $enLanguage = new Language();
        $enLanguage->setCode('en')
            ->setName('English')
            ->setNativeName('English')
            ->setIsActive(true)
            ->setIsDefault(false)
            ->setSortOrder(2);
        $manager->persist($enLanguage);

        // Create admin user
        $admin = new User();
        $admin->setEmail('admin@edgeloan.com')
            ->setFirstName('Admin')
            ->setLastName('System')
            ->setRoles(['ROLE_ADMIN'])
            ->setAccountType(AccountType::INDIVIDUAL)
            ->setIsVerified(true)
            ->setVerificationStatus(VerificationStatus::VERIFIED)
            ->setPhoneNumber('0123456789')
            ->setAddress('123 Rue de la Paix')
            ->setCity('Paris')
            ->setPostalCode('75001')
            ->setCountry('France')
            ->setDateOfBirth(new \DateTime('1980-01-01'))
            ->setMonthlyIncome('5000.00')
            ->setEmploymentStatus('CDI');

        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);

        // Create test user - individual
        $userIndividual = new User();
        $userIndividual->setEmail('user@example.com')
            ->setFirstName('Jean')
            ->setLastName('Dupont')
            ->setRoles(['ROLE_USER'])
            ->setAccountType(AccountType::INDIVIDUAL)
            ->setIsVerified(true)
            ->setVerificationStatus(VerificationStatus::VERIFIED)
            ->setPhoneNumber('0123456789')
            ->setAddress('456 Avenue des Champs')
            ->setCity('Lyon')
            ->setPostalCode('69000')
            ->setCountry('France')
            ->setDateOfBirth(new \DateTime('1985-05-15'))
            ->setMonthlyIncome('3500.00')
            ->setEmploymentStatus('CDI');

        $hashedPassword = $this->passwordHasher->hashPassword($userIndividual, 'user123');
        $userIndividual->setPassword($hashedPassword);
        $manager->persist($userIndividual);

        // Create test user - business
        $userBusiness = new User();
        $userBusiness->setEmail('business@example.com')
            ->setFirstName('Marie')
            ->setLastName('Martin')
            ->setRoles(['ROLE_USER'])
            ->setAccountType(AccountType::BUSINESS)
            ->setIsVerified(true)
            ->setVerificationStatus(VerificationStatus::VERIFIED)
            ->setPhoneNumber('0987654321')
            ->setAddress('789 Boulevard du Commerce')
            ->setCity('Marseille')
            ->setPostalCode('13000')
            ->setCountry('France')
            ->setCompanyName('Martin SARL')
            ->setSiretNumber('12345678901234')
            ->setBusinessSector('Commerce')
            ->setLegalForm('SARL')
            ->setMonthlyIncome('8000.00');

        $hashedPassword = $this->passwordHasher->hashPassword($userBusiness, 'business123');
        $userBusiness->setPassword($hashedPassword);
        $manager->persist($userBusiness);

        $manager->flush();

        // Create loan types
        $loanTypes = [
            [
                'slug' => 'pret-personnel',
                'minAmount' => 1000,
                'maxAmount' => 50000,
                'minDuration' => 12,
                'maxDuration' => 84,
                'rate' => 4.5,
                'accountTypes' => ['INDIVIDUAL'],
                'documents' => ['ID_CARD', 'PROOF_INCOME', 'BANK_STATEMENT'],
                'translations' => [
                    'fr' => [
                        'title' => 'Prêt Personnel',
                        'description' => 'Un prêt personnel adapté à tous vos projets personnels.',
                        'shortDescription' => 'Financez vos projets personnels',
                        'conditions' => 'Être majeur, résider en France, avoir des revenus réguliers',
                        'benefits' => 'Taux avantageux, remboursement flexible, réponse rapide'
                    ],
                    'en' => [
                        'title' => 'Personal Loan',
                        'description' => 'A personal loan adapted to all your personal projects.',
                        'shortDescription' => 'Finance your personal projects',
                        'conditions' => 'Be of legal age, reside in France, have regular income',
                        'benefits' => 'Advantageous rate, flexible repayment, quick response'
                    ]
                ]
            ],
            [
                'slug' => 'pret-immobilier',
                'minAmount' => 50000,
                'maxAmount' => 500000,
                'minDuration' => 120,
                'maxDuration' => 300,
                'rate' => 2.8,
                'accountTypes' => ['INDIVIDUAL', 'BUSINESS'],
                'documents' => ['ID_CARD', 'PROOF_INCOME', 'BANK_STATEMENT'],
                'translations' => [
                    'fr' => [
                        'title' => 'Prêt Immobilier',
                        'description' => 'Financez votre acquisition immobilière avec notre prêt immobilier.',
                        'shortDescription' => 'Achetez votre logement',
                        'conditions' => 'Apport personnel, garanties, assurance emprunteur',
                        'benefits' => 'Taux très avantageux, durée jusqu\'à 25 ans, accompagnement personnalisé'
                    ],
                    'en' => [
                        'title' => 'Mortgage Loan',
                        'description' => 'Finance your real estate acquisition with our mortgage loan.',
                        'shortDescription' => 'Buy your home',
                        'conditions' => 'Personal contribution, guarantees, borrower insurance',
                        'benefits' => 'Very advantageous rate, duration up to 25 years, personalized support'
                    ]
                ]
            ],
            [
                'slug' => 'pret-auto',
                'minAmount' => 5000,
                'maxAmount' => 80000,
                'minDuration' => 12,
                'maxDuration' => 84,
                'rate' => 3.2,
                'accountTypes' => ['INDIVIDUAL'],
                'documents' => ['ID_CARD', 'PROOF_INCOME', 'BANK_STATEMENT'],
                'translations' => [
                    'fr' => [
                        'title' => 'Prêt Auto',
                        'description' => 'Financez l\'achat de votre véhicule neuf ou d\'occasion.',
                        'shortDescription' => 'Achetez votre véhicule',
                        'conditions' => 'Véhicule de moins de 8 ans, justificatifs de revenus',
                        'benefits' => 'Financement jusqu\'à 100%, remboursement anticipé possible'
                    ],
                    'en' => [
                        'title' => 'Car Loan',
                        'description' => 'Finance the purchase of your new or used vehicle.',
                        'shortDescription' => 'Buy your vehicle',
                        'conditions' => 'Vehicle less than 8 years old, proof of income',
                        'benefits' => 'Financing up to 100%, early repayment possible'
                    ]
                ]
            ],
            [
                'slug' => 'pret-professionnel',
                'minAmount' => 10000,
                'maxAmount' => 200000,
                'minDuration' => 24,
                'maxDuration' => 120,
                'rate' => 4.8,
                'accountTypes' => ['BUSINESS'],
                'documents' => ['KBIS', 'BALANCE_SHEET', 'BANK_STATEMENT'],
                'translations' => [
                    'fr' => [
                        'title' => 'Prêt Professionnel',
                        'description' => 'Développez votre activité avec notre prêt professionnel.',
                        'shortDescription' => 'Développez votre entreprise',
                        'conditions' => 'Entreprise créée depuis plus de 2 ans, bilans comptables',
                        'benefits' => 'Accompagnement dédié, taux négociés, solutions sur mesure'
                    ],
                    'en' => [
                        'title' => 'Business Loan',
                        'description' => 'Develop your business with our professional loan.',
                        'shortDescription' => 'Develop your business',
                        'conditions' => 'Company created for more than 2 years, financial statements',
                        'benefits' => 'Dedicated support, negotiated rates, tailor-made solutions'
                    ]
                ]
            ]
        ];

        foreach ($loanTypes as $index => $loanTypeData) {
            $loanType = new LoanType();
            $loanType->setSlug($loanTypeData['slug'])
                ->setMinAmount((string) $loanTypeData['minAmount'])
                ->setMaxAmount((string) $loanTypeData['maxAmount'])
                ->setMinDuration($loanTypeData['minDuration'])
                ->setMaxDuration($loanTypeData['maxDuration'])
                ->setBaseInterestRate((string) $loanTypeData['rate'])
                ->setAllowedAccountTypes($loanTypeData['accountTypes'])
                ->setRequiredDocuments($loanTypeData['documents'])
                ->setSortOrder($index + 1)
                ->setIsActive(true);

            $manager->persist($loanType);

            // Add translations
            foreach ($loanTypeData['translations'] as $langCode => $translationData) {
                $language = $langCode === 'fr' ? $frLanguage : $enLanguage;
                
                $translation = new LoanTypeTranslation();
                $translation->setLoanType($loanType)
                    ->setLanguage($language)
                    ->setTitle($translationData['title'])
                    ->setDescription($translationData['description'])
                    ->setShortDescription($translationData['shortDescription'])
                    ->setConditions($translationData['conditions'])
                    ->setBenefits($translationData['benefits']);

                $manager->persist($translation);
            }
        }

        $manager->flush();
    }
}