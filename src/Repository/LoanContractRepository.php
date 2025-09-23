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

    /**
     * Find contracts by user (through loan application)
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('lc')
            ->join('lc.loanApplication', 'la')
            ->where('la.user = :user')
            ->andWhere('lc.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->orderBy('lc.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active contracts
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('lc')
            ->where('lc.isActive = :active')
            ->andWhere('lc.signedAt IS NOT NULL')
            ->andWhere('lc.endDate > :now')
            ->setParameter('active', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('lc.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find contracts expiring soon
     */
    public function findExpiringSoon(int $days = 30): array
    {
        $date = new \DateTime("+{$days} days");
        
        return $this->createQueryBuilder('lc')
            ->where('lc.isActive = :active')
            ->andWhere('lc.signedAt IS NOT NULL')
            ->andWhere('lc.endDate BETWEEN :now AND :date')
            ->setParameter('active', true)
            ->setParameter('now', new \DateTime())
            ->setParameter('date', $date)
            ->orderBy('lc.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unsigned contracts
     */
    public function findUnsigned(): array
    {
        return $this->createQueryBuilder('lc')
            ->where('lc.isActive = :active')
            ->andWhere('lc.signedAt IS NULL')
            ->setParameter('active', true)
            ->orderBy('lc.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get contract statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('lc');
        
        return [
            'total' => $qb->select('COUNT(lc.id)')->where('lc.isActive = :active')->setParameter('active', true)->getQuery()->getSingleScalarResult(),
            'signed' => $qb->select('COUNT(lc.id)')->where('lc.isActive = :active AND lc.signedAt IS NOT NULL')->setParameter('active', true)->getQuery()->getSingleScalarResult(),
            'active' => count($this->findActive()),
            'total_amount' => $qb->select('SUM(lc.originalAmount)')->where('lc.isActive = :active AND lc.signedAt IS NOT NULL')->setParameter('active', true)->getQuery()->getSingleScalarResult(),
        ];
    }
}