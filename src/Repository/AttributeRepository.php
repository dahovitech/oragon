<?php

namespace App\Repository;

use App\Entity\Attribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attribute>
 */
class AttributeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attribute::class);
    }

    /**
     * Find active attributes
     */
    public function findActiveAttributes(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('a.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find filterable attributes
     */
    public function findFilterableAttributes(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.isFilterable = :filterable')
            ->setParameter('active', true)
            ->setParameter('filterable', true)
            ->orderBy('a.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attributes by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.type = :type')
            ->andWhere('a.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('a.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get attributes with values count
     */
    public function getAttributesWithValuesCount(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a, COUNT(av.id) as valuesCount')
            ->leftJoin('a.values', 'av', 'WITH', 'av.isActive = true')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('a.id')
            ->orderBy('a.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
