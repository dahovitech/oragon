<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'categories')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist', 'remove'])]
    private Collection $children;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Media $image = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: CategoryTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Product::class)]
    private Collection $products;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->translations = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function getImage(): ?Media
    {
        return $this->image;
    }

    public function setImage(?Media $image): static
    {
        $this->image = $image;
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

    public function setUpdatedAt(): static
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * @return Collection<int, CategoryTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(CategoryTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setCategory($this);
        }

        return $this;
    }

    public function removeTranslation(CategoryTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getCategory() === $this) {
                $translation->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setCategory($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            if ($product->getCategory() === $this) {
                $product->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * Get translation for a specific language
     */
    public function getTranslation(?string $languageCode = null): ?CategoryTranslation
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
    public function getTranslationWithFallback(string $languageCode, string $fallbackLanguageCode = 'fr'): ?CategoryTranslation
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
        return $translation ? $translation->getName() : 'Untitled Category';
    }

    /**
     * Get description for a specific language with fallback
     */
    public function getDescription(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? ($translation->getDescription() ?? '') : '';
    }

    /**
     * Get slug for a specific language with fallback
     */
    public function getSlug(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? ($translation->getSlug() ?? '') : '';
    }

    /**
     * Check if category has translation for a specific language
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
                'partial' => !empty($translation->getName()) || !empty($translation->getDescription()),
                'translation' => $translation
            ];
        }
        return $status;
    }

    /**
     * Check if category is root (has no parent)
     */
    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    /**
     * Check if category has children
     */
    public function hasChildren(): bool
    {
        return !$this->children->isEmpty();
    }

    /**
     * Get depth level (0 for root categories)
     */
    public function getLevel(): int
    {
        $level = 0;
        $parent = $this->parent;
        while ($parent !== null) {
            $level++;
            $parent = $parent->getParent();
        }
        return $level;
    }

    /**
     * Get all ancestors (parent, grandparent, etc.)
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $parent = $this->parent;
        while ($parent !== null) {
            $ancestors[] = $parent;
            $parent = $parent->getParent();
        }
        return array_reverse($ancestors);
    }

    /**
     * Get active products count
     */
    public function getActiveProductsCount(): int
    {
        return $this->products->filter(fn($product) => $product->isActive())->count();
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}
