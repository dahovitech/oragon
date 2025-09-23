<?php

namespace App\Service;

use App\Entity\LoanContract;
use App\Entity\LoanApplication;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class ContractGenerationService
{
    public function __construct(
        private Environment $twig,
        private ParameterBagInterface $parameterBag
    ) {}

    public function generateContract(LoanApplication $application): LoanContract
    {
        // Créer l'entité contrat
        $contract = new LoanContract();
        $contract->setLoanApplication($application);
        $contract->setContractNumber($this->generateContractNumber());
        $contract->setOriginalAmount($application->getRequestedAmount());
        $contract->setInterestRate($application->getApprovedRate());
        $contract->setDuration($application->getDuration());
        
        // Calculer la mensualité
        $monthlyPayment = $this->calculateMonthlyPayment(
            (float) $application->getRequestedAmount(),
            $application->getApprovedRate(),
            $application->getDuration()
        );
        $contract->setMonthlyPayment((string) $monthlyPayment);
        
        // Calculer le TAEG
        $taeg = $this->calculateTaeg(
            (float) $application->getRequestedAmount(),
            $application->getApprovedRate(),
            $application->getDuration(),
            $monthlyPayment
        );
        $contract->setTaeg($taeg);
        
        // Définir les dates
        $contract->setCreatedAt(new \DateTime());
        $startDate = new \DateTime();
        $startDate->modify('+2 days'); // Le contrat démarre 2 jours après signature
        $contract->setStartDate($startDate);
        
        $endDate = clone $startDate;
        $endDate->modify('+' . $application->getDuration() . ' months');
        $contract->setEndDate($endDate);
        
        // Définir le jour de prélèvement (par défaut le 5 du mois)
        $contract->setPaymentDay(5);
        
        // Calculer la première échéance
        $firstPaymentDate = clone $startDate;
        $firstPaymentDate->modify('first day of next month');
        $firstPaymentDate->setDate(
            $firstPaymentDate->format('Y'),
            $firstPaymentDate->format('n'),
            $contract->getPaymentDay()
        );
        $contract->setFirstPaymentDate($firstPaymentDate);
        
        // Générer le PDF du contrat
        $pdfPath = $this->generateContractPDF($contract);
        $contract->setContractPdf($pdfPath);
        
        // Initialiser les montants
        $contract->setRemainingAmount($application->getRequestedAmount());
        $contract->setTotalAmountPaid('0.00');
        
        // Statut initial
        $contract->setStatus('GENERATED');
        
        return $contract;
    }

    public function addDigitalSignature(LoanContract $contract, string $signatureData, \DateTime $signedAt): void
    {
        $contract->setDigitalSignature($signatureData);
        $contract->setSignedAt($signedAt);
        $contract->setStatus('ACTIVE');
        
        // Régénérer le PDF avec la signature
        $this->regenerateContractWithSignature($contract);
    }

    public function regenerateContract(LoanContract $contract): void
    {
        // Regenerate the PDF
        $newPdfPath = $this->generateContractPDF($contract);
        $contract->setContractPdf($newPdfPath);
    }

    private function generateContractNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        
        return "CTR-{$year}{$month}-{$timestamp}-{$random}";
    }

    private function calculateMonthlyPayment(float $amount, float $annualRate, int $duration): float
    {
        $monthlyRate = $annualRate / 100 / 12;
        
        if ($monthlyRate == 0) {
            return $amount / $duration;
        }
        
        $payment = $amount * ($monthlyRate * pow(1 + $monthlyRate, $duration)) / (pow(1 + $monthlyRate, $duration) - 1);
        
        return round($payment, 2);
    }

    private function calculateTaeg(float $amount, float $annualRate, int $duration, float $monthlyPayment): float
    {
        // Calcul simplifié du TAEG (dans un cas réel, il faudrait inclure tous les frais)
        $totalPaid = $monthlyPayment * $duration;
        $totalInterest = $totalPaid - $amount;
        
        // TAEG approximatif
        $taeg = ($totalInterest / $amount) / ($duration / 12) * 100;
        
        return round($taeg, 2);
    }

    private function generateContractPDF(LoanContract $contract): string
    {
        // Générer le contenu HTML du contrat
        $html = $this->twig->render('pdf/contract.html.twig', [
            'contract' => $contract,
            'application' => $contract->getLoanApplication(),
            'user' => $contract->getLoanApplication()->getUser(),
            'generatedAt' => new \DateTime(),
        ]);

        // Nom du fichier PDF
        $filename = 'contract_' . $contract->getContractNumber() . '_' . time() . '.pdf';
        $filepath = 'upload/contracts/' . $filename;
        $fullPath = $this->parameterBag->get('kernel.project_dir') . '/public/' . $filepath;

        // Créer le répertoire s'il n'existe pas
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Générer le PDF (ici on simule avec un fichier HTML pour la démo)
        // Dans un vrai projet, on utiliserait une librairie comme wkhtmltopdf, TCPDF ou DomPDF
        file_put_contents($fullPath, $this->convertHtmlToPdfContent($html));

        return $filepath;
    }

    private function regenerateContractWithSignature(LoanContract $contract): void
    {
        // Similaire à generateContractPDF mais inclut les données de signature
        $html = $this->twig->render('pdf/contract_signed.html.twig', [
            'contract' => $contract,
            'application' => $contract->getLoanApplication(),
            'user' => $contract->getLoanApplication()->getUser(),
            'signature' => $contract->getDigitalSignature(),
            'signedAt' => $contract->getSignedAt(),
            'generatedAt' => new \DateTime(),
        ]);

        $filename = 'contract_signed_' . $contract->getContractNumber() . '_' . time() . '.pdf';
        $filepath = 'upload/contracts/' . $filename;
        $fullPath = $this->parameterBag->get('kernel.project_dir') . '/public/' . $filepath;

        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $this->convertHtmlToPdfContent($html));
        $contract->setContractPdf($filepath);
    }

    private function convertHtmlToPdfContent(string $html): string
    {
        // Dans un vrai projet, utiliser une vraie librairie PDF
        // Pour la démo, on retourne le HTML avec un en-tête PDF simulé
        $pdfHeader = "%PDF-1.4\n% Contrat EdgeLoan - Document simulé\n";
        $pdfContent = $pdfHeader . "\n\n" . $html . "\n\n";
        
        return $pdfContent;
    }

    public function calculateContractMetrics(LoanContract $contract): array
    {
        $originalAmount = (float) $contract->getOriginalAmount();
        $monthlyPayment = (float) $contract->getMonthlyPayment();
        $duration = $contract->getDuration();
        
        $totalAmount = $monthlyPayment * $duration;
        $totalInterest = $totalAmount - $originalAmount;
        $interestPercentage = ($totalInterest / $originalAmount) * 100;
        
        return [
            'originalAmount' => $originalAmount,
            'totalAmount' => $totalAmount,
            'totalInterest' => $totalInterest,
            'interestPercentage' => round($interestPercentage, 2),
            'monthlyPayment' => $monthlyPayment,
            'duration' => $duration,
        ];
    }

    public function validateContractTerms(LoanApplication $application): array
    {
        $errors = [];
        
        // Vérifications de base
        if ((float) $application->getRequestedAmount() < 500) {
            $errors[] = "Le montant minimum est de 500€";
        }
        
        if ((float) $application->getRequestedAmount() > 50000) {
            $errors[] = "Le montant maximum est de 50 000€";
        }
        
        if ($application->getDuration() < 6) {
            $errors[] = "La durée minimum est de 6 mois";
        }
        
        if ($application->getDuration() > 84) {
            $errors[] = "La durée maximum est de 84 mois";
        }
        
        if ($application->getApprovedRate() <= 0 || $application->getApprovedRate() > 20) {
            $errors[] = "Le taux d'intérêt doit être compris entre 0.1% et 20%";
        }
        
        return $errors;
    }
}