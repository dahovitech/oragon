<?php

namespace App\Controller;

use App\Entity\AccountVerification;
use App\Repository\AccountVerificationRepository;
use App\Repository\LoanApplicationRepository;
use App\Repository\LoanContractRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountVerificationRepository $verificationRepository,
        private UserRepository $userRepository,
        private LoanApplicationRepository $loanApplicationRepository,
        private LoanContractRepository $loanContractRepository
    ) {
    }

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        // Get statistics for dashboard
        $stats = [
            'pendingVerifications' => count($this->verificationRepository->findPendingVerifications()),
            'totalUsers' => count($this->userRepository->findAll()),
            'verifiedUsers' => count($this->userRepository->findBy(['isVerified' => true])),
            'rejectedVerifications' => count($this->verificationRepository->findByStatus('REJECTED')),
            
            // Loan applications stats
            'totalApplications' => $this->loanApplicationRepository->count([]),
            'pendingApplications' => $this->loanApplicationRepository->count(['status' => 'SUBMITTED']),
            'underReviewApplications' => $this->loanApplicationRepository->count(['status' => 'UNDER_REVIEW']),
            'approvedApplications' => $this->loanApplicationRepository->count(['status' => 'APPROVED']),
            
            // Contracts stats
            'totalContracts' => $this->loanContractRepository->count([]),
            'activeContracts' => $this->loanContractRepository->count(['isActive' => true]),
            'signedContracts' => $this->loanContractRepository->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.signedAt IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult(),
        ];

        $recentVerifications = $this->verificationRepository->createQueryBuilder('v')
            ->orderBy('v.submittedAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $recentApplications = $this->loanApplicationRepository->createQueryBuilder('la')
            ->orderBy('la.submittedAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recentVerifications' => $recentVerifications,
            'recentApplications' => $recentApplications,
        ]);
    }

    #[Route('/verifications', name: 'admin_verifications')]
    public function verifications(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        
        if ($status === 'pending') {
            $verifications = $this->verificationRepository->findPendingVerifications();
        } elseif ($status === 'verified') {
            $verifications = $this->verificationRepository->findByStatus('VERIFIED');
        } elseif ($status === 'rejected') {
            $verifications = $this->verificationRepository->findByStatus('REJECTED');
        } else {
            $verifications = $this->verificationRepository->findBy([], ['submittedAt' => 'DESC']);
        }

        return $this->render('admin/verifications.html.twig', [
            'verifications' => $verifications,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/verification/{id}', name: 'admin_verification_detail')]
    public function verificationDetail(AccountVerification $verification): Response
    {
        return $this->render('admin/verification_detail.html.twig', [
            'verification' => $verification,
        ]);
    }

    #[Route('/verification/{id}/approve', name: 'admin_verification_approve', methods: ['POST'])]
    public function approveVerification(AccountVerification $verification, Request $request): Response
    {
        $comments = $request->request->get('comments', '');
        
        $verification->setStatus('VERIFIED');
        $verification->setVerifiedAt(new \DateTimeImmutable());
        $verification->setVerifiedBy($this->getUser());
        
        if ($comments) {
            $verification->setComments($comments);
        }

        // Update user verification status if all required verifications are complete
        $user = $verification->getUser();
        $this->updateUserVerificationStatus($user);

        $this->entityManager->flush();

        $this->addFlash('success', 'Vérification approuvée avec succès.');

        return $this->redirectToRoute('admin_verification_detail', ['id' => $verification->getId()]);
    }

    #[Route('/verification/{id}/reject', name: 'admin_verification_reject', methods: ['POST'])]
    public function rejectVerification(AccountVerification $verification, Request $request): Response
    {
        $comments = $request->request->get('comments');
        
        if (!$comments) {
            $this->addFlash('error', 'Veuillez fournir une raison pour le rejet.');
            return $this->redirectToRoute('admin_verification_detail', ['id' => $verification->getId()]);
        }

        $verification->setStatus('REJECTED');
        $verification->setVerifiedAt(new \DateTimeImmutable());
        $verification->setVerifiedBy($this->getUser());
        $verification->setComments($comments);

        $this->entityManager->flush();

        $this->addFlash('success', 'Vérification rejetée.');

        return $this->redirectToRoute('admin_verification_detail', ['id' => $verification->getId()]);
    }

    #[Route('/users', name: 'admin_users')]
    public function users(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $verificationStatus = $request->query->get('verification', 'all');

        $queryBuilder = $this->userRepository->createQueryBuilder('u');

        if ($search) {
            $queryBuilder->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                        ->setParameter('search', '%' . $search . '%');
        }

        if ($verificationStatus !== 'all') {
            if ($verificationStatus === 'verified') {
                $queryBuilder->andWhere('u.isVerified = :verified')
                            ->setParameter('verified', true);
            } elseif ($verificationStatus === 'unverified') {
                $queryBuilder->andWhere('u.isVerified = :verified OR u.isVerified IS NULL')
                            ->setParameter('verified', false);
            }
        }

        $users = $queryBuilder->orderBy('u.createdAt', 'DESC')
                            ->getQuery()
                            ->getResult();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'search' => $search,
            'currentVerificationStatus' => $verificationStatus,
        ]);
    }

    private function updateUserVerificationStatus($user): void
    {
        // Get all verifications for this user
        $verifications = $this->verificationRepository->findByUser($user);
        
        // Check if user has verified identity verification (minimum requirement)
        $hasVerifiedIdentity = false;
        
        foreach ($verifications as $verification) {
            if ($verification->getVerificationType() === 'IDENTITY' && $verification->getStatus() === 'VERIFIED') {
                $hasVerifiedIdentity = true;
                break;
            }
        }

        if ($hasVerifiedIdentity) {
            $user->setIsVerified(true);
            $user->setVerificationStatus('VERIFIED');
        } else {
            $user->setIsVerified(false);
            $user->setVerificationStatus('PENDING');
        }
    }
}