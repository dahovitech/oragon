<?php

namespace App\Controller\Admin;

use App\Entity\LoanContract;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Service\PaymentScheduleService;
use App\Service\ContractGenerationService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/contracts', name: 'admin_contracts_')]
#[IsGranted('ROLE_ADMIN')]
class ContractAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentScheduleService $paymentScheduleService,
        private ContractGenerationService $contractService,
        private NotificationService $notificationService
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status');
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sort', 'signedAt');
        $sortOrder = $request->query->get('order', 'desc');

        $qb = $this->entityManager->getRepository(LoanContract::class)
            ->createQueryBuilder('lc')
            ->leftJoin('lc.loanApplication', 'la')
            ->leftJoin('la.user', 'u');

        if ($status) {
            $qb->andWhere('lc.status = :status')
               ->setParameter('status', $status);
        }

        if ($search) {
            $qb->andWhere('(u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search OR lc.contractNumber LIKE :search)')
               ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('lc.' . $sortBy, $sortOrder);

        $contracts = $qb->getQuery()->getResult();

        // Statistiques des contrats
        $stats = $this->getContractStats();

        return $this->render('admin/contracts/index.html.twig', [
            'contracts' => $contracts,
            'stats' => $stats,
            'currentStatus' => $status,
            'currentSearch' => $search,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show(LoanContract $contract): Response
    {
        $user = $contract->getLoanApplication()->getUser();
        
        // Échéancier complet
        $paymentSchedule = $this->paymentScheduleService->generateSchedule($contract);
        
        // Historique des paiements
        $payments = $this->entityManager->getRepository(Payment::class)
            ->findBy(['loanContract' => $contract], ['dueDate' => 'ASC']);
        
        // Statistiques du contrat
        $contractStats = $this->getContractStatistics($contract);
        
        // Calculs financiers
        $financialSummary = $this->calculateFinancialSummary($contract, $payments);

        return $this->render('admin/contracts/show.html.twig', [
            'contract' => $contract,
            'user' => $user,
            'paymentSchedule' => $paymentSchedule,
            'payments' => $payments,
            'contractStats' => $contractStats,
            'financialSummary' => $financialSummary,
        ]);
    }

    #[Route('/{id}/payments', name: 'payments')]
    public function payments(LoanContract $contract): Response
    {
        $payments = $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.loanContract = :contract')
            ->setParameter('contract', $contract)
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();

        $stats = [
            'total_payments' => count($payments),
            'paid_payments' => count(array_filter($payments, fn($p) => $p->getStatus() === PaymentStatus::PAID)),
            'pending_payments' => count(array_filter($payments, fn($p) => $p->getStatus() === PaymentStatus::PENDING)),
            'overdue_payments' => count(array_filter($payments, fn($p) => $p->getStatus() === PaymentStatus::PENDING && $p->getDueDate() < new \DateTime())),
            'total_amount' => array_sum(array_map(fn($p) => $p->getAmount(), $payments)),
            'paid_amount' => array_sum(array_map(fn($p) => $p->getStatus() === PaymentStatus::PAID ? $p->getAmount() : 0, $payments)),
        ];

        return $this->render('admin/contracts/payments.html.twig', [
            'contract' => $contract,
            'payments' => $payments,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/payment/{paymentId}/mark-paid', name: 'mark_payment_paid', methods: ['POST'])]
    public function markPaymentPaid(LoanContract $contract, int $paymentId, Request $request): JsonResponse
    {
        $payment = $this->entityManager->getRepository(Payment::class)->find($paymentId);
        
        if (!$payment || $payment->getLoanContract() !== $contract) {
            return new JsonResponse(['error' => 'Paiement non trouvé'], 404);
        }

        if ($payment->getStatus() === PaymentStatus::PAID) {
            return new JsonResponse(['error' => 'Ce paiement est déjà marqué comme payé'], 400);
        }

        $payment->setStatus(PaymentStatus::PAID);
        $payment->setPaidAt(new \DateTime());
        
        // Ajouter une note si fournie
        $note = $request->request->get('note');
        if ($note) {
            $payment->setNote($note);
        }

        $this->entityManager->flush();

        // Notification à l'utilisateur
        $this->notificationService->sendPaymentConfirmation(
            $contract->getLoanApplication()->getUser(),
            $payment
        );

        return new JsonResponse([
            'success' => true,
            'message' => 'Paiement marqué comme payé avec succès',
            'payment_id' => $payment->getId()
        ]);
    }

    #[Route('/{id}/suspend', name: 'suspend', methods: ['POST'])]
    public function suspendContract(LoanContract $contract, Request $request): JsonResponse
    {
        if ($contract->getStatus() !== 'ACTIVE') {
            return new JsonResponse(['error' => 'Seuls les contrats actifs peuvent être suspendus'], 400);
        }

        $reason = $request->request->get('reason');
        
        $contract->setStatus('SUSPENDED');
        $contract->setSuspensionReason($reason);
        $contract->setSuspendedAt(new \DateTime());
        
        $this->entityManager->flush();

        // Notification
        $this->notificationService->sendContractSuspensionNotification(
            $contract->getLoanApplication()->getUser(),
            $contract,
            $reason
        );

        return new JsonResponse([
            'success' => true,
            'message' => 'Contrat suspendu avec succès'
        ]);
    }

    #[Route('/{id}/reactivate', name: 'reactivate', methods: ['POST'])]
    public function reactivateContract(LoanContract $contract): JsonResponse
    {
        if ($contract->getStatus() !== 'SUSPENDED') {
            return new JsonResponse(['error' => 'Seuls les contrats suspendus peuvent être réactivés'], 400);
        }

        $contract->setStatus('ACTIVE');
        $contract->setSuspensionReason(null);
        $contract->setSuspendedAt(null);
        
        $this->entityManager->flush();

        // Notification
        $this->notificationService->sendContractReactivationNotification(
            $contract->getLoanApplication()->getUser(),
            $contract
        );

        return new JsonResponse([
            'success' => true,
            'message' => 'Contrat réactivé avec succès'
        ]);
    }

    #[Route('/export', name: 'export')]
    public function exportContracts(Request $request): StreamedResponse
    {
        $status = $request->query->get('status');
        
        $qb = $this->entityManager->getRepository(LoanContract::class)
            ->createQueryBuilder('lc')
            ->leftJoin('lc.loanApplication', 'la')
            ->leftJoin('la.user', 'u');

        if ($status) {
            $qb->andWhere('lc.status = :status')
               ->setParameter('status', $status);
        }

        $contracts = $qb->getQuery()->getResult();

        $response = new StreamedResponse();
        
        $response->setCallback(function() use ($contracts) {
            $handle = fopen('php://output', 'w+');
            
            // En-têtes CSV
            fputcsv($handle, [
                'Numéro de contrat',
                'Client',
                'Email',
                'Montant original',
                'Montant restant',
                'Taux d\'intérêt',
                'Durée (mois)',
                'Statut',
                'Date de signature',
                'Date d\'échéance'
            ]);
            
            foreach ($contracts as $contract) {
                $user = $contract->getLoanApplication()->getUser();
                fputcsv($handle, [
                    $contract->getContractNumber(),
                    $user->getFirstName() . ' ' . $user->getLastName(),
                    $user->getEmail(),
                    $contract->getOriginalAmount(),
                    $contract->getRemainingAmount(),
                    $contract->getInterestRate() . '%',
                    $contract->getDurationInMonths(),
                    $contract->getStatus(),
                    $contract->getSignedAt()?->format('d/m/Y'),
                    $contract->getMaturityDate()?->format('d/m/Y')
                ]);
            }
            
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="contrats_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    #[Route('/overdue-report', name: 'overdue_report')]
    public function overdueReport(): Response
    {
        $overduePayments = $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->leftJoin('p.loanContract', 'lc')
            ->leftJoin('lc.loanApplication', 'la')
            ->leftJoin('la.user', 'u')
            ->where('p.dueDate < :now')
            ->andWhere('p.status = :pending')
            ->setParameter('now', new \DateTime())
            ->setParameter('pending', PaymentStatus::PENDING)
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();

        // Regrouper par contrat
        $overdueByContract = [];
        $totalOverdue = 0;
        
        foreach ($overduePayments as $payment) {
            $contractId = $payment->getLoanContract()->getId();
            if (!isset($overdueByContract[$contractId])) {
                $overdueByContract[$contractId] = [
                    'contract' => $payment->getLoanContract(),
                    'user' => $payment->getLoanContract()->getLoanApplication()->getUser(),
                    'payments' => [],
                    'total_amount' => 0,
                    'days_overdue' => 0
                ];
            }
            
            $overdueByContract[$contractId]['payments'][] = $payment;
            $overdueByContract[$contractId]['total_amount'] += $payment->getAmount();
            $totalOverdue += $payment->getAmount();
            
            // Calculer le nombre de jours de retard
            $daysOverdue = (new \DateTime())->diff($payment->getDueDate())->days;
            if ($daysOverdue > $overdueByContract[$contractId]['days_overdue']) {
                $overdueByContract[$contractId]['days_overdue'] = $daysOverdue;
            }
        }

        return $this->render('admin/contracts/overdue_report.html.twig', [
            'overdueByContract' => $overdueByContract,
            'totalOverdue' => $totalOverdue,
            'totalContracts' => count($overdueByContract),
            'totalPayments' => count($overduePayments),
        ]);
    }

    private function getContractStats(): array
    {
        $total = $this->entityManager->getRepository(LoanContract::class)->count([]);
        $active = $this->entityManager->getRepository(LoanContract::class)->count(['status' => 'ACTIVE']);
        $completed = $this->entityManager->getRepository(LoanContract::class)->count(['status' => 'COMPLETED']);
        $suspended = $this->entityManager->getRepository(LoanContract::class)->count(['status' => 'SUSPENDED']);
        
        $totalAmount = $this->entityManager->getRepository(LoanContract::class)
            ->createQueryBuilder('lc')
            ->select('SUM(CAST(lc.originalAmount as DECIMAL(15,2)))')
            ->where('lc.status = :status')
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'total' => $total,
            'active' => $active,
            'completed' => $completed,
            'suspended' => $suspended,
            'total_amount' => $totalAmount,
        ];
    }

    private function getContractStatistics(LoanContract $contract): array
    {
        $payments = $this->entityManager->getRepository(Payment::class)
            ->findBy(['loanContract' => $contract]);

        $totalPayments = count($payments);
        $paidPayments = count(array_filter($payments, fn($p) => $p->getStatus() === PaymentStatus::PAID));
        $pendingPayments = count(array_filter($payments, fn($p) => $p->getStatus() === PaymentStatus::PENDING));
        
        $now = new \DateTime();
        $overduePayments = count(array_filter($payments, fn($p) => 
            $p->getStatus() === PaymentStatus::PENDING && $p->getDueDate() < $now
        ));

        return [
            'total_payments' => $totalPayments,
            'paid_payments' => $paidPayments,
            'pending_payments' => $pendingPayments,
            'overdue_payments' => $overduePayments,
            'completion_rate' => $totalPayments > 0 ? round(($paidPayments / $totalPayments) * 100, 1) : 0,
        ];
    }

    private function calculateFinancialSummary(LoanContract $contract, array $payments): array
    {
        $totalScheduled = array_sum(array_map(fn($p) => $p->getAmount(), $payments));
        $totalPaid = array_sum(array_map(fn($p) => 
            $p->getStatus() === PaymentStatus::PAID ? $p->getAmount() : 0, $payments
        ));
        $totalRemaining = $totalScheduled - $totalPaid;

        return [
            'original_amount' => $contract->getOriginalAmount(),
            'total_scheduled' => $totalScheduled,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'interest_earned' => $totalScheduled - $contract->getOriginalAmount(),
            'payment_progress' => $totalScheduled > 0 ? round(($totalPaid / $totalScheduled) * 100, 1) : 0,
        ];
    }
}