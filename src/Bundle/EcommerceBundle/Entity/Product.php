<?php

namespace App\Bundle\EcommerceBundle\Entity;

use App\Bundle\EcommerceBundle\Repository\ProductRepository;
use App\Bundle\CoreBundle\Entity\Category;
use App\Bundle\MediaBundle\Entity\Media;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'ecommerce_products')]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    private ?string $sku = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $price = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $comparePrice = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $discountPercentage = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $stock = 0;

    #[ORM\Column]
    private bool $trackStock = true;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column]
    private bool $isDigital = false;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 3, nullable: true)]
    private ?string $weight = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dimensions = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    private ?Media $featuredImage = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $images;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductVariant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $variants;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Review::class)]
    private Collection $reviews;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column]
    private int $viewCount = 0;

    #[ORM\Column]
    private int $salesCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->variants = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters et setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(string $sku): static
    {
        $this->sku = $sku;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getComparePrice(): ?string
    {
        return $this->comparePrice;
    }

    public function setComparePrice(?string $comparePrice): static
    {
        $this->comparePrice = $comparePrice;
        return $this;
    }

    public function getDiscountPercentage(): ?string
    {
        return $this->discountPercentage;
    }

    public function setDiscountPercentage(?string $discountPercentage): static
    {
        $this->discountPercentage = $discountPercentage;
        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;
        return $this;
    }

    public function isTrackStock(): bool
    {
        return $this->trackStock;
    }

    public function setTrackStock(bool $trackStock): static
    {
        $this->trackStock = $trackStock;
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

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function isDigital(): bool
    {
        return $this->isDigital;
    }

    public function setIsDigital(bool $isDigital): static
    {
        $this->isDigital = $isDigital;
        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(?string $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function getDimensions(): ?string
    {
        return $this->dimensions;
    }

    public function setDimensions(?string $dimensions): static
    {
        $this->dimensions = $dimensions;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getFeaturedImage(): ?Media
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?Media $featuredImage): static
    {
        $this->featuredImage = $featuredImage;
        return $this;
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProductImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }
        return $this;
    }

    public function removeImage(ProductImage $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getProduct() === $this) {
                $image->setProduct(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(ProductVariant $variant): static
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setProduct($this);
        }
        return $this;
    }

    public function removeVariant(ProductVariant $variant): static
    {
        if ($this->variants->removeElement($variant)) {
            if ($variant->getProduct() === $this) {
                $variant->setProduct(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setProduct($this);
        }
        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getProduct() === $this) {
                $review->setProduct(null);
            }
        }
        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $this->metaTitle = $metaTitle;
        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function incrementViewCount(): static
    {
        $this->viewCount++;
        return $this;
    }

    public function getSalesCount(): int
    {
        return $this->salesCount;
    }

    public function incrementSalesCount(int $quantity = 1): static
    {
        $this->salesCount += $quantity;
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

    // Méthodes métier
    public function isInStock(): bool
    {
        return !$this->trackStock || $this->stock > 0;
    }

    public function isOnSale(): bool
    {
        return $this->comparePrice !== null && 
               $this->comparePrice > $this->price;
    }

    public function getSalePrice(): ?string
    {
        if ($this->discountPercentage !== null) {
            $discount = (float)$this->price * ((float)$this->discountPercentage / 100);
            return number_format((float)$this->price - $discount, 2);
        }
        
        return $this->price;
    }

    public function getAverageRating(): float
    {
        $reviews = $this->reviews->filter(fn(Review $review) => $review->isApproved());
        
        if ($reviews->isEmpty()) {
            return 0;
        }
        
        $total = $reviews->reduce(fn(int $carry, Review $review) => $carry + $review->getRating(), 0);
        
        return $total / $reviews->count();
    }

    public function getApprovedReviews(): Collection
    {
        return $this->reviews->filter(fn(Review $review) => $review->isApproved());
    }

    public function __toString(): string
    {
        return $this->name ?? 'Product #' . $this->id;
    }
}