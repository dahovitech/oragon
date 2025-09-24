<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Find active users
     */
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users by role
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find administrators
     */
    public function findAdministrators(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :admin OR u.roles LIKE :super_admin')
            ->setParameter('admin', '%"ROLE_ADMIN"%')
            ->setParameter('super_admin', '%"ROLE_SUPER_ADMIN"%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search users by name or email
     */
    public function searchUsers(string $query): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.firstName LIKE :query OR u.lastName LIKE :query OR u.email LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics(): array
    {
        $qb = $this->createQueryBuilder('u');
        
        $totalUsers = $qb->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $activeUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $recentUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :date')
            ->setParameter('date', new \DateTimeImmutable('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        $admins = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :admin OR u.roles LIKE :super_admin')
            ->setParameter('admin', '%"ROLE_ADMIN"%')
            ->setParameter('super_admin', '%"ROLE_SUPER_ADMIN"%')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'inactive' => $totalUsers - $activeUsers,
            'recent' => $recentUsers,
            'admins' => $admins,
        ];
    }

    /**
     * Find recent users (last 30 days)
     */
    public function findRecentUsers(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.createdAt >= :date')
            ->setParameter('date', new \DateTimeImmutable('-30 days'))
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total users
     */
    public function countTotalUsers(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count new users this month
     */
    public function countNewUsersThisMonth(): int
    {
        $firstDayOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :date')
            ->setParameter('date', $firstDayOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active users
     */
    public function countActiveUsers(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users registered in the last N days
     */
    public function countUsersRegisteredInLastDays(int $days): int
    {
        $date = new \DateTimeImmutable("-{$days} days");
        
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get users registration trend (last 12 months)
     */
    public function getUsersRegistrationTrend(): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        
        return $qb->select('DATE_FORMAT(u.createdAt, \'%Y-%m\') as month, COUNT(u.id) as count')
            ->from(User::class, 'u')
            ->andWhere('u.createdAt >= :date')
            ->setParameter('date', new \DateTimeImmutable('-12 months'))
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Get users registration by day (last 30 days)
     */
    public function getUsersRegistrationByDay(): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        
        return $qb->select('DATE(u.createdAt) as date, COUNT(u.id) as count')
            ->from(User::class, 'u')
            ->andWhere('u.createdAt >= :date')
            ->setParameter('date', new \DateTimeImmutable('-30 days'))
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Find users with recent activity (last login in last N days)
     */
    public function findUsersWithRecentActivity(int $days = 30, int $limit = 50): array
    {
        $date = new \DateTimeImmutable("-{$days} days");
        
        return $this->createQueryBuilder('u')
            ->andWhere('u.lastLoginAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('u.lastLoginAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user activity statistics
     */
    public function getUserActivityStatistics(): array
    {
        $qb = $this->createQueryBuilder('u');
        
        $totalUsers = $qb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        
        $activeLastWeek = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.lastLoginAt >= :date')
            ->setParameter('date', new \DateTimeImmutable('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();
            
        $activeLastMonth = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.lastLoginAt >= :date')
            ->setParameter('date', new \DateTimeImmutable('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_users' => $totalUsers,
            'active_last_week' => $activeLastWeek,
            'active_last_month' => $activeLastMonth,
            'inactive_users' => $totalUsers - $activeLastMonth,
        ];
    }
}
