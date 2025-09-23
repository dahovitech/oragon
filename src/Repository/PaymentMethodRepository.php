<?php

namespace App\Repository;

use App\Entity\PaymentMethod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentMethod>
 */
class PaymentMethodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentMethod::class);
    }

    /**
     * Find active payment methods
     */
    public function findActivePaymentMethods(): array
    {
        return $this->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
    }

    /**
     * Find payment method by provider
     */
    public function findByProvider(string $provider): ?PaymentMethod
    {
        return $this->findOneBy(['provider' => $provider]);
    }

    /**
     * Find payment methods with translations
     */
    public function findPaymentMethodsWithTranslations(): array
    {
        return $this->createQueryBuilder('pm')
            ->leftJoin('pm.translations', 'pmt')
            ->leftJoin('pmt.language', 'l')
            ->addSelect('pmt', 'l')
            ->where('pm.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('pm.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}