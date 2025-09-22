<?php

namespace App\Repository;

use App\Entity\Service;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Trouver tous les services actifs ordonnés par ordre de tri
     */
    public function findActiveServices(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver un service par son slug
     */
    public function findBySlug(string $slug): ?Service
    {
        return $this->createQueryBuilder('s')
            ->where('s.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouver un service actif par son slug
     */
    public function findActiveBySlug(string $slug): ?Service
    {
        return $this->createQueryBuilder('s')
            ->where('s.slug = :slug')
            ->andWhere('s.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouver tous les services avec leurs traductions
     */
    public function findAllWithTranslations(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.translations', 't')
            ->leftJoin('t.language', 'l')
            ->addSelect('t', 'l')
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les services avec leurs traductions pour une langue spécifique
     */
    public function findWithTranslations(?string $locale = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.translations', 't')
            ->leftJoin('t.language', 'l')
            ->addSelect('t', 'l')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.sortOrder', 'ASC');

        if ($locale) {
            $qb->andWhere('l.code = :locale OR l.code IS NULL')
               ->setParameter('locale', $locale);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouver un service avec ses traductions par slug
     */
    public function findBySlugWithTranslations(string $slug): ?Service
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.translations', 't')
            ->leftJoin('t.language', 'l')
            ->leftJoin('s.image', 'i')
            ->addSelect('t', 'l', 'i')
            ->where('s.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Obtenir les statistiques de traduction pour tous les services
     */
    public function getTranslationStatistics(): array
    {
        $services = $this->findAllWithTranslations();
        $languages = $this->getEntityManager()->getRepository(Language::class)->findActiveLanguages();
        
        $stats = [
            'total_services' => count($services),
            'languages' => [],
            'global_completion' => 0
        ];

        $totalCompletions = 0;
        $totalPossibleTranslations = count($services) * count($languages);

        foreach ($languages as $language) {
            $languageStats = [
                'language' => $language,
                'translated_services' => 0,
                'partial_services' => 0,
                'missing_services' => 0,
                'completion_percentage' => 0
            ];

            $totalCompletion = 0;

            foreach ($services as $service) {
                $translation = $service->getTranslation($language->getCode());
                if ($translation) {
                    $completion = $translation->getCompletionPercentage();
                    $totalCompletion += $completion;

                    if ($completion === 100) {
                        $languageStats['translated_services']++;
                    } elseif ($completion > 0) {
                        $languageStats['partial_services']++;
                    } else {
                        $languageStats['missing_services']++;
                    }
                } else {
                    $languageStats['missing_services']++;
                }
            }

            if (count($services) > 0) {
                $languageStats['completion_percentage'] = intval($totalCompletion / count($services));
            }

            $stats['languages'][$language->getCode()] = $languageStats;
            $totalCompletions += $totalCompletion;
        }

        if ($totalPossibleTranslations > 0) {
            $stats['global_completion'] = intval($totalCompletions / $totalPossibleTranslations);
        }

        return $stats;
    }

    /**
     * Trouver les services qui ont besoin de traductions pour une langue
     */
    public function findServicesNeedingTranslation(string $locale): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.translations', 't', 'WITH', 't.language = :language')
            ->leftJoin('t.language', 'l')
            ->where('s.isActive = :active')
            ->andWhere('t.id IS NULL OR t.title = :empty OR t.description IS NULL')
            ->setParameter('active', true)
            ->setParameter('language', $this->getEntityManager()->getRepository(Language::class)->findActiveByCode($locale))
            ->setParameter('empty', '')
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir le prochain ordre de tri disponible
     */
    public function getNextSortOrder(): int
    {
        $result = $this->createQueryBuilder('s')
            ->select('MAX(s.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? $result + 1 : 1;
    }

    /**
     * Vérifier si un slug est unique
     */
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('s.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() === 0;
    }
}
