<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Repository\EmailTemplateRepository")]
#[ORM\Table(name: "email_templates")]
#[ORM\UniqueConstraint(columns: ["name", "locale"], name: "uniq_template_name_locale")]
#[ORM\Index(columns: ["type"], name: "idx_template_type")]
#[ORM\Index(columns: ["active"], name: "idx_template_active")]
class EmailTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\Column(type: "string", length: 100)]
    private string $type;

    #[ORM\Column(type: "string", length: 5)]
    private string $locale = 'fr';

    #[ORM\Column(type: "string", length: 255)]
    private string $subject;

    #[ORM\Column(type: "text")]
    private string $htmlContent;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $textContent = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $variables = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: "boolean")]
    private bool $active = true;

    #[ORM\Column(type: "integer")]
    private int $version = 1;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $preheader = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getHtmlContent(): string
    {
        return $this->htmlContent;
    }

    public function setHtmlContent(string $htmlContent): self
    {
        $this->htmlContent = $htmlContent;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTextContent(): ?string
    {
        return $this->textContent;
    }

    public function setTextContent(?string $textContent): self
    {
        $this->textContent = $textContent;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getVariables(): ?array
    {
        return $this->variables;
    }

    public function setVariables(?array $variables): self
    {
        $this->variables = $variables;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function incrementVersion(): self
    {
        $this->version++;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPreheader(): ?string
    {
        return $this->preheader;
    }

    public function setPreheader(?string $preheader): self
    {
        $this->preheader = $preheader;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function processContent(array $variables = []): array
    {
        $subject = $this->subject;
        $htmlContent = $this->htmlContent;
        $textContent = $this->textContent;

        foreach ($variables as $key => $value) {
            $placeholder = '{{ ' . $key . ' }}';
            $subject = str_replace($placeholder, (string)$value, $subject);
            $htmlContent = str_replace($placeholder, (string)$value, $htmlContent);
            if ($textContent) {
                $textContent = str_replace($placeholder, (string)$value, $textContent);
            }
        }

        return [
            'subject' => $subject,
            'html' => $htmlContent,
            'text' => $textContent,
            'preheader' => $this->preheader
        ];
    }

    public function getRequiredVariables(): array
    {
        return $this->variables ?? [];
    }

    public function validateVariables(array $variables): array
    {
        $required = $this->getRequiredVariables();
        $missing = [];

        foreach ($required as $variable) {
            if (is_array($variable) && isset($variable['name'])) {
                if (!isset($variables[$variable['name']])) {
                    $missing[] = $variable['name'];
                }
            } elseif (is_string($variable) && !isset($variables[$variable])) {
                $missing[] = $variable;
            }
        }

        return $missing;
    }

    public function getPlaceholders(): array
    {
        $placeholders = [];
        $content = $this->htmlContent . ' ' . $this->subject . ' ' . ($this->textContent ?? '');
        
        preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/', $content, $matches);
        
        if (!empty($matches[1])) {
            $placeholders = array_unique($matches[1]);
        }
        
        return $placeholders;
    }

    public function isDraft(): bool
    {
        return !$this->active;
    }

    public function duplicate(string $newName): self
    {
        $duplicate = new self();
        $duplicate->setName($newName);
        $duplicate->setType($this->type);
        $duplicate->setLocale($this->locale);
        $duplicate->setSubject($this->subject);
        $duplicate->setHtmlContent($this->htmlContent);
        $duplicate->setTextContent($this->textContent);
        $duplicate->setVariables($this->variables);
        $duplicate->setMetadata($this->metadata);
        $duplicate->setDescription($this->description);
        $duplicate->setPreheader($this->preheader);
        $duplicate->setActive(false); // Start as draft
        
        return $duplicate;
    }
}