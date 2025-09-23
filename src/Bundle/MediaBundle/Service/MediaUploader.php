<?php

namespace App\Bundle\MediaBundle\Service;

use App\Bundle\MediaBundle\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class MediaUploader
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private string $uploadDirectory
    ) {}

    /**
     * Upload a file and create a Media entity
     */
    public function upload(UploadedFile $file, ?string $alt = null): Media
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Ensure upload directory exists
        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }

        $file->move($this->uploadDirectory, $fileName);

        $media = new Media();
        $media->setFileName($fileName);
        $media->setAlt($alt ?: $originalFilename);
        $media->setExtension($file->guessExtension());
        $media->setMimeType($file->getMimeType());
        $media->setFileSize($file->getSize());

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    /**
     * Upload multiple files at once
     */
    public function uploadMultiple(array $files, array $alts = []): array
    {
        $mediaEntities = [];
        
        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                $alt = $alts[$index] ?? null;
                $mediaEntities[] = $this->upload($file, $alt);
            }
        }

        return $mediaEntities;
    }

    /**
     * Delete a media file and entity
     */
    public function delete(Media $media): void
    {
        $filePath = $this->uploadDirectory . '/' . $media->getFileName();
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($media);
        $this->entityManager->flush();
    }

    /**
     * Get file size for a media entity
     */
    public function getFileSize(Media $media): int
    {
        $filePath = $this->uploadDirectory . '/' . $media->getFileName();
        return file_exists($filePath) ? filesize($filePath) : 0;
    }

    /**
     * Check if file exists
     */
    public function fileExists(Media $media): bool
    {
        $filePath = $this->uploadDirectory . '/' . $media->getFileName();
        return file_exists($filePath);
    }

    /**
     * Get absolute path to media file
     */
    public function getAbsolutePath(Media $media): string
    {
        return $this->uploadDirectory . '/' . $media->getFileName();
    }

    /**
     * Generate thumbnail for images (placeholder for future implementation)
     */
    public function generateThumbnail(Media $media, int $width = 150, int $height = 150): ?string
    {
        if (!$media->isImage()) {
            return null;
        }

        // Placeholder for thumbnail generation logic
        // This could be implemented using imagine/imagine or intervention/image
        return null;
    }

    /**
     * Validate uploaded file
     */
    public function validateFile(UploadedFile $file, array $allowedTypes = []): array
    {
        $errors = [];

        // Check if file upload was successful
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed';
            return $errors;
        }

        // Check file size (10MB limit)
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file->getSize() > $maxSize) {
            $errors[] = 'File size exceeds 10MB limit';
        }

        // Check allowed file types
        if (!empty($allowedTypes)) {
            $extension = $file->guessExtension();
            if (!in_array($extension, $allowedTypes)) {
                $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes);
            }
        }

        // Check MIME type for security
        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'video/mp4', 'video/avi', 'video/quicktime',
            'audio/mpeg', 'audio/wav', 'audio/flac',
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain', 'text/csv'
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            $errors[] = 'File type not allowed for security reasons';
        }

        return $errors;
    }

    /**
     * Get upload statistics
     */
    public function getUploadStatistics(): array
    {
        $uploadDir = $this->uploadDirectory;
        $totalSize = 0;
        $fileCount = 0;

        if (is_dir($uploadDir)) {
            $files = scandir($uploadDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $uploadDir . '/' . $file;
                    if (is_file($filePath)) {
                        $totalSize += filesize($filePath);
                        $fileCount++;
                    }
                }
            }
        }

        return [
            'fileCount' => $fileCount,
            'totalSize' => $totalSize,
            'formattedSize' => $this->formatBytes($totalSize),
            'uploadDirectory' => $uploadDir
        ];
    }

    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}