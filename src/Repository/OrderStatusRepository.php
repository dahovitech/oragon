<?php

namespace App\Repository;

use App\Entity\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderStatus>
 */
class OrderStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderStatus::class);
    }

    /**
     * Find active order statuses ordered by sort order
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('os')
            ->where('os.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('os.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find by code
     */
    public function findByCode(string $code): ?OrderStatus
    {
        return $this->createQueryBuilder('os')
            ->where('os.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get next sort order value
     */
    public function getNextSortOrder(): int
    {
        $result = $this->createQueryBuilder('os')
            ->select('MAX(os.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }
}