<?php

namespace App\Entity;

use App\Repository\PaymentMethodRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentMethodRepository::class)]
#[ORM\Table(name: 'payment_methods')]
class PaymentMethod
{
    public const PROVIDER_STRIPE = 'stripe';
    public const PROVIDER_PAYPAL = 'paypal';
    public const PROVIDER_BANK_TRANSFER = 'bank_transfer';
    public const PROVIDER_CASH_ON_DELIVERY = 'cod';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $code;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::PROVIDER_STRIPE,
        self::PROVIDER_PAYPAL,
        self::PROVIDER_BANK_TRANSFER,
        self::PROVIDER_CASH_ON_DELIVERY
    ])]
    private string $provider;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Assert\LessThan(100)]
    private ?float $feePercentage = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?float $feeFixed = null;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\OneToMany(mappedBy: 'paymentMethod', targetEntity: PaymentMethodTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $configuration = [];

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $iconPath = null;

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

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;
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

    public function getFeePercentage(): ?float
    {
        return $this->feePercentage;
    }

    public function setFeePercentage(?float $feePercentage): static
    {
        $this->feePercentage = $feePercentage;
        return $this;
    }

    public function getFeeFixed(): ?float
    {
        return $this->feeFixed;
    }

    public function setFeeFixed(?float $feeFixed): static
    {
        $this->feeFixed = $feeFixed;
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

    public function getIconPath(): ?string
    {
        return $this->iconPath;
    }

    public function setIconPath(?string $iconPath): static
    {
        $this->iconPath = $iconPath;
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
     * @return Collection<int, PaymentMethodTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(PaymentMethodTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setPaymentMethod($this);
        }

        return $this;
    }

    public function removeTranslation(PaymentMethodTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getPaymentMethod() === $this) {
                $translation->setPaymentMethod(null);
            }
        }

        return $this;
    }

    /**
     * Get translation for a specific language
     */
    public function getTranslation(?string $languageCode = null): ?PaymentMethodTranslation
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
     * Calculate payment fee for amount
     */
    public function calculateFee(float $amount): float
    {
        $fee = 0.0;

        if ($this->feePercentage) {
            $fee += $amount * ($this->feePercentage / 100);
        }

        if ($this->feeFixed) {
            $fee += $this->feeFixed;
        }

        return round($fee, 2);
    }

    /**
     * Check if payment method supports refunds
     */
    public function supportsRefunds(): bool
    {
        return in_array($this->provider, [self::PROVIDER_STRIPE, self::PROVIDER_PAYPAL]);
    }

    /**
     * Check if payment method is online
     */
    public function isOnlinePayment(): bool
    {
        return in_array($this->provider, [self::PROVIDER_STRIPE, self::PROVIDER_PAYPAL]);
    }

    /**
     * Get provider choices for forms
     */
    public static function getProviderChoices(): array
    {
        return [
            'Stripe' => self::PROVIDER_STRIPE,
            'PayPal' => self::PROVIDER_PAYPAL,
            'Bank Transfer' => self::PROVIDER_BANK_TRANSFER,
            'Cash on Delivery' => self::PROVIDER_CASH_ON_DELIVERY,
        ];
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}