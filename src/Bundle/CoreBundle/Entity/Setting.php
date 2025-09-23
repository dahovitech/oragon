<?php

namespace App\Bundle\CoreBundle\Entity;

use App\Bundle\CoreBundle\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ORM\Table(name: 'core_settings')]
#[ORM\HasLifecycleCallbacks]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z_][a-z0-9_]*$/', message: 'La clé ne peut contenir que des lettres minuscules, chiffres et underscores')]
    private ?string $settingKey = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $settingValue = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['string', 'text', 'integer', 'float', 'boolean', 'json', 'array', 'email', 'url'])]
    private ?string $type = 'string';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $section = null;

    #[ORM\Column]
    private bool $isPublic = false;

    #[ORM\Column]
    private bool $isRequired = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $defaultValue = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettingKey(): ?string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): static
    {
        $this->settingKey = $settingKey;
        return $this;
    }

    public function getSettingValue(): ?string
    {
        return $this->settingValue;
    }

    public function setSettingValue(?string $settingValue): static
    {
        $this->settingValue = $settingValue;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function setSection(?string $section): static
    {
        $this->section = $section;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;
        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue): static
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
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

    /**
     * Get the parsed value based on the type
     */
    public function getParsedValue(): mixed
    {
        if ($this->settingValue === null) {
            return $this->getDefaultValue();
        }

        return match($this->type) {
            'boolean' => filter_var($this->settingValue, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->settingValue,
            'float' => (float) $this->settingValue,
            'json', 'array' => json_decode($this->settingValue, true),
            'email' => filter_var($this->settingValue, FILTER_VALIDATE_EMAIL) ? $this->settingValue : null,
            'url' => filter_var($this->settingValue, FILTER_VALIDATE_URL) ? $this->settingValue : null,
            default => $this->settingValue
        };
    }

    /**
     * Set a parsed value (converts it to string for storage)
     */
    public function setParsedValue(mixed $value): static
    {
        $this->settingValue = match($this->type) {
            'boolean' => $value ? '1' : '0',
            'integer', 'float' => (string) $value,
            'json', 'array' => json_encode($value),
            default => (string) $value
        };

        return $this;
    }

    /**
     * Get the display name (label or key)
     */
    public function getDisplayName(): string
    {
        return $this->label ?: ucfirst(str_replace('_', ' ', $this->settingKey));
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }
}