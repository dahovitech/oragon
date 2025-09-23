<?php

namespace App\Service;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LocaleService
{
    private const SESSION_LOCALE_KEY = '_locale';
    private const DEFAULT_LOCALE = 'fr';

    public function __construct(
        private LanguageRepository $languageRepository,
        private RequestStack $requestStack,
        private string $defaultLocale = self::DEFAULT_LOCALE
    ) {
    }

    /**
     * Get all active languages
     */
    public function getActiveLanguages(): array
    {
        return $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
    }

    /**
     * Get default language
     */
    public function getDefaultLanguage(): ?Language
    {
        return $this->languageRepository->findOneBy(['isDefault' => true]) 
            ?? $this->languageRepository->findOneBy(['code' => $this->defaultLocale]);
    }

    /**
     * Get current language from various sources
     */
    public function getCurrentLanguage(): ?Language
    {
        $locale = $this->getCurrentLocale();
        return $this->languageRepository->findOneBy(['code' => $locale, 'isActive' => true]);
    }

    /**
     * Get current locale with fallback priority:
     * 1. Request attribute (_locale)
     * 2. Session stored locale
     * 3. Browser preferred language
     * 4. Default locale
     */
    public function getCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return $this->defaultLocale;
        }

        // 1. Check request attribute (from URL routing)
        $locale = $request->attributes->get('_locale');
        if ($locale && $this->isValidLocale($locale)) {
            return $locale;
        }

        // 2. Check session
        $session = $request->getSession();
        $locale = $session->get(self::SESSION_LOCALE_KEY);
        if ($locale && $this->isValidLocale($locale)) {
            return $locale;
        }

        // 3. Check browser preference
        $locale = $this->getBrowserPreferredLocale($request);
        if ($locale && $this->isValidLocale($locale)) {
            return $locale;
        }

        // 4. Fallback to default
        return $this->defaultLocale;
    }

    /**
     * Switch to a new language
     */
    public function switchLanguage(string $locale): bool
    {
        if (!$this->isValidLocale($locale)) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->hasSession()) {
            $request->getSession()->set(self::SESSION_LOCALE_KEY, $locale);
            $request->setLocale($locale);
        }

        return true;
    }

    /**
     * Check if locale is valid (exists and is active)
     */
    public function isValidLocale(string $locale): bool
    {
        return $this->languageRepository->findOneBy(['code' => $locale, 'isActive' => true]) !== null;
    }

    /**
     * Get available locale codes
     */
    public function getAvailableLocales(): array
    {
        return array_map(
            fn(Language $lang) => $lang->getCode(),
            $this->getActiveLanguages()
        );
    }

    /**
     * Get language by code
     */
    public function getLanguageByCode(string $code): ?Language
    {
        return $this->languageRepository->findOneBy(['code' => $code, 'isActive' => true]);
    }

    /**
     * Format currency according to locale
     */
    public function formatCurrency(float $amount, ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        $language = $this->getLanguageByCode($locale);
        
        $currency = $language?->getCurrency() ?? 'EUR';
        
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($amount, $currency);
    }

    /**
     * Format date according to locale
     */
    public function formatDate(\DateTimeInterface $date, ?string $locale = null, int $dateType = \IntlDateFormatter::MEDIUM): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        $language = $this->getLanguageByCode($locale);
        
        // Use custom date format if available
        if ($language && $language->getDateFormat()) {
            return $date->format($language->getDateFormat());
        }
        
        $formatter = new \IntlDateFormatter(
            $locale,
            $dateType,
            \IntlDateFormatter::NONE,
            $date->getTimezone()
        );
        
        return $formatter->format($date) ?: $date->format('Y-m-d');
    }

    /**
     * Format number according to locale
     */
    public function formatNumber(float $number, ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        return $formatter->format($number) ?: (string) $number;
    }

    /**
     * Get text direction for current locale (RTL/LTR)
     */
    public function getTextDirection(?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        $language = $this->getLanguageByCode($locale);
        
        return $language?->getTextDirection() ?? 'ltr';
    }

    /**
     * Check if current locale is RTL
     */
    public function isRtl(?string $locale = null): bool
    {
        return $this->getTextDirection($locale) === 'rtl';
    }

    /**
     * Get browser preferred locale from Accept-Language header
     */
    private function getBrowserPreferredLocale(Request $request): ?string
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

        // Find first available locale
        foreach (array_keys($locales) as $locale) {
            if ($this->isValidLocale($locale)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Get localized URL for current route in different language
     */
    public function getLocalizedUrl(string $targetLocale, ?Request $request = null): ?string
    {
        $request = $request ?? $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return null;
        }

        $pathInfo = $request->getPathInfo();
        $currentLocale = $this->getCurrentLocale();
        
        // If URL starts with current locale, replace it
        if (str_starts_with($pathInfo, "/$currentLocale/")) {
            return str_replace("/$currentLocale/", "/$targetLocale/", $pathInfo);
        }
        
        // If URL doesn't have locale prefix, add target locale
        return "/$targetLocale$pathInfo";
    }

    /**
     * Get language options for forms
     */
    public function getLanguageChoices(): array
    {
        $choices = [];
        foreach ($this->getActiveLanguages() as $language) {
            $choices[$language->getNativeName()] = $language->getCode();
        }
        return $choices;
    }

    /**
     * Get locale display name in current language
     */
    public function getLocaleDisplayName(string $locale, ?string $displayLocale = null): string
    {
        $displayLocale = $displayLocale ?? $this->getCurrentLocale();
        
        $language = $this->getLanguageByCode($locale);
        if (!$language) {
            return $locale;
        }

        // If displaying in the same locale, use native name
        if ($locale === $displayLocale) {
            return $language->getNativeName();
        }

        // Otherwise use the regular name
        return $language->getName();
    }
}