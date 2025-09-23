<?php

namespace App\Entity;

use App\Repository\AttributeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttributeRepository::class)]
#[ORM\Table(name: 'attributes')]
class Attribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['select', 'multiselect', 'text', 'boolean', 'number'])]
    private string $type = 'select';

    #[ORM\Column(type: 'boolean')]
    private bool $isRequired = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isFilterable = true;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'attribute', targetEntity: AttributeTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\OneToMany(mappedBy: 'attribute', targetEntity: AttributeValue::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $values;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->values = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function isFilterable(): bool
    {
        return $this->isFilterable;
    }

    public function setIsFilterable(bool $isFilterable): static
    {
        $this->isFilterable = $isFilterable;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    public function setUpdatedAt(): static
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * @return Collection<int, AttributeTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(AttributeTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setAttribute($this);
        }

        return $this;
    }

    public function removeTranslation(AttributeTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getAttribute() === $this) {
                $translation->setAttribute(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AttributeValue>
     */
    public function getValues(): Collection
    {
        return $this->values;
    }

    public function addValue(AttributeValue $value): static
    {
        if (!$this->values->contains($value)) {
            $this->values->add($value);
            $value->setAttribute($this);
        }

        return $this;
    }

    public function removeValue(AttributeValue $value): static
    {
        if ($this->values->removeElement($value)) {
            if ($value->getAttribute() === $this) {
                $value->setAttribute(null);
            }
        }

        return $this;
    }

    /**
     * Get translation for a specific language
     */
    public function getTranslation(?string $languageCode = null): ?AttributeTranslation
    {
        if ($languageCode === null) {
            return $this->translations->first() ?: null;
        }

        foreach ($this->translations as $translation) {
            if ($translation->getLanguage()->getCode() === $languageCode) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Get translation with fallback
     */
    public function getTranslationWithFallback(string $languageCode, string $fallbackLanguageCode = 'fr'): ?AttributeTranslation
    {
        $translation = $this->getTranslation($languageCode);
        
        if (!$translation) {
            $translation = $this->getTranslation($fallbackLanguageCode);
        }

        return $translation;
    }

    /**
     * Get name for a specific language with fallback
     */
    public function getName(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? $translation->getName() : 'Untitled Attribute';
    }

    /**
     * Check if attribute has translation for a specific language
     */
    public function hasTranslation(string $languageCode): bool
    {
        return $this->getTranslation($languageCode) !== null;
    }

    /**
     * Get completion status for all translations
     */
    public function getTranslationStatus(): array
    {
        $status = [];
        foreach ($this->translations as $translation) {
            $status[$translation->getLanguage()->getCode()] = [
                'complete' => !empty($translation->getName()),
                'partial' => !empty($translation->getName()),
                'translation' => $translation
            ];
        }
        return $status;
    }

    /**
     * Get active values
     */
    public function getActiveValues(): Collection
    {
        return $this->values->filter(fn($value) => $value->isActive());
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}
