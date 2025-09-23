<?php

namespace App\Form;

use App\Entity\AccountVerification;
use App\Enum\DocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class AccountVerificationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('verificationType', EnumType::class, [
                'class' => DocumentType::class,
                'label' => 'Type de vérification',
                'choice_label' => function (DocumentType $type) {
                    return match($type) {
                        DocumentType::ID_CARD => 'Pièce d\'identité',
                        DocumentType::PROOF_INCOME => 'Justificatif de revenus',
                        DocumentType::BANK_STATEMENT => 'Relevé bancaire',
                        DocumentType::BUSINESS_REGISTRATION => 'Extrait Kbis',
                        DocumentType::PROOF_ADDRESS => 'Justificatif de domicile',
                        DocumentType::TAX_RETURN => 'Avis d\'imposition',
                        DocumentType::PAYSLIP => 'Bulletin de salaire',
                        DocumentType::OTHER => 'Autre document',
                    };
                },
                'attr' => ['class' => 'form-select'],
                'constraints' => [new NotBlank()]
            ])
            ->add('documents', FileType::class, [
                'label' => 'Documents à télécharger',
                'multiple' => true,
                'mapped' => false,
                'required' => true,
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
                ]
            ])
            ->add('comments', TextareaType::class, [
                'label' => 'Commentaires (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Ajoutez des informations complémentaires...'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Soumettre pour vérification',
                'attr' => ['class' => 'btn btn-primary btn-lg w-100']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AccountVerification::class,
        ]);
    }
}