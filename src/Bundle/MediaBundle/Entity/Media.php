<?php

namespace App\Bundle\MediaBundle\Entity;

use App\Bundle\MediaBundle\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'media')]
#[ORM\HasLifecycleCallbacks]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $alt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $extension = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    private ?File $file = null;
    private ?string $tempFilename = null;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): static
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): static
    {
        $this->alt = $alt;
        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(?string $extension): static
    {
        $this->extension = $extension;
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

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;
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

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): static
    {
        $this->file = $file;

        if (null !== $this->fileName) {
            $this->tempFilename = $this->fileName;
            $this->fileName = null;
            $this->alt = null;
            $this->extension = null;
            $this->mimeType = null;
            $this->fileSize = null;
        }

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function preUpload(): void
    {
        if (null === $this->file) {
            return;
        }

        $extension = $this->file->guessExtension();
        if ($extension) {
            $this->fileName = uniqid() . '.' . $extension;
            $this->extension = $extension;
        }

        $this->alt = $this->alt ?: $this->file->getClientOriginalName();
        $this->mimeType = $this->file->getMimeType();
        $this->fileSize = $this->file->getSize();
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    public function upload(): void
    {
        if (null === $this->file) {
            return;
        }

        // Delete old file if it exists
        if (null !== $this->tempFilename) {
            $oldFile = $this->getUploadRootDir() . DIRECTORY_SEPARATOR . $this->tempFilename;
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        $this->file->move($this->getUploadRootDir(), $this->fileName);
        $this->file = null;
    }

    #[ORM\PreRemove]
    public function preRemoveUpload(): void
    {
        $this->tempFilename = $this->getUploadRootDir() . DIRECTORY_SEPARATOR . $this->fileName;
    }

    #[ORM\PostRemove]
    public function removeUpload(): void
    {
        if ($this->tempFilename && file_exists($this->tempFilename)) {
            unlink($this->tempFilename);
        }
    }

    public function getUploadDir(): string
    {
        return 'upload/media';
    }

    protected function getUploadRootDir(): string
    {
        return __DIR__ . '/../../../../public/' . $this->getUploadDir();
    }

    public function getWebPath(): string
    {
        return $this->getUploadDir() . '/' . $this->fileName;
    }

    public function getAbsolutePath(): string
    {
        return $this->getUploadRootDir() . DIRECTORY_SEPARATOR . $this->fileName;
    }

    public function isImage(): bool
    {
        return in_array($this->extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
    }

    public function isVideo(): bool
    {
        return in_array($this->extension, ['mp4', 'avi', 'mov', 'wmv', 'flv'], true);
    }

    public function isAudio(): bool
    {
        return in_array($this->extension, ['mp3', 'wav', 'flac', 'aac'], true);
    }

    public function getFormattedFileSize(): string
    {
        if (!$this->fileSize) {
            return 'Unknown';
        }

        $bytes = $this->fileSize;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function __toString(): string
    {
        return $this->alt ?: $this->fileName ?: 'Media #' . $this->id;
    }
}