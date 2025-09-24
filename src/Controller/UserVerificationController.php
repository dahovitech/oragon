<?php

namespace App\Controller;

use App\Entity\AccountVerification;
use App\Entity\VerificationDocument;
use App\Form\AccountVerificationType;
use App\Repository\AccountVerificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/verification')]
#[IsGranted('ROLE_USER')]
class UserVerificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountVerificationRepository $accountVerificationRepository,
        private SluggerInterface $slugger
    ) {
    }

    #[Route('/', name: 'user_verification_index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        
        // Get existing verifications for this user
        $existingVerifications = $this->accountVerificationRepository->findByUser($user);
        
        // Create new verification form
        $accountVerification = new AccountVerification();
        $accountVerification->setUser($user);
        
        $form = $this->createForm(AccountVerificationType::class, $accountVerification);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Save the verification entity first
                $this->entityManager->persist($accountVerification);
                
                // Handle file uploads
                $this->handleFileUploads($form, $accountVerification);
                
                $this->entityManager->flush();

                $this->addFlash('success', 'Votre demande de vérification a été soumise avec succès. Nous examinerons vos documents dans les plus brefs délais.');
                
                return $this->redirectToRoute('user_verification_index');
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la soumission de votre demande. Veuillez réessayer.');
            }
        }

        return $this->render('user_verification/index.html.twig', [
            'form' => $form,
            'existingVerifications' => $existingVerifications,
            'user' => $user,
        ]);
    }

    #[Route('/status', name: 'user_verification_status')]
    public function status(): Response
    {
        $user = $this->getUser();
        $verifications = $this->accountVerificationRepository->findByUser($user);

        return $this->render('user_verification/status.html.twig', [
            'verifications' => $verifications,
            'user' => $user,
        ]);
    }

    private function handleFileUploads($form, AccountVerification $accountVerification): void
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/verification';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0755, true);
        }

        // Handle different types of documents
        $documentTypes = [
            'identityDocuments' => 'ID_CARD',
            'addressDocuments' => 'PROOF_ADDRESS',
            'incomeDocuments' => 'PROOF_INCOME',
            'businessDocuments' => 'BUSINESS_REGISTRATION'
        ];

        foreach ($documentTypes as $formField => $documentType) {
            $files = $form->get($formField)->getData();
            
            if ($files) {
                foreach ($files as $file) {
                    if ($file) {
                        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $this->slugger->slug($originalFilename);
                        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                        try {
                            $file->move($uploadDirectory, $newFilename);
                            
                            // Create VerificationDocument entity
                            $verificationDocument = new VerificationDocument();
                            $verificationDocument->setAccountVerification($accountVerification);
                            $verificationDocument->setDocumentType($documentType);
                            $verificationDocument->setFileName($newFilename);
                            $verificationDocument->setOriginalName($file->getClientOriginalName());
                            $verificationDocument->setFilePath('/uploads/verification/' . $newFilename);
                            
                            $this->entityManager->persist($verificationDocument);
                            $accountVerification->addDocument($verificationDocument);
                            
                        } catch (FileException $e) {
                            throw new \Exception('Erreur lors du téléchargement du fichier: ' . $originalFilename);
                        }
                    }
                }
            }
        }
    }
}