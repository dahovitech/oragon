<?php

namespace App\Bundle\NotificationBundle\Controller;

use App\Bundle\NotificationBundle\Repository\NotificationRepository;
use App\Bundle\NotificationBundle\Repository\NotificationPreferenceRepository;
use App\Bundle\NotificationBundle\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications', name: 'api_notifications_')]
#[IsGranted('ROLE_USER')]
class NotificationApiController extends AbstractController
{
    private NotificationRepository $notificationRepository;
    private NotificationPreferenceRepository $preferenceRepository;
    private NotificationService $notificationService;

    public function __construct(
        NotificationRepository $notificationRepository,
        NotificationPreferenceRepository $preferenceRepository,
        NotificationService $notificationService
    ) {
        $this->notificationRepository = $notificationRepository;
        $this->preferenceRepository = $preferenceRepository;
        $this->notificationService = $notificationService;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $unreadOnly = $request->query->getBoolean('unread_only', false);

        $notifications = $this->notificationService->getUserNotifications(
            $user->getId(),
            $unreadOnly,
            $page,
            $limit
        );

        $unreadCount = $this->notificationService->getUnreadCount($user->getId());

        return new JsonResponse([
            'notifications' => array_map([$this, 'serializeNotification'], $notifications),
            'unread_count' => $unreadCount,
            'page' => $page,
            'limit' => $limit,
            'has_more' => count($notifications) === $limit
        ]);
    }

    #[Route('/unread-count', name: 'unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $count = $this->notificationService->getUnreadCount($user->getId());

        return new JsonResponse(['unread_count' => $count]);
    }

    #[Route('/{id}/read', name: 'mark_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markAsRead(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $success = $this->notificationService->markAsRead($id, $user->getId());

        if (!$success) {
            return new JsonResponse(['error' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/mark-read', name: 'mark_multiple_read', methods: ['POST'])]
    public function markMultipleAsRead(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $notificationIds = $data['notification_ids'] ?? [];

        if (empty($notificationIds)) {
            return new JsonResponse(['error' => 'No notification IDs provided'], Response::HTTP_BAD_REQUEST);
        }

        $count = $this->notificationService->markMultipleAsRead($notificationIds, $user->getId());

        return new JsonResponse([
            'success' => true,
            'marked_count' => $count
        ]);
    }

    #[Route('/mark-all-read', name: 'mark_all_read', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $count = $this->notificationService->markAllAsRead($user->getId());

        return new JsonResponse([
            'success' => true,
            'marked_count' => $count
        ]);
    }

    #[Route('/preferences', name: 'preferences', methods: ['GET', 'POST'])]
    public function preferences(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        if ($request->isMethod('GET')) {
            $preferences = $this->preferenceRepository->findUserPreferences($user->getId());
            $defaultTypes = $this->preferenceRepository->getDefaultNotificationTypes();

            $result = [];
            foreach ($defaultTypes as $type => $defaultChannels) {
                $preference = array_filter($preferences, fn($p) => $p->getType() === $type);
                $preference = reset($preference);

                $result[$type] = [
                    'enabled' => $preference ? $preference->isEnabled() : true,
                    'channels' => $preference ? $preference->getChannels() : $defaultChannels,
                    'frequency' => $preference ? $preference->getFrequency() : 'immediate',
                    'quiet_hours_start' => $preference && $preference->getQuietHoursStart() ? 
                        $preference->getQuietHoursStart()->format('H:i') : null,
                    'quiet_hours_end' => $preference && $preference->getQuietHoursEnd() ? 
                        $preference->getQuietHoursEnd()->format('H:i') : null,
                ];
            }

            return new JsonResponse(['preferences' => $result]);
        }

        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            $preferences = $data['preferences'] ?? [];

            try {
                $this->preferenceRepository->updateUserPreferences($user->getId(), $preferences);
                
                return new JsonResponse(['success' => true]);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        }
    }

    #[Route('/preferences/{type}', name: 'preference_type', methods: ['GET', 'PUT'], requirements: ['type' => '[a-z_]+'])]
    public function preferenceByType(string $type, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        if ($request->isMethod('GET')) {
            $preference = $this->preferenceRepository->findUserPreference($user->getId(), $type);
            
            if (!$preference) {
                $defaultChannels = $this->preferenceRepository->getDefaultNotificationTypes()[$type] ?? ['email'];
                $preference = $this->preferenceRepository->getOrCreateUserPreference($user->getId(), $type, $defaultChannels);
            }

            return new JsonResponse([
                'type' => $type,
                'enabled' => $preference->isEnabled(),
                'channels' => $preference->getChannels(),
                'frequency' => $preference->getFrequency(),
                'quiet_hours_start' => $preference->getQuietHoursStart() ? 
                    $preference->getQuietHoursStart()->format('H:i') : null,
                'quiet_hours_end' => $preference->getQuietHoursEnd() ? 
                    $preference->getQuietHoursEnd()->format('H:i') : null,
            ]);
        }

        if ($request->isMethod('PUT')) {
            $data = json_decode($request->getContent(), true);
            
            try {
                $this->preferenceRepository->updateUserPreferences($user->getId(), [$type => $data]);
                
                return new JsonResponse(['success' => true]);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        }
    }

    #[Route('/channels', name: 'available_channels', methods: ['GET'])]
    public function availableChannels(): JsonResponse
    {
        return new JsonResponse([
            'channels' => [
                'email' => 'Email',
                'database' => 'Notifications in-app',
                'push' => 'Notifications push',
                'sms' => 'SMS'
            ]
        ]);
    }

    #[Route('/types', name: 'notification_types', methods: ['GET'])]
    public function notificationTypes(): JsonResponse
    {
        $types = $this->preferenceRepository->getDefaultNotificationTypes();
        
        $typeDescriptions = [
            'welcome' => 'Messages de bienvenue',
            'order_confirmation' => 'Confirmations de commande',
            'order_shipped' => 'Notifications d\'expédition',
            'password_reset' => 'Réinitialisation de mot de passe',
            'comment_reply' => 'Réponses aux commentaires',
            'newsletter' => 'Newsletter',
            'security_alert' => 'Alertes de sécurité',
            'system_maintenance' => 'Maintenance système',
            'marketing' => 'Communications marketing',
            'product_update' => 'Mises à jour produit'
        ];

        $result = [];
        foreach ($types as $type => $defaultChannels) {
            $result[$type] = [
                'name' => $typeDescriptions[$type] ?? ucfirst(str_replace('_', ' ', $type)),
                'default_channels' => $defaultChannels
            ];
        }

        return new JsonResponse(['types' => $result]);
    }

    #[Route('/test', name: 'send_test', methods: ['POST'])]
    public function sendTest(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $type = $data['type'] ?? 'test';

        try {
            $notification = $this->notificationService->sendToUser(
                $user->getId(),
                $type,
                'Notification de test',
                'Ceci est une notification de test pour vérifier vos préférences.',
                ['test' => true],
                ['database', 'email'],
                'normal'
            );

            return new JsonResponse([
                'success' => true,
                'notification_id' => $notification->getId()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function serializeNotification($notification): array
    {
        return [
            'id' => $notification->getId(),
            'type' => $notification->getType(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'priority' => $notification->getPriority(),
            'category' => $notification->getCategory(),
            'action_url' => $notification->getActionUrl(),
            'action_text' => $notification->getActionText(),
            'data' => $notification->getData(),
            'is_read' => $notification->isRead(),
            'created_at' => $notification->getCreatedAt()->format('c'),
            'read_at' => $notification->getReadAt() ? $notification->getReadAt()->format('c') : null,
        ];
    }
}