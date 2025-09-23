<?php

namespace App\Service;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class TranslationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LanguageRepository $languageRepository,
        private SluggerInterface $slugger,
        private LocaleService $localeService
    ) {}

    /**
     * Get translation for an entity and field
     */
    public function getTranslation(object $entity, string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?? $this->localeService->getCurrentLocale();
        
        // Try to get the specific translation method
        $methodName = 'getTranslation';
        if (method_exists($entity, $methodName)) {
            $translation = $entity->$methodName($locale);
            if ($translation && method_exists($translation, 'get' . ucfirst($field))) {
                $value = $translation->{'get' . ucfirst($field)}();
                if (!empty($value)) {
                    return $value;
                }
            }
        }

        // Fallback to default language
        $defaultLocale = $this->localeService->getDefaultLocale();
        if ($locale !== $defaultLocale && method_exists($entity, $methodName)) {
            $translation = $entity->$methodName($defaultLocale);
            if ($translation && method_exists($translation, 'get' . ucfirst($field))) {
                return $translation->{'get' . ucfirst($field)}();
            }
        }

        return null;
    }

    /**
     * Check if translation exists for an entity and field
     */
    public function hasTranslation(object $entity, string $field, ?string $locale = null): bool
    {
        $translation = $this->getTranslation($entity, $field, $locale);
        return !empty($translation);
    }

    /**
     * Get translation or fallback value
     */
    public function getTranslationOrFallback(object $entity, string $field, ?string $locale = null, ?string $fallback = null): string
    {
        $translation = $this->getTranslation($entity, $field, $locale);
        
        if (!empty($translation)) {
            return $translation;
        }

        // Try default locale
        $defaultLocale = $this->localeService->getDefaultLocale();
        if ($locale !== $defaultLocale) {
            $translation = $this->getTranslation($entity, $field, $defaultLocale);
            if (!empty($translation)) {
                return $translation;
            }
        }

        return $fallback ?? '';
    }

    /**
     * Set translation for an entity
     */
    public function setTranslation(object $entity, string $field, string $value, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->localeService->getCurrentLocale();
        $language = $this->languageRepository->findByCode($locale);
        
        if (!$language || !$language->isActive()) {
            return false;
        }

        // Try to get the specific translation method
        $getTranslationMethod = 'getTranslation';
        $addTranslationMethod = 'addTranslation';
        
        if (!method_exists($entity, $getTranslationMethod) || !method_exists($entity, $addTranslationMethod)) {
            return false;
        }

        $translation = $entity->$getTranslationMethod($locale);
        
        // Create new translation if doesn't exist
        if (!$translation) {
            $entityClass = get_class($entity);
            $translationClass = $entityClass . 'Translation';
            
            if (!class_exists($translationClass)) {
                return false;
            }

            $translation = new $translationClass();
            $translation->setLanguage($language);
            
            // Set the main entity reference
            $entityName = strtolower((new \ReflectionClass($entity))->getShortName());
            $setEntityMethod = 'set' . ucfirst($entityName);
            if (method_exists($translation, $setEntityMethod)) {
                $translation->$setEntityMethod($entity);
            }

            $entity->$addTranslationMethod($translation);
        }

        // Set the field value
        $setMethod = 'set' . ucfirst($field);
        if (method_exists($translation, $setMethod)) {
            $translation->$setMethod($value);
            $this->entityManager->persist($translation);
            return true;
        }

        return false;
    }

    /**
     * Get completion stats for an entity
     */
    public function getCompletionStats(object $entity, ?string $locale = null): array
    {
        $locale = $locale ?? $this->localeService->getCurrentLocale();
        $translation = null;
        
        $getTranslationMethod = 'getTranslation';
        if (method_exists($entity, $getTranslationMethod)) {
            $translation = $entity->$getTranslationMethod($locale);
        }

        if (!$translation) {
            return [
                'total' => 0,
                'completed' => 0,
                'percentage' => 0,
                'missing_fields' => []
            ];
        }

        // Get translatable fields from the translation entity
        $reflection = new \ReflectionClass($translation);
        $translatableFields = [];
        $completedFields = 0;
        $missingFields = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();
            
            // Skip system fields
            if (in_array($propertyName, ['id', 'language', 'createdAt', 'updatedAt'])) {
                continue;
            }
            
            // Skip foreign key relations to main entity
            if (strpos($propertyName, 'Id') !== false || $property->getType()?->getName() === get_class($entity)) {
                continue;
            }

            $translatableFields[] = $propertyName;
            
            $getMethod = 'get' . ucfirst($propertyName);
            if (method_exists($translation, $getMethod)) {
                $value = $translation->$getMethod();
                if (!empty($value)) {
                    $completedFields++;
                } else {
                    $missingFields[] = $propertyName;
                }
            }
        }

        $total = count($translatableFields);
        $percentage = $total > 0 ? round(($completedFields / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'completed' => $completedFields,
            'percentage' => $percentage,
            'missing_fields' => $missingFields
        ];
    }

    /**
     * Bulk translate from one locale to another
     */
    public function bulkTranslate(array $entities, string $fromLocale, string $toLocale, bool $overwrite = false): int
    {
        $fromLanguage = $this->languageRepository->findByCode($fromLocale);
        $toLanguage = $this->languageRepository->findByCode($toLocale);
        
        if (!$fromLanguage || !$toLanguage || !$toLanguage->isActive()) {
            return 0;
        }

        $translated = 0;

        foreach ($entities as $entity) {
            if (!method_exists($entity, 'getTranslation') || !method_exists($entity, 'hasTranslation')) {
                continue;
            }

            // Skip if target translation exists and we don't want to overwrite
            if (!$overwrite && $entity->hasTranslation($toLocale)) {
                continue;
            }

            $sourceTranslation = $entity->getTranslation($fromLocale);
            if (!$sourceTranslation) {
                continue;
            }

            // Copy all translatable fields
            $reflection = new \ReflectionClass($sourceTranslation);
            foreach ($reflection->getProperties() as $property) {
                $propertyName = $property->getName();
                
                // Skip system fields
                if (in_array($propertyName, ['id', 'language', 'createdAt', 'updatedAt'])) {
                    continue;
                }
                
                // Skip foreign key relations
                if (strpos($propertyName, 'Id') !== false || $property->getType()?->getName() === get_class($entity)) {
                    continue;
                }

                $getMethod = 'get' . ucfirst($propertyName);
                if (method_exists($sourceTranslation, $getMethod)) {
                    $value = $sourceTranslation->$getMethod();
                    if (!empty($value)) {
                        $this->setTranslation($entity, $propertyName, $value, $toLocale);
                    }
                }
            }

            $translated++;
        }

        if ($translated > 0) {
            $this->entityManager->flush();
        }

        return $translated;
    }

    /**
     * Generate unique slug for translatable entity
     */
    public function generateUniqueSlug(string $title, object $entity, ?string $locale = null): string
    {
        $locale = $locale ?? $this->localeService->getCurrentLocale();
        $baseSlug = $this->slugger->slug($title)->lower();
        $slug = $baseSlug;
        $counter = 1;

        // Get repository for the entity
        $repository = $this->entityManager->getRepository(get_class($entity));
        
        // Check if repository has a method to find by slug
        if (method_exists($repository, 'findBySlug')) {
            while ($repository->findBySlug($slug)) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
        }

        return $slug;
    }

    /**
     * Create missing translations for an entity
     */
    public function createMissingTranslations(object $entity, ?string $sourceLocale = null): int
    {
        $sourceLocale = $sourceLocale ?? $this->localeService->getDefaultLocale();
        $activeLanguages = $this->localeService->getActiveLanguages();
        $created = 0;

        if (!method_exists($entity, 'getTranslation') || !method_exists($entity, 'hasTranslation')) {
            return 0;
        }

        $sourceTranslation = $entity->getTranslation($sourceLocale);
        if (!$sourceTranslation) {
            return 0;
        }

        foreach ($activeLanguages as $language) {
            if ($language->getCode() === $sourceLocale) {
                continue;
            }

            if ($entity->hasTranslation($language->getCode())) {
                continue;
            }

            // Copy from source translation
            $reflection = new \ReflectionClass($sourceTranslation);
            foreach ($reflection->getProperties() as $property) {
                $propertyName = $property->getName();
                
                // Skip system fields
                if (in_array($propertyName, ['id', 'language', 'createdAt', 'updatedAt'])) {
                    continue;
                }
                
                // Skip foreign key relations
                if (strpos($propertyName, 'Id') !== false || $property->getType()?->getName() === get_class($entity)) {
                    continue;
                }

                $getMethod = 'get' . ucfirst($propertyName);
                if (method_exists($sourceTranslation, $getMethod)) {
                    $value = $sourceTranslation->$getMethod();
                    if (!empty($value)) {
                        $this->setTranslation($entity, $propertyName, $value, $language->getCode());
                    }
                }
            }

            $created++;
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        return $created;
    }

    /**
     * Remove translation for an entity and locale
     */
    public function removeTranslation(object $entity, string $locale): bool
    {
        if (!method_exists($entity, 'getTranslation') || !method_exists($entity, 'removeTranslation')) {
            return false;
        }

        $translation = $entity->getTranslation($locale);
        if (!$translation) {
            return false;
        }

        $entity->removeTranslation($translation);
        $this->entityManager->remove($translation);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get all translations for an entity
     */
    public function getAllTranslations(object $entity): array
    {
        if (!method_exists($entity, 'getTranslations')) {
            return [];
        }

        $translations = [];
        foreach ($entity->getTranslations() as $translation) {
            if (method_exists($translation, 'getLanguage')) {
                $language = $translation->getLanguage();
                if ($language) {
                    $translations[$language->getCode()] = $translation;
                }
            }
        }

        return $translations;
    }
}