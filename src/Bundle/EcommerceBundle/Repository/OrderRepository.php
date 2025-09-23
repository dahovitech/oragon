<?php

namespace App\Bundle\EcommerceBundle\Repository;

use App\Bundle\EcommerceBundle\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 *
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find order by order number
     */
    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return $this->createQueryBuilder('o')
            ->where('o.orderNumber = :orderNumber')
            ->setParameter('orderNumber', $orderNumber)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find orders by user
     */
    public function findByUser($user, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find orders by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->setParameter('status', $status)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find orders by payment status
     */
    public function findByPaymentStatus(string $paymentStatus): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.paymentStatus = :paymentStatus')
            ->setParameter('paymentStatus', $paymentStatus)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find recent orders
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find orders within date range
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.createdAt >= :from')
            ->andWhere('o.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get orders statistics
     */
    public function getStatistics(\DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->select('
                COUNT(o.id) as totalOrders,
                SUM(CAST(o.total AS DECIMAL(10,2))) as totalRevenue,
                AVG(CAST(o.total AS DECIMAL(10,2))) as averageOrderValue,
                COUNT(CASE WHEN o.status = :pending THEN 1 END) as pendingOrders,
                COUNT(CASE WHEN o.status = :confirmed THEN 1 END) as confirmedOrders,
                COUNT(CASE WHEN o.status = :processing THEN 1 END) as processingOrders,
                COUNT(CASE WHEN o.status = :shipped THEN 1 END) as shippedOrders,
                COUNT(CASE WHEN o.status = :delivered THEN 1 END) as deliveredOrders,
                COUNT(CASE WHEN o.status = :cancelled THEN 1 END) as cancelledOrders,
                COUNT(CASE WHEN o.paymentStatus = :paid THEN 1 END) as paidOrders
            ')
            ->setParameter('pending', Order::STATUS_PENDING)
            ->setParameter('confirmed', Order::STATUS_CONFIRMED)
            ->setParameter('processing', Order::STATUS_PROCESSING)
            ->setParameter('shipped', Order::STATUS_SHIPPED)
            ->setParameter('delivered', Order::STATUS_DELIVERED)
            ->setParameter('cancelled', Order::STATUS_CANCELLED)
            ->setParameter('paid', Order::PAYMENT_STATUS_PAID);

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
            'total_orders' => (int)$result['totalOrders'],
            'total_revenue' => round((float)($result['totalRevenue'] ?? 0), 2),
            'average_order_value' => round((float)($result['averageOrderValue'] ?? 0), 2),
            'pending_orders' => (int)$result['pendingOrders'],
            'confirmed_orders' => (int)$result['confirmedOrders'],
            'processing_orders' => (int)$result['processingOrders'],
            'shipped_orders' => (int)$result['shippedOrders'],
            'delivered_orders' => (int)$result['deliveredOrders'],
            'cancelled_orders' => (int)$result['cancelledOrders'],
            'paid_orders' => (int)$result['paidOrders'],
        ];
    }

    /**
     * Get monthly revenue data
     */
    public function getMonthlyRevenue(int $year): array
    {
        $results = $this->createQueryBuilder('o')
            ->select('
                MONTH(o.createdAt) as month,
                SUM(CAST(o.total AS DECIMAL(10,2))) as revenue,
                COUNT(o.id) as orderCount
            ')
            ->where('YEAR(o.createdAt) = :year')
            ->andWhere('o.paymentStatus = :paid')
            ->setParameter('year', $year)
            ->setParameter('paid', Order::PAYMENT_STATUS_PAID)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        // Initialize all months with zero revenue
        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[$i] = [
                'month' => $i,
                'revenue' => 0.00,
                'order_count' => 0
            ];
        }

        // Fill in actual data
        foreach ($results as $result) {
            $monthlyData[(int)$result['month']] = [
                'month' => (int)$result['month'],
                'revenue' => round((float)$result['revenue'], 2),
                'order_count' => (int)$result['orderCount']
            ];
        }

        return array_values($monthlyData);
    }

    /**
     * Find orders requiring action
     */
    public function findRequiringAction(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status IN (:statuses)')
            ->setParameter('statuses', [
                Order::STATUS_PENDING,
                Order::STATUS_CONFIRMED
            ])
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Search orders
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.orderNumber LIKE :query')
            ->orWhere('o.billingEmail LIKE :query')
            ->orWhere('o.billingFirstName LIKE :query')
            ->orWhere('o.billingLastName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find orders by user email
     */
    public function findByEmail(string $email): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.billingEmail = :email')
            ->setParameter('email', $email)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get top customers by order count
     */
    public function getTopCustomers(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->select('
                u.id as userId,
                u.email,
                u.firstName,
                u.lastName,
                COUNT(o.id) as orderCount,
                SUM(CAST(o.total AS DECIMAL(10,2))) as totalSpent
            ')
            ->join('o.user', 'u')
            ->groupBy('u.id')
            ->orderBy('orderCount', 'DESC')
            ->addOrderBy('totalSpent', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }
}