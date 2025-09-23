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
     * Find by shipping method and language
     */
    public function findByShippingMethodAndLanguage(int $shippingMethodId, string $languageCode): ?ShippingMethodTranslation
    {
        return $this->createQueryBuilder('smt')
            ->join('smt.language', 'l')
            ->where('smt.shippingMethod = :shippingMethodId')
            ->andWhere('l.code = :languageCode')
            ->setParameter('shippingMethodId', $shippingMethodId)
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find by language
     */
    public function findByLanguage(string $languageCode): array
    {
        return $this->createQueryBuilder('smt')
            ->join('smt.language', 'l')
            ->where('l.code = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getResult();
    }
}