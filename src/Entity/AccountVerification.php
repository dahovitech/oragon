<?php

namespace App\Entity;

use App\Enum\DocumentType;
use App\Enum\VerificationStatus;
use App\Repository\AccountVerificationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

enum VerificationType: string
{
    case IDENTITY = 'IDENTITY';
    case ADDRESS = 'ADDRESS';
    case INCOME = 'INCOME';
    case BUSINESS = 'BUSINESS';

    public function getLabel(): string
    {
        return match($this) {
            self::IDENTITY => 'Vérification d\'identité',
            self::ADDRESS => 'Vérification d\'adresse',
            self::INCOME => 'Vérification de revenus',
            self::BUSINESS => 'Vérification d\'entreprise',
        };
    }

    public function getRequiredDocuments(): array
    {
        return match($this) {
            self::IDENTITY => [DocumentType::ID_CARD, DocumentType::PASSPORT],
            self::ADDRESS => [DocumentType::PROOF_ADDRESS],
            self::INCOME => [DocumentType::PROOF_INCOME, DocumentType::BANK_STATEMENT],
            self::BUSINESS => [DocumentType::KBIS, DocumentType::BALANCE_SHEET],
        };
    }
}

#[ORM\Entity(repositoryClass: AccountVerificationRepository::class)]
#[ORM\Table(name: 'account_verifications')]
#[ORM\HasLifecycleCallbacks]
class AccountVerification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'verifications')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'string', enumType: VerificationType::class)]
    private VerificationType $verificationType;

    #[ORM\Column(type: 'string', enumType: VerificationStatus::class)]
    private VerificationStatus $status = VerificationStatus::PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $submittedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $verifiedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comments = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\OneToMany(mappedBy: 'accountVerification', targetEntity: VerificationDocument::class, cascade: ['persist', 'remove'])]
    private Collection $documents;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->submittedAt = new \DateTimeImmutable();
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getVerificationType(): VerificationType
    {
        return $this->verificationType;
    }

    public function setVerificationType(VerificationType $verificationType): static
    {
        $this->verificationType = $verificationType;
        return $this;
    }

    public function getStatus(): VerificationStatus
    {
        return $this->status;
    }

    public function setStatus(VerificationStatus $status): static
    {
        $this->status = $status;
        
        // Auto-set verification timestamp for verified status
        if ($status === VerificationStatus::VERIFIED && !$this->verifiedAt) {
            $this->verifiedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;
        return $this;
    }

    public function getVerifiedBy(): ?User
    {
        return $this->verifiedBy;
    }

    public function setVerifiedBy(?User $verifiedBy): static
    {
        $this->verifiedBy = $verifiedBy;
        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): static
    {
        $this->comments = $comments;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, VerificationDocument>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(VerificationDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setAccountVerification($this);
        }

        return $this;
    }

    public function removeDocument(VerificationDocument $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getAccountVerification() === $this) {
                $document->setAccountVerification(null);
            }
        }

        return $this;
    }

    // Méthodes utilitaires
    public function isPending(): bool
    {
        return $this->status === VerificationStatus::PENDING;
    }

    public function isVerified(): bool
    {
        return $this->status === VerificationStatus::VERIFIED;
    }

    public function isRejected(): bool
    {
        return $this->status === VerificationStatus::REJECTED;
    }

    public function canBeModified(): bool
    {
        return $this->status === VerificationStatus::PENDING;
    }

    public function getRequiredDocuments(): array
    {
        return $this->verificationType->getRequiredDocuments();
    }

    public function hasAllRequiredDocuments(): bool
    {
        $required = $this->getRequiredDocuments();
        $uploaded = $this->documents->map(fn($doc) => $doc->getDocumentType())->toArray();
        
        foreach ($required as $requiredType) {
            if (!in_array($requiredType, $uploaded)) {
                return false;
            }
        }
        
        return true;
    }

    public function getCompletionPercentage(): int
    {
        $required = $this->getRequiredDocuments();
        $uploaded = $this->documents->map(fn($doc) => $doc->getDocumentType())->toArray();
        
        if (empty($required)) {
            return 100;
        }
        
        $completed = 0;
        foreach ($required as $requiredType) {
            if (in_array($requiredType, $uploaded)) {
                $completed++;
            }
        }
        
        return (int) ($completed / count($required) * 100);
    }

    public function getDaysWaiting(): int
    {
        return $this->submittedAt->diff(new \DateTimeImmutable())->days;
    }

    public function getProcessingTime(): ?int
    {
        if (!$this->verifiedAt) {
            return null;
        }
        
        return $this->submittedAt->diff($this->verifiedAt)->days;
    }

    public function markAsVerified(User $verifiedBy, ?string $comments = null): void
    {
        $this->setStatus(VerificationStatus::VERIFIED);
        $this->setVerifiedBy($verifiedBy);
        $this->setComments($comments);
        $this->setVerifiedAt(new \DateTimeImmutable());
        
        // Update user verification status if this is identity verification
        if ($this->verificationType === VerificationType::IDENTITY) {
            $this->user->setIsVerified(true);
            $this->user->setVerificationStatus(VerificationStatus::VERIFIED);
        }
    }

    public function markAsRejected(User $verifiedBy, string $rejectionReason, ?string $comments = null): void
    {
        $this->setStatus(VerificationStatus::REJECTED);
        $this->setVerifiedBy($verifiedBy);
        $this->setRejectionReason($rejectionReason);
        $this->setComments($comments);
        $this->setVerifiedAt(new \DateTimeImmutable());
        
        // Update user verification status if this is identity verification
        if ($this->verificationType === VerificationType::IDENTITY) {
            $this->user->setIsVerified(false);
            $this->user->setVerificationStatus(VerificationStatus::REJECTED);
        }
    }

    public function __toString(): string
    {
        return $this->verificationType->getLabel() . ' - ' . $this->user->getFullName();
    }
}