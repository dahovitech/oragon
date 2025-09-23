<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Find orders by user
     */
    public function findByUser(User $user, array $orderBy = ['createdAt' => 'DESC']): array
    {
        return $this->findBy(['user' => $user], $orderBy);
    }

    /**
     * Find orders by status
     */
    public function findByStatus(string $status, array $orderBy = ['createdAt' => 'DESC']): array
    {
        return $this->findBy(['status' => $status], $orderBy);
    }

    /**
     * Find recent orders
     */
    public function findRecentOrders(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find orders by date range
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get order statistics
     */
    public function getOrderStatistics(): array
    {
        $qb = $this->createQueryBuilder('o');
        
        $result = $qb
            ->select([
                'COUNT(o.id) as totalOrders',
                'SUM(o.totalAmount) as totalRevenue',
                'AVG(o.totalAmount) as averageOrderValue',
                'o.status',
                'COUNT(CASE WHEN o.status = \'pending\' THEN 1 END) as pendingOrders',
                'COUNT(CASE WHEN o.status = \'processing\' THEN 1 END) as processingOrders',
                'COUNT(CASE WHEN o.status = \'shipped\' THEN 1 END) as shippedOrders',
                'COUNT(CASE WHEN o.status = \'delivered\' THEN 1 END) as deliveredOrders',
                'COUNT(CASE WHEN o.status = \'cancelled\' THEN 1 END) as cancelledOrders'
            ])
            ->getQuery()
            ->getOneOrNullResult();

        return $result ?: [
            'totalOrders' => 0,
            'totalRevenue' => 0,
            'averageOrderValue' => 0,
            'pendingOrders' => 0,
            'processingOrders' => 0,
            'shippedOrders' => 0,
            'deliveredOrders' => 0,
            'cancelledOrders' => 0
        ];
    }

    /**
     * Find orders with items
     */
    public function findOrdersWithItems(array $criteria = [], array $orderBy = ['createdAt' => 'DESC']): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.orderItems', 'oi')
            ->leftJoin('oi.product', 'p')
            ->addSelect('oi', 'p');

        foreach ($criteria as $field => $value) {
            $qb->andWhere("o.$field = :$field")
               ->setParameter($field, $value);
        }

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy("o.$field", $direction);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search orders
     */
    public function searchOrders(string $query): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')
            ->where('o.orderNumber LIKE :query')
            ->orWhere('u.email LIKE :query')
            ->orWhere('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Order[] Returns an array of Order objects
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

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}