<?php

namespace App\Service;

use App\Entity\Language;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\Category;
use App\Entity\CategoryTranslation;
use App\Entity\Brand;
use App\Entity\BrandTranslation;
use App\Entity\Attribute;
use App\Entity\AttributeTranslation;
use App\Entity\AttributeValue;
use App\Entity\AttributeValueTranslation;
use App\Entity\Page;
use App\Entity\PageTranslation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service for managing e-commerce translations
 */
class EcommerceTranslationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger
    ) {
    }

    /**
     * Create or update product with translations
     */
    public function createOrUpdateProduct(Product $product, array $translationsData): Product
    {
        $languages = $this->getActiveLanguages();

        foreach ($languages as $language) {
            $languageCode = $language->getCode();
            
            if (!isset($translationsData[$languageCode])) {
                continue;
            }

            $data = $translationsData[$languageCode];
            $translation = $product->getTranslation($languageCode);

            if (!$translation) {
                $translation = new ProductTranslation();
                $translation->setProduct($product);
                $translation->setLanguage($language);
                $product->addTranslation($translation);
            }

            if (isset($data['name'])) {
                $translation->setName($data['name']);
            }

            if (isset($data['description'])) {
                $translation->setDescription($data['description']);
            }

            if (isset($data['shortDescription'])) {
                $translation->setShortDescription($data['shortDescription']);
            }

            if (isset($data['metaTitle'])) {
                $translation->setMetaTitle($data['metaTitle']);
            }

            if (isset($data['metaDescription'])) {
                $translation->setMetaDescription($data['metaDescription']);
            }

            // Auto-generate slug if not provided
            if (!isset($data['slug']) && isset($data['name'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name'], ProductTranslation::class, $languageCode);
            }

            if (isset($data['slug'])) {
                $translation->setSlug($data['slug']);
            }

            $translation->setUpdatedAt();
        }

        return $product;
    }

    /**
     * Create or update category with translations
     */
    public function createOrUpdateCategory(Category $category, array $translationsData): Category
    {
        $languages = $this->getActiveLanguages();

        foreach ($languages as $language) {
            $languageCode = $language->getCode();
            
            if (!isset($translationsData[$languageCode])) {
                continue;
            }

            $data = $translationsData[$languageCode];
            $translation = $category->getTranslation($languageCode);

            if (!$translation) {
                $translation = new CategoryTranslation();
                $translation->setCategory($category);
                $translation->setLanguage($language);
                $category->addTranslation($translation);
            }

            if (isset($data['name'])) {
                $translation->setName($data['name']);
            }

            if (isset($data['description'])) {
                $translation->setDescription($data['description']);
            }

            if (isset($data['metaTitle'])) {
                $translation->setMetaTitle($data['metaTitle']);
            }

            if (isset($data['metaDescription'])) {
                $translation->setMetaDescription($data['metaDescription']);
            }

            // Auto-generate slug if not provided
            if (!isset($data['slug']) && isset($data['name'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name'], CategoryTranslation::class, $languageCode);
            }

            if (isset($data['slug'])) {
                $translation->setSlug($data['slug']);
            }

            $translation->setUpdatedAt();
        }

        return $category;
    }

    /**
     * Create or update brand with translations
     */
    public function createOrUpdateBrand(Brand $brand, array $translationsData): Brand
    {
        $languages = $this->getActiveLanguages();

        foreach ($languages as $language) {
            $languageCode = $language->getCode();
            
            if (!isset($translationsData[$languageCode])) {
                continue;
            }

            $data = $translationsData[$languageCode];
            $translation = $brand->getTranslation($languageCode);

            if (!$translation) {
                $translation = new BrandTranslation();
                $translation->setBrand($brand);
                $translation->setLanguage($language);
                $brand->addTranslation($translation);
            }

            if (isset($data['name'])) {
                $translation->setName($data['name']);
            }

            if (isset($data['description'])) {
                $translation->setDescription($data['description']);
            }

            if (isset($data['metaTitle'])) {
                $translation->setMetaTitle($data['metaTitle']);
            }

            if (isset($data['metaDescription'])) {
                $translation->setMetaDescription($data['metaDescription']);
            }

            $translation->setUpdatedAt();
        }

        return $brand;
    }

    /**
     * Create or update attribute with translations
     */
    public function createOrUpdateAttribute(Attribute $attribute, array $translationsData): Attribute
    {
        $languages = $this->getActiveLanguages();

        foreach ($languages as $language) {
            $languageCode = $language->getCode();
            
            if (!isset($translationsData[$languageCode])) {
                continue;
            }

            $data = $translationsData[$languageCode];
            $translation = $attribute->getTranslation($languageCode);

            if (!$translation) {
                $translation = new AttributeTranslation();
                $translation->setAttribute($attribute);
                $translation->setLanguage($language);
                $attribute->addTranslation($translation);
            }

            if (isset($data['name'])) {
                $translation->setName($data['name']);
            }

            $translation->setUpdatedAt();
        }

        return $attribute;
    }

    /**
     * Create or update attribute value with translations
     */
    public function createOrUpdateAttributeValue(AttributeValue $attributeValue, array $translationsData): AttributeValue
    {
        $languages = $this->getActiveLanguages();

        foreach ($languages as $language) {
            $languageCode = $language->getCode();
            
            if (!isset($translationsData[$languageCode])) {
                continue;
            }

            $data = $translationsData[$languageCode];
            $translation = $attributeValue->getTranslation($languageCode);

            if (!$translation) {
                $translation = new AttributeValueTranslation();
                $translation->setAttributeValue($attributeValue);
                $translation->setLanguage($language);
                $attributeValue->addTranslation($translation);
            }

            if (isset($data['value'])) {
                $translation->setValue($data['value']);
            }

            $translation->setUpdatedAt();
        }

        return $attributeValue;
    }

    /**
     * Create or update page with translations
     */
    public function createOrUpdatePage(Page $page, array $translationsData): Page
    {
        $languages = $this->getActiveLanguages();

        foreach ($languages as $language) {
            $languageCode = $language->getCode();
            
            if (!isset($translationsData[$languageCode])) {
                continue;
            }

            $data = $translationsData[$languageCode];
            $translation = $page->getTranslation($languageCode);

            if (!$translation) {
                $translation = new PageTranslation();
                $translation->setPage($page);
                $translation->setLanguage($language);
                $page->addTranslation($translation);
            }

            if (isset($data['title'])) {
                $translation->setTitle($data['title']);
            }

            if (isset($data['content'])) {
                $translation->setContent($data['content']);
            }

            if (isset($data['metaTitle'])) {
                $translation->setMetaTitle($data['metaTitle']);
            }

            if (isset($data['metaDescription'])) {
                $translation->setMetaDescription($data['metaDescription']);
            }

            // Auto-generate slug if not provided
            if (!isset($data['slug']) && isset($data['title'])) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], PageTranslation::class, $languageCode);
            }

            if (isset($data['slug'])) {
                $translation->setSlug($data['slug']);
            }

            $translation->setUpdatedAt();
        }

        return $page;
    }

    /**
     * Duplicate translation from one language to another
     */
    public function duplicateTranslation(string $entityClass, int $entityId, string $sourceLanguage, string $targetLanguage): bool
    {
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);
        if (!$entity) {
            return false;
        }

        $sourceTranslation = $entity->getTranslation($sourceLanguage);
        if (!$sourceTranslation) {
            return false;
        }

        $targetTranslation = $entity->getTranslation($targetLanguage);
        $targetLanguageEntity = $this->getLanguageByCode($targetLanguage);
        
        if (!$targetLanguageEntity) {
            return false;
        }

        if (!$targetTranslation) {
            $targetTranslation = $this->createTranslationEntity($entityClass, $entity, $targetLanguageEntity);
        }

        $this->copyTranslationData($sourceTranslation, $targetTranslation);
        
        $this->entityManager->persist($targetTranslation);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get translation completion statistics
     */
    public function getTranslationStatistics(): array
    {
        $stats = [];
        $languages = $this->getActiveLanguages();
        
        $entities = [
            'products' => Product::class,
            'categories' => Category::class,
            'brands' => Brand::class,
            'attributes' => Attribute::class,
            'attribute_values' => AttributeValue::class,
            'pages' => Page::class
        ];

        foreach ($entities as $name => $class) {
            $stats[$name] = [];
            $totalEntities = $this->entityManager->getRepository($class)->count([]);
            
            foreach ($languages as $language) {
                $translatedCount = $this->getTranslatedCount($class, $language->getCode());
                $percentage = $totalEntities > 0 ? round(($translatedCount / $totalEntities) * 100, 1) : 0;
                
                $stats[$name][$language->getCode()] = [
                    'translated' => $translatedCount,
                    'total' => $totalEntities,
                    'percentage' => $percentage
                ];
            }
        }

        return $stats;
    }

    /**
     * Create missing translations for all entities
     */
    public function createMissingTranslations(string $languageCode, string $sourceLanguageCode = 'fr'): int
    {
        $created = 0;
        $targetLanguage = $this->getLanguageByCode($languageCode);
        
        if (!$targetLanguage) {
            return 0;
        }

        $entities = [
            Product::class,
            Category::class,
            Brand::class,
            Attribute::class,
            AttributeValue::class,
            Page::class
        ];

        foreach ($entities as $entityClass) {
            $repository = $this->entityManager->getRepository($entityClass);
            $allEntities = $repository->findAll();

            foreach ($allEntities as $entity) {
                if (!$entity->hasTranslation($languageCode)) {
                    $sourceTranslation = $entity->getTranslation($sourceLanguageCode);
                    
                    if ($sourceTranslation) {
                        $newTranslation = $this->createTranslationEntity($entityClass, $entity, $targetLanguage);
                        $this->copyTranslationData($sourceTranslation, $newTranslation);
                        
                        $this->entityManager->persist($newTranslation);
                        $created++;
                    }
                }
            }
        }

        $this->entityManager->flush();
        return $created;
    }

    /**
     * Generate unique slug
     */
    private function generateUniqueSlug(string $title, string $translationClass, string $languageCode): string
    {
        $baseSlug = $this->slugger->slug($title)->lower();
        $slug = $baseSlug;
        $counter = 1;

        $repository = $this->entityManager->getRepository($translationClass);
        
        while ($this->slugExists($repository, $slug, $languageCode)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     */
    private function slugExists($repository, string $slug, string $languageCode): bool
    {
        $qb = $repository->createQueryBuilder('t');
        $qb->leftJoin('t.language', 'l')
           ->where('t.slug = :slug')
           ->andWhere('l.code = :languageCode')
           ->setParameter('slug', $slug)
           ->setParameter('languageCode', $languageCode);

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /**
     * Get active languages
     */
    private function getActiveLanguages(): array
    {
        return $this->entityManager->getRepository(Language::class)->findBy(
            ['isActive' => true],
            ['sortOrder' => 'ASC']
        );
    }

    /**
     * Get language by code
     */
    private function getLanguageByCode(string $code): ?Language
    {
        return $this->entityManager->getRepository(Language::class)->findOneBy(['code' => $code]);
    }

    /**
     * Get translated count for entity and language
     */
    private function getTranslatedCount(string $entityClass, string $languageCode): int
    {
        $translationClass = $this->getTranslationClass($entityClass);
        
        if (!$translationClass) {
            return 0;
        }

        $qb = $this->entityManager->getRepository($translationClass)->createQueryBuilder('t');
        $qb->leftJoin('t.language', 'l')
           ->select('COUNT(t.id)')
           ->where('l.code = :languageCode')
           ->setParameter('languageCode', $languageCode);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get translation class name for entity
     */
    private function getTranslationClass(string $entityClass): ?string
    {
        $map = [
            Product::class => ProductTranslation::class,
            Category::class => CategoryTranslation::class,
            Brand::class => BrandTranslation::class,
            Attribute::class => AttributeTranslation::class,
            AttributeValue::class => AttributeValueTranslation::class,
            Page::class => PageTranslation::class,
        ];

        return $map[$entityClass] ?? null;
    }

    /**
     * Create translation entity
     */
    private function createTranslationEntity(string $entityClass, $entity, Language $language)
    {
        switch ($entityClass) {
            case Product::class:
                $translation = new ProductTranslation();
                $translation->setProduct($entity);
                break;
            case Category::class:
                $translation = new CategoryTranslation();
                $translation->setCategory($entity);
                break;
            case Brand::class:
                $translation = new BrandTranslation();
                $translation->setBrand($entity);
                break;
            case Attribute::class:
                $translation = new AttributeTranslation();
                $translation->setAttribute($entity);
                break;
            case AttributeValue::class:
                $translation = new AttributeValueTranslation();
                $translation->setAttributeValue($entity);
                break;
            case Page::class:
                $translation = new PageTranslation();
                $translation->setPage($entity);
                break;
            default:
                throw new \InvalidArgumentException('Unsupported entity class: ' . $entityClass);
        }

        $translation->setLanguage($language);
        $entity->addTranslation($translation);

        return $translation;
    }

    /**
     * Copy translation data from source to target
     */
    private function copyTranslationData($source, $target): void
    {
        if ($source instanceof ProductTranslation && $target instanceof ProductTranslation) {
            $target->setName($source->getName());
            $target->setDescription($source->getDescription());
            $target->setShortDescription($source->getShortDescription());
            $target->setMetaTitle($source->getMetaTitle());
            $target->setMetaDescription($source->getMetaDescription());
            if ($source->getSlug()) {
                $target->setSlug($source->getSlug() . '-' . $target->getLanguage()->getCode());
            }
        } elseif ($source instanceof CategoryTranslation && $target instanceof CategoryTranslation) {
            $target->setName($source->getName());
            $target->setDescription($source->getDescription());
            $target->setMetaTitle($source->getMetaTitle());
            $target->setMetaDescription($source->getMetaDescription());
            if ($source->getSlug()) {
                $target->setSlug($source->getSlug() . '-' . $target->getLanguage()->getCode());
            }
        } elseif ($source instanceof BrandTranslation && $target instanceof BrandTranslation) {
            $target->setName($source->getName());
            $target->setDescription($source->getDescription());
            $target->setMetaTitle($source->getMetaTitle());
            $target->setMetaDescription($source->getMetaDescription());
        } elseif ($source instanceof AttributeTranslation && $target instanceof AttributeTranslation) {
            $target->setName($source->getName());
        } elseif ($source instanceof AttributeValueTranslation && $target instanceof AttributeValueTranslation) {
            $target->setValue($source->getValue());
        } elseif ($source instanceof PageTranslation && $target instanceof PageTranslation) {
            $target->setTitle($source->getTitle());
            $target->setContent($source->getContent());
            $target->setMetaTitle($source->getMetaTitle());
            $target->setMetaDescription($source->getMetaDescription());
            if ($source->getSlug()) {
                $target->setSlug($source->getSlug() . '-' . $target->getLanguage()->getCode());
            }
        }

        $target->setUpdatedAt();
    }
}