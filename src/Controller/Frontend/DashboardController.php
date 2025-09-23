<?php

namespace App\Controller\Frontend;

use App\Entity\LoanApplication;
use App\Entity\AccountVerification;
use App\Repository\LoanApplicationRepository;
use App\Repository\AccountVerificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard', name: 'app_dashboard_')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoanApplicationRepository $loanApplicationRepository,
        private AccountVerificationRepository $verificationRepository
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Récupérer les demandes de prêt de l'utilisateur
        $loanApplications = $this->loanApplicationRepository->findBy(
            ['user' => $user], 
            ['submittedAt' => 'DESC'],
            5 // Limit to last 5
        );

        // Récupérer les vérifications de compte
        $verifications = $this->verificationRepository->findBy(
            ['user' => $user], 
            ['submittedAt' => 'DESC']
        );

        // Statistiques utilisateur
        $stats = [
            'totalApplications' => $this->loanApplicationRepository->count(['user' => $user]),
            'pendingApplications' => $this->loanApplicationRepository->count([
                'user' => $user, 
                'status' => [\App\Enum\LoanApplicationStatus::SUBMITTED, \App\Enum\LoanApplicationStatus::UNDER_REVIEW]
            ]),
            'approvedApplications' => $this->loanApplicationRepository->count([
                'user' => $user, 
                'status' => \App\Enum\LoanApplicationStatus::APPROVED
            ]),
            'verificationStatus' => $user->getVerificationStatus(),
            'isVerified' => $user->isVerified(),
            'totalVerifications' => count($verifications),
            'pendingVerifications' => $this->verificationRepository->count([
                'user' => $user, 
                'status' => \App\Enum\VerificationStatus::PENDING
            ])
        ];

        // Dernières activités
        $recentActivities = $this->getRecentActivities($user);

        return $this->render('frontend/dashboard/index.html.twig', [
            'user' => $user,
            'loanApplications' => $loanApplications,
            'verifications' => $verifications,
            'stats' => $stats,
            'recentActivities' => $recentActivities
        ]);
    }

    #[Route('/mes-demandes', name: 'applications')]
    public function applications(): Response
    {
        $user = $this->getUser();
        
        $applications = $this->loanApplicationRepository->findBy(
            ['user' => $user], 
            ['submittedAt' => 'DESC']
        );

        return $this->render('frontend/dashboard/applications.html.twig', [
            'applications' => $applications,
            'user' => $user
        ]);
    }

    #[Route('/verification', name: 'verification')]
    public function verification(): Response
    {
        $user = $this->getUser();
        
        $verifications = $this->verificationRepository->findBy(
            ['user' => $user], 
            ['submittedAt' => 'DESC']
        );

        return $this->render('frontend/dashboard/verification.html.twig', [
            'verifications' => $verifications,
            'user' => $user
        ]);
    }

    #[Route('/profil', name: 'profile')]
    public function profile(): Response
    {
        $user = $this->getUser();

        return $this->render('frontend/dashboard/profile.html.twig', [
            'user' => $user
        ]);
    }

    private function getRecentActivities($user): array
    {
        $activities = [];

        // Récupérer les dernières demandes de prêt
        $recentApplications = $this->loanApplicationRepository->findBy(
            ['user' => $user], 
            ['submittedAt' => 'DESC'],
            3
        );

        foreach ($recentApplications as $application) {
            $activities[] = [
                'type' => 'loan_application',
                'title' => 'Demande de prêt ' . $application->getLoanType()->getName(),
                'description' => 'Montant: ' . number_format($application->getRequestedAmount(), 0, ',', ' ') . '€',
                'status' => $application->getStatus(),
                'date' => $application->getSubmittedAt(),
                'link' => $this->generateUrl('app_loan_application_detail', ['id' => $application->getId()])
            ];
        }

        // Récupérer les dernières vérifications
        $recentVerifications = $this->verificationRepository->findBy(
            ['user' => $user], 
            ['submittedAt' => 'DESC'],
            2
        );

        foreach ($recentVerifications as $verification) {
            $activities[] = [
                'type' => 'verification',
                'title' => 'Vérification ' . $verification->getVerificationType()->value,
                'description' => 'Document soumis pour vérification',
                'status' => $verification->getStatus(),
                'date' => $verification->getSubmittedAt(),
                'link' => $this->generateUrl('app_verification_index')
            ];
        }

        // Trier par date décroissante
        usort($activities, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return array_slice($activities, 0, 5); // Limiter à 5 activités
    }
}