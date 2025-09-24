<?php

namespace App\Controller;

use App\Repository\LoanApplicationRepository;
use App\Repository\AccountVerificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private LoanApplicationRepository $loanApplicationRepository,
        private AccountVerificationRepository $accountVerificationRepository
    ) {
    }

    #[Route('/', name: 'dashboard_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Get user's loan applications
        $loanApplications = $this->loanApplicationRepository->findBy(
            ['user' => $user],
            ['submittedAt' => 'DESC'],
            5 // Limit to 5 most recent
        );

        // Get user's verification status
        $verifications = $this->accountVerificationRepository->findBy(
            ['user' => $user],
            ['submittedAt' => 'DESC']
        );

        // Calculate verification progress
        $requiredVerifications = ['IDENTITY', 'ADDRESS', 'INCOME'];
        $completedVerifications = [];
        $pendingVerifications = [];
        
        foreach ($verifications as $verification) {
            if ($verification->getStatus() === 'VERIFIED') {
                $completedVerifications[] = $verification->getVerificationType();
            } elseif ($verification->getStatus() === 'PENDING') {
                $pendingVerifications[] = $verification->getVerificationType();
            }
        }

        $verificationProgress = count($completedVerifications) / count($requiredVerifications) * 100;

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'loanApplications' => $loanApplications,
            'verifications' => $verifications,
            'verificationProgress' => $verificationProgress,
            'completedVerifications' => $completedVerifications,
            'pendingVerifications' => $pendingVerifications,
            'requiredVerifications' => $requiredVerifications,
        ]);
    }

    #[Route('/profile', name: 'dashboard_profile')]
    public function profile(): Response
    {
        $user = $this->getUser();

        return $this->render('dashboard/profile.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/applications', name: 'dashboard_applications')]
    public function applications(): Response
    {
        $user = $this->getUser();
        
        $loanApplications = $this->loanApplicationRepository->findBy(
            ['user' => $user],
            ['submittedAt' => 'DESC']
        );

        return $this->render('dashboard/applications.html.twig', [
            'loanApplications' => $loanApplications,
        ]);
    }

    #[Route('/verifications', name: 'dashboard_verifications')]
    public function verifications(): Response
    {
        $user = $this->getUser();
        
        $verifications = $this->accountVerificationRepository->findBy(
            ['user' => $user],
            ['submittedAt' => 'DESC']
        );

        return $this->render('dashboard/verifications.html.twig', [
            'verifications' => $verifications,
        ]);
    }
}