<?php

namespace App\Service;

use App\Repository\LanguageRepository;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;

class SeoService
{
    public function __construct(
        private LanguageRepository $languageRepository,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private UrlGeneratorInterface $urlGenerator,
        private LocaleService $localeService
    ) {
    }

    /**
     * Generate multilingual sitemap XML
     */
    public function generateSitemap(): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml"></urlset>');
        
        $languages = $this->languageRepository->findActiveLanguages();
        
        // Add homepage
        $this->addMultilingualUrl($xml, 'homepage', [], $languages);
        
        // Add product pages
        $products = $this->productRepository->findActive();
        foreach ($products as $product) {
            $this->addMultilingualUrl($xml, 'product_detail', ['id' => $product->getId()], $languages, $product->getUpdatedAt());
        }
        
        // Add category pages
        $categories = $this->categoryRepository->findRootCategories();
        foreach ($categories as $category) {
            $this->addMultilingualUrl($xml, 'category_products', ['id' => $category->getId()], $languages);
        }
        
        // Add static pages
        $this->addMultilingualUrl($xml, 'about', [], $languages);
        $this->addMultilingualUrl($xml, 'contact', [], $languages);
        $this->addMultilingualUrl($xml, 'blog_index', [], $languages);
        
        return $xml->asXML();
    }

    /**
     * Add URL with hreflang alternatives to sitemap
     */
    private function addMultilingualUrl(\SimpleXMLElement $xml, string $route, array $params, array $languages, ?\DateTime $lastMod = null): void
    {
        foreach ($languages as $language) {
            $url = $xml->addChild('url');
            
            // Generate URL for this language
            $routeParams = array_merge($params, ['_locale' => $language->getCode()]);
            $loc = $this->urlGenerator->generate($route, $routeParams, UrlGeneratorInterface::ABSOLUTE_URL);
            $url->addChild('loc', htmlspecialchars($loc));
            
            if ($lastMod) {
                $url->addChild('lastmod', $lastMod->format('Y-m-d\TH:i:s\Z'));
            }
            
            $url->addChild('changefreq', 'weekly');
            $url->addChild('priority', '0.8');
            
            // Add hreflang alternatives
            foreach ($languages as $altLanguage) {
                $link = $url->addChild('xhtml:link', '', 'http://www.w3.org/1999/xhtml');
                $link->addAttribute('rel', 'alternate');
                $link->addAttribute('hreflang', $altLanguage->getCode());
                
                $altParams = array_merge($params, ['_locale' => $altLanguage->getCode()]);
                $altUrl = $this->urlGenerator->generate($route, $altParams, UrlGeneratorInterface::ABSOLUTE_URL);
                $link->addAttribute('href', $altUrl);
            }
        }
    }

    /**
     * Generate hreflang tags for a specific page
     */
    public function generateHreflangTags(string $route, array $params = []): array
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $hreflangTags = [];
        
        foreach ($languages as $language) {
            $routeParams = array_merge($params, ['_locale' => $language->getCode()]);
            $url = $this->urlGenerator->generate($route, $routeParams, UrlGeneratorInterface::ABSOLUTE_URL);
            
            $hreflangTags[] = [
                'hreflang' => $language->getCode(),
                'href' => $url,
                'language' => $language
            ];
        }
        
        return $hreflangTags;
    }

    /**
     * Generate meta tags for SEO
     */
    public function generateMetaTags(array $data): array
    {
        $meta = [
            'title' => $data['title'] ?? 'Oragon E-commerce',
            'description' => $data['description'] ?? 'Your multilingual e-commerce solution',
            'keywords' => $data['keywords'] ?? 'e-commerce, multilingual, shop',
            'canonical' => $data['canonical'] ?? null,
            'robots' => $data['robots'] ?? 'index,follow',
            'og_title' => $data['og_title'] ?? $data['title'] ?? 'Oragon E-commerce',
            'og_description' => $data['og_description'] ?? $data['description'] ?? 'Your multilingual e-commerce solution',
            'og_image' => $data['og_image'] ?? null,
            'og_url' => $data['og_url'] ?? null,
            'og_type' => $data['og_type'] ?? 'website',
            'twitter_card' => $data['twitter_card'] ?? 'summary',
            'twitter_title' => $data['twitter_title'] ?? $data['title'] ?? 'Oragon E-commerce',
            'twitter_description' => $data['twitter_description'] ?? $data['description'] ?? 'Your multilingual e-commerce solution',
            'twitter_image' => $data['twitter_image'] ?? $data['og_image'] ?? null,
        ];
        
        return $meta;
    }

    /**
     * Generate structured data (JSON-LD) for products
     */
    public function generateProductStructuredData($product, string $locale): array
    {
        $productTranslation = null;
        foreach ($product->getTranslations() as $translation) {
            if ($translation->getLanguage()->getCode() === $locale) {
                $productTranslation = $translation;
                break;
            }
        }
        
        if (!$productTranslation) {
            return [];
        }

        $structuredData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $productTranslation->getName(),
            'description' => $productTranslation->getDescription(),
            'sku' => $product->getSku(),
            'gtin' => $product->getGtin() ?? $product->getSku(),
            'offers' => [
                '@type' => 'Offer',
                'url' => $this->urlGenerator->generate('product_detail', [
                    'id' => $product->getId(),
                    '_locale' => $locale
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'priceCurrency' => 'EUR', // Should be configurable
                'price' => $product->getPrice(),
                'availability' => $product->getStockQuantity() > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ]
        ];

        // Add brand if available
        if ($product->getBrand()) {
            $structuredData['brand'] = [
                '@type' => 'Brand',
                'name' => $product->getBrand()->getName()
            ];
        }

        // Add category if available
        if ($product->getCategory()) {
            $categoryTranslation = null;
            foreach ($product->getCategory()->getTranslations() as $translation) {
                if ($translation->getLanguage()->getCode() === $locale) {
                    $categoryTranslation = $translation;
                    break;
                }
            }
            
            if ($categoryTranslation) {
                $structuredData['category'] = $categoryTranslation->getName();
            }
        }

        // Add images if available
        if ($product->getImages() && count($product->getImages()) > 0) {
            $structuredData['image'] = [];
            foreach ($product->getImages() as $image) {
                $structuredData['image'][] = $image->getPath(); // Assuming full URL
            }
        }

        return $structuredData;
    }

    /**
     * Generate structured data for organization
     */
    public function generateOrganizationStructuredData(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Oragon E-commerce',
            'url' => $this->urlGenerator->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'logo' => $this->urlGenerator->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL) . '/assets/logo.png',
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => '+33-1-23-45-67-89', // Should be configurable
                'contactType' => 'customer service',
                'availableLanguage' => array_map(
                    fn($lang) => $lang->getCode(),
                    $this->languageRepository->findActiveLanguages()
                )
            ]
        ];
    }

    /**
     * Generate breadcrumb structured data
     */
    public function generateBreadcrumbStructuredData(array $breadcrumbs): array
    {
        $listItems = [];
        
        foreach ($breadcrumbs as $position => $breadcrumb) {
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $breadcrumb['name'],
                'item' => $breadcrumb['url']
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems
        ];
    }

    /**
     * Optimize URL slug for SEO
     */
    public function optimizeSlug(string $text, string $locale = 'fr'): string
    {
        // Remove accents and special characters
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove', $text);
        
        // Convert to lowercase
        $text = strtolower($text);
        
        // Replace spaces and special characters with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        // Remove leading and trailing hyphens
        $text = trim($text, '-');
        
        // Remove multiple consecutive hyphens
        $text = preg_replace('/-+/', '-', $text);
        
        return $text;
    }

    /**
     * Generate robots.txt content
     */
    public function generateRobotsTxt(): string
    {
        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /admin/\n";
        $content .= "Disallow: /api/\n";
        $content .= "Disallow: /_profiler/\n";
        $content .= "\n";
        
        // Add sitemap URL
        $sitemapUrl = $this->urlGenerator->generate('sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $content .= "Sitemap: $sitemapUrl\n";
        
        return $content;
    }
}