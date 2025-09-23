<?php

namespace App\Tests\Unit\Service;

use App\Service\CacheService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;

class CacheServiceTest extends TestCase
{
    private CacheService $cacheService;
    private CacheItemPoolInterface $translationCache;
    private CacheItemPoolInterface $productCache;
    private CacheItemPoolInterface $categoryCache;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->translationCache = $this->createMock(CacheItemPoolInterface::class);
        $this->productCache = $this->createMock(CacheItemPoolInterface::class);
        $this->categoryCache = $this->createMock(CacheItemPoolInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->cacheService = new CacheService(
            $this->translationCache,
            $this->productCache,
            $this->categoryCache,
            $this->logger
        );
    }

    public function testGetTranslationFromCache(): void
    {
        $key = 'test_key';
        $locale = 'fr';
        $expectedData = ['value' => 'test translation'];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($expectedData);

        $this->translationCache->expects($this->once())
            ->method('getItem')
            ->with("translation_{$locale}_{$key}")
            ->willReturn($cacheItem);

        $result = $this->cacheService->getTranslation($key, $locale);

        $this->assertEquals($expectedData, $result);
    }

    public function testGetTranslationCacheMiss(): void
    {
        $key = 'test_key';
        $locale = 'en';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $this->translationCache->expects($this->once())
            ->method('getItem')
            ->with("translation_{$locale}_{$key}")
            ->willReturn($cacheItem);

        $result = $this->cacheService->getTranslation($key, $locale);

        $this->assertNull($result);
    }

    public function testSetTranslationInCache(): void
    {
        $key = 'test_key';
        $locale = 'es';
        $data = ['value' => 'prueba'];
        $ttl = 3600;

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('set')
            ->with($data);
        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with($ttl);

        $this->translationCache->expects($this->once())
            ->method('getItem')
            ->with("translation_{$locale}_{$key}")
            ->willReturn($cacheItem);
        $this->translationCache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $this->cacheService->setTranslation($key, $locale, $data, $ttl);
    }

    public function testClearAllCaches(): void
    {
        $this->translationCache->expects($this->once())->method('clear');
        $this->productCache->expects($this->once())->method('clear');
        $this->categoryCache->expects($this->once())->method('clear');

        $this->cacheService->clearAll();
    }

    public function testGetProduct(): void
    {
        $productId = 123;
        $locale = 'fr';
        $expectedData = ['id' => 123, 'name' => 'Test Product'];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($expectedData);

        $this->productCache->expects($this->once())
            ->method('getItem')
            ->with("product_{$locale}_{$productId}")
            ->willReturn($cacheItem);

        $result = $this->cacheService->getProduct($productId, $locale);

        $this->assertEquals($expectedData, $result);
    }

    public function testSetProduct(): void
    {
        $productId = 456;
        $locale = 'en';
        $data = ['id' => 456, 'name' => 'Another Product'];
        $ttl = 1800;

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('set')
            ->with($data);
        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with($ttl);

        $this->productCache->expects($this->once())
            ->method('getItem')
            ->with("product_{$locale}_{$productId}")
            ->willReturn($cacheItem);
        $this->productCache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $this->cacheService->setProduct($productId, $locale, $data, $ttl);
    }

    public function testHandleExceptionInGetTranslation(): void
    {
        $key = 'error_key';
        $locale = 'fr';

        $this->translationCache->expects($this->once())
            ->method('getItem')
            ->willThrowException(new \Exception('Cache error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Error getting translation from cache',
                $this->arrayHasKey('error')
            );

        $result = $this->cacheService->getTranslation($key, $locale);

        $this->assertNull($result);
    }
}