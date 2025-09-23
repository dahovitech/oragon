<?php

namespace App\Form;

use App\Entity\Blog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlogType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Title', 'required' => true])
            ->add('slug', TextType::class, ['label' => 'Slug', 'required' => true])
            ->add('excerpt', TextareaType::class, ['label' => 'Excerpt', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('content', TextareaType::class, ['label' => 'Content', 'required' => true, 'attr' => ['rows' => 10]])
            ->add('isPublished', CheckboxType::class, ['label' => 'Published', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Blog::class]);
    }
}
