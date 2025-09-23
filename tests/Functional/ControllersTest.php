<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AdminDashboardControllerTest extends WebTestCase
{
    public function testAdvancedDashboardAccess(): void
    {
        $client = static::createClient();
        
        // Note: This test assumes no authentication is required for testing
        // In a real scenario, you would need to authenticate first
        $crawler = $client->request('GET', '/admin/dashboard/advanced');
        
        // Should be accessible (or redirect to login if auth is required)
        $this->assertTrue($client->getResponse()->isSuccessful() || $client->getResponse()->isRedirection());
    }

    public function testAnalyticsDashboard(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/dashboard/analytics');
        
        $this->assertTrue($client->getResponse()->isSuccessful() || $client->getResponse()->isRedirection());
    }

    public function testReportsDashboard(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/dashboard/reports');
        
        $this->assertTrue($client->getResponse()->isSuccessful() || $client->getResponse()->isRedirection());
    }
}

class SeoControllerTest extends WebTestCase
{
    public function testSitemapGeneration(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/sitemap.xml');
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/xml');
        
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<urlset', $content);
    }

    public function testRobotsTxt(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/robots.txt');
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/plain');
        
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Sitemap:', $content);
    }
}

class CacheControllerTest extends WebTestCase
{
    public function testCacheIndexPage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/cache/');
        
        // Should be accessible or redirect to login
        $this->assertTrue($client->getResponse()->isSuccessful() || $client->getResponse()->isRedirection());
    }

    public function testClearAllCacheEndpoint(): void
    {
        $client = static::createClient();
        $client->request('POST', '/admin/cache/clear/all', [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'CONTENT_TYPE' => 'application/json',
        ]);
        
        // Should return JSON response (or redirect to login)
        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful() || $response->isRedirection());
        
        if ($response->isSuccessful()) {
            $this->assertJson($response->getContent());
        }
    }

    public function testCacheStats(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/cache/stats');
        
        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful() || $response->isRedirection());
        
        if ($response->isSuccessful()) {
            $this->assertJson($response->getContent());
        }
    }
}