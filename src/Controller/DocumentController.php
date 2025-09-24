<?php

namespace App\Controller;

use App\Entity\Document;
use App\Form\DocumentType;
use App\Form\DocumentVerificationType;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/documents')]
#[IsGranted('ROLE_USER')]
class DocumentController extends AbstractController
{
    private DocumentService $documentService;
    private EntityManagerInterface $entityManager;

    public function __construct(DocumentService $documentService, EntityManagerInterface $entityManager)
    {
        $this->documentService = $documentService;
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_documents_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $documents = $this->documentService->getDocumentsByUser($user);
        $kycProgress = $this->documentService->getUserKycProgress($user);
        $requirements = $this->documentService->getDocumentTypeRequirements();

        return $this->render('documents/index.html.twig', [
            'documents' => $documents,
            'kyc_progress' => $kycProgress,
            'requirements' => $requirements,
        ]);
    }

    #[Route('/upload', name: 'app_documents_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        $document = new Document();
        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $uploadedFile = $form->get('file')->getData();
                $type = $form->get('type')->getData();
                $name = $form->get('name')->getData();

                $this->documentService->uploadDocument(
                    $this->getUser(),
                    $uploadedFile,
                    $type,
                    $name
                );

                $this->addFlash('success', 'Document uploadé avec succès ! Il sera vérifié sous 24-48h.');

                return $this->redirectToRoute('app_documents_index');

            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de l\'upload du fichier: ' . $e->getMessage());
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer.');
            }
        }

        return $this->render('documents/upload.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/view', name: 'app_documents_view', methods: ['GET'])]
    public function view(Document $document): Response
    {
        // Vérifier que l'utilisateur peut voir ce document
        if ($document->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à voir ce document.');
        }

        return $this->render('documents/view.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_documents_delete', methods: ['POST'])]
    public function delete(Document $document, Request $request): Response
    {
        // Vérifier que l'utilisateur peut supprimer ce document
        if ($document->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce document.');
        }

        // Vérifier le token CSRF
        if ($this->isCsrfTokenValid('delete_document_'.$document->getId(), $request->request->get('_token'))) {
            try {
                $this->documentService->deleteDocument($document);
                $this->addFlash('success', 'Document supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression du document.');
            }
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_documents_index');
    }

    #[Route('/kyc-status', name: 'app_documents_kyc_status', methods: ['GET'])]
    public function kycStatus(): Response
    {
        $user = $this->getUser();
        $kycProgress = $this->documentService->getUserKycProgress($user);
        $documents = $this->documentService->getDocumentsByUser($user);

        return $this->render('documents/kyc_status.html.twig', [
            'kyc_progress' => $kycProgress,
            'documents' => $documents,
        ]);
    }

    // ========================================
    // ROUTES ADMINISTRATEUR
    // ========================================

    #[Route('/admin', name: 'app_documents_admin_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminIndex(): Response
    {
        $pendingDocuments = $this->documentService->getPendingDocuments();
        $stats = $this->documentService->getDocumentStats();

        return $this->render('documents/admin/index.html.twig', [
            'pending_documents' => $pendingDocuments,
            'stats' => $stats,
        ]);
    }

    #[Route('/admin/{id}/verify', name: 'app_documents_admin_verify', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminVerify(Document $document, Request $request): Response
    {
        $form = $this->createForm(DocumentVerificationType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $status = $form->get('status')->getData();
                $reason = $form->get('rejectionReason')->getData();

                if ($status === Document::STATUS_REJECTED && empty($reason)) {
                    $this->addFlash('error', 'Une raison de rejet est obligatoire pour rejeter un document.');
                } else {
                    if ($status === Document::STATUS_APPROVED) {
                        $this->documentService->approveDocument($document, $this->getUser(), $reason);
                        $this->addFlash('success', 'Document approuvé avec succès.');
                    } elseif ($status === Document::STATUS_REJECTED) {
                        $this->documentService->rejectDocument($document, $this->getUser(), $reason);
                        $this->addFlash('success', 'Document rejeté avec succès.');
                    } else {
                        // Statut en attente, juste sauvegarder
                        $document->setRejectionReason($reason);
                        $this->entityManager->flush();
                        $this->addFlash('success', 'Document mis à jour avec succès.');
                    }

                    return $this->redirectToRoute('app_documents_admin_index');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la vérification du document.');
            }
        }

        return $this->render('documents/admin/verify.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/admin/all', name: 'app_documents_admin_all', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminAll(Request $request): Response
    {
        $status = $request->query->get('status');
        $type = $request->query->get('type');

        // Pour simplifier, on récupère tous les documents et on les filtre côté template
        // En production, il serait mieux de filtrer côté base de données
        $qb = $this->entityManager->getRepository(Document::class)->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')
            ->addSelect('u')
            ->orderBy('d.uploadedAt', 'DESC');

        if ($status) {
            $qb->andWhere('d.status = :status')
               ->setParameter('status', $status);
        }

        if ($type) {
            $qb->andWhere('d.type = :type')
               ->setParameter('type', $type);
        }

        $documents = $qb->getQuery()->getResult();

        return $this->render('documents/admin/all.html.twig', [
            'documents' => $documents,
            'current_status' => $status,
            'current_type' => $type,
        ]);
    }

    #[Route('/admin/stats', name: 'app_documents_admin_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminStats(): Response
    {
        $stats = $this->documentService->getDocumentStats();
        
        $repository = $this->entityManager->getRepository(Document::class);
        $typeStats = $repository->getDocumentStatsByType();

        return $this->render('documents/admin/stats.html.twig', [
            'stats' => $stats,
            'type_stats' => $typeStats,
        ]);
    }
}