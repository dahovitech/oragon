<?php

namespace App\Repository;

use App\Entity\VerificationDocument;
use App\Entity\AccountVerification;
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

    public function findByAccountVerification(AccountVerification $accountVerification): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.accountVerification = :accountVerification')
            ->setParameter('accountVerification', $accountVerification)
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

    public function findByDocumentType(string $documentType): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.documentType = :type')
            ->setParameter('type', $documentType)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}