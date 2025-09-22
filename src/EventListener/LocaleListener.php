<?php

namespace App\EventListener;

use App\Repository\LanguageRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class LocaleListener
{
    public function __construct(
        private LanguageRepository $languageRepository,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Skip if not main request
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $request->attributes->get('_route');
        $isAdminRoute = $route && str_starts_with($route, 'admin_');
        $isFrontendRoute = $route && str_starts_with($route, 'frontend_');

        if ($isAdminRoute) {
            $this->handleAdminLocale($request);
        } elseif ($isFrontendRoute) {
            $this->handleFrontendLocale($request, $event);
        }
    }

    private function handleAdminLocale($request): void
    {
        $session = $request->getSession();
        
        // Get locale from session first, then from request, then default
        $locale = $session->get('_admin_locale');
        
        if (!$locale) {
            $locale = $request->getLocale();
        }
        
        // If still no locale, get the default language from database
        if (!$locale) {
            $defaultLanguage = $this->languageRepository->findDefaultLanguage();
            
            if ($defaultLanguage) {
                $locale = $defaultLanguage->getCode();
            } else {
                // Fallback to French if no default language is found
                $locale = 'fr';
            }
        }

        // Validate that the current locale exists in our languages
        $activeLanguage = $this->languageRepository->findActiveByCode($locale);
        if (!$activeLanguage) {
            // If current locale is not active, fall back to default
            $defaultLanguage = $this->languageRepository->findDefaultLanguage();
            if ($defaultLanguage) {
                $locale = $defaultLanguage->getCode();
            }
        }
        
        // Set the locale for the request and store in session
        $request->setLocale($locale);
        $session->set('_admin_locale', $locale);
    }

    private function handleFrontendLocale($request, RequestEvent $event): void
    {
        $pathInfo = $request->getPathInfo();
        $session = $request->getSession();
        
        // Extract locale from URL path (e.g., /fr/services or /en/about)
        $urlLocale = $this->extractLocaleFromPath($pathInfo);
        
        if ($urlLocale) {
            // Check if the locale is valid and active
            $language = $this->languageRepository->findActiveByCode($urlLocale);
            if ($language) {
                $request->setLocale($urlLocale);
                $session->set('_locale', $urlLocale);
                
                // Update request attributes for route matching
                $newPathInfo = substr($pathInfo, strlen('/' . $urlLocale));
                if (empty($newPathInfo)) {
                    $newPathInfo = '/';
                }
                $request->server->set('PATH_INFO', $newPathInfo);
                $request->attributes->set('_locale', $urlLocale);
                return;\n            }\n        }\n\n        // No valid locale in URL, detect and redirect\n        $detectedLocale = $this->detectUserLocale($request, $session);\n        \n        // Only redirect if we're on the homepage or a route without locale\n        if ($this->shouldRedirectToLocalizedUrl($pathInfo)) {\n            $localizedPath = '/' . $detectedLocale . $pathInfo;\n            $response = new RedirectResponse($localizedPath, 302);\n            $event->setResponse($response);\n            return;\n        }\n        \n        // Set the detected locale for the request\n        $request->setLocale($detectedLocale);\n        $session->set('_locale', $detectedLocale);\n    }\n\n    private function extractLocaleFromPath(string $pathInfo): ?string\n    {\n        if (preg_match('#^/([a-z]{2})(/.*)?$#', $pathInfo, $matches)) {\n            return $matches[1];\n        }\n        return null;\n    }\n\n    private function detectUserLocale($request, $session): string\n    {\n        // First, check session\n        $sessionLocale = $session->get('_locale');\n        if ($sessionLocale && $this->languageRepository->findActiveByCode($sessionLocale)) {\n            return $sessionLocale;\n        }\n\n        // Then, check browser preferences\n        $acceptLanguages = $request->getLanguages();\n        $activeLanguageCodes = $this->languageRepository->findActiveLanguageCodes();\n        \n        foreach ($acceptLanguages as $browserLocale) {\n            // Try exact match first\n            if (in_array($browserLocale, $activeLanguageCodes)) {\n                return $browserLocale;\n            }\n            \n            // Try language part only (e.g., 'en' from 'en-US')\n            $languagePart = substr($browserLocale, 0, 2);\n            if (in_array($languagePart, $activeLanguageCodes)) {\n                return $languagePart;\n            }\n        }\n\n        // Fallback to default language\n        $defaultLanguage = $this->languageRepository->findDefaultLanguage();\n        return $defaultLanguage ? $defaultLanguage->getCode() : 'fr';\n    }\n\n    private function shouldRedirectToLocalizedUrl(string $pathInfo): bool\n    {\n        // Redirect for homepage and main navigation paths\n        $pathsToRedirect = [\n            '/',\n            '/services',\n            '/about',\n            '/contact',\n            '/news',\n            '/portfolio'\n        ];\n        \n        return in_array($pathInfo, $pathsToRedirect) || \n               preg_match('#^/[^/]+$#', $pathInfo); // Single level paths\n    }\n}
