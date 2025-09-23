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
     * Find translations by language code
     */
    public function findByLanguageCode(string $languageCode): array
    {
        return $this->createQueryBuilder('pmt')
            ->leftJoin('pmt.language', 'l')
            ->leftJoin('pmt.paymentMethod', 'pm')
            ->where('l.code = :code')
            ->andWhere('pm.isActive = :active')
            ->setParameter('code', $languageCode)
            ->setParameter('active', true)
            ->orderBy('pm.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}