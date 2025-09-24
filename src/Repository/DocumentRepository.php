<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->setParameter('user', $user)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndType(User $user, string $type): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->andWhere('d.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndStatus(User $user, string $status): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->andWhere('d.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingDocuments(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->setParameter('status', Document::STATUS_PENDING)
            ->orderBy('d.uploadedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findExpiredDocuments(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.expiresAt < :now')
            ->andWhere('d.status != :expired')
            ->setParameter('now', new \DateTime())
            ->setParameter('expired', Document::STATUS_EXPIRED)
            ->getQuery()
            ->getResult();
    }

    public function getDocumentStats(): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.status, COUNT(d) as count')
            ->groupBy('d.status');

        $results = $qb->getQuery()->getResult();
        
        $stats = [
            Document::STATUS_PENDING => 0,
            Document::STATUS_APPROVED => 0,
            Document::STATUS_REJECTED => 0,
            Document::STATUS_EXPIRED => 0,
        ];

        foreach ($results as $result) {
            $stats[$result['status']] = (int) $result['count'];
        }

        $stats['total'] = array_sum($stats);

        return $stats;
    }

    public function getDocumentStatsByType(): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.type, d.status, COUNT(d) as count')
            ->groupBy('d.type, d.status');

        return $qb->getQuery()->getResult();
    }

    public function getUserKycProgress(User $user): array
    {
        $requiredTypes = [
            Document::TYPE_IDENTITY_CARD,
            Document::TYPE_PROOF_OF_ADDRESS,
            Document::TYPE_INCOME_PROOF,
        ];

        $qb = $this->createQueryBuilder('d')
            ->select('d.type, d.status, COUNT(d) as count')
            ->andWhere('d.user = :user')
            ->andWhere('d.type IN (:types)')
            ->setParameter('user', $user)
            ->setParameter('types', $requiredTypes)
            ->groupBy('d.type, d.status');

        $results = $qb->getQuery()->getResult();

        $progress = [];
        foreach ($requiredTypes as $type) {
            $progress[$type] = [
                'approved' => 0,
                'pending' => 0,
                'rejected' => 0,
                'has_approved' => false,
            ];
        }

        foreach ($results as $result) {
            $type = $result['type'];
            $status = $result['status'];
            $count = (int) $result['count'];

            if (isset($progress[$type])) {
                $progress[$type][$status] = $count;
                if ($status === Document::STATUS_APPROVED && $count > 0) {
                    $progress[$type]['has_approved'] = true;
                }
            }
        }

        $completedTypes = 0;
        foreach ($progress as $typeProgress) {
            if ($typeProgress['has_approved']) {
                $completedTypes++;
            }
        }

        return [
            'progress' => $progress,
            'completion_rate' => count($requiredTypes) > 0 ? ($completedTypes / count($requiredTypes)) * 100 : 0,
            'completed_types' => $completedTypes,
            'total_required' => count($requiredTypes),
            'is_complete' => $completedTypes === count($requiredTypes),
        ];
    }
}