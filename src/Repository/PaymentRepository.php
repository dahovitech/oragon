<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\LoanContract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findByLoanContract(LoanContract $loanContract): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.loanContract = :loanContract')
            ->setParameter('loanContract', $loanContract)
            ->orderBy('p.paymentNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOverduePayments(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.dueDate < :today')
            ->setParameter('status', 'PENDING')
            ->setParameter('today', new \DateTimeImmutable())
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUpcomingPayments(int $days = 7): array
    {
        $futureDate = new \DateTimeImmutable('+' . $days . ' days');
        
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.dueDate BETWEEN :today AND :futureDate')
            ->setParameter('status', 'PENDING')
            ->setParameter('today', new \DateTimeImmutable())
            ->setParameter('futureDate', $futureDate)
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}