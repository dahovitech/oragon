<?php

namespace App\Repository;

use App\Entity\AttributeValue;
use App\Entity\Attribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttributeValue>
 */
class AttributeValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeValue::class);
    }

    /**
     * Find active attribute values
     */
    public function findActiveValues(): array
    {
        return $this->createQueryBuilder('av')
            ->andWhere('av.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('av.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find values by attribute
     */
    public function findByAttribute(Attribute $attribute): array
    {
        return $this->createQueryBuilder('av')
            ->andWhere('av.attribute = :attribute')
            ->andWhere('av.isActive = :active')
            ->setParameter('attribute', $attribute)
            ->setParameter('active', true)
            ->orderBy('av.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find color values (with color codes)
     */
    public function findColorValues(): array
    {
        return $this->createQueryBuilder('av')
            ->andWhere('av.colorCode IS NOT NULL')
            ->andWhere('av.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('av.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
