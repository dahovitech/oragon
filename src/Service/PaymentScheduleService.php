<?php

namespace App\Service;

use App\Entity\LoanContract;
use App\Entity\Payment;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;

class PaymentScheduleService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function generatePaymentSchedule(LoanContract $contract): void
    {
        $paymentScheduleData = json_decode($contract->getPaymentSchedule(), true);
        
        if (empty($paymentScheduleData)) {
            throw new \InvalidArgumentException('Échéancier de paiement invalide');
        }

        // Supprimer les anciens paiements s'ils existent
        $existingPayments = $this->entityManager->getRepository(Payment::class)
            ->findBy(['loanContract' => $contract]);
        
        foreach ($existingPayments as $payment) {
            $this->entityManager->remove($payment);
        }

        // Créer les nouveaux paiements
        foreach ($paymentScheduleData as $scheduleItem) {
            $payment = new Payment();
            $payment->setLoanContract($contract);
            $payment->setPaymentNumber($scheduleItem['paymentNumber']);
            $payment->setDueDate(new \DateTime($scheduleItem['dueDate']));
            $payment->setAmount($scheduleItem['monthlyPayment']);
            $payment->setPrincipalAmount($scheduleItem['principalAmount']);
            $payment->setInterestAmount($scheduleItem['interestAmount']);
            $payment->setStatus(PaymentStatus::PENDING);

            $this->entityManager->persist($payment);
        }

        $this->entityManager->flush();
    }

    public function recordPayment(Payment $payment, float $amount, string $paymentMethod = 'bank_transfer', \DateTime $paidAt = null): bool
    {
        if ($payment->getStatus() !== PaymentStatus::PENDING) {
            throw new \InvalidArgumentException('Ce paiement ne peut pas être enregistré');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être positif');
        }

        $payment->setStatus(PaymentStatus::PAID);
        $payment->setPaidAt($paidAt ?? new \DateTime());
        $payment->setPaymentMethod($paymentMethod);
        
        // Si le montant payé est différent du montant dû, on peut l'ajuster
        if ($amount !== $payment->getAmount()) {
            $payment->setAmount($amount);
        }

        $this->entityManager->flush();

        // Vérifier si le prêt est entièrement remboursé
        $this->checkLoanCompletion($payment->getLoanContract());

        return true;
    }

    public function markPaymentAsLate(Payment $payment): void
    {
        if ($payment->getStatus() === PaymentStatus::PENDING && 
            $payment->getDueDate() < new \DateTime()) {
            $payment->setStatus(PaymentStatus::LATE);
            $this->entityManager->flush();
        }
    }

    public function markPaymentAsMissed(Payment $payment): void
    {
        if (in_array($payment->getStatus(), [PaymentStatus::PENDING, PaymentStatus::LATE])) {
            $payment->setStatus(PaymentStatus::MISSED);
            $this->entityManager->flush();
        }
    }

    public function getPaymentScheduleForContract(LoanContract $contract): array
    {
        return $this->entityManager->getRepository(Payment::class)
            ->findBy(['loanContract' => $contract], ['paymentNumber' => 'ASC']);
    }

    public function getOverduePayments(): array
    {
        return $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.status = :pending')
            ->andWhere('p.dueDate < :today')
            ->setParameter('pending', PaymentStatus::PENDING)
            ->setParameter('today', new \DateTime())
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getUpcomingPayments(int $days = 7): array
    {
        $endDate = new \DateTime();
        $endDate->modify("+{$days} days");

        return $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.status = :pending')
            ->andWhere('p.dueDate BETWEEN :today AND :endDate')
            ->setParameter('pending', PaymentStatus::PENDING)
            ->setParameter('today', new \DateTime())
            ->setParameter('endDate', $endDate)
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function calculateEarlyRepaymentAmount(LoanContract $contract, \DateTime $repaymentDate = null): array
    {
        $repaymentDate = $repaymentDate ?? new \DateTime();
        
        $remainingPayments = $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.loanContract = :contract')
            ->andWhere('p.status = :pending')
            ->andWhere('p.dueDate > :repaymentDate')
            ->setParameter('contract', $contract)
            ->setParameter('pending', PaymentStatus::PENDING)
            ->setParameter('repaymentDate', $repaymentDate)
            ->getQuery()
            ->getResult();

        $totalPrincipal = 0;
        $totalInterest = 0;
        $savedInterest = 0;

        foreach ($remainingPayments as $payment) {
            $totalPrincipal += $payment->getPrincipalAmount();
            $totalInterest += $payment->getInterestAmount();
        }

        // Calculer les intérêts économisés (par exemple, 50% des intérêts futurs)
        $savedInterest = $totalInterest * 0.5;
        $earlyRepaymentAmount = $totalPrincipal + ($totalInterest - $savedInterest);

        return [
            'remainingPrincipal' => $totalPrincipal,
            'futureInterest' => $totalInterest,
            'savedInterest' => $savedInterest,
            'earlyRepaymentAmount' => $earlyRepaymentAmount,
            'totalSavings' => $savedInterest,
            'remainingPayments' => count($remainingPayments)
        ];
    }

    public function processEarlyRepayment(LoanContract $contract, float $amount, string $paymentMethod = 'bank_transfer'): bool
    {
        $earlyRepaymentData = $this->calculateEarlyRepaymentAmount($contract);
        
        if ($amount < $earlyRepaymentData['earlyRepaymentAmount']) {
            throw new \InvalidArgumentException('Le montant est insuffisant pour le remboursement anticipé');
        }

        // Marquer tous les paiements restants comme payés
        $remainingPayments = $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.loanContract = :contract')
            ->andWhere('p.status = :pending')
            ->setParameter('contract', $contract)
            ->setParameter('pending', PaymentStatus::PENDING)
            ->getQuery()
            ->getResult();

        $now = new \DateTime();
        foreach ($remainingPayments as $payment) {
            $payment->setStatus(PaymentStatus::PAID);
            $payment->setPaidAt($now);
            $payment->setPaymentMethod($paymentMethod);
            
            // Ajuster le montant pour le dernier paiement (remboursement anticipé)
            if ($payment === end($remainingPayments)) {
                $payment->setAmount($earlyRepaymentData['earlyRepaymentAmount']);
                $payment->setInterestAmount($payment->getInterestAmount() - $earlyRepaymentData['savedInterest']);
            }
        }

        // Marquer le contrat comme terminé
        $contract->setIsActive(false);
        
        $this->entityManager->flush();

        return true;
    }

    public function getPaymentStatistics(LoanContract $contract): array
    {
        $payments = $this->getPaymentScheduleForContract($contract);
        
        $totalPayments = count($payments);
        $paidPayments = count(array_filter($payments, fn($p) => $p->getStatus() === PaymentStatus::PAID));
        $latePayments = count(array_filter($payments, fn($p) => $p->getStatus() === PaymentStatus::LATE));
        $missedPayments = count(array_filter($payments, fn($p) => $p->getStatus() === PaymentStatus::MISSED));
        
        $totalPaid = array_sum(array_map(fn($p) => $p->getStatus() === PaymentStatus::PAID ? $p->getAmount() : 0, $payments));
        $totalDue = array_sum(array_map(fn($p) => $p->getAmount(), $payments));
        
        return [
            'totalPayments' => $totalPayments,
            'paidPayments' => $paidPayments,
            'pendingPayments' => $totalPayments - $paidPayments - $latePayments - $missedPayments,
            'latePayments' => $latePayments,
            'missedPayments' => $missedPayments,
            'completionPercentage' => $totalPayments > 0 ? round(($paidPayments / $totalPayments) * 100, 2) : 0,
            'totalPaid' => $totalPaid,
            'totalDue' => $totalDue,
            'remainingBalance' => $totalDue - $totalPaid,
            'nextPaymentDue' => $this->getNextPaymentDue($contract)
        ];
    }

    private function getNextPaymentDue(LoanContract $contract): ?Payment
    {
        return $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.loanContract = :contract')
            ->andWhere('p.status = :pending')
            ->setParameter('contract', $contract)
            ->setParameter('pending', PaymentStatus::PENDING)
            ->orderBy('p.dueDate', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function checkLoanCompletion(LoanContract $contract): void
    {
        $pendingPayments = $this->entityManager->getRepository(Payment::class)
            ->count([
                'loanContract' => $contract,
                'status' => PaymentStatus::PENDING
            ]);

        if ($pendingPayments === 0) {
            $contract->setIsActive(false);
            
            // Mettre à jour le statut de la demande de prêt
            $application = $contract->getLoanApplication();
            $application->setStatus(\App\Enum\LoanApplicationStatus::DISBURSED);
            
            $this->entityManager->flush();
        }
    }

    public function updateOverduePayments(): int
    {
        $updated = 0;
        $overduePayments = $this->getOverduePayments();
        
        foreach ($overduePayments as $payment) {
            $daysPastDue = $payment->getDueDate()->diff(new \DateTime())->days;
            
            if ($daysPastDue <= 30) {
                $this->markPaymentAsLate($payment);
            } else {
                $this->markPaymentAsMissed($payment);
            }
            
            $updated++;
        }

        return $updated;
    }
}