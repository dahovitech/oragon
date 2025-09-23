<?php

namespace App\Bundle\BlogBundle\Repository;

use App\Bundle\BlogBundle\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function save(Post $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Post $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Post[] Returns an array of published posts
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Post[] Returns an array of featured posts
     */
    public function findFeatured(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.isFeatured = :featured')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('featured', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find posts by category
     */
    public function findByCategory($category, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.category = :category')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('category', $category)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find posts by tag
     */
    public function findByTag($tag, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.tags', 't')
            ->andWhere('p.status = :status')
            ->andWhere('t = :tag')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('tag', $tag)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search posts by title or content
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.title LIKE :query OR p.content LIKE :query')
            ->setParameter('status', 'published')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find post by slug
     */
    public function findBySlug(string $slug): ?Post
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('slug', $slug)
            ->setParameter('status', 'published')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get recent posts
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get popular posts (by view count)
     */
    public function findPopular(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.viewCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find posts by criteria with pagination
     */
    public function findByCriteria(array $criteria, int $page = 1, int $limit = 10, $categoryId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->leftJoin('p.category', 'c')
            ->orderBy('p.createdAt', 'DESC');

        foreach ($criteria as $field => $value) {
            $qb->andWhere("p.$field = :$field")
               ->setParameter($field, $value);
        }

        if ($categoryId) {
            $qb->andWhere('p.category = :categoryId')
               ->setParameter('categoryId', $categoryId);
        }

        return $qb->setFirstResult(($page - 1) * $limit)
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Count posts by criteria
     */
    public function countByCriteria(array $criteria, $categoryId = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');

        foreach ($criteria as $field => $value) {
            $qb->andWhere("p.$field = :$field")
               ->setParameter($field, $value);
        }

        if ($categoryId) {
            $qb->andWhere('p.category = :categoryId')
               ->setParameter('categoryId', $categoryId);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }
}