<?php

namespace App\Repository;

use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Language>
 */
class LanguageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Language::class);
    }

    /**
     * Find active languages ordered by sort order
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('l.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find default language
     */
    public function findDefault(): ?Language
    {
        return $this->createQueryBuilder('l')
            ->where('l.isDefault = :default')
            ->andWhere('l.isActive = :active')
            ->setParameter('default', true)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active language by code
     */
    public function findActiveByCode(string $code): ?Language
    {
        return $this->createQueryBuilder('l')
            ->where('l.code = :code')
            ->andWhere('l.isActive = :active')
            ->setParameter('code', $code)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }
}