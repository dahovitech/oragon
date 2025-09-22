<?php

namespace App\Twig;

use App\Service\LocalizationService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Extension Twig pour la gestion de l'internationalisation
 * Expose les fonctionnalités de localisation dans les templates
 */
class LocalizationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private LocalizationService $localizationService
    ) {}

    /**
     * Exposer des variables globales dans Twig
     */
    public function getGlobals(): array
    {
        return [
            'localization_service' => $this->localizationService,
            'current_language' => $this->localizationService->getCurrentLanguage(),
            'available_languages' => $this->localizationService->getActiveLanguages(),
        ];
    }

    /**
     * Fonctions Twig personnalisées
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('localized_url', [$this, 'getLocalizedUrl']),
            new TwigFunction('current_url_in_locale', [$this, 'getCurrentUrlInLocale']),
            new TwigFunction('is_language_available', [$this, 'isLanguageAvailable']),
            new TwigFunction('content_with_fallback', [$this, 'getContentWithFallback']),
            new TwigFunction('is_content_translated', [$this, 'isContentTranslated']),
        ];
    }

    /**
     * Générer une URL localisée
     */
    public function getLocalizedUrl(string $route, string $locale, array $parameters = []): string
    {
        return $this->localizationService->generateLocalizedUrl($route, $locale, $parameters);
    }

    /**
     * Obtenir l'URL courante dans une autre langue
     */
    public function getCurrentUrlInLocale(string $locale): string
    {
        return $this->localizationService->getCurrentUrlInLocale($locale);
    }

    /**
     * Vérifier si une langue est disponible
     */
    public function isLanguageAvailable(string $locale): bool
    {
        return $this->localizationService->isLanguageAvailable($locale);
    }

    /**
     * Obtenir du contenu avec fallback
     */
    public function getContentWithFallback($entity, string $method, ?string $locale = null)
    {
        return $this->localizationService->getContentWithFallback($entity, $method, $locale);
    }

    /**
     * Vérifier si le contenu est traduit
     */
    public function isContentTranslated($entity, ?string $locale = null): bool
    {
        return $this->localizationService->isContentTranslated($entity, $locale);
    }
}
