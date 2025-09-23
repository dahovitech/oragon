<?php

namespace App\Bundle\BlogBundle\Repository;

use App\Bundle\BlogBundle\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function save(Tag $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Tag $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find tag by slug
     */
    public function findBySlug(string $slug): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get popular tags (by post count)
     */
    public function findPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.posts', 'p')
            ->addSelect('COUNT(p.id) as postCount')
            ->groupBy('t.id')
            ->orderBy('postCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search tags by name
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}