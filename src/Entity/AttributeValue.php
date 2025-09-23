<?php

namespace App\Entity;

use App\Repository\AttributeValueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttributeValueRepository::class)]
#[ORM\Table(name: 'attribute_values')]
class AttributeValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Attribute::class, inversedBy: 'values')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Attribute $attribute = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $colorCode = null;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'attributeValue', targetEntity: AttributeValueTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\OneToMany(mappedBy: 'attributeValue', targetEntity: ProductAttribute::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $productAttributes;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->productAttributes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttribute(): ?Attribute
    {
        return $this->attribute;
    }

    public function setAttribute(?Attribute $attribute): static
    {
        $this->attribute = $attribute;
        return $this;
    }

    public function getColorCode(): ?string
    {
        return $this->colorCode;
    }

    public function setColorCode(?string $colorCode): static
    {
        $this->colorCode = $colorCode;
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
     * @return Collection<int, AttributeValueTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(AttributeValueTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setAttributeValue($this);
        }

        return $this;
    }

    public function removeTranslation(AttributeValueTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getAttributeValue() === $this) {
                $translation->setAttributeValue(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductAttribute>
     */
    public function getProductAttributes(): Collection
    {
        return $this->productAttributes;
    }

    public function addProductAttribute(ProductAttribute $productAttribute): static
    {
        if (!$this->productAttributes->contains($productAttribute)) {
            $this->productAttributes->add($productAttribute);
            $productAttribute->setAttributeValue($this);
        }

        return $this;
    }

    public function removeProductAttribute(ProductAttribute $productAttribute): static
    {
        if ($this->productAttributes->removeElement($productAttribute)) {
            if ($productAttribute->getAttributeValue() === $this) {
                $productAttribute->setAttributeValue(null);
            }
        }

        return $this;
    }

    /**
     * Get translation for a specific language
     */
    public function getTranslation(?string $languageCode = null): ?AttributeValueTranslation
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
    public function getTranslationWithFallback(string $languageCode, string $fallbackLanguageCode = 'fr'): ?AttributeValueTranslation
    {
        $translation = $this->getTranslation($languageCode);
        
        if (!$translation) {
            $translation = $this->getTranslation($fallbackLanguageCode);
        }

        return $translation;
    }

    /**
     * Get value for a specific language with fallback
     */
    public function getValue(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? $translation->getValue() : 'Untitled Value';
    }

    /**
     * Check if attribute value has translation for a specific language
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
                'complete' => !empty($translation->getValue()),
                'partial' => !empty($translation->getValue()),
                'translation' => $translation
            ];
        }
        return $status;
    }

    public function __toString(): string
    {
        return $this->getValue();
    }
}
