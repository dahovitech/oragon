<?php

namespace App\Controller\Security;

use App\Entity\User;
use App\Service\Security\TwoFactorService;
use App\Service\Security\SecurityAuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/security', name: 'user_security_')]
#[IsGranted('ROLE_USER')]
class SecurityController extends AbstractController
{
    public function __construct(
        private TwoFactorService $twoFactorService,
        private SecurityAuditService $auditService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $status = $this->twoFactorService->get2FAStatus($user);

        return $this->render('security/index.html.twig', [
            'user' => $user,
            'two_factor_status' => $status,
            'recent_events' => $this->auditService->getUserSecurityEvents($user, 5),
        ]);
    }

    #[Route('/2fa/enable', name: '2fa_enable')]
    public function enable2FA(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $method = $request->request->get('method', 'google');
            
            try {
                if ($method === 'google') {
                    $secret = $this->twoFactorService->enableGoogleAuthenticator($user);
                    $qrCode = $this->twoFactorService->generateQrCode($user);
                } else {
                    $secret = $this->twoFactorService->enableTotp($user);
                    $qrCode = null; // TOTP doesn't need QR code
                }

                $this->auditService->log2FAEvent($user, 'setup_started', ['method' => $method]);

                return $this->render('security/2fa_setup.html.twig', [
                    'user' => $user,
                    'secret' => $secret,
                    'qr_code' => $qrCode,
                    'method' => $method,
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'activation de la 2FA : ' . $e->getMessage());
            }
        }

        return $this->render('security/2fa_enable.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/2fa/confirm', name: '2fa_confirm')]
    public function confirm2FA(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            
            if ($this->twoFactorService->verifyCode($user, $code)) {
                // Generate backup codes
                $backupCodes = $this->twoFactorService->generateBackupCodes($user);
                
                $this->auditService->log2FAEvent($user, 'enabled', [
                    'method' => $user->isGoogleAuthenticatorEnabled() ? 'google' : 'totp'
                ]);

                $this->addFlash('success', 'Authentification à deux facteurs activée avec succès !');

                return $this->render('security/2fa_backup_codes.html.twig', [
                    'user' => $user,
                    'backup_codes' => $backupCodes,
                ]);
            } else {
                $this->auditService->log2FAEvent($user, 'setup_failed', ['reason' => 'invalid_code']);
                $this->addFlash('error', 'Code invalide. Veuillez réessayer.');
            }
        }

        return $this->redirectToRoute('user_security_2fa_enable');
    }

    #[Route('/2fa/disable', name: '2fa_disable')]
    public function disable2FA(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            
            // Verify current password before disabling 2FA
            if ($this->isPasswordValid($user, $password)) {
                $this->twoFactorService->disable2FA($user);
                $this->auditService->log2FAEvent($user, 'disabled');
                
                $this->addFlash('success', 'Authentification à deux facteurs désactivée.');
            } else {
                $this->addFlash('error', 'Mot de passe incorrect.');
            }
        }

        return $this->redirectToRoute('user_security_index');
    }

    #[Route('/2fa/backup-codes/regenerate', name: '2fa_backup_codes_regenerate')]
    public function regenerateBackupCodes(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->twoFactorService->is2FAEnabled($user)) {
            $this->addFlash('error', 'La 2FA doit être activée pour générer des codes de secours.');
            return $this->redirectToRoute('user_security_index');
        }

        if ($request->isMethod('POST')) {
            $backupCodes = $this->twoFactorService->generateBackupCodes($user);
            $this->auditService->log2FAEvent($user, 'backup_codes_regenerated');
            
            return $this->render('security/2fa_backup_codes.html.twig', [
                'user' => $user,
                'backup_codes' => $backupCodes,
                'regenerated' => true,
            ]);
        }

        return $this->render('security/2fa_backup_codes_confirm.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/api/2fa/status', name: '2fa_status_api', methods: ['GET'])]
    public function get2FAStatusApi(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $status = $this->twoFactorService->get2FAStatus($user);

        return $this->json($status);
    }

    #[Route('/password/change', name: 'password_change')]
    public function changePassword(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->get('current_password');
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');

            // Validate current password
            if (!$this->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Mot de passe actuel incorrect.');
                return $this->render('security/change_password.html.twig', ['user' => $user]);
            }

            // Validate new password
            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'La confirmation du mot de passe ne correspond pas.');
                return $this->render('security/change_password.html.twig', ['user' => $user]);
            }

            if (strlen($newPassword) < 8) {
                $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
                return $this->render('security/change_password.html.twig', ['user' => $user]);
            }

            // Hash and save new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $user->setPassword($hashedPassword);
            $this->entityManager->flush();

            $this->auditService->logPasswordChange($user);
            $this->addFlash('success', 'Mot de passe modifié avec succès.');

            return $this->redirectToRoute('user_security_index');
        }

        return $this->render('security/change_password.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * Helper method to verify password
     */
    private function isPasswordValid(User $user, string $password): bool
    {
        return password_verify($password, $user->getPassword());
    }
}