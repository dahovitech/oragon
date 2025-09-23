<?php

namespace App\Repository;

use App\Entity\Brand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Brand>
 */
class BrandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Brand::class);
    }

    /**
     * Find active brands
     */
    public function findActiveBrands(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('b.sortOrder', 'ASC')
            ->addOrderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get brands with products count
     */
    public function getBrandsWithProductsCount(): array
    {
        return $this->createQueryBuilder('b')
            ->select('b, COUNT(p.id) as productsCount')
            ->leftJoin('b.products', 'p', 'WITH', 'p.isActive = true')
            ->andWhere('b.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('b.id')
            ->orderBy('b.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find brands by letter
     */
    public function findByLetter(string $letter, string $locale = 'fr'): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.translations', 't')
            ->leftJoin('t.language', 'l')
            ->andWhere('b.isActive = :active')
            ->andWhere('l.code = :locale')
            ->andWhere('t.name LIKE :letter')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->setParameter('letter', $letter . '%')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
