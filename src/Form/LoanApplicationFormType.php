<?php

namespace App\Form;

use App\Entity\LoanApplication;
use App\Entity\LoanType;
use App\Enum\LoanApplicationStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class LoanApplicationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('loanType', EntityType::class, [
                'class' => LoanType::class,
                'choice_label' => 'name',
                'label' => 'Type de prêt',
                'query_builder' => function($repository) {
                    return $repository->createQueryBuilder('lt')
                        ->where('lt.isActive = true')
                        ->orderBy('lt.name', 'ASC');
                },
                'attr' => ['class' => 'form-select'],
                'constraints' => [new NotBlank()],
                'help' => 'Sélectionnez le type de prêt qui correspond à votre besoin'
            ])
            
            ->add('requestedAmount', MoneyType::class, [
                'label' => 'Montant demandé',
                'currency' => 'EUR',
                'attr' => ['class' => 'form-control', 'step' => '100'],
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 1000, 'max' => 500000])
                ],
                'help' => 'Montant entre 1 000€ et 500 000€'
            ])
            
            ->add('duration', IntegerType::class, [
                'label' => 'Durée (en mois)',
                'attr' => ['class' => 'form-control', 'min' => 12, 'max' => 120],
                'constraints' => [
                    new NotBlank(),
                    new Range(['min' => 12, 'max' => 120])
                ],
                'help' => 'Durée entre 12 et 120 mois'
            ])
            
            ->add('purpose', ChoiceType::class, [
                'label' => 'Objet du prêt',
                'choices' => [
                    'Achat immobilier' => 'real_estate_purchase',
                    'Travaux et rénovation' => 'renovation_works',
                    'Achat véhicule' => 'vehicle_purchase',
                    'Consolidation de dettes' => 'debt_consolidation',
                    'Projet personnel' => 'personal_project',
                    'Investissement professionnel' => 'business_investment',
                    'Trésorerie d\'entreprise' => 'business_cash_flow',
                    'Équipements professionnels' => 'professional_equipment',
                    'Autres' => 'other'
                ],
                'attr' => ['class' => 'form-select'],
                'constraints' => [new NotBlank()],
                'help' => 'Précisez l\'utilisation prévue des fonds'
            ])
            
            ->add('guarantees', TextareaType::class, [
                'label' => 'Garanties proposées (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Décrivez les garanties que vous pouvez proposer (hypothèque, caution, etc.)'
                ],
                'help' => 'Les garanties peuvent améliorer vos conditions d\'emprunt'
            ])
            
            ->add('personalInfo', TextareaType::class, [
                'label' => 'Informations complémentaires',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Ajoutez toute information utile pour votre dossier...'
                ],
                'help' => 'Précisez votre situation, vos projets, etc.'
            ])
            
            ->add('documents', FileType::class, [
                'label' => 'Documents justificatifs',
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.jpg,.jpeg,.png'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf',
                            'image/jpeg',
                            'image/jpg',
                            'image/png'
                        ],
                        'mimeTypesMessage' => 'Seuls les fichiers PDF, JPG et PNG sont acceptés.',
                    ])
                ],
                'help' => 'Justificatifs de revenus, relevés bancaires, etc. (PDF, JPG, PNG - max 10MB)'
            ])
            
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer en brouillon',
                'attr' => ['class' => 'btn btn-outline-primary']
            ])
            
            ->add('submit', SubmitType::class, [
                'label' => 'Soumettre la demande',
                'attr' => ['class' => 'btn btn-primary']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LoanApplication::class,
        ]);
    }
}