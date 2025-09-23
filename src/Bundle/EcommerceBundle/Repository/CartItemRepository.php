<?php

namespace App\Bundle\EcommerceBundle\Repository;

use App\Bundle\EcommerceBundle\Entity\CartItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 *
 * @method CartItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method CartItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method CartItem[]    findAll()
 * @method CartItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    public function save(CartItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CartItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find cart item by cart, product and variant
     */
    public function findByCartAndProduct($cart, $product, $variant = null): ?CartItem
    {
        $qb = $this->createQueryBuilder('ci')
            ->where('ci.cart = :cart')
            ->andWhere('ci.product = :product')
            ->setParameter('cart', $cart)
            ->setParameter('product', $product);

        if ($variant) {
            $qb->andWhere('ci.variant = :variant')
               ->setParameter('variant', $variant);
        } else {
            $qb->andWhere('ci.variant IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find items by cart ordered by creation date
     */
    public function findByCartOrdered($cart): array
    {
        return $this->createQueryBuilder('ci')
            ->join('ci.product', 'p')
            ->leftJoin('ci.variant', 'v')
            ->where('ci.cart = :cart')
            ->setParameter('cart', $cart)
            ->orderBy('ci.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get total quantity for a cart
     */
    public function getTotalQuantityByCart($cart): int
    {
        $result = $this->createQueryBuilder('ci')
            ->select('SUM(ci.quantity)')
            ->where('ci.cart = :cart')
            ->setParameter('cart', $cart)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int)$result : 0;
    }

    /**
     * Get total value for a cart
     */
    public function getTotalValueByCart($cart): string
    {
        $result = $this->createQueryBuilder('ci')
            ->select('SUM(CAST(ci.lineTotal AS DECIMAL(10,2)))')
            ->where('ci.cart = :cart')
            ->setParameter('cart', $cart)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (string)$result : '0.00';
    }

    /**
     * Find items with insufficient stock
     */
    public function findWithInsufficientStock($cart): array
    {
        return $this->createQueryBuilder('ci')
            ->join('ci.product', 'p')
            ->leftJoin('ci.variant', 'v')
            ->where('ci.cart = :cart')
            ->andWhere('
                (ci.variant IS NULL AND p.trackStock = true AND ci.quantity > p.stock) OR
                (ci.variant IS NOT NULL AND ci.quantity > v.stock)
            ')
            ->setParameter('cart', $cart)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find items by product
     */
    public function findByProduct($product): array
    {
        return $this->createQueryBuilder('ci')
            ->join('ci.cart', 'c')
            ->where('ci.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find items by variant
     */
    public function findByVariant($variant): array
    {
        return $this->createQueryBuilder('ci')
            ->join('ci.cart', 'c')
            ->where('ci.variant = :variant')
            ->setParameter('variant', $variant)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Remove items older than specified date
     */
    public function removeOldItems(\DateTimeInterface $beforeDate): int
    {
        return $this->createQueryBuilder('ci')
            ->delete()
            ->where('ci.createdAt < :date')
            ->setParameter('date', $beforeDate)
            ->getQuery()
            ->execute();
    }
}