<?php

namespace App\Form;

use App\Entity\LoanApplication;
use App\Entity\LoanType;
use App\Repository\LoanTypeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class LoanApplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('loanType', EntityType::class, [
                'class' => LoanType::class,
                'choice_label' => 'name',
                'query_builder' => function (LoanTypeRepository $repo) use ($options) {
                    $qb = $repo->createQueryBuilder('lt')
                        ->where('lt.isActive = :active')
                        ->setParameter('active', true);
                    
                    if ($options['account_type']) {
                        $qb->andWhere('JSON_CONTAINS(lt.allowedAccountTypes, :accountType) = 1')
                           ->setParameter('accountType', json_encode($options['account_type']));
                    }
                    
                    return $qb->orderBy('lt.name', 'ASC');
                },
                'label' => 'Type de prêt',
                'placeholder' => 'Sélectionnez un type de prêt',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'form-select']
            ])
            ->add('requestedAmount', MoneyType::class, [
                'label' => 'Montant demandé',
                'currency' => 'EUR',
                'constraints' => [
                    new NotBlank(),
                    new Positive()
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 10000'
                ]
            ])
            ->add('duration', IntegerType::class, [
                'label' => 'Durée (en mois)',
                'constraints' => [
                    new NotBlank(),
                    new Positive()
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 60',
                    'min' => 1,
                    'max' => 360
                ]
            ])
            ->add('purpose', TextareaType::class, [
                'label' => 'Objet du prêt',
                'constraints' => [new NotBlank()],
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Décrivez précisément l\'utilisation prévue des fonds...'
                ]
            ])
            ->add('monthlyIncome', MoneyType::class, [
                'label' => 'Revenus mensuels nets',
                'currency' => 'EUR',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 3500'
                ]
            ])
            ->add('monthlyExpenses', MoneyType::class, [
                'label' => 'Charges mensuelles',
                'currency' => 'EUR',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 1200'
                ]
            ])
            ->add('employmentStatus', ChoiceType::class, [
                'label' => 'Situation professionnelle',
                'choices' => [
                    'CDI' => 'CDI',
                    'CDD' => 'CDD',
                    'Fonctionnaire' => 'FONCTIONNAIRE',
                    'Profession libérale' => 'PROFESSION_LIBERALE',
                    'Artisan/Commerçant' => 'ARTISAN_COMMERCANT',
                    'Retraité' => 'RETRAITE',
                    'Étudiant' => 'ETUDIANT',
                    'Demandeur d\'emploi' => 'DEMANDEUR_EMPLOI',
                    'Autre' => 'AUTRE'
                ],
                'placeholder' => 'Sélectionnez votre situation',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'form-select']
            ])
            ->add('companyName', TextType::class, [
                'label' => 'Nom de l\'employeur/entreprise',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom de votre employeur ou entreprise'
                ]
            ])
            ->add('guarantees', TextareaType::class, [
                'label' => 'Garanties proposées (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Décrivez les garanties que vous pouvez apporter...'
                ]
            ])
            ->add('documents', FileType::class, [
                'label' => 'Documents justificatifs',
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/gif'
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier PDF ou image valide',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.jpg,.jpeg,.png,.gif'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LoanApplication::class,
            'account_type' => null,
        ]);
    }
}