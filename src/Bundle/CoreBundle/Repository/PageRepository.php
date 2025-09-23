<?php

namespace App\Bundle\CoreBundle\Repository;

use App\Bundle\CoreBundle\Entity\Page;
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
     * Find active pages
     */
    public function findActivePages(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the homepage
     */
    public function findHomepage(): ?Page
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isHomepage = :homepage')
            ->andWhere('p.isActive = :active')
            ->setParameter('homepage', true)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find a page by slug
     */
    public function findBySlug(string $slug): ?Page
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Search pages by title or content
     */
    public function searchPages(string $query): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.title LIKE :query OR p.content LIKE :query')
            ->andWhere('p.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('p.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pages with pagination
     */
    public function findWithPagination(int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('p')
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.title', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total pages
     */
    public function countTotal(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active pages
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find recent pages
     */
    public function findRecentPages(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pages by template
     */
    public function findByTemplate(string $template): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.template = :template')
            ->andWhere('p.isActive = :active')
            ->setParameter('template', $template)
            ->setParameter('active', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get pages statistics
     */
    public function getStatistics(): array
    {
        $total = $this->countTotal();
        $active = $this->countActive();
        $inactive = $total - $active;

        $recent = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.createdAt >= :date')
            ->setParameter('date', new \DateTimeImmutable('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'recent' => $recent,
        ];
    }

    /**
     * Check if slug exists (excluding a specific page ID)
     */
    public function isSlugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Find next sort order
     */
    public function getNextSortOrder(): int
    {
        $result = $this->createQueryBuilder('p')
            ->select('MAX(p.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }
}