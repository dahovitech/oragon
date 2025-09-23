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
     * Find by blog and language
     */
    public function findByBlogAndLanguage(int $blogId, string $languageCode): ?BlogTranslation
    {
        return $this->createQueryBuilder('bt')
            ->join('bt.language', 'l')
            ->where('bt.blog = :blogId')
            ->andWhere('l.code = :languageCode')
            ->setParameter('blogId', $blogId)
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find incomplete translations (below certain percentage)
     */
    public function findIncomplete(int $threshold = 80): array
    {
        // This would require custom SQL or application-level filtering
        // For now, return all and filter in service layer
        return $this->findAll();
    }

    /**
     * Find by language
     */
    public function findByLanguage(string $languageCode): array
    {
        return $this->createQueryBuilder('bt')
            ->join('bt.language', 'l')
            ->where('l.code = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find missing translations for a blog
     */
    public function findMissingForBlog(int $blogId): array
    {
        // Get all active languages
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT l.code
            FROM languages l
            WHERE l.is_active = 1
            AND l.code NOT IN (
                SELECT lang.code
                FROM blog_translations bt
                JOIN languages lang ON bt.language_id = lang.id
                WHERE bt.blog_id = :blogId
            )
        ';
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['blogId' => $blogId]);
        
        return $result->fetchAllAssociative();
    }
}