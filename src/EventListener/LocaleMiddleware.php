<?php

namespace App\EventListener;

use App\Service\LocaleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class LocaleMiddleware implements EventSubscriberInterface
{
    public function __construct(
        private LocaleService $localeService,
        private RouterInterface $router,
        private string $defaultLocale = 'fr'
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]]
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Skip for internal requests or non-master requests
        if (!$event->isMainRequest()) {
            return;
        }

        // Skip for admin routes, API routes, or assets
        if ($this->shouldSkipLocaleDetection($request)) {
            return;
        }

        $pathInfo = $request->getPathInfo();
        $localeFromUrl = $this->extractLocaleFromUrl($pathInfo);

        if ($localeFromUrl) {
            // Locale found in URL
            if ($this->localeService->isValidLocale($localeFromUrl)) {
                // Valid locale, set it and continue
                $request->setLocale($localeFromUrl);
                $request->attributes->set('_locale', $localeFromUrl);
                
                // Store in session for future requests
                if ($request->hasSession()) {
                    $request->getSession()->set('_locale', $localeFromUrl);
                }
            } else {
                // Invalid locale in URL, redirect to default
                $this->redirectToValidLocale($event, $pathInfo, $this->defaultLocale);
                return;
            }
        } else {
            // No locale in URL, determine the best locale and redirect
            $bestLocale = $this->determineBestLocale($request);
            $this->redirectToValidLocale($event, $pathInfo, $bestLocale);
            return;
        }
    }

    /**
     * Extract locale from URL path
     */
    private function extractLocaleFromUrl(string $pathInfo): ?string
    {
        // Check if path starts with a locale (e.g., /fr/page or /en/products)
        if (preg_match('/^\/([a-z]{2})(?:\/|$)/', $pathInfo, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Determine the best locale for the user
     */
    private function determineBestLocale(Request $request): string
    {
        // 1. Check session
        if ($request->hasSession()) {
            $sessionLocale = $request->getSession()->get('_locale');
            if ($sessionLocale && $this->localeService->isValidLocale($sessionLocale)) {
                return $sessionLocale;
            }
        }

        // 2. Check user preference (if authenticated)
        // This would check user entity preferences if implemented
        // $user = $this->security->getUser();
        // if ($user && method_exists($user, 'getPreferredLocale')) {
        //     $userLocale = $user->getPreferredLocale();
        //     if ($userLocale && $this->localeService->isValidLocale($userLocale)) {
        //         return $userLocale;
        //     }
        // }

        // 3. Check browser language
        $browserLocale = $this->getBrowserLocale($request);
        if ($browserLocale && $this->localeService->isValidLocale($browserLocale)) {
            return $browserLocale;
        }

        // 4. Fallback to default
        return $this->defaultLocale;
    }

    /**
     * Get browser preferred locale
     */
    private function getBrowserLocale(Request $request): ?string
    {
        $acceptLanguage = $request->headers->get('Accept-Language');
        
        if (!$acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header
        $locales = [];
        foreach (explode(',', $acceptLanguage) as $part) {
            $parts = explode(';', trim($part));
            $locale = trim($parts[0]);
            $quality = 1.0;
            
            if (isset($parts[1]) && strpos($parts[1], 'q=') === 0) {
                $quality = (float) substr($parts[1], 2);
            }
            
            // Extract just the language part (e.g., 'fr' from 'fr-FR')
            $langCode = strtolower(explode('-', $locale)[0]);
            $locales[$langCode] = $quality;
        }

        // Sort by quality (preference)
        arsort($locales);

        // Return first available locale
        foreach (array_keys($locales) as $locale) {
            if ($this->localeService->isValidLocale($locale)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Redirect to URL with valid locale
     */
    private function redirectToValidLocale(RequestEvent $event, string $pathInfo, string $locale): void
    {
        $request = $event->getRequest();
        
        // Remove existing locale from path if present
        $pathWithoutLocale = preg_replace('/^\/[a-z]{2}(?=\/|$)/', '', $pathInfo);
        
        // Ensure path starts with /
        if (!str_starts_with($pathWithoutLocale, '/')) {
            $pathWithoutLocale = '/' . $pathWithoutLocale;
        }

        // Create new URL with locale
        $newPath = '/' . $locale . $pathWithoutLocale;
        
        // Preserve query string
        $queryString = $request->getQueryString();
        if ($queryString) {
            $newPath .= '?' . $queryString;
        }

        $response = new RedirectResponse($newPath, 302);
        $event->setResponse($response);
    }

    /**
     * Check if locale detection should be skipped for this request
     */
    private function shouldSkipLocaleDetection(Request $request): bool
    {
        $pathInfo = $request->getPathInfo();

        // Skip for admin routes
        if (str_starts_with($pathInfo, '/admin')) {
            return true;
        }

        // Skip for API routes
        if (str_starts_with($pathInfo, '/api')) {
            return true;
        }

        // Skip for assets (CSS, JS, images, etc.)
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot|pdf)$/i', $pathInfo)) {
            return true;
        }

        // Skip for Symfony profiler
        if (str_starts_with($pathInfo, '/_profiler') || str_starts_with($pathInfo, '/_wdt')) {
            return true;
        }

        // Skip for security routes (login, logout, etc.)
        if (str_starts_with($pathInfo, '/login') || str_starts_with($pathInfo, '/logout')) {
            return true;
        }

        return false;
    }

    /**
     * Get available locales for this application
     */
    private function getAvailableLocales(): array
    {
        return $this->localeService->getAvailableLocales();
    }
}