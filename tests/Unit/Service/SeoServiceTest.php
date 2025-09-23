<?php

namespace App\Tests\Unit\Service;

use App\Service\SeoService;
use App\Service\LocaleService;
use App\Repository\LanguageRepository;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Entity\Language;
use App\Entity\Product;
use App\Entity\Category;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SeoServiceTest extends TestCase
{
    private SeoService $seoService;
    private LanguageRepository $languageRepository;
    private ProductRepository $productRepository;
    private CategoryRepository $categoryRepository;
    private UrlGeneratorInterface $urlGenerator;
    private LocaleService $localeService;

    protected function setUp(): void
    {
        $this->languageRepository = $this->createMock(LanguageRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->localeService = $this->createMock(LocaleService::class);

        $this->seoService = new SeoService(
            $this->languageRepository,
            $this->productRepository,
            $this->categoryRepository,
            $this->urlGenerator,
            $this->localeService
        );
    }

    public function testOptimizeSlug(): void
    {
        $testCases = [
            'Simple text' => 'simple-text',
            'Text with accents àéîôù' => 'text-with-accents-aeiou',
            'Special chars @#$%^&*()' => 'special-chars',
            'Multiple   spaces' => 'multiple-spaces',
            '---Leading and trailing---' => 'leading-and-trailing',
            'UPPERCASE Text' => 'uppercase-text',
            'français café créé' => 'francais-cafe-cree',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->seoService->optimizeSlug($input);
            $this->assertEquals($expected, $result, "Failed for input: '$input'");
        }
    }

    public function testGenerateMetaTags(): void
    {
        $data = [
            'title' => 'Test Product',
            'description' => 'This is a test product description',
            'keywords' => 'test, product, keywords',
        ];

        $meta = $this->seoService->generateMetaTags($data);

        $this->assertArrayHasKey('title', $meta);
        $this->assertArrayHasKey('description', $meta);
        $this->assertArrayHasKey('keywords', $meta);
        $this->assertArrayHasKey('og_title', $meta);
        $this->assertArrayHasKey('og_description', $meta);
        $this->assertArrayHasKey('twitter_card', $meta);

        $this->assertEquals('Test Product', $meta['title']);
        $this->assertEquals('This is a test product description', $meta['description']);
        $this->assertEquals('test, product, keywords', $meta['keywords']);
        $this->assertEquals('Test Product', $meta['og_title']);
        $this->assertEquals('summary', $meta['twitter_card']);
    }

    public function testGenerateMetaTagsWithDefaults(): void
    {
        $meta = $this->seoService->generateMetaTags([]);

        $this->assertEquals('Oragon E-commerce', $meta['title']);
        $this->assertEquals('Your multilingual e-commerce solution', $meta['description']);
        $this->assertEquals('e-commerce, multilingual, shop', $meta['keywords']);
        $this->assertEquals('index,follow', $meta['robots']);
        $this->assertEquals('website', $meta['og_type']);
    }

    public function testGenerateHreflangTags(): void
    {
        $language1 = new Language();
        $language1->setCode('fr');
        $language1->setName('Français');

        $language2 = new Language();
        $language2->setCode('en');
        $language2->setName('English');

        $languages = [$language1, $language2];

        $this->languageRepository->expects($this->once())
            ->method('findActiveLanguages')
            ->willReturn($languages);

        $this->urlGenerator->expects($this->exactly(2))
            ->method('generate')
            ->willReturnCallback(function ($route, $params, $type) {
                $locale = $params['_locale'];
                return "https://example.com/$locale/test-page";
            });

        $hreflangTags = $this->seoService->generateHreflangTags('test_route', ['id' => 123]);

        $this->assertCount(2, $hreflangTags);
        
        $this->assertEquals('fr', $hreflangTags[0]['hreflang']);
        $this->assertEquals('https://example.com/fr/test-page', $hreflangTags[0]['href']);
        
        $this->assertEquals('en', $hreflangTags[1]['hreflang']);
        $this->assertEquals('https://example.com/en/test-page', $hreflangTags[1]['href']);
    }

    public function testGenerateOrganizationStructuredData(): void
    {
        $this->languageRepository->expects($this->once())
            ->method('findActiveLanguages')
            ->willReturn([]);

        $this->urlGenerator->expects($this->exactly(2))
            ->method('generate')
            ->willReturn('https://example.com');

        $structuredData = $this->seoService->generateOrganizationStructuredData();

        $this->assertEquals('https://schema.org', $structuredData['@context']);
        $this->assertEquals('Organization', $structuredData['@type']);
        $this->assertEquals('Oragon E-commerce', $structuredData['name']);
        $this->assertArrayHasKey('contactPoint', $structuredData);
        $this->assertEquals('ContactPoint', $structuredData['contactPoint']['@type']);
    }

    public function testGenerateBreadcrumbStructuredData(): void
    {
        $breadcrumbs = [
            ['name' => 'Home', 'url' => 'https://example.com'],
            ['name' => 'Products', 'url' => 'https://example.com/products'],
            ['name' => 'Laptops', 'url' => 'https://example.com/products/laptops'],
        ];

        $structuredData = $this->seoService->generateBreadcrumbStructuredData($breadcrumbs);

        $this->assertEquals('https://schema.org', $structuredData['@context']);
        $this->assertEquals('BreadcrumbList', $structuredData['@type']);
        $this->assertArrayHasKey('itemListElement', $structuredData);
        $this->assertCount(3, $structuredData['itemListElement']);

        $firstItem = $structuredData['itemListElement'][0];
        $this->assertEquals(1, $firstItem['position']);
        $this->assertEquals('Home', $firstItem['name']);
        $this->assertEquals('https://example.com', $firstItem['item']);

        $lastItem = $structuredData['itemListElement'][2];
        $this->assertEquals(3, $lastItem['position']);
        $this->assertEquals('Laptops', $lastItem['name']);
        $this->assertEquals('https://example.com/products/laptops', $lastItem['item']);
    }

    public function testGenerateRobotsTxt(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/sitemap.xml');

        $robotsTxt = $this->seoService->generateRobotsTxt();

        $this->assertStringContainsString('User-agent: *', $robotsTxt);
        $this->assertStringContainsString('Allow: /', $robotsTxt);
        $this->assertStringContainsString('Disallow: /admin/', $robotsTxt);
        $this->assertStringContainsString('Disallow: /api/', $robotsTxt);
        $this->assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $robotsTxt);
    }
}