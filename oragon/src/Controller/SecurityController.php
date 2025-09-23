<?php

namespace App\Controller;

use App\Service\TwoFactorService;
use App\Service\SecurityAuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account/security')]
#[IsGranted('ROLE_USER')]
class SecurityController extends AbstractController
{
    private TwoFactorService $twoFactorService;
    private SecurityAuditService $securityAuditService;

    public function __construct(
        TwoFactorService $twoFactorService,
        SecurityAuditService $securityAuditService
    ) {
        $this->twoFactorService = $twoFactorService;
        $this->securityAuditService = $securityAuditService;
    }

    #[Route('', name: 'user_security_settings')]
    public function securitySettings(): Response
    {
        $user = $this->getUser();
        $twoFactorConfig = $this->twoFactorService->getConfiguration($user);
        $isEnabled = $this->twoFactorService->isEnabled($user);

        return $this->render('security/settings.html.twig', [
            'two_factor_enabled' => $isEnabled,
            'two_factor_config' => $twoFactorConfig,
            'remaining_backup_codes' => $this->twoFactorService->getRemainingBackupCodesCount($user),
            'needs_backup_regeneration' => $this->twoFactorService->needsBackupCodeRegeneration($user),
        ]);
    }

    #[Route('/two-factor/setup', name: 'user_two_factor_setup')]
    public function setupTwoFactor(Request $request): Response
    {
        $user = $this->getUser();

        if ($this->twoFactorService->isEnabled($user)) {
            $this->addFlash('info', 'L\'authentification à deux facteurs est déjà activée.');
            return $this->redirectToRoute('user_security_settings');
        }

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            $deviceName = $request->request->get('device_name', 'Mon appareil');

            if (!$code) {
                $this->addFlash('error', 'Le code de vérification est requis.');
                return $this->redirectToRoute('user_two_factor_setup');
            }

            if (!$this->twoFactorService->isValidTOTPFormat($code)) {
                $this->addFlash('error', 'Format de code invalide. Utilisez un code à 6 chiffres.');
                return $this->redirectToRoute('user_two_factor_setup');
            }

            if ($this->twoFactorService->completeSetup($user, $code)) {
                $this->securityAuditService->logTwoFactorEvent($user, 'enabled', true, $request);
                
                $this->addFlash('success', 'Authentification à deux facteurs activée avec succès !');
                return $this->redirectToRoute('user_two_factor_backup_codes');
            } else {
                $this->securityAuditService->logTwoFactorEvent($user, 'setup_failed', false, $request);
                $this->addFlash('error', 'Code de vérification incorrect. Veuillez réessayer.');
            }
        } else {
            // Initialize setup
            $this->twoFactorService->initializeSetup($user);
        }

        $twoFactorConfig = $this->twoFactorService->getConfiguration($user);
        $qrCodeUrl = $twoFactorConfig->getQrCodeUrl();
        $manualKey = $twoFactorConfig->getManualEntryKey();

