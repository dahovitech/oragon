<?php

namespace App\Bundle\BlogBundle\Service;

use App\Bundle\BlogBundle\Entity\Post;
use App\Bundle\BlogBundle\Repository\PostRepository;
use App\Bundle\BlogBundle\Repository\TagRepository;
use App\Bundle\CoreBundle\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class BlogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PostRepository $postRepository,
        private TagRepository $tagRepository,
        private CategoryRepository $categoryRepository,
        private SluggerInterface $slugger
    ) {}

    /**
     * Get published posts with pagination
     */
    public function getPublishedPosts(int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;
        
        $queryBuilder = $this->postRepository->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $posts = $queryBuilder->getQuery()->getResult();
        
        // Get total count for pagination
        $totalQuery = $this->postRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('now', new \DateTimeImmutable());
            
        $total = $totalQuery->getQuery()->getSingleScalarResult();

        return [
            'posts' => $posts,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * Get featured posts
     */
    public function getFeaturedPosts(int $limit = 5): array
    {
        return $this->postRepository->findFeatured($limit);
    }

    /**
     * Get recent posts
     */
    public function getRecentPosts(int $limit = 5): array
    {
        return $this->postRepository->findRecent($limit);
    }

    /**
     * Get popular posts
     */
    public function getPopularPosts(int $limit = 5): array
    {
        return $this->postRepository->findPopular($limit);
    }

    /**
     * Get post by slug
     */
    public function getPostBySlug(string $slug): ?Post
    {
        return $this->postRepository->findBySlug($slug);
    }

    /**
     * Get posts by category
     */
    public function getPostsByCategory(string $categorySlug, int $page = 1, int $limit = 10): array
    {
        $category = $this->categoryRepository->findOneBy(['slug' => $categorySlug, 'type' => 'blog']);
        
        if (!$category) {
            return ['posts' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0];
        }

        $offset = ($page - 1) * $limit;
        
        $queryBuilder = $this->postRepository->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.category = :category')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('category', $category)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $posts = $queryBuilder->getQuery()->getResult();
        
        // Get total count
        $totalQuery = $this->postRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->andWhere('p.category = :category')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('category', $category)
            ->setParameter('now', new \DateTimeImmutable());
            
        $total = $totalQuery->getQuery()->getSingleScalarResult();

        return [
            'posts' => $posts,
            'category' => $category,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * Get posts by tag
     */
    public function getPostsByTag(string $tagSlug, int $page = 1, int $limit = 10): array
    {
        $tag = $this->tagRepository->findBySlug($tagSlug);
        
        if (!$tag) {
            return ['posts' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0];
        }

        $offset = ($page - 1) * $limit;
        
        $queryBuilder = $this->postRepository->createQueryBuilder('p')
            ->join('p.tags', 't')
            ->andWhere('p.status = :status')
            ->andWhere('t = :tag')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('tag', $tag)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $posts = $queryBuilder->getQuery()->getResult();
        
        // Get total count
        $totalQuery = $this->postRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.tags', 't')
            ->andWhere('p.status = :status')
            ->andWhere('t = :tag')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('tag', $tag)
            ->setParameter('now', new \DateTimeImmutable());
            
        $total = $totalQuery->getQuery()->getSingleScalarResult();

        return [
            'posts' => $posts,
            'tag' => $tag,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * Search posts
     */
    public function searchPosts(string $query, int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;
        
        $queryBuilder = $this->postRepository->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.title LIKE :query OR p.content LIKE :query OR p.excerpt LIKE :query')
            ->setParameter('status', 'published')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.publishedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $posts = $queryBuilder->getQuery()->getResult();
        
        // Get total count
        $totalQuery = $this->postRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.title LIKE :query OR p.content LIKE :query OR p.excerpt LIKE :query')
            ->setParameter('status', 'published')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('query', '%' . $query . '%');
            
        $total = $totalQuery->getQuery()->getSingleScalarResult();

        return [
            'posts' => $posts,
            'query' => $query,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * Increment post view count
     */
    public function incrementViewCount(Post $post): void
    {
        $post->incrementViewCount();
        $this->entityManager->flush();
    }

    /**
     * Generate unique slug for post
     */
    public function generateSlug(string $title): string
    {
        $baseSlug = $this->slugger->slug($title)->lower();
        $slug = $baseSlug;
        $counter = 1;

        while ($this->postRepository->findOneBy(['slug' => $slug])) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get blog statistics
     */
    public function getBlogStatistics(): array
    {
        $postsCount = $this->postRepository->count(['status' => 'published']);
        $categoriesCount = $this->categoryRepository->count(['type' => 'blog', 'isActive' => true]);
        $tagsCount = $this->tagRepository->count([]);
        
        // Get most viewed post
        $mostViewedPost = $this->postRepository->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('p.viewCount', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'posts_count' => $postsCount,
            'categories_count' => $categoriesCount,
            'tags_count' => $tagsCount,
            'most_viewed_post' => $mostViewedPost,
        ];
    }
}