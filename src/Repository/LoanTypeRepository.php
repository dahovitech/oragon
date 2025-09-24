<?php

namespace App\Repository;

use App\Entity\LoanType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanType>
 */
class LoanTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanType::class);
    }

    public function findActiveTypes(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.isActive = :val')
            ->setParameter('val', true)
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByAccountType(string $accountType): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.isActive = :active')
            ->andWhere('JSON_CONTAINS(l.allowedAccountTypes, :accountType) = 1')
            ->setParameter('active', true)
            ->setParameter('accountType', json_encode($accountType))
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}