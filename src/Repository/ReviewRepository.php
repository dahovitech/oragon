<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * Find approved reviews for a product
     */
    public function findApprovedByProduct($product, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.product = :product')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('product', $product)
            ->setParameter('approved', true)
            ->orderBy('r.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get average rating for a product
     */
    public function getAverageRatingForProduct($product): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) as avgRating')
            ->andWhere('r.product = :product')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('product', $product)
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round((float)$result, 1) : 0.0;
    }

    public function save(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}