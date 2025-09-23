<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: User::class)]
class NotificationEventListener
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send welcome notification when a new user is created
     */
    public function postPersist(LifecycleEventArgs $event): void
    {
        $entity = $event->getObject();

        if ($entity instanceof User) {
            // Send welcome notification to new user
            try {
                $this->notificationService->sendWelcomeNotification($entity);
            } catch (\Exception $e) {
                // Log the error but don't break the registration process
                error_log('Failed to send welcome notification to user ' . $entity->getId() . ': ' . $e->getMessage());
            }
        }
    }
}