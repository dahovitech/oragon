<?php

namespace App\DataFixtures;

use App\Entity\Attribute;
use App\Entity\AttributeTranslation;
use App\Entity\AttributeValue;
use App\Entity\AttributeValueTranslation;
use App\Entity\Brand;
use App\Entity\BrandTranslation;
use App\Entity\Category;
use App\Entity\CategoryTranslation;
use App\Entity\Language;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EcommerceFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Créer les langues
        $languages = $this->createLanguages($manager);

        // Créer un utilisateur admin
        $this->createAdminUser($manager);

        // Créer les attributs
        $attributes = $this->createAttributes($manager, $languages);

        // Créer les marques
        $brands = $this->createBrands($manager, $languages);

        // Créer les catégories
        $categories = $this->createCategories($manager, $languages);

        // Créer les produits
        $this->createProducts($manager, $languages, $brands, $categories, $attributes);

        $manager->flush();
    }

    private function createLanguages(ObjectManager $manager): array
    {
        $languagesData = [
            ['fr', 'Français', 'Français', true, true, 0],
            ['en', 'English', 'English', true, false, 1],
            ['es', 'Español', 'Español', true, false, 2],
            ['de', 'Deutsch', 'Deutsch', true, false, 3],
            ['it', 'Italiano', 'Italiano', true, false, 4],
        ];

        $languages = [];
        foreach ($languagesData as [$code, $name, $nativeName, $isActive, $isDefault, $sortOrder]) {
            $language = new Language();
            $language->setCode($code)
                ->setName($name)
                ->setNativeName($nativeName)
                ->setIsActive($isActive)
                ->setIsDefault($isDefault)
                ->setSortOrder($sortOrder);

            $manager->persist($language);
            $languages[$code] = $language;
        }

        return $languages;
    }

    private function createAdminUser(ObjectManager $manager): User
    {
        $user = new User();
        $user->setEmail('admin@viva-shop.com')
            ->setFirstName('Admin')
            ->setLastName('Viva Shop')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsActive(true);

        $hashedPassword = $this->passwordHasher->hashPassword($user, 'admin123');
        $user->setPassword($hashedPassword);

        $manager->persist($user);

        return $user;
    }

    private function createAttributes(ObjectManager $manager, array $languages): array
    {
        $attributesData = [
            [
                'type' => 'select',
                'isRequired' => false,
                'isFilterable' => true,
                'sortOrder' => 0,
                'translations' => [
                    'fr' => 'Couleur',
                    'en' => 'Color',
                    'es' => 'Color',
                    'de' => 'Farbe',
                    'it' => 'Colore',
                ],
                'values' => [
                    ['value' => ['fr' => 'Rouge', 'en' => 'Red', 'es' => 'Rojo', 'de' => 'Rot', 'it' => 'Rosso'], 'color' => '#FF0000'],
                    ['value' => ['fr' => 'Bleu', 'en' => 'Blue', 'es' => 'Azul', 'de' => 'Blau', 'it' => 'Blu'], 'color' => '#0000FF'],
                    ['value' => ['fr' => 'Vert', 'en' => 'Green', 'es' => 'Verde', 'de' => 'Grün', 'it' => 'Verde'], 'color' => '#00FF00'],
                    ['value' => ['fr' => 'Noir', 'en' => 'Black', 'es' => 'Negro', 'de' => 'Schwarz', 'it' => 'Nero'], 'color' => '#000000'],
                    ['value' => ['fr' => 'Blanc', 'en' => 'White', 'es' => 'Blanco', 'de' => 'Weiß', 'it' => 'Bianco'], 'color' => '#FFFFFF'],
                ]
            ],
            [
                'type' => 'select',
                'isRequired' => false,
                'isFilterable' => true,
                'sortOrder' => 1,
                'translations' => [
                    'fr' => 'Taille',
                    'en' => 'Size',
                    'es' => 'Talla',
                    'de' => 'Größe',
                    'it' => 'Taglia',
                ],
                'values' => [
                    ['value' => ['fr' => 'XS', 'en' => 'XS', 'es' => 'XS', 'de' => 'XS', 'it' => 'XS'], 'color' => null],
                    ['value' => ['fr' => 'S', 'en' => 'S', 'es' => 'S', 'de' => 'S', 'it' => 'S'], 'color' => null],
                    ['value' => ['fr' => 'M', 'en' => 'M', 'es' => 'M', 'de' => 'M', 'it' => 'M'], 'color' => null],
                    ['value' => ['fr' => 'L', 'en' => 'L', 'es' => 'L', 'de' => 'L', 'it' => 'L'], 'color' => null],
                    ['value' => ['fr' => 'XL', 'en' => 'XL', 'es' => 'XL', 'de' => 'XL', 'it' => 'XL'], 'color' => null],
                ]
            ],
            [
                'type' => 'select',
                'isRequired' => false,
                'isFilterable' => true,
                'sortOrder' => 2,
                'translations' => [
                    'fr' => 'Matière',
                    'en' => 'Material',
                    'es' => 'Material',
                    'de' => 'Material',
                    'it' => 'Materiale',
                ],
                'values' => [
                    ['value' => ['fr' => 'Coton', 'en' => 'Cotton', 'es' => 'Algodón', 'de' => 'Baumwolle', 'it' => 'Cotone'], 'color' => null],
                    ['value' => ['fr' => 'Polyester', 'en' => 'Polyester', 'es' => 'Poliéster', 'de' => 'Polyester', 'it' => 'Poliestere'], 'color' => null],
                    ['value' => ['fr' => 'Laine', 'en' => 'Wool', 'es' => 'Lana', 'de' => 'Wolle', 'it' => 'Lana'], 'color' => null],
                    ['value' => ['fr' => 'Soie', 'en' => 'Silk', 'es' => 'Seda', 'de' => 'Seide', 'it' => 'Seta'], 'color' => null],
                ]
            ]
        ];

        $attributes = [];
        foreach ($attributesData as $index => $attrData) {
            $attribute = new Attribute();
            $attribute->setType($attrData['type'])
                ->setIsRequired($attrData['isRequired'])
                ->setIsFilterable($attrData['isFilterable'])
                ->setSortOrder($attrData['sortOrder'])
                ->setIsActive(true);

            $manager->persist($attribute);

            // Créer les traductions de l'attribut
            foreach ($attrData['translations'] as $langCode => $name) {
                $translation = new AttributeTranslation();
                $translation->setAttribute($attribute)
                    ->setLanguage($languages[$langCode])
                    ->setName($name);

                $manager->persist($translation);
                $attribute->addTranslation($translation);
            }

            // Créer les valeurs de l'attribut
            foreach ($attrData['values'] as $valueIndex => $valueData) {
                $attributeValue = new AttributeValue();
                $attributeValue->setAttribute($attribute)
                    ->setColorCode($valueData['color'])
                    ->setSortOrder($valueIndex)
                    ->setIsActive(true);

                $manager->persist($attributeValue);

                // Créer les traductions des valeurs
                foreach ($valueData['value'] as $langCode => $value) {
                    $valueTranslation = new AttributeValueTranslation();
                    $valueTranslation->setAttributeValue($attributeValue)
                        ->setLanguage($languages[$langCode])
                        ->setValue($value);

                    $manager->persist($valueTranslation);
                    $attributeValue->addTranslation($valueTranslation);
                }

                $attribute->addValue($attributeValue);
            }

            $attributes[] = $attribute;
        }

        return $attributes;
    }

    private function createBrands(ObjectManager $manager, array $languages): array
    {
        $brandsData = [
            [
                'website' => 'https://www.nike.com',
                'sortOrder' => 0,
                'translations' => [
                    'fr' => [
                        'name' => 'Nike',
                        'description' => 'Marque de sport leader mondial, connue pour ses chaussures et vêtements de sport innovants.',
                    ],
                    'en' => [
                        'name' => 'Nike',
                        'description' => 'Leading global sports brand, known for innovative sports shoes and apparel.',
                    ],
                    'es' => [
                        'name' => 'Nike',
                        'description' => 'Marca deportiva líder mundial, conocida por sus zapatos y ropa deportiva innovadores.',
                    ],
                    'de' => [
                        'name' => 'Nike',
                        'description' => 'Führende globale Sportmarke, bekannt für innovative Sportschuhe und Bekleidung.',
                    ],
                    'it' => [
                        'name' => 'Nike',
                        'description' => 'Marchio sportivo leader mondiale, noto per scarpe e abbigliamento sportivo innovativi.',
                    ],
                ]
            ],
            [
                'website' => 'https://www.adidas.com',
                'sortOrder' => 1,
                'translations' => [
                    'fr' => [
                        'name' => 'Adidas',
                        'description' => 'Marque allemande emblématique spécialisée dans les équipements sportifs.',
                    ],
                    'en' => [
                        'name' => 'Adidas',
                        'description' => 'Iconic German brand specializing in sports equipment.',
                    ],
                    'es' => [
                        'name' => 'Adidas',
                        'description' => 'Marca alemana icónica especializada en equipos deportivos.',
                    ],
                    'de' => [
                        'name' => 'Adidas',
                        'description' => 'Ikonische deutsche Marke, spezialisiert auf Sportausrüstung.',
                    ],
                    'it' => [
                        'name' => 'Adidas',
                        'description' => 'Marchio tedesco iconico specializzato in attrezzature sportive.',
                    ],
                ]
            ],
            [
                'website' => 'https://www.puma.com',
                'sortOrder' => 2,
                'translations' => [
                    'fr' => [
                        'name' => 'Puma',
                        'description' => 'Marque de sport allemande innovante avec un design moderne.',
                    ],
                    'en' => [
                        'name' => 'Puma',
                        'description' => 'Innovative German sports brand with modern design.',
                    ],
                    'es' => [
                        'name' => 'Puma',
                        'description' => 'Marca deportiva alemana innovadora con diseño moderno.',
                    ],
                    'de' => [
                        'name' => 'Puma',
                        'description' => 'Innovative deutsche Sportmarke mit modernem Design.',
                    ],
                    'it' => [
                        'name' => 'Puma',
                        'description' => 'Marchio sportivo tedesco innovativo con design moderno.',
                    ],
                ]
            ]
        ];

        $brands = [];
        foreach ($brandsData as $brandData) {
            $brand = new Brand();
            $brand->setWebsite($brandData['website'])
                ->setSortOrder($brandData['sortOrder'])
                ->setIsActive(true);

            $manager->persist($brand);

            // Créer les traductions de la marque
            foreach ($brandData['translations'] as $langCode => $translation) {
                $brandTranslation = new BrandTranslation();
                $brandTranslation->setBrand($brand)
                    ->setLanguage($languages[$langCode])
                    ->setName($translation['name'])
                    ->setDescription($translation['description']);

                $manager->persist($brandTranslation);
                $brand->addTranslation($brandTranslation);
            }

            $brands[] = $brand;
        }

        return $brands;
    }

    private function createCategories(ObjectManager $manager, array $languages): array
    {
        $categoriesData = [
            [
                'parent' => null,
                'sortOrder' => 0,
                'translations' => [
                    'fr' => [
                        'name' => 'Vêtements',
                        'description' => 'Toute notre collection de vêtements pour homme et femme.',
                        'slug' => 'vetements',
                    ],
                    'en' => [
                        'name' => 'Clothing',
                        'description' => 'Our entire collection of clothing for men and women.',
                        'slug' => 'clothing',
                    ],
                    'es' => [
                        'name' => 'Ropa',
                        'description' => 'Toda nuestra colección de ropa para hombres y mujeres.',
                        'slug' => 'ropa',
                    ],
                    'de' => [
                        'name' => 'Kleidung',
                        'description' => 'Unsere gesamte Kollektion für Männer und Frauen.',
                        'slug' => 'kleidung',
                    ],
                    'it' => [
                        'name' => 'Abbigliamento',
                        'description' => 'Tutta la nostra collezione di abbigliamento per uomo e donna.',
                        'slug' => 'abbigliamento',
                    ],
                ]
            ],
            [
                'parent' => null,
                'sortOrder' => 1,
                'translations' => [
                    'fr' => [
                        'name' => 'Chaussures',
                        'description' => 'Collection complète de chaussures pour toutes les occasions.',
                        'slug' => 'chaussures',
                    ],
                    'en' => [
                        'name' => 'Shoes',
                        'description' => 'Complete collection of shoes for all occasions.',
                        'slug' => 'shoes',
                    ],
                    'es' => [
                        'name' => 'Zapatos',
                        'description' => 'Colección completa de zapatos para todas las ocasiones.',
                        'slug' => 'zapatos',
                    ],
                    'de' => [
                        'name' => 'Schuhe',
                        'description' => 'Komplette Schuhkollektion für alle Gelegenheiten.',
                        'slug' => 'schuhe',
                    ],
                    'it' => [
                        'name' => 'Scarpe',
                        'description' => 'Collezione completa di scarpe per tutte le occasioni.',
                        'slug' => 'scarpe',
                    ],
                ]
            ],
            [
                'parent' => null,
                'sortOrder' => 2,
                'translations' => [
                    'fr' => [
                        'name' => 'Accessoires',
                        'description' => 'Sacs, ceintures, bijoux et autres accessoires mode.',
                        'slug' => 'accessoires',
                    ],
                    'en' => [
                        'name' => 'Accessories',
                        'description' => 'Bags, belts, jewelry and other fashion accessories.',
                        'slug' => 'accessories',
                    ],
                    'es' => [
                        'name' => 'Accesorios',
                        'description' => 'Bolsos, cinturones, joyas y otros accesorios de moda.',
                        'slug' => 'accesorios',
                    ],
                    'de' => [
                        'name' => 'Accessoires',
                        'description' => 'Taschen, Gürtel, Schmuck und andere Modeaccessoires.',
                        'slug' => 'accessoires',
                    ],
                    'it' => [
                        'name' => 'Accessori',
                        'description' => 'Borse, cinture, gioielli e altri accessori moda.',
                        'slug' => 'accessori',
                    ],
                ]
            ]
        ];

        $categories = [];
        foreach ($categoriesData as $categoryData) {
            $category = new Category();
            $category->setSortOrder($categoryData['sortOrder'])
                ->setIsActive(true);

            $manager->persist($category);

            // Créer les traductions de la catégorie
            foreach ($categoryData['translations'] as $langCode => $translation) {
                $categoryTranslation = new CategoryTranslation();
                $categoryTranslation->setCategory($category)
                    ->setLanguage($languages[$langCode])
                    ->setName($translation['name'])
                    ->setDescription($translation['description'])
                    ->setSlug($translation['slug']);

                $manager->persist($categoryTranslation);
                $category->addTranslation($categoryTranslation);
            }

            $categories[] = $category;
        }

        return $categories;
    }

    private function createProducts(ObjectManager $manager, array $languages, array $brands, array $categories, array $attributes): array
    {
        $productsData = [
            [
                'sku' => 'NIKE-AIR-001',
                'price' => '129.99',
                'comparePrice' => '149.99',
                'stockQuantity' => 50,
                'isFeatured' => true,
                'brandIndex' => 0, // Nike
                'categoryIndex' => 1, // Chaussures
                'translations' => [
                    'fr' => [
                        'name' => 'Nike Air Max 90',
                        'description' => 'Chaussures de sport iconiques avec technologie Air Max pour un confort exceptionnel. Design intemporel et performance optimale.',
                        'shortDescription' => 'Chaussures de sport Nike Air Max avec amorti exceptionnel.',
                        'slug' => 'nike-air-max-90',
                    ],
                    'en' => [
                        'name' => 'Nike Air Max 90',
                        'description' => 'Iconic sports shoes with Air Max technology for exceptional comfort. Timeless design and optimal performance.',
                        'shortDescription' => 'Nike Air Max sports shoes with exceptional cushioning.',
                        'slug' => 'nike-air-max-90',
                    ],
                    'es' => [
                        'name' => 'Nike Air Max 90',
                        'description' => 'Zapatos deportivos icónicos con tecnología Air Max para comodidad excepcional. Diseño atemporal y rendimiento óptimo.',
                        'shortDescription' => 'Zapatos deportivos Nike Air Max con amortiguación excepcional.',
                        'slug' => 'nike-air-max-90',
                    ],
                    'de' => [
                        'name' => 'Nike Air Max 90',
                        'description' => 'Ikonische Sportschuhe mit Air Max-Technologie für außergewöhnlichen Komfort. Zeitloses Design und optimale Leistung.',
                        'shortDescription' => 'Nike Air Max Sportschuhe mit außergewöhnlicher Dämpfung.',
                        'slug' => 'nike-air-max-90',
                    ],
                    'it' => [
                        'name' => 'Nike Air Max 90',
                        'description' => 'Scarpe sportive iconiche con tecnologia Air Max per comfort eccezionale. Design senza tempo e prestazioni ottimali.',
                        'shortDescription' => 'Scarpe sportive Nike Air Max con ammortizzazione eccezionale.',
                        'slug' => 'nike-air-max-90',
                    ],
                ]
            ],
            [
                'sku' => 'ADIDAS-UB-002',
                'price' => '179.99',
                'comparePrice' => '199.99',
                'stockQuantity' => 30,
                'isFeatured' => true,
                'brandIndex' => 1, // Adidas
                'categoryIndex' => 1, // Chaussures
                'translations' => [
                    'fr' => [
                        'name' => 'Adidas Ultraboost 22',
                        'description' => 'Chaussures de running haute performance avec technologie Boost. Retour d\'énergie optimal et confort toute la journée.',
                        'shortDescription' => 'Chaussures de running Adidas avec technologie Boost.',
                        'slug' => 'adidas-ultraboost-22',
                    ],
                    'en' => [
                        'name' => 'Adidas Ultraboost 22',
                        'description' => 'High-performance running shoes with Boost technology. Optimal energy return and all-day comfort.',
                        'shortDescription' => 'Adidas running shoes with Boost technology.',
                        'slug' => 'adidas-ultraboost-22',
                    ],
                    'es' => [
                        'name' => 'Adidas Ultraboost 22',
                        'description' => 'Zapatos de running de alto rendimiento con tecnología Boost. Retorno de energía óptimo y comodidad todo el día.',
                        'shortDescription' => 'Zapatos de running Adidas con tecnología Boost.',
                        'slug' => 'adidas-ultraboost-22',
                    ],
                    'de' => [
                        'name' => 'Adidas Ultraboost 22',
                        'description' => 'Hochleistungs-Laufschuhe mit Boost-Technologie. Optimale Energierückgabe und ganztägiger Komfort.',
                        'shortDescription' => 'Adidas Laufschuhe mit Boost-Technologie.',
                        'slug' => 'adidas-ultraboost-22',
                    ],
                    'it' => [
                        'name' => 'Adidas Ultraboost 22',
                        'description' => 'Scarpe da running ad alte prestazioni con tecnologia Boost. Ritorno energetico ottimale e comfort tutto il giorno.',
                        'shortDescription' => 'Scarpe da running Adidas con tecnologia Boost.',
                        'slug' => 'adidas-ultraboost-22',
                    ],
                ]
            ],
            [
                'sku' => 'NIKE-TEE-003',
                'price' => '29.99',
                'comparePrice' => null,
                'stockQuantity' => 100,
                'isFeatured' => false,
                'brandIndex' => 0, // Nike
                'categoryIndex' => 0, // Vêtements
                'translations' => [
                    'fr' => [
                        'name' => 'T-shirt Nike Dri-FIT',
                        'description' => 'T-shirt de sport en tissu respirant Dri-FIT pour évacuer la transpiration. Coupe confortable et moderne.',
                        'shortDescription' => 'T-shirt de sport Nike en tissu respirant.',
                        'slug' => 't-shirt-nike-dri-fit',
                    ],
                    'en' => [
                        'name' => 'Nike Dri-FIT T-shirt',
                        'description' => 'Sports t-shirt in breathable Dri-FIT fabric to wick away sweat. Comfortable and modern fit.',
                        'shortDescription' => 'Nike sports t-shirt in breathable fabric.',
                        'slug' => 'nike-dri-fit-t-shirt',
                    ],
                    'es' => [
                        'name' => 'Camiseta Nike Dri-FIT',
                        'description' => 'Camiseta deportiva en tela transpirable Dri-FIT para alejar el sudor. Ajuste cómodo y moderno.',
                        'shortDescription' => 'Camiseta deportiva Nike en tela transpirable.',
                        'slug' => 'camiseta-nike-dri-fit',
                    ],
                    'de' => [
                        'name' => 'Nike Dri-FIT T-Shirt',
                        'description' => 'Sport-T-Shirt aus atmungsaktivem Dri-FIT-Gewebe zur Schweißableitung. Bequeme und moderne Passform.',
                        'shortDescription' => 'Nike Sport-T-Shirt aus atmungsaktivem Gewebe.',
                        'slug' => 'nike-dri-fit-t-shirt',
                    ],
                    'it' => [
                        'name' => 'T-shirt Nike Dri-FIT',
                        'description' => 'T-shirt sportiva in tessuto traspirante Dri-FIT per allontanare il sudore. Vestibilità comoda e moderna.',
                        'shortDescription' => 'T-shirt sportiva Nike in tessuto traspirante.',
                        'slug' => 't-shirt-nike-dri-fit',
                    ],
                ]
            ]
        ];

        $products = [];
        foreach ($productsData as $productData) {
            $product = new Product();
            $product->setSku($productData['sku'])
                ->setPrice($productData['price'])
                ->setComparePrice($productData['comparePrice'])
                ->setStockQuantity($productData['stockQuantity'])
                ->setIsActive(true)
                ->setIsFeatured($productData['isFeatured'])
                ->setIsDigital(false)
                ->setBrand($brands[$productData['brandIndex']])
                ->setCategory($categories[$productData['categoryIndex']]);

            $manager->persist($product);

            // Créer les traductions du produit
            foreach ($productData['translations'] as $langCode => $translation) {
                $productTranslation = new ProductTranslation();
                $productTranslation->setProduct($product)
                    ->setLanguage($languages[$langCode])
                    ->setName($translation['name'])
                    ->setDescription($translation['description'])
                    ->setShortDescription($translation['shortDescription'])
                    ->setSlug($translation['slug']);

                $manager->persist($productTranslation);
                $product->addTranslation($productTranslation);
            }

            $products[] = $product;
        }

        return $products;
    }
}