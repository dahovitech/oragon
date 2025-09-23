<?php

namespace App\Controller\Admin;

use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Repository\BlogRepository;
use App\Service\LocaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/analytics', name: 'admin_analytics_')]
#[IsGranted('ROLE_ADMIN')]
class AnalyticsController extends AbstractController
{
    public function __construct(
        private OrderRepository $orderRepository,
        private ProductRepository $productRepository,
        private UserRepository $userRepository,
        private BlogRepository $blogRepository,
        private LocaleService $localeService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $period = $request->query->get('period', 'last_30_days');
        $dateRange = $this->getDateRange($period);

        // General Statistics
        $stats = [
            'total_orders' => $this->orderRepository->count([]),
            'total_products' => $this->productRepository->count(['isActive' => true]),
            'total_users' => $this->userRepository->count(['isActive' => true]),
            'total_blog_posts' => $this->blogRepository->count(['isPublished' => true])
        ];

        // Revenue Statistics
        $revenue = $this->getRevenueStats($dateRange);
        
        // Language Statistics
        $languageStats = $this->getLanguageStats($dateRange);

        // Top Products
        $topProducts = $this->getTopProducts($dateRange, 10);

        // Recent Orders
        $recentOrders = $this->orderRepository->findBy(
            [], 
            ['createdAt' => 'DESC'], 
            10
        );

        // User Growth
        $userGrowth = $this->getUserGrowthStats($dateRange);

        return $this->render('admin/analytics/dashboard.html.twig', [
            'stats' => $stats,
            'revenue' => $revenue,
            'language_stats' => $languageStats,
            'top_products' => $topProducts,
            'recent_orders' => $recentOrders,
            'user_growth' => $userGrowth,
            'period' => $period,
            'available_languages' => $this->localeService->getActiveLanguages()
        ]);
    }

    #[Route('/reports/translation-status', name: 'translation_reports', methods: ['GET'])]
    public function translationReports(): Response
    {
        $languages = $this->localeService->getActiveLanguages();
        $defaultLocale = $this->localeService->getDefaultLanguage()->getCode();

        // Get translation completion stats for products
        $productStats = $this->getTranslationCompletionStats('Product', 'ProductTranslation', $languages);
        
        // Get translation completion stats for blog posts
        $blogStats = $this->getTranslationCompletionStats('Blog', 'BlogTranslation', $languages);
        
        // Get translation completion stats for categories
        $categoryStats = $this->getTranslationCompletionStats('Category', 'CategoryTranslation', $languages);

        // Missing translations report
        $missingTranslations = $this->getMissingTranslations($languages, $defaultLocale);

        return $this->render('admin/analytics/translation_reports.html.twig', [
            'languages' => $languages,
            'default_locale' => $defaultLocale,
            'product_stats' => $productStats,
            'blog_stats' => $blogStats,
            'category_stats' => $categoryStats,
            'missing_translations' => $missingTranslations
        ]);
    }

    #[Route('/api/sales-chart/{period}', name: 'api_sales_chart', methods: ['GET'])]
    public function salesChartData(string $period): JsonResponse
    {
        $dateRange = $this->getDateRange($period);
        
        $salesData = $this->orderRepository->createQueryBuilder('o')
            ->select('DATE(o.createdAt) as date, SUM(o.totalAmount) as total, COUNT(o.id) as orders_count')
            ->where('o.createdAt >= :startDate')
            ->andWhere('o.createdAt <= :endDate')
            ->andWhere('o.status IN (:completedStatuses)')
            ->setParameter('startDate', $dateRange['start'])
            ->setParameter('endDate', $dateRange['end'])
            ->setParameter('completedStatuses', ['delivered', 'completed'])
            ->groupBy('DATE(o.createdAt)')
            ->orderBy('DATE(o.createdAt)', 'ASC')
            ->getQuery()
            ->getResult();

        // Fill missing dates with zero values
        $filledData = $this->fillMissingDates($salesData, $dateRange, $period);

        return $this->json([
            'labels' => array_column($filledData, 'date'),
            'revenue' => array_column($filledData, 'total'),
            'orders' => array_column($filledData, 'orders_count')
        ]);
    }

    #[Route('/api/language-distribution', name: 'api_language_distribution', methods: ['GET'])]
    public function languageDistribution(): JsonResponse
    {
        $languages = $this->localeService->getActiveLanguages();
        $distribution = [];

        foreach ($languages as $language) {
            // Get user count by language preference
            $userCount = $this->userRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('JSON_EXTRACT(u.preferences, \'$.language\') = :language')
                ->setParameter('language', $language->getCode())
                ->getQuery()
                ->getSingleScalarResult();

            // Get order count by user language
            $orderCount = $this->orderRepository->createQueryBuilder('o')
                ->select('COUNT(o.id)')
                ->leftJoin('o.user', 'u')
                ->where('JSON_EXTRACT(u.preferences, \'$.language\') = :language')
                ->setParameter('language', $language->getCode())
                ->getQuery()
                ->getSingleScalarResult();

            $distribution[] = [
                'language' => $language->getNativeName(),
                'code' => $language->getCode(),
                'users' => (int) $userCount,
                'orders' => (int) $orderCount
            ];
        }

        return $this->json($distribution);
    }

