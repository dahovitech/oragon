<?php

namespace App\Form;

use App\Entity\LoanApplication;
use App\Entity\LoanType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LoanApplicationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $loanTypes = $options['loan_types'] ?? [];

        $builder
            // Section 1: Détails du prêt
            ->add('loanType', EntityType::class, [
                'class' => LoanType::class,
                'choices' => $loanTypes,
                'choice_label' => function(LoanType $loanType) {
                    $translation = $loanType->getTranslations()->first();
                    return $translation ? $translation->getName() : 'Type de prêt';
                },
                'choice_attr' => function(LoanType $loanType) {
                    return [
                        'data-min-amount' => $loanType->getMinAmount(),
                        'data-max-amount' => $loanType->getMaxAmount(),
                        'data-min-duration' => $loanType->getMinDuration(),
                        'data-max-duration' => $loanType->getMaxDuration(),
                        'data-interest-rate' => $loanType->getBaseInterestRate(),
                    ];
                },
                'label' => 'Type de prêt',
                'placeholder' => 'Sélectionnez un type de prêt',
                'attr' => [
                    'class' => 'form-select loan-type-select',
                    'data-live-search' => 'true'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez sélectionner un type de prêt.'])
                ]
            ])
            
            ->add('requestedAmount', MoneyType::class, [
                'label' => 'Montant demandé',
                'currency' => 'EUR',
                'divisor' => 1,
                'attr' => [
                    'class' => 'form-control loan-amount-input',
                    'placeholder' => '25000.00',
                    'min' => 1000,
                    'max' => 500000,
                    'step' => '100'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le montant est requis.']),
                    new Assert\Positive(['message' => 'Le montant doit être positif.']),
                    new Assert\Range([
                        'min' => 1000,
                        'max' => 500000,
                        'notInRangeMessage' => 'Le montant doit être entre {{ min }}€ et {{ max }}€.'
                    ])
                ]
            ])
            
            ->add('duration', IntegerType::class, [
                'label' => 'Durée (en mois)',
                'attr' => [
                    'class' => 'form-control loan-duration-input',
                    'placeholder' => '24',
                    'min' => 6,
                    'max' => 120
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La durée est requise.']),
                    new Assert\Positive(['message' => 'La durée doit être positive.']),
                    new Assert\Range([
                        'min' => 6,
                        'max' => 120,
                        'notInRangeMessage' => 'La durée doit être entre {{ min }} et {{ max }} mois.'
                    ])
                ]
            ])
            
            ->add('purpose', TextareaType::class, [
                'label' => 'Objectif du prêt',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Décrivez l\'utilisation prévue des fonds...',
                    'rows' => 3,
                    'maxlength' => 500
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'objectif du prêt est requis.']),
                    new Assert\Length([
                        'max' => 500,
                        'maxMessage' => 'L\'objectif ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])

            // Section 2: Informations personnelles
            ->add('personalInfo', PersonalInfoType::class, [
                'label' => false,
                'mapped' => false,
                'data' => $options['personal_info_data'] ?? []
            ])

            // Section 3: Informations financières  
            ->add('financialInfo', FinancialInfoType::class, [
                'label' => false,
                'mapped' => false,
                'data' => $options['financial_info_data'] ?? []
            ])

            // Section 4: Garanties (optionnel)
            ->add('guarantees', TextareaType::class, [
                'label' => 'Garanties proposées',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Décrivez les garanties que vous pouvez proposer (optionnel)...',
                    'rows' => 3,
                    'maxlength' => 1000
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'La description des garanties ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LoanApplication::class,
            'loan_types' => [],
            'personal_info_data' => [],
            'financial_info_data' => [],
        ]);
    }
}

