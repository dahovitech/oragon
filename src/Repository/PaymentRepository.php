<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\LoanContract;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Find payments by loan contract
     */
    public function findByLoanContract(LoanContract $loanContract): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.loanContract = :loanContract')
            ->setParameter('loanContract', $loanContract)
            ->orderBy('p.paymentNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find payments by user (through loan contract)
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.loanContract', 'lc')
            ->join('lc.loanApplication', 'la')
            ->where('la.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.dueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find overdue payments
     */
    public function findOverdue(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.dueDate < :now')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('now', new \DateTime())
            ->setParameter('statuses', ['PENDING', 'PARTIAL'])
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find payments due in the next X days
     */
    public function findDueSoon(int $days = 7): array
    {
        $endDate = new \DateTime("+{$days} days");
        
        return $this->createQueryBuilder('p')
            ->where('p.dueDate BETWEEN :now AND :endDate')
            ->andWhere('p.status = :status')
            ->setParameter('now', new \DateTime())
            ->setParameter('endDate', $endDate)
            ->setParameter('status', 'PENDING')
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent payments
     */
    public function findRecentPayments(int $days = 30): array
    {
        $date = new \DateTime("-{$days} days");
        
        return $this->createQueryBuilder('p')
            ->where('p.paidAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('p.paidAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count payments by status
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count')
            ->groupBy('p.status')
            ->getQuery()
            ->getArrayResult();
        
        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = $row['count'];
        }
        
        return $counts;
    }

    /**
     * Get payment statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('p');
        
        return [
            'total' => $qb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult(),
            'paid' => $qb->select('COUNT(p.id)')->where('p.status = :status')->setParameter('status', 'PAID')->getQuery()->getSingleScalarResult(),
            'overdue' => count($this->findOverdue()),
            'due_soon' => count($this->findDueSoon()),
            'total_collected' => $qb->select('SUM(p.paidAmount)')->getQuery()->getSingleScalarResult(),
            'avg_payment' => $qb->select('AVG(p.amount)')->getQuery()->getSingleScalarResult(),
        ];
    }
}