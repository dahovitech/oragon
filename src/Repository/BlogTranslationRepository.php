<?php

namespace App\Repository;

use App\Entity\BlogTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogTranslation>
 */
class BlogTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogTranslation::class);
    }

    /**
     * Find by slug
     */
    public function findBySlug(string $slug): ?BlogTranslation
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find translations by language code
     */
    public function findByLanguageCode(string $languageCode): array
    {
        return $this->createQueryBuilder('bt')
            ->leftJoin('bt.language', 'l')
            ->leftJoin('bt.blog', 'b')
            ->where('l.code = :code')
            ->andWhere('b.isPublished = :published')
            ->setParameter('code', $languageCode)
            ->setParameter('published', true)
            ->orderBy('b.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}