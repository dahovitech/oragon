<?php

namespace App\Service;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Countries;

/**
 * Service for managing locale and internationalization for e-commerce
 */
class LocaleService
{
    public function __construct(
        private LanguageRepository $languageRepository,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Get all active languages
     */
    public function getActiveLanguages(): array
    {
        return $this->languageRepository->findBy(
            ['isActive' => true],
            ['sortOrder' => 'ASC']
        );
    }

    /**
     * Get current language
     */
    public function getCurrentLanguage(): ?Language
    {
        $currentLocale = $this->getCurrentLocale();
        return $this->languageRepository->findActiveByCode($currentLocale);
    }

    /**
     * Get current locale code
     */
    public function getCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return $this->getDefaultLanguage()->getCode();
        }

        return $request->getLocale();
    }

    /**
     * Get default language
     */
    public function getDefaultLanguage(): Language
    {
        $default = $this->languageRepository->findDefaultLanguage();
        
        if (!$default) {
            // Fallback to first active language
            $active = $this->getActiveLanguages();
            if (!empty($active)) {
                return $active[0];
            }
            
            throw new \RuntimeException('No active languages found in the system');
        }
        
        return $default;
    }

    /**
     * Get default locale code
     */
    public function getDefaultLocale(): string
    {
        return $this->getDefaultLanguage()->getCode();
    }

    /**
     * Check if a locale is available and active
     */
    public function isLocaleAvailable(string $locale): bool
    {
        return $this->languageRepository->findActiveByCode($locale) !== null;
    }

    /**
     * Switch to a different language
     */
    public function switchLanguage(string $locale): bool
    {
        if (!$this->isLocaleAvailable($locale)) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            $session->set('_locale', $locale);
            $request->setLocale($locale);
            return true;
        }

        return false;
    }

    /**
     * Format currency for current locale
     */
    public function formatCurrency(float $amount, ?string $locale = null): string
    {
        $locale = $locale ?: $this->getCurrentLocale();
        $language = $this->languageRepository->findActiveByCode($locale);
        
        $currency = $language ? $language->getCurrency() : 'EUR';
        
        if (!$currency) {
            $currency = 'EUR';
        }

        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($amount, $currency);
    }

    /**
     * Get currency symbol for current locale
     */
    public function getCurrencySymbol(?string $locale = null): string
    {
        $locale = $locale ?: $this->getCurrentLocale();
        $language = $this->languageRepository->findActiveByCode($locale);
        
        $currency = $language ? $language->getCurrency() : 'EUR';
        
        if (!$currency) {
            $currency = 'EUR';
        }

        return Currencies::getSymbol($currency, $locale);
    }

    /**
     * Get currency code for current locale
     */
    public function getCurrencyCode(?string $locale = null): string
    {
        $locale = $locale ?: $this->getCurrentLocale();
        $language = $this->languageRepository->findActiveByCode($locale);
        
        return $language ? ($language->getCurrency() ?: 'EUR') : 'EUR';
    }

    /**
     * Format date for current locale
     */
    public function formatDate(\DateTimeInterface $date, ?string $locale = null, int $dateType = \IntlDateFormatter::MEDIUM): string
    {
        $locale = $locale ?: $this->getCurrentLocale();
        
        $formatter = new \IntlDateFormatter(
            $locale,
            $dateType,
            \IntlDateFormatter::NONE
        );
        
        return $formatter->format($date);
    }

    /**
     * Format date and time for current locale
     */
    public function formatDateTime(\DateTimeInterface $dateTime, ?string $locale = null, int $dateType = \IntlDateFormatter::MEDIUM, int $timeType = \IntlDateFormatter::SHORT): string
    {
        $locale = $locale ?: $this->getCurrentLocale();
        
        $formatter = new \IntlDateFormatter(
            $locale,
            $dateType,
            $timeType
        );
        
        return $formatter->format($dateTime);
    }

    /**
     * Format number for current locale
     */
    public function formatNumber(float $number, ?string $locale = null, int $decimals = 2): string
    {
        $locale = $locale ?: $this->getCurrentLocale();
        
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
        
        return $formatter->format($number);
    }

    /**
     * Get country name in current locale
     */
    public function getCountryName(string $countryCode, ?string $locale = null): string
    {
        $locale = $locale ?: $this->getCurrentLocale();
        return Countries::getName($countryCode, $locale);
    }

    /**
     * Get language direction (LTR or RTL)
     */
    public function getLanguageDirection(?string $locale = null): string
    {
        $locale = $locale ?: $this->getCurrentLocale();
        $language = $this->languageRepository->findActiveByCode($locale);
        
        return $language && $language->isRtl() ? 'rtl' : 'ltr';
    }

    /**
     * Check if current language is RTL
     */
    public function isRtl(?string $locale = null): bool
    {
        return $this->getLanguageDirection($locale) === 'rtl';
    }

    /**
     * Get URLs for language switcher
     */
    public function getLanguageSwitcherUrls(): array
    {
        $urls = [];
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return $urls;
        }

        $currentPath = $request->getPathInfo();
        $currentLocale = $this->getCurrentLocale();
        
        // Remove current locale from path
        $pathWithoutLocale = preg_replace('#^/' . preg_quote($currentLocale) . '(/.*)?$#', '$1', $currentPath);
        if (empty($pathWithoutLocale)) {
            $pathWithoutLocale = '/';
        }

        foreach ($this->getActiveLanguages() as $language) {
            $localeCode = $language->getCode();
            $localizedPath = '/' . $localeCode . $pathWithoutLocale;
            
            $urls[$localeCode] = [
                'url' => $localizedPath,
                'language' => $language,
                'is_current' => $localeCode === $currentLocale
            ];
        }

        return $urls;
    }

    /**
     * Generate localized path
     */
    public function getLocalizedPath(string $path, ?string $locale = null): string
    {
        $locale = $locale ?: $this->getCurrentLocale();
        
        // Ensure path starts with /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        
        return '/' . $locale . $path;
    }

    /**
     * Get language statistics
     */
    public function getLanguageStatistics(): array
    {
        $languages = $this->getActiveLanguages();
        $stats = [];
        
        foreach ($languages as $language) {
            $stats[] = [
                'code' => $language->getCode(),
                'name' => $language->getName(),
                'native_name' => $language->getNativeName(),
                'currency' => $language->getCurrency(),
                'region' => $language->getRegion(),
                'is_default' => $language->isDefault(),
                'is_rtl' => $language->isRtl(),
                'sort_order' => $language->getSortOrder()
            ];
        }
        
        return $stats;
    }

    /**
     * Get preferred language from browser headers
     */
    public function getPreferredLanguageFromBrowser(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return null;
        }

        $acceptLanguages = $request->getLanguages();
        $activeLanguageCodes = array_map(
            fn(Language $lang) => $lang->getCode(),
            $this->getActiveLanguages()
        );
        
        foreach ($acceptLanguages as $browserLocale) {
            // Try exact match first
            if (in_array($browserLocale, $activeLanguageCodes)) {
                return $browserLocale;
            }
            
            // Try language part only (e.g., 'en' from 'en-US')
            $languagePart = substr($browserLocale, 0, 2);
            if (in_array($languagePart, $activeLanguageCodes)) {
                return $languagePart;
            }
        }
        
        return null;
    }

    /**
     * Validate and normalize locale
     */
    public function normalizeLocale(string $locale): ?string
    {
        $locale = strtolower(trim($locale));
        
        if (strlen($locale) !== 2) {
            return null;
        }
        
        return $this->isLocaleAvailable($locale) ? $locale : null;
    }
}