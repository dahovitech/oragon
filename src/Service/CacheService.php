<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class CacheService
{
    private CacheItemPoolInterface $translationCache;
    private CacheItemPoolInterface $productCache;
    private CacheItemPoolInterface $categoryCache;
    private LoggerInterface $logger;

    public function __construct(
        CacheItemPoolInterface $translationCache,
        CacheItemPoolInterface $productCache,
        CacheItemPoolInterface $categoryCache,
        LoggerInterface $logger
    ) {
        $this->translationCache = $translationCache;
        $this->productCache = $productCache;
        $this->categoryCache = $categoryCache;
        $this->logger = $logger;
    }

    /**
     * Get translation from cache
     */
    public function getTranslation(string $key, string $locale): ?array
    {
        try {
            $cacheKey = $this->generateTranslationKey($key, $locale);
            $item = $this->translationCache->getItem($cacheKey);
            
            if ($item->isHit()) {
                $this->logger->info('Translation cache hit', ['key' => $key, 'locale' => $locale]);
                return $item->get();
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Error getting translation from cache', [
                'key' => $key,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Store translation in cache
     */
    public function setTranslation(string $key, string $locale, array $data, int $ttl = 86400): void
    {
        try {
            $cacheKey = $this->generateTranslationKey($key, $locale);
            $item = $this->translationCache->getItem($cacheKey);
            $item->set($data);
            $item->expiresAfter($ttl);
            
            $this->translationCache->save($item);
            $this->logger->info('Translation cached', ['key' => $key, 'locale' => $locale]);
        } catch (\Exception $e) {
            $this->logger->error('Error setting translation cache', [
                'key' => $key,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get product from cache
     */
    public function getProduct(int $productId, string $locale): ?array
    {
        try {
            $cacheKey = $this->generateProductKey($productId, $locale);
            $item = $this->productCache->getItem($cacheKey);
            
            if ($item->isHit()) {
                return $item->get();
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Error getting product from cache', [
                'productId' => $productId,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Store product in cache
     */
    public function setProduct(int $productId, string $locale, array $data, int $ttl = 3600): void
    {
        try {
            $cacheKey = $this->generateProductKey($productId, $locale);
            $item = $this->productCache->getItem($cacheKey);
            $item->set($data);
            $item->expiresAfter($ttl);
            
            $this->productCache->save($item);
        } catch (\Exception $e) {
            $this->logger->error('Error setting product cache', [
                'productId' => $productId,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get category from cache
     */
    public function getCategory(int $categoryId, string $locale): ?array
    {
        try {
            $cacheKey = $this->generateCategoryKey($categoryId, $locale);
            $item = $this->categoryCache->getItem($cacheKey);
            
            if ($item->isHit()) {
                return $item->get();
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Error getting category from cache', [
                'categoryId' => $categoryId,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Store category in cache
     */
    public function setCategory(int $categoryId, string $locale, array $data, int $ttl = 7200): void
    {
        try {
            $cacheKey = $this->generateCategoryKey($categoryId, $locale);
            $item = $this->categoryCache->getItem($cacheKey);
            $item->set($data);
            $item->expiresAfter($ttl);
            
            $this->categoryCache->save($item);
        } catch (\Exception $e) {
            $this->logger->error('Error setting category cache', [
                'categoryId' => $categoryId,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all caches
     */
    public function clearAll(): void
    {
        try {
            $this->translationCache->clear();
            $this->productCache->clear();
            $this->categoryCache->clear();
            $this->logger->info('All caches cleared');
        } catch (\Exception $e) {
            $this->logger->error('Error clearing caches', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Clear translation cache
     */
    public function clearTranslationCache(): void
    {
        try {
            $this->translationCache->clear();
            $this->logger->info('Translation cache cleared');
        } catch (\Exception $e) {
            $this->logger->error('Error clearing translation cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Clear product cache
     */
    public function clearProductCache(): void
    {
        try {
            $this->productCache->clear();
            $this->logger->info('Product cache cleared');
        } catch (\Exception $e) {
            $this->logger->error('Error clearing product cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Clear category cache
     */
    public function clearCategoryCache(): void
    {
        try {
            $this->categoryCache->clear();
            $this->logger->info('Category cache cleared');
        } catch (\Exception $e) {
            $this->logger->error('Error clearing category cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        // This would require implementing cache adapter with stats support
        return [
            'translation_cache' => ['hits' => 0, 'misses' => 0],
            'product_cache' => ['hits' => 0, 'misses' => 0],
            'category_cache' => ['hits' => 0, 'misses' => 0],
        ];
    }

    private function generateTranslationKey(string $key, string $locale): string
    {
        return "translation_{$locale}_{$key}";
    }

    private function generateProductKey(int $productId, string $locale): string
    {
        return "product_{$locale}_{$productId}";
    }

    private function generateCategoryKey(int $categoryId, string $locale): string
    {
        return "category_{$locale}_{$categoryId}";
    }
}