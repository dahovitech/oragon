<?php

namespace App\Controller\Frontend;

use App\Entity\LoanContract;
use App\Entity\LoanApplication;
use App\Service\ContractGenerationService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contrats', name: 'app_contracts_')]
#[IsGranted('ROLE_USER')]
class ContractController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContractGenerationService $contractService,
        private NotificationService $notificationService
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Récupérer les contrats de l'utilisateur
        $contracts = $this->entityManager->getRepository(LoanContract::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.loanApplication', 'la')
            ->leftJoin('la.user', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('frontend/contracts/index.html.twig', [
            'contracts' => $contracts,
            'user' => $user
        ]);
    }

    #[Route('/{id}', name: 'detail')]
    public function detail(LoanContract $contract): Response
    {
        // Vérifier que l'utilisateur peut accéder à ce contrat
        if ($contract->getLoanApplication()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Récupérer les paiements associés
        $payments = $this->entityManager->getRepository(\App\Entity\Payment::class)
            ->findBy(['loanContract' => $contract], ['paymentNumber' => 'ASC']);

        // Calculer les statistiques
        $stats = [
            'totalPayments' => count($payments),
            'paidPayments' => count(array_filter($payments, fn($p) => $p->isPaid())),
            'totalPaid' => array_sum(array_map(fn($p) => $p->isPaid() ? $p->getAmountFloat() : 0, $payments)),
            'remainingBalance' => $contract->getRemainingAmount(),
            'nextPaymentDue' => null
        ];

        // Trouver le prochain paiement dû
        foreach ($payments as $payment) {
            if (!$payment->isPaid()) {
                $stats['nextPaymentDue'] = $payment;
                break;
            }
        }

        return $this->render('frontend/contracts/detail.html.twig', [
            'contract' => $contract,
            'payments' => $payments,
            'stats' => $stats
        ]);
    }

    #[Route('/{id}/signer', name: 'sign')]
    public function sign(LoanContract $contract, Request $request): Response
    {
        // Vérifier que l'utilisateur peut signer ce contrat
        if ($contract->getLoanApplication()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que le contrat n'est pas déjà signé
        if ($contract->isSigned()) {
            $this->addFlash('info', 'Ce contrat a déjà été signé.');
            return $this->redirectToRoute('app_contracts_detail', ['id' => $contract->getId()]);
        }

        if ($request->isMethod('POST')) {
            $signatureData = $request->request->get('signature');
            $acceptTerms = $request->request->get('accept_terms');

            if (!$acceptTerms) {
                $this->addFlash('error', 'Vous devez accepter les termes et conditions pour signer le contrat.');
                return $this->render('frontend/contracts/sign.html.twig', ['contract' => $contract]);
            }

            if (!$signatureData) {
                $this->addFlash('error', 'Veuillez apposer votre signature électronique.');
                return $this->render('frontend/contracts/sign.html.twig', ['contract' => $contract]);
            }

            // Enregistrer la signature
            $this->contractService->addDigitalSignature($contract, $signatureData, new \DateTime());

            // Changer le statut de la demande
            $application = $contract->getLoanApplication();
            $application->setStatus(\App\Enum\LoanApplicationStatus::DISBURSED);
            
            $this->entityManager->flush();

            // Notification
            $this->notificationService->sendContractSigned($application);

            $this->addFlash('success', 'Votre contrat a été signé avec succès. Les fonds seront débloqués sous 48h.');

            return $this->redirectToRoute('app_contracts_detail', ['id' => $contract->getId()]);
        }

        return $this->render('frontend/contracts/sign.html.twig', [
            'contract' => $contract
        ]);
    }

    #[Route('/{id}/telecharger', name: 'download')]
    public function download(LoanContract $contract): BinaryFileResponse
    {
        // Vérifier que l'utilisateur peut télécharger ce contrat
        if ($contract->getLoanApplication()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $contract->getContractPdf();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier de contrat n\'existe pas.');
        }

        return $this->file($filePath, 'contrat_' . $contract->getContractNumber() . '.pdf');
    }

    #[Route('/{id}/regenerer', name: 'regenerate', methods: ['POST'])]
    public function regenerate(LoanContract $contract): JsonResponse
    {
        // Vérifier que l'utilisateur peut régénérer ce contrat
        if ($contract->getLoanApplication()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Ne pas permettre la régénération d'un contrat signé
        if ($contract->isSigned()) {
            return new JsonResponse(['error' => 'Impossible de régénérer un contrat signé'], 400);
        }

        try {
            $this->contractService->regenerateContract($contract);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Le contrat a été régénéré avec succès.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la régénération du contrat: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/echeancier', name: 'payment_schedule')]
    public function paymentSchedule(LoanContract $contract): Response
    {
        // Vérifier que l'utilisateur peut accéder à ce contrat
        if ($contract->getLoanApplication()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $payments = $this->entityManager->getRepository(\App\Entity\Payment::class)
            ->findBy(['loanContract' => $contract], ['paymentNumber' => 'ASC']);

        return $this->render('frontend/contracts/payment_schedule.html.twig', [
            'contract' => $contract,
            'payments' => $payments
        ]);
    }

    #[Route('/{id}/remboursement-anticipe', name: 'early_repayment')]
    public function earlyRepayment(LoanContract $contract, Request $request): Response
    {
        // Vérifier que l'utilisateur peut accéder à ce contrat
        if ($contract->getLoanApplication()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que le contrat est actif
        if (!$contract->isActive()) {
            $this->addFlash('error', 'Ce contrat n\'est plus actif.');
            return $this->redirectToRoute('app_contracts_detail', ['id' => $contract->getId()]);
        }

        $paymentScheduleService = new \App\Service\PaymentScheduleService($this->entityManager);
        $earlyRepaymentData = $paymentScheduleService->calculateEarlyRepaymentAmount($contract);

        if ($request->isMethod('POST')) {
            $confirmRepayment = $request->request->get('confirm_repayment');
            
            if ($confirmRepayment) {
                try {
                    $paymentScheduleService->processEarlyRepayment(
                        $contract, 
                        $earlyRepaymentData['earlyRepaymentAmount']
                    );

                    $this->addFlash('success', 'Votre remboursement anticipé a été traité avec succès.');
                    
                    return $this->redirectToRoute('app_contracts_detail', ['id' => $contract->getId()]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors du traitement: ' . $e->getMessage());
                }
            }
        }

        return $this->render('frontend/contracts/early_repayment.html.twig', [
            'contract' => $contract,
            'earlyRepaymentData' => $earlyRepaymentData
        ]);
    }

    #[Route('/{id}/historique-paiements', name: 'payment_history')]
    public function paymentHistory(LoanContract $contract): Response
    {
        // Vérifier que l'utilisateur peut accéder à ce contrat
        if ($contract->getLoanApplication()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $payments = $this->entityManager->getRepository(\App\Entity\Payment::class)
            ->createQueryBuilder('p')
            ->where('p.loanContract = :contract')
            ->andWhere('p.status = :paid')
            ->setParameter('contract', $contract)
            ->setParameter('paid', \App\Enum\PaymentStatus::PAID)
            ->orderBy('p.paidAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('frontend/contracts/payment_history.html.twig', [
            'contract' => $contract,
            'payments' => $payments
        ]);
    }

    #[Route('/{id}/simulation-remboursement', name: 'repayment_simulation')]
    public function repaymentSimulation(LoanContract $contract, Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur peut accéder à ce contrat
        if ($contract->getLoanApplication()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $repaymentDate = $request->query->get('date', date('Y-m-d'));
        $repaymentDateTime = new \DateTime($repaymentDate);

        $paymentScheduleService = new \App\Service\PaymentScheduleService($this->entityManager);
        $simulationData = $paymentScheduleService->calculateEarlyRepaymentAmount($contract, $repaymentDateTime);

        return new JsonResponse($simulationData);
    }

    #[Route('/{id}/attestation', name: 'certificate')]
    public function certificate(LoanContract $contract): Response
    {
        // Vérifier que l'utilisateur peut accéder à ce contrat
        if ($contract->getLoanApplication()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que le contrat est terminé (tous les paiements effectués)
        if ($contract->isActive()) {
            $this->addFlash('error', 'L\'attestation de fin de prêt n\'est disponible qu\'après remboursement complet.');
            return $this->redirectToRoute('app_contracts_detail', ['id' => $contract->getId()]);
        }

        return $this->render('frontend/contracts/certificate.html.twig', [
            'contract' => $contract
        ]);
    }
}