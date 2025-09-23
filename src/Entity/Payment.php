<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case LATE = 'LATE';
    case MISSED = 'MISSED';
    case PARTIAL = 'PARTIAL';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::PAID => 'Payé',
            self::LATE => 'En retard',
            self::MISSED => 'Manqué',
            self::PARTIAL => 'Partiel',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::PENDING => 'badge bg-warning',
            self::PAID => 'badge bg-success',
            self::LATE => 'badge bg-danger',
            self::MISSED => 'badge bg-dark',
            self::PARTIAL => 'badge bg-info',
        };
    }
}

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private LoanContract $loanContract;

    #[ORM\Column(type: 'integer')]
    private int $paymentNumber;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $dueDate;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $principalAmount;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $interestAmount;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $paidAmount = '0.00';

    #[ORM\Column(type: 'string', enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::PENDING;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $lateFees = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
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

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoanContract(): LoanContract
    {
        return $this->loanContract;
    }

    public function setLoanContract(LoanContract $loanContract): static
    {
        $this->loanContract = $loanContract;
        return $this;
    }

    public function getPaymentNumber(): int
    {
        return $this->paymentNumber;
    }

    public function setPaymentNumber(int $paymentNumber): static
    {
        $this->paymentNumber = $paymentNumber;
        return $this;
    }

    public function getDueDate(): \DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getPrincipalAmount(): string
    {
        return $this->principalAmount;
    }

    public function setPrincipalAmount(string $principalAmount): static
    {
        $this->principalAmount = $principalAmount;
        return $this;
    }

    public function getInterestAmount(): string
    {
        return $this->interestAmount;
    }

    public function setInterestAmount(string $interestAmount): static
    {
        $this->interestAmount = $interestAmount;
        return $this;
    }

    public function getPaidAmount(): string
    {
        return $this->paidAmount;
    }

    public function setPaidAmount(string $paidAmount): static
    {
        $this->paidAmount = $paidAmount;
        $this->updatePaymentStatus();
        return $this;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;
        
        // Auto-set payment timestamp for paid status
        if ($status === PaymentStatus::PAID && !$this->paidAt) {
            $this->paidAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
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

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): static
    {
        $this->transactionId = $transactionId;
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

    public function getLateFees(): ?string
    {
        return $this->lateFees;
    }

    public function setLateFees(?string $lateFees): static
    {
        $this->lateFees = $lateFees;
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

    // Méthodes utilitaires
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }

    public function isLate(): bool
    {
        return $this->status === PaymentStatus::LATE;
    }

    public function isMissed(): bool
    {
        return $this->status === PaymentStatus::MISSED;
    }

    public function isPartial(): bool
    {
        return $this->status === PaymentStatus::PARTIAL;
    }

    public function isOverdue(): bool
    {
        return $this->dueDate < new \DateTime() && !$this->isPaid();
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        return (new \DateTime())->diff($this->dueDate)->days;
    }

    public function getDaysUntilDue(): int
    {
        $diff = $this->dueDate->diff(new \DateTime());
        return $diff->invert ? $diff->days : 0;
    }

    public function getRemainingAmount(): float
    {
        return max(0, (float) $this->amount - (float) $this->paidAmount);
    }

    public function getPaymentPercentage(): float
    {
        $totalAmount = (float) $this->amount;
        if ($totalAmount == 0) {
            return 0;
        }
        
        return ((float) $this->paidAmount / $totalAmount) * 100;
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->paidAmount >= (float) $this->amount;
    }

    public function updatePaymentStatus(): void
    {
        $paidAmount = (float) $this->paidAmount;
        $totalAmount = (float) $this->amount;
        
        if ($paidAmount >= $totalAmount) {
            $this->status = PaymentStatus::PAID;
            if (!$this->paidAt) {
                $this->paidAt = new \DateTimeImmutable();
            }
        } elseif ($paidAmount > 0) {
            $this->status = PaymentStatus::PARTIAL;
        } elseif ($this->isOverdue()) {
            $this->status = $this->getDaysOverdue() > 30 ? PaymentStatus::MISSED : PaymentStatus::LATE;
        } else {
            $this->status = PaymentStatus::PENDING;
        }
    }

    public function recordPayment(float $amount, string $method = null, string $transactionId = null): void
    {
        $currentPaid = (float) $this->paidAmount;
        $newPaidAmount = $currentPaid + $amount;
        
        $this->setPaidAmount((string) $newPaidAmount);
        $this->setPaymentMethod($method);
        $this->setTransactionId($transactionId);
        
        if ($this->isFullyPaid()) {
            $this->setPaidAt(new \DateTimeImmutable());
        }
        
        $this->updatePaymentStatus();
    }

    public function calculateLateFees(): float
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        $daysOverdue = $this->getDaysOverdue();
        $baseAmount = (float) $this->amount;
        
        // 0.1% par jour de retard, maximum 10% du montant
        $feeRate = min(0.10, $daysOverdue * 0.001);
        
        return $baseAmount * $feeRate;
    }

    public function applyLateFees(): void
    {
        if ($this->isOverdue() && !$this->lateFees) {
            $fees = $this->calculateLateFees();
            $this->setLateFees((string) $fees);
        }
    }

    public function getTotalAmountDue(): float
    {
        $amount = (float) $this->amount;
        $lateFees = (float) ($this->lateFees ?? 0);
        return $amount + $lateFees;
    }

    public function getFormattedAmount(): string
    {
        return number_format((float) $this->amount, 2, ',', ' ') . ' €';
    }

    public function getFormattedPaidAmount(): string
    {
        return number_format((float) $this->paidAmount, 2, ',', ' ') . ' €';
    }

    public function getFormattedRemainingAmount(): string
    {
        return number_format($this->getRemainingAmount(), 2, ',', ' ') . ' €';
    }

    public function getFormattedLateFees(): string
    {
        $fees = (float) ($this->lateFees ?? 0);
        return number_format($fees, 2, ',', ' ') . ' €';
    }

    public function __toString(): string
    {
        return "Paiement #{$this->paymentNumber} - {$this->getFormattedAmount()}";
    }
}