<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Repository\LoanApplicationRepository;
use App\Repository\DocumentRepository;

class AnalyticsService
{
    public function __construct(
        private UserRepository $userRepository,
        private LoanApplicationRepository $loanApplicationRepository,
        private DocumentRepository $documentRepository
    ) {
    }

    /**
     * Get dashboard overview statistics
     */
    public function getDashboardOverview(): array
    {
        return [
            'users' => [
                'total' => $this->userRepository->countTotalUsers(),
                'new_this_month' => $this->userRepository->countNewUsersThisMonth(),
                'active' => $this->userRepository->countActiveUsers(),
                'growth_rate' => $this->calculateUserGrowthRate(),
            ],
            'loans' => [
                'total' => $this->loanApplicationRepository->countTotalLoans(),
                'pending' => $this->loanApplicationRepository->countPendingLoans(),
                'approved' => $this->loanApplicationRepository->countApprovedLoans(),
                'rejected' => $this->loanApplicationRepository->countRejectedLoans(),
                'this_month' => $this->loanApplicationRepository->countLoansThisMonth(),
            ],
            'amounts' => [
                'total' => $this->loanApplicationRepository->getTotalLoanAmount(),
                'approved' => $this->loanApplicationRepository->getApprovedLoanAmount(),
                'pending' => $this->loanApplicationRepository->getPendingLoanAmount(),
                'average' => $this->loanApplicationRepository->getAverageLoanAmount(),
            ],
            'approval_rate' => $this->loanApplicationRepository->getLoanApprovalRate(),
        ];
    }

    /**
     * Get user analytics data
     */
    public function getUserAnalytics(): array
    {
        return [
            'statistics' => $this->userRepository->getUserStatistics(),
            'activity' => $this->userRepository->getUserActivityStatistics(),
            'registration_trend' => $this->userRepository->getUsersRegistrationTrend(),
            'registration_by_day' => $this->userRepository->getUsersRegistrationByDay(),
            'recent_users' => $this->userRepository->findRecentUsers(10),
        ];
    }

    /**
     * Get loan analytics data
     */
    public function getLoanAnalytics(): array
    {
        return [
            'statistics' => $this->loanApplicationRepository->getComprehensiveStatistics(),
            'trend' => $this->loanApplicationRepository->getLoanApplicationsTrend(),
            'by_day' => $this->loanApplicationRepository->getLoanApplicationsByDay(),
            'recent_applications' => $this->loanApplicationRepository->getRecentLoanApplications(10),
            'approval_rate' => $this->loanApplicationRepository->getLoanApprovalRate(),
        ];
    }

    /**
     * Get document verification statistics
     */
    public function getDocumentStatistics(): array
    {
        $em = $this->documentRepository->createQueryBuilder('d')->getEntityManager();
        
        // Count documents by status
        $statusCounts = $em->createQueryBuilder()
            ->select('d.status, COUNT(d.id) as count')
            ->from('App\Entity\Document', 'd')
            ->groupBy('d.status')
            ->getQuery()
            ->getArrayResult();
        
        $counts = [];
        foreach ($statusCounts as $row) {
            $counts[$row['status']] = $row['count'];
        }
        
        // Count documents by type
        $typeCounts = $em->createQueryBuilder()
            ->select('d.documentType, COUNT(d.id) as count')
            ->from('App\Entity\Document', 'd')
            ->groupBy('d.documentType')
            ->getQuery()
            ->getArrayResult();
        
        $types = [];
        foreach ($typeCounts as $row) {
            $types[$row['documentType']] = $row['count'];
        }
        
        // Total documents
        $totalDocuments = $em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from('App\Entity\Document', 'd')
            ->getQuery()
            ->getSingleScalarResult();
        
        return [
            'total' => $totalDocuments,
            'by_status' => $counts,
            'by_type' => $types,
            'pending' => $counts['pending'] ?? 0,
            'approved' => $counts['approved'] ?? 0,
            'rejected' => $counts['rejected'] ?? 0,
        ];
    }

    /**
     * Calculate user growth rate (current month vs previous month)
     */
    private function calculateUserGrowthRate(): float
    {
        $currentMonth = $this->userRepository->countNewUsersThisMonth();
        
        $firstDayOfLastMonth = new \DateTimeImmutable('first day of last month 00:00:00');
        $lastDayOfLastMonth = new \DateTimeImmutable('last day of last month 23:59:59');
        
        $lastMonth = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :start AND u.createdAt <= :end')
            ->setParameter('start', $firstDayOfLastMonth)
            ->setParameter('end', $lastDayOfLastMonth)
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($lastMonth == 0) {
            return $currentMonth > 0 ? 100.0 : 0.0;
        }
        
        return round((($currentMonth - $lastMonth) / $lastMonth) * 100, 2);
    }

