<?php

namespace App\Repository;

use App\Entity\ShippingMethod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingMethod>
 */
class ShippingMethodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingMethod::class);
    }

    /**
     * Find active shipping methods ordered by sort order
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('sm')
            ->where('sm.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('sm.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find available methods for order
     */
    public function findAvailableForOrder(float $orderTotal, float $orderWeight = 0): array
    {
        $methods = $this->findActive();
        
        return array_filter($methods, function(ShippingMethod $method) use ($orderTotal, $orderWeight) {
            return $method->isAvailableForOrder($orderTotal, $orderWeight);
        });
    }

    /**
     * Find by code
     */
    public function findByCode(string $code): ?ShippingMethod
    {
        return $this->createQueryBuilder('sm')
            ->where('sm.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get next sort order value
     */
    public function getNextSortOrder(): int
    {
        $result = $this->createQueryBuilder('sm')
            ->select('MAX(sm.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }
}