<?php

namespace App\Entity;

use App\Enum\PaymentStatus;
use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LoanContract::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?LoanContract $loanContract = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $paymentNumber = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dueDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $principalAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $interestAmount = null;

    #[ORM\Column(type: 'string', enumType: PaymentStatus::class)]
    private PaymentStatus $status;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $paidAt = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->status = PaymentStatus::PENDING;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoanContract(): ?LoanContract
    {
        return $this->loanContract;
    }

    public function setLoanContract(?LoanContract $loanContract): static
    {
        $this->loanContract = $loanContract;

        return $this;
    }

    public function getPaymentNumber(): ?int
    {
        return $this->paymentNumber;
    }

    public function setPaymentNumber(int $paymentNumber): static
    {
        $this->paymentNumber = $paymentNumber;

        return $this;
    }

    public function getDueDate(): ?\DateTime
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTime $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string|float $amount): static
    {
        $this->amount = (string)$amount;

        return $this;
    }

    public function getPrincipalAmount(): ?string
    {
        return $this->principalAmount;
    }

    public function setPrincipalAmount(string|float $principalAmount): static
    {
        $this->principalAmount = (string)$principalAmount;

        return $this;
    }

    public function getInterestAmount(): ?string
    {
        return $this->interestAmount;
    }

    public function setInterestAmount(string|float $interestAmount): static
    {
        $this->interestAmount = (string)$interestAmount;

        return $this;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPaidAt(): ?\DateTime
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTime $paidAt): static
    {
        $this->paidAt = $paidAt;

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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    // Méthodes utilitaires

    public function isOverdue(): bool
    {
        return $this->status === PaymentStatus::PENDING && 
               $this->dueDate < new \DateTime();
    }

    public function isDue(): bool
    {
        return $this->status === PaymentStatus::PENDING && 
               $this->dueDate <= new \DateTime();
    }

    public function isUpcoming(int $days = 7): bool
    {
        $futureDate = new \DateTime();
        $futureDate->modify("+{$days} days");
        
        return $this->status === PaymentStatus::PENDING && 
               $this->dueDate <= $futureDate && 
               $this->dueDate > new \DateTime();
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        $today = new \DateTime();
        return $today->diff($this->dueDate)->days;
    }

    public function getDaysUntilDue(): int
    {
        if ($this->status !== PaymentStatus::PENDING) {
            return 0;
        }

        $today = new \DateTime();
        if ($this->dueDate < $today) {
            return -$this->getDaysOverdue(); // Négatif si en retard
        }

        return $today->diff($this->dueDate)->days;
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            PaymentStatus::PENDING => 'En attente',
            PaymentStatus::PAID => 'Payé',
            PaymentStatus::LATE => 'En retard',
            PaymentStatus::MISSED => 'Manqué',
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            PaymentStatus::PENDING => 'warning',
            PaymentStatus::PAID => 'success',
            PaymentStatus::LATE => 'danger',
            PaymentStatus::MISSED => 'dark',
        };
    }

    public function getAmountFloat(): float
    {
        return (float)$this->amount;
    }

    public function getPrincipalAmountFloat(): float
    {
        return (float)$this->principalAmount;
    }

    public function getInterestAmountFloat(): float
    {
        return (float)$this->interestAmount;
    }
}