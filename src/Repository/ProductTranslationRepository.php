<?php

namespace App\Repository;

use App\Entity\ProductTranslation;
use App\Entity\Language;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductTranslation>
 */
class ProductTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductTranslation::class);
    }

    /**
     * Find translation by product and language
     */
    public function findByProductAndLanguage(Product $product, Language $language): ?ProductTranslation
    {
        return $this->createQueryBuilder('pt')
            ->andWhere('pt.product = :product')
            ->andWhere('pt.language = :language')
            ->setParameter('product', $product)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get completion statistics by language
     */
    public function getCompletionStats(): array
    {
        $qb = $this->createQueryBuilder('pt')
            ->select('l.code as language_code, l.name as language_name')
            ->addSelect('COUNT(pt.id) as total')
            ->addSelect('SUM(CASE WHEN pt.name IS NOT NULL AND pt.name != \'\'\' THEN 1 ELSE 0 END) as with_name')
            ->addSelect('SUM(CASE WHEN pt.description IS NOT NULL AND pt.description != \'\'\' THEN 1 ELSE 0 END) as with_description')
            ->addSelect('SUM(CASE WHEN pt.shortDescription IS NOT NULL AND pt.shortDescription != \'\'\' THEN 1 ELSE 0 END) as with_short_description')
            ->addSelect('SUM(CASE WHEN pt.slug IS NOT NULL AND pt.slug != \'\'\' THEN 1 ELSE 0 END) as with_slug')
            ->join('pt.language', 'l')
            ->where('l.isActive = true')
            ->groupBy('l.id')
            ->orderBy('l.sortOrder')
            ->getQuery()
            ->getResult();

        return $qb;
    }

    /**
     * Find incomplete translations
     */
    public function findIncompleteTranslations(string $languageCode): array
    {
        return $this->createQueryBuilder('pt')
            ->join('pt.language', 'l')
            ->where('l.code = :languageCode')
            ->andWhere("(pt.name IS NULL OR pt.name = \"\" OR pt.description IS NULL OR pt.description = \"\")")
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if slug exists for language
     */
    public function isSlugExists(string $slug, string $languageCode, ?int $excludeProductId = null): bool
    {
        $qb = $this->createQueryBuilder('pt')
            ->select('COUNT(pt.id)')
            ->join('pt.language', 'l')
            ->where('pt.slug = :slug')
            ->andWhere('l.code = :languageCode')
            ->setParameter('slug', $slug)
            ->setParameter('languageCode', $languageCode);

        if ($excludeProductId) {
            $qb->andWhere('pt.product != :excludeProduct')
                ->setParameter('excludeProduct', $excludeProductId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
