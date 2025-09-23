<?php

namespace App\Bundle\AnalyticsBundle\Repository;

use App\Bundle\AnalyticsBundle\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function save(Event $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get events by type and date range
     */
    public function getEventsByType(string $eventType, \DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.eventType = :eventType')
            ->setParameter('eventType', $eventType)
            ->orderBy('e.createdAt', 'DESC');

        if ($from) {
            $qb->andWhere('e.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('e.createdAt <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get event statistics by category
     */
    public function getEventStatsByCategory(\DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.category, e.eventType, COUNT(e.id) as count, AVG(e.value) as avgValue')
            ->groupBy('e.category', 'e.eventType')
            ->orderBy('count', 'DESC');

        if ($from) {
            $qb->andWhere('e.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('e.createdAt <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get top events by frequency
     */
    public function getTopEvents(int $limit = 10, \DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.eventType, e.category, e.action, e.label, COUNT(e.id) as count')
            ->groupBy('e.eventType', 'e.category', 'e.action', 'e.label')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit);

        if ($from) {
            $qb->andWhere('e.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('e.createdAt <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get events timeline by date
     */
    public function getEventsTimeline(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('e')
            ->select('DATE(e.createdAt) as date, e.eventType, e.category, COUNT(e.id) as count')
            ->where('e.createdAt >= :from')
            ->andWhere('e.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('date', 'e.eventType', 'e.category')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get conversion funnel data
     */
    public function getConversionFunnel(array $eventSequence, \DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $results = [];
        
        foreach ($eventSequence as $step => $eventType) {
            $qb = $this->createQueryBuilder('e')
                ->select('COUNT(DISTINCT e.sessionId) as count')
                ->where('e.eventType = :eventType')
                ->setParameter('eventType', $eventType);

            if ($from) {
                $qb->andWhere('e.createdAt >= :from')
                   ->setParameter('from', $from);
            }

            if ($to) {
                $qb->andWhere('e.createdAt <= :to')
                   ->setParameter('to', $to);
            }

            $count = $qb->getQuery()->getSingleScalarResult();
            
            $results[$step] = [
                'event_type' => $eventType,
                'count' => $count,
                'conversion_rate' => $step === 0 ? 100 : 
                    ($results[$step - 1]['count'] > 0 ? ($count / $results[$step - 1]['count']) * 100 : 0)
            ];
        }

        return $results;
    }

    /**
     * Get user journey for a specific user
     */
    public function getUserJourney(int $userId, \DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('e.createdAt', 'ASC');

        if ($from) {
            $qb->andWhere('e.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('e.createdAt <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get cohort analysis data
     */
    public function getCohortAnalysis(\DateTimeInterface $from, \DateTimeInterface $to, string $eventType = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('DATE(e.createdAt) as date, COUNT(DISTINCT e.userId) as users')
            ->where('e.createdAt >= :from')
            ->andWhere('e.createdAt <= :to')
            ->andWhere('e.userId IS NOT NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        if ($eventType) {
            $qb->andWhere('e.eventType = :eventType')
               ->setParameter('eventType', $eventType);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get real-time events (last hour)
     */
    public function getRealTimeEvents(): array
    {
        $oneHourAgo = new \DateTimeImmutable('-1 hour');
        
        return $this->createQueryBuilder('e')
            ->where('e.createdAt >= :oneHourAgo')
            ->setParameter('oneHourAgo', $oneHourAgo)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get events by session
     */
    public function getEventsBySession(string $sessionId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}