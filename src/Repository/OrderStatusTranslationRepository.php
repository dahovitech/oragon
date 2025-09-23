<?php

namespace App\Repository;

use App\Entity\OrderStatus;
use App\Entity\OrderStatusTranslation;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderStatusTranslation>
 */
class OrderStatusTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderStatusTranslation::class);
    }

    /**
     * Find translation by order status and language
     */
    public function findByOrderStatusAndLanguage(OrderStatus $orderStatus, Language $language): ?OrderStatusTranslation
    {
        return $this->findOneBy([
            'orderStatus' => $orderStatus,
            'language' => $language
        ]);
    }

    /**
     * Find translations by language code
     */
    public function findByLanguageCode(string $languageCode): array
    {
        return $this->createQueryBuilder('ost')
            ->leftJoin('ost.language', 'l')
            ->where('l.code = :code')
            ->setParameter('code', $languageCode)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get translation statistics for a language
     */
    public function getTranslationStatistics(string $languageCode): array
    {
        $qb = $this->createQueryBuilder('ost')
            ->select([
                'COUNT(ost.id) as total',
                'SUM(CASE WHEN ost.name IS NOT NULL AND ost.name != \'\' THEN 1 ELSE 0 END) as complete',
                'SUM(CASE WHEN ost.name IS NOT NULL AND ost.name != \'\' AND (ost.description IS NULL OR ost.description = \'\') THEN 1 ELSE 0 END) as incomplete'
            ])
            ->leftJoin('ost.language', 'l')
            ->where('l.code = :code')
            ->setParameter('code', $languageCode);

        $result = $qb->getQuery()->getOneOrNullResult();
        
        $total = (int) ($result['total'] ?? 0);
        $complete = (int) ($result['complete'] ?? 0);
        $incomplete = (int) ($result['incomplete'] ?? 0);

        return [
            'total' => $total,
            'complete' => $complete,
            'incomplete' => $incomplete,
            'percentage' => $total > 0 ? round(($complete / $total) * 100, 1) : 0
        ];
    }

    /**
     * Duplicate translation to another language
     */
    public function duplicateTranslation(OrderStatusTranslation $sourceTranslation, Language $targetLanguage): OrderStatusTranslation
    {
        $newTranslation = new OrderStatusTranslation();
        $newTranslation->setOrderStatus($sourceTranslation->getOrderStatus());
        $newTranslation->setLanguage($targetLanguage);
        $newTranslation->setName($sourceTranslation->getName());
        $newTranslation->setDescription($sourceTranslation->getDescription());

        return $newTranslation;
    }

    //    /**
    //     * @return OrderStatusTranslation[] Returns an array of OrderStatusTranslation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?OrderStatusTranslation
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}