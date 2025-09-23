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

    /**
     * Find active loan types ordered by sort order
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('lt.sortOrder', 'ASC')
            ->addOrderBy('lt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find loan types available for a specific account type
     */
    public function findForAccountType(string $accountType): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.isActive = :active')
            ->andWhere('JSON_CONTAINS(lt.allowedAccountTypes, :accountType) = 1')
            ->setParameter('active', true)
            ->setParameter('accountType', json_encode($accountType))
            ->orderBy('lt.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find loan types by amount range
     */
    public function findByAmountRange(float $minAmount, float $maxAmount): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.isActive = :active')
            ->andWhere('lt.minAmount <= :maxAmount')
            ->andWhere('lt.maxAmount >= :minAmount')
            ->setParameter('active', true)
            ->setParameter('minAmount', $minAmount)
            ->setParameter('maxAmount', $maxAmount)
            ->orderBy('lt.baseInterestRate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search loan types by title (with translations)
     */
    public function searchByTitle(string $query, string $locale = 'fr'): array
    {
        return $this->createQueryBuilder('lt')
            ->join('lt.translations', 't')
            ->join('t.language', 'l')
            ->where('lt.isActive = :active')
            ->andWhere('l.code = :locale')
            ->andWhere('t.title LIKE :query')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}