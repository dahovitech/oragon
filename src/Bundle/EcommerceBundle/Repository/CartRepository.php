<?php

namespace App\Bundle\EcommerceBundle\Repository;

use App\Bundle\EcommerceBundle\Entity\Cart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cart>
 *
 * @method Cart|null find($id, $lockMode = null, $lockVersion = null)
 * @method Cart|null findOneBy(array $criteria, array $orderBy = null)
 * @method Cart[]    findAll()
 * @method Cart[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function save(Cart $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Cart $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find or create cart for user
     */
    public function findOrCreateForUser($user): Cart
    {
        $cart = $this->findOneBy(['user' => $user]);

        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->save($cart, true);
        }

        return $cart;
    }

    /**
     * Find or create cart for session
     */
    public function findOrCreateForSession(string $sessionId): Cart
    {
        $cart = $this->findOneBy(['sessionId' => $sessionId, 'user' => null]);

        if (!$cart) {
            $cart = new Cart();
            $cart->setSessionId($sessionId);
            $this->save($cart, true);
        }

        return $cart;
    }

    /**
     * Merge session cart with user cart
     */
    public function mergeSessionCartWithUser(string $sessionId, $user): Cart
    {
        $sessionCart = $this->findOneBy(['sessionId' => $sessionId, 'user' => null]);
        $userCart = $this->findOneBy(['user' => $user]);

        if (!$sessionCart) {
            return $userCart ?: $this->findOrCreateForUser($user);
        }

        if (!$userCart) {
            // Convert session cart to user cart
            $sessionCart->setUser($user);
            $sessionCart->setSessionId(null);
            $this->save($sessionCart, true);
            return $sessionCart;
        }

        // Merge session cart items into user cart
        foreach ($sessionCart->getItems() as $sessionItem) {
            $userCart->addItem($sessionItem);
        }

        // Remove session cart
        $this->remove($sessionCart, true);

        return $userCart;
    }

    /**
     * Find abandoned carts
     */
    public function findAbandoned(\DateTimeInterface $beforeDate): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.lastActivity < :date')
            ->andWhere('SIZE(c.items) > 0')
            ->setParameter('date', $beforeDate)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find carts with specific products
     */
    public function findWithProduct($product): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.items', 'ci')
            ->where('ci.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Clean up old empty carts
     */
    public function cleanupEmptyCarts(\DateTimeInterface $beforeDate): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.updatedAt < :date')
            ->andWhere('SIZE(c.items) = 0')
            ->setParameter('date', $beforeDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Get cart statistics
     */
    public function getStatistics(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('
                COUNT(c.id) as totalCarts,
                COUNT(CASE WHEN SIZE(c.items) > 0 THEN 1 END) as cartsWithItems,
                AVG(CAST(c.total AS DECIMAL(10,2))) as averageValue,
                SUM(CAST(c.total AS DECIMAL(10,2))) as totalValue
            ')
            ->getQuery()
            ->getSingleResult();

        return [
            'total_carts' => (int)$result['totalCarts'],
            'carts_with_items' => (int)$result['cartsWithItems'],
            'average_value' => round((float)($result['averageValue'] ?? 0), 2),
            'total_value' => round((float)($result['totalValue'] ?? 0), 2),
        ];
    }
}