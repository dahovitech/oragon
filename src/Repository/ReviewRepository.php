<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\Product;
use App\Entity\User;
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
     * Find approved reviews
     */
    public function findApprovedReviews(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('approved', true)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reviews by product
     */
    public function findByProduct(Product $product, bool $approvedOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.product = :product')
            ->setParameter('product', $product)
            ->orderBy('r.createdAt', 'DESC');

        if ($approvedOnly) {
            $qb->andWhere('r.isApproved = :approved')
               ->setParameter('approved', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find reviews by user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get pending reviews (for admin)
     */
    public function findPendingReviews(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('approved', false)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get average rating for product
     */
    public function getAverageRatingForProduct(Product $product): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) as avgRating')
            ->andWhere('r.product = :product')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('product', $product)
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round((float) $result, 1) : 0.0;
    }

    /**
     * Get rating distribution for product
     */
    public function getRatingDistribution(Product $product): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.rating, COUNT(r.id) as count')
            ->andWhere('r.product = :product')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('product', $product)
            ->setParameter('approved', true)
            ->groupBy('r.rating')
            ->orderBy('r.rating', 'DESC')
            ->getQuery()
            ->getResult();

        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($results as $result) {
            $distribution[$result['rating']] = (int) $result['count'];
        }

        return $distribution;
    }

    /**
     * Get reviews count by rating for product
     */
    public function getReviewsCountByRating(Product $product): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.rating, COUNT(r.id) as count')
            ->andWhere('r.product = :product')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('product', $product)
            ->setParameter('approved', true)
            ->groupBy('r.rating')
            ->orderBy('r.rating', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if user has already reviewed product
     */
    public function hasUserReviewedProduct(User $user, Product $product): bool
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('r.product = :product')
            ->setParameter('user', $user)
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Get recent reviews
     */
    public function findRecentReviews(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('approved', true)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}