<?php

namespace App\Twig;

use App\Repository\LanguageRepository;
use App\Service\TranslationManagerService;
use App\Service\LocaleService;
use App\Service\TranslationService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class TranslationExtension extends AbstractExtension
{
    public function __construct(
        private LanguageRepository $languageRepository,
        private TranslationManagerService $translationManager,
        private LocaleService $localeService,
        private TranslationService $translationService,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function getFunctions(): array
    {
        return [
            // Existing functions
            new TwigFunction('get_admin_languages', [$this, 'getAdminLanguages']),
            new TwigFunction('get_current_admin_language', [$this, 'getCurrentAdminLanguage']),
            new TwigFunction('translation_progress', [$this, 'getTranslationProgress']),
            new TwigFunction('is_translation_complete', [$this, 'isTranslationComplete']),
            new TwigFunction('get_available_languages', [$this, 'getAvailableLanguages']),
            new TwigFunction('get_current_language', [$this, 'getCurrentLanguage']),
            new TwigFunction('language_switcher_urls', [$this, 'getLanguageSwitcherUrls']),
            new TwigFunction('localized_path', [$this, 'getLocalizedPath']),
            
            // New functions with LocaleService and TranslationService
            new TwigFunction('get_entity_translation', [$this, 'getEntityTranslation']),
            new TwigFunction('has_entity_translation', [$this, 'hasEntityTranslation']),
            new TwigFunction('entity_translation_stats', [$this, 'getEntityTranslationStats']),
            new TwigFunction('format_currency_locale', [$this, 'formatCurrencyLocale']),
            new TwigFunction('format_date_locale', [$this, 'formatDateLocale']),
            new TwigFunction('format_datetime_locale', [$this, 'formatDateTimeLocale']),
            new TwigFunction('get_text_direction', [$this, 'getTextDirection']),
            new TwigFunction('is_rtl', [$this, 'isRtl']),
            new TwigFunction('get_fallback_locale', [$this, 'getFallbackLocale']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('trans_entity', [$this, 'transEntity']),
            new TwigFilter('currency_locale', [$this, 'formatCurrencyLocale']),
            new TwigFilter('date_locale', [$this, 'formatDateLocale']),
            new TwigFilter('datetime_locale', [$this, 'formatDateTimeLocale']),
        ];
    }

    /**
     * Get all active languages for admin interface
     */
    public function getAdminLanguages(): array
    {
        return $this->languageRepository->findActiveLanguages();
    }

    /**
     * Get current admin language based on locale
     */
    public function getCurrentAdminLanguage(string $locale): ?\App\Entity\Language
    {
        return $this->languageRepository->findActiveByCode($locale);
    }

    /**
     * Get translation progress for a domain and locale
     */
    public function getTranslationProgress(string $domain, string $locale): array
    {
        $stats = $this->translationManager->getTranslationStats($domain);
        return $stats[$locale] ?? ['total' => 0, 'completed' => 0, 'missing' => 0, 'percentage' => 0];
    }

    /**
     * Check if translation is complete for a domain and locale
     */
    public function isTranslationComplete(string $domain, string $locale, int $threshold = 100): bool
    {
        $progress = $this->getTranslationProgress($domain, $locale);
        return $progress['percentage'] >= $threshold;
    }

    /**
     * Get all active languages for frontend
     */
    public function getAvailableLanguages(): array
    {
        return $this->languageRepository->findActiveLanguages();
    }

    /**
     * Get current language object
     */
    public function getCurrentLanguage(): ?\App\Entity\Language
    {
        return $this->localeService->getCurrentLanguage();
    }

    /**
     * Generate language switcher URLs for the current page
     */
    public function getLanguageSwitcherUrls(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return [];
        }

        $route = $request->get('_route');
        $routeParams = $request->get('_route_params', []);
        $languages = $this->languageRepository->findActiveLanguages();
        $urls = [];

        foreach ($languages as $language) {
            try {
                // Update locale in route parameters
                $params = array_merge($routeParams, ['_locale' => $language->getCode()]);
                
                $url = $this->urlGenerator->generate($route, $params);
                $urls[$language->getCode()] = [
                    'language' => $language,
                    'url' => $url,
                    'is_current' => $language->getCode() === $request->getLocale()
                ];
            } catch (\Exception $e) {
                // Fallback to homepage if route generation fails
                $urls[$language->getCode()] = [
                    'language' => $language,
                    'url' => '/' . $language->getCode(),
                    'is_current' => $language->getCode() === $request->getLocale()
                ];
            }
        }

        return $urls;
    }

    /**
     * Generate a localized path for a specific route
     */
    public function getLocalizedPath(string $route, array $parameters = [], ?string $locale = null): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $currentLocale = $locale ?? ($request ? $request->getLocale() : 'fr');
        
        $params = array_merge($parameters, ['_locale' => $currentLocale]);
        
        try {
            return $this->urlGenerator->generate($route, $params);
        } catch (\Exception $e) {
            return '/' . $currentLocale;
        }
    }

    // NEW METHODS FOR ENTITY TRANSLATIONS

    /**
     * Get entity translation
     */
    public function getEntityTranslation(object $entity, string $field, ?string $locale = null): ?string
    {
        return $this->translationService->getTranslation($entity, $field, $locale);
    }

    /**
     * Check if entity has translation
     */
    public function hasEntityTranslation(object $entity, string $field, ?string $locale = null): bool
    {
        return $this->translationService->hasTranslation($entity, $field, $locale);
    }

    /**
     * Get entity translation with fallback
     */
    public function transEntity(object $entity, string $field, ?string $locale = null, ?string $fallback = null): string
    {
        return $this->translationService->getTranslationOrFallback($entity, $field, $locale, $fallback);
    }

    /**
     * Get entity translation statistics
     */
    public function getEntityTranslationStats(object $entity, ?string $locale = null): array
    {
        return $this->translationService->getCompletionStats($entity, $locale);
    }

    /**
     * Format currency using locale service
     */
    public function formatCurrencyLocale(float $amount, ?string $locale = null): string
    {
        return $this->localeService->formatCurrency($amount, $locale);
    }

    /**
     * Format date using locale service
     */
    public function formatDateLocale(\DateTimeInterface $date, ?string $locale = null, string $format = 'medium'): string
    {
        return $this->localeService->formatDate($date, $locale, $format);
    }

    /**
     * Format datetime using locale service
     */
    public function formatDateTimeLocale(\DateTimeInterface $datetime, ?string $locale = null, string $dateFormat = 'medium', string $timeFormat = 'short'): string
    {
        return $this->localeService->formatDateTime($datetime, $locale, $dateFormat, $timeFormat);
    }

    /**
     * Get text direction for locale
     */
    public function getTextDirection(?string $locale = null): string
    {
        return $this->localeService->getTextDirection($locale);
    }

    /**
     * Check if locale is RTL
     */
    public function isRtl(?string $locale = null): bool
    {
        return $this->localeService->isRtl($locale);
    }

    /**
     * Get fallback locale
     */
    public function getFallbackLocale(): string
    {
        return $this->localeService->getFallbackLocale();
    }
}
