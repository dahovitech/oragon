<?php

namespace App\Repository;

use App\Entity\OrderStatusTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderStatusTranslation>
 */
class OrderStatusTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderStatusTranslation::class);
    }

    /**
     * Find by order status and language
     */
    public function findByOrderStatusAndLanguage(int $orderStatusId, string $languageCode): ?OrderStatusTranslation
    {
        return $this->createQueryBuilder('ost')
            ->join('ost.language', 'l')
            ->where('ost.orderStatus = :orderStatusId')
            ->andWhere('l.code = :languageCode')
            ->setParameter('orderStatusId', $orderStatusId)
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find by language
     */
    public function findByLanguage(string $languageCode): array
    {
        return $this->createQueryBuilder('ost')
            ->join('ost.language', 'l')
            ->where('l.code = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getResult();
    }
}