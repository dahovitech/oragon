<?php

namespace App\Bundle\AnalyticsBundle\Service;

use App\Bundle\AnalyticsBundle\Repository\PageViewRepository;
use App\Bundle\AnalyticsBundle\Repository\EventRepository;
use App\Bundle\BlogBundle\Repository\PostRepository;
use App\Bundle\EcommerceBundle\Repository\OrderRepository;
use App\Bundle\EcommerceBundle\Repository\ProductRepository;
use App\Bundle\UserBundle\Repository\UserRepository;

class ReportGenerator
{
    private PageViewRepository $pageViewRepository;
    private EventRepository $eventRepository;
    private PostRepository $postRepository;
    private OrderRepository $orderRepository;
    private ProductRepository $productRepository;
    private UserRepository $userRepository;

    public function __construct(
        PageViewRepository $pageViewRepository,
        EventRepository $eventRepository,
        PostRepository $postRepository,
        OrderRepository $orderRepository,
        ProductRepository $productRepository,
        UserRepository $userRepository
    ) {
        $this->pageViewRepository = $pageViewRepository;
        $this->eventRepository = $eventRepository;
        $this->postRepository = $postRepository;
        $this->orderRepository = $orderRepository;
        $this->productRepository = $productRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Generate comprehensive dashboard data
     */
    public function generateDashboard(\DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        if (!$from) {
            $from = new \DateTimeImmutable('-30 days');
        }
        if (!$to) {
            $to = new \DateTimeImmutable();
        }

        return [
            'overview' => $this->getOverviewStats($from, $to),
            'traffic' => $this->getTrafficReport($from, $to),
            'content' => $this->getContentReport($from, $to),
            'ecommerce' => $this->getEcommerceReport($from, $to),
            'users' => $this->getUserReport($from, $to),
            'real_time' => $this->getRealTimeData()
        ];
    }

    /**
     * Get overview statistics
     */
    public function getOverviewStats(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $pageViews = $this->pageViewRepository->createQueryBuilder('pv')
            ->select('COUNT(pv.id)')
            ->where('pv.createdAt >= :from')
            ->andWhere('pv.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $uniqueVisitors = $this->pageViewRepository->getUniqueVisitors($from, $to);
        $bounceRate = $this->pageViewRepository->getBounceRate($from, $to);
        $avgSessionDuration = $this->pageViewRepository->getAverageSessionDuration($from, $to);

        $totalEvents = $this->eventRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.createdAt >= :from')
            ->andWhere('e.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'page_views' => $pageViews,
            'unique_visitors' => $uniqueVisitors,
            'bounce_rate' => round($bounceRate, 2),
            'avg_session_duration' => round($avgSessionDuration, 0),
            'total_events' => $totalEvents,
            'pages_per_session' => $uniqueVisitors > 0 ? round($pageViews / $uniqueVisitors, 2) : 0
        ];
    }

    /**
     * Get traffic report
     */
    public function getTrafficReport(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return [
            'daily_views' => $this->pageViewRepository->getViewsByDateRange($from, $to),
            'top_pages' => $this->pageViewRepository->getTopPages(10, $from, $to),
            'referrers' => $this->pageViewRepository->getReferrerStats(10),
            'user_agents' => $this->pageViewRepository->getUserAgentStats(),
            'hourly_distribution' => $this->getHourlyDistribution($from, $to)
        ];
    }

    /**
     * Get content report (blog)
     */
    public function getContentReport(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        // Articles les plus lus
        $topPosts = $this->pageViewRepository->createQueryBuilder('pv')
            ->select('pv.url, pv.title, COUNT(pv.id) as views')
            ->where('pv.url LIKE :blogPath')
            ->andWhere('pv.createdAt >= :from')
            ->andWhere('pv.createdAt <= :to')
            ->setParameter('blogPath', '%/blog/%')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('pv.url', 'pv.title')
            ->orderBy('views', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Événements liés au blog
        $blogEvents = $this->eventRepository->createQueryBuilder('e')
            ->select('e.action, COUNT(e.id) as count')
            ->where('e.category = :category')
            ->andWhere('e.createdAt >= :from')
            ->andWhere('e.createdAt <= :to')
            ->setParameter('category', 'content')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('e.action')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'top_posts' => $topPosts,
            'blog_events' => $blogEvents,
            'content_engagement' => $this->getContentEngagement($from, $to)
        ];
    }

    /**
     * Get e-commerce report
     */
    public function getEcommerceReport(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        // Statistiques des commandes
        $orderStats = $this->orderRepository->getStatistics($from, $to);

        // Revenus mensuels
        $monthlyRevenue = $this->orderRepository->getMonthlyRevenue($to->format('Y'));

        // Produits les plus vus
        $topViewedProducts = $this->pageViewRepository->createQueryBuilder('pv')
            ->select('pv.url, pv.title, COUNT(pv.id) as views')
            ->where('pv.url LIKE :productPath')
            ->andWhere('pv.createdAt >= :from')
            ->andWhere('pv.createdAt <= :to')
            ->setParameter('productPath', '%/shop/product/%')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('pv.url', 'pv.title')
            ->orderBy('views', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Événements e-commerce
        $ecommerceEvents = $this->eventRepository->createQueryBuilder('e')
            ->select('e.action, COUNT(e.id) as count, SUM(e.value) as totalValue')
            ->where('e.category = :category')
            ->andWhere('e.createdAt >= :from')
            ->andWhere('e.createdAt <= :to')
            ->setParameter('category', 'commerce')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('e.action')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        // Taux de conversion
        $productViews = $this->eventRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.eventType = :viewType')
            ->andWhere('e.createdAt >= :from')
            ->andWhere('e.createdAt <= :to')
            ->setParameter('viewType', 'product_view')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $purchases = $orderStats['total_orders'];
        $conversionRate = $productViews > 0 ? ($purchases / $productViews) * 100 : 0;

        return [
            'order_statistics' => $orderStats,
            'monthly_revenue' => $monthlyRevenue,
            'top_viewed_products' => $topViewedProducts,
            'ecommerce_events' => $ecommerceEvents,
            'conversion_rate' => round($conversionRate, 2),
            'cart_abandonment' => $this->getCartAbandonmentRate($from, $to)
        ];
    }

    /**
     * Get user report
     */
    public function getUserReport(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $userStats = $this->userRepository->getUserStatistics();

        // Nouvelles inscriptions par période
        $newUsers = $this->userRepository->createQueryBuilder('u')
            ->select('DATE(u.createdAt) as date, COUNT(u.id) as count')
            ->where('u.createdAt >= :from')
            ->andWhere('u.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        // Activité des utilisateurs
        $userActivity = $this->eventRepository->createQueryBuilder('e')
            ->select('e.userId, COUNT(e.id) as activity_count')
            ->where('e.userId IS NOT NULL')
            ->andWhere('e.createdAt >= :from')
            ->andWhere('e.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('e.userId')
            ->orderBy('activity_count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return [
            'user_statistics' => $userStats,
            'new_users' => $newUsers,
            'user_activity' => $userActivity,
            'user_engagement' => $this->getUserEngagement($from, $to)
        ];
    }

    /**
     * Get real-time data
     */
    public function getRealTimeData(): array
    {
        $now = new \DateTimeImmutable();
        $oneHourAgo = $now->modify('-1 hour');

        return [
            'active_users' => $this->getActiveUsers(),
            'recent_pageviews' => $this->pageViewRepository->createQueryBuilder('pv')
                ->where('pv.createdAt >= :oneHourAgo')
                ->setParameter('oneHourAgo', $oneHourAgo)
                ->orderBy('pv.createdAt', 'DESC')
                ->setMaxResults(20)
                ->getQuery()
                ->getResult(),
            'recent_events' => $this->eventRepository->getRealTimeEvents(),
            'current_traffic' => $this->getCurrentTrafficSources()
        ];
    }

    /**
     * Get hourly distribution of traffic
     */
    private function getHourlyDistribution(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $hourlyData = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyData[$i] = 0;
        }

        $results = $this->pageViewRepository->createQueryBuilder('pv')
            ->select('HOUR(pv.createdAt) as hour, COUNT(pv.id) as views')
            ->where('pv.createdAt >= :from')
            ->andWhere('pv.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('hour')
            ->getQuery()
            ->getResult();

        foreach ($results as $result) {
            $hourlyData[(int)$result['hour']] = (int)$result['views'];
        }

        return $hourlyData;
    }

    /**
     * Get content engagement metrics
     */
    private function getContentEngagement(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $engagementEvents = ['read_more', 'share', 'comment', 'like'];
        $engagement = [];

        foreach ($engagementEvents as $event) {
            $count = $this->eventRepository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->where('e.action = :action')
                ->andWhere('e.createdAt >= :from')
                ->andWhere('e.createdAt <= :to')
                ->setParameter('action', $event)
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getQuery()
                ->getSingleScalarResult();

            $engagement[$event] = $count;
        }

        return $engagement;
    }

    /**
     * Get cart abandonment rate
     */
    private function getCartAbandonmentRate(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        $cartAdds = $this->eventRepository->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.sessionId)')
            ->where('e.action = :action')
            ->andWhere('e.createdAt >= :from')
            ->andWhere('e.createdAt <= :to')
            ->setParameter('action', 'add_to_cart')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $purchases = $this->eventRepository->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.sessionId)')
            ->where('e.action = :action')
            ->andWhere('e.createdAt >= :from')
            ->andWhere('e.createdAt <= :to')
            ->setParameter('action', 'purchase')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        if ($cartAdds == 0) {
            return 0;
        }

        return ((1 - ($purchases / $cartAdds)) * 100);
    }

    /**
     * Get user engagement metrics
     */
    private function getUserEngagement(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        // Utilisateurs actifs
        $activeUsers = $this->eventRepository->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.userId)')
            ->where('e.userId IS NOT NULL')
            ->andWhere('e.createdAt >= :from')
            ->andWhere('e.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        // Sessions par utilisateur
        $sessionsPerUser = $this->pageViewRepository->createQueryBuilder('pv')
            ->select('AVG(sessions_count)')
            ->from('(' . 
                $this->pageViewRepository->createQueryBuilder('pv2')
                    ->select('pv2.userId, COUNT(DISTINCT pv2.sessionId) as sessions_count')
                    ->where('pv2.userId IS NOT NULL')
                    ->andWhere('pv2.createdAt >= :from')
                    ->andWhere('pv2.createdAt <= :to')
                    ->groupBy('pv2.userId')
                    ->getDQL() . 
                ') as user_sessions')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'active_users' => $activeUsers,
            'avg_sessions_per_user' => round($sessionsPerUser ?: 0, 2),
            'user_retention' => $this->getUserRetention($from, $to)
        ];
    }

    /**
     * Get current active users (last 5 minutes)
     */
    private function getActiveUsers(): int
    {
        $fiveMinutesAgo = new \DateTimeImmutable('-5 minutes');
        
        return $this->pageViewRepository->createQueryBuilder('pv')
            ->select('COUNT(DISTINCT pv.sessionId)')
            ->where('pv.createdAt >= :fiveMinutesAgo')
            ->setParameter('fiveMinutesAgo', $fiveMinutesAgo)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get current traffic sources
     */
    private function getCurrentTrafficSources(): array
    {
        $oneHourAgo = new \DateTimeImmutable('-1 hour');
        
        return $this->pageViewRepository->createQueryBuilder('pv')
            ->select('pv.referrer, COUNT(pv.id) as count')
            ->where('pv.createdAt >= :oneHourAgo')
            ->andWhere('pv.referrer IS NOT NULL')
            ->setParameter('oneHourAgo', $oneHourAgo)
            ->groupBy('pv.referrer')
            ->orderBy('count', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user retention rate
     */
    private function getUserRetention(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        // Utilisateurs qui sont revenus dans la période
        $returningUsers = $this->pageViewRepository->createQueryBuilder('pv')
            ->select('COUNT(DISTINCT pv.userId)')
            ->where('pv.userId IS NOT NULL')
            ->andWhere('pv.createdAt >= :from')
            ->andWhere('pv.createdAt <= :to')
            ->andWhere('pv.userId IN (' .
                $this->pageViewRepository->createQueryBuilder('pv2')
                    ->select('DISTINCT pv2.userId')
                    ->where('pv2.userId IS NOT NULL')
                    ->andWhere('pv2.createdAt < :from')
                    ->getDQL() .
                ')')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        // Total d'utilisateurs existants avant la période
        $existingUsers = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt < :from')
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();

        if ($existingUsers == 0) {
            return 0;
        }

        return ($returningUsers / $existingUsers) * 100;
    }
}