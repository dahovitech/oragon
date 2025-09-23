<?php

namespace App\Bundle\EcommerceBundle\Entity;

use App\Bundle\EcommerceBundle\Repository\CartItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\Table(name: 'ecommerce_cart_items')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'cart_product_variant_unique', columns: ['cart_id', 'product_id', 'variant_id'])]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Cart $cart = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductVariant $variant = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $unitPrice = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $lineTotal = '0.00';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $customOptions = [];

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

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculateLineTotal(): void
    {
        $unitPrice = (float)$this->unitPrice;
        $this->lineTotal = (string)($unitPrice * $this->quantity);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        
        // Auto-set unit price from product
        if ($product && !$this->variant) {
            $this->unitPrice = $product->getPrice();
        }
        
        return $this;
    }

    public function getVariant(): ?ProductVariant
    {
        return $this->variant;
    }

    public function setVariant(?ProductVariant $variant): static
    {
        $this->variant = $variant;
        
        // Auto-set unit price from variant
        if ($variant) {
            $this->unitPrice = $variant->getEffectivePrice();
        }
        
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = max(1, $quantity);
        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getLineTotal(): string
    {
        return $this->lineTotal;
    }

    public function setLineTotal(string $lineTotal): static
    {
        $this->lineTotal = $lineTotal;
        return $this;
    }

    public function getCustomOptions(): ?array
    {
        return $this->customOptions;
    }

    public function setCustomOptions(?array $customOptions): static
    {
        $this->customOptions = $customOptions;
        return $this;
    }

    public function addCustomOption(string $key, mixed $value): static
    {
        if (!$this->customOptions) {
            $this->customOptions = [];
        }
        
        $this->customOptions[$key] = $value;
        return $this;
    }

    public function removeCustomOption(string $key): static
    {
        if ($this->customOptions && isset($this->customOptions[$key])) {
            unset($this->customOptions[$key]);
        }
        
        return $this;
    }

    public function getCustomOption(string $key): mixed
    {
        return $this->customOptions[$key] ?? null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDisplayName(): string
    {
        $name = $this->product?->getName() ?? 'Product';
        
        if ($this->variant) {
            $name .= ' (' . $this->variant->getName() . ')';
        }
        
        return $name;
    }

    public function increaseQuantity(int $amount = 1): static
    {
        $this->quantity += $amount;
        return $this;
    }

    public function decreaseQuantity(int $amount = 1): static
    {
        $this->quantity = max(1, $this->quantity - $amount);
        return $this;
    }

    public function getEffectiveStock(): int
    {
        if ($this->variant) {
            return $this->variant->getStock();
        }
        
        return $this->product?->getStock() ?? 0;
    }

    public function hasEnoughStock(): bool
    {
        return $this->getEffectiveStock() >= $this->quantity;
    }
}