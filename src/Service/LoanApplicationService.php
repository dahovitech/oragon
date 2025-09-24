<?php

namespace App\Service;

use App\Entity\LoanApplication;
use App\Entity\User;
use App\Enum\LoanApplicationStatus;
use App\Repository\LoanApplicationRepository;
use App\Service\ContractGeneratorService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LoanApplicationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoanApplicationRepository $loanApplicationRepository,
        private ContractGeneratorService $contractGenerator,
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Submit a new loan application
     */
    public function submitApplication(LoanApplication $application): void
    {
        $application->setStatus(LoanApplicationStatus::SUBMITTED);
        $application->setSubmittedAt(new \DateTime());
        
        $this->entityManager->persist($application);
        $this->entityManager->flush();
        
        // Send confirmation email
        $this->notificationService->sendLoanApplicationSubmitted($application);
        
        $this->logger->info('Loan application submitted', [
            'application_id' => $application->getId(),
            'user_id' => $application->getUser()->getId(),
            'amount' => $application->getRequestedAmount()
        ]);
    }

    /**
     * Put application under review
     */
    public function putUnderReview(LoanApplication $application): void
    {
        $application->setStatus(LoanApplicationStatus::UNDER_REVIEW);
        $application->setReviewedAt(new \DateTime());
        
        $this->entityManager->flush();
        
        // Send notification
        $this->notificationService->sendLoanApplicationUnderReview($application);
        
        $this->logger->info('Loan application under review', [
            'application_id' => $application->getId()
        ]);
    }

    /**
     * Approve loan application and generate contract
     */
    public function approveApplication(LoanApplication $application, ?User $approvedBy = null): void
    {
        $application->setStatus(LoanApplicationStatus::APPROVED);
        $application->setReviewedAt(new \DateTime());
        
        if ($approvedBy) {
            $application->setReviewedBy($approvedBy);
        }
        
        $this->entityManager->flush();
        
        try {
            // Generate contract automatically
            $contract = $this->contractGenerator->generateContract($application);
            
            // Send approval notification
            $this->notificationService->sendLoanApplicationApproved($application);
            
            $this->logger->info('Loan application approved and contract generated', [
                'application_id' => $application->getId(),
                'contract_number' => $contract->getContractNumber(),
                'approved_by' => $approvedBy?->getId()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate contract after approval', [
                'application_id' => $application->getId(),
                'error' => $e->getMessage()
            ]);
            
            // Still send approval notification even if contract generation fails
            $this->notificationService->sendLoanApplicationApproved($application);
        }
    }

    /**
     * Reject loan application
     */
    public function rejectApplication(LoanApplication $application, string $reason = '', ?User $rejectedBy = null): void
    {
        $application->setStatus(LoanApplicationStatus::REJECTED);
        $application->setReviewedAt(new \DateTime());
        $application->setRejectionReason($reason);
        
        if ($rejectedBy) {
            $application->setReviewedBy($rejectedBy);
        }
        
        $this->entityManager->flush();
        
        // Send rejection notification
        $this->notificationService->sendLoanApplicationRejected($application, $reason);
        
        $this->logger->info('Loan application rejected', [
            'application_id' => $application->getId(),
            'reason' => $reason,
            'rejected_by' => $rejectedBy?->getId()
        ]);
    }

    /**
     * Request additional documents
     */
    public function requestDocuments(LoanApplication $application, array $requiredDocuments): void
    {
        $application->setStatus(LoanApplicationStatus::PENDING_DOCUMENTS);
        $application->setReviewedAt(new \DateTime());
        
        $this->entityManager->flush();
        
        // Send notification about required documents
        // Note: This would need a new method in NotificationService
        // $this->notificationService->sendLoanApplicationPendingDocuments($application, $requiredDocuments);
        
        $this->logger->info('Documents requested for loan application', [
            'application_id' => $application->getId(),
            'documents_count' => count($requiredDocuments)
        ]);
    }

    /**
     * Calculate loan terms based on application
     */
    public function calculateLoanTerms(LoanApplication $application): array
    {
        $amount = floatval($application->getRequestedAmount());
        $months = $application->getDurationMonths();
        
        // Get interest rate based on loan type and user profile
        $interestRate = $this->calculateInterestRate($application);
        
        // Calculate monthly payment using standard amortization formula
        $monthlyRate = $interestRate / 100 / 12;
        
        if ($monthlyRate == 0) {
            $monthlyPayment = $amount / $months;
        } else {
            $monthlyPayment = $amount * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
        }
        
        $totalAmount = $monthlyPayment * $months;
        $totalInterest = $totalAmount - $amount;
        
        return [
            'principal' => $amount,
            'interest_rate' => $interestRate,
            'duration_months' => $months,
            'monthly_payment' => round($monthlyPayment, 2),
            'total_amount' => round($totalAmount, 2),
            'total_interest' => round($totalInterest, 2),
        ];
    }

    /**
     * Calculate interest rate based on loan type and user profile
     */
    private function calculateInterestRate(LoanApplication $application): float
    {
        $loanType = $application->getLoanType();
        $baseRate = match($loanType) {
            'personal' => 8.5,
            'business' => 12.0,
            'emergency' => 15.0,
            'mortgage' => 6.5,
            'auto' => 7.5,
            default => 10.0
        };
        
        // Adjust rate based on amount (higher amounts get slightly better rates)
        $amount = floatval($application->getRequestedAmount());
        if ($amount > 5000000) { // > 5M FCFA
            $baseRate -= 0.5;
        } elseif ($amount > 2000000) { // > 2M FCFA
            $baseRate -= 0.25;
        }
        
        // Adjust rate based on duration (longer terms get slightly higher rates)
        $months = $application->getDurationMonths();
        if ($months > 36) {
            $baseRate += 0.5;
        } elseif ($months > 24) {
            $baseRate += 0.25;
        }
        
        return max(5.0, min(20.0, $baseRate)); // Keep rate between 5% and 20%
    }

    /**
     * Get loan application statistics
     */
    public function getApplicationStatistics(): array
    {
        return [
            'total' => $this->loanApplicationRepository->count([]),
            'submitted' => $this->loanApplicationRepository->count(['status' => LoanApplicationStatus::SUBMITTED]),
            'under_review' => $this->loanApplicationRepository->count(['status' => LoanApplicationStatus::UNDER_REVIEW]),
            'pending_documents' => $this->loanApplicationRepository->count(['status' => LoanApplicationStatus::PENDING_DOCUMENTS]),
            'approved' => $this->loanApplicationRepository->count(['status' => LoanApplicationStatus::APPROVED]),
            'rejected' => $this->loanApplicationRepository->count(['status' => LoanApplicationStatus::REJECTED]),
        ];
    }

    /**
     * Get applications requiring review
     */
    public function getApplicationsRequiringReview(): array
    {
        return $this->loanApplicationRepository->findBy([
            'status' => [
                LoanApplicationStatus::SUBMITTED,
                LoanApplicationStatus::UNDER_REVIEW,
                LoanApplicationStatus::PENDING_DOCUMENTS
            ]
        ], ['submittedAt' => 'ASC']);
    }

    /**
     * Get user's loan applications
     */
    public function getUserApplications(User $user): array
    {
        return $this->loanApplicationRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Check if user can apply for a new loan
     */
    public function canUserApplyForLoan(User $user): bool
    {
        // Check for active pending applications
        $pendingApplications = $this->loanApplicationRepository->findBy([
            'user' => $user,
            'status' => [
                LoanApplicationStatus::SUBMITTED,
                LoanApplicationStatus::UNDER_REVIEW,
                LoanApplicationStatus::PENDING_DOCUMENTS
            ]
        ]);
        
        // User can apply if no pending applications
        return empty($pendingApplications);
    }

    /**
     * Get application with all related data
     */
    public function getApplicationWithDetails(int $applicationId): ?LoanApplication
    {
        return $this->loanApplicationRepository->createQueryBuilder('la')
            ->leftJoin('la.user', 'u')
            ->addSelect('u')
            ->leftJoin('u.documents', 'd')
            ->addSelect('d')
            ->where('la.id = :id')
            ->setParameter('id', $applicationId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Update application status with logging
     */
    public function updateApplicationStatus(LoanApplication $application, LoanApplicationStatus $newStatus, ?string $reason = null): void
    {
        $oldStatus = $application->getStatus();
        $application->setStatus($newStatus);
        
        if ($reason) {
            $application->setRejectionReason($reason);
        }
        
        $this->entityManager->flush();
        
        $this->logger->info('Loan application status updated', [
            'application_id' => $application->getId(),
            'old_status' => $oldStatus->value,
            'new_status' => $newStatus->value,
            'reason' => $reason
        ]);
    }
}