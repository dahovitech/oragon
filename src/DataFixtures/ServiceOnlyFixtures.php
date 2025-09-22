<?php

namespace App\DataFixtures;

use App\Entity\Service;
use App\Entity\ServiceTranslation;
use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ServiceOnlyFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        // Récupérer les langues existantes
        $languageRepo = $manager->getRepository(Language::class);
        $languages = [
            'fr' => $languageRepo->findOneBy(['code' => 'fr']),
            'en' => $languageRepo->findOneBy(['code' => 'en']),
            'es' => $languageRepo->findOneBy(['code' => 'es']),
            'de' => $languageRepo->findOneBy(['code' => 'de'])
        ];

        $services = $this->getServicesData();

        foreach ($services as $serviceData) {
            $service = new Service();
            $service->setSlug($serviceData['slug'])
                   ->setIsActive($serviceData['is_active'])
                   ->setSortOrder($serviceData['sort_order']);

            $manager->persist($service);

            // Ajouter les traductions
            foreach ($serviceData['translations'] as $locale => $translationData) {
                if (isset($languages[$locale]) && $languages[$locale]) {
                    $translation = new ServiceTranslation();
                    $translation->setTranslatable($service)
                               ->setLanguage($languages[$locale])
                               ->setTitle($translationData['title'])
                               ->setDescription($translationData['description'])
                               ->setContent($translationData['content'])
                               ->setMetaTitle($translationData['meta_title'])
                               ->setMetaDescription($translationData['meta_description']);

                    $manager->persist($translation);
                }
            }
        }

        $manager->flush();
    }

    private function getServicesData(): array
    {
        return [
            [
                'slug' => 'web-development',
                'is_active' => true,
                'sort_order' => 1,
                'translations' => [
                    'fr' => [
                        'title' => 'Développement Web',
                        'description' => 'Création de sites web modernes et responsive avec les dernières technologies.',
                        'content' => 'Notre équipe de développeurs experts crée des sites web performants, sécurisés et optimisés pour le référencement. Nous utilisons les frameworks les plus récents comme Symfony, React et Vue.js pour vous offrir une solution sur mesure.',
                        'meta_title' => 'Développement Web Professionnel | Oragon',
                        'meta_description' => 'Services de développement web professionnel avec Symfony, React et Vue.js. Sites modernes, responsive et optimisés SEO.'
                    ],
                    'en' => [
                        'title' => 'Web Development',
                        'description' => 'Creating modern and responsive websites with the latest technologies.',
                        'content' => 'Our team of expert developers creates high-performance, secure and SEO-optimized websites. We use the latest frameworks like Symfony, React and Vue.js to offer you a tailor-made solution.',
                        'meta_title' => 'Professional Web Development | Oragon',
                        'meta_description' => 'Professional web development services with Symfony, React and Vue.js. Modern, responsive and SEO-optimized websites.'
                    ],
                    'es' => [
                        'title' => 'Desarrollo Web',
                        'description' => 'Creación de sitios web modernos y responsive con las últimas tecnologías.',
                        'content' => 'Nuestro equipo de desarrolladores expertos crea sitios web de alto rendimiento, seguros y optimizados para SEO. Utilizamos los frameworks más recientes como Symfony, React y Vue.js para ofrecerte una solución a medida.',
                        'meta_title' => 'Desarrollo Web Profesional | Oragon',
                        'meta_description' => 'Servicios de desarrollo web profesional con Symfony, React y Vue.js. Sitios modernos, responsive y optimizados para SEO.'
                    ],
                    'de' => [
                        'title' => 'Webentwicklung',
                        'description' => 'Erstellung moderner und responsiver Websites mit den neuesten Technologien.',
                        'content' => 'Unser Team von erfahrenen Entwicklern erstellt leistungsstarke, sichere und SEO-optimierte Websites. Wir verwenden die neuesten Frameworks wie Symfony, React und Vue.js, um Ihnen eine maßgeschneiderte Lösung zu bieten.',
                        'meta_title' => 'Professionelle Webentwicklung | Oragon',
                        'meta_description' => 'Professionelle Webentwicklungsdienstleistungen mit Symfony, React und Vue.js. Moderne, responsive und SEO-optimierte Websites.'
                    ]
                ]
            ],
            [
                'slug' => 'mobile-app-development',
                'is_active' => true,
                'sort_order' => 2,
                'translations' => [
                    'fr' => [
                        'title' => 'Développement d\'Applications Mobiles',
                        'description' => 'Applications natives et cross-platform pour iOS et Android.',
                        'content' => 'Nous développons des applications mobiles performantes et intuitives pour iOS et Android. Que ce soit en natif (Swift, Kotlin) ou en cross-platform (React Native, Flutter), nous créons des apps qui offrent une expérience utilisateur exceptionnelle.',
                        'meta_title' => 'Développement Apps Mobiles iOS Android | Oragon',
                        'meta_description' => 'Développement d\'applications mobiles natives et cross-platform pour iOS et Android. React Native, Flutter, Swift, Kotlin.'
                    ],
                    'en' => [
                        'title' => 'Mobile App Development',
                        'description' => 'Native and cross-platform applications for iOS and Android.',
                        'content' => 'We develop high-performance and intuitive mobile applications for iOS and Android. Whether native (Swift, Kotlin) or cross-platform (React Native, Flutter), we create apps that offer an exceptional user experience.',
                        'meta_title' => 'Mobile App Development iOS Android | Oragon',
                        'meta_description' => 'Native and cross-platform mobile app development for iOS and Android. React Native, Flutter, Swift, Kotlin.'
                    ],
                    'es' => [
                        'title' => 'Desarrollo de Aplicaciones Móviles',
                        'description' => 'Aplicaciones nativas y multiplataforma para iOS y Android.',
                        'content' => 'Desarrollamos aplicaciones móviles de alto rendimiento e intuitivas para iOS y Android. Ya sea nativo (Swift, Kotlin) o multiplataforma (React Native, Flutter), creamos apps que ofrecen una experiencia de usuario excepcional.',
                        'meta_title' => 'Desarrollo Apps Móviles iOS Android | Oragon',
                        'meta_description' => 'Desarrollo de aplicaciones móviles nativas y multiplataforma para iOS y Android. React Native, Flutter, Swift, Kotlin.'
                    ],
                    'de' => [
                        'title' => 'Mobile App Entwicklung',
                        'description' => 'Native und plattformübergreifende Anwendungen für iOS und Android.',
                        'content' => 'Wir entwickeln leistungsstarke und intuitive mobile Anwendungen für iOS und Android. Ob nativ (Swift, Kotlin) oder plattformübergreifend (React Native, Flutter), wir erstellen Apps, die eine außergewöhnliche Benutzererfahrung bieten.',
                        'meta_title' => 'Mobile App Entwicklung iOS Android | Oragon',
                        'meta_description' => 'Native und plattformübergreifende mobile App-Entwicklung für iOS und Android. React Native, Flutter, Swift, Kotlin.'
                    ]
                ]
            ],
            [
                'slug' => 'digital-marketing',
                'is_active' => true,
                'sort_order' => 3,
                'translations' => [
                    'fr' => [
                        'title' => 'Marketing Digital',
                        'description' => 'Stratégies de marketing digital pour augmenter votre visibilité en ligne.',
                        'content' => 'Nos experts en marketing digital vous accompagnent pour développer votre présence en ligne. SEO, SEA, réseaux sociaux, email marketing - nous créons des campagnes personnalisées qui génèrent des résultats mesurables.',
                        'meta_title' => 'Agence Marketing Digital SEO SEA | Oragon',
                        'meta_description' => 'Agence de marketing digital spécialisée en SEO, SEA, réseaux sociaux et email marketing. Stratégies personnalisées pour votre visibilité en ligne.'
                    ],
                    'en' => [
                        'title' => 'Digital Marketing',
                        'description' => 'Digital marketing strategies to increase your online visibility.',
                        'content' => 'Our digital marketing experts help you develop your online presence. SEO, SEA, social media, email marketing - we create personalized campaigns that generate measurable results.',
                        'meta_title' => 'Digital Marketing Agency SEO SEA | Oragon',
                        'meta_description' => 'Digital marketing agency specialized in SEO, SEA, social media and email marketing. Personalized strategies for your online visibility.'
                    ],
                    'es' => [
                        'title' => 'Marketing Digital',
                        'description' => 'Estrategias de marketing digital para aumentar su visibilidad en línea.',
                        'content' => 'Nuestros expertos en marketing digital te ayudan a desarrollar tu presencia en línea. SEO, SEA, redes sociales, email marketing: creamos campañas personalizadas que generan resultados medibles.',
                        'meta_title' => 'Agencia Marketing Digital SEO SEA | Oragon',
                        'meta_description' => 'Agencia de marketing digital especializada en SEO, SEA, redes sociales y email marketing. Estrategias personalizadas para tu visibilidad en línea.'
                    ],
                    'de' => [
                        'title' => 'Digitales Marketing',
                        'description' => 'Digitale Marketing-Strategien zur Steigerung Ihrer Online-Sichtbarkeit.',
                        'content' => 'Unsere Digital-Marketing-Experten helfen Ihnen bei der Entwicklung Ihrer Online-Präsenz. SEO, SEA, soziale Medien, E-Mail-Marketing - wir erstellen personalisierte Kampagnen, die messbare Ergebnisse liefern.',
                        'meta_title' => 'Digital Marketing Agentur SEO SEA | Oragon',
                        'meta_description' => 'Digital Marketing Agentur spezialisiert auf SEO, SEA, soziale Medien und E-Mail-Marketing. Personalisierte Strategien für Ihre Online-Sichtbarkeit.'
                    ]
                ]
            ]
        ];
    }

    public static function getGroups(): array
    {
        return ['service-only', 'dev'];
    }
}
