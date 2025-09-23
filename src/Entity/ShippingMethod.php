<?php

namespace App\Entity;

use App\Repository\ShippingMethodRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShippingMethodRepository::class)]
#[ORM\Table(name: 'shipping_methods')]
class ShippingMethod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $code;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private float $price = 0.00;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $estimatedDays = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?float $freeShippingThreshold = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?float $maxWeight = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\OneToMany(mappedBy: 'shippingMethod', targetEntity: ShippingMethodTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $configuration = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getEstimatedDays(): ?int
    {
        return $this->estimatedDays;
    }

    public function setEstimatedDays(?int $estimatedDays): static
    {
        $this->estimatedDays = $estimatedDays;
        return $this;
    }

    public function getFreeShippingThreshold(): ?float
    {
        return $this->freeShippingThreshold;
    }

    public function setFreeShippingThreshold(?float $freeShippingThreshold): static
    {
        $this->freeShippingThreshold = $freeShippingThreshold;
        return $this;
    }

    public function getMaxWeight(): ?float
    {
        return $this->maxWeight;
    }

    public function setMaxWeight(?float $maxWeight): static
    {
        $this->maxWeight = $maxWeight;
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

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function setConfiguration(?array $configuration): static
    {
        $this->configuration = $configuration;
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
     * @return Collection<int, ShippingMethodTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(ShippingMethodTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setShippingMethod($this);
        }

        return $this;
    }

    public function removeTranslation(ShippingMethodTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getShippingMethod() === $this) {
                $translation->setShippingMethod(null);
            }
        }

        return $this;
    }

    /**
     * Get translation for a specific language
     */
    public function getTranslation(?string $languageCode = null): ?ShippingMethodTranslation
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
     * Get name for a specific language with fallback
     */
    public function getName(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslation($languageCode);
        
        if (!$translation) {
            $translation = $this->getTranslation($fallbackLanguageCode);
        }

        return $translation ? $translation->getName() : ucfirst($this->code);
    }

    /**
     * Get description for a specific language with fallback
     */
    public function getDescription(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslation($languageCode);
        
        if (!$translation) {
            $translation = $this->getTranslation($fallbackLanguageCode);
        }

        return $translation ? ($translation->getDescription() ?? '') : '';
    }

    /**
     * Calculate shipping cost for order
     */
    public function calculateCost(float $orderTotal, float $orderWeight = 0): float
    {
        // Free shipping if threshold is met
        if ($this->freeShippingThreshold && $orderTotal >= $this->freeShippingThreshold) {
            return 0.00;
        }

        // Check weight limit
        if ($this->maxWeight && $orderWeight > $this->maxWeight) {
            return -1; // Indicate unavailable
        }

        return $this->price;
    }

    /**
     * Check if shipping method is available for order
     */
    public function isAvailableForOrder(float $orderTotal, float $orderWeight = 0): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if ($this->maxWeight && $orderWeight > $this->maxWeight) {
            return false;
        }

        return true;
    }

    /**
     * Get estimated delivery date
     */
    public function getEstimatedDeliveryDate(): ?\DateTimeImmutable
    {
        if (!$this->estimatedDays) {
            return null;
        }

        return (new \DateTimeImmutable())->add(new \DateInterval('P' . $this->estimatedDays . 'D'));
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}