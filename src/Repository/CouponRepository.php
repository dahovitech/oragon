<?php

namespace App\Repository;

use App\Entity\Coupon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Coupon>
 */
class CouponRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Coupon::class);
    }

    /**
     * Find active coupons
     */
    public function findActiveCoupons(): array
    {
        return $this->findBy(['isActive' => true], ['createdAt' => 'DESC']);
    }

    /**
     * Find coupon by code
     */
    public function findByCode(string $code): ?Coupon
    {
        return $this->findOneBy(['code' => strtoupper($code)]);
    }

    /**
     * Find valid coupons
     */
    public function findValidCoupons(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->andWhere('(c.startDate IS NULL OR c.startDate <= :now)')
            ->andWhere('(c.endDate IS NULL OR c.endDate >= :now)')
            ->andWhere('(c.usageLimit IS NULL OR c.usedCount < c.usageLimit)')
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}