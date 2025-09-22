<?php

namespace App\Form;

use App\Entity\Service;
use App\Entity\Language;
use App\Entity\ServiceTranslation;
use App\Repository\LanguageRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceWithTranslationsType extends AbstractType
{
    public function __construct(
        private LanguageRepository $languageRepository
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Ajouter les champs de base du service
        $builder
            ->add('service', ServiceType::class, [
                'label' => false,
                'data' => $options['service']
            ]);

        // Listener pour initialiser les traductions
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();
            
            $service = $data['service'] ?? null;
            $activeLanguages = $this->languageRepository->findActiveLanguages();
            
            $translations = [];
            
            foreach ($activeLanguages as $language) {
                $translation = null;
                
                if ($service && $service->getId()) {
                    $translation = $service->getTranslation($language->getCode());
                }
                
                if (!$translation) {
                    $translation = new ServiceTranslation();
                    $translation->setLanguage($language);
                    if ($service) {
                        $translation->setTranslatable($service);
                    }
                }
                
                $translations[$language->getCode()] = $translation;
            }
            
            // Ajouter un champ pour chaque langue
            foreach ($translations as $locale => $translation) {
                $language = $translation->getLanguage();
                
                $form->add('translation_' . $locale, ServiceTranslationType::class, [
                    'label' => sprintf('Traduction %s (%s)', $language->getName(), $language->getNativeName()),
                    'data' => $translation,
                    'is_edit_mode' => true,
                    'attr' => [
                        'data-language' => $locale,
                        'data-language-name' => $language->getName()
                    ]
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'service' => null,
            'inherit_data' => false,
        ]);
    }
}
