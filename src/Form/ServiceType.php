<?php

namespace App\Form;

use App\Entity\Service;
use App\Entity\Media;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'help' => 'Identifiant unique pour l\'URL (ex: mon-service)',
                'attr' => [
                    'placeholder' => 'mon-service',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le slug est obligatoire'])
                ]
            ])
            ->add('image', EntityType::class, [
                'class' => Media::class,
                'choice_label' => 'alt',
                'label' => 'Image',
                'required' => false,
                'placeholder' => 'Sélectionner une image',
                'attr' => ['class' => 'form-control']
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Service actif',
                'help' => 'Décochez pour désactiver ce service',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'help' => 'Plus petit = affiché en premier',
                'attr' => [
                    'min' => 0,
                    'max' => 999,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Range(['min' => 0, 'max' => 999])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
        ]);
    }
}
