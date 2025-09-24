<?php

namespace App\Entity;

use App\Repository\LoanApplicationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanApplicationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class LoanApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: LoanType::class, inversedBy: 'loanApplications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?LoanType $loanType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $requestedAmount = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $duration = null; // in months

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $purpose = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'DRAFT'; // 'DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'DISBURSED'

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $monthlyPayment = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $interestRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $totalAmount = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $rejectedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $guarantees = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $personalInfo = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $financialInfo = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'loanApplication', targetEntity: LoanDocument::class, cascade: ['persist', 'remove'])]
    private Collection $documents;

    #[ORM\OneToOne(mappedBy: 'loanApplication', targetEntity: LoanContract::class, cascade: ['persist', 'remove'])]
    private ?LoanContract $loanContract = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->documents = new ArrayCollection();
        $this->status = 'DRAFT';
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLoanType(): ?LoanType
    {
        return $this->loanType;
    }

    public function setLoanType(?LoanType $loanType): static
    {
        $this->loanType = $loanType;
        return $this;
    }

    public function getRequestedAmount(): ?string
    {
        return $this->requestedAmount;
    }

    public function setRequestedAmount(string $requestedAmount): static
    {
        $this->requestedAmount = $requestedAmount;
        return $this;
    }

    public function getDuration(): ?int
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
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

    public function getLoanContract(): ?LoanContract
    {
        return $this->loanContract;
    }

    public function setLoanContract(?LoanContract $loanContract): static
    {
        if ($loanContract === null && $this->loanContract !== null) {
            $this->loanContract->setLoanApplication(null);
        }

        if ($loanContract !== null && $loanContract->getLoanApplication() !== $this) {
            $loanContract->setLoanApplication($this);
        }

        $this->loanContract = $loanContract;

        return $this;
    }
}