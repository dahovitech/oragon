<?php

namespace App\Bundle\NotificationBundle\Service;

use App\Bundle\NotificationBundle\Entity\Notification;
use App\Bundle\NotificationBundle\Entity\NotificationPreference;
use App\Bundle\NotificationBundle\Repository\NotificationRepository;
use App\Bundle\NotificationBundle\Repository\NotificationPreferenceRepository;
use App\Bundle\UserBundle\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class NotificationService
{
    private EntityManagerInterface $entityManager;
    private NotificationRepository $notificationRepository;
    private NotificationPreferenceRepository $preferenceRepository;
    private UserRepository $userRepository;
    private EmailService $emailService;
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        EntityManagerInterface $entityManager,
        NotificationRepository $notificationRepository,
        NotificationPreferenceRepository $preferenceRepository,
        UserRepository $userRepository,
        EmailService $emailService,
        TokenStorageInterface $tokenStorage
    ) {
        $this->entityManager = $entityManager;
        $this->notificationRepository = $notificationRepository;
        $this->preferenceRepository = $preferenceRepository;
        $this->userRepository = $userRepository;
        $this->emailService = $emailService;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Send notification to a specific user
     */
    public function sendToUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?array $channels = null,
        string $priority = 'normal',
        ?\DateTimeInterface $scheduledAt = null
    ): Notification {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new \InvalidArgumentException("User with ID {$userId} not found");
        }

        return $this->send(
            $type,
            $title,
            $message,
            $data,
            $channels,
            $priority,
            $scheduledAt,
            $userId,
            $user->getEmail()
        );
    }

    /**
     * Send notification to email address (guest or user)
     */
    public function sendToEmail(
        string $email,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?array $channels = null,
        string $priority = 'normal',
        ?\DateTimeInterface $scheduledAt = null
    ): Notification {
        return $this->send(
            $type,
            $title,
            $message,
            $data,
            $channels ?: ['email'],
            $priority,
            $scheduledAt,
            null,
            $email
        );
    }

    /**
     * Send bulk notifications
     */
    public function sendBulk(
        array $userIds,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?array $channels = null,
        string $priority = 'normal',
        ?\DateTimeInterface $scheduledAt = null
    ): array {
        $notifications = [];

        $this->entityManager->beginTransaction();
        try {
            foreach ($userIds as $userId) {
                $notifications[] = $this->sendToUser(
                    $userId,
                    $type,
                    $title,
                    $message,
                    $data,
                    $channels,
                    $priority,
                    $scheduledAt
                );
            }

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return $notifications;
    }

    /**
     * Create and potentially send notification
     */
    private function send(
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?array $channels = null,
        string $priority = 'normal',
        ?\DateTimeInterface $scheduledAt = null,
        ?int $userId = null,
        ?string $userEmail = null
    ): Notification {
        // Create notification entity
        $notification = new Notification();
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setUserId($userId);
        $notification->setUserEmail($userEmail);
        $notification->setPriority($priority);
        $notification->setData($data);
        $notification->setScheduledAt($scheduledAt);

        // Determine channels based on user preferences
        if ($userId && !$channels) {
            $channels = $this->determineChannelsForUser($userId, $type);
        } elseif (!$channels) {
            $channels = ['email']; // Default fallback
        }

        $notification->setChannels($channels);

        // Add action URL if provided in data
        if (isset($data['action_url'])) {
            $notification->setActionUrl($data['action_url']);
        }
        if (isset($data['action_text'])) {
            $notification->setActionText($data['action_text']);
        }

        $this->notificationRepository->save($notification, true);

        // Send immediately if not scheduled
        if (!$scheduledAt || $scheduledAt <= new \DateTimeImmutable()) {
            $this->processNotification($notification);
        }

        return $notification;
    }

    /**
     * Process notification for sending
     */
    public function processNotification(Notification $notification): bool
    {
        if ($notification->getStatus() !== 'pending') {
            return false;
        }

        $channels = $notification->getChannels() ?? [];
        $success = false;

        foreach ($channels as $channel) {
            try {
                $channelSuccess = $this->sendViaChannel($notification, $channel);
                if ($channelSuccess) {
                    $success = true;
                }
            } catch (\Exception $e) {
                // Log error but continue with other channels
                error_log("Failed to send notification {$notification->getId()} via {$channel}: " . $e->getMessage());
            }
        }

        if ($success) {
            $notification->markAsSent();
        } else {
            $notification->markAsFailed('All channels failed');
        }

        $this->notificationRepository->save($notification, true);

        return $success;
    }

    /**
     * Send notification via specific channel
     */
    private function sendViaChannel(Notification $notification, string $channel): bool
    {
        switch ($channel) {
            case 'email':
                return $this->sendViaEmail($notification);
            
            case 'database':
                return $this->sendViaDatabase($notification);
            
            case 'push':
                return $this->sendViaPush($notification);
            
            case 'sms':
                return $this->sendViaSms($notification);
            
            default:
                throw new \InvalidArgumentException("Unsupported channel: {$channel}");
        }
    }

    /**
     * Send notification via email
     */
    private function sendViaEmail(Notification $notification): bool
    {
        if (!$notification->getUserEmail()) {
            return false;
        }

        return $this->emailService->sendNotificationEmail($notification);
    }

    /**
     * Send notification via database (for in-app notifications)
     */
    private function sendViaDatabase(Notification $notification): bool
    {
        // For database notifications, just mark as sent since it's already in DB
        return true;
    }

    /**
     * Send notification via push
     */
    private function sendViaPush(Notification $notification): bool
    {
        // TODO: Implement push notification service
        // This would integrate with Firebase FCM, Apple Push, etc.
        return false;
    }

    /**
     * Send notification via SMS
     */
    private function sendViaSms(Notification $notification): bool
    {
        // TODO: Implement SMS service
        // This would integrate with Twilio, AWS SNS, etc.
        return false;
    }

    /**
     * Determine channels based on user preferences
     */
    private function determineChannelsForUser(int $userId, string $type): array
    {
        $preference = $this->preferenceRepository->findUserPreference($userId, $type);
        
        if (!$preference) {
            // Create default preference
            $preference = $this->preferenceRepository->getOrCreateUserPreference($userId, $type);
        }

        return $preference->getChannels();
    }

    /**
     * Get user notifications with pagination
     */
    public function getUserNotifications(int $userId, bool $unreadOnly = false, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        $qb = $this->notificationRepository->createQueryBuilder('n')
            ->where('n.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('n.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($unreadOnly) {
            $qb->andWhere('n.readAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = $this->notificationRepository->findOneBy([
            'id' => $notificationId,
            'userId' => $userId
        ]);

        if (!$notification) {
            return false;
        }

        $notification->markAsRead();
        $this->notificationRepository->save($notification, true);

        return true;
    }

    /**
     * Mark multiple notifications as read
     */
    public function markMultipleAsRead(array $notificationIds, int $userId): int
    {
        return $this->notificationRepository->markAsRead($notificationIds, $userId);
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(int $userId): int
    {
        return $this->notificationRepository->markAllAsRead($userId);
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->notificationRepository->countUnreadNotifications($userId);
    }

    /**
     * Process pending notifications
     */
    public function processPendingNotifications(int $batchSize = 100): int
    {
        $notifications = $this->notificationRepository->findPendingNotifications($batchSize);
        $processed = 0;

        foreach ($notifications as $notification) {
            if ($this->processNotification($notification)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Send system notification to all users
     */
    public function sendSystemNotification(
        string $title,
        string $message,
        array $data = [],
        string $priority = 'high'
    ): int {
        $users = $this->userRepository->findAll();
        $userIds = array_map(fn($user) => $user->getId(), $users);

        $notifications = $this->sendBulk(
            $userIds,
            'system',
            $title,
            $message,
            $data,
            ['database', 'email'],
            $priority
        );

        return count($notifications);
    }

    /**
     * Quick notification methods for common types
     */
    public function sendWelcomeNotification(int $userId): Notification
    {
        return $this->sendToUser(
            $userId,
            'welcome',
            'Bienvenue sur notre plateforme !',
            'Merci de vous être inscrit. Découvrez toutes nos fonctionnalités.',
            ['action_url' => '/dashboard', 'action_text' => 'Découvrir']
        );
    }

    public function sendOrderConfirmation(int $userId, array $orderData): Notification
    {
        return $this->sendToUser(
            $userId,
            'order_confirmation',
            'Commande confirmée',
            "Votre commande #{$orderData['id']} a été confirmée.",
            $orderData
        );
    }

    public function sendPasswordReset(string $email, string $resetToken): Notification
    {
        return $this->sendToEmail(
            $email,
            'password_reset',
            'Réinitialisation de mot de passe',
            'Cliquez sur le lien pour réinitialiser votre mot de passe.',
            [
                'reset_token' => $resetToken,
                'action_url' => "/reset-password/{$resetToken}",
                'action_text' => 'Réinitialiser'
            ]
        );
    }
}