<?php

namespace App\Repository;

use App\Entity\SecurityEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecurityEvent>
 *
 * @method SecurityEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method SecurityEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method SecurityEvent[]    findAll()
 * @method SecurityEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SecurityEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityEvent::class);
    }

    public function save(SecurityEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SecurityEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find recent security events
     */
    public function findRecentEvents(int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events by severity
     */
    public function findBySeverity(string $severity, int $limit = 100): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.severity = :severity')
            ->setParameter('severity', $severity)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find critical unresolved events
     */
    public function findCriticalUnresolvedEvents(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.severity IN (:severities)')
            ->andWhere('s.resolved = :resolved')
            ->setParameter('severities', ['critical', 'high'])
            ->setParameter('resolved', false)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events by user
     */
    public function findByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events by IP address
     */
    public function findByIpAddress(string $ipAddress, int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.ipAddress = :ip')
            ->setParameter('ip', $ipAddress)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events by type
     */
    public function findByEventType(string $eventType, int $limit = 100): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.eventType = :eventType')
            ->setParameter('eventType', $eventType)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find failed login attempts
     */
    public function findFailedLoginAttempts(\DateTimeInterface $since, ?string $ipAddress = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.eventType = :eventType')
            ->andWhere('s.createdAt >= :since')
            ->setParameter('eventType', 'login_failure')
            ->setParameter('since', $since)
            ->orderBy('s.createdAt', 'DESC');

        if ($ipAddress) {
            $qb->andWhere('s.ipAddress = :ip')
               ->setParameter('ip', $ipAddress);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find suspicious IPs
     */
    public function findSuspiciousIps(\DateTimeInterface $since, int $threshold = 10): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.ipAddress, COUNT(s.id) as event_count')
            ->andWhere('s.createdAt >= :since')
            ->andWhere('s.eventType IN (:suspiciousTypes)')
            ->andWhere('s.ipAddress IS NOT NULL')
            ->setParameter('since', $since)
            ->setParameter('suspiciousTypes', [
                'login_failure',
                'rate_limit_exceeded',
                'suspicious_activity',
                'csrf_token_mismatch',
                'sql_injection_attempt',
                'xss_attempt'
            ])
            ->groupBy('s.ipAddress')
            ->having('COUNT(s.id) >= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('event_count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get security statistics
     */
    public function getSecurityStatistics(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select([
                'COUNT(s.id) as total_events',
                'SUM(CASE WHEN s.severity = :critical THEN 1 ELSE 0 END) as critical_events',
                'SUM(CASE WHEN s.severity = :high THEN 1 ELSE 0 END) as high_events',
                'SUM(CASE WHEN s.resolved = false THEN 1 ELSE 0 END) as unresolved_events',
                'COUNT(DISTINCT s.ipAddress) as unique_ips',
                'COUNT(DISTINCT s.user) as affected_users'
            ])
            ->andWhere('s.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('critical', 'critical')
            ->setParameter('high', 'high');

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Get events by type statistics
     */
    public function getEventTypeStatistics(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.eventType, COUNT(s.id) as event_count')
            ->andWhere('s.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('s.eventType')
            ->orderBy('event_count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get hourly event distribution
     */
    public function getHourlyEventDistribution(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('s')
            ->select('HOUR(s.createdAt) as hour, COUNT(s.id) as event_count')
            ->andWhere('s.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events requiring attention
     */
    public function findEventsRequiringAttention(): array
    {
        return [
            'critical_unresolved' => $this->findCriticalUnresolvedEvents(),
            'recent_suspicious' => $this->findSuspiciousIps(new \DateTimeImmutable('-24 hours')),
            'failed_logins' => $this->findFailedLoginAttempts(new \DateTimeImmutable('-1 hour')),
        ];
    }

    /**
     * Clean old events
     */
    public function cleanOldEvents(\DateTimeInterface $before, array $excludeSeverities = ['critical', 'high']): int
    {
        $qb = $this->createQueryBuilder('s')
            ->delete()
            ->andWhere('s.createdAt < :before')
            ->setParameter('before', $before);

        if (!empty($excludeSeverities)) {
            $qb->andWhere('s.severity NOT IN (:excludeSeverities)')
               ->setParameter('excludeSeverities', $excludeSeverities);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Mark events as resolved
     */
    public function markAsResolved(array $eventIds, string $resolution, ?int $resolvedBy = null): int
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.resolved', ':resolved')
            ->set('s.resolution', ':resolution')
            ->set('s.resolvedAt', ':resolvedAt')
            ->set('s.resolvedBy', ':resolvedBy')
            ->andWhere('s.id IN (:eventIds)')
            ->setParameter('resolved', true)
            ->setParameter('resolution', $resolution)
            ->setParameter('resolvedAt', new \DateTimeImmutable())
            ->setParameter('resolvedBy', $resolvedBy)
            ->setParameter('eventIds', $eventIds)
            ->getQuery()
            ->execute();
    }

    /**
     * Get top user agents
     */
    public function getTopUserAgents(\DateTimeInterface $from, \DateTimeInterface $to, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.userAgent, COUNT(s.id) as usage_count')
            ->andWhere('s.createdAt BETWEEN :from AND :to')
            ->andWhere('s.userAgent IS NOT NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('s.userAgent')
            ->orderBy('usage_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get security trends
     */
    public function getSecurityTrends(int $days = 30): array
    {
        $trends = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = new \DateTimeImmutable("-{$i} days");
            $dayStart = $date->setTime(0, 0, 0);
            $dayEnd = $date->setTime(23, 59, 59);
            
            $dayStats = $this->createQueryBuilder('s')
                ->select([
                    'COUNT(s.id) as total',
                    'SUM(CASE WHEN s.severity IN (:critical) THEN 1 ELSE 0 END) as critical'
                ])
                ->andWhere('s.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $dayStart)
                ->setParameter('end', $dayEnd)
                ->setParameter('critical', ['critical', 'high'])
                ->getQuery()
                ->getSingleResult();
            
            $trends[] = [
                'date' => $date->format('Y-m-d'),
                'total_events' => (int) $dayStats['total'],
                'critical_events' => (int) $dayStats['critical'],
            ];
        }
        
        return array_reverse($trends);
    }

    /**
     * Find events with pattern
     */
    public function findEventsWithPattern(string $pattern, string $field = 'description'): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere("s.{$field} LIKE :pattern")
            ->setParameter('pattern', '%' . $pattern . '%')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count events by date range
     */
    public function countEventsByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }
}