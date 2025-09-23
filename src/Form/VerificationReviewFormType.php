<?php

namespace App\Form;

use App\Entity\AccountVerification;
use App\Enum\VerificationStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class VerificationReviewFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class, [
                'class' => VerificationStatus::class,
                'label' => 'Statut de vérification',
                'choice_label' => function (VerificationStatus $status) {
                    return match($status) {
                        VerificationStatus::PENDING => 'En attente',
                        VerificationStatus::VERIFIED => 'Vérifié',
                        VerificationStatus::REJECTED => 'Rejeté',
                    };
                },
                'attr' => ['class' => 'form-select'],
                'constraints' => [new NotBlank()]
            ])
            ->add('comments', TextareaType::class, [
                'label' => 'Commentaires de vérification',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Ajoutez vos commentaires sur cette vérification...'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer la décision',
                'attr' => ['class' => 'btn btn-primary']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AccountVerification::class,
        ]);
    }
}