<?php

namespace App\Repository;

use App\Entity\NotificationPreference;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationPreference>
 *
 * @method NotificationPreference|null find($id, $lockMode = null, $lockVersion = null)
 * @method NotificationPreference|null findOneBy(array $criteria, array $orderBy = null)
 * @method NotificationPreference[]    findAll()
 * @method NotificationPreference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    public function save(NotificationPreference $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NotificationPreference $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find user preference by type
     */
    public function findUserPreference(User $user, string $type): ?NotificationPreference
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all preferences for a user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.type', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get or create user preference
     */
    public function getOrCreateUserPreference(User $user, string $type): NotificationPreference
    {
        $preference = $this->findUserPreference($user, $type);

        if (!$preference) {
            $preference = new NotificationPreference();
            $preference->setUser($user);
            $preference->setType($type);
            $preference->setChannels(NotificationPreference::getDefaultChannelsForType($type));
            
            $this->save($preference, true);
        }

        return $preference;
    }

    /**
     * Update user preferences
     */
    public function updateUserPreferences(User $user, array $preferences): array
    {
        $updated = [];

        $this->getEntityManager()->beginTransaction();
        try {
            foreach ($preferences as $type => $settings) {
                $preference = $this->getOrCreateUserPreference($user, $type);
                
                if (isset($settings['enabled'])) {
                    $preference->setEnabled((bool)$settings['enabled']);
                }
                
                if (isset($settings['channels'])) {
                    $preference->setChannels((array)$settings['channels']);
                }
                
                if (isset($settings['frequency'])) {
                    $preference->setFrequency((string)$settings['frequency']);
                }
                
                if (isset($settings['quiet_hours_start'])) {
                    $time = $settings['quiet_hours_start'] ? new \DateTime($settings['quiet_hours_start']) : null;
                    $preference->setQuietHoursStart($time);
                }
                
                if (isset($settings['quiet_hours_end'])) {
                    $time = $settings['quiet_hours_end'] ? new \DateTime($settings['quiet_hours_end']) : null;
                    $preference->setQuietHoursEnd($time);
                }
                
                if (isset($settings['settings'])) {
                    $preference->setSettings((array)$settings['settings']);
                }

                $this->save($preference);
                $updated[] = $preference;
            }

            $this->getEntityManager()->commit();
            $this->getEntityManager()->flush();
        } catch (\Exception $e) {
            $this->getEntityManager()->rollback();
            throw $e;
        }

        return $updated;
    }

    /**
     * Find users with specific preference settings
     */
    public function findUsersWithChannelEnabled(string $type, string $channel): array
    {
        return $this->createQueryBuilder('p')
            ->select('IDENTITY(p.user)')
            ->andWhere('p.type = :type')
            ->andWhere('p.enabled = :enabled')
            ->andWhere('JSON_CONTAINS(p.channels, :channel) = 1')
            ->setParameter('type', $type)
            ->setParameter('enabled', true)
            ->setParameter('channel', '"' . $channel . '"')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Find users for digest notifications
     */
    public function findUsersForDigest(string $frequency): array
    {
        return $this->createQueryBuilder('p')
            ->select('IDENTITY(p.user) as user_id, p.type')
            ->andWhere('p.frequency = :frequency')
            ->andWhere('p.enabled = :enabled')
            ->setParameter('frequency', $frequency)
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if user should receive notification
     */
    public function shouldUserReceiveNotification(User $user, string $type, string $channel): bool
    {
        $preference = $this->findUserPreference($user, $type);
        
        if (!$preference) {
            // No preference set, use defaults
            $defaultChannels = NotificationPreference::getDefaultChannelsForType($type);
            return in_array($channel, $defaultChannels);
        }

        return $preference->shouldReceiveNotification($channel);
    }

    /**
     * Get users in quiet hours
     */
    public function findUsersInQuietHours(): array
    {
        $now = new \DateTime();
        $currentTime = $now->format('H:i:s');

        return $this->createQueryBuilder('p')
            ->select('IDENTITY(p.user)')
            ->andWhere('p.quietHoursStart IS NOT NULL')
            ->andWhere('p.quietHoursEnd IS NOT NULL')
            ->andWhere(
                '(p.quietHoursStart <= p.quietHoursEnd AND :currentTime BETWEEN p.quietHoursStart AND p.quietHoursEnd) OR ' .
                '(p.quietHoursStart > p.quietHoursEnd AND (:currentTime >= p.quietHoursStart OR :currentTime <= p.quietHoursEnd))'
            )
            ->setParameter('currentTime', $currentTime)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Initialize default preferences for user
     */
    public function initializeDefaultPreferences(User $user): array
    {
        $defaultTypes = [
            'welcome',
            'order_confirmation',
            'password_reset',
            'comment_reply',
            'system_alert',
            'newsletter',
            'marketing',
            'security'
        ];

        $preferences = [];

        foreach ($defaultTypes as $type) {
            $existing = $this->findUserPreference($user, $type);
            
            if (!$existing) {
                $preference = new NotificationPreference();
                $preference->setUser($user);
                $preference->setType($type);
                $preference->setChannels(NotificationPreference::getDefaultChannelsForType($type));
                
                // Set specific defaults based on type
                switch ($type) {
                    case 'marketing':
                    case 'newsletter':
                        $preference->setFrequency('weekly');
                        break;
                    case 'security':
                    case 'password_reset':
                        $preference->setFrequency('immediate');
                        break;
                    default:
                        $preference->setFrequency('immediate');
                }

                $this->save($preference);
                $preferences[] = $preference;
            }
        }

        if (!empty($preferences)) {
            $this->getEntityManager()->flush();
        }

        return $preferences;
    }

    /**
     * Get preference statistics
     */
    public function getStatistics(): array
    {
        return $this->createQueryBuilder('p')
            ->select([
                'p.type',
                'COUNT(p.id) as total_users',
                'SUM(CASE WHEN p.enabled = true THEN 1 ELSE 0 END) as enabled_users',
                'SUM(CASE WHEN JSON_CONTAINS(p.channels, \'"email"\') = 1 THEN 1 ELSE 0 END) as email_users',
                'SUM(CASE WHEN JSON_CONTAINS(p.channels, \'"database"\') = 1 THEN 1 ELSE 0 END) as database_users',
                'SUM(CASE WHEN p.frequency = \'immediate\' THEN 1 ELSE 0 END) as immediate_users',
                'SUM(CASE WHEN p.frequency = \'daily\' THEN 1 ELSE 0 END) as daily_users',
                'SUM(CASE WHEN p.frequency = \'weekly\' THEN 1 ELSE 0 END) as weekly_users'
            ])
            ->groupBy('p.type')
            ->orderBy('total_users', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Bulk update preferences
     */
    public function bulkUpdatePreferences(array $userIds, string $type, array $settings): int
    {
        $updated = 0;

        $this->getEntityManager()->beginTransaction();
        try {
            foreach ($userIds as $userId) {
                $user = $this->getEntityManager()->getRepository(User::class)->find($userId);
                if ($user) {
                    $preference = $this->getOrCreateUserPreference($user, $type);
                    
                    foreach ($settings as $key => $value) {
                        switch ($key) {
                            case 'enabled':
                                $preference->setEnabled((bool)$value);
                                break;
                            case 'channels':
                                $preference->setChannels((array)$value);
                                break;
                            case 'frequency':
                                $preference->setFrequency((string)$value);
                                break;
                        }
                    }
                    
                    $this->save($preference);
                    $updated++;
                }
            }

            $this->getEntityManager()->commit();
            $this->getEntityManager()->flush();
        } catch (\Exception $e) {
            $this->getEntityManager()->rollback();
            throw $e;
        }

        return $updated;
    }
}