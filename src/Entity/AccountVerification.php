<?php

namespace App\Entity;

use App\Enum\DocumentType;
use App\Enum\VerificationStatus;
use App\Repository\AccountVerificationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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

    #[ORM\Column(type: 'string', enumType: DocumentType::class)]
    private DocumentType $verificationType;

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

    public function getVerificationType(): DocumentType
    {
        return $this->verificationType;
    }

    public function setVerificationType(DocumentType $verificationType): static
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

    public function hasAllRequiredDocuments(): bool
    {
        // Simplification: vérifier qu'il y a au moins un document
        return $this->documents->count() > 0;
    }

    public function getCompletionPercentage(): int
    {
        // Simplification: basé sur la présence de documents
        return $this->documents->count() > 0 ? 100 : 0;
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
        if ($this->verificationType === DocumentType::ID_CARD) {
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
        if ($this->verificationType === DocumentType::ID_CARD) {
            $this->user->setIsVerified(false);
            $this->user->setVerificationStatus(VerificationStatus::REJECTED);
        }
    }

    public function __toString(): string
    {
        $type_label = match($this->verificationType) {
            DocumentType::ID_CARD => 'Pièce d\'identité',
            DocumentType::PROOF_INCOME => 'Justificatif de revenus',
            DocumentType::BANK_STATEMENT => 'Relevé bancaire',
            DocumentType::BUSINESS_REGISTRATION => 'Extrait Kbis',
            DocumentType::PROOF_ADDRESS => 'Justificatif de domicile',
            DocumentType::TAX_RETURN => 'Avis d\'imposition',
            DocumentType::PAYSLIP => 'Bulletin de salaire',
            default => 'Document',
        };
        
        return $type_label . ' - ' . $this->user->getFirstName() . ' ' . $this->user->getLastName();
    }
}