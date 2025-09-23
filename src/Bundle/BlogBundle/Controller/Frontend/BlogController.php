<?php

namespace App\Bundle\BlogBundle\Controller\Frontend;

use App\Bundle\BlogBundle\Service\BlogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/blog')]
class BlogController extends AbstractController
{
    public function __construct(
        private BlogService $blogService
    ) {}

    #[Route('', name: 'blog_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $result = $this->blogService->getPublishedPosts($page, $limit);
        $featuredPosts = $this->blogService->getFeaturedPosts(3);
        $recentPosts = $this->blogService->getRecentPosts(5);

        return $this->render('blog/frontend/index.html.twig', [
            'posts' => $result['posts'],
            'pagination' => [
                'page' => $result['page'],
                'pages' => $result['pages'],
                'total' => $result['total'],
                'limit' => $result['limit']
            ],
            'featured_posts' => $featuredPosts,
            'recent_posts' => $recentPosts,
        ]);
    }

    #[Route('/post/{slug}', name: 'blog_post_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $post = $this->blogService->getPostBySlug($slug);

        if (!$post) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        // Increment view count
        $this->blogService->incrementViewCount($post);

        // Get related posts (same category)
        $relatedPosts = [];
        if ($post->getCategory()) {
            $relatedResult = $this->blogService->getPostsByCategory(
                $post->getCategory()->getSlug(),
                1,
                4
            );
            $relatedPosts = array_filter(
                $relatedResult['posts'],
                fn($p) => $p->getId() !== $post->getId()
            );
        }

        $recentPosts = $this->blogService->getRecentPosts(5);

        return $this->render('blog/frontend/show.html.twig', [
            'post' => $post,
            'related_posts' => array_slice($relatedPosts, 0, 3),
            'recent_posts' => $recentPosts,
        ]);
    }

    #[Route('/category/{slug}', name: 'blog_category', methods: ['GET'])]
    public function category(string $slug, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $result = $this->blogService->getPostsByCategory($slug, $page, $limit);

        if (empty($result['posts']) && !isset($result['category'])) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }

        return $this->render('blog/frontend/category.html.twig', [
            'posts' => $result['posts'],
            'category' => $result['category'] ?? null,
            'pagination' => [
                'page' => $result['page'],
                'pages' => $result['pages'],
                'total' => $result['total'],
                'limit' => $result['limit']
            ],
        ]);
    }

    #[Route('/tag/{slug}', name: 'blog_tag', methods: ['GET'])]
    public function tag(string $slug, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $result = $this->blogService->getPostsByTag($slug, $page, $limit);

        if (empty($result['posts']) && !isset($result['tag'])) {
            throw $this->createNotFoundException('Tag non trouvé');
        }

        return $this->render('blog/frontend/tag.html.twig', [
            'posts' => $result['posts'],
            'tag' => $result['tag'] ?? null,
            'pagination' => [
                'page' => $result['page'],
                'pages' => $result['pages'],
                'total' => $result['total'],
                'limit' => $result['limit']
            ],
        ]);
    }

    #[Route('/search', name: 'blog_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = trim($request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        if (empty($query)) {
            return $this->render('blog/frontend/search.html.twig', [
                'posts' => [],
                'query' => '',
                'pagination' => [
                    'page' => 1,
                    'pages' => 0,
                    'total' => 0,
                    'limit' => $limit
                ],
            ]);
        }

        $result = $this->blogService->searchPosts($query, $page, $limit);

        return $this->render('blog/frontend/search.html.twig', [
            'posts' => $result['posts'],
            'query' => $result['query'],
            'pagination' => [
                'page' => $result['page'],
                'pages' => $result['pages'],
                'total' => $result['total'],
                'limit' => $result['limit']
            ],
        ]);
    }
}