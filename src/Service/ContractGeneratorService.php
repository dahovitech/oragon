<?php

namespace App\Service;

use App\Entity\LoanApplication;
use App\Entity\LoanContract;
use App\Repository\LoanContractRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

class ContractGeneratorService
{
    private string $contractsDirectory;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoanContractRepository $contractRepository,
        private Environment $twig,
        private NotificationService $notificationService,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        $this->contractsDirectory = $this->projectDir . '/public/uploads/contracts/';
        
        // Create contracts directory if it doesn't exist
        if (!is_dir($this->contractsDirectory)) {
            mkdir($this->contractsDirectory, 0755, true);
        }
    }

    /**
     * Generate a contract for an approved loan application
     */
    public function generateContract(LoanApplication $loanApplication): LoanContract
    {
        // Check if contract already exists
        $existingContract = $this->contractRepository->findOneBy(['loanApplication' => $loanApplication]);
        if ($existingContract) {
            return $existingContract;
        }

        // Create new contract entity
        $contract = new LoanContract();
        $contract->setLoanApplication($loanApplication);
        
        // Set contract details from loan application
        $this->populateContractDetails($contract, $loanApplication);
        
        // Generate contract content
        $contractContent = $this->generateContractContent($contract);
        $contract->setContractContent($contractContent);
        
        // Generate PDF
        $pdfFilePath = $this->generatePDF($contract, $contractContent);
        $contract->setFilePath($pdfFilePath);
        $contract->setFileName(basename($pdfFilePath));
        
        // Save to database
        $this->entityManager->persist($contract);
        $this->entityManager->flush();
        
        // Send notification to user
        $this->notificationService->sendContractGenerated($loanApplication->getUser(), $contract);
        
        return $contract;
    }

    /**
     * Populate contract details from loan application
     */
    private function populateContractDetails(LoanContract $contract, LoanApplication $loanApplication): void
    {
        $contract->setContractAmount($loanApplication->getRequestedAmount());
        
        // Get interest rate based on loan type
        $interestRate = $this->getInterestRateForLoanType($loanApplication->getLoanType());
        $contract->setInterestRate($interestRate);
        
        $contract->setDurationMonths($loanApplication->getDurationMonths());
        
        // Set contract dates
        $startDate = new \DateTime();
        $endDate = clone $startDate;
        $endDate->modify('+' . $loanApplication->getDurationMonths() . ' months');
        
        $contract->setStartDate($startDate);
        $contract->setEndDate($endDate);
        
        // Calculate payments
        $monthlyPayment = $contract->calculateMonthlyPayment();
        $contract->setMonthlyPayment((string)$monthlyPayment);
        
        $totalAmount = $contract->calculateTotalAmount();
        $contract->setTotalAmount((string)$totalAmount);
        
        // Set terms and conditions
        $contract->setTerms($this->getStandardTerms());
        $contract->setConditions($this->getStandardConditions($loanApplication->getLoanType()));
    }

    /**
     * Generate contract content using Twig template
     */
    private function generateContractContent(LoanContract $contract): string
    {
        return $this->twig->render('contracts/loan_contract.html.twig', [
            'contract' => $contract,
            'loanApplication' => $contract->getLoanApplication(),
            'user' => $contract->getLoanApplication()->getUser(),
            'generated_date' => new \DateTime(),
        ]);
    }

    /**
     * Generate PDF from HTML content
     */
    private function generatePDF(LoanContract $contract, string $htmlContent): string
    {
        // Configure Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Load HTML content
        $dompdf->loadHtml($htmlContent);
        
        // Set paper size and orientation
        $dompdf->setPaper('A4', 'portrait');
        
        // Render PDF
        $dompdf->render();
        
        // Generate unique filename
        $filename = 'contract_' . $contract->getContractNumber() . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $filePath = $this->contractsDirectory . $filename;
        
        // Save PDF to file
        file_put_contents($filePath, $dompdf->output());
        
        return $filename;
    }

    /**
     * Get interest rate based on loan type
     */
    private function getInterestRateForLoanType(?string $loanType): float
    {
        return match($loanType) {
            'personal' => 8.5,
            'business' => 12.0,
            'emergency' => 15.0,
            'mortgage' => 6.5,
            'auto' => 7.5,
            default => 10.0
        };
    }

    /**
     * Get standard terms and conditions
     */
    private function getStandardTerms(): string
    {
        return "1. Le prêt sera remboursé selon l'échéancier convenu.\n" .
               "2. Le taux d'intérêt est fixe pour toute la durée du prêt.\n" .
               "3. En cas de retard de paiement, des pénalités pourront s'appliquer.\n" .
               "4. Le prêt peut être remboursé par anticipation sans pénalités.\n" .
               "5. L'emprunteur s'engage à maintenir une assurance appropriée.\n" .
               "6. Toute modification doit faire l'objet d'un avenant écrit.";
    }

    /**
     * Get standard conditions based on loan type
     */
    private function getStandardConditions(string $loanType): string
    {
        $baseConditions = "CONDITIONS GÉNÉRALES:\n\n" .
                         "• L'emprunteur certifie l'exactitude des informations fournies.\n" .
                         "• Le prêt est accordé sous réserve de vérifications d'usage.\n" .
                         "• Les remboursements s'effectuent par prélèvement automatique.\n" .
                         "• En cas de difficultés, l'emprunteur doit contacter Oragon immédiatement.\n\n";

        $specificConditions = match($loanType) {
            'business' => "CONDITIONS SPÉCIALES POUR PRÊT PROFESSIONNEL:\n" .
                         "• Justificatifs d'activité professionnelle requis.\n" .
                         "• Suivi trimestriel de l'activité.\n",
            'mortgage' => "CONDITIONS SPÉCIALES POUR PRÊT IMMOBILIER:\n" .
                         "• Garantie hypothécaire sur le bien financé.\n" .
                         "• Assurance décès-invalidité obligatoire.\n",
            'emergency' => "CONDITIONS SPÉCIALES POUR PRÊT D'URGENCE:\n" .
                          "• Déblocage rapide des fonds sous 48h.\n" .
                          "• Suivi renforcé du remboursement.\n",
            default => ""
        };

        return $baseConditions . $specificConditions;
    }

    /**
     * Regenerate contract (in case of modifications)
     */
    public function regenerateContract(LoanContract $contract): LoanContract
    {
        // Update contract content
        $contractContent = $this->generateContractContent($contract);
        $contract->setContractContent($contractContent);
        
        // Delete old PDF file if exists
        $oldFilePath = $this->contractsDirectory . $contract->getFilePath();
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
        
        // Generate new PDF
        $pdfFilePath = $this->generatePDF($contract, $contractContent);
        $contract->setFilePath($pdfFilePath);
        $contract->setFileName(basename($pdfFilePath));
        $contract->setUpdatedAt(new \DateTime());
        
        // Save changes
        $this->entityManager->flush();
        
        return $contract;
    }

    /**
     * Send contract to user
     */
    public function sendContract(LoanContract $contract): void
    {
        $contract->setStatus('sent');
        $this->entityManager->flush();
        
        // Send contract via email
        $this->notificationService->sendContractForSigning(
            $contract->getLoanApplication()->getUser(), 
            $contract
        );
    }

    /**
     * Mark contract as signed
     */
    public function signContract(LoanContract $contract, string $signature = null): void
    {
        $contract->sign();
        
        if ($signature) {
            $contract->setSignature($signature);
        }
        
        $this->entityManager->flush();
        
        // Notify about signed contract
        $this->notificationService->sendContractSigned(
            $contract->getLoanApplication()->getUser(), 
            $contract
        );
    }

    /**
     * Activate signed contract
     */
    public function activateContract(LoanContract $contract): void
    {
        $contract->activate();
        $this->entityManager->flush();
        
        // Notify about activated contract
        $this->notificationService->sendContractActivated(
            $contract->getLoanApplication()->getUser(), 
            $contract
        );
    }

    /**
     * Get contract statistics
     */
    public function getContractStatistics(): array
    {
        $qb = $this->contractRepository->createQueryBuilder('c');
        
        return [
            'total' => $qb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult(),
            'generated' => $this->contractRepository->count(['status' => 'generated']),
            'sent' => $this->contractRepository->count(['status' => 'sent']),
            'signed' => $this->contractRepository->count(['status' => 'signed']),
            'active' => $this->contractRepository->count(['status' => 'active']),
            'completed' => $this->contractRepository->count(['status' => 'completed']),
        ];
    }

    /**
     * Get contract by number
     */
    public function getContractByNumber(string $contractNumber): ?LoanContract
    {
        return $this->contractRepository->findOneBy(['contractNumber' => $contractNumber]);
    }

    /**
     * Get user contracts
     */
    public function getUserContracts(int $userId): array
    {
        return $this->contractRepository->findContractsByUser($userId);
    }

    /**
     * Check if contract file exists
     */
    public function contractFileExists(LoanContract $contract): bool
    {
        $filePath = $this->contractsDirectory . $contract->getFilePath();
        return file_exists($filePath);
    }

    /**
     * Get contract file path for download
     */
    public function getContractFilePath(LoanContract $contract): ?string
    {
        if (!$this->contractFileExists($contract)) {
            return null;
        }
        
        return $this->contractsDirectory . $contract->getFilePath();
    }
}