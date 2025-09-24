<?php

namespace App\Entity;

use App\Repository\LoanContractRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoanContractRepository::class)]
#[ORM\Table(name: 'loan_contract')]
class LoanContract
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: LoanApplication::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?LoanApplication $loanApplication = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $contractNumber = null;

    #[ORM\Column(length: 255)]
    private ?string $filePath = null;

    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contractContent = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $generatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $signedAt = null;

    #[ORM\Column(length: 50, options: ['default' => 'generated'])]
    private ?string $status = 'generated';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $contractAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $interestRate = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $durationMonths = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $monthlyPayment = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalAmount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $terms = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditions = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $signature = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->generatedAt = new \DateTime();
        $this->contractNumber = $this->generateContractNumber();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoanApplication(): ?LoanApplication
    {
        return $this->loanApplication;
    }

    public function setLoanApplication(LoanApplication $loanApplication): static
    {
        $this->loanApplication = $loanApplication;
        return $this;
    }

    public function getContractNumber(): ?string
    {
        return $this->contractNumber;
    }

    public function setContractNumber(string $contractNumber): static
    {
        $this->contractNumber = $contractNumber;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getContractContent(): ?string
    {
        return $this->contractContent;
    }

    public function setContractContent(?string $contractContent): static
    {
        $this->contractContent = $contractContent;
        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeInterface
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeInterface $generatedAt): static
    {
        $this->generatedAt = $generatedAt;
        return $this;
    }

    public function getSignedAt(): ?\DateTimeInterface
    {
        return $this->signedAt;
    }

    public function setSignedAt(?\DateTimeInterface $signedAt): static
    {
        $this->signedAt = $signedAt;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getContractAmount(): ?string
    {
        return $this->contractAmount;
    }

    public function setContractAmount(string $contractAmount): static
    {
        $this->contractAmount = $contractAmount;
        return $this;
    }

    public function getInterestRate(): ?string
    {
        return $this->interestRate;
    }

    public function setInterestRate(string $interestRate): static
    {
        $this->interestRate = $interestRate;
        return $this;
    }

    public function getDurationMonths(): ?int
    {
        return $this->durationMonths;
    }

    public function setDurationMonths(int $durationMonths): static
    {
        $this->durationMonths = $durationMonths;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getMonthlyPayment(): ?string
    {
        return $this->monthlyPayment;
    }

    public function setMonthlyPayment(string $monthlyPayment): static
    {
        $this->monthlyPayment = $monthlyPayment;
        return $this;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getTerms(): ?string
    {
        return $this->terms;
    }

    public function setTerms(?string $terms): static
    {
        $this->terms = $terms;
        return $this;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): static
    {
        $this->conditions = $conditions;
        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(?string $signature): static
    {
        $this->signature = $signature;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    private function generateContractNumber(): string
    {
        return 'ORAGON-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Check if contract is signed
     */
    public function isSigned(): bool
    {
        return $this->status === 'signed' && $this->signedAt !== null;
    }

    /**
     * Check if contract is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Sign the contract
     */
    public function sign(): self
    {
        $this->status = 'signed';
        $this->signedAt = new \DateTime();
        return $this;
    }

    /**
     * Activate the contract
     */
    public function activate(): self
    {
        $this->status = 'active';
        return $this;
    }

    /**
     * Get the full file path
     */
    public function getFullFilePath(): ?string
    {
        if (!$this->filePath) {
            return null;
        }
        
        return 'contracts/' . $this->filePath;
    }

    /**
     * Calculate monthly payment based on loan details
     */
    public function calculateMonthlyPayment(): float
    {
        if (!$this->contractAmount || !$this->interestRate || !$this->durationMonths) {
            return 0;
        }

        $principal = floatval($this->contractAmount);
        $rate = floatval($this->interestRate) / 100 / 12; // Monthly interest rate
        $periods = $this->durationMonths;

        if ($rate == 0) {
            return $principal / $periods;
        }

        $payment = $principal * ($rate * pow(1 + $rate, $periods)) / (pow(1 + $rate, $periods) - 1);
        
        return round($payment, 2);
    }

    /**
     * Calculate total amount to be paid
     */
    public function calculateTotalAmount(): float
    {
        $monthlyPayment = $this->calculateMonthlyPayment();
        return round($monthlyPayment * $this->durationMonths, 2);
    }

    /**
     * Get status badge class for display
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'generated' => 'bg-info',
            'sent' => 'bg-warning',
            'signed' => 'bg-success',
            'active' => 'bg-primary',
            'completed' => 'bg-success',
            'cancelled' => 'bg-danger',
            default => 'bg-secondary'
        };
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayName(): string
    {
        return match($this->status) {
            'generated' => 'Généré',
            'sent' => 'Envoyé',
            'signed' => 'Signé',
            'active' => 'Actif',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            default => 'Inconnu'
        };
    }
}