<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class DocumentService
{
    private EntityManagerInterface $entityManager;
    private DocumentRepository $documentRepository;
    private SluggerInterface $slugger;
    private NotificationService $notificationService;

    // Taille maximum des fichiers (en bytes): 10MB
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;
    
    // Extensions autorisées
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public function __construct(
        EntityManagerInterface $entityManager,
        DocumentRepository $documentRepository,
        SluggerInterface $slugger,
        NotificationService $notificationService
    ) {
        $this->entityManager = $entityManager;
        $this->documentRepository = $documentRepository;
        $this->slugger = $slugger;
        $this->notificationService = $notificationService;
    }

    public function uploadDocument(User $user, UploadedFile $file, string $type, string $name = null): Document
    {
        $this->validateFile($file);

        $document = new Document();
        $document->setUser($user);
        $document->setType($type);
        $document->setName($name ?: $this->generateDocumentName($type));
        $document->setFile($file);
        $document->setStatus(Document::STATUS_PENDING);

        // Définir la date d'expiration en fonction du type de document
        $this->setExpirationDate($document);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        // Envoyer une notification à l'utilisateur
        $this->notificationService->sendDocumentUploaded($document);

        // Notifier les administrateurs qu'un nouveau document nécessite une vérification
        $this->notificationService->sendDocumentPendingReview($document);

        return $document;
    }

    public function approveDocument(Document $document, User $verifiedBy, string $reason = null): void
    {
        $document->setStatus(Document::STATUS_APPROVED);
        $document->setVerifiedBy($verifiedBy);
        $document->setVerifiedAt(new \DateTime());
        
        if ($reason) {
            $document->setRejectionReason($reason); // Peut servir de commentaire
        }

        $this->entityManager->flush();

        // Notifier l'utilisateur
        $this->notificationService->sendDocumentApproved($document);

        // Vérifier si l'utilisateur a complété son KYC
        $this->checkKycCompletion($document->getUser());
    }

    public function rejectDocument(Document $document, User $verifiedBy, string $reason): void
    {
        $document->setStatus(Document::STATUS_REJECTED);
        $document->setVerifiedBy($verifiedBy);
        $document->setVerifiedAt(new \DateTime());
        $document->setRejectionReason($reason);

        $this->entityManager->flush();

        // Notifier l'utilisateur
        $this->notificationService->sendDocumentRejected($document);
    }

    public function deleteDocument(Document $document): void
    {
        $this->entityManager->remove($document);
        $this->entityManager->flush();
    }

    public function getDocumentsByUser(User $user): array
    {
        return $this->documentRepository->findByUser($user);
    }

    public function getDocumentsByUserAndType(User $user, string $type): array
    {
        return $this->documentRepository->findByUserAndType($user, $type);
    }

    public function getPendingDocuments(): array
    {
        return $this->documentRepository->findPendingDocuments();
    }

    public function getDocumentStats(): array
    {
        return $this->documentRepository->getDocumentStats();
    }

    public function getUserKycProgress(User $user): array
    {
        return $this->documentRepository->getUserKycProgress($user);
    }

    public function isKycComplete(User $user): bool
    {
        $kycProgress = $this->getUserKycProgress($user);
        return $kycProgress['is_complete'];
    }

    public function markExpiredDocuments(): int
    {
        $expiredDocuments = $this->documentRepository->findExpiredDocuments();
        $count = 0;

        foreach ($expiredDocuments as $document) {
            $document->setStatus(Document::STATUS_EXPIRED);
            $count++;

            // Notifier l'utilisateur
            $this->notificationService->sendDocumentExpired($document);
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    private function validateFile(UploadedFile $file): void
    {
        // Vérifier la taille du fichier
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                sprintf('Le fichier est trop volumineux. Taille maximum autorisée: %s MB', self::MAX_FILE_SIZE / (1024 * 1024))
            );
        }

        // Vérifier l'extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new \InvalidArgumentException(
                sprintf('Extension de fichier non autorisée. Extensions acceptées: %s', implode(', ', self::ALLOWED_EXTENSIONS))
            );
        }

        // Vérifier le type MIME
        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png'
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new \InvalidArgumentException('Type de fichier non autorisé.');
        }
    }

    private function generateDocumentName(string $type): string
    {
        $typeLabels = [
            Document::TYPE_IDENTITY_CARD => 'Carte d\'identité',
            Document::TYPE_PASSPORT => 'Passeport',
            Document::TYPE_DRIVING_LICENSE => 'Permis de conduire',
            Document::TYPE_PROOF_OF_ADDRESS => 'Justificatif de domicile',
            Document::TYPE_INCOME_PROOF => 'Justificatif de revenus',
            Document::TYPE_BANK_STATEMENT => 'Relevé bancaire',
            Document::TYPE_EMPLOYMENT_CONTRACT => 'Contrat de travail',
            Document::TYPE_OTHER => 'Autre document',
        ];

        return $typeLabels[$type] ?? 'Document';
    }

    private function setExpirationDate(Document $document): void
    {
        $expirationMonths = [
            Document::TYPE_IDENTITY_CARD => 120, // 10 ans
            Document::TYPE_PASSPORT => 120, // 10 ans
            Document::TYPE_DRIVING_LICENSE => 180, // 15 ans
            Document::TYPE_PROOF_OF_ADDRESS => 3, // 3 mois
            Document::TYPE_INCOME_PROOF => 6, // 6 mois
            Document::TYPE_BANK_STATEMENT => 6, // 6 mois
            Document::TYPE_EMPLOYMENT_CONTRACT => 12, // 1 an
        ];

        if (isset($expirationMonths[$document->getType()])) {
            $expirationDate = new \DateTime();
            $expirationDate->add(new \DateInterval('P' . $expirationMonths[$document->getType()] . 'M'));
            $document->setExpiresAt($expirationDate);
        }
    }

    private function checkKycCompletion(User $user): void
    {
        if ($this->isKycComplete($user)) {
            // Mettre à jour le statut de vérification de l'utilisateur
            if (!$user->isVerified()) {
                $user->setIsVerified(true);
                $user->setVerificationStatus(\App\Enum\VerificationStatus::VERIFIED);
                $this->entityManager->flush();

                // Envoyer une notification de KYC complété
                $this->notificationService->sendKycCompleted($user);
            }
        }
    }

    public function getDocumentTypeRequirements(): array
    {
        return [
            Document::TYPE_IDENTITY_CARD => [
                'required' => true,
                'description' => 'Carte d\'identité ou passeport valide',
                'formats' => ['PDF', 'JPG', 'PNG'],
                'max_size' => '10 MB'
            ],
            Document::TYPE_PROOF_OF_ADDRESS => [
                'required' => true,
                'description' => 'Justificatif de domicile de moins de 3 mois',
                'formats' => ['PDF', 'JPG', 'PNG'],
                'max_size' => '10 MB'
            ],
            Document::TYPE_INCOME_PROOF => [
                'required' => true,
                'description' => 'Justificatif de revenus (bulletin de salaire, avis d\'imposition)',
                'formats' => ['PDF', 'JPG', 'PNG'],
                'max_size' => '10 MB'
            ],
            Document::TYPE_BANK_STATEMENT => [
                'required' => false,
                'description' => 'Relevé bancaire des 3 derniers mois',
                'formats' => ['PDF', 'JPG', 'PNG'],
                'max_size' => '10 MB'
            ],
            Document::TYPE_EMPLOYMENT_CONTRACT => [
                'required' => false,
                'description' => 'Contrat de travail ou attestation employeur',
                'formats' => ['PDF', 'JPG', 'PNG'],
                'max_size' => '10 MB'
            ]
        ];
    }
}