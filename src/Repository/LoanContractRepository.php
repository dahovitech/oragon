<?php

namespace App\Repository;

use App\Entity\LoanContract;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanContract>
 */
class LoanContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanContract::class);
    }

    public function findActiveContractsByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.loanApplication', 'la')
            ->andWhere('la.user = :user')
            ->andWhere('c.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByContractNumber(string $contractNumber): ?LoanContract
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.contractNumber = :contractNumber')
            ->setParameter('contractNumber', $contractNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }
}