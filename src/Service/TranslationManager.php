<?php

namespace App\Service;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Serializer\SerializerInterface;

class TranslationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslationService $translationService,
        private LocaleService $localeService,
        private LanguageRepository $languageRepository,
        private ?SerializerInterface $serializer = null
    ) {
    }

    /**
     * Get global translation statistics
     */
    public function getGlobalStats(): array
    {
        $languages = $this->localeService->getActiveLanguages();
        $entityTypes = $this->getTranslatableEntityTypes();
        
        $stats = [
            'languages' => count($languages),
            'entity_types' => count($entityTypes),
            'total_translations' => 0,
            'completion_by_language' => [],
            'completion_by_entity' => []
        ];

        foreach ($languages as $language) {
            $languageStats = $this->getLanguageStats($language->getCode());
            $stats['completion_by_language'][$language->getCode()] = $languageStats;
            $stats['total_translations'] += $languageStats['total_entities'];
        }

        foreach ($entityTypes as $entityType) {
            $stats['completion_by_entity'][$entityType] = $this->getEntityTypeStats($entityType);
        }

        return $stats;
    }

    /**
     * Get translation statistics for a specific language
     */
    public function getLanguageStats(string $locale): array
    {
        $entityTypes = $this->getTranslatableEntityTypes();
        $stats = [
            'locale' => $locale,
            'total_entities' => 0,
            'translated_entities' => 0,
            'completion_percentage' => 0,
            'by_entity_type' => []
        ];

        foreach ($entityTypes as $entityType) {
            $entityStats = $this->getEntityTypeStatsForLanguage($entityType, $locale);
            $stats['by_entity_type'][$entityType] = $entityStats;
            $stats['total_entities'] += $entityStats['total'];
            $stats['translated_entities'] += $entityStats['translated'];
        }

        $stats['completion_percentage'] = $stats['total_entities'] > 0 
            ? round(($stats['translated_entities'] / $stats['total_entities']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * Get statistics for a specific entity type
     */
    public function getEntityTypeStats(string $entityType): array
    {
        $languages = $this->localeService->getActiveLanguages();
        $repository = $this->entityManager->getRepository($entityType);
        $totalEntities = $repository->count([]);

        $stats = [
            'entity_type' => $entityType,
            'total_entities' => $totalEntities,
            'by_language' => []
        ];

        foreach ($languages as $language) {
            $languageStats = $this->getEntityTypeStatsForLanguage($entityType, $language->getCode());
            $stats['by_language'][$language->getCode()] = $languageStats;
        }

        return $stats;
    }

    /**
     * Get statistics for a specific entity type in a specific language
     */
    private function getEntityTypeStatsForLanguage(string $entityType, string $locale): array
    {
        $repository = $this->entityManager->getRepository($entityType);
        $entities = $repository->findAll();
        
        $total = count($entities);
        $translated = 0;
        $completionSum = 0;

        foreach ($entities as $entity) {
            if ($this->translationService->hasTranslation($entity, $locale)) {
                $translated++;
            }
            $completionSum += $this->translationService->getCompletionPercentage($entity, $locale);
        }

        return [
            'total' => $total,
            'translated' => $translated,
            'completion_percentage' => $total > 0 ? round($completionSum / $total, 2) : 0,
            'translation_percentage' => $total > 0 ? round(($translated / $total) * 100, 2) : 0
        ];
    }

    /**
     * Export translations to array format
     */
    public function exportTranslations(string $entityType, ?string $locale = null): array
    {
        $repository = $this->entityManager->getRepository($entityType);
        $entities = $repository->findAll();
        $languages = $locale ? [$this->languageRepository->findOneBy(['code' => $locale])] : $this->localeService->getActiveLanguages();
        
        $export = [];

        foreach ($entities as $entity) {
            $entityData = [
                'id' => $entity->getId(),
                'translations' => []
            ];

            foreach ($languages as $language) {
                if (!$language) continue;
                
                $translation = $this->getTranslationEntityForExport($entity, $language->getCode());
                if ($translation) {
                    $entityData['translations'][$language->getCode()] = $this->serializeTranslation($translation);
                }
            }

            $export[] = $entityData;
        }

        return $export;
    }

    /**
     * Import translations from array format
     */
    public function importTranslations(string $entityType, array $data): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $repository = $this->entityManager->getRepository($entityType);

        foreach ($data as $entityData) {
            try {
                $entity = $repository->find($entityData['id']);
                
                if (!$entity) {
                    $results['errors'][] = "Entity with ID {$entityData['id']} not found";
                    $results['failed']++;
                    continue;
                }

                foreach ($entityData['translations'] as $locale => $translationData) {
                    if ($this->translationService->setTranslation($entity, $locale, $translationData)) {
                        $results['success']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "Failed to import translation for entity {$entityData['id']}, locale {$locale}";
                    }
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error importing entity {$entityData['id']}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Find entities missing translations in specific languages
     */
    public function findMissingTranslations(string $entityType, array $locales = []): array
    {
        if (empty($locales)) {
            $locales = array_map(fn($lang) => $lang->getCode(), $this->localeService->getActiveLanguages());
        }

        $repository = $this->entityManager->getRepository($entityType);
        $entities = $repository->findAll();
        $missing = [];

        foreach ($entities as $entity) {
            $entityMissing = [];
            
            foreach ($locales as $locale) {
                if (!$this->translationService->hasTranslation($entity, $locale)) {
                    $entityMissing[] = $locale;
                }
            }

            if (!empty($entityMissing)) {
                $missing[] = [
                    'entity' => $entity,
                    'missing_locales' => $entityMissing
                ];
            }
        }

        return $missing;
    }

    /**
     * Find entities with incomplete translations
     */
    public function findIncompleteTranslations(string $entityType, int $threshold = 100): array
    {
        $repository = $this->entityManager->getRepository($entityType);
        $entities = $repository->findAll();
        $incomplete = [];

        foreach ($entities as $entity) {
            $entityIncomplete = [];
            $languages = $this->localeService->getActiveLanguages();
            
            foreach ($languages as $language) {
                $completion = $this->translationService->getCompletionPercentage($entity, $language->getCode());
                
                if ($completion > 0 && $completion < $threshold) {
                    $entityIncomplete[] = [
                        'locale' => $language->getCode(),
                        'completion' => $completion
                    ];
                }
            }

            if (!empty($entityIncomplete)) {
                $incomplete[] = [
                    'entity' => $entity,
                    'incomplete_translations' => $entityIncomplete
                ];
            }
        }

        return $incomplete;
    }

    /**
     * Bulk copy translations from one language to another
     */
    public function bulkCopyTranslations(string $entityType, string $fromLocale, string $toLocale, bool $overwrite = false): array
    {
        $repository = $this->entityManager->getRepository($entityType);
        $entities = $repository->findAll();
        
        $results = [
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($entities as $entity) {
            try {
                // Skip if target translation exists and overwrite is false
                if (!$overwrite && $this->translationService->hasTranslation($entity, $toLocale)) {
                    $results['skipped']++;
                    continue;
                }

                if ($this->translationService->copyTranslation($entity, $fromLocale, $toLocale)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to copy translation for entity ID: " . $entity->getId();
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error copying translation for entity ID " . $entity->getId() . ": " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Clean up orphaned translations (translations without parent entity)
     */
    public function cleanupOrphanedTranslations(): array
    {
        $results = [
            'cleaned' => 0,
            'errors' => []
        ];

        $entityTypes = $this->getTranslatableEntityTypes();

        foreach ($entityTypes as $entityType) {
            $translationClass = $entityType . 'Translation';
            
            if (!class_exists($translationClass)) {
                continue;
            }

            try {
                $repository = $this->entityManager->getRepository($translationClass);
                $translations = $repository->findAll();

                foreach ($translations as $translation) {
                    $mainEntityGetter = 'get' . $this->getEntityNameFromClass($entityType);
                    
                    if (method_exists($translation, $mainEntityGetter)) {
                        $mainEntity = $translation->$mainEntityGetter();
                        
                        if (!$mainEntity) {
                            $this->entityManager->remove($translation);
                            $results['cleaned']++;
                        }
                    }
                }

                $this->entityManager->flush();
            } catch (\Exception $e) {
                $results['errors'][] = "Error cleaning {$translationClass}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Generate translation report
     */
    public function generateReport(): array
    {
        $report = [
            'generated_at' => new \DateTimeImmutable(),
            'global_stats' => $this->getGlobalStats(),
            'missing_translations' => [],
            'incomplete_translations' => [],
            'recommendations' => []
        ];

        $entityTypes = $this->getTranslatableEntityTypes();
        
        foreach ($entityTypes as $entityType) {
            $missing = $this->findMissingTranslations($entityType);
            if (!empty($missing)) {
                $report['missing_translations'][$entityType] = $missing;
            }

            $incomplete = $this->findIncompleteTranslations($entityType, 90);
            if (!empty($incomplete)) {
                $report['incomplete_translations'][$entityType] = $incomplete;
            }
        }

        // Generate recommendations
        $report['recommendations'] = $this->generateRecommendations($report);

        return $report;
    }

    /**
     * Get list of translatable entity types
     */
    private function getTranslatableEntityTypes(): array
    {
        // Return entity classes that have translation entities
        return [
            'App\Entity\Product',
            'App\Entity\Category',
            'App\Entity\Brand',
            'App\Entity\Attribute',
            'App\Entity\AttributeValue',
            'App\Entity\Page',
            'App\Entity\Blog'
        ];
    }

    /**
     * Get entity name from class name
     */
    private function getEntityNameFromClass(string $entityClass): string
    {
        $parts = explode('\\', $entityClass);
        return lcfirst(end($parts));
    }

    /**
     * Get translation entity for export
     */
    private function getTranslationEntityForExport(object $entity, string $locale): ?object
    {
        if (!method_exists($entity, 'getTranslations')) {
            return null;
        }

        foreach ($entity->getTranslations() as $translation) {
            if ($translation->getLanguage()?->getCode() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Serialize translation entity to array
     */
    private function serializeTranslation(object $translation): array
    {
        $data = [];
        $commonFields = ['name', 'title', 'description', 'content', 'excerpt', 'metaTitle', 'metaDescription', 'slug'];

        foreach ($commonFields as $field) {
            $getter = 'get' . ucfirst($field);
            if (method_exists($translation, $getter)) {
                $data[$field] = $translation->$getter();
            }
        }

        return $data;
    }

    /**
     * Generate recommendations based on report data
     */
    private function generateRecommendations(array $report): array
    {
        $recommendations = [];

        // Check for languages with low completion rates
        foreach ($report['global_stats']['completion_by_language'] as $locale => $stats) {
            if ($stats['completion_percentage'] < 50) {
                $recommendations[] = [
                    'type' => 'low_completion',
                    'message' => "Language '{$locale}' has low completion rate ({$stats['completion_percentage']}%). Consider prioritizing translations for this language.",
                    'priority' => 'high'
                ];
            }
        }

        // Check for entity types with many missing translations
        foreach ($report['missing_translations'] as $entityType => $missing) {
            if (count($missing) > 10) {
                $recommendations[] = [
                    'type' => 'many_missing',
                    'message' => "Entity type '{$entityType}' has many entities missing translations (" . count($missing) . "). Consider bulk translation operations.",
                    'priority' => 'medium'
                ];
            }
        }

        return $recommendations;
    }
}