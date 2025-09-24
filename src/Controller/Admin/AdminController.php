<?php

namespace App\Controller\Admin;

use App\Service\AnalyticsService;
use App\Repository\UserRepository;
use App\Repository\LoanApplicationRepository;
use App\Repository\DocumentRepository;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private AnalyticsService $analyticsService,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private LoanApplicationRepository $loanApplicationRepository,
        private DocumentRepository $documentRepository,
        private DocumentService $documentService
    ) {
    }

    #[Route('/', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // Obtenir toutes les données via le service d'analytics
        $overview = $this->analyticsService->getDashboardOverview();
        $kpis = $this->analyticsService->getKPIs();
        $chartData = $this->analyticsService->getChartsData();
        $recentActivities = $this->analyticsService->getRecentActivities(15);
        $documentStats = $this->analyticsService->getDocumentStatistics();

        return $this->render('admin/dashboard.html.twig', [
            'overview' => $overview,
            'kpis' => $kpis,
            'chart_data' => $chartData,
            'recent_activities' => $recentActivities,
            'document_stats' => $documentStats,
        ]);
    }

    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(): Response
    {
        $userAnalytics = $this->analyticsService->getUserAnalytics();
        $allUsers = $this->userRepository->findBy([], ['createdAt' => 'DESC'], 50);

        return $this->render('admin/users.html.twig', [
            'users' => $allUsers,
            'analytics' => $userAnalytics,
        ]);
    }

    #[Route('/loan-applications', name: 'app_admin_loan_applications', methods: ['GET'])]
    public function loanApplications(): Response
    {
        $loanAnalytics = $this->analyticsService->getLoanAnalytics();
        $allLoans = $this->loanApplicationRepository->findBy([], ['createdAt' => 'DESC'], 50);
        $loansByStatus = [
            'pending' => $this->loanApplicationRepository->findByStatus(\App\Enum\LoanApplicationStatus::SUBMITTED),
            'under_review' => $this->loanApplicationRepository->findByStatus(\App\Enum\LoanApplicationStatus::UNDER_REVIEW),
            'approved' => $this->loanApplicationRepository->findByStatus(\App\Enum\LoanApplicationStatus::APPROVED),
            'rejected' => $this->loanApplicationRepository->findByStatus(\App\Enum\LoanApplicationStatus::REJECTED),
        ];

        return $this->render('admin/loan_applications.html.twig', [
            'loan_applications' => $allLoans,
            'loans_by_status' => $loansByStatus,
            'analytics' => $loanAnalytics,
        ]);
    }

    #[Route('/analytics', name: 'app_admin_analytics', methods: ['GET'])]
    public function analytics(): Response
    {
        // Données analytiques complètes
        $userAnalytics = $this->analyticsService->getUserAnalytics();
        $loanAnalytics = $this->analyticsService->getLoanAnalytics();
        $documentStats = $this->analyticsService->getDocumentStatistics();
        $chartData = $this->analyticsService->getChartsData();
        $overview = $this->analyticsService->getDashboardOverview();

        return $this->render('admin/analytics.html.twig', [
            'user_analytics' => $userAnalytics,
            'loan_analytics' => $loanAnalytics,
            'document_stats' => $documentStats,
            'chart_data' => $chartData,
            'overview' => $overview,
        ]);
    }

    #[Route('/documents', name: 'app_admin_documents', methods: ['GET'])]
    public function documents(): Response
    {
        $documentStats = $this->analyticsService->getDocumentStatistics();
        $pendingDocuments = $this->documentRepository->findBy(['status' => 'pending'], ['uploadedAt' => 'DESC'], 30);
        $recentDocuments = $this->documentRepository->findBy([], ['uploadedAt' => 'DESC'], 50);

        return $this->render('admin/documents.html.twig', [
            'pending_documents' => $pendingDocuments,
            'recent_documents' => $recentDocuments,
            'document_stats' => $documentStats,
        ]);
    }

    #[Route('/settings', name: 'app_admin_settings', methods: ['GET', 'POST'])]
    public function settings(): Response
    {
        // Configuration système, tarifs, paramètres globaux
        return $this->render('admin/settings.html.twig', [
            'system_settings' => $this->getSystemSettings(),
        ]);
    }

    #[Route('/reports', name: 'app_admin_reports', methods: ['GET'])]
    public function reports(): Response
    {
        $overview = $this->analyticsService->getDashboardOverview();
        $userAnalytics = $this->analyticsService->getUserAnalytics();
        $loanAnalytics = $this->analyticsService->getLoanAnalytics();
        $documentStats = $this->analyticsService->getDocumentStatistics();

        return $this->render('admin/reports.html.twig', [
            'overview' => $overview,
            'user_analytics' => $userAnalytics,
            'loan_analytics' => $loanAnalytics,
            'document_stats' => $documentStats,
        ]);
    }

    private function getSystemSettings(): array
    {
        return [
            'app_name' => 'Oragon Lending',
            'version' => '1.0.0',
            'max_loan_amount' => 10000000, // 10M FCFA
            'min_loan_amount' => 100000,   // 100K FCFA
            'interest_rates' => [
                'personal' => 8.5,
                'business' => 12.0,
                'emergency' => 15.0,
            ],
            'document_retention_days' => 2555, // 7 ans
            'email_notifications' => true,
            'sms_notifications' => false,
        ];
    }
}