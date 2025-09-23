<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    public function __construct(
        private string $uploadsDirectory,
        private SluggerInterface $slugger
    ) {}

    public function upload(UploadedFile $file, string $directory = ''): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $targetDirectory = $this->uploadsDirectory;
        if ($directory) {
            $targetDirectory .= '/' . $directory;
        }

        // Créer le répertoire s'il n'existe pas
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        try {
            $file->move($targetDirectory, $fileName);
        } catch (FileException $e) {
            throw new \RuntimeException('Erreur lors du téléchargement du fichier: ' . $e->getMessage());
        }

        return $fileName;
    }

    public function delete(string $filePath): bool
    {
        $fullPath = $this->uploadsDirectory . '/' . $filePath;
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }

    public function getFileUrl(string $filePath): string
    {
        return '/uploads/' . $filePath;
    }

    public function getFileSize(string $filePath): int
    {
        $fullPath = $this->uploadsDirectory . '/' . $filePath;
        
        if (file_exists($fullPath)) {
            return filesize($fullPath);
        }
        
        return 0;
    }

    public function validateFile(UploadedFile $file, array $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'], int $maxSize = 10485760): array
    {
        $errors = [];
        
        // Vérifier la taille
        if ($file->getSize() > $maxSize) {
            $errors[] = 'Le fichier est trop volumineux. Taille maximale autorisée: ' . ($maxSize / 1024 / 1024) . 'MB';
        }
        
        // Vérifier le type
        $extension = strtolower($file->guessExtension());
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = 'Type de fichier non autorisé. Types acceptés: ' . implode(', ', $allowedTypes);
        }
        
        return $errors;
    }
}