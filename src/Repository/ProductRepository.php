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
    public function findActive(): array
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
    public function findFeatured(int $limit = 8): array
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
    public function findByCategory($category, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.category = :category')
            ->setParameter('active', true)
            ->setParameter('category', $category)
            ->orderBy('p.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find products by brand
     */
    public function findByBrand($brand, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.brand = :brand')
            ->setParameter('active', true)
            ->setParameter('brand', $brand)
            ->orderBy('p.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search products by name (with translations)
     */
    public function searchByName(string $query, string $locale = 'fr'): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->leftJoin('t.language', 'l')
            ->andWhere('p.isActive = :active')
            ->andWhere('l.code = :locale')
            ->andWhere('t.name LIKE :query OR t.description LIKE :query')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products with filters
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true);

        if (isset($filters['category'])) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (isset($filters['brand'])) {
            $qb->andWhere('p.brand = :brand')
               ->setParameter('brand', $filters['brand']);
        }

        if (isset($filters['minPrice'])) {
            $qb->andWhere('p.price >= :minPrice')
               ->setParameter('minPrice', $filters['minPrice']);
        }

        if (isset($filters['maxPrice'])) {
            $qb->andWhere('p.price <= :maxPrice')
               ->setParameter('maxPrice', $filters['maxPrice']);
        }

        if (isset($filters['inStock']) && $filters['inStock']) {
            $qb->andWhere('(p.trackStock = false OR p.stockQuantity > 0)');
        }

        if (isset($filters['sort'])) {
            switch ($filters['sort']) {
                case 'price_asc':
                    $qb->orderBy('p.price', 'ASC');
                    break;
                case 'price_desc':
                    $qb->orderBy('p.price', 'DESC');
                    break;
                case 'newest':
                    $qb->orderBy('p.createdAt', 'DESC');
                    break;
                case 'oldest':
                    $qb->orderBy('p.createdAt', 'ASC');
                    break;
                default:
                    $qb->orderBy('p.createdAt', 'DESC');
            }
        } else {
            $qb->orderBy('p.createdAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get products count by category
     */
    public function getCountByCategory(): array
    {
        return $this->createQueryBuilder('p')
            ->select('c.id, COUNT(p.id) as count')
            ->leftJoin('p.category', 'c')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get related products
     */
    public function findRelatedProducts(Product $product, int $limit = 4): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.id != :productId')
            ->setParameter('active', true)
            ->setParameter('productId', $product->getId())
            ->setMaxResults($limit);

        // Try to find products in the same category first
        if ($product->getCategory()) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $product->getCategory());
        }

        $results = $qb->getQuery()->getResult();

        // If not enough products in same category, find by brand
        if (count($results) < $limit && $product->getBrand()) {
            $remaining = $limit - count($results);
            $brandProducts = $this->createQueryBuilder('p')
                ->andWhere('p.isActive = :active')
                ->andWhere('p.id != :productId')
                ->andWhere('p.brand = :brand')
                ->setParameter('active', true)
                ->setParameter('productId', $product->getId())
                ->setParameter('brand', $product->getBrand())
                ->setMaxResults($remaining)
                ->getQuery()
                ->getResult();

            $results = array_merge($results, $brandProducts);
        }

        return $results;
    }

    public function save(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}