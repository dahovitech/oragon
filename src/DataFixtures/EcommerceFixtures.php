<?php

namespace App\DataFixtures;

use App\Entity\Language;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\Category;
use App\Entity\CategoryTranslation;
use App\Entity\Brand;
use App\Entity\BrandTranslation;
use App\Entity\Attribute;
use App\Entity\AttributeTranslation;
use App\Entity\AttributeValue;
use App\Entity\AttributeValueTranslation;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EcommerceFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create languages first
        $languages = $this->createLanguages($manager);
        
        // Create admin user
        $this->createAdminUser($manager);
        
        // Create brands
        $brands = $this->createBrands($manager, $languages);
        
        // Create categories
        $categories = $this->createCategories($manager, $languages);
        
        // Create attributes
        $attributes = $this->createAttributes($manager, $languages);
        
        // Create products
        $this->createProducts($manager, $languages, $brands, $categories, $attributes);
        
        // Create pages
        $this->createPages($manager, $languages);
        
        $manager->flush();
    }

    private function createLanguages(ObjectManager $manager): array
    {
        $languagesData = [
            [
                'code' => 'fr',
                'name' => 'Français',
                'nativeName' => 'Français',
                'currency' => 'EUR',
                'region' => 'FR',
                'isDefault' => true,
                'sortOrder' => 1
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'nativeName' => 'English',
                'currency' => 'USD',
                'region' => 'US',
                'isDefault' => false,
                'sortOrder' => 2
            ],
            [
                'code' => 'es',
                'name' => 'Español',
                'nativeName' => 'Español',
                'currency' => 'EUR',
                'region' => 'ES',
                'isDefault' => false,
                'sortOrder' => 3
            ],
            [
                'code' => 'de',
                'name' => 'Deutsch',
                'nativeName' => 'Deutsch',
                'currency' => 'EUR',
                'region' => 'DE',
                'isDefault' => false,
                'sortOrder' => 4
            ],
            [
                'code' => 'it',
                'name' => 'Italiano',
                'nativeName' => 'Italiano',
                'currency' => 'EUR',
                'region' => 'IT',
                'isDefault' => false,
                'sortOrder' => 5
            ]
        ];

        $languages = [];
        foreach ($languagesData as $data) {
            $language = new Language();
            $language->setCode($data['code']);
            $language->setName($data['name']);
            $language->setNativeName($data['nativeName']);
            $language->setCurrency($data['currency']);
            $language->setRegion($data['region']);
            $language->setIsDefault($data['isDefault']);
            $language->setSortOrder($data['sortOrder']);
            $language->setIsActive(true);

            $manager->persist($language);
            $languages[$data['code']] = $language;
        }

        return $languages;
    }

    private function createAdminUser(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@chromeShop.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('ChromeShop');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsActive(true);
        
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);

        $manager->persist($admin);
    }

    private function createBrands(ObjectManager $manager, array $languages): array
    {
        $brandsData = [
            [
                'translations' => [
                    'fr' => ['name' => 'Nike', 'description' => 'Marque de sport mondiale leader'],
                    'en' => ['name' => 'Nike', 'description' => 'Leading global sports brand'],
                    'es' => ['name' => 'Nike', 'description' => 'Marca deportiva global líder'],
                    'de' => ['name' => 'Nike', 'description' => 'Führende globale Sportmarke'],
                    'it' => ['name' => 'Nike', 'description' => 'Marchio sportivo globale leader']
                ],
                'website' => 'https://www.nike.com'
            ],
            [
                'translations' => [
                    'fr' => ['name' => 'Apple', 'description' => 'Technologie et innovation'],
                    'en' => ['name' => 'Apple', 'description' => 'Technology and innovation'],
                    'es' => ['name' => 'Apple', 'description' => 'Tecnología e innovación'],
                    'de' => ['name' => 'Apple', 'description' => 'Technologie und Innovation'],
                    'it' => ['name' => 'Apple', 'description' => 'Tecnologia e innovazione']
                ],
                'website' => 'https://www.apple.com'
            ],
            [
                'translations' => [
                    'fr' => ['name' => 'Samsung', 'description' => 'Électronique et technologie'],
                    'en' => ['name' => 'Samsung', 'description' => 'Electronics and technology'],
                    'es' => ['name' => 'Samsung', 'description' => 'Electrónica y tecnología'],
                    'de' => ['name' => 'Samsung', 'description' => 'Elektronik und Technologie'],
                    'it' => ['name' => 'Samsung', 'description' => 'Elettronica e tecnologia']
                ],
                'website' => 'https://www.samsung.com'
            ]
        ];

        $brands = [];
        foreach ($brandsData as $data) {
            $brand = new Brand();
            $brand->setWebsite($data['website']);
            $brand->setIsActive(true);

            foreach ($data['translations'] as $langCode => $translation) {
                if (isset($languages[$langCode])) {
                    $brandTranslation = new BrandTranslation();
                    $brandTranslation->setBrand($brand);
                    $brandTranslation->setLanguage($languages[$langCode]);
                    $brandTranslation->setName($translation['name']);
                    $brandTranslation->setDescription($translation['description']);
                    
                    $brand->addTranslation($brandTranslation);
                    $manager->persist($brandTranslation);
                }
            }

            $manager->persist($brand);
            $brands[] = $brand;
        }

        return $brands;
    }

    private function createCategories(ObjectManager $manager, array $languages): array
    {
        $categoriesData = [
            [
                'translations' => [
                    'fr' => ['name' => 'Électronique', 'description' => 'Appareils électroniques et gadgets', 'slug' => 'electronique'],
                    'en' => ['name' => 'Electronics', 'description' => 'Electronic devices and gadgets', 'slug' => 'electronics'],
                    'es' => ['name' => 'Electrónica', 'description' => 'Dispositivos electrónicos y gadgets', 'slug' => 'electronica'],
                    'de' => ['name' => 'Elektronik', 'description' => 'Elektronische Geräte und Gadgets', 'slug' => 'elektronik'],
                    'it' => ['name' => 'Elettronica', 'description' => 'Dispositivi elettronici e gadget', 'slug' => 'elettronica']
                ]
            ],
            [
                'translations' => [
                    'fr' => ['name' => 'Mode', 'description' => 'Vêtements et accessoires de mode', 'slug' => 'mode'],
                    'en' => ['name' => 'Fashion', 'description' => 'Clothing and fashion accessories', 'slug' => 'fashion'],
                    'es' => ['name' => 'Moda', 'description' => 'Ropa y accesorios de moda', 'slug' => 'moda'],
                    'de' => ['name' => 'Mode', 'description' => 'Kleidung und Mode-Accessoires', 'slug' => 'mode'],
                    'it' => ['name' => 'Moda', 'description' => 'Abbigliamento e accessori moda', 'slug' => 'moda']
                ]
            ],
            [
                'translations' => [
                    'fr' => ['name' => 'Maison & Jardin', 'description' => 'Articles pour la maison et le jardin', 'slug' => 'maison-jardin'],
                    'en' => ['name' => 'Home & Garden', 'description' => 'Home and garden items', 'slug' => 'home-garden'],
                    'es' => ['name' => 'Casa y Jardín', 'description' => 'Artículos para casa y jardín', 'slug' => 'casa-jardin'],
                    'de' => ['name' => 'Haus & Garten', 'description' => 'Haus- und Gartenartikel', 'slug' => 'haus-garten'],
                    'it' => ['name' => 'Casa e Giardino', 'description' => 'Articoli per casa e giardino', 'slug' => 'casa-giardino']
                ]
            ],
            [
                'translations' => [
                    'fr' => ['name' => 'Sport', 'description' => 'Équipements et vêtements de sport', 'slug' => 'sport'],
                    'en' => ['name' => 'Sports', 'description' => 'Sports equipment and clothing', 'slug' => 'sports'],
                    'es' => ['name' => 'Deportes', 'description' => 'Equipos y ropa deportiva', 'slug' => 'deportes'],
                    'de' => ['name' => 'Sport', 'description' => 'Sportausrüstung und -bekleidung', 'slug' => 'sport'],
                    'it' => ['name' => 'Sport', 'description' => 'Attrezzature e abbigliamento sportivo', 'slug' => 'sport']
                ]
            ]
        ];

        $categories = [];
        foreach ($categoriesData as $data) {
            $category = new Category();
            $category->setIsActive(true);

            foreach ($data['translations'] as $langCode => $translation) {
                if (isset($languages[$langCode])) {
                    $categoryTranslation = new CategoryTranslation();
                    $categoryTranslation->setCategory($category);
                    $categoryTranslation->setLanguage($languages[$langCode]);
                    $categoryTranslation->setName($translation['name']);
                    $categoryTranslation->setDescription($translation['description']);
                    $categoryTranslation->setSlug($translation['slug']);
                    
                    $category->addTranslation($categoryTranslation);
                    $manager->persist($categoryTranslation);
                }
            }

            $manager->persist($category);
            $categories[] = $category;
        }

        return $categories;
    }

    private function createAttributes(ObjectManager $manager, array $languages): array
    {
        $attributesData = [
            [
                'type' => Attribute::TYPE_SELECT,
                'translations' => [
                    'fr' => ['name' => 'Couleur'],
                    'en' => ['name' => 'Color'],
                    'es' => ['name' => 'Color'],
                    'de' => ['name' => 'Farbe'],
                    'it' => ['name' => 'Colore']
                ],
                'values' => [
                    [
                        'translations' => [
                            'fr' => ['value' => 'Rouge'],
                            'en' => ['value' => 'Red'],
                            'es' => ['value' => 'Rojo'],
                            'de' => ['value' => 'Rot'],
                            'it' => ['value' => 'Rosso']
                        ],
                        'colorCode' => '#FF0000'
                    ],
                    [
                        'translations' => [
                            'fr' => ['value' => 'Bleu'],
                            'en' => ['value' => 'Blue'],
                            'es' => ['value' => 'Azul'],
                            'de' => ['value' => 'Blau'],
                            'it' => ['value' => 'Blu']
                        ],
                        'colorCode' => '#0000FF'
                    ],
                    [
                        'translations' => [
                            'fr' => ['value' => 'Noir'],
                            'en' => ['value' => 'Black'],
                            'es' => ['value' => 'Negro'],
                            'de' => ['value' => 'Schwarz'],
                            'it' => ['value' => 'Nero']
                        ],
                        'colorCode' => '#000000'
                    ]
                ]
            ],
            [
                'type' => Attribute::TYPE_SELECT,
                'translations' => [
                    'fr' => ['name' => 'Taille'],
                    'en' => ['name' => 'Size'],
                    'es' => ['name' => 'Tamaño'],
                    'de' => ['name' => 'Größe'],
                    'it' => ['name' => 'Taglia']
                ],
                'values' => [
                    [
                        'translations' => [
                            'fr' => ['value' => 'XS'],
                            'en' => ['value' => 'XS'],
                            'es' => ['value' => 'XS'],
                            'de' => ['value' => 'XS'],
                            'it' => ['value' => 'XS']
                        ]
                    ],
                    [
                        'translations' => [
                            'fr' => ['value' => 'S'],
                            'en' => ['value' => 'S'],
                            'es' => ['value' => 'S'],
                            'de' => ['value' => 'S'],
                            'it' => ['value' => 'S']
                        ]
                    ],
                    [
                        'translations' => [
                            'fr' => ['value' => 'M'],
                            'en' => ['value' => 'M'],
                            'es' => ['value' => 'M'],
                            'de' => ['value' => 'M'],
                            'it' => ['value' => 'M']
                        ]
                    ],
                    [
                        'translations' => [
                            'fr' => ['value' => 'L'],
                            'en' => ['value' => 'L'],
                            'es' => ['value' => 'L'],
                            'de' => ['value' => 'L'],
                            'it' => ['value' => 'L']
                        ]
                    ],
                    [
                        'translations' => [
                            'fr' => ['value' => 'XL'],
                            'en' => ['value' => 'XL'],
                            'es' => ['value' => 'XL'],
                            'de' => ['value' => 'XL'],
                            'it' => ['value' => 'XL']
                        ]
                    ]
                ]
            ]
        ];

        $attributes = [];
        foreach ($attributesData as $data) {
            $attribute = new Attribute();
            $attribute->setType($data['type']);
            $attribute->setIsActive(true);
            $attribute->setIsFilterable(true);

            foreach ($data['translations'] as $langCode => $translation) {
                if (isset($languages[$langCode])) {
                    $attributeTranslation = new AttributeTranslation();
                    $attributeTranslation->setAttribute($attribute);
                    $attributeTranslation->setLanguage($languages[$langCode]);
                    $attributeTranslation->setName($translation['name']);
                    
                    $attribute->addTranslation($attributeTranslation);
                    $manager->persist($attributeTranslation);
                }
            }

            // Create attribute values
            foreach ($data['values'] as $valueData) {
                $attributeValue = new AttributeValue();
                $attributeValue->setAttribute($attribute);
                $attributeValue->setIsActive(true);
                
                if (isset($valueData['colorCode'])) {
                    $attributeValue->setColorCode($valueData['colorCode']);
                }

                foreach ($valueData['translations'] as $langCode => $translation) {
                    if (isset($languages[$langCode])) {
                        $valueTranslation = new AttributeValueTranslation();
                        $valueTranslation->setAttributeValue($attributeValue);
                        $valueTranslation->setLanguage($languages[$langCode]);
                        $valueTranslation->setValue($translation['value']);
                        
                        $attributeValue->addTranslation($valueTranslation);
                        $manager->persist($valueTranslation);
                    }
                }

                $attribute->addValue($attributeValue);
                $manager->persist($attributeValue);
            }

            $manager->persist($attribute);
            $attributes[] = $attribute;
        }

        return $attributes;
    }

    private function createProducts(ObjectManager $manager, array $languages, array $brands, array $categories, array $attributes): void
    {
        $productsData = [
            [
                'sku' => 'IPHONE15-128-BLK',
                'price' => '799.00',
                'stockQuantity' => 50,
                'isFeatured' => true,
                'brand' => $brands[1], // Apple
                'category' => $categories[0], // Electronics
                'translations' => [
                    'fr' => [
                        'name' => 'iPhone 15 128GB Noir',
                        'description' => 'Le nouveau iPhone 15 avec puce A17 Bionic et appareil photo 48 MP.',
                        'shortDescription' => 'iPhone 15 avec 128GB de stockage en noir.',
                        'slug' => 'iphone-15-128gb-noir'
                    ],
                    'en' => [
                        'name' => 'iPhone 15 128GB Black',
                        'description' => 'The new iPhone 15 with A17 Bionic chip and 48MP camera.',
                        'shortDescription' => 'iPhone 15 with 128GB storage in black.',
                        'slug' => 'iphone-15-128gb-black'
                    ],
                    'es' => [
                        'name' => 'iPhone 15 128GB Negro',
                        'description' => 'El nuevo iPhone 15 con chip A17 Bionic y cámara de 48 MP.',
                        'shortDescription' => 'iPhone 15 con 128GB de almacenamiento en negro.',
                        'slug' => 'iphone-15-128gb-negro'
                    ]
                ]
            ],
            [
                'sku' => 'NIKE-AIR-MAX-42',
                'price' => '129.99',
                'stockQuantity' => 25,
                'isFeatured' => true,
                'brand' => $brands[0], // Nike
                'category' => $categories[3], // Sports
                'translations' => [
                    'fr' => [
                        'name' => 'Nike Air Max 90 Taille 42',
                        'description' => 'Chaussures de sport iconiques avec amorti Air visible.',
                        'shortDescription' => 'Nike Air Max 90 pour homme en taille 42.',
                        'slug' => 'nike-air-max-90-taille-42'
                    ],
                    'en' => [
                        'name' => 'Nike Air Max 90 Size 42',
                        'description' => 'Iconic sports shoes with visible Air cushioning.',
                        'shortDescription' => 'Nike Air Max 90 for men in size 42.',
                        'slug' => 'nike-air-max-90-size-42'
                    ],
                    'es' => [
                        'name' => 'Nike Air Max 90 Talla 42',
                        'description' => 'Zapatillas deportivas icónicas con amortiguación Air visible.',
                        'shortDescription' => 'Nike Air Max 90 para hombre en talla 42.',
                        'slug' => 'nike-air-max-90-talla-42'
                    ]
                ]
            ]
        ];

        foreach ($productsData as $data) {
            $product = new Product();
            $product->setSku($data['sku']);
            $product->setPrice($data['price']);
            $product->setStockQuantity($data['stockQuantity']);
            $product->setIsFeatured($data['isFeatured']);
            $product->setBrand($data['brand']);
            $product->setCategory($data['category']);
            $product->setIsActive(true);

            foreach ($data['translations'] as $langCode => $translation) {
                if (isset($languages[$langCode])) {
                    $productTranslation = new ProductTranslation();
                    $productTranslation->setProduct($product);
                    $productTranslation->setLanguage($languages[$langCode]);
                    $productTranslation->setName($translation['name']);
                    $productTranslation->setDescription($translation['description']);
                    $productTranslation->setShortDescription($translation['shortDescription']);
                    $productTranslation->setSlug($translation['slug']);
                    
                    $product->addTranslation($productTranslation);
                    $manager->persist($productTranslation);
                }
            }

            $manager->persist($product);
        }
    }

    private function createPages(ObjectManager $manager, array $languages): void
    {
        $pagesData = [
            [
                'type' => Page::TYPE_ABOUT,
                'translations' => [
                    'fr' => [
                        'title' => 'À propos de nous',
                        'content' => '<h1>À propos de ChromeShop</h1><p>ChromeShop est votre destination e-commerce multilingue pour tous vos besoins shopping.</p>',
                        'slug' => 'a-propos'
                    ],
                    'en' => [
                        'title' => 'About Us',
                        'content' => '<h1>About ChromeShop</h1><p>ChromeShop is your multilingual e-commerce destination for all your shopping needs.</p>',
                        'slug' => 'about-us'
                    ],
                    'es' => [
                        'title' => 'Acerca de nosotros',
                        'content' => '<h1>Acerca de ChromeShop</h1><p>ChromeShop es tu destino de comercio electrónico multilingüe para todas tus necesidades de compra.</p>',
                        'slug' => 'acerca-de-nosotros'
                    ]
                ]
            ],
            [
                'type' => Page::TYPE_PRIVACY,
                'translations' => [
                    'fr' => [
                        'title' => 'Politique de confidentialité',
                        'content' => '<h1>Politique de confidentialité</h1><p>Votre vie privée est importante pour nous...</p>',
                        'slug' => 'politique-confidentialite'
                    ],
                    'en' => [
                        'title' => 'Privacy Policy',
                        'content' => '<h1>Privacy Policy</h1><p>Your privacy is important to us...</p>',
                        'slug' => 'privacy-policy'
                    ],
                    'es' => [
                        'title' => 'Política de Privacidad',
                        'content' => '<h1>Política de Privacidad</h1><p>Su privacidad es importante para nosotros...</p>',
                        'slug' => 'politica-privacidad'
                    ]
                ]
            ]
        ];

        foreach ($pagesData as $data) {
            $page = new Page();
            $page->setType($data['type']);
            $page->setIsActive(true);

            foreach ($data['translations'] as $langCode => $translation) {
                if (isset($languages[$langCode])) {
                    $pageTranslation = new PageTranslation();
                    $pageTranslation->setPage($page);
                    $pageTranslation->setLanguage($languages[$langCode]);
                    $pageTranslation->setTitle($translation['title']);
                    $pageTranslation->setContent($translation['content']);
                    $pageTranslation->setSlug($translation['slug']);
                    
                    $page->addTranslation($pageTranslation);
                    $manager->persist($pageTranslation);
                }
            }

            $manager->persist($page);
        }
    }
}