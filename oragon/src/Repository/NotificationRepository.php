<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 *
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function save(Notification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Notification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find notifications by user
     */
    public function findByUser(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unread notifications by user
     */
    public function findUnreadByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unread notifications by user
     */
    public function countUnreadByUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find pending notifications to process
     */
    public function findPendingNotifications(int $limit = 100): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.status = :status')
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
     * Find scheduled notifications ready to send
     */
    public function findScheduledNotifications(): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.status = :status')
            ->andWhere('n.scheduledAt IS NOT NULL')
            ->andWhere('n.scheduledAt <= :now')
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('n.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find notifications by type
     */
    public function findByType(string $type, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.type = :type')
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find failed notifications
     */
    public function findFailedNotifications(int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.status = :status')
            ->setParameter('status', 'failed')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Mark notifications as read by user
     */
    public function markAsReadByUser(User $user, array $notificationIds = []): int
    {
        $qb = $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':readAt')
            ->andWhere('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('readAt', new \DateTimeImmutable())
            ->setParameter('user', $user);

        if (!empty($notificationIds)) {
            $qb->andWhere('n.id IN (:ids)')
               ->setParameter('ids', $notificationIds);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Delete old notifications
     */
    public function deleteOldNotifications(\DateTimeInterface $before): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.createdAt < :before')
            ->andWhere('n.status != :pending')
            ->setParameter('before', $before)
            ->setParameter('pending', 'pending')
            ->getQuery()
            ->execute();
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->select([
                'COUNT(n.id) as total',
                'SUM(CASE WHEN n.status = :sent THEN 1 ELSE 0 END) as sent',
                'SUM(CASE WHEN n.status = :pending THEN 1 ELSE 0 END) as pending',
                'SUM(CASE WHEN n.status = :failed THEN 1 ELSE 0 END) as failed',
                'SUM(CASE WHEN n.readAt IS NOT NULL THEN 1 ELSE 0 END) as read',
                'AVG(n.attempts) as avg_attempts'
            ])
            ->setParameter('sent', 'sent')
            ->setParameter('pending', 'pending')
            ->setParameter('failed', 'failed');

        if ($from) {
            $qb->andWhere('n.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('n.createdAt <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Get statistics by type
     */
    public function getStatisticsByType(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->select([
                'n.type',
                'COUNT(n.id) as total',
                'SUM(CASE WHEN n.status = :sent THEN 1 ELSE 0 END) as sent',
                'SUM(CASE WHEN n.status = :failed THEN 1 ELSE 0 END) as failed'
            ])
            ->groupBy('n.type')
            ->setParameter('sent', 'sent')
            ->setParameter('failed', 'failed');

        if ($from) {
            $qb->andWhere('n.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('n.createdAt <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retry failed notifications
     */
    public function retryFailedNotifications(int $maxAttempts = 3): array
    {
        $notifications = $this->createQueryBuilder('n')
            ->andWhere('n.status = :failed')
            ->andWhere('n.attempts < :maxAttempts')
            ->setParameter('failed', 'failed')
            ->setParameter('maxAttempts', $maxAttempts)
            ->getQuery()
            ->getResult();

        foreach ($notifications as $notification) {
            $notification->setStatus('pending');
            $notification->setFailureReason(null);
        }

        if (!empty($notifications)) {
            $this->getEntityManager()->flush();
        }

        return $notifications;
    }
}