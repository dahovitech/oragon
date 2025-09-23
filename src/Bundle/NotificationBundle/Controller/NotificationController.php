<?php

namespace App\Bundle\NotificationBundle\Controller;

use App\Bundle\NotificationBundle\Entity\Notification;
use App\Bundle\NotificationBundle\Entity\EmailTemplate;
use App\Bundle\NotificationBundle\Repository\NotificationRepository;
use App\Bundle\NotificationBundle\Repository\NotificationPreferenceRepository;
use App\Bundle\NotificationBundle\Repository\EmailTemplateRepository;
use App\Bundle\NotificationBundle\Service\NotificationService;
use App\Bundle\NotificationBundle\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/notifications', name: 'admin_notifications_')]
#[IsGranted('ROLE_ADMIN')]
class NotificationController extends AbstractController
{
    private NotificationRepository $notificationRepository;
    private NotificationPreferenceRepository $preferenceRepository;
    private EmailTemplateRepository $templateRepository;
    private NotificationService $notificationService;
    private EmailService $emailService;

    public function __construct(
        NotificationRepository $notificationRepository,
        NotificationPreferenceRepository $preferenceRepository,
        EmailTemplateRepository $templateRepository,
        NotificationService $notificationService,
        EmailService $emailService
    ) {
        $this->notificationRepository = $notificationRepository;
        $this->preferenceRepository = $preferenceRepository;
        $this->templateRepository = $templateRepository;
        $this->notificationService = $notificationService;
        $this->emailService = $emailService;
    }

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $status = $request->query->get('status');
        $type = $request->query->get('type');

        $qb = $this->notificationRepository->createQueryBuilder('n');

        if ($status) {
            $qb->andWhere('n.status = :status')
               ->setParameter('status', $status);
        }

        if ($type) {
            $qb->andWhere('n.type = :type')
               ->setParameter('type', $type);
        }

        $notifications = $qb->orderBy('n.createdAt', 'DESC')
                           ->setFirstResult(($page - 1) * 20)
                           ->setMaxResults(20)
                           ->getQuery()
                           ->getResult();

        $totalCount = $qb->select('COUNT(n.id)')
                        ->getQuery()
                        ->getSingleScalarResult();

        $stats = $this->notificationRepository->getStatistics();

