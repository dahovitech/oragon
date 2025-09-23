<?php

namespace App\Repository;

use App\Entity\CategoryTranslation;
use App\Entity\Language;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryTranslation>
 */
class CategoryTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryTranslation::class);
    }

    /**
     * Find translation by category and language
     */
    public function findByCategoryAndLanguage(Category $category, Language $language): ?CategoryTranslation
    {
        return $this->createQueryBuilder('ct')
            ->andWhere('ct.category = :category')
            ->andWhere('ct.language = :language')
            ->setParameter('category', $category)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get completion statistics by language
     */
    public function getCompletionStats(): array
    {
        return $this->createQueryBuilder('ct')
            ->select('l.code as language_code, l.name as language_name')
            ->addSelect('COUNT(ct.id) as total')
            ->addSelect('SUM(CASE WHEN ct.name IS NOT NULL AND ct.name != \'\'\' THEN 1 ELSE 0 END) as with_name')
            ->addSelect('SUM(CASE WHEN ct.description IS NOT NULL AND ct.description != \'\'\' THEN 1 ELSE 0 END) as with_description')
            ->join('ct.language', 'l')
            ->where('l.isActive = true')
            ->groupBy('l.id')
            ->orderBy('l.sortOrder')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if slug exists for language
     */
    public function isSlugExists(string $slug, string $languageCode, ?int $excludeCategoryId = null): bool
    {
        $qb = $this->createQueryBuilder('ct')
            ->select('COUNT(ct.id)')
            ->join('ct.language', 'l')
            ->where('ct.slug = :slug')
            ->andWhere('l.code = :languageCode')
            ->setParameter('slug', $slug)
            ->setParameter('languageCode', $languageCode);

        if ($excludeCategoryId) {
            $qb->andWhere('ct.category != :excludeCategory')
                ->setParameter('excludeCategory', $excludeCategoryId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
