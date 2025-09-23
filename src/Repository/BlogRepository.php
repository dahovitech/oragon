<?php

namespace App\Repository;

use App\Entity\Blog;
use App\Entity\User;
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
    public function findPublishedBlogs(int $limit = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('b.publishedAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find blogs by author
     */
    public function findByAuthor(User $author): array
    {
        return $this->findBy(['author' => $author], ['createdAt' => 'DESC']);
    }

    /**
     * Find blogs with translations
     */
    public function findBlogsWithTranslations(): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.translations', 'bt')
            ->leftJoin('bt.language', 'l')
            ->addSelect('bt', 'l')
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find blog by slug and language
     */
    public function findBySlugAndLanguage(string $slug, string $languageCode): ?Blog
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.translations', 'bt')
            ->leftJoin('bt.language', 'l')
            ->where('bt.slug = :slug')
            ->andWhere('l.code = :language')
            ->andWhere('b.isPublished = :published')
            ->setParameter('slug', $slug)
            ->setParameter('language', $languageCode)
            ->setParameter('published', true)
            ->getQuery()
            ->getOneOrNullResult();
    }
}