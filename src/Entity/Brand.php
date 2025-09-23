<?php

namespace App\Entity;

use App\Repository\BrandRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BrandRepository::class)]
#[ORM\Table(name: 'brands')]
class Brand
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Media $logo = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $website = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'brand', targetEntity: BrandTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\OneToMany(mappedBy: 'brand', targetEntity: Product::class)]
    private Collection $products;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogo(): ?Media
    {
        return $this->logo;
    }

    public function setLogo(?Media $logo): static
    {
        $this->logo = $logo;
        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;
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
     * @return Collection<int, BrandTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(BrandTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setBrand($this);
        }

        return $this;
    }

    public function removeTranslation(BrandTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getBrand() === $this) {
                $translation->setBrand(null);
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
            $product->setBrand($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            if ($product->getBrand() === $this) {
                $product->setBrand(null);
            }
        }

        return $this;
    }

    /**
     * Get translation for a specific language
     */
    public function getTranslation(?string $languageCode = null): ?BrandTranslation
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
    public function getTranslationWithFallback(string $languageCode, string $fallbackLanguageCode = 'fr'): ?BrandTranslation
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
        return $translation ? $translation->getName() : 'Untitled Brand';
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
     * Check if brand has translation for a specific language
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
