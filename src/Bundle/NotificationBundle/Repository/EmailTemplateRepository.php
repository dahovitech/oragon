<?php

namespace App\Bundle\NotificationBundle\Repository;

use App\Bundle\NotificationBundle\Entity\EmailTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EmailTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplate::class);
    }

    public function save(EmailTemplate $template, bool $flush = false): void
    {
        $this->getEntityManager()->persist($template);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EmailTemplate $template, bool $flush = false): void
    {
        $this->getEntityManager()->remove($template);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find active template by name and locale
     */
    public function findActiveTemplate(string $name, string $locale = 'fr'): ?EmailTemplate
    {
        return $this->findOneBy([
            'name' => $name,
            'locale' => $locale,
            'active' => true
        ]);
    }

    /**
     * Find template by name and locale (including inactive)
     */
    public function findTemplate(string $name, string $locale = 'fr'): ?EmailTemplate
    {
        return $this->findOneBy([
            'name' => $name,
            'locale' => $locale
        ]);
    }

    /**
     * Get all templates by type
     */
    public function findByType(string $type, bool $activeOnly = true): array
    {
        $criteria = ['type' => $type];
        if ($activeOnly) {
            $criteria['active'] = true;
        }

        return $this->findBy($criteria, ['name' => 'ASC']);
    }

    /**
     * Get all available template names
     */
    public function getTemplateNames(): array
    {
        return $this->createQueryBuilder('t')
            ->select('DISTINCT t.name')
            ->where('t.active = true')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Get all available template types
     */
    public function getTemplateTypes(): array
    {
        return $this->createQueryBuilder('t')
            ->select('DISTINCT t.type')
            ->where('t.active = true')
            ->orderBy('t.type', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Get templates with their locales
     */
    public function getTemplatesWithLocales(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.name, t.type, t.locale, t.active, t.version, t.description')
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('t.locale', 'ASC')
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
        ?string $description = null
    ): EmailTemplate {
        $template = $this->findTemplate($name, $locale);

        if ($template) {
            $template->setSubject($subject);
            $template->setHtmlContent($htmlContent);
            $template->setTextContent($textContent);
            $template->setVariables($variables);
            $template->setDescription($description);
            $template->incrementVersion();
        } else {
            $template = new EmailTemplate();
            $template->setName($name);
            $template->setType($type);
            $template->setLocale($locale);
            $template->setSubject($subject);
            $template->setHtmlContent($htmlContent);
            $template->setTextContent($textContent);
            $template->setVariables($variables);
            $template->setDescription($description);
        }

        $this->save($template, true);
        return $template;
    }

    /**
     * Get template statistics
     */
    public function getTemplateStatistics(): array
    {
        $stats = $this->createQueryBuilder('t')
            ->select('
                COUNT(t.id) as total_templates,
                SUM(CASE WHEN t.active = true THEN 1 ELSE 0 END) as active_templates,
                COUNT(DISTINCT t.name) as unique_templates,
                COUNT(DISTINCT t.type) as template_types,
                COUNT(DISTINCT t.locale) as supported_locales
            ')
            ->getQuery()
            ->getSingleResult();

        // Get templates by type
        $byType = $this->createQueryBuilder('t')
            ->select('t.type, COUNT(t.id) as count')
            ->where('t.active = true')
            ->groupBy('t.type')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        // Get templates by locale
        $byLocale = $this->createQueryBuilder('t')
            ->select('t.locale, COUNT(t.id) as count')
            ->where('t.active = true')
            ->groupBy('t.locale')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'total_templates' => (int)$stats['total_templates'],
            'active_templates' => (int)$stats['active_templates'],
            'unique_templates' => (int)$stats['unique_templates'],
            'template_types' => (int)$stats['template_types'],
            'supported_locales' => (int)$stats['supported_locales'],
            'by_type' => $byType,
            'by_locale' => $byLocale
        ];
    }

    /**
     * Search templates
     */
    public function searchTemplates(string $query, ?string $type = null, ?string $locale = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.name LIKE :query OR t.subject LIKE :query OR t.description LIKE :query')
            ->setParameter('query', '%' . $query . '%');

        if ($type) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $type);
        }

        if ($locale) {
            $qb->andWhere('t.locale = :locale')
               ->setParameter('locale', $locale);
        }

        return $qb->orderBy('t.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Clone template to different locale
     */
    public function cloneToLocale(string $templateName, string $fromLocale, string $toLocale): ?EmailTemplate
    {
        $sourceTemplate = $this->findTemplate($templateName, $fromLocale);
        if (!$sourceTemplate) {
            return null;
        }

        $existingTemplate = $this->findTemplate($templateName, $toLocale);
        if ($existingTemplate) {
            return $existingTemplate;
        }

        $newTemplate = new EmailTemplate();
        $newTemplate->setName($sourceTemplate->getName());
        $newTemplate->setType($sourceTemplate->getType());
        $newTemplate->setLocale($toLocale);
        $newTemplate->setSubject($sourceTemplate->getSubject());
        $newTemplate->setHtmlContent($sourceTemplate->getHtmlContent());
        $newTemplate->setTextContent($sourceTemplate->getTextContent());
        $newTemplate->setVariables($sourceTemplate->getVariables());
        $newTemplate->setDescription($sourceTemplate->getDescription());
        $newTemplate->setPreheader($sourceTemplate->getPreheader());

        $this->save($newTemplate, true);
        return $newTemplate;
    }

    /**
     * Get missing templates for locale
     */
    public function getMissingTemplatesForLocale(string $locale): array
    {
        $allTemplateNames = $this->createQueryBuilder('t1')
            ->select('DISTINCT t1.name')
            ->getQuery()
            ->getSingleColumnResult();

        $existingForLocale = $this->createQueryBuilder('t2')
            ->select('DISTINCT t2.name')
            ->where('t2.locale = :locale')
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getSingleColumnResult();

        return array_diff($allTemplateNames, $existingForLocale);
    }
}