<?php

namespace App\Entity;

use App\Enum\DocumentType;
use App\Repository\VerificationDocumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VerificationDocumentRepository::class)]
#[ORM\Table(name: 'verification_documents')]
#[ORM\HasLifecycleCallbacks]
class VerificationDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    private AccountVerification $accountVerification;

    #[ORM\Column(type: 'string', enumType: DocumentType::class)]
    private DocumentType $documentType;

    #[ORM\Column(length: 255)]
    private string $fileName;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 500)]
    private string $filePath;

    #[ORM\Column(type: 'integer')]
    private int $fileSize; // en bytes

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $verifiedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $verificationNotes = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccountVerification(): AccountVerification
    {
        return $this->accountVerification;
    }

    public function setAccountVerification(AccountVerification $accountVerification): static
    {
        $this->accountVerification = $accountVerification;
        return $this;
    }

    public function getDocumentType(): DocumentType
    {
        return $this->documentType;
    }

    public function setDocumentType(DocumentType $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;
        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        
        // Auto-set verification timestamp
        if ($isVerified && !$this->verifiedAt) {
            $this->verifiedAt = new \DateTimeImmutable();
        }
        
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

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;
        return $this;
    }

    public function getVerificationNotes(): ?string
    {
        return $this->verificationNotes;
    }

    public function setVerificationNotes(?string $verificationNotes): static
    {
        $this->verificationNotes = $verificationNotes;
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

    // Méthodes utilitaires (similaires à LoanDocument)
    public function getFileExtension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function getFormattedFileSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->fileSize;
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 2) . ' ' . $units[$unit];
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    public function getDownloadUrl(): string
    {
        return '/uploads/verification-documents/' . $this->fileName;
    }

    public function getAbsolutePath(): string
    {
        return $this->filePath;
    }

    public function exists(): bool
    {
        return file_exists($this->filePath);
    }

    public function isValidMimeType(): bool
    {
        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/jpg'
        ];
        
        return in_array($this->mimeType, $allowedMimes);
    }

    public function isValidSize(): bool
    {
        $maxSize = $this->documentType->getMaxFileSize();
        return $this->fileSize <= $maxSize;
    }

    public function isValidExtension(): bool
    {
        $allowedExtensions = $this->documentType->getAllowedExtensions();
        return in_array($this->getFileExtension(), $allowedExtensions);
    }

    public function isValid(): bool
    {
        return $this->isValidMimeType() && $this->isValidSize() && $this->isValidExtension() && $this->exists();
    }

    public function getStatusBadge(): string
    {
        if (!$this->isActive) {
            return '<span class="badge bg-secondary">Supprimé</span>';
        }
        
        if ($this->isVerified) {
            return '<span class="badge bg-success">Vérifié</span>';
        }
        
        return '<span class="badge bg-warning">En attente</span>';
    }

    public function markAsVerified(User $verifiedBy, ?string $notes = null): void
    {
        $this->setIsVerified(true);
        $this->setVerifiedBy($verifiedBy);
        $this->setVerificationNotes($notes);
        $this->setVerifiedAt(new \DateTimeImmutable());
    }

    public function __toString(): string
    {
        return $this->originalName;
    }
}