        return $this->render('@Notification/admin/index.html.twig', [
            'notifications' => $notifications,
            'currentPage' => $page,
            'totalPages' => ceil($totalCount / 20),
            'totalCount' => $totalCount,
            'stats' => $stats,
            'currentStatus' => $status,
            'currentType' => $type,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            try {
                if ($data['recipient_type'] === 'user') {
                    $notification = $this->notificationService->sendToUser(
                        (int)$data['user_id'],
                        $data['type'],
                        $data['title'],
                        $data['message'],
                        json_decode($data['data'] ?? '{}', true),
                        $data['channels'] ?? ['email'],
                        $data['priority'] ?? 'normal',
                        !empty($data['scheduled_at']) ? new \DateTimeImmutable($data['scheduled_at']) : null
                    );
                } elseif ($data['recipient_type'] === 'email') {
                    $notification = $this->notificationService->sendToEmail(
                        $data['email'],
                        $data['type'],
                        $data['title'],
                        $data['message'],
                        json_decode($data['data'] ?? '{}', true),
                        $data['channels'] ?? ['email'],
                        $data['priority'] ?? 'normal',
                        !empty($data['scheduled_at']) ? new \DateTimeImmutable($data['scheduled_at']) : null
                    );
                } elseif ($data['recipient_type'] === 'all') {
                    $count = $this->notificationService->sendSystemNotification(
                        $data['title'],
                        $data['message'],
                        json_decode($data['data'] ?? '{}', true),
                        $data['priority'] ?? 'normal'
                    );
                    
                    $this->addFlash('success', "Notification envoyée à {$count} utilisateurs.");
                    return $this->redirectToRoute('admin_notifications_index');
                }

                $this->addFlash('success', 'Notification créée avec succès.');
                return $this->redirectToRoute('admin_notifications_show', ['id' => $notification->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création de la notification: ' . $e->getMessage());
            }
        }

        return $this->render('@Notification/admin/create.html.twig');
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Notification $notification): Response
    {
        return $this->render('@Notification/admin/show.html.twig', [
            'notification' => $notification,
        ]);
    }

    #[Route('/{id}/resend', name: 'resend', methods: ['POST'])]
    public function resend(Notification $notification): JsonResponse
    {
        if (!$notification->canBeResent()) {
            return new JsonResponse(['success' => false, 'message' => 'Cette notification ne peut pas être renvoyée.']);
        }

        try {
            $notification->setStatus('pending');
            $this->notificationRepository->save($notification, true);
            
            $success = $this->notificationService->processNotification($notification);
            
            return new JsonResponse([
                'success' => $success,
                'message' => $success ? 'Notification renvoyée avec succès.' : 'Échec du renvoi de la notification.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    #[Route('/templates', name: 'templates')]
    public function templates(Request $request): Response
    {
        $templates = $this->templateRepository->getTemplatesWithLocales();
        $stats = $this->templateRepository->getTemplateStatistics();

        return $this->render('@Notification/admin/templates.html.twig', [
            'templates' => $templates,
            'stats' => $stats,
        ]);
    }

    #[Route('/templates/create', name: 'template_create', methods: ['GET', 'POST'])]
    public function createTemplate(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            try {
                $variables = [];
                if (!empty($data['variables'])) {
                    $variableLines = explode("\n", trim($data['variables']));
                    foreach ($variableLines as $line) {
                        $parts = explode('|', trim($line));
                        if (count($parts) >= 2) {
                            $variables[] = [
                                'name' => trim($parts[0]),
                                'description' => trim($parts[1]),
                                'required' => isset($parts[2]) && strtolower(trim($parts[2])) === 'required'
                            ];
                        }
                    }
                }

                $template = $this->emailService->createTemplate(
                    $data['name'],
                    $data['type'],
                    $data['subject'],
                    $data['html_content'],
                    $data['locale'] ?? 'fr',
                    $data['text_content'] ?: null,
                    $variables,
                    $data['description'] ?: null
                );

                $this->addFlash('success', 'Template créé avec succès.');
                return $this->redirectToRoute('admin_notifications_template_edit', ['id' => $template->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du template: ' . $e->getMessage());
            }
        }

        return $this->render('@Notification/admin/template_create.html.twig');
    }

    #[Route('/templates/{id}/edit', name: 'template_edit', requirements: ['id' => '\d+'])]
    public function editTemplate(EmailTemplate $template, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            try {
                $variables = [];
                if (!empty($data['variables'])) {
                    $variableLines = explode("\n", trim($data['variables']));
                    foreach ($variableLines as $line) {
                        $parts = explode('|', trim($line));
                        if (count($parts) >= 2) {
                            $variables[] = [
                                'name' => trim($parts[0]),
                                'description' => trim($parts[1]),
                                'required' => isset($parts[2]) && strtolower(trim($parts[2])) === 'required'
                            ];
                        }
                    }
                }

                $template->setSubject($data['subject']);
                $template->setHtmlContent($data['html_content']);
                $template->setTextContent($data['text_content'] ?: null);
                $template->setVariables($variables);
                $template->setDescription($data['description'] ?: null);
                $template->setActive(isset($data['active']));

                $this->templateRepository->save($template, true);

                $this->addFlash('success', 'Template mis à jour avec succès.');
                return $this->redirectToRoute('admin_notifications_template_edit', ['id' => $template->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour du template: ' . $e->getMessage());
            }
        }

        return $this->render('@Notification/admin/template_edit.html.twig', [
            'template' => $template,
        ]);
    }

    #[Route('/templates/{id}/preview', name: 'template_preview')]
    public function previewTemplate(EmailTemplate $template, Request $request): Response
    {
        $variables = [];
        $variableInput = $request->query->get('variables', '');
        
        if ($variableInput) {
            parse_str($variableInput, $variables);
        }

        try {
            $preview = $this->emailService->getTemplatePreview($template->getName(), $variables, $template->getLocale());
            
            return $this->render('@Notification/admin/template_preview.html.twig', [
                'template' => $template,
                'preview' => $preview,
                'variables' => $variables,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération de l\'aperçu: ' . $e->getMessage());
            return $this->redirectToRoute('admin_notifications_template_edit', ['id' => $template->getId()]);
        }
    }

    #[Route('/api/stats', name: 'api_stats', methods: ['GET'])]
    public function getStats(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $fromDate = $from ? new \DateTimeImmutable($from) : new \DateTimeImmutable('-30 days');
        $toDate = $to ? new \DateTimeImmutable($to) : new \DateTimeImmutable();

        $stats = $this->notificationRepository->getStatistics($fromDate, $toDate);
        $byType = $this->notificationRepository->getNotificationsByType($fromDate, $toDate);
        $dailyCounts = $this->notificationRepository->getDailyNotificationCounts($fromDate, $toDate);

        return new JsonResponse([
            'stats' => $stats,
            'by_type' => $byType,
            'daily_counts' => $dailyCounts,
        ]);
    }

    #[Route('/api/send-test', name: 'api_send_test', methods: ['POST'])]
    public function sendTestEmail(Request $request): JsonResponse
    {
        $email = $request->request->get('email');
        
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['success' => false, 'message' => 'Email invalide.']);
        }

        try {
            $success = $this->emailService->sendTestEmail($email);
            
            return new JsonResponse([
                'success' => $success,
                'message' => $success ? 'Email de test envoyé avec succès.' : 'Échec de l\'envoi de l\'email de test.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    #[Route('/process-pending', name: 'process_pending', methods: ['POST'])]
    public function processPending(): JsonResponse
    {
        try {
            $processed = $this->notificationService->processPendingNotifications();
            
            return new JsonResponse([
                'success' => true,
                'processed' => $processed,
                'message' => "{$processed} notifications traitées."
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    #[Route('/preferences', name: 'preferences')]
    public function preferences(): Response
    {
        $stats = $this->preferenceRepository->getPreferencesStatistics();

        return $this->render('@Notification/admin/preferences.html.twig', [
            'stats' => $stats,
        ]);
    }

    #[Route('/cleanup', name: 'cleanup', methods: ['POST'])]
    public function cleanup(Request $request): JsonResponse
    {
        $days = $request->request->getInt('days', 90);
        
        try {
            $deleted = $this->notificationRepository->cleanupOldNotifications($days);
            
            return new JsonResponse([
                'success' => true,
                'deleted' => $deleted,
                'message' => "{$deleted} notifications supprimées."
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}