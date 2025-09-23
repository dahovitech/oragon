<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\LoanContract;
use App\Enum\PaymentStatus;
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
     * Trouve les paiements en retard
     */
    public function findOverduePayments(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :pending')
            ->andWhere('p.dueDate < :today')
            ->setParameter('pending', PaymentStatus::PENDING)
            ->setParameter('today', new \DateTime())
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements à venir dans les X jours
     */
    public function findUpcomingPayments(int $days = 7): array
    {
        $endDate = new \DateTime();
        $endDate->modify("+{$days} days");

        return $this->createQueryBuilder('p')
            ->where('p.status = :pending')
            ->andWhere('p.dueDate BETWEEN :today AND :endDate')
            ->setParameter('pending', PaymentStatus::PENDING)
            ->setParameter('today', new \DateTime())
            ->setParameter('endDate', $endDate)
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements par contrat
     */
    public function findByLoanContract(LoanContract $contract): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.loanContract = :contract')
            ->setParameter('contract', $contract)
            ->orderBy('p.paymentNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements en attente pour un contrat
     */
    public function findPendingByContract(LoanContract $contract): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.loanContract = :contract')
            ->andWhere('p.status = :pending')
            ->setParameter('contract', $contract)
            ->setParameter('pending', PaymentStatus::PENDING)
            ->orderBy('p.paymentNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le prochain paiement dû pour un contrat
     */
    public function findNextPaymentDue(LoanContract $contract): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->where('p.loanContract = :contract')
            ->andWhere('p.status = :pending')
            ->setParameter('contract', $contract)
            ->setParameter('pending', PaymentStatus::PENDING)
            ->orderBy('p.dueDate', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Calcule les statistiques de paiement pour un contrat
     */
    public function getPaymentStatistics(LoanContract $contract): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.loanContract = :contract')
            ->setParameter('contract', $contract);

        $allPayments = $qb->getQuery()->getResult();

        $stats = [
            'total' => count($allPayments),
            'paid' => 0,
            'pending' => 0,
            'late' => 0,
            'missed' => 0,
            'totalPaid' => 0,
            'totalDue' => 0,
            'completionPercentage' => 0
        ];

        foreach ($allPayments as $payment) {
            $stats['totalDue'] += $payment->getAmountFloat();

            switch ($payment->getStatus()) {
                case PaymentStatus::PAID:
                    $stats['paid']++;
                    $stats['totalPaid'] += $payment->getAmountFloat();
                    break;
                case PaymentStatus::PENDING:
                    $stats['pending']++;
                    break;
                case PaymentStatus::LATE:
                    $stats['late']++;
                    break;
                case PaymentStatus::MISSED:
                    $stats['missed']++;
                    break;
            }
        }

        if ($stats['total'] > 0) {
            $stats['completionPercentage'] = round(($stats['paid'] / $stats['total']) * 100, 2);
        }

        $stats['remainingBalance'] = $stats['totalDue'] - $stats['totalPaid'];

        return $stats;
    }

    /**
     * Trouve les paiements par statut et période
     */
    public function findByStatusAndDateRange(PaymentStatus $status, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.dueDate BETWEEN :startDate AND :endDate')
            ->setParameter('status', $status)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements par méthode de paiement
     */
    public function findByPaymentMethod(string $paymentMethod): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.paymentMethod = :method')
            ->andWhere('p.status = :paid')
            ->setParameter('method', $paymentMethod)
            ->setParameter('paid', PaymentStatus::PAID)
            ->orderBy('p.paidAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le montant total des paiements reçus sur une période
     */
    public function getTotalReceivedAmount(\DateTime $startDate, \DateTime $endDate): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->where('p.status = :paid')
            ->andWhere('p.paidAt BETWEEN :startDate AND :endDate')
            ->setParameter('paid', PaymentStatus::PAID)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (float)($result ?? 0);
    }

    /**
     * Trouve les contrats avec des paiements en retard
     */
    public function findContractsWithOverduePayments(): array
    {
        return $this->createQueryBuilder('p')
            ->select('DISTINCT lc.id')
            ->leftJoin('p.loanContract', 'lc')
            ->where('p.status = :pending')
            ->andWhere('p.dueDate < :today')
            ->setParameter('pending', PaymentStatus::PENDING)
            ->setParameter('today', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour le statut des paiements en retard
     */
    public function updateOverduePayments(): int
    {
        $today = new \DateTime();
        $thirtyDaysAgo = clone $today;
        $thirtyDaysAgo->modify('-30 days');

        // Marquer comme en retard (1-30 jours)
        $lateCount = $this->createQueryBuilder('p')
            ->update()
            ->set('p.status', ':late')
            ->where('p.status = :pending')
            ->andWhere('p.dueDate < :today')
            ->andWhere('p.dueDate >= :thirtyDaysAgo')
            ->setParameter('late', PaymentStatus::LATE)
            ->setParameter('pending', PaymentStatus::PENDING)
            ->setParameter('today', $today)
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->getQuery()
            ->execute();

        // Marquer comme manqué (plus de 30 jours)
        $missedCount = $this->createQueryBuilder('p')
            ->update()
            ->set('p.status', ':missed')
            ->where('p.status IN (:statuses)')
            ->andWhere('p.dueDate < :thirtyDaysAgo')
            ->setParameter('missed', PaymentStatus::MISSED)
            ->setParameter('statuses', [PaymentStatus::PENDING, PaymentStatus::LATE])
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->getQuery()
            ->execute();

        return $lateCount + $missedCount;
    }
}