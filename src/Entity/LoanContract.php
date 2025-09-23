<?php

namespace App\Entity;

use App\Repository\LoanContractRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanContractRepository::class)]
#[ORM\Table(name: 'loan_contracts')]
#[ORM\HasLifecycleCallbacks]
class LoanContract
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'contract')]
    #[ORM\JoinColumn(nullable: false)]
    private LoanApplication $loanApplication;

    #[ORM\Column(length: 50, unique: true)]
    private string $contractNumber;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $signedAt = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $contractPdf = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $digitalSignature = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $endDate;

    #[ORM\Column(type: 'json')]
    private array $paymentSchedule = [];

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $originalAmount;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalAmount;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $monthlyPayment;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $interestRate;

    #[ORM\Column(type: 'integer')]
    private int $duration; // en mois

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $remainingAmount;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // Relations
    #[ORM\OneToMany(mappedBy: 'loanContract', targetEntity: Payment::class, cascade: ['persist', 'remove'])]
    private Collection $payments;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->generateContractNumber();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function generateContractNumber(): void
    {
        $this->contractNumber = 'CT' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoanApplication(): LoanApplication
    {
        return $this->loanApplication;
    }

    public function setLoanApplication(LoanApplication $loanApplication): static
    {
        $this->loanApplication = $loanApplication;
        
        // Initialize contract details from loan application
        $this->originalAmount = $loanApplication->getRequestedAmount();
        $this->monthlyPayment = $loanApplication->getMonthlyPayment() ?? '0';
        $this->totalAmount = $loanApplication->getTotalAmount() ?? '0';
        $this->interestRate = $loanApplication->getInterestRate() ?? '0';
        $this->duration = $loanApplication->getDuration();
        $this->remainingAmount = $this->originalAmount;
        
        return $this;
    }

    public function getContractNumber(): string
    {
        return $this->contractNumber;
    }

    public function setContractNumber(string $contractNumber): static
    {
        $this->contractNumber = $contractNumber;
        return $this;
    }

    public function getSignedAt(): ?\DateTimeImmutable
    {
        return $this->signedAt;
    }

    public function setSignedAt(?\DateTimeImmutable $signedAt): static
    {
        $this->signedAt = $signedAt;
        return $this;
    }

    public function getContractPdf(): ?string
    {
        return $this->contractPdf;
    }

    public function setContractPdf(?string $contractPdf): static
    {
        $this->contractPdf = $contractPdf;
        return $this;
    }

    public function getDigitalSignature(): ?string
    {
        return $this->digitalSignature;
    }

    public function setDigitalSignature(?string $digitalSignature): static
    {
        $this->digitalSignature = $digitalSignature;
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

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): \DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getPaymentSchedule(): array
    {
        return $this->paymentSchedule;
    }

    public function setPaymentSchedule(array $paymentSchedule): static
    {
        $this->paymentSchedule = $paymentSchedule;
        return $this;
    }

    public function getOriginalAmount(): string
    {
        return $this->originalAmount;
    }

    public function setOriginalAmount(string $originalAmount): static
    {
        $this->originalAmount = $originalAmount;
        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getMonthlyPayment(): string
    {
        return $this->monthlyPayment;
    }

    public function setMonthlyPayment(string $monthlyPayment): static
    {
        $this->monthlyPayment = $monthlyPayment;
        return $this;
    }

    public function getInterestRate(): string
    {
        return $this->interestRate;
    }

    public function setInterestRate(string $interestRate): static
    {
        $this->interestRate = $interestRate;
        return $this;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getRemainingAmount(): string
    {
        return $this->remainingAmount;
    }

    public function setRemainingAmount(string $remainingAmount): static
    {
        $this->remainingAmount = $remainingAmount;
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
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setLoanContract($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getLoanContract() === $this) {
                $payment->setLoanContract(null);
            }
        }

        return $this;
    }

    // Méthodes utilitaires
    public function isSigned(): bool
    {
        return $this->signedAt !== null;
    }

    public function isExpired(): bool
    {
        return $this->endDate < new \DateTime();
    }

    public function getDaysRemaining(): int
    {
        $diff = (new \DateTime())->diff($this->endDate);
        return $diff->invert ? 0 : $diff->days;
    }

    public function getCompletionPercentage(): float
    {
        $paidAmount = $this->getPaidAmount();
        $totalAmount = (float) $this->totalAmount;
        
        if ($totalAmount == 0) {
            return 0;
        }
        
        return min(100, ($paidAmount / $totalAmount) * 100);
    }

    public function getPaidAmount(): float
    {
        $paidAmount = 0;
        foreach ($this->payments as $payment) {
            if ($payment->isPaid()) {
                $paidAmount += (float) $payment->getAmount();
            }
        }
        return $paidAmount;
    }

    public function getNextPaymentDue(): ?Payment
    {
        foreach ($this->payments as $payment) {
            if ($payment->isPending()) {
                return $payment;
            }
        }
        return null;
    }

    public function getOverduePayments(): Collection
    {
        return $this->payments->filter(function($payment) {
            return $payment->isOverdue();
        });
    }

    public function getPaidPayments(): Collection
    {
        return $this->payments->filter(function($payment) {
            return $payment->isPaid();
        });
    }

    public function getRemainingPayments(): Collection
    {
        return $this->payments->filter(function($payment) {
            return !$payment->isPaid();
        });
    }

    public function generatePaymentSchedule(): void
    {
        $schedule = [];
        $monthlyAmount = (float) $this->monthlyPayment;
        $monthlyRate = (float) $this->interestRate / 100 / 12;
        $remainingPrincipal = (float) $this->originalAmount;
        
        $currentDate = clone $this->startDate;
        
        for ($i = 1; $i <= $this->duration; $i++) {
            $interestAmount = $remainingPrincipal * $monthlyRate;
            $principalAmount = $monthlyAmount - $interestAmount;
            $remainingPrincipal -= $principalAmount;
            
            // Adjust last payment to account for rounding
            if ($i === $this->duration) {
                $principalAmount += $remainingPrincipal;
                $monthlyAmount = $principalAmount + $interestAmount;
                $remainingPrincipal = 0;
            }
            
            $schedule[] = [
                'payment_number' => $i,
                'due_date' => $currentDate->format('Y-m-d'),
                'amount' => round($monthlyAmount, 2),
                'principal' => round($principalAmount, 2),
                'interest' => round($interestAmount, 2),
                'remaining_principal' => round($remainingPrincipal, 2)
            ];
            
            $currentDate->modify('+1 month');
        }
        
        $this->paymentSchedule = $schedule;
        
        // Set end date
        $this->endDate = $currentDate;
    }

    public function createPaymentEntities(): void
    {
        // Remove existing payments
        foreach ($this->payments as $payment) {
            $this->removePayment($payment);
        }
        
        // Create new payment entities from schedule
        foreach ($this->paymentSchedule as $scheduledPayment) {
            $payment = new Payment();
            $payment->setLoanContract($this);
            $payment->setPaymentNumber($scheduledPayment['payment_number']);
            $payment->setDueDate(new \DateTime($scheduledPayment['due_date']));
            $payment->setAmount((string) $scheduledPayment['amount']);
            $payment->setPrincipalAmount((string) $scheduledPayment['principal']);
            $payment->setInterestAmount((string) $scheduledPayment['interest']);
            
            $this->addPayment($payment);
        }
    }

    public function getFormattedOriginalAmount(): string
    {
        return number_format((float) $this->originalAmount, 2, ',', ' ') . ' €';
    }

    public function getFormattedTotalAmount(): string
    {
        return number_format((float) $this->totalAmount, 2, ',', ' ') . ' €';
    }

    public function getFormattedMonthlyPayment(): string
    {
        return number_format((float) $this->monthlyPayment, 2, ',', ' ') . ' €';
    }

    public function getFormattedRemainingAmount(): string
    {
        return number_format((float) $this->remainingAmount, 2, ',', ' ') . ' €';
    }

    public function updateRemainingAmount(): void
    {
        $paidAmount = $this->getPaidAmount();
        $this->remainingAmount = (string) max(0, (float) $this->totalAmount - $paidAmount);
    }

    public function __toString(): string
    {
        return $this->contractNumber;
    }
}