<?php

namespace App\Bundle\BlogBundle\Repository;

use App\Bundle\BlogBundle\Entity\Category;
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

    public function save(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find categories with post counts
     */
    public function findWithPostCounts(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c, COUNT(p.id) as postCount')
            ->leftJoin('c.posts', 'p')
            ->andWhere('p.status = :status OR p.status IS NULL')
            ->setParameter('status', 'published')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
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
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active categories (with at least one published post)
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.posts', 'p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'published')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}