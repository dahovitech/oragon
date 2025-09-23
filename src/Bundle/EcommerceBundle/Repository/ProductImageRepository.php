<?php

namespace App\Bundle\EcommerceBundle\Repository;

use App\Bundle\EcommerceBundle\Entity\ProductImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductImage>
 *
 * @method ProductImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductImage[]    findAll()
 * @method ProductImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductImage::class);
    }

    public function save(ProductImage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductImage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find images by product ordered by position
     */
    public function findByProductOrdered($product): array
    {
        return $this->createQueryBuilder('pi')
            ->where('pi.product = :product')
            ->setParameter('product', $product)
            ->orderBy('pi.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get the next position for a product
     */
    public function getNextPosition($product): int
    {
        $result = $this->createQueryBuilder('pi')
            ->select('MAX(pi.position)')
            ->where('pi.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result === null) ? 0 : $result + 1;
    }

    /**
     * Reorder positions after deletion
     */
    public function reorderPositions($product, int $deletedPosition): void
    {
        $this->createQueryBuilder('pi')
            ->update()
            ->set('pi.position', 'pi.position - 1')
            ->where('pi.product = :product')
            ->andWhere('pi.position > :position')
            ->setParameter('product', $product)
            ->setParameter('position', $deletedPosition)
            ->getQuery()
            ->execute();
    }
}