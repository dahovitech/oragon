<?php

namespace App\Controller;

use App\Service\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SeoController extends AbstractController
{
    public function __construct(private SeoService $seoService)
    {
    }

    #[Route('/sitemap.xml', name: 'sitemap')]
    public function sitemap(): Response
    {
        $xmlContent = $this->seoService->generateSitemap();
        
        $response = new Response($xmlContent);
        $response->headers->set('Content-Type', 'application/xml');
        $response->headers->set('Cache-Control', 'public, max-age=3600'); // Cache for 1 hour
        
        return $response;
    }

    #[Route('/robots.txt', name: 'robots')]
    public function robots(): Response
    {
        $content = $this->seoService->generateRobotsTxt();
        
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Cache-Control', 'public, max-age=86400'); // Cache for 24 hours
        
        return $response;
    }
}