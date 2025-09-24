<?php

namespace App\Controller\Admin;

use App\Entity\LoanApplication;
use App\Entity\LoanContract;
use App\Repository\LoanApplicationRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/loan-applications')]
#[IsGranted('ROLE_ADMIN')]
class AdminLoanApplicationController extends AbstractController
{
    public function __construct(
        private LoanApplicationRepository $loanApplicationRepository,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private NotificationService $notificationService
    ) {
    }

    #[Route('/', name: 'admin_loan_applications_index')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $search = $request->query->get('search', '');
        
        $queryBuilder = $this->loanApplicationRepository->createQueryBuilder('la')
            ->leftJoin('la.user', 'u')
            ->leftJoin('la.loanType', 'lt')
            ->orderBy('la.submittedAt', 'DESC');

        // Filter by status
        if ($status !== 'all') {
            $queryBuilder->andWhere('la.status = :status')
                        ->setParameter('status', $status);
        }

        // Search filter
        if ($search) {
            $queryBuilder->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search OR lt.name LIKE :search')
                        ->setParameter('search', '%' . $search . '%');
        }

        $applications = $queryBuilder->getQuery()->getResult();

        // Get statistics
        $stats = [
            'total' => $this->loanApplicationRepository->count([]),
            'pending' => $this->loanApplicationRepository->count(['status' => 'SUBMITTED']),
            'under_review' => $this->loanApplicationRepository->count(['status' => 'UNDER_REVIEW']),
            'approved' => $this->loanApplicationRepository->count(['status' => 'APPROVED']),
            'rejected' => $this->loanApplicationRepository->count(['status' => 'REJECTED']),
            'disbursed' => $this->loanApplicationRepository->count(['status' => 'DISBURSED']),
        ];

        return $this->render('admin/loan_applications/index.html.twig', [
            'applications' => $applications,
            'stats' => $stats,
            'current_status' => $status,
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'admin_loan_application_detail', requirements: ['id' => '\d+'])]
    public function detail(LoanApplication $application): Response
    {
        return $this->render('admin/loan_applications/detail.html.twig', [
            'application' => $application,
        ]);
    }

    #[Route('/{id}/review', name: 'admin_loan_application_review', requirements: ['id' => '\d+'])]
    public function review(Request $request, LoanApplication $application): Response
    {
        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            $comments = $request->request->get('comments', '');

            switch ($action) {
                case 'start_review':
                    $application->setStatus('UNDER_REVIEW');
                    $application->setReviewedAt(new \DateTime());
                    
                    // Créer une notification pour l'utilisateur
                    $this->notificationService->createNotification(
                        $application->getUser(),
                        'Demande en cours d\'étude',
                        'Votre demande de prêt est maintenant en cours d\'examen par nos équipes.',
                        'loan_application',
                        "/loan-application/{$application->getId()}",
                        ['loan_application_id' => $application->getId(), 'status' => 'UNDER_REVIEW']
                    );
                    
                    $this->addFlash('success', 'La demande est maintenant en cours d\'étude.');
                    break;

                case 'approve':
                    $interestRate = $request->request->get('interest_rate', $application->getLoanType()->getBaseInterestRate());
                    $monthlyPayment = $request->request->get('monthly_payment');
                    $totalAmount = $request->request->get('total_amount');

                    $application->setStatus('APPROVED');
                    $application->setApprovedAt(new \DateTime());
                    $application->setInterestRate((float) $interestRate);
                    
                    if ($monthlyPayment) {
                        $application->setMonthlyPayment((float) $monthlyPayment);
                    }
                    if ($totalAmount) {
                        $application->setTotalAmount((float) $totalAmount);
                    }

                    // Create loan contract
                    $this->createLoanContract($application);

                    // Créer une notification d'approbation
                    $this->notificationService->createLoanApplicationNotification(
                        $application->getUser(),
                        'approved',
                        $application->getId()
                    );

                    $this->addFlash('success', 'La demande a été approuvée et le contrat généré.');
                    break;

                case 'reject':
                    $rejectionReason = $request->request->get('rejection_reason', '');
                    $application->setStatus('REJECTED');
                    $application->setRejectedAt(new \DateTime());
                    $application->setRejectionReason($rejectionReason);
                    
                    // Créer une notification de rejet
                    $this->notificationService->createLoanApplicationNotification(
                        $application->getUser(),
                        'rejected',
                        $application->getId()
                    );
                    
                    $this->addFlash('success', 'La demande a été rejetée.');
                    break;
            }

            $this->entityManager->flush();
            return $this->redirectToRoute('admin_loan_application_detail', ['id' => $application->getId()]);
        }

