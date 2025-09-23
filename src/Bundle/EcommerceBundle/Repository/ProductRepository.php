<?php

namespace App\Bundle\EcommerceBundle\Repository;

use App\Bundle\EcommerceBundle\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 *
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
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

    /**
     * Find active products with optional filtering
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find featured products
     */
    public function findFeatured(int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->andWhere('p.isFeatured = :featured')
            ->setParameter('active', true)
            ->setParameter('featured', true)
            ->orderBy('p.salesCount', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search products by name or description
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->andWhere('p.name LIKE :query OR p.description LIKE :query OR p.shortDescription LIKE :query')
            ->setParameter('active', true)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find products by category
     */
    public function findByCategory($category, int $limit = null, int $offset = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->andWhere('p.category = :category')
            ->setParameter('active', true)
            ->setParameter('category', $category)
            ->orderBy('p.name', 'ASC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find products with low stock
     */
    public function findWithLowStock(int $threshold = 5): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->andWhere('p.trackStock = :trackStock')
            ->andWhere('p.stock <= :threshold')
            ->setParameter('active', true)
            ->setParameter('trackStock', true)
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find products by price range
     */
    public function findByPriceRange(float $minPrice = null, float $maxPrice = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->setParameter('active', true);

        if ($minPrice !== null) {
            $qb->andWhere('CAST(p.price AS DECIMAL(10,2)) >= :minPrice')
               ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice !== null) {
            $qb->andWhere('CAST(p.price AS DECIMAL(10,2)) <= :maxPrice')
               ->setParameter('maxPrice', $maxPrice);
        }

        return $qb->orderBy('CAST(p.price AS DECIMAL(10,2))', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get products with statistics (views, sales)
     */
    public function findWithStats(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p', '(p.viewCount + p.salesCount * 5) as HIDDEN popularity')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('popularity', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find products by slug
     */
    public function findOneBySlug(string $slug): ?Product
    {
        return $this->createQueryBuilder('p')
            ->where('p.slug = :slug')
            ->andWhere('p.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find products by SKU
     */
    public function findOneBySku(string $sku): ?Product
    {
        return $this->createQueryBuilder('p')
            ->where('p.sku = :sku')
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Create a base query builder for filtering products
     */
    public function createFilterQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.featuredImage', 'fi')
            ->where('p.isActive = :active')
            ->setParameter('active', true);
    }

    /**
     * Get products count by filters
     */
    public function countByFilters(array $filters = []): int
    {
        $qb = $this->createFilterQueryBuilder()
            ->select('COUNT(p.id)');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find products with pagination and filters
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createFilterQueryBuilder();

        $this->applyFilters($qb, $filters);

        // Apply sorting
        $sortField = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'ASC';

        switch ($sortField) {
            case 'price':
                $qb->orderBy('CAST(p.price AS DECIMAL(10,2))', $sortOrder);
                break;
            case 'created':
                $qb->orderBy('p.createdAt', $sortOrder);
                break;
            case 'popularity':
                $qb->orderBy('p.viewCount', 'DESC')
                   ->addOrderBy('p.salesCount', 'DESC');
                break;
            default:
                $qb->orderBy('p.name', $sortOrder);
        }

        return $qb->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['category'])) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (isset($filters['min_price'])) {
            $qb->andWhere('CAST(p.price AS DECIMAL(10,2)) >= :minPrice')
               ->setParameter('minPrice', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $qb->andWhere('CAST(p.price AS DECIMAL(10,2)) <= :maxPrice')
               ->setParameter('maxPrice', $filters['max_price']);
        }

        if (isset($filters['featured']) && $filters['featured']) {
            $qb->andWhere('p.isFeatured = :featured')
               ->setParameter('featured', true);
        }

        if (isset($filters['in_stock']) && $filters['in_stock']) {
            $qb->andWhere('(p.trackStock = false OR p.stock > 0)');
        }
    }
}