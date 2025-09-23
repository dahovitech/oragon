<?php

namespace App\Repository;

use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageTranslation>
 */
class PageTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageTranslation::class);
    }

    /**
     * Find translation by page and language
     */
    public function findByPageAndLanguage(Page $page, Language $language): ?PageTranslation
    {
        return $this->findOneBy([
            'page' => $page,
            'language' => $language
        ]);
    }

    /**
     * Find translations by language code
     */
    public function findByLanguageCode(string $languageCode): array
    {
        return $this->createQueryBuilder('pt')
            ->leftJoin('pt.language', 'l')
            ->leftJoin('pt.page', 'p')
            ->where('l.code = :code')
            ->andWhere('p.isActive = :active')
            ->setParameter('code', $languageCode)
            ->setParameter('active', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find by slug
     */
    public function findBySlug(string $slug): ?PageTranslation
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Get translation statistics for a language
     */
    public function getTranslationStatistics(string $languageCode): array
    {
        $qb = $this->createQueryBuilder('pt')
            ->select([
                'COUNT(pt.id) as total',
                'SUM(CASE WHEN pt.title IS NOT NULL AND pt.title != \'\' AND pt.content IS NOT NULL AND pt.content != \'\' THEN 1 ELSE 0 END) as complete',
                'SUM(CASE WHEN (pt.title IS NOT NULL AND pt.title != \'\') AND (pt.content IS NULL OR pt.content = \'\') THEN 1 ELSE 0 END) as incomplete'
            ])
            ->leftJoin('pt.language', 'l')
            ->where('l.code = :code')
            ->setParameter('code', $languageCode);

        $result = $qb->getQuery()->getOneOrNullResult();
        
        $total = (int) ($result['total'] ?? 0);
        $complete = (int) ($result['complete'] ?? 0);
        $incomplete = (int) ($result['incomplete'] ?? 0);

        return [
            'total' => $total,
            'complete' => $complete,
            'incomplete' => $incomplete,
            'percentage' => $total > 0 ? round(($complete / $total) * 100, 1) : 0
        ];
    }

    /**
     * Duplicate translation to another language
     */
    public function duplicateTranslation(PageTranslation $sourceTranslation, Language $targetLanguage): PageTranslation
    {
        $newTranslation = new PageTranslation();
        $newTranslation->setPage($sourceTranslation->getPage());
        $newTranslation->setLanguage($targetLanguage);
        $newTranslation->setTitle($sourceTranslation->getTitle());
        $newTranslation->setContent($sourceTranslation->getContent());
        $newTranslation->setMetaTitle($sourceTranslation->getMetaTitle());
        $newTranslation->setMetaDescription($sourceTranslation->getMetaDescription());
        
        // Generate new slug for the target language
        $baseSlug = $sourceTranslation->getSlug() . '-' . $targetLanguage->getCode();
        $newTranslation->setSlug($this->generateUniqueSlug($baseSlug));

        return $newTranslation;
    }

    /**
     * Generate unique slug
     */
    private function generateUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while ($this->findBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    //    /**
    //     * @return PageTranslation[] Returns an array of PageTranslation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?PageTranslation
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}