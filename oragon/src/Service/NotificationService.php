<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\UserRepository;
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
        User $user,
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
            $channels,
            $priority,
            $scheduledAt,
            $user,
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
        array $users,
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
            foreach ($users as $user) {
                if ($user instanceof User) {
                    $notifications[] = $this->sendToUser(
                        $user,
                        $type,
                        $title,
                        $message,
                        $data,
                        $channels,
                        $priority,
                        $scheduledAt
                    );
                }
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
        ?User $user = null,
        ?string $userEmail = null
    ): Notification {
        // Create notification entity
        $notification = new Notification();
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setUser($user);
        $notification->setUserEmail($userEmail);
        $notification->setPriority($priority);
        $notification->setData($data);
        $notification->setScheduledAt($scheduledAt);

        // Determine channels based on user preferences
        if ($user && !$channels) {
            $channels = $this->determineChannelsForUser($user, $type);
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
            $notification->incrementAttempts();
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
    private function determineChannelsForUser(User $user, string $type): array
    {
        $preference = $this->preferenceRepository->findUserPreference($user, $type);
        
        if (!$preference) {
            // Create default preference
            $preference = $this->preferenceRepository->getOrCreateUserPreference($user, $type);
        }

        return $preference->getChannels();
    }

    /**
     * Get user notifications with pagination
     */
    public function getUserNotifications(User $user, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        return $this->notificationRepository->findByUser($user, $limit, $offset);
    }

    /**
     * Get unread notifications count for user
     */
    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->countUnreadByUser($user);
    }

    /**
     * Mark notifications as read
     */
    public function markAsRead(User $user, array $notificationIds = []): int
    {
        return $this->notificationRepository->markAsReadByUser($user, $notificationIds);
    }

    /**
     * Process pending notifications
     */
    public function processPendingNotifications(int $limit = 100): array
    {
        $notifications = $this->notificationRepository->findPendingNotifications($limit);
        $processed = [];

        foreach ($notifications as $notification) {
            try {
                $success = $this->processNotification($notification);
                $processed[] = [
                    'notification' => $notification,
                    'success' => $success
                ];
            } catch (\Exception $e) {
                $notification->incrementAttempts();
                $notification->markAsFailed($e->getMessage());
                $this->notificationRepository->save($notification, true);
                
                $processed[] = [
                    'notification' => $notification,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $processed;
    }

    /**
     * Process scheduled notifications
     */
    public function processScheduledNotifications(): array
    {
        $notifications = $this->notificationRepository->findScheduledNotifications();
        $processed = [];

        foreach ($notifications as $notification) {
            try {
                $success = $this->processNotification($notification);
                $processed[] = [
                    'notification' => $notification,
                    'success' => $success
                ];
            } catch (\Exception $e) {
                $notification->incrementAttempts();
                $notification->markAsFailed($e->getMessage());
                $this->notificationRepository->save($notification, true);
                
                $processed[] = [
                    'notification' => $notification,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $processed;
    }

    /**
     * Retry failed notifications
     */
    public function retryFailedNotifications(int $maxAttempts = 3): array
    {
        $notifications = $this->notificationRepository->retryFailedNotifications($maxAttempts);
        $processed = [];

        foreach ($notifications as $notification) {
            try {
                $success = $this->processNotification($notification);
                $processed[] = [
                    'notification' => $notification,
                    'success' => $success
                ];
            } catch (\Exception $e) {
                $notification->markAsFailed($e->getMessage());
                $this->notificationRepository->save($notification, true);
                
                $processed[] = [
                    'notification' => $notification,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $processed;
    }

    /**
     * Clean old notifications
     */
    public function cleanOldNotifications(int $daysOld = 90): int
    {
        $cutoffDate = new \DateTimeImmutable('-' . $daysOld . ' days');
        return $this->notificationRepository->deleteOldNotifications($cutoffDate);
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        return $this->notificationRepository->getStatistics($from, $to);
    }

    /**
     * Send welcome notification to new user
     */
    public function sendWelcomeNotification(User $user): Notification
    {
        return $this->sendToUser(
            $user,
            'welcome',
            'Bienvenue sur notre plateforme !',
            'Votre compte a été créé avec succès. Nous sommes ravis de vous accueillir.',
            [
                'user_name' => $user->getFirstName() ?? $user->getEmail(),
                'action_url' => '/dashboard',
                'action_text' => 'Accéder à votre tableau de bord'
            ]
        );
    }

    /**
     * Send password reset notification
     */
    public function sendPasswordResetNotification(User $user, string $resetToken): Notification
    {
        return $this->sendToUser(
            $user,
            'password_reset',
            'Réinitialisation de votre mot de passe',
            'Une demande de réinitialisation de mot de passe a été effectuée pour votre compte.',
            [
                'user_name' => $user->getFirstName() ?? $user->getEmail(),
                'reset_token' => $resetToken,
                'action_url' => '/reset-password?token=' . $resetToken,
                'action_text' => 'Réinitialiser mon mot de passe'
            ],
            ['email']
        );
    }

    /**
     * Send system alert notification
     */
    public function sendSystemAlert(string $title, string $message, array $data = []): array
    {
        $users = $this->userRepository->findAll();
        
        return $this->sendBulk(
            $users,
            'system_alert',
            $title,
            $message,
            $data,
            ['email', 'database'],
            'high'
        );
    }
}