<?php

namespace App\Form;

use App\Entity\AccountVerification;
use App\Entity\VerificationDocument;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class AccountVerificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('verificationType', ChoiceType::class, [
                'label' => 'Type de vérification',
                'choices' => [
                    'Vérification d\'identité' => 'IDENTITY',
                    'Justificatif de domicile' => 'ADDRESS',
                    'Justificatifs de revenus' => 'INCOME',
                    'Documents d\'entreprise' => 'BUSINESS',
                ],
                'placeholder' => 'Sélectionnez le type de vérification',
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un type de vérification']),
                ],
            ])
            
            ->add('identityDocuments', FileType::class, [
                'label' => 'Pièces d\'identité (CNI, Passeport)',
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.jpg,.jpeg,.png',
                ],
                'constraints' => [
                    new All([
                        new File([
                            'maxSize' => '10M',
                            'mimeTypes' => [
                                'application/pdf',
                                'image/jpeg',
                                'image/jpg',
                                'image/png',
                            ],
                            'mimeTypesMessage' => 'Veuillez uploader un fichier PDF, JPG ou PNG valide',
                        ])
                    ])
                ],
                'help' => 'Formats acceptés: PDF, JPG, PNG. Taille maximum: 10MB par fichier.',
            ])
            
            ->add('addressDocuments', FileType::class, [
                'label' => 'Justificatifs de domicile (Facture, Attestation)',
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.jpg,.jpeg,.png',
                ],
                'constraints' => [
                    new All([
                        new File([
                            'maxSize' => '10M',
                            'mimeTypes' => [
                                'application/pdf',
                                'image/jpeg',
                                'image/jpg',
                                'image/png',
                            ],
                            'mimeTypesMessage' => 'Veuillez uploader un fichier PDF, JPG ou PNG valide',
                        ])
                    ])
                ],
                'help' => 'Facture d\'électricité, gaz, téléphone ou attestation de domicile récente (moins de 3 mois).',
            ])
            
            ->add('incomeDocuments', FileType::class, [
                'label' => 'Justificatifs de revenus',
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.jpg,.jpeg,.png',
                ],
                'constraints' => [
                    new All([
                        new File([
                            'maxSize' => '10M',
                            'mimeTypes' => [
                                'application/pdf',
                                'image/jpeg',
                                'image/jpg',
                                'image/png',
                            ],
                            'mimeTypesMessage' => 'Veuillez uploader un fichier PDF, JPG ou PNG valide',
                        ])
                    ])
                ],
                'help' => '3 derniers bulletins de salaire, avis d\'imposition, ou justificatifs de revenus.',
            ])
            
            ->add('businessDocuments', FileType::class, [
                'label' => 'Documents d\'entreprise (pour les professionnels)',
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.jpg,.jpeg,.png',
                ],
                'constraints' => [
                    new All([
                        new File([
                            'maxSize' => '10M',
                            'mimeTypes' => [
                                'application/pdf',
                                'image/jpeg',
                                'image/jpg',
                                'image/png',
                            ],
                            'mimeTypesMessage' => 'Veuillez uploader un fichier PDF, JPG ou PNG valide',
                        ])
                    ])
                ],
                'help' => 'Kbis, statuts de l\'entreprise, bilans comptables, etc.',
            ])
            
            ->add('comments', TextareaType::class, [
                'label' => 'Commentaires additionnels (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Ajoutez des informations complémentaires si nécessaire...',
                ],
                'help' => 'Vous pouvez ajouter des précisions concernant votre demande de vérification.',
            ])
            
            ->add('submit', SubmitType::class, [
                'label' => 'Soumettre la demande de vérification',
                'attr' => [
                    'class' => 'easilon-btn w-100',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AccountVerification::class,
        ]);
    }
}