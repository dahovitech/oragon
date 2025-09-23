<?php

namespace App\Controller\Frontend;

use App\Entity\Blog;
use App\Repository\BlogRepository;
use App\Service\LocaleService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', requirements: ['_locale' => 'fr|en|es|de|it'], defaults: ['_locale' => 'fr'])]
class BlogController extends AbstractController
{
    public function __construct(
        private BlogRepository $blogRepository,
        private LocaleService $localeService,
        private PaginatorInterface $paginator
    ) {
    }

    #[Route('/blog', name: 'blog_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = $this->localeService->getCurrentLocale();
        
        $queryBuilder = $this->blogRepository->createQueryBuilder('b')
            ->leftJoin('b.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('b.isPublished = :published')
            ->andWhere('l.code = :locale')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('locale', $locale)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.publishedAt', 'DESC');

        // Search functionality
        $search = $request->query->get('search');
        if ($search) {
            $queryBuilder->andWhere('t.title LIKE :search OR t.content LIKE :search OR t.excerpt LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Category filter (if categories are implemented for blog)
        $category = $request->query->get('category');
        if ($category) {
            $queryBuilder->leftJoin('b.categories', 'cat')
                ->andWhere('cat.slug = :category')
                ->setParameter('category', $category);
        }

        $posts = $this->paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            6 // Posts per page
        );

        // Get recent posts for sidebar
        $recentPosts = $this->blogRepository->createQueryBuilder('b')
            ->leftJoin('b.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('b.isPublished = :published')
            ->andWhere('l.code = :locale')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('locale', $locale)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.publishedAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('blog/index.html.twig', [
            'posts' => $posts,
            'recent_posts' => $recentPosts,
            'search_query' => $search,
            'current_category' => $category,
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/blog/{slug}', name: 'blog_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $locale = $this->localeService->getCurrentLocale();
        
        // Find blog post by slug in current locale
        $post = $this->blogRepository->createQueryBuilder('b')
            ->leftJoin('b.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('b.isPublished = :published')
            ->andWhere('t.slug = :slug')
            ->andWhere('l.code = :locale')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('slug', $slug)
            ->setParameter('locale', $locale)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();

        if (!$post) {
            throw $this->createNotFoundException('Blog post not found');
        }

        // Get related posts (same author or similar content)
        $relatedPosts = $this->blogRepository->createQueryBuilder('b')
            ->leftJoin('b.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('b.isPublished = :published')
            ->andWhere('b.id != :currentPostId')
            ->andWhere('l.code = :locale')
            ->andWhere('b.publishedAt <= :now')
            ->andWhere('(b.author = :author OR t.title LIKE :titleWords)')
            ->setParameter('published', true)
            ->setParameter('currentPostId', $post->getId())
            ->setParameter('locale', $locale)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('author', $post->getAuthor())
            ->setParameter('titleWords', '%' . implode('%', array_slice(explode(' ', $post->getTranslation($locale)->getTitle()), 0, 3)) . '%')
            ->orderBy('b.publishedAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        // Get recent posts for sidebar
        $recentPosts = $this->blogRepository->createQueryBuilder('b')
            ->leftJoin('b.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('b.isPublished = :published')
            ->andWhere('l.code = :locale')
            ->andWhere('b.id != :currentPostId')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('locale', $locale)
            ->setParameter('currentPostId', $post->getId())
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.publishedAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('blog/show.html.twig', [
            'post' => $post,
            'related_posts' => $relatedPosts,
            'recent_posts' => $recentPosts,
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/blog/category/{slug}', name: 'blog_category', methods: ['GET'])]
    public function category(string $slug, Request $request): Response
    {
        $locale = $this->localeService->getCurrentLocale();
        
        // Forward to index with category filter
        $request->query->set('category', $slug);
        return $this->forward(self::class . '::index', [], $request->query->all());
    }

    #[Route('/blog/author/{id}', name: 'blog_author', methods: ['GET'])]
    public function author(int $id, Request $request): Response
    {
        $locale = $this->localeService->getCurrentLocale();
        
        $queryBuilder = $this->blogRepository->createQueryBuilder('b')
            ->leftJoin('b.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('b.isPublished = :published')
            ->andWhere('b.author = :authorId')
            ->andWhere('l.code = :locale')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('authorId', $id)
            ->setParameter('locale', $locale)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.publishedAt', 'DESC');

        $posts = $this->paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            6
        );

        // Get author info from first post
        $author = null;
        if ($posts->getItems()) {
            $author = $posts->getItems()[0]->getAuthor();
        }

        if (!$author) {
            throw $this->createNotFoundException('Author not found');
        }

        return $this->render('blog/author.html.twig', [
            'posts' => $posts,
            'author' => $author,
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/api/blog/search', name: 'api_blog_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $locale = $this->localeService->getCurrentLocale();
        
        $posts = $this->blogRepository->createQueryBuilder('b')
            ->select('b.id', 't.title', 't.slug', 't.excerpt')
            ->leftJoin('b.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('b.isPublished = :published')
            ->andWhere('l.code = :locale')
            ->andWhere('t.title LIKE :query OR t.content LIKE :query')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('locale', $locale)
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.publishedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->json($posts);
    }
}