<?php

namespace App\Form;

use App\Entity\ServiceTranslation;
use App\Entity\Language;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class ServiceTranslationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEditMode = $options['is_edit_mode'] ?? false;

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'placeholder' => 'Titre du service',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le titre est obligatoire']),
                    new Length(['min' => 2, 'max' => 255])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description courte',
                'help' => 'Description courte affichée dans les listes (max. 500 caractères)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description courte du service...',
                    'rows' => 3,
                    'maxlength' => 500,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Length(['max' => 500])
                ]
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu complet',
                'help' => 'Contenu détaillé affiché sur la page du service',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Contenu détaillé du service...',
                    'rows' => 8,
                    'class' => 'form-control'
                ]
            ])
            ->add('metaTitle', TextType::class, [
                'label' => 'Meta titre (SEO)',
                'help' => 'Titre pour les moteurs de recherche (max. 255 caractères)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Titre SEO pour les moteurs de recherche',
                    'maxlength' => 255,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Length(['max' => 255])
                ]
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'Meta description (SEO)',
                'help' => 'Description pour les moteurs de recherche (max. 160 caractères)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description SEO pour les moteurs de recherche',
                    'rows' => 2,
                    'maxlength' => 160,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Length(['max' => 160])
                ]
            ]);

        // Ajouter le sélecteur de langue uniquement en mode création
        if (!$isEditMode) {
            $builder->add('language', EntityType::class, [
                'class' => Language::class,
                'choice_label' => 'name',
                'label' => 'Langue',
                'placeholder' => 'Sélectionner une langue',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'La langue est obligatoire'])
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServiceTranslation::class,
            'is_edit_mode' => false,
        ]);
    }
}
