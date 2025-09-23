<?php

namespace App\Twig;

use App\Service\LocaleService;
use App\Service\CartService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

/**
 * Twig extension for e-commerce specific functions
 */
class EcommerceExtension extends AbstractExtension
{
    public function __construct(
        private LocaleService $localeService,
        private CartService $cartService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            // Locale functions
            new TwigFunction('current_currency', [$this, 'getCurrentCurrency']),
            new TwigFunction('currency_symbol', [$this, 'getCurrencySymbol']),
            new TwigFunction('is_rtl', [$this, 'isRtl']),
            new TwigFunction('country_name', [$this, 'getCountryName']),
            
            // Cart functions
            new TwigFunction('cart_items_count', [$this, 'getCartItemsCount']),
            new TwigFunction('cart_is_empty', [$this, 'isCartEmpty']),
            new TwigFunction('cart_summary', [$this, 'getCartSummary']),
            
            // Translation helpers
            new TwigFunction('has_translation', [$this, 'hasTranslation']),
            new TwigFunction('entity_translation', [$this, 'getEntityTranslation']),
            new TwigFunction('fallback_translation', [$this, 'getFallbackTranslation']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('currency', [$this, 'formatCurrency']),
            new TwigFilter('localized_date', [$this, 'formatLocalizedDate']),
            new TwigFilter('localized_datetime', [$this, 'formatLocalizedDateTime']),
            new TwigFilter('localized_number', [$this, 'formatLocalizedNumber']),
            new TwigFilter('translate_entity', [$this, 'translateEntity']),
        ];
    }

    // Locale Functions

    /**
     * Get current currency code
     */
    public function getCurrentCurrency(?string $locale = null): string
    {
        return $this->localeService->getCurrencyCode($locale);
    }

    /**
     * Get currency symbol for current or specified locale
     */
    public function getCurrencySymbol(?string $locale = null): string
    {
        return $this->localeService->getCurrencySymbol($locale);
    }

    /**
     * Check if current language is RTL
     */
    public function isRtl(?string $locale = null): bool
    {
        return $this->localeService->isRtl($locale);
    }

    /**
     * Get country name in current locale
     */
    public function getCountryName(string $countryCode, ?string $locale = null): string
    {
        return $this->localeService->getCountryName($countryCode, $locale);
    }

    // Cart Functions

    /**
     * Get cart items count
     */
    public function getCartItemsCount(): int
    {
        return $this->cartService->getItemsCount();
    }

    /**
     * Check if cart is empty
     */
    public function isCartEmpty(): bool
    {
        return $this->cartService->isEmpty();
    }

    /**
     * Get cart summary
     */
    public function getCartSummary(): array
    {
        $locale = $this->localeService->getCurrentLocale();
        return $this->cartService->getCartSummary($locale);
    }

    // Translation Helper Functions

    /**
     * Check if an entity has translation for a specific language
     */
    public function hasTranslation($entity, string $languageCode): bool
    {
        if (!$entity || !method_exists($entity, 'hasTranslation')) {
            return false;
        }

        return $entity->hasTranslation($languageCode);
    }

    /**
     * Get entity translation for a specific language
     */
    public function getEntityTranslation($entity, string $languageCode)
    {
        if (!$entity || !method_exists($entity, 'getTranslation')) {
            return null;
        }

        return $entity->getTranslation($languageCode);
    }

    /**
     * Get entity translation with fallback
     */
    public function getFallbackTranslation($entity, string $languageCode, string $fallbackLanguageCode = null)
    {
        if (!$entity || !method_exists($entity, 'getTranslationWithFallback')) {
            return null;
        }

        $fallback = $fallbackLanguageCode ?: $this->localeService->getDefaultLocale();
        return $entity->getTranslationWithFallback($languageCode, $fallback);
    }

    // Filters

    /**
     * Format currency amount
     */
    public function formatCurrency(float $amount, ?string $locale = null): string
    {
        return $this->localeService->formatCurrency($amount, $locale);
    }

    /**
     * Format date for current locale
     */
    public function formatLocalizedDate(\DateTimeInterface $date, ?string $locale = null, int $dateType = \IntlDateFormatter::MEDIUM): string
    {
        return $this->localeService->formatDate($date, $locale, $dateType);
    }

    /**
     * Format date and time for current locale
     */
    public function formatLocalizedDateTime(\DateTimeInterface $dateTime, ?string $locale = null, int $dateType = \IntlDateFormatter::MEDIUM, int $timeType = \IntlDateFormatter::SHORT): string
    {
        return $this->localeService->formatDateTime($dateTime, $locale, $dateType, $timeType);
    }

    /**
     * Format number for current locale
     */
    public function formatLocalizedNumber(float $number, ?string $locale = null, int $decimals = 2): string
    {
        return $this->localeService->formatNumber($number, $locale, $decimals);
    }

    /**
     * Get translated field value from entity
     */
    public function translateEntity($entity, string $field, ?string $locale = null, string $fallbackLocale = null): string
    {
        if (!$entity) {
            return '';
        }

        $locale = $locale ?: $this->localeService->getCurrentLocale();
        $fallback = $fallbackLocale ?: $this->localeService->getDefaultLocale();

        // Try to get method dynamically (e.g., getName, getTitle, getDescription)
        $methodName = 'get' . ucfirst($field);
        
        if (method_exists($entity, $methodName)) {
            try {
                return $entity->$methodName($locale, $fallback);
            } catch (\Exception $e) {
                // Fallback to default method call
                if (method_exists($entity, $methodName)) {
                    return $entity->$methodName();
                }
            }
        }

        return '';
    }
}