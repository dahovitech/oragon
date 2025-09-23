<?php

namespace App\Bundle\NotificationBundle\Repository;

use App\Bundle\NotificationBundle\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function save(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->persist($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->remove($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find pending notifications to send
     */
    public function findPendingNotifications(int $limit = 100): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.scheduledAt IS NULL OR n.scheduledAt <= :now')
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('n.priority', 'DESC')
            ->addOrderBy('n.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find notifications for a specific user
     */
    public function findUserNotifications(int $userId, bool $unreadOnly = false, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($unreadOnly) {
            $qb->andWhere('n.readAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count unread notifications for a user
     */
    public function countUnreadNotifications(int $userId): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.userId = :userId')
            ->andWhere('n.readAt IS NULL')
            ->andWhere('n.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'sent')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Mark multiple notifications as read
     */
    public function markAsRead(array $notificationIds, int $userId): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('n.id IN (:ids)')
            ->andWhere('n.userId = :userId')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('ids', $notificationIds)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('n.userId = :userId')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    /**
     * Get notifications statistics
     */
    public function getStatistics(\DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        if (!$from) {
            $from = new \DateTimeImmutable('-30 days');
        }
        if (!$to) {
            $to = new \DateTimeImmutable();
        }

        $qb = $this->createQueryBuilder('n')
            ->select('
                COUNT(n.id) as total,
                SUM(CASE WHEN n.status = \'sent\' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN n.status = \'pending\' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN n.status = \'failed\' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN n.readAt IS NOT NULL THEN 1 ELSE 0 END) as read
            ')
            ->where('n.createdAt >= :from')
            ->andWhere('n.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int)$result['total'],
            'sent' => (int)$result['sent'],
            'pending' => (int)$result['pending'],
            'failed' => (int)$result['failed'],
            'read' => (int)$result['read'],
            'read_rate' => $result['sent'] > 0 ? round(($result['read'] / $result['sent']) * 100, 2) : 0
        ];
    }

    /**
     * Get notifications by type for statistics
     */
    public function getNotificationsByType(\DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        if (!$from) {
            $from = new \DateTimeImmutable('-30 days');
        }
        if (!$to) {
            $to = new \DateTimeImmutable();
        }

        return $this->createQueryBuilder('n')
            ->select('n.type, COUNT(n.id) as count')
            ->where('n.createdAt >= :from')
            ->andWhere('n.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('n.type')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get daily notification counts
     */
    public function getDailyNotificationCounts(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('n')
            ->select('DATE(n.createdAt) as date, COUNT(n.id) as count')
            ->where('n.createdAt >= :from')
            ->andWhere('n.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications(int $daysToKeep = 90): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$daysToKeep} days");

        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :cutoff')
            ->andWhere('n.status IN (:statuses)')
            ->setParameter('cutoff', $cutoffDate)
            ->setParameter('statuses', ['sent', 'failed'])
            ->getQuery()
            ->execute();
    }

    /**
     * Find failed notifications that can be retried
     */
    public function findRetryableNotifications(int $maxAttempts = 3): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.attempts < :maxAttempts')
            ->andWhere('n.createdAt > :minDate')
            ->setParameter('status', 'failed')
            ->setParameter('maxAttempts', $maxAttempts)
            ->setParameter('minDate', new \DateTimeImmutable('-1 day'))
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Update notification status in batch
     */
    public function updateStatusBatch(array $notificationIds, string $status): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.status', ':status')
            ->where('n.id IN (:ids)')
            ->setParameter('status', $status)
            ->setParameter('ids', $notificationIds)
            ->getQuery()
            ->execute();
    }
}