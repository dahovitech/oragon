<?php

namespace App\Form;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du document',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Carte d\'identité recto-verso'
                ],
                'required' => false,
                'help' => 'Laissez vide pour utiliser le nom par défaut'
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de document',
                'choices' => [
                    'Carte d\'identité' => Document::TYPE_IDENTITY_CARD,
                    'Passeport' => Document::TYPE_PASSPORT,
                    'Permis de conduire' => Document::TYPE_DRIVING_LICENSE,
                    'Justificatif de domicile' => Document::TYPE_PROOF_OF_ADDRESS,
                    'Justificatif de revenus' => Document::TYPE_INCOME_PROOF,
                    'Relevé bancaire' => Document::TYPE_BANK_STATEMENT,
                    'Contrat de travail' => Document::TYPE_EMPLOYMENT_CONTRACT,
                    'Autre' => Document::TYPE_OTHER,
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'placeholder' => 'Sélectionnez un type',
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner un type de document')
                ]
            ])
            ->add('file', FileType::class, [
                'label' => 'Fichier',
                'mapped' => true,
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.jpg,.jpeg,.png'
                ],
                'help' => 'Formats acceptés: PDF, JPG, PNG. Taille maximum: 10 MB',
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf',
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier PDF, JPG ou PNG valide',
                        'maxSizeMessage' => 'Le fichier est trop volumineux ({{ size }} {{ suffix }}). Taille maximum autorisée: {{ limit }} {{ suffix }}',
                    ])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
        ]);
    }
}