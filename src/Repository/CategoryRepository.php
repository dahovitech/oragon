<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Find active categories
     */
    public function findActiveCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find root categories (no parent)
     */
    public function findRootCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent IS NULL')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find categories by parent
     */
    public function findByParent(Category $parent): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent = :parent')
            ->andWhere('c.isActive = :active')
            ->setParameter('parent', $parent)
            ->setParameter('active', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get categories tree structure
     */
    public function getCategoriesTree(): array
    {
        $categories = $this->findActiveCategories();
        return $this->buildTree($categories);
    }

    /**
     * Build tree structure from flat array
     */
    private function buildTree(array $categories, ?Category $parent = null): array
    {
        $tree = [];
        foreach ($categories as $category) {
            if ($category->getParent() === $parent) {
                $children = $this->buildTree($categories, $category);
                if ($children) {
                    $tree[] = [
                        'category' => $category,
                        'children' => $children
                    ];
                } else {
                    $tree[] = ['category' => $category, 'children' => []];
                }
            }
        }
        return $tree;
    }

    /**
     * Get category with products count
     */
    public function getCategoriesWithProductsCount(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c, COUNT(p.id) as productsCount')
            ->leftJoin('c.products', 'p', 'WITH', 'p.isActive = true')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
