<?php

namespace App\Repository;

use App\Entity\ProductImage;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductImage>
 */
class ProductImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductImage::class);
    }

    /**
     * Find images by product
     */
    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('pi')
            ->andWhere('pi.product = :product')
            ->setParameter('product', $product)
            ->orderBy('pi.isPrimary', 'DESC')
            ->addOrderBy('pi.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find primary image for product
     */
    public function findPrimaryByProduct(Product $product): ?ProductImage
    {
        return $this->createQueryBuilder('pi')
            ->andWhere('pi.product = :product')
            ->andWhere('pi.isPrimary = :primary')
            ->setParameter('product', $product)
            ->setParameter('primary', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find first image if no primary set
     */
    public function findFirstByProduct(Product $product): ?ProductImage
    {
        return $this->createQueryBuilder('pi')
            ->andWhere('pi.product = :product')
            ->setParameter('product', $product)
            ->orderBy('pi.sortOrder', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
