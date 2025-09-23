<?php

namespace App\Service;

use App\Entity\LoanApplication;
use App\Entity\LoanContract;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class ContractGenerationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Environment $twig,
        private ParameterBagInterface $parameterBag
    ) {}

    public function generateContract(LoanApplication $application): LoanContract
    {
        // Générer un numéro de contrat unique
        $contractNumber = $this->generateContractNumber();

        // Créer l'entité contrat
        $contract = new LoanContract();
        $contract->setLoanApplication($application);
        $contract->setContractNumber($contractNumber);
        $contract->setIsActive(true);
        $contract->setStartDate(new \DateTime());
        $contract->setEndDate((new \DateTime())->modify('+' . $application->getDuration() . ' months'));

        // Générer l'échéancier de paiement
        $paymentSchedule = $this->generatePaymentSchedule($application);
        $contract->setPaymentSchedule($paymentSchedule);

        // Générer le PDF du contrat
        $contractPdf = $this->generateContractPdf($contract);
        $contract->setContractPdf($contractPdf);

        return $contract;
    }

    public function generateContractPdf(LoanContract $contract): string
    {
        $application = $contract->getLoanApplication();
        $user = $application->getUser();

        // Préparer les données pour le template
        $contractData = [
            'contract' => $contract,
            'application' => $application,
            'user' => $user,
            'loanType' => $application->getLoanType(),
            'generationDate' => new \DateTime(),
            'paymentSchedule' => json_decode($contract->getPaymentSchedule(), true),
            'companyInfo' => [
                'name' => 'EdgeLoan',
                'address' => '123 Avenue des Prêts, 75001 Paris',
                'siret' => '12345678901234',
                'phone' => '01 23 45 67 89',
                'email' => 'contact@edgeloan.fr'
            ]
        ];

        // Générer le HTML du contrat
        $html = $this->twig->render('admin/contracts/contract_template.html.twig', $contractData);

        // Configuration PDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        // Générer le PDF
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Sauvegarder le PDF
        $pdfContent = $dompdf->output();
        $fileName = 'contrat_' . $contract->getContractNumber() . '_' . date('Y-m-d') . '.pdf';
        $filePath = $this->parameterBag->get('kernel.project_dir') . '/public/uploads/contracts/' . $fileName;

        // Créer le répertoire s'il n'existe pas
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($filePath, $pdfContent);

        return 'uploads/contracts/' . $fileName;
    }

    public function regenerateContract(LoanContract $contract): void
    {
        $contractPdf = $this->generateContractPdf($contract);
        $contract->setContractPdf($contractPdf);
        
        $this->entityManager->flush();
    }

    private function generateContractNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        
        // Compter les contrats du mois
        $count = $this->entityManager->getRepository(LoanContract::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('YEAR(c.startDate) = :year')
            ->andWhere('MONTH(c.startDate) = :month')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->getQuery()
            ->getSingleScalarResult();

        $nextNumber = $count + 1;

        return sprintf('CTR-%s%s-%04d', $year, $month, $nextNumber);
    }

    private function generatePaymentSchedule(LoanApplication $application): string
    {
        $schedule = [];
        $amount = $application->getRequestedAmount();
        $duration = $application->getDuration();
        $monthlyPayment = $application->getMonthlyPayment();
        $interestRate = $application->getInterestRate();
        $monthlyRate = $interestRate / 100 / 12;
        
        $remainingBalance = $amount;
        $currentDate = new \DateTime();
        $currentDate->modify('first day of next month'); // Premier paiement le mois suivant

        for ($i = 1; $i <= $duration; $i++) {
            $interestAmount = $remainingBalance * $monthlyRate;
            $principalAmount = $monthlyPayment - $interestAmount;
            $remainingBalance -= $principalAmount;

            // Ajustement pour le dernier paiement (éviter les erreurs d'arrondi)
            if ($i === $duration) {
                $principalAmount += $remainingBalance;
                $remainingBalance = 0;
                $monthlyPayment = $principalAmount + $interestAmount;
            }

            $schedule[] = [
                'paymentNumber' => $i,
                'dueDate' => $currentDate->format('Y-m-d'),
                'monthlyPayment' => round($monthlyPayment, 2),
                'principalAmount' => round($principalAmount, 2),
                'interestAmount' => round($interestAmount, 2),
                'remainingBalance' => round(max(0, $remainingBalance), 2),
                'status' => 'pending'
            ];

            $currentDate->modify('+1 month');
        }

        return json_encode($schedule);
    }

    public function addDigitalSignature(LoanContract $contract, string $signatureData, \DateTime $signedAt = null): void
    {
        $contract->setDigitalSignature($signatureData);
        $contract->setSignedAt($signedAt ?? new \DateTime());
        
        $this->entityManager->flush();
    }

    public function validateContract(LoanContract $contract): array
    {
        $errors = [];

        // Vérifications de base
        if (!$contract->getLoanApplication()) {
            $errors[] = 'Aucune demande de prêt associée';
        }

        if (!$contract->getContractNumber()) {
            $errors[] = 'Numéro de contrat manquant';
        }

        if (!$contract->getStartDate() || !$contract->getEndDate()) {
            $errors[] = 'Dates de début ou fin manquantes';
        }

        if ($contract->getStartDate() && $contract->getEndDate() && 
            $contract->getStartDate() >= $contract->getEndDate()) {
            $errors[] = 'Date de fin antérieure à la date de début';
        }

        if (!$contract->getPaymentSchedule()) {
            $errors[] = 'Échéancier de paiement manquant';
        }

        // Vérification de l'échéancier
        $paymentSchedule = json_decode($contract->getPaymentSchedule(), true);
        if (empty($paymentSchedule)) {
            $errors[] = 'Échéancier de paiement invalide';
        }

        return $errors;
    }
}