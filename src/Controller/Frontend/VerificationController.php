<?php

namespace App\Controller\Frontend;

use App\Entity\AccountVerification;
use App\Entity\VerificationDocument;
use App\Form\AccountVerificationFormType;
use App\Enum\VerificationStatus;
use App\Service\FileUploadService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/verification', name: 'app_verification_')]
#[IsGranted('ROLE_USER')]
class VerificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileUploadService $fileUploadService,
        private NotificationService $notificationService
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Récupérer les vérifications existantes
        $verifications = $this->entityManager
            ->getRepository(AccountVerification::class)
            ->findBy(['user' => $user], ['submittedAt' => 'DESC']);

        return $this->render('frontend/verification/index.html.twig', [
            'verifications' => $verifications,
            'user' => $user
        ]);
    }

    #[Route('/nouvelle', name: 'new')]
    public function new(Request $request): Response
    {
        $verification = new AccountVerification();
        $verification->setUser($this->getUser());
        $verification->setStatus(VerificationStatus::PENDING);
        $verification->setSubmittedAt(new \DateTime());

        $form = $this->createForm(AccountVerificationFormType::class, $verification);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traitement des fichiers uploadés
            $uploadedFiles = $form->get('documents')->getData();
            
            if ($uploadedFiles) {
                foreach ($uploadedFiles as $uploadedFile) {
                    $fileName = $this->fileUploadService->upload($uploadedFile, 'verification');
                    
                    $document = new VerificationDocument();
                    $document->setFileName($fileName);
                    $document->setOriginalName($uploadedFile->getClientOriginalName());
                    $document->setFilePath('uploads/verification/' . $fileName);
                    $document->setUploadedAt(new \DateTime());
                    $document->setDocumentType($verification->getVerificationType());
                    
                    $verification->addDocument($document);
                    $this->entityManager->persist($document);
                }
            }

            $this->entityManager->persist($verification);
            $this->entityManager->flush();

            // Notification
            $this->notificationService->sendVerificationSubmitted($verification);

            $this->addFlash('success', 'Votre demande de vérification a été soumise avec succès. Vous recevrez une notification une fois le traitement effectué.');

            return $this->redirectToRoute('app_verification_index');
        }

        return $this->render('frontend/verification/new.html.twig', [
            'form' => $form,
            'verification' => $verification
        ]);
    }

    #[Route('/detail/{id}', name: 'detail')]
    public function detail(AccountVerification $verification): Response
    {
        // Vérifier que l'utilisateur peut voir cette vérification
        if ($verification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('frontend/verification/detail.html.twig', [
            'verification' => $verification
        ]);
    }

    #[Route('/statut', name: 'status')]
    public function status(): Response
    {
        $user = $this->getUser();
        
        // Calculer le pourcentage de vérification
        $totalVerifications = $this->entityManager
            ->getRepository(AccountVerification::class)
            ->count(['user' => $user]);

        $verifiedCount = $this->entityManager
            ->getRepository(AccountVerification::class)
            ->count(['user' => $user, 'status' => VerificationStatus::VERIFIED]);

        $verificationProgress = $totalVerifications > 0 ? 
            round(($verifiedCount / $totalVerifications) * 100) : 0;

        return $this->render('frontend/verification/status.html.twig', [
            'user' => $user,
            'verificationProgress' => $verificationProgress,
            'totalVerifications' => $totalVerifications,
            'verifiedCount' => $verifiedCount
        ]);
    }
}