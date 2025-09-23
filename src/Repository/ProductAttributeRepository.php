<?php

namespace App\Repository;

use App\Entity\ProductAttribute;
use App\Entity\Product;
use App\Entity\Attribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductAttribute>
 */
class ProductAttributeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductAttribute::class);
    }

    /**
     * Find attributes by product
     */
    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.product = :product')
            ->setParameter('product', $product)
            ->orderBy('pa.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products by attribute value
     */
    public function findProductsByAttributeValue(int $attributeValueId): array
    {
        return $this->createQueryBuilder('pa')
            ->select('p')
            ->join('pa.product', 'p')
            ->andWhere('pa.attributeValue = :attributeValue')
            ->andWhere('p.isActive = :active')
            ->setParameter('attributeValue', $attributeValueId)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get unique attribute values used by products
     */
    public function getUsedAttributeValues(Attribute $attribute): array
    {
        return $this->createQueryBuilder('pa')
            ->select('DISTINCT av')
            ->join('pa.attributeValue', 'av')
            ->join('pa.product', 'p')
            ->andWhere('av.attribute = :attribute')
            ->andWhere('p.isActive = :active')
            ->setParameter('attribute', $attribute)
            ->setParameter('active', true)
            ->orderBy('av.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
