<?php

namespace App\Repository;

use App\Entity\PaymentMethodTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentMethodTranslation>
 */
class PaymentMethodTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentMethodTranslation::class);
    }

    /**
     * Find by payment method and language
     */
    public function findByPaymentMethodAndLanguage(int $paymentMethodId, string $languageCode): ?PaymentMethodTranslation
    {
        return $this->createQueryBuilder('pmt')
            ->join('pmt.language', 'l')
            ->where('pmt.paymentMethod = :paymentMethodId')
            ->andWhere('l.code = :languageCode')
            ->setParameter('paymentMethodId', $paymentMethodId)
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find by language
     */
    public function findByLanguage(string $languageCode): array
    {
        return $this->createQueryBuilder('pmt')
            ->join('pmt.language', 'l')
            ->where('l.code = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getResult();
    }
}