    #[Route('/api/top-products/{period}', name: 'api_top_products', methods: ['GET'])]
    public function topProductsData(string $period): JsonResponse
    {
        $dateRange = $this->getDateRange($period);
        $topProducts = $this->getTopProducts($dateRange, 20);

        $data = [];
        foreach ($topProducts as $product) {
            $data[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'total_sold' => (int) $product['total_sold'],
                'revenue' => (float) $product['revenue']
            ];
        }

        return $this->json($data);
    }

    private function getDateRange(string $period): array
    {
        $endDate = new \DateTimeImmutable();
        
        switch ($period) {
            case 'today':
                $startDate = $endDate->setTime(0, 0, 0);
                break;
            case 'yesterday':
                $startDate = $endDate->modify('-1 day')->setTime(0, 0, 0);
                $endDate = $endDate->modify('-1 day')->setTime(23, 59, 59);
                break;
            case 'last_7_days':
                $startDate = $endDate->modify('-7 days')->setTime(0, 0, 0);
                break;
            case 'last_30_days':
                $startDate = $endDate->modify('-30 days')->setTime(0, 0, 0);
                break;
            case 'last_3_months':
                $startDate = $endDate->modify('-3 months')->setTime(0, 0, 0);
                break;
            case 'last_year':
                $startDate = $endDate->modify('-1 year')->setTime(0, 0, 0);
                break;
            case 'this_month':
                $startDate = $endDate->modify('first day of this month')->setTime(0, 0, 0);
                break;
            case 'last_month':
                $startDate = $endDate->modify('first day of last month')->setTime(0, 0, 0);
                $endDate = $endDate->modify('last day of last month')->setTime(23, 59, 59);
                break;
            default:
                $startDate = $endDate->modify('-30 days')->setTime(0, 0, 0);
        }

        return ['start' => $startDate, 'end' => $endDate];
    }

    private function getRevenueStats(array $dateRange): array
    {
        $revenue = $this->orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalAmount) as total, COUNT(o.id) as orders_count, AVG(o.totalAmount) as avg_order')
            ->where('o.createdAt >= :startDate')
            ->andWhere('o.createdAt <= :endDate')
            ->andWhere('o.status IN (:completedStatuses)')
            ->setParameter('startDate', $dateRange['start'])
            ->setParameter('endDate', $dateRange['end'])
            ->setParameter('completedStatuses', ['delivered', 'completed'])
            ->getQuery()
            ->getOneOrNullResult();

        // Get previous period for comparison
        $previousPeriod = [
            'start' => $dateRange['start']->modify('-' . $dateRange['start']->diff($dateRange['end'])->days . ' days'),
            'end' => $dateRange['start']
        ];

