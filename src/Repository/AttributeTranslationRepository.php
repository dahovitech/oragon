<?php

namespace App\Repository;

use App\Entity\AttributeTranslation;
use App\Entity\Language;
use App\Entity\Attribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttributeTranslation>
 */
class AttributeTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeTranslation::class);
    }

    /**
     * Find translation by attribute and language
     */
    public function findByAttributeAndLanguage(Attribute $attribute, Language $language): ?AttributeTranslation
    {
        return $this->createQueryBuilder('at')
            ->andWhere('at.attribute = :attribute')
            ->andWhere('at.language = :language')
            ->setParameter('attribute', $attribute)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get completion statistics by language
     */
    public function getCompletionStats(): array
    {
        return $this->createQueryBuilder('at')
            ->select('l.code as language_code, l.name as language_name')
            ->addSelect('COUNT(at.id) as total')
            ->addSelect('SUM(CASE WHEN at.name IS NOT NULL AND at.name != \'\'\' THEN 1 ELSE 0 END) as with_name')
            ->join('at.language', 'l')
            ->where('l.isActive = true')
            ->groupBy('l.id')
            ->orderBy('l.sortOrder')
            ->getQuery()
            ->getResult();
    }
}
