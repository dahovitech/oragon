<?php

namespace App\Bundle\AnalyticsBundle\Repository;

use App\Bundle\AnalyticsBundle\Entity\PageView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageView>
 */
class PageViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageView::class);
    }

    public function save(PageView $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get page views count by date range
     */
    public function getViewsByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('pv')
            ->select('DATE(pv.createdAt) as date, COUNT(pv.id) as views')
            ->where('pv.createdAt >= :from')
            ->andWhere('pv.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get top pages by views
     */
    public function getTopPages(int $limit = 10, \DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('pv')
            ->select('pv.url, pv.title, COUNT(pv.id) as views')
            ->groupBy('pv.url', 'pv.title')
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit);

        if ($from) {
            $qb->andWhere('pv.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('pv.createdAt <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get unique visitors count
     */
    public function getUniqueVisitors(\DateTimeInterface $from = null, \DateTimeInterface $to = null): int
    {
        $qb = $this->createQueryBuilder('pv')
            ->select('COUNT(DISTINCT pv.ipAddress) as uniqueVisitors');

        if ($from) {
            $qb->andWhere('pv.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('pv.createdAt <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get page views by hour
     */
    public function getViewsByHour(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('pv')
            ->select('HOUR(pv.createdAt) as hour, COUNT(pv.id) as views')
            ->where('DATE(pv.createdAt) = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get referrer statistics
     */
    public function getReferrerStats(int $limit = 10): array
    {
        return $this->createQueryBuilder('pv')
            ->select('pv.referrer, COUNT(pv.id) as count')
            ->where('pv.referrer IS NOT NULL')
            ->andWhere('pv.referrer != :empty')
            ->setParameter('empty', '')
            ->groupBy('pv.referrer')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user agent statistics
     */
    public function getUserAgentStats(): array
    {
        $results = $this->createQueryBuilder('pv')
            ->select('pv.userAgent, COUNT(pv.id) as count')
            ->where('pv.userAgent IS NOT NULL')
            ->groupBy('pv.userAgent')
            ->orderBy('count', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $browsers = [];
        $os = [];

        foreach ($results as $result) {
            $userAgent = $result['userAgent'];
            $count = $result['count'];

            // Détection simplifiée du navigateur
            if (strpos($userAgent, 'Chrome') !== false) {
                $browsers['Chrome'] = ($browsers['Chrome'] ?? 0) + $count;
            } elseif (strpos($userAgent, 'Firefox') !== false) {
                $browsers['Firefox'] = ($browsers['Firefox'] ?? 0) + $count;
            } elseif (strpos($userAgent, 'Safari') !== false) {
                $browsers['Safari'] = ($browsers['Safari'] ?? 0) + $count;
            } elseif (strpos($userAgent, 'Edge') !== false) {
                $browsers['Edge'] = ($browsers['Edge'] ?? 0) + $count;
            } else {
                $browsers['Other'] = ($browsers['Other'] ?? 0) + $count;
            }

            // Détection simplifiée de l'OS
            if (strpos($userAgent, 'Windows') !== false) {
                $os['Windows'] = ($os['Windows'] ?? 0) + $count;
            } elseif (strpos($userAgent, 'Mac') !== false) {
                $os['macOS'] = ($os['macOS'] ?? 0) + $count;
            } elseif (strpos($userAgent, 'Linux') !== false) {
                $os['Linux'] = ($os['Linux'] ?? 0) + $count;
            } elseif (strpos($userAgent, 'Android') !== false) {
                $os['Android'] = ($os['Android'] ?? 0) + $count;
            } elseif (strpos($userAgent, 'iOS') !== false) {
                $os['iOS'] = ($os['iOS'] ?? 0) + $count;
            } else {
                $os['Other'] = ($os['Other'] ?? 0) + $count;
            }
        }

        return [
            'browsers' => $browsers,
            'operating_systems' => $os
        ];
    }

    /**
     * Get bounce rate (single page sessions)
     */
    public function getBounceRate(\DateTimeInterface $from = null, \DateTimeInterface $to = null): float
    {
        $qb = $this->createQueryBuilder('pv')
            ->select('pv.sessionId')
            ->where('pv.sessionId IS NOT NULL');

        if ($from) {
            $qb->andWhere('pv.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('pv.createdAt <= :to')
               ->setParameter('to', $to);
        }

        $sessionViews = $qb->groupBy('pv.sessionId')
            ->having('COUNT(pv.id) = 1')
            ->getQuery()
            ->getResult();

        $totalSessions = $this->createQueryBuilder('pv')
            ->select('COUNT(DISTINCT pv.sessionId)')
            ->where('pv.sessionId IS NOT NULL');

        if ($from) {
            $totalSessions->andWhere('pv.createdAt >= :from')
                         ->setParameter('from', $from);
        }

        if ($to) {
            $totalSessions->andWhere('pv.createdAt <= :to')
                         ->setParameter('to', $to);
        }

        $total = $totalSessions->getQuery()->getSingleScalarResult();

        if ($total == 0) {
            return 0.0;
        }

        return (count($sessionViews) / $total) * 100;
    }

    /**
     * Get average session duration
     */
    public function getAverageSessionDuration(\DateTimeInterface $from = null, \DateTimeInterface $to = null): float
    {
        $qb = $this->createQueryBuilder('pv')
            ->select('pv.sessionId, MIN(pv.createdAt) as sessionStart, MAX(pv.createdAt) as sessionEnd')
            ->where('pv.sessionId IS NOT NULL')
            ->groupBy('pv.sessionId')
            ->having('COUNT(pv.id) > 1');

        if ($from) {
            $qb->andWhere('pv.createdAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('pv.createdAt <= :to')
               ->setParameter('to', $to);
        }

        $sessions = $qb->getQuery()->getResult();

        if (empty($sessions)) {
            return 0.0;
        }

        $totalDuration = 0;
        foreach ($sessions as $session) {
            $start = new \DateTime($session['sessionStart']);
            $end = new \DateTime($session['sessionEnd']);
            $totalDuration += $end->getTimestamp() - $start->getTimestamp();
        }

        return $totalDuration / count($sessions);
    }
}