<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private NotificationService $notificationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        NotificationService $notificationService
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->notificationService = $notificationService;
    }

    #[Route('/{id}/details', name: 'app_admin_user_details', methods: ['GET'])]
    public function details(User $user): Response
    {
        return $this->render('admin/user_details.html.twig', [
            'user' => $user,
            'kyc_progress' => $user->getKycProgress(),
            'loan_applications' => $user->getLoanApplications(),
            'documents' => $user->getDocuments(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            // Mise à jour des informations utilisateur
            if (isset($data['firstName'])) {
                $user->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $user->setLastName($data['lastName']);
            }
            if (isset($data['email'])) {
                $user->setEmail($data['email']);
            }
            if (isset($data['phoneNumber'])) {
                $user->setPhoneNumber($data['phoneNumber']);
            }
            if (isset($data['address'])) {
                $user->setAddress($data['address']);
            }
            if (isset($data['city'])) {
                $user->setCity($data['city']);
            }
            if (isset($data['postalCode'])) {
                $user->setPostalCode($data['postalCode']);
            }
            if (isset($data['monthlyIncome'])) {
                $user->setMonthlyIncome($data['monthlyIncome']);
            }
            if (isset($data['employmentStatus'])) {
                $user->setEmploymentStatus($data['employmentStatus']);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Informations utilisateur mises à jour avec succès.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/suspend', name: 'app_admin_user_suspend', methods: ['POST'])]
    public function suspend(User $user): JsonResponse
    {
        $user->setIsActive(false);
        $this->entityManager->flush();

        // Envoyer notification de suspension
        $this->notificationService->sendAccountSuspended($user);

        return new JsonResponse(['success' => true, 'message' => 'Utilisateur suspendu avec succès.']);
    }

    #[Route('/{id}/activate', name: 'app_admin_user_activate', methods: ['POST'])]
    public function activate(User $user): JsonResponse
    {
        $user->setIsActive(true);
        $this->entityManager->flush();

        // Envoyer notification de réactivation
        $this->notificationService->sendAccountReactivated($user);

        return new JsonResponse(['success' => true, 'message' => 'Utilisateur activé avec succès.']);
    }

    #[Route('/{id}/verify', name: 'app_admin_user_verify', methods: ['POST'])]
    public function verify(User $user): JsonResponse
    {
        $user->setIsVerified(true);
        $user->setVerificationStatus(\App\Enum\VerificationStatus::VERIFIED);
        $this->entityManager->flush();

        // Envoyer notification de vérification
        $this->notificationService->sendAccountVerified($user);

        return new JsonResponse(['success' => true, 'message' => 'Utilisateur vérifié avec succès.']);
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', methods: ['DELETE'])]
    public function delete(User $user): JsonResponse
    {
        try {
            // Vérifier s'il y a des prêts actifs
            $activeLoanApplications = $user->getLoanApplications()->filter(function($loan) {
                return in_array($loan->getStatus(), ['approved', 'under_review']);
            });

            if ($activeLoanApplications->count() > 0) {
                return new JsonResponse([
                    'success' => false, 
                    'message' => 'Impossible de supprimer un utilisateur avec des prêts actifs.'
                ], 400);
            }

            // Supprimer les documents associés (si nécessaire, géré par orphanRemoval)
            // Supprimer l'utilisateur
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Utilisateur supprimé avec succès.']);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false, 
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/send-email', name: 'app_admin_user_send_email', methods: ['GET', 'POST'])]
    public function sendEmail(Request $request, User $user): Response
    {
        if ($request->isMethod('POST')) {
            $subject = $request->request->get('subject');
            $message = $request->request->get('message');
            $template = $request->request->get('template', 'custom');

            try {
                $this->notificationService->sendCustomEmailToUser($user, $subject, $message, $template);
                $this->addFlash('success', 'Email envoyé avec succès à ' . $user->getEmail());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/send_email.html.twig', [
            'user' => $user,
            'email_templates' => $this->getEmailTemplates(),
        ]);
    }

    #[Route('/export', name: 'app_admin_users_export', methods: ['GET'])]
    public function export(): Response
    {
        $users = $this->userRepository->findAll();
        
        $csvData = [];
        $csvData[] = [
            'ID', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Type de compte', 
            'Vérifié', 'Actif', 'Date d\'inscription', 'Dernière connexion',
            'Revenus mensuels', 'Statut professionnel', 'Ville', 'Code postal'
        ];

        foreach ($users as $user) {
            $csvData[] = [
                $user->getId(),
                $user->getFirstName(),
                $user->getLastName(),
                $user->getEmail(),
                $user->getPhoneNumber(),
                $user->getAccountType()->value,
                $user->isVerified() ? 'Oui' : 'Non',
                $user->isActive() ? 'Oui' : 'Non',
                $user->getCreatedAt()->format('d/m/Y'),
                $user->getLastLoginAt() ? $user->getLastLoginAt()->format('d/m/Y') : 'Jamais',
                $user->getMonthlyIncome() ?: '',
                $user->getEmploymentStatus() ?: '',
                $user->getCity() ?: '',
                $user->getPostalCode() ?: ''
            ];
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="utilisateurs_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://temp', 'w+');
        
        // BOM UTF-8 pour Excel
        fwrite($output, "\xEF\xBB\xBF");
        
        foreach ($csvData as $row) {
            fputcsv($output, $row, ';');
        }

        rewind($output);
        $response->setContent(stream_get_contents($output));
        fclose($output);

        return $response;
    }

    #[Route('/bulk-actions', name: 'app_admin_users_bulk_actions', methods: ['POST'])]
    public function bulkActions(Request $request): JsonResponse
    {
        $action = $request->request->get('action');
        $userIds = $request->request->get('user_ids', []);

        if (empty($userIds)) {
            return new JsonResponse(['success' => false, 'message' => 'Aucun utilisateur sélectionné.']);
        }

        $users = $this->userRepository->findBy(['id' => $userIds]);
        $count = 0;

        try {
            switch ($action) {
                case 'activate':
                    foreach ($users as $user) {
                        $user->setIsActive(true);
                        $count++;
                    }
                    $this->entityManager->flush();
                    $message = "$count utilisateur(s) activé(s).";
                    break;

                case 'suspend':
                    foreach ($users as $user) {
                        $user->setIsActive(false);
                        $count++;
                    }
                    $this->entityManager->flush();
                    $message = "$count utilisateur(s) suspendu(s).";
                    break;

                case 'verify':
                    foreach ($users as $user) {
                        if (!$user->isVerified()) {
                            $user->setIsVerified(true);
                            $user->setVerificationStatus(\App\Enum\VerificationStatus::VERIFIED);
                            $count++;
                        }
                    }
                    $this->entityManager->flush();
                    $message = "$count utilisateur(s) vérifié(s).";
                    break;

                case 'send_email':
                    $subject = $request->request->get('email_subject');
                    $emailMessage = $request->request->get('email_message');
                    
                    if (!$subject || !$emailMessage) {
                        return new JsonResponse(['success' => false, 'message' => 'Sujet et message requis.']);
                    }

                    foreach ($users as $user) {
                        $this->notificationService->sendCustomEmailToUser($user, $subject, $emailMessage);
                        $count++;
                    }
                    $message = "Email envoyé à $count utilisateur(s).";
                    break;

                default:
                    return new JsonResponse(['success' => false, 'message' => 'Action non reconnue.']);
            }

            return new JsonResponse(['success' => true, 'message' => $message]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    #[Route('/statistics', name: 'app_admin_users_statistics', methods: ['GET'])]
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_users' => $this->userRepository->count([]),
            'verified_users' => $this->userRepository->count(['isVerified' => true]),
            'active_users' => $this->userRepository->count(['isActive' => true]),
            'individual_accounts' => $this->userRepository->countByAccountType('individual'),
            'business_accounts' => $this->userRepository->countByAccountType('business'),
            'users_with_loans' => $this->userRepository->countUsersWithLoanApplications(),
            'registration_by_month' => $this->userRepository->getRegistrationsByMonth(),
            'geographic_distribution' => $this->userRepository->getGeographicDistribution(),
        ];

        return new JsonResponse($stats);
    }

    private function getEmailTemplates(): array
    {
        return [
            'welcome' => [
                'name' => 'Bienvenue',
                'subject' => 'Bienvenue sur EdgeLoan !',
                'description' => 'Email de bienvenue pour nouveaux utilisateurs'
            ],
            'verification_reminder' => [
                'name' => 'Rappel de vérification',
                'subject' => 'Complétez votre vérification d\'identité',
                'description' => 'Rappel pour compléter le processus KYC'
            ],
            'promotional' => [
                'name' => 'Promotionnel',
                'subject' => 'Offre spéciale EdgeLoan',
                'description' => 'Email promotionnel pour offres spéciales'
            ],
            'support' => [
                'name' => 'Support',
                'subject' => 'Support EdgeLoan',
                'description' => 'Email de support technique'
            ],
            'custom' => [
                'name' => 'Personnalisé',
                'subject' => '',
                'description' => 'Email personnalisé'
            ]
        ];
    }
}