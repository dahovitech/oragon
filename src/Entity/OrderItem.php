<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_items')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $quantity;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $unitPrice;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $totalPrice;

    #[ORM\Column(type: 'string', length: 255)]
    private string $productName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $productSku = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $productAttributes = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $productImageUrl = null;

    public function __construct()
    {
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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        
        // Auto-fill product details when product is set
        if ($product) {
            $this->productName = $product->getName();
            $this->productSku = $product->getSku();
            $this->unitPrice = $product->getPrice();
            
            $mainImage = $product->getMainImage();
            if ($mainImage) {
                $this->productImageUrl = $mainImage->getUrl();
            }
        }
        
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

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        $this->calculateTotalPrice();
        return $this;
    }

    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): static
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

    public function getProductImageUrl(): ?string
    {
        return $this->productImageUrl;
    }

    public function setProductImageUrl(?string $productImageUrl): static
    {
        $this->productImageUrl = $productImageUrl;
        return $this;
    }

    /**
     * Calculate total price based on quantity and unit price
     */
    private function calculateTotalPrice(): void
    {
        $total = (float)$this->unitPrice * $this->quantity;
        $this->totalPrice = number_format($total, 2, '.', '');
    }

    /**
     * Get formatted product attributes
     */
    public function getFormattedAttributes(): string
    {
        if (!$this->productAttributes) {
            return '';
        }
        
        $formatted = [];
        foreach ($this->productAttributes as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }
        
        return implode(', ', $formatted);
    }

    /**
     * Add product attribute
     */
    public function addProductAttribute(string $name, string $value): static
    {
        if ($this->productAttributes === null) {
            $this->productAttributes = [];
        }
        $this->productAttributes[$name] = $value;
        return $this;
    }

    /**
     * Get subtotal (for display purposes)
     */
    public function getSubtotal(): float
    {
        return (float)$this->totalPrice;
    }

    public function __toString(): string
    {
        return sprintf('%s x%d (%s)',
            $this->productName,
            $this->quantity,
            $this->order ? $this->order->getOrderNumber() : 'No order'
        );
    }
}