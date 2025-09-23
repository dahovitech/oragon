<?php

namespace App\Repository;

use App\Entity\BrandTranslation;
use App\Entity\Language;
use App\Entity\Brand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BrandTranslation>
 */
class BrandTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BrandTranslation::class);
    }

    /**
     * Find translation by brand and language
     */
    public function findByBrandAndLanguage(Brand $brand, Language $language): ?BrandTranslation
    {
        return $this->createQueryBuilder('bt')
            ->andWhere('bt.brand = :brand')
            ->andWhere('bt.language = :language')
            ->setParameter('brand', $brand)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get completion statistics by language
     */
    public function getCompletionStats(): array
    {
        return $this->createQueryBuilder('bt')
            ->select('l.code as language_code, l.name as language_name')
            ->addSelect('COUNT(bt.id) as total')
            ->addSelect('SUM(CASE WHEN bt.name IS NOT NULL AND bt.name != \'\'\' THEN 1 ELSE 0 END) as with_name')
            ->addSelect('SUM(CASE WHEN bt.description IS NOT NULL AND bt.description != \'\'\' THEN 1 ELSE 0 END) as with_description')
            ->join('bt.language', 'l')
            ->where('l.isActive = true')
            ->groupBy('l.id')
            ->orderBy('l.sortOrder')
            ->getQuery()
            ->getResult();
    }
}
