<?php

namespace App\Controller\Admin;

use App\Entity\LoanApplication;
use App\Entity\LoanContract;
use App\Entity\User;
use App\Enum\LoanApplicationStatus;
use App\Service\ContractGenerationService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/loan-applications', name: 'admin_loan_applications_')]
#[IsGranted('ROLE_ADMIN')]
class LoanApplicationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContractGenerationService $contractService,
        private NotificationService $notificationService
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status');
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sort', 'createdAt');
        $sortOrder = $request->query->get('order', 'desc');

        $qb = $this->entityManager->getRepository(LoanApplication::class)
            ->createQueryBuilder('la')
            ->leftJoin('la.user', 'u')
            ->leftJoin('la.loanType', 'lt');

        if ($status) {
            $qb->andWhere('la.status = :status')
               ->setParameter('status', LoanApplicationStatus::from($status));
        }

        if ($search) {
            $qb->andWhere('(u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search OR la.id = :searchId)')
               ->setParameter('search', '%' . $search . '%')
               ->setParameter('searchId', $search);
        }

        $qb->orderBy('la.' . $sortBy, $sortOrder);

        $applications = $qb->getQuery()->getResult();

        // Statistiques
        $stats = $this->getApplicationStats();

        return $this->render('admin/loan_applications/index.html.twig', [
            'applications' => $applications,
            'stats' => $stats,
            'currentStatus' => $status,
            'currentSearch' => $search,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show(LoanApplication $application): Response
    {
        $user = $application->getUser();
        
        // Récupérer l'historique des changements de statut
        $statusHistory = $this->getStatusHistory($application);
        
        // Score de crédit simulé
        $creditScore = $this->calculateCreditScore($user, $application);
        
        // Documents uploadés
        $documents = $this->getApplicationDocuments($application);

        return $this->render('admin/loan_applications/show.html.twig', [
            'application' => $application,
            'user' => $user,
            'statusHistory' => $statusHistory,
            'creditScore' => $creditScore,
            'documents' => $documents,
        ]);
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(LoanApplication $application, Request $request): JsonResponse
    {
        if (!$application->getStatus()->canBeApproved()) {
            return new JsonResponse(['error' => 'Cette demande ne peut pas être approuvée dans son état actuel'], 400);
        }

        $approvedAmount = $request->request->get('approved_amount');
        $approvedRate = $request->request->get('approved_rate');
        $duration = $request->request->get('duration');
        $adminComment = $request->request->get('admin_comment');

        // Validation
        if (!$approvedAmount || !$approvedRate || !$duration) {
            return new JsonResponse(['error' => 'Tous les champs sont requis'], 400);
        }

        try {
            $this->entityManager->beginTransaction();

            // Mettre à jour la demande
            $application->setApprovedAmount((string) $approvedAmount);
            $application->setApprovedRate((float) $approvedRate);
            $application->setDuration((int) $duration);
            $application->setStatus(LoanApplicationStatus::APPROVED);
            $application->setApprovedAt(new \DateTime());
            $application->setApprovedBy($this->getUser());
            $application->setAdminComment($adminComment);

            // Générer le contrat
            $contract = $this->contractService->generateContract($application);
            $this->entityManager->persist($contract);

            // Mettre à jour le statut vers CONTRACT_GENERATED
            $application->setStatus(LoanApplicationStatus::CONTRACT_GENERATED);

            $this->entityManager->flush();
            $this->entityManager->commit();

            // Envoyer notification
            $this->notificationService->sendApplicationStatusUpdate(
                $application, 
                LoanApplicationStatus::CONTRACT_GENERATED->value
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Demande approuvée et contrat généré avec succès',
                'newStatus' => $application->getStatus()->getLabel()
            ]);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return new JsonResponse(['error' => 'Erreur lors de l\'approbation: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    public function reject(LoanApplication $application, Request $request): JsonResponse
    {
        if (!$application->getStatus()->canBeRejected()) {
            return new JsonResponse(['error' => 'Cette demande ne peut pas être rejetée dans son état actuel'], 400);
        }

        $rejectReason = $request->request->get('reject_reason');
        $adminComment = $request->request->get('admin_comment');

        if (!$rejectReason) {
            return new JsonResponse(['error' => 'La raison du rejet est requise'], 400);
        }

        try {
            $application->setStatus(LoanApplicationStatus::REJECTED);
            $application->setRejectedAt(new \DateTime());
            $application->setRejectedBy($this->getUser());
            $application->setRejectReason($rejectReason);
            $application->setAdminComment($adminComment);

            $this->entityManager->flush();

            // Envoyer notification
            $this->notificationService->sendApplicationStatusUpdate(
                $application, 
                LoanApplicationStatus::REJECTED->value
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Demande rejetée avec succès',
                'newStatus' => $application->getStatus()->getLabel()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors du rejet: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/request-documents', name: 'request_documents', methods: ['POST'])]
    public function requestDocuments(LoanApplication $application, Request $request): JsonResponse
    {
        $documentsRequested = $request->request->get('documents_requested');
        $message = $request->request->get('message');

        if (!$documentsRequested) {
            return new JsonResponse(['error' => 'Veuillez spécifier les documents requis'], 400);
        }

        try {
            $application->setStatus(LoanApplicationStatus::DOCUMENTS_REQUESTED);
            $application->setDocumentsRequested(json_encode($documentsRequested));
            $application->setDocumentsRequestMessage($message);
            $application->setDocumentsRequestedAt(new \DateTime());

            $this->entityManager->flush();

            // Envoyer notification
            $this->notificationService->sendApplicationStatusUpdate(
                $application, 
                LoanApplicationStatus::DOCUMENTS_REQUESTED->value
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Demande de documents envoyée',
                'newStatus' => $application->getStatus()->getLabel()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la demande: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/update-status', name: 'update_status', methods: ['POST'])]
    public function updateStatus(LoanApplication $application, Request $request): JsonResponse
    {
        $newStatus = $request->request->get('status');
        $comment = $request->request->get('comment');

        try {
            $statusEnum = LoanApplicationStatus::from($newStatus);
            
            // Vérifier si le changement de statut est autorisé
            $allowedStatuses = $application->getStatus()->getNextPossibleStatuses();
            if (!in_array($statusEnum, $allowedStatuses)) {
                return new JsonResponse(['error' => 'Changement de statut non autorisé'], 400);
            }

            $application->setStatus($statusEnum);
            if ($comment) {
                $application->setAdminComment($comment);
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'newStatus' => $statusEnum->getLabel()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/bulk-actions', name: 'bulk_actions', methods: ['POST'])]
    public function bulkActions(Request $request): JsonResponse
    {
        $action = $request->request->get('action');
        $applicationIds = $request->request->get('application_ids', []);

        if (empty($applicationIds)) {
            return new JsonResponse(['error' => 'Aucune demande sélectionnée'], 400);
        }

        $applications = $this->entityManager->getRepository(LoanApplication::class)
            ->findBy(['id' => $applicationIds]);

        $successCount = 0;
        $errors = [];

        foreach ($applications as $application) {
            try {
                switch ($action) {
                    case 'mark_under_review':
                        if ($application->getStatus()->canBeApproved()) {
                            $application->setStatus(LoanApplicationStatus::UNDER_REVIEW);
                            $successCount++;
                        }
                        break;
                    case 'export':
                        // Logic for export will be handled separately
                        $successCount++;
                        break;
                    default:
                        $errors[] = "Action non reconnue: $action";
                }
            } catch (\Exception $e) {
                $errors[] = "Erreur pour la demande {$application->getId()}: " . $e->getMessage();
            }
        }

        if ($successCount > 0) {
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'success' => $successCount > 0,
            'message' => "$successCount demandes traitées avec succès",
            'errors' => $errors
        ]);
    }

    #[Route('/statistics', name: 'statistics')]
    public function statistics(): JsonResponse
    {
        $stats = $this->getDetailedStats();
        return new JsonResponse($stats);
    }

    #[Route('/export', name: 'export')]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $status = $request->query->get('status');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        $qb = $this->entityManager->getRepository(LoanApplication::class)
            ->createQueryBuilder('la')
            ->leftJoin('la.user', 'u')
            ->leftJoin('la.loanType', 'lt');

        if ($status) {
            $qb->andWhere('la.status = :status')
               ->setParameter('status', LoanApplicationStatus::from($status));
        }

        if ($dateFrom) {
            $qb->andWhere('la.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($dateFrom));
        }

        if ($dateTo) {
            $qb->andWhere('la.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTime($dateTo . ' 23:59:59'));
        }

        $applications = $qb->getQuery()->getResult();

        if ($format === 'csv') {
            return $this->exportToCsv($applications);
        } else {
            return $this->exportToExcel($applications);
        }
    }

    private function getApplicationStats(): array
    {
        $repo = $this->entityManager->getRepository(LoanApplication::class);
        
        return [
            'total' => $repo->count([]),
            'pending' => $repo->count(['status' => LoanApplicationStatus::SUBMITTED]),
            'under_review' => $repo->count(['status' => LoanApplicationStatus::UNDER_REVIEW]),
            'approved' => $repo->count(['status' => LoanApplicationStatus::APPROVED]),
            'rejected' => $repo->count(['status' => LoanApplicationStatus::REJECTED]),
            'active' => $repo->count(['status' => LoanApplicationStatus::ACTIVE]),
            'completed' => $repo->count(['status' => LoanApplicationStatus::COMPLETED]),
        ];
    }

    private function getDetailedStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Statistiques par mois
        $monthlyStats = $qb->select('YEAR(la.createdAt) as year, MONTH(la.createdAt) as month, COUNT(la.id) as count')
            ->from(LoanApplication::class, 'la')
            ->where('la.createdAt >= :sixMonthsAgo')
            ->setParameter('sixMonthsAgo', new \DateTime('-6 months'))
            ->groupBy('year, month')
            ->orderBy('year, month')
            ->getQuery()
            ->getResult();

        // Montants moyens par statut
        $amountStats = $this->entityManager->createQueryBuilder()
            ->select('la.status, AVG(CAST(la.requestedAmount as DECIMAL(10,2))) as avgAmount, COUNT(la.id) as count')
            ->from(LoanApplication::class, 'la')
            ->groupBy('la.status')
            ->getQuery()
            ->getResult();

        return [
            'monthly' => $monthlyStats,
            'amounts' => $amountStats,
            'basic' => $this->getApplicationStats()
        ];
    }

    private function getStatusHistory(LoanApplication $application): array
    {
        // Dans un vrai projet, on aurait une table d'audit pour tracker les changements
        // Ici on simule avec les données disponibles
        $history = [];
        
        if ($application->getCreatedAt()) {
            $history[] = [
                'status' => LoanApplicationStatus::SUBMITTED,
                'date' => $application->getCreatedAt(),
                'user' => $application->getUser(),
                'comment' => 'Demande soumise'
            ];
        }
        
        if ($application->getApprovedAt()) {
            $history[] = [
                'status' => LoanApplicationStatus::APPROVED,
                'date' => $application->getApprovedAt(),
                'user' => $application->getApprovedBy(),
                'comment' => $application->getAdminComment()
            ];
        }
        
        if ($application->getRejectedAt()) {
            $history[] = [
                'status' => LoanApplicationStatus::REJECTED,
                'date' => $application->getRejectedAt(),
                'user' => $application->getRejectedBy(),
                'comment' => $application->getRejectReason()
            ];
        }
        
        return $history;
    }

    private function calculateCreditScore(User $user, LoanApplication $application): array
    {
        // Simulation d'un score de crédit
        $score = 650; // Score de base
        
        // Facteurs positifs
        if ($user->getMonthlyIncome() && (float) $user->getMonthlyIncome() > 2000) {
            $score += 50;
        }
        
        if ($user->isVerified()) {
            $score += 30;
        }
        
        $requestedAmount = (float) $application->getRequestedAmount();
        $monthlyIncome = (float) $user->getMonthlyIncome();
        
        if ($monthlyIncome > 0) {
            $debtToIncomeRatio = ($requestedAmount / $application->getDuration()) / $monthlyIncome;
            if ($debtToIncomeRatio < 0.3) {
                $score += 40;
            } elseif ($debtToIncomeRatio > 0.5) {
                $score -= 30;
            }
        }
        
        // Limiter le score entre 300 et 850
        $score = max(300, min(850, $score));
        
        $rating = 'Poor';
        if ($score >= 670) $rating = 'Good';
        if ($score >= 740) $rating = 'Very Good';
        if ($score >= 800) $rating = 'Excellent';
        
        return [
            'score' => $score,
            'rating' => $rating,
            'factors' => [
                'income' => $monthlyIncome,
                'verification' => $user->isVerified(),
                'debt_to_income' => $monthlyIncome > 0 ? round($debtToIncomeRatio * 100, 1) : 0
            ]
        ];
    }

    private function getApplicationDocuments(LoanApplication $application): array
    {
        // Récupérer les documents liés à la demande
        // Dans un vrai projet, il y aurait une entité Document
        return [];
    }

    private function exportToCsv(array $applications): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="loan_applications_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($output, [
            'ID', 'Date', 'Nom', 'Email', 'Montant', 'Durée', 'Statut', 'Type de prêt'
        ]);

        // Data
        foreach ($applications as $app) {
            fputcsv($output, [
                $app->getId(),
                $app->getCreatedAt()->format('d/m/Y'),
                $app->getUser()->getFirstName() . ' ' . $app->getUser()->getLastName(),
                $app->getUser()->getEmail(),
                $app->getRequestedAmount(),
                $app->getDuration(),
                $app->getStatus()->getLabel(),
                $app->getLoanType()->getName()
            ]);
        }

        rewind($output);
        $response->setContent(stream_get_contents($output));
        fclose($output);

        return $response;
    }

    private function exportToExcel(array $applications): Response
    {
        // Pour Excel, on utiliserait une librairie comme PhpSpreadsheet
        // Ici on retourne le CSV pour simplifier
        return $this->exportToCsv($applications);
    }
}