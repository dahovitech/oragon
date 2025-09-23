<?php

namespace App\Repository;

use App\Entity\ShippingMethod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingMethod>
 */
class ShippingMethodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingMethod::class);
    }

    /**
     * Find active shipping methods
     */
    public function findActiveShippingMethods(): array
    {
        return $this->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
    }

    /**
     * Find shipping methods with translations
     */
    public function findShippingMethodsWithTranslations(): array
    {
        return $this->createQueryBuilder('sm')
            ->leftJoin('sm.translations', 'smt')
            ->leftJoin('smt.language', 'l')
            ->addSelect('smt', 'l')
            ->where('sm.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('sm.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}