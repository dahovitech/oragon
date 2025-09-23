<?php

namespace App\Repository;

use App\Entity\Blog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Blog>
 */
class BlogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Blog::class);
    }

    /**
     * Find published blogs
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isPublished = :published')
            ->andWhere('b.publishedAt IS NOT NULL')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find published blogs with pagination
     */
    public function findPublishedPaginated(int $page = 1, int $limit = 10): array
    {
        $query = $this->createQueryBuilder('b')
            ->where('b.isPublished = :published')
            ->andWhere('b.publishedAt IS NOT NULL')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Count published blogs
     */
    public function countPublished(): int
    {
        return $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.isPublished = :published')
            ->andWhere('b.publishedAt IS NOT NULL')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find by author
     */
    public function findByAuthor(int $authorId, bool $publishedOnly = true): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.author = :authorId')
            ->setParameter('authorId', $authorId)
            ->orderBy('b.publishedAt', 'DESC');

        if ($publishedOnly) {
            $qb->andWhere('b.isPublished = :published')
               ->andWhere('b.publishedAt IS NOT NULL')
               ->andWhere('b.publishedAt <= :now')
               ->setParameter('published', true)
               ->setParameter('now', new \DateTimeImmutable());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find popular blogs (by view count)
     */
    public function findPopular(int $limit = 5): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isPublished = :published')
            ->andWhere('b.publishedAt IS NOT NULL')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.viewCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent blogs
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isPublished = :published')
            ->andWhere('b.publishedAt IS NOT NULL')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search blogs by keyword (requires translations)
     */
    public function search(string $keyword, ?string $locale = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.translations', 't')
            ->where('b.isPublished = :published')
            ->andWhere('b.publishedAt IS NOT NULL')
            ->andWhere('b.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTimeImmutable());

        if ($locale) {
            $qb->leftJoin('t.language', 'l')
               ->andWhere('l.code = :locale')
               ->setParameter('locale', $locale);
        }

        $qb->andWhere('t.title LIKE :keyword OR t.content LIKE :keyword OR t.excerpt LIKE :keyword')
           ->setParameter('keyword', '%' . $keyword . '%')
           ->orderBy('b.publishedAt', 'DESC');

        return $qb->getQuery()->getResult();
    }
}