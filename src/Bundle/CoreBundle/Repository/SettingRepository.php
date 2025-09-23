<?php

namespace App\Bundle\CoreBundle\Repository;

use App\Bundle\CoreBundle\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /**
     * Find setting by key
     */
    public function findByKey(string $key): ?Setting
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.settingKey = :key')
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find settings by section
     */
    public function findBySection(string $section): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.section = :section')
            ->setParameter('section', $section)
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.settingKey', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find public settings
     */
    public function findPublicSettings(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isPublic = :public')
            ->setParameter('public', true)
            ->orderBy('s.section', 'ASC')
            ->addOrderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find settings by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.type = :type')
            ->setParameter('type', $type)
            ->orderBy('s.section', 'ASC')
            ->addOrderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all settings grouped by section
     */
    public function findGroupedBySection(): array
    {
        $settings = $this->createQueryBuilder('s')
            ->orderBy('s.section', 'ASC')
            ->addOrderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.settingKey', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($settings as $setting) {
            $section = $setting->getSection() ?: 'general';
            if (!isset($grouped[$section])) {
                $grouped[$section] = [];
            }
            $grouped[$section][] = $setting;
        }

        return $grouped;
    }

    /**
     * Find required settings that have no value
     */
    public function findRequiredWithoutValue(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isRequired = :required')
            ->andWhere('s.settingValue IS NULL OR s.settingValue = :empty')
            ->setParameter('required', true)
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get settings as key-value array
     */
    public function getAsArray(?string $section = null): array
    {
        $qb = $this->createQueryBuilder('s');

        if ($section) {
            $qb->andWhere('s.section = :section')
                ->setParameter('section', $section);
        }

        $settings = $qb->getQuery()->getResult();
        $array = [];

        foreach ($settings as $setting) {
            $array[$setting->getSettingKey()] = $setting->getParsedValue();
        }

        return $array;
    }

    /**
     * Get public settings as key-value array
     */
    public function getPublicAsArray(): array
    {
        $settings = $this->findPublicSettings();
        $array = [];

        foreach ($settings as $setting) {
            $array[$setting->getSettingKey()] = $setting->getParsedValue();
        }

        return $array;
    }

    /**
     * Search settings by key or description
     */
    public function searchSettings(string $query): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.settingKey LIKE :query OR s.label LIKE :query OR s.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('s.section', 'ASC')
            ->addOrderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all sections
     */
    public function getAllSections(): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('DISTINCT s.section')
            ->where('s.section IS NOT NULL')
            ->orderBy('s.section', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'section');
    }

    /**
     * Get settings statistics
     */
    public function getStatistics(): array
    {
        $total = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $bySection = $this->createQueryBuilder('s')
            ->select('s.section, COUNT(s.id) as count')
            ->groupBy('s.section')
            ->getQuery()
            ->getResult();

        $byType = $this->createQueryBuilder('s')
            ->select('s.type, COUNT(s.id) as count')
            ->groupBy('s.type')
            ->getQuery()
            ->getResult();

        $public = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.isPublic = :public')
            ->setParameter('public', true)
            ->getQuery()
            ->getSingleScalarResult();

        $required = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.isRequired = :required')
            ->setParameter('required', true)
            ->getQuery()
            ->getSingleScalarResult();

        $empty = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.settingValue IS NULL OR s.settingValue = :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'public' => $public,
            'required' => $required,
            'empty' => $empty,
            'bySection' => $bySection,
            'byType' => $byType,
        ];
    }

    /**
     * Check if a setting key exists (excluding a specific setting ID)
     */
    public function isKeyExists(string $key, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.settingKey = :key')
            ->setParameter('key', $key);

        if ($excludeId) {
            $qb->andWhere('s.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Get next sort order for a section
     */
    public function getNextSortOrder(?string $section = null): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('MAX(s.sortOrder)');

        if ($section) {
            $qb->andWhere('s.section = :section')
                ->setParameter('section', $section);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * Bulk update settings values
     */
    public function bulkUpdateValues(array $keyValuePairs): int
    {
        $updated = 0;

        foreach ($keyValuePairs as $key => $value) {
            $setting = $this->findByKey($key);
            if ($setting) {
                $setting->setParsedValue($value);
                $this->getEntityManager()->persist($setting);
                $updated++;
            }
        }

        if ($updated > 0) {
            $this->getEntityManager()->flush();
        }

        return $updated;
    }
}