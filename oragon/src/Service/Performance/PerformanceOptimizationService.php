<?php

namespace App\Service\Performance;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

class PerformanceOptimizationService
{
    public function __construct(
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Cache a value with automatic key generation and expiration
     */
    public function cacheValue(string $key, callable $callback, int $ttl = 3600): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl) {
            $item->expiresAfter($ttl);
            return $callback();
        });
    }

    /**
     * Cache database query results
     */
    public function cacheQuery(string $queryId, callable $queryCallback, int $ttl = 3600): mixed
    {
        $cacheKey = 'query_' . md5($queryId);
        
        return $this->cacheValue($cacheKey, function() use ($queryCallback, $queryId) {
            $startTime = microtime(true);
            $result = $queryCallback();
            $duration = microtime(true) - $startTime;
            
            // Log slow queries
            if ($duration > 1.0) {
                $this->logger->warning('Slow query detected', [
                    'query_id' => $queryId,
                    'duration' => $duration,
                ]);
            }
            
            return $result;
        }, $ttl);
    }

    /**
     * Cache view/template results
     */
    public function cacheView(string $template, array $parameters = [], int $ttl = 3600): string
    {
        $cacheKey = 'view_' . md5($template . serialize($parameters));
        
        return $this->cacheValue($cacheKey, function() use ($template, $parameters) {
            // This would typically render the template
            // For now, return a placeholder
            return "Cached view: $template";
        }, $ttl);
    }

    /**
     * Cache API responses
     */
    public function cacheApiResponse(string $endpoint, array $params = [], int $ttl = 1800): array
    {
        $cacheKey = 'api_' . md5($endpoint . serialize($params));
        
        return $this->cacheValue($cacheKey, function() use ($endpoint, $params) {
            // This would typically make the API call
            $this->logger->info('API cache miss', ['endpoint' => $endpoint]);
            return ['cached' => true, 'endpoint' => $endpoint, 'params' => $params];
        }, $ttl);
    }

    /**
     * Warm up cache with critical data
     */
    public function warmupCache(): array
    {
        $results = [];
        
        // Cache critical configurations
        $results['config'] = $this->cacheValue('app_config', function() {
            return [
                'app_name' => 'Oragon Platform',
                'version' => '1.0.0',
                'features' => ['2fa', 'cache', 'api'],
            ];
        }, 86400);
        
        // Cache menu items
        $results['menu'] = $this->cacheValue('main_menu', function() {
            return [
                ['name' => 'Dashboard', 'url' => '/dashboard'],
                ['name' => 'Users', 'url' => '/admin/users'],
                ['name' => 'Settings', 'url' => '/settings'],
            ];
        }, 3600);
        
        // Cache system statistics
        $results['stats'] = $this->cacheValue('system_stats', function() {
            return [
                'users_count' => 0, // Would query database
                'active_sessions' => 0,
                'cache_hit_ratio' => 0.95,
            ];
        }, 300);
        
        $this->logger->info('Cache warmed up', ['cached_items' => array_keys($results)]);
        
        return $results;
    }

    /**
     * Clear cache by pattern
     */
    public function clearCacheByPattern(string $pattern): void
    {
        // Symfony's cache component doesn't have built-in pattern clearing
        // This would require a custom cache adapter or Redis-specific commands
        $this->logger->info('Cache clear requested', ['pattern' => $pattern]);
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        // This would typically query Redis or the cache adapter for statistics
        return [
            'hit_ratio' => 0.85,
            'miss_ratio' => 0.15,
            'total_keys' => 1250,
            'memory_usage' => '45MB',
            'uptime' => '24h 15m',
            'operations_per_second' => 1500,
        ];
    }

    /**
     * Optimize images for web delivery
     */
    public function optimizeImage(string $imagePath, array $options = []): array
    {
        $defaultOptions = [
            'quality' => 85,
            'format' => 'webp',
            'sizes' => [320, 640, 1024, 1920],
            'lazy_load' => true,
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        // This would typically process the image using ImageMagick or similar
        $optimizedImages = [];
        
        foreach ($options['sizes'] as $size) {
            $optimizedImages[] = [
                'size' => $size,
                'url' => "/images/optimized/{$size}/" . basename($imagePath),
                'format' => $options['format'],
            ];
        }
        
        $this->logger->info('Image optimized', [
            'original' => $imagePath,
            'variants' => count($optimizedImages),
        ]);
        
        return [
            'original' => $imagePath,
            'optimized' => $optimizedImages,
            'lazy_load' => $options['lazy_load'],
        ];
    }

    /**
     * Generate critical CSS for above-the-fold content
     */
    public function generateCriticalCSS(string $url): string
    {
        // This would typically use a tool like Puppeteer to extract critical CSS
        $criticalCSS = "
            body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
            .header { background: #fff; border-bottom: 1px solid #e0e0e0; }
            .nav { display: flex; align-items: center; padding: 1rem; }
            .btn-primary { background: #007bff; border: 1px solid #007bff; color: #fff; padding: 0.375rem 0.75rem; }
        ";
        
        $this->logger->info('Critical CSS generated', ['url' => $url, 'size' => strlen($criticalCSS)]);
        
        return $criticalCSS;
    }

    /**
     * Preload critical resources
     */
    public function getCriticalResources(): array
    {
        return [
            [
                'type' => 'style',
                'href' => '/build/css/app.css',
                'as' => 'style',
            ],
            [
                'type' => 'script',
                'href' => '/build/js/app.js',
                'as' => 'script',
            ],
            [
                'type' => 'font',
                'href' => '/fonts/main.woff2',
                'as' => 'font',
                'crossorigin' => true,
            ],
        ];
    }

    /**
     * Monitor performance metrics
     */
    public function recordPerformanceMetric(string $metric, float $value, array $tags = []): void
    {
        $this->logger->info('Performance metric recorded', [
            'metric' => $metric,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => time(),
        ]);
        
        // This would typically send metrics to a monitoring system like Prometheus
    }

    /**
     * Get performance recommendations
     */
    public function getPerformanceRecommendations(): array
    {
        $stats = $this->getCacheStats();
        $recommendations = [];
        
        if ($stats['hit_ratio'] < 0.8) {
            $recommendations[] = [
                'type' => 'cache',
                'priority' => 'high',
                'message' => 'Cache hit ratio is low. Consider increasing cache TTL for stable data.',
            ];
        }
        
        if ($stats['memory_usage'] === '45MB') {
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'medium',
                'message' => 'Memory usage is moderate. Monitor for growth patterns.',
            ];
        }
        
        $recommendations[] = [
            'type' => 'optimization',
            'priority' => 'low',
            'message' => 'Consider implementing image lazy loading on product pages.',
        ];
        
        return $recommendations;
    }
}