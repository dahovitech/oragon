<?php

namespace App\Repository;

use App\Entity\AccountVerification;
use App\Entity\User;
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

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingVerifications(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'PENDING')
            ->orderBy('a.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndType(User $user, string $verificationType): ?AccountVerification
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.verificationType = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $verificationType)
            ->orderBy('a.submittedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}