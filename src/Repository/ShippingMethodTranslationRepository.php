<?php

namespace App\Repository;

use App\Entity\ShippingMethodTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingMethodTranslation>
 */
class ShippingMethodTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingMethodTranslation::class);
    }

    /**
     * Find translations by language code
     */
    public function findByLanguageCode(string $languageCode): array
    {
        return $this->createQueryBuilder('smt')
            ->leftJoin('smt.language', 'l')
            ->leftJoin('smt.shippingMethod', 'sm')
            ->where('l.code = :code')
            ->andWhere('sm.isActive = :active')
            ->setParameter('code', $languageCode)
            ->setParameter('active', true)
            ->orderBy('sm.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}