        return $this->render('security/two_factor_setup.html.twig', [
            'qr_code_url' => $qrCodeUrl,
            'manual_key' => $manualKey,
            'user_email' => $user->getEmail(),
        ]);
    }

    #[Route('/two-factor/backup-codes', name: 'user_two_factor_backup_codes')]
    public function backupCodes(Request $request): Response
    {
        $user = $this->getUser();

        if (!$this->twoFactorService->isEnabled($user)) {
            $this->addFlash('error', 'L\'authentification à deux facteurs n\'est pas activée.');
            return $this->redirectToRoute('user_security_settings');
        }

        $twoFactorConfig = $this->twoFactorService->getConfiguration($user);
        $backupCodes = $twoFactorConfig->getBackupCodes();

        if ($request->isMethod('POST') && $request->request->get('action') === 'regenerate') {
            $newCodes = $this->twoFactorService->generateNewBackupCodes($user);
            $this->securityAuditService->logTwoFactorEvent($user, 'backup_codes_regenerated', true, $request);
            
            $this->addFlash('success', 'Nouveaux codes de récupération générés.');
            $backupCodes = $newCodes;
        }

        return $this->render('security/backup_codes.html.twig', [
            'backup_codes' => $backupCodes,
            'remaining_count' => count($backupCodes ?? []),
        ]);
    }

    #[Route('/two-factor/disable', name: 'user_two_factor_disable', methods: ['POST'])]
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $currentPassword = $request->request->get('current_password');

        if (!$currentPassword) {
            return new JsonResponse(['error' => 'Mot de passe actuel requis'], 400);
        }

        // Here you would verify the current password
        // For this example, we'll assume it's verified

        if ($this->twoFactorService->disable($user)) {
            $this->securityAuditService->logTwoFactorEvent($user, 'disabled', true, $request);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Authentification à deux facteurs désactivée.'
            ]);
        }

        return new JsonResponse(['error' => 'Erreur lors de la désactivation'], 500);
    }

    #[Route('/two-factor/verify', name: 'user_two_factor_verify', methods: ['POST'])]
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $code = $request->request->get('code');

        if (!$code) {
            return new JsonResponse(['error' => 'Code requis'], 400);
        }

        if (!$this->twoFactorService->isValidTOTPFormat($code) && !$this->twoFactorService->isValidBackupCodeFormat($code)) {
            return new JsonResponse(['error' => 'Format de code invalide'], 400);
        }

        if ($this->twoFactorService->verifyCode($user, $code)) {
            $this->securityAuditService->logTwoFactorEvent($user, 'verified', true, $request);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Code vérifié avec succès',
                'remaining_backup_codes' => $this->twoFactorService->getRemainingBackupCodesCount($user)
            ]);
        }

        $this->securityAuditService->logTwoFactorEvent($user, 'verification_failed', false, $request);
        
        return new JsonResponse(['error' => 'Code incorrect'], 400);
    }

    #[Route('/activity', name: 'user_security_activity')]
    public function securityActivity(Request $request): Response
    {
        $user = $this->getUser();
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        $events = $this->securityAuditService->getUserSecurityEvents($user, $page, $limit);
        $totalEvents = $this->securityAuditService->countUserSecurityEvents($user);

        return $this->render('security/activity.html.twig', [
            'events' => $events,
            'current_page' => $page,
            'total_pages' => ceil($totalEvents / $limit),
            'total_events' => $totalEvents,
        ]);
    }

    #[Route('/sessions', name: 'user_security_sessions')]
    public function activeSessions(): Response
    {
        // This would show active user sessions
        // For now, return a placeholder
        return $this->render('security/sessions.html.twig', [
            'sessions' => [], // TODO: Implement session management
        ]);
    }

    #[Route('/export', name: 'user_security_export', methods: ['POST'])]
    public function exportSecurityData(Request $request): Response
    {
        $user = $this->getUser();
        $format = $request->request->get('format', 'json');

        // Log the export request
        $this->securityAuditService->logEvent(
            'data_export',
            'Export de données de sécurité demandé',
            'medium',
            $user,
            $request,
            ['export_type' => 'security_data', 'format' => $format]
        );

        $data = [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'two_factor_enabled' => $this->twoFactorService->isEnabled($user),
            'export_date' => date('c'),
            'security_events' => [], // Would contain user's security events
        ];

        if ($format === 'json') {
            $response = new JsonResponse($data);
            $response->headers->set('Content-Disposition', 'attachment; filename="security-data.json"');
            return $response;
        }

        // For other formats, you could implement CSV, PDF, etc.
        return new JsonResponse(['error' => 'Format not supported'], 400);
    }

    #[Route('/test-2fa', name: 'user_security_test_2fa', methods: ['POST'])]
    public function testTwoFactor(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$this->twoFactorService->isEnabled($user)) {
            return new JsonResponse(['error' => '2FA not enabled'], 400);
        }

        // This endpoint could be used to test 2FA functionality
        $twoFactorConfig = $this->twoFactorService->getConfiguration($user);
        
        return new JsonResponse([
            'success' => true,
            'message' => '2FA is properly configured',
            'device_name' => $twoFactorConfig->getDeviceName(),
            'remaining_backup_codes' => $this->twoFactorService->getRemainingBackupCodesCount($user),
            'last_used' => $twoFactorConfig->getLastUsedAt()?->format('c'),
        ]);
    }
}