        return $this->render('admin/loan_applications/review.html.twig', [
            'application' => $application,
        ]);
    }

    #[Route('/{id}/documents', name: 'admin_loan_application_documents', requirements: ['id' => '\d+'])]
    public function documents(Request $request, LoanApplication $application): Response
    {
        if ($request->isMethod('POST')) {
            $documentId = $request->request->get('document_id');
            $action = $request->request->get('action');
            
            $document = null;
            foreach ($application->getDocuments() as $doc) {
                if ($doc->getId() == $documentId) {
                    $document = $doc;
                    break;
                }
            }
            
            if ($document) {
                if ($action === 'verify') {
                    $document->setIsVerified(true);
                    $document->setVerifiedAt(new \DateTime());
                    $document->setVerifiedBy($this->getUser());
                    $this->addFlash('success', 'Document vérifié.');
                } elseif ($action === 'reject') {
                    $document->setIsVerified(false);
                    $this->addFlash('warning', 'Document marqué comme non vérifié.');
                }
                
                $this->entityManager->flush();
            }
        }

        return $this->render('admin/loan_applications/documents.html.twig', [
            'application' => $application,
        ]);
    }

    #[Route('/statistics', name: 'admin_loan_applications_stats')]
    public function statistics(): Response
    {
        // Monthly applications stats
        $monthlyStats = $this->entityManager->createQuery('
            SELECT 
                MONTH(la.submittedAt) as month,
                YEAR(la.submittedAt) as year,
                COUNT(la.id) as count,
                SUM(la.requestedAmount) as total_amount
            FROM App\Entity\LoanApplication la 
            WHERE la.submittedAt >= :start_date
            GROUP BY YEAR(la.submittedAt), MONTH(la.submittedAt)
            ORDER BY year DESC, month DESC
        ')
        ->setParameter('start_date', new \DateTime('-12 months'))
        ->getResult();

        // Approval rates by loan type
        $approvalRates = $this->entityManager->createQuery('
            SELECT 
                lt.name as loan_type,
                COUNT(la.id) as total_applications,
                SUM(CASE WHEN la.status = \'APPROVED\' THEN 1 ELSE 0 END) as approved_count
            FROM App\Entity\LoanApplication la
            JOIN la.loanType lt
            GROUP BY lt.id, lt.name
        ')->getResult();

        return $this->render('admin/loan_applications/statistics.html.twig', [
            'monthly_stats' => $monthlyStats,
            'approval_rates' => $approvalRates,
        ]);
    }

    private function createLoanContract(LoanApplication $application): void
    {
        $contract = new LoanContract();
        $contract->setLoanApplication($application);
        $contract->setContractNumber('CONT-' . date('Y') . '-' . str_pad($application->getId(), 6, '0', STR_PAD_LEFT));
        $contract->setStartDate(new \DateTime());
        $contract->setEndDate(new \DateTime('+' . $application->getDuration() . ' months'));
        $contract->setIsActive(false); // Will be activated when signed
        
        // Generate payment schedule
        $paymentSchedule = $this->generatePaymentSchedule($application);
        $contract->setPaymentSchedule($paymentSchedule);

        $this->entityManager->persist($contract);
    }

    private function generatePaymentSchedule(LoanApplication $application): array
    {
        $schedule = [];
        $startDate = new \DateTime('first day of next month');
        $monthlyPayment = $application->getMonthlyPayment();
        $remainingPrincipal = $application->getRequestedAmount();
        $monthlyRate = $application->getInterestRate() / 100 / 12;

        for ($i = 1; $i <= $application->getDuration(); $i++) {
            $interestPayment = $remainingPrincipal * $monthlyRate;
            $principalPayment = $monthlyPayment - $interestPayment;
            $remainingPrincipal -= $principalPayment;

            $paymentDate = clone $startDate;
            $paymentDate->modify('+' . ($i - 1) . ' months');

            $schedule[] = [
                'payment_number' => $i,
                'due_date' => $paymentDate->format('Y-m-d'),
                'total_amount' => round($monthlyPayment, 2),
                'principal_amount' => round($principalPayment, 2),
                'interest_amount' => round($interestPayment, 2),
                'remaining_principal' => round(max(0, $remainingPrincipal), 2),
            ];
        }

        return $schedule;
    }
}