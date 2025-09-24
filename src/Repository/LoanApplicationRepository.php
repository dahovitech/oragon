<?php

namespace App\Repository;

use App\Entity\LoanApplication;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanApplication>
 */
class LoanApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanApplication::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.status = :status')
            ->setParameter('status', $status)
            ->orderBy('l.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingApplications(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('statuses', ['SUBMITTED', 'UNDER_REVIEW'])
            ->orderBy('l.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}