    /**
     * Get charts data for dashboard
     */
    public function getChartsData(): array
    {
        return [
            'user_registration' => $this->prepareChartData(
                $this->userRepository->getUsersRegistrationByDay(),
                'date',
                'count'
            ),
            'loan_applications' => $this->prepareChartData(
                $this->loanApplicationRepository->getLoanApplicationsByDay(),
                'date',
                'count'
            ),
            'loan_amounts' => $this->prepareChartData(
                $this->loanApplicationRepository->getLoanApplicationsByDay(),
                'date',
                'total_amount'
            ),
            'user_trend_monthly' => $this->prepareChartData(
                $this->userRepository->getUsersRegistrationTrend(),
                'month',
                'count'
            ),
            'loan_trend_monthly' => $this->prepareChartData(
                $this->loanApplicationRepository->getLoanApplicationsTrend(),
                'month',
                'count'
            ),
        ];
    }

    /**
     * Prepare data for charts (labels and values)
     */
    private function prepareChartData(array $data, string $labelKey, string $valueKey): array
    {
        $labels = [];
        $values = [];
        
        foreach ($data as $item) {
            $labels[] = $item[$labelKey] ?? '';
            $values[] = $item[$valueKey] ?? 0;
        }
        
        return [
            'labels' => $labels,
            'data' => $values,
        ];
    }

    /**
     * Get recent activities for dashboard
     */
    public function getRecentActivities(int $limit = 20): array
    {
        $activities = [];
        
        // Recent loan applications
        $recentLoans = $this->loanApplicationRepository->getRecentLoanApplications(10);
        foreach ($recentLoans as $loan) {
            $activities[] = [
                'type' => 'loan_application',
                'date' => $loan->getCreatedAt(),
                'user' => $loan->getUser(),
                'data' => $loan,
            ];
        }
        
        // Recent user registrations
        $recentUsers = $this->userRepository->findRecentUsers(10);
        foreach ($recentUsers as $user) {
            $activities[] = [
                'type' => 'user_registration',
                'date' => $user->getCreatedAt(),
                'user' => $user,
                'data' => $user,
            ];
        }
        
        // Sort by date (most recent first)
        usort($activities, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });
        
        return array_slice($activities, 0, $limit);
    }

    /**
     * Get key performance indicators (KPIs)
     */
    public function getKPIs(): array
    {
        $userStats = $this->userRepository->getUserStatistics();
        $loanStats = $this->loanApplicationRepository->getComprehensiveStatistics();
        $approvalRate = $this->loanApplicationRepository->getLoanApprovalRate();
        
        return [
            [
                'label' => 'Total Users',
                'value' => $userStats['total'],
                'change' => $this->calculateUserGrowthRate(),
                'icon' => 'users',
                'color' => 'primary',
            ],
            [
                'label' => 'Total Loans',
                'value' => $loanStats['total_applications'],
                'change' => $this->calculateLoanGrowthRate(),
                'icon' => 'file-text',
                'color' => 'info',
            ],
            [
                'label' => 'Approved Amount',
                'value' => number_format($loanStats['approved_loan_amount'], 0, ',', ' ') . ' FCFA',
                'subtitle' => 'Total approved',
                'icon' => 'dollar-sign',
                'color' => 'success',
            ],
            [
                'label' => 'Approval Rate',
                'value' => $approvalRate['approval_rate'] . '%',
                'subtitle' => $approvalRate['approved'] . '/' . $approvalRate['total'] . ' approved',
                'icon' => 'check-circle',
                'color' => 'warning',
            ],
        ];
    }

    /**
     * Calculate loan growth rate (current month vs previous month)
     */
    private function calculateLoanGrowthRate(): float
    {
        $currentMonth = $this->loanApplicationRepository->countLoansThisMonth();
        
        $firstDayOfLastMonth = new \DateTime('first day of last month 00:00:00');
        $lastDayOfLastMonth = new \DateTime('last day of last month 23:59:59');
        
        $lastMonth = $this->loanApplicationRepository->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->andWhere('la.createdAt >= :start AND la.createdAt <= :end')
            ->setParameter('start', $firstDayOfLastMonth)
            ->setParameter('end', $lastDayOfLastMonth)
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($lastMonth == 0) {
            return $currentMonth > 0 ? 100.0 : 0.0;
        }
        
        return round((($currentMonth - $lastMonth) / $lastMonth) * 100, 2);
    }
}