        $previousRevenue = $this->orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalAmount) as total')
            ->where('o.createdAt >= :startDate')
            ->andWhere('o.createdAt <= :endDate')
            ->andWhere('o.status IN (:completedStatuses)')
            ->setParameter('startDate', $previousPeriod['start'])
            ->setParameter('endDate', $previousPeriod['end'])
            ->setParameter('completedStatuses', ['delivered', 'completed'])
            ->getQuery()
            ->getSingleScalarResult();

        $currentTotal = (float) ($revenue['total'] ?? 0);
        $previousTotal = (float) ($previousRevenue ?? 0);
        
        $growth = $previousTotal > 0 ? (($currentTotal - $previousTotal) / $previousTotal) * 100 : 0;

        return [
            'total' => $currentTotal,
            'orders_count' => (int) ($revenue['orders_count'] ?? 0),
            'avg_order_value' => (float) ($revenue['avg_order'] ?? 0),
            'growth_percentage' => round($growth, 2)
        ];
    }

    private function getLanguageStats(array $dateRange): array
    {
        $languages = $this->localeService->getActiveLanguages();
        $stats = [];

        foreach ($languages as $language) {
            $orderCount = $this->orderRepository->createQueryBuilder('o')
                ->select('COUNT(o.id)')
                ->leftJoin('o.user', 'u')
                ->where('o.createdAt >= :startDate')
                ->andWhere('o.createdAt <= :endDate')
                ->andWhere('JSON_EXTRACT(u.preferences, \'$.language\') = :language')
                ->setParameter('startDate', $dateRange['start'])
                ->setParameter('endDate', $dateRange['end'])
                ->setParameter('language', $language->getCode())
                ->getQuery()
                ->getSingleScalarResult();

            $revenue = $this->orderRepository->createQueryBuilder('o')
                ->select('SUM(o.totalAmount)')
                ->leftJoin('o.user', 'u')
                ->where('o.createdAt >= :startDate')
                ->andWhere('o.createdAt <= :endDate')
                ->andWhere('JSON_EXTRACT(u.preferences, \'$.language\') = :language')
                ->andWhere('o.status IN (:completedStatuses)')
                ->setParameter('startDate', $dateRange['start'])
                ->setParameter('endDate', $dateRange['end'])
                ->setParameter('language', $language->getCode())
                ->setParameter('completedStatuses', ['delivered', 'completed'])
                ->getQuery()
                ->getSingleScalarResult();

            $stats[] = [
                'language' => $language,
                'orders' => (int) $orderCount,
                'revenue' => (float) ($revenue ?? 0)
            ];
        }

        return $stats;
    }

    private function getTopProducts(array $dateRange, int $limit): array
    {
        return $this->entityManager->createQuery('
            SELECT p.id, pt.name, SUM(oi.quantity) as total_sold, SUM(oi.totalPrice) as revenue
            FROM App\Entity\OrderItem oi
            JOIN oi.product p
            JOIN oi.order o
            LEFT JOIN p.translations pt WITH pt.language = :defaultLanguage
            WHERE o.createdAt >= :startDate 
            AND o.createdAt <= :endDate
            AND o.status IN (:completedStatuses)
            GROUP BY p.id, pt.name
            ORDER BY total_sold DESC
        ')
        ->setParameter('startDate', $dateRange['start'])
        ->setParameter('endDate', $dateRange['end'])
        ->setParameter('completedStatuses', ['delivered', 'completed'])
        ->setParameter('defaultLanguage', $this->localeService->getDefaultLanguage())
        ->setMaxResults($limit)
        ->getResult();
    }

    private function getUserGrowthStats(array $dateRange): array
    {
        return $this->userRepository->createQueryBuilder('u')
            ->select('DATE(u.createdAt) as date, COUNT(u.id) as count')
            ->where('u.createdAt >= :startDate')
            ->andWhere('u.createdAt <= :endDate')
            ->setParameter('startDate', $dateRange['start'])
            ->setParameter('endDate', $dateRange['end'])
            ->groupBy('DATE(u.createdAt)')
            ->orderBy('DATE(u.createdAt)', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function getTranslationCompletionStats(string $entity, string $translationEntity, array $languages): array
    {
        $stats = [];
        $entityClass = 'App\\Entity\\' . $entity;
        $translationClass = 'App\\Entity\\' . $translationEntity;

        $totalEntities = $this->entityManager->getRepository($entityClass)->count([]);

        foreach ($languages as $language) {
            $translatedCount = $this->entityManager->createQuery("
                SELECT COUNT(DISTINCT e.id)
                FROM {$entityClass} e
                JOIN {$translationClass} t WITH t.{strtolower($entity)} = e
                WHERE t.language = :language
            ")
            ->setParameter('language', $language)
            ->getSingleScalarResult();

            $completionPercentage = $totalEntities > 0 ? ($translatedCount / $totalEntities) * 100 : 0;

            $stats[$language->getCode()] = [
                'language' => $language,
                'total' => $totalEntities,
                'translated' => (int) $translatedCount,
                'completion_percentage' => round($completionPercentage, 1)
            ];
        }

        return $stats;
    }

    private function getMissingTranslations(array $languages, string $defaultLocale): array
    {
        $missing = [];
        
        // Check products missing translations
        foreach ($languages as $language) {
            if ($language->getCode() === $defaultLocale) continue;

            $missingProducts = $this->entityManager->createQuery('
                SELECT p.id, pt_default.name
                FROM App\Entity\Product p
                LEFT JOIN p.translations pt WITH pt.language = :language
                LEFT JOIN p.translations pt_default WITH pt_default.language = :defaultLanguage
                WHERE p.isActive = true AND pt.id IS NULL
            ')
            ->setParameter('language', $language)
            ->setParameter('defaultLanguage', $this->localeService->getDefaultLanguage())
            ->getResult();

            if (!empty($missingProducts)) {
                $missing['products'][$language->getCode()] = $missingProducts;
            }
        }

        return $missing;
    }

    private function fillMissingDates(array $data, array $dateRange, string $period): array
    {
        $filled = [];
        $interval = $period === 'today' || $period === 'yesterday' ? new \DateInterval('PT1H') : new \DateInterval('P1D');
        
        $current = clone $dateRange['start'];
        $dataByDate = [];
        
        foreach ($data as $row) {
            $dataByDate[$row['date']] = $row;
        }

        while ($current <= $dateRange['end']) {
            $dateKey = $current->format('Y-m-d');
            
            $filled[] = [
                'date' => $dateKey,
                'total' => isset($dataByDate[$dateKey]) ? (float) $dataByDate[$dateKey]['total'] : 0,
                'orders_count' => isset($dataByDate[$dateKey]) ? (int) $dataByDate[$dateKey]['orders_count'] : 0
            ];
            
            $current = $current->add($interval);
        }

        return $filled;
    }
}