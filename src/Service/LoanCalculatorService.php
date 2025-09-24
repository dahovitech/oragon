<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LoanCalculatorService
{
    public function __construct(
        #[Autowire('%app.loan.default_processing_fee%')] private float $defaultProcessingFee = 1.5
    ) {}

    /**
     * Calcule les détails d'un prêt
     */
    public function calculateLoan(float $amount, int $durationMonths, float $annualInterestRate): array
    {
        // Taux d'intérêt mensuel
        $monthlyInterestRate = $annualInterestRate / 100 / 12;
        
        // Calcul du paiement mensuel avec la formule standard de prêt
        $monthlyPayment = $this->calculateMonthlyPayment($amount, $monthlyInterestRate, $durationMonths);
        
        // Montant total à rembourser
        $totalAmount = $monthlyPayment * $durationMonths;
        
        // Intérêts totaux
        $totalInterest = $totalAmount - $amount;
        
        // Frais de traitement (optionnel)
        $processingFee = $amount * ($this->defaultProcessingFee / 100);
        
        return [
            'principal_amount' => number_format($amount, 2),
            'monthly_payment' => number_format($monthlyPayment, 2),
            'total_amount' => number_format($totalAmount, 2),
            'total_interest' => number_format($totalInterest, 2),
            'processing_fee' => number_format($processingFee, 2),
            'interest_rate' => number_format($annualInterestRate, 2),
            'duration_months' => $durationMonths,
            'duration_years' => round($durationMonths / 12, 1),
        ];
    }

    /**
     * Calcule le paiement mensuel en utilisant la formule PMT
     * PMT = P * (r * (1 + r)^n) / ((1 + r)^n - 1)
     */
    private function calculateMonthlyPayment(float $principal, float $monthlyRate, int $months): float
    {
        if ($monthlyRate === 0.0) {
            // Si pas d'intérêts, diviser simplement le principal par le nombre de mois
            return $principal / $months;
        }

        $numerator = $principal * $monthlyRate * pow(1 + $monthlyRate, $months);
        $denominator = pow(1 + $monthlyRate, $months) - 1;
        
        return $numerator / $denominator;
    }

    /**
     * Calcule la capacité d'emprunt basée sur le revenu
     */
    public function calculateBorrowingCapacity(
        float $monthlyIncome, 
        float $monthlyExpenses = 0, 
        float $maxDebtToIncomeRatio = 35.0,
        float $annualInterestRate = 5.0,
        int $maxDurationMonths = 84
    ): array {
        // Calcul du revenu disponible pour le remboursement
        $availableIncome = $monthlyIncome - $monthlyExpenses;
        $maxMonthlyPayment = $availableIncome * ($maxDebtToIncomeRatio / 100);
        
        // Calcul du montant maximum empruntable
        $monthlyInterestRate = $annualInterestRate / 100 / 12;
        
        if ($monthlyInterestRate === 0.0) {
            $maxBorrowAmount = $maxMonthlyPayment * $maxDurationMonths;
        } else {
            $denominator = $monthlyInterestRate * pow(1 + $monthlyInterestRate, $maxDurationMonths);
            $numerator = pow(1 + $monthlyInterestRate, $maxDurationMonths) - 1;
            $maxBorrowAmount = $maxMonthlyPayment * ($numerator / $denominator);
        }

        return [
            'max_borrow_amount' => number_format($maxBorrowAmount, 2),
            'max_monthly_payment' => number_format($maxMonthlyPayment, 2),
            'debt_to_income_ratio' => $maxDebtToIncomeRatio,
            'available_income' => number_format($availableIncome, 2),
        ];
    }

    /**
     * Génère un tableau d'amortissement
     */
    public function generateAmortizationSchedule(float $amount, int $durationMonths, float $annualInterestRate): array
    {
        $monthlyInterestRate = $annualInterestRate / 100 / 12;
        $monthlyPayment = $this->calculateMonthlyPayment($amount, $monthlyInterestRate, $durationMonths);
        
        $schedule = [];
        $remainingBalance = $amount;
        
        for ($month = 1; $month <= $durationMonths; $month++) {
            $interestPayment = $remainingBalance * $monthlyInterestRate;
            $principalPayment = $monthlyPayment - $interestPayment;
            $remainingBalance -= $principalPayment;
            
            // Correction pour le dernier paiement (éviter les arrondis négatifs)
            if ($month === $durationMonths) {
                $principalPayment += $remainingBalance;
                $remainingBalance = 0;
            }
            
            $schedule[] = [
                'month' => $month,
                'payment' => number_format($monthlyPayment, 2),
                'principal' => number_format($principalPayment, 2),
                'interest' => number_format($interestPayment, 2),
                'balance' => number_format(max(0, $remainingBalance), 2),
            ];
        }
        
        return $schedule;
    }

    /**
     * Compare différentes options de prêt
     */
    public function compareLoanOptions(array $loanOptions): array
    {
        $comparisons = [];
        
        foreach ($loanOptions as $option) {
            $calculation = $this->calculateLoan(
                $option['amount'],
                $option['duration'],
                $option['interest_rate']
            );
            
            $comparisons[] = array_merge($option, $calculation);
        }
        
        // Trier par paiement mensuel
        usort($comparisons, fn($a, $b) => (float)str_replace(',', '', $a['monthly_payment']) <=> (float)str_replace(',', '', $b['monthly_payment']));
        
        return $comparisons;
    }

    /**
     * Calcule l'impact du paiement anticipé
     */
    public function calculateEarlyPaymentImpact(
        float $amount, 
        int $durationMonths, 
        float $annualInterestRate, 
        float $extraMonthlyPayment
    ): array {
        $monthlyInterestRate = $annualInterestRate / 100 / 12;
        $regularPayment = $this->calculateMonthlyPayment($amount, $monthlyInterestRate, $durationMonths);
        $totalPayment = $regularPayment + $extraMonthlyPayment;
        
        $balance = $amount;
        $month = 0;
        $totalInterestPaid = 0;
        
        while ($balance > 0.01 && $month < $durationMonths * 2) { // Protection contre boucle infinie
            $month++;
            $interestPayment = $balance * $monthlyInterestRate;
            $principalPayment = min($totalPayment - $interestPayment, $balance);
            
            $balance -= $principalPayment;
            $totalInterestPaid += $interestPayment;
        }
        
        $originalTotal = $regularPayment * $durationMonths;
        $newTotal = $totalPayment * $month;
        $savings = $originalTotal - $newTotal;
        $monthsSaved = $durationMonths - $month;
        
        return [
            'new_duration_months' => $month,
            'months_saved' => $monthsSaved,
            'total_interest_saved' => number_format($savings, 2),
            'new_total_cost' => number_format($newTotal, 2),
            'extra_payment' => number_format($extraMonthlyPayment, 2),
        ];
    }
}