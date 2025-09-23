<?php

namespace App\Bundle\CoreBundle\Repository;

use App\Bundle\CoreBundle\Entity\Category;
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
    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
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
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find categories by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.type = :type')
            ->andWhere('c.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find category by slug
     */
    public function findBySlug(string $slug): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.slug = :slug')
            ->andWhere('c.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find children of a category
     */
    public function findChildren(Category $parent): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent = :parent')
            ->andWhere('c.isActive = :active')
            ->setParameter('parent', $parent)
            ->setParameter('active', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find categories tree structure
     */
    public function findTree(?string $type = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        if ($type) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $type);
        }

        $categories = $qb->getQuery()->getResult();

        return $this->buildTree($categories);
    }

    /**
     * Build hierarchical tree from flat array
     */
    private function buildTree(array $categories, ?Category $parent = null): array
    {
        $tree = [];

        foreach ($categories as $category) {
            if ($category->getParent() === $parent) {
                $children = $this->buildTree($categories, $category);
                if (!empty($children)) {
                    $tree[] = [
                        'category' => $category,
                        'children' => $children
                    ];
                } else {
                    $tree[] = [
                        'category' => $category,
                        'children' => []
                    ];
                }
            }
        }

        return $tree;
    }

    /**
     * Search categories
     */
    public function searchCategories(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.name LIKE :query OR c.description LIKE :query')
            ->andWhere('c.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get categories statistics
     */
    public function getStatistics(): array
    {
        $total = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $byType = $this->createQueryBuilder('c')
            ->select('c.type, COUNT(c.id) as count')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.type')
            ->getQuery()
            ->getResult();

        $roots = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.parent IS NULL')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'roots' => $roots,
            'byType' => $byType,
        ];
    }

    /**
     * Check if slug exists (excluding a specific category ID)
     */
    public function isSlugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Find next sort order for a given parent
     */
    public function getNextSortOrder(?Category $parent = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('MAX(c.sortOrder)');

        if ($parent) {
            $qb->andWhere('c.parent = :parent')
                ->setParameter('parent', $parent);
        } else {
            $qb->andWhere('c.parent IS NULL');
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * Find categories with their children count
     */
    public function findWithChildrenCount(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c, COUNT(children.id) as childrenCount')
            ->leftJoin('c.children', 'children')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all descendants of a category
     */
    public function findAllDescendants(Category $category): array
    {
        $descendants = [];
        $this->collectDescendants($category, $descendants);
        return $descendants;
    }

    /**
     * Recursively collect all descendants
     */
    private function collectDescendants(Category $category, array &$descendants): void
    {
        $children = $this->findChildren($category);
        foreach ($children as $child) {
            $descendants[] = $child;
            $this->collectDescendants($child, $descendants);
        }
    }
}