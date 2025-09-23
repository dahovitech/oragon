<?php

namespace App\Bundle\EcommerceBundle\Entity;

use App\Bundle\EcommerceBundle\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'ecommerce_order_items')]
#[ORM\HasLifecycleCallbacks]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $productName = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private ?string $productSku = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $variantName = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $variantSku = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private string $unitPrice = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $lineTotal = '0.00';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $productSnapshot = [];

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

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;
        return $this;
    }

    public function getProductSku(): ?string
    {
        return $this->productSku;
    }

    public function setProductSku(string $productSku): static
    {
        $this->productSku = $productSku;
        return $this;
    }

    public function getVariantName(): ?string
    {
        return $this->variantName;
    }

    public function setVariantName(?string $variantName): static
    {
        $this->variantName = $variantName;
        return $this;
    }

    public function getVariantSku(): ?string
    {
        return $this->variantSku;
    }

    public function setVariantSku(?string $variantSku): static
    {
        $this->variantSku = $variantSku;
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

    public function getProductSnapshot(): ?array
    {
        return $this->productSnapshot;
    }

    public function setProductSnapshot(?array $productSnapshot): static
    {
        $this->productSnapshot = $productSnapshot;
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
        $name = $this->productName;
        
        if ($this->variantName) {
            $name .= ' (' . $this->variantName . ')';
        }
        
        return $name;
    }

    public function getEffectiveSku(): string
    {
        return $this->variantSku ?: $this->productSku;
    }

    public static function createFromCartItem(CartItem $cartItem): self
    {
        $orderItem = new self();
        
        $product = $cartItem->getProduct();
        $variant = $cartItem->getVariant();
        
        $orderItem->setProductName($product->getName());
        $orderItem->setProductSku($product->getSku());
        $orderItem->setQuantity($cartItem->getQuantity());
        $orderItem->setUnitPrice($cartItem->getUnitPrice());
        
        if ($variant) {
            $orderItem->setVariantName($variant->getName());
            $orderItem->setVariantSku($variant->getSku());
        }
        
        // Store product snapshot for future reference
        $orderItem->setProductSnapshot([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'variant' => $variant ? [
                'id' => $variant->getId(),
                'name' => $variant->getName(),
                'sku' => $variant->getSku(),
                'price_adjustment' => $variant->getPriceAdjustment(),
            ] : null,
        ]);
        
        $orderItem->setCustomOptions($cartItem->getCustomOptions());
        
        return $orderItem;
    }
}