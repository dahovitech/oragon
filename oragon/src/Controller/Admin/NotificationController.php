<?php

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Entity\EmailTemplate;
use App\Repository\NotificationRepository;
use App\Repository\EmailTemplateRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/admin/notifications')]
class NotificationController extends AbstractController
{
    private NotificationService $notificationService;
    private EmailService $emailService;
    private NotificationRepository $notificationRepository;
    private EmailTemplateRepository $templateRepository;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        NotificationService $notificationService,
        EmailService $emailService,
        NotificationRepository $notificationRepository,
        EmailTemplateRepository $templateRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->notificationService = $notificationService;
        $this->emailService = $emailService;
        $this->notificationRepository = $notificationRepository;
        $this->templateRepository = $templateRepository;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('', name: 'admin_notifications_index')]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $type = $request->query->get('type');
        $status = $request->query->get('status');

        $qb = $this->notificationRepository->createQueryBuilder('n');

        if ($type) {
            $qb->andWhere('n.type = :type')->setParameter('type', $type);
        }

        if ($status) {
            $qb->andWhere('n.status = :status')->setParameter('status', $status);
        }

        $notifications = $qb->orderBy('n.createdAt', 'DESC')
                           ->setMaxResults($limit)
                           ->setFirstResult($offset)
                           ->getQuery()
                           ->getResult();

        $total = $qb->select('COUNT(n.id)')
                   ->setMaxResults(null)
                   ->setFirstResult(null)
                   ->getQuery()
                   ->getSingleScalarResult();

        $statistics = $this->notificationService->getStatistics();

        return $this->render('admin/notification/index.html.twig', [
            'notifications' => $notifications,
            'statistics' => $statistics,
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'filters' => [
                'type' => $type,
                'status' => $status
            ]
        ]);
    }

    #[Route('/send', name: 'admin_notifications_send')]
    public function send(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            try {
                $recipients = [];
                
                if ($data['recipient_type'] === 'all_users') {
                    $recipients = $this->userRepository->findAll();
                } elseif ($data['recipient_type'] === 'specific_users') {
                    $userIds = explode(',', $data['user_ids']);
                    $recipients = $this->userRepository->findBy(['id' => $userIds]);
                } elseif ($data['recipient_type'] === 'email') {
                    // Send to specific email
                    $notification = $this->notificationService->sendToEmail(
                        $data['email'],
                        $data['type'],
                        $data['title'],
                        $data['message'],
                        $data['data'] ?? [],
                        $data['channels'] ?? null,
                        $data['priority'] ?? 'normal'
                    );
                    
                    $this->addFlash('success', 'Notification envoyée avec succès à ' . $data['email']);
                    return $this->redirectToRoute('admin_notifications_index');
                }

                if (!empty($recipients)) {
                    $notifications = $this->notificationService->sendBulk(
                        $recipients,
                        $data['type'],
                        $data['title'],
                        $data['message'],
                        $data['data'] ?? [],
                        $data['channels'] ?? null,
                        $data['priority'] ?? 'normal'
                    );
                    
                    $this->addFlash('success', sprintf('Notification envoyée à %d utilisateurs', count($notifications)));
                    return $this->redirectToRoute('admin_notifications_index');
                }
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'envoi: ' . $e->getMessage());
            }
        }

        $users = $this->userRepository->findAll();
        $templates = $this->templateRepository->findActiveTemplates();

        return $this->render('admin/notification/send.html.twig', [
            'users' => $users,
            'templates' => $templates
        ]);
    }

    #[Route('/{id}', name: 'admin_notifications_show', requirements: ['id' => '\d+'])]
    public function show(Notification $notification): Response
    {
        return $this->render('admin/notification/show.html.twig', [
            'notification' => $notification
        ]);
    }

    #[Route('/{id}/retry', name: 'admin_notifications_retry', methods: ['POST'])]
    public function retry(Notification $notification): JsonResponse
    {
        try {
            if ($notification->getStatus() !== 'failed') {
                return new JsonResponse(['error' => 'Seules les notifications échouées peuvent être relancées'], 400);
            }

            $notification->setStatus('pending');
            $notification->setFailureReason(null);
            $this->entityManager->flush();

            $success = $this->notificationService->processNotification($notification);

            return new JsonResponse([
                'success' => $success,
                'status' => $notification->getStatus(),
                'message' => $success ? 'Notification relancée avec succès' : 'Échec du relancement'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/process-pending', name: 'admin_notifications_process_pending', methods: ['POST'])]
    public function processPending(): JsonResponse
    {
        try {
            $results = $this->notificationService->processPendingNotifications(50);
            
            $processed = count($results);
            $successful = count(array_filter($results, fn($r) => $r['success']));
            
            return new JsonResponse([
                'processed' => $processed,
                'successful' => $successful,
                'failed' => $processed - $successful,
                'message' => sprintf('%d notifications traitées (%d réussies, %d échouées)', 
                    $processed, $successful, $processed - $successful)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/statistics', name: 'admin_notifications_statistics')]
    public function statistics(Request $request): Response
    {
        $from = $request->query->get('from') ? new \DateTime($request->query->get('from')) : null;
        $to = $request->query->get('to') ? new \DateTime($request->query->get('to')) : null;

        $statistics = $this->notificationService->getStatistics($from, $to);
        $statisticsByType = $this->notificationRepository->getStatisticsByType($from, $to);

        return $this->render('admin/notification/statistics.html.twig', [
            'statistics' => $statistics,
            'statistics_by_type' => $statisticsByType,
            'from' => $from,
            'to' => $to
        ]);
    }

    #[Route('/templates', name: 'admin_notification_templates')]
    public function templates(): Response
    {
        $templates = $this->templateRepository->findAll();
        $groupedTemplates = [];
        
        foreach ($templates as $template) {
            $groupedTemplates[$template->getType()][] = $template;
        }

        return $this->render('admin/notification/templates/index.html.twig', [
            'grouped_templates' => $groupedTemplates
        ]);
    }

    #[Route('/templates/new', name: 'admin_notification_templates_new')]
    public function newTemplate(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            try {
                $template = $this->emailService->createTemplate(
                    $data['name'],
                    $data['type'],
                    $data['subject'],
                    $data['html_content'],
                    $data['locale'] ?? 'fr',
                    $data['text_content'] ?? null,
                    isset($data['variables']) ? json_decode($data['variables'], true) : null,
                    $data['description'] ?? null
                );

                if (isset($data['preheader'])) {
                    $template->setPreheader($data['preheader']);
                    $this->entityManager->flush();
                }

                $this->addFlash('success', 'Template créé avec succès');
                return $this->redirectToRoute('admin_notification_templates');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création: ' . $e->getMessage());
            }
        }

        return $this->render('admin/notification/templates/new.html.twig');
    }

    #[Route('/templates/{id}', name: 'admin_notification_templates_show', requirements: ['id' => '\d+'])]
    public function showTemplate(EmailTemplate $template): Response
    {
        return $this->render('admin/notification/templates/show.html.twig', [
            'template' => $template
        ]);
    }

    #[Route('/templates/{id}/edit', name: 'admin_notification_templates_edit', requirements: ['id' => '\d+'])]
    public function editTemplate(EmailTemplate $template, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            try {
                // Create backup before editing
                $this->templateRepository->createBackup($template);

                $template->setSubject($data['subject']);
                $template->setHtmlContent($data['html_content']);
                $template->setTextContent($data['text_content'] ?? null);
                $template->setDescription($data['description'] ?? null);
                $template->setPreheader($data['preheader'] ?? null);
                
                if (isset($data['variables'])) {
                    $template->setVariables(json_decode($data['variables'], true));
                }
                
                $template->incrementVersion();
                $this->entityManager->flush();

                $this->addFlash('success', 'Template modifié avec succès');
                return $this->redirectToRoute('admin_notification_templates_show', ['id' => $template->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification: ' . $e->getMessage());
            }
        }

        return $this->render('admin/notification/templates/edit.html.twig', [
            'template' => $template
        ]);
    }

    #[Route('/templates/{id}/preview', name: 'admin_notification_templates_preview', requirements: ['id' => '\d+'])]
    public function previewTemplate(EmailTemplate $template, Request $request): Response
    {
        $variables = [];
        if ($request->query->has('variables')) {
            $variables = json_decode($request->query->get('variables'), true) ?? [];
        }

        $preview = $this->emailService->previewTemplate($template, $variables);
        $errors = $this->emailService->validateTemplate($template, $variables);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'preview' => $preview,
                'errors' => $errors
            ]);
        }

        return $this->render('admin/notification/templates/preview.html.twig', [
            'template' => $template,
            'preview' => $preview,
            'errors' => $errors,
            'variables' => $variables
        ]);
    }

    #[Route('/templates/{id}/test', name: 'admin_notification_templates_test', methods: ['POST'])]
    public function testTemplate(EmailTemplate $template, Request $request): JsonResponse
    {
        $email = $request->request->get('email');
        $variables = json_decode($request->request->get('variables', '{}'), true) ?? [];

        try {
            $success = $this->emailService->sendCustomEmail(
                $email,
                'Test Template: ' . $template->getName(),
                $template->getName(),
                $variables
            );

            return new JsonResponse([
                'success' => $success,
                'message' => $success ? 'Email de test envoyé avec succès' : 'Échec de l\'envoi'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/test-email', name: 'admin_notifications_test_email', methods: ['POST'])]
    public function testEmail(Request $request): JsonResponse
    {
        $email = $request->request->get('email');

        try {
            $success = $this->emailService->sendTestEmail($email);
            
            return new JsonResponse([
                'success' => $success,
                'message' => $success ? 'Email de test envoyé avec succès' : 'Échec de l\'envoi du test'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/cleanup', name: 'admin_notifications_cleanup', methods: ['POST'])]
    public function cleanup(Request $request): JsonResponse
    {
        $daysOld = $request->request->getInt('days_old', 90);

        try {
            $deleted = $this->notificationService->cleanOldNotifications($daysOld);
            
            return new JsonResponse([
                'deleted' => $deleted,
                'message' => sprintf('%d notifications anciennes supprimées', $deleted)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}