<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_items')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false)]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private float $unitPrice = 0.00;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private float $totalPrice = 0.00;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $productName;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $productSku = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $productAttributes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;
        
        // Auto-fill product details when product is set
        $this->productName = $product->getTranslation('en')?->getName() ?? 'Product';
        $this->productSku = $product->getSku();
        $this->unitPrice = $product->getPrice();
        $this->calculateTotalPrice();
        
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        $this->calculateTotalPrice();
        return $this;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        $this->calculateTotalPrice();
        return $this;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): static
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getProductName(): string
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

    public function setProductSku(?string $productSku): static
    {
        $this->productSku = $productSku;
        return $this;
    }

    public function getProductAttributes(): ?array
    {
        return $this->productAttributes;
    }

    public function setProductAttributes(?array $productAttributes): static
    {
        $this->productAttributes = $productAttributes;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Calculate total price based on quantity and unit price
     */
    private function calculateTotalPrice(): void
    {
        $this->totalPrice = $this->quantity * $this->unitPrice;
    }

    /**
     * Get product name with attributes (for display)
     */
    public function getDisplayName(): string
    {
        $name = $this->productName;
        
        if ($this->productAttributes && !empty($this->productAttributes)) {
            $attributes = [];
            foreach ($this->productAttributes as $key => $value) {
                $attributes[] = "$key: $value";
            }
            $name .= ' (' . implode(', ', $attributes) . ')';
        }
        
        return $name;
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }
}