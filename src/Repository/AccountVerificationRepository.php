<?php

namespace App\Repository;

use App\Entity\AccountVerification;
use App\Entity\User;
use App\Enum\VerificationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountVerification>
 */
class AccountVerificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountVerification::class);
    }

    /**
     * Find verifications by user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('av')
            ->where('av.user = :user')
            ->setParameter('user', $user)
            ->orderBy('av.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find verifications by status
     */
    public function findByStatus(VerificationStatus $status): array
    {
        return $this->createQueryBuilder('av')
            ->where('av.status = :status')
            ->setParameter('status', $status)
            ->orderBy('av.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending verifications
     */
    public function findPending(): array
    {
        return $this->findByStatus(VerificationStatus::PENDING);
    }

    /**
     * Find verifications older than specified days
     */
    public function findOlderThan(int $days): array
    {
        $date = new \DateTime("-{$days} days");
        
        return $this->createQueryBuilder('av')
            ->where('av.submittedAt < :date')
            ->andWhere('av.status = :status')
            ->setParameter('date', $date)
            ->setParameter('status', VerificationStatus::PENDING)
            ->orderBy('av.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count verifications by status
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('av')
            ->select('av.status, COUNT(av.id) as count')
            ->groupBy('av.status')
            ->getQuery()
            ->getArrayResult();
        
        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = $row['count'];
        }
        
        return $counts;
    }

    /**
     * Get verification statistics
     */
    public function getStatistics(): array
    {
        $statusCounts = $this->countByStatus();
        
        return [
            'total' => array_sum($statusCounts),
            'pending' => $statusCounts[VerificationStatus::PENDING->value] ?? 0,
            'verified' => $statusCounts[VerificationStatus::VERIFIED->value] ?? 0,
            'rejected' => $statusCounts[VerificationStatus::REJECTED->value] ?? 0,
            'avg_processing_time' => $this->getAverageProcessingTime(),
        ];
    }

    /**
     * Get average processing time in days
     */
    private function getAverageProcessingTime(): ?float
    {
        $result = $this->createQueryBuilder('av')
            ->select('AVG(DATEDIFF(av.verifiedAt, av.submittedAt)) as avg_days')
            ->where('av.verifiedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
        
        return $result ? round($result, 1) : null;
    }
}