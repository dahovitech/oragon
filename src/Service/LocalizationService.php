<?php

namespace App\Service;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service pour la gestion de la localisation
 * Gère la langue courante, les URLs localisées et les mécanismes de fallback
 */
class LocalizationService
{
    private ?Language $currentLanguage = null;

    public function __construct(
        private LanguageRepository $languageRepository,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    /**
     * Obtenir la langue courante
     */
    public function getCurrentLanguage(): ?Language
    {
        if ($this->currentLanguage === null) {
            $request = $this->requestStack->getCurrentRequest();
            $locale = $request?->getLocale() ?? 'fr';
            
            $this->currentLanguage = $this->languageRepository->findActiveByCode($locale);
            
            if (!$this->currentLanguage) {
                $this->currentLanguage = $this->languageRepository->findDefaultLanguage();
            }
        }

        return $this->currentLanguage;
    }

    /**
     * Obtenir le code de la langue courante
     */
    public function getCurrentLocale(): string
    {
        $language = $this->getCurrentLanguage();
        return $language ? $language->getCode() : 'fr';
    }

    /**
     * Obtenir toutes les langues actives
     */
    public function getActiveLanguages(): array
    {
        return $this->languageRepository->findActiveLanguages();
    }

    /**
     * Générer une URL localisée
     */
    public function generateLocalizedUrl(string $route, string $locale, array $parameters = []): string
    {
        // Ajouter le préfixe de langue aux paramètres
        $localizedParameters = array_merge(['_locale' => $locale], $parameters);
        
        return $this->urlGenerator->generate($route, $localizedParameters);
    }

    /**
     * Obtenir l'URL de la page courante dans une autre langue
     */
    public function getCurrentUrlInLocale(string $locale): string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return '/';
        }

        $currentRoute = $request->attributes->get('_route');
        $currentParams = $request->attributes->get('_route_params', []);
        
        // Supprimer l'ancien paramètre de locale s'il existe
        unset($currentParams['_locale']);
        
        try {
            return $this->generateLocalizedUrl($currentRoute, $locale, $currentParams);
        } catch (\Exception $e) {
            // Fallback vers la page d'accueil si la route n'est pas trouvée
            return '/' . $locale . '/';
        }
    }

    /**
     * Vérifier si une langue est disponible
     */
    public function isLanguageAvailable(string $locale): bool
    {
        $activeLanguages = $this->languageRepository->findActiveLanguageCodes();
        return in_array($locale, $activeLanguages);
    }

    /**
     * Obtenir la langue par défaut
     */
    public function getDefaultLanguage(): ?Language
    {
        return $this->languageRepository->findDefaultLanguage();
    }

    /**
     * Obtenir le code de la langue par défaut
     */
    public function getDefaultLocale(): string
    {
        $defaultLanguage = $this->getDefaultLanguage();
        return $defaultLanguage ? $defaultLanguage->getCode() : 'fr';
    }

    /**
     * Définir la langue courante (utile pour les tests ou l'administration)
     */
    public function setCurrentLanguage(?Language $language): void
    {
        $this->currentLanguage = $language;
    }

    /**
     * Obtenir les informations de langue pour le sélecteur
     */
    public function getLanguageSelectorData(): array
    {
        $languages = $this->getActiveLanguages();
        $currentLocale = $this->getCurrentLocale();
        $result = [];

        foreach ($languages as $language) {
            $result[] = [
                'code' => $language->getCode(),
                'name' => $language->getName(),
                'nativeName' => $language->getNativeName(),
                'isCurrent' => $language->getCode() === $currentLocale,
                'url' => $this->getCurrentUrlInLocale($language->getCode())
            ];
        }

        return $result;
    }

    /**
     * Obtenir le chemin sans préfixe de langue
     */
    public function getPathWithoutLocale(string $path): string
    {
        $activeLanguages = $this->languageRepository->findActiveLanguageCodes();
        
        foreach ($activeLanguages as $locale) {
            $prefix = '/' . $locale;
            if (str_starts_with($path, $prefix . '/') || $path === $prefix) {
                return substr($path, strlen($prefix)) ?: '/';
            }
        }

        return $path;
    }

    /**
     * Ajouter un préfixe de langue à un chemin
     */
    public function addLocaleToPath(string $path, ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        // Supprimer d'abord tout préfixe existant
        $cleanPath = $this->getPathWithoutLocale($path);
        
        // Ajouter le nouveau préfixe
        if ($cleanPath === '/') {
            return '/' . $locale . '/';
        }
        
        return '/' . $locale . $cleanPath;
    }

    /**
     * Vérifier si le contenu est traduit dans la langue courante
     */
    public function isContentTranslated($entity, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        if (method_exists($entity, 'isTranslatedInto')) {
            return $entity->isTranslatedInto($locale);
        }
        
        if (method_exists($entity, 'getTranslation')) {
            $translation = $entity->getTranslation($locale);
            return $translation && method_exists($translation, 'isComplete') ? $translation->isComplete() : (bool) $translation;
        }

        return true; // Par défaut, considérer comme traduit
    }

    /**
     * Obtenir le contenu avec fallback vers d'autres langues
     */
    public function getContentWithFallback($entity, string $method, ?string $locale = null)
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        // Essayer d'abord la langue demandée
        if (method_exists($entity, 'setCurrentLocale')) {
            $entity->setCurrentLocale($locale);
        }
        
        if (method_exists($entity, $method)) {
            $content = $entity->$method($locale);
            if (!empty($content)) {
                return $content;
            }
        }

        // Fallback vers la langue par défaut
        $defaultLocale = $this->getDefaultLocale();
        if ($locale !== $defaultLocale) {
            if (method_exists($entity, 'setCurrentLocale')) {
                $entity->setCurrentLocale($defaultLocale);
            }
            
            if (method_exists($entity, $method)) {
                $content = $entity->$method($defaultLocale);
                if (!empty($content)) {
                    return $content;
                }
            }
        }

        // Fallback vers n'importe quelle langue disponible
        if (method_exists($entity, 'getAvailableLocales')) {
            $availableLocales = $entity->getAvailableLocales();
            foreach ($availableLocales as $availableLocale) {
                if ($availableLocale !== $locale && $availableLocale !== $defaultLocale) {
                    if (method_exists($entity, 'setCurrentLocale')) {
                        $entity->setCurrentLocale($availableLocale);
                    }
                    
                    if (method_exists($entity, $method)) {
                        $content = $entity->$method($availableLocale);
                        if (!empty($content)) {
                            return $content;
                        }
                    }
                }
            }
        }

        return null;
    }
}
