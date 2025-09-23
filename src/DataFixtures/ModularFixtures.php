<?php

namespace App\DataFixtures;

use App\Bundle\CoreBundle\Entity\Category;
use App\Bundle\CoreBundle\Entity\Page;
use App\Bundle\CoreBundle\Entity\Setting;
use App\Bundle\UserBundle\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ModularFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Create admin user
        $admin = new User();
        $admin->setEmail('admin@oragon.local');
        $admin->setFirstName('Admin');
        $admin->setLastName('Oragon');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setIsActive(true);
        $manager->persist($admin);

        // Create test user
        $user = new User();
        $user->setEmail('user@oragon.local');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'user123'));
        $user->setIsActive(true);
        $manager->persist($user);

        // Create core settings
        $settings = [
            // General settings
            ['site_name', 'Oragon CMS', 'string', 'general', 'Nom du site', 'Le nom de votre site web', true, 1],
            ['site_description', 'Plateforme CMS moderne basée sur Symfony', 'text', 'general', 'Description du site', 'Une brève description de votre site', true, 2],
            ['admin_email', 'admin@oragon.local', 'email', 'general', 'Email administrateur', 'Adresse email de l\'administrateur principal', false, 3],
            ['timezone', 'Europe/Paris', 'string', 'general', 'Fuseau horaire', 'Fuseau horaire du site', true, 4],
            ['language', 'fr', 'string', 'general', 'Langue par défaut', 'Langue par défaut du site', true, 5],

            // Theme settings
            ['theme_default', 'default', 'string', 'theme', 'Thème par défaut', 'Thème utilisé pour l\'affichage du site', true, 1],
            ['theme_color_primary', '#007bff', 'string', 'theme', 'Couleur principale', 'Couleur principale du thème', true, 2],
            ['theme_color_secondary', '#6c757d', 'string', 'theme', 'Couleur secondaire', 'Couleur secondaire du thème', true, 3],

            // System settings
            ['maintenance_mode', '0', 'boolean', 'system', 'Mode maintenance', 'Activer le mode maintenance', false, 1],
            ['cache_enabled', '1', 'boolean', 'system', 'Cache activé', 'Activer le système de cache', false, 2],
            ['debug_mode', '0', 'boolean', 'system', 'Mode debug', 'Activer le mode debug (développement uniquement)', false, 3],

            // Media settings
            ['media_max_size', '10485760', 'integer', 'media', 'Taille max fichiers (bytes)', 'Taille maximale des fichiers uploadés en bytes', false, 1],
            ['media_allowed_types', '["jpg","jpeg","png","gif","webp","pdf","doc","docx"]', 'array', 'media', 'Types de fichiers autorisés', 'Extensions de fichiers autorisées pour l\'upload', false, 2],

            // SEO settings
            ['seo_meta_title', 'Oragon CMS - Plateforme moderne', 'string', 'seo', 'Meta titre global', 'Titre meta par défaut du site', true, 1],
            ['seo_meta_description', 'Découvrez Oragon, une plateforme CMS moderne et modulaire basée sur Symfony', 'text', 'seo', 'Meta description globale', 'Description meta par défaut du site', true, 2],
            ['seo_robots', 'index, follow', 'string', 'seo', 'Directives robots', 'Instructions pour les robots des moteurs de recherche', true, 3],
        ];

        foreach ($settings as [$key, $value, $type, $section, $label, $description, $isPublic, $sortOrder]) {
            $setting = new Setting();
            $setting->setSettingKey($key);
            $setting->setSettingValue($value);
            $setting->setType($type);
            $setting->setSection($section);
            $setting->setLabel($label);
            $setting->setDescription($description);
            $setting->setIsPublic($isPublic);
            $setting->setSortOrder($sortOrder);
            
            $manager->persist($setting);
        }

        // Create categories
        $categories = [
            // Blog categories
            ['Actualités', 'actualites', 'blog', 'Catégorie pour les articles d\'actualités', '#007bff', null, 1],
            ['Tutoriels', 'tutoriels', 'blog', 'Catégorie pour les tutoriels', '#28a745', null, 2],
            ['Annonces', 'annonces', 'blog', 'Catégorie pour les annonces', '#ffc107', null, 3],
            
            // Service categories
            ['Développement', 'developpement', 'service', 'Services de développement', '#dc3545', null, 1],
            ['Consulting', 'consulting', 'service', 'Services de consulting', '#6f42c1', null, 2],
            ['Formation', 'formation', 'service', 'Services de formation', '#fd7e14', null, 3],
            
            // Product categories
            ['Logiciels', 'logiciels', 'product', 'Produits logiciels', '#20c997', null, 1],
            ['Templates', 'templates', 'product', 'Templates et thèmes', '#e83e8c', null, 2],
            
            // Media categories
            ['Images', 'images', 'media', 'Fichiers images', '#17a2b8', null, 1],
            ['Documents', 'documents', 'media', 'Documents PDF et autres', '#6c757d', null, 2],
        ];

        $categoryEntities = [];
        foreach ($categories as [$name, $slug, $type, $description, $color, $parentSlug, $sortOrder]) {
            $category = new Category();
            $category->setName($name);
            $category->setSlug($slug);
            $category->setType($type);
            $category->setDescription($description);
            $category->setColor($color);
            $category->setSortOrder($sortOrder);
            $category->setIsActive(true);
            
            if ($parentSlug && isset($categoryEntities[$parentSlug])) {
                $category->setParent($categoryEntities[$parentSlug]);
            }
            
            $manager->persist($category);
            $categoryEntities[$slug] = $category;
        }

        // Create pages
        $pages = [
            [
                'title' => 'Accueil',
                'slug' => 'accueil',
                'content' => '<h1>Bienvenue sur Oragon CMS</h1>
<p>Découvrez la puissance de notre plateforme CMS modulaire construite avec Symfony.</p>
<h2>Fonctionnalités principales</h2>
<ul>
<li>Architecture modulaire avec bundles Symfony</li>
<li>Gestion avancée des utilisateurs</li>
<li>Système de médias intégré</li>
<li>Configuration flexible</li>
<li>Interface d\'administration moderne</li>
</ul>',
                'metaTitle' => 'Accueil - Oragon CMS',
                'metaDescription' => 'Bienvenue sur Oragon, votre plateforme CMS moderne et modulaire',
                'isHomepage' => true,
                'sortOrder' => 1,
            ],
            [
                'title' => 'À propos',
                'slug' => 'a-propos',
                'content' => '<h1>À propos d\'Oragon</h1>
<p>Oragon est une plateforme CMS moderne développée avec Symfony 7.3, conçue selon une architecture modulaire pour offrir flexibilité et évolutivité.</p>
<h2>Notre vision</h2>
<p>Créer un CMS qui s\'adapte à vos besoins plutôt que de vous adapter au CMS.</p>',
                'metaTitle' => 'À propos - Oragon CMS',
                'metaDescription' => 'Découvrez l\'histoire et la vision derrière Oragon CMS',
                'isHomepage' => false,
                'sortOrder' => 2,
            ],
            [
                'title' => 'Contact',
                'slug' => 'contact',
                'content' => '<h1>Contactez-nous</h1>
<p>Vous avez des questions ou souhaitez en savoir plus sur Oragon ?</p>
<h2>Informations de contact</h2>
<ul>
<li>Email : contact@oragon.local</li>
<li>Support : support@oragon.local</li>
</ul>',
                'metaTitle' => 'Contact - Oragon CMS',
                'metaDescription' => 'Contactez l\'équipe Oragon pour vos questions et demandes',
                'isHomepage' => false,
                'sortOrder' => 3,
            ],
        ];

        foreach ($pages as $pageData) {
            $page = new Page();
            $page->setTitle($pageData['title']);
            $page->setSlug($pageData['slug']);
            $page->setContent($pageData['content']);
            $page->setMetaTitle($pageData['metaTitle']);
            $page->setMetaDescription($pageData['metaDescription']);
            $page->setIsActive(true);
            $page->setIsHomepage($pageData['isHomepage']);
            $page->setSortOrder($pageData['sortOrder']);
            
            $manager->persist($page);
        }

        $manager->flush();
    }
}