<?php

namespace App\Repository;

use App\Entity\PaymentMethod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentMethod>
 */
class PaymentMethodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentMethod::class);
    }

    /**
     * Find active payment methods ordered by sort order
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('pm')
            ->where('pm.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('pm.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find by code
     */
    public function findByCode(string $code): ?PaymentMethod
    {
        return $this->createQueryBuilder('pm')
            ->where('pm.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find by provider
     */
    public function findByProvider(string $provider): array
    {
        return $this->createQueryBuilder('pm')
            ->where('pm.provider = :provider')
            ->andWhere('pm.isActive = :active')
            ->setParameter('provider', $provider)
            ->setParameter('active', true)
            ->orderBy('pm.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find online payment methods
     */
    public function findOnlineMethods(): array
    {
        return $this->createQueryBuilder('pm')
            ->where('pm.provider IN (:providers)')
            ->andWhere('pm.isActive = :active')
            ->setParameter('providers', [PaymentMethod::PROVIDER_STRIPE, PaymentMethod::PROVIDER_PAYPAL])
            ->setParameter('active', true)
            ->orderBy('pm.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next sort order value
     */
    public function getNextSortOrder(): int
    {
        $result = $this->createQueryBuilder('pm')
            ->select('MAX(pm.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }
}