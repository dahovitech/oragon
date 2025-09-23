<?php

namespace App\Entity;

use App\Enum\LoanApplicationStatus;
use App\Repository\LoanApplicationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanApplicationRepository::class)]
#[ORM\Table(name: 'loan_applications')]
#[ORM\HasLifecycleCallbacks]
class LoanApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $applicationNumber;

    #[ORM\ManyToOne(inversedBy: 'loanApplications')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(inversedBy: 'loanApplications')]
    #[ORM\JoinColumn(nullable: false)]
    private LoanType $loanType;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private string $requestedAmount;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $duration; // en mois

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $purpose = null;

    #[ORM\Column(type: 'string', enumType: LoanApplicationStatus::class)]
    private LoanApplicationStatus $status = LoanApplicationStatus::DRAFT;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $monthlyPayment = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $interestRate = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $totalAmount = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $personalInfo = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $financialInfo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $guarantees = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adminNotes = null;

    // Timestamps
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $rejectedAt = null;

    // Relations
    #[ORM\OneToMany(mappedBy: 'loanApplication', targetEntity: LoanDocument::class, cascade: ['persist', 'remove'])]
    private Collection $documents;

    #[ORM\OneToOne(mappedBy: 'loanApplication', targetEntity: LoanContract::class, cascade: ['persist', 'remove'])]
    private ?LoanContract $contract = null;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->generateApplicationNumber();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function generateApplicationNumber(): void
    {
        $this->applicationNumber = 'LA' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApplicationNumber(): string
    {
        return $this->applicationNumber;
    }

    public function setApplicationNumber(string $applicationNumber): static
    {
        $this->applicationNumber = $applicationNumber;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getLoanType(): LoanType
    {
        return $this->loanType;
    }

    public function setLoanType(LoanType $loanType): static
    {
        $this->loanType = $loanType;
        return $this;
    }

    public function getRequestedAmount(): string
    {
        return $this->requestedAmount;
    }

    public function setRequestedAmount(string $requestedAmount): static
    {
        $this->requestedAmount = $requestedAmount;
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

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function setPurpose(?string $purpose): static
    {
        $this->purpose = $purpose;
        return $this;
    }

    public function getStatus(): LoanApplicationStatus
    {
        return $this->status;
    }

    public function setStatus(LoanApplicationStatus $status): static
    {
        $this->status = $status;
        
        // Auto-set timestamps based on status
        $now = new \DateTimeImmutable();
        match($status) {
            LoanApplicationStatus::SUBMITTED => $this->submittedAt = $this->submittedAt ?: $now,
            LoanApplicationStatus::UNDER_REVIEW => $this->reviewedAt = $this->reviewedAt ?: $now,
            LoanApplicationStatus::APPROVED => $this->approvedAt = $this->approvedAt ?: $now,
            LoanApplicationStatus::REJECTED => $this->rejectedAt = $this->rejectedAt ?: $now,
            default => null,
        };

        return $this;
    }

    public function getMonthlyPayment(): ?string
    {
        return $this->monthlyPayment;
    }

    public function setMonthlyPayment(?string $monthlyPayment): static
    {
        $this->monthlyPayment = $monthlyPayment;
        return $this;
    }

    public function getInterestRate(): ?string
    {
        return $this->interestRate;
    }

    public function setInterestRate(?string $interestRate): static
    {
        $this->interestRate = $interestRate;
        return $this;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(?string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getPersonalInfo(): ?array
    {
        return $this->personalInfo;
    }

    public function setPersonalInfo(?array $personalInfo): static
    {
        $this->personalInfo = $personalInfo;
        return $this;
    }

    public function getFinancialInfo(): ?array
    {
        return $this->financialInfo;
    }

    public function setFinancialInfo(?array $financialInfo): static
    {
        $this->financialInfo = $financialInfo;
        return $this;
    }

    public function getGuarantees(): ?string
    {
        return $this->guarantees;
    }

    public function setGuarantees(?string $guarantees): static
    {
        $this->guarantees = $guarantees;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): static
    {
        $this->adminNotes = $adminNotes;
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

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getRejectedAt(): ?\DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function setRejectedAt(?\DateTimeImmutable $rejectedAt): static
    {
        $this->rejectedAt = $rejectedAt;
        return $this;
    }

    /**
     * @return Collection<int, LoanDocument>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(LoanDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setLoanApplication($this);
        }

        return $this;
    }

    public function removeDocument(LoanDocument $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getLoanApplication() === $this) {
                $document->setLoanApplication(null);
            }
        }

        return $this;
    }

    public function getContract(): ?LoanContract
    {
        return $this->contract;
    }

    public function setContract(?LoanContract $contract): static
    {
        if ($contract === null && $this->contract !== null) {
            $this->contract->setLoanApplication(null);
        }

        if ($contract !== null && $contract->getLoanApplication() !== $this) {
            $contract->setLoanApplication($this);
        }

        $this->contract = $contract;

        return $this;
    }

    // Méthodes utilitaires
    public function calculateLoanDetails(): void
    {
        $amount = (float) $this->requestedAmount;
        $rate = (float) $this->loanType->getBaseInterestRate();
        $duration = $this->duration;

        $monthlyPayment = $this->loanType->calculateMonthlyPayment($amount, $duration);
        $totalAmount = $monthlyPayment * $duration;

        $this->setMonthlyPayment((string) round($monthlyPayment, 2));
        $this->setTotalAmount((string) round($totalAmount, 2));
        $this->setInterestRate($this->loanType->getBaseInterestRate());
    }

    public function getFormattedAmount(): string
    {
        return number_format((float) $this->requestedAmount, 0, ',', ' ') . ' €';
    }

    public function getFormattedMonthlyPayment(): string
    {
        return $this->monthlyPayment ? number_format((float) $this->monthlyPayment, 2, ',', ' ') . ' €' : 'N/A';
    }

    public function getProgressPercentage(): int
    {
        $steps = [
            LoanApplicationStatus::DRAFT => 20,
            LoanApplicationStatus::SUBMITTED => 40,
            LoanApplicationStatus::UNDER_REVIEW => 60,
            LoanApplicationStatus::APPROVED => 80,
            LoanApplicationStatus::DISBURSED => 100,
        ];

        return $steps[$this->status] ?? 0;
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function canBeSubmitted(): bool
    {
        return $this->status->canBeSubmitted() && $this->hasRequiredDocuments();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    private function hasRequiredDocuments(): bool
    {
        $requiredTypes = $this->loanType->getRequiredDocuments();
        $uploadedTypes = $this->documents->map(fn($doc) => $doc->getDocumentType()->value)->toArray();
        
        foreach ($requiredTypes as $requiredType) {
            if (!in_array($requiredType, $uploadedTypes)) {
                return false;
            }
        }
        
        return true;
    }

    public function __toString(): string
    {
        return $this->applicationNumber;
    }
}