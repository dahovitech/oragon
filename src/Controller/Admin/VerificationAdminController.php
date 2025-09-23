<?php

namespace App\Controller\Admin;

use App\Entity\AccountVerification;
use App\Form\VerificationReviewFormType;
use App\Enum\VerificationStatus;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/verification', name: 'admin_verification_')]
#[IsGranted('ROLE_ADMIN')]
class VerificationAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'pending');
        
        $queryBuilder = $this->entityManager
            ->getRepository(AccountVerification::class)
            ->createQueryBuilder('v')
            ->leftJoin('v.user', 'u')
            ->addSelect('u')
            ->orderBy('v.submittedAt', 'DESC');

        // Filtrer par statut
        if ($status === 'pending') {
            $queryBuilder->where('v.status = :status')
                ->setParameter('status', VerificationStatus::PENDING);
        } elseif ($status === 'verified') {
            $queryBuilder->where('v.status = :status')
                ->setParameter('status', VerificationStatus::VERIFIED);
        } elseif ($status === 'rejected') {
            $queryBuilder->where('v.status = :status')
                ->setParameter('status', VerificationStatus::REJECTED);
        }

        $verifications = $queryBuilder->getQuery()->getResult();

        // Statistiques
        $stats = [
            'pending' => $this->entityManager->getRepository(AccountVerification::class)
                ->count(['status' => VerificationStatus::PENDING]),
            'verified' => $this->entityManager->getRepository(AccountVerification::class)
                ->count(['status' => VerificationStatus::VERIFIED]),
            'rejected' => $this->entityManager->getRepository(AccountVerification::class)
                ->count(['status' => VerificationStatus::REJECTED]),
        ];

        return $this->render('admin/verification/index.html.twig', [
            'verifications' => $verifications,
            'stats' => $stats,
            'currentStatus' => $status
        ]);
    }

    #[Route('/review/{id}', name: 'review')]
    public function review(AccountVerification $verification, Request $request): Response
    {
        $form = $this->createForm(VerificationReviewFormType::class, $verification);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $verification->setVerifiedAt(new \DateTime());
            $verification->setVerifiedBy($this->getUser());

            $this->entityManager->flush();

            // Notification à l'utilisateur
            $this->notificationService->sendVerificationStatusUpdate($verification);

            // Mettre à jour le statut de vérification global de l'utilisateur
            $this->updateUserVerificationStatus($verification->getUser());

            $status = $verification->getStatus() === VerificationStatus::VERIFIED ? 'approuvée' : 'rejetée';
            $this->addFlash('success', "Vérification {$status} avec succès.");

            return $this->redirectToRoute('admin_verification_index');
        }

        return $this->render('admin/verification/review.html.twig', [
            'verification' => $verification,
            'form' => $form
        ]);
    }

    #[Route('/detail/{id}', name: 'detail')]
    public function detail(AccountVerification $verification): Response
    {
        return $this->render('admin/verification/detail.html.twig', [
            'verification' => $verification
        ]);
    }

    #[Route('/bulk-action', name: 'bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action = $request->request->get('action');
        $verificationIds = $request->request->all('verification_ids');

        if (!$verificationIds) {
            $this->addFlash('error', 'Aucune vérification sélectionnée.');
            return $this->redirectToRoute('admin_verification_index');
        }

        $verifications = $this->entityManager
            ->getRepository(AccountVerification::class)
            ->findBy(['id' => $verificationIds]);

        $count = 0;
        foreach ($verifications as $verification) {
            if ($action === 'approve' && $verification->getStatus() === VerificationStatus::PENDING) {
                $verification->setStatus(VerificationStatus::VERIFIED);
                $verification->setVerifiedAt(new \DateTime());
                $verification->setVerifiedBy($this->getUser());
                $this->notificationService->sendVerificationStatusUpdate($verification);
                $this->updateUserVerificationStatus($verification->getUser());
                $count++;
            } elseif ($action === 'reject' && $verification->getStatus() === VerificationStatus::PENDING) {
                $verification->setStatus(VerificationStatus::REJECTED);
                $verification->setVerifiedAt(new \DateTime());
                $verification->setVerifiedBy($this->getUser());
                $this->notificationService->sendVerificationStatusUpdate($verification);
                $count++;
            }
        }

        $this->entityManager->flush();

        $actionText = $action === 'approve' ? 'approuvées' : 'rejetées';
        $this->addFlash('success', "{$count} vérifications {$actionText}.");

        return $this->redirectToRoute('admin_verification_index');
    }

    private function updateUserVerificationStatus($user): void
    {
        // Vérifier si l'utilisateur a toutes ses vérifications validées
        $pendingVerifications = $this->entityManager
            ->getRepository(AccountVerification::class)
            ->count([
                'user' => $user, 
                'status' => VerificationStatus::PENDING
            ]);

        $rejectedVerifications = $this->entityManager
            ->getRepository(AccountVerification::class)
            ->count([
                'user' => $user, 
                'status' => VerificationStatus::REJECTED
            ]);

        if ($pendingVerifications === 0 && $rejectedVerifications === 0) {
            $user->setIsVerified(true);
            $user->setVerificationStatus(VerificationStatus::VERIFIED);
        } elseif ($rejectedVerifications > 0) {
            $user->setIsVerified(false);
            $user->setVerificationStatus(VerificationStatus::REJECTED);
        } else {
            $user->setIsVerified(false);
            $user->setVerificationStatus(VerificationStatus::PENDING);
        }
    }
}