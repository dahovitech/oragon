<?php

namespace App\Service;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class TranslationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LanguageRepository $languageRepository,
        private LocaleService $localeService,
        private CacheService $cacheService
    ) {
    }

    /**
     * Get translation for an entity in a specific language
     */
    public function getTranslation(object $entity, string $field, string $locale): ?string
    {
        // Generate cache key
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : spl_object_id($entity);
        $entityType = (new \ReflectionClass($entity))->getShortName();
        $cacheKey = "{$entityType}_{$entityId}_{$field}";
        
        // Try cache first
        $cachedTranslation = $this->cacheService->getTranslation($cacheKey, $locale);
        if ($cachedTranslation !== null) {
            return $cachedTranslation['value'] ?? null;
        }

        $translationEntity = $this->getTranslationEntity($entity, $locale);
        
        if (!$translationEntity) {
            // Cache null result to avoid repeated database queries
            $this->cacheService->setTranslation($cacheKey, $locale, ['value' => null], 1800); // 30 min for null
            return null;
        }

        $getter = 'get' . ucfirst($field);
        
        if (!method_exists($translationEntity, $getter)) {
            return null;
        }

        $value = $translationEntity->$getter();
        
        // Cache the result
        $this->cacheService->setTranslation($cacheKey, $locale, ['value' => $value]);
        
        return $value;
    }

    /**
     * Get translation with fallback to default language
     */
    public function getTranslationWithFallback(object $entity, string $field, string $locale, ?string $fallbackLocale = null): ?string
    {
        // Try primary locale
        $translation = $this->getTranslation($entity, $field, $locale);
        
        if ($translation !== null && $translation !== '') {
            return $translation;
        }

        // Try fallback locale
        $fallbackLocale = $fallbackLocale ?? $this->localeService->getDefaultLanguage()?->getCode() ?? 'fr';
        
        if ($locale !== $fallbackLocale) {
            $translation = $this->getTranslation($entity, $field, $fallbackLocale);
            
            if ($translation !== null && $translation !== '') {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Check if entity has translation for a specific language
     */
    public function hasTranslation(object $entity, string $locale): bool
    {
        return $this->getTranslationEntity($entity, $locale) !== null;
    }

    /**
     * Check if specific field is translated for a language
     */
    public function hasFieldTranslation(object $entity, string $field, string $locale): bool
    {
        $translation = $this->getTranslation($entity, $field, $locale);
        return $translation !== null && $translation !== '';
    }

    /**
     * Get translation completion percentage for an entity in a specific language
     */
    public function getCompletionPercentage(object $entity, string $locale): int
    {
        $translationEntity = $this->getTranslationEntity($entity, $locale);
        
        if (!$translationEntity) {
            return 0;
        }

        // Use entity's own completion method if available
        if (method_exists($translationEntity, 'getCompletionPercentage')) {
            return $translationEntity->getCompletionPercentage();
        }

        // Calculate based on common fields
        $fields = $this->getTranslatableFields($translationEntity);
        $completedFields = 0;

        foreach ($fields as $field) {
            $getter = 'get' . ucfirst($field);
            if (method_exists($translationEntity, $getter)) {
                $value = $translationEntity->$getter();
                if ($value !== null && $value !== '') {
                    $completedFields++;
                }
            }
        }

        return empty($fields) ? 0 : (int) round(($completedFields / count($fields)) * 100);
    }

    /**
     * Get completion statistics for all languages
     */
    public function getCompletionStats(object $entity): array
    {
        $stats = [];
        $languages = $this->localeService->getActiveLanguages();

        foreach ($languages as $language) {
            $stats[$language->getCode()] = [
                'language' => $language,
                'completion' => $this->getCompletionPercentage($entity, $language->getCode()),
                'has_translation' => $this->hasTranslation($entity, $language->getCode())
            ];
        }

        return $stats;
    }

    /**
     * Create or update translation for an entity
     */
    public function setTranslation(object $entity, string $locale, array $data): bool
    {
        $language = $this->languageRepository->findOneBy(['code' => $locale, 'isActive' => true]);
        
        if (!$language) {
            return false;
        }

        $translationEntity = $this->getTranslationEntity($entity, $locale);
        
        if (!$translationEntity) {
            $translationEntity = $this->createTranslationEntity($entity, $language);
        }

        // Set fields
        foreach ($data as $field => $value) {
            $setter = 'set' . ucfirst($field);
            if (method_exists($translationEntity, $setter)) {
                $translationEntity->$setter($value);
                
                // Invalidate cache for this field
                $entityId = method_exists($entity, 'getId') ? $entity->getId() : spl_object_id($entity);
                $entityType = (new \ReflectionClass($entity))->getShortName();
                $cacheKey = "{$entityType}_{$entityId}_{$field}";
                
                // Update cache with new value
                $this->cacheService->setTranslation($cacheKey, $locale, ['value' => $value]);
            }
        }

        // Update timestamp if method exists
        if (method_exists($translationEntity, 'setUpdatedAt')) {
            $translationEntity->setUpdatedAt();
        }

        $this->entityManager->persist($translationEntity);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Delete translation for a specific language
     */
    public function deleteTranslation(object $entity, string $locale): bool
    {
        $translationEntity = $this->getTranslationEntity($entity, $locale);
        
        if (!$translationEntity) {
            return false;
        }

        $this->entityManager->remove($translationEntity);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Copy translation from one language to another
     */
    public function copyTranslation(object $entity, string $fromLocale, string $toLocale): bool
    {
        $sourceTranslation = $this->getTranslationEntity($entity, $fromLocale);
        
        if (!$sourceTranslation) {
            return false;
        }

        $targetLanguage = $this->languageRepository->findOneBy(['code' => $toLocale, 'isActive' => true]);
        
        if (!$targetLanguage) {
            return false;
        }

        // Get translatable fields
        $fields = $this->getTranslatableFields($sourceTranslation);
        $data = [];

        foreach ($fields as $field) {
            $getter = 'get' . ucfirst($field);
            if (method_exists($sourceTranslation, $getter)) {
                $data[$field] = $sourceTranslation->$getter();
            }
        }

        return $this->setTranslation($entity, $toLocale, $data);
    }

    /**
     * Get all missing translations for an entity
     */
    public function getMissingTranslations(object $entity): array
    {
        $missing = [];
        $languages = $this->localeService->getActiveLanguages();

        foreach ($languages as $language) {
            if (!$this->hasTranslation($entity, $language->getCode())) {
                $missing[] = $language;
            }
        }

        return $missing;
    }

    /**
     * Get incomplete translations (partially translated)
     */
    public function getIncompleteTranslations(object $entity, int $threshold = 100): array
    {
        $incomplete = [];
        $languages = $this->localeService->getActiveLanguages();

        foreach ($languages as $language) {
            $completion = $this->getCompletionPercentage($entity, $language->getCode());
            if ($completion > 0 && $completion < $threshold) {
                $incomplete[] = [
                    'language' => $language,
                    'completion' => $completion
                ];
            }
        }

        return $incomplete;
    }

    /**
     * Auto-translate missing fields using a source translation
     */
    public function autoFillMissingFields(object $entity, string $targetLocale, string $sourceLocale = 'fr'): bool
    {
        $sourceTranslation = $this->getTranslationEntity($entity, $sourceLocale);
        $targetTranslation = $this->getTranslationEntity($entity, $targetLocale);
        
        if (!$sourceTranslation || !$targetTranslation) {
            return false;
        }

        $fields = $this->getTranslatableFields($sourceTranslation);
        $updated = false;

        foreach ($fields as $field) {
            $getter = 'get' . ucfirst($field);
            $setter = 'set' . ucfirst($field);
            
            if (method_exists($sourceTranslation, $getter) && method_exists($targetTranslation, $setter)) {
                $targetValue = $targetTranslation->$getter();
                
                // Only fill if target is empty
                if (empty($targetValue)) {
                    $sourceValue = $sourceTranslation->$getter();
                    if (!empty($sourceValue)) {
                        $targetTranslation->$setter($sourceValue . ' [AUTO-FILLED]');
                        $updated = true;
                    }
                }
            }
        }

        if ($updated) {
            $this->entityManager->flush();
        }

        return $updated;
    }

    /**
     * Get translation entity for a specific locale
     */
    private function getTranslationEntity(object $entity, string $locale): ?object
    {
        if (!method_exists($entity, 'getTranslations')) {
            return null;
        }

        $translations = $entity->getTranslations();
        
        foreach ($translations as $translation) {
            if ($translation->getLanguage()?->getCode() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Create new translation entity
     */
    private function createTranslationEntity(object $entity, Language $language): ?object
    {
        $entityClass = get_class($entity);
        $translationClass = $entityClass . 'Translation';

        if (!class_exists($translationClass)) {
            return null;
        }

        $translationEntity = new $translationClass();
        
        // Set the main entity relationship
        $mainEntitySetter = 'set' . $this->getEntityName($entity);
        if (method_exists($translationEntity, $mainEntitySetter)) {
            $translationEntity->$mainEntitySetter($entity);
        }

        // Set language
        if (method_exists($translationEntity, 'setLanguage')) {
            $translationEntity->setLanguage($language);
        }

        // Add to main entity's translations collection
        if (method_exists($entity, 'addTranslation')) {
            $entity->addTranslation($translationEntity);
        }

        return $translationEntity;
    }

    /**
     * Get entity name from class
     */
    private function getEntityName(object $entity): string
    {
        $reflection = new \ReflectionClass($entity);
        return lcfirst($reflection->getShortName());
    }

    /**
     * Get translatable fields from a translation entity
     */
    private function getTranslatableFields(object $translationEntity): array
    {
        $commonFields = ['name', 'title', 'description', 'content', 'excerpt', 'metaTitle', 'metaDescription', 'slug'];
        $fields = [];

        foreach ($commonFields as $field) {
            if (method_exists($translationEntity, 'get' . ucfirst($field))) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Bulk translate multiple entities
     */
    public function bulkTranslate(array $entities, string $fromLocale, string $toLocale): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($entities as $entity) {
            try {
                if ($this->copyTranslation($entity, $fromLocale, $toLocale)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to translate entity ID: " . $entity->getId();
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error translating entity ID " . $entity->getId() . ": " . $e->getMessage();
            }
        }

        return $results;
    }
}