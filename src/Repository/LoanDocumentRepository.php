<?php

namespace App\Repository;

use App\Entity\LoanDocument;
use App\Entity\LoanApplication;
use App\Enum\DocumentType;
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

    /**
     * Find documents by loan application
     */
    public function findByLoanApplication(LoanApplication $loanApplication): array
    {
        return $this->createQueryBuilder('ld')
            ->where('ld.loanApplication = :loanApplication')
            ->andWhere('ld.isActive = :active')
            ->setParameter('loanApplication', $loanApplication)
            ->setParameter('active', true)
            ->orderBy('ld.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find documents by type
     */
    public function findByDocumentType(DocumentType $documentType): array
    {
        return $this->createQueryBuilder('ld')
            ->where('ld.documentType = :documentType')
            ->andWhere('ld.isActive = :active')
            ->setParameter('documentType', $documentType)
            ->setParameter('active', true)
            ->orderBy('ld.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unverified documents
     */
    public function findUnverified(): array
    {
        return $this->createQueryBuilder('ld')
            ->where('ld.isVerified = :verified')
            ->andWhere('ld.isActive = :active')
            ->setParameter('verified', false)
            ->setParameter('active', true)
            ->orderBy('ld.uploadedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count documents by verification status
     */
    public function countByVerificationStatus(): array
    {
        $result = $this->createQueryBuilder('ld')
            ->select('ld.isVerified, COUNT(ld.id) as count')
            ->where('ld.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('ld.isVerified')
            ->getQuery()
            ->getArrayResult();
        
        $counts = ['verified' => 0, 'unverified' => 0];
        foreach ($result as $row) {
            $key = $row['isVerified'] ? 'verified' : 'unverified';
            $counts[$key] = $row['count'];
        }
        
        return $counts;
    }
}