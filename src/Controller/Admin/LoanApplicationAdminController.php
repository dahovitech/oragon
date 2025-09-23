<?php

namespace App\Controller\Admin;

use App\Entity\LoanApplication;
use App\Entity\LoanContract;
use App\Entity\Payment;
use App\Enum\LoanApplicationStatus;
use App\Repository\LoanApplicationRepository;
use App\Service\NotificationService;
use App\Service\ContractGenerationService;
use App\Service\PaymentScheduleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/demandes', name: 'admin_loan_applications_')]
#[IsGranted('ROLE_ADMIN')]
class LoanApplicationAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoanApplicationRepository $applicationRepository,
        private NotificationService $notificationService,
        private ContractGenerationService $contractService,
        private PaymentScheduleService $paymentScheduleService
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $search = $request->query->get('search');
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        $queryBuilder = $this->applicationRepository->createQueryBuilder('la')
            ->leftJoin('la.user', 'u')
            ->leftJoin('la.loanType', 'lt')
            ->addSelect('u', 'lt')
            ->orderBy('la.submittedAt', 'DESC');

        // Filtres
        if ($status !== 'all') {
            $queryBuilder->andWhere('la.status = :status')
                ->setParameter('status', LoanApplicationStatus::from($status));
        }

        if ($search) {
            $queryBuilder->andWhere('
                u.email LIKE :search OR 
                u.firstName LIKE :search OR 
                u.lastName LIKE :search OR
                lt.name LIKE :search OR
                la.id = :searchId
            ')
            ->setParameter('search', '%' . $search . '%')
            ->setParameter('searchId', is_numeric($search) ? (int)$search : 0);
        }

        // Pagination
        $offset = ($page - 1) * $limit;
        $totalQuery = clone $queryBuilder;
        $total = $totalQuery->select('COUNT(la.id)')->getQuery()->getSingleScalarResult();
        
        $applications = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Statistiques pour le dashboard
        $stats = [
            'total' => $this->applicationRepository->count([]),
            'submitted' => $this->applicationRepository->count(['status' => LoanApplicationStatus::SUBMITTED]),
            'under_review' => $this->applicationRepository->count(['status' => LoanApplicationStatus::UNDER_REVIEW]),
            'approved' => $this->applicationRepository->count(['status' => LoanApplicationStatus::APPROVED]),
            'rejected' => $this->applicationRepository->count(['status' => LoanApplicationStatus::REJECTED]),
            'disbursed' => $this->applicationRepository->count(['status' => LoanApplicationStatus::DISBURSED]),
        ];

        return $this->render('admin/loan_applications/index.html.twig', [
            'applications' => $applications,
            'stats' => $stats,
            'currentPage' => $page,
            'totalPages' => ceil($total / $limit),
            'filters' => [
                'status' => $status,
                'search' => $search,
            ]
        ]);
    }

    #[Route('/{id}', name: 'detail')]
    public function detail(LoanApplication $application): Response
    {
        // Récupérer l'historique des changements de statut
        $statusHistory = $this->getStatusHistory($application);

        // Calculer les métriques de risque
        $riskAssessment = $this->calculateRiskAssessment($application);

        return $this->render('admin/loan_applications/detail.html.twig', [
            'application' => $application,
            'statusHistory' => $statusHistory,
            'riskAssessment' => $riskAssessment
        ]);
    }

    #[Route('/{id}/review', name: 'review')]
    public function review(LoanApplication $application, Request $request): Response
    {
        if ($application->getStatus() !== LoanApplicationStatus::SUBMITTED) {
            $this->addFlash('error', 'Cette demande ne peut pas être examinée dans son état actuel.');
            return $this->redirectToRoute('admin_loan_applications_detail', ['id' => $application->getId()]);
        }

        if ($request->isMethod('POST')) {
            $application->setStatus(LoanApplicationStatus::UNDER_REVIEW);
            $application->setReviewedAt(new \DateTime());
            
            $this->entityManager->flush();

            // Notification à l'utilisateur
            $this->notificationService->sendLoanApplicationUnderReview($application);

            $this->addFlash('success', 'La demande est maintenant en cours d\'examen.');
            
            return $this->redirectToRoute('admin_loan_applications_detail', ['id' => $application->getId()]);
        }

        return $this->render('admin/loan_applications/review.html.twig', [
            'application' => $application
        ]);
    }

    #[Route('/{id}/approve', name: 'approve')]
    public function approve(LoanApplication $application, Request $request): Response
    {
        if (!in_array($application->getStatus(), [LoanApplicationStatus::SUBMITTED, LoanApplicationStatus::UNDER_REVIEW])) {
            $this->addFlash('error', 'Cette demande ne peut pas être approuvée dans son état actuel.');
            return $this->redirectToRoute('admin_loan_applications_detail', ['id' => $application->getId()]);
        }

        if ($request->isMethod('POST')) {
            $approvalData = $request->request->all();
            
            // Mise à jour des conditions si modifiées
            if (isset($approvalData['approved_amount'])) {
                $application->setRequestedAmount((float)$approvalData['approved_amount']);
            }
            if (isset($approvalData['approved_rate'])) {
                $application->setInterestRate((float)$approvalData['approved_rate']);
            }
            if (isset($approvalData['approved_duration'])) {
                $application->setDuration((int)$approvalData['approved_duration']);
            }

            // Recalculer les mensualités avec les nouvelles conditions
            $this->recalculateLoanDetails($application);

            $application->setStatus(LoanApplicationStatus::APPROVED);
            $application->setApprovedAt(new \DateTime());
            
            $this->entityManager->flush();

            // Générer le contrat
            $this->generateContract($application);

            // Notification à l'utilisateur
            $this->notificationService->sendLoanApplicationApproved($application);

            $this->addFlash('success', 'La demande a été approuvée et le contrat généré.');
            
            return $this->redirectToRoute('admin_loan_applications_detail', ['id' => $application->getId()]);
        }

        return $this->render('admin/loan_applications/approve.html.twig', [
            'application' => $application
        ]);
    }

    #[Route('/{id}/reject', name: 'reject')]
    public function reject(LoanApplication $application, Request $request): Response
    {
        if (!in_array($application->getStatus(), [LoanApplicationStatus::SUBMITTED, LoanApplicationStatus::UNDER_REVIEW])) {
            $this->addFlash('error', 'Cette demande ne peut pas être rejetée dans son état actuel.');
            return $this->redirectToRoute('admin_loan_applications_detail', ['id' => $application->getId()]);
        }

        if ($request->isMethod('POST')) {
            $rejectionReason = $request->request->get('rejection_reason');
            
            $application->setStatus(LoanApplicationStatus::REJECTED);
            $application->setRejectedAt(new \DateTime());
            $application->setRejectionReason($rejectionReason);
            
            $this->entityManager->flush();

            // Notification à l'utilisateur
            $this->notificationService->sendLoanApplicationRejected($application);

            $this->addFlash('success', 'La demande a été rejetée.');
            
            return $this->redirectToRoute('admin_loan_applications_detail', ['id' => $application->getId()]);
        }

        return $this->render('admin/loan_applications/reject.html.twig', [
            'application' => $application
        ]);
    }

    #[Route('/{id}/documents', name: 'documents')]
    public function documents(LoanApplication $application): Response
    {
        return $this->render('admin/loan_applications/documents.html.twig', [
            'application' => $application
        ]);
    }

    #[Route('/{id}/verify-document/{documentId}', name: 'verify_document', methods: ['POST'])]
    public function verifyDocument(LoanApplication $application, int $documentId): JsonResponse
    {
        $document = $application->getDocuments()->filter(fn($doc) => $doc->getId() === $documentId)->first();
        
        if (!$document) {
            return new JsonResponse(['error' => 'Document non trouvé'], 404);
        }

        $document->setIsVerified(true);
        $document->setVerifiedBy($this->getUser());
        $document->setVerifiedAt(new \DateTime());
        
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/bulk-action', name: 'bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): JsonResponse
    {
        $action = $request->request->get('action');
        $applicationIds = $request->request->get('application_ids', []);

        if (empty($applicationIds)) {
            return new JsonResponse(['error' => 'Aucune demande sélectionnée'], 400);
        }

        $applications = $this->applicationRepository->findBy(['id' => $applicationIds]);
        $processed = 0;

        foreach ($applications as $application) {
            switch ($action) {
                case 'mark_under_review':
                    if ($application->getStatus() === LoanApplicationStatus::SUBMITTED) {
                        $application->setStatus(LoanApplicationStatus::UNDER_REVIEW);
                        $application->setReviewedAt(new \DateTime());
                        $processed++;
                    }
                    break;
                
                case 'send_reminder':
                    // Envoyer un rappel à l'utilisateur pour compléter sa demande
                    $this->notificationService->sendLoanApplicationReminder($application);
                    $processed++;
                    break;
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'processed' => $processed,
            'message' => "$processed demande(s) traitée(s)"
        ]);
    }

    private function generateContract(LoanApplication $application): void
    {
        // Vérifier s'il n'y a pas déjà un contrat
        $existingContract = $this->entityManager->getRepository(LoanContract::class)
            ->findOneBy(['loanApplication' => $application]);

        if ($existingContract) {
            return; // Contrat déjà généré
        }

        $contract = $this->contractService->generateContract($application);
        
        // Générer l'échéancier de paiement
        $this->paymentScheduleService->generatePaymentSchedule($contract);
        
        $this->entityManager->persist($contract);
        $this->entityManager->flush();
    }

    private function recalculateLoanDetails(LoanApplication $application): void
    {
        $amount = $application->getRequestedAmount();
        $duration = $application->getDuration();
        $rate = $application->getInterestRate();

        if (!$amount || !$duration || !$rate) {
            return;
        }

        // Calcul des mensualités
        $monthlyRate = $rate / 100 / 12;
        
        if ($monthlyRate > 0) {
            $monthlyPayment = $amount * ($monthlyRate * pow(1 + $monthlyRate, $duration)) / 
                             (pow(1 + $monthlyRate, $duration) - 1);
        } else {
            $monthlyPayment = $amount / $duration;
        }

        $application->setMonthlyPayment($monthlyPayment);
        $application->setTotalAmount($monthlyPayment * $duration);
    }

    private function getStatusHistory(LoanApplication $application): array
    {
        // Pour l'instant, on retourne les dates importantes
        // Dans une version plus avancée, on pourrait avoir une table d'historique dédiée
        $history = [];

        if ($application->getSubmittedAt()) {
            $history[] = [
                'status' => 'SUBMITTED',
                'date' => $application->getSubmittedAt(),
                'description' => 'Demande soumise par l\'utilisateur'
            ];
        }

        if ($application->getReviewedAt()) {
            $history[] = [
                'status' => 'UNDER_REVIEW',
                'date' => $application->getReviewedAt(),
                'description' => 'Mise en cours d\'examen'
            ];
        }

        if ($application->getApprovedAt()) {
            $history[] = [
                'status' => 'APPROVED',
                'date' => $application->getApprovedAt(),
                'description' => 'Demande approuvée'
            ];
        }

        if ($application->getRejectedAt()) {
            $history[] = [
                'status' => 'REJECTED',
                'date' => $application->getRejectedAt(),
                'description' => 'Demande rejetée'
            ];
        }

        return $history;
    }

    private function calculateRiskAssessment(LoanApplication $application): array
    {
        $user = $application->getUser();
        $score = 0;
        $factors = [];

        // Facteurs positifs
        if ($user->isVerified()) {
            $score += 20;
            $factors[] = ['type' => 'positive', 'description' => 'Compte vérifié', 'points' => 20];
        }

        if ($user->getMonthlyIncome() > 0) {
            $debtToIncomeRatio = ($application->getMonthlyPayment() / $user->getMonthlyIncome()) * 100;
            
            if ($debtToIncomeRatio <= 25) {
                $score += 30;
                $factors[] = ['type' => 'positive', 'description' => 'Ratio d\'endettement excellent (<25%)', 'points' => 30];
            } elseif ($debtToIncomeRatio <= 33) {
                $score += 20;
                $factors[] = ['type' => 'positive', 'description' => 'Ratio d\'endettement acceptable (<33%)', 'points' => 20];
            } else {
                $score -= 10;
                $factors[] = ['type' => 'negative', 'description' => 'Ratio d\'endettement élevé (>33%)', 'points' => -10];
            }
        }

        // Documents complets
        $totalDocuments = count($application->getDocuments());
        $verifiedDocuments = count($application->getDocuments()->filter(fn($doc) => $doc->isVerified()));
        
        if ($totalDocuments >= 3 && $verifiedDocuments === $totalDocuments) {
            $score += 15;
            $factors[] = ['type' => 'positive', 'description' => 'Tous les documents vérifiés', 'points' => 15];
        }

        // Montant demandé vs revenus
        if ($user->getMonthlyIncome() > 0) {
            $loanToIncomeRatio = $application->getRequestedAmount() / ($user->getMonthlyIncome() * 12);
            
            if ($loanToIncomeRatio <= 3) {
                $score += 10;
                $factors[] = ['type' => 'positive', 'description' => 'Montant raisonnable vs revenus', 'points' => 10];
            } elseif ($loanToIncomeRatio > 5) {
                $score -= 15;
                $factors[] = ['type' => 'negative', 'description' => 'Montant élevé vs revenus', 'points' => -15];
            }
        }

        // Déterminer le niveau de risque
        $riskLevel = 'high';
        $riskColor = 'danger';
        
        if ($score >= 70) {
            $riskLevel = 'low';
            $riskColor = 'success';
        } elseif ($score >= 40) {
            $riskLevel = 'medium';
            $riskColor = 'warning';
        }

        return [
            'score' => max(0, min(100, $score)),
            'level' => $riskLevel,
            'color' => $riskColor,
            'factors' => $factors
        ];
    }
}