<?php

namespace App\Bundle\EcommerceBundle\Repository;

use App\Bundle\EcommerceBundle\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 *
 * @method OrderItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderItem[]    findAll()
 * @method OrderItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    public function save(OrderItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OrderItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find items by order
     */
    public function findByOrder($order): array
    {
        return $this->createQueryBuilder('oi')
            ->where('oi.order = :order')
            ->setParameter('order', $order)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find items by product SKU
     */
    public function findByProductSku(string $sku): array
    {
        return $this->createQueryBuilder('oi')
            ->join('oi.order', 'o')
            ->where('oi.productSku = :sku OR oi.variantSku = :sku')
            ->setParameter('sku', $sku)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get best selling products
     */
    public function getBestSellingProducts(int $limit = 10, \DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('oi')
            ->select('
                oi.productName,
                oi.productSku,
                SUM(oi.quantity) as totalQuantitySold,
                COUNT(DISTINCT oi.order) as orderCount,
                SUM(CAST(oi.lineTotal AS DECIMAL(10,2))) as totalRevenue
            ')
            ->join('oi.order', 'o')
            ->where('o.paymentStatus = :paid')
            ->setParameter('paid', 'paid')
            ->groupBy('oi.productSku')
            ->orderBy('totalQuantitySold', 'DESC')
            ->setMaxResults($limit);

        if ($from) {
            $qb->andWhere('o.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('o.createdAt <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get sales data by product
     */
    public function getSalesByProduct(string $productSku, \DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('oi')
            ->select('
                DATE(o.createdAt) as saleDate,
                SUM(oi.quantity) as quantitySold,
                SUM(CAST(oi.lineTotal AS DECIMAL(10,2))) as revenue
            ')
            ->join('oi.order', 'o')
            ->where('oi.productSku = :sku')
            ->andWhere('o.paymentStatus = :paid')
            ->setParameter('sku', $productSku)
            ->setParameter('paid', 'paid')
            ->groupBy('saleDate')
            ->orderBy('saleDate', 'ASC');

        if ($from) {
            $qb->andWhere('o.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('o.createdAt <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get total quantity sold for a product
     */
    public function getTotalQuantitySold(string $productSku): int
    {
        $result = $this->createQueryBuilder('oi')
            ->select('SUM(oi.quantity)')
            ->join('oi.order', 'o')
            ->where('oi.productSku = :sku OR oi.variantSku = :sku')
            ->andWhere('o.paymentStatus = :paid')
            ->setParameter('sku', $productSku)
            ->setParameter('paid', 'paid')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int)$result : 0;
    }

    /**
     * Get revenue by product
     */
    public function getRevenueByProduct(string $productSku): float
    {
        $result = $this->createQueryBuilder('oi')
            ->select('SUM(CAST(oi.lineTotal AS DECIMAL(10,2)))')
            ->join('oi.order', 'o')
            ->where('oi.productSku = :sku OR oi.variantSku = :sku')
            ->andWhere('o.paymentStatus = :paid')
            ->setParameter('sku', $productSku)
            ->setParameter('paid', 'paid')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float)$result : 0.0;
    }

    /**
     * Find items bought together with a specific product
     */
    public function findFrequentlyBoughtTogether(string $productSku, int $limit = 5): array
    {
        return $this->createQueryBuilder('oi1')
            ->select('
                oi2.productName,
                oi2.productSku,
                COUNT(DISTINCT oi2.order) as coOccurrenceCount
            ')
            ->join('oi1.order', 'o1')
            ->join('o1.items', 'oi2')
            ->where('oi1.productSku = :sku')
            ->andWhere('oi2.productSku != :sku')
            ->andWhere('o1.paymentStatus = :paid')
            ->setParameter('sku', $productSku)
            ->setParameter('paid', 'paid')
            ->groupBy('oi2.productSku')
            ->orderBy('coOccurrenceCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get order items statistics
     */
    public function getStatistics(\DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('oi')
            ->select('
                COUNT(oi.id) as totalItems,
                SUM(oi.quantity) as totalQuantity,
                SUM(CAST(oi.lineTotal AS DECIMAL(10,2))) as totalRevenue,
                AVG(CAST(oi.unitPrice AS DECIMAL(10,2))) as averageUnitPrice
            ')
            ->join('oi.order', 'o')
            ->where('o.paymentStatus = :paid')
            ->setParameter('paid', 'paid');

        if ($from) {
            $qb->andWhere('o.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('o.createdAt <= :to')
               ->setParameter('to', $to);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total_items' => (int)$result['totalItems'],
            'total_quantity' => (int)$result['totalQuantity'],
            'total_revenue' => round((float)($result['totalRevenue'] ?? 0), 2),
            'average_unit_price' => round((float)($result['averageUnitPrice'] ?? 0), 2),
        ];
    }
}