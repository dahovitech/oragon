<?php

namespace App\Repository;

use App\Entity\VerificationDocument;
use App\Entity\AccountVerification;
use App\Enum\DocumentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VerificationDocument>
 */
class VerificationDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VerificationDocument::class);
    }

    /**
     * Find documents by account verification
     */
    public function findByAccountVerification(AccountVerification $accountVerification): array
    {
        return $this->createQueryBuilder('vd')
            ->where('vd.accountVerification = :accountVerification')
            ->andWhere('vd.isActive = :active')
            ->setParameter('accountVerification', $accountVerification)
            ->setParameter('active', true)
            ->orderBy('vd.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find documents by type
     */
    public function findByDocumentType(DocumentType $documentType): array
    {
        return $this->createQueryBuilder('vd')
            ->where('vd.documentType = :documentType')
            ->andWhere('vd.isActive = :active')
            ->setParameter('documentType', $documentType)
            ->setParameter('active', true)
            ->orderBy('vd.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unverified documents
     */
    public function findUnverified(): array
    {
        return $this->createQueryBuilder('vd')
            ->where('vd.isVerified = :verified')
            ->andWhere('vd.isActive = :active')
            ->setParameter('verified', false)
            ->setParameter('active', true)
            ->orderBy('vd.uploadedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}