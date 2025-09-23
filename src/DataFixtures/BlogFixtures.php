<?php

namespace App\DataFixtures;

use App\Bundle\BlogBundle\Entity\Comment;
use App\Bundle\BlogBundle\Entity\Post;
use App\Bundle\BlogBundle\Entity\Tag;
use App\Bundle\CoreBundle\Entity\Category;
use App\Bundle\CoreBundle\Entity\Language;
use App\Bundle\UserBundle\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BlogFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Créer des langues
        $frLanguage = new Language();
        $frLanguage->setName('Français');
        $frLanguage->setCode('fr');
        $frLanguage->setLocale('fr_FR');
        $frLanguage->setIsActive(true);
        $frLanguage->setIsDefault(true);
        $manager->persist($frLanguage);

        $enLanguage = new Language();
        $enLanguage->setName('English');
        $enLanguage->setCode('en');
        $enLanguage->setLocale('en_US');
        $enLanguage->setIsActive(true);
        $enLanguage->setIsDefault(false);
        $manager->persist($enLanguage);

        // Créer des catégories pour le blog
        $techCategory = new Category();
        $techCategory->setName('Technologie');
        $techCategory->setSlug('technologie');
        $techCategory->setDescription('Articles sur la technologie et l\'innovation');
        $techCategory->setType('blog');
        $techCategory->setIsActive(true);
        $techCategory->setSortOrder(1);
        $techCategory->setColor('#007bff');
        $manager->persist($techCategory);

        $businessCategory = new Category();
        $businessCategory->setName('Business');
        $businessCategory->setSlug('business');
        $businessCategory->setDescription('Articles sur le business et l\'entrepreneuriat');
        $businessCategory->setType('blog');
        $businessCategory->setIsActive(true);
        $businessCategory->setSortOrder(2);
        $businessCategory->setColor('#28a745');
        $manager->persist($businessCategory);

        // Créer des tags
        $tags = [
            ['name' => 'PHP', 'slug' => 'php', 'color' => '#8993be'],
            ['name' => 'Symfony', 'slug' => 'symfony', 'color' => '#000000'],
            ['name' => 'JavaScript', 'slug' => 'javascript', 'color' => '#f7df1e'],
            ['name' => 'Innovation', 'slug' => 'innovation', 'color' => '#ff6b6b'],
            ['name' => 'Startup', 'slug' => 'startup', 'color' => '#4ecdc4'],
        ];

        $tagEntities = [];
        foreach ($tags as $tagData) {
            $tag = new Tag();
            $tag->setName($tagData['name']);
            $tag->setSlug($tagData['slug']);
            $tag->setColor($tagData['color']);
            $manager->persist($tag);
            $tagEntities[] = $tag;
        }

        // Récupérer un utilisateur existant (assumant qu'il y en a un depuis CoreFixtures)
        $userRepository = $manager->getRepository(User::class);
        $author = $userRepository->findOneBy([]) ?? $this->createDefaultUser($manager);

        // Créer des articles de blog
        $posts = [
            [
                'title' => 'Les tendances du développement web en 2025',
                'slug' => 'tendances-developpement-web-2025',
                'excerpt' => 'Découvrez les technologies et frameworks qui domineront le développement web cette année.',
                'content' => '<p>Le développement web évolue constamment, et 2025 apporte son lot de nouvelles tendances passionnantes.</p><p>Parmi les technologies émergentes, nous retrouvons :</p><ul><li>Les Progressive Web Apps (PWA)</li><li>L\'intelligence artificielle intégrée</li><li>Les frameworks full-stack modernes</li><li>L\'amélioration des performances</li></ul><p>Ces innovations transforment la façon dont nous concevons et développons les applications web.</p>',
                'category' => $techCategory,
                'tags' => [$tagEntities[0], $tagEntities[1]], // PHP, Symfony
                'isFeatured' => true,
            ],
            [
                'title' => 'Comment créer une startup tech réussie',
                'slug' => 'creer-startup-tech-reussie',
                'excerpt' => 'Les étapes clés pour lancer et développer une startup technologique performante.',
                'content' => '<p>Créer une startup technologique requiert une approche méthodique et stratégique.</p><p>Voici les étapes essentielles :</p><ol><li>Identifier un problème réel à résoudre</li><li>Développer un MVP (Minimum Viable Product)</li><li>Valider le marché et les besoins</li><li>Constituer une équipe compétente</li><li>Lever des fonds si nécessaire</li></ol><p>Chaque étape demande du temps et de la réflexion pour maximiser les chances de succès.</p>',
                'category' => $businessCategory,
                'tags' => [$tagEntities[3], $tagEntities[4]], // Innovation, Startup
                'isFeatured' => false,
            ],
            [
                'title' => 'JavaScript moderne : ES2025 et ses nouveautés',
                'slug' => 'javascript-moderne-es2025-nouveautes',
                'excerpt' => 'Tour d\'horizon des nouvelles fonctionnalités de JavaScript ES2025 et leur impact sur le développement.',
                'content' => '<p>JavaScript continue d\'évoluer avec ES2025 qui apporte de nombreuses améliorations.</p><p>Les principales nouveautés incluent :</p><ul><li>De nouveaux opérateurs pour la manipulation d\'objets</li><li>Des améliorations des modules</li><li>Des optimisations de performance</li><li>Une meilleure gestion des erreurs</li></ul><p>Ces évolutions rendent le langage plus puissant et plus agréable à utiliser.</p>',
                'category' => $techCategory,
                'tags' => [$tagEntities[2]], // JavaScript
                'isFeatured' => true,
            ],
        ];

        $postEntities = [];
        foreach ($posts as $postData) {
            $post = new Post();
            $post->setTitle($postData['title']);
            $post->setSlug($postData['slug']);
            $post->setExcerpt($postData['excerpt']);
            $post->setContent($postData['content']);
            $post->setAuthor($author);
            $post->setCategory($postData['category']);
            $post->setStatus('published');
            $post->setIsFeatured($postData['isFeatured']);
            $post->setAllowComments(true);
            $post->setPublishedAt(new \DateTimeImmutable('-' . rand(1, 30) . ' days'));
            $post->setMetaTitle($postData['title']);
            $post->setMetaDescription($postData['excerpt']);

            foreach ($postData['tags'] as $tag) {
                $post->addTag($tag);
            }

            $manager->persist($post);
            $postEntities[] = $post;
        }

        // Créer des commentaires
        $comments = [
            [
                'post' => $postEntities[0],
                'content' => 'Excellent article ! Les PWA sont vraiment l\'avenir du web mobile.',
                'authorName' => 'Marie Dupont',
                'authorEmail' => 'marie.dupont@example.com',
                'status' => 'approved',
            ],
            [
                'post' => $postEntities[0],
                'content' => 'Je suis d\'accord, mais il faut aussi considérer les limitations actuelles des PWA.',
                'authorName' => 'Pierre Martin',
                'authorEmail' => 'pierre.martin@example.com',
                'status' => 'approved',
            ],
            [
                'post' => $postEntities[1],
                'content' => 'Très bon guide pour les entrepreneurs débutants. Merci pour ces conseils pratiques.',
                'authorName' => 'Sophie Leclerc',
                'authorEmail' => 'sophie.leclerc@example.com',
                'status' => 'approved',
            ],
            [
                'post' => $postEntities[2],
                'content' => 'ES2025 semble prometteur ! Hâte de tester ces nouvelles fonctionnalités.',
                'authorName' => 'Thomas Roux',
                'authorEmail' => 'thomas.roux@example.com',
                'status' => 'pending',
            ],
        ];

        foreach ($comments as $commentData) {
            $comment = new Comment();
            $comment->setPost($commentData['post']);
            $comment->setContent($commentData['content']);
            $comment->setAuthorName($commentData['authorName']);
            $comment->setAuthorEmail($commentData['authorEmail']);
            $comment->setStatus($commentData['status']);
            $comment->setIpAddress('192.168.1.' . rand(1, 254));
            $comment->setUserAgent('Mozilla/5.0 (Example Browser)');
            $manager->persist($comment);
        }

        $manager->flush();
    }

    private function createDefaultUser(ObjectManager $manager): User
    {
        $user = new User();
        $user->setEmail('author@oragon.fr');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setRoles(['ROLE_USER', 'ROLE_AUTHOR']);
        $user->setPassword('$2y$13$hashedpassword'); // Mot de passe hashé
        $user->setIsActive(true);
        $manager->persist($user);
        return $user;
    }
}