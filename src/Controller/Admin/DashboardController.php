<?php

namespace App\Controller\Admin;

use App\Entity\LoanApplication;
use App\Entity\LoanContract;
use App\Entity\User;
use App\Entity\Payment;
use App\Enum\LoanApplicationStatus;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'dashboard')]
    public function dashboard(): Response
    {
        // Statistiques principales
        $stats = $this->getDashboardStats();
        
        // Activité récente
        $recentActivity = $this->getRecentActivity();
        
        // Demandes en attente d'action
        $pendingApplications = $this->getPendingApplications();
        
        // Paiements en retard
        $overduePayments = $this->getOverduePayments();
        
        // Graphiques de données
        $chartData = $this->getChartData();

        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'pendingApplications' => $pendingApplications,
            'overduePayments' => $overduePayments,
            'chartData' => $chartData,
        ]);
    }

    #[Route('/stats/overview', name: 'stats_overview')]
    public function statsOverview(): JsonResponse
    {
        $stats = $this->getDashboardStats();
        return new JsonResponse($stats);
    }

    #[Route('/stats/monthly', name: 'stats_monthly')]
    public function monthlyStats(): JsonResponse
    {
        $monthlyData = $this->getMonthlyStats();
        return new JsonResponse($monthlyData);
    }

    #[Route('/notifications/count', name: 'notifications_count')]
    public function notificationsCount(): JsonResponse
    {
        $count = $this->getNotificationsCount();
        return new JsonResponse(['count' => $count]);
    }

    private function getDashboardStats(): array
    {
        $now = new \DateTime();
        $startOfMonth = new \DateTime('first day of this month');
        $startOfWeek = new \DateTime('monday this week');

        // Applications
        $totalApplications = $this->entityManager->getRepository(LoanApplication::class)->count([]);
        $pendingApplications = $this->entityManager->getRepository(LoanApplication::class)
            ->count(['status' => LoanApplicationStatus::SUBMITTED]);
        $monthlyApplications = $this->entityManager->getRepository(LoanApplication::class)
            ->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->where('la.createdAt >= :start')
            ->setParameter('start', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        // Contrats
        $activeContracts = $this->entityManager->getRepository(LoanContract::class)
            ->createQueryBuilder('lc')
            ->select('COUNT(lc.id)')
            ->where('lc.status = :status')
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getSingleScalarResult();

        // Montants
        $totalLoanAmount = $this->entityManager->getRepository(LoanContract::class)
            ->createQueryBuilder('lc')
            ->select('SUM(CAST(lc.originalAmount as DECIMAL(15,2)))')
            ->where('lc.status = :status')
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $monthlyDisbursed = $this->entityManager->getRepository(LoanContract::class)
            ->createQueryBuilder('lc')
            ->select('SUM(CAST(lc.originalAmount as DECIMAL(15,2)))')
            ->where('lc.signedAt >= :start')
            ->andWhere('lc.status = :status')
            ->setParameter('start', $startOfMonth)
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Paiements
        $overduePayments = $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.dueDate < :now')
            ->andWhere('p.status = :pending')
            ->setParameter('now', $now)
            ->setParameter('pending', PaymentStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $weeklyPayments = $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->select('SUM(CAST(p.amount as DECIMAL(15,2)))')
            ->where('p.paidAt >= :start')
            ->andWhere('p.status = :paid')
            ->setParameter('start', $startOfWeek)
            ->setParameter('paid', PaymentStatus::PAID)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Utilisateurs
        $totalUsers = $this->entityManager->getRepository(User::class)->count([]);
        $monthlyUsers = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :start')
            ->setParameter('start', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'applications' => [
                'total' => $totalApplications,
                'pending' => $pendingApplications,
                'monthly' => $monthlyApplications,
                'approval_rate' => $totalApplications > 0 ? round(($totalApplications - $pendingApplications) / $totalApplications * 100, 1) : 0
            ],
            'contracts' => [
                'active' => $activeContracts,
                'total_amount' => $totalLoanAmount,
                'monthly_disbursed' => $monthlyDisbursed,
                'average_amount' => $activeContracts > 0 ? round($totalLoanAmount / $activeContracts, 0) : 0
            ],
            'payments' => [
                'overdue' => $overduePayments,
                'weekly_collected' => $weeklyPayments,
                'collection_rate' => 98.5 // Simulation
            ],
            'users' => [
                'total' => $totalUsers,
                'monthly' => $monthlyUsers,
                'verified' => $this->entityManager->getRepository(User::class)->count(['isVerified' => true])
            ]
        ];
    }

    private function getRecentActivity(): array
    {
        $activities = [];

        // Récentes demandes
        $recentApplications = $this->entityManager->getRepository(LoanApplication::class)
            ->createQueryBuilder('la')
            ->leftJoin('la.user', 'u')
            ->leftJoin('la.loanType', 'lt')
            ->orderBy('la.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($recentApplications as $app) {
            $activities[] = [
                'type' => 'application',
                'icon' => 'fa-file-alt',
                'color' => 'primary',
                'title' => 'Nouvelle demande de prêt',
                'description' => "{$app->getUser()->getFirstName()} {$app->getUser()->getLastName()} - {$app->getRequestedAmount()}€",
                'time' => $app->getCreatedAt(),
                'link' => $this->generateUrl('admin_loan_applications_show', ['id' => $app->getId()])
            ];
        }

        // Contrats signés récents
        $recentContracts = $this->entityManager->getRepository(LoanContract::class)
            ->createQueryBuilder('lc')
            ->leftJoin('lc.loanApplication', 'la')
            ->leftJoin('la.user', 'u')
            ->where('lc.signedAt IS NOT NULL')
            ->orderBy('lc.signedAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        foreach ($recentContracts as $contract) {
            $activities[] = [
                'type' => 'contract',
                'icon' => 'fa-pen',
                'color' => 'success',
                'title' => 'Contrat signé',
                'description' => "Contrat #{$contract->getContractNumber()} - {$contract->getOriginalAmount()}€",
                'time' => $contract->getSignedAt(),
                'link' => $this->generateUrl('admin_contracts_show', ['id' => $contract->getId()])
            ];
        }

        // Trier par date
        usort($activities, function($a, $b) {
            return $b['time'] <=> $a['time'];
        });

        return array_slice($activities, 0, 10);
    }

    private function getPendingApplications(): array
    {
        return $this->entityManager->getRepository(LoanApplication::class)
            ->createQueryBuilder('la')
            ->leftJoin('la.user', 'u')
            ->leftJoin('la.loanType', 'lt')
            ->where('la.status IN (:statuses)')
            ->setParameter('statuses', [
                LoanApplicationStatus::SUBMITTED,
                LoanApplicationStatus::UNDER_REVIEW,
                LoanApplicationStatus::DOCUMENTS_REQUESTED
            ])
            ->orderBy('la.createdAt', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    private function getOverduePayments(): array
    {
        $now = new \DateTime();
        
        return $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->leftJoin('p.loanContract', 'lc')
            ->leftJoin('lc.loanApplication', 'la')
            ->leftJoin('la.user', 'u')
            ->where('p.dueDate < :now')
            ->andWhere('p.status = :pending')
            ->setParameter('now', $now)
            ->setParameter('pending', PaymentStatus::PENDING)
            ->orderBy('p.dueDate', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    private function getChartData(): array
    {
        // Données pour graphiques
        $monthlyData = $this->getMonthlyStats();
        
        // Répartition des statuts d'applications
        $statusDistribution = $this->entityManager->getRepository(LoanApplication::class)
            ->createQueryBuilder('la')
            ->select('la.status, COUNT(la.id) as count')
            ->groupBy('la.status')
            ->getQuery()
            ->getResult();

        // Évolution des montants
        $amountEvolution = $this->entityManager->getRepository(LoanContract::class)
            ->createQueryBuilder('lc')
            ->select('YEAR(lc.signedAt) as year, MONTH(lc.signedAt) as month, SUM(CAST(lc.originalAmount as DECIMAL(15,2))) as total')
            ->where('lc.signedAt IS NOT NULL')
            ->andWhere('lc.signedAt >= :sixMonthsAgo')
            ->setParameter('sixMonthsAgo', new \DateTime('-6 months'))
            ->groupBy('year, month')
            ->orderBy('year, month')
            ->getQuery()
            ->getResult();

        return [
            'monthly' => $monthlyData,
            'status_distribution' => $statusDistribution,
            'amount_evolution' => $amountEvolution
        ];
    }

    private function getMonthlyStats(): array
    {
        $sixMonthsAgo = new \DateTime('-6 months');
        
        return $this->entityManager->getRepository(LoanApplication::class)
            ->createQueryBuilder('la')
            ->select('YEAR(la.createdAt) as year, MONTH(la.createdAt) as month, COUNT(la.id) as applications')
            ->where('la.createdAt >= :start')
            ->setParameter('start', $sixMonthsAgo)
            ->groupBy('year, month')
            ->orderBy('year, month')
            ->getQuery()
            ->getResult();
    }

    private function getNotificationsCount(): int
    {
        $count = 0;
        
        // Demandes en attente
        $count += $this->entityManager->getRepository(LoanApplication::class)
            ->count(['status' => LoanApplicationStatus::SUBMITTED]);
        
        // Paiements en retard
        $count += $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.dueDate < :now')
            ->andWhere('p.status = :pending')
            ->setParameter('now', new \DateTime())
            ->setParameter('pending', PaymentStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
        
        return $count;
    }
}