<?php

namespace App\Repository;

use App\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Page>
 */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    /**
     * Find all active pages
     */
    public function findActivePages(): array
    {
        return $this->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
    }

    /**
     * Find page by type
     */
    public function findByType(string $type): ?Page
    {
        return $this->findOneBy(['type' => $type]);
    }

    /**
     * Find pages with translations
     */
    public function findPagesWithTranslations(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 'pt')
            ->leftJoin('pt.language', 'l')
            ->addSelect('pt', 'l')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find page by slug and language
     */
    public function findBySlugAndLanguage(string $slug, string $languageCode): ?Page
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 'pt')
            ->leftJoin('pt.language', 'l')
            ->where('pt.slug = :slug')
            ->andWhere('l.code = :language')
            ->andWhere('p.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('language', $languageCode)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return Page[] Returns an array of Page objects
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

    //    public function findOneBySomeField($value): ?Page
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}