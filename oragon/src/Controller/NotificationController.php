<?php

namespace App\Controller;

use App\Entity\NotificationPreference;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    private NotificationRepository $notificationRepository;
    private NotificationPreferenceRepository $preferenceRepository;

    public function __construct(
        NotificationRepository $notificationRepository,
        NotificationPreferenceRepository $preferenceRepository
    ) {
        $this->notificationRepository = $notificationRepository;
        $this->preferenceRepository = $preferenceRepository;
    }

    #[Route('', name: 'user_notifications')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        $notifications = $this->notificationRepository->findByUser($user, $limit, ($page - 1) * $limit);
        $unreadCount = $this->notificationRepository->countUnreadByUser($user);

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'current_page' => $page
        ]);
    }

    #[Route('/unread', name: 'user_notifications_unread')]
    public function unread(): Response
    {
        $user = $this->getUser();
        $notifications = $this->notificationRepository->findUnreadByUser($user);

        return $this->render('notification/unread.html.twig', [
            'notifications' => $notifications
        ]);
    }

    #[Route('/mark-read', name: 'user_notifications_mark_read', methods: ['POST'])]
    public function markAsRead(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $notificationIds = $request->request->get('notification_ids', []);

        try {
            $markedCount = $this->notificationRepository->markAsReadByUser($user, $notificationIds);
            $unreadCount = $this->notificationRepository->countUnreadByUser($user);

            return new JsonResponse([
                'success' => true,
                'marked_count' => $markedCount,
                'unread_count' => $unreadCount
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/mark-all-read', name: 'user_notifications_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->getUser();

        try {
            $markedCount = $this->notificationRepository->markAsReadByUser($user);

            return new JsonResponse([
                'success' => true,
                'marked_count' => $markedCount,
                'message' => sprintf('%d notifications marquées comme lues', $markedCount)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/count', name: 'user_notifications_count')]
    public function getUnreadCount(): JsonResponse
    {
        $user = $this->getUser();
        $count = $this->notificationRepository->countUnreadByUser($user);

        return new JsonResponse(['count' => $count]);
    }

    #[Route('/preferences', name: 'user_notification_preferences')]
    public function preferences(Request $request): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $preferences = $request->request->all('preferences');
            
            try {
                $this->preferenceRepository->updateUserPreferences($user, $preferences);
                $this->addFlash('success', 'Préférences mises à jour avec succès');
                return $this->redirectToRoute('user_notification_preferences');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
            }
        }

        // Get existing preferences or create defaults
        $userPreferences = $this->preferenceRepository->findByUser($user);
        
        if (empty($userPreferences)) {
            $userPreferences = $this->preferenceRepository->initializeDefaultPreferences($user);
        }

        // Group preferences by type
        $groupedPreferences = [];
        foreach ($userPreferences as $preference) {
            $groupedPreferences[$preference->getType()] = $preference;
        }

        return $this->render('notification/preferences.html.twig', [
            'preferences' => $groupedPreferences,
            'available_channels' => NotificationPreference::getAvailableChannels(),
            'available_frequencies' => NotificationPreference::getAvailableFrequencies()
        ]);
    }

    #[Route('/preferences/reset', name: 'user_notification_preferences_reset', methods: ['POST'])]
    public function resetPreferences(): JsonResponse
    {
        $user = $this->getUser();

        try {
            // Delete existing preferences
            $existingPreferences = $this->preferenceRepository->findByUser($user);
            foreach ($existingPreferences as $preference) {
                $this->preferenceRepository->remove($preference);
            }

            // Initialize default preferences
            $defaultPreferences = $this->preferenceRepository->initializeDefaultPreferences($user);

            return new JsonResponse([
                'success' => true,
                'message' => 'Préférences réinitialisées aux valeurs par défaut',
                'preferences_count' => count($defaultPreferences)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/latest', name: 'api_notifications_latest')]
    public function getLatestNotifications(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = $request->query->getInt('limit', 10);

        $notifications = $this->notificationRepository->findUnreadByUser($user, $limit);
        $data = [];

        foreach ($notifications as $notification) {
            $data[] = [
                'id' => $notification->getId(),
                'type' => $notification->getType(),
                'title' => $notification->getTitle(),
                'message' => $notification->getMessage(),
                'created_at' => $notification->getCreatedAt()->format('c'),
                'action_url' => $notification->getActionUrl(),
                'action_text' => $notification->getActionText(),
                'is_read' => $notification->isRead()
            ];
        }

        return new JsonResponse([
            'notifications' => $data,
            'unread_count' => $this->notificationRepository->countUnreadByUser($user)
        ]);
    }

    #[Route('/{id}/read', name: 'user_notification_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markSingleAsRead(int $id): JsonResponse
    {
        $user = $this->getUser();
        
        try {
            $notification = $this->notificationRepository->find($id);
            
            if (!$notification || $notification->getUser() !== $user) {
                return new JsonResponse(['error' => 'Notification non trouvée'], 404);
            }

            if (!$notification->isRead()) {
                $notification->markAsRead();
                $this->notificationRepository->save($notification, true);
            }

            return new JsonResponse([
                'success' => true,
                'unread_count' => $this->notificationRepository->countUnreadByUser($user)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/widget', name: 'user_notifications_widget')]
    public function widget(): Response
    {
        $user = $this->getUser();
        $notifications = $this->notificationRepository->findUnreadByUser($user, 5);
        $unreadCount = $this->notificationRepository->countUnreadByUser($user);

        return $this->render('notification/widget.html.twig', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }
}