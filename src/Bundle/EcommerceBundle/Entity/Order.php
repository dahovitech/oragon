<?php

namespace App\Bundle\EcommerceBundle\Entity;

use App\Bundle\EcommerceBundle\Repository\OrderRepository;
use App\Bundle\UserBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'ecommerce_orders')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';
    public const PAYMENT_STATUS_PARTIAL_REFUND = 'partial_refund';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $orderNumber = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 20)]
    private string $paymentStatus = self::PAYMENT_STATUS_PENDING;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $subtotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $taxAmount = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $shippingCost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $discountAmount = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $total = '0.00';

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $couponCode = null;

    // Billing Address
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $billingFirstName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $billingLastName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $billingEmail = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $billingPhone = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $billingAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billingAddress2 = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $billingCity = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $billingState = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    private ?string $billingPostalCode = null;

    #[ORM\Column(length: 2)]
    #[Assert\NotBlank]
    private ?string $billingCountry = null;

    // Shipping Address
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shippingFirstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shippingLastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $shippingPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shippingAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shippingAddress2 = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $shippingCity = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $shippingState = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $shippingPostalCode = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $shippingCountry = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $shippingMethod = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentTransactionId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->generateOrderNumber();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function generateOrderNumber(): void
    {
        $this->orderNumber = 'ORD-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): static
    {
        $this->orderNumber = $orderNumber;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        
        // Auto-set timestamps based on status
        if ($status === self::STATUS_SHIPPED && !$this->shippedAt) {
            $this->shippedAt = new \DateTimeImmutable();
        } elseif ($status === self::STATUS_DELIVERED && !$this->deliveredAt) {
            $this->deliveredAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }

        return $this;
    }

    public function removeItem(OrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOrder() === $this) {
                $item->setOrder(null);
            }
        }

        return $this;
    }

    // Getters and setters for financial fields
    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): static
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    public function getTaxAmount(): string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;
        return $this;
    }

    public function getShippingCost(): string
    {
        return $this->shippingCost;
    }

    public function setShippingCost(string $shippingCost): static
    {
        $this->shippingCost = $shippingCost;
        return $this;
    }

    public function getDiscountAmount(): string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(string $discountAmount): static
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getCouponCode(): ?string
    {
        return $this->couponCode;
    }

    public function setCouponCode(?string $couponCode): static
    {
        $this->couponCode = $couponCode;
        return $this;
    }

    // Billing address getters/setters
    public function getBillingFirstName(): ?string
    {
        return $this->billingFirstName;
    }

    public function setBillingFirstName(string $billingFirstName): static
    {
        $this->billingFirstName = $billingFirstName;
        return $this;
    }

    public function getBillingLastName(): ?string
    {
        return $this->billingLastName;
    }

    public function setBillingLastName(string $billingLastName): static
    {
        $this->billingLastName = $billingLastName;
        return $this;
    }

    public function getBillingEmail(): ?string
    {
        return $this->billingEmail;
    }

    public function setBillingEmail(string $billingEmail): static
    {
        $this->billingEmail = $billingEmail;
        return $this;
    }

    public function getBillingPhone(): ?string
    {
        return $this->billingPhone;
    }

    public function setBillingPhone(?string $billingPhone): static
    {
        $this->billingPhone = $billingPhone;
        return $this;
    }

    public function getBillingAddress(): ?string
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(string $billingAddress): static
    {
        $this->billingAddress = $billingAddress;
        return $this;
    }

    public function getBillingAddress2(): ?string
    {
        return $this->billingAddress2;
    }

    public function setBillingAddress2(?string $billingAddress2): static
    {
        $this->billingAddress2 = $billingAddress2;
        return $this;
    }

    public function getBillingCity(): ?string
    {
        return $this->billingCity;
    }

    public function setBillingCity(string $billingCity): static
    {
        $this->billingCity = $billingCity;
        return $this;
    }

    public function getBillingState(): ?string
    {
        return $this->billingState;
    }

    public function setBillingState(?string $billingState): static
    {
        $this->billingState = $billingState;
        return $this;
    }

    public function getBillingPostalCode(): ?string
    {
        return $this->billingPostalCode;
    }

    public function setBillingPostalCode(string $billingPostalCode): static
    {
        $this->billingPostalCode = $billingPostalCode;
        return $this;
    }

    public function getBillingCountry(): ?string
    {
        return $this->billingCountry;
    }

    public function setBillingCountry(string $billingCountry): static
    {
        $this->billingCountry = $billingCountry;
        return $this;
    }

    // Shipping address methods omitted for brevity - similar pattern

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): static
    {
        $this->trackingNumber = $trackingNumber;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
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

    public function getShippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    // Helper methods
    public function getTotalQuantity(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getQuantity();
        }
        return $total;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function isCancellable(): bool
    {
        return !in_array($this->status, [self::STATUS_SHIPPED, self::STATUS_DELIVERED, self::STATUS_CANCELLED, self::STATUS_REFUNDED]);
    }

    public function isPaid(): bool
    {
        return $this->paymentStatus === self::PAYMENT_STATUS_PAID;
    }

    public static function getStatusChoices(): array
    {
        return [
            'En attente' => self::STATUS_PENDING,
            'Confirmée' => self::STATUS_CONFIRMED,
            'En traitement' => self::STATUS_PROCESSING,
            'Expédiée' => self::STATUS_SHIPPED,
            'Livrée' => self::STATUS_DELIVERED,
            'Annulée' => self::STATUS_CANCELLED,
            'Remboursée' => self::STATUS_REFUNDED,
        ];
    }

    public static function getPaymentStatusChoices(): array
    {
        return [
            'En attente' => self::PAYMENT_STATUS_PENDING,
            'Payée' => self::PAYMENT_STATUS_PAID,
            'Échouée' => self::PAYMENT_STATUS_FAILED,
            'Remboursée' => self::PAYMENT_STATUS_REFUNDED,
            'Remb. partiel' => self::PAYMENT_STATUS_PARTIAL_REFUND,
        ];
    }
}