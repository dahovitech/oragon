<?php

namespace App\Form;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentVerificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', ChoiceType::class, [
                'label' => 'Statut de vérification',
                'choices' => [
                    'Approuvé' => Document::STATUS_APPROVED,
                    'Rejeté' => Document::STATUS_REJECTED,
                    'En attente' => Document::STATUS_PENDING,
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('rejectionReason', TextareaType::class, [
                'label' => 'Commentaire / Raison du rejet',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Indiquez la raison du rejet ou un commentaire (optionnel pour les approbations)'
                ],
                'help' => 'Obligatoire en cas de rejet, optionnel pour les autres statuts'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
        ]);
    }
}