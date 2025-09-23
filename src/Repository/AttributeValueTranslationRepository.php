<?php

namespace App\Repository;

use App\Entity\AttributeValueTranslation;
use App\Entity\Language;
use App\Entity\AttributeValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttributeValueTranslation>
 */
class AttributeValueTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeValueTranslation::class);
    }

    /**
     * Find translation by attribute value and language
     */
    public function findByAttributeValueAndLanguage(AttributeValue $attributeValue, Language $language): ?AttributeValueTranslation
    {
        return $this->createQueryBuilder('avt')
            ->andWhere('avt.attributeValue = :attributeValue')
            ->andWhere('avt.language = :language')
            ->setParameter('attributeValue', $attributeValue)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get completion statistics by language
     */
    public function getCompletionStats(): array
    {
        return $this->createQueryBuilder('avt')
            ->select('l.code as language_code, l.name as language_name')
            ->addSelect('COUNT(avt.id) as total')
            ->addSelect('SUM(CASE WHEN avt.value IS NOT NULL AND avt.value != \'\'\' THEN 1 ELSE 0 END) as with_value')
            ->join('avt.language', 'l')
            ->where('l.isActive = true')
            ->groupBy('l.id')
            ->orderBy('l.sortOrder')
            ->getQuery()
            ->getResult();
    }
}
