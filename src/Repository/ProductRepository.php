<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Find active products
     */
    public function findActiveProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find featured products
     */
    public function findFeaturedProducts(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isFeatured = :featured')
            ->setParameter('active', true)
            ->setParameter('featured', true)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products by category
     */
    public function findByCategory(int $categoryId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.category = :category')
            ->andWhere('p.isActive = :active')
            ->setParameter('category', $categoryId)
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products by brand
     */
    public function findByBrand(int $brandId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.brand = :brand')
            ->andWhere('p.isActive = :active')
            ->setParameter('brand', $brandId)
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search products by term with translations
     */
    public function searchProducts(string $term, string $locale = 'fr'): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->leftJoin('t.language', 'l')
            ->andWhere('p.isActive = :active')
            ->andWhere('l.code = :locale')
            ->andWhere('t.name LIKE :term OR t.description LIKE :term OR t.shortDescription LIKE :term')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get products with low stock
     */
    public function findLowStockProducts(int $threshold = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.stockQuantity <= :threshold')
            ->andWhere('p.stockQuantity > 0')
            ->setParameter('active', true)
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stockQuantity', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get products statistics
     */
    public function getProductsStats(): array
    {
        $qb = $this->createQueryBuilder('p');
        
        return [
            'total' => $qb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult(),
            'active' => $qb->select('COUNT(p.id)')->andWhere('p.isActive = true')->getQuery()->getSingleScalarResult(),
            'featured' => $qb->select('COUNT(p.id)')->andWhere('p.isFeatured = true')->getQuery()->getSingleScalarResult(),
            'outOfStock' => $qb->select('COUNT(p.id)')->andWhere('p.stockQuantity = 0')->getQuery()->getSingleScalarResult(),
        ];
    }
}
