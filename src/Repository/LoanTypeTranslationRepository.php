<?php

namespace App\Repository;

use App\Entity\LoanTypeTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanTypeTranslation>
 */
class LoanTypeTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanTypeTranslation::class);
    }

    /**
     * Find translation by loan type and language code
     */
    public function findByLoanTypeAndLanguage(int $loanTypeId, string $languageCode): ?LoanTypeTranslation
    {
        return $this->createQueryBuilder('ltt')
            ->join('ltt.language', 'l')
            ->where('ltt.loanType = :loanType')
            ->andWhere('l.code = :languageCode')
            ->setParameter('loanType', $loanTypeId)
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find incomplete translations
     */
    public function findIncomplete(): array
    {
        return $this->createQueryBuilder('ltt')
            ->where('ltt.title IS NULL OR ltt.title = :empty')
            ->orWhere('ltt.description IS NULL OR ltt.description = :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();
    }
}