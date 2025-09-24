<?php

namespace App\Repository;

use App\Entity\LoanDocument;
use App\Entity\LoanApplication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanDocument>
 */
class LoanDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanDocument::class);
    }

    public function findByLoanApplication(LoanApplication $loanApplication): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.loanApplication = :loanApplication')
            ->setParameter('loanApplication', $loanApplication)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findUnverifiedDocuments(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.isVerified = :verified')
            ->setParameter('verified', false)
            ->orderBy('d.uploadedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}