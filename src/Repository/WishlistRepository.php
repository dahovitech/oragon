<?php

namespace App\Repository;

use App\Entity\Wishlist;
use App\Entity\User;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Wishlist>
 */
class WishlistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wishlist::class);
    }

    /**
     * Find wishlist items by user
     */
    public function findByUser(User $user, array $orderBy = ['createdAt' => 'DESC']): array
    {
        return $this->findBy(['user' => $user], $orderBy);
    }

    /**
     * Find wishlist item by user and product
     */
    public function findByUserAndProduct(User $user, Product $product): ?Wishlist
    {
        return $this->findOneBy(['user' => $user, 'product' => $product]);
    }

    /**
     * Check if product is in user's wishlist
     */
    public function isInWishlist(User $user, Product $product): bool
    {
        return $this->findByUserAndProduct($user, $product) !== null;
    }

    /**
     * Add product to user's wishlist
     */
    public function addToWishlist(User $user, Product $product): Wishlist
    {
        $existingItem = $this->findByUserAndProduct($user, $product);
        
        if ($existingItem) {
            return $existingItem;
        }

        $wishlistItem = new Wishlist();
        $wishlistItem->setUser($user);
        $wishlistItem->setProduct($product);

        $this->getEntityManager()->persist($wishlistItem);
        $this->getEntityManager()->flush();

        return $wishlistItem;
    }

    /**
     * Remove product from user's wishlist
     */
    public function removeFromWishlist(User $user, Product $product): bool
    {
        $wishlistItem = $this->findByUserAndProduct($user, $product);
        
        if (!$wishlistItem) {
            return false;
        }

        $this->getEntityManager()->remove($wishlistItem);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Get wishlist items with products for user
     */
    public function findByUserWithProducts(User $user): array
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.product', 'p')
            ->leftJoin('p.translations', 'pt')
            ->leftJoin('pt.language', 'l')
            ->addSelect('p', 'pt', 'l')
            ->where('w.user = :user')
            ->andWhere('p.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get wishlist count for user
     */
    public function getWishlistCount(User $user): int
    {
        return $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->leftJoin('w.product', 'p')
            ->where('w.user = :user')
            ->andWhere('p.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get most wishlisted products
     */
    public function getMostWishlistedProducts(int $limit = 10): array
    {
        return $this->createQueryBuilder('w')
            ->select('p', 'COUNT(w.id) as wishlistCount')
            ->leftJoin('w.product', 'p')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('p.id')
            ->orderBy('wishlistCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Wishlist[] Returns an array of Wishlist objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('w.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Wishlist
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}