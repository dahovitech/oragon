<?php

namespace App\EventListener;

use App\Repository\LanguageRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * EventListener pour la gestion de la langue dans les URLs
 * Détecte automatiquement la langue préférée de l'utilisateur
 * et redirige vers l'URL appropriée avec préfixe de langue
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class LocaleListener
{
    public function __construct(
        private LanguageRepository $languageRepository,
        private UrlGeneratorInterface $urlGenerator,
        private string $defaultLocale = 'fr'
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Ne pas traiter les routes admin, API ou assets
        $pathInfo = $request->getPathInfo();
        if ($this->shouldSkipLocaleDetection($pathInfo)) {
            return;
        }

        // Obtenir les langues actives
        $activeLanguages = $this->languageRepository->findActiveLanguageCodes();
        if (empty($activeLanguages)) {
            return;
        }

        // Vérifier si l'URL contient déjà un préfixe de langue valide
        $locale = $this->extractLocaleFromPath($pathInfo, $activeLanguages);
        
        if ($locale) {
            // Définir la locale pour la requête
            $request->setLocale($locale);
            $request->getSession()?->set('_locale', $locale);
            
            // Modifier le pathInfo pour supprimer le préfixe de langue
            $newPathInfo = substr($pathInfo, strlen('/' . $locale));
            if (empty($newPathInfo)) {
                $newPathInfo = '/';
            }
            $request->server->set('PATH_INFO', $newPathInfo);
            
            return;
        }

        // Si pas de locale dans l'URL, détecter et rediriger
        $this->redirectToLocalizedUrl($event, $activeLanguages, $pathInfo);
    }

    /**
     * Vérifier si on doit ignorer la détection de locale pour cette route
     */
    private function shouldSkipLocaleDetection(string $pathInfo): bool
    {
        $skipPrefixes = [
            '/admin',
            '/api',
            '/assets',
            '/upload',
            '/media',
            '/_',
            '/css',
            '/js',
            '/images',
            '/fonts',
            '/favicon'
        ];

        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($pathInfo, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extraire la locale du chemin si elle existe
     */
    private function extractLocaleFromPath(string $pathInfo, array $activeLanguages): ?string
    {
        if (preg_match('#^/([a-z]{2})(/.*)?$#', $pathInfo, $matches)) {
            $locale = $matches[1];
            if (in_array($locale, $activeLanguages)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Rediriger vers l'URL localisée appropriée
     */
    private function redirectToLocalizedUrl(RequestEvent $event, array $activeLanguages, string $pathInfo): void
    {
        $request = $event->getRequest();
        
        // Détecter la langue préférée
        $preferredLocale = $this->detectPreferredLocale($request, $activeLanguages);
        
        // Construire l'URL localisée
        $localizedPath = '/' . $preferredLocale . $pathInfo;
        
        // Ajouter les paramètres de requête s'ils existent
        $queryString = $request->getQueryString();
        if ($queryString) {
            $localizedPath .= '?' . $queryString;
        }

        // Créer et définir la réponse de redirection
        $response = new RedirectResponse($localizedPath, 302);
        $event->setResponse($response);
    }

    /**
     * Détecter la langue préférée de l'utilisateur
     */
    private function detectPreferredLocale($request, array $activeLanguages): string
    {
        // 1. Vérifier la session
        $sessionLocale = $request->getSession()?->get('_locale');
        if ($sessionLocale && in_array($sessionLocale, $activeLanguages)) {
            return $sessionLocale;
        }

        // 2. Vérifier les cookies
        $cookieLocale = $request->cookies->get('locale');
        if ($cookieLocale && in_array($cookieLocale, $activeLanguages)) {
            return $cookieLocale;
        }

        // 3. Détecter depuis l'en-tête Accept-Language
        $acceptLanguage = $request->headers->get('Accept-Language', '');
        $browserLocale = $this->parseAcceptLanguage($acceptLanguage, $activeLanguages);
        if ($browserLocale) {
            return $browserLocale;
        }

        // 4. Utiliser la langue par défaut du site
        $defaultLanguage = $this->languageRepository->findDefaultLanguage();
        if ($defaultLanguage && in_array($defaultLanguage->getCode(), $activeLanguages)) {
            return $defaultLanguage->getCode();
        }

        // 5. Fallback vers la locale par défaut
        return $this->defaultLocale;
    }

    /**
     * Parser l'en-tête Accept-Language pour trouver une langue supportée
     */
    private function parseAcceptLanguage(string $acceptLanguage, array $activeLanguages): ?string
    {
        if (empty($acceptLanguage)) {
            return null;
        }

        // Parser l'en-tête Accept-Language
        $languages = [];
        $parts = explode(',', $acceptLanguage);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^([a-z]{2})(?:-[A-Z]{2})?(?:;q=([0-9.]+))?$/', $part, $matches)) {
                $locale = $matches[1];
                $quality = isset($matches[2]) ? (float) $matches[2] : 1.0;
                $languages[$locale] = $quality;
            }
        }

        // Trier par qualité décroissante
        arsort($languages);

        // Retourner la première langue supportée
        foreach (array_keys($languages) as $locale) {
            if (in_array($locale, $activeLanguages)) {
                return $locale;
            }
        }

        return null;
    }
}
