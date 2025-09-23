<?php

namespace App\Repository;

use App\Entity\CouponTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CouponTranslation>
 */
class CouponTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CouponTranslation::class);
    }

    /**
     * Find translations by language code
     */
    public function findByLanguageCode(string $languageCode): array
    {
        return $this->createQueryBuilder('ct')
            ->leftJoin('ct.language', 'l')
            ->leftJoin('ct.coupon', 'c')
            ->where('l.code = :code')
            ->andWhere('c.isActive = :active')
            ->setParameter('code', $languageCode)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
}