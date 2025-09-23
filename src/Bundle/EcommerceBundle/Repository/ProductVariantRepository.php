<?php

namespace App\Bundle\EcommerceBundle\Repository;

use App\Bundle\EcommerceBundle\Entity\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductVariant>
 *
 * @method ProductVariant|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductVariant|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductVariant[]    findAll()
 * @method ProductVariant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariant::class);
    }

    public function save(ProductVariant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductVariant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find active variants by product
     */
    public function findActiveByProduct($product): array
    {
        return $this->createQueryBuilder('pv')
            ->where('pv.product = :product')
            ->andWhere('pv.isActive = :active')
            ->setParameter('product', $product)
            ->setParameter('active', true)
            ->orderBy('pv.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find variant by SKU
     */
    public function findOneBySku(string $sku): ?ProductVariant
    {
        return $this->createQueryBuilder('pv')
            ->where('pv.sku = :sku')
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find variants with low stock
     */
    public function findWithLowStock(int $threshold = 5): array
    {
        return $this->createQueryBuilder('pv')
            ->join('pv.product', 'p')
            ->where('pv.isActive = :active')
            ->andWhere('p.isActive = :productActive')
            ->andWhere('pv.stock <= :threshold')
            ->setParameter('active', true)
            ->setParameter('productActive', true)
            ->setParameter('threshold', $threshold)
            ->orderBy('pv.stock', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find variants in stock
     */
    public function findInStockByProduct($product): array
    {
        return $this->createQueryBuilder('pv')
            ->where('pv.product = :product')
            ->andWhere('pv.isActive = :active')
            ->andWhere('pv.stock > 0')
            ->setParameter('product', $product)
            ->setParameter('active', true)
            ->orderBy('pv.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get total stock for all variants of a product
     */
    public function getTotalStockByProduct($product): int
    {
        $result = $this->createQueryBuilder('pv')
            ->select('SUM(pv.stock)')
            ->where('pv.product = :product')
            ->andWhere('pv.isActive = :active')
            ->setParameter('product', $product)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int)$result : 0;
    }
}