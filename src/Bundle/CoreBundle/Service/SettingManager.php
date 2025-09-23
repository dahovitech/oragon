<?php

namespace App\Bundle\CoreBundle\Service;

use App\Bundle\CoreBundle\Entity\Setting;
use App\Bundle\CoreBundle\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class SettingManager
{
    private array $cache = [];
    private bool $cacheLoaded = false;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SettingRepository $settingRepository
    ) {}

    /**
     * Get a setting value by key
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->cacheLoaded) {
            $this->loadCache();
        }

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return $default;
    }

    /**
     * Set a setting value
     */
    public function set(string $key, mixed $value, ?string $type = null, ?string $section = null): Setting
    {
        $setting = $this->settingRepository->findByKey($key);
        
        if (!$setting) {
            $setting = new Setting();
            $setting->setSettingKey($key);
            $setting->setType($type ?: $this->guessType($value));
            $setting->setSection($section ?: 'general');
        }

        $setting->setParsedValue($value);

        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        // Update cache
        $this->cache[$key] = $value;

        return $setting;
    }

    /**
     * Delete a setting
     */
    public function delete(string $key): bool
    {
        $setting = $this->settingRepository->findByKey($key);
        
        if ($setting) {
            $this->entityManager->remove($setting);
            $this->entityManager->flush();
            unset($this->cache[$key]);
            return true;
        }

        return false;
    }

    /**
     * Get settings by section
     */
    public function getBySection(string $section): array
    {
        $settings = $this->settingRepository->findBySection($section);
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->getSettingKey()] = $setting->getParsedValue();
        }

        return $result;
    }

    /**
     * Get all public settings
     */
    public function getPublicSettings(): array
    {
        return $this->settingRepository->getPublicAsArray();
    }

    /**
     * Get all settings grouped by section
     */
    public function getAllGroupedBySection(): array
    {
        return $this->settingRepository->findGroupedBySection();
    }

    /**
     * Bulk update settings
     */
    public function bulkSet(array $keyValuePairs): int
    {
        $updated = 0;

        foreach ($keyValuePairs as $key => $value) {
            $setting = $this->settingRepository->findByKey($key);
            
            if ($setting) {
                $setting->setParsedValue($value);
                $this->entityManager->persist($setting);
                $this->cache[$key] = $value;
                $updated++;
            }
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }

        return $updated;
    }

    /**
     * Check if a setting exists
     */
    public function has(string $key): bool
    {
        return $this->settingRepository->findByKey($key) !== null;
    }

    /**
     * Initialize default settings
     */
    public function initializeDefaults(): void
    {
        $defaults = [
            // General settings
            'site_name' => ['value' => 'Oragon CMS', 'type' => 'string', 'section' => 'general', 'label' => 'Nom du site', 'description' => 'Le nom de votre site web'],
            'site_description' => ['value' => 'Plateforme CMS moderne basée sur Symfony', 'type' => 'text', 'section' => 'general', 'label' => 'Description du site', 'description' => 'Une brève description de votre site'],
            'admin_email' => ['value' => 'admin@oragon.local', 'type' => 'email', 'section' => 'general', 'label' => 'Email administrateur', 'description' => 'Adresse email de l\'administrateur principal'],
            'timezone' => ['value' => 'Europe/Paris', 'type' => 'string', 'section' => 'general', 'label' => 'Fuseau horaire', 'description' => 'Fuseau horaire du site'],
            'language' => ['value' => 'fr', 'type' => 'string', 'section' => 'general', 'label' => 'Langue par défaut', 'description' => 'Langue par défaut du site'],

            // Theme settings
            'theme_default' => ['value' => 'default', 'type' => 'string', 'section' => 'theme', 'label' => 'Thème par défaut', 'description' => 'Thème utilisé pour l\'affichage du site'],
            'theme_color_primary' => ['value' => '#007bff', 'type' => 'string', 'section' => 'theme', 'label' => 'Couleur principale', 'description' => 'Couleur principale du thème'],
            'theme_color_secondary' => ['value' => '#6c757d', 'type' => 'string', 'section' => 'theme', 'label' => 'Couleur secondaire', 'description' => 'Couleur secondaire du thème'],

            // System settings
            'maintenance_mode' => ['value' => false, 'type' => 'boolean', 'section' => 'system', 'label' => 'Mode maintenance', 'description' => 'Activer le mode maintenance'],
            'cache_enabled' => ['value' => true, 'type' => 'boolean', 'section' => 'system', 'label' => 'Cache activé', 'description' => 'Activer le système de cache'],
            'debug_mode' => ['value' => false, 'type' => 'boolean', 'section' => 'system', 'label' => 'Mode debug', 'description' => 'Activer le mode debug (développement uniquement)'],

            // Media settings
            'media_max_size' => ['value' => 10485760, 'type' => 'integer', 'section' => 'media', 'label' => 'Taille max fichiers (bytes)', 'description' => 'Taille maximale des fichiers uploadés en bytes'],
            'media_allowed_types' => ['value' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'], 'type' => 'array', 'section' => 'media', 'label' => 'Types de fichiers autorisés', 'description' => 'Extensions de fichiers autorisées pour l\'upload'],

            // SEO settings
            'seo_meta_title' => ['value' => '', 'type' => 'string', 'section' => 'seo', 'label' => 'Meta titre global', 'description' => 'Titre meta par défaut du site'],
            'seo_meta_description' => ['value' => '', 'type' => 'text', 'section' => 'seo', 'label' => 'Meta description globale', 'description' => 'Description meta par défaut du site'],
            'seo_robots' => ['value' => 'index, follow', 'type' => 'string', 'section' => 'seo', 'label' => 'Directives robots', 'description' => 'Instructions pour les robots des moteurs de recherche'],
        ];

        $created = 0;
        foreach ($defaults as $key => $config) {
            if (!$this->has($key)) {
                $setting = new Setting();
                $setting->setSettingKey($key);
                $setting->setParsedValue($config['value']);
                $setting->setType($config['type']);
                $setting->setSection($config['section']);
                $setting->setLabel($config['label']);
                $setting->setDescription($config['description']);
                $setting->setIsPublic(in_array($config['section'], ['general', 'theme', 'seo']));
                $setting->setSortOrder($created);

                $this->entityManager->persist($setting);
                $created++;
            }
        }

        if ($created > 0) {
            $this->entityManager->flush();
            $this->clearCache();
        }
    }

    /**
     * Load all settings into cache
     */
    private function loadCache(): void
    {
        $this->cache = $this->settingRepository->getAsArray();
        $this->cacheLoaded = true;
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
        $this->cacheLoaded = false;
    }

    /**
     * Guess the type of a value
     */
    private function guessType(mixed $value): string
    {
        return match(true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'array',
            filter_var($value, FILTER_VALIDATE_EMAIL) !== false => 'email',
            filter_var($value, FILTER_VALIDATE_URL) !== false => 'url',
            strlen($value) > 255 => 'text',
            default => 'string'
        };
    }

    /**
     * Get settings for frontend (public only)
     */
    public function getFrontendSettings(): array
    {
        $publicSettings = $this->getPublicSettings();
        
        // Add some computed values
        $publicSettings['current_year'] = date('Y');
        $publicSettings['site_url'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        return $publicSettings;
    }

    /**
     * Validate setting value based on type
     */
    public function validateValue(string $type, mixed $value): array
    {
        $errors = [];

        switch ($type) {
            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Adresse email invalide';
                }
                break;
            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = 'URL invalide';
                }
                break;
            case 'integer':
                if ($value !== null && !is_numeric($value)) {
                    $errors[] = 'Doit être un nombre entier';
                }
                break;
            case 'float':
                if ($value !== null && !is_numeric($value)) {
                    $errors[] = 'Doit être un nombre décimal';
                }
                break;
            case 'boolean':
                if ($value !== null && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                    $errors[] = 'Doit être vrai ou faux';
                }
                break;
            case 'json':
                if ($value && json_decode($value) === null && json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = 'JSON invalide';
                }
                break;
        }

        return $errors;
    }
}