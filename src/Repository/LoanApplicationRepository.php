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

    /**
     * Count total loan applications
     */
    public function countTotalLoans(): int
    {
        return $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count pending loan applications
     */
    public function countPendingLoans(): int
    {
        return $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->andWhere('la.status IN (:statuses)')
            ->setParameter('statuses', [
                LoanApplicationStatus::SUBMITTED,
                LoanApplicationStatus::UNDER_REVIEW,
                LoanApplicationStatus::PENDING_DOCUMENTS
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count approved loan applications
     */
    public function countApprovedLoans(): int
    {
        return $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->andWhere('la.status = :status')
            ->setParameter('status', LoanApplicationStatus::APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count rejected loan applications
     */
    public function countRejectedLoans(): int
    {
        return $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->andWhere('la.status = :status')
            ->setParameter('status', LoanApplicationStatus::REJECTED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get total loan amount (all applications)
     */
    public function getTotalLoanAmount(): float
    {
        $result = $this->createQueryBuilder('la')
            ->select('SUM(la.requestedAmount)')
            ->getQuery()
            ->getSingleScalarResult();
            
        return $result ?? 0.0;
    }

    /**
     * Get approved loan amount
     */
    public function getApprovedLoanAmount(): float
    {
        $result = $this->createQueryBuilder('la')
            ->select('SUM(la.requestedAmount)')
            ->andWhere('la.status = :status')
            ->setParameter('status', LoanApplicationStatus::APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
            
        return $result ?? 0.0;
    }

    /**
     * Get pending loan amount
     */
    public function getPendingLoanAmount(): float
    {
        $result = $this->createQueryBuilder('la')
            ->select('SUM(la.requestedAmount)')
            ->andWhere('la.status IN (:statuses)')
            ->setParameter('statuses', [
                LoanApplicationStatus::SUBMITTED,
                LoanApplicationStatus::UNDER_REVIEW,
                LoanApplicationStatus::PENDING_DOCUMENTS
            ])
            ->getQuery()
            ->getSingleScalarResult();
            
        return $result ?? 0.0;
    }

    /**
     * Get average loan amount
     */
    public function getAverageLoanAmount(): float
    {
        $result = $this->createQueryBuilder('la')
            ->select('AVG(la.requestedAmount)')
            ->getQuery()
            ->getSingleScalarResult();
            
        return $result ?? 0.0;
    }

    /**
     * Get loan applications this month
     */
    public function getLoansThisMonth(): array
    {
        $firstDayOfMonth = new \DateTime('first day of this month 00:00:00');
        
        return $this->createQueryBuilder('la')
            ->andWhere('la.createdAt >= :date')
            ->setParameter('date', $firstDayOfMonth)
            ->orderBy('la.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count loan applications this month
     */
    public function countLoansThisMonth(): int
    {
        $firstDayOfMonth = new \DateTime('first day of this month 00:00:00');
        
        return $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->andWhere('la.createdAt >= :date')
            ->setParameter('date', $firstDayOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get loan applications trend (last 12 months)
     */
    public function getLoanApplicationsTrend(): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        
        return $qb->select('DATE_FORMAT(la.createdAt, \'%Y-%m\') as month, COUNT(la.id) as count, SUM(la.requestedAmount) as total_amount')
            ->from(LoanApplication::class, 'la')
            ->andWhere('la.createdAt >= :date')
            ->setParameter('date', new \DateTime('-12 months'))
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Get loan applications by day (last 30 days)
     */
    public function getLoanApplicationsByDay(): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        
        return $qb->select('DATE(la.createdAt) as date, COUNT(la.id) as count, SUM(la.requestedAmount) as total_amount')
            ->from(LoanApplication::class, 'la')
            ->andWhere('la.createdAt >= :date')
            ->setParameter('date', new \DateTime('-30 days'))
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Get recent loan applications for dashboard
     */
    public function getRecentLoanApplications(int $limit = 10): array
    {
        return $this->createQueryBuilder('la')
            ->leftJoin('la.user', 'u')
            ->addSelect('u')
            ->orderBy('la.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get loan approval rate
     */
    public function getLoanApprovalRate(): array
    {
        $total = $this->countTotalLoans();
        $approved = $this->countApprovedLoans();
        $rejected = $this->countRejectedLoans();
        
        return [
            'total' => $total,
            'approved' => $approved,
            'rejected' => $rejected,
            'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
            'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get comprehensive loan statistics
     */
    public function getComprehensiveStatistics(): array
    {
        $statusCounts = $this->countByStatus();
        
        return [
            'total_applications' => $this->countTotalLoans(),
            'pending_applications' => $this->countPendingLoans(),
            'approved_applications' => $this->countApprovedLoans(),
            'rejected_applications' => $this->countRejectedLoans(),
            'total_loan_amount' => $this->getTotalLoanAmount(),
            'approved_loan_amount' => $this->getApprovedLoanAmount(),
            'pending_loan_amount' => $this->getPendingLoanAmount(),
            'average_loan_amount' => $this->getAverageLoanAmount(),
            'applications_this_month' => $this->countLoansThisMonth(),
            'status_breakdown' => $statusCounts,
            'approval_rate' => $this->getLoanApprovalRate(),
        ];
    }

    /**
     * Get loans by status with user information
     */
    public function findByStatusWithUser(LoanApplicationStatus $status, int $limit = 20): array
    {
        return $this->createQueryBuilder('la')
            ->leftJoin('la.user', 'u')
            ->addSelect('u')
            ->andWhere('la.status = :status')
            ->setParameter('status', $status)
            ->orderBy('la.submittedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}