// Sous-formulaire pour les informations personnelles
class PersonalInfoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('full_name', TextType::class, [
                'label' => 'Nom complet',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Prénom Nom'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom complet est requis.']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'votre.email@example.com'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'email est requis.']),
                    new Assert\Email(['message' => 'Veuillez entrer un email valide.'])
                ]
            ])
            
            ->add('phone', TelType::class, [
                'label' => 'Numéro de téléphone',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+33 1 23 45 67 89'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le numéro de téléphone est requis.']),
                    new Assert\Regex([
                        'pattern' => '/^[+]?[\d\s\-\(\)\.]{8,20}$/',
                        'message' => 'Veuillez entrer un numéro de téléphone valide.'
                    ])
                ]
            ])
            
            ->add('birth_date', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'max' => (new \DateTime('-18 years'))->format('Y-m-d')
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La date de naissance est requise.']),
                    new Assert\LessThan([
                        'value' => '-18 years',
                        'message' => 'Vous devez être majeur pour faire une demande de prêt.'
                    ])
                ]
            ])
            
            ->add('marital_status', ChoiceType::class, [
                'label' => 'Situation familiale',
                'choices' => [
                    'Célibataire' => 'single',
                    'Marié(e)' => 'married',
                    'Divorcé(e)' => 'divorced',
                    'Veuf/Veuve' => 'widowed',
                    'Pacsé(e)' => 'civil_union'
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez votre situation',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La situation familiale est requise.'])
                ]
            ])
            
            ->add('dependents', IntegerType::class, [
                'label' => 'Nombre de personnes à charge',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 10
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nombre de personnes à charge est requis.']),
                    new Assert\Range([
                        'min' => 0,
                        'max' => 10,
                        'notInRangeMessage' => 'Le nombre de personnes à charge doit être entre {{ min }} et {{ max }}.'
                    ])
                ]
            ])

            // Adresse
            ->add('address_house', TextType::class, [
                'label' => 'Numéro et nom de voie',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '123 Rue de la Paix'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'adresse est requise.'])
                ]
            ])
            
            ->add('address_street', TextType::class, [
                'label' => 'Complément d\'adresse',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Appartement, étage, etc.'
                ]
            ])
            
            ->add('address_city', TextType::class, [
                'label' => 'Ville',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Paris'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La ville est requise.'])
                ]
            ])
            
            ->add('address_postal_code', TextType::class, [
                'label' => 'Code postal',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '75001'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le code postal est requis.']),
                    new Assert\Regex([
                        'pattern' => '/^\d{5}$/',
                        'message' => 'Le code postal doit contenir 5 chiffres.'
                    ])
                ]
            ])
            
            ->add('address_country', ChoiceType::class, [
                'label' => 'Pays',
                'choices' => [
                    'France' => 'FR',
                    'Belgique' => 'BE',
                    'Suisse' => 'CH',
                    'Luxembourg' => 'LU',
                    'Canada' => 'CA'
                ],
                'attr' => ['class' => 'form-select'],
                'data' => 'FR',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le pays est requis.'])
                ]
            ]);
    }
}

// Sous-formulaire pour les informations financières
class FinancialInfoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('monthly_income', MoneyType::class, [
                'label' => 'Revenu mensuel net',
                'currency' => 'EUR',
                'divisor' => 1,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '3000.00'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le revenu mensuel est requis.']),
                    new Assert\Positive(['message' => 'Le revenu doit être positif.'])
                ]
            ])
            
            ->add('employment_type', ChoiceType::class, [
                'label' => 'Type d\'emploi',
                'choices' => [
                    'CDI' => 'permanent',
                    'CDD' => 'temporary',
                    'Freelance/Indépendant' => 'freelance',
                    'Fonctionnaire' => 'civil_servant',
                    'Retraité' => 'retired',
                    'Étudiant' => 'student',
                    'Demandeur d\'emploi' => 'unemployed'
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez votre situation',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le type d\'emploi est requis.'])
                ]
            ])
            
            ->add('employment_industry', TextType::class, [
                'label' => 'Secteur d\'activité',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Informatique, Finance, Santé...'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le secteur d\'activité est requis.'])
                ]
            ])
            
            ->add('employer_name', TextType::class, [
                'label' => 'Nom de l\'employeur',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom de l\'entreprise'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom de l\'employeur est requis.'])
                ]
            ])
            
            ->add('work_phone', TelType::class, [
                'label' => 'Téléphone professionnel',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+33 1 23 45 67 89'
                ],
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => '/^[+]?[\d\s\-\(\)\.]{8,20}$/',
                        'message' => 'Veuillez entrer un numéro de téléphone valide.'
                    ])
                ]
            ])
            
            ->add('monthly_expenses', MoneyType::class, [
                'label' => 'Charges mensuelles',
                'currency' => 'EUR',
                'divisor' => 1,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '1500.00'
                ],
                'constraints' => [
                    new Assert\PositiveOrZero(['message' => 'Les charges doivent être positives ou nulles.'])
                ]
            ])
            
            ->add('existing_loans', MoneyType::class, [
                'label' => 'Prêts en cours (mensualités)',
                'currency' => 'EUR',
                'divisor' => 1,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '500.00'
                ],
                'constraints' => [
                    new Assert\PositiveOrZero(['message' => 'Le montant des prêts en cours doit être positif ou nul.'])
                ]
            ]);
    }
}