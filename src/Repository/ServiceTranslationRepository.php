<?php

namespace App\Repository;

use App\Entity\ServiceTranslation;
use App\Entity\Service;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceTranslation>
 */
class ServiceTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceTranslation::class);
    }

    /**
     * Trouver une traduction par service et langue
     */
    public function findByServiceAndLanguage(Service $service, Language $language): ?ServiceTranslation
    {
        return $this->createQueryBuilder('st')
            ->where('st.translatable = :service')
            ->andWhere('st.language = :language')
            ->setParameter('service', $service)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouver toutes les traductions d'un service
     */
    public function findByService(Service $service): array
    {
        return $this->createQueryBuilder('st')
            ->leftJoin('st.language', 'l')
            ->addSelect('l')
            ->where('st.translatable = :service')
            ->setParameter('service', $service)
            ->orderBy('l.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver toutes les traductions pour une langue
     */
    public function findByLanguage(Language $language): array
    {
        return $this->createQueryBuilder('st')
            ->leftJoin('st.translatable', 's')
            ->addSelect('s')
            ->where('st.language = :language')
            ->setParameter('language', $language)
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les traductions complètes pour une langue
     */
    public function findCompleteByLanguage(Language $language): array
    {
        return $this->createQueryBuilder('st')
            ->leftJoin('st.translatable', 's')
            ->addSelect('s')
            ->where('st.language = :language')
            ->andWhere('st.title != :empty')
            ->andWhere('st.description IS NOT NULL')
            ->andWhere('st.description != :empty')
            ->setParameter('language', $language)
            ->setParameter('empty', '')
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les traductions incomplètes pour une langue
     */
    public function findIncompleteByLanguage(Language $language): array
    {
        return $this->createQueryBuilder('st')
            ->leftJoin('st.translatable', 's')
            ->addSelect('s')
            ->where('st.language = :language')
            ->andWhere('st.title = :empty OR st.description IS NULL OR st.description = :empty')
            ->setParameter('language', $language)
            ->setParameter('empty', '')
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les traductions par statut pour une langue
     */
    public function countByStatus(Language $language): array
    {
        $total = $this->createQueryBuilder('st')
            ->select('COUNT(st.id)')
            ->where('st.language = :language')
            ->setParameter('language', $language)
            ->getQuery()
            ->getSingleScalarResult();

        $complete = $this->createQueryBuilder('st')
            ->select('COUNT(st.id)')
            ->where('st.language = :language')
            ->andWhere('st.title != :empty')
            ->andWhere('st.description IS NOT NULL')
            ->andWhere('st.description != :empty')
            ->setParameter('language', $language)
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();

        $partial = $this->createQueryBuilder('st')
            ->select('COUNT(st.id)')
            ->where('st.language = :language')
            ->andWhere('(st.title != :empty OR (st.description IS NOT NULL AND st.description != :empty))')
            ->andWhere('NOT (st.title != :empty AND st.description IS NOT NULL AND st.description != :empty)')
            ->setParameter('language', $language)
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'complete' => $complete,
            'partial' => $partial,
            'missing' => $total - $complete - $partial
        ];
    }

    /**
     * Obtenir les statistiques détaillées pour toutes les langues
     */
    public function getDetailedStatistics(): array
    {
        $languages = $this->getEntityManager()->getRepository(Language::class)->findActiveLanguages();
        $stats = [];

        foreach ($languages as $language) {
            $counts = $this->countByStatus($language);
            
            $stats[$language->getCode()] = [
                'language' => $language,
                'total' => $counts['total'],
                'complete' => $counts['complete'],
                'partial' => $counts['partial'],
                'missing' => $counts['missing'],
                'completion_percentage' => $counts['total'] > 0 ? 
                    intval(($counts['complete'] / $counts['total']) * 100) : 0
            ];
        }

        return $stats;
    }

    /**
     * Trouver les traductions récemment modifiées
     */
    public function findRecentlyUpdated(int $limit = 10): array
    {
        return $this->createQueryBuilder('st')
            ->leftJoin('st.translatable', 's')
            ->leftJoin('st.language', 'l')
            ->addSelect('s', 'l')
            ->where('st.updatedAt IS NOT NULL')
            ->orderBy('st.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprimer toutes les traductions pour une langue
     */
    public function deleteByLanguage(Language $language): int
    {
        return $this->createQueryBuilder('st')
            ->delete()
            ->where('st.language = :language')
            ->setParameter('language', $language)
            ->getQuery()
            ->execute();
    }

    /**
     * Dupliquer les traductions d'une langue source vers une langue cible
     */
    public function duplicateTranslations(Language $sourceLanguage, Language $targetLanguage): int
    {
        $sourceTranslations = $this->findByLanguage($sourceLanguage);
        $count = 0;

        foreach ($sourceTranslations as $sourceTranslation) {
            // Vérifier si une traduction existe déjà pour la langue cible
            $existingTranslation = $this->findByServiceAndLanguage(
                $sourceTranslation->getTranslatable(),
                $targetLanguage
            );

            if (!$existingTranslation) {
                $newTranslation = new ServiceTranslation();
                $newTranslation->setTranslatable($sourceTranslation->getTranslatable());
                $newTranslation->setLanguage($targetLanguage);
                $newTranslation->copyFrom($sourceTranslation);

                $this->getEntityManager()->persist($newTranslation);
                $count++;
            }
        }

        if ($count > 0) {
            $this->getEntityManager()->flush();
        }

        return $count;
    }
}
