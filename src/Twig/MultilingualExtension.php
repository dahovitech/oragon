<?php

namespace App\Twig;

use App\Service\LocaleService;
use App\Service\TranslationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class MultilingualExtension extends AbstractExtension
{
    public function __construct(
        private LocaleService $localeService,
        private TranslationService $translationService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_active_languages', [$this, 'getActiveLanguages']),
            new TwigFunction('get_current_language', [$this, 'getCurrentLanguage']),
            new TwigFunction('get_translation', [$this, 'getTranslation']),
            new TwigFunction('has_translation', [$this, 'hasTranslation']),
            new TwigFunction('get_completion_percentage', [$this, 'getCompletionPercentage']),
            new TwigFunction('get_localized_url', [$this, 'getLocalizedUrl']),
            new TwigFunction('get_language_choices', [$this, 'getLanguageChoices']),
            new TwigFunction('get_locale_display_name', [$this, 'getLocaleDisplayName']),
            new TwigFunction('is_rtl', [$this, 'isRtl']),
            new TwigFunction('get_text_direction', [$this, 'getTextDirection']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('trans_field', [$this, 'translateField']),
            new TwigFilter('trans_fallback', [$this, 'translateWithFallback']),
            new TwigFilter('currency_locale', [$this, 'formatCurrency']),
            new TwigFilter('date_locale', [$this, 'formatDate']),
            new TwigFilter('number_locale', [$this, 'formatNumber']),
            new TwigFilter('locale_url', [$this, 'getLocalizedUrl']),
        ];
    }

    public function getTests(): array
    {
        return [
            new TwigTest('translated', [$this, 'hasTranslation']),
            new TwigTest('rtl', [$this, 'isRtl']),
        ];
    }

    /**
     * Get all active languages
     */
    public function getActiveLanguages(): array
    {
        return $this->localeService->getActiveLanguages();
    }

    /**
     * Get current language
     */
    public function getCurrentLanguage()
    {
        return $this->localeService->getCurrentLanguage();
    }

    /**
     * Get translation for entity field
     */
    public function getTranslation(object $entity, string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?? $this->localeService->getCurrentLocale();
        return $this->translationService->getTranslation($entity, $field, $locale);
    }

    /**
     * Check if entity has translation
     */
    public function hasTranslation(object $entity, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->localeService->getCurrentLocale();
        return $this->translationService->hasTranslation($entity, $locale);
    }

    /**
     * Get translation completion percentage
     */
    public function getCompletionPercentage(object $entity, ?string $locale = null): int
    {
        $locale = $locale ?? $this->localeService->getCurrentLocale();
        return $this->translationService->getCompletionPercentage($entity, $locale);
    }

    /**
     * Get localized URL for different language
     */
    public function getLocalizedUrl(string $targetLocale, ?object $request = null): ?string
    {
        return $this->localeService->getLocalizedUrl($targetLocale, $request);
    }

    /**
     * Get language choices for forms
     */
    public function getLanguageChoices(): array
    {
        return $this->localeService->getLanguageChoices();
    }

    /**
     * Get locale display name
     */
    public function getLocaleDisplayName(string $locale, ?string $displayLocale = null): string
    {
        return $this->localeService->getLocaleDisplayName($locale, $displayLocale);
    }

    /**
     * Check if locale is RTL
     */
    public function isRtl(?string $locale = null): bool
    {
        return $this->localeService->isRtl($locale);
    }

    /**
     * Get text direction for locale
     */
    public function getTextDirection(?string $locale = null): string
    {
        return $this->localeService->getTextDirection($locale);
    }

    /**
     * Translate entity field with current locale
     */
    public function translateField(object $entity, string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?? $this->localeService->getCurrentLocale();
        return $this->translationService->getTranslation($entity, $field, $locale);
    }

    /**
     * Translate with fallback to default language
     */
    public function translateWithFallback(object $entity, string $field, ?string $locale = null, ?string $fallbackLocale = null): ?string
    {
        $locale = $locale ?? $this->localeService->getCurrentLocale();
        return $this->translationService->getTranslationWithFallback($entity, $field, $locale, $fallbackLocale);
    }

    /**
     * Format currency according to locale
     */
    public function formatCurrency(float $amount, ?string $locale = null): string
    {
        return $this->localeService->formatCurrency($amount, $locale);
    }

    /**
     * Format date according to locale
     */
    public function formatDate(\DateTimeInterface $date, ?string $locale = null, int $dateType = \IntlDateFormatter::MEDIUM): string
    {
        return $this->localeService->formatDate($date, $locale, $dateType);
    }

    /**
     * Format number according to locale
     */
    public function formatNumber(float $number, ?string $locale = null): string
    {
        return $this->localeService->formatNumber($number, $locale);
    }
}