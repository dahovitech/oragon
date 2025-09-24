<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    private NotificationService $notificationService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        NotificationService $notificationService,
        EntityManagerInterface $entityManager
    ) {
        $this->notificationService = $notificationService;
        $this->entityManager = $entityManager;
    }

    #[Route('', name: 'app_notifications_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $notifications = $this->notificationService->getAllNotifications($user);
        $unreadCount = $this->notificationService->countUnreadNotifications($user);

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    #[Route('/unread', name: 'app_notifications_unread')]
    public function unread(): JsonResponse
    {
        $user = $this->getUser();
        $notifications = $this->notificationService->getUnreadNotifications($user);
        $unreadCount = $this->notificationService->countUnreadNotifications($user);

        $data = [];
        foreach ($notifications as $notification) {
            $data[] = [
                'id' => $notification->getId(),
                'title' => $notification->getTitle(),
                'message' => $notification->getMessage(),
                'type' => $notification->getType(),
                'priority' => $notification->getPriority(),
                'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
                'actionUrl' => $notification->getActionUrl(),
                'actionLabel' => $notification->getActionLabel(),
            ];
        }

        return new JsonResponse([
            'notifications' => $data,
            'unread_count' => $unreadCount,
        ]);
    }

    #[Route('/{id}/read', name: 'app_notifications_mark_read', methods: ['POST'])]
    public function markAsRead(Notification $notification): JsonResponse
    {
        if ($notification->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $this->notificationService->markAsRead($notification);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/mark-all-read', name: 'app_notifications_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->getUser();
        $this->notificationService->markAllAsRead($user);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/delete', name: 'app_notifications_delete', methods: ['DELETE'])]
    public function delete(Notification $notification): JsonResponse
    {
        if ($notification->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $this->notificationService->deleteNotification($notification);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/count', name: 'app_notifications_count')]
    public function count(): JsonResponse
    {
        $user = $this->getUser();
        $unreadCount = $this->notificationService->countUnreadNotifications($user);

        return new JsonResponse(['count' => $unreadCount]);
    }
}
