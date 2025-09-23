<?php

namespace App\Controller\Admin;

use App\Service\CacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\LanguageRepository;

#[Route('/cache', name: 'admin_cache_')]
class CacheController extends AbstractController
{
    public function __construct(
        private CacheService $cacheService,
        private LanguageRepository $languageRepository
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $stats = $this->cacheService->getCacheStats();
        
        return $this->render('admin/cache/index.html.twig', [
            'cache_stats' => $stats,
            'admin_languages' => $this->languageRepository->findActiveLanguages(),
        ]);
    }

    #[Route('/clear/all', name: 'clear_all', methods: ['POST'])]
    public function clearAll(): JsonResponse
    {
        try {
            $this->cacheService->clearAll();
            $this->addFlash('success', 'All caches cleared successfully.');
            
            return new JsonResponse([
                'success' => true,
                'message' => 'All caches cleared successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error clearing caches: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/clear/translations', name: 'clear_translations', methods: ['POST'])]
    public function clearTranslations(): JsonResponse
    {
        try {
            $this->cacheService->clearTranslationCache();
            $this->addFlash('success', 'Translation cache cleared successfully.');
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Translation cache cleared successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error clearing translation cache: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/clear/products', name: 'clear_products', methods: ['POST'])]
    public function clearProducts(): JsonResponse
    {
        try {
            $this->cacheService->clearProductCache();
            $this->addFlash('success', 'Product cache cleared successfully.');
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Product cache cleared successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error clearing product cache: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/clear/categories', name: 'clear_categories', methods: ['POST'])]
    public function clearCategories(): JsonResponse
    {
        try {
            $this->cacheService->clearCategoryCache();
            $this->addFlash('success', 'Category cache cleared successfully.');
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Category cache cleared successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error clearing category cache: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/stats', name: 'stats')]
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->cacheService->getCacheStats();
            
            return new JsonResponse([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error getting cache stats: ' . $e->getMessage()
            ], 500);
        }
    }
}