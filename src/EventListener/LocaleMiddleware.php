<?php

namespace App\EventListener;

use App\Service\LocaleService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 15)]
class LocaleMiddleware
{
    public function __construct(
        private LocaleService $localeService,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Skip for admin routes, API routes, and assets
        $pathInfo = $request->getPathInfo();
        if ($this->shouldSkipLocaleDetection($pathInfo)) {
            return;
        }

        // Get locale from various sources
        $detectedLocale = $this->detectLocale($request);
        
        // Check if detected locale is valid and active
        if ($detectedLocale && $this->localeService->isActiveLocale($detectedLocale)) {
            $request->setLocale($detectedLocale);
            
            // Store in session for persistence
            $session = $request->getSession();
            if ($session && $session->isStarted()) {
                $session->set('_locale', $detectedLocale);
            }
        } else {
            // Fallback to default locale
            $defaultLocale = $this->localeService->getDefaultLocale();
            $request->setLocale($defaultLocale);
        }

        // Handle URL redirects for SEO-friendly multilingual URLs
        $this->handleUrlRedirects($event, $request);
    }

    /**
     * Detect locale from various sources in order of priority
     */
    private function detectLocale($request): ?string
    {
        // 1. Priority: URL parameter (highest priority)
        $urlLocale = $this->getLocaleFromUrl($request);
        if ($urlLocale) {
            return $urlLocale;
        }

        // 2. Session preference
        $session = $request->getSession();
        if ($session && $session->isStarted()) {
            $sessionLocale = $session->get('_locale');
            if ($sessionLocale && $this->localeService->isActiveLocale($sessionLocale)) {
                return $sessionLocale;
            }
        }

        // 3. User preference (if user is logged in)
        $userLocale = $this->getUserPreferredLocale($request);
        if ($userLocale) {
            return $userLocale;
        }

        // 4. Browser language preference
        $browserLocale = $this->localeService->detectBrowserLanguage();
        if ($browserLocale) {
            return $browserLocale;
        }

        // 5. Fallback to default
        return $this->localeService->getDefaultLocale();
    }

    /**
     * Extract locale from URL path
     */
    private function getLocaleFromUrl($request): ?string
    {
        $pathInfo = $request->getPathInfo();
        
        // Match patterns like /fr/... or /en/...
        if (preg_match('#^/([a-z]{2})(/.*)?$#', $pathInfo, $matches)) {
            $locale = $matches[1];
            if ($this->localeService->isActiveLocale($locale)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Get user's preferred locale if authenticated
     */
    private function getUserPreferredLocale($request): ?string
    {
        // This would need to be implemented based on your User entity
        // For now, return null as this is a placeholder
        
        // Example implementation:
        // $user = $this->security->getUser();
        // if ($user && method_exists($user, 'getPreferredLocale')) {
        //     return $user->getPreferredLocale();
        // }
        
        return null;
    }

    /**
     * Handle URL redirects for multilingual URLs
     */
    private function handleUrlRedirects(RequestEvent $event, $request): void
    {
        $pathInfo = $request->getPathInfo();
        $locale = $request->getLocale();
        
        // If URL doesn't start with a locale, redirect to include it
        if (!preg_match('#^/[a-z]{2}(/.*)?$#', $pathInfo)) {
            // Don't redirect for certain paths
            if ($this->shouldSkipLocaleRedirect($pathInfo)) {
                return;
            }

            // Create new URL with locale prefix
            $newPath = '/' . $locale . $pathInfo;
            
            // Preserve query parameters
            $queryString = $request->getQueryString();
            if ($queryString) {
                $newPath .= '?' . $queryString;
            }

            $response = new RedirectResponse($newPath, 301);
            $event->setResponse($response);
        }
    }

    /**
     * Check if locale detection should be skipped for this path
     */
    private function shouldSkipLocaleDetection(string $pathInfo): bool
    {
        $skipPatterns = [
            '#^/admin#',           // Admin routes
            '#^/api#',             // API routes
            '#^/_#',               // Symfony internal routes (profiler, etc.)
            '#^/bundles#',         // Asset bundles
            '#^/build#',           // Webpack build assets
            '#^/uploads#',         // Upload directory
            '#^/media#',           // Media files
            '#\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$#', // Static assets
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $pathInfo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if locale redirect should be skipped for this path
     */
    private function shouldSkipLocaleRedirect(string $pathInfo): bool
    {
        $skipPatterns = [
            '#^/$#',               // Homepage - handle separately
            '#^/admin#',           // Admin routes
            '#^/api#',             // API routes
            '#^/_#',               // Symfony internal routes
            '#^/bundles#',         // Asset bundles
            '#^/build#',           // Webpack build assets
            '#^/uploads#',         // Upload directory
            '#^/media#',           // Media files
            '#\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$#', // Static assets
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $pathInfo)) {
                return true;
            }
        }

        return false;
    }
}