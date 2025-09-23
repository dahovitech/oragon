<?php

namespace App\Repository;

use App\Entity\LoanApplication;
use App\Entity\User;
use App\Enum\LoanApplicationStatus;
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

    /**
     * Find applications by user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('la')
            ->where('la.user = :user')
            ->setParameter('user', $user)
            ->orderBy('la.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find applications by status
     */
    public function findByStatus(LoanApplicationStatus $status): array
    {
        return $this->createQueryBuilder('la')
            ->where('la.status = :status')
            ->setParameter('status', $status)
            ->orderBy('la.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find applications pending review
     */
    public function findPendingReview(): array
    {
        return $this->findByStatus(LoanApplicationStatus::UNDER_REVIEW);
    }

    /**
     * Find applications submitted recently
     */
    public function findRecentSubmissions(int $days = 7): array
    {
        $date = new \DateTime("-{$days} days");
        
        return $this->createQueryBuilder('la')
            ->where('la.submittedAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('la.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count applications by status
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('la')
            ->select('la.status, COUNT(la.id) as count')
            ->groupBy('la.status')
            ->getQuery()
            ->getArrayResult();
        
        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = $row['count'];
        }
        
        return $counts;
    }

    /**
     * Find applications with statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('la');
        
        return [
            'total' => $qb->select('COUNT(la.id)')->getQuery()->getSingleScalarResult(),
            'pending' => $this->countByStatus()[LoanApplicationStatus::SUBMITTED->value] ?? 0,
            'approved' => $this->countByStatus()[LoanApplicationStatus::APPROVED->value] ?? 0,
            'rejected' => $this->countByStatus()[LoanApplicationStatus::REJECTED->value] ?? 0,
            'avg_amount' => $qb->select('AVG(la.requestedAmount)')->getQuery()->getSingleScalarResult(),
        ];
    }
}