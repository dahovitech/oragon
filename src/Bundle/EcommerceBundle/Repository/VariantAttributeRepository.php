<?php

namespace App\Bundle\EcommerceBundle\Repository;

use App\Bundle\EcommerceBundle\Entity\VariantAttribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VariantAttribute>
 *
 * @method VariantAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method VariantAttribute|null findOneBy(array $criteria, array $orderBy = null)
 * @method VariantAttribute[]    findAll()
 * @method VariantAttribute[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VariantAttributeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VariantAttribute::class);
    }

    public function save(VariantAttribute $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(VariantAttribute $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find attributes by variant ordered by position
     */
    public function findByVariantOrdered($variant): array
    {
        return $this->createQueryBuilder('va')
            ->where('va.variant = :variant')
            ->setParameter('variant', $variant)
            ->orderBy('va.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get all unique attribute names for a product
     */
    public function getAttributeNamesByProduct($product): array
    {
        return $this->createQueryBuilder('va')
            ->select('DISTINCT va.name')
            ->join('va.variant', 'v')
            ->where('v.product = :product')
            ->andWhere('v.isActive = :active')
            ->setParameter('product', $product)
            ->setParameter('active', true)
            ->orderBy('va.name', 'ASC')
            ->getQuery()
            ->getSingleColumnResult()
        ;
    }

    /**
     * Get all unique values for an attribute name within a product
     */
    public function getAttributeValuesByProductAndName($product, string $attributeName): array
    {
        return $this->createQueryBuilder('va')
            ->select('DISTINCT va.value')
            ->join('va.variant', 'v')
            ->where('v.product = :product')
            ->andWhere('v.isActive = :active')
            ->andWhere('va.name = :name')
            ->setParameter('product', $product)
            ->setParameter('active', true)
            ->setParameter('name', $attributeName)
            ->orderBy('va.value', 'ASC')
            ->getQuery()
            ->getSingleColumnResult()
        ;
    }
}