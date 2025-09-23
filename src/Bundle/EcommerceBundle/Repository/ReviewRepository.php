<?php

namespace App\Bundle\EcommerceBundle\Repository;

use App\Bundle\EcommerceBundle\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 *
 * @method Review|null find($id, $lockMode = null, $lockVersion = null)
 * @method Review|null findOneBy(array $criteria, array $orderBy = null)
 * @method Review[]    findAll()
 * @method Review[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
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

    /**
     * Find approved reviews for a product
     */
    public function findApprovedByProduct($product, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->where('r.product = :product')
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
     * Find pending reviews for admin
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->join('r.product', 'p')
            ->where('r.isApproved = :approved')
            ->setParameter('approved', false)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get average rating for a product
     */
    public function getAverageRating($product): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->where('r.product = :product')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('product', $product)
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round((float)$result, 2) : null;
    }

    /**
     * Get review count for a product
     */
    public function getReviewCount($product): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.product = :product')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('product', $product)
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Get rating distribution for a product
     */
    public function getRatingDistribution($product): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.rating, COUNT(r.id) as count')
            ->where('r.product = :product')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('product', $product)
            ->setParameter('approved', true)
            ->groupBy('r.rating')
            ->orderBy('r.rating', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        // Initialize all ratings with 0 count
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        
        foreach ($results as $result) {
            $distribution[$result['rating']] = (int)$result['count'];
        }

        return $distribution;
    }

    /**
     * Find reviews by user
     */
    public function findByUser($user, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.product', 'p')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find reviews by rating
     */
    public function findByRating($product, int $rating): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->where('r.product = :product')
            ->andWhere('r.rating = :rating')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('product', $product)
            ->setParameter('rating', $rating)
            ->setParameter('approved', true)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Check if user has already reviewed a product
     */
    public function hasUserReviewedProduct($user, $product): bool
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :user')
            ->andWhere('r.product = :product')
            ->setParameter('user', $user)
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Find helpful reviews for a product
     */
    public function findMostHelpful($product, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->where('r.product = :product')
            ->andWhere('r.isApproved = :approved')
            ->andWhere('r.totalVotes > 0')
            ->setParameter('product', $product)
            ->setParameter('approved', true)
            ->orderBy('(r.helpfulVotes / r.totalVotes)', 'DESC')
            ->addOrderBy('r.totalVotes', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }
}