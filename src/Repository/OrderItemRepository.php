<?php

namespace App\Repository;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    /**
     * Find items by order
     */
    public function findByOrder(Order $order): array
    {
        return $this->findBy(['order' => $order], ['createdAt' => 'ASC']);
    }

    /**
     * Find items by product
     */
    public function findByProduct(Product $product): array
    {
        return $this->findBy(['product' => $product], ['createdAt' => 'DESC']);
    }

    /**
     * Get best selling products
     */
    public function getBestSellingProducts(int $limit = 10): array
    {
        return $this->createQueryBuilder('oi')
            ->select('p', 'SUM(oi.quantity) as totalSold')
            ->leftJoin('oi.product', 'p')
            ->groupBy('p.id')
            ->orderBy('totalSold', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get sales statistics for a product
     */
    public function getProductSalesStats(Product $product): array
    {
        $result = $this->createQueryBuilder('oi')
            ->select([
                'SUM(oi.quantity) as totalQuantitySold',
                'SUM(oi.totalPrice) as totalRevenue',
                'COUNT(DISTINCT oi.order) as totalOrders',
                'AVG(oi.quantity) as averageQuantityPerOrder'
            ])
            ->where('oi.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ?: [
            'totalQuantitySold' => 0,
            'totalRevenue' => 0,
            'totalOrders' => 0,
            'averageQuantityPerOrder' => 0
        ];
    }

    /**
     * Get revenue by date range
     */
    public function getRevenueByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('oi')
            ->select([
                'DATE(oi.createdAt) as date',
                'SUM(oi.totalPrice) as dailyRevenue',
                'SUM(oi.quantity) as dailyQuantity'
            ])
            ->where('oi.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return OrderItem[] Returns an array of OrderItem objects
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

    //    public function findOneBySomeField($value): ?OrderItem
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}