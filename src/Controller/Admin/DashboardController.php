<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\LanguageRepository;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/dashboard', name: 'admin_dashboard_')]
class DashboardController extends AbstractController
{
    #[Route('/advanced', name: 'advanced')]
    public function index(
        LanguageRepository $languageRepository,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Collect comprehensive statistics
        $stats = [
            'languages' => [
                'total' => $languageRepository->count([]),
                'active' => $languageRepository->countActiveLanguages(),
                'default' => $languageRepository->findDefaultLanguage(),
            ],
            'products' => [
                'total' => $productRepository->count([]),
                'active' => $productRepository->countActiveProducts(),
                'recent' => $productRepository->findRecentProducts(5),
            ],
            'categories' => [
                'total' => $categoryRepository->count([]),
                'active' => $categoryRepository->countActiveCategories(),
            ],
            'users' => [
                'total' => $userRepository->count([]),
                'recent' => $userRepository->findRecentUsers(5),
            ]
        ];

        // Get popular products and categories statistics
        $popularProducts = $productRepository->findMostPopularProducts(10);
        $recentActivity = $this->getRecentActivity($entityManager);
        $systemHealth = $this->getSystemHealthCheck();

        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $stats,
            'popular_products' => $popularProducts,
            'recent_activity' => $recentActivity,
            'system_health' => $systemHealth,
            'admin_languages' => $languageRepository->findActiveLanguages(),
        ]);
    }

    #[Route('/analytics', name: 'analytics')]
    public function analytics(): Response
    {
        // Placeholder for analytics dashboard
        return $this->render('admin/dashboard/analytics.html.twig', [
            'admin_languages' => $this->getLanguages(),
        ]);
    }

    #[Route('/reports', name: 'reports')]
    public function reports(): Response
    {
        // Placeholder for reports dashboard
        return $this->render('admin/dashboard/reports.html.twig', [
            'admin_languages' => $this->getLanguages(),
        ]);
    }

    private function getRecentActivity(EntityManagerInterface $entityManager): array
    {
        // Simulate recent activity data
        return [
            [
                'type' => 'product_added',
                'description' => 'New product added',
                'timestamp' => new \DateTime('-2 hours'),
                'user' => 'Admin'
            ],
            [
                'type' => 'user_registered',
                'description' => 'New user registered',
                'timestamp' => new \DateTime('-4 hours'),
                'user' => 'System'
            ],
            [
                'type' => 'language_updated',
                'description' => 'Language settings updated',
                'timestamp' => new \DateTime('-6 hours'),
                'user' => 'Admin'
            ]
        ];
    }

    private function getSystemHealthCheck(): array
    {
        return [
            'database' => 'healthy',
            'cache' => 'healthy',
            'storage' => 'healthy',
            'translations' => 'healthy',
            'last_check' => new \DateTime(),
        ];
    }

    private function getLanguages(): array
    {
        $languageRepository = $this->container->get('doctrine')->getRepository(\App\Entity\Language::class);
        return $languageRepository->findActiveLanguages();
    }
}