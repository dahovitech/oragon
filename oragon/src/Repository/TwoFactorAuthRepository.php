<?php

namespace App\Repository;

use App\Entity\TwoFactorAuth;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TwoFactorAuth>
 *
 * @method TwoFactorAuth|null find($id, $lockMode = null, $lockVersion = null)
 * @method TwoFactorAuth|null findOneBy(array $criteria, array $orderBy = null)
 * @method TwoFactorAuth[]    findAll()
 * @method TwoFactorAuth[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TwoFactorAuthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TwoFactorAuth::class);
    }

    public function save(TwoFactorAuth $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TwoFactorAuth $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find or create 2FA record for user
     */
    public function findOrCreateForUser(User $user): TwoFactorAuth
    {
        $twoFactor = $this->findOneBy(['user' => $user]);
        
        if (!$twoFactor) {
            $twoFactor = new TwoFactorAuth();
            $twoFactor->setUser($user);
            $this->save($twoFactor, true);
        }
        
        return $twoFactor;
    }

    /**
     * Find 2FA by user
     */
    public function findByUser(User $user): ?TwoFactorAuth
    {
        return $this->findOneBy(['user' => $user]);
    }

    /**
     * Find enabled 2FA configurations
     */
    public function findEnabledConfigurations(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('t.enabledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users with 2FA enabled
     */
    public function findUsersWithTwoFactorEnabled(): array
    {
        return $this->createQueryBuilder('t')
            ->select('IDENTITY(t.user)')
            ->andWhere('t.enabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Count enabled 2FA configurations
     */
    public function countEnabledConfigurations(): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.enabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find configurations by usage
     */
    public function findMostUsedConfigurations(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.enabled = :enabled')
            ->andWhere('t.usageCount > 0')
            ->setParameter('enabled', true)
            ->orderBy('t.usageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find configurations not used recently
     */
    public function findUnusedConfigurations(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.enabled = :enabled')
            ->andWhere('t.lastUsedAt < :since OR t.lastUsedAt IS NULL')
            ->setParameter('enabled', true)
            ->setParameter('since', $since)
            ->orderBy('t.lastUsedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find configurations with low backup codes
     */
    public function findConfigurationsWithLowBackupCodes(int $threshold = 2): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.enabled = :enabled')
            ->andWhere('JSON_LENGTH(t.backupCodes) <= :threshold')
            ->setParameter('enabled', true)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get 2FA adoption statistics
     */
    public function getAdoptionStatistics(): array
    {
        $totalUsers = $this->getEntityManager()
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $enabledCount = $this->countEnabledConfigurations();
        $configuredCount = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.secret IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_users' => $totalUsers,
            'enabled_count' => $enabledCount,
            'configured_count' => $configuredCount,
            'adoption_rate' => $totalUsers > 0 ? round(($enabledCount / $totalUsers) * 100, 2) : 0,
            'configuration_rate' => $totalUsers > 0 ? round(($configuredCount / $totalUsers) * 100, 2) : 0,
        ];
    }

    /**
     * Get usage statistics
     */
    public function getUsageStatistics(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select([
                'COUNT(t.id) as total_configurations',
                'SUM(t.usageCount) as total_usage',
                'AVG(t.usageCount) as average_usage',
                'MAX(t.usageCount) as max_usage',
            ])
            ->andWhere('t.enabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleResult();

        // Get recent usage (last 30 days)
        $recentUsage = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.enabled = :enabled')
            ->andWhere('t.lastUsedAt >= :since')
            ->setParameter('enabled', true)
            ->setParameter('since', new \DateTimeImmutable('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return array_merge($result, [
            'recent_usage_30d' => $recentUsage,
        ]);
    }

    /**
     * Clean up old unused configurations
     */
    public function cleanupOldConfigurations(\DateTimeInterface $cutoffDate): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.enabled = :enabled')
            ->andWhere('t.createdAt < :cutoff')
            ->andWhere('t.secret IS NULL OR t.secret = :empty')
            ->setParameter('enabled', false)
            ->setParameter('cutoff', $cutoffDate)
            ->setParameter('empty', '')
            ->getQuery()
            ->execute();
    }

    /**
     * Find configurations requiring attention
     */
    public function findConfigurationsRequiringAttention(): array
    {
        $lowBackupCodes = $this->findConfigurationsWithLowBackupCodes(2);
        $unused = $this->findUnusedConfigurations(new \DateTimeImmutable('-90 days'));
        
        return [
            'low_backup_codes' => $lowBackupCodes,
            'unused_configurations' => $unused,
        ];
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed(TwoFactorAuth $twoFactor): void
    {
        $twoFactor->incrementUsageCount();
        $this->save($twoFactor, true);
    }

    /**
     * Disable 2FA for user
     */
    public function disableForUser(User $user): bool
    {
        $twoFactor = $this->findByUser($user);
        
        if ($twoFactor && $twoFactor->isEnabled()) {
            $twoFactor->setEnabled(false);
            $this->save($twoFactor, true);
            return true;
        }
        
        return false;
    }

    /**
     * Reset 2FA configuration for user
     */
    public function resetForUser(User $user): bool
    {
        $twoFactor = $this->findByUser($user);
        
        if ($twoFactor) {
            $twoFactor->reset();
            $this->save($twoFactor, true);
            return true;
        }
        
        return false;
    }
}