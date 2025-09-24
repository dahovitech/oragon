<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[Vich\Uploadable]
class Document
{
    public const TYPE_IDENTITY_CARD = 'identity_card';
    public const TYPE_PASSPORT = 'passport';
    public const TYPE_DRIVING_LICENSE = 'driving_license';
    public const TYPE_PROOF_OF_ADDRESS = 'proof_of_address';
    public const TYPE_INCOME_PROOF = 'income_proof';
    public const TYPE_BANK_STATEMENT = 'bank_statement';
    public const TYPE_EMPLOYMENT_CONTRACT = 'employment_contract';
    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[Vich\UploadableField(mapping: 'documents', fileNameProperty: 'filename', size: 'fileSize')]
    private ?File $file = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $uploadedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $verifiedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?User $verifiedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): static
    {
        $this->file = $file;

        if (null !== $file) {
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;

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

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getUploadedAt(): ?\DateTimeInterface
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeInterface $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeInterface
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeInterface $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

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

    public function getVerifiedBy(): ?User
    {
        return $this->verifiedBy;
    }

    public function setVerifiedBy(?User $verifiedBy): static
    {
        $this->verifiedBy = $verifiedBy;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDocumentTypes(): array
    {
        return [
            'Carte d\'identité' => self::TYPE_IDENTITY_CARD,
            'Passeport' => self::TYPE_PASSPORT,
            'Permis de conduire' => self::TYPE_DRIVING_LICENSE,
            'Justificatif de domicile' => self::TYPE_PROOF_OF_ADDRESS,
            'Justificatif de revenus' => self::TYPE_INCOME_PROOF,
            'Relevé bancaire' => self::TYPE_BANK_STATEMENT,
            'Contrat de travail' => self::TYPE_EMPLOYMENT_CONTRACT,
            'Autre' => self::TYPE_OTHER,
        ];
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'badge-warning',
            self::STATUS_APPROVED => 'badge-success',
            self::STATUS_REJECTED => 'badge-danger',
            self::STATUS_EXPIRED => 'badge-secondary',
            default => 'badge-secondary',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_APPROVED => 'Approuvé',
            self::STATUS_REJECTED => 'Rejeté',
            self::STATUS_EXPIRED => 'Expiré',
            default => 'Inconnu',
        };
    }

    public function getTypeLabel(): string
    {
        $types = $this->getDocumentTypes();
        return array_search($this->type, $types) ?: 'Type inconnu';
    }

    public function isExpired(): bool
    {
        if (!$this->expiresAt) {
            return false;
        }

        return $this->expiresAt < new \DateTime();
    }
}