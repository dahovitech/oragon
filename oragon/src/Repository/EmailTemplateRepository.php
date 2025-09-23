<?php

namespace App\Repository;

use App\Entity\EmailTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailTemplate>
 *
 * @method EmailTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmailTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmailTemplate[]    findAll()
 * @method EmailTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmailTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplate::class);
    }

    public function save(EmailTemplate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EmailTemplate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find active template by type and locale
     */
    public function findActiveTemplate(string $type, string $locale = 'fr'): ?EmailTemplate
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.type = :type')
            ->andWhere('t.locale = :locale')
            ->andWhere('t.active = :active')
            ->setParameter('type', $type)
            ->setParameter('locale', $locale)
            ->setParameter('active', true)
            ->orderBy('t.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find template by name and locale
     */
    public function findByNameAndLocale(string $name, string $locale = 'fr'): ?EmailTemplate
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.name = :name')
            ->andWhere('t.locale = :locale')
            ->setParameter('name', $name)
            ->setParameter('locale', $locale)
            ->orderBy('t.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all templates by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.type = :type')
            ->setParameter('type', $type)
            ->orderBy('t.locale', 'ASC')
            ->addOrderBy('t.version', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active templates
     */
    public function findActiveTemplates(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.active = :active')
            ->setParameter('active', true)
            ->orderBy('t.type', 'ASC')
            ->addOrderBy('t.locale', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find template versions
     */
    public function findVersions(string $name, string $locale = 'fr'): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.name = :name')
            ->andWhere('t.locale = :locale')
            ->setParameter('name', $name)
            ->setParameter('locale', $locale)
            ->orderBy('t.version', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Create or update template
     */
    public function createOrUpdate(
        string $name,
        string $type,
        string $subject,
        string $htmlContent,
        string $locale = 'fr',
        ?string $textContent = null,
        ?array $variables = null,
        ?string $description = null,
        ?string $preheader = null
    ): EmailTemplate {
        $existing = $this->findByNameAndLocale($name, $locale);

        if ($existing) {
            // Update existing template
            $existing->setSubject($subject);
            $existing->setHtmlContent($htmlContent);
            $existing->setTextContent($textContent);
            $existing->setVariables($variables);
            $existing->setDescription($description);
            $existing->setPreheader($preheader);
            $existing->incrementVersion();

            $this->save($existing, true);
            return $existing;
        } else {
            // Create new template
            $template = new EmailTemplate();
            $template->setName($name);
            $template->setType($type);
            $template->setLocale($locale);
            $template->setSubject($subject);
            $template->setHtmlContent($htmlContent);
            $template->setTextContent($textContent);
            $template->setVariables($variables);
            $template->setDescription($description);
            $template->setPreheader($preheader);

            $this->save($template, true);
            return $template;
        }
    }

    /**
     * Get distinct template types
     */
    public function getDistinctTypes(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('DISTINCT t.type')
            ->orderBy('t.type', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'type');
    }

    /**
     * Get distinct locales
     */
    public function getDistinctLocales(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('DISTINCT t.locale')
            ->orderBy('t.locale', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'locale');
    }

    /**
     * Search templates
     */
    public function search(string $query, ?string $type = null, ?string $locale = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.name LIKE :query OR t.subject LIKE :query OR t.description LIKE :query')
            ->setParameter('query', '%' . $query . '%');

        if ($type) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $type);
        }

        if ($locale) {
            $qb->andWhere('t.locale = :locale')
               ->setParameter('locale', $locale);
        }

        return $qb->orderBy('t.updatedAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Clone template to different locale
     */
    public function cloneToLocale(EmailTemplate $template, string $targetLocale): EmailTemplate
    {
        $clone = new EmailTemplate();
        $clone->setName($template->getName());
        $clone->setType($template->getType());
        $clone->setLocale($targetLocale);
        $clone->setSubject($template->getSubject());
        $clone->setHtmlContent($template->getHtmlContent());
        $clone->setTextContent($template->getTextContent());
        $clone->setVariables($template->getVariables());
        $clone->setMetadata($template->getMetadata());
        $clone->setDescription($template->getDescription());
        $clone->setPreheader($template->getPreheader());
        $clone->setActive(false); // Start as draft

        $this->save($clone, true);
        return $clone;
    }

    /**
     * Get template usage statistics
     */
    public function getUsageStatistics(): array
    {
        // This would require a join with notifications table
        // For now, return basic template statistics
        return $this->createQueryBuilder('t')
            ->select([
                't.type',
                'COUNT(t.id) as template_count',
                'SUM(CASE WHEN t.active = true THEN 1 ELSE 0 END) as active_count'
            ])
            ->groupBy('t.type')
            ->orderBy('template_count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find templates needing update (old versions)
     */
    public function findOutdatedTemplates(int $daysOld = 90): array
    {
        $cutoffDate = new \DateTimeImmutable('-' . $daysOld . ' days');

        return $this->createQueryBuilder('t')
            ->andWhere('t.updatedAt < :cutoff')
            ->andWhere('t.active = :active')
            ->setParameter('cutoff', $cutoffDate)
            ->setParameter('active', true)
            ->orderBy('t.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Backup template before modification
     */
    public function createBackup(EmailTemplate $template): EmailTemplate
    {
        $backup = new EmailTemplate();
        $backup->setName($template->getName() . '_backup_' . time());
        $backup->setType($template->getType());
        $backup->setLocale($template->getLocale());
        $backup->setSubject($template->getSubject());
        $backup->setHtmlContent($template->getHtmlContent());
        $backup->setTextContent($template->getTextContent());
        $backup->setVariables($template->getVariables());
        $backup->setMetadata(array_merge($template->getMetadata() ?? [], [
            'backup_of' => $template->getId(),
            'backup_created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        ]));
        $backup->setDescription('Backup of ' . $template->getName());
        $backup->setPreheader($template->getPreheader());
        $backup->setActive(false);

        $this->save($backup, true);
        return $backup;
    }
}