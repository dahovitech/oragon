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
     * Find all active order statuses
     */
    public function findActiveStatuses(): array
    {
        return $this->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
    }

    /**
     * Find status by code
     */
    public function findByCode(string $code): ?OrderStatus
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Find statuses with translations
     */
    public function findStatusesWithTranslations(): array
    {
        return $this->createQueryBuilder('os')
            ->leftJoin('os.translations', 'ost')
            ->leftJoin('ost.language', 'l')
            ->addSelect('ost', 'l')
            ->where('os.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('os.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return OrderStatus[] Returns an array of OrderStatus objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?OrderStatus
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}