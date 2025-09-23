<?php

namespace App\Service;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LocaleService
{
    private ?Language $currentLanguage = null;

    public function __construct(
        private LanguageRepository $languageRepository,
        private RequestStack $requestStack
    ) {}

    /**
     * Get all active languages
     */
    public function getActiveLanguages(): array
    {
        return $this->languageRepository->findActiveLanguages();
    }

    /**
     * Get active language codes
     */
    public function getActiveLanguageCodes(): array
    {
        return $this->languageRepository->findActiveLanguageCodes();
    }

    /**
     * Get current language based on request locale
     */
    public function getCurrentLanguage(): ?Language
    {
        if ($this->currentLanguage === null) {
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $locale = $request->getLocale();
                $this->currentLanguage = $this->languageRepository->findByCode($locale);
            }

            // Fallback to default language if current not found
            if (!$this->currentLanguage) {
                $this->currentLanguage = $this->getDefaultLanguage();
            }
        }

        return $this->currentLanguage;
    }

    /**
     * Get current locale code
     */
    public function getCurrentLocale(): string
    {
        $language = $this->getCurrentLanguage();
        return $language ? $language->getCode() : 'fr';
    }

    /**
     * Get default language
     */
    public function getDefaultLanguage(): ?Language
    {
        return $this->languageRepository->findDefaultLanguage();
    }

    /**
     * Get default locale code
     */
    public function getDefaultLocale(): string
    {
        $language = $this->getDefaultLanguage();
        return $language ? $language->getCode() : 'fr';
    }

    /**
     * Switch to a specific language
     */
    public function switchLanguage(string $locale): bool
    {
        $language = $this->languageRepository->findActiveByCode($locale);
        if (!$language) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $request->setLocale($locale);
            
            // Store in session for persistence
            $session = $request->getSession();
            if ($session instanceof SessionInterface) {
                $session->set('_locale', $locale);
            }
        }

        $this->currentLanguage = $language;
        return true;
    }

    /**
     * Check if a locale is active
     */
    public function isActiveLocale(string $locale): bool
    {
        return $this->languageRepository->findActiveByCode($locale) !== null;
    }

    /**
     * Format currency for current locale
     */
    public function formatCurrency(float $amount, ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        // Define currency mappings by locale
        $currencyMap = [
            'fr' => 'EUR',
            'en' => 'USD',
            'es' => 'EUR',
            'de' => 'EUR',
            'it' => 'EUR',
        ];

        $currency = $currencyMap[$locale] ?? 'EUR';
        
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($amount, $currency);
    }

    /**
     * Format number for current locale
     */
    public function formatNumber(float $number, ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        return $formatter->format($number);
    }

    /**
     * Format date for current locale
     */
    public function formatDate(\DateTimeInterface $date, ?string $locale = null, string $format = 'medium'): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        $formatter = new \IntlDateFormatter(
            $locale,
            $format === 'short' ? \IntlDateFormatter::SHORT : 
            ($format === 'long' ? \IntlDateFormatter::LONG : \IntlDateFormatter::MEDIUM),
            \IntlDateFormatter::NONE
        );
        
        return $formatter->format($date);
    }

    /**
     * Format datetime for current locale
     */
    public function formatDateTime(\DateTimeInterface $datetime, ?string $locale = null, string $dateFormat = 'medium', string $timeFormat = 'short'): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        $formatter = new \IntlDateFormatter(
            $locale,
            $dateFormat === 'short' ? \IntlDateFormatter::SHORT : 
            ($dateFormat === 'long' ? \IntlDateFormatter::LONG : \IntlDateFormatter::MEDIUM),
            $timeFormat === 'short' ? \IntlDateFormatter::SHORT : 
            ($timeFormat === 'long' ? \IntlDateFormatter::LONG : \IntlDateFormatter::MEDIUM)
        );
        
        return $formatter->format($datetime);
    }

    /**
     * Get text direction for current locale (RTL/LTR)
     */
    public function getTextDirection(?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        // RTL languages
        $rtlLanguages = ['ar', 'he', 'fa', 'ur'];
        
        return in_array($locale, $rtlLanguages) ? 'rtl' : 'ltr';
    }

    /**
     * Get language by code
     */
    public function getLanguageByCode(string $code): ?Language
    {
        return $this->languageRepository->findByCode($code);
    }

    /**
     * Detect browser language preference
     */
    public function detectBrowserLanguage(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $acceptLanguage = $request->headers->get('Accept-Language');
        if (!$acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header
        $languages = [];
        foreach (explode(',', $acceptLanguage) as $lang) {
            $lang = trim($lang);
            if (strpos($lang, ';') !== false) {
                [$code, $quality] = explode(';', $lang, 2);
                $quality = (float) str_replace('q=', '', $quality);
            } else {
                $code = $lang;
                $quality = 1.0;
            }
            
            // Extract main language code (en-US -> en)
            $code = substr($code, 0, 2);
            $languages[$code] = $quality;
        }

        // Sort by quality
        arsort($languages);

        // Find first match in active languages
        $activeLanguages = $this->getActiveLanguageCodes();
        foreach (array_keys($languages) as $code) {
            if (in_array($code, $activeLanguages)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Get fallback locale if translation is missing
     */
    public function getFallbackLocale(): string
    {
        return $this->getDefaultLocale();
    }

    /**
     * Check if current locale is RTL
     */
    public function isRtl(?string $locale = null): bool
    {
        return $this->getTextDirection($locale) === 'rtl';
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
                'language' => $language,
                'code' => $language->getCode(),
                'name' => $language->getName(),
                'nativeName' => $language->getNativeName(),
                'isDefault' => $language->isDefault(),
                'sortOrder' => $language->getSortOrder(),
                'textDirection' => $this->getTextDirection($language->getCode()),
            ];
        }

        // Sort by sort order
        usort($stats, fn($a, $b) => $a['sortOrder'] <=> $b['sortOrder']);

        return $stats;
    }
}