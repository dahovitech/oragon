<?php

namespace App\Bundle\NotificationBundle\Repository;

use App\Bundle\NotificationBundle\Entity\NotificationPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    public function save(NotificationPreference $preference, bool $flush = false): void
    {
        $this->getEntityManager()->persist($preference);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NotificationPreference $preference, bool $flush = false): void
    {
        $this->getEntityManager()->remove($preference);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find user preferences for a specific notification type
     */
    public function findUserPreference(int $userId, string $type): ?NotificationPreference
    {
        return $this->findOneBy([
            'userId' => $userId,
            'type' => $type
        ]);
    }

    /**
     * Get all preferences for a user
     */
    public function findUserPreferences(int $userId): array
    {
        return $this->findBy(
            ['userId' => $userId],
            ['type' => 'ASC']
        );
    }

    /**
     * Get or create user preference for a type
     */
    public function getOrCreateUserPreference(int $userId, string $type, array $defaultChannels = ['email']): NotificationPreference
    {
        $preference = $this->findUserPreference($userId, $type);

        if (!$preference) {
            $preference = new NotificationPreference();
            $preference->setUserId($userId);
            $preference->setType($type);
            $preference->setChannels($defaultChannels);
            $this->save($preference, true);
        }

        return $preference;
    }

    /**
     * Update user preferences in batch
     */
    public function updateUserPreferences(int $userId, array $preferences): void
    {
        $this->getEntityManager()->beginTransaction();

        try {
            foreach ($preferences as $type => $settings) {
                $preference = $this->getOrCreateUserPreference($userId, $type);
                
                if (isset($settings['enabled'])) {
                    $preference->setEnabled($settings['enabled']);
                }
                
                if (isset($settings['channels'])) {
                    $preference->setChannels($settings['channels']);
                }
                
                if (isset($settings['frequency'])) {
                    $preference->setFrequency($settings['frequency']);
                }
                
                if (isset($settings['quiet_hours_start'])) {
                    $time = \DateTime::createFromFormat('H:i', $settings['quiet_hours_start']);
                    $preference->setQuietHoursStart($time ?: null);
                }
                
                if (isset($settings['quiet_hours_end'])) {
                    $time = \DateTime::createFromFormat('H:i', $settings['quiet_hours_end']);
                    $preference->setQuietHoursEnd($time ?: null);
                }

                $this->save($preference);
            }

            $this->getEntityManager()->commit();
        } catch (\Exception $e) {
            $this->getEntityManager()->rollback();
            throw $e;
        }
    }

    /**
     * Check if user wants to receive notification via specific channel
     */
    public function shouldReceiveNotification(int $userId, string $type, string $channel): bool
    {
        $preference = $this->findUserPreference($userId, $type);

        if (!$preference) {
            // Default behavior - assume user wants email notifications
            return $channel === 'email';
        }

        return $preference->shouldReceiveNotification($channel);
    }

    /**
     * Get users who want to receive a specific notification type
     */
    public function getUsersForNotificationType(string $type, string $channel): array
    {
        return $this->createQueryBuilder('np')
            ->select('np.userId')
            ->where('np.type = :type')
            ->andWhere('np.enabled = true')
            ->andWhere('JSON_CONTAINS(np.channels, :channel) = 1')
            ->setParameter('type', $type)
            ->setParameter('channel', json_encode($channel))
            ->getQuery()
            ->getResult();
    }

    /**
     * Get notification preferences statistics
     */
    public function getPreferencesStatistics(): array
    {
        // Count by notification type
        $typeStats = $this->createQueryBuilder('np')
            ->select('np.type, COUNT(np.id) as count, SUM(CASE WHEN np.enabled = true THEN 1 ELSE 0 END) as enabled_count')
            ->groupBy('np.type')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        // Count by channel
        $channelStats = $this->getEntityManager()->getConnection()
            ->executeQuery('
                SELECT 
                    channel.value as channel,
                    COUNT(*) as count
                FROM notification_preferences np
                CROSS JOIN JSON_TABLE(np.channels, "$[*]" COLUMNS (value VARCHAR(50) PATH "$")) channel
                WHERE np.enabled = 1
                GROUP BY channel.value
                ORDER BY count DESC
            ')
            ->fetchAllAssociative();

        // Overall stats
        $overallStats = $this->createQueryBuilder('np')
            ->select('
                COUNT(np.id) as total_preferences,
                SUM(CASE WHEN np.enabled = true THEN 1 ELSE 0 END) as enabled_preferences,
                COUNT(DISTINCT np.userId) as users_with_preferences
            ')
            ->getQuery()
            ->getSingleResult();

        return [
            'by_type' => $typeStats,
            'by_channel' => $channelStats,
            'overall' => $overallStats
        ];
    }

    /**
     * Get default notification types that should be created for new users
     */
    public function getDefaultNotificationTypes(): array
    {
        return [
            'welcome' => ['email'],
            'order_confirmation' => ['email', 'sms'],
            'order_shipped' => ['email'],
            'password_reset' => ['email'],
            'comment_reply' => ['email'],
            'newsletter' => ['email'],
            'security_alert' => ['email', 'push'],
            'system_maintenance' => ['email'],
            'marketing' => ['email'],
            'product_update' => ['email']
        ];
    }

    /**
     * Initialize default preferences for a new user
     */
    public function initializeDefaultPreferences(int $userId): void
    {
        $defaultTypes = $this->getDefaultNotificationTypes();

        $this->getEntityManager()->beginTransaction();

        try {
            foreach ($defaultTypes as $type => $channels) {
                $existing = $this->findUserPreference($userId, $type);
                if (!$existing) {
                    $preference = new NotificationPreference();
                    $preference->setUserId($userId);
                    $preference->setType($type);
                    $preference->setChannels($channels);
                    $this->save($preference);
                }
            }

            $this->getEntityManager()->commit();
        } catch (\Exception $e) {
            $this->getEntityManager()->rollback();
            throw $e;
        }
    }
}