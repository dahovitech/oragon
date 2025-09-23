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

    public function calculateEarlyRepaymentAmount(LoanContract $contract, ?\DateTime $repaymentDate = null): array
    {
        if ($repaymentDate === null) {
            $repaymentDate = new \DateTime();
        }

        $originalAmount = (float) $contract->getOriginalAmount();
        $interestRate = $contract->getInterestRate() / 100;
        $duration = $contract->getDuration();
        $monthlyPayment = (float) $contract->getMonthlyPayment();

        // Calcul du capital restant dû
        $startDate = $contract->getStartDate() ?? $contract->getSignedAt();
        $monthsElapsed = $this->calculateMonthsElapsed($startDate, $repaymentDate);
        
        // Capital remboursé jusqu'à présent
        $capitalPaid = 0;
        $payments = $this->entityManager->getRepository(Payment::class)
            ->findBy(['loanContract' => $contract, 'status' => PaymentStatus::PAID]);
        
        foreach ($payments as $payment) {
            $capitalPaid += (float) $payment->getCapitalAmount();
        }

        $remainingCapital = $originalAmount - $capitalPaid;

        // Intérêts courus jusqu'à la date de remboursement
        $daysSinceLastPayment = $this->calculateDaysSinceLastPayment($contract, $repaymentDate);
        $dailyInterestRate = $interestRate / 365;
        $accruedInterests = $remainingCapital * $dailyInterestRate * $daysSinceLastPayment;

        // Montant total pour remboursement anticipé
        $earlyRepaymentAmount = $remainingCapital + $accruedInterests;

        // Calcul des économies
        $remainingPayments = $this->getRemainingPayments($contract);
        $remainingInterests = 0;
        foreach ($remainingPayments as $payment) {
            $remainingInterests += (float) $payment->getInterestAmount();
        }

        $interestSavings = max(0, $remainingInterests - $accruedInterests);
        $monthsSaved = count($remainingPayments);

        return [
            'earlyRepaymentAmount' => round($earlyRepaymentAmount, 2),
            'remainingCapital' => round($remainingCapital, 2),
            'accruedInterests' => round($accruedInterests, 2),
            'interestSavings' => round($interestSavings, 2),
            'monthsSaved' => $monthsSaved,
            'remainingMonths' => count($remainingPayments),
            'remainingInterests' => round($remainingInterests, 2),
            'normalTotalCost' => round($remainingCapital + $remainingInterests, 2),
        ];
    }

    public function processEarlyRepayment(LoanContract $contract, float $amount): void
    {
        $this->entityManager->beginTransaction();

        try {
            // Marquer le contrat comme remboursé anticipativement
            $contract->setEarlyRepaymentDate(new \DateTime());
            $contract->setRemainingAmount('0.00');
            $contract->setStatus('COMPLETED');

            // Annuler tous les paiements futurs
            $futurePayments = $this->entityManager->getRepository(Payment::class)
                ->createQueryBuilder('p')
                ->where('p.loanContract = :contract')
                ->andWhere('p.status = :pending')
                ->setParameter('contract', $contract)
                ->setParameter('pending', PaymentStatus::PENDING)
                ->getQuery()
                ->getResult();

            foreach ($futurePayments as $payment) {
                $payment->setStatus(PaymentStatus::CANCELLED);
            }

            // Créer un paiement de remboursement anticipé
            $earlyPayment = new Payment();
            $earlyPayment->setLoanContract($contract);
            $earlyPayment->setPaymentNumber(999); // Numéro spécial pour remboursement anticipé
            $earlyPayment->setAmount((string) $amount);
            $earlyPayment->setCapitalAmount((string) $contract->getRemainingAmount());
            $earlyPayment->setInterestAmount((string) ($amount - (float) $contract->getRemainingAmount()));
            $earlyPayment->setDueDate(new \DateTime());
            $earlyPayment->setStatus(PaymentStatus::PAID);
            $earlyPayment->setPaidAt(new \DateTime());
            $earlyPayment->setPaymentMethod('EARLY_REPAYMENT');
            $earlyPayment->setRemainingCapital('0.00');

            $this->entityManager->persist($earlyPayment);
            $this->entityManager->flush();
            $this->entityManager->commit();

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    private function calculateMonthsElapsed(\DateTime $startDate, \DateTime $currentDate): int
    {
        $diff = $startDate->diff($currentDate);
        return ($diff->y * 12) + $diff->m;
    }

    private function calculateDaysSinceLastPayment(LoanContract $contract, \DateTime $currentDate): int
    {
        $lastPayment = $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.loanContract = :contract')
            ->andWhere('p.status = :paid')
            ->setParameter('contract', $contract)
            ->setParameter('paid', PaymentStatus::PAID)
            ->orderBy('p.paidAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastPayment) {
            return $lastPayment->getPaidAt()->diff($currentDate)->days;
        } else {
            $startDate = $contract->getStartDate() ?? $contract->getSignedAt();
            return $startDate->diff($currentDate)->days;
        }
    }

    private function getRemainingPayments(LoanContract $contract): array
    {
        return $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.loanContract = :contract')
            ->andWhere('p.status = :pending')
            ->setParameter('contract', $contract)
            ->setParameter('pending', PaymentStatus::PENDING)
            ->orderBy('p.paymentNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function generatePaymentSchedule(LoanContract $contract): array
    {
        $payments = [];
        $originalAmount = (float) $contract->getOriginalAmount();
        $interestRate = $contract->getInterestRate() / 100;
        $duration = $contract->getDuration();
        $monthlyPayment = (float) $contract->getMonthlyPayment();
        
        $monthlyInterestRate = $interestRate / 12;
        $remainingCapital = $originalAmount;
        $startDate = $contract->getStartDate() ?? $contract->getSignedAt();

        for ($i = 1; $i <= $duration; $i++) {
            $interestAmount = $remainingCapital * $monthlyInterestRate;
            $capitalAmount = $monthlyPayment - $interestAmount;
            $remainingCapital -= $capitalAmount;

            // Ajustement pour le dernier paiement
            if ($i == $duration) {
                $capitalAmount += $remainingCapital;
                $remainingCapital = 0;
            }

            $dueDate = clone $startDate;
            $dueDate->modify("+$i months");

            $payments[] = [
                'paymentNumber' => $i,
                'dueDate' => $dueDate,
                'amount' => $monthlyPayment,
                'capitalAmount' => round($capitalAmount, 2),
                'interestAmount' => round($interestAmount, 2),
                'remainingCapital' => round(max(0, $remainingCapital), 2),
            ];
        }

        return $payments;
    }

    public function createPaymentEntities(LoanContract $contract): void
    {
        $schedule = $this->generatePaymentSchedule($contract);

        foreach ($schedule as $paymentData) {
            $payment = new Payment();
            $payment->setLoanContract($contract);
            $payment->setPaymentNumber($paymentData['paymentNumber']);
            $payment->setDueDate($paymentData['dueDate']);
            $payment->setAmount((string) $paymentData['amount']);
            $payment->setCapitalAmount((string) $paymentData['capitalAmount']);
            $payment->setInterestAmount((string) $paymentData['interestAmount']);
            $payment->setRemainingCapital((string) $paymentData['remainingCapital']);
            $payment->setStatus(PaymentStatus::PENDING);

            $this->entityManager->persist($payment);
        }

        $this->entityManager->flush();
    }
}