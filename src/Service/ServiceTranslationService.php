<?php

namespace App\Service;

use App\Entity\Service;
use App\Entity\ServiceTranslation;
use App\Entity\Language;
use App\Repository\ServiceRepository;
use App\Repository\ServiceTranslationRepository;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour la gestion centralisée des traductions de services
 */
class ServiceTranslationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceRepository $serviceRepository,
        private ServiceTranslationRepository $serviceTranslationRepository,
        private LanguageRepository $languageRepository
    ) {}

    /**
     * Sauvegarder un service avec ses traductions
     */
    public function saveServiceWithTranslations(Service $service, array $translationsData): Service
    {
        // Sauvegarder d'abord le service
        if (!$service->getId()) {
            $this->entityManager->persist($service);
            $this->entityManager->flush(); // Flush pour obtenir l'ID
        }

        // Traiter chaque traduction
        foreach ($translationsData as $locale => $translationData) {
            if ($translationData instanceof ServiceTranslation) {
                $translation = $translationData;
                
                // S'assurer que la traduction est liée au service
                if (!$translation->getTranslatable()) {
                    $translation->setTranslatable($service);
                }
                
                // Sauvegarder seulement si la traduction a du contenu
                if ($this->isTranslationValid($translation)) {
                    $this->entityManager->persist($translation);
                }
            }
        }

        $this->entityManager->flush();
        
        return $service;
    }

    /**
     * Créer une nouvelle traduction pour un service
     */
    public function createTranslation(Service $service, Language $language, array $data): ServiceTranslation
    {
        // Vérifier si une traduction existe déjà
        $existingTranslation = $this->serviceTranslationRepository->findByServiceAndLanguage($service, $language);
        
        if ($existingTranslation) {
            throw new \InvalidArgumentException(
                sprintf('Une traduction existe déjà pour le service "%s" en %s', $service->getSlug(), $language->getName())
            );
        }

        $translation = new ServiceTranslation();
        $translation->setTranslatable($service)
                   ->setLanguage($language)
                   ->setTitle($data['title'] ?? '')
                   ->setDescription($data['description'] ?? null)
                   ->setContent($data['content'] ?? null)
                   ->setMetaTitle($data['metaTitle'] ?? null)
                   ->setMetaDescription($data['metaDescription'] ?? null);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        return $translation;
    }

    /**
     * Dupliquer une traduction d'une langue vers une autre
     */
    public function duplicateTranslation(Service $service, string $sourceLocale, string $targetLocale): ServiceTranslation
    {
        $sourceLanguage = $this->languageRepository->findActiveByCode($sourceLocale);
        $targetLanguage = $this->languageRepository->findActiveByCode($targetLocale);

        if (!$sourceLanguage || !$targetLanguage) {
            throw new \InvalidArgumentException('Langue source ou cible introuvable');
        }

        $sourceTranslation = $this->serviceTranslationRepository->findByServiceAndLanguage($service, $sourceLanguage);
        if (!$sourceTranslation) {
            throw new \InvalidArgumentException('Traduction source introuvable');
        }

        // Vérifier si la traduction cible existe déjà
        $existingTargetTranslation = $this->serviceTranslationRepository->findByServiceAndLanguage($service, $targetLanguage);
        if ($existingTargetTranslation) {
            throw new \InvalidArgumentException('Une traduction existe déjà dans la langue cible');
        }

        $targetTranslation = new ServiceTranslation();
        $targetTranslation->setTranslatable($service)
                         ->setLanguage($targetLanguage)
                         ->copyFrom($sourceTranslation);

        $this->entityManager->persist($targetTranslation);
        $this->entityManager->flush();

        return $targetTranslation;
    }

    /**
     * Supprimer une traduction
     */
    public function deleteTranslation(ServiceTranslation $translation): void
    {
        $this->entityManager->remove($translation);
        $this->entityManager->flush();
    }

    /**
     * Obtenir les statistiques de traduction pour tous les services
     */
    public function getTranslationStatistics(): array
    {
        return $this->serviceRepository->getTranslationStatistics();
    }

    /**
     * Obtenir les services qui ont besoin de traduction pour une langue
     */
    public function getServicesNeedingTranslation(string $locale): array
    {
        return $this->serviceRepository->findServicesNeedingTranslation($locale);
    }

    /**
     * Initialiser les traductions manquantes pour un service
     */
    public function initializeMissingTranslations(Service $service): int
    {
        $activeLanguages = $this->languageRepository->findActiveLanguages();
        $created = 0;

        foreach ($activeLanguages as $language) {
            $existingTranslation = $this->serviceTranslationRepository->findByServiceAndLanguage($service, $language);
            
            if (!$existingTranslation) {
                $translation = new ServiceTranslation();
                $translation->setTranslatable($service)
                           ->setLanguage($language)
                           ->setTitle(''); // Titre vide pour indiquer qu'il faut le remplir

                $this->entityManager->persist($translation);
                $created++;
            }
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        return $created;
    }

    /**
     * Vérifier si une traduction est valide (a du contenu utile)
     */
    private function isTranslationValid(ServiceTranslation $translation): bool
    {
        return !empty(trim($translation->getTitle()));
    }

    /**
     * Obtenir le pourcentage de complétion global des traductions
     */
    public function getGlobalCompletionPercentage(): int
    {
        $stats = $this->getTranslationStatistics();
        return $stats['global_completion'] ?? 0;
    }

    /**
     * Obtenir les traductions récemment modifiées
     */
    public function getRecentlyUpdatedTranslations(int $limit = 10): array
    {
        return $this->serviceTranslationRepository->findRecentlyUpdated($limit);
    }

    /**
     * Synchroniser un service avec toutes les langues actives
     */
    public function synchronizeServiceWithLanguages(Service $service): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'errors' => []
        ];

        $activeLanguages = $this->languageRepository->findActiveLanguages();

        foreach ($activeLanguages as $language) {
            try {
                $existingTranslation = $this->serviceTranslationRepository->findByServiceAndLanguage($service, $language);
                
                if (!$existingTranslation) {
                    $translation = new ServiceTranslation();
                    $translation->setTranslatable($service)
                               ->setLanguage($language)
                               ->setTitle(''); // Titre vide à remplir

                    $this->entityManager->persist($translation);
                    $result['created']++;
                }
            } catch (\Exception $e) {
                $result['errors'][$language->getCode()] = $e->getMessage();
            }
        }

        if ($result['created'] > 0) {
            $this->entityManager->flush();
        }

        return $result;
    }
}
